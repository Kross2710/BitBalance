<script setup>
// BitBalance Wrapped — an Instagram-Stories recap of the user's week, fed by
// GET /api/wrapped (see lib/wrapped.js). Five slides: aura archetype, top badge,
// streak, leaderboard, bento summary. The Spotify slide (payload.spotify) is
// deferred with the Beats epic. Image export is P3 — not here yet.
import { ref, computed, onMounted } from 'vue';
import { api } from '../lib/api.js';
import { useAuthStore } from '../stores/auth.js';
import { useStoryCarousel } from '../composables/useStoryCarousel.js';
import { locale, t } from '../i18n/index.js';

const emit = defineEmits(['close']);
const auth = useAuthStore();

const data = ref(null);
const loading = ref(true);
const error = ref('');

// Slides present in this payload (Spotify slide only if the backend sent it).
const slides = computed(() => {
  if (!data.value) return [];
  const s = ['aura', 'badge', 'streak', 'leaderboard', 'summary'];
  if (data.value.spotify) s.splice(4, 0, 'spotify');
  return s;
});

const car = useStoryCarousel({
  count: () => slides.value.length,
  durationMs: 5000,
  onComplete: () => emit('close'),
});

onMounted(async () => {
  try {
    data.value = await api.get(`/api/wrapped?lang=${encodeURIComponent(locale.value)}`);
  } catch (e) {
    error.value = e.message || t('wrapped.load_error');
  } finally {
    loading.value = false;
    if (data.value) car.start();
  }
});

function close() {
  car.stop();
  emit('close');
}

// ---- Gestures: tap zones (left=prev / right=next), hold-to-pause, swipe ----
let downX = 0;
let downT = 0;
let holdTimer = null;

function onPointerDown(e) {
  downX = e.clientX;
  downT = Date.now();
  holdTimer = setTimeout(() => car.pause(), 180); // hold to pause
}
function onPointerUp(e) {
  clearTimeout(holdTimer);
  car.resume();
  const dx = e.clientX - downX;
  const dt = Date.now() - downT;
  if (Math.abs(dx) > 45) {
    dx < 0 ? car.next() : car.prev(); // swipe
    return;
  }
  if (dt < 250) {
    const rect = e.currentTarget.getBoundingClientRect();
    e.clientX - rect.left < rect.width * 0.32 ? car.prev() : car.next(); // tap
  }
}
function onPointerLeave() {
  clearTimeout(holdTimer);
  car.resume();
}

// ---- Per-slide derived data ----
const TONES = {
  primary: '#3b82f6',
  secondary: '#a78bfa',
  accent: '#f472b6',
  success: '#4ade80',
  warning: '#fbbf24',
  danger: '#f87171',
};
const toneColor = (t) => TONES[t] || TONES.primary;

// Vibrant aura gradient picked deterministically from the archetype text so the
// same recap always looks the same (and different archetypes vary).
const AURA_PRESETS = [
  'radial-gradient(circle at 15% 15%, rgba(255,150,0,0.40), transparent 45%), radial-gradient(circle at 85% 80%, rgba(255,51,102,0.38), transparent 52%), radial-gradient(circle at 50% 50%, rgba(88,204,2,0.16), transparent 60%)',
  'radial-gradient(circle at 12% 20%, rgba(28,176,246,0.42), transparent 46%), radial-gradient(circle at 88% 78%, rgba(125,60,255,0.40), transparent 52%), radial-gradient(circle at 50% 50%, rgba(74,222,128,0.16), transparent 60%)',
  'radial-gradient(circle at 82% 16%, rgba(255,210,0,0.38), transparent 46%), radial-gradient(circle at 16% 82%, rgba(88,204,2,0.36), transparent 52%), radial-gradient(circle at 50% 45%, rgba(28,176,246,0.16), transparent 60%)',
  'radial-gradient(circle at 20% 18%, rgba(244,114,182,0.42), transparent 46%), radial-gradient(circle at 85% 76%, rgba(125,60,255,0.38), transparent 52%), radial-gradient(circle at 50% 55%, rgba(56,189,248,0.16), transparent 60%)',
];
const auraGradient = computed(() => {
  const key = (data.value?.diet_archetype || '').split('').reduce((a, c) => a + c.charCodeAt(0), 0);
  return AURA_PRESETS[key % AURA_PRESETS.length];
});

