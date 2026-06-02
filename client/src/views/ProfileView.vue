<script setup>
// Profile page — mirrors the editable fields of the legacy profile.php that the
// JSON API exposes: account details (name/handle/email), bio, theme, calorie
// goal, and physical info. Image upload + language are not part of the API yet.
import { ref, reactive, onMounted } from 'vue';
import { useRouter } from 'vue-router';
import { api } from '../lib/api.js';
import { useAuthStore } from '../stores/auth.js';

const auth = useAuthStore();
const router = useRouter();

async function onLogout() {
  await auth.logout();
  router.push({ name: 'login' });
}

const loading = ref(true);
const saving = ref(false);
const error = ref('');
const success = ref('');
const meta = reactive({ role: '', status: '', goalDate: null, image: null });

// Single flat form bound to the inputs; '' is fine for the optional numeric
// fields — the API treats empty as null.
const form = reactive({
  first_name: '',
  last_name: '',
  user_name: '',
  email: '',
  bio: '',
  theme_preference: 'system',
  calorie_goal: '',
  age: '',
  gender: '',
  weight: '',
  height: '',
});

function hydrate(data) {
  form.first_name = data.user.first_name ?? '';
  form.last_name = data.user.last_name ?? '';
  form.user_name = data.user.user_name ?? '';
  form.email = data.user.email ?? '';
  form.bio = data.bio ?? '';
  form.theme_preference = data.user.theme_preference ?? 'system';
  form.calorie_goal = data.goal?.calorie_goal ?? '';
  form.age = data.physical?.age ?? '';
  form.gender = data.physical?.gender ?? '';
  form.weight = data.physical?.weight ?? '';
  form.height = data.physical?.height ?? '';

  meta.role = data.user.role ?? '';
  meta.status = data.status ?? '';
  meta.goalDate = data.goal?.date_set ?? null;
  meta.image = data.user.profile_image ?? null;
}

onMounted(async () => {
  try {
    hydrate(await api.get('/api/profile'));
  } catch (e) {
    error.value = e.message;
  } finally {
    loading.value = false;
  }
});

async function onSubmit() {
  error.value = '';
  success.value = '';
  saving.value = true;
  try {
    const data = await api.post('/api/profile/update', { ...form });
    hydrate(data);
    // Keep the shared auth store (greeting, theme, etc.) in sync with the save.
    auth.user = data.user;
    success.value = 'Profile updated successfully.';
  } catch (e) {
    error.value = e.message;
  } finally {
    saving.value = false;
  }
}

const initials = () =>
  (form.first_name.charAt(0) + (form.last_name.charAt(0) || '')).toUpperCase() || 'B';
</script>

<template>
  <main style="max-width: 720px; margin: 0 auto; padding: 8px 16px">
    <h1 style="margin: 6px 0 16px">Profile</h1>

    <p v-if="loading" class="muted">Loading…</p>

    <form v-else @submit.prevent="onSubmit">
      <!-- Identity header -->
      <section class="card" style="display: flex; align-items: center; gap: 16px">
        <div class="avatar">
          <img v-if="meta.image" :src="meta.image" alt="Profile image" />
          <span v-else>{{ initials() }}</span>
        </div>
        <div>
          <strong style="font-size: 18px">{{ form.user_name || '—' }}</strong>
          <p class="muted" style="margin: 4px 0 0; font-size: 13px">
            <span style="text-transform: capitalize">{{ meta.role || 'regular' }}</span>
            <span v-if="meta.status"> · {{ meta.status }}</span>
          </p>
        </div>
      </section>

      <!-- Account -->
      <section class="card" style="margin-top: 14px">
        <h2 style="margin: 0 0 12px; font-size: 16px">Account</h2>
        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px">
          <div><label>First name</label><input v-model="form.first_name" required /></div>
          <div><label>Last name</label><input v-model="form.last_name" required /></div>
        </div>
        <label style="display: block; margin-top: 12px">Username</label>
        <input v-model="form.user_name" required />
        <p class="hint">3-30 characters: letters, numbers, and . # - _</p>
        <label style="display: block; margin-top: 12px">Email</label>
        <input v-model="form.email" type="email" required />
        <label style="display: block; margin-top: 12px">Bio</label>
        <textarea v-model="form.bio" rows="3" />
      </section>

      <!-- Preferences -->
      <section class="card" style="margin-top: 14px">
        <h2 style="margin: 0 0 12px; font-size: 16px">Preferences</h2>
        <label>Theme</label>
        <select v-model="form.theme_preference">
          <option value="system">System</option>
          <option value="light">Light</option>
          <option value="dark">Dark</option>
        </select>
      </section>

      <!-- Goal + physical -->
      <section class="card" style="margin-top: 14px">
        <h2 style="margin: 0 0 12px; font-size: 16px">Goal &amp; body</h2>
        <label>Daily calorie goal (kcal)</label>
        <input v-model="form.calorie_goal" type="number" min="800" max="10000" />
        <p v-if="meta.goalDate" class="hint">Last set {{ meta.goalDate }}</p>

        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px; margin-top: 12px">
          <div>
            <label>Age</label>
            <input v-model="form.age" type="number" min="1" max="130" />
          </div>
          <div>
            <label>Gender</label>
            <select v-model="form.gender">
              <option value="">—</option>
              <option value="male">Male</option>
              <option value="female">Female</option>
              <option value="other">Other</option>
            </select>
          </div>
          <div>
            <label>Weight (kg)</label>
            <input v-model="form.weight" type="number" step="0.1" min="1" max="999" />
          </div>
          <div>
            <label>Height (cm)</label>
            <input v-model="form.height" type="number" step="0.1" min="1" max="300" />
          </div>
        </div>
      </section>

      <div style="margin-top: 16px; display: flex; align-items: center; gap: 14px">
        <button type="submit" :disabled="saving">{{ saving ? 'Saving…' : 'Save changes' }}</button>
        <span v-if="success" class="ok">{{ success }}</span>
        <span v-if="error" class="error" style="margin: 0">{{ error }}</span>
      </div>
    </form>

    <!-- Account session: logout lives here (the topbar's logout is being retired). -->
    <section v-if="!loading" class="card logout-card">
      <button type="button" class="logout-btn" @click="onLogout">
        <i class="fa-solid fa-right-from-bracket" /> Log out
      </button>
    </section>
  </main>
</template>

<style scoped>
.muted { color: var(--muted); font-size: 13px; }
.hint { color: var(--muted); font-size: 12px; margin: 6px 0 0; }
.ok { color: var(--accent); font-size: 13px; }
label { font-size: 13px; color: var(--muted); }
textarea {
  width: 100%;
  padding: 10px 12px;
  border-radius: 8px;
  border: 1px solid var(--border);
  background: #12151b;
  color: var(--text);
  font-size: 14px;
  font-family: inherit;
  resize: vertical;
}
.avatar {
  flex: none;
  width: 56px;
  height: 56px;
  border-radius: 50%;
  background: var(--accent);
  color: #04210f;
  font-weight: 800;
  font-size: 20px;
  display: grid;
  place-items: center;
  overflow: hidden;
}
.avatar img { width: 100%; height: 100%; object-fit: cover; }

.logout-card { margin-top: 16px; padding: 16px; }
.logout-btn {
  width: 100%;
  min-height: 48px;
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 8px;
  background: #2a2e37;
  color: #f87171;
  font-weight: 700;
}
</style>
