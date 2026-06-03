// Standalone Vitest config (kept separate from the intentionally-uncommitted
// vite.config.js, which holds local dev proxy/allowedHosts). Vitest prefers
// vitest.config.* over vite.config.*, so this is the single source for tests.
import { defineConfig } from 'vitest/config';
import vue from '@vitejs/plugin-vue';

export default defineConfig({
  plugins: [vue()],
  test: {
    environment: 'jsdom',
    include: ['test/**/*.test.js'],
  },
});
