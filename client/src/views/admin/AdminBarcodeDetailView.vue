<script setup>
import { ref, onMounted } from 'vue';
import { useRoute, useRouter, RouterLink } from 'vue-router';
import { api } from '../../lib/api.js';
import { t } from '../../i18n/index.js';
import ConfirmDialog from '../../components/ConfirmDialog.vue';

const route = useRoute();
const router = useRouter();
const barcode = String(route.params.barcode);
const detail = ref(null);
const loading = ref(true);
const error = ref('');
const evicting = ref(false);
const evictOpen = ref(false);

async function load() {
  loading.value = true;
  try {
    detail.value = await api.get(`/api/admin/barcodes/${encodeURIComponent(barcode)}`);
    error.value = '';
  } catch (e) {
    error.value = e.message;
  } finally {
    loading.value = false;
  }
}
onMounted(load);

async function confirmEvict() {
  evicting.value = true; error.value = '';
  try {
    await api.post(`/api/admin/barcodes/${encodeURIComponent(barcode)}/evict`, {});
    router.push('/admin/barcodes');
  } catch (e) {
    error.value = e.message;
    evictOpen.value = false;
  } finally {
    evicting.value = false;
  }
}

const num = (v, unit = '') => (v == null ? '—' : `${v}${unit}`);
const fmt = (s) => (s ? String(s).replace('T', ' ').slice(0, 16) : '—');
</script>

<template>
  <section class="detail">
    <RouterLink to="/admin/barcodes" class="back">&larr; {{ $t('admin.barcodes.back') }}</RouterLink>

    <p v-if="loading" class="muted">…</p>
    <p v-else-if="error && !detail" class="error">{{ error }}</p>

    <template v-else-if="detail">
      <header class="head">
        <img v-if="detail.product.image_url" :src="detail.product.image_url" alt="" class="hero-img" />
        <span v-else class="hero-img hero-empty"><i class="fa-solid fa-box" /></span>
        <div>
          <h1>{{ detail.product.product_name || $t('admin.barcodes.no_name') }}</h1>
          <div v-if="detail.product.brand" class="sub">{{ detail.product.brand }}</div>
          <div class="mono">{{ detail.product.barcode }}</div>
          <span class="badge" :class="'src-' + detail.product.source">{{ $t('admin.barcodes.source.' + detail.product.source) }}</span>
        </div>
      </header>

      <p v-if="error" class="error">{{ error }}</p>

      <div class="grid">
        <div class="card">
          <h2>{{ $t('admin.barcodes.nutrition') }}</h2>
          <dl class="kv">
            <dt>{{ $t('admin.barcodes.serving_size') }}</dt><dd>{{ detail.product.serving_size || '—' }}</dd>
            <dt>{{ $t('admin.barcodes.col_kcal') }} ({{ $t('admin.barcodes.per_serving') }})</dt><dd>{{ num(detail.product.kcal_per_serving, ' kcal') }}</dd>
            <dt>{{ $t('admin.barcodes.col_kcal') }} ({{ $t('admin.barcodes.per_100g') }})</dt><dd>{{ num(detail.product.kcal_per_100g, ' kcal') }}</dd>
            <dt>{{ $t('admin.barcodes.protein') }}</dt><dd>{{ num(detail.product.protein_per_serving, ' g') }}</dd>
            <dt>{{ $t('admin.barcodes.carbs') }}</dt><dd>{{ num(detail.product.carbs_per_serving, ' g') }}</dd>
            <dt>{{ $t('admin.barcodes.fat') }}</dt><dd>{{ num(detail.product.fat_per_serving, ' g') }}</dd>
            <dt>{{ $t('admin.barcodes.sugar') }}</dt><dd>{{ num(detail.product.sugar_per_serving, ' g') }}</dd>
          </dl>
        </div>

        <div class="card">
          <h2>{{ $t('admin.barcodes.meta') }}</h2>
          <dl class="kv">
            <dt>{{ $t('admin.barcodes.lookups') }}</dt><dd>{{ detail.product.lookup_count }}</dd>
            <dt>{{ $t('admin.barcodes.col_source') }}</dt><dd>{{ $t('admin.barcodes.source.' + detail.product.source) }}</dd>
            <dt v-if="detail.product.submitted_by_user_id">{{ $t('admin.barcodes.submitted_by') }}</dt>
            <dd v-if="detail.product.submitted_by_user_id">{{ detail.product.submitted_by_name ? '@' + detail.product.submitted_by_name : '#' + detail.product.submitted_by_user_id }}</dd>
            <dt>{{ $t('admin.barcodes.first_cached') }}</dt><dd>{{ fmt(detail.product.created_at) }}</dd>
            <dt>{{ $t('admin.barcodes.last_updated') }}</dt><dd>{{ fmt(detail.product.updated_at) }}</dd>
          </dl>
          <div class="actions">
            <button class="btn-danger" :disabled="evicting" @click="evictOpen = true">
              {{ evicting ? $t('admin.barcodes.evicting') : $t('admin.barcodes.evict') }}
            </button>
          </div>
        </div>
      </div>

      <div class="card">
        <h2>{{ $t('admin.barcodes.scan_history') }}</h2>
        <div v-if="detail.scan_stats.total" class="scan-counts">
          <span class="pill r-cache_hit">{{ $t('admin.barcodes.result.cache_hit') }}: {{ detail.scan_stats.cache_hit }}</span>
          <span class="pill r-api_found">{{ $t('admin.barcodes.result.api_found') }}: {{ detail.scan_stats.api_found }}</span>
          <span class="pill r-api_miss">{{ $t('admin.barcodes.result.api_miss') }}: {{ detail.scan_stats.api_miss }}</span>
          <span class="pill r-api_error">{{ $t('admin.barcodes.result.api_error') }}: {{ detail.scan_stats.api_error }}</span>
        </div>
        <table v-if="detail.scans.length" class="mini">
          <thead>
            <tr>
              <th>{{ $t('admin.barcodes.scan_when') }}</th>
              <th>{{ $t('admin.barcodes.scan_user') }}</th>
              <th>{{ $t('admin.barcodes.scan_result') }}</th>
              <th>{{ $t('admin.barcodes.scan_latency') }}</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="s in detail.scans" :key="s.scan_id">
              <td>{{ fmt(s.created_at) }}</td>
              <td>{{ s.user_name ? '@' + s.user_name : (s.user_id ? '#' + s.user_id : '—') }}</td>
              <td><span class="pill" :class="'r-' + s.result">{{ $t('admin.barcodes.result.' + s.result) }}</span></td>
              <td>{{ s.latency_ms == null ? '—' : s.latency_ms + ' ms' }}</td>
            </tr>
          </tbody>
        </table>
        <p v-else class="muted">{{ $t('admin.barcodes.no_scans') }}</p>
      </div>
    </template>

    <ConfirmDialog
      :open="evictOpen"
      :title="$t('admin.barcodes.confirm_evict_title')"
      :message="$t('admin.barcodes.confirm_evict')"
      :confirm-label="$t('admin.barcodes.evict')"
      :busy="evicting"
      @confirm="confirmEvict"
      @cancel="evictOpen = false"
    />
  </section>
