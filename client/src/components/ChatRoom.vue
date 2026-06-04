<script setup>
// Reusable two-way chat room over the /api/pt message contract
// ({ messages:[{message_id,sender_role,content,created_at}], my_role } on GET,
// { message } on POST { content }). Used by both the client's My Trainer panel
// and the trainer workspace — the only difference is the endpoint + my_role.
// Handles optimistic send, scroll-to-bottom, and a 12s incremental poll
// (paused when the tab is hidden), mirroring pt-chat.js.
import { ref, computed, watch, nextTick, onMounted, onBeforeUnmount } from 'vue';
import { api } from '../lib/api.js';

const props = defineProps({
  // Base path without query, e.g. '/api/pt/messages' or '/api/pt/clients/5/messages'.
  path: { type: String, required: true },
  myRole: { type: String, required: true }, // 'client' | 'trainer'
  placeholder: { type: String, default: '' },
});
const emit = defineEmits(['sent']);

const POLL_MS = 12000;

const messages = ref([]);
const input = ref('');
const sending = ref(false);
const error = ref('');
const threadEl = ref(null);
let pollTimer = null;

const lastId = computed(() =>
  messages.value.reduce((max, m) => (typeof m.message_id === 'number' && m.message_id > max ? m.message_id : max), 0)
);

function scrollToBottom() {
  nextTick(() => {
    if (threadEl.value) threadEl.value.scrollTop = threadEl.value.scrollHeight;
  });
}

async function loadAll() {
  error.value = '';
  try {
    const data = await api.get(props.path);
    messages.value = data.messages;
    scrollToBottom();
  } catch (e) {
    error.value = e.message;
  }
}

async function poll() {
  if (document.hidden) return;
  try {
    const data = await api.get(`${props.path}?since=${lastId.value}`, { background: true });
    if (data.messages.length) {
      messages.value.push(...data.messages);
      scrollToBottom();
    }
  } catch {
    /* transient — next tick retries */
  }
}

async function send() {
  const text = input.value.trim();
  if (text === '' || sending.value) return;
  error.value = '';
  sending.value = true;

  const optimistic = { message_id: `tmp-${Date.now()}`, sender_role: props.myRole, content: text };
  messages.value.push(optimistic);
  input.value = '';
  scrollToBottom();

  try {
    const data = await api.post(props.path, { content: text });
    const i = messages.value.indexOf(optimistic);
    if (i !== -1) messages.value.splice(i, 1, data.message);
    else messages.value.push(data.message);
    emit('sent');
  } catch (e) {
    const i = messages.value.indexOf(optimistic);
    if (i !== -1) messages.value.splice(i, 1);
    input.value = text;
    error.value = e.message;
  } finally {
    sending.value = false;
  }
}

// Reload from scratch when the endpoint changes (e.g. trainer switches client).
watch(
  () => props.path,
  () => {
    messages.value = [];
    loadAll();
  }
);

onMounted(() => {
  loadAll();
  pollTimer = setInterval(poll, POLL_MS);
});
onBeforeUnmount(() => clearInterval(pollTimer));

defineExpose({ scrollToBottom });
</script>

<template>
  <div class="chat">
    <div ref="threadEl" class="messages">
      <p v-if="!messages.length" class="empty muted">{{ $t('chat.empty') }}</p>
      <div v-for="m in messages" :key="m.message_id" class="msg" :class="m.sender_role === myRole ? 'me' : 'them'">
        <div class="bubble">{{ m.content }}</div>
      </div>
    </div>
    <p v-if="error" class="error">{{ error }}</p>
    <form class="composer" @submit.prevent="send">
      <textarea
        v-model="input"
        rows="1"
        :placeholder="placeholder || $t('chat.placeholder')"
        :disabled="sending"
        @keydown.enter.exact.prevent="send"
      />
      <button type="submit" :disabled="sending || !input.trim()"><i class="fa-solid fa-paper-plane" /></button>
    </form>
  </div>
</template>

<style scoped>
.chat {
  flex: 1;
  min-height: 0;
  display: flex;
  flex-direction: column;
  background: var(--card);
  border: 1px solid var(--border);
  border-radius: 14px;
  overflow: hidden;
}
.messages { flex: 1; overflow-y: auto; padding: 16px; display: flex; flex-direction: column; gap: 10px; }
.empty { margin: auto; }
.muted { color: var(--muted); font-size: 13px; }
.msg { display: flex; max-width: 82%; }
.msg.me { align-self: flex-end; }
.msg.them { align-self: flex-start; }
.bubble {
  padding: 9px 13px; border-radius: 14px; font-size: 14px; line-height: 1.45;
  white-space: pre-wrap; word-break: break-word;
}
.msg.me .bubble { background: var(--accent); color: var(--on-accent); border-bottom-right-radius: 4px; }
.msg.them .bubble { background: var(--inset); border: 1px solid var(--border); border-bottom-left-radius: 4px; }
.error { color: #f87171; font-size: 13px; margin: 0; padding: 0 12px; }
.composer { border-top: 1px solid var(--border); padding: 10px 12px; display: flex; gap: 8px; align-items: flex-end; }
.composer textarea { flex: 1; resize: none; max-height: 120px; font-family: inherit; }
.composer button { flex: none; padding: 10px 14px; }
</style>
