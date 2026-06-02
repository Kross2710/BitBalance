<script setup>
import { ref, onMounted, computed } from 'vue';
import { useRouter } from 'vue-router';
import { api } from '../lib/api.js';
import { useAuthStore } from '../stores/auth.js';

const auth = useAuthStore();
const router = useRouter();

const day = ref(null); // full /api/dashboard/day payload
const selectedDate = ref(new Date().toISOString().slice(0, 10));
const today = new Date().toISOString().slice(0, 10);
const loading = ref(true);
const error = ref('');

const isToday = computed(() => selectedDate.value === today);
const entries = computed(() => day.value?.entries ?? []);
const progress = computed(() => (day.value?.progress_percentage ?? 0) + '%');
const maxHistory = computed(() => Math.max(1, ...(day.value?.history?.calories ?? [0])));

// Quick-log form (today only)
const form = ref({ food_item: '', calories: '', meal_category: 'breakfast', protein: '', carbs: '', fat: '' });
const saving = ref(false);

// Transient toast for XP gained / level-ups.
const toast = ref(null);
function showToast(msg) {
  toast.value = msg;
  setTimeout(() => (toast.value = null), 2800);
}

async function loadDay() {
  loading.value = true;
  error.value = '';
  try {
    day.value = await api.get(`/api/dashboard/day?date=${selectedDate.value}`);
  } catch (e) {
    error.value = e.message;
  } finally {
    loading.value = false;
  }
}

function shiftDay(delta) {
  const d = new Date(selectedDate.value + 'T00:00:00Z');
  d.setUTCDate(d.getUTCDate() + delta);
  const next = d.toISOString().slice(0, 10);
  if (next > today) return; // no future
  selectedDate.value = next;
  loadDay();
}

async function addEntry() {
  saving.value = true;
  error.value = '';
  try {
    const data = await api.post('/api/intake/create', form.value);
    form.value = { food_item: '', calories: '', meal_category: 'breakfast', protein: '', carbs: '', fat: '' };
    await loadDay(); // refresh aggregates (calories, macros, chart, streak, XP)
    if (data.xp?.levelup) {
      showToast(`🎉 Level up! ${data.xp.levelup.from} → ${data.xp.levelup.to}`);
    } else if (data.xp?.added > 0) {
      showToast(`+${data.xp.added} XP`);
    }
  } catch (e) {
    error.value = e.message;
  } finally {
    saving.value = false;
  }
}

// --- Edit / delete ---
const editingId = ref(null);
const editForm = ref({});

function startEdit(e) {
  editingId.value = e.id;
  editForm.value = { intake_id: e.id, food_item: e.food_item, calories: e.calories, meal_category: e.meal_category, protein: e.protein, carbs: e.carbs, fat: e.fat };
}
function cancelEdit() {
  editingId.value = null;
}
async function saveEdit() {
  error.value = '';
  try {
    await api.post('/api/intake/update', editForm.value);
    editingId.value = null;
    await loadDay();
  } catch (e) {
    error.value = e.message;
  }
}
async function removeEntry(e) {
  if (!confirm(`Delete "${e.food_item}"?`)) return;
  error.value = '';
  try {
    await api.post('/api/intake/delete', { intake_id: e.id });
    await loadDay();
  } catch (err) {
    error.value = err.message;
  }
}

async function onLogout() {
  await auth.logout();
  router.push({ name: 'login' });
}

const prettyDate = computed(() =>
  new Date(selectedDate.value + 'T00:00:00Z').toLocaleDateString('en-US', {
    timeZone: 'UTC', weekday: 'short', month: 'short', day: 'numeric',
  })
);

onMounted(loadDay);
</script>

