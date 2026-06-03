<script setup>
// Trainer's detail view for one client: 7-day trend + four tabs
// (Diary / Chat / Feedback / Goal). Ports the dashboard-pt.php details drawer
// and the save_feedback / propose_goal actions to /api/pt/clients/:id/*.
import { ref, computed, watch, onMounted } from 'vue';
import { api } from '../lib/api.js';
import { t } from '../i18n/index.js';
import ChatRoom from './ChatRoom.vue';

const props = defineProps({
  client: { type: Object, required: true }, // from the clients list (user_id, names, …)
});
const emit = defineEmits(['back', 'updated', 'terminated']);

const loading = ref(true);
const error = ref('');
const detail = ref(null);
const tab = ref('diary'); // 'diary' | 'chat' | 'feedback' | 'goal'
const confirmingRemove = ref(false);
const removeBusy = ref(false);

const name = computed(() => `${props.client.first_name ?? ''} ${props.client.last_name ?? ''}`.trim() || props.client.user_name);
const initial = computed(() => (name.value || 'C').trim().charAt(0).toUpperCase());

// "today" = the last day of the server-built trend, so it matches the backend's VN clock.
const today = computed(() => (detail.value?.trend?.length ? detail.value.trend[detail.value.trend.length - 1].date : ''));
const trendMax = computed(() => {
  const t = detail.value?.trend || [];
  const goal = detail.value?.calorie_goal || 0;
  return Math.max(goal, ...t.map((d) => d.cal), 1);
});

// Feedback editor state
const fbDate = ref('');
const fbContent = ref('');
const fbBusy = ref(false);
const fbMsg = ref('');

// Goal proposer state
const goal = ref({ calorie_goal: '', protein: '', carbs: '', fat: '', note: '' });
const goalBusy = ref(false);
const goalMsg = ref('');

const feedbackByDate = computed(() => {
  const map = {};
  for (const f of detail.value?.feedback || []) map[f.date_for] = f.content;
  return map;
});

function prefillFeedback() {
  fbContent.value = feedbackByDate.value[fbDate.value] || '';
}

async function load() {
  loading.value = true;
  error.value = '';
  detail.value = null;
  try {
    const data = await api.get(`/api/pt/clients/${props.client.user_id}`);
    detail.value = data;
    fbDate.value = today.value;
    prefillFeedback();
    goal.value.calorie_goal = data.calorie_goal ?? '';
  } catch (e) {
    error.value = e.message;
  } finally {
    loading.value = false;
  }
}

async function saveFeedback() {
  if (fbBusy.value) return;
  fbBusy.value = true;
  fbMsg.value = '';
  try {
    const r = await api.post(`/api/pt/clients/${props.client.user_id}/feedback`, {
      date_for: fbDate.value,
      content: fbContent.value,
    });
    // reflect locally so history + prefill stay in sync without a reload
    const list = detail.value.feedback.filter((f) => f.date_for !== fbDate.value);
    if (r.saved) list.push({ date_for: fbDate.value, content: fbContent.value.trim() });
    list.sort((a, b) => (a.date_for < b.date_for ? 1 : -1));
    detail.value.feedback = list;
    fbMsg.value = r.saved ? t('trainer.detail.saved') : t('trainer.detail.cleared');
  } catch (e) {
    fbMsg.value = e.message;
  } finally {
    fbBusy.value = false;
  }
}

async function proposeGoal() {
  if (goalBusy.value) return;
  goalBusy.value = true;
  goalMsg.value = '';
  try {
    await api.post(`/api/pt/clients/${props.client.user_id}/propose-goal`, {
      calorie_goal: Number(goal.value.calorie_goal),
      protein: goal.value.protein === '' ? '' : Number(goal.value.protein),
      carbs: goal.value.carbs === '' ? '' : Number(goal.value.carbs),
      fat: goal.value.fat === '' ? '' : Number(goal.value.fat),
      note: goal.value.note,
    });
    goalMsg.value = t('trainer.detail.proposal_sent');
    emit('updated');
  } catch (e) {
    goalMsg.value = e.message;
  } finally {
    goalBusy.value = false;
  }
}

async function terminate() {
  if (removeBusy.value) return;
  removeBusy.value = true;
  error.value = '';
  try {
    await api.post(`/api/pt/clients/${props.client.user_id}/terminate`);
    emit('terminated', props.client.user_id);
  } catch (e) {
    error.value = e.message;
    removeBusy.value = false;
  }
}

watch(fbDate, prefillFeedback);
watch(() => props.client.user_id, () => {
  confirmingRemove.value = false;
  load();
});
onMounted(load);
</script>

