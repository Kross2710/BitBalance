<script setup>
// "Find clients" tab of the PT workspace — the trainer searches regular users by
// name/handle and invites them; the client then accepts (consent). Counterpart of
// the client-side TrainerDirectory. Strings are plain English for now (the i18n
// pass localizes components separately).
import { ref, watch, onMounted } from 'vue';
import { api } from '../lib/api.js';

const emit = defineEmits(['changed']);

const q = ref('');
const results = ref([]);
const sent = ref([]);
const searching = ref(false);
const busyId = ref(0);
const error = ref('');
let debounce = null;

function name(u) {
  return `${u.first_name ?? ''} ${u.last_name ?? ''}`.trim() || u.user_name;
}

async function runSearch() {
  const term = q.value.trim();
  if (term.length < 2) {
    results.value = [];
    return;
  }
  searching.value = true;
  error.value = '';
  try {
    // Background: type-ahead search with its own `searching` indicator.
    const data = await api.get(`/api/pt/invitable?q=${encodeURIComponent(term)}`, { background: true });
    results.value = data.clients;
  } catch (e) {
    error.value = e.message;
  } finally {
    searching.value = false;
  }
}

async function loadSent() {
  try {
    const data = await api.get('/api/pt/invites/sent');
    sent.value = data.invites;
  } catch (e) {
    error.value = e.message;
  }
}

async function invite(u) {
  if (busyId.value) return;
  busyId.value = u.user_id;
  error.value = '';
  try {
    await api.post('/api/pt/invite', { client_id: u.user_id });
    u.state = 'invited';
    await loadSent();
    emit('changed');
  } catch (e) {
    error.value = e.message;
  } finally {
    busyId.value = 0;
  }
}

async function cancel(userId) {
  if (busyId.value) return;
  busyId.value = userId;
  error.value = '';
  try {
    await api.post('/api/pt/invite/cancel', { client_id: userId });
    const r = results.value.find((x) => x.user_id === userId);
    if (r) r.state = 'invitable';
    await loadSent();
    emit('changed');
  } catch (e) {
    error.value = e.message;
  } finally {
    busyId.value = 0;
  }
}

watch(q, () => {
  clearTimeout(debounce);
  debounce = setTimeout(runSearch, 300);
});

onMounted(loadSent);
</script>

<template>
  <div class="find">
    <input v-model="q" class="search" type="search" :placeholder="$t('trainer.find.search_placeholder')" />
    <p v-if="error" class="error">{{ error }}</p>

    <!-- Search results -->
    <p v-if="searching" class="muted center pad">{{ $t('trainer.find.searching') }}</p>
    <p v-else-if="q.trim().length >= 2 && !results.length" class="muted center pad">{{ $t('trainer.find.no_match', { q }) }}</p>
    <ul v-else-if="results.length" class="rows">
      <li v-for="u in results" :key="u.user_id" class="row">
        <span class="avatar">
          <img v-if="u.profile_image" :src="u.profile_image" alt="" />
          <span v-else>{{ name(u).charAt(0).toUpperCase() }}</span>
        </span>
        <div class="meta">
          <strong>{{ name(u) }}</strong>
          <span class="muted">@{{ u.user_name }}</span>
        </div>
        <button v-if="u.state === 'invitable'" class="invite" :disabled="busyId === u.user_id" @click="invite(u)">
          <i class="fa-solid fa-user-plus" /> {{ $t('trainer.find.invite') }}
        </button>
        <button v-else-if="u.state === 'invited'" class="ghost-sm" :disabled="busyId === u.user_id" @click="cancel(u.user_id)">
          {{ $t('trainer.find.cancel_invite') }}
        </button>
        <span v-else class="state muted">{{ $t('trainer.find.state.' + u.state) }}</span>
      </li>
    </ul>
    <p v-else class="muted center pad">{{ $t('trainer.find.prompt') }}</p>

    <!-- Outstanding invites -->
    <div v-if="sent.length" class="sent">
      <h3>{{ $t('trainer.find.pending_title') }}</h3>
      <ul class="rows">
        <li v-for="u in sent" :key="u.user_id" class="row">
          <span class="avatar">
            <img v-if="u.profile_image" :src="u.profile_image" alt="" />
            <span v-else>{{ name(u).charAt(0).toUpperCase() }}</span>
          </span>
          <div class="meta">
            <strong>{{ name(u) }}</strong>
            <span class="muted">@{{ u.user_name }} · {{ $t('trainer.find.awaiting') }}</span>
          </div>
          <button class="ghost-sm" :disabled="busyId === u.user_id" @click="cancel(u.user_id)">{{ $t('common.cancel') }}</button>
        </li>
      </ul>
    </div>
  </div>
</template>

<style scoped>
.find { height: 100%; overflow-y: auto; display: flex; flex-direction: column; }
.muted { color: var(--muted); font-size: 13px; }
.center { text-align: center; }
.pad { padding: 16px; }
.error { color: #f87171; font-size: 13px; margin: 6px 0; }
.search { flex: none; margin-bottom: 12px; }

.rows { list-style: none; margin: 0; padding: 0; display: flex; flex-direction: column; gap: 8px; }
.row { display: flex; align-items: center; gap: 12px; background: var(--card); border: 1px solid var(--border); border-radius: 12px; padding: 10px 12px; }
.avatar {
  flex: none; width: 40px; height: 40px; border-radius: 50%; overflow: hidden;
  display: grid; place-items: center; background: var(--accent); color: var(--on-accent); font-weight: 800;
}
.avatar img { width: 100%; height: 100%; object-fit: cover; }
.meta { flex: 1; min-width: 0; display: flex; flex-direction: column; gap: 2px; }
.meta strong { white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.invite { flex: none; min-height: 36px; padding: 7px 14px; font-size: 13px; }
.ghost-sm { flex: none; min-height: 36px; padding: 7px 12px; font-size: 13px; background: var(--card); color: var(--text); border: 1px solid var(--border); }
.state { flex: none; font-size: 12px; }

.sent { margin-top: 18px; }
.sent h3 { margin: 0 0 8px; font-size: 14px; }
</style>
