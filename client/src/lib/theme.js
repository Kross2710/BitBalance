// Theme controller — mirrors the i18n module. Resolves the theme at import
// (localStorage -> 'system'), applies data-theme on <html> BEFORE first paint,
// and exposes a reactive preference + setter. CSS keys off [data-theme="light"]
// (see styles.css); the default :root is dark.
//
// Preference values: 'system' | 'light' | 'dark'. 'system' follows the OS via
// prefers-color-scheme and live-updates when the OS flips.
import { ref } from 'vue';

const KEY = 'theme';
const VALID = ['system', 'light', 'dark'];

function stored() {
  try {
    const v = localStorage.getItem(KEY);
    return VALID.includes(v) ? v : null;
  } catch {
    return null;
  }
}

const mql = () =>
  typeof window !== 'undefined' && window.matchMedia
    ? window.matchMedia('(prefers-color-scheme: dark)')
    : null;

// 'light'/'dark' are explicit; anything else ('system') follows the OS.
function resolve(pref) {
  if (pref === 'light' || pref === 'dark') return pref;
  const m = mql();
  return m && m.matches ? 'dark' : 'light';
}

function apply(pref) {
  document.documentElement.dataset.theme = resolve(pref);
}

/** Active preference. Reactive so the Profile <select> stays in sync. */
export const themePref = ref(stored() || 'system');

// Apply synchronously at import — keeps first paint correct, no flash.
apply(themePref.value);

// Re-apply when the OS theme changes, but only while following 'system'.
const m = mql();
if (m) {
  m.addEventListener('change', () => {
    if (themePref.value === 'system') apply('system');
  });
}

/**
 * Set the theme. Flips the reactive ref + <html> data-theme immediately (live
 * preview). When persist is true (a real user choice) it also writes
 * localStorage so guests and pre-/me boots render the chosen theme without a
 * flash. The DB copy is saved separately by the Profile form's Save button
 * (theme_preference travels in the profile-update payload).
 */
export function setTheme(pref, { persist = true } = {}) {
  if (!VALID.includes(pref)) return;
  themePref.value = pref;
  apply(pref);
  if (persist) {
    try {
      localStorage.setItem(KEY, pref);
    } catch {
      /* ignore — the in-memory ref still holds the choice this session */
    }
  }
}
