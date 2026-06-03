<script setup>
import { ref, reactive, computed } from 'vue';
import { useRouter } from 'vue-router';
import { api } from '../lib/api.js';
import { useAuthStore } from '../stores/auth.js';

const auth = useAuthStore();
const router = useRouter();

// 5 screens: about(0) · metrics(1) · activity(2) · goal+pace(3) · preview(4).
// Pace is disclosed inline on the goal screen (lose/gain only), so the step
// count is fixed — unlike the PHP wizard's variable 6/7.
const TOTAL = 5;

const GENDERS = ['male', 'female', 'other'];
const GENDER_ICONS = { male: 'fa-mars', female: 'fa-venus', other: 'fa-circle' };

const ACTIVITIES = ['sedentary', 'lightly_active', 'moderately_active', 'very_active', 'extra_active'];
const ACTIVITY_ICONS = {
  sedentary: 'fa-couch',
  lightly_active: 'fa-person-walking',
  moderately_active: 'fa-person-running',
  very_active: 'fa-dumbbell',
  extra_active: 'fa-fire-flame-curved',
};

const GOALS = ['lose', 'maintain', 'gain'];
const GOAL_ICONS = { lose: 'fa-arrow-trend-down', maintain: 'fa-scale-balanced', gain: 'fa-arrow-trend-up' };

const PACES = [
  { value: '0.25', key: 'gentle', icon: 'fa-feather', rate: 0.25 },
  { value: '0.5', key: 'steady', icon: 'fa-bolt', rate: 0.5 },
  { value: '0.75', key: 'fast', icon: 'fa-fire', rate: 0.75 },
];

// Sensible defaults (like PHP) so a phone user taps through fast; every value is
// visible and editable on its screen.
const form = reactive({
  gender: 'male',
  age: 25,
  height: 170,
  weight: 65,
  activity_level: 'moderately_active',
  goal_mode: 'maintain',
  weekly_rate: '0.5',
  target_weight: '',
});

const step = ref(0);
const error = ref('');
const busy = ref(false);
const plan = ref(null);

const needsPace = computed(() => form.goal_mode !== 'maintain');
const pct = computed(() => `${((step.value + 1) / TOTAL) * 100}%`);

function inRange(v, min, max) {
  const n = Number(v);
  return Number.isFinite(n) && n >= min && n <= max;
}

// Per-step gate for the Continue button. Form state is reactive and lives across
// steps, so values are preserved for free — no PHP-style re-injection on error.
const stepValid = computed(() => {
  switch (step.value) {
    case 0:
      return GENDERS.includes(form.gender) && inRange(form.age, 13, 100);
    case 1:
      return inRange(form.height, 100, 250) && inRange(form.weight, 30, 300);
    case 2:
      return ACTIVITIES.includes(form.activity_level);
    case 3:
      return GOALS.includes(form.goal_mode) && (!needsPace.value || !!form.weekly_rate);
    default:
      return true;
  }
});

// Maintain ignores pace/target; the server normalizes too, but keep the payload
// clean so preview and commit send identical bodies.
function payload() {
  return {
    gender: form.gender,
    age: form.age,
    height: form.height,
    weight: form.weight,
    activity_level: form.activity_level,
    goal_mode: form.goal_mode,
    weekly_rate: needsPace.value ? form.weekly_rate : 0,
    target_weight: needsPace.value ? form.target_weight : '',
  };
}

function back() {
  if (busy.value || step.value === 0) return;
  error.value = '';
  step.value -= 1;
}

// Fetch the plan WITHOUT persisting (single source of truth = the server), then
// reveal the preview screen. No fake "AI is calculating" delay.
async function loadPreview() {
  busy.value = true;
  error.value = '';
  try {
    plan.value = await api.post('/api/onboarding/preview', payload());
    step.value = 4;
  } catch (e) {
    error.value = e.message;
  } finally {
    busy.value = false;
  }
}

function next() {
  if (!stepValid.value || busy.value) return;
  error.value = '';
  if (step.value === 3) {
    loadPreview();
  } else if (step.value < 4) {
    step.value += 1;
  }
}

async function commit() {
  if (busy.value) return;
  busy.value = true;
  error.value = '';
  try {
    await api.post('/api/onboarding/save', payload());
    auth.markOnboarded();
    router.push({ name: 'dashboard' });
  } catch (e) {
    error.value = e.message;
    busy.value = false;
  }
}
</script>

