<script setup>
// Settings — system/app preferences split out of the Profile page: appearance
// (theme), language, privacy, AI Coach voice (tone + custom persona) and meal
// reminders. Identity (name/email/bio/avatar) and body/goal stay on Profile.
import { ref, reactive, onMounted, watch } from 'vue';
import { api } from '../lib/api.js';
import { useBadgesStore } from '../stores/badges.js';
import { t, locale, setLocale, locales } from '../i18n/index.js';
import { setTheme } from '../lib/theme.js';

const badgesStore = useBadgesStore();

const loading = ref(true);
const saving = ref(false);
const error = ref('');
const success = ref('');

const form = reactive({
  theme_preference: 'system',
  visibility: 'friends',
  show_favorite_food: true,
  ai_tone: 'formal',
  ai_persona: '',
});

const PERSONA_MAX = 280;

// Live-preview the theme on pick (session-only); the DB write rides the Save.
watch(() => form.theme_preference, (v) => setTheme(v, { persist: false }));

function hydrate(data) {
  form.theme_preference = data.user?.theme_preference ?? 'system';
  form.visibility = data.privacy?.visibility ?? 'friends';
  form.show_favorite_food = data.privacy?.show_favorite_food ?? true;
  form.ai_tone = data.ai?.tone ?? 'formal';
  form.ai_persona = data.ai?.persona ?? '';
}

onMounted(async () => {
  loadReminders();
  try {
    hydrate(await api.get('/api/profile'));
  } catch (e) {
    error.value = e.message;
  } finally {
    loading.value = false;
  }
});

async function onSave() {
  error.value = '';
  success.value = '';
  saving.value = true;
  try {
    const data = await api.post('/api/profile/settings', {
      theme_preference: form.theme_preference,
      visibility: form.visibility,
      show_favorite_food: form.show_favorite_food,
      ai_tone: form.ai_tone,
      ai_persona: form.ai_persona,
    });
    hydrate(data);
    setTheme(data.user?.theme_preference ?? 'system'); // persist now it's saved
    success.value = t('settings.saved');
  } catch (e) {
    error.value = e.message;
  } finally {
    saving.value = false;
  }
}

// ---- Meal reminders (moved from Profile) ----
const REMINDER_MEALS = [
  { key: 'breakfast', labelKey: 'reminders.meal.breakfast', icon: 'fa-mug-saucer' },
  { key: 'lunch', labelKey: 'reminders.meal.lunch', icon: 'fa-bowl-food' },
  { key: 'dinner', labelKey: 'reminders.meal.dinner', icon: 'fa-utensils' },
  { key: 'snack', labelKey: 'reminders.meal.snack', icon: 'fa-cookie-bite' },
];
const reminders = reactive({
  enabled: false,
  meals: {
    breakfast: { enabled: true, time: '08:30' },
    lunch: { enabled: true, time: '12:30' },
    dinner: { enabled: true, time: '19:00' },
    snack: { enabled: false, time: '16:00' },
  },
});
const savingReminders = ref(false);
const remindersMsg = ref('');

async function loadReminders() {
  try {
    const d = await api.get('/api/reminders');
    reminders.enabled = d.enabled;
    reminders.meals = d.meals;
  } catch {
    /* non-fatal: keep defaults */
  }
}

async function saveReminders() {
  remindersMsg.value = '';
  savingReminders.value = true;
  try {
    const d = await api.post('/api/reminders', { enabled: reminders.enabled, meals: reminders.meals });
    reminders.enabled = d.enabled;
    reminders.meals = d.meals;
    remindersMsg.value = t('reminders.saved');
    badgesStore.refresh();
  } catch (e) {
    remindersMsg.value = e.message;
  } finally {
    savingReminders.value = false;
  }
}
</script>

