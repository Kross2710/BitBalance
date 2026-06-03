<script setup>
// Shared calories + macros summary card. Used on both the Dashboard and the
// Intake page so the running total looks identical and stays in one place.
// Fed by either the dashboard `day` payload or intake's `daily_summary` — both
// carry total_calories, calorie_goal, progress_percentage, macros, macro_goals.
// overlimit + remaining/over are derived here (neither payload needs to send a
// status_class/focus block).
import { computed } from 'vue';

const props = defineProps({
  summary: { type: Object, required: true },
});

const total = computed(() => Number(props.summary.total_calories) || 0);
const goal = computed(() => {
  const g = props.summary.calorie_goal;
  return g != null && Number(g) > 0 ? Number(g) : null;
});
const progress = computed(() => (props.summary.progress_percentage ?? 0) + '%');
const overlimit = computed(() => goal.value != null && total.value > goal.value);
const remaining = computed(() => (goal.value != null ? Math.max(0, goal.value - total.value) : null));
const overBy = computed(() => (goal.value != null ? Math.max(0, total.value - goal.value) : null));
const macros = computed(() => props.summary.macros || { protein: 0, carbs: 0, fat: 0 });
const macroGoals = computed(() => props.summary.macro_goals || { protein: 0, carbs: 0, fat: 0 });
</script>

<template>
  <section class="card calorie-summary">
    <div class="cs-head">
      <strong>{{ $t('dashboard.summary.calories') }}</strong>
      <span class="muted">{{ total }} / {{ goal ?? '—' }} {{ $t('common.kcal') }}</span>
    </div>
    <div class="bar"><div :style="{ width: progress, background: overlimit ? '#f87171' : 'var(--accent)' }" /></div>
    <div class="cs-macros muted">
      <span>P {{ macros.protein }} / {{ macroGoals.protein }}g</span>
      <span>C {{ macros.carbs }} / {{ macroGoals.carbs }}g</span>
      <span>F {{ macros.fat }} / {{ macroGoals.fat }}g</span>
    </div>
    <p v-if="goal != null" class="muted cs-focus">
      <template v-if="!overlimit">{{ $t('dashboard.focus.title.left', { n: remaining }) }}</template>
      <template v-else>{{ $t('dashboard.focus.title.over', { n: overBy }) }}</template>
    </p>
  </section>
</template>

<style scoped>
.muted { color: var(--muted); font-size: 13px; }
.cs-head { display: flex; justify-content: space-between; }
.bar { height: 10px; background: var(--inset); border-radius: 6px; margin-top: 10px; overflow: hidden; }
.bar > div { height: 100%; transition: width 0.3s; }
.cs-macros { display: flex; gap: 16px; margin-top: 12px; font-size: 13px; }
.cs-focus { margin: 10px 0 0; font-size: 13px; }
</style>
