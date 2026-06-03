<script setup>
// Three compact macro inputs (P / C / F) on one row. Used by the Intake form's
// macro tray and the edit sheet. Per the UX feedback, colour lives only on the
// letter chip + left border + focus ring — the input keeps the app's surface bg
// so it never reads as "disabled". The unit "g" sits inside each field.
const props = defineProps({
  protein: { type: [String, Number], default: '' },
  carbs: { type: [String, Number], default: '' },
  fat: { type: [String, Number], default: '' },
});
const emit = defineEmits(['update:protein', 'update:carbs', 'update:fat']);

const FIELDS = [
  { key: 'protein', cls: 'p', abbr: 'P', labelKey: 'intake.protein_g' },
  { key: 'carbs', cls: 'c', abbr: 'C', labelKey: 'intake.carbs_g' },
  { key: 'fat', cls: 'f', abbr: 'F', labelKey: 'intake.fat_g' },
];
</script>

<template>
  <div class="macros-row">
    <div v-for="f in FIELDS" :key="f.key" class="macro-field" :class="f.cls">
      <span class="macro-letter" aria-hidden="true">{{ f.abbr }}</span>
      <input
        :value="props[f.key]"
        type="number"
        min="0"
        step="any"
        inputmode="decimal"
        placeholder="0"
        :aria-label="$t(f.labelKey)"
        @input="emit('update:' + f.key, $event.target.value)"
      />
      <span class="macro-unit" aria-hidden="true">g</span>
    </div>
  </div>
</template>

<style scoped>
.macros-row { display: grid; grid-template-columns: repeat(3, 1fr); gap: 8px; margin-top: 10px; }
.macro-field {
  display: flex;
  align-items: center;
  gap: 6px;
  min-width: 0;
  padding: 0 10px 0 8px;
  background: var(--inset);
  border: 1px solid var(--border);
  border-left: 3px solid var(--mc);
  border-radius: 10px;
  min-height: 46px;
  transition: border-color 0.15s, box-shadow 0.15s;
}
.macro-field.p { --mc: var(--macro-p); --mcbg: var(--macro-p-bg); }
.macro-field.c { --mc: var(--macro-c); --mcbg: var(--macro-c-bg); }
.macro-field.f { --mc: var(--macro-f); --mcbg: var(--macro-f-bg); }
.macro-field:focus-within { border-color: var(--mc); box-shadow: 0 0 0 3px var(--mcbg); }
.macro-letter {
  flex: none;
  display: inline-grid;
  place-items: center;
  width: 20px;
  height: 20px;
  border-radius: 6px;
  font-size: 11px;
  font-weight: 800;
  color: var(--mc);
  background: var(--mcbg);
}
.macro-field input {
  flex: 1;
  min-width: 0;
  border: none;
  background: transparent;
  padding: 10px 0;
  font-size: 15px;
  color: var(--text);
}
.macro-field input:focus { outline: none; }
.macro-unit { flex: none; color: var(--muted); font-size: 12px; }
</style>
