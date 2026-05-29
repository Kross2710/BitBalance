<?php
/**
 * Reusable logging success toast (slide-up, auto-hide 3.5s).
 *
 * Include on any dashboard page that needs success notifications.
 * Auto-fires once on page load if $success_message (from $_GET['success']) is set.
 * Call window.showLoggingToast(message, subtext?, type?) from JS to trigger manually.
 *
 * Usage:
 *   $success_message = $_GET['success'] ?? '';
 *   include PROJECT_ROOT . 'dashboard/views/logging-toast.php';
 */
$success_message = $success_message ?? '';
?>
<div id="loggingToast" class="logging-toast" role="status" aria-live="polite">
    <div class="toast-content">
        <div class="toast-icon"><i class="fas fa-check-circle"></i></div>
        <div class="toast-text">
            <span id="toastMessage"><?= t('toast.logged_success') ?></span>
            <span id="toastSubtext" class="toast-subtext"></span>
        </div>
    </div>
</div>

<script>
    // Logging success toast — slides up, auto-hides after 3.5s
    window.showLoggingToast = function (message, subtext, type) {
        subtext = subtext || '';
        type = type || 'success';
        const toast = document.getElementById('loggingToast');
        const iconEl = toast ? toast.querySelector('.toast-icon i') : null;
        const msgEl = document.getElementById('toastMessage');
        const subEl = document.getElementById('toastSubtext');
        if (!toast || !msgEl) return;

        toast.classList.remove('success', 'error', 'warning');
        toast.classList.add(type);
        if (iconEl) {
            iconEl.className = type === 'error'
                ? 'fas fa-circle-exclamation'
                : (type === 'warning' ? 'fas fa-triangle-exclamation' : 'fas fa-check-circle');
        }
        msgEl.textContent = message;
        if (subEl) subEl.textContent = subtext;

        toast.classList.add('show');
        clearTimeout(toast._hideTimer);
        toast._hideTimer = setTimeout(() => toast.classList.remove('show'), 3500);
    };

    <?php if (!empty($success_message)): ?>
    document.addEventListener('DOMContentLoaded', () => {
        showLoggingToast(<?= json_encode($success_message, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>);
        if (typeof celebrateStreak === 'function') celebrateStreak();
    });
    <?php endif; ?>
</script>
