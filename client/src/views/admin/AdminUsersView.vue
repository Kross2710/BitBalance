<script setup>
import { ref, onMounted, watch } from 'vue';
import { RouterLink } from 'vue-router';
import { api } from '../../lib/api.js';
import ConfirmDialog from '../../components/ConfirmDialog.vue';

const users = ref([]);
const total = ref(0);
const page = ref(1);
const pages = ref(1);
const q = ref('');
const role = ref('');
const status = ref('');
const loading = ref(true);
const error = ref('');
const busyId = ref(0);

async function load() {
  loading.value = true;
  try {
    const params = new URLSearchParams({ page: String(page.value) });
    if (q.value.trim()) params.set('q', q.value.trim());
    if (role.value) params.set('role', role.value);
    if (status.value) params.set('status', status.value);
    const d = await api.get(`/api/admin/users?${params}`);
    users.value = d.users;
    total.value = d.total;
    pages.value = d.pages;
    page.value = d.page;
    error.value = '';
  } catch (e) {
    error.value = e.message;
  } finally {
    loading.value = false;
  }
}
onMounted(load);

// Debounced text search; filter changes reload immediately. Both reset to page 1.
let searchTimer = null;
function onSearch() {
  clearTimeout(searchTimer);
  searchTimer = setTimeout(() => { page.value = 1; load(); }, 300);
}
watch([role, status], () => { page.value = 1; load(); });

function go(p) {
  if (p < 1 || p > pages.value || p === page.value) return;
  page.value = p;
  load();
}

async function quickAction(u, action) {
  busyId.value = u.user_id;
  try {
    await api.post(`/api/admin/users/${u.user_id}/${action}`, {});
    await load();
  } catch (e) {
    error.value = e.message;
  } finally {
    busyId.value = 0;
  }
}

// Ban goes through an in-app confirm dialog (no native popup — see DESIGN.md).
const confirmUser = ref(null);
function askBan(u) { confirmUser.value = u; }
async function confirmBan() {
  const u = confirmUser.value;
  if (!u) return;
  await quickAction(u, 'ban');
  confirmUser.value = null;
}

const fmtDate = (s) => (s ? String(s).slice(0, 10) : '—');
</script>

<template>
  <section class="users">
    <div class="page-head">
      <h1>{{ $t('admin.users.title') }}</h1>
      <RouterLink to="/admin/users/new" class="btn-new">{{ $t('admin.users.new') }}</RouterLink>
    </div>

    <div class="filters">
      <input v-model="q" :placeholder="$t('admin.users.search_ph')" class="search" @input="onSearch" />
      <select v-model="role">
        <option value="">{{ $t('admin.users.all_roles') }}</option>
        <option value="regular">{{ $t('admin.role.regular') }}</option>
        <option value="pt">{{ $t('admin.role.pt') }}</option>
        <option value="admin">{{ $t('admin.role.admin') }}</option>
      </select>
      <select v-model="status">
        <option value="">{{ $t('admin.users.all_statuses') }}</option>
        <option value="active">{{ $t('admin.status.active') }}</option>
        <option value="banned">{{ $t('admin.status.banned') }}</option>
        <option value="archived">{{ $t('admin.status.archived') }}</option>
      </select>
    </div>

    <p v-if="error" class="error">{{ error }}</p>

    <div class="table-wrap">
      <table>
        <thead>
          <tr>
            <th>{{ $t('admin.users.col_user') }}</th>
            <th>{{ $t('admin.users.col_email') }}</th>
            <th>{{ $t('admin.users.col_role') }}</th>
            <th>{{ $t('admin.users.col_status') }}</th>
            <th>{{ $t('admin.users.col_joined') }}</th>
            <th></th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="u in users" :key="u.user_id">
            <td :data-label="$t('admin.users.col_user')">
              <div class="u-name">{{ u.first_name || u.user_name }} {{ u.last_name }}</div>
              <div class="u-handle">@{{ u.user_name }}</div>
            </td>
            <td class="u-email" :data-label="$t('admin.users.col_email')">{{ u.email }}</td>
            <td :data-label="$t('admin.users.col_role')"><span class="badge" :class="'role-' + u.role">{{ $t('admin.role.' + u.role) }}</span></td>
            <td :data-label="$t('admin.users.col_status')"><span class="badge" :class="'st-' + u.status">{{ $t('admin.status.' + u.status) }}</span></td>
            <td class="u-date" :data-label="$t('admin.users.col_joined')">{{ fmtDate(u.created_at) }}</td>
            <td class="u-actions">
              <RouterLink :to="`/admin/users/${u.user_id}`" class="btn-link">{{ $t('admin.users.view') }}</RouterLink>
              <button v-if="u.status === 'active'" :disabled="busyId === u.user_id" class="btn-danger" @click="askBan(u)">{{ $t('admin.action.ban') }}</button>
              <button v-else-if="u.status === 'banned'" :disabled="busyId === u.user_id" class="btn-ghost" @click="quickAction(u, 'unban')">{{ $t('admin.action.unban') }}</button>
            </td>
          </tr>
          <tr v-if="!loading && !users.length">
            <td colspan="6" class="empty">{{ $t('admin.users.none') }}</td>
          </tr>
        </tbody>
      </table>
    </div>

    <div class="pager">
      <button :disabled="page <= 1" @click="go(page - 1)">{{ $t('admin.users.prev') }}</button>
      <span class="pageinfo">{{ $t('admin.users.page') }} {{ page }} / {{ pages }} · {{ total }}</span>
      <button :disabled="page >= pages" @click="go(page + 1)">{{ $t('admin.users.next') }}</button>
    </div>

    <ConfirmDialog
      :open="!!confirmUser"
      :title="$t('admin.users.confirm_ban_title')"
      :message="$t('admin.users.confirm_ban')"
      :confirm-label="$t('admin.action.ban')"
      :busy="busyId === confirmUser?.user_id"
      @confirm="confirmBan"
      @cancel="confirmUser = null"
    />
  </section>
