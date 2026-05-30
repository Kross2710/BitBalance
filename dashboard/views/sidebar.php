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
?>
<div class="sidebar">
    <a href="dashboard.php" class="nav-link <?php echo ($activePage == 'overview') ? 'active' : ''; ?>"
        data-short="<?= t('dashboard.sidebar.overview_short') ?>">
        <i class="fas fa-th-large"></i> <?= t('dashboard.sidebar.overview') ?>
    </a>

    <a href="dashboard-intake.php" class="nav-link <?php echo ($activePage == 'intake') ? 'active' : ''; ?>"
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

    <a href="dashboard-wiki.php" class="nav-link <?php echo ($activePage == 'wiki') ? 'active' : ''; ?>"
        data-short="<?= t('dashboard.sidebar.wiki_short') ?>">
        <i class="fas fa-book-medical"></i> <?= t('dashboard.sidebar.wiki') ?>
    </a>

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
