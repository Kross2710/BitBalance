<script setup>
// AI Coach chat — conversation list + thread + composer. Assistant replies may
// carry food-log suggestion cards; tapping "Add to Log" posts to the intake API
// (the model never logs anything itself). Text-only (no image upload yet).
import { ref, reactive, computed, nextTick, onMounted } from 'vue';
import { api } from '../lib/api.js';

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

const limitReached = computed(() => usage.limit != null && usage.used != null && usage.used >= usage.limit);

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
    <!-- Conversation list -->
    <aside class="convos">
      <button class="newchat" @click="newChat"><i class="fa-solid fa-plus" /> New chat</button>
      <ul>
        <li
          v-for="c in conversations"
          :key="c.id"
          :class="{ active: c.id === activeId }"
          @click="selectConversation(c.id)"
        >
          <span class="title">{{ c.title }}</span>
          <button class="del" title="Delete" @click.stop="removeConversation(c.id)">
            <i class="fa-solid fa-trash" />
          </button>
        </li>
        <li v-if="!conversations.length" class="empty muted">No conversations yet</li>
      </ul>
    </aside>

    <!-- Thread -->
    <section class="thread-wrap">
      <div ref="threadEl" class="thread">
        <p v-if="loadingMsgs" class="muted center">Loading…</p>

        <div v-else-if="!messages.length" class="welcome">
          <div class="avatar"><i class="fa-solid fa-dumbbell" /></div>
          <h2>AI Coach</h2>
          <p class="muted">
            Ask about nutrition or fitness, or tell me what you ate and I'll prep a log card for you.
          </p>
        </div>

        <div v-for="m in messages" :key="m.id" class="msg" :class="m.role">
          <div class="bubble" v-html="renderContent(m.content)" />
          <!-- Food-log suggestion cards -->
          <div v-if="m.food_log_suggestions && m.food_log_suggestions.length" class="suggestions">
            <div v-for="(item, idx) in m.food_log_suggestions" :key="idx" class="food-card">
              <div class="food-head">
                <strong>{{ item.food_name }}</strong>
                <span class="cat">{{ item.meal_category }}</span>
              </div>
              <div class="macros muted">
                {{ item.calories }} kcal · P {{ item.protein }}g · C {{ item.carbs }}g · F {{ item.fat }}g
              </div>
              <button
                class="addbtn"
                :class="{ done: added[`${m.id}:${idx}`] === 'done' }"
                :disabled="added[`${m.id}:${idx}`] === 'done' || added[`${m.id}:${idx}`] === 'busy'"
                @click="addToLog(item, m.id, idx)"
              >
                <template v-if="added[`${m.id}:${idx}`] === 'done'"><i class="fa-solid fa-check" /> Added</template>
                <template v-else-if="added[`${m.id}:${idx}`] === 'busy'">Adding…</template>
                <template v-else><i class="fa-solid fa-plus" /> Add to Log</template>
              </button>
            </div>
          </div>
        </div>
      </div>

      <!-- Composer -->
      <form class="composer" @submit.prevent="send">
        <p v-if="error" class="error">{{ error }}</p>
        <p v-if="limitReached" class="muted center">Daily limit reached ({{ usage.limit }}). Try again tomorrow.</p>
        <div class="row">
          <textarea
            v-model="input"
            rows="1"
            placeholder="Message your coach…"
            :disabled="sending || limitReached"
            @keydown.enter.exact.prevent="send"
          />
          <button type="submit" :disabled="sending || limitReached || !input.trim()">
            <i class="fa-solid fa-paper-plane" />
          </button>
        </div>
        <p v-if="usage.used != null" class="usage muted">{{ usage.used }} / {{ usage.limit }} messages today</p>
      </form>
    </section>
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
  height: calc(100vh - 60px);
}
.muted { color: var(--muted); font-size: 13px; }
.center { text-align: center; }

/* ---- Conversation list ---- */
.convos {
  display: flex;
  flex-direction: column;
  gap: 10px;
  min-height: 0;
}
.newchat {
  background: var(--card);
  color: var(--text);
  border: 1px solid var(--border);
  text-align: left;
}
.convos ul {
  list-style: none;
  margin: 0;
  padding: 0;
  overflow-y: auto;
  display: flex;
  flex-direction: column;
  gap: 2px;
}
.convos li {
  display: flex;
  align-items: center;
  gap: 6px;
  padding: 9px 10px;
  border-radius: 8px;
  cursor: pointer;
  font-size: 13px;
}
.convos li:hover { background: #12151b; }
.convos li.active { background: #12151b; color: var(--accent); }
.convos li.empty { cursor: default; }
.convos li.empty:hover { background: transparent; }
.convos .title {
  flex: 1;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}
.convos .del {
  background: transparent;
  color: var(--muted);
  padding: 2px 6px;
  opacity: 0;
}
.convos li:hover .del { opacity: 1; }
.convos .del:hover { color: #f87171; }

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
  color: #04210f;
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
.msg.user .bubble { background: var(--accent); color: #04210f; border-bottom-right-radius: 4px; }
.msg.assistant .bubble { background: #12151b; border: 1px solid var(--border); border-bottom-left-radius: 4px; }
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
.addbtn { font-size: 12px; padding: 6px 12px; }
.addbtn.done { background: #2a2e37; color: var(--accent); }

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
  .coach { grid-template-columns: 1fr; height: calc(100vh - 130px); }
  .convos {
    flex-direction: row;
    align-items: center;
    overflow-x: auto;
  }
  .convos ul { flex-direction: row; }
  .convos li { white-space: nowrap; }
  .msg { max-width: 92%; }
}
</style>
