<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/../include/init.php';
require_once __DIR__ . '/handlers/functions.php';

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
        'matched_concept' => 'Cặp đôi Vibe tiêu biểu',
        
        // DJ Mixer localization
        'dj_title' => 'BitBalance AI DJ Mixer 🎚️',
        'dj_subtitle' => 'Mix bài hát vừa nghe cùng thực đơn để xem điểm "hợp cạ" từ AI!',
        'dj_deck_track' => 'Mâm Xoay Nhạc',
        'dj_deck_food' => 'Đĩa Thức Ăn',
        'dj_empty_track' => 'Bấm + ở danh sách bài hát...',
        'dj_empty_food' => 'Bấm + ở danh sách món ăn...',
        'dj_crossfader_title' => 'Chọn Vibe Tâm Trạng',
        'dj_mix_btn' => 'Bấm Mix Ngay! 🎚️',
        'dj_result_title' => 'KẾT QUẢ PHỐI TRỘN AI',
        'dj_match_lbl' => 'ĐỘ HỢP CẠ',
        'dj_reset_btn' => 'Mix Bản Khác 🔁',
        'dj_mixing_active' => 'Đang xoay đĩa & mix nhạc...',
        'dj_score_energy' => 'Độ bốc',
        'dj_score_comfort' => 'Độ an ủi',
        'dj_score_chaos' => 'Độ bất ngờ',
        'dj_ai_offline' => 'AI tạm offline — vẫn lên đồ bằng dữ liệu thật của bạn',
        'history_title' => 'Bộ sưu tập & Lịch sử Mix',
        'collection_label' => 'Archetype đã mở khoá',
        'history_empty' => 'Chưa có bản mix nào — thử mix một bài hát với món ăn ở trên nhé!',
        'mix_just_now' => 'Vừa xong',
        'dj_keep_btn' => 'Giữ lại',
        'dj_discard_btn' => 'Bỏ qua',
        'dj_kept_toast' => 'Đã lưu vào bộ sưu tập! 🎉'
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
        'matched_concept' => 'Featured Vibe Pair',
        
        // DJ Mixer localization
        'dj_title' => 'BitBalance AI DJ Mixer 🎚️',
        'dj_subtitle' => 'Mix your recent tracks with weekly meals to rate AI vibe compatibility!',
        'dj_deck_track' => 'Music Vinyl',
        'dj_deck_food' => 'Fuel Plate',
        'dj_empty_track' => 'Click + on a song to load...',
        'dj_empty_food' => 'Click + on a food to load...',
        'dj_crossfader_title' => 'Crossfade Mood Vibe',
        'dj_mix_btn' => 'MIX IT UP! 🎚️',
        'dj_result_title' => 'AI MIXER RESULT',
        'dj_match_lbl' => 'COMPATIBILITY',
        'dj_reset_btn' => 'Mix Another 🔁',
        'dj_mixing_active' => 'Spinning & mixing vibes...',
        'dj_score_energy' => 'Energy Sync',
        'dj_score_comfort' => 'Comfort',
        'dj_score_chaos' => 'Chaos',
        'dj_ai_offline' => 'AI offline — still styled from your real data',
        'history_title' => 'Collection & Mix History',
        'collection_label' => 'Archetypes unlocked',
        'history_empty' => 'No mixes yet — mix a song with a food above to start your collection!',
        'mix_just_now' => 'Just now',
        'dj_keep_btn' => 'Keep',
        'dj_discard_btn' => 'Discard',
        'dj_kept_toast' => 'Saved to your collection! 🎉'
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

