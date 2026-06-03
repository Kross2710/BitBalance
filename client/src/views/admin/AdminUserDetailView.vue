<script setup>
import { ref, reactive, onMounted } from 'vue';
import { useRoute, RouterLink } from 'vue-router';
import { api } from '../../lib/api.js';
import { t } from '../../i18n/index.js';
import ConfirmDialog from '../../components/ConfirmDialog.vue';

const route = useRoute();
const id = Number(route.params.id);
const detail = ref(null);
const loading = ref(true);
const error = ref('');
const saving = ref(false);
const notice = ref('');
const form = reactive({ first_name: '', last_name: '', user_name: '', email: '', role: 'regular', status: 'active' });

function syncForm(u) {
  form.first_name = u.first_name || '';
  form.last_name = u.last_name || '';
  form.user_name = u.user_name || '';
  form.email = u.email || '';
  form.role = u.role || 'regular';
  form.status = u.status || 'active';
}

async function load() {
  loading.value = true;
  try {
    detail.value = await api.get(`/api/admin/users/${id}`);
    syncForm(detail.value.user);
    error.value = '';
  } catch (e) {
    error.value = e.message;
  } finally {
    loading.value = false;
  }
}
onMounted(load);

async function save() {
  saving.value = true; notice.value = ''; error.value = '';
  try {
    detail.value = await api.patch(`/api/admin/users/${id}`, { ...form });
    syncForm(detail.value.user);
    notice.value = t('admin.user.saved');
  } catch (e) {
    error.value = e.message;
  } finally {
    saving.value = false;
  }
}

async function act(action) {
  saving.value = true; notice.value = ''; error.value = '';
  try {
    detail.value = await api.post(`/api/admin/users/${id}/${action}`, {});
    syncForm(detail.value.user);
    notice.value = t('admin.user.done');
  } catch (e) {
    error.value = e.message;
  } finally {
    saving.value = false;
  }
}

// Ban is confirmed via an in-app dialog instead of window.confirm (see DESIGN.md).
const confirmBanOpen = ref(false);
async function confirmBan() {
  await act('ban');
  if (!error.value) confirmBanOpen.value = false;
}

// Admin-set password (account recovery). Confirmed via dialog before applying.
const pw = reactive({ password: '', confirm_password: '' });
const resetOpen = ref(false);
async function confirmReset() {
  saving.value = true; notice.value = ''; error.value = '';
  try {
    detail.value = await api.post(`/api/admin/users/${id}/password`, { ...pw });
    syncForm(detail.value.user);
    pw.password = '';
    pw.confirm_password = '';
    notice.value = t('admin.user.password_set');
    resetOpen.value = false;
  } catch (e) {
    error.value = e.message;
  } finally {
    saving.value = false;
  }
}

const fmt = (s) => (s ? String(s).replace('T', ' ').slice(0, 16) : '—');
</script>

