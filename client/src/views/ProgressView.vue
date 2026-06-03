<script setup>
// Progress / achievements page — the daily "how am I doing" view, ported from
// dashboard-progress.php but reshaped for mobile: a compact level/XP/streak
// header, the 3 achievements closest to unlocking, an unlocked-badge grid,
// personal records, and the full list tucked behind a toggle. Tapping any
// achievement opens its detail (description + tiers) in a bottom sheet. Reached
// from the avatar menu; Wrapped stays the weekly "story", this is the everyday view.
import { ref, computed, onMounted } from 'vue';
import { api } from '../lib/api.js';
import { t } from '../i18n/index.js';
import AchievementCard from '../components/AchievementCard.vue';
import BottomSheet from '../components/BottomSheet.vue';

const data = ref(null);
const loading = ref(true);
const error = ref('');
const showAll = ref(false);
const detail = ref(null);

async function load() {
  loading.value = true;
  error.value = '';
  try {
    data.value = await api.get('/api/progress');
  } catch (e) {
    error.value = e.message;
  } finally {
    loading.value = false;
  }
}
onMounted(load);

// Prefer localized copy, fall back to the server string (English) so an
// unkeyed achievement still renders instead of a raw i18n key.
function loc(key, fallback) {
  const s = t(key);
  return s === key ? fallback : s;
}
const fmt = (n) => Number(n || 0).toLocaleString();

const summary = computed(() => data.value?.summary ?? null);
const xp = computed(() => summary.value?.xp ?? {});
const achievements = computed(() => data.value?.achievements ?? []);

// XP ring geometry (r=34 in an 80x80 viewBox).
const RING_C = 2 * Math.PI * 34;
const ringOffset = computed(() => RING_C * (1 - Math.min(100, xp.value.progress_pct || 0) / 100));

// The 3 closest to their next tier (most progress first), still in progress.
const nextUp = computed(() =>
  achievements.value
    .filter((a) => !a.is_complete)
    .sort((a, b) => b.progress_pct - a.progress_pct)
    .slice(0, 3)
);
// Earned badges, strongest tier first.
const unlocked = computed(() => achievements.value.filter((a) => a.level > 0).sort((a, b) => b.level - a.level));

const TONES = {
  primary: { c: '#4ade80', bg: 'rgba(74, 222, 128, 0.14)' },
  secondary: { c: '#60a5fa', bg: 'rgba(96, 165, 250, 0.14)' },
  accent: { c: '#a78bfa', bg: 'rgba(167, 139, 250, 0.14)' },
  success: { c: '#34d399', bg: 'rgba(52, 211, 153, 0.14)' },
  warning: { c: '#fbbf24', bg: 'rgba(251, 191, 36, 0.14)' },
};
const toneOf = (a) => TONES[a?.tone] || TONES.primary;

// Records: localize label + unit; favorite food carries the dish name + a
// "{n} logs" caption parsed from the server's unit string.
const recordViews = computed(() =>
  (data.value?.records ?? []).map((r) => {
    if (r.key === 'favorite_food') {
      if (!r.unit) return { icon: r.icon, label: t('progress.records.favorite_food'), value: t('progress.records.favorite_empty'), caption: '' };
      const n = parseInt(r.unit, 10) || 0;
      return { icon: r.icon, label: t('progress.records.favorite_food'), value: r.value, caption: t('progress.records.favorite_logs', { n }) };
    }
    const captions = { longest_streak: t('progress.unit.days'), most_foods_day: t('progress.unit.foods'), most_xp_day: 'XP' };
    return { icon: r.icon, label: t(`progress.records.${r.key}`), value: fmt(r.value), caption: captions[r.key] || '' };
  })
);

// Detail-sheet derived copy.
const detailDesc = computed(() => (detail.value ? loc(`progress.ach.${detail.value.id}.desc`, detail.value.description) : ''));
const detailUnit = computed(() => (detail.value ? loc(`progress.ach.${detail.value.id}.unit`, detail.value.unit) : ''));

function openDetail(a) {
  detail.value = a;
}
</script>

