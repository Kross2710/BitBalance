<?php
// dashboard/handlers/story_data.php
require_once __DIR__ . '/../../include/init.php';
require_once __DIR__ . '/../../include/handlers/achievements.php';

header('Content-Type: application/json');

// Check Login
if (!isset($_SESSION['user'])) {
    echo json_encode(['ok' => false, 'error' => 'Unauthorized access']);
    exit();
}

$userId = (int) $_SESSION['user']['user_id'];
$lang = isset($_GET['lang']) && $_GET['lang'] === 'vi' ? 'vi' : 'en';

// Calculate the current week and year
$weekYear = date('W-Y');

try {
    // 1. Check database cache first
    $cacheStmt = $pdo->prepare(
        "SELECT generated_json FROM weekly_wrapped_cache 
         WHERE user_id = ? AND week_year = ? AND lang = ? LIMIT 1"
    );
    $cacheStmt->execute([$userId, $weekYear, $lang]);
    $cachedRow = $cacheStmt->fetch(PDO::FETCH_ASSOC);

    if ($cachedRow) {
        $data = json_decode($cachedRow['generated_json'], true);
        if ($data) {
            echo json_encode(array_merge(['ok' => true, 'cached' => true], $data));
            exit();
        }
    }

    // 2. Cache miss: Fetch stats & food logs
    $achProgress = bb_achievements_progress($pdo, $userId);
    $summary = $achProgress['summary'];
    
    // Get detailed food items over the past 30 days
    $foodQuery = $pdo->prepare(
        "SELECT food_item, COUNT(*) AS count_logged
         FROM intakeLog
         WHERE user_id = ? AND date_intake >= DATE_SUB(NOW(), INTERVAL 30 DAY)
         GROUP BY food_item
         ORDER BY count_logged DESC, food_item ASC
         LIMIT 15"
    );
    $foodQuery->execute([$userId]);
    $foodsLogged = $foodQuery->fetchAll(PDO::FETCH_ASSOC) ?: [];

    // Format food list for Gemini
    $foodListStr = '';
    foreach ($foodsLogged as $food) {
        $foodListStr .= $food['food_item'] . ': ' . $food['count_logged'] . ' times, ';
    }
    $foodListStr = rtrim($foodListStr, ', ');
    if (empty($foodListStr)) {
        $foodListStr = ($lang === 'vi') ? 'Chưa có món ăn nào được ghi nhận' : 'No foods logged yet';
    }

    // Leaderboard ranking status
    $friendsCount = bb_achievement_friend_count($pdo, $userId);
    $leaderboardRank = '#1';
    if ($friendsCount > 0) {
        $weeklyLeaders = leaderboard_friends($pdo, $userId, 'weekly', 500);
        foreach ($weeklyLeaders as $idx => $leader) {
            if ((int)$leader['user_id'] === $userId) {
                $leaderboardRank = '#' . ($idx + 1);
                break;
            }
        }
    }

    // Favorite food and top badge
    $favoriteFood = 'Not enough data';
    foreach ($achProgress['records'] as $record) {
        if ($record['label'] === 'Favorite food') {
            $favoriteFood = $record['value'];
            break;
        }
    }

    // Get highest unlocked achievement name
    $topBadge = 'First Bite';
    $unlockedBadges = [];
    foreach ($achProgress['achievements'] as $ach) {
        if ($ach['level'] > 0) {
            $unlockedBadges[] = $ach;
        }
    }
    if (!empty($unlockedBadges)) {
        // Find badge with highest level
        usort($unlockedBadges, function($a, $b) {
            return $b['level'] <=> $a['level'];
        });
        $topBadge = $unlockedBadges[0]['name'];
    }

    // 3. Prompt engineering for Gemini
    if ($lang === 'vi') {
        $systemPrompt = "Bạn là trợ lý AI kể chuyện thông minh, dí dỏm của ứng dụng theo dõi calo BitBalance. Dưới đây là số liệu dinh dưỡng và thói quen ăn uống của người dùng tên là " . $_SESSION['user']['user_name'] . ":
- Cấp độ hiện tại: " . $summary['xp']['current_level'] . "
- Điểm kinh nghiệm (XP) hiện có: " . $summary['xp']['total_xp'] . " XP
- Tổng số món ăn đã ghi nhận trong tuần/tháng: " . $summary['total_foods'] . " món
- Chuỗi đăng nhập liên tục (Streak): " . $summary['current_streak'] . " ngày
- Xếp hạng bạn bè: " . $leaderboardRank . "
- Món ăn yêu thích nhất: " . $favoriteFood . "
- Danh mục huy hiệu nổi bật tuần này: " . $topBadge . "
- Danh sách tất cả các món ăn đã log trong 30 ngày qua: [" . $foodListStr . "]

Nhiệm vụ của bạn:
1. Phân tích danh sách món ăn trong 30 ngày qua để đặt cho họ một \"Hình mẫu ẩm thực\" (Dietary Archetype) thật hài hước, mang phong cách trẻ trung kiểu Việt Nam (ví dụ: \"Chiến binh ức gà nửa mùa\", \"Đại sứ bánh mì kẹp\", \"Chúa tể hảo ngọt trà sữa\").
2. Viết 1 dòng ngắn giải thích lý do hài hước cho hình mẫu này (chỉ ra sự tương phản đáng yêu, ví dụ nạp protein xây cơ nhưng vẫn log trà sữa xả cơ).
3. Viết các câu chú thích (caption) ngắn (dưới 20 từ) cực kỳ dí dỏm bằng tiếng Việt cho 4 slide Story sau:
   - slide1_aura (Hào quang tuần qua)
   - slide2_topfood (Món ăn hoặc huy hiệu nổi bật nhất)
   - slide3_streak (Chuỗi streak hoặc kỷ luật rực lửa)
   - slide4_leaderboard (Cạnh tranh xếp hạng bạn bè đầy tính tấu hài)

Bạn chỉ được trả về DUY NHẤT một đối tượng JSON thuần túy (không có định dạng markdown bọc ngoài, không có ```json wrapper), theo cấu trúc:
{
  \"diet_archetype\": \"Tên hình mẫu ẩm thực\",
  \"archetype_desc\": \"Lời giải thích hài hước\",
  \"slide1_aura\": \"Caption slide 1\",
  \"slide2_topfood\": \"Caption slide 2\",
  \"slide3_streak\": \"Caption slide 3\",
  \"slide4_leaderboard\": \"Caption slide 4\"
}";
    } else {
        $systemPrompt = "You are the witty, smart AI Storyteller of the BitBalance calorie tracking app. Here is the nutrition and eating habits data of the user named " . $_SESSION['user']['user_name'] . ":
- Current Level: " . $summary['xp']['current_level'] . "
- Total XP earned: " . $summary['xp']['total_xp'] . " XP
- Total foods logged: " . $summary['total_foods'] . " foods
- Logging streak: " . $summary['current_streak'] . " days
- Friend leaderboard rank: " . $leaderboardRank . "
- Favorite logged food: " . $favoriteFood . "
- Current Top Badge: " . $topBadge . "
- List of foods logged in the last 30 days: [" . $foodListStr . "]

Your Tasks:
1. Analyze their 30-day food list and give them a highly creative and humorous \"Dietary Archetype\" (e.g., \"Part-Time Chicken Breast Warrior\", \"Double-Agent Banh Mi Enthusiast\", \"Yin-Yang Sweet Tooth Master\").
2. Write a 1-sentence funny explanation for this archetype (pointing out any funny contrasts in their logged foods, like trying to bulk up with protein but neutralizing it with sweet desserts).
3. Write very short, engaging, and witty captions (under 20 words each) in English for 4 Story slides:
   - slide1_aura (Their weekly aura and vibe)
   - slide2_topfood (Highlighting their favorite food or top badge)
   - slide3_streak (Fueling the discipline flame or streak)
   - slide4_leaderboard (Playful teasing about friends ranking)

You must return ONLY a raw JSON object (no markdown code blocks, no ```json wrappers) with this structure:
{
  \"diet_archetype\": \"Dietary Archetype Name\",
  \"archetype_desc\": \"Funny explanation sentence\",
  \"slide1_aura\": \"Caption for slide 1\",
  \"slide2_topfood\": \"Caption for slide 2\",
  \"slide3_streak\": \"Caption for slide 3\",
  \"slide4_leaderboard\": \"Caption for slide 4\"
}";
    }

    // 4. Send request to Gemini
    $apiUrl = "https://generativelanguage.googleapis.com/v1beta/models/gemini-3.1-flash-lite:generateContent?key=" . GEMINI_API_KEY;
    $body = [
        'contents' => [
            ['parts' => [['text' => $systemPrompt]]]
        ]
    ];

    $ch = curl_init($apiUrl);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body));
    
    // SSL configurations for local XAMPP & RMIT shared hosting compatibility
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);

    $response = curl_exec($ch);

    if (curl_errno($ch)) {
        throw new Exception('Gemini cURL connection error: ' . curl_error($ch));
    }

    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

    $aiData = null;
    if ($httpCode === 200) {
        $resJson = json_decode($response, true);
        $rawText = $resJson['candidates'][0]['content']['parts'][0]['text'] ?? '';
        
        // Clean potential markdown wrappers
        $cleanText = str_replace(['```json', '```'], '', $rawText);
        $cleanText = trim($cleanText);
        
        $aiData = json_decode($cleanText, true);
    }

    // 5. Fallback if Gemini is offline or formatting fails
    if (!$aiData) {
        if ($lang === 'vi') {
            $aiData = [
                'diet_archetype' => 'Tín Đồ Ăn Uống Độc Lập',
                'archetype_desc' => 'Ghi nhận calo đầy kiên trì, kiên định chinh phục mọi đỉnh cao ẩm thực!',
                'slide1_aura' => 'Hào quang rực rỡ tỏa sáng khắp căn bếp của bạn tuần này!',
                'slide2_topfood' => 'Huy hiệu ' . $topBadge . ' đã sẵn sàng tỏa sáng trên story của bạn.',
                'slide3_streak' => 'Duy trì kỷ luật thép với chuỗi ' . $summary['current_streak'] . ' ngày streak cực cháy!',
                'slide4_leaderboard' => 'Đứng thứ ' . $leaderboardRank . ' trên bảng xếp hạng! Một vị thế vô cùng đáng gờm.'
            ];
        } else {
            $aiData = [
                'diet_archetype' => 'Dedicated Food Tracker',
                'archetype_desc' => 'Consistently logging meals and building habits every single day!',
                'slide1_aura' => 'Your nutrition aura is glowing brightly with discipline this week!',
                'slide2_topfood' => 'Your top badge ' . $topBadge . ' is ready to shine on your story.',
                'slide3_streak' => 'Keeping the fire hot with an awesome ' . $summary['current_streak'] . '-day streak!',
                'slide4_leaderboard' => 'Secured rank ' . $leaderboardRank . ' on the leaderboard. A truly formidable position!'
            ];
        }
    }

    // Save success response to cache (both original stats and generated captions)
    $finalData = array_merge([
        'user' => [
            'username' => $_SESSION['user']['user_name'],
            'level' => $summary['xp']['current_level'],
            'progress_pct' => $summary['xp']['progress_pct'],
            'total_xp' => $summary['xp']['total_xp'],
        ],
        'stats' => [
            'total_foods' => $summary['total_foods'],
            'logged_days' => $summary['logged_days'],
            'streak' => $summary['current_streak'],
            'leaderboard_rank' => $leaderboardRank,
            'favorite_food' => $favoriteFood,
        ],
        'badge' => [
            'name' => $topBadge,
            'icon' => !empty($unlockedBadges[0]['icon']) ? $unlockedBadges[0]['icon'] : 'fa-star',
            'tone' => !empty($unlockedBadges[0]['tone']) ? $unlockedBadges[0]['tone'] : 'primary'
        ]
    ], $aiData);

    $cacheInsert = $pdo->prepare(
        "INSERT INTO weekly_wrapped_cache (user_id, week_year, lang, generated_json)
         VALUES (?, ?, ?, ?)
         ON DUPLICATE KEY UPDATE generated_json = VALUES(generated_json), created_at = CURRENT_TIMESTAMP"
    );
    $cacheInsert->execute([$userId, $weekYear, $lang, json_encode($finalData)]);

    echo json_encode(array_merge(['ok' => true, 'cached' => false], $finalData));

} catch (Exception $e) {
    echo json_encode([
        'ok' => false,
        'error' => 'Database or execution error: ' . $e->getMessage()
    ]);
}
?>
