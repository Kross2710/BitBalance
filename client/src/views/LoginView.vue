<script setup>
import { ref } from 'vue';
import { useRouter, useRoute, RouterLink } from 'vue-router';
import { useAuthStore } from '../stores/auth.js';

const auth = useAuthStore();
const router = useRouter();
const route = useRoute();

const email = ref('');
const password = ref('');
const remember = ref(false);
const error = ref('');
const busy = ref(false);

async function onSubmit() {
  error.value = '';
  busy.value = true;
  try {
    await auth.login(email.value, password.value, remember.value);
    router.push(route.query.redirect || { name: 'dashboard' });
  } catch (e) {
    error.value = e.message;
  } finally {
    busy.value = false;
  }
}
</script>

<template>
  <main class="auth">
    <div class="hero">
      <span class="mark">B</span>
      <h1>BitBalance</h1>
      <p class="tagline">Track calories. Build streaks. Hit your goals.</p>
    </div>

    <form class="card auth-card" @submit.prevent="onSubmit">
      <label for="login-email">Email</label>
      <input id="login-email" v-model="email" type="email" autocomplete="email" placeholder="you@example.com" required />
      <label for="login-password">Password</label>
      <input id="login-password" v-model="password" type="password" autocomplete="current-password" placeholder="Your password" required />
      <label class="remember">
        <input v-model="remember" type="checkbox" />
        <span>Keep me signed in for 30 days</span>
      </label>
      <button type="submit" class="submit" :disabled="busy">
        {{ busy ? 'Signing in…' : 'Sign in' }}
      </button>
      <p v-if="error" class="error">{{ error }}</p>
      <p class="muted switch">No account? <RouterLink to="/signup">Sign up</RouterLink></p>
    </form>
  </main>
</template>

<style scoped>
.auth {
  max-width: 400px;
  margin: 0 auto;
  min-height: 100vh;
  display: flex;
  flex-direction: column;
  justify-content: center;
  padding: 24px 18px;
  /* Subtle accent glow so the screen doesn't read as flat dark-on-dark. */
  background: radial-gradient(120% 60% at 50% 0%, rgba(74, 222, 128, 0.08), transparent 60%);
}
.hero { text-align: center; margin-bottom: 22px; }
.mark {
  display: inline-grid;
  place-items: center;
  width: 52px;
  height: 52px;
  border-radius: 14px;
  background: var(--accent);
  color: #04210f;
  font-weight: 800;
  font-size: 26px;
  margin-bottom: 12px;
}
.hero h1 { margin: 0; font-size: 26px; }
.tagline { color: var(--muted); font-size: 14px; margin: 6px 0 0; }

.auth-card {
  padding: 22px;
  /* Lift the card off the background with a clearer edge + soft shadow. */
  border-color: #353c49;
  box-shadow: 0 12px 32px rgba(0, 0, 0, 0.45);
}
.auth-card label { font-size: 13px; color: var(--muted); display: block; margin-bottom: 6px; }
.auth-card label + input { margin-bottom: 14px; }
/* Remember-me row: a label wrapping the checkbox (override the block label rule). */
.auth-card .remember {
  display: flex;
  align-items: center;
  gap: 8px;
  margin: 2px 0 6px;
  min-height: 44px;
  color: var(--text);
  font-size: 13px;
  cursor: pointer;
}
.auth-card .remember input { width: auto; margin: 0; }
.submit { width: 100%; margin-top: 8px; min-height: 50px; font-size: 16px; }
.muted { color: var(--muted); font-size: 13px; }
.switch { text-align: center; margin: 16px 0 0; }
a { color: var(--accent); font-weight: 600; }
</style>
