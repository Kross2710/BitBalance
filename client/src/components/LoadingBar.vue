<script setup>
// Thin top-of-app progress bar. Reads the shared loadingState; the width is a
// GPU-friendly scaleX transform. Mounted once in App.vue so it overlays every
// view. Purely decorative for assistive tech (the views announce their own state).
import { loadingState } from '../lib/loadingBar.js';
</script>

<template>
  <div
    class="loadbar"
    :class="{ on: loadingState.visible }"
    :style="{ transform: `scaleX(${loadingState.progress / 100})` }"
    aria-hidden="true"
  />
</template>

<style scoped>
.loadbar {
  position: fixed;
  top: 0;
  left: 0;
  right: 0;
  height: 3px;
  transform-origin: 0 50%;
  transform: scaleX(0);
  background: var(--accent);
  box-shadow: 0 0 8px var(--accent);
  z-index: 2000;
  opacity: 0;
  transition: transform 0.2s ease, opacity 0.3s ease;
  pointer-events: none;
}
.loadbar.on {
  opacity: 1;
}
@media (prefers-reduced-motion: reduce) {
  .loadbar {
    transition: opacity 0.2s ease;
  }
}
</style>
