<script setup>
// PT workspace (route /trainer, role 'pt' only — reached via the topbar avatar
// menu, NOT the bottom nav). Master-detail of clients + a connection-requests
// tab. Ports dashboard-pt.php. Detail/feedback/goal/chat live in
// TrainerClientDetail; data comes from /api/pt/clients|requests.
import { ref, computed, onMounted } from 'vue';
import { useRouter } from 'vue-router';
import { api } from '../lib/api.js';
import { useAuthStore } from '../stores/auth.js';
import TrainerClientDetail from '../components/TrainerClientDetail.vue';
import TrainerFindClients from '../components/TrainerFindClients.vue';

const router = useRouter();
const auth = useAuthStore();

const tab = ref('clients'); // 'clients' | 'requests' | 'find'
const clients = ref([]);
const requests = ref([]);
const selected = ref(null);
const loading = ref(true);
const error = ref('');
const busyReq = ref(0); // request_id currently being acted on

// Non-PT users shouldn't be here (the API would 403 anyway). Bounce home.
if (auth.user && auth.user.role !== 'pt') router.replace('/dashboard');

const unreadTotal = computed(() => clients.value.reduce((n, c) => n + (c.unread || 0), 0));
const noLogCount = computed(() => clients.value.filter((c) => c.calories_today <= 0).length);

function clientName(c) {
  return `${c.first_name ?? ''} ${c.last_name ?? ''}`.trim() || c.user_name;
}
function pct(c) {
  if (!c.calorie_goal) return null;
  return Math.round((c.calories_today / c.calorie_goal) * 100);
}

async function loadClients() {
  try {
    const data = await api.get('/api/pt/clients');
    clients.value = data.clients;
    // keep the selected client's row reference fresh after a refresh
    if (selected.value) selected.value = clients.value.find((c) => c.user_id === selected.value.user_id) || null;
  } catch (e) {
    error.value = e.message;
  }
}
async function loadRequests() {
  try {
    const data = await api.get('/api/pt/requests');
    requests.value = data.requests;
  } catch (e) {
    error.value = e.message;
  }
}

async function load() {
  loading.value = true;
  error.value = '';
  await Promise.all([loadClients(), loadRequests()]);
  loading.value = false;
}

// A client was removed from the trainer detail — drop them and clear selection.
function onTerminated(clientId) {
  clients.value = clients.value.filter((c) => c.user_id !== clientId);
  selected.value = null;
}

async function respondRequest(req, action) {
  if (busyReq.value) return;
  busyReq.value = req.request_id;
  try {
    await api.post(`/api/pt/requests/${req.request_id}/respond`, { action });
    requests.value = requests.value.filter((r) => r.request_id !== req.request_id);
    if (action === 'accept') await loadClients(); // new client appears in the list
  } catch (e) {
    error.value = e.message;
  } finally {
    busyReq.value = 0;
  }
}

onMounted(load);
</script>

