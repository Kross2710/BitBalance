<script setup>
import { ref, computed } from 'vue';
import { useRouter } from 'vue-router';
import { api } from '../lib/api.js';
import { useAuthStore } from '../stores/auth.js';

const auth = useAuthStore();
const router = useRouter();

const ACTIVITY = [
  ['sedentary', 'Sedentary — little or no exercise'],
  ['lightly_active', 'Lightly active — 1-3 days/week'],
  ['moderately_active', 'Moderately active — 3-5 days/week'],
  ['very_active', 'Very active — 6-7 days/week'],
  ['extra_active', 'Extra active — physical job / intense'],
];
const GOALS = [
  ['lose', 'Lose weight'],
  ['maintain', 'Maintain'],
  ['gain', 'Gain weight'],
];

const form = ref({
  gender: 'male',
  age: '',
  height: '',
  weight: '',
  activity_level: 'moderately_active',
  goal_mode: 'maintain',
  weekly_rate: '0.5',
  target_weight: '',
});
const error = ref('');
const busy = ref(false);
const result = ref(null);

const needsPace = computed(() => form.value.goal_mode !== 'maintain');

async function onSubmit() {
  error.value = '';
  busy.value = true;
  try {
    result.value = await api.post('/api/onboarding/save', form.value);
    auth.markOnboarded();
    // Show the computed plan briefly, then move to the dashboard.
    setTimeout(() => router.push({ name: 'dashboard' }), 1400);
  } catch (e) {
    error.value = e.message;
  } finally {
    busy.value = false;
  }
}
</script>

<template>
  <main style="max-width: 460px; margin: 6vh auto; padding: 0 16px">
    <h1 style="text-align: center">Let's set your goal</h1>

    <div v-if="result" class="card" style="text-align: center">
      <p class="muted">Your daily target</p>
      <p style="font-size: 32px; font-weight: 700; margin: 4px 0; color: var(--accent)">
        {{ result.calorie_goal }} kcal
      </p>
      <p class="muted">BMR {{ result.bmr }} · TDEE {{ result.tdee }} · {{ result.hydration_ml }} ml water</p>
      <p class="muted">Taking you to your dashboard…</p>
    </div>

    <form v-else class="card" @submit.prevent="onSubmit">
      <label>Gender</label>
      <select v-model="form.gender">
        <option value="male">Male</option>
        <option value="female">Female</option>
        <option value="other">Other</option>
      </select>

      <div style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 10px; margin-top: 12px">
        <div><label>Age</label><input v-model="form.age" type="number" min="13" max="100" required /></div>
        <div><label>Height (cm)</label><input v-model="form.height" type="number" min="100" max="250" required /></div>
        <div><label>Weight (kg)</label><input v-model="form.weight" type="number" min="30" max="300" required /></div>
      </div>

      <label style="display: block; margin-top: 12px">Activity level</label>
      <select v-model="form.activity_level">
        <option v-for="[v, l] in ACTIVITY" :key="v" :value="v">{{ l }}</option>
      </select>

      <label style="display: block; margin-top: 12px">Goal</label>
      <select v-model="form.goal_mode">
        <option v-for="[v, l] in GOALS" :key="v" :value="v">{{ l }}</option>
      </select>

      <template v-if="needsPace">
        <label style="display: block; margin-top: 12px">Weekly pace (kg/week)</label>
        <input v-model="form.weekly_rate" type="number" step="0.1" min="0" max="1.5" />
        <label style="display: block; margin-top: 12px">Target weight (kg, optional)</label>
        <input v-model="form.target_weight" type="number" min="0" max="500" />
      </template>

      <button type="submit" :disabled="busy" style="width: 100%; margin-top: 18px">
        {{ busy ? 'Building plan…' : 'Build my plan' }}
      </button>
      <p v-if="error" class="error">{{ error }}</p>
    </form>
  </main>
</template>

<style scoped>
.muted { color: var(--muted); font-size: 13px; }
</style>
