<script setup>
// "My Trainer" segment of the Coach Hub — the client's view of their personal
// trainer: a trainer card, a pending goal-proposal to accept/decline, two-way
// chat (shared ChatRoom), and advice (feedback) history. Ports dashboard-coach.php
// + respond_goal_proposal.php to the /api/pt/* client endpoints.
import { ref, computed, onMounted } from 'vue';
import { api } from '../lib/api.js';
import { t } from '../i18n/index.js';
import ChatRoom from './ChatRoom.vue';
import TrainerDirectory from './TrainerDirectory.vue';

const loading = ref(true);
const error = ref('');
const trainer = ref(null);
const pending = ref(null); // outgoing request awaiting approval
const invites = ref([]); // incoming invites from trainers (PT-initiated)
const feedback = ref([]);
const proposal = ref(null);
const tab = ref('chat'); // 'chat' | 'advice'
const proposalBusy = ref(false);
const reqBusy = ref(false);
const inviteBusy = ref(false);
const goalDone = ref(false); // brief confirmation after accepting
const confirmingDisconnect = ref(false);
const disconnectBusy = ref(false);

const pendingName = computed(() => {
  const p = pending.value;
  if (!p) return '';
  return `${p.first_name ?? ''} ${p.last_name ?? ''}`.trim() || p.user_name;
});

const trainerName = computed(() => {
  const t = trainer.value;
  if (!t) return '';
  return `${t.first_name ?? ''} ${t.last_name ?? ''}`.trim() || t.user_name;
});
const trainerInitial = computed(() => (trainerName.value || 'T').trim().charAt(0).toUpperCase());
const specialties = computed(() =>
  (trainer.value?.specialties || '')
    .split(/[,;]/)
    .map((s) => s.trim())
    .filter(Boolean)
);

async function load() {
  loading.value = true;
  error.value = '';
  try {
    const data = await api.get('/api/pt/my-trainer');
    trainer.value = data.trainer;
    pending.value = data.pending;
    invites.value = data.invites || [];
    feedback.value = data.feedback || [];
    proposal.value = data.proposal;
  } catch (e) {
    error.value = e.message;
  } finally {
    loading.value = false;
  }
}

async function respondProposal(decision) {
  if (!proposal.value || proposalBusy.value) return;
  proposalBusy.value = true;
  error.value = '';
  try {
    await api.post('/api/pt/goal-proposal/respond', { proposal_id: proposal.value.id, decision });
    if (decision === 'accept') goalDone.value = true;
    proposal.value = null;
  } catch (e) {
    error.value = e.message;
  } finally {
    proposalBusy.value = false;
  }
}

function macroLine(p) {
  if (p.protein_goal == null || p.carbs_goal == null || p.fat_goal == null) return null;
  return `${t('intake.macro_abbr.protein')} ${p.protein_goal}g · ${t('intake.macro_abbr.carbs')} ${p.carbs_goal}g · ${t('intake.macro_abbr.fat')} ${p.fat_goal}g`;
}

async function cancelRequest() {
  if (reqBusy.value) return;
  reqBusy.value = true;
  error.value = '';
  try {
    await api.post('/api/pt/request/cancel');
    await load(); // back to the directory
  } catch (e) {
    error.value = e.message;
  } finally {
    reqBusy.value = false;
  }
}

function inviteName(inv) {
  return inv.trainer_name || inv.user_name;
}

async function respondInvite(inv, action) {
  if (inviteBusy.value) return;
  inviteBusy.value = true;
  error.value = '';
  try {
    await api.post('/api/pt/invites/respond', { request_id: inv.request_id, action });
    if (action === 'accept') await load(); // now connected
    else invites.value = invites.value.filter((i) => i.request_id !== inv.request_id);
  } catch (e) {
    error.value = e.message;
  } finally {
    inviteBusy.value = false;
  }
}

async function disconnect() {
  if (disconnectBusy.value) return;
  disconnectBusy.value = true;
  error.value = '';
  try {
    await api.post('/api/pt/disconnect');
    confirmingDisconnect.value = false;
    await load(); // back to the directory
  } catch (e) {
    error.value = e.message;
  } finally {
    disconnectBusy.value = false;
  }
}

