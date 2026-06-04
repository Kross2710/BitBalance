<script setup>
import { ref, onMounted, computed } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { api } from '../lib/api.js';
import { appToday } from '../lib/date.js';
import { useAuthStore } from '../stores/auth.js';
import { locale } from '../i18n/index.js';
import WrappedStory from '../components/WrappedStory.vue';
import CalorieSummaryCard from '../components/CalorieSummaryCard.vue';
import MealBadge from '../components/MealBadge.vue';

const auth = useAuthStore();
const route = useRoute();
const router = useRouter();

// BitBalance Wrapped story overlay. Opened by the hero button or a ?wrapped=open
// deep-link (e.g. from a future share link); the query is stripped once consumed.
const showWrapped = ref(false);
function openWrapped() {
  showWrapped.value = true;
}

const day = ref(null); // full /api/dashboard/day payload
const selectedDate = ref(appToday());
const renderedDate = ref(selectedDate.value); // transition key; flips only after new data loads
const slideDir = ref('next'); // 'next' = newer day (enter from right), 'prev' = older day
const today = appToday();
const loading = ref(true);
const error = ref('');

const isToday = computed(() => selectedDate.value === today);
const entries = computed(() => day.value?.entries ?? []);
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
      weekday: d.toLocaleDateString(locale.value === 'vi' ? 'vi-VN' : 'en-US', { timeZone: 'UTC', weekday: 'short' }),
      dayNum: d.getUTCDate(),
      isToday: iso === today,
    });
  }
  return out;
});

function selectDate(iso) {
  if (iso === selectedDate.value) return;
  slideDir.value = iso > selectedDate.value ? 'next' : 'prev';
  selectedDate.value = iso;
  loadDay();
}

async function loadDay() {
  if (!day.value) loading.value = true; // full loading line only on the first load
  error.value = '';
  try {
    // Background: the dashboard has its own skeleton + day-switch slide, so this
    // load drives those, not the global top bar (avoids a redundant double signal).
    day.value = await api.get(`/api/dashboard/day?date=${selectedDate.value}`, { background: true });
    renderedDate.value = selectedDate.value; // flip the transition key together with the data
    computeNudge(); // day's meal totals changed → re-evaluate the reminder
  } catch (e) {
    error.value = e.message;
  } finally {
    loading.value = false;
  }
}

// Full-screen viewer for a logged food photo (AI Photo entries). The dashboard
// is a read-only overview; editing/deleting entries lives on the Intake page.
const lightbox = ref('');

// ---- In-app meal reminder nudge ----
// When a reminder's time has passed today and that meal isn't logged yet, show
// a dismissible banner. (Background push is future work — this only fires while
// the app is open.) Dismissal is remembered per meal per day in localStorage.
const reminders = ref(null);
const nudge = ref(null);

async function loadReminders() {
  try {
    reminders.value = await api.get('/api/reminders');
  } catch {
    reminders.value = null;
  }
  computeNudge();
}

function computeNudge() {
  nudge.value = null;
  const r = reminders.value;
  if (!r || !r.enabled || !day.value || !isToday.value) return;
  const nowHM = new Date().toLocaleTimeString('en-GB', { hour12: false }).slice(0, 5);
  const dateKey = new Date().toLocaleDateString('en-CA');
  const cats = day.value.meal_categories || {};
  const due = [];
  for (const m of ['breakfast', 'lunch', 'dinner', 'snack']) {
    const cfg = r.meals?.[m];
    if (!cfg?.enabled) continue; // reminder off for this meal
    if (nowHM < cfg.time) continue; // its time hasn't arrived
    if (Number(cats[m]) > 0) continue; // already logged today
    if (localStorage.getItem(`bb_rd_${dateKey}_${m}`)) continue; // dismissed today
    due.push({ key: m, time: cfg.time });
  }
  if (!due.length) return;
  due.sort((a, b) => (a.time < b.time ? 1 : -1)); // most-recently-due first
  nudge.value = { key: due[0].key, dateKey };
}

function dismissNudge() {
  if (nudge.value) localStorage.setItem(`bb_rd_${nudge.value.dateKey}_${nudge.value.key}`, '1');
  nudge.value = null;
}

onMounted(() => {
  loadDay();
  loadReminders();
  if (route.query.wrapped === 'open') {
    showWrapped.value = true;
    router.replace({ query: { ...route.query, wrapped: undefined } });
  }
});
</script>