<template>
  <main class="progress">
    <header class="page-head"><h1>{{ $t('progress.title') }}</h1></header>

    <p v-if="loading" class="muted state">{{ $t('common.loading') }}</p>
    <p v-else-if="error" class="error state">{{ error }}</p>

    <template v-else-if="summary">
      <!-- Hero: XP ring + streak -->
      <section class="card hero">
        <div class="ring">
          <svg viewBox="0 0 80 80" aria-hidden="true">
            <circle class="ring-track" cx="40" cy="40" r="34" />
            <circle
              class="ring-fill"
              cx="40"
              cy="40"
              r="34"
              :stroke-dasharray="RING_C"
              :stroke-dashoffset="ringOffset"
              transform="rotate(-90 40 40)"
            />
          </svg>
          <div class="ring-label">
            <small>LV</small>
            <strong>{{ xp.current_level }}</strong>
          </div>
        </div>
        <div class="hero-meta">
          <div class="xp-bar"><div :style="{ width: (xp.progress_pct || 0) + '%' }" /></div>
          <small class="muted">{{ $t('progress.hero.xp', { into: xp.xp_into_level, next: xp.xp_for_next }) }}</small>
          <span class="streak"><i class="fa-solid fa-fire" /> {{ summary.current_streak }}
            <span class="muted">{{ $t('progress.hero.streak') }}</span>
          </span>
        </div>
      </section>

      <!-- Summary stats -->
      <section class="tiles">
        <div class="card tile">
          <span class="muted">{{ $t('progress.stat.total_xp') }}</span>
          <strong>{{ fmt(xp.total_xp) }}</strong>
        </div>
        <div class="card tile">
          <span class="muted">{{ $t('progress.stat.unlocked') }}</span>
          <strong>{{ summary.unlocked }}/{{ summary.total_achievements }}</strong>
        </div>
        <div class="card tile">
          <span class="muted">{{ $t('progress.stat.foods') }}</span>
          <strong>{{ fmt(summary.total_foods) }}</strong>
        </div>
      </section>

      <!-- Next to unlock -->
      <section class="sec">
        <h2 class="sec-title">{{ $t('progress.section.next') }}</h2>
        <div v-if="nextUp.length" class="stack">
          <AchievementCard v-for="a in nextUp" :key="a.id" :ach="a" @open="openDetail" />
        </div>
        <p v-else class="muted state">{{ $t('progress.empty.next') }}</p>
      </section>

      <!-- Unlocked badge grid -->
      <section class="sec">
        <h2 class="sec-title">{{ $t('progress.section.unlocked') }}</h2>
        <div v-if="unlocked.length" class="badges">
          <button
            v-for="a in unlocked"
            :key="a.id"
            type="button"
            class="badge-chip"
            @click="openDetail(a)"
          >
            <span class="bc-ico" :style="{ color: toneOf(a).c, background: toneOf(a).bg }">
              <i class="fa-solid" :class="a.icon" />
            </span>
            <span class="bc-name">{{ a.name }}</span>
            <span class="bc-lv muted">{{ $t('progress.hero.level', { n: a.level }) }}</span>
          </button>
        </div>
        <p v-else class="muted state">{{ $t('progress.empty.unlocked') }}</p>
      </section>

      <!-- Personal records -->
      <section class="sec">
        <h2 class="sec-title">{{ $t('progress.section.records') }}</h2>
        <div class="records">
          <div v-for="r in recordViews" :key="r.label" class="card rec">
            <span class="rec-ico"><i class="fa-solid" :class="r.icon" /></span>
            <span class="rec-body">
              <span class="muted rec-label">{{ r.label }}</span>
              <strong class="rec-val">{{ r.value }}</strong>
              <small v-if="r.caption" class="muted">{{ r.caption }}</small>
            </span>
          </div>
        </div>
      </section>

      <!-- All achievements (collapsible) -->
      <section class="sec">
        <button type="button" class="sec-toggle" @click="showAll = !showAll" :aria-expanded="showAll">
          <h2 class="sec-title">{{ $t('progress.section.all') }}</h2>
          <span class="muted toggle-hint">
            {{ showAll ? $t('progress.all.hide') : $t('progress.all.show') }}
            <i class="fa-solid" :class="showAll ? 'fa-chevron-up' : 'fa-chevron-down'" />
          </span>
        </button>
        <div v-if="showAll" class="stack">
          <AchievementCard v-for="a in achievements" :key="a.id" :ach="a" @open="openDetail" />
        </div>
      </section>
    </template>

    <!-- Achievement detail -->
    <BottomSheet :open="!!detail" :title="detail?.name" @close="detail = null">
      <div v-if="detail" class="detail">
        <div class="detail-head">
          <span class="detail-ico" :style="{ color: toneOf(detail).c, background: toneOf(detail).bg }">
            <i class="fa-solid" :class="detail.icon" />
          </span>
          <p class="detail-desc">{{ detailDesc }}</p>
        </div>
        <div class="detail-prog">
          <div class="xp-bar"><div :style="{ width: detail.progress_pct + '%', background: toneOf(detail).c }" /></div>
          <small class="muted">
            <strong>{{ fmt(detail.value) }}</strong>
            <template v-if="!detail.is_complete"> / {{ fmt(detail.next_target) }}</template>
            {{ detailUnit }}
          </small>
        </div>
        <div class="tiers">
          <span class="muted tiers-title">{{ $t('progress.detail.tiers') }}</span>
          <div v-for="(th, i) in detail.thresholds" :key="i" class="tier-row" :class="{ done: detail.level > i }">
            <span class="tier-n">{{ $t('progress.detail.tier', { n: i + 1 }) }}</span>
            <span class="tier-th">{{ fmt(th) }} {{ detailUnit }}</span>
            <i v-if="detail.level > i" class="fa-solid fa-check" />
          </div>
        </div>
      </div>
    </BottomSheet>
  </main>
</template>