// DJ Mixer history + archetype collection (Diet & Beats)
$mixHistory = $spotifyConnected ? bb_get_beats_mix_history($pdo, $userId, 12) : [];
$mixCollection = $spotifyConnected ? bb_get_beats_collection($pdo, $userId) : [];
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

                <!-- AI MOOD-FOOD DJ MIXER -->
                <section class="dj-mixer-board" id="djMixerBoard">
                    <!-- Particle Floating Notes Canvas -->
                    <canvas class="dj-canvas" id="djCanvas"></canvas>
                    
                    <div class="dj-mixer-header">
                        <div>
                            <h2><i class="fa-solid fa-compact-disc" id="djTitleIcon"></i> <?= htmlspecialchars($t['dj_title'], ENT_QUOTES) ?></h2>
                            <p><?= htmlspecialchars($t['dj_subtitle'], ENT_QUOTES) ?></p>
                        </div>
                    </div>

                    <div class="dj-mixer-decks">
                        <!-- LEFT DECK: SONG VINYL -->
                        <div class="dj-deck" id="djTrackDeck">
                            <span class="dj-deck-title"><i class="fa-solid fa-music"></i> <?= htmlspecialchars($t['dj_deck_track'], ENT_QUOTES) ?></span>
                            <div class="dj-turntable-wrapper">
                                <div class="dj-turntable" id="djTrackPlate">
                                    <div class="dj-turntable-grooves"></div>
                                    <div class="dj-album-art-slot" id="djTrackArtSlot">
                                        <i class="fa-solid fa-compact-disc"></i>
                                    </div>
                                </div>
                                <div class="dj-tonearm" id="djTonearm">
                                    <div class="dj-tonearm-base"></div>
                                    <div class="dj-tonearm-stick"></div>
                                    <div class="dj-tonearm-head"></div>
                                </div>
                            </div>
                            <div class="dj-deck-info" id="djTrackInfo">
                                <span class="dj-slot-empty-text"><i class="fa-solid fa-plus"></i> <?= htmlspecialchars($t['dj_empty_track'], ENT_QUOTES) ?></span>
                            </div>
                        </div>

                        <!-- CENTER CONTROLS: WAVEFORM & MIX BUTTON -->
                        <div class="dj-center-controls">
                            <span class="dj-slider-title" style="margin-bottom: 12px;"><?= ($lang === 'vi') ? 'AI Tự Động Phân Tích Vibe 🧠' : 'AI Auto-Vibe Analyzer 🧠' ?></span>
                            
                            <!-- 3D Neon Waveform visualizer container -->
                            <div class="dj-waveform-container" id="djWaveformContainer">
                                <div class="dj-wave-bar"></div>
                                <div class="dj-wave-bar"></div>
                                <div class="dj-wave-bar"></div>
                                <div class="dj-wave-bar"></div>
                                <div class="dj-wave-bar"></div>
                                <div class="dj-wave-bar"></div>
                            </div>
                            
                            <div class="dj-mood-indicator" id="djMoodIndicator" style="margin-top: 12px; margin-bottom: 20px; font-size: 13px; font-weight: 800; color: var(--color-text-secondary); width: 100%; text-align: center; border: none; box-shadow: none; background: transparent; padding: 0;">
                                <?= ($lang === 'vi') ? 'Chờ nạp nhạc & thực đơn...' : 'Awaiting track & food selection...' ?>
                            </div>

                            <button class="btn-dj-mix" id="djMixBtn" disabled>
                                <i class="fa-solid fa-wand-magic-sparkles"></i>
                                <span><?= htmlspecialchars($t['dj_mix_btn'], ENT_QUOTES) ?></span>
                            </button>
                        </div>

                        <!-- RIGHT DECK: FOOD PLATE -->
                        <div class="dj-deck" id="djFoodDeck">
                            <span class="dj-deck-title"><i class="fa-solid fa-bowl-food"></i> <?= htmlspecialchars($t['dj_deck_food'], ENT_QUOTES) ?></span>
                            <div class="dj-turntable-wrapper">
                                <div class="dj-turntable" id="djFoodPlate" style="background: var(--color-surface);">
                                    <div class="dj-album-art-slot" id="djFoodArtSlot" style="border-radius: var(--radius-md); width: 80px; height: 80px; border: 2px solid var(--color-border); background: var(--color-surface); box-shadow: 0 4px 0 var(--color-border-subtle);">
                                        <i class="fa-solid fa-utensils" style="font-size: 32px;"></i>
                                    </div>
                                </div>
                            </div>
                            <div class="dj-deck-info" id="djFoodInfo">
                                <span class="dj-slot-empty-text"><i class="fa-solid fa-plus"></i> <?= htmlspecialchars($t['dj_empty_food'], ENT_QUOTES) ?></span>
                            </div>
                        </div>
                    </div>

                    <!-- 3D FLIP RESULT OVERLAY -->
                    <div class="dj-result-overlay" id="djResultOverlay">
                        <div class="dj-result-card" id="djResultCard">
                            <h3><i class="fa-solid fa-sparkles" style="color: var(--color-accent);"></i> <?= htmlspecialchars($t['dj_result_title'], ENT_QUOTES) ?></h3>

                            <div class="dj-score-ring">
                                <div class="dj-score-val" id="djScoreVal">0<span class="dj-score-pct">%</span></div>
                            </div>

                            <span class="dj-result-vibe-chip" id="djResultVibe" hidden></span>
                            <h4 class="dj-result-archetype" id="djResultArchetype">-</h4>
                            <p class="dj-result-tagline" id="djResultTagline" hidden></p>

                            <div class="dj-score-bars" id="djScoreBars">
                                <div class="dj-score-bar">
                                    <div class="dj-score-bar-head"><span><i class="fa-solid fa-bolt"></i> <?= htmlspecialchars($t['dj_score_energy'], ENT_QUOTES) ?></span><span class="dj-score-bar-val" id="djBarEnergyVal">0%</span></div>
                                    <div class="dj-score-bar-track"><div class="dj-score-bar-fill energy" id="djBarEnergy"></div></div>
                                </div>
                                <div class="dj-score-bar">
                                    <div class="dj-score-bar-head"><span><i class="fa-solid fa-mug-hot"></i> <?= htmlspecialchars($t['dj_score_comfort'], ENT_QUOTES) ?></span><span class="dj-score-bar-val" id="djBarComfortVal">0%</span></div>
                                    <div class="dj-score-bar-track"><div class="dj-score-bar-fill comfort" id="djBarComfort"></div></div>
                                </div>
                                <div class="dj-score-bar">
                                    <div class="dj-score-bar-head"><span><i class="fa-solid fa-bolt-lightning"></i> <?= htmlspecialchars($t['dj_score_chaos'], ENT_QUOTES) ?></span><span class="dj-score-bar-val" id="djBarChaosVal">0%</span></div>
                                    <div class="dj-score-bar-track"><div class="dj-score-bar-fill chaos" id="djBarChaos"></div></div>
                                </div>
                            </div>

                            <p class="dj-result-comment" id="djResultComment">-</p>
                            <p class="dj-result-funfact" id="djResultFunFact" hidden><i class="fa-solid fa-lightbulb"></i> <span id="djResultFunFactText"></span></p>
                            <span class="dj-result-rarity" id="djResultRarity" hidden></span>
                            <span class="dj-result-aihint" id="djResultAiHint" hidden><i class="fa-solid fa-plug-circle-xmark"></i> <?= htmlspecialchars($t['dj_ai_offline'], ENT_QUOTES) ?></span>

                            <div class="dj-result-actions">
                                <button class="btn-dj-keep" id="djResultKeepBtn"><i class="fa-solid fa-bookmark"></i> <?= htmlspecialchars($t['dj_keep_btn'], ENT_QUOTES) ?></button>
                                <button class="btn-dj-reset" id="djResultDiscardBtn"><?= htmlspecialchars($t['dj_discard_btn'], ENT_QUOTES) ?></button>
                            </div>
                        </div>
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
                                                <img id="matched-track-art" src="<?= BASE_URL ?>images/default-album.svg" alt="Track Art">
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
                                            <button class="dj-load-btn load-track-btn" data-track="<?= htmlspecialchars($track['track'], ENT_QUOTES) ?>" data-artist="<?= htmlspecialchars($track['artist'], ENT_QUOTES) ?>" data-image="<?= htmlspecialchars($track['image'] ?? '', ENT_QUOTES) ?>" title="<?= ($lang === 'vi') ? 'Nạp vào DJ Mixer' : 'Load to DJ Mixer' ?>">
                                                <i class="fa-solid fa-plus"></i>
                                            </button>
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
                                            <button class="dj-load-btn load-food-btn" data-food="<?= htmlspecialchars($food['food_item'], ENT_QUOTES) ?>" data-calories="<?= (int)($food['total_cal'] / $food['count_logged']) ?>" title="<?= ($lang === 'vi') ? 'Nạp vào DJ Mixer' : 'Load to DJ Mixer' ?>">
                                                <i class="fa-solid fa-plus"></i>
                                            </button>
                                        </div>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </div>
                        </section>
                    </div>
                </div>

                <!-- MIX HISTORY + ARCHETYPE COLLECTION -->
                <section class="beats-section mix-history-section">
                    <h2><i class="fa-solid fa-record-vinyl"></i> <?= htmlspecialchars($t['history_title'], ENT_QUOTES) ?></h2>

                    <div class="mix-collection">
                        <div class="mix-collection-stat">
                            <strong id="mixCollectionCount"><?= count($mixCollection) ?></strong>
                            <span><?= htmlspecialchars($t['collection_label'], ENT_QUOTES) ?></span>
                        </div>
                        <div class="mix-collection-chips" id="mixCollectionChips">
                            <?php foreach ($mixCollection as $col): ?>
                                <span class="archetype-chip" data-arch="<?= htmlspecialchars($col['archetype'], ENT_QUOTES) ?>"
                                    title="<?= (int) $col['best_score'] ?>% · x<?= (int) $col['hits'] ?>">
                                    <?= htmlspecialchars($col['archetype'], ENT_QUOTES) ?>
                                </span>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <ul class="mix-history-list" id="mixHistoryList">
                        <?php if (empty($mixHistory)): ?>
                            <li class="mix-history-empty" id="mixHistoryEmpty"><?= htmlspecialchars($t['history_empty'], ENT_QUOTES) ?></li>
                        <?php else: ?>
                            <?php foreach ($mixHistory as $m): ?>
                                <?php $band = $m['match_score'] >= 80 ? 'high' : ($m['match_score'] >= 50 ? 'mid' : 'low'); ?>
                                <li class="mhi-row" data-mix-id="<?= (int) $m['mix_id'] ?>" data-arch="<?= htmlspecialchars($m['archetype'] ?: $m['detected_vibe'], ENT_QUOTES) ?>">
                                    <div class="mhi-delete-layer"><i class="fa-solid fa-trash"></i></div>
                                    <div class="mix-history-item">
                                        <span class="mhi-score <?= $band ?>"><?= (int) $m['match_score'] ?><small>%</small></span>
                                        <div class="mhi-body">
                                            <div class="mhi-arch"><?= htmlspecialchars($m['archetype'] ?: $m['detected_vibe'], ENT_QUOTES) ?></div>
                                            <div class="mhi-combo">
                                                <i class="fa-solid fa-music"></i> <?= htmlspecialchars($m['track_name'], ENT_QUOTES) ?>
                                                <i class="fa-solid fa-arrows-left-right mhi-x"></i>
                                                <i class="fa-solid fa-utensils"></i> <?= htmlspecialchars($m['food_item'], ENT_QUOTES) ?>
                                            </div>
                                        </div>
                                        <?php if (!empty($m['rarity'])): ?>
                                            <span class="mhi-rarity"><?= htmlspecialchars($m['rarity'], ENT_QUOTES) ?></span>
                                        <?php endif; ?>
                                    </div>
                                </li>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </ul>
                </section>

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
            const KEEP_LABEL = <?= json_encode($t['dj_keep_btn']) ?>;
            const KEPT_TOAST = <?= json_encode($t['dj_kept_toast']) ?>;

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
            // =========================================================================
            // VANILLA JS DJ MIXER CONTROLLER
            // =========================================================================
            const djMixerBoard = document.getElementById('djMixerBoard');
            if (djMixerBoard) {
                const djTrackPlate = document.getElementById('djTrackPlate');
                const djTrackArtSlot = document.getElementById('djTrackArtSlot');
                const djTrackInfo = document.getElementById('djTrackInfo');
                const djTrackDeck = document.getElementById('djTrackDeck');
                const djTonearm = document.getElementById('djTonearm');
                
                const djFoodPlate = document.getElementById('djFoodPlate');
                const djFoodArtSlot = document.getElementById('djFoodArtSlot');
                const djFoodInfo = document.getElementById('djFoodInfo');
                const djFoodDeck = document.getElementById('djFoodDeck');
                
                const djMoodIndicator = document.getElementById('djMoodIndicator');
                const djMixBtn = document.getElementById('djMixBtn');
                const djTitleIcon = document.getElementById('djTitleIcon');
                
                const djResultOverlay = document.getElementById('djResultOverlay');
                const djResultCard = document.getElementById('djResultCard');
                const djScoreVal = document.getElementById('djScoreVal');
                const djResultArchetype = document.getElementById('djResultArchetype');
                const djResultComment = document.getElementById('djResultComment');
                const djResultVibe = document.getElementById('djResultVibe');
                const djResultTagline = document.getElementById('djResultTagline');
                const djResultRarity = document.getElementById('djResultRarity');
                const djResultAiHint = document.getElementById('djResultAiHint');

                // --- Reveal helpers (Wrapped-style) ---
                // Show an element only when there's content; optionally write into a child.
                const setOptional = (el, value, target) => {
                    if (!el) return;
                    const v = (value || '').toString().trim();
                    el.hidden = (v === '');
                    if (v !== '') (target || el).textContent = v;
                };
                // Count a number up to `to` over ~700ms.
                const countUp = (el, to) => {
                    if (!el) return;
                    const start = performance.now(), dur = 700;
                    const step = (now) => {
                        const k = Math.min(1, (now - start) / dur);
                        const val = Math.round(to * (1 - Math.pow(1 - k, 3))); // easeOutCubic
                        el.innerHTML = `${val}<span class="dj-score-pct">%</span>`;
                        if (k < 1) requestAnimationFrame(step);
                    };
                    requestAnimationFrame(step);
                };
                // Grow a score bar to `pct`% and show its value.
                const animateBar = (barId, valId, pct) => {
                    const bar = document.getElementById(barId), val = document.getElementById(valId);
                    const n = Math.max(0, Math.min(100, parseInt(pct, 10) || 0));
                    if (bar) bar.style.width = n + '%';
                    if (val) val.textContent = n + '%';
                };
                // Prepend a freshly created mix to the history list + grow the
                // collection when its archetype is newly unlocked (no reload).
                const escHtml = (s) => (s || '').toString().replace(/[&<>"]/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[c]));
                const addMixToHistory = (item) => {
                    const list = document.getElementById('mixHistoryList');
                    if (!list) return;
                    const empty = document.getElementById('mixHistoryEmpty');
                    if (empty) empty.remove();

                    const score = parseInt(item.match_score, 10) || 0;
                    const band = score >= 80 ? 'high' : (score >= 50 ? 'mid' : 'low');
                    const arch = item.archetype || item.detected_vibe || '';
                    const track = item.track_name || (loadedTrack && loadedTrack.track) || '';
                    const food = item.food_item || (loadedFood && loadedFood.food) || '';
                    const li = document.createElement('li');
                    li.className = 'mix-history-item is-new';
                    li.innerHTML =
                        `<span class="mhi-score ${band}">${score}<small>%</small></span>` +
                        `<div class="mhi-body"><div class="mhi-arch">${escHtml(arch)}</div>` +
                        `<div class="mhi-combo"><i class="fa-solid fa-music"></i> ${escHtml(track)}` +
                        ` <i class="fa-solid fa-arrows-left-right mhi-x"></i> <i class="fa-solid fa-utensils"></i> ${escHtml(food)}</div></div>` +
                        (item.rarity ? `<span class="mhi-rarity">${escHtml(item.rarity)}</span>` : '');
                    list.prepend(li);
                    while (list.children.length > 12) list.lastElementChild.remove();

                    // Collection: add a chip + bump count if this archetype is new
                    const chips = document.getElementById('mixCollectionChips');
                    if (chips && arch) {
                        const exists = [...chips.children].some(c => c.dataset.arch === arch);
                        if (!exists) {
                            const chip = document.createElement('span');
                            chip.className = 'archetype-chip is-new';
                            chip.dataset.arch = arch;
                            chip.title = score + '%';
                            chip.textContent = arch;
                            chips.prepend(chip);
                            const countEl = document.getElementById('mixCollectionCount');
                            if (countEl) countEl.textContent = (parseInt(countEl.textContent, 10) || 0) + 1;
                        }
                    }
                };
                const djResultKeepBtn = document.getElementById('djResultKeepBtn');
                const djResultDiscardBtn = document.getElementById('djResultDiscardBtn');
                
                const loadTrackBtns = document.querySelectorAll('.load-track-btn');
                const loadFoodBtns = document.querySelectorAll('.load-food-btn');
                
                let loadedTrack = null; // { name, artist, image }
                let loadedFood = null;  // { name, calories }
                
                // Sound synthesizer via Web Audio API (Very high fidelity gamified boop)
                const playSynthesizerSound = (type) => {
                    try {
                        const AudioContextClass = window.AudioContext || window.webkitAudioContext;
                        if (!AudioContextClass) return;
                        const ctx = new AudioContextClass();
                        const osc = ctx.createOscillator();
                        const gain = ctx.createGain();
                        
                        osc.connect(gain);
                        gain.connect(ctx.destination);
                        
                        if (type === 'load') {
                            // Upward cheerful slide
                            osc.type = 'sine';
                            osc.frequency.setValueAtTime(320, ctx.currentTime);
                            osc.frequency.exponentialRampToValueAtTime(640, ctx.currentTime + 0.12);
                            gain.gain.setValueAtTime(0.15, ctx.currentTime);
                            gain.gain.exponentialRampToValueAtTime(0.01, ctx.currentTime + 0.15);
                            osc.start();
                            osc.stop(ctx.currentTime + 0.15);
                        } else if (type === 'mix') {
                            // DJ scratch-like sweep
                            osc.type = 'sawtooth';
                            osc.frequency.setValueAtTime(150, ctx.currentTime);
                            osc.frequency.linearRampToValueAtTime(400, ctx.currentTime + 0.1);
                            osc.frequency.exponentialRampToValueAtTime(80, ctx.currentTime + 0.3);
                            gain.gain.setValueAtTime(0.12, ctx.currentTime);
                            gain.gain.exponentialRampToValueAtTime(0.01, ctx.currentTime + 0.35);
                            osc.start();
                            osc.stop(ctx.currentTime + 0.35);
                        } else if (type === 'success') {
                            // Double ding chime
                            osc.type = 'triangle';
                            osc.frequency.setValueAtTime(523.25, ctx.currentTime); // C5
                            gain.gain.setValueAtTime(0.15, ctx.currentTime);
                            gain.gain.exponentialRampToValueAtTime(0.01, ctx.currentTime + 0.15);
                            osc.start();
                            
                            const osc2 = ctx.createOscillator();
                            const gain2 = ctx.createGain();
                            osc2.connect(gain2);
                            gain2.connect(ctx.destination);
                            osc2.type = 'triangle';
                            osc2.frequency.setValueAtTime(659.25, ctx.currentTime + 0.1); // E5
                            gain2.gain.setValueAtTime(0.15, ctx.currentTime + 0.1);
                            gain2.gain.exponentialRampToValueAtTime(0.01, ctx.currentTime + 0.35);
                            osc2.start(ctx.currentTime + 0.1);
                            
                            osc.stop(ctx.currentTime + 0.15);
                            osc2.stop(ctx.currentTime + 0.35);
                        }
                    } catch (e) {
                        // Safe ignore if audio blocked/unsupported
                    }
                };
                
                const checkReadyToMix = () => {
                    if (loadedTrack && loadedFood) {
                        djMixBtn.removeAttribute('disabled');
                        djMixBtn.classList.add('glowing');
                        djMixerBoard.classList.add('ready');
                        djMoodIndicator.textContent = lang === 'vi' ? 'Bản mix đã sẵn sàng! 🎚️' : 'Mix ready to spin! 🎚️';
                    } else {
                        djMixBtn.setAttribute('disabled', 'true');
                        djMixBtn.classList.remove('glowing');
                        djMixerBoard.classList.remove('ready');
                        djMoodIndicator.textContent = lang === 'vi' ? 'Chờ nạp nhạc & thực đơn...' : 'Awaiting track & food selection...';
                    }
                };
                
                // Load song into the turntable
                loadTrackBtns.forEach(btn => {
                    btn.addEventListener('click', () => {
                        loadTrackBtns.forEach(b => b.classList.remove('loaded'));
                        btn.classList.add('loaded');
                        
                        loadedTrack = {
                            track: btn.dataset.track,
                            artist: btn.dataset.artist,
                            image: btn.dataset.image
                        };
                        
                        // Update UI Turntable
                        djTrackArtSlot.innerHTML = loadedTrack.image 
                            ? `<img src="${loadedTrack.image}" alt="Album Art">`
                            : `<i class="fa-solid fa-compact-disc"></i>`;
                            
                        djTrackInfo.innerHTML = `
                            <strong>${loadedTrack.track}</strong>
                            <span>${loadedTrack.artist}</span>
                        `;
                        
                        djTrackDeck.classList.add('loaded-track');
                        playSynthesizerSound('load');
                        
                        // Flash background color to indicate drop
                        djTrackPlate.style.transform = 'scale(0.95)';
                        setTimeout(() => { djTrackPlate.style.transform = ''; }, 150);
                        
                        checkReadyToMix();
                    });
                });
                
                // Load food into the plate
                loadFoodBtns.forEach(btn => {
                    btn.addEventListener('click', () => {
                        loadFoodBtns.forEach(b => b.classList.remove('loaded'));
                        btn.classList.add('loaded');
                        
                        loadedFood = {
                            food: btn.dataset.food,
                            calories: parseInt(btn.dataset.calories, 10)
                        };
                        
                        // Update UI Plate
                        djFoodArtSlot.innerHTML = `<i class="fa-solid fa-bowl-food" style="font-size: 34px; color: var(--color-primary);"></i>`;
                        djFoodInfo.innerHTML = `
                            <strong>${loadedFood.food}</strong>
                            <span>~${loadedFood.calories} kcal</span>
                        `;
                        
                        djFoodDeck.classList.add('loaded-food');
                        playSynthesizerSound('load');
                        
                        // Flash plate drop
                        djFoodPlate.style.transform = 'scale(0.95)';
                        setTimeout(() => { djFoodPlate.style.transform = ''; }, 150);
                        
                        checkReadyToMix();
                    });
                });
                
                // Canvas particle floating system (HTML5 floating music notes)
                const canvas = document.getElementById('djCanvas');
                const ctx = canvas.getContext('2d');
                let animationId = null;
                let particles = [];
                
                const resizeCanvas = () => {
                    canvas.width = djMixerBoard.offsetWidth;
                    canvas.height = djMixerBoard.offsetHeight;
                };
                window.addEventListener('resize', resizeCanvas);
                resizeCanvas();
                
                class Particle {
                    constructor() {
                        this.x = Math.random() * canvas.width;
                        this.y = canvas.height + 20;
                        this.size = Math.random() * 12 + 8;
                        this.speedY = -(Math.random() * 2 + 1);
                        this.speedX = Math.random() * 2 - 1;
                        this.opacity = 1;
                        this.fade = Math.random() * 0.015 + 0.005;
                        this.char = ['♫', '♪', '♬', '♩', '✨', '🎵'][Math.floor(Math.random() * 6)];
                        // Colors corresponding to moods
                        const colors = ['#1cb0f6', '#58cc02', '#ff9600', '#a855f7'];
                        this.color = colors[Math.floor(Math.random() * colors.length)];
                    }
                    update() {
                        this.y += this.speedY;
                        this.x += this.speedX;
                        this.opacity -= this.fade;
                    }
                    draw() {
                        ctx.save();
                        ctx.globalAlpha = this.opacity;
                        ctx.fillStyle = this.color;
                        ctx.font = `${this.size}px Outfit, Inter, sans-serif`;
                        ctx.fillText(this.char, this.x, this.y);
                        ctx.restore();
                    }
                }
                
                const animateParticles = () => {
                    ctx.clearRect(0, 0, canvas.width, canvas.height);
                    
                    if (Math.random() < 0.25) {
                        particles.push(new Particle());
                    }
                    
                    particles.forEach((p, idx) => {
                        p.update();
                        p.draw();
                        if (p.opacity <= 0 || p.y < -20) {
                            particles.splice(idx, 1);
                        }
                    });
                    
                    animationId = requestAnimationFrame(animateParticles);
                };
                
                const startVisualEffects = () => {
                    djTrackDeck.classList.add('playing');
                    djFoodDeck.classList.add('playing');
                    djMixerBoard.classList.add('mixing');
                    djTitleIcon.classList.add('fa-spin');
                    djTitleIcon.style.animationDuration = '1s';
                    djMoodIndicator.textContent = lang === 'vi' ? 'Đang phân tích vibe nhạc... 🧠' : 'Analyzing track vibe... 🧠';
                    particles = [];
                    animateParticles();
                };

                const stopVisualEffects = () => {
                    djTrackDeck.classList.remove('playing');
                    djFoodDeck.classList.remove('playing');
                    djMixerBoard.classList.remove('mixing');
                    djTitleIcon.classList.remove('fa-spin');
                    if (animationId) {
                        cancelAnimationFrame(animationId);
                        ctx.clearRect(0, 0, canvas.width, canvas.height);
                    }
                };
                
                // Mix Button Click Trigger
                djMixBtn.addEventListener('click', () => {
                    if (!loadedTrack || !loadedFood) return;
                    
                    // Disable triggers during mixing
                    djMixBtn.setAttribute('disabled', 'true');
                    loadTrackBtns.forEach(b => b.setAttribute('disabled', 'true'));
                    loadFoodBtns.forEach(b => b.setAttribute('disabled', 'true'));
                    
                    playSynthesizerSound('mix');
                    startVisualEffects();
                    
                    // Prepare POST payload
                    const fd = new FormData();
                    fd.append('track_name', loadedTrack.track);
                    fd.append('artist_name', loadedTrack.artist);
                    fd.append('food_item', loadedFood.food);
                    fd.append('calories', loadedFood.calories);
                    
                    const startTime = Date.now();
                    
                    fetch('handlers/beats_mixer.php', { method: 'POST', body: fd })
                        .then(r => r.json())
                        .then(data => {
                            // Ensure the turntable spins for at least 1.8 seconds for suspense!
                            const elapsedTime = Date.now() - startTime;
                            const delay = Math.max(0, 1800 - elapsedTime);
                            
                            setTimeout(() => {
                                stopVisualEffects();
                                
                                if (data.ok) {
                                    // Headline score & styling (ring = average of the 3 dimensions)
                                    const score = parseInt(data.match_score, 10) || 0;

                                    djResultCard.classList.remove('score-high', 'score-mid', 'score-low');
                                    if (score >= 80) {
                                        djResultCard.classList.add('score-high');
                                    } else if (score >= 50) {
                                        djResultCard.classList.add('score-mid');
                                    } else {
                                        djResultCard.classList.add('score-low');
                                    }

                                    // Persona + vibe chip + tagline
                                    djResultArchetype.textContent = data.archetype || data.detected_vibe || '';
                                    setOptional(djResultVibe, data.detected_vibe);
                                    setOptional(djResultTagline, data.tagline);

                                    // Verdict + fun fact + rarity
                                    djResultComment.textContent = data.verdict || data.comment || '';
                                    setOptional(document.getElementById('djResultFunFact'), data.fun_fact, document.getElementById('djResultFunFactText'));
                                    setOptional(djResultRarity, data.rarity);

                                    // Subtle hint when the witty copy came from the offline fallback
                                    djResultAiHint.hidden = (data.ai !== false);

                                    // Reset the Keep control for this new result (not saved yet)
                                    if (djResultKeepBtn) {
                                        djResultKeepBtn.disabled = false;
                                        djResultKeepBtn.classList.remove('kept');
                                        djResultKeepBtn.innerHTML = `<i class="fa-solid fa-bookmark"></i> ${KEEP_LABEL}`;
                                    }

                                    // Open overlay, then animate the reveal (Wrapped-style)
                                    djResultOverlay.classList.add('active');
                                    playSynthesizerSound('success');

                                    const scores = data.scores || {};
                                    countUp(djScoreVal, score);
                                    setTimeout(() => animateBar('djBarEnergy', 'djBarEnergyVal', scores.energy_sync), 250);
                                    setTimeout(() => animateBar('djBarComfort', 'djBarComfortVal', scores.comfort), 450);
                                    setTimeout(() => animateBar('djBarChaos', 'djBarChaosVal', scores.chaos), 650);
                                    // Note: mixes are saved only when the user taps "Keep".
                                } else {
                                    alert(data.error || 'Mixing failed');
                                    djMixBtn.removeAttribute('disabled');
                                }
                                
                                // Re-enable selector controls
                                loadTrackBtns.forEach(b => b.removeAttribute('disabled'));
                                loadFoodBtns.forEach(b => b.removeAttribute('disabled'));
                            }, delay);
                        })
                        .catch(err => {
                            console.error('Mix API error:', err);
                            stopVisualEffects();
                            alert(lang === 'vi' ? 'Không thể kết nối máy chủ AI.' : 'Failed to reach AI mixer server.');
                            djMixBtn.removeAttribute('disabled');
                            loadTrackBtns.forEach(b => b.removeAttribute('disabled'));
                            loadFoodBtns.forEach(b => b.removeAttribute('disabled'));
                        });
                });
                
                const closeResult = () => {
                    djResultOverlay.classList.remove('active');
                    playSynthesizerSound('load');
                    checkReadyToMix();
                };

                // KEEP: persist the pending mix server-side, then add it live.
                djResultKeepBtn.addEventListener('click', () => {
                    djResultKeepBtn.disabled = true;
                    fetch('handlers/beats_mix_save.php', { method: 'POST' })
                        .then(r => r.json())
                        .then(res => {
                            if (res.ok && res.saved && res.item) {
                                addMixToHistory(res.item);
                                showBeatsToast(KEPT_TOAST);
                                playSynthesizerSound('success');
                            }
                            closeResult();
                        })
                        .catch(() => { djResultKeepBtn.disabled = false; });
                });

                // DISCARD: close without saving (the pending mix is simply dropped).
                djResultDiscardBtn.addEventListener('click', closeResult);
            }

            // Lightweight toast for "kept" confirmation.
            function showBeatsToast(msg) {
                let el = document.getElementById('beatsToast');
                if (!el) {
                    el = document.createElement('div');
                    el.id = 'beatsToast';
                    el.className = 'beats-toast';
                    document.body.appendChild(el);
                }
                el.textContent = msg;
                el.classList.add('show');
                clearTimeout(el._t);
                el._t = setTimeout(() => el.classList.remove('show'), 2600);
            }
        });
    </script>
</body>
</html>