<template>
  <main style="max-width: 820px; margin: 0 auto; padding: 8px 16px">
    <!-- Greeting (moved here from the topbar, which now shows brand + avatar). -->
    <p v-if="auth.user" class="greet">{{ $t('dashboard.greeting.hi', { name: auth.user.first_name || auth.user.handle }) }}</p>

    <!-- In-app meal reminder nudge -->
    <div v-if="nudge" class="nudge">
      <i class="fa-solid fa-bell" />
      <span>{{ $t('dashboard.nudge.time_to_log', { meal: $t('dashboard.meal.' + nudge.key) }) }}</span>
      <RouterLink to="/intake" class="nudge-cta">{{ $t('dashboard.nudge.log_now') }}</RouterLink>
      <button type="button" class="nudge-x" :aria-label="$t('dashboard.nudge.dismiss')" @click="dismissNudge"><i class="fa-solid fa-xmark" /></button>
    </div>

    <!-- Level / XP pill -->
    <div v-if="day" class="hero">
      <div class="level-pill">
        <span class="lvl">{{ $t('dashboard.level.lv', { n: day.current_level }) }}</span>
        <div class="xp">
          <div class="xp-bar"><div :style="{ width: day.xp_progress_percentage + '%' }" /></div>
          <small class="muted">{{ $t('dashboard.xp.progress', { into: day.xp_into_level, next: day.xp_for_next }) }}</small>
        </div>
      </div>
      <span class="streak-flame" :title="$t('dashboard.streak.title')">
        <i class="fa-solid fa-fire" /> {{ day.streak.current }}
      </span>
    </div>
    <!-- Reserve the hero-pill height on first load so the page below doesn't jump. -->
    <div v-else-if="loading" class="sk hero-skel" aria-hidden="true"></div>

    <!-- BitBalance Wrapped entry -->
    <button type="button" class="wrapped-cta" @click="openWrapped">
      <span class="wc-ic"><i class="fa-solid fa-wand-magic-sparkles" /></span>
      <span class="wc-txt">
        <strong>{{ $t('dashboard.wrapped.title') }}</strong>
        <small>{{ $t('dashboard.wrapped.subtitle') }}</small>
      </span>
      <i class="fa-solid fa-chevron-right wc-arrow" />
    </button>

    <!-- Compact date strip (last 7 days) -->
    <div class="datestrip">
      <button
        v-for="d in dateStrip"
        :key="d.iso"
        class="day-chip"
        :class="{ active: d.iso === selectedDate }"
        @click="selectDate(d.iso)"
      >
        <small>{{ d.isToday ? $t('dashboard.today.heading') : d.weekday }}</small>
        <strong>{{ d.dayNum }}</strong>
      </button>
    </div>

    <!-- First-load skeleton: reserves the deterministic data blocks' height so the
         content below doesn't jump ~800px when the API resolves (CLS). The variable
         entries list below is not reserved (its height is data-dependent). Static,
         no shimmer, per the agreed lightweight scope. -->
    <div v-if="loading" class="day-skeleton" role="status" :aria-label="$t('common.loading')">
      <div class="sk" style="height: 134px; margin-top: 14px"></div>
      <div class="tiles">
        <div class="sk" style="height: 103px"></div>
        <div class="sk" style="height: 103px"></div>
        <div class="sk" style="height: 103px"></div>
      </div>
      <div class="sk" style="height: 172px; margin-top: 14px"></div>
      <div class="sk" style="height: 85px; margin-top: 14px"></div>
      <div class="actions">
        <div class="sk" style="height: 52px"></div>
        <div class="sk" style="height: 52px"></div>
      </div>
    </div>

    <Transition v-else :name="'day-' + slideDir" mode="out-in">
      <div v-if="day" :key="renderedDate" class="day-content">
      <!-- Calorie + macro summary (shared with the Intake page) -->
      <CalorieSummaryCard :summary="day" style="margin-top: 14px" />

      <!-- Stat tiles -->
      <section class="tiles">
        <div class="card tile">
          <span class="muted">{{ $t('dashboard.tile.focus') }}</span>
          <strong>{{ day.focus?.macro_focus?.label ?? '—' }}</strong>
          <small class="muted">{{ day.focus?.macro_focus?.gap ? $t('dashboard.focus.gap_left', { n: day.focus.macro_focus.gap }) : $t('dashboard.focus.on_track') }}</small>
        </div>
        <div class="card tile"><span class="muted">{{ $t('dashboard.tile.bmi') }}</span><strong>{{ day.bmi.value ?? '—' }}</strong><small class="muted">{{ day.bmi.category ?? $t('dashboard.tile.no_data') }}</small></div>
        <div class="card tile"><span class="muted">{{ $t('dashboard.tile.avg_7day') }}</span><strong>{{ day.average_calories ?? '—' }}</strong><small class="muted">{{ $t('dashboard.tile.kcal_day') }}</small></div>
      </section>

      <!-- 7-day calorie chart -->
      <section class="card" style="margin-top: 14px">
        <strong>{{ $t('dashboard.last7.heading') }}</strong>
        <div class="chart">
          <div v-for="(c, i) in day.history.calories" :key="i" class="chart-col">
            <div class="chart-bar" :style="{ height: Math.round((c / maxHistory) * 80) + 'px' }" :title="$t('dashboard.kcal_value', { n: c })" />
            <small class="muted">{{ day.history.labels[i] }}</small>
          </div>
        </div>
      </section>

      <!-- Meal breakdown -->
      <section class="card" style="margin-top: 14px; display: flex; justify-content: space-around; text-align: center">
        <div v-for="(cal, meal) in day.meal_categories" :key="meal" class="meal-col">
          <MealBadge :meal="meal" />
          <strong>{{ cal }}</strong>
        </div>
      </section>

      <!-- Quick actions -->
      <section class="actions">
        <RouterLink :to="isToday ? '/intake' : { path: '/intake', query: { date: selectedDate } }" class="action primary">
          <i class="fa-solid fa-utensils" /> {{ $t('dashboard.action.log_food') }}
        </RouterLink>
        <RouterLink to="/coach" class="action">
          <i class="fa-solid fa-dumbbell" /> {{ $t('dashboard.action.ask_coach') }}
        </RouterLink>
      </section>

      <p v-if="error" class="error">{{ error }}</p>

      <!-- Entries (read-only overview; manage them on the Intake page) -->
      <section style="margin-top: 14px">
        <p v-if="!entries.length" class="muted">{{ $t('dashboard.entries.empty') }}</p>
        <ul v-else style="list-style: none; padding: 0; display: flex; flex-direction: column; gap: 8px">
          <li v-for="e in entries" :key="e.id" class="card" style="padding: 12px 16px">
            <div style="display: flex; justify-content: space-between; align-items: center; gap: 10px">
              <span style="display: flex; align-items: center; gap: 10px; min-width: 0">
                <img
                  v-if="e.image_path"
                  :src="e.image_path"
                  class="entry-thumb"
                  width="40"
                  height="40"
                  decoding="async"
                  :alt="$t('dashboard.entry.photo_alt')"
                  @click="lightbox = e.image_path"
                />
                <span style="min-width: 0">{{ e.food_item }} <MealBadge :meal="e.meal_category" /></span>
              </span>
              <strong style="flex: none">{{ $t('dashboard.kcal_value', { n: e.calories }) }}</strong>
            </div>
          </li>
        </ul>
        <RouterLink
          v-if="entries.length"
          :to="isToday ? '/intake' : { path: '/intake', query: { date: selectedDate } }"
          class="manage-link"
        >
          <i class="fa-solid fa-pen" /> {{ isToday ? $t('dashboard.entries.edit_today') : $t('dashboard.entries.edit_day') }}
        </RouterLink>
      </section>
      </div>
    </Transition>

    <!-- Food photo viewer -->
    <div v-if="lightbox" class="lightbox" @click="lightbox = ''">
      <img :src="lightbox" :alt="$t('dashboard.entry.photo_alt')" />
    </div>

    <!-- BitBalance Wrapped story overlay -->
    <WrappedStory v-if="showWrapped" @close="showWrapped = false" />
  </main>