<style scoped>
.progress {
  max-width: 820px;
  margin: 0 auto;
  padding: 8px 16px 32px;
  display: flex;
  flex-direction: column;
  gap: 14px;
}
.page-head h1 { margin: 4px 0 0; font-size: 20px; font-weight: 800; }
.state { margin: 8px 2px; font-size: 14px; }
.card { background: var(--card); border: 1px solid var(--border); border-radius: 14px; padding: 16px; }

/* Hero */
.hero { display: flex; align-items: center; gap: 16px; }
.ring { position: relative; flex: none; width: 80px; height: 80px; }
.ring svg { width: 80px; height: 80px; }
.ring-track { fill: none; stroke: var(--inset); stroke-width: 6; }
.ring-fill { fill: none; stroke: #a78bfa; stroke-width: 6; stroke-linecap: round; transition: stroke-dashoffset 0.5s ease; }
.ring-label { position: absolute; inset: 0; display: grid; place-content: center; text-align: center; line-height: 1; }
.ring-label small { font-size: 10px; color: var(--muted); font-weight: 700; letter-spacing: 0.08em; }
.ring-label strong { font-size: 24px; font-weight: 800; }
.hero-meta { flex: 1; min-width: 0; display: flex; flex-direction: column; gap: 6px; }
.xp-bar { height: 8px; background: var(--inset); border-radius: 4px; overflow: hidden; }
.xp-bar > div { height: 100%; background: #a78bfa; transition: width 0.4s ease; }
.streak { font-weight: 800; color: #fb923c; display: flex; align-items: center; gap: 6px; margin-top: 2px; }
.streak .muted { font-weight: 600; font-size: 12px; }

/* Summary stats */
.tiles { display: grid; grid-template-columns: repeat(3, 1fr); gap: 10px; }
.tile { display: flex; flex-direction: column; gap: 3px; padding: 12px 14px; }
.tile .muted { font-size: 12px; }
.tile strong { font-size: 18px; }

/* Sections */
.sec { display: flex; flex-direction: column; gap: 10px; }
.sec-title { margin: 0; font-size: 14px; font-weight: 800; letter-spacing: 0.01em; }
.stack { display: flex; flex-direction: column; gap: 8px; }
.sec-toggle {
  display: flex; align-items: center; justify-content: space-between;
  width: 100%; background: transparent; border: none; padding: 0; min-height: 0; color: var(--text);
}
.toggle-hint { font-size: 12px; font-weight: 700; display: inline-flex; align-items: center; gap: 6px; }

/* Unlocked badge grid */
.badges { display: grid; grid-template-columns: repeat(auto-fill, minmax(150px, 1fr)); gap: 8px; }
.badge-chip {
  display: flex; align-items: center; gap: 10px;
  padding: 10px; background: var(--card); border: 1px solid var(--border); border-radius: 12px;
  color: var(--text); text-align: left; min-height: 0;
}
.badge-chip:hover { border-color: var(--field-border); }
.bc-ico { flex: none; width: 34px; height: 34px; border-radius: 9px; display: grid; place-items: center; font-size: 15px; }
.bc-name { flex: 1; min-width: 0; font-weight: 700; font-size: 13px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.bc-lv { flex: none; font-size: 11px; font-weight: 700; }

/* Records */
.records { display: grid; grid-template-columns: repeat(2, 1fr); gap: 10px; }
.rec { display: flex; align-items: center; gap: 12px; padding: 14px; }
.rec-ico { flex: none; width: 36px; height: 36px; border-radius: 10px; display: grid; place-items: center; background: var(--inset); color: var(--muted); font-size: 15px; }
.rec-body { display: flex; flex-direction: column; gap: 1px; min-width: 0; }
.rec-label { font-size: 12px; }
.rec-val { font-size: 18px; font-weight: 800; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.rec-body small { font-size: 11px; }

/* Detail sheet */
.detail { display: flex; flex-direction: column; gap: 16px; padding-bottom: 6px; }
.detail-head { display: flex; align-items: center; gap: 14px; }
.detail-ico { flex: none; width: 52px; height: 52px; border-radius: 14px; display: grid; place-items: center; font-size: 22px; }
.detail-desc { margin: 0; color: var(--muted); font-size: 14px; line-height: 1.5; }
.detail-prog { display: flex; flex-direction: column; gap: 6px; }
.detail-prog .xp-bar { height: 10px; }
.detail-prog small strong { color: var(--text); }
.tiers { display: flex; flex-direction: column; gap: 6px; }
.tiers-title { font-size: 12px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.05em; margin-bottom: 2px; }
.tier-row {
  display: flex; align-items: center; gap: 10px;
  padding: 9px 12px; background: var(--inset); border-radius: 10px; font-size: 13px;
}
.tier-row.done { color: var(--text); }
.tier-row .tier-n { flex: none; font-weight: 700; min-width: 56px; }
.tier-row .tier-th { flex: 1; color: var(--muted); }
.tier-row.done .tier-th { color: var(--text); }
.tier-row .fa-check { flex: none; color: var(--accent); }

@media (max-width: 380px) {
  .tiles { grid-template-columns: 1fr 1fr; }
  .tiles .tile:last-child { grid-column: 1 / -1; }
}
</style>
