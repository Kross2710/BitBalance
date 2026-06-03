<script setup>
// Shared meal badge — one colour palette (the --meal-* tokens in styles.css) used
// by the Intake history rows AND the Dashboard meal breakdown/entries, so a meal
// reads the same everywhere. Soft pill: token bg + token text + meal icon.
defineProps({ meal: { type: String, required: true } });

const ICONS = {
  breakfast: 'fa-mug-saucer',
  lunch: 'fa-bowl-food',
  dinner: 'fa-utensils',
  snack: 'fa-cookie-bite',
};
</script>

<template>
  <span class="meal-badge" :class="meal">
    <i class="fa-solid" :class="ICONS[meal]" aria-hidden="true" />
    {{ $t('dashboard.meal.' + meal) }}
  </span>
</template>

<style scoped>
.meal-badge {
  display: inline-flex;
  align-items: center;
  gap: 5px;
  font-size: 11px;
  font-weight: 700;
  padding: 3px 9px;
  border-radius: 999px;
  white-space: nowrap;
}
.meal-badge i { font-size: 10px; }
.meal-badge.breakfast { background: var(--meal-breakfast-bg); color: var(--meal-breakfast); }
.meal-badge.lunch { background: var(--meal-lunch-bg); color: var(--meal-lunch); }
.meal-badge.dinner { background: var(--meal-dinner-bg); color: var(--meal-dinner); }
.meal-badge.snack { background: var(--meal-snack-bg); color: var(--meal-snack); }
</style>
