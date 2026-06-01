/* =============================================================
 * BitBalance UI helpers — unified, non-blocking feedback.
 *
 *   showToast(message, { subtext, type, duration, action })
 *       type: 'success' | 'error' | 'warning' | 'info'   (default 'success')
 *       duration: ms before auto-dismiss; 0 = sticky      (default 3500)
 *       action: { label, onClick }  -> renders a button   (e.g. Undo / Retry)
 *       returns { dismiss(), el }
 *
 *   showConfirm({ title, message, confirmLabel, cancelLabel, danger })
 *       -> Promise<boolean>  (true = confirmed, false = cancelled)
 *
 *   showLoggingToast(message, subtext, type)   // back-compat alias
 *
 * Self-contained: injects its own DOM + relies on css/components/ui-feedback.css.
 * Loaded once (deferred) from views/head_css.php, so available on every page.
 * ============================================================= */
(function () {
    'use strict';
    if (window.__bbUiHelpers) return;
    window.__bbUiHelpers = true;

    var lang = (document.documentElement.getAttribute('lang') || 'en').toLowerCase().indexOf('vi') === 0 ? 'vi' : 'en';
    var STR = {
        confirm: lang === 'vi' ? 'Đồng ý' : 'Confirm',
        cancel: lang === 'vi' ? 'Huỷ' : 'Cancel',
        title: lang === 'vi' ? 'Bạn chắc chứ?' : 'Are you sure?',
        close: lang === 'vi' ? 'Đóng' : 'Close'
    };

    var ICONS = {
        success: 'fa-circle-check',
        error: 'fa-circle-exclamation',
        warning: 'fa-triangle-exclamation',
        info: 'fa-circle-info'
    };

    var stack = null;
    var modal = null, modalTitle, modalMsg, modalConfirm, modalCancel;
    var confirmResolver = null;

    function ensureStack() {
        if (stack && document.body.contains(stack)) return stack;
        stack = document.createElement('div');
        stack.className = 'bb-toast-stack';
        stack.setAttribute('role', 'status');
        stack.setAttribute('aria-live', 'polite');
        document.body.appendChild(stack);
        return stack;
    }

    function ensureModal() {
        if (modal && document.body.contains(modal)) return modal;
        modal = document.createElement('div');
        modal.className = 'bb-confirm-overlay';
        modal.innerHTML =
            '<div class="bb-confirm-box" role="dialog" aria-modal="true" aria-labelledby="bbConfirmTitle">'
            + '<button type="button" class="bb-confirm-close" aria-label="' + STR.close + '">&times;</button>'
            + '<h3 id="bbConfirmTitle" class="bb-confirm-title"></h3>'
            + '<p class="bb-confirm-msg"></p>'
            + '<div class="bb-confirm-actions">'
            + '<button type="button" class="bb-btn bb-btn-cancel"></button>'
            + '<button type="button" class="bb-btn bb-btn-confirm"></button>'
            + '</div></div>';
        document.body.appendChild(modal);
        modalTitle = modal.querySelector('.bb-confirm-title');
        modalMsg = modal.querySelector('.bb-confirm-msg');
        modalConfirm = modal.querySelector('.bb-btn-confirm');
        modalCancel = modal.querySelector('.bb-btn-cancel');

        modalConfirm.addEventListener('click', function () { resolveConfirm(true); });
        modalCancel.addEventListener('click', function () { resolveConfirm(false); });
        modal.querySelector('.bb-confirm-close').addEventListener('click', function () { resolveConfirm(false); });
        modal.addEventListener('click', function (e) { if (e.target === modal) resolveConfirm(false); });
        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape' && modal.classList.contains('is-open')) resolveConfirm(false);
        });
        return modal;
    }

    function showToast(message, opts) {
        opts = opts || {};
        ensureStack();
        var type = opts.type || 'success';
        var duration = typeof opts.duration === 'number' ? opts.duration : 3500;

        var toast = document.createElement('div');
        toast.className = 'bb-toast bb-toast--' + type;

        var icon = document.createElement('div');
        icon.className = 'bb-toast__icon';
        icon.innerHTML = '<i class="fas ' + (ICONS[type] || ICONS.success) + '"></i>';
        toast.appendChild(icon);

        var textWrap = document.createElement('div');
        textWrap.className = 'bb-toast__text';
        var msg = document.createElement('span');
        msg.className = 'bb-toast__msg';
        msg.textContent = message == null ? '' : String(message);
        textWrap.appendChild(msg);
        if (opts.subtext) {
            var sub = document.createElement('span');
            sub.className = 'bb-toast__sub';
            sub.textContent = String(opts.subtext);
            textWrap.appendChild(sub);
        }
        toast.appendChild(textWrap);

        var hideTimer = null;
        var dismissed = false;
        function dismiss() {
            if (dismissed) return;
            dismissed = true;
            clearTimeout(hideTimer);
            toast.classList.remove('is-show');
            setTimeout(function () { if (toast.parentNode) toast.parentNode.removeChild(toast); }, 300);
        }

        if (opts.action && opts.action.label) {
            var actionBtn = document.createElement('button');
            actionBtn.type = 'button';
            actionBtn.className = 'bb-toast__action';
            actionBtn.textContent = opts.action.label;
            actionBtn.addEventListener('click', function () {
                try {
                    if (typeof opts.action.onClick === 'function') opts.action.onClick();
                } finally {
                    dismiss();
                }
            });
            toast.appendChild(actionBtn);
        }

        var closeBtn = document.createElement('button');
        closeBtn.type = 'button';
        closeBtn.className = 'bb-toast__close';
        closeBtn.setAttribute('aria-label', STR.close);
        closeBtn.innerHTML = '&times;';
        closeBtn.addEventListener('click', dismiss);
        toast.appendChild(closeBtn);

        stack.appendChild(toast);
        void toast.offsetWidth; // force reflow so the transition runs
        toast.classList.add('is-show');

        if (duration > 0) hideTimer = setTimeout(dismiss, duration);

        return { dismiss: dismiss, el: toast };
    }

    function resolveConfirm(val) {
        if (!modal) return;
        modal.classList.remove('is-open');
        document.body.classList.remove('bb-modal-open');
        var r = confirmResolver;
        confirmResolver = null;
        if (r) r(val);
    }

    function showConfirm(opts) {
        opts = opts || {};
        ensureModal();
        modalTitle.textContent = opts.title || STR.title;
        modalMsg.textContent = opts.message || '';
        modalMsg.style.display = opts.message ? '' : 'none';
        modalConfirm.textContent = opts.confirmLabel || STR.confirm;
        modalCancel.textContent = opts.cancelLabel || STR.cancel;
        modalConfirm.className = 'bb-btn bb-btn-confirm' + (opts.danger ? ' bb-btn-danger' : '');

        return new Promise(function (resolve) {
            if (confirmResolver) confirmResolver(false); // close any previous one
            confirmResolver = resolve;
            modal.classList.add('is-open');
            document.body.classList.add('bb-modal-open');
            setTimeout(function () { try { modalConfirm.focus(); } catch (e) {} }, 30);
        });
    }

    /* ---------- Declarative confirm ----------
     * Progressive enhancement for simple cases. Add to a single-submit <form>,
     * an <a href>, or a standalone <button>:
     *   data-confirm="message"  [data-confirm-title] [data-confirm-ok] [data-confirm-danger]
     * NOTE: not for multi-submit-button forms — a programmatic submit() drops the
     * clicked button's name/value. Use window.showConfirm() in a JS handler there.
     */
    function handleDeclarative(e, el) {
        e.preventDefault();
        showConfirm({
            message: el.getAttribute('data-confirm') || '',
            title: el.getAttribute('data-confirm-title') || undefined,
            confirmLabel: el.getAttribute('data-confirm-ok') || undefined,
            danger: el.hasAttribute('data-confirm-danger')
        }).then(function (ok) {
            if (!ok) return;
            if (el.tagName === 'FORM') el.submit();
            else if (el.tagName === 'A' && el.href) window.location.href = el.href;
            else if (el.form) el.form.submit();
        });
    }
    document.addEventListener('submit', function (e) {
        var f = e.target;
        if (f && f.tagName === 'FORM' && f.hasAttribute('data-confirm')) handleDeclarative(e, f);
    }, false);
    document.addEventListener('click', function (e) {
        var el = e.target.closest && e.target.closest('[data-confirm]');
        if (el && el.tagName !== 'FORM') handleDeclarative(e, el);
    }, false);

    /* ---------- Submit lock ----------
     * <form class="js-submit-lock"> disables its submit button + shows a spinner
     * when it submits, and blocks accidental double-submits. The submit itself
     * proceeds normally (full-page POST); the button is disabled on the next tick
     * so its name/value is still included in the request. The submit event only
     * fires once the browser's own validation passes, so an invalid form never
     * gets locked.
     */
    document.addEventListener('submit', function (e) {
        var form = e.target;
        if (!(form instanceof HTMLFormElement) || !form.classList.contains('js-submit-lock')) return;
        if (form.dataset.bbLocked === '1') { e.preventDefault(); return; }
        form.dataset.bbLocked = '1';
        var btn = form.querySelector('button[type="submit"], input[type="submit"]');
        if (btn && 'innerHTML' in btn) {
            btn.dataset.bbOrig = btn.innerHTML;
            btn.innerHTML = '<i class="fas fa-spinner fa-spin" aria-hidden="true"></i> ' + btn.dataset.bbOrig;
        }
        if (btn) setTimeout(function () { btn.disabled = true; }, 0);
    }, false);

    window.showToast = showToast;
    window.showConfirm = showConfirm;
    window.showLoggingToast = function (message, subtext, type) {
        return showToast(message, { subtext: subtext, type: type || 'success' });
    };
})();
