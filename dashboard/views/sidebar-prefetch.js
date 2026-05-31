/**
 * Sidebar perceived-speed enhancement — NO SPA, no AJAX content swapping.
 *
 * Two pure progressive enhancements over normal full-page navigation:
 *
 *   1. Prefetch-on-hover — when the pointer rests on a sidebar link (or a touch
 *      starts on it), quietly fetch that page into the browser cache so the real
 *      click loads near-instantly. It's still a genuine full navigation, so no
 *      script re-execution / state problems exist.
 *
 *   2. Loading bar — a thin bar at the top starts on click and animates while
 *      the next page loads, giving instant feedback. The bar lives on the
 *      outgoing page and simply disappears when the new document renders.
 *
 * Both degrade silently: unsupported browsers just get plain navigation.
 */
(function () {
    'use strict';

    const sidebar = document.querySelector('.sidebar');
    if (!sidebar) return;

    // --- Shared helpers ------------------------------------------------------

    // Only same-origin, real navigations (skip #anchors, mailto:, _blank, etc).
    function navigableUrl(link) {
        if (!link) return null;
        if (link.target && link.target !== '_self') return null;
        const href = link.getAttribute('href');
        if (!href || href.charAt(0) === '#') return null;
        let url;
        try { url = new URL(href, location.href); } catch (e) { return null; }
        if (url.origin !== location.origin) return null;
        if (url.href.split('#')[0] === location.href.split('#')[0]) return null; // same page
        return url.href;
    }

    // --- 1. Prefetch on hover / touch ---------------------------------------

    // Respect data-saver and very slow connections — don't waste their bytes.
    const conn = navigator.connection;
    const prefetchAllowed =
        'relList' in document.createElement('link') &&
        document.createElement('link').relList.supports('prefetch') &&
        !(conn && (conn.saveData || /(^|-)2g$/.test(conn.effectiveType || '')));

    const prefetched = new Set();
    let hoverTimer = null;

    function prefetch(url) {
        if (!prefetchAllowed || prefetched.has(url)) return;
        prefetched.add(url);
        const link = document.createElement('link');
        link.rel = 'prefetch';
        link.href = url;
        document.head.appendChild(link);
    }

    if (prefetchAllowed) {
        // Small intent delay so a quick pass-over doesn't trigger a fetch.
        sidebar.addEventListener('pointerover', function (e) {
            const link = e.target.closest('.nav-link');
            const url = navigableUrl(link);
            if (!url || prefetched.has(url)) return;
            clearTimeout(hoverTimer);
            hoverTimer = setTimeout(function () { prefetch(url); }, 65);
        });
        sidebar.addEventListener('pointerout', function () {
            clearTimeout(hoverTimer);
        });
        // Touch has no hover — prefetch the instant a tap begins (still ~100ms
        // before the click fires, enough to warm the cache).
        sidebar.addEventListener('touchstart', function (e) {
            const link = e.target.closest('.nav-link');
            const url = navigableUrl(link);
            if (url) prefetch(url);
        }, { passive: true });
    }

    // --- 2. Top loading bar --------------------------------------------------

    const style = document.createElement('style');
    style.textContent =
        '#bb-progress{position:fixed;top:0;left:0;height:3px;width:0;z-index:99999;' +
        'background:var(--color-primary,#60a5fa);box-shadow:0 0 8px var(--color-primary,#60a5fa);' +
        'opacity:0;transition:width .2s ease,opacity .3s ease;pointer-events:none}' +
        '#bb-progress.is-active{opacity:1}';
    document.head.appendChild(style);

    const bar = document.createElement('div');
    bar.id = 'bb-progress';
    bar.setAttribute('aria-hidden', 'true');
    document.documentElement.appendChild(bar);

    let trickle = null;

    function startBar() {
        clearInterval(trickle);
        bar.classList.add('is-active');
        let pct = 8;
        bar.style.width = pct + '%';
        // Trickle toward ~90% so it feels alive without ever "finishing" before
        // the real navigation actually swaps the document.
        trickle = setInterval(function () {
            pct += (90 - pct) * 0.12;
            bar.style.width = pct + '%';
            if (pct > 89.5) clearInterval(trickle);
        }, 180);
    }

    function resetBar() {
        clearInterval(trickle);
        bar.classList.remove('is-active');
        bar.style.width = '0';
    }

    // Start on click of any navigable sidebar link.
    sidebar.addEventListener('click', function (e) {
        if (e.button !== 0 || e.metaKey || e.ctrlKey || e.shiftKey || e.altKey) return;
        if (navigableUrl(e.target.closest('.nav-link'))) startBar();
    });

    // If the user comes BACK via bfcache, the old page (bar showing) is restored
    // from memory — clear it so it doesn't sit stuck at 90%.
    window.addEventListener('pageshow', resetBar);
    window.addEventListener('pagehide', resetBar);
})();

/**
 * Keep date-carrying links in sync with the live ?date.
 *
 * On Overview the calendar changes the day via history.pushState WITHOUT a
 * reload, so links rendered server-side at page load (sidebar Overview/Intake,
 * the quick-log FAB, the per-meal "+" buttons) keep the page-load date and would
 * drop the selection when clicked. We re-point them at the current URL's ?date
 * whenever it changes — which also makes prefetch-on-hover warm the right URL.
 */
(function () {
    'use strict';

    // Links that should follow the selected day. Overview & Intake only — other
    // sidebar pages don't share this date flow. "Back to today" opts out.
    const SELECTOR = [
        '.sidebar a[href*="dashboard.php"]',
        '.sidebar a[href*="dashboard-intake.php"]',
        '.quick-log-fab',
        'a.btn-add-bento',
    ].join(',');

    function currentDate() {
        const d = new URLSearchParams(location.search).get('date');
        return /^\d{4}-\d{2}-\d{2}$/.test(d || '') ? d : null;
    }

    function withDate(href, date) {
        try {
            const u = new URL(href, location.href);
            if (date) u.searchParams.set('date', date);
            else u.searchParams.delete('date');
            return u.pathname + u.search;
        } catch (e) {
            return href;
        }
    }

    function refresh() {
        const date = currentDate();
        document.querySelectorAll(SELECTOR).forEach(function (a) {
            if (a.hasAttribute('data-no-date')) return; // e.g. "Back to today"
            a.setAttribute('href', withDate(a.getAttribute('href'), date));
        });
    }

    // pushState/replaceState fire no event — wrap them so we can react to the
    // calendar's in-place day changes.
    ['pushState', 'replaceState'].forEach(function (m) {
        const orig = history[m];
        history[m] = function () {
            const r = orig.apply(this, arguments);
            window.dispatchEvent(new Event('bb:locationchange'));
            return r;
        };
    });

    function onChange() {
        refresh();
        // The calendar replaces some content (incl. the bento "+") AFTER pushState;
        // re-run on the next tick so those fresh links get the date too.
        setTimeout(refresh, 0);
    }

    window.addEventListener('bb:locationchange', onChange);
    window.addEventListener('popstate', onChange);
    if (document.readyState !== 'loading') refresh();
    else document.addEventListener('DOMContentLoaded', refresh);
})();
