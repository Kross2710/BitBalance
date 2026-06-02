import { defineConfig } from 'vite';
import vue from '@vitejs/plugin-vue';

// Dev proxy: the SPA calls "/api/..." and Vite forwards it to the Express
// server. This keeps requests SAME-ORIGIN, so the session cookie just works
// and we don't depend on CORS during development.
export default defineConfig({
  plugins: [vue()],
  server: {
    port: 5173,
    proxy: {
      '/api': {
        target: 'http://localhost:3000',
        changeOrigin: true,
      },
    },
  },
});
