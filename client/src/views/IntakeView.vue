<script setup>
// Dedicated Food Intake page — the primary "log food" surface (ports the core
// of the PHP Food Intake page minus barcode/AI photo, which come later).
// Big food field with history-backed autocomplete + recent chips, calories,
// meal, optional macros, and a full-width Log Entry button.
import { ref, reactive, computed, onMounted, watch } from 'vue';
import { api } from '../lib/api.js';

// Default the meal to the current time-of-day, like the PHP app / AI Coach.
function mealFromHour() {
  const h = new Date().getHours();
  if (h >= 5 && h < 11) return 'breakfast';
  if (h >= 11 && h < 15) return 'lunch';
  if (h >= 17 && h < 22) return 'dinner';
  return 'snack';
}

const form = reactive({ food_item: '', calories: '', meal_category: mealFromHour(), protein: '', carbs: '', fat: '' });
const showMacros = ref(false);
const recent = ref([]);
const suggestions = ref([]);
const showSuggest = ref(false);
const saving = ref(false);
const error = ref('');
const success = ref('');
let suggestTimer = null;
let justPicked = false;

const canSubmit = computed(() => form.food_item.trim() !== '' && Number(form.calories) > 0);

async function loadRecent() {
  try {
    const data = await api.get('/api/intake/suggest');
    recent.value = data.items;
  } catch {
    /* non-fatal: chips just won't show */
  }
}

function applyItem(item) {
  form.food_item = item.food_item;
  form.calories = item.calories;
  form.protein = item.protein ?? '';
  form.carbs = item.carbs ?? '';
  form.fat = item.fat ?? '';
  if (item.protein || item.carbs || item.fat) showMacros.value = true;
  showSuggest.value = false;
  suggestions.value = [];
}

function pickChip(item) {
  justPicked = true; // suppress the watch-triggered autocomplete for this change
  applyItem(item);
}

// Delay hiding so a click on a suggestion (mousedown) still registers.
function hideSuggestSoon() {
  setTimeout(() => (showSuggest.value = false), 150);
}

// Debounced autocomplete as the user types the food name.
watch(
  () => form.food_item,
  (val) => {
    if (justPicked) {
      justPicked = false;
      return;
    }
    clearTimeout(suggestTimer);
    const q = val.trim();
    if (q === '') {
      suggestions.value = [];
      showSuggest.value = false;
      return;
    }
    suggestTimer = setTimeout(async () => {
      try {
        const data = await api.get(`/api/intake/suggest?q=${encodeURIComponent(q)}`);
        suggestions.value = data.items;
        showSuggest.value = data.items.length > 0;
      } catch {
        suggestions.value = [];
      }
    }, 220);
  }
);

async function onSubmit() {
  if (!canSubmit.value || saving.value) return;
  error.value = '';
  success.value = '';
  saving.value = true;
  try {
    await api.post('/api/intake/create', { ...form });
    success.value = `Logged ${form.food_item}.`;
    form.food_item = '';
    form.calories = '';
    form.protein = '';
    form.carbs = '';
    form.fat = '';
    showMacros.value = false;
    suggestions.value = [];
    showSuggest.value = false;
    await loadRecent();
  } catch (e) {
    error.value = e.message;
  } finally {
    saving.value = false;
  }
}

onMounted(loadRecent);
</script>

