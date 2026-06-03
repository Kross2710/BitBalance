// Celebration-toast queue (module-level singleton). Any view can fire a
// level-up / achievement-unlock toast via celebrate(); a single <UnlockToast>
// mounted in the app renders them one at a time, each auto-dismissing before
// the next. Decoupled this way so the trigger (a food log in IntakeView) and
// the UI (the toast) don't have to know about each other.
import { ref } from 'vue';

const queue = ref([]);
const current = ref(null);
let timer = null;
let seq = 0;

function next() {
  clearTimeout(timer);
  if (!queue.value.length) {
    current.value = null;
    return;
  }
  current.value = queue.value.shift();
  timer = setTimeout(next, 3400);
}

/**
 * Enqueue one or more celebration items. Item shapes:
 *   { type: 'levelup', level }
 *   { type: 'unlock',  name, icon, tone }          // first tier of an achievement
 *   { type: 'tier',    name, icon, tone, level }   // climbed to a higher tier
 */
export function celebrate(items) {
  const list = (Array.isArray(items) ? items : [items]).filter(Boolean);
  if (!list.length) return;
  for (const it of list) it.k = ++seq; // stable key so each toast re-animates
  queue.value.push(...list);
  if (!current.value) next();
}

/** Dismiss the visible toast and advance to the next queued one (if any). */
export function dismissToast() {
  next();
}

export function useUnlockToast() {
  return { current };
}
