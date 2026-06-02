<script setup>
import { ref, onMounted } from 'vue';
import { useRouter, useRoute, RouterLink } from 'vue-router';
import { useAuthStore } from '../stores/auth.js';
import { locale, setLocale } from '../i18n/index.js';
import GoogleSignInButton from '../components/GoogleSignInButton.vue';
import LocaleSwitcher from '../components/LocaleSwitcher.vue';

const auth = useAuthStore();
const router = useRouter();
const route = useRoute();

const form = ref({ first_name: '', last_name: '', email: '', password: '', confirm_password: '' });
// Surface an error bounced back by the Google OAuth redirect.
const error = ref(typeof route.query.error === 'string' ? route.query.error : '');
const busy = ref(false);

onMounted(() => auth.loadProviders());

async function onSubmit() {
  error.value = '';
  busy.value = true;
  try {
    await auth.register(form.value);
    // Carry the guest's chosen/displayed language onto the new account (which
    // defaults to 'en') so they keep seeing the UI they signed up in.
    setLocale(locale.value, { persist: true });
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
    <h1 style="text-align: center">{{ $t('auth.create_account') }}</h1>
    <LocaleSwitcher style="margin: 12px 0 18px" />
    <form class="card" @submit.prevent="onSubmit">
      <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 10px">
        <div>
          <label for="signup-first">{{ $t('auth.first_name') }}</label>
          <input id="signup-first" v-model="form.first_name" autocomplete="given-name" required />
        </div>
        <div>
          <label for="signup-last">{{ $t('auth.last_name') }}</label>
          <input id="signup-last" v-model="form.last_name" autocomplete="family-name" required />
        </div>
      </div>
      <label for="signup-email" style="display: block; margin-top: 12px">{{ $t('auth.email') }}</label>
      <input id="signup-email" v-model="form.email" type="email" autocomplete="email" required />
      <label for="signup-password" style="display: block; margin-top: 12px">{{ $t('auth.password') }}</label>
      <input id="signup-password" v-model="form.password" type="password" autocomplete="new-password" required />
      <small class="muted">{{ $t('auth.password_hint') }}</small>
      <label for="signup-confirm" style="display: block; margin-top: 12px">{{ $t('auth.confirm_password') }}</label>
      <input id="signup-confirm" v-model="form.confirm_password" type="password" autocomplete="new-password" required />
      <button type="submit" :disabled="busy" style="width: 100%; margin-top: 18px">
        {{ busy ? $t('auth.creating') : $t('auth.sign_up') }}
      </button>
      <p v-if="error" class="error">{{ error }}</p>

      <template v-if="auth.providers.google">
        <div class="or"><span>{{ $t('auth.or') }}</span></div>
        <GoogleSignInButton from="signup" />
      </template>

      <p class="muted" style="text-align: center; margin: 14px 0 0">
        {{ $t('auth.have_account') }} <RouterLink to="/login">{{ $t('auth.sign_in') }}</RouterLink>
      </p>
    </form>
  </main>
</template>

<style scoped>
.muted { color: var(--muted); font-size: 13px; }
a { color: var(--accent); }
/* "or" divider between the password form and the Google button. */
.or {
  display: flex;
  align-items: center;
  margin: 16px 0;
  color: var(--muted);
  font-size: 12px;
}
.or::before,
.or::after {
  content: '';
  flex: 1;
  height: 1px;
  background: #353c49;
}
.or span { padding: 0 12px; }
</style>