onMounted(load);
</script>

<template>
  <div class="trainer">
    <p v-if="loading" class="muted center pad">{{ $t('common.loading') }}</p>

    <!-- Pending outgoing request -->
    <div v-else-if="pending" class="placeholder">
      <div class="avatar empty"><i class="fa-solid fa-hourglass-half" /></div>
      <h2>{{ $t('coach.my_trainer.request_sent') }}</h2>
      <p class="muted">{{ $t('coach.my_trainer.waiting_for', { name: pendingName }) }}</p>
      <p v-if="error" class="error">{{ error }}</p>
      <button class="ghost" :disabled="reqBusy" @click="cancelRequest">{{ $t('coach.my_trainer.cancel_request') }}</button>
    </div>

    <!-- No trainer → incoming invites (if any) + browse the directory -->
    <div v-else-if="!trainer" class="no-trainer">
      <div v-if="invites.length" class="invites">
        <div v-for="inv in invites" :key="inv.request_id" class="invite-card">
          <span class="inv-avatar">
            <img v-if="inv.profile_image" :src="inv.profile_image" alt="" />
            <span v-else>{{ inviteName(inv).charAt(0).toUpperCase() }}</span>
          </span>
          <div class="inv-meta">
            <strong>{{ $t('coach.my_trainer.invite.wants', { name: inviteName(inv) }) }}</strong>
            <span class="muted">@{{ inv.user_name }}</span>
          </div>
          <div class="inv-actions">
            <button class="accept" :disabled="inviteBusy" @click="respondInvite(inv, 'accept')">{{ $t('coach.my_trainer.accept') }}</button>
            <button class="ghost-sm" :disabled="inviteBusy" @click="respondInvite(inv, 'decline')">{{ $t('coach.my_trainer.decline') }}</button>
          </div>
        </div>
      </div>
      <TrainerDirectory @requested="load" />
    </div>

    <!-- Trainer connected -->
    <template v-else>
      <!-- Hero -->
      <div class="hero">
        <span class="avatar">
          <img v-if="trainer.profile_image" :src="trainer.profile_image" alt="" />
          <span v-else class="initial">{{ trainerInitial }}</span>
        </span>
        <div class="hero-meta">
          <span class="kicker">{{ $t('coach.my_trainer.your_trainer') }}</span>
          <strong class="name">{{ trainerName }}</strong>
          <span class="handle muted">@{{ trainer.user_name }}</span>
          <div v-if="specialties.length || trainer.experience_years != null" class="chips">
            <span v-for="s in specialties" :key="s" class="chip">{{ s }}</span>
            <span v-if="trainer.experience_years != null" class="chip alt">{{ $t('coach.my_trainer.exp_years', { n: trainer.experience_years }) }}</span>
          </div>
        </div>
        <button class="hero-action" :aria-label="$t('coach.my_trainer.disconnect_label')" :title="$t('coach.my_trainer.disconnect_title')" @click="confirmingDisconnect = true">
          <i class="fa-solid fa-link-slash" />
        </button>
      </div>

      <!-- Inline confirm before leaving the trainer -->
      <div v-if="confirmingDisconnect" class="confirm-bar">
        <span>{{ $t('coach.my_trainer.confirm_disconnect', { name: trainerName }) }}</span>
        <div class="confirm-actions">
          <button class="danger" :disabled="disconnectBusy" @click="disconnect">{{ $t('coach.my_trainer.disconnect') }}</button>
          <button class="ghost-sm" :disabled="disconnectBusy" @click="confirmingDisconnect = false">{{ $t('common.cancel') }}</button>
        </div>
      </div>

      <p v-if="error" class="error pad">{{ error }}</p>
      <p v-if="goalDone" class="note pad"><i class="fa-solid fa-check" /> {{ $t('coach.my_trainer.goal_updated') }}</p>

      <!-- Pending goal proposal -->
      <div v-if="proposal" class="proposal">
        <div class="proposal-head">
          <i class="fa-solid fa-bullseye" />
          <strong>{{ $t('coach.my_trainer.goal_proposal') }}</strong>
        </div>
        <p class="proposal-body">
          <strong>{{ $t('coach.my_trainer.suggests_goal', { name: trainerName, calories: proposal.calorie_goal }) }}</strong>
          <span v-if="macroLine(proposal)" class="muted"> · {{ macroLine(proposal) }}</span>
        </p>
        <p v-if="proposal.note" class="proposal-note muted">“{{ proposal.note }}”</p>
        <div class="proposal-actions">
          <button class="accept" :disabled="proposalBusy" @click="respondProposal('accept')">{{ $t('coach.my_trainer.accept') }}</button>
          <button class="decline" :disabled="proposalBusy" @click="respondProposal('decline')">{{ $t('coach.my_trainer.decline') }}</button>
        </div>
      </div>

      <!-- Tabs -->
      <div class="mt-tabs" role="tablist">
        <button class="mt-tab" :class="{ on: tab === 'chat' }" @click="tab = 'chat'">{{ $t('coach.my_trainer.chat') }}</button>
        <button class="mt-tab" :class="{ on: tab === 'advice' }" @click="tab = 'advice'">
          {{ $t('coach.my_trainer.advice') }}<span v-if="feedback.length" class="count">{{ feedback.length }}</span>
        </button>
      </div>

      <!-- Chat (shared room) -->
      <ChatRoom v-show="tab === 'chat'" path="/api/pt/messages" my-role="client" :placeholder="$t('coach.my_trainer.chat_placeholder')" />

      <!-- Advice -->
      <div v-show="tab === 'advice'" class="advice">
        <p v-if="!feedback.length" class="muted center pad">{{ $t('coach.my_trainer.no_advice') }}</p>
        <article v-for="(f, i) in feedback" :key="i" class="advice-item">
          <div class="advice-date">{{ f.date_for }}</div>
          <p class="advice-content">{{ f.content }}</p>
        </article>
      </div>
    </template>
  </div>
