<script setup>
import { ref, onMounted } from 'vue';
import { useRouter, useRoute, RouterLink } from 'vue-router';
import { useAuthStore } from '../stores/auth.js';
import { setLocale } from '../i18n/index.js';
import GoogleSignInButton from '../components/GoogleSignInButton.vue';
import LocaleSwitcher from '../components/LocaleSwitcher.vue';

const auth = useAuthStore();
const router = useRouter();
const route = useRoute();

const email = ref('');
const password = ref('');
const remember = ref(false);
// Surface an error bounced back by the Google OAuth redirect (e.g. cancelled).
const error = ref(typeof route.query.error === 'string' ? route.query.error : '');
const busy = ref(false);

onMounted(() => auth.loadProviders());

async function onSubmit() {
  error.value = '';
  busy.value = true;
  try {
    const user = await auth.login(email.value, password.value, remember.value);
    // Adopt the account's stored language (a guest may have toggled to something else).
    if (user?.language_preference) setLocale(user.language_preference, { persist: false });
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
      <p class="tagline">{{ $t('auth.tagline') }}</p>
      <LocaleSwitcher class="hero-locale" />
    </div>

    <form class="card auth-card" @submit.prevent="onSubmit">
      <label for="login-email">{{ $t('auth.email') }}</label>
      <input id="login-email" v-model="email" type="email" autocomplete="email" placeholder="you@example.com" required />
      <label for="login-password">{{ $t('auth.password') }}</label>
      <input id="login-password" v-model="password" type="password" autocomplete="current-password" :placeholder="$t('auth.password_placeholder')" required />
      <label class="remember">
        <input v-model="remember" type="checkbox" />
        <span>{{ $t('auth.remember') }}</span>
      </label>
      <button type="submit" class="submit" :disabled="busy">
        {{ busy ? $t('auth.signing_in') : $t('auth.sign_in') }}
      </button>
      <p v-if="error" class="error">{{ error }}</p>

      <template v-if="auth.providers.google">
        <div class="or"><span>{{ $t('auth.or') }}</span></div>
        <GoogleSignInButton from="login" />
      </template>

      <p class="muted switch">{{ $t('auth.no_account') }} <RouterLink to="/signup">{{ $t('auth.sign_up') }}</RouterLink></p>
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
  color: var(--on-accent);
  font-weight: 800;
  font-size: 26px;
  margin-bottom: 12px;
}
.hero h1 { margin: 0; font-size: 26px; }
.tagline { color: var(--muted); font-size: 14px; margin: 6px 0 0; }
.hero-locale { margin-top: 16px; }

.auth-card {
  padding: 22px;
  /* Lift the card off the background with a clearer edge + soft shadow. */
  border-color: var(--border);
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
/* "or" divider between the password form and the Google button. */
.or {
  display: flex;
  align-items: center;
  text-align: center;
  margin: 16px 0;
  color: var(--muted);
  font-size: 12px;
}
.or::before,
.or::after {
  content: '';
  flex: 1;
  height: 1px;
  background: var(--border);
}
.or span { padding: 0 12px; }
.muted { color: var(--muted); font-size: 13px; }
.switch { text-align: center; margin: 16px 0 0; }
a { color: var(--accent); font-weight: 600; }
</style>