<template>
  <main class="wiz">
    <div class="wiz-inner">
      <header class="wiz-top">
        <button
          v-if="step > 0"
          type="button"
          class="wiz-back"
          :disabled="busy"
          :aria-label="$t('onboarding.back')"
          @click="back"
        >
          <i class="fa-solid fa-arrow-left"></i>
        </button>
        <div class="wiz-track"><div class="wiz-fill" :style="{ width: pct }"></div></div>
        <span class="wiz-count">{{ $t('onboarding.progress', { n: step + 1, total: TOTAL }) }}</span>
      </header>

      <div class="wiz-steps">
          <!-- Step 0 — About you -->
          <section v-if="step === 0" class="card wiz-step">
            <h1>{{ $t('onboarding.step.about.title') }}</h1>
            <p class="muted">{{ $t('onboarding.step.about.sub') }}</p>

            <span class="wiz-label">{{ $t('profile.body.gender') }}</span>
            <div class="choice-grid cols-3">
              <button
                v-for="g in GENDERS"
                :key="g"
                type="button"
                class="choice"
                :class="{ 'is-selected': form.gender === g }"
                @click="form.gender = g"
              >
                <i class="fa-solid" :class="GENDER_ICONS[g]"></i>
                <span>{{ $t('profile.body.gender.' + g) }}</span>
              </button>
            </div>

            <label class="wiz-label" for="wiz-age">{{ $t('profile.body.age') }}</label>
            <input id="wiz-age" v-model.number="form.age" type="number" inputmode="numeric" min="13" max="100" />
          </section>

          <!-- Step 1 — Body metrics -->
          <section v-else-if="step === 1" class="card wiz-step">
            <h1>{{ $t('onboarding.step.metrics.title') }}</h1>
            <p class="muted">{{ $t('onboarding.step.metrics.sub') }}</p>

            <label class="wiz-label" for="wiz-height">{{ $t('profile.body.height') }}</label>
            <input id="wiz-height" v-model.number="form.height" type="number" inputmode="numeric" min="100" max="250" />

            <label class="wiz-label" for="wiz-weight">{{ $t('profile.body.weight') }}</label>
            <input id="wiz-weight" v-model.number="form.weight" type="number" inputmode="numeric" min="30" max="300" />
          </section>

          <!-- Step 2 — Activity -->
          <section v-else-if="step === 2" class="card wiz-step">
            <h1>{{ $t('onboarding.step.activity.title') }}</h1>
            <p class="muted">{{ $t('onboarding.step.activity.sub') }}</p>

            <div class="choice-grid">
              <button
                v-for="a in ACTIVITIES"
                :key="a"
                type="button"
                class="choice row"
                :class="{ 'is-selected': form.activity_level === a }"
                @click="form.activity_level = a"
              >
                <i class="fa-solid" :class="ACTIVITY_ICONS[a]"></i>
                <span class="choice-text">
                  <strong>{{ $t('onboarding.activity.' + a + '.label') }}</strong>
                  <span class="sub">{{ $t('onboarding.activity.' + a + '.detail') }}</span>
                </span>
              </button>
            </div>
          </section>

          <!-- Step 3 — Goal (+ pace inline for lose/gain) -->
          <section v-else-if="step === 3" class="card wiz-step">
            <h1>{{ $t('onboarding.step.goal.title') }}</h1>
            <p class="muted">{{ $t('onboarding.step.goal.sub') }}</p>

            <div class="choice-grid cols-3">
              <button
                v-for="g in GOALS"
                :key="g"
                type="button"
                class="choice"
                :class="{ 'is-selected': form.goal_mode === g }"
                @click="form.goal_mode = g"
              >
                <i class="fa-solid" :class="GOAL_ICONS[g]"></i>
                <span>{{ $t('onboarding.goal.' + g) }}</span>
              </button>
            </div>
            <p class="wiz-hint">{{ $t('onboarding.goal.' + form.goal_mode + '.copy') }}</p>

            <template v-if="needsPace">
              <span class="wiz-label">{{ $t('onboarding.pace.label') }}</span>
              <div class="choice-grid cols-3">
                <button
                  v-for="p in PACES"
                  :key="p.value"
                  type="button"
                  class="choice"
                  :class="{ 'is-selected': form.weekly_rate === p.value }"
                  @click="form.weekly_rate = p.value"
                >
                  <i class="fa-solid" :class="p.icon"></i>
                  <span>{{ $t('onboarding.pace.' + p.key) }}</span>
                  <span class="sub">{{ $t('onboarding.pace.rate', { n: p.rate }) }}</span>
                </button>
              </div>

              <label class="wiz-label" for="wiz-target">{{ $t('onboarding.target_weight') }}</label>
              <input
                id="wiz-target"
                v-model="form.target_weight"
                type="number"
                inputmode="decimal"
                min="30"
                max="300"
                step="0.1"
              />
            </template>
          </section>

          <!-- Step 4 — Plan preview -->
          <section v-else-if="step === 4 && plan" class="card wiz-step">
            <span class="wiz-ready"><i class="fa-solid fa-circle-check"></i> {{ $t('onboarding.preview.ready') }}</span>
            <h1>{{ $t('onboarding.step.preview.title') }}</h1>

            <div class="wiz-cal">
              <span class="muted">{{ $t('onboarding.daily_target') }}</span>
              <strong>{{ plan.calorie_goal }} <small>{{ $t('common.kcal') }}</small></strong>
            </div>

            <div class="wiz-metrics">
              <div>
                <span>{{ $t('onboarding.preview.protein') }}</span>
                <strong>{{ plan.macros.protein }}<small>{{ $t('onboarding.preview.per_day') }}</small></strong>
              </div>
              <div>
                <span>{{ $t('onboarding.preview.carbs') }}</span>
                <strong>{{ plan.macros.carbs }}<small>{{ $t('onboarding.preview.per_day') }}</small></strong>
              </div>
              <div>
                <span>{{ $t('onboarding.preview.fat') }}</span>
                <strong>{{ plan.macros.fat }}<small>{{ $t('onboarding.preview.per_day') }}</small></strong>
              </div>
            </div>

            <div class="wiz-metrics">
              <div>
                <span>{{ $t('onboarding.preview.bmr') }}</span>
                <strong>{{ plan.bmr }}</strong>
              </div>
              <div>
                <span>{{ $t('onboarding.preview.tdee') }}</span>
                <strong>{{ plan.tdee }}</strong>
              </div>
              <div>
                <span>{{ $t('onboarding.preview.water') }}</span>
                <strong>{{ plan.hydration_ml }} ml</strong>
              </div>
            </div>

            <p class="wiz-disclaimer">
              <i class="fa-solid fa-circle-info"></i>
              <span>{{ $t('onboarding.disclaimer') }}</span>
            </p>
          </section>

          <div class="wiz-actions">
            <button v-if="step < 3" :disabled="!stepValid" @click="next">{{ $t('onboarding.continue') }}</button>
            <button v-else-if="step === 3" :disabled="!stepValid || busy" @click="next">
              {{ busy ? $t('onboarding.building') : $t('onboarding.see_plan') }}
            </button>
            <button v-else :disabled="busy" @click="commit">
              {{ busy ? $t('onboarding.building') : $t('onboarding.commit') }}
            </button>
          </div>
          <p v-if="error" class="error">{{ error }}</p>
      </div>
    </div>
  </main>
