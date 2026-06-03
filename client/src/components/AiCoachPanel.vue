<script setup>
// AI Coach chat — conversation list + thread + composer. Assistant replies may
// carry food-log suggestion cards; tapping "Add to Log" posts to the intake API
// (the model never logs anything itself). Text-only (no image upload yet).
//
// Conversation switching adapts to width: a persistent sidebar on desktop, and
// a bottom sheet (opened from the thread header) on mobile — no more horizontal
// strip eating vertical space.
import { ref, reactive, computed, nextTick, onMounted } from 'vue';
import { api } from '../lib/api.js';
import { t } from '../i18n/index.js';
import ConversationList from './ConversationList.vue';
import BottomSheet from './BottomSheet.vue';

const conversations = ref([]);
const activeId = ref(0); // 0 = unsaved new chat
const messages = ref([]);
const input = ref('');
const sending = ref(false);
const loadingMsgs = ref(false);
const error = ref('');
const usage = reactive({ used: null, limit: null });
const added = reactive({}); // key `${msgId}:${idx}` -> 'done' | 'busy' | 'error'
const threadEl = ref(null);
const sheetOpen = ref(false); // mobile conversation sheet

const limitReached = computed(() => usage.limit != null && usage.used != null && usage.used >= usage.limit);

// Title shown in the mobile thread header so the user knows which chat they're in.
const activeTitle = computed(() => {
  if (activeId.value === 0) return t('coach.chat.new');
  const c = conversations.value.find((c) => c.id === activeId.value);
  return c ? c.title : t('coach.chat.title_fallback');
});

function scrollToBottom() {
  nextTick(() => {
    if (threadEl.value) threadEl.value.scrollTop = threadEl.value.scrollHeight;
  });
}

async function loadConversations() {
  try {
    conversations.value = await api.get('/api/ai-coach/conversations');
  } catch (e) {
    error.value = e.message;
  }
}

async function selectConversation(id) {
  if (id === activeId.value) return;
  activeId.value = id;
  messages.value = [];
  loadingMsgs.value = true;
  error.value = '';
  try {
    const data = await api.get(`/api/ai-coach/messages?conversation_id=${id}`);
    messages.value = data.messages;
    scrollToBottom();
  } catch (e) {
    error.value = e.message;
  } finally {
    loadingMsgs.value = false;
  }
}

function newChat() {
  activeId.value = 0;
  messages.value = [];
  error.value = '';
}

// Sheet variants: switch the conversation, then dismiss the sheet.
function selectFromSheet(id) {
  selectConversation(id);
  sheetOpen.value = false;
}
function newChatFromSheet() {
  newChat();
  sheetOpen.value = false;
}

async function send() {
  const text = input.value.trim();
  if (text === '' || sending.value) return;
  error.value = '';
  sending.value = true;

  // Optimistic user bubble.
  const optimistic = { id: `tmp-${Date.now()}`, role: 'user', content: text, food_log_suggestions: [] };
  messages.value.push(optimistic);
  input.value = '';
  scrollToBottom();

  try {
    const data = await api.post('/api/ai-coach/send', {
      message: text,
      conversation_id: activeId.value,
      client_now: new Date().toISOString(),
      client_tz_offset: new Date().getTimezoneOffset(),
    });

    // Replace optimistic bubble with the real persisted pair.
    const i = messages.value.indexOf(optimistic);
    if (i !== -1) messages.value.splice(i, 1, data.user_message, data.assistant_message);
    else messages.value.push(data.user_message, data.assistant_message);

    usage.used = data.usage_today;
    usage.limit = data.daily_limit;

    if (activeId.value !== data.conversation_id) {
      activeId.value = data.conversation_id;
    }
    await loadConversations();
    scrollToBottom();
  } catch (e) {
    // Roll back the optimistic bubble and restore the text so it isn't lost.
    const i = messages.value.indexOf(optimistic);
    if (i !== -1) messages.value.splice(i, 1);
    input.value = text;
    error.value = e.message;
  } finally {
    sending.value = false;
  }
}

async function addToLog(item, msgId, idx) {
  const key = `${msgId}:${idx}`;
  if (added[key] === 'done' || added[key] === 'busy') return;
  added[key] = 'busy';
  try {
    await api.post('/api/intake/create', {
      food_item: item.food_name,
      meal_category: item.meal_category,
      calories: item.calories,
      protein: item.protein,
      carbs: item.carbs,
      fat: item.fat,
    });
    added[key] = 'done';
  } catch (e) {
    added[key] = 'error';
    error.value = e.message;
  }
}

