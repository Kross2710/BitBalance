<script setup>
// AI food chat — multi-turn port of the PHP intake chat (dashboard/handlers/
// ai_chat.php). Attach a photo and/or type, the AI replies with a nutrition
// card, and you can keep messaging to correct the dish ("it's actually pho bo").
// Tapping "Add to log" emits the estimate up to the Intake form (the chat never
// logs anything itself). Stateless server: the client owns the conversation and
// re-sends recent turns + the active photo each call, so corrections keep context.
import { ref, nextTick, watch, onBeforeUnmount } from 'vue';
import { t } from '../i18n/index.js';
import { browserTz } from '../lib/api.js';
import { compressImage } from '../lib/image.js';
import BottomSheet from './BottomSheet.vue';

const props = defineProps({ open: { type: Boolean, default: false } });
const emit = defineEmits(['close', 'pick']);

const messages = ref([]); // { role:'user'|'bot', text, imageUrl?, card? }
const input = ref('');
const busy = ref(false);
const error = ref('');
const threadEl = ref(null);
const photoInput = ref(null);

// The active (compressed) photo persists across turns so a follow-up correction
// is still sent with the image; cleared on remove, Add-to-log, or reopen.
const activeImage = ref(null);
const activeImagePreview = ref('');

function scrollToBottom() {
  nextTick(() => {
    if (threadEl.value) threadEl.value.scrollTop = threadEl.value.scrollHeight;
  });
}

function clearImage() {
  if (activeImagePreview.value) URL.revokeObjectURL(activeImagePreview.value);
  activeImagePreview.value = '';
  activeImage.value = null;
}

async function onPickImage(e) {
  const file = e.target.files?.[0];
  e.target.value = ''; // allow re-picking the same file
  if (!file) return;
  error.value = '';
  try {
    const compressed = await compressImage(file, { filename: 'meal.jpg' });
    clearImage();
    activeImage.value = compressed;
    activeImagePreview.value = URL.createObjectURL(compressed);
  } catch (err) {
    error.value = err.message;
  }
}

async function send() {
  const text = input.value.trim();
  if ((!text && !activeImage.value) || busy.value) return;
  error.value = '';
  busy.value = true;

  // History is the turns SO FAR (the server appends this new user turn itself).
  const history = messages.value.map((m) => ({ role: m.role === 'user' ? 'user' : 'assistant', content: m.text || '' }));

  const userMsg = { role: 'user', text: text || t('intake.ai.sent_photo'), imageUrl: activeImage.value ? activeImagePreview.value : '' };
  messages.value.push(userMsg);
  input.value = '';
  scrollToBottom();

  try {
    const fd = new FormData();
    if (activeImage.value) fd.append('image', activeImage.value);
    if (text) fd.append('message', text);
    fd.append('history', JSON.stringify(history.slice(-8)));
    // FormData can't go through the JSON api helper — post it directly.
    const res = await fetch('/api/intake/ai-chat', {
      method: 'POST',
      credentials: 'include',
      headers: { 'X-Requested-With': 'XMLHttpRequest', 'X-Timezone': browserTz() },
      body: fd,
    });
    const json = await res.json();
    if (!json.ok) throw new Error(json.message || t('intake.ai.failed'));
    const card = json.data;
    // A short text summary doubles as the assistant turn's history content.
    const summary = card.food_name
      ? `${card.food_name} - ${card.calories} ${t('common.kcal')} (P${card.protein}/C${card.carbs}/F${card.fat})${card.short_advice ? '. ' + card.short_advice : ''}`
      : card.short_advice || t('intake.ai.couldnt_identify');
    messages.value.push({ role: 'bot', card, text: summary });
    scrollToBottom();
  } catch (err) {
    error.value = err.message;
    messages.value.pop(); // drop the optimistic user bubble
    input.value = text; // don't lose what they typed
  } finally {
    busy.value = false;
  }
}

function addToLog(card) {
  emit('pick', {
    food_item: card.food_name,
    calories: card.calories,
    protein: card.protein,
    carbs: card.carbs,
    fat: card.fat,
    image_path: card.image_path || '',
  });
}

// Fresh chat each time it opens.
watch(
  () => props.open,
  (isOpen) => {
    if (isOpen) {
      messages.value = [];
      input.value = '';
      error.value = '';
      clearImage();
    }
  }
);

onBeforeUnmount(clearImage);
</script>

