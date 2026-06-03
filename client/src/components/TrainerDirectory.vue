<script setup>
// Browsable directory of onboarded trainers a client with no trainer can connect
// to (no need to know the exact handle). Ports the $pt_directory grid +
// send_trainer_request from profile.php. Lives in the My Trainer empty state.
import { ref, computed, onMounted } from 'vue';
import { api } from '../lib/api.js';

const emit = defineEmits(['requested']);

const trainers = ref([]);
const loading = ref(true);
const error = ref('');
const q = ref('');
const busyId = ref(0);

function fullName(t) {
  return `${t.first_name ?? ''} ${t.last_name ?? ''}`.trim() || t.user_name;
}
function specialtiesOf(t) {
  return (t.specialties || '')
    .split(/[,;]/)
    .map((s) => s.trim())
    .filter(Boolean);
}

const filtered = computed(() => {
  const term = q.value.trim().toLowerCase();
  if (!term) return trainers.value;
  return trainers.value.filter((t) =>
    `${fullName(t)} ${t.user_name} ${t.specialties || ''}`.toLowerCase().includes(term)
  );
});

async function load() {
  loading.value = true;
  error.value = '';
  try {
    const data = await api.get('/api/pt/directory');
    trainers.value = data.trainers;
  } catch (e) {
    error.value = e.message;
  } finally {
    loading.value = false;
  }
}

async function connect(t) {
  if (busyId.value || t.is_full) return;
  busyId.value = t.user_id;
  error.value = '';
  try {
    await api.post('/api/pt/request', { trainer_id: t.user_id });
    emit('requested');
  } catch (e) {
    error.value = e.message;
  } finally {
    busyId.value = 0;
  }
}

onMounted(load);
</script>

<template>
  <div class="dir">
    <div class="dir-head">
      <h2>{{ $t('coach.directory.title') }}</h2>
      <p class="muted">{{ $t('coach.directory.subtitle') }}</p>
    </div>

    <input v-model="q" class="dir-search" type="search" :placeholder="$t('coach.directory.search_placeholder')" />
    <p v-if="error" class="error">{{ error }}</p>

    <p v-if="loading" class="muted center pad">{{ $t('coach.directory.loading') }}</p>
    <p v-else-if="!trainers.length" class="muted center pad">{{ $t('coach.directory.empty') }}</p>
    <p v-else-if="!filtered.length" class="muted center pad">{{ $t('coach.directory.no_match', { q }) }}</p>

    <div v-else class="grid">
      <article v-for="t in filtered" :key="t.user_id" class="pt-card">
        <header class="pt-top">
          <span class="avatar">
            <img v-if="t.profile_image" :src="t.profile_image" alt="" />
            <span v-else>{{ fullName(t).charAt(0).toUpperCase() }}</span>
          </span>
          <div class="pt-id">
            <strong>{{ fullName(t) }}</strong>
            <span class="muted">@{{ t.user_name }}</span>
          </div>
        </header>

        <div v-if="specialtiesOf(t).length" class="chips">
          <span v-for="s in specialtiesOf(t)" :key="s" class="chip">{{ s }}</span>
        </div>
        <p v-if="t.bio" class="bio muted">{{ t.bio }}</p>

        <div class="pt-foot">
          <span class="meta muted">
            <template v-if="t.experience_years != null">{{ $t('coach.my_trainer.exp_years', { n: t.experience_years }) }} · </template>
            <span :class="{ full: t.is_full }">{{ t.client_count }}<template v-if="t.max_clients != null">/{{ t.max_clients }}</template> {{ $t('coach.directory.clients') }}</span>
          </span>
          <button class="connect" :disabled="t.is_full || busyId === t.user_id" @click="connect(t)">
            <i class="fa-solid fa-link" /> {{ t.is_full ? $t('coach.directory.full') : $t('coach.directory.connect') }}
          </button>
        </div>
      </article>
    </div>
  </div>
</template>

<style scoped>
.dir { height: 100%; overflow-y: auto; display: flex; flex-direction: column; }
.muted { color: var(--muted); font-size: 13px; }
.center { text-align: center; }
.pad { padding: 16px; }
.error { color: #f87171; font-size: 13px; margin: 6px 0; }

.dir-head { text-align: center; margin: 6px 0 12px; }
.dir-head h2 { margin: 0 0 4px; }
.dir-search { flex: none; margin-bottom: 12px; }

.grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(240px, 1fr)); gap: 10px; }
.pt-card {
  background: var(--card); border: 1px solid var(--border); border-radius: 12px; padding: 12px 14px;
  display: flex; flex-direction: column; gap: 8px;
}
.pt-top { display: flex; align-items: center; gap: 10px; }
.avatar {
  flex: none; width: 42px; height: 42px; border-radius: 50%; overflow: hidden;
  display: grid; place-items: center; background: var(--accent); color: var(--on-accent); font-weight: 800;
}
.avatar img { width: 100%; height: 100%; object-fit: cover; }
.pt-id { display: flex; flex-direction: column; min-width: 0; }
.pt-id strong { white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.pt-id .muted { font-size: 12px; }
.chips { display: flex; flex-wrap: wrap; gap: 5px; }
.chip { font-size: 11px; color: var(--accent); border: 1px solid var(--border); border-radius: 999px; padding: 2px 9px; }
.bio { margin: 0; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; line-height: 1.4; }
.pt-foot { margin-top: auto; display: flex; align-items: center; justify-content: space-between; gap: 8px; }
.meta { font-size: 12px; }
.meta .full { color: #f59e0b; font-weight: 700; }
.connect { min-height: 36px; padding: 7px 14px; font-size: 13px; }

@media (max-width: 767px) {
  .grid { grid-template-columns: 1fr; }
}
</style>