<template>
  <section class="detail">
    <header class="d-head">
      <button class="back" :aria-label="$t('trainer.detail.back')" @click="emit('back')"><i class="fa-solid fa-arrow-left" /></button>
      <span class="avatar">
        <img v-if="client.profile_image" :src="client.profile_image" alt="" />
        <span v-else>{{ initial }}</span>
      </span>
      <div class="d-meta">
        <strong>{{ name }}</strong>
        <span class="muted">@{{ client.user_name }}</span>
      </div>
      <button class="remove" :aria-label="$t('trainer.detail.remove_label')" :title="$t('trainer.detail.remove_label')" @click="confirmingRemove = true">
        <i class="fa-solid fa-user-xmark" />
      </button>
    </header>

    <!-- Inline confirm before removing the client -->
    <div v-if="confirmingRemove" class="confirm-bar">
      <span>{{ $t('trainer.detail.confirm_remove', { name }) }}</span>
      <div class="confirm-actions">
        <button class="danger" :disabled="removeBusy" @click="terminate">{{ $t('trainer.detail.remove') }}</button>
        <button class="ghost-sm" :disabled="removeBusy" @click="confirmingRemove = false">{{ $t('common.cancel') }}</button>
      </div>
    </div>

    <p v-if="loading" class="muted center pad">{{ $t('common.loading') }}</p>
    <p v-else-if="error" class="error pad">{{ error }}</p>

    <template v-else>
      <!-- 7-day trend -->
      <div class="trend">
        <div v-for="d in detail.trend" :key="d.date" class="bar-col">
          <div class="bar-track">
            <div class="bar" :style="{ height: Math.round((d.cal / trendMax) * 100) + '%' }" :class="{ over: detail.calorie_goal && d.cal > detail.calorie_goal }" />
          </div>
          <span class="bar-lbl">{{ d.weekday }}</span>
        </div>
      </div>
      <p class="trend-cap muted">
        {{ $t('trainer.detail.trend_caption', { goal: detail.calorie_goal ?? '—', today: detail.trend[detail.trend.length - 1].cal }) }}
      </p>

      <!-- Tabs -->
      <div class="d-tabs" role="tablist">
        <button v-for="tabKey in ['diary','chat','feedback','goal']" :key="tabKey" class="d-tab" :class="{ on: tab === tabKey }" @click="tab = tabKey">
          {{ $t('trainer.detail.tab.' + tabKey) }}
        </button>
      </div>

      <!-- Diary -->
      <div v-show="tab === 'diary'" class="pane diary">
        <p v-if="!detail.diary.length" class="muted center pad">{{ $t('trainer.detail.no_meals') }}</p>
        <div v-for="(m, i) in detail.diary" :key="i" class="log-row">
          <div class="log-main">
            <strong>{{ m.food_item }}</strong>
            <span class="cat muted">{{ m.meal_category }}</span>
          </div>
          <div class="log-macros muted">{{ m.calories }} {{ $t('common.kcal') }} · {{ $t('intake.macro_abbr.protein') }}{{ m.protein }} {{ $t('intake.macro_abbr.carbs') }}{{ m.carbs }} {{ $t('intake.macro_abbr.fat') }}{{ m.fat }}</div>
        </div>
      </div>

      <!-- Chat -->
      <ChatRoom
        v-show="tab === 'chat'"
        :path="`/api/pt/clients/${client.user_id}/messages`"
        my-role="trainer"
        :placeholder="$t('trainer.detail.chat_placeholder', { name })"
        @sent="emit('updated')"
      />

      <!-- Feedback editor -->
      <div v-show="tab === 'feedback'" class="pane feedback">
        <label class="fld">
          <span class="fld-lbl">{{ $t('trainer.detail.date') }}</span>
          <input type="date" v-model="fbDate" :max="today" />
        </label>
        <label class="fld">
          <span class="fld-lbl">{{ $t('trainer.detail.advice_for_day') }}</span>
          <textarea v-model="fbContent" rows="4" :placeholder="$t('trainer.detail.feedback_placeholder')" />
        </label>
        <div class="row">
          <button :disabled="fbBusy" @click="saveFeedback">{{ $t('common.save') }}</button>
          <span v-if="fbMsg" class="muted">{{ fbMsg }}</span>
        </div>
        <div v-if="detail.feedback.length" class="fb-history">
          <span class="fld-lbl">{{ $t('trainer.detail.history') }}</span>
          <button v-for="f in detail.feedback" :key="f.date_for" class="chip" :class="{ on: f.date_for === fbDate }" @click="fbDate = f.date_for">
            {{ f.date_for }}
          </button>
        </div>
      </div>

      <!-- Goal proposer -->
      <div v-show="tab === 'goal'" class="pane goal">
        <p class="muted">{{ $t('trainer.detail.current_goal') }} <strong>{{ detail.calorie_goal ?? '—' }}</strong> {{ $t('dashboard.tile.kcal_day') }}</p>
        <label class="fld">
          <span class="fld-lbl">{{ $t('trainer.detail.propose_calories') }}</span>
          <input type="number" v-model="goal.calorie_goal" min="800" max="10000" :placeholder="$t('trainer.detail.calorie_placeholder')" />
        </label>
        <div class="macro-row">
          <label class="fld"><span class="fld-lbl">{{ $t('trainer.detail.protein_g') }}</span><input type="number" v-model="goal.protein" min="0" max="999" /></label>
          <label class="fld"><span class="fld-lbl">{{ $t('trainer.detail.carbs_g') }}</span><input type="number" v-model="goal.carbs" min="0" max="999" /></label>
          <label class="fld"><span class="fld-lbl">{{ $t('trainer.detail.fat_g') }}</span><input type="number" v-model="goal.fat" min="0" max="999" /></label>
        </div>
        <p class="hint muted">{{ $t('trainer.detail.macro_hint') }}</p>
        <label class="fld">
          <span class="fld-lbl">{{ $t('trainer.detail.note_optional') }}</span>
          <input type="text" v-model="goal.note" maxlength="255" :placeholder="$t('trainer.detail.note_placeholder')" />
        </label>
        <div class="row">
          <button :disabled="goalBusy || !goal.calorie_goal" @click="proposeGoal">{{ $t('trainer.detail.propose_goal') }}</button>
          <span v-if="goalMsg" class="muted">{{ goalMsg }}</span>
        </div>
      </div>
    </template>
  </section>
