// Auth store — holds the current user and bootstraps it from /api/auth/me on
// app load so a page refresh keeps the session.
import { defineStore } from 'pinia';
import { ref } from 'vue';
import { api } from '../lib/api.js';

export const useAuthStore = defineStore('auth', () => {
  const user = ref(null);
  const ready = ref(false); // becomes true once the initial /me check resolves
  // Which third-party sign-in providers the server has configured (e.g. Google).
  const providers = ref({ google: false });

  // Load once for the login/signup views so unconfigured buttons stay hidden.
  async function loadProviders() {
    try {
      providers.value = await api.get('/api/auth/providers');
    } catch {
      providers.value = { google: false };
    }
  }

  async function bootstrap() {
    try {
      user.value = await api.get('/api/auth/me');
    } catch {
      user.value = null;
    } finally {
      ready.value = true;
    }
  }

  async function login(email, password, remember = false) {
    user.value = await api.post('/api/auth/login', { email, password, remember });
    return user.value;
  }

  async function register(payload) {
    user.value = await api.post('/api/auth/register', payload);
    return user.value;
  }

  // Called after onboarding completes so the guard stops redirecting back to it.
  function markOnboarded() {
    if (user.value) user.value.needs_onboarding = false;
  }

  async function logout() {
    await api.post('/api/auth/logout');
    user.value = null;
  }

  return { user, ready, providers, loadProviders, bootstrap, login, register, logout, markOnboarded };
});
