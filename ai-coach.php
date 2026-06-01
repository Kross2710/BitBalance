<?php
require_once __DIR__ . '/include/init.php';
require_once __DIR__ . '/include/csrf.php';

// Require login
if (!$isLoggedIn) {
    header('Location: ' . BASE_URL . 'login.php');
    exit;
}

$activeHeader = 'ai-coach';
$csrfToken    = csrf_token();
?>
<!DOCTYPE html>
<html lang="<?= html_lang_attr() ?>"
    data-theme="<?php echo $_SESSION['user']['theme_preference'] ?? 'system'; ?>">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= t('aic.title_tag') ?></title>

    <?php
    $pageCss = ['css/pages/ai-coach.css'];
    include PROJECT_ROOT . 'views/head_css.php';
    ?>
    <script src="https://kit.fontawesome.com/b94f65ead2.js" crossorigin="anonymous"></script>
    <script src="<?= BASE_URL ?>js/image-compress.js?v=<?= @filemtime(PROJECT_ROOT . 'js/image-compress.js') ?>"></script>
</head>

<body class="ai-coach-body">
    <?php include 'views/header.php'; ?>

    <main class="aic-shell" id="aicShell">
        <!-- Backdrop (mobile only, behind sidebar) -->
        <div class="aic-backdrop" id="aicBackdrop"></div>

        <!-- Sidebar: conversation list -->
        <aside class="aic-sidebar" id="aicSidebar">
            <div class="aic-sidebar-header">
                <button class="aic-new-chat-btn" id="aicNewChatBtn">
                    <i class="fas fa-plus"></i> <?= t('aic.new_chat') ?>
                </button>
            </div>
            <div class="aic-conv-list" id="aicConvList">
                <div class="aic-conv-empty"><?= t('aic.loading') ?></div>
            </div>
            <div class="aic-sidebar-footer">
                <span class="aic-usage" id="aicUsage"><?= t('aic.usage_placeholder') ?></span>
            </div>
        </aside>

        <!-- Main chat panel -->
        <section class="aic-main">
            <div class="aic-main-topbar">
                <button class="aic-sidebar-toggle" id="aicSidebarToggle" title="<?= t('aic.toggle_chats_title') ?>" aria-label="<?= t('aic.toggle_chats_aria') ?>">
                    <i class="fas fa-bars"></i>
                </button>
                <span class="aic-main-title"><?= t('aic.main_title') ?></span>
            </div>
            <div class="aic-messages" id="aicMessages">
                <div class="aic-welcome">
                    <div class="aic-welcome-icon"><i class="fas fa-sparkles"></i></div>
                    <h1><?= t('aic.welcome.title') ?></h1>
                    <p><?= t('aic.welcome.subtitle') ?></p>
                    <div class="aic-suggestions">
                        <button class="aic-suggest" data-prompt="<?= t('aic.suggest.goal') ?>"><?= t('aic.suggest.goal') ?></button>
                        <button class="aic-suggest" data-prompt="<?= t('aic.suggest.dinner') ?>"><?= t('aic.suggest.dinner') ?></button>
                        <button class="aic-suggest" data-prompt="<?= t('aic.suggest.patterns') ?>"><?= t('aic.suggest.patterns') ?></button>
                    </div>
                </div>
            </div>

            <form class="aic-composer" id="aicComposer" enctype="multipart/form-data">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES) ?>">
                <input type="hidden" name="conversation_id" id="aicConvId" value="">

                <div class="aic-image-preview" id="aicImagePreview" hidden>
                    <img id="aicImagePreviewImg" alt="">
                    <button type="button" class="aic-img-remove" id="aicImageRemove" title="<?= t('aic.remove_image') ?>">
                        <i class="fas fa-times"></i>
                    </button>
                </div>

                <div class="aic-composer-row">
                    <label class="aic-attach-btn" title="<?= t('aic.attach_image') ?>">
                        <i class="fas fa-image"></i>
                        <input type="file" name="image" id="aicImageInput" accept="image/jpeg,image/png,image/webp" hidden>
                    </label>
                    <textarea
                        class="aic-input"
                        id="aicInput"
                        name="message"
                        placeholder="<?= t('aic.input_placeholder') ?>"
                        rows="1"></textarea>
                    <button type="submit" class="aic-send-btn" id="aicSendBtn" title="<?= t('aic.send_title') ?>">
                        <i class="fas fa-paper-plane"></i>
                    </button>
                </div>
                <div class="aic-composer-hint">
                    <?= t('aic.composer_hint') ?>
                </div>
            </form>
        </section>
    </main>

    <script>
    (function () {
        const API = '<?= BASE_URL ?>handlers/ai_coach.php';
        const BASE = '<?= BASE_URL ?>';
        const CSRF = '<?= htmlspecialchars($csrfToken, ENT_QUOTES) ?>';
        // Translated strings used by the streaming chat client. We expose them
        // as raw template strings; the JS does its own {placeholder} substitution
        // alongside DOM-safe escaping.
        const I18N = {
            logging: <?= json_encode(t_raw('aic.logging')) ?>,
            logged: <?= json_encode(t_raw('aic.logged')) ?>,
            couldNotLog: <?= json_encode(t_raw('aic.could_not_log', ['error' => '{error}'])) ?>,
            unknownError: <?= json_encode(t_raw('aic.unknown_error')) ?>,
            networkError: <?= json_encode(t_raw('aic.network_error', ['error' => '{error}'])) ?>,
            errorLoading: <?= json_encode(t_raw('aic.error_loading')) ?>,
            noChats: <?= json_encode(t_raw('aic.no_chats')) ?>,
            deleteTitle: <?= json_encode(t_raw('aic.delete_title')) ?>,
            confirmDelete: <?= json_encode(t_raw('aic.confirm_delete')) ?>,
            couldNotLoad: <?= json_encode(t_raw('aic.could_not_load', ['error' => '{error}'])) ?>,
            imageTooBig: <?= json_encode(t_raw('aic.image_too_big')) ?>,
            imageReadFailed: <?= json_encode(t_raw('aic.image_read_failed', ['error' => '{error}'])) ?>,
            decodeFailed: <?= json_encode(t_raw('aic.decode_failed')) ?>,
            emptyImage: <?= json_encode(t_raw('aic.empty_image')) ?>,
            encodeFailed: <?= json_encode(t_raw('aic.encode_failed')) ?>,
            usageFmt: <?= json_encode(t_raw('aic.usage', ['used' => '{used}', 'limit' => '{limit}'])) ?>,
        };

        const $messages = document.getElementById('aicMessages');
        const $convList = document.getElementById('aicConvList');
        const $form     = document.getElementById('aicComposer');
        const $input    = document.getElementById('aicInput');
        const $convId   = document.getElementById('aicConvId');
        const $newBtn   = document.getElementById('aicNewChatBtn');
        const $sendBtn  = document.getElementById('aicSendBtn');
        const $imageIn  = document.getElementById('aicImageInput');
        const $preview  = document.getElementById('aicImagePreview');
        const $previewImg = document.getElementById('aicImagePreviewImg');
        const $imageRm  = document.getElementById('aicImageRemove');
        const $usage    = document.getElementById('aicUsage');
        const $shell    = document.getElementById('aicShell');
        const $toggle   = document.getElementById('aicSidebarToggle');
        const $backdrop = document.getElementById('aicBackdrop');

        let currentConvId = null;
        let isSending = false;
        // Holds the *processed* (re-encoded SDR JPEG) file ready to upload.
        // We don't trust $imageIn.files[0] at submit time because that's the
        // raw user pick (possibly HDR / huge / HEIC-converted).
        let pendingImageFile = null;

        // ---------- Sidebar toggle ----------
        const MOBILE_BP = 768;
        function isMobile() { return window.innerWidth <= MOBILE_BP; }
        // Initial state: closed on mobile, open on desktop
        if (isMobile()) $shell.classList.add('sidebar-collapsed');

        function closeSidebarOnMobile() {
            if (isMobile()) $shell.classList.add('sidebar-collapsed');
        }
        $toggle.addEventListener('click', () => {
            $shell.classList.toggle('sidebar-collapsed');
        });
        $backdrop.addEventListener('click', () => {
            $shell.classList.add('sidebar-collapsed');
        });
        // Re-evaluate on resize (avoid stuck "open" overlay when rotating to desktop)
        window.addEventListener('resize', () => {
            if (!isMobile()) $shell.classList.remove('sidebar-collapsed');
        });

        // ---------- iOS keyboard-aware layout ----------
        // Why: on iOS Safari, `dvh`/`svh` units do NOT shrink when the
        // on-screen keyboard opens — only `window.visualViewport.height`
        // does. So we mirror visualViewport into CSS custom properties
        // (--aic-vh / --aic-vp-top) that the layout consumes. Body + shell
        // then shrink in real time with the keyboard, keeping the composer
        // glued to the top of the keyboard instead of vanishing behind it.
        //
        // Also doubles as the scroll-reset guard: re-pinning body to the
        // visualViewport on every change means iOS can't leave the header
        // floating below the top of the screen.
        const docEl = document.documentElement;
        function syncViewport() {
            const vv = window.visualViewport;
            const vh = vv ? vv.height : window.innerHeight;
            const vt = vv ? vv.offsetTop : 0;
            docEl.style.setProperty('--aic-vh', vh + 'px');
            docEl.style.setProperty('--aic-vp-top', vt + 'px');
        }
        syncViewport();
        window.addEventListener('resize', syncViewport);
        window.addEventListener('orientationchange', syncViewport);
        if (window.visualViewport) {
            window.visualViewport.addEventListener('resize', syncViewport);
            window.visualViewport.addEventListener('scroll', syncViewport);
        }
        
        // Programmatically reset scroll position on focus and blur to counteract iOS forced page scrolling.
        // We isolate this logic from continuous visualViewport event listeners to prevent the infinite
        // scroll event recursion loop which freezes and crashes Safari on iOS (iPhones).
        const resetScroll = () => {
            if (window.scrollY !== 0 || window.scrollX !== 0) {
                window.scrollTo(0, 0);
            }
            syncViewport();
        };

        $input.addEventListener('focus', () => {
            setTimeout(resetScroll, 100);
        });
        $input.addEventListener('blur', () => {
            setTimeout(resetScroll, 100);
        });

        // ---------- Helpers ----------
        function escapeHtml(s) {
            return String(s).replace(/[&<>"']/g, c => ({
                '&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'
            }[c]));
        }

        function formatInline(s) {
            return s
                // Bold: **text**
                .replace(/\*\*([^\n*][^*\n]*?)\*\*/g, '<strong>$1</strong>')
                // Inline code: `text`
                .replace(/`([^`\n]+)`/g, '<code>$1</code>')
                // Auto-link URLs
                .replace(/(https?:\/\/[^\s<]+)/g, '<a href="$1" target="_blank" rel="noopener">$1</a>');
        }

        function renderMessageContent(text) {
            // 1) Escape HTML first so user text can't inject markup
            const escaped = escapeHtml(text);
            const lines = escaped.split('\n');
            const out = [];
            let inList = false;

            for (let i = 0; i < lines.length; i++) {
                const line = lines[i];
                const bulletMatch = line.match(/^\s*[\*\-]\s+(.+)$/);
                if (bulletMatch) {
                    if (!inList) { out.push('<ul>'); inList = true; }
                    out.push('<li>' + formatInline(bulletMatch[1]) + '</li>');
                } else {
                    if (inList) { out.push('</ul>'); inList = false; }
                    if (line.trim() === '') {
                        out.push('<br>');
                    } else {
                        out.push(formatInline(line) + '<br>');
                    }
                }
            }
            if (inList) out.push('</ul>');

            // Collapse trailing <br> and consecutive <br><br><br> down to max two
            return out.join('')
                .replace(/(<br>\s*){3,}/g, '<br><br>')
                .replace(/(<br>\s*)+$/, '');
        }

        function bubbleHtml(msg) {
            const isUser = msg.role === 'user';
            const img = msg.image_path
                ? `<div class="aic-msg-image"><img src="${BASE}${escapeHtml(msg.image_path)}" alt=""></div>`
                : '';
            const text = msg.content
                ? `<div class="aic-msg-text">${renderMessageContent(msg.content)}</div>`
                : '';
            return `
                <div class="aic-msg ${isUser ? 'aic-msg-user' : 'aic-msg-ai'}">
                    <div class="aic-msg-avatar">
                        <i class="fas ${isUser ? 'fa-user' : 'fa-sparkles'}"></i>
                    </div>
                    <div class="aic-msg-body">
                        ${img}
                        ${text}
                    </div>
                </div>`;
        }

        function appendMessage(msg) {
            const wrap = document.createElement('div');
            wrap.innerHTML = bubbleHtml(msg);
            const node = wrap.firstElementChild;
            $messages.appendChild(node);
            $messages.scrollTop = $messages.scrollHeight;
            return node;
        }

        // ---------- Food log suggestion cards ----------
        const MEAL_ICON = { breakfast: '🥐', lunch: '🍱', dinner: '🍽️', snack: '🍎' };

        function suggestionCardHtml(item, idx) {
            const macros = `
                <span class="aic-macro p">P ${item.protein}g</span>
                <span class="aic-macro c">C ${item.carbs}g</span>
                <span class="aic-macro f">F ${item.fat}g</span>`;
            return `
                <div class="aic-food-card" data-idx="${idx}">
                    <div class="aic-food-card-main">
                        <div class="aic-food-card-head">
                            <span class="aic-food-meal">${MEAL_ICON[item.meal_category] || '🍽️'} ${escapeHtml(item.meal_category)}</span>
                            <span class="aic-food-cal">${item.calories} kcal</span>
                        </div>
                        <div class="aic-food-name">${escapeHtml(item.food_name)}</div>
                        <div class="aic-food-macros">${macros}</div>
                    </div>
                    <button class="aic-food-log-btn" data-idx="${idx}">
                        <i class="fas fa-plus"></i> Add to Log
                    </button>
                </div>`;
        }

        function attachSuggestions(bubbleNode, items) {
            if (!items || !items.length) return;
            const body = bubbleNode.querySelector('.aic-msg-body');
            if (!body) return;

            const wrap = document.createElement('div');
            wrap.className = 'aic-food-cards';
            wrap.innerHTML = items.map((it, i) => suggestionCardHtml(it, i)).join('');
            body.appendChild(wrap);

            // Store data on the wrapper for click handler
            wrap._items = items;

            wrap.addEventListener('click', async (e) => {
                const btn = e.target.closest('.aic-food-log-btn');
                if (!btn) return;
                const idx = parseInt(btn.dataset.idx, 10);
                const item = wrap._items[idx];
                if (!item || btn.disabled) return;

                btn.disabled = true;
                const originalHtml = btn.innerHTML;
                btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> ' + I18N.logging;

                const fd = new FormData();
                fd.append('food_item',     item.food_name);
                fd.append('calories',      item.calories);
                fd.append('meal_category', item.meal_category);
                fd.append('protein',       item.protein);
                fd.append('carbs',         item.carbs);
                fd.append('fat',           item.fat);

                try {
                    const r = await fetch(BASE + 'dashboard/handlers/process_intake.php', {
                        method: 'POST',
                        body: fd,
                        credentials: 'same-origin',
                        headers: { 'X-Requested-With': 'fetch' },
                    });
                    const data = await r.json();
                    if (data.ok) {
                        btn.classList.add('logged');
                        btn.innerHTML = '<i class="fas fa-check"></i> ' + I18N.logged;
                    } else {
                        btn.disabled = false;
                        btn.innerHTML = originalHtml;
                        showToast(I18N.couldNotLog.replace('{error}', data.error || I18N.unknownError), { type: 'error' });
                    }
                } catch (err) {
                    btn.disabled = false;
                    btn.innerHTML = originalHtml;
                    showToast(I18N.networkError.replace('{error}', err.message), { type: 'error' });
                }
            });
        }

        function clearMessages() {
            $messages.innerHTML = '';
        }

        function showTyping() {
            const el = document.createElement('div');
            el.className = 'aic-msg aic-msg-ai aic-typing';
            el.id = 'aicTyping';
            el.innerHTML = `
                <div class="aic-msg-avatar"><i class="fas fa-sparkles"></i></div>
                <div class="aic-msg-body">
                    <div class="aic-msg-text"><span class="dot"></span><span class="dot"></span><span class="dot"></span></div>
                </div>`;
            $messages.appendChild(el);
            $messages.scrollTop = $messages.scrollHeight;
        }
        function hideTyping() {
            const t = document.getElementById('aicTyping');
            if (t) t.remove();
        }

        // ---------- API calls ----------
        async function apiGet(action, params = {}) {
            const qs = new URLSearchParams({ action, ...params }).toString();
            const r = await fetch(`${API}?${qs}`, { credentials: 'same-origin' });
            return r.json();
        }

        async function apiPost(action, formData) {
            formData.append('csrf_token', CSRF);
            const r = await fetch(`${API}?action=${action}`, {
                method: 'POST',
                body: formData,
                credentials: 'same-origin',
            });
            return r.json();
        }

        // ---------- Sidebar list ----------
        async function loadConversations() {
            const data = await apiGet('list_conversations');
            if (!data.ok) {
                $convList.innerHTML = `<div class="aic-conv-empty">${escapeHtml(I18N.errorLoading)}</div>`;
                return;
            }
            if (!data.conversations.length) {
                $convList.innerHTML = `<div class="aic-conv-empty">${escapeHtml(I18N.noChats)}</div>`;
                return;
            }
            $convList.innerHTML = data.conversations.map(c => `
                <div class="aic-conv-item ${c.conversation_id == currentConvId ? 'active' : ''}"
                     data-id="${c.conversation_id}">
                    <div class="aic-conv-title">${escapeHtml(c.title)}</div>
                    <button class="aic-conv-del" title="${escapeHtml(I18N.deleteTitle)}" data-id="${c.conversation_id}">
                        <i class="fas fa-trash"></i>
                    </button>
                </div>
            `).join('');
        }

        $convList.addEventListener('click', async (e) => {
            const delBtn = e.target.closest('.aic-conv-del');
            if (delBtn) {
                e.stopPropagation();
                if (!(await showConfirm({ message: I18N.confirmDelete, danger: true }))) return;
                const fd = new FormData();
                fd.append('conversation_id', delBtn.dataset.id);
                const r = await apiPost('delete_conversation', fd);
                if (r.ok) {
                    if (String(currentConvId) === String(delBtn.dataset.id)) {
                        startNewChat();
                    }
                    loadConversations();
                }
                return;
            }
            const item = e.target.closest('.aic-conv-item');
            if (item) {
                openConversation(item.dataset.id);
                closeSidebarOnMobile();
            }
        });

        async function openConversation(id) {
            currentConvId = id;
            $convId.value = id;
            clearMessages();
            const data = await apiGet('get_conversation', { id });
            if (!data.ok) {
                $messages.innerHTML = `<div class="aic-error">${escapeHtml(I18N.couldNotLoad.replace('{error}', data.error || ''))}</div>`;
                return;
            }
            data.messages.forEach(appendMessage);
            loadConversations();
        }

        const WELCOME_HTML = $messages.innerHTML; // capture initial welcome screen

        function startNewChat() {
            currentConvId = null;
            $convId.value = '';
            $messages.innerHTML = WELCOME_HTML;
            loadConversations();
        }

        $newBtn.addEventListener('click', () => {
            startNewChat();
            closeSidebarOnMobile();
        });

        // ---------- Composer ----------
        $input.addEventListener('input', () => {
            $input.style.height = 'auto';
            $input.style.height = Math.min($input.scrollHeight, 200) + 'px';
        });

        $input.addEventListener('keydown', (e) => {
            if (e.key === 'Enter' && !e.shiftKey) {
                e.preventDefault();
                $form.requestSubmit();
            }
        });

        // Image compression lives in js/image-compress.js (shared with the
        // calorie-estimate chat on dashboard-intake.php). It re-encodes the
        // pick into a downscaled sRGB JPEG — drawing through a 2D canvas drops
        // iPhone HDR gain maps / color profiles. We still cap the *result* size
        // here and surface a localized message if it's somehow still too big.
        const MAX_UPLOAD_BYTES = 5 * 1024 * 1024;
        const compressImage = (file) => BitBalanceImage.compressImage(file, {
            messages: { decode: I18N.decodeFailed, empty: I18N.emptyImage, encode: I18N.encodeFailed },
        });

        function clearPreview() {
            $imageIn.value = '';
            pendingImageFile = null;
            $preview.hidden = true;
            if ($previewImg.src && $previewImg.src.startsWith('blob:')) {
                URL.revokeObjectURL($previewImg.src);
            }
            $previewImg.removeAttribute('src');
        }

        $imageIn.addEventListener('change', async () => {
            const raw = $imageIn.files[0];
            if (!raw) { clearPreview(); return; }

            $sendBtn.disabled = true;
            try {
                const processed = await compressImage(raw);
                if (processed.size > MAX_UPLOAD_BYTES) {
                    showToast(I18N.imageTooBig, { type: 'error' });
                    clearPreview();
                    return;
                }
                pendingImageFile = processed;
                if ($previewImg.src && $previewImg.src.startsWith('blob:')) {
                    URL.revokeObjectURL($previewImg.src);
                }
                $previewImg.src = URL.createObjectURL(processed);
                $preview.hidden = false;
            } catch (err) {
                showToast(I18N.imageReadFailed.replace('{error}', err.message), { type: 'error' });
                clearPreview();
            } finally {
                $sendBtn.disabled = false;
            }
        });
        $imageRm.addEventListener('click', clearPreview);

        document.addEventListener('click', (e) => {
            const s = e.target.closest('.aic-suggest');
            if (s) {
                $input.value = s.dataset.prompt;
                $input.focus();
            }
        });

        // Create an empty assistant bubble to be filled token-by-token.
        function createStreamingBubble() {
            const node = appendMessage({ role: 'assistant', content: '' });
            const textEl = document.createElement('div');
            textEl.className = 'aic-msg-text';
            // bubbleHtml() omits .aic-msg-text when content is empty; insert one.
            node.querySelector('.aic-msg-body').appendChild(textEl);

            let raw = '';
            return {
                node,
                appendText(delta) {
                    raw += delta;
                    textEl.innerHTML = renderMessageContent(raw);
                    $messages.scrollTop = $messages.scrollHeight;
                },
            };
        }

        // Stream from a POST endpoint via fetch + ReadableStream. Parses SSE
        // events incrementally and dispatches them to handlers[eventName].
        // Throws on non-OK response or stream read errors.
        async function streamSse(url, formData, handlers) {
            formData.append('csrf_token', CSRF);
            const res = await fetch(url, {
                method: 'POST',
                body: formData,
                credentials: 'same-origin',
                headers: { 'Accept': 'text/event-stream' },
            });
            if (!res.ok || !res.body) {
                let msg = 'HTTP ' + res.status;
                try {
                    const j = await res.json();
                    if (j && j.error) msg = j.error;
                } catch (_) {}
                throw new Error(msg);
            }
            const reader  = res.body.getReader();
            const decoder = new TextDecoder('utf-8');
            let buffer = '';

            while (true) {
                const { done, value } = await reader.read();
                if (done) break;
                buffer += decoder.decode(value, { stream: true });

                let sepIdx;
                while ((sepIdx = buffer.indexOf('\n\n')) >= 0) {
                    const rawEvent = buffer.slice(0, sepIdx).replace(/\r/g, '');
                    buffer = buffer.slice(sepIdx + 2);
                    let eventName = 'message';
                    let dataStr = '';
                    for (const line of rawEvent.split('\n')) {
                        if (line.startsWith('event:')) eventName = line.slice(6).trim();
                        else if (line.startsWith('data:')) dataStr += line.slice(5).replace(/^ /, '');
                    }
                    if (!dataStr) continue;
                    let data;
                    try { data = JSON.parse(dataStr); }
                    catch (_) { continue; }
                    const fn = handlers[eventName];
                    if (fn) fn(data);
                }
            }
        }

        $form.addEventListener('submit', async (e) => {
            e.preventDefault();
            if (isSending) return;
            const text = $input.value.trim();
            const hasImage = !!pendingImageFile;
            if (!text && !hasImage) return;

            isSending = true;
            $sendBtn.disabled = true;

            // Build the request payload up-front — once captured, we can
            // safely wipe the composer before awaiting the network call.
            const fd = new FormData();
            fd.append('conversation_id', currentConvId || '');
            fd.append('message', text);
            if (pendingImageFile) {
                fd.append('image', pendingImageFile, pendingImageFile.name || 'photo.jpg');
            }
            fd.append('client_now', new Date().toISOString());
            fd.append('client_tz_offset', String(new Date().getTimezoneOffset())); // minutes

            // Clear welcome if first message
            const welcome = $messages.querySelector('.aic-welcome');
            if (welcome) welcome.remove();

            // Optimistic user message
            const localImageUrl = hasImage ? URL.createObjectURL(pendingImageFile) : null;
            appendMessage({
                role: 'user',
                content: text,
                image_path: null, // server will return real path; preview locally instead
            });
            if (localImageUrl) {
                // Insert preview image into the just-appended user bubble
                const lastUser = $messages.querySelector('.aic-msg-user:last-child .aic-msg-body');
                if (lastUser) {
                    const imgEl = document.createElement('div');
                    imgEl.className = 'aic-msg-image';
                    imgEl.innerHTML = `<img src="${localImageUrl}" alt="">`;
                    lastUser.insertBefore(imgEl, lastUser.firstChild);
                }
            }

            // Wipe composer NOW — payload is already in `fd`. This avoids the
            // bug where the attached preview lingered beside the sent bubble
            // for the whole duration of the AI request.
            $input.value = '';
            $input.style.height = 'auto';
            clearPreview();

            showTyping();

            let bubble = null;
            let streamError = null;
            let doneInfo = null;

            try {
                await streamSse(`${API}?action=stream_message`, fd, {
                    meta: (data) => {
                        currentConvId = data.conversation_id;
                        $convId.value = currentConvId;
                    },
                    chunk: (data) => {
                        if (!bubble) {
                            hideTyping();
                            bubble = createStreamingBubble();
                        }
                        if (data && typeof data.text === 'string') {
                            bubble.appendText(data.text);
                        }
                    },
                    done: (data) => { doneInfo = data; },
                    error: (data) => { streamError = (data && data.error) || I18N.unknownError; },
                });
            } catch (err) {
                streamError = streamError || I18N.networkError.replace('{error}', err.message);
            } finally {
                hideTyping();
            }

            if (streamError) {
                if (bubble) {
                    bubble.appendText('\n\n⚠️ ' + streamError);
                } else {
                    appendMessage({ role: 'assistant', content: '⚠️ ' + streamError });
                }
            } else if (doneInfo) {
                if (bubble) {
                    attachSuggestions(bubble.node, doneInfo.food_log_suggestions);
                }
                if (doneInfo.usage_today != null) {
                    $usage.textContent = I18N.usageFmt
                        .replace('{used}', doneInfo.usage_today)
                        .replace('{limit}', doneInfo.daily_limit);
                }
                loadConversations();
            } else if (!bubble) {
                // Stream closed with no chunks and no done event — treat as error.
                appendMessage({ role: 'assistant', content: '⚠️ AI returned empty response' });
            }

            isSending = false;
            $sendBtn.disabled = false;
            // Don't auto-focus on mobile — that pops the keyboard back up
            // immediately after send, which is jarring while reading the reply.
            if (!isMobile()) $input.focus();
        });

        // ---------- Init ----------
        loadConversations();
    })();
    </script>
</body>
</html>