</template>

<style scoped>
.detail { height: 100%; display: flex; flex-direction: column; min-height: 0; }
.muted { color: var(--muted); font-size: 13px; }
.center { text-align: center; }
.pad { padding: 14px; }
.error { color: #f87171; margin: 0; }

.d-head { flex: none; display: flex; align-items: center; gap: 10px; padding: 0 2px 12px; }
.back {
  flex: none; width: 38px; height: 38px; min-height: 0; padding: 0;
  background: transparent; color: var(--text); border: 1px solid var(--border);
}
.d-head .avatar {
  flex: none; width: 40px; height: 40px; border-radius: 50%; overflow: hidden;
  display: grid; place-items: center; background: var(--accent); color: var(--on-accent); font-weight: 800;
}
.d-head .avatar img { width: 100%; height: 100%; object-fit: cover; }
.d-meta { display: flex; flex-direction: column; min-width: 0; }
.d-meta strong { white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.remove {
  flex: none; margin-left: auto; width: 38px; height: 38px; min-height: 0; padding: 0;
  background: transparent; border: 1px solid var(--border); color: var(--muted);
}
.remove:hover { color: #f87171; border-color: #f87171; }
.confirm-bar {
  flex: none; display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: 10px;
  background: var(--card); border: 1px solid #f87171; border-radius: 12px;
  padding: 10px 14px; margin-bottom: 10px; font-size: 13px;
}
.confirm-actions { display: flex; gap: 8px; }
.confirm-actions button { min-height: 36px; padding: 7px 14px; font-size: 13px; }
.danger { background: #ef4444; color: #fff; }
.ghost-sm { background: var(--card); color: var(--text); border: 1px solid var(--border); }

/* Trend */
.trend { flex: none; display: flex; gap: 6px; align-items: flex-end; height: 72px; padding: 0 2px; }
.bar-col { flex: 1; display: flex; flex-direction: column; align-items: center; gap: 4px; height: 100%; }
.bar-track { flex: 1; width: 100%; display: flex; align-items: flex-end; }
.bar { width: 100%; background: var(--accent); border-radius: 4px 4px 0 0; min-height: 2px; }
.bar.over { background: #f59e0b; }
.bar-lbl { font-size: 10px; color: var(--muted); }
.trend-cap { flex: none; margin: 6px 0 10px; }

/* Tabs */
.d-tabs { flex: none; display: flex; gap: 6px; margin-bottom: 10px; }
.d-tab {
  flex: 1; min-height: 38px; padding: 6px; border-radius: 9px;
  background: var(--card); border: 1px solid var(--border); color: var(--muted);
  font-weight: 700; font-size: 12px;
}
.d-tab.on { color: var(--accent); border-color: var(--accent); background: var(--inset); }

.pane { flex: 1; min-height: 0; overflow-y: auto; }

/* Diary */
.diary { display: flex; flex-direction: column; gap: 6px; }
.log-row { background: var(--card); border: 1px solid var(--border); border-radius: 10px; padding: 9px 12px; }
.log-main { display: flex; justify-content: space-between; align-items: center; gap: 8px; }
.log-main .cat { text-transform: capitalize; font-size: 12px; }
.log-macros { margin-top: 3px; font-size: 12px; }

/* Forms (feedback + goal) */
.fld { display: flex; flex-direction: column; gap: 4px; margin-bottom: 10px; }
.fld-lbl { font-size: 12px; color: var(--muted); font-weight: 600; }
.macro-row { display: flex; gap: 8px; }
.macro-row .fld { flex: 1; }
.hint { margin: 0 0 10px; font-size: 12px; }
.row { display: flex; align-items: center; gap: 10px; }
.row button { min-height: 40px; }
.fb-history { margin-top: 14px; display: flex; flex-wrap: wrap; gap: 6px; align-items: center; }
.chip {
  min-height: 0; padding: 5px 10px; font-size: 12px; font-weight: 600;
  background: var(--card); border: 1px solid var(--border); color: var(--muted); border-radius: 999px;
}
.chip.on { color: var(--accent); border-color: var(--accent); }
</style>
