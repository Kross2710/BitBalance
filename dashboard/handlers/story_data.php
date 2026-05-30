<?php
// dashboard/handlers/story_data.php
require_once __DIR__ . '/../../include/init.php';
require_once __DIR__ . '/../../include/handlers/achievements.php';

// Bump this whenever the prompt / payload shape changes, so cached weekly
// stories from the old format are regenerated instead of served stale.
if (!defined('STORY_CACHE_VERSION')) {
    define('STORY_CACHE_VERSION', 3);
}

function refresh_spotify_token($pdo, $userId, $spotifyRow) {
    if (!defined('SPOTIFY_CLIENT_ID') || !defined('SPOTIFY_CLIENT_SECRET')) {
        return null;
    }
    
    $tokenUrl = 'https://accounts.spotify.com/api/token';
    $body = [
        'grant_type' => 'refresh_token',
        'refresh_token' => $spotifyRow['refresh_token']
    ];
    
    $authHeader = 'Basic ' . base64_encode(SPOTIFY_CLIENT_ID . ':' . SPOTIFY_CLIENT_SECRET);
    
    $ch = curl_init($tokenUrl);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($body));
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Authorization: ' . $authHeader,
        'Content-Type: application/x-www-form-urlencoded'
    ]);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($httpCode === 200) {
        $resData = json_decode($response, true);
        if (isset($resData['access_token'])) {
            $newAccess = $resData['access_token'];
            $expiresIn = (int) $resData['expires_in'];
            $expiresAt = date('Y-m-d H:i:s', time() + $expiresIn);
            
            // Update database
            $stmt = $pdo->prepare("UPDATE user_spotify SET access_token = ?, expires_at = ? WHERE user_id = ?");
            $stmt->execute([$newAccess, $expiresAt, $userId]);
            
            return $newAccess;
        }
    }
    return null;
}

header('Content-Type: application/json');

// Check Login
if (!isset($_SESSION['user'])) {
    echo json_encode(['ok' => false, 'error' => 'Unauthorized access']);
    exit();
}

