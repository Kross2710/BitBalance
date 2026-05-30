<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/../include/init.php';

$activePage = 'beats';
$activeHeader = 'dashboard';
$bodyClass = 'page-beats';
$displayUser = $isLoggedIn ? $user['user_name'] : 'Guest';

if (!$isLoggedIn) {
    header('Location: ' . BASE_URL . 'login.php');
    exit();
}

$userId = (int) $user['user_id'];
$lang = (function_exists('current_locale') && current_locale() === 'vi') ? 'vi' : 'en';

// Page Localized Texts
$texts = [
    'vi' => [
        'title' => 'Nhạc & Thực đơn | BitBalance',
        'kicker' => 'Âm nhạc & Dinh dưỡng',
        'connected_title' => 'Đã kết nối Spotify',
        'connected_subtitle' => 'Gu âm nhạc và thực đơn dinh dưỡng của bạn đang được đồng bộ thời gian thực!',
        'open_story' => 'Xem câu chuyện tuần này ✨',
        'disconnect' => 'Hủy kết nối',
        'hero_title' => 'Cá tính Dinh dưỡng & Gu nhạc',
        'hero_desc' => 'Mood-Food Matcher: Phân tích gu âm nhạc gần đây và đối chiếu với chất lượng khẩu phần calo của bạn.',
        'vibe_card_title' => 'Hình mẫu Đồng điệu AI',
        'recent_beats_title' => 'Bài hát nghe gần đây',
        'weekly_fuel_title' => 'Món ăn trong tuần (7 ngày qua)',
        'times_logged' => '{n} lần ghi nhận',
        'promo_title' => 'Đồng bộ Gu nhạc & Thực đơn của bạn',
        'promo_subtitle' => 'Bữa ăn khuya của bạn có giai điệu như thế nào? Liên kết Spotify để mở khóa tính năng tấu hài cực mạnh và phân tích từ AI!',
        'connect_btn' => 'Kết nối tài khoản Spotify',
        'feature_wrapped_title' => 'Mở khóa Slide thứ 6 đặc biệt',
        'feature_wrapped_desc' => 'Mở khóa slide "Diet & Beats" độc quyền trong Weekly Wrapped Story để chia sẻ lên Instagram.',
        'feature_history_title' => 'Nhật ký cặp đôi Nhạc & Món ăn',
        'feature_history_desc' => 'Theo dõi lịch sử chi tiết những bài hát bạn đã nghe đúng lúc thưởng thức đồ ăn.',
        'feature_ai_title' => 'Phân tích hình mẫu ẩm thực AI',
        'feature_ai_desc' => 'AI Gemini sẽ gọi tên phong cách ẩm thực của bạn dựa trên sự kết hợp bài hát và món ăn một cách hài hước.',
        'vibe_recommend' => 'Gợi ý món ăn theo gu nhạc hôm nay',
        'recommend_title' => 'Thực đơn đề xuất cho tâm trạng của bạn',
        'rec_lofi' => 'Lo-Fi Chill Beats 🎧',
        'rec_lofi_food' => 'Trà xanh ấm & Bánh quy yến mạch nhẹ nhàng',
        'rec_workout' => 'Workout / Electronic / Rock ⚡',
        'rec_workout_food' => 'Sinh tố Whey Protein giàu đạm để phục hồi cơ bắp',
        'rec_sad' => 'Sad Indie / Ballad 🌧️',
        'rec_sad_food' => 'Một tô súp ấm hoặc chocolate đắng để xoa dịu tâm hồn',
        'loading_vibe' => 'Đang phân tích cá tính âm nhạc và ẩm thực của bạn...',
        'matched_concept' => 'Cặp đôi Vibe tiêu biểu'
    ],
    'en' => [
        'title' => 'Diet & Beats | BitBalance',
        'kicker' => 'Music & Nutrition',
        'connected_title' => 'Spotify Connected',
        'connected_subtitle' => 'Your music taste and dietary intake are syncing in real time!',
        'open_story' => 'View Weekly Wrapped ✨',
        'disconnect' => 'Disconnect Spotify',
        'hero_title' => 'Music & Food Personality',
        'hero_desc' => 'Mood-Food Matcher: Analyzes your overall music taste against your recent dietary macronutrient profile.',
        'vibe_card_title' => 'AI Combined Archetype',
        'recent_beats_title' => 'Recently Played Tracks',
        'weekly_fuel_title' => 'Weekly Fuel (Last 7 Days)',
        'times_logged' => 'logged {n} times',
        'promo_title' => 'Sync Your Diet & Music Taste',
        'promo_subtitle' => 'What does your late-night snack sound like? Connect your Spotify account to discover the exact soundtrack to your eating habits, unlock premium AI features!',
        'connect_btn' => 'Connect Spotify Account',
        'feature_wrapped_title' => 'Unlock Special 6th Slide',
        'feature_wrapped_desc' => 'Unlock the exclusive "Diet & Beats" slide in your Weekly Wrapped Story, styled premium for Instagram.',
        'feature_history_title' => 'Matched Song & Snack History',
        'feature_history_desc' => 'Trace exact times when your musical vibes matched perfectly with your food cravings.',
        'feature_ai_title' => 'AI Dietary Archetype',
        'feature_ai_desc' => 'Gemini AI analyzes your music + food combos to give you hilarious, custom archetype names.',
        'vibe_recommend' => 'Food Pairings for Your Music Vibe',
        'recommend_title' => 'Suggested Fuel for Your Current Beats',
        'rec_lofi' => 'Lo-Fi Chill Beats 🎧',
        'rec_lofi_food' => 'Warm Green Tea & Light Oatmeal Cookies',
        'rec_workout' => 'Workout / Electronic / Rock ⚡',
        'rec_workout_food' => 'A High-Protein Whey Shake for muscle recovery',
        'rec_sad' => 'Sad Indie / Ballad 🌧️',
        'rec_sad_food' => 'A warm bowl of soup or dark chocolate to soothe the soul',
        'loading_vibe' => 'Analyzing your music and culinary vibes...',
        'matched_concept' => 'Featured Vibe Pair'
    ]
];

