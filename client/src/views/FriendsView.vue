<script setup>
import { ref, computed, onMounted, onUnmounted, watch } from 'vue';
import { api } from '../lib/api.js';
import { t } from '../i18n/index.js';
import FriendProfileSheet from '../components/FriendProfileSheet.vue';
import ConfirmDialog from '../components/ConfirmDialog.vue';

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
    const d = await api.get('/api/social/poll', { background: true });
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
  loadLeaderboard(); // pre-warm: powers the hero tiles + the Ranks tab
  timer = setInterval(poll, 15000);
});
onUnmounted(() => clearInterval(timer));

// --- Leaderboard --------------------------------------------------------------
const leaders = ref([]);
const period = ref('weekly');
const lbLoading = ref(false);
// The weekly self-row, captured once so the hero tiles (rank + weekly XP) stay
// put even if the Ranks toggle flips to all-time.
const meWeekly = ref(null);

async function loadLeaderboard() {
  lbLoading.value = true;
  try {
    // Background: triggered by switching to the Ranks tab / toggling the period,
    // and has its own lbLoading indicator — must not flash the global bar.
    const d = await api.get(`/api/social/leaderboard?period=${period.value}`, { background: true });
    leaders.value = d.leaders;
    if (period.value === 'weekly') meWeekly.value = d.leaders.find((u) => u.is_current_user) || null;
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
    // Background: search-as-you-type with its own `searching` indicator.
    const d = await api.post('/api/social/search', { q: term }, { background: true });
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
// --- Per-row kebab menu (Friends list) ---------------------------------------
const openMenuId = ref(null);
const toggleMenu = (id) => {
  openMenuId.value = openMenuId.value === id ? null : id;
};

// --- Profile peek sheet -------------------------------------------------------
const profileOpen = ref(false);
const profileUserId = ref(null);
function openProfile(userId) {
  openMenuId.value = null;
  profileUserId.value = userId;
  profileOpen.value = true;
}
function onProfileChanged() {
  poll();
  if (tab.value === 'leaderboard') loadLeaderboard();
}

// --- Remove friend (via ConfirmDialog; native confirm() retired) -------------
const confirmOpen = ref(false);
const pendingRemove = ref(null);
function askRemove(u) {
  pendingRemove.value = u;
  openMenuId.value = null;
  profileOpen.value = false; // don't stack the dialog under the sheet
  confirmOpen.value = true;
}
async function confirmRemove() {
  const u = pendingRemove.value;
  if (!u) return;
  await mutate('/api/social/unfriend', { target_id: u.user_id });
  confirmOpen.value = false;
  pendingRemove.value = null;
}
function cancelRemove() {
  confirmOpen.value = false;
  pendingRemove.value = null;
}

const pendingInCount = computed(() => pendingIn.value.length);
const initials = (name) => (name || '?').slice(0, 1).toUpperCase();

// Empty-state sentences embed a "Find" button mid-sentence. We can't use
// v-html (no escaping rule), so split the localized string on {link} and render
// the button between the two text halves. Reactive via computed → re-splits on
// locale switch.
const splitOnLink = (key) => {
  const [before = '', after = ''] = t(key).split('{link}');
  return { before, after };
};
const noFriendsParts = computed(() => splitOnLink('friends.empty.no_friends_inline'));
const soloLbParts = computed(() => splitOnLink('friends.lb.solo_inline'));
</script>

<template>
  <main class="wrap">
    <h1 class="title">{{ $t('friends.hero.title') }}</h1>

    <!-- Hero metrics: always present so the page never reads as empty. -->
    <div class="hero-metrics">
      <div class="hm">
        <strong v-if="!loading">{{ friends.length }}</strong>
        <span v-else class="sk sk-num" aria-hidden="true" />
        <small>{{ $t('friends.metric.friends') }}</small>
      </div>
      <div class="hm">
        <strong v-if="meWeekly">#{{ meWeekly.rank }}</strong>
        <span v-else-if="lbLoading" class="sk sk-num" aria-hidden="true" />
        <strong v-else>—</strong>
        <small>{{ $t('friends.metric.rank') }}</small>
      </div>
      <div class="hm">
        <strong v-if="meWeekly">{{ meWeekly.weekly_xp }}</strong>
        <span v-else-if="lbLoading" class="sk sk-num" aria-hidden="true" />
        <strong v-else>—</strong>
        <small>{{ $t('friends.metric.weekly_xp') }}</small>
      </div>
    </div>

    <!-- Tabs -->
    <div class="tabs" role="tablist">
      <button class="tab" :class="{ on: tab === 'friends' }" @click="tab = 'friends'">
        {{ $t('friends.metric.friends') }}<span v-if="friends.length" class="count">{{ friends.length }}</span>
      </button>
      <button class="tab" :class="{ on: tab === 'pending' }" @click="tab = 'pending'">
        {{ $t('friends.tab.pending') }}<span v-if="pendingInCount" class="count alert">{{ pendingInCount }}</span>
      </button>
      <button class="tab" :class="{ on: tab === 'leaderboard' }" @click="tab = 'leaderboard'">{{ $t('friends.tab.ranks') }}</button>
      <button class="tab" :class="{ on: tab === 'find' }" @click="tab = 'find'">{{ $t('friends.tab.find_short') }}</button>
    </div>

    <p v-if="error" class="error">{{ error }}</p>

    <!-- Tab panels: a quick fade/slide on switch. Each section is keyed so the
         same-tag <section> elements actually transition (Vue would otherwise
         patch them in place with no animation). out-in avoids height overlap. -->
    <Transition name="tab" mode="out-in">
    <!-- FRIENDS -->
    <section v-if="tab === 'friends'" key="friends">
      <p v-if="loading" class="muted">{{ $t('common.loading') }}</p>
      <p v-else-if="!friends.length" class="muted empty">
        {{ noFriendsParts.before }}<button class="link" @click="tab = 'find'">{{ $t('friends.tab.find_short') }}</button>{{ noFriendsParts.after }}
      </p>
      <ul v-else class="list">
        <li v-for="u in friends" :key="u.user_id" class="row card">
          <button type="button" class="row-tap" @click="openProfile(u.user_id)">
            <span class="avatar"><img v-if="u.profile_image" :src="u.profile_image" alt="" /><span v-else>{{ initials(u.user_name) }}</span></span>
            <span class="meta">
              <strong>{{ u.user_name }}</strong>
              <small class="muted">{{ $t('friends.card.level_short') }} {{ u.current_level }} · <i class="fa-solid fa-fire" :class="{ lit: u.logging_streak > 0 }" /> {{ u.logging_streak }}{{ $t('friends.card.day_short') }} · {{ $t('friends.card.weekly_xp', { n: u.weekly_xp }) }}</small>
            </span>
          </button>
          <div class="kebab-wrap">
            <button class="icon-btn" :aria-label="$t('friends.menu.aria')" :disabled="busy" @click.stop="toggleMenu(u.user_id)">
              <i class="fa-solid fa-ellipsis" />
            </button>
            <div v-if="openMenuId === u.user_id" class="menu" @click.stop>
              <button class="menu-item" @click="openProfile(u.user_id)"><i class="fa-solid fa-user" /> {{ $t('friends.menu.view_profile') }}</button>
              <button class="menu-item danger" @click="askRemove(u)"><i class="fa-solid fa-user-xmark" /> {{ $t('friends.menu.remove') }}</button>
            </div>
          </div>
        </li>
      </ul>
      <div v-if="openMenuId !== null" class="menu-scrim" @click="openMenuId = null" />
    </section>

    <!-- PENDING -->
    <section v-else-if="tab === 'pending'" key="pending">
      <p v-if="loading" class="muted">{{ $t('common.loading') }}</p>
      <template v-else>
        <h2 class="sub">{{ $t('friends.subhead.requests_in') }}</h2>
        <p v-if="!pendingIn.length" class="muted empty">{{ $t('friends.empty.no_incoming') }}</p>
        <ul v-else class="list">
          <li v-for="r in pendingIn" :key="r.request_id" class="row card">
            <button type="button" class="row-tap" @click="openProfile(r.user_id)">
              <span class="avatar"><img v-if="r.profile_image" :src="r.profile_image" alt="" /><span v-else>{{ initials(r.user_name) }}</span></span>
              <span class="meta">
                <strong>{{ r.user_name }}</strong>
                <small class="muted">{{ $t('friends.card.level_short') }} {{ r.current_level }} · <i class="fa-solid fa-fire" :class="{ lit: r.logging_streak > 0 }" /> {{ r.logging_streak }}{{ $t('friends.card.day_short') }}</small>
              </span>
            </button>
            <span class="actions">
              <button class="btn primary" :disabled="busy" @click="acceptReq(r)">{{ $t('friends.card.btn_accept') }}</button>
              <button class="btn ghost" :disabled="busy" @click="rejectReq(r)">{{ $t('friends.card.btn_decline') }}</button>
            </span>
          </li>
        </ul>

        <h2 class="sub">{{ $t('friends.subhead.requests_out') }}</h2>
        <p v-if="!pendingOut.length" class="muted empty">{{ $t('friends.empty.no_outgoing') }}</p>
        <ul v-else class="list">
          <li v-for="r in pendingOut" :key="r.request_id" class="row card">
            <button type="button" class="row-tap" @click="openProfile(r.user_id)">
              <span class="avatar"><img v-if="r.profile_image" :src="r.profile_image" alt="" /><span v-else>{{ initials(r.user_name) }}</span></span>
              <span class="meta">
                <strong>{{ r.user_name }}</strong>
                <small class="muted">{{ $t('friends.card.hint_waiting') }}</small>
              </span>
            </button>
            <button class="btn ghost" :disabled="busy" @click="cancelReq(r)">{{ $t('friends.card.btn_cancel') }}</button>
          </li>
        </ul>
      </template>
    </section>

    <!-- LEADERBOARD -->
    <section v-else-if="tab === 'leaderboard'" key="leaderboard">
      <div class="seg">
        <button class="seg-btn" :class="{ on: period === 'weekly' }" @click="period = 'weekly'">{{ $t('friends.leaderboard.range.week') }}</button>
        <button class="seg-btn" :class="{ on: period === 'all_time' }" @click="period = 'all_time'">{{ $t('friends.leaderboard.range.all') }}</button>
      </div>
      <p v-if="lbLoading && !leaders.length" class="muted">{{ $t('common.loading') }}</p>
      <template v-else>
        <!-- Always render the rows: the API returns the user's own row (rank 1)
             even with no friends, so the board is never blank. Keyed by period so
             toggling This week / All time animates the swap; the list is kept during
             the quick background refetch (no loading flash). -->
        <Transition name="tab" mode="out-in">
          <ul v-if="leaders.length" :key="period" class="list">
          <li v-for="u in leaders" :key="u.user_id" class="row card" :class="{ me: u.is_current_user }">
            <span class="rank" :class="'r' + u.rank">{{ u.rank }}</span>
            <button type="button" class="row-tap" @click="openProfile(u.user_id)">
              <span class="avatar"><img v-if="u.profile_image" :src="u.profile_image" alt="" /><span v-else>{{ initials(u.user_name) }}</span></span>
              <span class="meta">
                <strong>{{ u.user_name }}<small v-if="u.is_current_user" class="you"> {{ $t('friends.lb.you_paren') }}</small></strong>
                <small class="muted">{{ $t('friends.card.level_short') }} {{ u.current_level }} · <i class="fa-solid fa-fire" :class="{ lit: u.logging_streak > 0 }" /> {{ u.logging_streak }}{{ $t('friends.card.day_short') }}</small>
              </span>
            </button>
            <strong class="score">{{ u.score_xp }}<small class="muted"> {{ $t('friends.col.xp') }}</small></strong>
          </li>
          </ul>
        </Transition>
        <!-- Solo nudge sits BELOW the self-row instead of replacing it. -->
        <p v-if="leaders.length <= 1" class="muted empty">
          {{ soloLbParts.before }}<button class="link" @click="tab = 'find'">{{ $t('friends.tab.find_short') }}</button>{{ soloLbParts.after }}
        </p>
      </template>
    </section>

    <!-- FIND -->
    <section v-else-if="tab === 'find'" key="find">
      <input
        v-model="q"
        class="search"
        type="search"
        :placeholder="$t('friends.find.placeholder')"
        @input="onSearchInput"
        :aria-label="$t('friends.find.aria')"
      />
      <div v-if="q.trim().length < 2" class="find-empty">
        <i class="fa-solid fa-user-plus" />
        <strong>{{ $t('friends.find.empty_title') }}</strong>
        <p class="muted">{{ $t('friends.find.empty_hint') }}</p>
      </div>
      <p v-else-if="searching" class="muted">{{ $t('friends.find.searching') }}</p>
      <p v-else-if="!results.length" class="muted empty">{{ $t('friends.find.no_results') }}</p>
      <ul v-else class="list">
        <li v-for="u in results" :key="u.user_id" class="row card">
          <button type="button" class="row-tap" @click="openProfile(u.user_id)">
            <span class="avatar"><img v-if="u.profile_image" :src="u.profile_image" alt="" /><span v-else>{{ initials(u.user_name) }}</span></span>
            <span class="meta">
              <strong>{{ u.user_name }}</strong>
              <small class="muted">{{ $t('friends.card.level_short') }} {{ u.current_level }} · <i class="fa-solid fa-fire" :class="{ lit: u.logging_streak > 0 }" /> {{ u.logging_streak }}{{ $t('friends.card.day_short') }}</small>
            </span>
          </button>
          <!-- CTA depends on the relationship the API annotates each result with. -->
          <button v-if="u.relationship === 'none'" class="btn primary" :disabled="busy" @click="addFriend(u)">{{ $t('friends.card.btn_add') }}</button>
          <button v-else-if="u.relationship === 'pending_in'" class="btn primary" :disabled="busy"
            @click="acceptFromSearch(u)">{{ $t('friends.card.btn_accept') }}</button>
          <span v-else-if="u.relationship === 'pending_out'" class="tag muted">{{ $t('friends.card.hint_pending') }}</span>
          <span v-else-if="u.relationship === 'friends'" class="tag ok">{{ $t('friends.card.hint_friends') }}</span>
          <span v-else class="tag muted">—</span>
        </li>
      </ul>
    </section>
    </Transition>

    <!-- Profile peek (tap any row) + remove confirm (kebab / sheet) -->
    <FriendProfileSheet
      :open="profileOpen"
      :user-id="profileUserId"
      @close="profileOpen = false"
      @changed="onProfileChanged"
      @remove="askRemove"
    />
    <ConfirmDialog
      :open="confirmOpen"
      :title="$t('friends.menu.remove')"
      :message="pendingRemove ? $t('friends.confirm_remove_named', { name: pendingRemove.user_name }) : ''"
      :confirm-label="$t('friends.card.btn_remove')"
      :busy="busy"
      @confirm="confirmRemove"
      @cancel="cancelRemove"
    />
  </main>
</template>

<style scoped>
.wrap { max-width: 720px; margin: 0 auto; padding: 8px 16px 24px; }
.title { font-size: 22px; margin: 6px 0 12px; }

/* Hero metrics */
.hero-metrics { display: flex; gap: 8px; margin-bottom: 14px; }
.hm {
  flex: 1; background: var(--card); border: 1px solid var(--border);
  border-radius: 10px; padding: 10px 8px; text-align: center;
  display: flex; flex-direction: column; gap: 2px;
}
.hm strong { font-size: 18px; font-weight: 800; }
.hm small { color: var(--muted); font-size: 11px; }
/* Placeholder for a hero tile value before the first poll / leaderboard resolves
   (avoids flashing a misleading "0" / "—"). Same fixed tile height, no shimmer. */
.sk { background: var(--inset); border: 1px solid var(--border); border-radius: 8px; }
.hm .sk-num { align-self: center; width: 30px; height: 20px; margin: 1px 0; }
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
.tab.on { color: var(--accent); border-color: var(--accent); background: var(--inset); }
.count {
  min-width: 18px; height: 18px; padding: 0 5px; border-radius: 999px;
  background: var(--surface-2); color: var(--text); font-size: 11px; display: grid; place-items: center;
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
  background: var(--surface-2); color: var(--text); font-weight: 800;
  display: grid; place-items: center;
}
.avatar img { width: 100%; height: 100%; object-fit: cover; }

/* Buttons */
.actions { display: flex; gap: 6px; }
.btn {
  min-height: 40px; padding: 0 14px; border-radius: 10px; font-weight: 700; font-size: 13px;
  border: 1px solid transparent; white-space: nowrap;
}
.btn.primary { background: var(--accent); color: var(--on-accent); }
.btn.ghost { background: var(--surface-2); color: var(--text); }
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
.seg-btn.on { color: var(--accent); border-color: var(--accent); background: var(--inset); }
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

/* Tappable row body — opens the profile peek. Strips button chrome so it reads
   as the row itself; the avatar + meta keep their own styles inside it. */
.row-tap {
  flex: 1; min-width: 0; display: flex; align-items: center; gap: 12px;
  background: none; border: none; padding: 0; margin: 0;
  text-align: left; color: inherit; font: inherit; cursor: pointer; min-height: 0;
}
.row-tap:hover .meta strong { color: var(--accent); }

/* Lit logging-streak flame (grey at 0, warm when active). */
.lit { color: var(--streak); }

/* Kebab menu (Friends list) */
.kebab-wrap { position: relative; flex: none; }
.icon-btn {
  width: 40px; height: 40px; min-height: 0; padding: 0; border-radius: 10px;
  background: var(--surface-2); color: var(--muted); border: 1px solid transparent;
  display: grid; place-items: center; cursor: pointer;
}
.icon-btn:hover { color: var(--text); }
.icon-btn:disabled { opacity: 0.5; }
.menu {
  position: absolute; right: 0; top: calc(100% + 6px); z-index: 20;
  min-width: 172px; background: var(--card); border: 1px solid var(--field-border);
  border-radius: 10px; padding: 6px; box-shadow: 0 8px 24px rgba(0, 0, 0, 0.4);
  display: flex; flex-direction: column; gap: 2px;
}
.menu-item {
  display: flex; align-items: center; gap: 10px; width: 100%; min-height: 40px;
  padding: 8px 10px; border-radius: 8px; background: none; border: none;
  color: var(--text); font-weight: 600; font-size: 13px; text-align: left; cursor: pointer;
}
.menu-item i { width: 16px; text-align: center; color: var(--muted); }
.menu-item:hover { background: var(--inset); }
.menu-item.danger, .menu-item.danger i { color: var(--danger); }
.menu-scrim { position: fixed; inset: 0; z-index: 10; }

/* Find empty-state */
.find-empty {
  text-align: center; padding: 36px 16px;
  display: flex; flex-direction: column; align-items: center; gap: 10px;
}
.find-empty > i { font-size: 32px; color: var(--muted); }
.find-empty strong { font-size: 16px; }
.find-empty p { margin: 0; max-width: 320px; font-size: 13px; line-height: 1.5; }

/* Tab panel switch — quick fade + slight slide (out-in, so variable-height
   panels never overlap). Replaces the loading-bar flash on tab change. */
.tab-enter-active,
.tab-leave-active { transition: opacity 0.16s ease, transform 0.16s ease; }
.tab-enter-from { opacity: 0; transform: translateY(6px); }
.tab-leave-to { opacity: 0; transform: translateY(-6px); }
@media (prefers-reduced-motion: reduce) {
  .tab-enter-active,
  .tab-leave-active { transition: opacity 0.12s ease; }
  .tab-enter-from,
  .tab-leave-to { transform: none; }
}
</style>
