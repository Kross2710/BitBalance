<script setup>
// Reusable confirm dialog — replaces window.confirm() across the admin panel so
// destructive actions use the app's own dark-flat styling (see DESIGN.md) instead
// of the browser's native popup. Teleports to <body> so it overlays regardless of
// the parent's stacking/overflow. Backdrop click and Esc both cancel (unless busy).
import { watch, onUnmounted } from 'vue';
import { t } from '../i18n/index.js';

const props = defineProps({
  open: { type: Boolean, default: false },
  title: { type: String, default: '' },
  message: { type: String, default: '' },
  confirmLabel: { type: String, default: '' },
  cancelLabel: { type: String, default: '' },
  danger: { type: Boolean, default: true },
  busy: { type: Boolean, default: false },
  // When true, the confirm button is disabled (e.g. nothing to do).
  confirmDisabled: { type: Boolean, default: false },
});
const emit = defineEmits(['confirm', 'cancel']);

function cancel() {
  if (props.busy) return;
  emit('cancel');
}

function onKey(e) {
  if (e.key === 'Escape') cancel();
}
watch(
  () => props.open,
  (isOpen) => {
    if (isOpen) document.addEventListener('keydown', onKey);
    else document.removeEventListener('keydown', onKey);
  }
);
onUnmounted(() => document.removeEventListener('keydown', onKey));
</script>

<template>
  <Teleport to="body">
    <Transition name="fade">
      <div v-if="open" class="cd-backdrop" @click.self="cancel">
        <div class="cd-card" role="alertdialog" aria-modal="true" :aria-label="title">
          <strong v-if="title" class="cd-title">{{ title }}</strong>
          <p v-if="message" class="cd-message">{{ message }}</p>
          <!-- Extra body (e.g. prune preview count, loading state). -->
          <div v-if="$slots.default" class="cd-body"><slot /></div>
          <div class="cd-actions">
            <button class="cd-cancel" :disabled="busy" @click="cancel">
              {{ cancelLabel || t('common.cancel') }}
            </button>
            <button
              class="cd-confirm"
              :class="{ danger }"
              :disabled="busy || confirmDisabled"
              @click="emit('confirm')"
            >
              {{ busy ? t('common.saving') : (confirmLabel || t('common.confirm')) }}
            </button>
          </div>
        </div>
      </div>
    </Transition>
  </Teleport>
</template>

<style scoped>
.cd-backdrop {
  position: fixed;
  inset: 0;
  z-index: 80;
  display: grid;
  place-items: center;
  padding: 20px;
  background: rgba(0, 0, 0, 0.6);
}
.cd-card {
  width: 100%;
  max-width: 380px;
  background: var(--card);
  border: 1px solid var(--border);
  border-radius: 14px;
  padding: 20px;
  box-shadow: 0 8px 24px rgba(0, 0, 0, 0.4);
}
.cd-title { display: block; font-size: 16px; font-weight: 800; }
.cd-message { color: var(--muted); font-size: 14px; line-height: 1.5; margin: 8px 0 0; }
.cd-body { margin-top: 10px; }
.cd-actions { display: flex; justify-content: flex-end; gap: 8px; margin-top: 18px; }
.cd-actions button {
  min-height: 40px;
  padding: 8px 16px;
  border-radius: 8px;
  font-weight: 700;
  font-size: 14px;
}
.cd-cancel { background: var(--surface-2); color: var(--text); }
.cd-confirm { background: var(--accent); color: var(--on-accent); }
.cd-confirm.danger { background: #ef4444; color: #fff; }
button:disabled { opacity: 0.6; cursor: default; }

/* Match the app's page fade. */
.fade-enter-active,
.fade-leave-active { transition: opacity 0.18s ease; }
.fade-enter-from,
.fade-leave-to { opacity: 0; }
</style>
