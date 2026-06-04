<script setup>
// Profile page — mirrors the editable fields of the legacy profile.php that the
// JSON API exposes: account details (name/handle/email), bio, theme, calorie
// goal, and physical info. Image upload + language are not part of the API yet.
import { ref, reactive, onMounted } from 'vue';
import { useRouter } from 'vue-router';
import { api, browserTz } from '../lib/api.js';
import AvatarCropper from '../components/AvatarCropper.vue';
import { useAuthStore } from '../stores/auth.js';
import { t } from '../i18n/index.js';

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
    // Keep the shared auth store (greeting, avatar, etc.) in sync with the save.
    auth.user = data.user;
    success.value = t('profile.updated');
  } catch (e) {
    error.value = e.message;
  } finally {
    saving.value = false;
  }
}

// ---- Avatar upload ----
// Picking a file opens the square cropper (Facebook-style reposition + zoom); the
// cropper hands back an already-cropped, compressed WebP that we upload. Cropping
// to the avatar square keeps stored files tiny.
const avatarInput = ref(null);
const avatarBusy = ref(false);
const cropOpen = ref(false);
const pickedFile = ref(null);

function onAvatarPicked(e) {
  const file = e.target.files?.[0];
  e.target.value = ''; // allow re-picking the same file
  if (!file) return;
  if (!file.type?.startsWith('image/')) {
    error.value = t('profile.avatar.upload_failed');
    return;
  }
  error.value = '';
  success.value = '';
  pickedFile.value = file;
  cropOpen.value = true;
}

async function onCropConfirm(cropped) {
  avatarBusy.value = true;
  try {
    const fd = new FormData();
    fd.append('image', cropped);
    const res = await fetch('/api/profile/avatar', {
      method: 'POST',
      credentials: 'include',
      headers: { 'X-Requested-With': 'XMLHttpRequest', 'X-Timezone': browserTz() },
      body: fd,
    });
    const json = await res.json();
    if (!json.ok) throw new Error(json.message || t('profile.avatar.upload_failed'));
    meta.image = json.data.profile_image;
    if (auth.user) auth.user.profile_image = json.data.profile_image; // topbar avatar updates
    success.value = t('profile.updated');
    cropOpen.value = false;
    pickedFile.value = null;
  } catch (err) {
    error.value = err.message;
    cropOpen.value = false;
  } finally {
    avatarBusy.value = false;
  }
}

const initials = () =>
  (form.first_name.charAt(0) + (form.last_name.charAt(0) || '')).toUpperCase() || 'B';
</script>

