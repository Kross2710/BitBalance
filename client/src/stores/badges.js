// Nav badge counts, shared so any view can trigger a refresh (e.g. Profile
// after saving reminder prefs) and the bottom-nav / sidebar update immediately
// — no page reload or tab switch needed.
//
//   /intake  → meal-reminder nudges currently "due": only when reminders are
//              enabled, counting each reminder-enabled meal whose time has
//              passed today (Asia/Bangkok) and isn't logged yet. Mirrors the
//              dashboard nudge; 0 when reminders are off.
//   /friends → incoming friend requests awaiting a response.
import { defineStore } from 'pinia';
import { reactive } from 'vue';
import { api } from '../lib/api.js';

const MEALS = ['breakfast', 'lunch', 'dinner', 'snack'];

export const useBadgesStore = defineStore('badges', () => {
  const counts = reactive({ '/intake': 0, '/friends': 0 });

  async function refresh() {
    // Independent sources; a failure on one must not blank the others.
    // Background: this runs on mount + every tab change, so it must not drive the
    // global loading bar (would flash on every navigation).
    const [summary, pending, reminders] = await Promise.allSettled([
      api.get('/api/dashboard/summary', { background: true }),
      api.get('/api/social/pending-count', { background: true }),
      api.get('/api/reminders', { background: true }),
    ]);

    if (summary.status === 'fulfilled' && reminders.status === 'fulfilled') {
      const meals = summary.value.meal_categories || {}; // capitalized keys
      const r = reminders.value;
      if (r?.enabled) {
        const nowHM = new Date().toLocaleTimeString('en-GB', { hour12: false }).slice(0, 5);
        counts['/intake'] = MEALS.filter((m) => {
          const cfg = r.meals?.[m];
          if (!cfg?.enabled || nowHM < cfg.time) return false; // off or not due yet
          const cap = m.charAt(0).toUpperCase() + m.slice(1);
          return !(Number(meals[cap]) > 0); // not logged today
        }).length;
      } else {
        counts['/intake'] = 0; // reminders off → no nudge badge
      }
    }

    if (pending.status === 'fulfilled') {
      counts['/friends'] = Number(pending.value.count) || 0;
    }
  }

  return { counts, refresh };
});
