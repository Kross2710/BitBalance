<script setup>
import { ref, onMounted, computed } from 'vue';
import { useRouter } from 'vue-router';
import { api } from '../lib/api.js';
import { useAuthStore } from '../stores/auth.js';

const auth = useAuthStore();
const router = useRouter();

const loading = ref(true);
const entries = ref([]);
const summary = ref(null);
const error = ref('');

// Quick-log form
const form = ref({ food_item: '', calories: '', meal_category: 'breakfast', protein: '', carbs: '', fat: '' });
const saving = ref(false);

const progress = computed(() => (summary.value?.progress_percentage ?? 0) + '%');

async function load() {
  loading.value = true;
  error.value = '';
  try {
    const data = await api.get('/api/intake/history');
    entries.value = data.entries;
    summary.value = data.daily_summary;
  } catch (e) {
    error.value = e.message;
  } finally {
    loading.value = false;
  }
}

async function addEntry() {
  saving.value = true;
  error.value = '';
  try {
    const data = await api.post('/api/intake/create', form.value);
    entries.value.unshift(data.entry);
    summary.value = data.daily_summary;
    form.value = { food_item: '', calories: '', meal_category: 'breakfast', protein: '', carbs: '', fat: '' };
  } catch (e) {
    error.value = e.message;
  } finally {
    saving.value = false;
  }
}

async function onLogout() {
  await auth.logout();
  router.push({ name: 'login' });
}

onMounted(load);
</script>

<template>
  <main style="max-width: 760px; margin: 0 auto; padding: 24px 16px">
    <header style="display: flex; justify-content: space-between; align-items: center">
      <h1 style="margin: 0">Hi, {{ auth.user?.first_name || auth.user?.handle }}</h1>
      <button @click="onLogout" style="background: #2a2e37; color: var(--text)">Log out</button>
    </header>

    <section v-if="summary" class="card" style="margin-top: 18px">
      <div style="display: flex; justify-content: space-between">
        <strong>Today</strong>
        <span class="muted">{{ summary.total_calories }} / {{ summary.calorie_goal ?? '—' }} kcal</span>
      </div>
      <div style="height: 10px; background: #12151b; border-radius: 6px; margin-top: 10px; overflow: hidden">
        <div :style="{ width: progress, height: '100%', background: 'var(--accent)', transition: 'width .3s' }" />
      </div>
      <div style="display: flex; gap: 16px; margin-top: 12px; font-size: 13px" class="muted">
        <span>P {{ summary.macros.protein }} / {{ summary.macro_goals.protein }}g</span>
        <span>C {{ summary.macros.carbs }} / {{ summary.macro_goals.carbs }}g</span>
        <span>F {{ summary.macros.fat }} / {{ summary.macro_goals.fat }}g</span>
      </div>
    </section>

    <section class="card" style="margin-top: 18px">
      <strong>Quick log</strong>
      <form @submit.prevent="addEntry" style="display: grid; grid-template-columns: 2fr 1fr 1fr; gap: 10px; margin-top: 12px">
        <input v-model="form.food_item" placeholder="Food item" required />
        <input v-model="form.calories" type="number" placeholder="kcal" min="1" required />
        <select v-model="form.meal_category">
          <option value="breakfast">Breakfast</option>
          <option value="lunch">Lunch</option>
          <option value="dinner">Dinner</option>
          <option value="snack">Snack</option>
        </select>
        <input v-model="form.protein" type="number" placeholder="Protein g" min="0" />
        <input v-model="form.carbs" type="number" placeholder="Carbs g" min="0" />
        <input v-model="form.fat" type="number" placeholder="Fat g" min="0" />
        <button type="submit" :disabled="saving" style="grid-column: 1 / -1">
          {{ saving ? 'Adding…' : 'Add entry' }}
        </button>
      </form>
    </section>

    <p v-if="error" class="error">{{ error }}</p>

    <section style="margin-top: 18px">
      <p v-if="loading" class="muted">Loading…</p>
      <p v-else-if="!entries.length" class="muted">No entries yet.</p>
      <ul v-else style="list-style: none; padding: 0; display: flex; flex-direction: column; gap: 8px">
        <li v-for="e in entries" :key="e.id" class="card" style="display: flex; justify-content: space-between; padding: 12px 16px">
          <span>{{ e.food_item }} <small class="muted">· {{ e.meal_category }}</small></span>
          <strong>{{ e.calories }} kcal</strong>
        </li>
      </ul>
    </section>
  </main>
</template>

<style scoped>
.muted { color: var(--muted); }
</style>
