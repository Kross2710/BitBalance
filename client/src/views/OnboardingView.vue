<script setup>
import { ref, computed } from 'vue';
import { useRouter } from 'vue-router';
import { api } from '../lib/api.js';
import { useAuthStore } from '../stores/auth.js';

const auth = useAuthStore();
const router = useRouter();

const ACTIVITY = [
  ['sedentary', 'onboarding.activity.sedentary'],
  ['lightly_active', 'onboarding.activity.lightly_active'],
  ['moderately_active', 'onboarding.activity.moderately_active'],
  ['very_active', 'onboarding.activity.very_active'],
  ['extra_active', 'onboarding.activity.extra_active'],
];
const GOALS = [
  ['lose', 'onboarding.goal.lose'],
  ['maintain', 'onboarding.goal.maintain'],
  ['gain', 'onboarding.goal.gain'],
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
    <h1 style="text-align: center">{{ $t('onboarding.title') }}</h1>

    <div v-if="result" class="card" style="text-align: center">
      <p class="muted">{{ $t('onboarding.daily_target') }}</p>
      <p style="font-size: 32px; font-weight: 700; margin: 4px 0; color: var(--accent)">
        {{ result.calorie_goal }} {{ $t('common.kcal') }}
      </p>
      <p class="muted">BMR {{ result.bmr }} · TDEE {{ result.tdee }} · {{ $t('onboarding.water', { n: result.hydration_ml }) }}</p>
      <p class="muted">{{ $t('onboarding.redirecting') }}</p>
    </div>

    <form v-else class="card" @submit.prevent="onSubmit">
      <label>{{ $t('profile.body.gender') }}</label>
      <select v-model="form.gender">
        <option value="male">{{ $t('profile.body.gender.male') }}</option>
        <option value="female">{{ $t('profile.body.gender.female') }}</option>
        <option value="other">{{ $t('profile.body.gender.other') }}</option>
      </select>

      <div style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 10px; margin-top: 12px">
        <div><label>{{ $t('profile.body.age') }}</label><input v-model="form.age" type="number" min="13" max="100" required /></div>
        <div><label>{{ $t('profile.body.height') }}</label><input v-model="form.height" type="number" min="100" max="250" required /></div>
        <div><label>{{ $t('profile.body.weight') }}</label><input v-model="form.weight" type="number" min="30" max="300" required /></div>
      </div>

      <label style="display: block; margin-top: 12px">{{ $t('onboarding.activity_level') }}</label>
      <select v-model="form.activity_level">
        <option v-for="[v, key] in ACTIVITY" :key="v" :value="v">{{ $t(key) }}</option>
      </select>

      <label style="display: block; margin-top: 12px">{{ $t('onboarding.goal') }}</label>
      <select v-model="form.goal_mode">
        <option v-for="[v, key] in GOALS" :key="v" :value="v">{{ $t(key) }}</option>
      </select>

      <template v-if="needsPace">
        <label style="display: block; margin-top: 12px">{{ $t('onboarding.weekly_pace') }}</label>
        <input v-model="form.weekly_rate" type="number" step="0.1" min="0" max="1.5" />
        <label style="display: block; margin-top: 12px">{{ $t('onboarding.target_weight') }}</label>
        <input v-model="form.target_weight" type="number" min="0" max="500" />
      </template>

      <button type="submit" :disabled="busy" style="width: 100%; margin-top: 18px">
        {{ busy ? $t('onboarding.building') : $t('onboarding.build_plan') }}
      </button>
      <p v-if="error" class="error">{{ error }}</p>
    </form>
  </main>
</template>

<style scoped>
.muted { color: var(--muted); font-size: 13px; }
</style>