<template>
  <div class="tv">
    <header class="tv-head">
      <h1 class="title">{{ $t('trainer.title') }}</h1>
      <div class="statstrip">
        <span>{{ $t('trainer.clients_count', { n: clients.length }) }}</span>
        <span v-if="unreadTotal" class="hot">{{ $t('trainer.unread_count', { n: unreadTotal }) }}</span>
        <span v-if="noLogCount" class="warn">{{ $t('trainer.no_log_count', { n: noLogCount }) }}</span>
        <span v-if="requests.length" class="warn">{{ $t('trainer.requests_count', { n: requests.length }) }}</span>
      </div>
    </header>

    <div class="tv-tabs" role="tablist">
      <button class="tv-tab" :class="{ on: tab === 'clients' }" @click="tab = 'clients'">
        {{ $t('trainer.tab.clients') }}<span v-if="clients.length" class="count">{{ clients.length }}</span>
      </button>
      <button class="tv-tab" :class="{ on: tab === 'requests' }" @click="tab = 'requests'">
        {{ $t('trainer.tab.requests') }}<span v-if="requests.length" class="count alert">{{ requests.length }}</span>
      </button>
      <button class="tv-tab" :class="{ on: tab === 'find' }" @click="tab = 'find'">{{ $t('trainer.tab.find') }}</button>
    </div>

    <p v-if="error" class="error">{{ error }}</p>
    <p v-if="loading" class="muted center pad">{{ $t('common.loading') }}</p>

    <!-- CLIENTS: master-detail -->
    <div v-show="tab === 'clients' && !loading" class="workspace" :class="{ 'has-detail': selected }">
      <div class="list-col">
        <p v-if="!clients.length" class="muted center pad">{{ $t('trainer.empty.clients') }}</p>
        <button
          v-for="c in clients"
          :key="c.user_id"
          class="client-card"
          :class="{ active: selected && selected.user_id === c.user_id, alert: c.calories_today <= 0 }"
          @click="selected = c"
        >
          <span class="c-avatar">
            <img v-if="c.profile_image" :src="c.profile_image" alt="" />
            <span v-else>{{ clientName(c).charAt(0).toUpperCase() }}</span>
          </span>
          <span class="c-meta">
            <span class="c-name">{{ clientName(c) }}<span v-if="c.unread" class="c-unread">{{ c.unread }}</span></span>
            <span class="c-sub muted">
              <template v-if="c.calorie_goal">{{ c.calories_today }}/{{ c.calorie_goal }} {{ $t('common.kcal') }} · {{ pct(c) }}%</template>
              <template v-else>{{ $t('trainer.client.kcal_today', { calories: c.calories_today }) }}</template>
            </span>
            <span v-if="c.calorie_goal" class="c-bar"><span class="c-fill" :class="{ over: pct(c) >= 110 }" :style="{ width: Math.min(pct(c), 100) + '%' }" /></span>
          </span>
          <span v-if="c.calories_today <= 0" class="flag">{{ $t('trainer.flag.no_log') }}</span>
        </button>
      </div>

      <div class="detail-col">
        <TrainerClientDetail
          v-if="selected"
          :key="selected.user_id"
          :client="selected"
          @back="selected = null"
          @updated="loadClients"
          @terminated="onTerminated"
        />
        <div v-else class="detail-empty muted">
          <i class="fa-solid fa-hand-pointer" />
          <p>{{ $t('trainer.empty.detail') }}</p>
        </div>
      </div>
    </div>

    <!-- REQUESTS -->
    <div v-show="tab === 'requests' && !loading" class="requests">
      <p v-if="!requests.length" class="muted center pad">{{ $t('trainer.empty.requests') }}</p>
      <div v-for="r in requests" :key="r.request_id" class="req-card">
        <span class="c-avatar">
          <img v-if="r.profile_image" :src="r.profile_image" alt="" />
          <span v-else>{{ clientName(r).charAt(0).toUpperCase() }}</span>
        </span>
        <div class="req-meta">
          <strong>{{ clientName(r) }}</strong>
          <span class="muted">@{{ r.user_name }} · {{ $t('trainer.request.streak', { n: r.logging_streak }) }}</span>
        </div>
        <div class="req-actions">
          <button class="accept" :disabled="busyReq === r.request_id" @click="respondRequest(r, 'accept')">{{ $t('trainer.request.accept') }}</button>
          <button class="decline" :disabled="busyReq === r.request_id" @click="respondRequest(r, 'reject')">{{ $t('trainer.request.decline') }}</button>
        </div>
      </div>
    </div>

    <!-- FIND CLIENTS (PT-initiated invites) -->
    <div v-show="tab === 'find' && !loading" class="find-wrap">
      <TrainerFindClients />
    </div>
  </div>
</template>