<template>
  <section class="detail">
    <RouterLink to="/admin/users" class="back">&larr; {{ $t('admin.user.back') }}</RouterLink>

    <p v-if="loading" class="muted">…</p>
    <p v-else-if="error && !detail" class="error">{{ error }}</p>

    <template v-else-if="detail">
      <header class="head">
        <div>
          <h1>{{ detail.user.first_name || detail.user.user_name }} {{ detail.user.last_name }}</h1>
          <div class="sub">@{{ detail.user.user_name }} · {{ detail.user.email }}</div>
        </div>
        <div class="hbadges">
          <span class="badge" :class="'role-' + detail.user.role">{{ $t('admin.role.' + detail.user.role) }}</span>
          <span class="badge" :class="'st-' + detail.user.status">{{ $t('admin.status.' + detail.user.status) }}</span>
        </div>
      </header>

      <p v-if="notice" class="notice">{{ notice }}</p>
      <p v-if="error" class="error">{{ error }}</p>

      <div class="grid">
        <div class="card">
          <h2>{{ $t('admin.user.edit') }}</h2>
          <label>{{ $t('admin.user.first_name') }}<input v-model="form.first_name" /></label>
          <label>{{ $t('admin.user.last_name') }}<input v-model="form.last_name" /></label>
          <label>{{ $t('admin.user.username') }}<input v-model="form.user_name" /></label>
          <label>{{ $t('admin.user.email') }}<input v-model="form.email" type="email" /></label>
          <label>{{ $t('admin.user.role') }}
            <select v-model="form.role">
              <option value="regular">{{ $t('admin.role.regular') }}</option>
              <option value="pt">{{ $t('admin.role.pt') }}</option>
              <option value="admin">{{ $t('admin.role.admin') }}</option>
            </select>
          </label>
          <label>{{ $t('admin.user.status') }}
            <select v-model="form.status">
              <option value="active">{{ $t('admin.status.active') }}</option>
              <option value="banned">{{ $t('admin.status.banned') }}</option>
              <option value="archived">{{ $t('admin.status.archived') }}</option>
            </select>
          </label>
          <button class="btn-primary" :disabled="saving" @click="save">
            {{ saving ? $t('admin.action.saving') : $t('admin.action.save') }}
          </button>
        </div>

        <div class="card">
          <h2>{{ $t('admin.user.access') }}</h2>
          <dl class="kv">
            <dt>{{ $t('admin.user.failed_attempts') }}</dt><dd>{{ detail.user.failed_attempts }}</dd>
            <dt>{{ $t('admin.user.locked_until') }}</dt>
            <dd>{{ detail.user.locked_until ? fmt(detail.user.locked_until) : $t('admin.user.not_locked') }}</dd>
            <dt>{{ $t('admin.user.joined') }}</dt><dd>{{ fmt(detail.user.created_at) }}</dd>
            <dt>{{ $t('admin.user.last_login') }}</dt>
            <dd>{{ detail.user.last_login ? fmt(detail.user.last_login) : $t('admin.user.never') }}</dd>
          </dl>
          <div class="actions">
            <button v-if="detail.user.status !== 'banned'" class="btn-danger" :disabled="saving" @click="confirmBanOpen = true">{{ $t('admin.action.ban') }}</button>
            <button v-if="detail.user.status === 'banned'" class="btn-ghost" :disabled="saving" @click="act('unban')">{{ $t('admin.action.unban') }}</button>
            <button v-if="detail.user.status !== 'archived'" class="btn-ghost" :disabled="saving" @click="act('archive')">{{ $t('admin.action.archive') }}</button>
            <button v-if="detail.user.status === 'archived'" class="btn-ghost" :disabled="saving" @click="act('restore')">{{ $t('admin.action.restore') }}</button>
            <button class="btn-ghost" :disabled="saving" @click="act('unlock')">{{ $t('admin.action.unlock') }}</button>
          </div>
        </div>
      </div>

      <div class="card">
        <h2>{{ $t('admin.user.reset_password') }}</h2>
        <div class="row2">
          <label>{{ $t('admin.user.new_password') }}<input v-model="pw.password" type="password" autocomplete="new-password" /></label>
          <label>{{ $t('admin.user.confirm_password') }}<input v-model="pw.confirm_password" type="password" autocomplete="new-password" /></label>
        </div>
        <p class="hint">{{ $t('admin.create.password_hint') }}</p>
        <button class="btn-reset" :disabled="saving || !pw.password || !pw.confirm_password" @click="resetOpen = true">
          {{ $t('admin.user.set_password') }}
        </button>
      </div>

      <div class="card">
        <h2>{{ $t('admin.user.login_attempts') }}</h2>
        <table v-if="detail.login_attempts.length" class="mini">
          <thead><tr><th>{{ $t('admin.user.when') }}</th><th>IP</th><th>{{ $t('admin.user.result') }}</th></tr></thead>
          <tbody>
            <tr v-for="(a, i) in detail.login_attempts" :key="i">
              <td>{{ fmt(a.attempted_at) }}</td>
              <td>{{ a.ip_address }}</td>
              <td :class="a.success ? 'ok' : 'bad'">{{ a.success ? $t('admin.user.success') : $t('admin.user.failed') }}</td>
            </tr>
          </tbody>
        </table>
        <p v-else class="muted">{{ $t('admin.user.none_data') }}</p>
      </div>

      <div class="card">
        <h2>{{ $t('admin.user.audit') }}</h2>
        <table v-if="detail.admin_actions.length" class="mini">
          <thead><tr><th>{{ $t('admin.user.when') }}</th><th>{{ $t('admin.user.action') }}</th><th>{{ $t('admin.user.by') }}</th></tr></thead>
          <tbody>
            <tr v-for="(a, i) in detail.admin_actions" :key="i">
              <td>{{ fmt(a.created_at) }}</td>
              <td>{{ a.action_type }}<span v-if="a.description" class="desc"> — {{ a.description }}</span></td>
              <td>{{ a.actor_name ? '@' + a.actor_name : '—' }}</td>
            </tr>
          </tbody>
        </table>
        <p v-else class="muted">{{ $t('admin.user.none_data') }}</p>
      </div>
    </template>

    <ConfirmDialog
      :open="confirmBanOpen"
      :title="$t('admin.users.confirm_ban_title')"
      :message="$t('admin.users.confirm_ban')"
      :confirm-label="$t('admin.action.ban')"
      :busy="saving"
      @confirm="confirmBan"
      @cancel="confirmBanOpen = false"
    />
    <ConfirmDialog
      :open="resetOpen"
      :title="$t('admin.user.confirm_reset_title')"
      :message="$t('admin.user.confirm_reset_message')"
      :confirm-label="$t('admin.user.set_password')"
      :danger="false"
      :busy="saving"
      @confirm="confirmReset"
      @cancel="resetOpen = false"
    />
  </section>
