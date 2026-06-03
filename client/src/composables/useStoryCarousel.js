// Instagram-Stories carousel engine: an auto-advancing slide index with a
// per-slide progress value (0..1), pause-on-hold, and manual next/prev/goTo.
// The component owns the tap/swipe/hold gestures and the rendering; this just
// runs the timer and the bookkeeping so WrappedStory.vue stays declarative.
import { ref, onUnmounted } from 'vue';

const TICK_MS = 50;

export function useStoryCarousel({ count, durationMs = 5000, onComplete } = {}) {
  const index = ref(0);
  const progress = ref(0); // 0..1 within the current slide
  const paused = ref(false);

  let timer = null;
  let last = 0;

  const total = () => (typeof count === 'function' ? count() : count) || 1;

  function clear() {
    if (timer) {
      clearInterval(timer);
      timer = null;
    }
  }

  function tick() {
    const now = Date.now();
    const dt = now - last;
    last = now;
    if (paused.value) return;
    progress.value += dt / durationMs;
    if (progress.value >= 1) {
      progress.value = 1;
      next();
    }
  }

  function start() {
    clear();
    last = Date.now();
    timer = setInterval(tick, TICK_MS);
  }
  function stop() {
    clear();
  }

  function goTo(i) {
    index.value = Math.max(0, Math.min(total() - 1, i));
    progress.value = 0;
    last = Date.now();
  }
  function next() {
    if (index.value >= total() - 1) {
      stop();
      onComplete?.();
      return;
    }
    goTo(index.value + 1);
  }
  function prev() {
    goTo(index.value - 1);
  }
  function pause() {
    paused.value = true;
  }
  function resume() {
    paused.value = false;
    last = Date.now();
  }

  onUnmounted(stop);

  return { index, progress, paused, start, stop, next, prev, goTo, pause, resume };
}