<template>
  <main style="max-width: 720px; margin: 0 auto; padding: 8px 16px">
    <h1 style="margin: 6px 0 16px">{{ $t('profile.title') }}</h1>

    <p v-if="loading" class="muted">{{ $t('common.loading') }}</p>

    <form v-else @submit.prevent="onSubmit">
      <!-- Identity header -->
      <section class="card" style="display: flex; align-items: center; gap: 16px">
        <div class="avatar avatar-edit">
          <img v-if="meta.image" :src="meta.image" :alt="$t('profile.avatar.alt')" />
          <span v-else>{{ initials() }}</span>
          <button
            type="button"
            class="avatar-btn"
            :disabled="avatarBusy"
            :aria-label="$t('profile.avatar.change')"
            @click="avatarInput?.click()"
          >
            <i class="fa-solid" :class="avatarBusy ? 'fa-spinner fa-spin' : 'fa-camera'" />
          </button>
          <input ref="avatarInput" type="file" accept="image/*" hidden @change="onAvatarPicked" />
        </div>
        <AvatarCropper
          :open="cropOpen"
          :file="pickedFile"
          :busy="avatarBusy"
          @confirm="onCropConfirm"
          @cancel="cropOpen = false; pickedFile = null"
        />
        <div>
          <strong style="font-size: 18px">{{ form.user_name || '—' }}</strong>
          <p class="muted" style="margin: 4px 0 0; font-size: 13px">
            <span style="text-transform: capitalize">{{ $t(meta.role === 'pt' ? 'profile.role.pt' : 'profile.role.regular') }}</span>
            <span v-if="meta.status"> · {{ meta.status }}</span>
          </p>
        </div>
      </section>

      <!-- Account -->
      <section class="card" style="margin-top: 14px">
        <h2 style="margin: 0 0 12px; font-size: 16px">{{ $t('profile.account') }}</h2>
        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px">
          <div><label>{{ $t('profile.field.first_name') }}</label><input v-model="form.first_name" required /></div>
          <div><label>{{ $t('profile.field.last_name') }}</label><input v-model="form.last_name" required /></div>
        </div>
        <label style="display: block; margin-top: 12px">{{ $t('profile.field.username') }}</label>
        <input v-model="form.user_name" required />
        <p class="hint">{{ $t('profile.field.username_hint') }}</p>
        <label style="display: block; margin-top: 12px">{{ $t('profile.field.email') }}</label>
        <input v-model="form.email" type="email" required />
        <label style="display: block; margin-top: 12px">{{ $t('profile.field.bio') }}</label>
        <textarea v-model="form.bio" rows="3" />
      </section>

      <!-- Goal + physical -->
      <section class="card" style="margin-top: 14px">
        <h2 style="margin: 0 0 12px; font-size: 16px">{{ $t('profile.goal_body.title') }}</h2>
        <label>{{ $t('profile.goal.calorie') }}</label>
        <input v-model="form.calorie_goal" type="number" min="800" max="10000" />
        <p v-if="meta.goalDate" class="hint">{{ $t('profile.goal.last_set', { date: meta.goalDate }) }}</p>

        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px; margin-top: 12px">
          <div>
            <label>{{ $t('profile.body.age') }}</label>
            <input v-model="form.age" type="number" min="1" max="130" />
          </div>
          <div>
            <label>{{ $t('profile.body.gender') }}</label>
            <select v-model="form.gender">
              <option value="">{{ $t('profile.body.gender.none') }}</option>
              <option value="male">{{ $t('profile.body.gender.male') }}</option>
              <option value="female">{{ $t('profile.body.gender.female') }}</option>
              <option value="other">{{ $t('profile.body.gender.other') }}</option>
            </select>
          </div>
          <div>
            <label>{{ $t('profile.body.weight') }}</label>
            <input v-model="form.weight" type="number" step="0.1" min="1" max="999" />
          </div>
          <div>
            <label>{{ $t('profile.body.height') }}</label>
            <input v-model="form.height" type="number" step="0.1" min="1" max="300" />
          </div>
        </div>
      </section>

      <div style="margin-top: 16px; display: flex; align-items: center; gap: 14px">
        <button type="submit" :disabled="saving">{{ saving ? $t('common.saving') : $t('common.save_changes') }}</button>
        <span v-if="success" class="ok">{{ success }}</span>
        <span v-if="error" class="error" style="margin: 0">{{ error }}</span>
      </div>
    </form>

    <!-- Account session: logout lives here (the topbar's logout is being retired). -->
    <section v-if="!loading" class="card logout-card">
      <button type="button" class="logout-btn" @click="onLogout">
        <i class="fa-solid fa-right-from-bracket" /> {{ $t('profile.logout') }}
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
  background: var(--inset);
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
  color: var(--on-accent);
  font-weight: 800;
  font-size: 20px;
  display: grid;
  place-items: center;
  overflow: hidden;
}
.avatar img { width: 100%; height: 100%; object-fit: cover; }
.avatar-edit { position: relative; }
.avatar-btn {
  position: absolute;
  left: 0;
  right: 0;
  bottom: 0;
  height: 40%;
  min-height: 0;
  padding: 0;
  border: none;
  border-radius: 0;
  background: rgba(0, 0, 0, 0.55);
  color: #fff;
  font-size: 12px;
  display: grid;
  place-items: center;
  cursor: pointer;
}
.avatar-btn:disabled { cursor: default; opacity: 0.8; }

.reminders { margin-top: 16px; padding: 16px; }
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

.logout-card { margin-top: 16px; padding: 16px; }
.logout-btn {
  width: 100%;
  min-height: 48px;
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 8px;
  background: var(--surface-2);
  color: #f87171;
  font-weight: 700;
}
.check {
  display: flex;
  align-items: center;
  gap: 8px;
  min-height: 44px;
  margin-top: 12px;
  font-size: 14px;
  color: var(--text);
  cursor: pointer;
}
.check input { width: auto; margin: 0; }
</style>
