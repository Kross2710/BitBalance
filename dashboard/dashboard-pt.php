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

// 4. Fetch today's feedback notes from PT to clients
$clientFeedback = [];
try {
    $stmt = $pdo->prepare("
        SELECT client_id, content 
        FROM pt_feedback 
        WHERE trainer_id = ? AND date_for = CURDATE()
    ");
    $stmt->execute([$me]);
    while ($row = $stmt->fetch()) {
        $clientFeedback[$row['client_id']] = $row['content'];
    }
} catch (PDOException $e) {
    error_log("PT Load Feedback Error: " . $e->getMessage());
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
    $pageCss = ['css/dashboard.css', 'css/pages/dashboard-pt.css'];
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
                <div class="pt-hero__metrics">
                    <div class="pt-metric">
                        <span class="pt-metric__label"><?= ($lang === 'vi') ? 'Học viên' : 'Clients' ?></span>
                        <strong><?= count($clients) ?></strong>
                    </div>
                    <div class="pt-metric">
                        <span class="pt-metric__label"><?= ($lang === 'vi') ? 'Yêu cầu chờ' : 'Pending requests' ?></span>
                        <strong id="pending-badge-hero"><?= count($requests) ?></strong>
                    </div>
                </div>
            </section>

            <!-- Navigation Tabs -->
            <nav class="pt-tabs" role="tablist">
                <button class="pt-tab active" role="tab" data-tab="clients">
                    <i class="fas fa-users"></i> <?= ($lang === 'vi') ? 'Danh sách học viên' : 'Active Clients' ?>
                    <span class="pt-tab__badge pt-tab__badge--muted"><?= count($clients) ?></span>
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
                                ?>
                                    <article class="client-card" data-user-id="<?= $cid ?>" data-name="<?= strtolower($name) ?>">
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
                                    </article>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>

                    <!-- Right: Dynamic Client Detailing Panel -->
                    <div class="pt-panel-side">
                        <div class="pt-details-card" id="clientDetailsCard" style="display: none;">
                            <button class="close-side-details" onclick="closeClientDetails()"><i class="fas fa-times"></i></button>
                            
                            <div class="details-header" style="display: flex; align-items: center; gap: 16px; margin-bottom: 24px; padding-bottom: 16px; border-bottom: 2px solid var(--color-border);">
                                <div class="details-avatar" id="det-avatar" style="width: 56px; height: 56px; border-radius: 50%; overflow: hidden; background: var(--color-surface-alt); display: flex; align-items: center; justify-content: center; border: 2px solid var(--color-border);"></div>
                                <div>
                                    <h2 id="det-name" style="font-size: 20px; font-weight: 700; margin: 0; color: var(--color-text);"></h2>
                                    <span id="det-handle" style="font-size: 13px; color: var(--color-text-secondary);"></span>
                                </div>
                            </div>

                            <!-- Meal Logs & Photos Section -->
                            <div class="details-logs-section">
                                <h4 style="font-weight: 700; color: var(--color-text); margin-bottom: 12px; font-size: 15px;"><i class="fas fa-utensils" style="color: var(--color-primary); margin-right: 8px;"></i> <?= ($lang === 'vi') ? 'Nhật ký bữa ăn hôm nay' : "Today's Meal Diary" ?></h4>
                                <div id="det-logs-container" style="display: flex; flex-direction: column; gap: 12px; margin-bottom: 24px; max-height: 320px; overflow-y: auto; padding-right: 4px;"></div>
                            </div>

                            <!-- PT Feedback / Nutrition Advice Form -->
                            <div class="details-feedback-section" style="padding-top: 16px; border-top: 2px dashed var(--color-border);">
                                <h4 style="font-weight: 700; color: var(--color-text); margin-bottom: 12px; font-size: 15px;"><i class="fas fa-comment-medical" style="color: var(--color-secondary); margin-right: 8px;"></i> <?= ($lang === 'vi') ? 'Góp ý dinh dưỡng của PT' : 'Nutritionist Feedback' ?></h4>
                                <form id="feedbackForm" onsubmit="saveClientFeedback(event)">
                                    <input type="hidden" name="client_id" id="det-client-id">
                                    <input type="hidden" name="date_for" id="det-date-for" value="<?= date('Y-m-d') ?>">
                                    <textarea name="content" id="det-feedback-content" rows="4" placeholder="<?= ($lang === 'vi') ? 'Viết lời động viên hoặc nhận xét về bữa ăn hôm nay (ví dụ: cần tăng đạm, hạn chế béo)...' : 'Write comments, advice or motivational words about today\'s logging (e.g. increase protein, reduce fats)...' ?>" style="width: 100%; border: 2px solid var(--color-border); border-radius: var(--radius-md); padding: 12px; font-size: 14px; background: var(--color-surface-alt); color: var(--color-text); resize: none; outline: none; transition: all var(--transition-base); margin-bottom: 12px;"></textarea>
                                    <div style="display: flex; gap: 8px; justify-content: flex-end;">
                                        <button type="button" class="btn-tactile btn-tactile--ghost" style="border: 2px solid var(--color-border); padding: 10px 16px; border-radius: var(--radius-md); font-weight: 700; background: var(--color-surface); color: var(--color-text); cursor: pointer;" onclick="disconnectClient()"><i class="fas fa-unlink"></i> <?= ($lang === 'vi') ? 'Hủy liên kết' : 'Disconnect' ?></button>
                                        <button type="submit" class="btn-tactile btn-tactile--primary" style="background: var(--color-primary); color: #ffffff; border: none; border-radius: var(--radius-md); padding: 10px 20px; font-weight: 700; cursor: pointer; box-shadow: 0 4px 0 var(--color-primary-hover);"><i class="fas fa-save"></i> <?= ($lang === 'vi') ? 'Lưu góp ý' : 'Save Feedback' ?></button>
                                    </div>
                                </form>
                            </div>
                        </div>

                        <div class="pt-details-placeholder" id="detailsPlaceholder" style="border: 2px dashed var(--color-border); border-radius: var(--radius-lg); padding: 48px 24px; text-align: center; color: var(--color-text-secondary); background: var(--color-surface);">
                            <i class="fas fa-user-check" style="font-size: 40px; margin-bottom: 16px;"></i>
                            <h3 style="font-size: 16px; font-weight: 700; color: var(--color-text);"><?= ($lang === 'vi') ? 'Chi tiết học viên' : 'Client Profile Details' ?></h3>
                            <p style="font-size: 13px; max-width: 240px; margin: 8px auto 0;"><?= ($lang === 'vi') ? 'Hãy chọn một học viên để kiểm tra chi tiết các món ăn, ảnh chụp và gửi nhận xét dinh dưỡng.' : 'Select a client from the list to review their meal logs, photos, and write nutrition feedback.' ?></p>
                        </div>
                    </div>
                </div>
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

    <?php include PROJECT_ROOT . 'views/footer.php'; ?>

    <script>
        (function() {
            const CSRF = <?= json_encode($csrfToken) ?>;
            const ENDPOINT = '<?= BASE_URL ?>dashboard/handlers/pt_action.php';
            const clientLogs = <?= json_encode($clientLogs) ?>;
            const clientFeedback = <?= json_encode($clientFeedback) ?>;
            const clientsData = <?= json_encode($clients) ?>;
            
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

            // Details card selectors
            const detailsPlaceholder = document.getElementById('detailsPlaceholder');
            const clientDetailsCard = document.getElementById('clientDetailsCard');
            const detAvatar = document.getElementById('det-avatar');
            const detName = document.getElementById('det-name');
            const detHandle = document.getElementById('det-handle');
            const detClientId = document.getElementById('det-client-id');
            const detLogsContainer = document.getElementById('det-logs-container');
            const detFeedbackContent = document.getElementById('det-feedback-content');

            // Select client
            document.querySelectorAll('#clientsGrid .client-card').forEach(card => {
                card.addEventListener('click', () => {
                    document.querySelectorAll('#clientsGrid .client-card').forEach(c => c.classList.remove('active'));
                    card.classList.add('active');
                    
                    const cid = parseInt(card.dataset.userId, 10);
                    const client = clientsData.find(c => parseInt(c.user_id, 10) === cid);
                    
                    if (client) {
                        showClientDetails(client);
                    }
                });
            });

            function showClientDetails(c) {
                detailsPlaceholder.style.display = 'none';
                clientDetailsCard.style.display = 'block';
                
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
                
                // Load feedback
                detFeedbackContent.value = clientFeedback[c.user_id] || '';
            }

            window.closeClientDetails = function() {
                document.querySelectorAll('#clientsGrid .client-card').forEach(c => c.classList.remove('active'));
                clientDetailsCard.style.display = 'none';
                detailsPlaceholder.style.display = 'block';
            };

            // Connect/Reject connection requests
            window.handleConnectionRequest = async function(reqId, action) {
                if (!confirm(`Are you sure you want to ${action} this request?`)) return;
                
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
                        alert(data.error || 'Action failed');
                        btn.disabled = false;
                    }
                } catch (err) {
                    alert('Connection error');
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
                        clientFeedback[cid] = content;
                        alert('Góp ý của PT đã được lưu thành công! Học viên sẽ nhìn thấy nhận xét này ngay lập tức.');
                    } else {
                        alert(data.error || 'Failed to save feedback');
                    }
                } catch (err) {
                    alert('Connection error');
                } finally {
                    btn.innerHTML = originalHtml;
                    btn.disabled = false;
                }
            };

            // Disconnect client
            window.disconnectClient = async function() {
                const cid = parseInt(detClientId.value, 10);
                if (!confirm(`Are you sure you want to disconnect this client? They will no longer share their diet journal with you.`)) return;
                
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
                        alert('Hủy liên kết học viên thành công.');
                        window.location.reload();
                    } else {
                        alert(data.error || 'Failed to disconnect');
                    }
                } catch (err) {
                    alert('Connection error');
                }
            };

        })();
    </script>
</body>
</html>