$t = $texts[$lang];

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
            
            $stmt = $pdo->prepare("UPDATE user_spotify SET access_token = ?, expires_at = ? WHERE user_id = ?");
            $stmt->execute([$newAccess, $expiresAt, $userId]);
            
            return $newAccess;
        }
    }
    return null;
}

// Fetch connection status
$spotifyConnected = false;
$spotifyRow = null;
try {
    $spotifyStmt = $pdo->prepare("SELECT * FROM user_spotify WHERE user_id = ? LIMIT 1");
    $spotifyStmt->execute([$userId]);
    $spotifyRow = $spotifyStmt->fetch(PDO::FETCH_ASSOC);
    $spotifyConnected = (bool) $spotifyRow;
} catch (PDOException $e) {
    // Database might not have table created yet
}

$recentTracks = [];
$errorMsg = null;

if ($spotifyConnected && $spotifyRow) {
    $accessToken = $spotifyRow['access_token'];
    
    if (strtotime($spotifyRow['expires_at']) <= time() + 30) {
        $accessToken = refresh_spotify_token($pdo, $userId, $spotifyRow);
    }
    
    if ($accessToken) {
        $recentUrl = 'https://api.spotify.com/v1/me/player/recently-played?limit=30';
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
            
            // Extract unique tracks
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
            $recentTracks = array_slice($recentTracks, 0, 5); // Display top 5 recent tracks
        } else {
            $errData = json_decode($spotifyRes, true);
            $errDetail = $errData['error']['message'] ?? 'Unknown error';
            $errorMsg = ($lang === 'vi') 
                ? "Đồng bộ từ Spotify thất bại (HTTP $spotifyCode: $errDetail). Vui lòng kiểm tra lại kết nối."
                : "Failed to sync recently played tracks from Spotify (HTTP $spotifyCode: $errDetail). Please check connection.";
        }
    } else {
        $errorMsg = ($lang === 'vi')
            ? "Không thể cấp quyền phiên làm việc với Spotify API."
            : "Unable to authorize Spotify API session.";
    }
}