$userId = (int) $_SESSION['user']['user_id'];
$lang = (function_exists('current_locale') && current_locale() === 'vi') ? 'vi' : 'en';

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
        // Ignore caches from an older prompt/payload version → regenerate fresh.
        if ($data && (int) ($data['_v'] ?? 0) === STORY_CACHE_VERSION) {
            // The weekly cache is frozen for the whole ISO week. If it was first
            // built before the user connected Spotify / had listening history, its
            // `spotify` block is null and the Diet & Beats "AI Combined Archetype"
            // card stays stuck on "Awaiting music". Only in that case (cache lacks
            // spotify AND the user is actually connected) do we bypass the cache and
            // regenerate so the vibe can populate. Otherwise serve the cache as-is.
            $cacheHasSpotify = !empty($data['spotify']);
            $userHasSpotify = false;
            if (!$cacheHasSpotify && defined('SPOTIFY_CLIENT_ID') && SPOTIFY_CLIENT_ID !== '') {
                $chk = $pdo->prepare("SELECT 1 FROM user_spotify WHERE user_id = ? LIMIT 1");
                $chk->execute([$userId]);
                $userHasSpotify = (bool) $chk->fetchColumn();
            }

            if ($cacheHasSpotify || !$userHasSpotify) {
                echo json_encode(array_merge(['ok' => true, 'cached' => true], $data));
                exit();
            }
            // else: connected but cache has no spotify → fall through to regenerate.
        }
    }

    // 2. Cache miss: Fetch stats & food logs
    $achProgress = bb_achievements_progress($pdo, $userId);
    $summary = $achProgress['summary'];
    
    // Check Spotify Connection
    $spotifyConnected = false;
    $recentTracks = [];

    if (defined('SPOTIFY_CLIENT_ID') && defined('SPOTIFY_CLIENT_SECRET') && SPOTIFY_CLIENT_ID !== '') {
        $spotifyStmt = $pdo->prepare("SELECT * FROM user_spotify WHERE user_id = ? LIMIT 1");
        $spotifyStmt->execute([$userId]);
        $spotifyRow = $spotifyStmt->fetch(PDO::FETCH_ASSOC);
        
        if ($spotifyRow) {
            $accessToken = $spotifyRow['access_token'];
            
            // Refresh token if expired (within 30 seconds of expiry)
            if (strtotime($spotifyRow['expires_at']) <= time() + 30) {
                $accessToken = refresh_spotify_token($pdo, $userId, $spotifyRow);
            }
            
            if ($accessToken) {
                $spotifyConnected = true;
                
                // Call Spotify Recently Played
                $recentUrl = 'https://api.spotify.com/v1/me/player/recently-played?limit=50';
                $ch = curl_init($recentUrl);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch, CURLOPT_HTTPHEADER, [
                    'Authorization: Bearer ' . $accessToken
                ]);
                curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
                curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
                
                $spotifyRes = curl_exec($ch);
                $spotifyCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                curl_close($ch);
                
                if ($spotifyCode === 200) {
                    $spotifyData = json_decode($spotifyRes, true);
                    $tracks = $spotifyData['items'] ?? [];
                    
                    // Group unique tracks
                    $seenTracks = [];
                    foreach ($tracks as $item) {
                        $track = $item['track'];
                        $artistsStr = implode(', ', array_column($track['artists'], 'name'));
                        $key = $track['name'] . '|' . $artistsStr;
                        
                        if (!isset($seenTracks[$key])) {
                            $seenTracks[$key] = true;
                            $recentTracks[] = [
                                'track' => $track['name'],
                                'artist' => $artistsStr,
                                'image' => $track['album']['images'][1]['url'] ?? ($track['album']['images'][0]['url'] ?? null)
                            ];
                        }
                    }
                    $recentTracks = array_slice($recentTracks, 0, 10);
                }
            }
        }
    }

    // Get user's intake log for the last 7 days
    $intakeStmt = $pdo->prepare(
        "SELECT food_item, calories, meal_category 
         FROM intakeLog 
         WHERE user_id = ? AND date_intake >= DATE_SUB(NOW(), INTERVAL 7 DAY)
         ORDER BY date_intake DESC"
    );
    $intakeStmt->execute([$userId]);
    $intakeLogs = $intakeStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

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

    // The exact food shown on the Diet & Beats card. Anchor the AI's Spotify
    // archetype/caption to THIS food so the copy matches what's displayed.
    $featuredFood = ($favoriteFood !== 'Not enough data')
        ? $favoriteFood
        : (($lang === 'vi') ? 'Món ăn yêu thích' : 'Favorite Snack');

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

    // Match prompt additions for Spotify
    $spotifyPromptVi = '';
    if ($spotifyConnected && !empty($recentTracks)) {
        $trackListStr = '';
        foreach ($recentTracks as $idx => $tr) {
            $trackListStr .= ($idx + 1) . ". \"" . $tr['track'] . "\" của \"" . $tr['artist'] . "\", ";
        }
        $trackListStr = rtrim($trackListStr, ', ');

        $spotifyPromptVi = "\n- [DỮ LIỆU ĐẶC BIỆT] Người dùng đã liên kết Spotify và vừa nghe các bài hát gần đây: [" . $trackListStr . "]
- BÀI HÁT TIÊU BIỂU đang được hiển thị cho người dùng trên thẻ là: \"" . $recentTracks[0]['track'] . "\" của \"" . $recentTracks[0]['artist'] . "\".
- MÓN ĂN TIÊU BIỂU đang được hiển thị trên thẻ là: \"" . $featuredFood . "\".
Hãy phân tích gu âm nhạc này (ví dụ: sôi động/mạnh mẽ, lofi/acoustic nhẹ nhàng, ballad buồn thảm/tâm trạng) kết hợp với MÓN ĂN TIÊU BIỂU để tạo ra Slide số 5 (chèn ngay trước slide Bento Tổng kết) đại diện cho phong cách kết hợp 'Diet & Beats' (Nhịp điệu & Thực đơn) của họ. Cung cấp thêm 2 trường sau trong đối tượng JSON trả về:
  + \"spotify_archetype\": Đặt một cái tên hình mẫu hài hước theo tiếng Việt kết hợp gu nhạc & MÓN ĂN TIÊU BIỂU nêu trên (ví dụ: \"Chiến Thần Bulking Nhạc Phonk\", \"Cú Đêm Lofi & Matcha Latte\", \"Sầu Sĩ Gặm Bánh Mì & Ballad Thất Tình\").
  + \"spotify_desc\": Viết 1 dòng ngắn tự giễu hài hước bằng tiếng Việt (dưới 20 từ). QUAN TRỌNG: chỉ được nhắc đến CHÍNH bài hát/nghệ sĩ TIÊU BIỂU và MÓN ĂN TIÊU BIỂU nêu trên (tuyệt đối không nhắc nghệ sĩ hay món ăn khác trong danh sách) để khớp với thẻ đang hiển thị.
";
    }

    $spotifyPromptEn = '';
    if ($spotifyConnected && !empty($recentTracks)) {
        $trackListStr = '';
        foreach ($recentTracks as $idx => $tr) {
            $trackListStr .= ($idx + 1) . ". \"" . $tr['track'] . "\" by \"" . $tr['artist'] . "\", ";
        }
        $trackListStr = rtrim($trackListStr, ', ');

        $spotifyPromptEn = "\n- [SPECIAL DATA] The user linked Spotify and recently listened to these tracks: [" . $trackListStr . "]
- The FEATURED track shown to the user on the card is: \"" . $recentTracks[0]['track'] . "\" by \"" . $recentTracks[0]['artist'] . "\".
- The FEATURED food shown on the card is: \"" . $featuredFood . "\".
Please analyze their music taste mood (e.g. upbeat/energetic, chill/lofi, sad ballad/emotional) along with the FEATURED food to unlock a dynamic 'Diet & Beats' Slide 5 (inserted right before the Bento Summary slide). Add these 2 fields to the JSON response:
  + \"spotify_archetype\": A funny name/archetype combining their music taste with the FEATURED food above (e.g., \"Phonk Gym-Bro Protein Bulker\", \"Midnight Lofi & Matcha Latte\", \"Sad Indie Toast Nibbler\").
  + \"spotify_desc\": A short, self-deprecating, witty caption in English (under 20 words). IMPORTANT: only mention the FEATURED track/artist and the FEATURED food above (never name any other artist or food from the list) so it matches the card being displayed.
";
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
- Danh sách tất cả các món ăn đã log trong 30 ngày qua: [" . $foodListStr . "]" . $spotifyPromptVi . "
 
Nhiệm vụ của bạn:
1. Phân tích danh sách món ăn trong 30 ngày qua để đặt cho họ một \"Hình mẫu ẩm thực\" (Dietary Archetype) thật hài hước, mang phong cách trẻ trung kiểu Việt Nam (ví dụ: \"Chiến binh ức gà nửa mùa\", \"Đại sứ bánh mì kẹp\", \"Chúa tể hảo ngọt trà sữa\").
2. Viết 1 dòng ngắn giải thích lý do hài hước cho hình mẫu này (chỉ ra sự tương phản đáng yêu, ví dụ nạp protein xây cơ nhưng vẫn log trà sữa xả cơ).
3. Viết các câu chú thích (caption) ngắn (dưới 20 từ) cực kỳ dí dỏm bằng tiếng Việt cho các slide Story sau:
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
  \"slide4_leaderboard\": \"Caption slide 4\",
  \"spotify_archetype\": \"Tên hình mẫu slide 5 (chỉ điền khi có dữ liệu đặc biệt Spotify ở trên, nếu không có hãy để trống \\\"\\\")\",
  \"spotify_desc\": \"Caption slide 5 (chỉ điền khi có dữ liệu đặc biệt Spotify ở trên, nếu không có hãy để trống \\\"\\\")\"
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
- List of foods logged in the last 30 days: [" . $foodListStr . "]" . $spotifyPromptEn . "
 
Your Tasks:
1. Analyze their 30-day food list and give them a highly creative and humorous \"Dietary Archetype\" (e.g., \"Part-Time Chicken Breast Warrior\", \"Double-Agent Banh Mi Enthusiast\", \"Yin-Yang Sweet Tooth Master\").
2. Write a 1-sentence funny explanation for this archetype (pointing out any funny contrasts in their logged foods, like trying to bulk up with protein but neutralizing it with sweet desserts).
3. Write very short, engaging, and witty captions (under 20 words each) in English for the Story slides:
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
  \"slide4_leaderboard\": \"Caption for slide 4\",
  \"spotify_archetype\": \"Slide 5 name (only fill if special Spotify data exists above, otherwise empty \\\"\\\")\",
  \"spotify_desc\": \"Slide 5 caption (only fill if special Spotify data exists above, otherwise empty \\\"\\\")\"
}";
    }

    // 4. Send request to Gemini
    $storyModel = defined('AI_COACH_MODEL') ? AI_COACH_MODEL : 'gemini-3.1-flash-lite';
    $apiUrl = "https://generativelanguage.googleapis.com/v1beta/models/{$storyModel}:generateContent?key=" . GEMINI_API_KEY;
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
                'slide4_leaderboard' => 'Đứng thứ ' . $leaderboardRank . ' trên bảng xếp hạng! Một vị thế vô cùng đáng gờm.',
                'spotify_archetype' => !empty($recentTracks) ? 'Sầu Sĩ Gặm Bánh Mì' : '',
                'spotify_desc' => !empty($recentTracks) ? 'Vừa ăn và vừa nghe nhạc buồn da diết. Sự kết hợp đầy xúc cảm!' : ''
            ];
        } else {
            $aiData = [
                'diet_archetype' => 'Dedicated Food Tracker',
                'archetype_desc' => 'Consistently logging meals and building habits every single day!',
                'slide1_aura' => 'Your nutrition aura is glowing brightly with discipline this week!',
                'slide2_topfood' => 'Your top badge ' . $topBadge . ' is ready to shine on your story.',
                'slide3_streak' => 'Keeping the fire hot with an awesome ' . $summary['current_streak'] . '-day streak!',
                'slide4_leaderboard' => 'Secured rank ' . $leaderboardRank . ' on the leaderboard. A truly formidable position!',
                'spotify_archetype' => !empty($recentTracks) ? 'Sad Indie Toast Nibbler' : '',
                'spotify_desc' => !empty($recentTracks) ? 'Logging meals while drowning in emotional indie tracks. Quite a vibe!' : ''
            ];
        }
    }

    // Pick top track and dynamic food for display
    $spotifyTrackName = '';
    $spotifyArtistName = '';
    $spotifyTrackImage = null;
    if (!empty($recentTracks)) {
        $spotifyTrackName = $recentTracks[0]['track'];
        $spotifyArtistName = $recentTracks[0]['artist'];
        $spotifyTrackImage = $recentTracks[0]['image'];
    }

    // Save success response to cache (both original stats and generated captions)
    $finalData = array_merge([
        '_v' => STORY_CACHE_VERSION,
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
        ],
        'spotify' => ($spotifyConnected && !empty($recentTracks) && !empty($aiData['spotify_archetype'])) ? [
            'track' => $spotifyTrackName,
            'artist' => $spotifyArtistName,
            'image' => $spotifyTrackImage,
            'food' => $featuredFood,
            'archetype' => $aiData['spotify_archetype'],
            'desc' => $aiData['spotify_desc']
        ] : null
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
