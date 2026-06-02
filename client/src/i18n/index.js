// Lightweight, dependency-free i18n for the Vue client. Mirrors the PHP t()
// contract (include/i18n/i18n.php): flat key => string tables, {placeholder}
// interpolation, fallback to English then to the key itself so missing
// translations are obvious in the UI.
//
// Unlike PHP's t(), this does NOT HTML-escape: Vue's text bindings ({{ }} and
// :attr) already escape, so escaping here would double-encode. Never feed t()
// output into v-html.
import { ref, computed } from 'vue';
import { locales, FALLBACK } from './locales.js';
import en from './en.js';
import vi from './vi.js';

const tables = { en, vi };
const COOKIE = 'lang';

const isValid = (code) => Object.prototype.hasOwnProperty.call(locales, code);

// --- Resolution (runs synchronously at import, before first paint) ---
// cookie `lang` -> navigator language (prefix-aware) -> fallback. The
// logged-in user's stored preference is applied later, after /me resolves
// (see router.js), so first paint is correct for guests and returning users.
function readCookie() {
  const m = document.cookie.match(/(?:^|;\s*)lang=([^;]+)/);
  return m ? decodeURIComponent(m[1]) : null;
}

function fromNavigator() {
  const tags = navigator.languages && navigator.languages.length ? navigator.languages : [navigator.language || ''];
  for (const tag of tags) {
    const low = (tag || '').toLowerCase();
    if (isValid(low)) return low;
    const prefix = low.split('-')[0]; // vi-VN -> vi, en-US -> en
    if (isValid(prefix)) return prefix;
  }
  return null;
}

function initialLocale() {
  const cookie = readCookie();
  if (cookie && isValid(cookie)) return cookie;
  return fromNavigator() || FALLBACK;
}

/** Active locale for this request. Reactive: anything reading it re-renders on change. */
export const locale = ref(initialLocale());

/** Interpolate {name} placeholders, matching PHP's str_replace behaviour. */
function interpolate(msg, vars) {
  if (!vars) return msg;
  return msg.replace(/\{(\w+)\}/g, (_, k) => (k in vars ? String(vars[k]) : `{${k}}`));
}

/**
 * Translate a key. Active locale -> English fallback -> the key itself.
 * Reads locale.value so callers re-render reactively when the locale changes.
 */
export function t(key, vars) {
  const active = tables[locale.value] || {};
  if (key in active) return interpolate(active[key], vars);
  const fb = tables[FALLBACK] || {};
  if (key in fb) return interpolate(fb[key], vars);
  return key;
}

function writeCookie(code) {
  const secure = location.protocol === 'https:' ? '; Secure' : '';
  // 30 days, Path=/, SameSite=Lax — mirrors set_locale() in include/i18n/i18n.php.
  document.cookie = `${COOKIE}=${encodeURIComponent(code)}; Max-Age=${60 * 60 * 24 * 30}; Path=/; SameSite=Lax${secure}`;
}

/**
 * Switch the active locale. Flips the reactive ref (re-render) and updates
 * <html lang>. When persist is true (the default — a real user choice) it also
 * writes the 30-day cookie and, for logged-in users, saves the preference to
 * the DB. persist:false is used when we are merely reflecting a value we just
 * read from the server, so we don't echo it straight back.
 */
export async function setLocale(code, { persist = true } = {}) {
  if (!isValid(code)) return false;
  locale.value = code;
  document.documentElement.lang = locales[code].htmlLang;
  if (persist) {
    writeCookie(code);
    try {
      const { useAuthStore } = await import('../stores/auth.js');
      const auth = useAuthStore();
      if (auth.user) {
        // Keep the store in sync so a later read (e.g. the router guard) doesn't
        // revert to a stale preference.
        auth.user.language_preference = code;
        const { api } = await import('../lib/api.js');
        await api.post('/api/profile/language', { language: code });
      }
    } catch {
      /* Never block the UI on a persistence failure — cookie still holds the choice. */
    }
  }
  return true;
}

export { locales };
export const currentLocale = computed(() => locale.value);

/**
 * Vue plugin: exposes $t in every template (no per-component import) and sets
 * the initial <html lang>. $t re-renders reactively because t() reads
 * locale.value at render time.
 */
export const i18n = {
  install(app) {
    app.config.globalProperties.$t = t;
    document.documentElement.lang = locales[locale.value].htmlLang;
  },
};