// Fetch user's top foods logged in the last 7 days
$topFoods = [];
if ($isLoggedIn) {
    $foodStmt = $pdo->prepare(
        "SELECT food_item, COUNT(*) AS count_logged, SUM(calories) AS total_cal, meal_category
         FROM intakeLog 
         WHERE user_id = ? AND date_intake >= DATE_SUB(NOW(), INTERVAL 7 DAY)
         GROUP BY food_item, meal_category
         ORDER BY count_logged DESC, food_item ASC
         LIMIT 5"
    );
    $foodStmt->execute([$userId]);
    $topFoods = $foodStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
}
?>
<!DOCTYPE html>
<html lang="<?= html_lang_attr() ?>" data-theme="<?= htmlspecialchars($_SESSION['user']['theme_preference'] ?? 'system', ENT_QUOTES) ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($t['title'], ENT_QUOTES) ?></title>
    <?php
    $pageComponents = ['sidebar', 'fab'];
    $pageCss = ['css/dashboard.css', 'css/pages/dashboard-beats.css', 'css/components/story-share.css'];
    include PROJECT_ROOT . 'views/head_css.php';
    ?>
    <script src="https://kit.fontawesome.com/b94f65ead2.js" crossorigin="anonymous"></script>
</head>

<body class="<?= htmlspecialchars($bodyClass, ENT_QUOTES) ?>">
    <?php include PROJECT_ROOT . 'views/header.php'; ?>
    <?php include PROJECT_ROOT . 'dashboard/views/sidebar.php'; ?>

    <main class="dashboard-content">
        <div class="beats-container">
            <?php if (isset($_GET['error']) || $errorMsg): ?>
                <div class="alert error">
                    <i class="fa-solid fa-circle-exclamation"></i> <?= htmlspecialchars($_GET['error'] ?? $errorMsg) ?>
                </div>
            <?php endif; ?>
            <?php if (isset($_GET['success'])): ?>
                <div class="alert success">
                    <i class="fa-solid fa-circle-check"></i> <?= htmlspecialchars($_GET['success']) ?>
                </div>
            <?php endif; ?>

            <?php if ($spotifyConnected): ?>
                <!-- CONNECTED DASHBOARD -->
                <section class="beats-hero">
                    <div class="beats-hero__copy">
                        <span class="beats-kicker"><i class="fa-solid fa-music"></i> <?= htmlspecialchars($t['kicker'], ENT_QUOTES) ?></span>
                        <h1><?= htmlspecialchars($t['hero_title'], ENT_QUOTES) ?></h1>
                        <p><?= htmlspecialchars($t['hero_desc'], ENT_QUOTES) ?></p>
                        
                        <div class="beats-hero__actions">
                            <a href="dashboard-progress.php?story=open" class="story-btn-primary">
                                <i class="fa-solid fa-wand-magic-sparkles"></i> <?= htmlspecialchars($t['open_story'], ENT_QUOTES) ?>
                            </a>
                            <a href="handlers/spotify_disconnect.php" class="beats-btn-danger" onclick="return confirm('<?= ($lang === 'vi') ? 'Bạn có chắc chắn muốn hủy liên kết tài khoản Spotify?' : 'Are you sure you want to disconnect your Spotify account?' ?>')">
                                <i class="fa-solid fa-link-slash"></i> <?= htmlspecialchars($t['disconnect'], ENT_QUOTES) ?>
                            </a>
                        </div>
                    </div>
                    
                    <div class="beats-status-card">
                        <div class="beats-spotify-logo"><i class="fa-brands fa-spotify"></i></div>
                        <h3><?= htmlspecialchars($t['connected_title'], ENT_QUOTES) ?></h3>
                        <p><?= htmlspecialchars($t['connected_subtitle'], ENT_QUOTES) ?></p>
                    </div>
                </section>

                <div class="beats-dashboard-grid">
                    <!-- LEFT COLUMN: AI VIBE CARD -->
                    <div class="beats-left-col">
                        <section class="beats-section vibe-card">
                            <h2><i class="fa-solid fa-wand-magic-sparkles"></i> <?= htmlspecialchars($t['vibe_card_title'], ENT_QUOTES) ?></h2>
                            
                            <div id="vibe-loading" class="vibe-loading-box">
                                <div class="beats-spinner"><i class="fa-solid fa-circle-notch fa-spin"></i></div>
                                <p><?= htmlspecialchars($t['loading_vibe'], ENT_QUOTES) ?></p>
                            </div>

                            <div id="vibe-content" class="vibe-content-box" style="display: none;">
                                <div class="vibe-badge-container">
                                    <span id="vibe-badge" class="vibe-sparkle-badge"><i class="fa-solid fa-sparkles"></i> <span id="vibe-title">Vibe</span></span>
                                </div>
                                <p id="vibe-desc" class="vibe-explanation"></p>
                                
                                <div class="concept-match-box">
                                    <h4><i class="fa-solid fa-compact-disc"></i> <?= htmlspecialchars($t['matched_concept'], ENT_QUOTES) ?></h4>
                                    <div class="concept-match-row">
                                        <div class="concept-music-side">
                                            <div class="concept-disc-art">
                                                <img id="matched-track-art" src="<?= BASE_URL ?>images/default-album.png" alt="Track Art">
                                                <div class="vinyl-disc"></div>
                                            </div>
                                            <strong id="matched-track-name">Track</strong>
                                            <span id="matched-track-artist">Artist</span>
                                        </div>
                                        <div class="concept-link-icon">
                                            <i class="fa-solid fa-heart-pulse"></i>
                                        </div>
                                        <div class="concept-food-side">
                                            <div class="concept-food-art">
                                                <i class="fa-solid fa-utensils"></i>
                                            </div>
                                            <strong id="matched-food-name">Food</strong>
                                            <span><?= ($lang === 'vi') ? 'Món ăn yêu thích nhất' : 'Most logged fuel' ?></span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </section>

                        <!-- SUGGESTED VIBES -->
                        <section class="beats-section recommendation-section" style="margin-top: 30px;">
                            <h2><i class="fa-solid fa-wand-magic"></i> <?= htmlspecialchars($t['recommend_title'], ENT_QUOTES) ?></h2>
                            <div class="recommendations-grid" id="fuelGrid"
                                data-recent-tracks='<?= htmlspecialchars(json_encode(array_map(fn($t) => ['track' => $t['track'], 'artist' => $t['artist']], $recentTracks), JSON_UNESCAPED_UNICODE | JSON_HEX_APOS | JSON_HEX_QUOT), ENT_QUOTES) ?>'>
                                <article class="recommend-card lofi-theme">
                                    <div class="recommend-icon"><i class="fa-solid fa-headphones"></i></div>
                                    <h4><?= htmlspecialchars($t['rec_lofi'], ENT_QUOTES) ?></h4>
                                    <p><?= htmlspecialchars($t['rec_lofi_food'], ENT_QUOTES) ?></p>
                                </article>
                                <article class="recommend-card workout-theme">
                                    <div class="recommend-icon"><i class="fa-solid fa-bolt"></i></div>
                                    <h4><?= htmlspecialchars($t['rec_workout'], ENT_QUOTES) ?></h4>
                                    <p><?= htmlspecialchars($t['rec_workout_food'], ENT_QUOTES) ?></p>
                                </article>
                                <article class="recommend-card sad-theme">
                                    <div class="recommend-icon"><i class="fa-solid fa-cloud-showers-heavy"></i></div>
                                    <h4><?= htmlspecialchars($t['rec_sad'], ENT_QUOTES) ?></h4>
                                    <p><?= htmlspecialchars($t['rec_sad_food'], ENT_QUOTES) ?></p>
                                </article>
                            </div>
                        </section>
                    </div>

                    <!-- RIGHT COLUMN: REALTIME LOGS -->
                    <div class="beats-right-col">
                        <!-- RECENT BEATS (SPOTIFY) -->
                        <section class="beats-section">
                            <h2><i class="fa-solid fa-compact-disc"></i> <?= htmlspecialchars($t['recent_beats_title'], ENT_QUOTES) ?></h2>
                            <div class="beats-simple-list">
                                <?php if (empty($recentTracks)): ?>
                                    <p class="empty-list-text"><?= ($lang === 'vi') ? 'Chưa nghe bài hát nào gần đây.' : 'No recently played tracks found.' ?></p>
                                <?php else: ?>
                                    <?php foreach ($recentTracks as $track): ?>
                                        <div class="beats-list-item">
                                            <div class="track-artwork mini-artwork">
                                                <?php if ($track['image']): ?>
                                                    <img src="<?= htmlspecialchars($track['image'], ENT_QUOTES) ?>" alt="Album Art">
                                                <?php else: ?>
                                                    <div class="track-art-fallback"><i class="fa-solid fa-music"></i></div>
                                                <?php endif; ?>
                                                <div class="vinyl-disc mini-disc"></div>
                                            </div>
                                            <div class="item-info">
                                                <strong><?= htmlspecialchars($track['track'], ENT_QUOTES) ?></strong>
                                                <span><?= htmlspecialchars($track['artist'], ENT_QUOTES) ?></span>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </div>
                        </section>

                        <!-- WEEKLY FUEL (FOODS) -->
                        <section class="beats-section" style="margin-top: 30px;">
                            <h2><i class="fa-solid fa-utensils"></i> <?= htmlspecialchars($t['weekly_fuel_title'], ENT_QUOTES) ?></h2>
                            <div class="beats-simple-list">
                                <?php if (empty($topFoods)): ?>
                                    <p class="empty-list-text"><?= ($lang === 'vi') ? 'Chưa ghi nhận món ăn nào tuần này.' : 'No foods logged this week.' ?></p>
                                <?php else: ?>
                                    <?php foreach ($topFoods as $food): ?>
                                        <div class="beats-list-item">
                                            <div class="food-artwork-badge">
                                                <i class="fa-solid fa-bowl-food"></i>
                                            </div>
                                            <div class="item-info">
                                                <strong><?= htmlspecialchars($food['food_item'], ENT_QUOTES) ?></strong>
                                                <span><?= htmlspecialchars(str_replace('{n}', $food['count_logged'], $t['times_logged']), ENT_QUOTES) ?></span>
                                            </div>
                                            <div class="item-calories">
                                                <?= number_format($food['total_cal'] / $food['count_logged']) ?> kcal
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </div>
                        </section>
                    </div>
                </div>

            <?php else: ?>
                <!-- PROMO STATE (DISCONNECTED) -->
                <section class="beats-promo-card">
                    <div class="beats-promo-icon"><i class="fa-brands fa-spotify"></i></div>
                    <h1><?= htmlspecialchars($t['promo_title'], ENT_QUOTES) ?></h1>
                    <p class="promo-desc"><?= htmlspecialchars($t['promo_subtitle'], ENT_QUOTES) ?></p>
                    
                    <a href="handlers/spotify_auth.php" class="beats-btn-connect">
                        <i class="fa-brands fa-spotify"></i> <?= htmlspecialchars($t['connect_btn'], ENT_QUOTES) ?>
                    </a>

                    <div class="beats-features-grid">
                        <article class="feature-item">
                            <div class="feature-icon"><i class="fa-solid fa-wand-magic-sparkles"></i></div>
                            <h3><?= htmlspecialchars($t['feature_wrapped_title'], ENT_QUOTES) ?></h3>
                            <p><?= htmlspecialchars($t['feature_wrapped_desc'], ENT_QUOTES) ?></p>
                        </article>
                        <article class="feature-item">
                            <div class="feature-icon"><i class="fa-solid fa-history"></i></div>
                            <h3><?= htmlspecialchars($t['feature_history_title'], ENT_QUOTES) ?></h3>
                            <p><?= htmlspecialchars($t['feature_history_desc'], ENT_QUOTES) ?></p>
                        </article>
                        <article class="feature-item">
                            <div class="feature-icon"><i class="fa-solid fa-brain"></i></div>
                            <h3><?= htmlspecialchars($t['feature_ai_title'], ENT_QUOTES) ?></h3>
                            <p><?= htmlspecialchars($t['feature_ai_desc'], ENT_QUOTES) ?></p>
                        </article>
                    </div>
                </section>
            <?php endif; ?>
        </div>
    </main>

    <?php include PROJECT_ROOT . 'views/footer.php'; ?>
    <?php /* Weekly Wrapped now lives on the Progress page; the hero CTA deep-links
             there (?story=open). No story-share.js needed here. */ ?>
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const lang = '<?= $lang ?>';
            
            // Check if user is connected to load the dynamic AI vibe card
            const vibeLoading = document.getElementById('vibe-loading');
            const vibeContent = document.getElementById('vibe-content');
            
            if (vibeLoading) {
                fetch('handlers/story_data.php')
                    .then(response => response.json())
                    .then(data => {
                        if (data.ok && data.spotify) {
                            vibeLoading.style.display = 'none';
                            vibeContent.style.display = 'block';
                            
                            document.getElementById('vibe-title').textContent = data.spotify.archetype;
                            document.getElementById('vibe-desc').textContent = data.spotify.desc;
                            
                            document.getElementById('matched-track-name').textContent = data.spotify.track;
                            document.getElementById('matched-track-artist').textContent = data.spotify.artist;
                            document.getElementById('matched-food-name').textContent = data.spotify.food;
                            
                            if (data.spotify.image) {
                                document.getElementById('matched-track-art').src = data.spotify.image;
                            }
                        } else {
                            vibeLoading.innerHTML = `
                                <div class="beats-empty-icon"><i class="fa-solid fa-headphones"></i></div>
                                <h3>${lang === 'vi' ? 'Đang cập nhật gu âm nhạc...' : 'Awaiting music activity...'}</h3>
                                <p>${lang === 'vi' ? 'Hãy mở Spotify nghe một vài bài hát rồi quay lại đây nhé!' : 'Play some songs on your linked Spotify account and refresh!'}</p>
                            `;
                        }
                    })
                    .catch(err => {
                        console.error('Error loading AI vibe:', err);
                        vibeLoading.innerHTML = `<p style="color: var(--color-danger); font-weight: 700;">${lang === 'vi' ? 'Không thể tải phân tích Vibe từ AI.' : 'Failed to load AI vibe analysis.'}</p>`;
                    });
            }

            // --- Suggested Fuel for Your Current Beats (AI, personalized) ---
            const fuelGrid = document.getElementById('fuelGrid');
            if (fuelGrid) {
                let recentTracks = [];
                try { recentTracks = JSON.parse(fuelGrid.dataset.recentTracks || '[]'); } catch (e) {}

                // No tracks → keep the static fallback cards already in the grid.
                if (recentTracks.length > 0) {
                    const moodMap = {
                        chill:     { cls: 'lofi-theme',    icon: 'fa-headphones' },
                        energetic: { cls: 'workout-theme', icon: 'fa-bolt' },
                        sad:       { cls: 'sad-theme',     icon: 'fa-cloud-showers-heavy' },
                        focus:     { cls: 'lofi-theme',    icon: 'fa-brain' },
                        happy:     { cls: 'workout-theme', icon: 'fa-face-smile' }
                    };
                    const esc = (s) => String(s == null ? '' : s).replace(/[&<>"]/g, c => (
                        { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[c]
                    ));

                    const fd = new FormData();
                    fd.append('tracks', JSON.stringify(recentTracks));

                    fetch('handlers/beats_fuel.php', { method: 'POST', body: fd })
                        .then(r => r.json())
                        .then(data => {
                            if (!data.ok || !Array.isArray(data.suggestions) || data.suggestions.length === 0) return;
                            const kcalLabel = lang === 'vi' ? 'kcal' : 'kcal';
                            fuelGrid.innerHTML = data.suggestions.map(s => {
                                const m = moodMap[s.mood] || moodMap.chill;
                                const kcal = s.kcal > 0 ? `<span class="fuel-kcal">~${s.kcal} ${kcalLabel}</span>` : '';
                                return `
                                <article class="recommend-card ${m.cls}">
                                    <div class="recommend-icon"><i class="fa-solid ${m.icon}"></i></div>
                                    <h4>${esc(s.vibe)}</h4>
                                    <p><strong>${esc(s.food)}</strong> ${kcal}<br>${esc(s.reason)}</p>
                                </article>`;
                            }).join('');
                        })
                        .catch(err => console.error('Error loading fuel suggestions:', err));
                }
            }
        });
    </script>
</body>
</html>