<template>
  <BottomSheet :open="open" :title="$t('intake.ai.title')" @close="emit('close')">
    <div class="chat">
      <div ref="threadEl" class="thread">
        <div v-if="!messages.length" class="intro">
          <div class="intro-icon"><i class="fa-solid fa-wand-magic-sparkles" /></div>
          <p class="muted">{{ $t('intake.ai.intro') }}</p>
        </div>

        <div v-for="(m, i) in messages" :key="i" class="msg" :class="m.role">
          <!-- Bot: a nutrition card when identified, otherwise a plain reply -->
          <template v-if="m.role === 'bot'">
            <div v-if="m.card && m.card.food_name" class="food-card">
              <div class="food-head">
                <strong>{{ m.card.food_name }}</strong>
                <span class="cal">{{ m.card.calories }} {{ $t('common.kcal') }}</span>
              </div>
              <div class="macros muted">
                {{ $t('intake.macro_abbr.protein') }} {{ m.card.protein }}g ·
                {{ $t('intake.macro_abbr.carbs') }} {{ m.card.carbs }}g ·
                {{ $t('intake.macro_abbr.fat') }} {{ m.card.fat }}g
                <span v-if="m.card.unit"> · {{ m.card.unit }}</span>
              </div>
              <p v-if="m.card.short_advice" class="advice">{{ m.card.short_advice }}</p>
              <button type="button" class="addbtn" @click="addToLog(m.card)">
                <i class="fa-solid fa-plus" /> {{ $t('intake.ai.add_to_log') }}
              </button>
            </div>
            <div v-else class="bubble">{{ m.card?.short_advice || m.text }}</div>
          </template>

          <!-- User: optional photo thumbnail + text -->
          <template v-else>
            <img v-if="m.imageUrl" :src="m.imageUrl" class="msg-thumb" :alt="$t('intake.food_photo_alt')" />
            <div v-if="m.text" class="bubble">{{ m.text }}</div>
          </template>
        </div>

        <div v-if="busy" class="msg bot"><div class="bubble muted">{{ $t('intake.ai.thinking') }}</div></div>
      </div>

      <form class="composer" @submit.prevent="send">
        <p v-if="error" class="error">{{ error }}</p>
        <div v-if="activeImagePreview" class="attach-chip">
          <img :src="activeImagePreview" :alt="$t('intake.food_photo_alt')" />
          <button type="button" class="attach-x" @click="clearImage" :aria-label="$t('intake.ai.remove_photo')">
            <i class="fa-solid fa-xmark" />
          </button>
        </div>
        <div class="row">
          <button type="button" class="tool" @click="photoInput?.click()" :aria-label="$t('intake.ai.attach_photo')">
            <i class="fa-solid fa-camera" />
          </button>
          <textarea
            v-model="input"
            rows="1"
            :placeholder="$t('intake.ai.placeholder')"
            :disabled="busy"
            @keydown.enter.exact.prevent="send"
          />
          <button type="submit" :disabled="busy || (!input.trim() && !activeImage)" :aria-label="$t('intake.ai.send')">
            <i class="fa-solid" :class="busy ? 'fa-spinner fa-spin' : 'fa-paper-plane'" />
          </button>
        </div>
        <input ref="photoInput" type="file" accept="image/*" style="display: none" @change="onPickImage" />
      </form>
    </div>
  </BottomSheet>
</template>

<style scoped>
.chat { display: flex; flex-direction: column; height: 68vh; }
.muted { color: var(--muted); font-size: 13px; }

.thread {
  flex: 1 1 auto;
  min-height: 0;
  overflow-y: auto;
  display: flex;
  flex-direction: column;
  gap: 12px;
  padding: 4px 2px 12px;
}

.intro { margin: auto; text-align: center; max-width: 280px; }
.intro-icon {
  width: 52px; height: 52px; margin: 0 auto 12px;
  display: grid; place-items: center;
  border-radius: 50%;
  background: rgba(74, 222, 128, 0.12);
  color: var(--accent);
  font-size: 20px;
}

.msg { display: flex; flex-direction: column; max-width: 88%; gap: 6px; }
.msg.user { align-self: flex-end; align-items: flex-end; }
.msg.bot { align-self: flex-start; align-items: flex-start; }
.bubble {
  padding: 10px 14px;
  border-radius: 14px;
  line-height: 1.5;
  font-size: 14px;
  word-break: break-word;
}
.msg.user .bubble { background: var(--accent); color: var(--on-accent); border-bottom-right-radius: 4px; }
.msg.bot .bubble { background: var(--inset); border: 1px solid var(--border); border-bottom-left-radius: 4px; }
.msg-thumb { width: 120px; max-width: 60%; border-radius: 12px; object-fit: cover; }

/* Bot nutrition card */
.food-card {
  background: var(--bg);
  border: 1px solid var(--border);
  border-radius: 12px;
  padding: 12px;
  width: 100%;
}
.food-head { display: flex; justify-content: space-between; align-items: baseline; gap: 8px; }
.food-head .cal { color: var(--accent); font-weight: 700; white-space: nowrap; }
.macros { margin: 6px 0 0; }
.advice { margin: 8px 0 0; font-size: 13px; color: #c4b5fd; }
.addbtn { margin-top: 10px; font-size: 13px; padding: 8px 14px; min-height: 0; }

/* Composer */
.composer { flex: none; border-top: 1px solid var(--border); padding: 12px 2px 2px; }
.composer .error { margin: 0 0 8px; }
.attach-chip { position: relative; width: 52px; height: 52px; margin-bottom: 8px; }
.attach-chip img { width: 100%; height: 100%; border-radius: 10px; object-fit: cover; }
.attach-x {
  position: absolute; top: -6px; right: -6px;
  width: 22px; height: 22px; min-height: 0; padding: 0;
  display: grid; place-items: center;
  border-radius: 50%; background: var(--surface-2); color: var(--text);
  font-size: 11px;
}
.composer .row { display: flex; gap: 8px; align-items: flex-end; }
.composer textarea { flex: 1; resize: none; max-height: 120px; font-family: inherit; }
.composer .tool {
  flex: none; width: 44px; height: 44px; min-height: 0; padding: 0;
  display: grid; place-items: center;
  background: var(--inset); color: var(--text); border: 1px solid var(--border);
}
.composer .tool:hover { color: var(--accent); border-color: var(--accent); }
.composer .row button[type='submit'] { flex: none; padding: 10px 14px; }
</style>
