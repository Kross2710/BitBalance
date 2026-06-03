<script setup>
// Floating celebration toast for level-ups + achievement unlocks. Driven by the
// shared queue in lib/unlockToast.js; teleported to <body> so it floats over the
// app chrome like the intake undo bar. Tapping "View" jumps to /progress.
import { computed } from 'vue';
import { useRouter } from 'vue-router';
import { t } from '../i18n/index.js';
import { useUnlockToast, dismissToast } from '../lib/unlockToast.js';

const { current } = useUnlockToast();
const router = useRouter();

const TONES = {
  primary: { c: '#4ade80', bg: 'rgba(74, 222, 128, 0.16)' },
  secondary: { c: '#60a5fa', bg: 'rgba(96, 165, 250, 0.16)' },
  accent: { c: '#a78bfa', bg: 'rgba(167, 139, 250, 0.16)' },
  success: { c: '#34d399', bg: 'rgba(52, 211, 153, 0.16)' },
  warning: { c: '#fbbf24', bg: 'rgba(251, 191, 36, 0.16)' },
};

const tone = computed(() => {
  if (current.value?.type === 'levelup') return { c: '#a78bfa', bg: 'rgba(167, 139, 250, 0.16)' };
  return TONES[current.value?.tone] || TONES.primary;
});
const icon = computed(() => (current.value?.type === 'levelup' ? 'fa-arrow-up' : current.value?.icon || 'fa-trophy'));
const title = computed(() => {
  const c = current.value;
  if (!c) return '';
  if (c.type === 'levelup') return t('progress.toast.levelup', { n: c.level });
  if (c.type === 'tier') return c.name;
  return t('progress.toast.unlocked');
});
const sub = computed(() => {
  const c = current.value;
  if (!c) return '';
  if (c.type === 'unlock') return c.name;
  if (c.type === 'tier') return t('progress.toast.tier', { n: c.level });
  return '';
});

function view() {
  dismissToast();
  router.push('/progress');
}
</script>

<template>
  <Teleport to="body">
    <Transition name="unlock">
      <div v-if="current" :key="current.k" class="unlock-toast" role="status">
        <span class="ut-ico" :style="{ color: tone.c, background: tone.bg }">
          <i class="fa-solid" :class="icon" />
        </span>
        <span class="ut-txt">
          <strong>{{ title }}</strong>
          <small v-if="sub">{{ sub }}</small>
        </span>
        <button type="button" class="ut-view" @click="view">{{ $t('progress.toast.view') }}</button>
      </div>
    </Transition>
  </Teleport>
</template>

<style scoped>
.unlock-toast {
  position: fixed;
  left: 50%;
  transform: translateX(-50%);
  bottom: calc(76px + env(safe-area-inset-bottom));
  z-index: 60;
  display: flex;
  align-items: center;
  gap: 12px;
  width: max-content;
  max-width: calc(100% - 32px);
  padding: 10px 12px 10px 12px;
  background: var(--card);
  border: 1px solid var(--border);
  border-radius: 14px;
  box-shadow: 0 8px 24px rgba(0, 0, 0, 0.45);
}
.ut-ico {
  flex: none;
  width: 38px;
  height: 38px;
  border-radius: 10px;
  display: grid;
  place-items: center;
  font-size: 16px;
}
.ut-txt { display: flex; flex-direction: column; gap: 1px; min-width: 0; }
.ut-txt strong { font-size: 14px; font-weight: 800; }
.ut-txt small { font-size: 12px; color: var(--muted); overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.ut-view {
  flex: none;
  margin-left: 4px;
  background: transparent;
  color: var(--accent);
  border: none;
  font-weight: 700;
  font-size: 13px;
  padding: 6px 8px;
  min-height: 0;
}

/* Pop up from the bottom with a tiny rise — celebratory but calm. */
.unlock-enter-active { transition: opacity 0.24s ease, transform 0.28s cubic-bezier(0.32, 0.72, 0, 1); }
.unlock-leave-active { transition: opacity 0.2s ease, transform 0.2s ease; }
.unlock-enter-from { opacity: 0; transform: translateX(-50%) translateY(16px); }
.unlock-leave-to { opacity: 0; transform: translateX(-50%) translateY(8px); }
</style>
