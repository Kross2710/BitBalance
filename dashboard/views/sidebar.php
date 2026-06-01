<?php
// Pending friend-request count for the sidebar badge. Cached in session for 60s.
$sidebarPendingFriends = 0;
if (!empty($_SESSION['user']) && isset($pdo)) {
    require_once __DIR__ . '/../../include/handlers/friends.php';
    try {
        $sidebarPendingFriends = friends_pending_count_incoming($pdo, (int) $_SESSION['user']['user_id']);
    } catch (Throwable $e) {
        $sidebarPendingFriends = 0;
    }
}
// PT <-> client unread badge (Task #4): PTs see it on the PT Dashboard link,
// clients on the Intake link. Cheap COUNTs, safe before migrations have run.
$sidebarPtBadge = 0;
$sidebarIsPt = false;
// Whether this (non-PT) user has an accepted trainer — drives the client-side
// "My Trainer" nav link. Cached in session for 60s like the friends count.
$sidebarHasTrainer = false;
if (!empty($_SESSION['user']) && isset($pdo)) {
    require_once __DIR__ . '/../../include/handlers/pt_notify.php';
    $sidebarIsPt = (($_SESSION['user']['role'] ?? 'regular') === 'pt');
    $sidebarPtBadge = pt_sidebar_badge_count($pdo, (int) $_SESSION['user']['user_id'], $sidebarIsPt);

    if (!$sidebarIsPt) {
        $__uid = (int) $_SESSION['user']['user_id'];
        if (isset($_SESSION['sidebar_has_trainer'], $_SESSION['sidebar_has_trainer_at'])
            && (time() - (int) $_SESSION['sidebar_has_trainer_at']) < 60) {
            $sidebarHasTrainer = (bool) $_SESSION['sidebar_has_trainer'];
        } else {
            try {
                $stmt = $pdo->prepare("SELECT 1 FROM trainer_client WHERE client_id = ? AND status = 'accepted' LIMIT 1");
                $stmt->execute([$__uid]);
                $sidebarHasTrainer = (bool) $stmt->fetchColumn();
            } catch (Throwable $e) {
                $sidebarHasTrainer = false;
            }
            $_SESSION['sidebar_has_trainer'] = $sidebarHasTrainer;
            $_SESSION['sidebar_has_trainer_at'] = time();
        }
    }
}
// Carry the currently-viewed day across the Overview <-> Intake flow, so
// reviewing a past day on one page lands on the same day on the other. Empty
// for today or on pages that don't set $selectedDate (no change there).
$__navDateQ = (!empty($selectedDate) && $selectedDate !== date('Y-m-d'))
    ? '?date=' . urlencode($selectedDate) : '';
?>
<div class="sidebar">
    <a href="dashboard.php<?= $__navDateQ ?>" class="nav-link <?php echo ($activePage == 'overview') ? 'active' : ''; ?>"
        data-short="<?= t('dashboard.sidebar.overview_short') ?>">
        <i class="fas fa-th-large"></i> <?= t('dashboard.sidebar.overview') ?>
    </a>

    <?php if (!empty($_SESSION['user']) && ($_SESSION['user']['role'] ?? 'regular') === 'pt'): ?>
        <a href="dashboard-pt.php" class="nav-link <?php echo ($activePage == 'pt_dashboard') ? 'active' : ''; ?>"
            data-short="PT">
            <i class="fas fa-dumbbell"></i> PT Dashboard
            <?php if ($sidebarPtBadge > 0): ?>
                <span class="nav-link__badge"><?= $sidebarPtBadge ?></span>
            <?php endif; ?>
        </a>
    <?php endif; ?>

    <?php if ($sidebarHasTrainer): ?>
        <a href="dashboard-coach.php" class="nav-link <?php echo ($activePage == 'coach') ? 'active' : ''; ?>"
            data-short="<?= t('dashboard.sidebar.coach_short') ?>">
            <i class="fas fa-dumbbell"></i> <?= t('dashboard.sidebar.coach') ?>
            <?php if ($sidebarPtBadge > 0): ?>
                <span class="nav-link__badge"><?= $sidebarPtBadge ?></span>
            <?php endif; ?>
        </a>
    <?php endif; ?>

    <a href="dashboard-intake.php<?= $__navDateQ ?>" class="nav-link <?php echo ($activePage == 'intake') ? 'active' : ''; ?>"
        data-short="<?= t('dashboard.sidebar.intake_short') ?>">
        <i class="fas fa-utensils"></i> <?= t('dashboard.sidebar.intake') ?>
    </a>

    <a href="dashboard-plan.php" class="nav-link <?php echo ($activePage == 'plan') ? 'active' : ''; ?>"
        data-short="<?= t('dashboard.sidebar.plan_short') ?>">
        <i class="fas fa-route"></i> <?= t('dashboard.sidebar.plan') ?>
    </a>

    <a href="dashboard-progress.php" class="nav-link <?php echo ($activePage == 'progress') ? 'active' : ''; ?>"
        data-short="<?= t('dashboard.sidebar.progress_short') ?>">
        <i class="fas fa-bolt"></i> <?= t('dashboard.sidebar.progress') ?>
    </a>

    <!-- <a href="dashboard-wiki.php" class="nav-link <?php echo ($activePage == 'wiki') ? 'active' : ''; ?>"
        data-short="<?= t('dashboard.sidebar.wiki_short') ?>">
        <i class="fas fa-book-medical"></i> <?= t('dashboard.sidebar.wiki') ?>
    </a> -->

    <a href="dashboard-friends.php" class="nav-link <?php echo ($activePage == 'friends') ? 'active' : ''; ?>"
        data-short="<?= t('dashboard.sidebar.friends_short') ?>">
        <i class="fas fa-user-friends"></i> <?= t('dashboard.sidebar.friends') ?>
        <?php if ($sidebarPendingFriends > 0): ?>
            <span class="nav-link__badge"><?= $sidebarPendingFriends ?></span>
        <?php endif; ?>
    </a>

    <a href="dashboard-beats.php" class="nav-link <?php echo ($activePage == 'beats') ? 'active' : ''; ?>"
        data-short="<?= t('dashboard.sidebar.beats_short') ?>">
        <i class="fa-solid fa-music"></i> <?= t('dashboard.sidebar.beats') ?>
    </a>


    <!-- <a href="promo-video.php" class="nav-link <?php echo ($activePage == 'promo-video') ? 'active' : ''; ?>"
        data-short="Promo">
        <i class="fas fa-video"></i> Video Promo
    </a> -->
</div>
<?php /* Perceived-speed boost (no SPA): prefetch sidebar pages on hover + show a top loading bar on click. Pure progressive enhancement. */ ?>
<script src="<?= BASE_URL ?>dashboard/views/sidebar-prefetch.js?v=<?= @filemtime(PROJECT_ROOT . 'dashboard/views/sidebar-prefetch.js') ?>" defer></script>
