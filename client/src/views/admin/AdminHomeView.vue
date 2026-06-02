<script setup>
import { ref, onMounted } from 'vue';
import { api } from '../../lib/api.js';

const summary = ref(null);
const error = ref('');
const loading = ref(true);

onMounted(async () => {
  try {
    summary.value = await api.get('/api/admin/summary');
  } catch (e) {
    error.value = e.message || 'Failed to load.';
  } finally {
    loading.value = false;
  }
});
</script>

<template>
  <section class="admin-home">
    <h1>{{ $t('admin.overview.title') }}</h1>
    <p class="intro">{{ $t('admin.overview.intro') }}</p>

    <p v-if="loading" class="muted">…</p>
    <p v-else-if="error" class="error">{{ error }}</p>
    <div v-else class="cards">
      <div class="card">
        <span class="n">{{ summary.users }}</span>
        <span class="l">{{ $t('admin.overview.users') }}</span>
      </div>
      <div class="card">
        <span class="n">{{ summary.admins }}</span>
        <span class="l">{{ $t('admin.overview.admins') }}</span>
      </div>
      <div class="card">
        <span class="n">{{ summary.activity_log_rows }}</span>
        <span class="l">{{ $t('admin.overview.logs') }}</span>
      </div>
    </div>
  </section>
</template>

<style scoped>
.admin-home { max-width: 900px; }
h1 { margin: 0 0 4px; font-size: 1.5rem; }
.intro { color: var(--muted); margin: 0 0 24px; }
.muted { color: var(--muted); }
.error { color: #ef4444; }
.cards { display: grid; grid-template-columns: repeat(auto-fit, minmax(160px, 1fr)); gap: 16px; }
.card {
  background: var(--card); border: 1px solid var(--border); border-radius: 14px;
  padding: 20px; display: flex; flex-direction: column; gap: 6px;
}
.card .n { font-size: 2rem; font-weight: 800; color: var(--accent); line-height: 1; }
.card .l { color: var(--muted); font-size: 0.9rem; }
</style>
