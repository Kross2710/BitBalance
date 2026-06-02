<script setup>
// "My Trainer" segment of the Coach Hub — the client's view of their personal
// trainer: a trainer card, a pending goal-proposal to accept/decline, two-way
// chat (shared ChatRoom), and advice (feedback) history. Ports dashboard-coach.php
// + respond_goal_proposal.php to the /api/pt/* client endpoints.
import { ref, computed, onMounted } from 'vue';
import { api } from '../lib/api.js';
import ChatRoom from './ChatRoom.vue';
import TrainerDirectory from './TrainerDirectory.vue';

const loading = ref(true);
const error = ref('');
const trainer = ref(null);
const pending = ref(null); // outgoing request awaiting approval
const feedback = ref([]);
const proposal = ref(null);
const tab = ref('chat'); // 'chat' | 'advice'
const proposalBusy = ref(false);
const reqBusy = ref(false);
const goalDone = ref(false); // brief confirmation after accepting

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
  return `P ${p.protein_goal}g · C ${p.carbs_goal}g · F ${p.fat_goal}g`;
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

onMounted(load);
</script>

<template>
  <div class="trainer">
    <p v-if="loading" class="muted center pad">Loading…</p>

    <!-- Pending outgoing request -->
    <div v-else-if="pending" class="placeholder">
      <div class="avatar empty"><i class="fa-solid fa-hourglass-half" /></div>
      <h2>Request sent</h2>
      <p class="muted">Waiting for <strong>{{ pendingName }}</strong> to accept your request.</p>
      <p v-if="error" class="error">{{ error }}</p>
      <button class="ghost" :disabled="reqBusy" @click="cancelRequest">Cancel request</button>
    </div>

    <!-- No trainer → browse the directory -->
    <TrainerDirectory v-else-if="!trainer" @requested="load" />

    <!-- Trainer connected -->
    <template v-else>
      <!-- Hero -->
      <div class="hero">
        <span class="avatar">
          <img v-if="trainer.profile_image" :src="trainer.profile_image" alt="" />
          <span v-else class="initial">{{ trainerInitial }}</span>
        </span>
        <div class="hero-meta">
          <span class="kicker">Your trainer</span>
          <strong class="name">{{ trainerName }}</strong>
          <span class="handle muted">@{{ trainer.user_name }}</span>
          <div v-if="specialties.length || trainer.experience_years != null" class="chips">
            <span v-for="s in specialties" :key="s" class="chip">{{ s }}</span>
            <span v-if="trainer.experience_years != null" class="chip alt">{{ trainer.experience_years }}y exp</span>
          </div>
        </div>
      </div>

      <p v-if="error" class="error pad">{{ error }}</p>
      <p v-if="goalDone" class="note pad"><i class="fa-solid fa-check" /> Goal updated from your trainer.</p>

      <!-- Pending goal proposal -->
      <div v-if="proposal" class="proposal">
        <div class="proposal-head">
          <i class="fa-solid fa-bullseye" />
          <strong>Goal proposal</strong>
        </div>
        <p class="proposal-body">
          {{ trainerName }} suggests <strong>{{ proposal.calorie_goal }} kcal/day</strong>
          <span v-if="macroLine(proposal)" class="muted"> · {{ macroLine(proposal) }}</span>
        </p>
        <p v-if="proposal.note" class="proposal-note muted">“{{ proposal.note }}”</p>
        <div class="proposal-actions">
          <button class="accept" :disabled="proposalBusy" @click="respondProposal('accept')">Accept</button>
          <button class="decline" :disabled="proposalBusy" @click="respondProposal('decline')">Decline</button>
        </div>
      </div>

      <!-- Tabs -->
      <div class="mt-tabs" role="tablist">
        <button class="mt-tab" :class="{ on: tab === 'chat' }" @click="tab = 'chat'">Chat</button>
        <button class="mt-tab" :class="{ on: tab === 'advice' }" @click="tab = 'advice'">
          Advice<span v-if="feedback.length" class="count">{{ feedback.length }}</span>
        </button>
      </div>

      <!-- Chat (shared room) -->
      <ChatRoom v-show="tab === 'chat'" path="/api/pt/messages" my-role="client" placeholder="Message your trainer…" />

      <!-- Advice -->
      <div v-show="tab === 'advice'" class="advice">
        <p v-if="!feedback.length" class="muted center pad">No advice from your trainer yet.</p>
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
  background: var(--accent); color: #04210f; font-weight: 700;
  padding: 10px 18px; border-radius: 8px;
}
.ghost { margin-top: 14px; background: var(--card); color: var(--text); border: 1px solid var(--border); }

/* Hero */
.hero { flex: none; display: flex; gap: 12px; align-items: center; padding: 4px 2px 12px; }
.hero .avatar {
  flex: none; width: 52px; height: 52px; border-radius: 50%; overflow: hidden;
  display: grid; place-items: center; background: var(--accent); color: #04210f;
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
.mt-tab.on { color: var(--accent); border-color: var(--accent); background: #12151b; }
.count {
  min-width: 18px; height: 18px; padding: 0 5px; border-radius: 999px;
  background: #2a2e37; color: var(--text); font-size: 11px; display: grid; place-items: center;
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