</template>

<style scoped>
.wiz {
  min-height: 100vh;
  display: flex;
  justify-content: center;
  padding: 24px 16px 40px;
}
.wiz-inner {
  width: 100%;
  max-width: 460px;
}

.wiz-top {
  display: flex;
  align-items: center;
  gap: 12px;
  margin-bottom: 18px;
}
.wiz-back {
  flex: none;
  width: 40px;
  height: 40px;
  min-height: 40px;
  padding: 0;
  display: grid;
  place-items: center;
  background: var(--surface-2);
  color: var(--text);
}
.wiz-track {
  flex: 1;
  height: 8px;
  background: var(--inset);
  border-radius: 6px;
  overflow: hidden;
}
.wiz-fill {
  height: 100%;
  background: var(--accent);
  border-radius: 6px;
  transition: width 0.3s ease;
}
.wiz-count {
  flex: none;
  font-size: 12px;
  color: var(--muted);
}

.wiz-step h1 {
  font-size: 22px;
  margin: 0 0 4px;
}
.muted {
  color: var(--muted);
  font-size: 14px;
}
.wiz-step > .muted {
  margin: 0 0 18px;
}
.wiz-label {
  display: block;
  margin: 18px 0 8px;
  font-size: 13px;
  font-weight: 600;
  color: var(--muted);
}
.wiz-hint {
  margin: 10px 2px 0;
  font-size: 13px;
  color: var(--muted);
}

.wiz-actions {
  margin-top: 18px;
}
.wiz-actions button {
  width: 100%;
}

/* Preview screen */
.wiz-ready {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  margin-bottom: 10px;
  padding: 5px 10px;
  border-radius: 999px;
  font-size: 12px;
  font-weight: 600;
  color: var(--accent);
  background: var(--focus-ring);
}
.wiz-cal {
  text-align: center;
  margin: 8px 0 16px;
}
.wiz-cal strong {
  display: block;
  font-size: 40px;
  font-weight: 800;
  color: var(--accent);
}
.wiz-cal small {
  font-size: 16px;
  font-weight: 600;
  color: var(--muted);
}
.wiz-metrics {
  display: grid;
  grid-template-columns: repeat(3, 1fr);
  gap: 10px;
  margin-top: 10px;
}
.wiz-metrics > div {
  padding: 12px;
  border: 1px solid var(--border);
  border-radius: 10px;
  background: var(--inset);
  text-align: center;
}
.wiz-metrics span {
  display: block;
  font-size: 12px;
  color: var(--muted);
}
.wiz-metrics strong {
  display: block;
  margin-top: 4px;
  font-size: 18px;
}
.wiz-metrics small {
  margin-left: 2px;
  font-size: 11px;
  font-weight: 600;
  color: var(--muted);
}
.wiz-disclaimer {
  display: flex;
  gap: 8px;
  margin: 16px 0 0;
  padding: 12px;
  border: 1px solid var(--border);
  border-radius: 10px;
  background: var(--inset);
  font-size: 12px;
  line-height: 1.5;
  color: var(--muted);
}
.wiz-disclaimer i {
  margin-top: 2px;
}
</style>