const avatarInitial = computed(() =>
  (auth.user?.first_name || auth.user?.handle || data.value?.user?.username || '?').charAt(0).toUpperCase()
);

// Leaderboard slide: show the mini friend board only when the user actually has
// friends (friends_top is [self] otherwise). Solo → a rank + "add friends" nudge.
const hasFriends = computed(() => (data.value?.friends_top?.length || 0) > 1);
const rowInitial = (n) => (n || '?').slice(0, 1).toUpperCase();

const bentoCells = computed(() => {
  const s = data.value?.stats || {};
  return [
    { label: t('wrapped.summary.foods_logged'), value: s.total_foods ?? 0 },
    { label: t('wrapped.summary.days_logged'), value: s.logged_days ?? 0 },
    { label: t('wrapped.summary.day_streak'), value: s.streak ?? 0 },
    { label: t('wrapped.summary.top_fuel'), value: s.favorite_food ?? '—', small: true },
  ];
});
</script>

<template>
  <div class="backdrop" @click.self="close">
    <div class="card">
      <!-- Progress segments -->
      <div class="bars">
        <div v-for="(s, i) in slides" :key="i" class="bar">
          <div
            class="bar-fill"
            :style="{ width: i < car.index.value ? '100%' : i === car.index.value ? car.progress.value * 100 + '%' : '0%' }"
          />
        </div>
      </div>

      <button class="close" type="button" :aria-label="$t('common.close')" @click="close"><i class="fa-solid fa-xmark" /></button>

      <!-- Loading / error -->
      <div v-if="loading" class="state">
        <div class="spinner" />
        <p>{{ $t('wrapped.loading') }}</p>
      </div>
      <div v-else-if="error" class="state">
        <p>{{ error }}</p>
        <button class="state-btn" type="button" @click="close">{{ $t('wrapped.close') }}</button>
      </div>

      <!-- Slides (gesture surface) -->
      <div
        v-else
        class="stage"
        @pointerdown="onPointerDown"
        @pointerup="onPointerUp"
        @pointerleave="onPointerLeave"
      >
        <!-- 1. Aura / archetype -->
        <section v-show="slides[car.index.value] === 'aura'" class="slide aura" :style="{ backgroundImage: auraGradient }">
          <span class="kicker">{{ $t('wrapped.aura.kicker') }}</span>
          <h1 class="title">{{ data.diet_archetype }}</h1>
          <p class="desc">{{ data.archetype_desc }}</p>
          <p class="caption">{{ data.slide1_aura }}</p>
        </section>

        <!-- 2. Top badge -->
        <section v-show="slides[car.index.value] === 'badge'" class="slide center">
          <div class="badge-burst" :style="{ borderColor: toneColor(data.badge.tone), boxShadow: `0 12px 0 ${toneColor(data.badge.tone)}33` }">
            <i :class="['fa-solid', data.badge.icon]" :style="{ color: toneColor(data.badge.tone) }" />
          </div>
          <span class="kicker">{{ $t('wrapped.badge.kicker') }}</span>
          <h2 class="big-name">{{ data.badge.name }}</h2>
          <p class="caption">{{ data.slide2_topfood }}</p>
        </section>

        <!-- 3. Streak -->
        <section v-show="slides[car.index.value] === 'streak'" class="slide center streak">
          <div class="flame"><i class="fa-solid fa-fire" /></div>
          <div class="streak-num">{{ data.stats.streak }}<span>{{ $t('wrapped.streak.day_suffix') }}</span></div>
          <span class="kicker">{{ $t('wrapped.streak.kicker') }}</span>
          <p class="caption">{{ data.slide3_streak }}</p>
        </section>

        <!-- 4. Leaderboard. With friends: compact top-3 (+ the user's own row if
             outside it), their row highlighted. Solo: the rank + a nudge.
             PRIVACY: friends' name/avatar are in-app only — anonymize on export (P3). -->
        <section v-show="slides[car.index.value] === 'leaderboard'" class="slide center">
          <span class="kicker">{{ $t('wrapped.leaderboard.kicker') }}</span>

          <ol v-if="hasFriends" class="lb-mini">
            <li v-for="r in data.friends_top" :key="r.rank" class="lb-row" :class="{ me: r.is_current_user }">
              <span class="lb-rank" :class="'r' + r.rank">{{ r.rank }}</span>
              <span class="lb-av">
                <img v-if="r.profile_image" :src="r.profile_image" alt="" />
                <span v-else>{{ rowInitial(r.user_name) }}</span>
              </span>
              <span class="lb-name">{{ r.is_current_user ? $t('wrapped.leaderboard.you') : r.user_name }}</span>
              <span class="lb-xp">{{ r.weekly_xp }} XP</span>
            </li>
          </ol>

          <template v-else>
            <div class="solo-rank">{{ data.stats.leaderboard_rank }}</div>
            <p class="solo-note">{{ $t('wrapped.leaderboard.solo') }}</p>
          </template>

          <p class="caption">{{ data.slide4_leaderboard }}</p>
        </section>

        <!-- 5. Bento summary -->
        <section v-show="slides[car.index.value] === 'summary'" class="slide summary">
          <h2 class="sum-title">{{ $t('wrapped.summary.week_title', { name: data.user.username }) }}</h2>
          <div class="featured">
            <div>
              <span class="arch-tag">{{ data.diet_archetype }}</span>
              <div class="lvl">{{ $t('wrapped.summary.level', { n: data.user.level }) }}</div>
            </div>
            <div class="xp">
              <div class="xp-bar"><div :style="{ width: (data.user.progress_pct || 0) + '%' }" /></div>
              <small>{{ data.user.total_xp }} XP</small>
            </div>
          </div>
          <div class="bento">
            <div v-for="(c, i) in bentoCells" :key="i" class="cell">
              <span class="cell-label">{{ c.label }}</span>
              <strong class="cell-val" :class="{ sm: c.small }">{{ c.value }}</strong>
            </div>
          </div>
          <button class="done" type="button" @click="close">{{ $t('wrapped.done') }}</button>
        </section>
      </div>
    </div>
  </div>