async function removeConversation(id) {
  try {
    await api.post('/api/ai-coach/delete', { conversation_id: id });
    conversations.value = conversations.value.filter((c) => c.id !== id);
    if (activeId.value === id) newChat();
  } catch (e) {
    error.value = e.message;
  }
}

// Minimal, XSS-safe renderer: escape, then **bold**, then bullets + breaks.
function renderContent(text) {
  const esc = String(text)
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;');
  return esc
    .replace(/\*\*(.+?)\*\*/g, '<strong>$1</strong>')
    .replace(/^\s*\*\s+(.*)$/gm, '<span class="bullet">$1</span>')
    .replace(/\n/g, '<br />');
}

onMounted(loadConversations);
</script>

<template>
  <div class="coach">
    <!-- Conversation list: persistent sidebar on desktop -->
    <aside class="convos">
      <ConversationList
        :conversations="conversations"
        :active-id="activeId"
        @select="selectConversation"
        @new="newChat"
        @delete="removeConversation"
      />
    </aside>

    <!-- Thread -->
    <section class="thread-wrap">
      <!-- Mobile-only header: current chat title + history sheet + new chat -->
      <header class="thread-head">
        <span class="th-title">{{ activeTitle }}</span>
        <div class="th-actions">
          <button class="icon-btn" :aria-label="$t('coach.chat.new')" @click="newChat"><i class="fa-solid fa-plus" /></button>
          <button class="icon-btn" :aria-label="$t('coach.chat.conversations')" @click="sheetOpen = true">
            <i class="fa-solid fa-clock-rotate-left" />
          </button>
        </div>
      </header>

      <div ref="threadEl" class="thread">
        <p v-if="loadingMsgs" class="muted center">{{ $t('common.loading') }}</p>

        <div v-else-if="!messages.length" class="welcome">
          <div class="avatar"><i class="fa-solid fa-dumbbell" /></div>
          <h2>{{ $t('coach.chat.welcome_title') }}</h2>
          <p class="muted">
            {{ $t('coach.chat.welcome_body') }}
          </p>
        </div>

        <div v-for="m in messages" :key="m.id" class="msg" :class="m.role">
          <div class="bubble" v-html="renderContent(m.content)" />
          <!-- Food-log suggestion cards -->
          <div v-if="m.food_log_suggestions && m.food_log_suggestions.length" class="suggestions">
            <div v-for="(item, idx) in m.food_log_suggestions" :key="idx" class="food-card">
              <div class="food-head">
                <strong>{{ item.food_name }}</strong>
                <span class="cat">{{ $t('dashboard.meal.' + item.meal_category) }}</span>
              </div>
              <div class="macros muted">
                {{ item.calories }} {{ $t('common.kcal') }} · {{ $t('intake.macro_abbr.protein') }} {{ item.protein }}g · {{ $t('intake.macro_abbr.carbs') }} {{ item.carbs }}g · {{ $t('intake.macro_abbr.fat') }} {{ item.fat }}g
              </div>
              <button
                class="addbtn"
                :class="{ done: added[`${m.id}:${idx}`] === 'done' }"
                :disabled="added[`${m.id}:${idx}`] === 'done' || added[`${m.id}:${idx}`] === 'busy'"
                @click="addToLog(item, m.id, idx)"
              >
                <template v-if="added[`${m.id}:${idx}`] === 'done'"><i class="fa-solid fa-check" /> {{ $t('coach.chat.added') }}</template>
                <template v-else-if="added[`${m.id}:${idx}`] === 'busy'">{{ $t('coach.chat.adding') }}</template>
                <template v-else><i class="fa-solid fa-plus" /> {{ $t('coach.chat.add_to_log') }}</template>
              </button>
            </div>
          </div>
        </div>
      </div>

      <!-- Composer -->
      <form class="composer" @submit.prevent="send">
        <p v-if="error" class="error">{{ error }}</p>
        <p v-if="limitReached" class="muted center">{{ $t('coach.chat.limit_reached', { limit: usage.limit }) }}</p>
        <div class="row">
          <textarea
            v-model="input"
            rows="1"
            :placeholder="$t('coach.chat.placeholder')"
            :disabled="sending || limitReached"
            @keydown.enter.exact.prevent="send"
          />
          <button type="submit" :disabled="sending || limitReached || !input.trim()">
            <i class="fa-solid fa-paper-plane" />
          </button>
        </div>
        <p v-if="usage.used != null" class="usage muted">{{ $t('coach.chat.usage', { used: usage.used, limit: usage.limit }) }}</p>
      </form>
    </section>

    <!-- Conversation list: bottom sheet on mobile -->
    <BottomSheet :open="sheetOpen" :title="$t('coach.chat.conversations')" @close="sheetOpen = false">
      <ConversationList
        :conversations="conversations"
        :active-id="activeId"
        @select="selectFromSheet"
        @new="newChatFromSheet"
        @delete="removeConversation"
      />
    </BottomSheet>
  </div>
