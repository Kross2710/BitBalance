<script setup>
import { ref, onMounted, watch } from 'vue';
import { RouterLink } from 'vue-router';
import { api } from '../../lib/api.js';
import { t } from '../../i18n/index.js';

const products = ref([]);
const stats = ref(null);
const sources = ref([]);
const total = ref(0);
const page = ref(1);
const pages = ref(1);
const q = ref('');
const source = ref('');
const sort = ref('popular');
const loading = ref(true);
const error = ref('');

async function load() {
  loading.value = true;
  try {
    const params = new URLSearchParams({ page: String(page.value), sort: sort.value });
    if (q.value.trim()) params.set('q', q.value.trim());
    if (source.value) params.set('source', source.value);
    const d = await api.get(`/api/admin/barcodes?${params}`);
    products.value = d.products;
    stats.value = d.stats;
    sources.value = d.sources;
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

// Debounced text search; filter/sort changes reload immediately. Both reset to page 1.
let searchTimer = null;
function onSearch() {
  clearTimeout(searchTimer);
  searchTimer = setTimeout(() => { page.value = 1; load(); }, 300);
}
watch([source, sort], () => { page.value = 1; load(); });

function go(p) {
  if (p < 1 || p > pages.value || p === page.value) return;
  page.value = p;
  load();
}

// Headline kcal for a row: prefer per-serving, fall back to per-100g.
function kcalLabel(p) {
  if (p.kcal_per_serving != null) return t('admin.barcodes.kcal_serving', { n: p.kcal_per_serving });
  if (p.kcal_per_100g != null) return t('admin.barcodes.kcal_100g', { n: Math.round(p.kcal_per_100g) });
  return t('admin.barcodes.kcal_unknown');
}

const fmtDate = (s) => (s ? String(s).slice(0, 10) : '—');
</script>

<template>
  <section class="barcodes">
    <h1>{{ $t('admin.barcodes.title') }}</h1>
    <p class="intro">{{ $t('admin.barcodes.intro') }}</p>

    <div v-if="stats" class="stat-strip">
      <div class="stat">
        <span class="n">{{ stats.products }}</span>
        <span class="l">{{ $t('admin.barcodes.stat_products') }}</span>
      </div>
      <div class="stat">
        <span class="n">{{ stats.total_scans }}</span>
        <span class="l">{{ $t('admin.barcodes.stat_scans') }}</span>
      </div>
      <div class="stat">
        <span class="n">{{ stats.hit_rate == null ? '—' : stats.hit_rate + '%' }}</span>
        <span class="l">{{ $t('admin.barcodes.stat_hit_rate') }}</span>
      </div>
      <div class="stat">
        <span class="n">{{ stats.api_miss }}</span>
        <span class="l">{{ $t('admin.barcodes.stat_misses') }}</span>
      </div>
    </div>

    <div class="filters">
      <input v-model="q" :placeholder="$t('admin.barcodes.search_ph')" class="search" @input="onSearch" />
      <select v-model="source">
        <option value="">{{ $t('admin.barcodes.all_sources') }}</option>
        <option v-for="s in sources" :key="s" :value="s">{{ $t('admin.barcodes.source.' + s) }}</option>
      </select>
      <select v-model="sort">
        <option value="popular">{{ $t('admin.barcodes.sort_popular') }}</option>
        <option value="recent">{{ $t('admin.barcodes.sort_recent') }}</option>
        <option value="oldest">{{ $t('admin.barcodes.sort_oldest') }}</option>
        <option value="name">{{ $t('admin.barcodes.sort_name') }}</option>
      </select>
    </div>

    <p v-if="error" class="error">{{ error }}</p>

    <div class="table-wrap">
      <table>
        <thead>
          <tr>
            <th>{{ $t('admin.barcodes.col_product') }}</th>
            <th>{{ $t('admin.barcodes.col_barcode') }}</th>
            <th>{{ $t('admin.barcodes.col_kcal') }}</th>
            <th>{{ $t('admin.barcodes.col_source') }}</th>
            <th>{{ $t('admin.barcodes.col_lookups') }}</th>
            <th>{{ $t('admin.barcodes.col_updated') }}</th>
            <th></th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="p in products" :key="p.barcode">
            <td :data-label="$t('admin.barcodes.col_product')">
              <div class="p-cell">
                <img v-if="p.image_url" :src="p.image_url" alt="" class="thumb" loading="lazy" />
                <span v-else class="thumb thumb-empty"><i class="fa-solid fa-box" /></span>
                <div>
                  <div class="p-name">{{ p.product_name || $t('admin.barcodes.no_name') }}</div>
                  <div v-if="p.brand" class="p-brand">{{ p.brand }}</div>
                </div>
              </div>
            </td>
            <td class="mono" :data-label="$t('admin.barcodes.col_barcode')">{{ p.barcode }}</td>
            <td :data-label="$t('admin.barcodes.col_kcal')">{{ kcalLabel(p) }}</td>
            <td :data-label="$t('admin.barcodes.col_source')">
              <span class="badge" :class="'src-' + p.source">{{ $t('admin.barcodes.source.' + p.source) }}</span>
            </td>
            <td class="num" :data-label="$t('admin.barcodes.col_lookups')">{{ p.lookup_count }}</td>
            <td class="b-date" :data-label="$t('admin.barcodes.col_updated')">{{ fmtDate(p.updated_at) }}</td>
            <td class="b-actions">
              <RouterLink :to="`/admin/barcodes/${p.barcode}`" class="btn-link">{{ $t('admin.barcodes.view') }}</RouterLink>
            </td>
          </tr>
          <tr v-if="!loading && !products.length">
            <td colspan="7" class="empty">{{ $t('admin.barcodes.none') }}</td>
          </tr>
        </tbody>
      </table>
    </div>

    <div class="pager">
      <button :disabled="page <= 1" @click="go(page - 1)">{{ $t('admin.users.prev') }}</button>
      <span class="pageinfo">{{ $t('admin.users.page') }} {{ page }} / {{ pages }} · {{ total }}</span>
      <button :disabled="page >= pages" @click="go(page + 1)">{{ $t('admin.users.next') }}</button>
    </div>
  </section>
</template>

<style scoped>
.barcodes { max-width: 1000px; }
h1 { margin: 0 0 4px; font-size: 1.5rem; }
.intro { color: var(--muted); margin: 0 0 20px; }
.stat-strip { display: grid; grid-template-columns: repeat(auto-fit, minmax(140px, 1fr)); gap: 12px; margin-bottom: 18px; }
.stat {
  background: var(--card); border: 1px solid var(--border); border-radius: 14px;
  padding: 14px 16px; display: flex; flex-direction: column; gap: 4px;
}
.stat .n { font-size: 1.6rem; font-weight: 800; color: var(--accent); line-height: 1; }
.stat .l { color: var(--muted); font-size: 0.82rem; }
.filters { display: flex; gap: 10px; flex-wrap: wrap; margin-bottom: 16px; }
.search { flex: 1; min-width: 200px; }
.filters input, .filters select {
  padding: 9px 12px; border-radius: 10px; border: 1px solid var(--border);
  background: var(--card); color: var(--text); font: inherit;
}
.error { color: #ef4444; margin: 8px 0; }
.table-wrap { overflow-x: auto; border: 1px solid var(--border); border-radius: 14px; }
table { width: 100%; border-collapse: collapse; }
th, td { text-align: left; padding: 11px 14px; border-bottom: 1px solid var(--border); vertical-align: middle; font-size: 0.88rem; }
th { font-size: 0.72rem; text-transform: uppercase; letter-spacing: 0.04em; color: var(--muted); }
tr:last-child td { border-bottom: none; }
.p-cell { display: flex; align-items: center; gap: 10px; }
.thumb { flex: none; width: 40px; height: 40px; border-radius: 8px; object-fit: cover; background: var(--inset); border: 1px solid var(--border); }
.thumb-empty { display: grid; place-items: center; color: var(--muted); }
.p-name { font-weight: 600; }
.p-brand { color: var(--muted); font-size: 0.8rem; }
.mono { font-family: ui-monospace, SFMono-Regular, Menlo, monospace; color: var(--muted); white-space: nowrap; }
.num { font-weight: 700; }
.b-date { color: var(--muted); font-size: 0.85rem; white-space: nowrap; }
.b-actions { white-space: nowrap; text-align: right; }
.empty { text-align: center; color: var(--muted); padding: 28px; }
.badge {
  display: inline-block; font-size: 0.72rem; font-weight: 700; padding: 3px 9px;
  border-radius: 999px; background: var(--border); color: var(--text);
}
.badge.src-openfoodfacts { background: #16a34a33; color: #86efac; }
.badge.src-user_submitted { background: #1cb0f633; color: #7dd3fc; }
.btn-link { color: var(--accent); text-decoration: none; font-weight: 600; font-size: 0.85rem; }
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
  td.b-actions { justify-content: flex-end; }
  td.b-actions::before, td.empty::before { display: none; }
  td.empty { justify-content: center; }
}
</style>