</template>

<style scoped>
.backdrop {
  position: fixed;
  inset: 0;
  z-index: 200;
  background: rgba(0, 0, 0, 0.65);
  backdrop-filter: blur(6px);
  display: grid;
  place-items: center;
}
.card {
  position: relative;
  width: 100%;
  height: 100dvh;
  max-width: 480px;
  background: var(--bg);
  overflow: hidden;
  color: var(--text);
  user-select: none;
}
@media (min-width: 520px) {
  .card {
    height: min(92dvh, 880px);
    border-radius: 24px;
    box-shadow: 0 24px 60px rgba(0, 0, 0, 0.6);
  }
}

/* Progress segments */
.bars {
  position: absolute;
  top: 14px;
  left: 14px;
  right: 14px;
  z-index: 4;
  display: flex;
  gap: 6px;
}
.bar {
  flex: 1;
  height: 4px;
  border-radius: 4px;
  background: rgba(255, 255, 255, 0.25);
  overflow: hidden;
}
.bar-fill {
  height: 100%;
  background: #fff;
  border-radius: 4px;
}

.close {
  position: absolute;
  top: 26px;
  right: 16px;
  z-index: 5;
  width: 36px;
  height: 36px;
  display: grid;
  place-items: center;
  border-radius: 50%;
  background: rgba(0, 0, 0, 0.35);
  color: #fff;
  font-size: 16px;
}

