// Global top loading bar controller. A thin bar at the top of the app signals
// "something is loading", driven by two independent sources:
//   - route navigation (a boolean: redirect-safe, so a multi-hop guard still
//     maps to a single bar) — set by router.js via navStart/navDone.
//   - in-flight API requests (a counter) — set by lib/api.js via reqStart/reqDone.
//     Background polls (friends/chat poll, nav-badge refresh, type-ahead search,
//     the dashboard's skeleton-backed data load) opt out via { background: true }.
//
// Philosophy: page content uses SKELETONS; the bar is only for work that's
// actually slow. So the reveal is DEFERRED by SHOW_DELAY — anything that finishes
// first (fast navigations, sub-300ms fetches) never flashes the bar. It surfaces
// only for genuinely long work (slow route change, Wrapped, AI Coach, barcode/AI
// photo, heavy admin/trainer data).
import { reactive } from 'vue';

export const loadingState = reactive({ progress: 0, visible: false });

let reqCount = 0;
let navActive = false;
let trickle = null;
let hideTimer = null;
let showTimer = null;

const SHOW_DELAY = 280; // ms — below this, work is "fast" and the bar stays hidden.

const active = () => navActive || reqCount > 0;

function startTrickle() {
  if (trickle) return;
  trickle = setInterval(() => {
    // Ease toward 90% and stall there until the work actually finishes, so the
    // bar feels alive without ever pretending to be done early.
    const remaining = 90 - loadingState.progress;
    if (remaining > 0) loadingState.progress += Math.max(0.4, remaining * 0.07);
  }, 220);
}
function stopTrickle() {
  if (trickle) {
    clearInterval(trickle);
    trickle = null;
  }
}

function reveal() {
  loadingState.visible = true;
  loadingState.progress = 8;
  startTrickle();
}

// Recompute the visual state from the two sources after any change.
function sync() {
  if (active()) {
    if (hideTimer) {
      clearTimeout(hideTimer);
      hideTimer = null;
    }
    if (loadingState.visible) {
      // Already on. If we'd snapped toward done (a finish that got cancelled when
      // new work arrived), resume mid-way so the bar keeps moving, not frozen at 100%.
      if (loadingState.progress >= 90) loadingState.progress = 75;
      startTrickle();
    } else if (!showTimer) {
      // Defer the reveal — fast work finishes before this fires and never shows.
      showTimer = setTimeout(() => {
        showTimer = null;
        if (active()) reveal();
      }, SHOW_DELAY);
    }
  } else {
    // Idle. If the bar never outlived the delay, cancel the pending reveal (no flash).
    if (showTimer) {
      clearTimeout(showTimer);
      showTimer = null;
    }
    if (loadingState.visible && !hideTimer) {
      stopTrickle();
      loadingState.progress = 100;
      hideTimer = setTimeout(() => {
        hideTimer = null;
        if (!active()) {
          loadingState.visible = false;
          loadingState.progress = 0;
        }
      }, 280);
    }
  }
}

export function reqStart() {
  reqCount++;
  sync();
}
export function reqDone() {
  reqCount = Math.max(0, reqCount - 1);
  sync();
}
export function navStart() {
  navActive = true;
  sync();
}
export function navDone() {
  navActive = false;
  sync();
}
