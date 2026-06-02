<script setup>
import { ref } from 'vue';
import { useRouter, useRoute, RouterLink } from 'vue-router';
import { useAuthStore } from '../stores/auth.js';

const auth = useAuthStore();
const router = useRouter();
const route = useRoute();

const email = ref('');
const password = ref('');
const error = ref('');
const busy = ref(false);

async function onSubmit() {
  error.value = '';
  busy.value = true;
  try {
    await auth.login(email.value, password.value);
    router.push(route.query.redirect || { name: 'dashboard' });
  } catch (e) {
    error.value = e.message;
  } finally {
    busy.value = false;
  }
}
</script>

<template>
  <main style="max-width: 380px; margin: 12vh auto; padding: 0 16px">
    <h1 style="text-align: center">BitBalance</h1>
    <form class="card" @submit.prevent="onSubmit">
      <label for="login-email">Email</label>
      <input id="login-email" v-model="email" type="email" autocomplete="email" required />
      <label for="login-password" style="display: block; margin-top: 14px">Password</label>
      <input id="login-password" v-model="password" type="password" autocomplete="current-password" required />
      <button type="submit" :disabled="busy" style="width: 100%; margin-top: 18px">
        {{ busy ? 'Signing in…' : 'Sign in' }}
      </button>
      <p v-if="error" class="error">{{ error }}</p>
      <p class="muted" style="text-align: center; margin: 14px 0 0">
        No account? <RouterLink to="/signup">Sign up</RouterLink>
      </p>
    </form>
  </main>
</template>

<style scoped>
.muted { color: var(--muted); font-size: 13px; }
a { color: var(--accent); }
</style>
