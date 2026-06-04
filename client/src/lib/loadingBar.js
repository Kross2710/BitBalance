// Global top loading bar controller. A thin bar at the top of the app signals
// "something is loading", driven by two independent sources:
//   - route navigation (a boolean: redirect-safe, so a multi-hop guard still
//     maps to a single bar) — set by router.js via navStart/navDone.
//   - in-flight API requests (a counter) — set by lib/api.js via reqStart/reqDone.
//     Background polls (friends/chat poll, nav-badge refresh) opt out so the bar
//     never flashes on the 15s timers.
// Rendered by components/LoadingBar.vue, which reads loadingState.
import { reactive } from 'vue';

export const loadingState = reactive({ progress: 0, visible: false });

let reqCount = 0;
let navActive = false;
let trickle = null;
let hideTimer = null;

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

// Recompute the visual state from the two sources after any change.
function sync() {
  if (active()) {
    const wasFinishing = !!hideTimer;
    if (hideTimer) {
      clearTimeout(hideTimer);
      hideTimer = null;
    }
    if (!loadingState.visible) {
      loadingState.visible = true;
      loadingState.progress = 8;
    } else if (wasFinishing || loadingState.progress >= 90) {
      // New work arrived just as we were snapping to done (e.g. the route's data
      // fetch starts the instant after navDone) — resume mid-way so the bar keeps
      // moving instead of freezing at 100%.
      loadingState.progress = Math.min(loadingState.progress, 75);
    }
    startTrickle();
  } else if (loadingState.visible && !hideTimer) {
    // Snap to 100%, then fade out (unless work resumes within the window).
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
