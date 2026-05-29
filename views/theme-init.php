<?php
/**
 * Theme bootstrap — MUST run in <head> BEFORE any stylesheet so the page
 * paints in the correct theme (no flash of wrong theme / FOUC).
 *
 * The server renders <html data-theme="..."> from the user's saved
 * preference, which is one of:
 *   - 'light' / 'dark' : an explicit choice that OVERRIDES the OS.
 *   - 'system' (or empty) : FOLLOW the OS / browser color scheme.
 *
 * This script resolves 'system' to a concrete 'light'/'dark' via
 * prefers-color-scheme, keeps the original preference in data-theme-pref,
 * and live-updates the page if the OS scheme flips while in system mode.
 *
 * No dependency on PROJECT_ROOT/BASE_URL — safe to include from anywhere:
 *   include __DIR__ . '/theme-init.php';
 */
?>
<script>
    (function () {
        var root = document.documentElement;
        var pref = root.getAttribute('data-theme') || 'system';
        var mq = window.matchMedia ? window.matchMedia('(prefers-color-scheme: dark)') : null;

        // Keep the *preference* separate from the *resolved* theme so the
        // OS listener below knows whether it's still allowed to take over.
        root.setAttribute('data-theme-pref', pref);

        if (pref === 'system') {
            root.setAttribute('data-theme', (mq && mq.matches) ? 'dark' : 'light');
        }

        // Follow the OS in real time, but only while the preference is "system".
        if (mq) {
            var onChange = function (e) {
                if (root.getAttribute('data-theme-pref') === 'system') {
                    root.setAttribute('data-theme', e.matches ? 'dark' : 'light');
                }
            };
            if (mq.addEventListener) {
                mq.addEventListener('change', onChange);
            } else if (mq.addListener) {
                mq.addListener(onChange); // Safari < 14
            }
        }
    })();
</script>