<template>
  <main class="settings-page">
    <h1>{{ $t('settings.title') }}</h1>

    <p v-if="loading" class="muted">{{ $t('common.loading') }}</p>

    <template v-else>
      <form @submit.prevent="onSave">
        <!-- Appearance + language -->
        <section class="card">
          <h2>{{ $t('profile.appearance.title') }}</h2>
          <label>{{ $t('profile.theme.label') }}</label>
          <select v-model="form.theme_preference">
            <option value="system">{{ $t('profile.theme.system') }}</option>
            <option value="light">{{ $t('profile.theme.light') }}</option>
            <option value="dark">{{ $t('profile.theme.dark') }}</option>
          </select>
          <label style="display: block; margin-top: 12px">{{ $t('profile.language.title') }}</label>
          <select :value="locale" @change="setLocale($event.target.value)">
            <option v-for="(meta, code) in locales" :key="code" :value="code">{{ meta.native }}</option>
          </select>
        </section>

        <!-- AI Coach voice -->
        <section class="card">
          <h2>{{ $t('settings.ai.title') }}</h2>
          <p class="hint" style="margin: 0 0 12px">{{ $t('settings.ai.intro') }}</p>
          <label>{{ $t('settings.ai.tone') }}</label>
          <div class="seg">
            <button
              type="button"
              class="seg-btn"
              :class="{ active: form.ai_tone === 'formal' }"
              @click="form.ai_tone = 'formal'"
            >
              {{ $t('settings.ai.tone.formal') }}
            </button>
            <button
              type="button"
              class="seg-btn"
              :class="{ active: form.ai_tone === 'casual' }"
              @click="form.ai_tone = 'casual'"
            >
              {{ $t('settings.ai.tone.casual') }}
            </button>
          </div>

          <label style="display: block; margin-top: 14px">{{ $t('settings.ai.persona') }}</label>
          <textarea
            v-model="form.ai_persona"
            rows="3"
            :maxlength="PERSONA_MAX"
            :placeholder="$t('settings.ai.persona_ph')"
          />
          <div class="persona-foot">
            <span class="hint">{{ $t('settings.ai.persona_hint') }}</span>
            <span class="counter">{{ form.ai_persona.length }}/{{ PERSONA_MAX }}</span>
          </div>
        </section>

        <!-- Privacy -->
        <section class="card">
          <h2>{{ $t('profile.privacy.title') }}</h2>
          <label>{{ $t('profile.privacy.visibility') }}</label>
          <select v-model="form.visibility">
            <option value="public">{{ $t('profile.privacy.visibility.public') }}</option>
            <option value="friends">{{ $t('profile.privacy.visibility.friends') }}</option>
            <option value="private">{{ $t('profile.privacy.visibility.private') }}</option>
          </select>
          <label class="check">
            <input v-model="form.show_favorite_food" type="checkbox" />
            <span>{{ $t('profile.privacy.show_favorite_food') }}</span>
          </label>
          <p class="hint">{{ $t('profile.privacy.show_favorite_food_hint') }}</p>
          <p class="hint">{{ $t('profile.privacy.note') }}</p>
        </section>

        <div class="save-row">
          <button type="submit" :disabled="saving">{{ saving ? $t('common.saving') : $t('common.save_changes') }}</button>
          <span v-if="success" class="ok">{{ success }}</span>
          <span v-if="error" class="error">{{ error }}</span>
        </div>
      </form>

      <!-- Meal reminders -->
      <section class="card reminders">
        <h2>{{ $t('reminders.title') }}</h2>
        <label class="rem-master">
          <input v-model="reminders.enabled" type="checkbox" />
          <span>{{ $t('reminders.enable') }}</span>
        </label>
        <p class="rem-hint">{{ $t('reminders.hint') }}</p>

        <div class="rem-grid" :class="{ off: !reminders.enabled }">
          <div v-for="m in REMINDER_MEALS" :key="m.key" class="rem-row">
            <label class="rem-meal">
              <input v-model="reminders.meals[m.key].enabled" type="checkbox" :disabled="!reminders.enabled" />
              <i class="fa-solid" :class="m.icon" />
              <span>{{ $t(m.labelKey) }}</span>
            </label>
            <input
              v-model="reminders.meals[m.key].time"
              type="time"
              class="rem-time"
              :disabled="!reminders.enabled || !reminders.meals[m.key].enabled"
            />
          </div>
        </div>

        <div class="rem-actions">
          <button type="button" :disabled="savingReminders" @click="saveReminders">
            {{ savingReminders ? $t('common.saving') : $t('reminders.save') }}
          </button>
          <span v-if="remindersMsg" class="ok">{{ remindersMsg }}</span>
        </div>
      </section>
    </template>
  </main>
</template>

<style scoped>
.settings-page { max-width: 720px; margin: 0 auto; padding: 8px 16px; }
.settings-page h1 { margin: 6px 0 16px; }
.muted { color: var(--muted); font-size: 13px; }
.hint { color: var(--muted); font-size: 12px; margin: 6px 0 0; }
.ok { color: var(--accent); font-size: 13px; }
.error { color: #f87171; font-size: 13px; margin: 0; }
.card { margin-top: 14px; }
.card:first-of-type { margin-top: 0; }
.card h2 { margin: 0 0 12px; font-size: 16px; }
label { font-size: 13px; color: var(--muted); }
textarea {
  width: 100%;
  padding: 10px 12px;
  border-radius: 8px;
  border: 1px solid var(--border);
  background: var(--inset);
  color: var(--text);
  font-size: 14px;
  font-family: inherit;
  resize: vertical;
}

/* Tone segmented toggle */
.seg { display: flex; gap: 8px; margin-top: 6px; }
.seg-btn {
  flex: 1;
  min-height: 42px;
  border: 1px solid var(--border);
  background: var(--inset);
  color: var(--muted);
  font-weight: 700;
  border-radius: 10px;
  cursor: pointer;
}
.seg-btn.active { background: var(--accent); color: var(--on-accent); border-color: transparent; }
.persona-foot { display: flex; justify-content: space-between; align-items: baseline; gap: 12px; }
.counter { color: var(--muted); font-size: 12px; flex: none; }

.check {
  display: flex; align-items: center; gap: 8px;
  min-height: 44px; margin-top: 12px; font-size: 14px; color: var(--text); cursor: pointer;
}
.check input { width: auto; margin: 0; }

.save-row { margin-top: 16px; display: flex; align-items: center; gap: 14px; }

.reminders { padding: 16px; }
.reminders h2 { font-size: 16px; margin: 0 0 12px; }
.rem-master { display: flex; align-items: center; gap: 8px; min-height: 44px; font-weight: 600; cursor: pointer; }
.rem-master input { width: auto; margin: 0; }
.rem-hint { color: var(--muted); font-size: 12px; margin: 0 0 10px; }
.rem-grid { display: flex; flex-direction: column; gap: 6px; }
.rem-grid.off { opacity: 0.5; }
.rem-row { display: flex; align-items: center; justify-content: space-between; gap: 12px; min-height: 44px; }
.rem-meal { display: flex; align-items: center; gap: 10px; cursor: pointer; }
.rem-meal input { width: auto; margin: 0; }
.rem-meal i { width: 18px; text-align: center; color: var(--muted); }
.rem-time { width: auto; flex: none; }
.rem-actions { display: flex; align-items: center; gap: 14px; margin-top: 14px; }
</style>