.state {
  position: absolute;
  inset: 0;
  display: grid;
  place-content: center;
  justify-items: center;
  gap: 14px;
  color: var(--muted);
}
.spinner {
  width: 36px;
  height: 36px;
  border: 3px solid var(--border);
  border-top-color: var(--accent);
  border-radius: 50%;
  animation: spin 0.8s linear infinite;
}
@keyframes spin { to { transform: rotate(360deg); } }
.state-btn { background: var(--card); color: var(--text); border: 1px solid var(--border); padding: 10px 18px; border-radius: 10px; }

/* Slides */
.stage { position: absolute; inset: 0; }
.slide {
  position: absolute;
  inset: 0;
  padding: 76px 30px 40px;
  display: flex;
  flex-direction: column;
}
.slide.center { align-items: center; justify-content: center; text-align: center; gap: 8px; }

.kicker {
  display: inline-block;
  font-size: 13px;
  font-weight: 800;
  text-transform: uppercase;
  letter-spacing: 0.08em;
  color: var(--accent);
}
.title { font-size: clamp(34px, 9vw, 46px); font-weight: 900; line-height: 1.05; margin: 14px 0 16px; }
.desc { font-size: 17px; font-weight: 600; line-height: 1.4; color: var(--text); opacity: 0.92; }
.caption { margin-top: auto; font-size: 16px; font-weight: 700; line-height: 1.35; color: var(--text); opacity: 0.85; }
.slide.center .caption { margin-top: 12px; max-width: 90%; }

/* Aura background sits on the dark base. */
.aura { background-color: var(--bg); background-repeat: no-repeat; }

/* Badge slide */
.badge-burst {
  width: 168px;
  height: 168px;
  border-radius: 40px;
  border: 4px solid;
  background: var(--card);
  display: grid;
  place-items: center;
  transform: rotate(-6deg);
  margin-bottom: 26px;
}
.badge-burst i { font-size: 78px; }
.big-name { font-size: clamp(30px, 8vw, 40px); font-weight: 900; line-height: 1.1; margin: 6px 0 0; }