</template>

<style scoped>
.detail { max-width: 920px; }
.back { color: var(--accent); text-decoration: none; font-weight: 600; font-size: 0.9rem; }
.head { display: flex; justify-content: space-between; align-items: flex-start; gap: 16px; margin: 14px 0 8px; flex-wrap: wrap; }
.head h1 { margin: 0 0 2px; font-size: 1.4rem; }
.sub { color: var(--muted); font-size: 0.9rem; word-break: break-all; }
.hbadges { display: flex; gap: 8px; flex: none; }
.muted { color: var(--muted); }
.error { color: #ef4444; margin: 8px 0; }
.notice { color: #86efac; margin: 8px 0; }
.grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 16px; margin: 12px 0; }
.card { background: var(--card); border: 1px solid var(--border); border-radius: 14px; padding: 18px 20px; margin-bottom: 16px; }
.card h2 { margin: 0 0 14px; font-size: 1.05rem; }
label { display: flex; flex-direction: column; gap: 5px; margin-bottom: 12px; font-size: 0.82rem; color: var(--muted); font-weight: 600; }
input, select { padding: 9px 12px; border-radius: 10px; border: 1px solid var(--border); background: var(--bg, #0f1115); color: var(--text); font: inherit; }
.btn-primary { font: inherit; font-weight: 700; cursor: pointer; padding: 10px 18px; border-radius: 10px; border: none; background: var(--accent); color: var(--on-accent); }
.row2 { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; }
.hint { color: var(--muted); font-size: 0.8rem; margin: -4px 0 12px; }
.btn-reset { font: inherit; font-weight: 700; cursor: pointer; padding: 9px 16px; border-radius: 10px; border: 1px solid var(--border); background: var(--surface-2); color: var(--text); }
@media (max-width: 520px) { .row2 { grid-template-columns: 1fr; } }
.kv { display: grid; grid-template-columns: auto 1fr; gap: 8px 16px; margin: 0 0 16px; }
.kv dt { color: var(--muted); font-size: 0.85rem; }
.kv dd { margin: 0; font-weight: 600; font-size: 0.9rem; }
.actions { display: flex; gap: 8px; flex-wrap: wrap; }
.actions button { font: inherit; font-size: 0.82rem; font-weight: 700; cursor: pointer; padding: 8px 14px; border-radius: 9px; border: 1px solid var(--border); background: var(--card); color: var(--text); }
.actions .btn-danger { background: #ef444422; color: #fca5a5; border-color: #ef444455; }
button:disabled { opacity: 0.5; cursor: default; }
.mini { width: 100%; border-collapse: collapse; }
.mini th, .mini td { text-align: left; padding: 8px 10px; border-bottom: 1px solid var(--border); font-size: 0.85rem; }
.mini th { font-size: 0.7rem; text-transform: uppercase; letter-spacing: 0.04em; color: var(--muted); }
.mini .ok { color: #86efac; }
.mini .bad { color: #fca5a5; }
.desc { color: var(--muted); }
.badge { display: inline-block; font-size: 0.72rem; font-weight: 700; padding: 3px 9px; border-radius: 999px; background: var(--border); color: var(--text); text-transform: capitalize; }
.badge.role-admin { background: #7c3aed33; color: #c4b5fd; }
.badge.role-pt { background: #1cb0f633; color: #7dd3fc; }
.badge.st-active { background: #16a34a33; color: #86efac; }
.badge.st-banned { background: #ef444433; color: #fca5a5; }
.badge.st-archived { background: #f59e0b33; color: #fcd34d; }
</style>
