<script setup>
import { ref, onMounted, watch } from 'vue';
import { api } from '../../lib/api.js';
import { t } from '../../i18n/index.js';
import ConfirmDialog from '../../components/ConfirmDialog.vue';

const logs = ref([]);
const actionTypes = ref([]);
const total = ref(0);
const page = ref(1);
const pages = ref(1);
const q = ref('');
const action = ref('');
const loading = ref(true);
const error = ref('');
const pruneDays = ref(30);
const pruning = ref(false);
const notice = ref('');
// Prune confirm dialog + preview of how many rows would be deleted.
const confirmOpen = ref(false);
const previewLoading = ref(false);
const previewCount = ref(0);
const previewDays = ref(0);

async function load() {
  loading.value = true;
  try {
    const params = new URLSearchParams({ page: String(page.value) });
    if (q.value.trim()) params.set('q', q.value.trim());
    if (action.value) params.set('action', action.value);
    const d = await api.get(`/api/admin/logs?${params}`);
    logs.value = d.logs;
    actionTypes.value = d.action_types;
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

let searchTimer = null;
function onSearch() {
  clearTimeout(searchTimer);
  searchTimer = setTimeout(() => { page.value = 1; load(); }, 300);
}
watch(action, () => { page.value = 1; load(); });

function go(p) {
  if (p < 1 || p > pages.value || p === page.value) return;
  page.value = p;
  load();
}

// Open the confirm dialog and fetch the blast radius (rows that would be deleted)
// so the admin sees the count before committing the irreversible delete.
async function askPrune() {
  const days = Number(pruneDays.value);
  if (!Number.isFinite(days) || days < 1 || days > 365) {
    error.value = t('admin.logs.bad_days');
    return;
  }
  error.value = '';
  notice.value = '';
  previewDays.value = days;
  previewCount.value = 0;
  previewLoading.value = true;
  confirmOpen.value = true;
  try {
    const d = await api.get(`/api/admin/logs/prune-preview?days=${days}`);
    previewCount.value = d.count;
  } catch (e) {
    error.value = e.message;
    confirmOpen.value = false;
  } finally {
    previewLoading.value = false;
  }
}

async function doPrune() {
  pruning.value = true; notice.value = ''; error.value = '';
  try {
    const d = await api.post('/api/admin/logs/prune', { days: previewDays.value });
    notice.value = `${t('admin.logs.pruned')} ${d.deleted}`;
    confirmOpen.value = false;
    page.value = 1;
    await load();
  } catch (e) {
    error.value = e.message;
  } finally {
    pruning.value = false;
  }
}

const fmt = (s) => (s ? String(s).replace('T', ' ').slice(0, 16) : '—');
</script>

<template>
  <section class="logs">
    <h1>{{ $t('admin.logs.title') }}</h1>

    <div class="prune-bar">
      <label>{{ $t('admin.logs.prune_label') }}
        <input v-model.number="pruneDays" type="number" min="1" max="365" class="days" />
        {{ $t('admin.logs.days') }}
      </label>
      <button class="btn-danger" :disabled="pruning" @click="askPrune">
        {{ pruning ? $t('admin.logs.pruning') : $t('admin.logs.prune') }}
      </button>
      <span v-if="notice" class="notice">{{ notice }}</span>
    </div>

    <div class="filters">
      <input v-model="q" :placeholder="$t('admin.logs.search_ph')" class="search" @input="onSearch" />
      <select v-model="action">
        <option value="">{{ $t('admin.logs.all_actions') }}</option>
        <option v-for="ty in actionTypes" :key="ty" :value="ty">{{ ty }}</option>
      </select>
    </div>

    <p v-if="error" class="error">{{ error }}</p>

    <div class="table-wrap">
      <table>
        <thead>
          <tr>
            <th>{{ $t('admin.logs.col_when') }}</th>
            <th>{{ $t('admin.logs.col_actor') }}</th>
            <th>{{ $t('admin.logs.col_action') }}</th>
            <th>{{ $t('admin.logs.col_target') }}</th>
            <th>{{ $t('admin.logs.col_desc') }}</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="l in logs" :key="l.log_id">
            <td class="when" :data-label="$t('admin.logs.col_when')">{{ fmt(l.created_at) }}</td>
            <td :data-label="$t('admin.logs.col_actor')">{{ l.actor_name ? '@' + l.actor_name : '—' }}</td>
            <td :data-label="$t('admin.logs.col_action')"><code>{{ l.action_type }}</code></td>
            <td class="target" :data-label="$t('admin.logs.col_target')">{{ l.target_table || '—' }}<span v-if="l.target_id" class="tid">#{{ l.target_id }}</span></td>
            <td class="desc" :data-label="$t('admin.logs.col_desc')">{{ l.description || '—' }}</td>
          </tr>
          <tr v-if="!loading && !logs.length">
            <td colspan="5" class="empty">{{ $t('admin.logs.none') }}</td>
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
      :open="confirmOpen"
      :title="$t('admin.logs.confirm_prune_title')"
      :confirm-label="$t('admin.logs.prune')"
      :busy="pruning"
      :confirm-disabled="previewLoading || previewCount === 0"
      @confirm="doPrune"
      @cancel="confirmOpen = false"
    >
      <p v-if="previewLoading" class="prune-line muted">{{ $t('admin.logs.prune_preview_loading') }}</p>
      <p v-else-if="previewCount === 0" class="prune-line">{{ $t('admin.logs.prune_preview_none', { days: previewDays }) }}</p>
      <p v-else class="prune-line">{{ $t('admin.logs.prune_preview', { n: previewCount, days: previewDays }) }}</p>
    </ConfirmDialog>
  </section>
</template>

<style scoped>
.logs { max-width: 1000px; }
h1 { margin: 0 0 16px; font-size: 1.5rem; }
.prune-bar {
  display: flex; align-items: center; gap: 12px; flex-wrap: wrap;
  background: var(--card); border: 1px solid var(--border); border-radius: 12px;
  padding: 12px 16px; margin-bottom: 16px;
}
.prune-bar label { display: flex; align-items: center; gap: 8px; font-size: 0.88rem; color: var(--muted); }
.days { width: 64px; padding: 7px 10px; border-radius: 8px; border: 1px solid var(--border); background: var(--card); color: var(--text); font: inherit; }
.filters { display: flex; gap: 10px; flex-wrap: wrap; margin-bottom: 16px; }
.search { flex: 1; min-width: 200px; }
.filters input, .filters select { padding: 9px 12px; border-radius: 10px; border: 1px solid var(--border); background: var(--card); color: var(--text); font: inherit; }
.error { color: #ef4444; margin: 8px 0; }
.notice { color: #86efac; font-size: 0.88rem; }
.table-wrap { overflow-x: auto; border: 1px solid var(--border); border-radius: 14px; }
table { width: 100%; border-collapse: collapse; }
th, td { text-align: left; padding: 10px 14px; border-bottom: 1px solid var(--border); vertical-align: top; font-size: 0.86rem; }
th { font-size: 0.7rem; text-transform: uppercase; letter-spacing: 0.04em; color: var(--muted); }
tr:last-child td { border-bottom: none; }
.when { white-space: nowrap; color: var(--muted); }
code { font-size: 0.82rem; background: var(--border); padding: 2px 6px; border-radius: 6px; }
.target { color: var(--muted); white-space: nowrap; }
.tid { opacity: 0.7; }
.desc { color: var(--text); }
.empty { text-align: center; color: var(--muted); padding: 28px; }
.btn-danger { font: inherit; font-size: 0.82rem; font-weight: 700; cursor: pointer; padding: 8px 14px; border-radius: 9px; border: 1px solid #ef444455; background: #ef444422; color: #fca5a5; }
button:disabled { opacity: 0.5; cursor: default; }
.pager { display: flex; align-items: center; gap: 14px; justify-content: center; margin-top: 18px; }
.pager button { font: inherit; padding: 8px 16px; border-radius: 10px; border: 1px solid var(--border); background: var(--card); color: var(--text); cursor: pointer; }
.pageinfo { color: var(--muted); font-size: 0.88rem; }
.prune-line { margin: 0; font-size: 0.9rem; line-height: 1.5; color: var(--text); }
.prune-line.muted { color: var(--muted); }

/* Mobile: stack each log row into a card (label : value) instead of a wide table. */
@media (max-width: 640px) {
  .table-wrap { overflow-x: visible; border: none; }
  table, tbody, tr, td { display: block; width: 100%; }
  thead { display: none; }
  tr {
    background: var(--card); border: 1px solid var(--border); border-radius: 14px;
    padding: 8px 14px; margin-bottom: 12px;
  }
  td { border: none; padding: 7px 0; display: flex; gap: 12px; align-items: baseline; justify-content: space-between; }
  td::before {
    content: attr(data-label); flex: none; color: var(--muted);
    font-size: 0.7rem; text-transform: uppercase; letter-spacing: 0.04em; font-weight: 700;
  }
  td.desc { text-align: right; }
  td.empty { justify-content: center; }
  td.empty::before { display: none; }
}
</style>