/* Streak slide */
.streak { background: radial-gradient(circle at 80% 18%, rgba(255,100,0,0.28), transparent 50%), radial-gradient(circle at 20% 82%, rgba(125,60,255,0.30), transparent 55%), var(--bg); }
.flame {
  width: 190px;
  height: 190px;
  border-radius: 50%;
  background: linear-gradient(135deg, #ff9600, #ff3366);
  display: grid;
  place-items: center;
  box-shadow: 0 14px 0 rgba(255, 255, 255, 0.12);
  animation: pulse 2s infinite alternate ease-in-out;
}
.flame i { font-size: 92px; color: #fff; filter: drop-shadow(0 5px 0 rgba(0, 0, 0, 0.18)); }
@keyframes pulse { from { transform: translateY(0) scale(1); } to { transform: translateY(-8px) scale(1.03); } }
.streak-num { font-size: 92px; font-weight: 900; line-height: 1; margin-top: 20px; }
.streak-num span { font-size: 38px; opacity: 0.7; margin-left: 4px; }

/* Leaderboard podium */
.podium { display: flex; align-items: flex-end; justify-content: center; gap: 12px; height: 280px; margin-bottom: 20px; }
.pedestal { width: 84px; border-radius: 14px 14px 0 0; border: 3px solid var(--border); background: var(--card); }
.pedestal.p1 { height: 220px; position: relative; background: linear-gradient(180deg, #fbbf24, #d99e16); border-color: #fbbf24; }
.pedestal.p2 { height: 150px; opacity: 0.55; }
.pedestal.p3 { height: 110px; opacity: 0.55; }
.avatar {
  position: absolute;
  top: -78px;
  left: 50%;
  transform: translateX(-50%);
  width: 72px;
  height: 72px;
  border-radius: 50%;
  border: 4px solid var(--text);
  background: var(--card);
  display: grid;
  place-items: center;
  font-size: 28px;
  font-weight: 900;
  overflow: hidden;
}
.avatar img { width: 100%; height: 100%; object-fit: cover; }
.rank { position: absolute; bottom: 14px; left: 0; right: 0; text-align: center; font-size: 56px; font-weight: 900; color: #1a1206; }

/* Friends mini-leaderboard (slide 4) */
.lb-mini { list-style: none; margin: 12px 0 0; padding: 0; width: 100%; max-width: 320px; display: flex; flex-direction: column; gap: 8px; }
.lb-row {
  display: flex; align-items: center; gap: 10px;
  background: var(--card); border: 1px solid var(--border); border-radius: 12px; padding: 10px 12px;
}
.lb-row.me { border-color: var(--accent); background: rgba(74, 222, 128, 0.08); }
.lb-rank { flex: none; width: 22px; text-align: center; font-weight: 900; font-size: 15px; color: var(--muted); }
.lb-rank.r1 { color: #fbbf24; }
.lb-rank.r2 { color: #cbd5e1; }
.lb-rank.r3 { color: #d8924e; }
.lb-av {
  flex: none; width: 34px; height: 34px; border-radius: 50%; overflow: hidden;
  background: var(--surface-2); color: var(--text); font-weight: 800; font-size: 14px;
  display: grid; place-items: center;
}
.lb-av img { width: 100%; height: 100%; object-fit: cover; }
.lb-name { flex: 1; min-width: 0; font-weight: 700; font-size: 14px; text-align: left; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.lb-row.me .lb-name { color: var(--accent); }
.lb-xp { flex: none; font-weight: 800; font-size: 13px; }
.solo-rank { font-size: 64px; font-weight: 900; color: var(--accent); line-height: 1; margin: 12px 0; }
.solo-note { font-size: 14px; color: var(--muted); max-width: 80%; }

/* Bento summary */
.summary { padding-top: 70px; gap: 16px; }
.sum-title { font-size: 26px; font-weight: 900; }
.featured {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 14px;
  background: linear-gradient(135deg, var(--card), #21252e);
  border: 2px solid var(--border);
  border-radius: 22px;
  padding: 20px;
}
.arch-tag {
  display: inline-block;
  font-size: 14px;
  font-weight: 900;
  text-transform: uppercase;
  letter-spacing: 0.02em;
  color: #04210f;
  background: var(--accent);
  padding: 7px 14px;
  border-radius: 12px;
}
.featured .lvl { margin-top: 12px; font-size: 22px; font-weight: 900; }
.featured .xp { width: 120px; flex: none; text-align: right; }
.xp-bar { height: 12px; background: var(--inset); border: 1px solid var(--border); border-radius: 999px; overflow: hidden; }
.xp-bar > div { height: 100%; background: var(--accent); }
.featured .xp small { color: var(--muted); font-size: 12px; }
.bento { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; }
.cell {
  background: var(--card);
  border: 2px solid var(--border);
  border-radius: 18px;
  padding: 18px;
  display: flex;
  flex-direction: column;
  gap: 8px;
  min-height: 96px;
  justify-content: space-between;
}
.cell-label { font-size: 12px; font-weight: 800; text-transform: uppercase; letter-spacing: 0.05em; color: var(--muted); }
.cell-val { font-size: 38px; font-weight: 900; line-height: 1; }
.cell-val.sm { font-size: 20px; line-height: 1.15; }
.done { margin-top: auto; min-height: 50px; border-radius: 12px; background: var(--accent); color: #04210f; font-size: 16px; font-weight: 800; }
</style>
