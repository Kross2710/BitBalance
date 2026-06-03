<script setup>
import { ref, computed, onMounted } from 'vue';
import { useRouter, useRoute, RouterLink } from 'vue-router';
import { useAuthStore } from '../stores/auth.js';
import { locale, setLocale, t } from '../i18n/index.js';
import GoogleSignInButton from '../components/GoogleSignInButton.vue';
import LocaleSwitcher from '../components/LocaleSwitcher.vue';

const auth = useAuthStore();
const router = useRouter();
const route = useRoute();

const form = ref({ first_name: '', last_name: '', email: '', password: '', confirm_password: '' });
// Surface an error bounced back by the Google OAuth redirect.
const error = ref(typeof route.query.error === 'string' ? route.query.error : '');
const busy = ref(false);

// Reveal toggles + terms gate — parity with the PHP signup page.
const showPassword = ref(false);
const showConfirm = ref(false);
const agreeTerms = ref(false);

// Live password requirements. These MUST match the server rules in
// server/src/routes/auth.js (length >= 8 AND PASSWORD_RE = lower+upper+digit)
// so the UI never shows all-green for a password the API will reject.
const pwReqs = computed(() => {
  const p = form.value.password || '';
  return [
    { key: 'length', ok: p.length >= 8 },
    { key: 'upper', ok: /[A-Z]/.test(p) },
    { key: 'lower', ok: /[a-z]/.test(p) },
    { key: 'number', ok: /\d/.test(p) },
  ];
});
const pwAllGood = computed(() => pwReqs.value.every((r) => r.ok));

onMounted(() => auth.loadProviders());

async function onSubmit() {
  error.value = '';
  // Validate client-side before the round-trip (DESIGN.md mobile rule).
  if (!agreeTerms.value) { error.value = t('auth.terms_required'); return; }
  if (!pwAllGood.value) { error.value = t('auth.password_hint'); return; }
  if (form.value.password !== form.value.confirm_password) {
    error.value = t('auth.password_mismatch');
    return;
  }
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
      <div class="pw-field">
        <input
          id="signup-password"
          v-model="form.password"
          :type="showPassword ? 'text' : 'password'"
          autocomplete="new-password"
          required
        />
        <button
          type="button"
          class="pw-toggle"
          :aria-label="showPassword ? $t('auth.hide_password') : $t('auth.show_password')"
          :aria-pressed="showPassword"
          @click="showPassword = !showPassword"
        >
          <i class="fa-solid" :class="showPassword ? 'fa-eye-slash' : 'fa-eye'" />
        </button>
      </div>

      <ul class="pw-reqs" aria-live="polite">
        <li v-for="r in pwReqs" :key="r.key" :class="{ ok: r.ok }">
          <i class="fa-solid" :class="r.ok ? 'fa-circle-check' : 'fa-circle'" />
          {{ $t('auth.pw_req_' + r.key) }}
        </li>
      </ul>

      <label for="signup-confirm" style="display: block; margin-top: 12px">{{ $t('auth.confirm_password') }}</label>
      <div class="pw-field">
        <input
          id="signup-confirm"
          v-model="form.confirm_password"
          :type="showConfirm ? 'text' : 'password'"
          autocomplete="new-password"
          required
        />
        <button
          type="button"
          class="pw-toggle"
          :aria-label="showConfirm ? $t('auth.hide_password') : $t('auth.show_password')"
          :aria-pressed="showConfirm"
          @click="showConfirm = !showConfirm"
        >
          <i class="fa-solid" :class="showConfirm ? 'fa-eye-slash' : 'fa-eye'" />
        </button>
      </div>

      <label class="terms">
        <input type="checkbox" v-model="agreeTerms" />
        <span>{{ $t('auth.agree_terms') }}</span>
      </label>

      <button type="submit" :disabled="busy" style="width: 100%; margin-top: 16px">
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

/* Password field + reveal toggle (>=44px tap target). */
.pw-field { position: relative; }
.pw-field input { padding-right: 46px; }
.pw-toggle {
  position: absolute;
  top: 0;
  right: 0;
  height: 100%;
  min-width: 44px;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  background: transparent;
  border: none;
  color: var(--muted);
  cursor: pointer;
}
.pw-toggle:hover { color: var(--text); }

/* Live password requirements. */
.pw-reqs {
  list-style: none;
  margin: 8px 0 0;
  padding: 0;
  display: flex;
  flex-wrap: wrap;
  gap: 4px 14px;
}
.pw-reqs li {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  color: var(--muted);
  font-size: 12px;
}
.pw-reqs li i { font-size: 11px; }
.pw-reqs li.ok { color: var(--accent); }

/* Terms gate (>=44px row). */
.terms {
  display: flex;
  align-items: center;
  gap: 10px;
  min-height: 44px;
  margin-top: 14px;
  color: var(--text);
  font-size: 13px;
  cursor: pointer;
}
.terms input {
  width: 18px;
  height: 18px;
  flex: none;
  accent-color: var(--accent);
}

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
  background: var(--border);
}
.or span { padding: 0 12px; }
</style>