</template>

<style scoped>
.coach {
  display: grid;
  grid-template-columns: 240px 1fr;
  gap: 14px;
  max-width: 1000px;
  margin: 0 auto;
  padding: 8px 16px;
  /* Fills the Coach Hub's panel area (the hub owns the viewport-height math). */
  height: 100%;
}
.muted { color: var(--muted); font-size: 13px; }
.center { text-align: center; }

/* ---- Conversation sidebar (desktop) ---- */
.convos {
  display: flex;
  flex-direction: column;
  min-height: 0;
}

/* ---- Thread ---- */
.thread-wrap {
  display: flex;
  flex-direction: column;
  min-height: 0;
  background: var(--card);
  border: 1px solid var(--border);
  border-radius: 14px;
  overflow: hidden;
}

/* Mobile-only header above the thread (hidden on desktop where the sidebar shows). */
.thread-head { display: none; }
.th-title {
  flex: 1;
  min-width: 0;
  font-weight: 700;
  font-size: 14px;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}
.th-actions { flex: none; display: flex; gap: 4px; }
.icon-btn {
  width: 40px;
  height: 40px;
  min-height: 0;
  padding: 0;
  background: transparent;
  color: var(--muted);
  border: 1px solid var(--border);
}
.icon-btn:hover { color: var(--text); }

.thread {
  flex: 1;
  overflow-y: auto;
  padding: 18px;
  display: flex;
  flex-direction: column;
  gap: 14px;
}
.welcome { margin: auto; text-align: center; max-width: 320px; }
.welcome .avatar {
  width: 56px;
  height: 56px;
  border-radius: 50%;
  background: var(--accent);
  color: var(--on-accent);
  display: grid;
  place-items: center;
  font-size: 22px;
  margin: 0 auto 12px;
}
.welcome h2 { margin: 0 0 6px; }

.msg { display: flex; flex-direction: column; max-width: 80%; }
.msg.user { align-self: flex-end; align-items: flex-end; }
.msg.assistant { align-self: flex-start; align-items: flex-start; }
.bubble {
  padding: 10px 14px;
  border-radius: 14px;
  line-height: 1.5;
  font-size: 14px;
  white-space: normal;
  word-break: break-word;
}
.msg.user .bubble { background: var(--accent); color: var(--on-accent); border-bottom-right-radius: 4px; }
.msg.assistant .bubble { background: var(--inset); border: 1px solid var(--border); border-bottom-left-radius: 4px; }
.bubble :deep(.bullet) { display: list-item; margin-left: 18px; }

/* ---- Suggestion cards ---- */
.suggestions { display: flex; flex-direction: column; gap: 8px; margin-top: 8px; width: 100%; }
.food-card {
  background: var(--bg);
  border: 1px solid var(--border);
  border-radius: 10px;
  padding: 10px 12px;
}
.food-head { display: flex; justify-content: space-between; align-items: center; gap: 8px; }
.food-head .cat {
  font-size: 11px;
  text-transform: capitalize;
  color: var(--muted);
  border: 1px solid var(--border);
  border-radius: 999px;
  padding: 1px 8px;
}
.macros { margin: 4px 0 8px; }
.addbtn { font-size: 12px; padding: 6px 12px; min-height: 0; }
.addbtn.done { background: var(--surface-2); color: var(--accent); }

/* ---- Composer ---- */
.composer { border-top: 1px solid var(--border); padding: 12px 14px; }
.composer .row { display: flex; gap: 8px; align-items: flex-end; }
.composer textarea {
  flex: 1;
  resize: none;
  max-height: 120px;
  font-family: inherit;
}
.composer .row button { flex: none; padding: 10px 14px; }
.usage { margin: 8px 0 0; text-align: right; }
.error { margin: 0 0 8px; }

@media (max-width: 767px) {
  .coach {
    grid-template-columns: 1fr;
    padding: 8px 12px;
  }
  /* Sidebar gives way to the thread header + bottom sheet on mobile. */
  .convos { display: none; }
  .thread-head {
    display: flex;
    align-items: center;
    gap: 8px;
    padding: 8px 10px 8px 14px;
    border-bottom: 1px solid var(--border);
  }
  .msg { max-width: 92%; }
}
</style>
