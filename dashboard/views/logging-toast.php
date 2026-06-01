<?php
/**
 * Auto-fires the success toast once on page load when $success_message
 * (from $_GET['success']) is set.
 *
 * The toast itself is now provided globally by js/ui-helpers.js
 * (window.showToast / window.showLoggingToast), loaded via views/head_css.php.
 * Call window.showLoggingToast(message, subtext?, type?) from anywhere.
 *
 * Usage:
 *   $success_message = $_GET['success'] ?? '';
 *   include PROJECT_ROOT . 'dashboard/views/logging-toast.php';
 */
$success_message = $success_message ?? '';
?>
<?php if (!empty($success_message)): ?>
<script>
    document.addEventListener('DOMContentLoaded', () => {
        if (typeof window.showLoggingToast === 'function') {
            window.showLoggingToast(<?= json_encode($success_message, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>);
        }
        if (typeof celebrateStreak === 'function') celebrateStreak();
    });
</script>
<?php endif; ?>
