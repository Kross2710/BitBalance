<script setup>
import { ref, computed, onMounted, onUnmounted, watch } from 'vue';
import { api } from '../lib/api.js';

// Four sections mirror the PHP friends page: Friends, Pending (in+out),
// Leaderboard (weekly/all-time), Find People (search + add).
const tab = ref('friends');

const friends = ref([]);
const pendingIn = ref([]);
const pendingOut = ref([]);
const error = ref('');
const loading = ref(true);
const busy = ref(false); // guards a mutation in flight

// --- Poll (friends + pending) -------------------------------------------------
async function poll() {
  try {
    const d = await api.get('/api/social/poll');
    friends.value = d.friends;
    pendingIn.value = d.pending_in;
    pendingOut.value = d.pending_out;
    error.value = '';
  } catch (e) {
    error.value = e.message;
  } finally {
    loading.value = false;
  }
}

// Poll every 15s while this page is mounted (PHP polls every 12s). Cheap, and
// it keeps incoming requests / accepts fresh without a websocket.
let timer = null;
onMounted(() => {
  poll();
  timer = setInterval(poll, 15000);
});
onUnmounted(() => clearInterval(timer));

// --- Leaderboard --------------------------------------------------------------
const leaders = ref([]);
const period = ref('weekly');
const lbLoading = ref(false);

async function loadLeaderboard() {
  lbLoading.value = true;
  try {
    const d = await api.get(`/api/social/leaderboard?period=${period.value}`);
    leaders.value = d.leaders;
  } catch (e) {
    error.value = e.message;
  } finally {
    lbLoading.value = false;
  }
}
watch(period, loadLeaderboard);
watch(tab, (t) => {
  if (t === 'leaderboard') loadLeaderboard();
});

// --- Search -------------------------------------------------------------------
const q = ref('');
const results = ref([]);
const searching = ref(false);
let searchTimer = null;

function onSearchInput() {
  clearTimeout(searchTimer);
  const term = q.value.trim();
  if (term.length < 2) {
    results.value = [];
    return;
  }
  searchTimer = setTimeout(runSearch, 300);
}
async function runSearch() {
  const term = q.value.trim();
  if (term.length < 2) return;
  searching.value = true;
  try {
    const d = await api.post('/api/social/search', { q: term });
    results.value = d.results;
  } catch (e) {
    error.value = e.message;
  } finally {
    searching.value = false;
  }
}

// --- Mutations ----------------------------------------------------------------
// Each runs, then refreshes poll data; on the Find tab it re-runs search so the
// relationship CTA on each result updates.
async function mutate(path, body) {
  if (busy.value) return;
  busy.value = true;
  error.value = '';
  try {
    await api.post(path, body);
    await poll();
    if (tab.value === 'find' && q.value.trim().length >= 2) await runSearch();
    if (tab.value === 'leaderboard') await loadLeaderboard();
  } catch (e) {
    error.value = e.message;
  } finally {
    busy.value = false;
  }
}
const addFriend = (u) => mutate('/api/social/send', { target_id: u.user_id });
const acceptReq = (r) => mutate('/api/social/accept', { request_id: r.request_id });
const rejectReq = (r) => mutate('/api/social/reject', { request_id: r.request_id });
const cancelReq = (r) => mutate('/api/social/cancel', { request_id: r.request_id });

// A search result with relationship 'pending_in' has no request_id of its own;
// resolve it from the incoming list (kept fresh by poll). If we can't find it
// (stale poll), fall back to the Pending tab where it's actionable.
function acceptFromSearch(u) {
  const match = pendingIn.value.find((r) => r.user_id === u.user_id);
  if (match) acceptReq(match);
  else tab.value = 'pending';
}
function removeFriend(u) {
  if (!confirm(`Remove ${u.user_name} from friends?`)) return;
  mutate('/api/social/unfriend', { target_id: u.user_id });
}

const pendingInCount = computed(() => pendingIn.value.length);
const initials = (name) => (name || '?').slice(0, 1).toUpperCase();
</script>

