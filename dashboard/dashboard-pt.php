<?php
// dashboard/dashboard-pt.php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/../include/init.php';
require_once __DIR__ . '/../include/csrf.php';
require_once __DIR__ . '/../include/handlers/log_attempt.php';

$lang = current_locale();

$activePage   = 'pt_dashboard';
$activeHeader = 'dashboard';
$bodyClass    = 'page-pt-dashboard';
$displayUser  = $isLoggedIn ? $user['user_name'] : 'Guest';

// Redirect if not logged in or not a PT
if (!$isLoggedIn || ($user['role'] ?? 'regular') !== 'pt') {
    header("Location: " . BASE_URL . "login.php");
    exit();
}

$me = (int) $user['user_id'];
log_attempt($pdo, $me, 'view', 'PT opened PT Dashboard', 'dashboard', null);

// 1. Fetch connected clients
$clients = [];
try {
    $stmt = $pdo->prepare("
        SELECT 
            u.user_id, u.user_name, u.first_name, u.last_name, u.profile_image,
            us.logging_streak,
            (SELECT calorie_goal FROM userGoal WHERE user_id = u.user_id ORDER BY date_set DESC LIMIT 1) AS calorie_goal,
            (SELECT weight FROM weight_log WHERE user_id = u.user_id ORDER BY date_logged DESC LIMIT 1) AS last_weight,
            COALESCE((SELECT SUM(calories) FROM intakeLog WHERE user_id = u.user_id AND DATE(date_intake) = CURDATE()), 0) AS calories_today,
            COALESCE((SELECT SUM(protein) FROM intakeLog WHERE user_id = u.user_id AND DATE(date_intake) = CURDATE()), 0) AS protein_today,
            COALESCE((SELECT SUM(carbs) FROM intakeLog WHERE user_id = u.user_id AND DATE(date_intake) = CURDATE()), 0) AS carbs_today,
            COALESCE((SELECT SUM(fat) FROM intakeLog WHERE user_id = u.user_id AND DATE(date_intake) = CURDATE()), 0) AS fat_today
        FROM trainer_client tc
        JOIN user u ON tc.client_id = u.user_id
        JOIN userStatus us ON u.user_id = us.user_id
        WHERE tc.trainer_id = ? AND tc.status = 'accepted'
        ORDER BY u.first_name ASC
    ");
    $stmt->execute([$me]);
    $clients = $stmt->fetchAll();
} catch (PDOException $e) {
    error_log("PT Load Clients Error: " . $e->getMessage());
}

// "Needs attention" tally for the hero: how many clients have logged nothing today.
$clientsNeedLog = 0;
foreach ($clients as $c) {
    if ((int) $c['calories_today'] <= 0) {
        $clientsNeedLog++;
    }
}

// 2. Fetch pending requests
$requests = [];
try {
    $stmt = $pdo->prepare("
        SELECT 
            tc.id AS request_id, tc.created_at,
            u.user_id, u.user_name, u.first_name, u.last_name, u.profile_image,
            us.logging_streak
        FROM trainer_client tc
        JOIN user u ON tc.client_id = u.user_id
        JOIN userStatus us ON u.user_id = us.user_id
        WHERE tc.trainer_id = ? AND tc.status = 'pending'
        ORDER BY tc.created_at DESC
    ");
    $stmt->execute([$me]);
    $requests = $stmt->fetchAll();
} catch (PDOException $e) {
    error_log("PT Load Requests Error: " . $e->getMessage());
}

// 3. Fetch today's intake logs for all active clients
$clientLogs = [];
if (!empty($clients)) {
    $clientIds = array_column($clients, 'user_id');
    $placeholders = implode(',', array_fill(0, count($clientIds), '?'));
    try {
        $stmt = $pdo->prepare("
            SELECT * 
            FROM intakeLog 
            WHERE user_id IN ($placeholders) AND DATE(date_intake) = CURDATE()
            ORDER BY date_intake DESC
        ");
        $stmt->execute($clientIds);
        while ($row = $stmt->fetch()) {
            $clientLogs[$row['user_id']][] = $row;
        }
    } catch (PDOException $e) {
        error_log("PT Load Client Logs Error: " . $e->getMessage());
    }
}

// 4. Fetch ALL feedback notes from PT to clients, keyed by client + date so the
//    PT can write/review advice for any past day (Task #1: per-day + history).
//    Shape: $clientFeedback[client_id]['YYYY-MM-DD'] = content.
$clientFeedback = [];
try {
    $stmt = $pdo->prepare("
        SELECT client_id, date_for, content
        FROM pt_feedback
        WHERE trainer_id = ?
        ORDER BY date_for DESC
    ");
    $stmt->execute([$me]);
    while ($row = $stmt->fetch()) {
        $clientFeedback[$row['client_id']][$row['date_for']] = $row['content'];
    }
} catch (PDOException $e) {
    error_log("PT Load Feedback Error: " . $e->getMessage());
}

// 5. Fetch last-7-day daily calorie/protein totals per client (Task #2: trend).
//    Shape: $clientTrends[client_id]['YYYY-MM-DD'] = ['cal' => x, 'pro' => y].
$clientTrends = [];
if (!empty($clients)) {
    $clientIds = array_column($clients, 'user_id');
    $placeholders = implode(',', array_fill(0, count($clientIds), '?'));
    try {
        $stmt = $pdo->prepare("
            SELECT user_id, DATE(date_intake) AS d,
                   SUM(calories) AS cal, SUM(protein) AS pro
            FROM intakeLog
            WHERE user_id IN ($placeholders)
              AND DATE(date_intake) >= DATE_SUB(CURDATE(), INTERVAL 6 DAY)
            GROUP BY user_id, DATE(date_intake)
        ");
        $stmt->execute($clientIds);
        while ($row = $stmt->fetch()) {
            $clientTrends[$row['user_id']][$row['d']] = [
                'cal' => (float) $row['cal'],
                'pro' => (float) $row['pro'],
            ];
        }
    } catch (PDOException $e) {
        error_log("PT Load Trends Error: " . $e->getMessage());
    }
}

// 6. Recent client-activity feed for the PT (Task #4): meals + weigh-ins across
//    all linked clients in the last 7 days, newest first.
$activityFeed = [];
$clientNames = [];
foreach ($clients as $c) {
    $clientNames[(int) $c['user_id']] = trim($c['first_name'] . ' ' . $c['last_name']);
}
if (!empty($clients)) {
    $clientIds = array_column($clients, 'user_id');
    $ph = implode(',', array_fill(0, count($clientIds), '?'));
    try {
        $stmt = $pdo->prepare("
            SELECT user_id, 'meal' AS kind, food_item AS label, calories AS amount, date_intake AS at_time
            FROM intakeLog
            WHERE user_id IN ($ph) AND date_intake >= DATE_SUB(NOW(), INTERVAL 7 DAY)
            UNION ALL
            SELECT user_id, 'weight' AS kind, NULL AS label, weight AS amount, date_logged AS at_time
            FROM weight_log
            WHERE user_id IN ($ph) AND date_logged >= DATE_SUB(NOW(), INTERVAL 7 DAY)
            ORDER BY at_time DESC
            LIMIT 30
        ");
        $stmt->execute(array_merge($clientIds, $clientIds));
        $activityFeed = $stmt->fetchAll();
    } catch (PDOException $e) {
        error_log("PT Activity Feed Error: " . $e->getMessage());
    }
}

// 7. Unread chat count per client, so the PT can see at a glance WHO messaged
//    (instead of opening each client). Shape: $clientUnread[client_id] = count.
$clientUnread = [];
$clientUnreadTotal = 0;
try {
    $stmt = $pdo->prepare("
        SELECT t.client_id, COUNT(*) AS unread
        FROM pt_message m
        JOIN pt_thread t ON m.thread_id = t.thread_id
        WHERE t.trainer_id = ? AND m.sender_role = 'client' AND m.seen_at IS NULL
        GROUP BY t.client_id
    ");
    $stmt->execute([$me]);
    while ($row = $stmt->fetch()) {
        $clientUnread[(int) $row['client_id']] = (int) $row['unread'];
        $clientUnreadTotal += (int) $row['unread'];
    }
} catch (PDOException $e) {
    error_log("PT Load Unread Error: " . $e->getMessage());
}

// Float clients who messaged to the top so the PT spots them without scanning.
// Manual partition (not usort) keeps name order within each group on PHP 7.x too.
if (!empty($clientUnread)) {
    $withMsg = [];
    $withoutMsg = [];
    foreach ($clients as $c) {
        if (($clientUnread[(int) $c['user_id']] ?? 0) > 0) {
            $withMsg[] = $c;
        } else {
            $withoutMsg[] = $c;
        }
    }
    $clients = array_merge($withMsg, $withoutMsg);
}

$csrfToken = csrf_token();
?>
<!DOCTYPE html>
<html lang="<?= html_lang_attr() ?>" data-theme="<?= htmlspecialchars($_SESSION['user']['theme_preference'] ?? 'system', ENT_QUOTES) ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= ($lang === 'vi') ? 'PT Dashboard - Quản lý Học viên' : 'PT Dashboard - Client Management' ?></title>
    <?php
    $pageComponents = ['sidebar', 'fab'];
    $pageCss = ['css/dashboard.css', 'css/pages/dashboard-pt.css', 'css/components/pt-chat.css'];
    include PROJECT_ROOT . 'views/head_css.php';
    ?>
    <script src="https://kit.fontawesome.com/b94f65ead2.js" crossorigin="anonymous"></script>
</head>
<body class="<?= htmlspecialchars($bodyClass, ENT_QUOTES) ?>">
    <?php include PROJECT_ROOT . 'views/header.php'; ?>
    <?php include PROJECT_ROOT . 'dashboard/views/sidebar.php'; ?>

    <main class="dashboard-content">
        <div class="pt-container">
            <!-- Header Hero -->
            <section class="pt-hero">
                <div class="pt-hero__copy">
                    <span class="pt-kicker"><i class="fas fa-dumbbell"></i> <?= ($lang === 'vi') ? 'Huấn luyện viên cá nhân' : 'Personal Trainer' ?></span>
                    <h1><?= ($lang === 'vi') ? 'PT Dashboard' : 'PT Dashboard' ?></h1>
                    <p><?= ($lang === 'vi') ? 'Theo dõi tiến trình dinh dưỡng, lượng calo nạp vào và kiểm tra ảnh bữa ăn thực tế của học viên.' : 'Monitor nutritional progress, calorie intake, and view client meal photos in real-time.' ?></p>
                </div>
                <div class="pt-statstrip">
                    <span class="pt-stat">
                        <i class="fas fa-users"></i> <strong><?= count($clients) ?></strong> <?= ($lang === 'vi') ? 'học viên' : 'clients' ?>
                    </span>
                    <span class="pt-stat <?= $clientUnreadTotal > 0 ? 'pt-stat--msg' : '' ?>" id="hero-msg-stat">
                        <i class="fas fa-comment-dots"></i> <strong id="hero-msg-count"><?= $clientUnreadTotal ?></strong> <?= ($lang === 'vi') ? 'tin mới' : 'new' ?>
                    </span>
                    <span class="pt-stat <?= $clientsNeedLog > 0 ? 'pt-stat--alert' : '' ?>">
                        <i class="fas fa-bell"></i> <strong><?= $clientsNeedLog ?></strong> <?= ($lang === 'vi') ? 'chưa ghi' : 'no log' ?>
                    </span>
                    <span class="pt-stat">
                        <i class="fas fa-user-plus"></i> <strong id="pending-badge-hero"><?= count($requests) ?></strong> <?= ($lang === 'vi') ? 'chờ' : 'pending' ?>
                    </span>
                </div>
            </section>

            <!-- Navigation Tabs -->
            <nav class="pt-tabs" role="tablist">
                <button class="pt-tab active" role="tab" data-tab="clients">
                    <i class="fas fa-users"></i> <?= ($lang === 'vi') ? 'Danh sách học viên' : 'Active Clients' ?>
                    <span class="pt-tab__badge pt-tab__badge--muted"><?= count($clients) ?></span>
                </button>
                <button class="pt-tab" role="tab" data-tab="activity">
                    <i class="fas fa-wave-square"></i> <?= ($lang === 'vi') ? 'Hoạt động' : 'Activity' ?>
                </button>
                <button class="pt-tab" role="tab" data-tab="requests">
                    <i class="fas fa-user-plus"></i> <?= ($lang === 'vi') ? 'Yêu cầu kết nối' : 'Connection Requests' ?>
                    <?php if (count($requests) > 0): ?>
                        <span class="pt-tab__badge pt-tab__badge--alert" id="pending-badge-tab"><?= count($requests) ?></span>
                    <?php endif; ?>
                </button>
            </nav>

            <!-- ============================== ACTIVE CLIENTS ============================== -->
            <section class="pt-panel" data-panel="clients">
                <div class="pt-panel-layout">
                    <!-- Left: Clients Bento Grid & Search -->
                    <div class="pt-panel-main">
                        <div class="pt-search-bar" style="margin-bottom: 24px; position: relative;">
                            <i class="fas fa-search" style="position: absolute; left: 16px; top: 50%; transform: translateY(-50%); color: var(--color-text-secondary); pointer-events: none;"></i>
                            <input type="search" id="clientSearchInput" placeholder="<?= ($lang === 'vi') ? 'Tìm kiếm học viên theo tên...' : 'Search clients by name...' ?>" style="width: 100%; border: 2px solid var(--color-border); border-radius: var(--radius-md); padding: 12px 16px 12px 48px; background: var(--color-surface); color: var(--color-text); font-size: 14px; outline: none; transition: all var(--transition-base);">
                        </div>

                        <?php if (empty($clients)): ?>
                            <div class="pt-empty">
                                <div class="pt-empty__icon"><i class="fas fa-user-friends"></i></div>
                                <h3><?= ($lang === 'vi') ? 'Chưa có học viên nào liên kết' : 'No clients connected yet' ?></h3>
                                <p><?= ($lang === 'vi') ? 'Hãy chia sẻ tên Handle (ví dụ: PT_Name#1234) để học viên có thể tìm và gửi yêu cầu kết nối từ phần cài đặt của họ.' : 'Share your username handle (e.g. PT_Name#1234) with your clients so they can request to link from their profile settings.' ?></p>
                            </div>
                        <?php else: ?>
                            <div class="pt-bento-grid" id="clientsGrid">
                                <?php foreach ($clients as $c):
                                    $cid = (int) $c['user_id'];
                                    $unread = $clientUnread[$cid] ?? 0;
                                    $name = htmlspecialchars($c['first_name'] . ' ' . $c['last_name'], ENT_QUOTES);
                                    $handle = htmlspecialchars($c['user_name'], ENT_QUOTES);
                                    $avatar = $c['profile_image'] ?? '';
                                    $streak = (int) $c['logging_streak'];
                                    $weight = $c['last_weight'] ? (float) $c['last_weight'] : null;
                                    $calGoal = (int) $c['calorie_goal'];
                                    $calToday = (int) $c['calories_today'];
                                    $pct = $calGoal > 0 ? min(100, round(($calToday / $calGoal) * 100)) : 0;
                                    
                                    // Macros
                                    $pToday = (float) $c['protein_today'];
                                    $cToday = (float) $c['carbs_today'];
                                    $fToday = (float) $c['fat_today'];
                                    $pGoal  = $calGoal > 0 ? round(($calGoal * 0.3) / 4) : 0; // standard estimation

                                    // "Needs attention" status: nothing logged today (streak at risk if
                                    // they had one), or meaningfully over the calorie goal.
                                    $rawPct   = $calGoal > 0 ? round(($calToday / $calGoal) * 100) : 0;
                                    $needsLog = ($calToday <= 0);
                                    $over     = (!$needsLog && $calGoal > 0 && $rawPct >= 110);
                                    $cardMod  = $needsLog ? ' client-card--alert' : ($over ? ' client-card--warn' : '');
                                ?>
                                    <article class="client-card<?= $cardMod ?>" data-user-id="<?= $cid ?>" data-name="<?= strtolower($name) ?>">
                                        <div class="client-card__header">
                                            <div class="client-card__avatar">
                                                <?php if ($avatar): ?>
                                                    <img src="<?= BASE_URL . htmlspecialchars($avatar, ENT_QUOTES) ?>" alt="">
                                                <?php else: ?>
                                                    <i class="fas fa-user"></i>
                                                <?php endif; ?>
                                            </div>
                                            <div class="client-card__title">
                                                <h3><?= $name ?></h3>
                                                <span><?= $handle ?></span>
                                            </div>
                                            <span class="client-card__streak" title="Streak"><i class="fas fa-fire"></i> <?= $streak ?>d</span>
                                            <?php if ($unread > 0): ?>
                                                <span class="client-card__unread" title="<?= ($lang === 'vi') ? 'Tin nhắn chưa đọc' : 'Unread messages' ?>"><i class="fas fa-comment-dots"></i> <?= $unread ?></span>
                                            <?php endif; ?>
                                        </div>

                                        <!-- Progress Bar -->
                                        <div class="client-progress">
                                            <div class="client-progress__labels">
                                                <span><strong><?= number_format($calToday) ?></strong> / <?= $calGoal > 0 ? number_format($calGoal) : '—' ?> kcal</span>
                                                <span class="pct-chip <?= $pct >= 100 ? 'over' : '' ?>"><?= $pct ?>%</span>
                                            </div>
                                            <div class="client-progress__bar">
                                                <div class="client-progress__fill <?= $pct >= 100 ? 'over' : '' ?>" style="width: <?= $pct ?>%;"></div>
                                            </div>
                                        </div>

                                        <!-- Small Bento Metrics -->
                                        <div class="client-metrics-row">
                                            <div class="client-mini-metric">
                                                <span class="label">Protein</span>
                                                <strong><?= round($pToday) ?>g</strong>
                                            </div>
                                            <div class="client-mini-metric">
                                                <span class="label"><?= ($lang === 'vi') ? 'Cân nặng' : 'Weight' ?></span>
                                                <strong><?= $weight ? $weight . ' kg' : '—' ?></strong>
                                            </div>
                                        </div>

                                        <?php if ($needsLog): ?>
                                            <div class="client-card__flag client-card__flag--alert">
                                                <i class="fas fa-bell"></i>
                                                <?= $streak > 0
                                                    ? (($lang === 'vi') ? 'Chưa ghi — streak sắp đứt' : 'No log — streak at risk')
                                                    : (($lang === 'vi') ? 'Chưa ghi hôm nay' : 'No log today') ?>
                                            </div>
                                        <?php elseif ($over): ?>
                                            <div class="client-card__flag client-card__flag--warn">
                                                <i class="fas fa-triangle-exclamation"></i>
                                                <?= ($lang === 'vi') ? 'Vượt calo (' . $rawPct . '%)' : 'Over calories (' . $rawPct . '%)' ?>
                                            </div>
                                        <?php endif; ?>
                                    </article>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Client detail drawer: bottom-sheet on mobile, side drawer on desktop -->
                <div class="pt-drawer" id="clientDrawer" hidden>
                    <div class="pt-drawer__backdrop" data-drawer-close></div>
                    <div class="pt-drawer__panel" role="dialog" aria-modal="true" aria-label="<?= ($lang === 'vi') ? 'Chi tiết học viên' : 'Client details' ?>">
                        <div class="pt-details-card" id="clientDetailsCard">
                            <button class="close-side-details" onclick="closeClientDetails()"><i class="fas fa-times"></i></button>
                            
                            <div class="details-header">
                                <div class="details-avatar" id="det-avatar"></div>
                                <div>
                                    <h2 class="details-name" id="det-name"></h2>
                                    <span class="details-handle" id="det-handle"></span>
                                </div>
                            </div>

                            <!-- 7-Day Trend Section (Task #2) -->
                            <div class="details-trend-section">
                                <h4 class="details-section-title"><i class="fas fa-chart-line ic-accent"></i> <?= ($lang === 'vi') ? 'Xu hướng 7 ngày' : '7-Day Trend' ?></h4>
                                <div class="details-trend-summary" id="det-trend-summary"></div>
                                <div class="details-trend-chart" id="det-trend-chart"></div>
                            </div>

                            <!-- Internal tabs: keep header + trend fixed, swap the rest to cut scrolling -->
                            <div class="details-tabs" role="tablist">
                                <button type="button" class="details-tab active" data-pane="diary"><i class="fas fa-utensils"></i> <?= ($lang === 'vi') ? 'Nhật ký' : 'Diary' ?></button>
                                <button type="button" class="details-tab" data-pane="chat"><i class="fas fa-comments"></i> <?= ($lang === 'vi') ? 'Chat' : 'Chat' ?></button>
                                <button type="button" class="details-tab" data-pane="feedback"><i class="fas fa-comment-medical"></i> <?= ($lang === 'vi') ? 'Góp ý' : 'Feedback' ?></button>
                            </div>

                            <!-- Meal Logs & Photos Section -->
                            <div class="details-tabpane details-logs-section" data-pane="diary">
                                <div class="details-logs" id="det-logs-container"></div>
                            </div>

                            <!-- Two-way Chat with Client (Task #3) -->
                            <div class="details-tabpane details-chat-section" data-pane="chat" hidden>
                                <div class="pt-chat" id="ptClientChat"
                                     data-endpoint="<?= BASE_URL ?>dashboard/handlers/pt_chat.php"
                                     data-csrf="<?= htmlspecialchars($csrfToken, ENT_QUOTES) ?>"
                                     data-self-role="trainer"
                                     data-empty-text="<?= ($lang === 'vi') ? 'Chưa có tin nhắn. Hãy bắt đầu trò chuyện!' : 'No messages yet. Start the conversation!' ?>">
                                    <div class="pt-chat__messages"></div>
                                    <form class="pt-chat__form">
                                        <textarea class="pt-chat__input" rows="1" placeholder="<?= ($lang === 'vi') ? 'Nhắn cho học viên...' : 'Message your client...' ?>"></textarea>
                                        <button type="submit"><i class="fas fa-paper-plane"></i></button>
                                    </form>
                                </div>
                            </div>

                            <!-- PT Feedback / Nutrition Advice Form -->
                            <div class="details-tabpane details-feedback-section" data-pane="feedback" hidden>
                                <h4 class="details-section-title"><i class="fas fa-comment-medical ic-secondary"></i> <?= ($lang === 'vi') ? 'Góp ý dinh dưỡng của PT' : 'Nutritionist Feedback' ?></h4>
                                <form id="feedbackForm" onsubmit="saveClientFeedback(event)">
                                    <input type="hidden" name="client_id" id="det-client-id">

                                    <!-- Day selector: write/review advice for any past day (max = today) -->
                                    <label class="feedback-label" for="det-date-for"><i class="fas fa-calendar-day"></i> <?= ($lang === 'vi') ? 'Góp ý cho ngày' : 'Feedback for day' ?></label>
                                    <input type="date" class="feedback-input" name="date_for" id="det-date-for" value="<?= date('Y-m-d') ?>" max="<?= date('Y-m-d') ?>">

                                    <!-- History of days this PT has already left advice for this client -->
                                    <div class="feedback-history" id="det-feedback-history"></div>

                                    <textarea class="feedback-textarea" name="content" id="det-feedback-content" rows="4" placeholder="<?= ($lang === 'vi') ? 'Viết lời động viên hoặc nhận xét về bữa ăn ngày đã chọn (ví dụ: cần tăng đạm, hạn chế béo)...' : 'Write comments, advice or motivational words about the selected day (e.g. increase protein, reduce fats)...' ?>"></textarea>
                                    <div class="feedback-actions">
                                        <button type="button" class="feedback-btn feedback-btn--ghost" onclick="disconnectClient()"><i class="fas fa-unlink"></i> <?= ($lang === 'vi') ? 'Hủy liên kết' : 'Disconnect' ?></button>
                                        <button type="submit" class="feedback-btn feedback-btn--primary"><i class="fas fa-save"></i> <?= ($lang === 'vi') ? 'Lưu góp ý' : 'Save Feedback' ?></button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
            </section>

            <!-- ============================== ACTIVITY FEED ============================== -->
            <section class="pt-panel" data-panel="activity" hidden>
                <?php if (empty($activityFeed)): ?>
                    <div class="pt-empty">
                        <div class="pt-empty__icon"><i class="fas fa-wave-square"></i></div>
                        <h3><?= ($lang === 'vi') ? 'Chưa có hoạt động gần đây' : 'No recent activity' ?></h3>
                        <p><?= ($lang === 'vi') ? 'Khi học viên ghi nhật ký bữa ăn hoặc cập nhật cân nặng, hoạt động sẽ hiện tại đây.' : 'When your clients log meals or update their weight, it shows up here.' ?></p>
                    </div>
                <?php else: ?>
                    <div class="activity-feed" style="display: flex; flex-direction: column; gap: 10px; max-width: 760px;">
                        <?php foreach ($activityFeed as $a):
                            $aUid  = (int) $a['user_id'];
                            $aName = htmlspecialchars($clientNames[$aUid] ?? ('#' . $aUid), ENT_QUOTES);
                            $isMeal = ($a['kind'] === 'meal');
                            $icon  = $isMeal ? 'fa-utensils' : 'fa-weight-scale';
                            $iconColor = $isMeal ? 'var(--color-primary)' : 'var(--color-secondary)';
                            $ts = strtotime($a['at_time']);
                            if ($isMeal) {
                                $detail = '<strong>' . htmlspecialchars($a['label'], ENT_QUOTES) . '</strong> · ' . number_format((int) $a['amount']) . ' kcal';
                                $verb = ($lang === 'vi') ? 'đã ghi' : 'logged';
                            } else {
                                $detail = '<strong>' . rtrim(rtrim(number_format((float) $a['amount'], 1), '0'), '.') . ' kg</strong>';
                                $verb = ($lang === 'vi') ? 'cập nhật cân nặng' : 'updated weight';
                            }
                        ?>
                            <article style="display: flex; align-items: center; gap: 14px; background: var(--color-surface); border: 2px solid var(--color-border); border-radius: var(--radius-md); padding: 12px 16px;">
                                <div style="width: 38px; height: 38px; border-radius: 50%; flex-shrink: 0; display: flex; align-items: center; justify-content: center; background: var(--color-surface-alt); border: 2px solid var(--color-border);">
                                    <i class="fas <?= $icon ?>" style="color: <?= $iconColor ?>;"></i>
                                </div>
                                <div style="flex: 1; min-width: 0;">
                                    <div style="font-size: 14px; color: var(--color-text);"><strong><?= $aName ?></strong> <?= $verb ?> <?= $detail ?></div>
                                </div>
                                <time style="font-size: 12px; color: var(--color-text-secondary); white-space: nowrap;"><?= date('j/n H:i', $ts) ?></time>
                            </article>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </section>

            <!-- ============================== CONNECTION REQUESTS ============================== -->
            <section class="pt-panel" data-panel="requests" hidden>
                <?php if (empty($requests)): ?>
                    <div class="pt-empty">
                        <div class="pt-empty__icon"><i class="fas fa-user-lock"></i></div>
                        <h3><?= ($lang === 'vi') ? 'Không có yêu cầu kết nối nào' : 'No connection requests' ?></h3>
                        <p><?= ($lang === 'vi') ? 'Khi học viên gửi yêu cầu liên kết tới Handle của bạn, danh sách yêu cầu sẽ xuất hiện tại đây để bạn xét duyệt.' : 'When clients request to link with you, their requests will appear here for your review.' ?></p>
                    </div>
                <?php else: ?>
                    <div class="requests-grid" id="requestsGrid">
                        <?php foreach ($requests as $r): 
                            $rid = (int) $r['request_id'];
                            $uid = (int) $r['user_id'];
                            $name = htmlspecialchars($r['first_name'] . ' ' . $r['last_name'], ENT_QUOTES);
                            $handle = htmlspecialchars($r['user_name'], ENT_QUOTES);
                            $avatar = $r['profile_image'] ?? '';
                            $streak = (int) $r['logging_streak'];
                        ?>
                            <article class="request-card" data-request-id="<?= $rid ?>" style="background: var(--color-surface); border: 2px solid var(--color-border); border-radius: var(--radius-lg); padding: 20px; box-shadow: 0 8px 0 var(--color-border-subtle), var(--shadow-sm); display: flex; align-items: center; justify-content: space-between; gap: 16px; transition: all var(--transition-base);">
                                <div style="display: flex; align-items: center; gap: 16px;">
                                    <div class="request-avatar" style="width: 48px; height: 48px; border-radius: 50%; overflow: hidden; border: 2px solid var(--color-border); display: flex; align-items: center; justify-content: center; background: var(--color-surface-alt);">
                                        <?php if ($avatar): ?>
                                            <img src="<?= BASE_URL . htmlspecialchars($avatar, ENT_QUOTES) ?>" style="width: 100%; height: 100%; object-fit: cover;">
                                        <?php else: ?>
                                            <i class="fas fa-user"></i>
                                        <?php endif; ?>
                                    </div>
                                    <div>
                                        <h3 style="font-size: 16px; font-weight: 700; margin: 0; color: var(--color-text);"><?= $name ?></h3>
                                        <span style="font-size: 13px; color: var(--color-text-secondary);"><?= $handle ?></span>
                                        <div style="margin-top: 4px; display: flex; gap: 8px; font-size: 12px; color: var(--color-text-secondary);">
                                            <span><i class="fas fa-fire" style="color: var(--color-accent);"></i> <?= $streak ?>d streak</span>
                                            <span>·</span>
                                            <span><?= date('d M Y', strtotime($r['created_at'])) ?></span>
                                        </div>
                                    </div>
                                </div>
                                <div style="display: flex; gap: 8px;">
                                    <button class="btn-tactile btn-tactile--primary js-accept-request" style="background: var(--color-primary); color: #ffffff; border: none; border-radius: var(--radius-md); padding: 8px 16px; font-weight: 700; cursor: pointer; box-shadow: 0 4px 0 var(--color-primary-hover);" onclick="handleConnectionRequest(<?= $rid ?>, 'accept')"><i class="fas fa-check"></i> <?= ($lang === 'vi') ? 'Duyệt' : 'Accept' ?></button>
                                    <button class="btn-tactile btn-tactile--ghost" style="border: 2px solid var(--color-border); padding: 8px 16px; border-radius: var(--radius-md); font-weight: 700; cursor: pointer; background: var(--color-surface); color: var(--color-text);" onclick="handleConnectionRequest(<?= $rid ?>, 'reject')"><?= ($lang === 'vi') ? 'Từ chối' : 'Decline' ?></button>
                                </div>
                            </article>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </section>
        </div>
    </main>

    <!-- View Photo Modal inside PT Dashboard too -->
    <?php include PROJECT_ROOT . 'dashboard/views/_view-photo-modal.php'; ?>

    <!-- Toast for non-blocking success/error feedback (replaces alert()) -->
    <?php include PROJECT_ROOT . 'dashboard/views/logging-toast.php'; ?>

    <?php include PROJECT_ROOT . 'views/footer.php'; ?>

    <script src="<?= BASE_URL ?>js/pt-chat.js"></script>
    <script>
        (function() {
            const CSRF = <?= json_encode($csrfToken) ?>;
            const ENDPOINT = '<?= BASE_URL ?>dashboard/handlers/pt_action.php';
            const clientLogs = <?= json_encode($clientLogs) ?>;
            // clientFeedback[client_id]['YYYY-MM-DD'] = content (Task #1: per-day history)
            const clientFeedback = <?= json_encode($clientFeedback) ?>;
            const clientsData = <?= json_encode($clients) ?>;
            // clientTrends[client_id]['YYYY-MM-DD'] = {cal, pro} (Task #2: 7-day trend)
            const clientTrends = <?= json_encode($clientTrends) ?>;
            const TODAY = <?= json_encode(date('Y-m-d')) ?>;
            const TODAY_LABEL = <?= json_encode($lang === 'vi' ? 'Hôm nay' : 'Today') ?>;
            const MSG = <?= json_encode($lang === 'vi' ? [
                'saved' => 'Đã lưu góp ý', 'saveErr' => 'Lưu góp ý thất bại',
                'conn' => 'Lỗi kết nối', 'actionErr' => 'Thao tác thất bại',
                'disconnected' => 'Đã hủy liên kết học viên', 'disconnectErr' => 'Hủy liên kết thất bại',
            ] : [
                'saved' => 'Feedback saved', 'saveErr' => 'Failed to save feedback',
                'conn' => 'Connection error', 'actionErr' => 'Action failed',
                'disconnected' => 'Client disconnected', 'disconnectErr' => 'Failed to disconnect',
            ]) ?>;
            // Toast helper with graceful fallback if the component isn't present.
            function notify(message, type) {
                if (window.showLoggingToast) { window.showLoggingToast(message, '', type || 'success'); }
                else { alert(message); }
            }
            const TREND_LABELS = <?= json_encode($lang === 'vi'
                ? ['avgCal' => 'TB calo/ngày', 'avgPro' => 'TB đạm/ngày', 'logged' => 'Ngày có ghi', 'goal' => 'Mục tiêu', 'noData' => 'Chưa có dữ liệu 7 ngày qua']
                : ['avgCal' => 'Avg kcal/day', 'avgPro' => 'Avg protein/day', 'logged' => 'Days logged', 'goal' => 'Goal', 'noData' => 'No data in the last 7 days']) ?>;
            
            const ptContainer = document.querySelector('.pt-container');
            const tabs = ptContainer.querySelectorAll('.pt-tab');
            const panels = ptContainer.querySelectorAll('.pt-panel');
            
            // Tab switching
            tabs.forEach(t => t.addEventListener('click', () => {
                tabs.forEach(x => x.classList.remove('active'));
                panels.forEach(p => p.setAttribute('hidden', ''));
                
                t.classList.add('active');
                ptContainer.querySelector(`[data-panel="${t.dataset.tab}"]`).removeAttribute('hidden');
            }));

            // Search filtering
            const searchInput = document.getElementById('clientSearchInput');
            if (searchInput) {
                searchInput.addEventListener('input', () => {
                    const q = searchInput.value.toLowerCase().trim();
                    const cards = document.querySelectorAll('#clientsGrid .client-card');
                    cards.forEach(card => {
                        const name = card.dataset.name || '';
                        card.style.display = name.includes(q) ? '' : 'none';
                    });
                });
            }

            // Photo Modal Logic
            const viewPhotoModal = document.getElementById('viewPhotoModal');
            const viewPhotoImg = document.getElementById('viewPhotoImg');
            const closePhotoBtn = document.getElementById('closeViewPhotoModal');
            
            if (viewPhotoModal && closePhotoBtn) {
                closePhotoBtn.addEventListener('click', () => {
                    viewPhotoModal.classList.remove('active');
                });
                viewPhotoModal.addEventListener('click', e => {
                    if (e.target === viewPhotoModal) {
                        viewPhotoModal.classList.remove('active');
                    }
                });
            }

            window.viewMealPhoto = function(src) {
                if (src && viewPhotoModal && viewPhotoImg) {
                    viewPhotoImg.src = src;
                    viewPhotoModal.classList.add('active');
                }
            };

            // Details drawer + card selectors
            const clientDrawer = document.getElementById('clientDrawer');
            const clientDetailsCard = document.getElementById('clientDetailsCard');
            // Per-client unread map (Task #4) — drives the smart default tab below
            const clientUnread = <?= json_encode((object) $clientUnread) ?>;
            const detAvatar = document.getElementById('det-avatar');
            const detName = document.getElementById('det-name');
            const detHandle = document.getElementById('det-handle');
            const detClientId = document.getElementById('det-client-id');
            const detLogsContainer = document.getElementById('det-logs-container');
            const detFeedbackContent = document.getElementById('det-feedback-content');
            const detDateFor = document.getElementById('det-date-for');
            const detHistory = document.getElementById('det-feedback-history');

            // ---- Per-day feedback helpers (Task #1) ----
            function feedbackMap(cid) {
                return clientFeedback[cid] || {};
            }
            function loadFeedbackForDate() {
                const cid = parseInt(detClientId.value, 10);
                detFeedbackContent.value = feedbackMap(cid)[detDateFor.value] || '';
            }
            function formatChipDate(d) {
                if (d === TODAY) return TODAY_LABEL;
                const parts = d.split('-');
                return parseInt(parts[2], 10) + '/' + parseInt(parts[1], 10);
            }
            function renderFeedbackHistory() {
                const cid = parseInt(detClientId.value, 10);
                const map = feedbackMap(cid);
                const dates = Object.keys(map)
                    .filter(d => (map[d] || '').trim() !== '')
                    .sort()
                    .reverse();
                detHistory.innerHTML = '';
                if (dates.length === 0) {
                    detHistory.style.display = 'none';
                    return;
                }
                detHistory.style.display = 'flex';
                dates.forEach(d => {
                    const active = d === detDateFor.value;
                    const chip = document.createElement('button');
                    chip.type = 'button';
                    chip.textContent = formatChipDate(d);
                    chip.title = d;
                    chip.style.cssText = `border:2px solid ${active ? 'var(--color-secondary)' : 'var(--color-border)'}; background:${active ? 'var(--color-secondary)' : 'var(--color-surface)'}; color:${active ? '#ffffff' : 'var(--color-text)'}; border-radius:var(--radius-md); padding:4px 10px; font-size:12px; font-weight:700; cursor:pointer; transition:all var(--transition-base);`;
                    chip.addEventListener('click', () => {
                        detDateFor.value = d;
                        loadFeedbackForDate();
                        renderFeedbackHistory();
                    });
                    detHistory.appendChild(chip);
                });
            }

            if (detDateFor) {
                detDateFor.addEventListener('change', () => {
                    loadFeedbackForDate();
                    renderFeedbackHistory();
                });
            }

            // ---- Two-way chat (Task #3): one widget, re-pointed per client ----
            const chatEl = document.getElementById('ptClientChat');
            const clientChat = chatEl ? new PTChat(chatEl) : null;

            // Clear a client's unread badge + decrement the hero tally
            function clearCardUnread(card) {
                const badge = card.querySelector('.client-card__unread');
                if (!badge) return;
                const n = parseInt(badge.textContent, 10) || 0;
                badge.remove();
                const heroCount = document.getElementById('hero-msg-count');
                if (heroCount) {
                    const left = Math.max(0, (parseInt(heroCount.textContent, 10) || 0) - n);
                    heroCount.textContent = left;
                    if (left === 0) {
                        const m = document.getElementById('hero-msg-stat');
                        if (m) m.classList.remove('pt-stat--msg');
                    }
                }
            }

            // ---- Internal tabs inside the details card (Diary / Chat / Feedback) ----
            const detailTabs = clientDetailsCard ? clientDetailsCard.querySelectorAll('.details-tab') : [];
            const detailPanes = clientDetailsCard ? clientDetailsCard.querySelectorAll('.details-tabpane') : [];
            function showDetailPane(name) {
                detailTabs.forEach(t => t.classList.toggle('active', t.dataset.pane === name));
                detailPanes.forEach(p => {
                    if (p.dataset.pane === name) { p.removeAttribute('hidden'); }
                    else { p.setAttribute('hidden', ''); }
                });
                // Messages render while hidden (display:none) → scroll once visible.
                if (name === 'chat' && clientChat && clientChat.messagesEl) {
                    clientChat.messagesEl.scrollTop = clientChat.messagesEl.scrollHeight;
                }
            }
            detailTabs.forEach(tab => tab.addEventListener('click', () => showDetailPane(tab.dataset.pane)));

            // ---- 7-day trend helpers (Task #2) ----
            const detTrendChart = document.getElementById('det-trend-chart');
            const detTrendSummary = document.getElementById('det-trend-summary');

            function last7Dates() {
                const p = TODAY.split('-').map(Number);
                const base = new Date(p[0], p[1] - 1, p[2]);
                const out = [];
                for (let i = 6; i >= 0; i--) {
                    const d = new Date(base);
                    d.setDate(base.getDate() - i);
                    const m = String(d.getMonth() + 1).padStart(2, '0');
                    const day = String(d.getDate()).padStart(2, '0');
                    out.push({ iso: `${d.getFullYear()}-${m}-${day}`, label: `${d.getDate()}/${d.getMonth() + 1}` });
                }
                return out;
            }

            function statChip(value, label) {
                return `<div style="flex:1; background:var(--color-surface-alt); border:2px solid var(--color-border); border-radius:var(--radius-md); padding:8px 10px; text-align:center;">
                    <strong style="display:block; font-size:16px; color:var(--color-text); line-height:1.2;">${value}</strong>
                    <span style="font-size:10px; color:var(--color-text-secondary);">${label}</span>
                </div>`;
            }

            function renderClientTrend(c) {
                const goal = parseInt(c.calorie_goal, 10) || 0;
                const trend = clientTrends[c.user_id] || {};
                const days = last7Dates();
                const cals = days.map(d => (trend[d.iso] ? Math.round(trend[d.iso].cal) : 0));
                const pros = days.map(d => (trend[d.iso] ? Math.round(trend[d.iso].pro) : 0));
                const loggedDays = cals.filter(v => v > 0).length;
                const avgCal = loggedDays ? Math.round(cals.reduce((a, b) => a + b, 0) / loggedDays) : 0;
                const avgPro = loggedDays ? Math.round(pros.reduce((a, b) => a + b, 0) / loggedDays) : 0;

                detTrendSummary.innerHTML =
                    statChip(avgCal.toLocaleString(), TREND_LABELS.avgCal) +
                    statChip(avgPro + 'g', TREND_LABELS.avgPro) +
                    statChip(loggedDays + '/7', TREND_LABELS.logged);

                detTrendChart.innerHTML = '';
                if (loggedDays === 0) {
                    detTrendChart.style.alignItems = 'center';
                    detTrendChart.innerHTML = `<p style="width:100%; text-align:center; font-size:13px; color:var(--color-text-secondary);">${TREND_LABELS.noData}</p>`;
                    return;
                }
                detTrendChart.style.alignItems = 'flex-end';

                const maxVal = Math.max(goal, ...cals, 1);

                // Goal reference line
                if (goal > 0) {
                    const goalLine = document.createElement('div');
                    const bottomPct = Math.min(100, (goal / maxVal) * 100);
                    goalLine.title = `${TREND_LABELS.goal}: ${goal.toLocaleString()} kcal`;
                    goalLine.style.cssText = `position:absolute; left:0; right:0; bottom:${bottomPct}%; border-top:2px dashed var(--color-secondary); opacity:0.7; pointer-events:none;`;
                    detTrendChart.appendChild(goalLine);
                }

                days.forEach((d, i) => {
                    const v = cals[i];
                    const isToday = d.iso === TODAY;
                    const over = goal > 0 && v > goal;
                    const heightPct = v > 0 ? Math.max(Math.round((v / maxVal) * 100), 4) : 0;
                    const col = document.createElement('div');
                    col.style.cssText = 'flex:1; display:flex; flex-direction:column; align-items:center; gap:4px; height:100%; min-width:0;';
                    col.innerHTML = `
                        <div style="flex:1; width:100%; display:flex; align-items:flex-end;">
                            <div title="${d.label}: ${v.toLocaleString()} kcal" style="width:100%; border-radius:4px 4px 0 0; height:${heightPct}%; min-height:${v > 0 ? '4px' : '0'}; background:${over ? 'var(--color-accent)' : 'var(--color-primary)'};"></div>
                        </div>
                        <span style="font-size:10px; font-weight:${isToday ? '700' : '400'}; color:${isToday ? 'var(--color-text)' : 'var(--color-text-secondary)'};">${d.label}</span>
                    `;
                    detTrendChart.appendChild(col);
                });
            }

            // Select client
            document.querySelectorAll('#clientsGrid .client-card').forEach(card => {
                card.addEventListener('click', () => {
                    document.querySelectorAll('#clientsGrid .client-card').forEach(c => c.classList.remove('active'));
                    card.classList.add('active');

                    // Opening a client loads + marks their chat read, so clear the
                    // unread badge here and drop the hero "new messages" tally.
                    clearCardUnread(card);

                    const cid = parseInt(card.dataset.userId, 10);
                    const client = clientsData.find(c => parseInt(c.user_id, 10) === cid);

                    if (client) {
                        showClientDetails(client);
                    }
                });
            });

            function showClientDetails(c) {
                openDrawer();

                detClientId.value = c.user_id;
                detName.textContent = c.first_name + ' ' + c.last_name;
                detHandle.textContent = c.user_name;
                
                // Avatar
                detAvatar.innerHTML = c.profile_image 
                    ? `<img src="<?= BASE_URL ?>${c.profile_image}" style="width:100%; height:100%; object-fit:cover;">`
                    : `<i class="fas fa-user" style="font-size:24px; color:var(--color-text-secondary);"></i>`;
                
                // Load logs
                const logs = clientLogs[c.user_id] || [];
                detLogsContainer.innerHTML = '';
                
                if (logs.length === 0) {
                    detLogsContainer.innerHTML = `<p style="font-size:13px; color:var(--color-text-secondary); text-align:center; padding:12px 0;">No meals logged today.</p>`;
                } else {
                    logs.forEach(log => {
                        const hasImage = log.image_path && log.image_path !== '';
                        const photoBtn = hasImage 
                            ? `<button type="button" class="btn-tactile" style="background:var(--color-primary-soft); color:var(--color-primary); border:2px solid var(--color-primary); border-radius:var(--radius-md); padding:4px 8px; font-size:11px; font-weight:700; cursor:pointer;" onclick="viewMealPhoto('<?= BASE_URL ?>${log.image_path}')"><i class="fas fa-camera"></i> Photo</button>`
                            : '';
                        const time = new Date(log.date_intake).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
                        
                        const itemHtml = `
                            <div class="meal-log-item" style="border: 2px solid var(--color-border); border-radius: var(--radius-md); padding: 12px; background: var(--color-surface); display: flex; justify-content: space-between; align-items: center; gap: 8px;">
                                <div>
                                    <div style="font-weight:700; font-size:14px; color:var(--color-text);">${log.food_item}</div>
                                    <div style="font-size:12px; color:var(--color-text-secondary); margin-top:2px;">
                                        <span style="color:var(--color-primary); font-weight:700;">${log.calories} kcal</span> · 
                                        <span>P: ${Math.round(log.protein)}g</span> · 
                                        <span>${time}</span>
                                    </div>
                                </div>
                                ${photoBtn}
                            </div>
                        `;
                        detLogsContainer.insertAdjacentHTML('beforeend', itemHtml);
                    });
                }
                
                // Smart default tab: if this client has unread messages, open
                // straight to Chat (the most likely action); otherwise the Diary.
                const hasUnread = (clientUnread[c.user_id] || clientUnread[String(c.user_id)] || 0) > 0;
                showDetailPane(hasUnread ? 'chat' : 'diary');

                // 7-day trend snapshot
                renderClientTrend(c);

                // Point the chat widget at this client and load the thread
                if (clientChat) {
                    clientChat.setCounterpart(c.user_id);
                    clientChat.load();
                }

                // Load feedback — default to today, with clickable history of past days
                detDateFor.value = TODAY;
                loadFeedbackForDate();
                renderFeedbackHistory();
            }

            // ---- Drawer open/close ----
            function openDrawer() {
                clientDrawer.removeAttribute('hidden');
                // next frame so the slide-in transition runs
                requestAnimationFrame(() => clientDrawer.classList.add('is-open'));
                document.body.classList.add('pt-drawer-open');
            }

            window.closeClientDetails = function() {
                clientDrawer.classList.remove('is-open');
                document.body.classList.remove('pt-drawer-open');
                document.querySelectorAll('#clientsGrid .client-card').forEach(c => c.classList.remove('active'));
                // hide after the slide-out transition
                setTimeout(() => { clientDrawer.setAttribute('hidden', ''); }, 280);
            };

            // Close on backdrop click + Escape
            clientDrawer.addEventListener('click', (e) => {
                if (e.target.hasAttribute('data-drawer-close')) closeClientDetails();
            });
            document.addEventListener('keydown', (e) => {
                if (e.key === 'Escape' && clientDrawer.classList.contains('is-open')) closeClientDetails();
            });

            // Connect/Reject connection requests
            window.handleConnectionRequest = async function(reqId, action) {
                if (!(await showConfirm({ message: `Are you sure you want to ${action} this request?`, danger: true }))) return;
                
                const btn = event.currentTarget;
                btn.disabled = true;
                
                try {
                    const fd = new FormData();
                    fd.append('action', action);
                    fd.append('request_id', reqId);
                    fd.append('csrf_token', CSRF);
                    
                    const res = await fetch(ENDPOINT, {
                        method: 'POST',
                        headers: { 'X-Requested-With': 'fetch' },
                        body: fd
                    });
                    const data = await res.json();
                    
                    if (data.ok) {
                        const card = btn.closest('.request-card');
                        card.style.opacity = '0';
                        setTimeout(() => {
                            card.remove();
                            // Update counts
                            const currentReqBadge = document.getElementById('pending-badge-tab');
                            const currentReqHero = document.getElementById('pending-badge-hero');
                            const reqCount = document.querySelectorAll('#requestsGrid .request-card').length;
                            
                            if (currentReqHero) currentReqHero.textContent = reqCount;
                            if (currentReqBadge) {
                                if (reqCount > 0) {
                                    currentReqBadge.textContent = reqCount;
                                } else {
                                    currentReqBadge.remove();
                                    document.getElementById('requestsGrid').insertAdjacentHTML('beforeend', `
                                        <div class="pt-empty">
                                            <div class="pt-empty__icon"><i class="fas fa-user-lock"></i></div>
                                            <h3>No connection requests</h3>
                                            <p>When clients request to link with you, their requests will appear here for your review.</p>
                                        </div>
                                    `);
                                }
                            }
                            
                            // Re-poll/reload page on accept so client is added to grid
                            if (action === 'accept') {
                                setTimeout(() => window.location.reload(), 300);
                            }
                        }, 300);
                    } else {
                        notify(data.error || MSG.actionErr, 'error');
                        btn.disabled = false;
                    }
                } catch (err) {
                    notify(MSG.conn, 'error');
                    btn.disabled = false;
                }
            };

            // Save feedback
            window.saveClientFeedback = async function(e) {
                e.preventDefault();
                const cid = parseInt(detClientId.value, 10);
                const content = detFeedbackContent.value.trim();
                
                const form = document.getElementById('feedbackForm');
                const btn = form.querySelector('button[type="submit"]');
                const originalHtml = btn.innerHTML;
                btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Saving...';
                btn.disabled = true;
                
                try {
                    const fd = new FormData(form);
                    fd.append('action', 'save_feedback');
                    fd.append('csrf_token', CSRF);
                    
                    const res = await fetch(ENDPOINT, {
                        method: 'POST',
                        headers: { 'X-Requested-With': 'fetch' },
                        body: fd
                    });
                    const data = await res.json();
                    
                    if (data.ok) {
                        const dateFor = detDateFor.value;
                        if (!clientFeedback[cid]) clientFeedback[cid] = {};
                        if (content === '') {
                            delete clientFeedback[cid][dateFor];
                        } else {
                            clientFeedback[cid][dateFor] = content;
                        }
                        renderFeedbackHistory();
                        notify(MSG.saved, 'success');
                    } else {
                        notify(data.error || MSG.saveErr, 'error');
                    }
                } catch (err) {
                    notify(MSG.conn, 'error');
                } finally {
                    btn.innerHTML = originalHtml;
                    btn.disabled = false;
                }
            };

            // Disconnect client
            window.disconnectClient = async function() {
                const cid = parseInt(detClientId.value, 10);
                if (!(await showConfirm({ message: `Are you sure you want to disconnect this client? They will no longer share their diet journal with you.`, danger: true }))) return;
                
                try {
                    const fd = new FormData();
                    fd.append('action', 'terminate');
                    fd.append('client_id', cid);
                    fd.append('csrf_token', CSRF);
                    
                    const res = await fetch(ENDPOINT, {
                        method: 'POST',
                        headers: { 'X-Requested-With': 'fetch' },
                        body: fd
                    });
                    const data = await res.json();
                    
                    if (data.ok) {
                        notify(MSG.disconnected, 'success');
                        setTimeout(() => window.location.reload(), 800);
                    } else {
                        notify(data.error || MSG.disconnectErr, 'error');
                    }
                } catch (err) {
                    notify(MSG.conn, 'error');
                }
            };

        })();
    </script>
</body>
</html>