<template>
  <main style="max-width: 820px; margin: 0 auto; padding: 24px 16px">
    <header style="display: flex; justify-content: space-between; align-items: center">
      <h1 style="margin: 0">Hi, {{ auth.user?.first_name || auth.user?.handle }}</h1>
      <button @click="onLogout" style="background: #2a2e37; color: var(--text)">Log out</button>
    </header>

    <!-- Date navigation -->
    <div class="datenav">
      <button @click="shiftDay(-1)" class="icon-btn">‹</button>
      <span>{{ isToday ? 'Today' : prettyDate }}</span>
      <button @click="shiftDay(1)" class="icon-btn" :disabled="isToday">›</button>
    </div>

    <p v-if="loading" class="muted">Loading…</p>

    <template v-else-if="day">
      <!-- Calorie + macro summary -->
      <section class="card" style="margin-top: 14px">
        <div style="display: flex; justify-content: space-between">
          <strong>Calories</strong>
          <span class="muted">{{ day.total_calories }} / {{ day.calorie_goal ?? '—' }} kcal</span>
        </div>
        <div class="bar"><div :style="{ width: progress, background: day.status_class === 'overlimit' ? '#f87171' : 'var(--accent)' }" /></div>
        <div style="display: flex; gap: 16px; margin-top: 12px; font-size: 13px" class="muted">
          <span>P {{ day.macros.protein }} / {{ day.macro_goals.protein }}g</span>
          <span>C {{ day.macros.carbs }} / {{ day.macro_goals.carbs }}g</span>
          <span>F {{ day.macros.fat }} / {{ day.macro_goals.fat }}g</span>
        </div>
        <p v-if="day.focus" class="muted" style="margin: 10px 0 0; font-size: 13px">
          <template v-if="day.focus.calorie_remaining != null">{{ day.focus.calorie_remaining }} kcal left ·</template>
          <template v-else-if="day.focus.calorie_over_by != null">{{ day.focus.calorie_over_by }} kcal over ·</template>
          Focus: {{ day.focus.macro_focus.label }}<template v-if="day.focus.macro_focus.gap"> ({{ day.focus.macro_focus.gap }}g)</template>
        </p>
      </section>

      <!-- Stat tiles -->
      <section class="tiles">
        <div class="card tile"><span class="muted">Streak</span><strong>🔥 {{ day.streak.current }}d</strong><small class="muted">best {{ day.streak.longest }}</small></div>
        <div class="card tile"><span class="muted">BMI</span><strong>{{ day.bmi.value ?? '—' }}</strong><small class="muted">{{ day.bmi.category ?? 'no data' }}</small></div>
        <div class="card tile"><span class="muted">7-day avg</span><strong>{{ day.average_calories ?? '—' }}</strong><small class="muted">kcal/day</small></div>
      </section>

      <!-- Level / XP -->
      <section class="card" style="margin-top: 14px">
        <div style="display: flex; justify-content: space-between">
          <strong>Level {{ day.current_level }}</strong>
          <span class="muted">{{ day.xp_into_level }} / {{ day.xp_for_next }} XP · {{ day.total_xp }} total</span>
        </div>
        <div class="bar"><div :style="{ width: day.xp_progress_percentage + '%', background: '#a78bfa' }" /></div>
      </section>

      <!-- 7-day calorie chart -->
      <section class="card" style="margin-top: 14px">
        <strong>Last 7 days</strong>
        <div class="chart">
          <div v-for="(c, i) in day.history.calories" :key="i" class="chart-col">
            <div class="chart-bar" :style="{ height: Math.round((c / maxHistory) * 80) + 'px' }" :title="c + ' kcal'" />
            <small class="muted">{{ day.history.labels[i] }}</small>
          </div>
        </div>
      </section>

      <!-- Meal breakdown -->
      <section class="card" style="margin-top: 14px; display: flex; justify-content: space-around; text-align: center">
        <div v-for="(cal, meal) in day.meal_categories" :key="meal">
          <div style="text-transform: capitalize" class="muted">{{ meal }}</div>
          <strong>{{ cal }}</strong>
        </div>
      </section>

      <!-- Quick log (today only) -->
      <section v-if="isToday" class="card" style="margin-top: 14px">
        <strong>Quick log</strong>
        <form @submit.prevent="addEntry" style="display: grid; grid-template-columns: 2fr 1fr 1fr; gap: 10px; margin-top: 12px">
          <input v-model="form.food_item" placeholder="Food item" required />
          <input v-model="form.calories" type="number" placeholder="kcal" min="1" required />
          <select v-model="form.meal_category">
            <option value="breakfast">Breakfast</option>
            <option value="lunch">Lunch</option>
            <option value="dinner">Dinner</option>
            <option value="snack">Snack</option>
          </select>
          <input v-model="form.protein" type="number" placeholder="Protein g" min="0" />
          <input v-model="form.carbs" type="number" placeholder="Carbs g" min="0" />
          <input v-model="form.fat" type="number" placeholder="Fat g" min="0" />
          <button type="submit" :disabled="saving" style="grid-column: 1 / -1">{{ saving ? 'Adding…' : 'Add entry' }}</button>
        </form>
      </section>
      <p v-else class="muted" style="margin-top: 14px; text-align: center">Viewing a past day — logging is available on Today only.</p>

      <p v-if="error" class="error">{{ error }}</p>

      <!-- Entries -->
      <section style="margin-top: 14px">
        <p v-if="!entries.length" class="muted">No entries for this day.</p>
        <ul v-else style="list-style: none; padding: 0; display: flex; flex-direction: column; gap: 8px">
          <li v-for="e in entries" :key="e.id" class="card" style="padding: 12px 16px">
            <div v-if="editingId === e.id" style="display: grid; grid-template-columns: 2fr 1fr 1fr; gap: 8px">
              <input v-model="editForm.food_item" />
              <input v-model="editForm.calories" type="number" min="1" />
              <select v-model="editForm.meal_category">
                <option value="breakfast">Breakfast</option>
                <option value="lunch">Lunch</option>
                <option value="dinner">Dinner</option>
                <option value="snack">Snack</option>
              </select>
              <input v-model="editForm.protein" type="number" min="0" placeholder="P" />
              <input v-model="editForm.carbs" type="number" min="0" placeholder="C" />
              <input v-model="editForm.fat" type="number" min="0" placeholder="F" />
              <div style="grid-column: 1 / -1; display: flex; gap: 8px">
                <button @click="saveEdit">Save</button>
                <button @click="cancelEdit" style="background: #2a2e37; color: var(--text)">Cancel</button>
              </div>
            </div>
            <div v-else style="display: flex; justify-content: space-between; align-items: center">
              <span>{{ e.food_item }} <small class="muted">· {{ e.meal_category }}</small></span>
              <span style="display: flex; gap: 10px; align-items: center">
                <strong>{{ e.calories }} kcal</strong>
                <button @click="startEdit(e)" class="icon-btn">Edit</button>
                <button @click="removeEntry(e)" class="icon-btn danger">Delete</button>
              </span>
            </div>
          </li>
        </ul>
      </section>
    </template>

    <!-- XP / level-up toast -->
    <Transition name="fade">
      <div v-if="toast" class="toast">{{ toast }}</div>
    </Transition>
  </main>