</template>

<style scoped>
.muted { color: var(--muted); }
.meal-col { display: flex; flex-direction: column; align-items: center; gap: 6px; }

.greet { font-weight: 800; font-size: 18px; margin: 2px 0 10px; }

/* In-app reminder nudge */
.nudge {
  display: flex;
  align-items: center;
  gap: 10px;
  margin: 0 0 12px;
  padding: 10px 12px;
  background: rgba(167, 139, 250, 0.12);
  border: 1px solid #4b3f7a;
  border-radius: 12px;
  font-size: 14px;
}
.nudge > i { color: #c4b5fd; }
.nudge span { flex: 1; min-width: 0; }
.nudge strong { text-transform: capitalize; }
.nudge-cta {
  flex: none;
  background: var(--accent);
  color: var(--on-accent);
  font-weight: 700;
  font-size: 13px;
  padding: 8px 12px;
  border-radius: 8px;
  text-decoration: none;
}
.nudge-x {
  flex: none;
  width: 32px;
  height: 32px;
  display: grid;
  place-items: center;
  background: transparent;
  color: var(--muted);
  padding: 0;
}

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
.xp-bar { height: 6px; background: var(--inset); border-radius: 4px; overflow: hidden; }
.xp-bar > div { height: 100%; background: #a78bfa; transition: width 0.3s; }
.level-pill small { display: block; margin-top: 3px; font-size: 11px; }
.streak-flame { font-weight: 800; color: #fb923c; white-space: nowrap; }

/* BitBalance Wrapped entry — a playful gradient CTA. */
.wrapped-cta {
  display: flex;
  align-items: center;
  gap: 12px;
  width: 100%;
  margin-top: 12px;
  padding: 12px 14px;
  text-align: left;
  border-radius: 14px;
  border: 1px solid #3a3550;
  background:
    radial-gradient(120% 140% at 0% 0%, rgba(167, 139, 250, 0.25), transparent 60%),
    radial-gradient(120% 140% at 100% 100%, rgba(74, 222, 128, 0.18), transparent 60%),
    var(--card);
  color: var(--text);
}
.wrapped-cta .wc-ic {
  flex: none;
  width: 40px;
  height: 40px;
  display: grid;
  place-items: center;
  border-radius: 11px;
  background: linear-gradient(135deg, #a78bfa, #4ade80);
  color: var(--on-accent);
  font-size: 17px;
}
.wrapped-cta .wc-txt { flex: 1; min-width: 0; display: flex; flex-direction: column; gap: 1px; }
.wrapped-cta .wc-txt strong { font-size: 15px; font-weight: 800; }
.wrapped-cta .wc-txt small { color: var(--muted); font-size: 12px; }
.wrapped-cta .wc-arrow { color: var(--muted); font-size: 13px; }

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
.day-chip.active { border-color: var(--accent); color: var(--accent); background: var(--inset); }

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
  background: var(--surface-2);
  color: var(--text);
}
.action.primary { background: var(--accent); color: var(--on-accent); }

.tiles { display: grid; grid-template-columns: repeat(3, 1fr); gap: 10px; margin-top: 14px; }
.tile { display: flex; flex-direction: column; gap: 2px; align-items: flex-start; }
.tile strong { font-size: 20px; }
.chart { display: flex; align-items: flex-end; gap: 8px; height: 100px; margin-top: 12px; }
.chart-col { flex: 1; display: flex; flex-direction: column; align-items: center; gap: 4px; justify-content: flex-end; }
.chart-bar { width: 100%; background: var(--accent); border-radius: 4px 4px 0 0; min-height: 2px; transition: height 0.3s; }

/* First-load skeleton placeholders — dim cards that hold the data blocks' space
   so nothing below jumps when the API resolves. Static (no shimmer) by design. */
.sk { background: var(--inset); border: 1px solid var(--border); border-radius: 14px; }
.hero-skel { height: 40px; margin-top: 6px; border-radius: 999px; }
.entry-thumb { flex: none; width: 40px; height: 40px; border-radius: 8px; object-fit: cover; cursor: pointer; }
.lightbox {
  position: fixed; inset: 0; z-index: 60; background: rgba(0, 0, 0, 0.85);
  display: grid; place-items: center; padding: 24px;
}
.lightbox img { max-width: 100%; max-height: 100%; border-radius: 12px; }
.manage-link {
  display: inline-flex; align-items: center; gap: 8px; margin-top: 12px;
  min-height: 44px; padding: 0 4px;
  color: var(--accent); font-size: 13px; font-weight: 600; text-decoration: none;
}

/* Day-switch transition: the old day slides out + fades, the new day fades in.
   Enter is fade-only on purpose — the new subtree (chart, badges, entry images)
   mounts on those same frames, so we keep its first frames cheap. The leaving
   side keeps the directional slide ('next' exits left, 'prev' right). translate3d
   + will-change promote a GPU layer for the transition's duration so iOS Safari
   composites it instead of repainting the whole subtree on the main thread.
   Kept small per DESIGN.md §7 — motion serves smoothness, not decoration. */
.day-next-enter-active, .day-next-leave-active,
.day-prev-enter-active, .day-prev-leave-active {
  transition: opacity 0.2s ease, transform 0.22s cubic-bezier(0.32, 0.72, 0, 1);
  will-change: transform, opacity;
  backface-visibility: hidden;
}
.day-next-enter-from, .day-prev-enter-from { opacity: 0; }
.day-next-leave-to { opacity: 0; transform: translate3d(-16px, 0, 0); }
.day-prev-leave-to { opacity: 0; transform: translate3d(16px, 0, 0); }

@media (prefers-reduced-motion: reduce) {
  .day-next-enter-active, .day-next-leave-active,
  .day-prev-enter-active, .day-prev-leave-active { transition: opacity 0.15s ease; }
  .day-next-leave-to, .day-prev-leave-to { transform: none; }
}
</style>