</template>

<style scoped>
.detail { max-width: 920px; }
.back { color: var(--accent); text-decoration: none; font-weight: 600; font-size: 0.9rem; }
.head { display: flex; align-items: flex-start; gap: 16px; margin: 14px 0 8px; flex-wrap: wrap; }
.hero-img { flex: none; width: 72px; height: 72px; border-radius: 12px; object-fit: cover; background: var(--inset); border: 1px solid var(--border); }
.hero-empty { display: grid; place-items: center; color: var(--muted); font-size: 1.4rem; }
.head h1 { margin: 0 0 2px; font-size: 1.4rem; }
.sub { color: var(--muted); font-size: 0.95rem; }
.mono { font-family: ui-monospace, SFMono-Regular, Menlo, monospace; color: var(--muted); font-size: 0.85rem; margin: 4px 0 8px; }
.muted { color: var(--muted); }
.error { color: #ef4444; margin: 8px 0; }
.grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 16px; margin: 12px 0; }
.card { background: var(--card); border: 1px solid var(--border); border-radius: 14px; padding: 18px 20px; margin-bottom: 16px; }
.card h2 { margin: 0 0 14px; font-size: 1.05rem; }
.kv { display: grid; grid-template-columns: auto 1fr; gap: 8px 16px; margin: 0 0 16px; }
.kv dt { color: var(--muted); font-size: 0.85rem; }
.kv dd { margin: 0; font-weight: 600; font-size: 0.9rem; text-align: right; }
.actions { display: flex; gap: 8px; flex-wrap: wrap; }
.actions button { font: inherit; font-size: 0.82rem; font-weight: 700; cursor: pointer; padding: 8px 14px; border-radius: 9px; border: 1px solid var(--border); background: var(--card); color: var(--text); }
.actions .btn-danger { background: #ef444422; color: #fca5a5; border-color: #ef444455; }
button:disabled { opacity: 0.5; cursor: default; }
.scan-counts { display: flex; gap: 8px; flex-wrap: wrap; margin-bottom: 14px; }
.mini { width: 100%; border-collapse: collapse; }
.mini th, .mini td { text-align: left; padding: 8px 10px; border-bottom: 1px solid var(--border); font-size: 0.85rem; }
.mini th { font-size: 0.7rem; text-transform: uppercase; letter-spacing: 0.04em; color: var(--muted); }
.badge { display: inline-block; font-size: 0.72rem; font-weight: 700; padding: 3px 9px; border-radius: 999px; background: var(--border); color: var(--text); }
.badge.src-openfoodfacts { background: #16a34a33; color: #86efac; }
.badge.src-user_submitted { background: #1cb0f633; color: #7dd3fc; }
.pill { display: inline-block; font-size: 0.72rem; font-weight: 700; padding: 3px 9px; border-radius: 999px; background: var(--border); color: var(--text); }
.pill.r-cache_hit { background: #16a34a33; color: #86efac; }
.pill.r-api_found { background: #1cb0f633; color: #7dd3fc; }
.pill.r-api_miss { background: #f59e0b33; color: #fcd34d; }
.pill.r-api_error { background: #ef444433; color: #fca5a5; }
</style>
