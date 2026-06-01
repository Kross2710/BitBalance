<?php
/**
 * Bouncy quick-log floating action button.
 * Tap the main button to fan out stacked quick actions; tap again (or click
 * outside / press Esc) to collapse. Included on logged-in dashboard pages and
 * visible on every viewport.
 *
 * Actions are plain links so they behave identically on every host page
 * (home, plan, wiki, history, calculator):
 *   - Add food   -> Intake (carries the viewed day so past-day logging targets
 *                   that day, mirroring the per-meal "+" buttons)
 *   - AI Coach   -> Coach page
 *   - Log weight -> Home dashboard with ?log=weight, which auto-opens the
 *                   existing weight modal (the modal only lives on the home page)
 *
 * Usage:
 *   <?php include PROJECT_ROOT . 'dashboard/views/quick-log-fab.php'; ?>
 */
// Carry the viewed day so logging from a past-day page targets that day.
$__fabDateQ = (!empty($selectedDate) && $selectedDate !== date('Y-m-d'))
    ? '?date=' . urlencode($selectedDate) : '';
?>
<div class="quick-fab" id="quickFab">
    <div class="quick-fab__menu" id="quickFabMenu" role="menu" aria-label="<?= t('fab.quick_log') ?>">
        <a class="quick-fab__item" role="menuitem"
           href="<?= BASE_URL ?>dashboard/dashboard-intake.php<?= $__fabDateQ ?>">
            <span class="quick-fab__item-label"><?= t('fab.add_food') ?></span>
            <span class="quick-fab__item-icon"><i class="fas fa-utensils"></i></span>
        </a>
        <a class="quick-fab__item" role="menuitem"
           href="<?= BASE_URL ?>dashboard/dashboard-coach.php">
            <span class="quick-fab__item-label"><?= t('fab.ai_coach') ?></span>
            <span class="quick-fab__item-icon"><i class="fas fa-robot"></i></span>
        </a>
        <a class="quick-fab__item" role="menuitem"
           href="<?= BASE_URL ?>dashboard/dashboard.php?log=weight">
            <span class="quick-fab__item-label"><?= t('fab.log_weight') ?></span>
            <span class="quick-fab__item-icon"><i class="fas fa-weight"></i></span>
        </a>
    </div>
    <button type="button" class="quick-fab__toggle" id="quickFabToggle"
            aria-haspopup="true" aria-expanded="false" aria-controls="quickFabMenu"
            aria-label="<?= t('fab.quick_log') ?>" title="<?= t('fab.quick_log') ?>">
        <i class="fas fa-plus quick-fab__plus"></i>
    </button>
</div>
<script>
(function () {
    var fab = document.getElementById('quickFab');
    if (!fab || fab.dataset.bound) return;
    fab.dataset.bound = '1';
    var toggle = document.getElementById('quickFabToggle');

    function setOpen(open) {
        fab.classList.toggle('is-open', open);
        toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
    }

    toggle.addEventListener('click', function (e) {
        e.stopPropagation();
        setOpen(!fab.classList.contains('is-open'));
    });
    // Collapse on outside click or Escape.
    document.addEventListener('click', function (e) {
        if (!fab.contains(e.target)) setOpen(false);
    });
    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape') setOpen(false);
    });
})();
</script>
