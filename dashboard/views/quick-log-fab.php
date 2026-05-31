<?php
/**
 * Quick-log floating action button.
 * Include on dashboard pages where the primary action is logging food intake.
 * Hidden on desktop (≥900px) since the sidebar already exposes Intake.
 *
 * Usage:
 *   <?php include PROJECT_ROOT . 'dashboard/views/quick-log-fab.php'; ?>
 */
// Carry the viewed day so logging from a past-day page targets that day.
$__fabDateQ = (!empty($selectedDate) && $selectedDate !== date('Y-m-d'))
    ? '?date=' . urlencode($selectedDate) : '';
?>
<a href="<?= BASE_URL ?>dashboard/dashboard-intake.php<?= $__fabDateQ ?>"
   class="quick-log-fab quick-log-fab--extended"
   aria-label="<?= t('fab.log_food') ?>"
   title="<?= t('fab.log_food') ?>">
    <i class="fas fa-plus"></i>
    <span class="quick-log-fab__label"><?= t('fab.log') ?></span>
</a>