<template>
  <main class="wrap">
    <h1 class="title">Friends</h1>

    <!-- Tabs -->
    <div class="tabs" role="tablist">
      <button class="tab" :class="{ on: tab === 'friends' }" @click="tab = 'friends'">
        Friends<span v-if="friends.length" class="count">{{ friends.length }}</span>
      </button>
      <button class="tab" :class="{ on: tab === 'pending' }" @click="tab = 'pending'">
        Pending<span v-if="pendingInCount" class="count alert">{{ pendingInCount }}</span>
      </button>
      <button class="tab" :class="{ on: tab === 'leaderboard' }" @click="tab = 'leaderboard'">Ranks</button>
      <button class="tab" :class="{ on: tab === 'find' }" @click="tab = 'find'">Find</button>
    </div>

    <p v-if="error" class="error">{{ error }}</p>

    <!-- FRIENDS -->
    <section v-if="tab === 'friends'">
      <p v-if="loading" class="muted">Loading…</p>
      <p v-else-if="!friends.length" class="muted empty">
        No friends yet. Head to <button class="link" @click="tab = 'find'">Find</button> to add some.
      </p>
      <ul v-else class="list">
        <li v-for="u in friends" :key="u.user_id" class="row card">
          <span class="avatar"><img v-if="u.profile_image" :src="u.profile_image" alt="" /><span v-else>{{ initials(u.user_name) }}</span></span>
          <span class="meta">
            <strong>{{ u.user_name }}</strong>
            <small class="muted">Lv {{ u.current_level }} · <i class="fa-solid fa-fire" /> {{ u.logging_streak }}d · {{ u.weekly_xp }} XP/wk</small>
          </span>
          <button class="btn ghost danger" :disabled="busy" @click="removeFriend(u)">Remove</button>
        </li>
      </ul>
    </section>

    <!-- PENDING -->
    <section v-else-if="tab === 'pending'">
      <p v-if="loading" class="muted">Loading…</p>
      <template v-else>
        <h2 class="sub">Requests for you</h2>
        <p v-if="!pendingIn.length" class="muted empty">No incoming requests.</p>
        <ul v-else class="list">
          <li v-for="r in pendingIn" :key="r.request_id" class="row card">
            <span class="avatar"><img v-if="r.profile_image" :src="r.profile_image" alt="" /><span v-else>{{ initials(r.user_name) }}</span></span>
            <span class="meta">
              <strong>{{ r.user_name }}</strong>
              <small class="muted">Lv {{ r.current_level }} · <i class="fa-solid fa-fire" /> {{ r.logging_streak }}d</small>
            </span>
            <span class="actions">
              <button class="btn primary" :disabled="busy" @click="acceptReq(r)">Accept</button>
              <button class="btn ghost" :disabled="busy" @click="rejectReq(r)">Decline</button>
            </span>
          </li>
        </ul>

        <h2 class="sub">Requests you sent</h2>
        <p v-if="!pendingOut.length" class="muted empty">Nothing pending.</p>
        <ul v-else class="list">
          <li v-for="r in pendingOut" :key="r.request_id" class="row card">
            <span class="avatar"><img v-if="r.profile_image" :src="r.profile_image" alt="" /><span v-else>{{ initials(r.user_name) }}</span></span>
            <span class="meta">
              <strong>{{ r.user_name }}</strong>
              <small class="muted">Waiting…</small>
            </span>
            <button class="btn ghost" :disabled="busy" @click="cancelReq(r)">Cancel</button>
          </li>
        </ul>
      </template>
    </section>

    <!-- LEADERBOARD -->
    <section v-else-if="tab === 'leaderboard'">
      <div class="seg">
        <button class="seg-btn" :class="{ on: period === 'weekly' }" @click="period = 'weekly'">This week</button>
        <button class="seg-btn" :class="{ on: period === 'all_time' }" @click="period = 'all_time'">All time</button>
      </div>
      <p v-if="lbLoading" class="muted">Loading…</p>
      <p v-else-if="leaders.length <= 1" class="muted empty">
        Add friends to see how you stack up. Go to <button class="link" @click="tab = 'find'">Find</button>.
      </p>
      <ul v-else class="list">
        <li v-for="u in leaders" :key="u.user_id" class="row card" :class="{ me: u.is_current_user }">
          <span class="rank" :class="'r' + u.rank">{{ u.rank }}</span>
          <span class="avatar"><img v-if="u.profile_image" :src="u.profile_image" alt="" /><span v-else>{{ initials(u.user_name) }}</span></span>
          <span class="meta">
            <strong>{{ u.user_name }}<small v-if="u.is_current_user" class="you"> (you)</small></strong>
            <small class="muted">Lv {{ u.current_level }} · <i class="fa-solid fa-fire" /> {{ u.logging_streak }}d</small>
          </span>
          <strong class="score">{{ u.score_xp }}<small class="muted"> XP</small></strong>
        </li>
      </ul>
    </section>

    <!-- FIND -->
    <section v-else-if="tab === 'find'">
      <input
        v-model="q"
        class="search"
        type="search"
        placeholder="Search by username (2+ characters)"
        @input="onSearchInput"
        aria-label="Search people by username"
      />
      <p v-if="searching" class="muted">Searching…</p>
      <p v-else-if="q.trim().length >= 2 && !results.length" class="muted empty">No users found.</p>
      <ul v-else-if="results.length" class="list">
        <li v-for="u in results" :key="u.user_id" class="row card">
          <span class="avatar"><img v-if="u.profile_image" :src="u.profile_image" alt="" /><span v-else>{{ initials(u.user_name) }}</span></span>
          <span class="meta">
            <strong>{{ u.user_name }}</strong>
            <small class="muted">Lv {{ u.current_level }} · <i class="fa-solid fa-fire" /> {{ u.logging_streak }}d</small>
          </span>
          <!-- CTA depends on the relationship the API annotates each result with. -->
          <button v-if="u.relationship === 'none'" class="btn primary" :disabled="busy" @click="addFriend(u)">Add</button>
          <button v-else-if="u.relationship === 'pending_in'" class="btn primary" :disabled="busy"
            @click="acceptFromSearch(u)">Accept</button>
          <span v-else-if="u.relationship === 'pending_out'" class="tag muted">Pending</span>
          <span v-else-if="u.relationship === 'friends'" class="tag ok">Friends</span>
          <span v-else class="tag muted">—</span>
        </li>
      </ul>
    </section>
  </main>
