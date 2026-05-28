<?php
/**
 * Quick-log floating action button.
 * Include on dashboard pages where the primary action is logging food intake.
 * Hidden on desktop (≥900px) since the sidebar already exposes Intake.
 *
 * Usage:
 *   <?php include PROJECT_ROOT . 'dashboard/views/quick-log-fab.php'; ?>
 */
?>
<a href="<?= BASE_URL ?>dashboard/dashboard-intake.php"
   class="quick-log-fab quick-log-fab--extended"
   aria-label="Log food intake"
   title="Log food intake">
    <i class="fas fa-plus"></i>
    <span class="quick-log-fab__label">Log</span>
</a>