</template>

<style scoped>
.trainer {
  height: 100%;
  max-width: 1000px;
  margin: 0 auto;
  padding: 8px 16px;
  display: flex;
  flex-direction: column;
  min-height: 0;
}
.muted { color: var(--muted); font-size: 13px; }
.center { text-align: center; }
.pad { padding: 14px; }
.error { color: #f87171; margin: 0; }
.note { color: var(--accent); margin: 0; font-size: 13px; }

/* Empty state */
.placeholder { margin: auto; text-align: center; max-width: 340px; }
.placeholder .avatar.empty {
  width: 56px; height: 56px; border-radius: 50%;
  background: var(--card); border: 1px solid var(--border); color: var(--accent);
  display: grid; place-items: center; font-size: 22px; margin: 0 auto 12px;
}
.placeholder h2 { margin: 0 0 6px; }
.btn-link {
  display: inline-block; margin-top: 14px; text-decoration: none;
  background: var(--accent); color: var(--on-accent); font-weight: 700;
  padding: 10px 18px; border-radius: 8px;
}
.ghost { margin-top: 14px; background: var(--card); color: var(--text); border: 1px solid var(--border); }

/* No-trainer state: incoming invites stacked above the directory */
.no-trainer { height: 100%; display: flex; flex-direction: column; min-height: 0; }
.invites { flex: none; display: flex; flex-direction: column; gap: 8px; margin-bottom: 12px; }
.invite-card {
  display: flex; align-items: center; gap: 12px;
  background: var(--card); border: 1px solid var(--accent); border-radius: 12px; padding: 10px 12px;
}
.inv-avatar {
  flex: none; width: 42px; height: 42px; border-radius: 50%; overflow: hidden;
  display: grid; place-items: center; background: var(--accent); color: var(--on-accent); font-weight: 800;
}
.inv-avatar img { width: 100%; height: 100%; object-fit: cover; }
.inv-meta { flex: 1; min-width: 0; display: flex; flex-direction: column; gap: 2px; }
.inv-meta strong { font-size: 14px; }
.inv-actions { flex: none; display: flex; gap: 8px; }
.inv-actions button { min-height: 36px; padding: 7px 14px; font-size: 13px; }
.no-trainer :deep(.dir) { flex: 1; min-height: 0; }

/* Hero */
.hero { flex: none; display: flex; gap: 12px; align-items: center; padding: 4px 2px 12px; }
.hero .avatar {
  flex: none; width: 52px; height: 52px; border-radius: 50%; overflow: hidden;
  display: grid; place-items: center; background: var(--accent); color: var(--on-accent);
}
.hero .avatar img { width: 100%; height: 100%; object-fit: cover; }
.hero .avatar .initial { font-weight: 800; font-size: 20px; }
.hero-meta { display: flex; flex-direction: column; gap: 1px; min-width: 0; }
.kicker { font-size: 11px; text-transform: uppercase; letter-spacing: 0.05em; color: var(--muted); }
.name { font-size: 17px; }
.handle { font-size: 13px; }
.chips { display: flex; flex-wrap: wrap; gap: 5px; margin-top: 6px; }
.chip {
  font-size: 11px; color: var(--accent); border: 1px solid var(--border);
  border-radius: 999px; padding: 2px 9px;
}
.chip.alt { color: var(--muted); }
.hero-action {
  flex: none; align-self: flex-start; margin-left: auto;
  width: 38px; height: 38px; min-height: 0; padding: 0;
  background: transparent; border: 1px solid var(--border); color: var(--muted);
}
.hero-action:hover { color: #f87171; border-color: #f87171; }

/* Inline disconnect confirm */
.confirm-bar {
  flex: none; display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: 10px;
  background: var(--card); border: 1px solid #f87171; border-radius: 12px;
  padding: 10px 14px; margin-bottom: 10px; font-size: 13px;
}
.confirm-actions { display: flex; gap: 8px; }
.confirm-actions button { min-height: 36px; padding: 7px 14px; font-size: 13px; }
.danger { background: #ef4444; color: #fff; }
.ghost-sm { background: var(--card); color: var(--text); border: 1px solid var(--border); }

/* Proposal */
.proposal {
  flex: none; background: var(--card); border: 1px solid var(--accent);
  border-radius: 12px; padding: 12px 14px; margin-bottom: 10px;
}
.proposal-head { display: flex; align-items: center; gap: 8px; color: var(--accent); margin-bottom: 6px; }
.proposal-head i { font-size: 14px; }
.proposal-body { margin: 0 0 4px; font-size: 14px; }
.proposal-note { margin: 0 0 10px; font-style: italic; }
.proposal-actions { display: flex; gap: 8px; }
.proposal-actions button { min-height: 38px; padding: 8px 18px; font-size: 13px; }
.proposal-actions .decline { background: var(--card); color: var(--text); border: 1px solid var(--border); }

/* Tabs */
.mt-tabs { flex: none; display: flex; gap: 6px; margin-bottom: 10px; }
.mt-tab {
  flex: 1; min-height: 40px; padding: 7px 6px; border-radius: 10px;
  background: var(--card); border: 1px solid var(--border); color: var(--muted);
  font-weight: 700; font-size: 13px; display: inline-flex; align-items: center; justify-content: center; gap: 6px;
}
.mt-tab.on { color: var(--accent); border-color: var(--accent); background: var(--inset); }
.count {
  min-width: 18px; height: 18px; padding: 0 5px; border-radius: 999px;
  background: var(--surface-2); color: var(--text); font-size: 11px; display: grid; place-items: center;
}

/* Advice */
.advice { flex: 1; min-height: 0; overflow-y: auto; display: flex; flex-direction: column; gap: 8px; }
.advice-item { background: var(--card); border: 1px solid var(--border); border-radius: 12px; padding: 12px 14px; }
.advice-date { font-size: 12px; color: var(--accent); font-weight: 700; margin-bottom: 4px; }
.advice-content { margin: 0; font-size: 14px; line-height: 1.5; white-space: pre-wrap; }

@media (max-width: 767px) {
  .trainer { padding: 8px 12px; }
}
</style>
