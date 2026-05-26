<?php
/**
 * Local-time auto-converter.
 *
 * Server emits datetimes as ISO 8601 with explicit TZ offset (see toIsoVN() in
 * dashboard/handlers/functions.php). This script finds every element with a
 * [data-iso] attribute on DOMContentLoaded and replaces its textContent with
 * the same instant formatted in the visitor's browser-local timezone.
 *
 * Usage in markup:
 *   <span data-iso="<?= toIsoVN($row['date_intake']) ?>" data-tz-format="time">14:30</span>
 *
 * The fallback text (between the tags) is what server rendered — shown if JS is
 * disabled or the ISO is malformed.
 *
 * Supported data-tz-format values:
 *   time            → "14:30"          (24h hour:minute)
 *   date-day        → "26"             (day of month, 2 digits)
 *   date-monthyear  → "May 2025"       (short month + year)
 *   date-short      → "26 May"         (day + short month)
 *   date-full       → "26 May 2025"    (day + short month + year)
 *   date-long       → locale-dependent (e.g. "May 26, 2025" en-US; "26 May 2025" en-GB)
 *   datetime        → locale full datetime (default fallback)
 *
 * window.formatLocal(iso, format) is exposed for use after AJAX inserts.
 */
?>
<script>
    (function () {
        const FORMAT_OPTIONS = {
            'time':           { hour: '2-digit', minute: '2-digit', hour12: false },
            'date-day':       { day: '2-digit' },
            'date-monthyear': { month: 'short', year: 'numeric' },
            'date-short':     { day: '2-digit', month: 'short' },
            'date-full':      { day: '2-digit', month: 'short', year: 'numeric' },
            'date-long':      { day: 'numeric', month: 'long', year: 'numeric' },
        };

        window.formatLocal = function (iso, format) {
            if (!iso) return '';
            const d = new Date(iso);
            if (isNaN(d.getTime())) return '';

            const opts = FORMAT_OPTIONS[format];
            if (!opts) return d.toLocaleString(); // datetime fallback

            // Only `time` uses toLocaleTimeString; rest use toLocaleDateString.
            return format === 'time'
                ? d.toLocaleTimeString(undefined, opts)
                : d.toLocaleDateString(undefined, opts);
        };

        function convertAll(root) {
            (root || document).querySelectorAll('[data-iso]').forEach(el => {
                const iso = el.getAttribute('data-iso');
                const fmt = el.getAttribute('data-tz-format') || 'datetime';
                const out = formatLocal(iso, fmt);
                if (out) el.textContent = out;
            });
        }

        // Expose for re-running after dynamic DOM inserts (AJAX append/edit).
        window.applyLocalTime = convertAll;

        document.addEventListener('DOMContentLoaded', () => convertAll());
    })();
</script>