</template>

<style scoped>
.wrap { max-width: 720px; margin: 0 auto; padding: 8px 16px 24px; }
.title { font-size: 22px; margin: 6px 0 12px; }
.muted { color: var(--muted); }
.empty { padding: 18px 4px; }
.error { color: #f87171; margin: 8px 0; }

/* Tabs */
.tabs { display: flex; gap: 6px; margin-bottom: 14px; }
.tab {
  flex: 1; min-height: 44px; padding: 8px 6px; border-radius: 10px;
  background: var(--card); border: 1px solid var(--border); color: var(--muted);
  font-weight: 700; font-size: 13px; display: inline-flex; align-items: center; justify-content: center; gap: 6px;
}
.tab.on { color: var(--accent); border-color: var(--accent); background: #12151b; }
.count {
  min-width: 18px; height: 18px; padding: 0 5px; border-radius: 999px;
  background: #2a2e37; color: var(--text); font-size: 11px; display: grid; place-items: center;
}
.count.alert { background: #ef4444; color: #fff; }

/* Rows */
.list { list-style: none; padding: 0; margin: 0; display: flex; flex-direction: column; gap: 8px; }
.row { display: flex; align-items: center; gap: 12px; padding: 10px 12px; }
.row .meta { flex: 1; min-width: 0; display: flex; flex-direction: column; gap: 2px; }
.row .meta strong { white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.row .meta small { font-size: 12px; }
.row.me { border-color: var(--accent); }

.avatar {
  flex: none; width: 40px; height: 40px; border-radius: 50%; overflow: hidden;
  background: #2a2e37; color: var(--text); font-weight: 800;
  display: grid; place-items: center;
}
.avatar img { width: 100%; height: 100%; object-fit: cover; }

/* Buttons */
.actions { display: flex; gap: 6px; }
.btn {
  min-height: 40px; padding: 0 14px; border-radius: 10px; font-weight: 700; font-size: 13px;
  border: 1px solid transparent; white-space: nowrap;
}
.btn.primary { background: var(--accent); color: #04210f; }
.btn.ghost { background: #2a2e37; color: var(--text); }
.btn.ghost.danger { color: #f87171; }
.btn:disabled { opacity: 0.5; }
.link { background: none; border: none; color: var(--accent); font-weight: 700; padding: 0; min-height: 0; text-decoration: underline; }

.tag { font-size: 12px; font-weight: 700; padding: 6px 10px; border-radius: 8px; }
.tag.ok { color: var(--accent); }

/* Leaderboard */
.seg { display: flex; gap: 6px; margin-bottom: 12px; }
.seg-btn {
  flex: 1; min-height: 40px; border-radius: 10px; font-weight: 700; font-size: 13px;
  background: var(--card); border: 1px solid var(--border); color: var(--muted);
}
.seg-btn.on { color: var(--accent); border-color: var(--accent); background: #12151b; }
.rank {
  flex: none; width: 26px; text-align: center; font-weight: 800; color: var(--muted);
}
.rank.r1 { color: #fbbf24; }
.rank.r2 { color: #cbd5e1; }
.rank.r3 { color: #d8924e; }
.score { white-space: nowrap; }
.you { color: var(--accent); font-weight: 700; }

.sub { font-size: 13px; text-transform: uppercase; letter-spacing: 0.04em; color: var(--muted); margin: 16px 0 8px; }
.sub:first-child { margin-top: 0; }

/* Search */
.search { width: 100%; margin-bottom: 12px; }
</style>
