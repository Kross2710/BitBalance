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
        data-short="Home">
        <i class="fas fa-th-large"></i> Overview
    </a>

    <a href="dashboard-intake.php" class="nav-link <?php echo ($activePage == 'intake') ? 'active' : ''; ?>"
        data-short="Intake">
        <i class="fas fa-utensils"></i> Food Intake
    </a>

    <a href="dashboard-calculator.php" class="nav-link <?php echo ($activePage == 'calculator') ? 'active' : ''; ?>"
        data-short="Calc">
        <i class="fas fa-calculator"></i> Calculator
    </a>

    <a href="dashboard-plan.php" class="nav-link <?php echo ($activePage == 'plan') ? 'active' : ''; ?>"
        data-short="Plan">
        <i class="fas fa-route"></i> Plan
    </a>

    <a href="dashboard-progress.php" class="nav-link <?php echo ($activePage == 'progress') ? 'active' : ''; ?>"
        data-short="XP">
        <i class="fas fa-bolt"></i> Progress
    </a>

    <a href="dashboard-history.php" class="nav-link <?php echo ($activePage == 'history') ? 'active' : ''; ?>"
        data-short="Hist">
        <i class="fas fa-history"></i> History
    </a>

    <a href="dashboard-wiki.php" class="nav-link <?php echo ($activePage == 'wiki') ? 'active' : ''; ?>"
        data-short="Wiki">
        <i class="fas fa-book-medical"></i> Wiki
    </a>

    <a href="dashboard-friends.php" class="nav-link <?php echo ($activePage == 'friends') ? 'active' : ''; ?>"
        data-short="Friends">
        <i class="fas fa-user-friends"></i> Friends
        <?php if ($sidebarPendingFriends > 0): ?>
            <span class="nav-link__badge"><?= $sidebarPendingFriends ?></span>
        <?php endif; ?>
    </a>
</div>
