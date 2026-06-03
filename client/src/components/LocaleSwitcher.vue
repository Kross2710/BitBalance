<script setup>
// Compact language switcher for the pre-auth screens (Login / Signup), where
// there's no Profile to reach. One ghost button per locale, showing each
// language in its own name. Mirrors the role the global footer switcher plays
// for logged-out users in the PHP app. For guests setLocale only writes the
// cookie + reactive locale (no DB call until they have an account).
import { locale, setLocale, locales } from '../i18n/index.js';
</script>

<template>
  <div class="locale-switch" role="group" :aria-label="$t('profile.language.title')">
    <button
      v-for="(meta, code) in locales"
      :key="code"
      type="button"
      class="ls-btn"
      :class="{ active: code === locale }"
      :aria-pressed="code === locale"
      @click="setLocale(code)"
    >
      {{ meta.native }}
    </button>
  </div>
</template>

<style scoped>
.locale-switch {
  display: flex;
  justify-content: center;
  gap: 8px;
}
.ls-btn {
  /* Override the global accent button skin — these are quiet, secondary. */
  width: auto;
  min-height: 0;
  padding: 6px 12px;
  border-radius: 999px;
  border: 1px solid var(--border);
  background: transparent;
  color: var(--muted);
  font-size: 13px;
  font-weight: 600;
  cursor: pointer;
}
.ls-btn:hover {
  color: var(--text);
  border-color: var(--border);
}
.ls-btn.active {
  color: var(--on-accent);
  background: var(--accent);
  border-color: var(--accent);
}
</style>
