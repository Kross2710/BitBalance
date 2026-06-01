/* js/pt-chat.js — shared two-way PT <-> Client chat widget (Task #3).
 *
 * Markup contract:
 *   <div class="pt-chat"
 *        data-endpoint="…/pt_chat.php"
 *        data-csrf="…"
 *        data-self-role="trainer|client"
 *        data-counterpart-id="123"      (optional; can be set later)
 *        data-empty-text="…"            (optional)
 *        data-auto-init>                 (optional; auto-load on DOMContentLoaded)
 *     <div class="pt-chat__messages"></div>
 *     <form class="pt-chat__form">
 *       <textarea class="pt-chat__input"></textarea>
 *       <button type="submit">Send</button>
 *     </form>
 *   </div>
 *
 * PT dashboard reuses one instance and calls setCounterpart(id) + load() as the
 * selected client changes; the client page just auto-inits a single instance.
 */
(function () {
    function esc(s) {
        const d = document.createElement('div');
        d.textContent = s == null ? '' : String(s);
        return d.innerHTML;
    }

    function notify(msg, type) {
        if (window.showToast) { window.showToast(msg, { type: type || 'error' }); }
        else if (window.showLoggingToast) { window.showLoggingToast(msg, '', type || 'error'); }
        else { alert(msg); }
    }

    function fmtTime(iso) {
        if (!iso) return '';
        const d = new Date(String(iso).replace(' ', 'T'));
        if (isNaN(d)) return '';
        return d.toLocaleString([], { day: '2-digit', month: '2-digit', hour: '2-digit', minute: '2-digit' });
    }

    class PTChat {
        constructor(root) {
            this.root = root;
            this.endpoint = root.dataset.endpoint;
            this.csrf = root.dataset.csrf || '';
            this.selfRole = root.dataset.selfRole || 'client';
            this.emptyText = root.dataset.emptyText || 'No messages yet.';
            this.counterpart = root.dataset.counterpartId ? parseInt(root.dataset.counterpartId, 10) : null;
            this.messagesEl = root.querySelector('.pt-chat__messages');
            this.form = root.querySelector('.pt-chat__form');
            this.input = root.querySelector('.pt-chat__input');
            this.loaded = false;

            // Live updates: poll for new messages every 12s. lastId is the fetch
            // cursor (highest id received from the server); seenIds dedupes against
            // optimistically-rendered sends so a poll never double-shows a message.
            this.lastId = 0;
            this.seenIds = new Set();
            this.pollMs = 12000;
            this._pollTimer = null;
            // Pause polling while the tab is hidden; catch up the moment it returns.
            document.addEventListener('visibilitychange', () => {
                if (!document.hidden && this.counterpart) this.poll();
            });

            // Better on-screen keyboard hints (the action key reads "send").
            this.input.setAttribute('enterkeyhint', 'send');
            this.input.setAttribute('autocomplete', 'off');
            this.baseHeight = 0;

            this.form.addEventListener('submit', (e) => this.onSubmit(e));

            // Enter sends, Shift+Enter = newline. Skip while the IME is composing
            // (e.g. Vietnamese/Telex) so Enter confirms the candidate, not sends.
            this.input.addEventListener('keydown', (e) => {
                if (e.key === 'Enter' && !e.shiftKey && !e.isComposing && e.keyCode !== 229) {
                    e.preventDefault();
                    this.form.requestSubmit ? this.form.requestSubmit() : this.onSubmit(new Event('submit'));
                }
            });

            // Auto-grow the textarea as the user types (capped; then it scrolls).
            this.input.addEventListener('input', () => this.autoGrow());

            // When focused (mobile keyboard opens), keep the latest message in view.
            this.input.addEventListener('focus', () => {
                setTimeout(() => { this.messagesEl.scrollTop = this.messagesEl.scrollHeight; }, 150);
            });
        }

        autoGrow() {
            const el = this.input;
            if (!this.baseHeight) this.baseHeight = el.clientHeight;
            el.style.height = 'auto';
            el.style.height = Math.min(el.scrollHeight, 120) + 'px';
        }

        resetInputHeight() {
            this.input.style.height = '';
            this.baseHeight = 0;
        }

        setCounterpart(id) {
            this.counterpart = id ? parseInt(id, 10) : null;
            this.loaded = false;
            this.lastId = 0;
            this.seenIds = new Set();
        }

        hint(text) {
            this.messagesEl.innerHTML = '<p class="pt-chat__hint">' + esc(text) + '</p>';
        }

        async load() {
            if (!this.counterpart) { this.hint(this.emptyText); return; }
            this.hint('…');
            try {
                const fd = new FormData();
                fd.append('action', 'fetch');
                fd.append('counterpart_id', this.counterpart);
                const res = await fetch(this.endpoint, { method: 'POST', headers: { 'X-Requested-With': 'fetch' }, body: fd });
                const data = await res.json();
                if (data.ok) {
                    this.loaded = true;
                    this.render(data.messages || []);
                    this.startPolling();
                } else {
                    this.hint(data.error || 'Error loading messages');
                }
            } catch (e) {
                this.hint('Connection error');
            }
        }

        render(msgs) {
            this.messagesEl.innerHTML = '';
            this.seenIds = new Set();
            this.lastId = 0;
            if (!msgs.length) { this.hint(this.emptyText); return; }
            msgs.forEach((m) => this.appendMessage(m, true));
            this.messagesEl.scrollTop = this.messagesEl.scrollHeight;
        }

        appendMessage(m, fromFetch) {
            const id = parseInt(m.message_id, 10);
            if (!isNaN(id)) {
                if (this.seenIds.has(id)) return;       // already rendered (dedupe)
                this.seenIds.add(id);
                if (fromFetch && id > this.lastId) this.lastId = id;
            }
            const mine = m.sender_role === this.selfRole;
            const row = document.createElement('div');
            row.className = 'pt-chat__msg ' + (mine ? 'pt-chat__msg--mine' : 'pt-chat__msg--them');
            row.innerHTML =
                '<div class="pt-chat__bubble">' + esc(m.content) + '</div>' +
                '<div class="pt-chat__time">' + esc(fmtTime(m.created_at)) + '</div>';
            this.messagesEl.appendChild(row);
        }

        isNearBottom() {
            const el = this.messagesEl;
            return (el.scrollHeight - el.scrollTop - el.clientHeight) < 60;
        }

        startPolling() {
            if (this._pollTimer) return;
            this._pollTimer = setInterval(() => this.poll(), this.pollMs);
        }

        // Fetch only messages newer than our cursor and append the new ones. Skips
        // when the tab is hidden, no counterpart is selected, or a send/poll is
        // already in flight. Only auto-scrolls if the user is already at the bottom.
        async poll() {
            if (document.hidden || !this.counterpart || this.sending || this._polling) return;
            this._polling = true;
            try {
                const fd = new FormData();
                fd.append('action', 'fetch');
                fd.append('counterpart_id', this.counterpart);
                fd.append('since', this.lastId);
                const res = await fetch(this.endpoint, { method: 'POST', headers: { 'X-Requested-With': 'fetch' }, body: fd });
                const data = await res.json();
                if (data.ok && data.messages && data.messages.length) {
                    const stick = this.isNearBottom();
                    if (this.messagesEl.querySelector('.pt-chat__hint')) { this.messagesEl.innerHTML = ''; this.loaded = true; }
                    data.messages.forEach((m) => this.appendMessage(m, true));
                    if (stick) this.messagesEl.scrollTop = this.messagesEl.scrollHeight;
                }
            } catch (e) {
                /* transient — try again on the next tick */
            } finally {
                this._polling = false;
            }
        }

        async onSubmit(e) {
            if (e && e.preventDefault) e.preventDefault();
            // In-flight guard: a slow request must not let a second Enter/click
            // fire the same message again (the cause of duplicate sends).
            if (this.sending) return;
            const content = (this.input.value || '').trim();
            if (!content || !this.counterpart) return;

            this.sending = true;
            this.input.value = ''; // clear up front so a repeat submit has nothing to send
            this.resetInputHeight();
            const btn = this.form.querySelector('button[type="submit"]');
            let btnOrig = null;
            if (btn) {
                btnOrig = btn.innerHTML;
                btn.disabled = true;
                btn.innerHTML = '<i class="fas fa-spinner fa-spin" aria-hidden="true"></i>';
            }
            try {
                const fd = new FormData();
                fd.append('action', 'send');
                fd.append('counterpart_id', this.counterpart);
                fd.append('content', content);
                fd.append('csrf_token', this.csrf);
                const res = await fetch(this.endpoint, { method: 'POST', headers: { 'X-Requested-With': 'fetch' }, body: fd });
                const data = await res.json();
                if (data.ok) {
                    // Optimistically append, clearing the empty hint if present.
                    if (!this.loaded || this.messagesEl.querySelector('.pt-chat__hint')) {
                        this.messagesEl.innerHTML = '';
                        this.loaded = true;
                    }
                    this.appendMessage(data.message, false);
                    this.messagesEl.scrollTop = this.messagesEl.scrollHeight;
                    // Keep focus so the keyboard stays up for back-to-back messages.
                    this.input.focus();
                } else {
                    this.input.value = content; // restore so the user can retry
                    this.autoGrow();
                    notify(data.error || 'Failed to send', 'error');
                }
            } catch (e) {
                this.input.value = content; // restore on network error
                this.autoGrow();
                notify('Connection error', 'error');
            } finally {
                this.sending = false;
                if (btn) { btn.disabled = false; if (btnOrig !== null) btn.innerHTML = btnOrig; }
            }
        }
    }

    window.PTChat = PTChat;

    // iOS/WebKit fix: opening the on-screen keyboard scrolls the page down to
    // reveal the input; when it closes, the viewport grows back but the page can
    // stay scrolled past its content, leaving a blank gap below the footer. When
    // the visual viewport grows (keyboard dismissed), clamp the scroll back in.
    if (window.visualViewport && !window.__ptChatViewportFix) {
        window.__ptChatViewportFix = true;
        let prevH = window.visualViewport.height;
        let timer = null;
        window.visualViewport.addEventListener('resize', () => {
            const h = window.visualViewport.height;
            const grew = h > prevH + 40; // keyboard just closed
            prevH = h;
            if (!grew) return;
            clearTimeout(timer);
            timer = setTimeout(() => {
                const max = document.documentElement.scrollHeight - window.innerHeight;
                if (window.scrollY > max + 1) window.scrollTo(0, Math.max(0, max));
            }, 80);
        });
    }

    document.addEventListener('DOMContentLoaded', () => {
        document.querySelectorAll('.pt-chat[data-auto-init]').forEach((root) => {
            if (root._ptChat) return; // never double-bind the same widget
            const c = new PTChat(root);
            root._ptChat = c;
            c.load();
        });
    });
})();