</template>

<style scoped>
.muted { color: var(--muted); }
.bar { height: 10px; background: #12151b; border-radius: 6px; margin-top: 10px; overflow: hidden; }
.bar > div { height: 100%; transition: width 0.3s; }
.datenav { display: flex; align-items: center; justify-content: center; gap: 14px; margin-top: 16px; font-weight: 600; }
.tiles { display: grid; grid-template-columns: repeat(3, 1fr); gap: 10px; margin-top: 14px; }
.tile { display: flex; flex-direction: column; gap: 2px; align-items: flex-start; }
.tile strong { font-size: 20px; }
.chart { display: flex; align-items: flex-end; gap: 8px; height: 100px; margin-top: 12px; }
.chart-col { flex: 1; display: flex; flex-direction: column; align-items: center; gap: 4px; justify-content: flex-end; }
.chart-bar { width: 100%; background: var(--accent); border-radius: 4px 4px 0 0; min-height: 2px; transition: height 0.3s; }
.icon-btn { background: #2a2e37; color: var(--text); padding: 6px 10px; font-size: 12px; font-weight: 600; }
.icon-btn.danger { color: #f87171; }
.icon-btn:disabled { opacity: 0.4; }
.toast {
  position: fixed;
  bottom: 24px;
  left: 50%;
  transform: translateX(-50%);
  background: #a78bfa;
  color: #1a1030;
  padding: 12px 20px;
  border-radius: 10px;
  font-weight: 700;
  box-shadow: 0 8px 24px rgba(0, 0, 0, 0.4);
  z-index: 50;
}
</style>
