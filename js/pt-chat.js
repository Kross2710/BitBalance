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

            this.form.addEventListener('submit', (e) => this.onSubmit(e));
            // Enter sends, Shift+Enter = newline.
            this.input.addEventListener('keydown', (e) => {
                if (e.key === 'Enter' && !e.shiftKey) {
                    e.preventDefault();
                    this.form.requestSubmit ? this.form.requestSubmit() : this.onSubmit(new Event('submit'));
                }
            });
        }

        setCounterpart(id) {
            this.counterpart = id ? parseInt(id, 10) : null;
            this.loaded = false;
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
                } else {
                    this.hint(data.error || 'Error loading messages');
                }
            } catch (e) {
                this.hint('Connection error');
            }
        }

        render(msgs) {
            this.messagesEl.innerHTML = '';
            if (!msgs.length) { this.hint(this.emptyText); return; }
            msgs.forEach((m) => this.appendMessage(m));
            this.messagesEl.scrollTop = this.messagesEl.scrollHeight;
        }

        appendMessage(m) {
            const mine = m.sender_role === this.selfRole;
            const row = document.createElement('div');
            row.className = 'pt-chat__msg ' + (mine ? 'pt-chat__msg--mine' : 'pt-chat__msg--them');
            row.innerHTML =
                '<div class="pt-chat__bubble">' + esc(m.content) + '</div>' +
                '<div class="pt-chat__time">' + esc(fmtTime(m.created_at)) + '</div>';
            this.messagesEl.appendChild(row);
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
            const btn = this.form.querySelector('button[type="submit"]');
            if (btn) btn.disabled = true;
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
                    this.appendMessage(data.message);
                    this.messagesEl.scrollTop = this.messagesEl.scrollHeight;
                } else {
                    this.input.value = content; // restore so the user can retry
                    alert(data.error || 'Failed to send');
                }
            } catch (e) {
                this.input.value = content; // restore on network error
                alert('Connection error');
            } finally {
                this.sending = false;
                if (btn) btn.disabled = false;
            }
        }
    }

    window.PTChat = PTChat;

    document.addEventListener('DOMContentLoaded', () => {
        document.querySelectorAll('.pt-chat[data-auto-init]').forEach((root) => {
            if (root._ptChat) return; // never double-bind the same widget
            const c = new PTChat(root);
            root._ptChat = c;
            c.load();
        });
    });
})();
