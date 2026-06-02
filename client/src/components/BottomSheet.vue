<script setup>
// Reusable mobile bottom sheet: slides up from the bottom with a grab handle,
// dimmed backdrop, tap-outside / Escape to close, and body scroll-lock while
// open. Teleported to <body> so it overlays the app chrome (topbar + tab bar).
// Mirrors the PHP app's `.pt-drawer` bottom-sheet so the two feel consistent.
import { watch, onBeforeUnmount } from 'vue';

const props = defineProps({
  open: { type: Boolean, default: false },
  title: { type: String, default: '' },
});
const emit = defineEmits(['close']);

function onKey(e) {
  if (e.key === 'Escape') emit('close');
}

// Lock background scroll + wire Escape only while the sheet is visible.
watch(
  () => props.open,
  (isOpen) => {
    if (typeof document === 'undefined') return;
    if (isOpen) {
      document.body.style.overflow = 'hidden';
      document.addEventListener('keydown', onKey);
    } else {
      document.body.style.overflow = '';
      document.removeEventListener('keydown', onKey);
    }
  }
);

onBeforeUnmount(() => {
  document.body.style.overflow = '';
  document.removeEventListener('keydown', onKey);
});
</script>

<template>
  <Teleport to="body">
    <Transition name="sheet">
      <div v-if="open" class="sheet" @click.self="emit('close')">
        <div class="sheet__panel" role="dialog" aria-modal="true" :aria-label="title || $t('coach.sheet.label')">
          <span class="sheet__handle" aria-hidden="true" />
          <header v-if="title || $slots.header" class="sheet__head">
            <slot name="header"><h3 class="sheet__title">{{ title }}</h3></slot>
            <button class="sheet__close" :aria-label="$t('common.close')" @click="emit('close')">
              <i class="fa-solid fa-xmark" />
            </button>
          </header>
          <div class="sheet__body"><slot /></div>
        </div>
      </div>
    </Transition>
  </Teleport>
</template>

<style scoped>
.sheet {
  position: fixed;
  inset: 0;
  z-index: 1000;
  display: flex;
  align-items: flex-end;
  background: rgba(0, 0, 0, 0.5);
}
.sheet__panel {
  display: flex;
  flex-direction: column;
  width: 100%;
  max-height: 85vh;
  background: var(--card);
  border-top: 1px solid var(--border);
  border-radius: 16px 16px 0 0;
  padding: 6px 14px calc(14px + env(safe-area-inset-bottom));
}
.sheet__handle {
  flex: none;
  width: 40px;
  height: 4px;
  margin: 4px auto 8px;
  border-radius: 999px;
  background: var(--border);
}
.sheet__head {
  flex: none;
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 8px;
  padding: 2px 0 10px;
}
.sheet__title {
  margin: 0;
  font-size: 15px;
  font-weight: 700;
}
.sheet__close {
  flex: none;
  width: 36px;
  height: 36px;
  min-height: 0;
  padding: 0;
  background: transparent;
  color: var(--muted);
}
.sheet__close:hover { color: var(--text); }
.sheet__body {
  flex: 1 1 auto;
  min-height: 0;
  overflow-y: auto;
  display: flex;
  flex-direction: column;
}

/* Backdrop fades; panel slides up from the bottom. */
.sheet-enter-active,
.sheet-leave-active { transition: opacity 0.22s ease; }
.sheet-enter-active .sheet__panel,
.sheet-leave-active .sheet__panel { transition: transform 0.26s cubic-bezier(0.32, 0.72, 0, 1); }
.sheet-enter-from,
.sheet-leave-to { opacity: 0; }
.sheet-enter-from .sheet__panel,
.sheet-leave-to .sheet__panel { transform: translateY(100%); }
</style>
