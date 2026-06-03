<script setup>
// Compact achievement card — a tighter take on dashboard-progress.php's
// achievement-card. One tap opens the full detail (description + tiers) in a
// bottom sheet, so the row itself stays short: icon, name, tier, a value/target
// line, and a progress bar tinted by the achievement's tone.
import { computed } from 'vue';
import { t } from '../i18n/index.js';

const props = defineProps({
  ach: { type: Object, required: true },
});
defineEmits(['open']);

// tone -> {color, soft bg}. Mirrors the dashboard's status palette (XP purple,
// streak orange) and the --meal-* token hues, kept flat/dark per DESIGN.md.
const TONES = {
  primary: { c: '#4ade80', bg: 'rgba(74, 222, 128, 0.14)' },
  secondary: { c: '#60a5fa', bg: 'rgba(96, 165, 250, 0.14)' },
  accent: { c: '#a78bfa', bg: 'rgba(167, 139, 250, 0.14)' },
  success: { c: '#34d399', bg: 'rgba(52, 211, 153, 0.14)' },
  warning: { c: '#fbbf24', bg: 'rgba(251, 191, 36, 0.14)' },
};

// Prefer the localized copy; fall back to the server string so a not-yet-keyed
// achievement still renders (English) instead of showing a raw i18n key.
function loc(key, fallback) {
  const s = t(key);
  return s === key ? fallback : s;
}

const locked = computed(() => props.ach.level === 0);
const tone = computed(() => TONES[props.ach.tone] || TONES.primary);
const unit = computed(() => loc(`progress.ach.${props.ach.id}.unit`, props.ach.unit));
const fmt = (n) => Number(n || 0).toLocaleString();
</script>

<template>
  <button type="button" class="ac" :class="{ locked }" @click="$emit('open', ach)">
    <span class="ac-ico" :style="{ color: tone.c, background: tone.bg }">
      <i class="fa-solid" :class="ach.icon" />
    </span>
    <span class="ac-body">
      <span class="ac-top">
        <span class="ac-name">{{ ach.name }}</span>
        <span class="ac-tier" :class="{ done: ach.is_complete }">
          <template v-if="ach.is_complete">{{ $t('progress.card.maxed') }}</template>
          <template v-else-if="locked">{{ $t('progress.card.locked') }}</template>
          <template v-else>{{ $t('progress.card.level', { n: ach.level, max: ach.max_level }) }}</template>
        </span>
      </span>
      <span class="ac-nums muted">
        <strong>{{ fmt(ach.value) }}</strong>
        <template v-if="!ach.is_complete"> / {{ fmt(ach.next_target) }}</template>
        {{ unit }}
      </span>
      <span class="ac-bar"><span :style="{ width: ach.progress_pct + '%', background: tone.c }" /></span>
    </span>
  </button>
</template>

<style scoped>
.ac {
  display: flex;
  align-items: flex-start;
  gap: 12px;
  width: 100%;
  text-align: left;
  padding: 12px;
  background: var(--card);
  border: 1px solid var(--border);
  border-radius: 12px;
  color: var(--text);
  min-height: 0;
}
.ac:hover { border-color: var(--field-border); }
.ac.locked { opacity: 0.66; }
.ac-ico {
  flex: none;
  width: 38px;
  height: 38px;
  border-radius: 10px;
  display: grid;
  place-items: center;
  font-size: 16px;
}
.ac-body { flex: 1; min-width: 0; display: flex; flex-direction: column; gap: 6px; }
.ac-top { display: flex; align-items: center; gap: 8px; }
.ac-name { flex: 1; min-width: 0; font-weight: 700; font-size: 14px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.ac-tier {
  flex: none;
  font-size: 11px;
  font-weight: 700;
  color: var(--muted);
  background: var(--inset);
  padding: 2px 8px;
  border-radius: 999px;
}
.ac-tier.done { color: var(--on-accent); background: var(--accent); }
.ac-nums { font-size: 12px; }
.ac-nums strong { color: var(--text); font-weight: 700; }
.ac-bar { height: 6px; background: var(--inset); border-radius: 4px; overflow: hidden; }
.ac-bar > span { display: block; height: 100%; transition: width 0.3s; }
</style>