<template>
  <main class="intake">
    <h1>Log food</h1>

    <form class="card" @submit.prevent="onSubmit">
      <!-- Food name + autocomplete -->
      <label for="intake-food">What did you eat?</label>
      <div class="food-field">
        <input
          id="intake-food"
          v-model="form.food_item"
          class="food-input"
          placeholder="e.g. Grilled chicken breast"
          autocomplete="off"
          required
          @focus="showSuggest = suggestions.length > 0"
          @blur="hideSuggestSoon"
        />
        <ul v-if="showSuggest" class="suggest">
          <li v-for="(s, i) in suggestions" :key="i" @mousedown.prevent="applyItem(s)">
            <span>{{ s.food_item }}</span>
            <span class="muted">{{ s.calories }} kcal</span>
          </li>
        </ul>
      </div>

      <!-- Recent chips -->
      <div v-if="recent.length" class="chips">
        <button v-for="(r, i) in recent" :key="i" type="button" class="chip" @click="pickChip(r)">
          {{ r.food_item }}
        </button>
      </div>

      <!-- Calories + meal -->
      <div class="two">
        <div>
          <label for="intake-kcal">Calories</label>
          <input id="intake-kcal" v-model="form.calories" type="number" min="1" placeholder="kcal" required />
        </div>
        <div>
          <label for="intake-meal">Meal</label>
          <select id="intake-meal" v-model="form.meal_category">
            <option value="breakfast">Breakfast</option>
            <option value="lunch">Lunch</option>
            <option value="dinner">Dinner</option>
            <option value="snack">Snack</option>
          </select>
        </div>
      </div>

      <!-- Optional macros -->
      <button type="button" class="ql-toggle" @click="showMacros = !showMacros">
        <i class="fa-solid" :class="showMacros ? 'fa-chevron-up' : 'fa-plus'" />
        {{ showMacros ? 'Hide macros' : 'Add macros (optional)' }}
      </button>
      <div v-if="showMacros" class="three">
        <div>
          <label for="intake-p">Protein g</label>
          <input id="intake-p" v-model="form.protein" type="number" min="0" />
        </div>
        <div>
          <label for="intake-c">Carbs g</label>
          <input id="intake-c" v-model="form.carbs" type="number" min="0" />
        </div>
        <div>
          <label for="intake-f">Fat g</label>
          <input id="intake-f" v-model="form.fat" type="number" min="0" />
        </div>
      </div>

      <button type="submit" class="log-btn" :disabled="!canSubmit || saving">
        {{ saving ? 'Logging…' : 'Log Entry' }}
      </button>
      <p v-if="success" class="ok">{{ success }}</p>
      <p v-if="error" class="error">{{ error }}</p>
    </form>
  </main>
</template>

<style scoped>
.intake { max-width: 560px; margin: 0 auto; padding: 8px 16px; }
.intake h1 { margin: 6px 0 16px; }
.muted { color: var(--muted); font-size: 13px; }
.ok { color: var(--accent); font-size: 13px; margin: 10px 0 0; }
label { font-size: 13px; color: var(--muted); display: block; margin-bottom: 4px; }

.food-field { position: relative; }
.food-input { font-size: 18px; padding: 14px; }
.suggest {
  position: absolute;
  left: 0;
  right: 0;
  top: calc(100% + 4px);
  z-index: 20;
  list-style: none;
  margin: 0;
  padding: 4px;
  background: var(--card);
  border: 1px solid var(--border);
  border-radius: 10px;
  box-shadow: 0 8px 24px rgba(0, 0, 0, 0.4);
}
.suggest li {
  display: flex;
  justify-content: space-between;
  gap: 10px;
  padding: 10px 10px;
  border-radius: 8px;
  cursor: pointer;
}
.suggest li:hover { background: #12151b; }

.chips { display: flex; flex-wrap: wrap; gap: 8px; margin-top: 12px; }
.chip {
  background: #12151b;
  color: var(--text);
  border: 1px solid var(--border);
  border-radius: 999px;
  padding: 7px 14px;
  min-height: 0;
  font-size: 13px;
  font-weight: 600;
}
.chip:hover { border-color: var(--accent); color: var(--accent); }

.two { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; margin-top: 14px; }
.three { display: grid; grid-template-columns: repeat(3, 1fr); gap: 12px; margin-top: 10px; }

.ql-toggle {
  background: transparent;
  color: var(--muted);
  border: 1px dashed var(--border);
  font-size: 13px;
  font-weight: 600;
  margin-top: 14px;
  padding: 8px 12px;
  min-height: 0;
}
.ql-toggle:hover { color: var(--text); }

.log-btn { width: 100%; margin-top: 18px; padding: 14px; font-size: 16px; }

@media (max-width: 480px) {
  .three { grid-template-columns: 1fr; }
}
</style>
