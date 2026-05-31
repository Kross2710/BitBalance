<?php
// dashboard/dashboard-coach.php
// Client-facing counterpart to dashboard-pt.php: a dedicated "My Trainer" page
// for clients who have an accepted trainer. Holds the two-way chat + the PT's
// feedback history (moved out of the Intake page, which was getting cluttered).
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/../include/init.php';
require_once __DIR__ . '/../include/csrf.php';
require_once __DIR__ . '/../include/handlers/log_attempt.php';

$lang = current_locale();

$activePage   = 'coach';
$activeHeader = 'dashboard';
$bodyClass    = 'page-coach';
$displayUser  = $isLoggedIn ? $user['user_name'] : 'Guest';

// Must be logged in.
if (!$isLoggedIn) {
    header("Location: " . BASE_URL . "login.php");
    exit();
}

$me = (int) $user['user_id'];

// The client's linked trainer — most recent accepted link (a client may have
// linked more than one PT over time). Drives both the chat and the page itself.
$myTrainer = null;
try {
    $stmt = $pdo->prepare("
        SELECT u.user_id, u.user_name, u.first_name, u.last_name, u.profile_image
        FROM trainer_client tc
        JOIN user u ON tc.trainer_id = u.user_id
        WHERE tc.client_id = ? AND tc.status = 'accepted'
        ORDER BY tc.responded_at DESC
        LIMIT 1
    ");
    $stmt->execute([$me]);
    $myTrainer = $stmt->fetch();
} catch (PDOException $e) {
    // Table may not exist yet (migrations run on-campus).
}

// No trainer → nothing to show here; send them to the PT directory on profile.
if (empty($myTrainer)) {
    header("Location: " . BASE_URL . "profile.php");
    exit();
}

log_attempt($pdo, $me, 'view', 'Client opened My Trainer page', 'dashboard', null);

// Full feedback history from this trainer, newest first (the Intake page only
// ever surfaced one day at a time).
$feedbackHistory = [];
try {
    $stmt = $pdo->prepare("
        SELECT pf.content, pf.date_for, u.first_name, u.last_name
        FROM pt_feedback pf
        JOIN user u ON pf.trainer_id = u.user_id
        WHERE pf.client_id = ?
        ORDER BY pf.date_for DESC
        LIMIT 60
    ");
    $stmt->execute([$me]);
    $feedbackHistory = $stmt->fetchAll();

    // Visiting this page clears the "new advice" notification badge.
    $pdo->prepare("UPDATE pt_feedback SET seen_at = NOW() WHERE client_id = ? AND seen_at IS NULL")
        ->execute([$me]);
} catch (PDOException $e) {
    // Table/columns may not exist yet.
}

$trainerName   = htmlspecialchars(trim($myTrainer['first_name'] . ' ' . $myTrainer['last_name']), ENT_QUOTES);
$trainerHandle = htmlspecialchars($myTrainer['user_name'] ?? '', ENT_QUOTES);
$csrfToken     = csrf_token();
?>
<!DOCTYPE html>
<html lang="<?= html_lang_attr() ?>" data-theme="<?= htmlspecialchars($_SESSION['user']['theme_preference'] ?? 'system', ENT_QUOTES) ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= ($lang === 'vi') ? 'PT của tôi' : 'My Trainer' ?> - BitBalance</title>
    <?php
    $pageComponents = ['sidebar', 'fab'];
    $pageCss = ['css/dashboard.css', 'css/pages/dashboard-pt.css', 'css/pages/dashboard-coach.css', 'css/components/pt-chat.css'];
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
                    <span class="pt-kicker"><i class="fas fa-dumbbell"></i> <?= ($lang === 'vi') ? 'Huấn luyện viên của bạn' : 'Your Personal Trainer' ?></span>
                    <h1><?= ($lang === 'vi') ? 'PT của tôi' : 'My Trainer' ?></h1>
                    <p><?= ($lang === 'vi') ? 'Trò chuyện trực tiếp với huấn luyện viên và xem lại những lời khuyên dinh dưỡng họ gửi cho bạn.' : 'Chat directly with your trainer and review the nutrition advice they send you.' ?></p>
                </div>
            </section>

            <section class="coach-card">
                <!-- Trainer identity header -->
                <div class="details-header">
                    <div class="details-avatar">
                        <?php if (!empty($myTrainer['profile_image'])): ?>
                            <img src="<?= BASE_URL . htmlspecialchars($myTrainer['profile_image'], ENT_QUOTES) ?>" alt="<?= $trainerName ?>">
                        <?php else: ?>
                            <i class="fas fa-dumbbell"></i>
                        <?php endif; ?>
                    </div>
                    <div>
                        <h2 class="details-name"><?= $trainerName ?></h2>
                        <?php if ($trainerHandle !== ''): ?>
                            <span class="details-handle">@<?= $trainerHandle ?></span>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Internal tabs: Chat (default) / Feedback -->
                <div class="details-tabs" role="tablist">
                    <button type="button" class="details-tab active" data-pane="chat"><i class="fas fa-comments"></i> <?= ($lang === 'vi') ? 'Trò chuyện' : 'Chat' ?></button>
                    <button type="button" class="details-tab" data-pane="feedback"><i class="fas fa-comment-medical"></i> <?= ($lang === 'vi') ? 'Lời khuyên' : 'Advice' ?>
                        <?php if (!empty($feedbackHistory)): ?>
                            <span class="coach-tab-count"><?= count($feedbackHistory) ?></span>
                        <?php endif; ?>
                    </button>
                </div>

                <!-- Chat pane -->
                <div class="details-tabpane" data-pane="chat">
                    <div class="pt-chat pt-chat--roomy" id="trainerChat" data-auto-init
                         data-endpoint="<?= BASE_URL ?>dashboard/handlers/pt_chat.php"
                         data-csrf="<?= htmlspecialchars($csrfToken, ENT_QUOTES) ?>"
                         data-self-role="client"
                         data-counterpart-id="<?= (int) $myTrainer['user_id'] ?>"
                         data-empty-text="<?= ($lang === 'vi') ? 'Chưa có tin nhắn. Hãy hỏi HLV của bạn!' : 'No messages yet. Ask your trainer!' ?>">
                        <div class="pt-chat__messages"></div>
                        <form class="pt-chat__form">
                            <textarea class="pt-chat__input" rows="1" placeholder="<?= ($lang === 'vi') ? 'Nhắn cho HLV của bạn...' : 'Message your trainer...' ?>"></textarea>
                            <button type="submit"><i class="fas fa-paper-plane"></i></button>
                        </form>
                    </div>
                </div>

                <!-- Feedback / advice history pane -->
                <div class="details-tabpane" data-pane="feedback" hidden>
                    <?php if (empty($feedbackHistory)): ?>
                        <div class="coach-empty">
                            <i class="fas fa-comment-slash"></i>
                            <p><?= ($lang === 'vi') ? 'HLV của bạn chưa gửi lời khuyên nào. Hãy tiếp tục ghi lại bữa ăn để nhận nhận xét nhé!' : 'Your trainer hasn\'t sent any advice yet. Keep logging your meals to get feedback!' ?></p>
                        </div>
                    <?php else: ?>
                        <ul class="coach-feedback-list">
                            <?php foreach ($feedbackHistory as $fb): ?>
                                <?php
                                $isToday  = ($fb['date_for'] === date('Y-m-d'));
                                $dateLabel = date('j/n/Y', strtotime($fb['date_for']));
                                ?>
                                <li class="coach-feedback-item<?= $isToday ? ' coach-feedback-item--today' : '' ?>">
                                    <div class="coach-feedback-meta">
                                        <i class="fas fa-calendar-day"></i>
                                        <span><?= ($lang === 'vi') ? 'Cho ngày ' : 'For ' ?><?= htmlspecialchars($dateLabel, ENT_QUOTES) ?></span>
                                        <?php if ($isToday): ?>
                                            <span class="coach-feedback-today"><?= ($lang === 'vi') ? 'Hôm nay' : 'Today' ?></span>
                                        <?php endif; ?>
                                    </div>
                                    <p class="coach-feedback-content"><?= htmlspecialchars($fb['content'], ENT_QUOTES) ?></p>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
                </div>
            </section>
        </div>
    </main>

    <!-- Toast for non-blocking chat error feedback (pt-chat.js uses showLoggingToast if present). -->
    <?php include PROJECT_ROOT . 'dashboard/views/logging-toast.php'; ?>

    <script src="<?= BASE_URL ?>js/pt-chat.js?v=<?= @filemtime(PROJECT_ROOT . 'js/pt-chat.js') ?>"></script>
    <script>
        // Internal tab switch (Chat / Advice) — mirrors the PT dashboard details tabs.
        (function () {
            const card = document.querySelector('.coach-card');
            if (!card) return;
            const tabs = card.querySelectorAll('.details-tab');
            const panes = card.querySelectorAll('.details-tabpane');
            function show(name) {
                tabs.forEach(t => t.classList.toggle('active', t.dataset.pane === name));
                panes.forEach(p => {
                    if (p.dataset.pane === name) { p.removeAttribute('hidden'); }
                    else { p.setAttribute('hidden', ''); }
                });
                // Chat messages render while the pane may be hidden → scroll once visible.
                if (name === 'chat') {
                    const root = document.getElementById('trainerChat');
                    const msgs = root && root.querySelector('.pt-chat__messages');
                    if (msgs) msgs.scrollTop = msgs.scrollHeight;
                }
            }
            tabs.forEach(tab => tab.addEventListener('click', () => show(tab.dataset.pane)));
        })();
    </script>
</body>
</html>
