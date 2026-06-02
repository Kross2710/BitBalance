<script setup>
import { ref } from 'vue';
import { useRouter, RouterLink } from 'vue-router';
import { useAuthStore } from '../stores/auth.js';

const auth = useAuthStore();
const router = useRouter();

const form = ref({ first_name: '', last_name: '', email: '', password: '', confirm_password: '' });
const error = ref('');
const busy = ref(false);

async function onSubmit() {
  error.value = '';
  busy.value = true;
  try {
    await auth.register(form.value);
    // Fresh accounts need onboarding; the router guard routes there.
    router.push({ name: 'onboarding' });
  } catch (e) {
    error.value = e.message;
  } finally {
    busy.value = false;
  }
}
</script>

<template>
  <main style="max-width: 420px; margin: 8vh auto; padding: 0 16px">
    <h1 style="text-align: center">Create account</h1>
    <form class="card" @submit.prevent="onSubmit">
      <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 10px">
        <div>
          <label for="signup-first">First name</label>
          <input id="signup-first" v-model="form.first_name" autocomplete="given-name" required />
        </div>
        <div>
          <label for="signup-last">Last name</label>
          <input id="signup-last" v-model="form.last_name" autocomplete="family-name" required />
        </div>
      </div>
      <label for="signup-email" style="display: block; margin-top: 12px">Email</label>
      <input id="signup-email" v-model="form.email" type="email" autocomplete="email" required />
      <label for="signup-password" style="display: block; margin-top: 12px">Password</label>
      <input id="signup-password" v-model="form.password" type="password" autocomplete="new-password" required />
      <small class="muted">Min 8 chars, with upper, lower and a number.</small>
      <label for="signup-confirm" style="display: block; margin-top: 12px">Confirm password</label>
      <input id="signup-confirm" v-model="form.confirm_password" type="password" autocomplete="new-password" required />
      <button type="submit" :disabled="busy" style="width: 100%; margin-top: 18px">
        {{ busy ? 'Creating…' : 'Sign up' }}
      </button>
      <p v-if="error" class="error">{{ error }}</p>
      <p class="muted" style="text-align: center; margin: 14px 0 0">
        Already have an account? <RouterLink to="/login">Sign in</RouterLink>
      </p>
    </form>
  </main>
</template>

<style scoped>
.muted { color: var(--muted); font-size: 13px; }
a { color: var(--accent); }
</style>
