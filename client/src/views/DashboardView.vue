<script setup>
import { ref, onMounted, computed } from 'vue';
import { api } from '../lib/api.js';

const day = ref(null); // full /api/dashboard/day payload
const selectedDate = ref(new Date().toISOString().slice(0, 10));
const today = new Date().toISOString().slice(0, 10);
const loading = ref(true);
const error = ref('');

const isToday = computed(() => selectedDate.value === today);
const entries = computed(() => day.value?.entries ?? []);
const progress = computed(() => (day.value?.progress_percentage ?? 0) + '%');
const maxHistory = computed(() => Math.max(1, ...(day.value?.history?.calories ?? [0])));

// Compact date strip: the last 7 days ending today, tappable to switch day.
const dateStrip = computed(() => {
  const out = [];
  for (let i = 6; i >= 0; i--) {
    const d = new Date(today + 'T00:00:00Z');
    d.setUTCDate(d.getUTCDate() - i);
    const iso = d.toISOString().slice(0, 10);
    out.push({
      iso,
      weekday: d.toLocaleDateString('en-US', { timeZone: 'UTC', weekday: 'short' }),
      dayNum: d.getUTCDate(),
      isToday: iso === today,
    });
  }
  return out;
});

function selectDate(iso) {
  if (iso === selectedDate.value) return;
  selectedDate.value = iso;
  loadDay();
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

// --- Edit / delete ---
const editingId = ref(null);
const editForm = ref({});

// Full-screen viewer for a logged food photo (AI Photo entries).
const lightbox = ref('');

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

onMounted(loadDay);
</script>

<template>
  <main style="max-width: 820px; margin: 0 auto; padding: 8px 16px">
    <!-- Level / XP pill -->
    <div v-if="day" class="hero">
      <div class="level-pill">
        <span class="lvl">Lv {{ day.current_level }}</span>
        <div class="xp">
          <div class="xp-bar"><div :style="{ width: day.xp_progress_percentage + '%' }" /></div>
          <small class="muted">{{ day.xp_into_level }} / {{ day.xp_for_next }} XP</small>
        </div>
      </div>
      <span class="streak-flame" title="Logging streak">
        <i class="fa-solid fa-fire" /> {{ day.streak.current }}
      </span>
    </div>

    <!-- Compact date strip (last 7 days) -->
    <div class="datestrip">
      <button
        v-for="d in dateStrip"
        :key="d.iso"
        class="day-chip"
        :class="{ active: d.iso === selectedDate }"
        @click="selectDate(d.iso)"
      >
        <small>{{ d.isToday ? 'Today' : d.weekday }}</small>
        <strong>{{ d.dayNum }}</strong>
      </button>
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
        <div class="card tile"><span class="muted">Streak</span><strong><i class="fa-solid fa-fire" style="color: #fb923c" /> {{ day.streak.current }}d</strong><small class="muted">best {{ day.streak.longest }}</small></div>
        <div class="card tile"><span class="muted">BMI</span><strong>{{ day.bmi.value ?? '—' }}</strong><small class="muted">{{ day.bmi.category ?? 'no data' }}</small></div>
        <div class="card tile"><span class="muted">7-day avg</span><strong>{{ day.average_calories ?? '—' }}</strong><small class="muted">kcal/day</small></div>
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

      <!-- Quick actions -->
      <section class="actions">
        <RouterLink to="/intake" class="action primary">
          <i class="fa-solid fa-utensils" /> Log food
        </RouterLink>
        <RouterLink to="/coach" class="action">
          <i class="fa-solid fa-dumbbell" /> Ask Coach
        </RouterLink>
      </section>

      <p v-if="error" class="error">{{ error }}</p>

      <!-- Entries -->
      <section style="margin-top: 14px">
        <p v-if="!entries.length" class="muted">No entries for this day.</p>
        <ul v-else style="list-style: none; padding: 0; display: flex; flex-direction: column; gap: 8px">
          <li v-for="e in entries" :key="e.id" class="card" style="padding: 12px 16px">
            <div v-if="editingId === e.id" style="display: grid; grid-template-columns: 2fr 1fr 1fr; gap: 8px">
              <input v-model="editForm.food_item" />
              <input v-model="editForm.calories" type="number" min="1" step="any" />
              <select v-model="editForm.meal_category">
                <option value="breakfast">Breakfast</option>
                <option value="lunch">Lunch</option>
                <option value="dinner">Dinner</option>
                <option value="snack">Snack</option>
              </select>
              <input v-model="editForm.protein" type="number" min="0" step="any" placeholder="P" />
              <input v-model="editForm.carbs" type="number" min="0" step="any" placeholder="C" />
              <input v-model="editForm.fat" type="number" min="0" step="any" placeholder="F" />
              <div style="grid-column: 1 / -1; display: flex; gap: 8px">
                <button @click="saveEdit">Save</button>
                <button @click="cancelEdit" style="background: #2a2e37; color: var(--text)">Cancel</button>
              </div>
            </div>
            <div v-else style="display: flex; justify-content: space-between; align-items: center; gap: 10px">
              <span style="display: flex; align-items: center; gap: 10px; min-width: 0">
                <img
                  v-if="e.image_path"
                  :src="e.image_path"
                  class="entry-thumb"
                  alt="Food photo"
                  @click="lightbox = e.image_path"
                />
                <span style="min-width: 0">{{ e.food_item }} <small class="muted">· {{ e.meal_category }}</small></span>
              </span>
              <span style="display: flex; gap: 10px; align-items: center; flex: none">
                <strong>{{ e.calories }} kcal</strong>
                <button @click="startEdit(e)" class="icon-btn">Edit</button>
                <button @click="removeEntry(e)" class="icon-btn danger">Delete</button>
              </span>
            </div>
          </li>
        </ul>
      </section>
    </template>

    <!-- Food photo viewer -->
    <div v-if="lightbox" class="lightbox" @click="lightbox = ''">
      <img :src="lightbox" alt="Food photo" />
    </div>
  </main>
</template>

<style scoped>
.muted { color: var(--muted); }
.bar { height: 10px; background: #12151b; border-radius: 6px; margin-top: 10px; overflow: hidden; }
.bar > div { height: 100%; transition: width 0.3s; }

/* Level / XP pill + streak flame */
.hero { display: flex; align-items: center; justify-content: space-between; gap: 12px; margin-top: 6px; }
.level-pill {
  display: flex;
  align-items: center;
  gap: 12px;
  background: var(--card);
  border: 1px solid var(--border);
  border-radius: 999px;
  padding: 8px 16px 8px 14px;
  flex: 1;
  min-width: 0;
}
.level-pill .lvl { font-weight: 800; color: #c4b5fd; white-space: nowrap; }
.level-pill .xp { flex: 1; min-width: 0; }
.xp-bar { height: 6px; background: #12151b; border-radius: 4px; overflow: hidden; }
.xp-bar > div { height: 100%; background: #a78bfa; transition: width 0.3s; }
.level-pill small { display: block; margin-top: 3px; font-size: 11px; }
.streak-flame { font-weight: 800; color: #fb923c; white-space: nowrap; }

/* Compact date strip — scrollable on narrow screens but no visible scrollbar
   (7 chips fit a phone width; scroll is a safety net, not a feature). */
.datestrip { display: flex; gap: 6px; margin-top: 14px; overflow-x: auto; scrollbar-width: none; -ms-overflow-style: none; }
.datestrip::-webkit-scrollbar { display: none; }
.day-chip {
  flex: 1;
  min-width: 44px;
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 2px;
  background: var(--card);
  color: var(--muted);
  border: 1px solid var(--border);
  border-radius: 10px;
  padding: 8px 4px;
}
.day-chip small { font-size: 10px; text-transform: uppercase; letter-spacing: 0.03em; }
.day-chip strong { font-size: 15px; }
.day-chip.active { border-color: var(--accent); color: var(--accent); background: #12151b; }

/* Quick actions */
.actions { display: grid; grid-template-columns: 1fr 1fr; gap: 10px; margin-top: 14px; }
.action {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 8px;
  min-height: 52px;
  border-radius: 12px;
  font-weight: 700;
  text-decoration: none;
  background: #2a2e37;
  color: var(--text);
}
.action.primary { background: var(--accent); color: #04210f; }

.tiles { display: grid; grid-template-columns: repeat(3, 1fr); gap: 10px; margin-top: 14px; }
.tile { display: flex; flex-direction: column; gap: 2px; align-items: flex-start; }
.tile strong { font-size: 20px; }
.chart { display: flex; align-items: flex-end; gap: 8px; height: 100px; margin-top: 12px; }
.chart-col { flex: 1; display: flex; flex-direction: column; align-items: center; gap: 4px; justify-content: flex-end; }
.chart-bar { width: 100%; background: var(--accent); border-radius: 4px 4px 0 0; min-height: 2px; transition: height 0.3s; }
.entry-thumb { flex: none; width: 40px; height: 40px; border-radius: 8px; object-fit: cover; cursor: pointer; }
.lightbox {
  position: fixed; inset: 0; z-index: 60; background: rgba(0, 0, 0, 0.85);
  display: grid; place-items: center; padding: 24px;
}
.lightbox img { max-width: 100%; max-height: 100%; border-radius: 12px; }
.icon-btn { background: #2a2e37; color: var(--text); padding: 6px 10px; font-size: 12px; font-weight: 600; }
.icon-btn.danger { color: #f87171; }
.icon-btn:disabled { opacity: 0.4; }
</style>
