<script setup>
// "Continue with Google" button. A plain anchor (full-page navigation) into the
// server-side OAuth flow at /api/auth/google — Google redirects back to the SPA
// once signed in. `from` tells the server which page to bounce errors back to.
defineProps({
  from: { type: String, default: 'login' },
});
</script>

<template>
  <a class="google-btn" :href="`/api/auth/google?from=${from}`">
    <svg class="g-logo" viewBox="0 0 18 18" aria-hidden="true">
      <path fill="#4285F4" d="M17.64 9.2c0-.64-.06-1.25-.16-1.84H9v3.48h4.84a4.14 4.14 0 0 1-1.8 2.72v2.26h2.92c1.71-1.57 2.68-3.89 2.68-6.62z" />
      <path fill="#34A853" d="M9 18c2.43 0 4.47-.8 5.96-2.18l-2.92-2.26c-.81.54-1.84.86-3.04.86-2.34 0-4.32-1.58-5.03-3.7H.96v2.33A9 9 0 0 0 9 18z" />
      <path fill="#FBBC05" d="M3.97 10.72A5.4 5.4 0 0 1 3.69 9c0-.6.1-1.18.28-1.72V4.95H.96A9 9 0 0 0 0 9c0 1.45.35 2.82.96 4.05l3.01-2.33z" />
      <path fill="#EA4335" d="M9 3.58c1.32 0 2.5.45 3.44 1.35l2.58-2.59C13.46.89 11.43 0 9 0A9 9 0 0 0 .96 4.95l3.01 2.33C4.68 5.16 6.66 3.58 9 3.58z" />
    </svg>
    <span>{{ $t('auth.google') }}</span>
  </a>
</template>

<style scoped>
.google-btn {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 10px;
  width: 100%;
  min-height: 50px;
  padding: 0 16px;
  border-radius: 12px;
  border: 1px solid #3a4150;
  background: #fff;
  color: #1f1f1f;
  font-size: 15px;
  font-weight: 600;
  text-decoration: none;
  transition: background 0.15s ease, box-shadow 0.15s ease;
}
.google-btn:hover { background: #f5f5f5; box-shadow: 0 2px 8px rgba(0, 0, 0, 0.35); }
.google-btn:active { background: #ececec; }
.g-logo { width: 18px; height: 18px; flex: none; }
</style>
