<script setup>
import { reactive, ref } from 'vue';
import { useRouter, RouterLink } from 'vue-router';
import { api } from '../../lib/api.js';

const router = useRouter();
const saving = ref(false);
const error = ref('');
const form = reactive({
  first_name: '',
  last_name: '',
  user_name: '',
  email: '',
  password: '',
  confirm_password: '',
  role: 'regular',
});

async function submit() {
  saving.value = true;
  error.value = '';
  try {
    const detail = await api.post('/api/admin/users', { ...form });
    // Land on the new user's detail page (server returns the full detail object).
    router.push(`/admin/users/${detail.user.user_id}`);
  } catch (e) {
    error.value = e.message;
  } finally {
    saving.value = false;
  }
}
</script>

<template>
  <section class="create">
    <RouterLink to="/admin/users" class="back">&larr; {{ $t('admin.user.back') }}</RouterLink>
    <h1>{{ $t('admin.create.title') }}</h1>
    <p class="intro">{{ $t('admin.create.intro') }}</p>

    <p v-if="error" class="error">{{ error }}</p>

    <form class="card" @submit.prevent="submit">
      <div class="row2">
        <label>{{ $t('admin.user.first_name') }}<input v-model="form.first_name" autocomplete="off" /></label>
        <label>{{ $t('admin.user.last_name') }}<input v-model="form.last_name" autocomplete="off" /></label>
      </div>
      <label>{{ $t('admin.user.username') }}<input v-model="form.user_name" autocomplete="off" /></label>
      <label>{{ $t('admin.user.email') }}<input v-model="form.email" type="email" autocomplete="off" /></label>
      <div class="row2">
        <label>{{ $t('admin.create.password') }}<input v-model="form.password" type="password" autocomplete="new-password" /></label>
        <label>{{ $t('admin.create.confirm_password') }}<input v-model="form.confirm_password" type="password" autocomplete="new-password" /></label>
      </div>
      <p class="hint">{{ $t('admin.create.password_hint') }}</p>
      <label>{{ $t('admin.user.role') }}
        <select v-model="form.role">
          <option value="regular">{{ $t('admin.role.regular') }}</option>
          <option value="pt">{{ $t('admin.role.pt') }}</option>
          <option value="admin">{{ $t('admin.role.admin') }}</option>
        </select>
      </label>

      <div class="actions">
        <button type="submit" class="btn-primary" :disabled="saving">
          {{ saving ? $t('admin.create.creating') : $t('admin.create.submit') }}
        </button>
        <RouterLink to="/admin/users" class="btn-ghost">{{ $t('common.cancel') }}</RouterLink>
      </div>
    </form>
  </section>
</template>

<style scoped>
.create { max-width: 600px; }
.back { color: var(--accent); text-decoration: none; font-weight: 600; font-size: 0.9rem; }
h1 { margin: 14px 0 4px; font-size: 1.5rem; }
.intro { color: var(--muted); margin: 0 0 16px; font-size: 0.92rem; }
.error { color: #ef4444; margin: 8px 0; }
.card { background: var(--card); border: 1px solid var(--border); border-radius: 14px; padding: 18px 20px; }
.row2 { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; }
label { display: flex; flex-direction: column; gap: 5px; margin-bottom: 12px; font-size: 0.82rem; color: var(--muted); font-weight: 600; }
input, select { padding: 9px 12px; border-radius: 10px; border: 1px solid var(--border); background: var(--bg, #0f1115); color: var(--text); font: inherit; }
.hint { color: var(--muted); font-size: 0.8rem; margin: -4px 0 14px; }
.actions { display: flex; align-items: center; gap: 10px; margin-top: 4px; }
.btn-primary { font: inherit; font-weight: 700; cursor: pointer; padding: 10px 18px; border-radius: 10px; border: none; background: var(--accent); color: var(--on-accent); }
.btn-ghost { font: inherit; font-weight: 700; font-size: 0.9rem; text-decoration: none; padding: 10px 16px; border-radius: 10px; border: 1px solid var(--border); background: var(--card); color: var(--text); }
button:disabled { opacity: 0.5; cursor: default; }
@media (max-width: 520px) { .row2 { grid-template-columns: 1fr; } }
</style>