<style scoped>
.tv {
  display: flex;
  flex-direction: column;
  height: calc(100vh - 60px);
  max-width: 1100px;
  margin: 0 auto;
  padding: 8px 16px;
}
.muted { color: var(--muted); font-size: 13px; }
.center { text-align: center; }
.pad { padding: 16px; }
.error { color: #f87171; margin: 4px 0; }

.tv-head { flex: none; }
.title { font-size: 22px; margin: 4px 0 6px; }
.statstrip { display: flex; flex-wrap: wrap; gap: 6px 14px; font-size: 13px; color: var(--muted); margin-bottom: 10px; }
.statstrip .hot { color: var(--accent); font-weight: 700; }
.statstrip .warn { color: #f59e0b; font-weight: 700; }

.tv-tabs { flex: none; display: flex; gap: 6px; margin-bottom: 12px; max-width: 360px; }
.tv-tab {
  flex: 1; min-height: 40px; padding: 7px 6px; border-radius: 10px;
  background: var(--card); border: 1px solid var(--border); color: var(--muted);
  font-weight: 700; font-size: 13px; display: inline-flex; align-items: center; justify-content: center; gap: 6px;
}
.tv-tab.on { color: var(--accent); border-color: var(--accent); background: var(--inset); }
.count { min-width: 18px; height: 18px; padding: 0 5px; border-radius: 999px; background: var(--surface-2); color: var(--text); font-size: 11px; display: grid; place-items: center; }
.count.alert { background: #ef4444; color: #fff; }

/* Master-detail */
.workspace { flex: 1; min-height: 0; display: grid; grid-template-columns: 320px 1fr; gap: 14px; }
.list-col { min-height: 0; overflow-y: auto; display: flex; flex-direction: column; gap: 8px; }
.detail-col { min-height: 0; background: var(--bg); border: 1px solid var(--border); border-radius: 14px; padding: 14px; overflow: hidden; }

.client-card {
  display: flex; align-items: center; gap: 10px; text-align: left;
  background: var(--card); border: 1px solid var(--border); border-radius: 12px;
  padding: 10px 12px; min-height: 0; color: var(--text); position: relative;
}
.client-card:hover { border-color: #3a414e; }
.client-card.active { border-color: var(--accent); }
.client-card.alert { border-left: 3px solid #f59e0b; }
.c-avatar {
  flex: none; width: 40px; height: 40px; border-radius: 50%; overflow: hidden;
  display: grid; place-items: center; background: var(--accent); color: var(--on-accent); font-weight: 800;
}
.c-avatar img { width: 100%; height: 100%; object-fit: cover; }
.c-meta { flex: 1; min-width: 0; display: flex; flex-direction: column; gap: 3px; }
.c-name { font-weight: 700; font-size: 14px; display: flex; align-items: center; gap: 6px; }
.c-unread { background: #ef4444; color: #fff; font-size: 10px; font-weight: 700; min-width: 16px; height: 16px; padding: 0 4px; border-radius: 999px; display: grid; place-items: center; }
.c-sub { font-size: 12px; }
.c-bar { height: 5px; background: var(--inset); border-radius: 999px; overflow: hidden; }
.c-fill { display: block; height: 100%; background: var(--accent); border-radius: 999px; }
.c-fill.over { background: #f59e0b; }
.flag { position: absolute; top: 8px; right: 10px; font-size: 10px; color: #f59e0b; font-weight: 700; }

.detail-empty { height: 100%; display: flex; flex-direction: column; align-items: center; justify-content: center; gap: 10px; text-align: center; }
.detail-empty i { font-size: 26px; opacity: 0.5; }

/* Find clients */
.find-wrap { flex: 1; min-height: 0; }

/* Requests */
.requests { flex: 1; min-height: 0; overflow-y: auto; display: flex; flex-direction: column; gap: 8px; }
.req-card { display: flex; align-items: center; gap: 12px; background: var(--card); border: 1px solid var(--border); border-radius: 12px; padding: 12px 14px; }
.req-meta { flex: 1; min-width: 0; display: flex; flex-direction: column; gap: 2px; }
.req-actions { flex: none; display: flex; gap: 8px; }
.req-actions button { min-height: 38px; padding: 8px 14px; font-size: 13px; }
.req-actions .decline { background: var(--card); color: var(--text); border: 1px solid var(--border); }

@media (max-width: 767px) {
  .tv { height: calc(100vh - 130px); padding: 8px 12px; }
  .workspace { grid-template-columns: 1fr; }
  /* One pane at a time: list by default, detail once a client is picked. */
  .workspace.has-detail .list-col { display: none; }
  .workspace:not(.has-detail) .detail-col { display: none; }
  .detail-col { padding: 12px; }
}
</style>