</template>

<style scoped>
.users { max-width: 1000px; }
.page-head { display: flex; align-items: center; justify-content: space-between; gap: 12px; margin: 0 0 16px; }
h1 { margin: 0; font-size: 1.5rem; }
.btn-new {
  flex: none; font-weight: 700; font-size: 0.85rem; text-decoration: none;
  padding: 9px 16px; border-radius: 10px; background: var(--accent); color: var(--on-accent);
}
.filters { display: flex; gap: 10px; flex-wrap: wrap; margin-bottom: 16px; }
.search { flex: 1; min-width: 200px; }
.filters input, .filters select {
  padding: 9px 12px; border-radius: 10px; border: 1px solid var(--border);
  background: var(--card); color: var(--text); font: inherit;
}
.error { color: #ef4444; margin: 8px 0; }
.table-wrap { overflow-x: auto; border: 1px solid var(--border); border-radius: 14px; }
table { width: 100%; border-collapse: collapse; }
th, td { text-align: left; padding: 11px 14px; border-bottom: 1px solid var(--border); vertical-align: middle; }
th { font-size: 0.72rem; text-transform: uppercase; letter-spacing: 0.04em; color: var(--muted); }
tr:last-child td { border-bottom: none; }
.u-name { font-weight: 600; }
.u-handle { color: var(--muted); font-size: 0.82rem; }
.u-email { color: var(--muted); font-size: 0.88rem; word-break: break-all; }
.u-date { color: var(--muted); font-size: 0.85rem; white-space: nowrap; }
.u-actions { display: flex; gap: 8px; align-items: center; white-space: nowrap; }
.empty { text-align: center; color: var(--muted); padding: 28px; }
.badge {
  display: inline-block; font-size: 0.72rem; font-weight: 700; padding: 3px 9px;
  border-radius: 999px; background: var(--border); color: var(--text); text-transform: capitalize;
}
.badge.role-admin { background: #7c3aed33; color: #c4b5fd; }
.badge.role-pt { background: #1cb0f633; color: #7dd3fc; }
.badge.st-active { background: #16a34a33; color: #86efac; }
.badge.st-banned { background: #ef444433; color: #fca5a5; }
.badge.st-archived { background: #f59e0b33; color: #fcd34d; }
.btn-link { color: var(--accent); text-decoration: none; font-weight: 600; font-size: 0.85rem; }
.btn-danger, .btn-ghost {
  font: inherit; font-size: 0.8rem; font-weight: 700; cursor: pointer;
  padding: 6px 12px; border-radius: 8px; border: 1px solid var(--border);
}
.btn-danger { background: #ef444422; color: #fca5a5; border-color: #ef444455; }
.btn-ghost { background: var(--card); color: var(--text); }
button:disabled { opacity: 0.5; cursor: default; }
.pager { display: flex; align-items: center; gap: 14px; justify-content: center; margin-top: 18px; }
.pager button { font: inherit; padding: 8px 16px; border-radius: 10px; border: 1px solid var(--border); background: var(--card); color: var(--text); cursor: pointer; }
.pageinfo { color: var(--muted); font-size: 0.88rem; }

/* Mobile: stack each row into a card (label : value) instead of a wide table. */
@media (max-width: 640px) {
  .table-wrap { overflow-x: visible; border: none; }
  table, tbody, tr, td { display: block; width: 100%; }
  thead { display: none; }
  tr {
    background: var(--card); border: 1px solid var(--border); border-radius: 14px;
    padding: 8px 14px; margin-bottom: 12px;
  }
  td { border: none; padding: 7px 0; display: flex; gap: 12px; align-items: center; justify-content: space-between; }
  td::before {
    content: attr(data-label); flex: none; color: var(--muted);
    font-size: 0.7rem; text-transform: uppercase; letter-spacing: 0.04em; font-weight: 700;
  }
  td.u-email { word-break: break-all; text-align: right; }
  td.u-actions { justify-content: flex-end; }
  td.u-actions::before, td.empty::before { display: none; }
  td.empty { justify-content: center; }
}
</style>
