<script setup>
// Dedicated Food Intake page — the primary "log food" surface (ports the core
// of the PHP Food Intake page minus barcode/AI photo, which come later).
// Big food field with history-backed autocomplete + recent chips, calories,
// meal, optional macros, and a full-width Log Entry button.
import { ref, reactive, computed, nextTick, onMounted, onBeforeUnmount, watch } from 'vue';
import { useRoute, RouterLink } from 'vue-router';
import { api } from '../lib/api.js';
import { appToday } from '../lib/date.js';
import { t, locale } from '../i18n/index.js';
import CalorieSummaryCard from '../components/CalorieSummaryCard.vue';
import AiFoodChat from '../components/AiFoodChat.vue';
import MacroInputs from '../components/MacroInputs.vue';
import BottomSheet from '../components/BottomSheet.vue';
import MealBadge from '../components/MealBadge.vue';
import UnlockToast from '../components/UnlockToast.vue';
import { celebrate } from '../lib/unlockToast.js';

// Default the meal to the current time-of-day, like the PHP app / AI Coach.
function mealFromHour() {
  const h = new Date().getHours();
  if (h >= 5 && h < 11) return 'breakfast';
  if (h >= 11 && h < 15) return 'lunch';
  if (h >= 17 && h < 22) return 'dinner';
  return 'snack';
}

// Meal segmented selector — four big pills instead of a <select>, each showing
// how much has already been logged for that meal today. Icons mirror the meal.
const MEALS = [
  { key: 'breakfast', labelKey: 'intake.meal.breakfast_emoji', icon: 'fa-mug-saucer' },
  { key: 'lunch', labelKey: 'intake.meal.lunch_emoji', icon: 'fa-bowl-food' },
  { key: 'dinner', labelKey: 'intake.meal.dinner_emoji', icon: 'fa-utensils' },
  { key: 'snack', labelKey: 'intake.meal.snack_emoji', icon: 'fa-cookie-bite' },
];

const form = reactive({ food_item: '', calories: '', meal_category: mealFromHour(), protein: '', carbs: '', fat: '', image_path: '' });
const showMacros = ref(false);
const recent = ref([]);
const suggestions = ref([]);
const showSuggest = ref(false);
const foodFocused = ref(false);
const saving = ref(false);
const error = ref('');
const success = ref('');
const undoEntry = ref(null); // last-deleted row, pending undo
let suggestTimer = null;
let successTimer = null;
let undoTimer = null;
let justPicked = false;

const canSubmit = computed(() => form.food_item.trim() !== '' && Number(form.calories) > 0);
const hasMacros = computed(() => Number(form.protein) > 0 || Number(form.carbs) > 0 || Number(form.fat) > 0);

// Success toast that auto-dismisses, so it doesn't linger after the entry it
// referred to is edited or deleted.
function flashSuccess(msg) {
  success.value = msg;
  clearTimeout(successTimer);
  successTimer = setTimeout(() => (success.value = ''), 4000);
}

// Turn a /create response into celebration toasts: level-up first, then any
// achievements that climbed a tier with this log (level 1 = freshly unlocked).
function celebrateFromLog(res) {
  const items = [];
  if (res?.xp?.levelup) items.push({ type: 'levelup', level: res.xp.levelup.to });
  for (const a of res?.newly_unlocked || []) {
    items.push(
      a.level <= 1
        ? { type: 'unlock', name: a.name, icon: a.icon, tone: a.tone }
        : { type: 'tier', name: a.name, icon: a.icon, tone: a.tone, level: a.level }
    );
  }
  celebrate(items);
}
function clearMessages() {
  success.value = '';
  clearTimeout(successTimer);
}

async function loadRecent() {
  try {
    const data = await api.get('/api/intake/suggest');
    recent.value = data.items;
  } catch {
    /* non-fatal: chips just won't show */
  }
}

// ---- Today's entries (manage what was logged today) ----
// The dashboard shows entries read-only; editing/deleting lives here. The server
// scopes /history?date= to the user's LOCAL day using the same timezone-shifted
// grouping the Dashboard uses, so the two pages stay in sync.
const route = useRoute();
const entries = ref([]);
const summary = ref(null); // daily total/goal/macros for the CalorieSummaryCard
const todayLocal = appToday();

// Backdating: the Dashboard date strip can carry a past day via ?date=, and we
// log to / show THAT day instead of today. Rules (ported from process_intake.php):
// never the future (clamped here + re-clamped server-side), and past mode is NOT
// sticky — a bare /intake always means today.
const activeDate = computed(() => {
  const q = route.query.date;
  if (typeof q === 'string' && /^\d{4}-\d{2}-\d{2}$/.test(q) && q <= todayLocal) return q;
  return todayLocal;
});
const isPastMode = computed(() => activeDate.value !== todayLocal);
const activeDateLabel = computed(() =>
  new Date(activeDate.value + 'T00:00:00').toLocaleDateString(locale.value === 'vi' ? 'vi-VN' : 'en-US', {
    weekday: 'short',
    month: 'short',
    day: 'numeric',
  })
);

// Today's kcal per meal, for the segmented selector's status line.
const mealKcal = computed(() => {
  const out = { breakfast: 0, lunch: 0, dinner: 0, snack: 0 };
  for (const e of entries.value) {
    if (out[e.meal_category] != null) out[e.meal_category] += e.calories;
  }
  return out;
});
const showEditSheet = ref(false);
const editForm = reactive({ intake_id: 0, food_item: '', calories: '', meal_category: 'snack', protein: '', carbs: '', fat: '' });

// Tap a logged entry to open a focused detail sheet; tap its photo to enlarge it
// (mirrors the Dashboard's photo lightbox). detailEntry doubles as the sheet's
// open flag; lightbox holds the enlarged photo src.
const detailEntry = ref(null);
const lightbox = ref('');

async function loadEntries() {
  try {
    const data = await api.get(`/api/intake/history?date=${activeDate.value}`);
    // Trust the server's day scope — it filters by DATE(date_intake + INTERVAL
    // tzShift MINUTE) = activeDate, the SAME timezone-aware grouping the Dashboard
    // uses. Do NOT re-filter here by the raw date_intake string: that value is the
    // +07:00 wall-clock time, so for any user not in +07:00 it disagrees with the
    // shifted day and would silently drop rows the Dashboard still shows (the two
    // pages then look out of sync near midnight).
    entries.value = data.entries;
    summary.value = data.daily_summary; // running total for the bar, scoped to activeDate
  } catch {
    /* non-fatal: section just stays empty */
  }
}
// Re-pull when the day changes (e.g. ?date= -> bare /intake), keeping past mode
// non-sticky.
watch(activeDate, loadEntries);

function startEdit(e) {
  Object.assign(editForm, {
    intake_id: e.id,
    food_item: e.food_item,
    calories: e.calories,
    meal_category: e.meal_category,
    protein: e.protein || '',
    carbs: e.carbs || '',
    fat: e.fat || '',
  });
  showEditSheet.value = true;
}
function cancelEdit() {
  showEditSheet.value = false;
}
async function saveEdit() {
  error.value = '';
  clearMessages();
  try {
    await api.post('/api/intake/update', { ...editForm });
    showEditSheet.value = false;
    await Promise.all([loadEntries(), loadRecent()]);
  } catch (e) {
    error.value = e.message;
  }
}
// Delete immediately and offer an Undo (PHP-style) instead of a blocking confirm —
// the delete route returns the deleted row, so restoring it is just a re-create.
async function removeEntry(e) {
  error.value = '';
  clearMessages();
  try {
    const res = await api.post('/api/intake/delete', { intake_id: e.id });
    await Promise.all([loadEntries(), loadRecent()]);
    showUndo(res.deleted_row);
  } catch (err) {
    error.value = err.message;
  }
}

function showUndo(row) {
  if (!row) return;
  clearTimeout(undoTimer);
  undoEntry.value = row;
  undoTimer = setTimeout(() => (undoEntry.value = null), 6000);
}
function dismissUndo() {
  undoEntry.value = null;
  clearTimeout(undoTimer);
}
// Restore the just-deleted entry on the same day (re-create from the snapshot).
async function undoDelete() {
  const row = undoEntry.value;
  dismissUndo();
  if (!row) return;
  error.value = '';
  try {
    await api.post('/api/intake/create', {
      food_item: row.food_item,
      calories: row.calories,
      protein: row.protein,
      carbs: row.carbs,
      fat: row.fat,
      meal_category: row.meal_category,
      image_path: row.image_path || '',
      date: (row.date_intake || '').slice(0, 10) || undefined,
    });
    await Promise.all([loadEntries(), loadRecent()]);
  } catch (err) {
    error.value = err.message;
  }
}

function applyItem(item) {
  form.food_item = item.food_item;
  form.calories = item.calories;
  form.protein = item.protein ?? '';
  form.carbs = item.carbs ?? '';
  form.fat = item.fat ?? '';
  clearPhoto(); // a chosen-from-history item has no photo of its own
  if (item.protein || item.carbs || item.fat) showMacros.value = true;
  showSuggest.value = false;
  suggestions.value = [];
}

function pickChip(item) {
  justPicked = true; // suppress the watch-triggered autocomplete for this change
  applyItem(item);
}

// Suggestions appear only in response to interaction (matches the PHP intake
// page): recent chips while the focused field is still empty, and the
// autocomplete dropdown only once the user has actually typed a query.
function onFoodFocus() {
  showSuggest.value = form.food_item.trim() !== '' && suggestions.value.length > 0;
  foodFocused.value = true;
}
// Delay hiding so a click on a chip / suggestion (mousedown) still registers.
function onFoodBlur() {
  setTimeout(() => {
    showSuggest.value = false;
    foodFocused.value = false;
  }, 150);
}

// Debounced autocomplete as the user types the food name.
watch(
  () => form.food_item,
  (val) => {
    if (justPicked) {
      justPicked = false;
      return;
    }
    clearTimeout(suggestTimer);
    const q = val.trim();
    if (q === '') {
      suggestions.value = [];
      showSuggest.value = false;
      return;
    }
    suggestTimer = setTimeout(async () => {
      try {
        // Background: fires per keystroke (debounced) + has its own dropdown, so it
        // must not flash the global loading bar.
        const data = await api.get(`/api/intake/suggest?q=${encodeURIComponent(q)}`, { background: true });
        suggestions.value = data.items;
        showSuggest.value = data.items.length > 0;
      } catch {
        suggestions.value = [];
      }
    }, 220);
  }
);

async function onSubmit() {
  if (!canSubmit.value || saving.value) return;
  error.value = '';
  success.value = '';
  dismissUndo();
  saving.value = true;
  try {
    const res = await api.post('/api/intake/create', { ...form, date: activeDate.value });
    flashSuccess(t('intake.logged_named', { name: form.food_item }));
    celebrateFromLog(res);
    form.food_item = '';
    form.calories = '';
    form.protein = '';
    form.carbs = '';
    form.fat = '';
    clearPhoto(); // clears the AI photo attached to the form
    showMacros.value = false;
    suggestions.value = [];
    showSuggest.value = false;
    await Promise.all([loadRecent(), loadEntries()]);
  } catch (e) {
    error.value = e.message;
  } finally {
    saving.value = false;
  }
}

// ---- Barcode scanner ----
// Prefers the native BarcodeDetector (Android Chrome: hardware-accelerated);
// falls back to a lazily-loaded ZXing decoder for browsers without it (notably
// iOS Safari). If no camera is available the user types the number manually.
// Either way the code is resolved server-side (cache -> OpenFoodFacts).
const showScanner = ref(false);
const manualCode = ref('');
const scanBusy = ref(false);
const scanError = ref('');
const scanResult = ref(null);
const cameraOn = ref(false);
const videoEl = ref(null);
let mediaStream = null;
let detector = null;
let rafId = null;
let zxingControls = null; // ZXing IScannerControls (owns its own camera stream)

async function openScanner() {
  showScanner.value = true;
  scanError.value = '';
  scanResult.value = null;
  manualCode.value = '';
  await startCamera();
}

function closeScanner() {
  stopCamera();
  showScanner.value = false;
}

async function startCamera() {
  scanError.value = '';
  cameraOn.value = true;
  await nextTick(); // ensure the <video> element is mounted before attaching
  try {
    if (typeof window !== 'undefined' && 'BarcodeDetector' in window) {
      await startNativeDetector();
    } else {
      await startZxing();
    }
  } catch (e) {
    cameraOn.value = false;
    scanError.value = t('intake.scan.camera_unavailable');
  }
}

// Native path (throws if camera is denied/unavailable -> caller shows manual entry).
async function startNativeDetector() {
  detector = new window.BarcodeDetector({
    formats: ['ean_13', 'ean_8', 'upc_a', 'upc_e', 'code_128'],
  });
  mediaStream = await navigator.mediaDevices.getUserMedia({ video: { facingMode: 'environment' } });
  if (videoEl.value) {
    videoEl.value.srcObject = mediaStream;
    await videoEl.value.play().catch(() => {});
  }
  detectLoop();
}

// ZXing fallback (iOS Safari). Lazily imported so it stays out of the main
// bundle; ZXing manages its own getUserMedia stream + continuous decode.
async function startZxing() {
  const { BrowserMultiFormatReader } = await import('@zxing/browser');
  const reader = new BrowserMultiFormatReader();
  zxingControls = await reader.decodeFromConstraints(
    { video: { facingMode: 'environment' } },
    videoEl.value,
    (result) => {
      if (result) {
        const code = result.getText();
        stopCamera();
        lookupBarcode(code);
      }
    }
  );
}

function stopCamera() {
  cameraOn.value = false;
  if (rafId) cancelAnimationFrame(rafId);
  rafId = null;
  if (mediaStream) {
    mediaStream.getTracks().forEach((t) => t.stop());
    mediaStream = null;
  }
  if (zxingControls) {
    zxingControls.stop();
    zxingControls = null;
  }
}

async function detectLoop() {
  if (!cameraOn.value || !detector || !videoEl.value) return;
  try {
    const codes = await detector.detect(videoEl.value);
    if (codes.length && codes[0].rawValue) {
      stopCamera();
      lookupBarcode(codes[0].rawValue);
      return;
    }
  } catch {
    /* transient decode error — keep looping */
  }
  rafId = requestAnimationFrame(detectLoop);
}

async function lookupBarcode(code) {
  const barcode = String(code).trim();
  if (!/^\d{6,20}$/.test(barcode)) {
    scanError.value = t('intake.scan.invalid_barcode');
    return;
  }
  scanError.value = '';
  scanResult.value = null;
  scanBusy.value = true;
  try {
    const data = await api.post('/api/intake/lookup-barcode', { barcode });
    if (data.found) scanResult.value = data;
    else scanError.value = t('intake.scan.no_product', { barcode });
  } catch (e) {
    scanError.value = e.message;
  } finally {
    scanBusy.value = false;
  }
}

function useProduct() {
  const p = scanResult.value;
  if (!p) return;
  form.food_item = [p.brand, p.product_name].filter(Boolean).join(' ').slice(0, 80) || t('intake.scan.scanned_item');
  form.calories = p.kcal_per_serving ?? (p.kcal_per_100g != null ? Math.round(p.kcal_per_100g) : '');
  if (p.protein != null || p.carbs != null || p.fat != null) {
    form.protein = p.protein ?? '';
    form.carbs = p.carbs ?? '';
    form.fat = p.fat ?? '';
    showMacros.value = true;
  }
  clearPhoto(); // a scanned product isn't the AI photo
  closeScanner();
}

// ---- AI food chat ----
const showAiChat = ref(false);

// Clear the AI photo attached to the form (when a history/barcode pick replaces
// it, or after logging).
function clearPhoto() {
  form.image_path = '';
}

// Add-to-log from the chat: prefill the form (the user still taps Log Entry, so
// they can adjust the meal/amount first — consistent with the rich-entry flow).
function onAiPick(p) {
  error.value = '';
  success.value = '';
  form.food_item = p.food_item || '';
  form.calories = p.calories || '';
  form.protein = p.protein ?? '';
  form.carbs = p.carbs ?? '';
  form.fat = p.fat ?? '';
  form.image_path = p.image_path || ''; // travels to /create so the entry keeps the photo
  if (p.protein || p.carbs || p.fat) showMacros.value = true;
  showAiChat.value = false;
}

// Time-of-day an entry was logged (app tz +07:00), e.g. "8:30 AM".
function entryTime(e) {
  const iso = e.iso_date || (e.date_intake ? e.date_intake.replace(' ', 'T') + '+07:00' : null);
  if (!iso) return '';
  // iso_date is the true instant; render it in the user's local (browser) zone.
  return new Date(iso).toLocaleTimeString(locale.value === 'vi' ? 'vi-VN' : 'en-US', {
    hour: 'numeric',
    minute: '2-digit',
  });
}

// Quick re-log: duplicate this entry onto the active day in one tap.
async function relog(e) {
  error.value = '';
  try {
    const res = await api.post('/api/intake/create', {
      food_item: e.food_item,
      calories: e.calories,
      protein: e.protein,
      carbs: e.carbs,
      fat: e.fat,
      meal_category: e.meal_category,
      date: activeDate.value,
    });
    flashSuccess(t('intake.logged_named', { name: e.food_item }));
    celebrateFromLog(res);
    await Promise.all([loadEntries(), loadRecent()]);
  } catch (err) {
    error.value = err.message;
  }
}

// ---- Entry detail sheet ----
function openDetail(e) {
  detailEntry.value = e;
}
function closeDetail() {
  detailEntry.value = null;
}
// Route the detail sheet's actions through the existing handlers, closing the
// detail sheet first so Edit never stacks a second bottom sheet on top of it.
function editFromDetail() {
  const e = detailEntry.value;
  closeDetail();
  if (e) startEdit(e);
}
async function relogFromDetail() {
  const e = detailEntry.value;
  closeDetail();
  if (e) await relog(e);
}
async function removeFromDetail() {
  const e = detailEntry.value;
  closeDetail();
  if (e) await removeEntry(e);
}

onMounted(() => {
  loadRecent();
  loadEntries();
});
onBeforeUnmount(() => {
  stopCamera();
  clearTimeout(successTimer);
  clearTimeout(undoTimer);
});
</script>

<template>
  <main class="intake">
    <h1>{{ $t('intake.add_entry') }}</h1>

    <!-- Past-day banner: logging is scoped to a back-dated day carried via ?date=. -->
    <div v-if="isPastMode" class="past-banner">
      <span><i class="fa-solid fa-clock-rotate-left" /> {{ $t('intake.past.banner', { date: activeDateLabel }) }}</span>
      <RouterLink to="/intake" class="past-back">{{ $t('intake.past.back_today') }}</RouterLink>
    </div>

    <!-- Running calorie total (shared with the Dashboard). Reserve its height with a
         skeleton until the first summary loads so the form below doesn't jump (CLS).
         summary stays truthy after the first load, so this only shows on cold load. -->
    <CalorieSummaryCard v-if="summary" :summary="summary" class="intake-summary" />
    <div v-else class="sk intake-summary-skel" aria-hidden="true"></div>

    <form class="card" @submit.prevent="onSubmit">
      <!-- Capture shortcuts -->
      <div class="io-actions">
        <button type="button" class="io-chip" @click="openScanner">
          <i class="fa-solid fa-barcode" /> {{ $t('intake.scan_barcode_chip') }}
        </button>
        <button type="button" class="io-chip" @click="showAiChat = true">
          <i class="fa-solid fa-wand-magic-sparkles" /> {{ $t('intake.ai_photo_chip') }}
        </button>
      </div>

      <!-- Food name + autocomplete -->
      <label for="intake-food">{{ $t('intake.what_did_you_eat') }}</label>
      <div class="food-field">
        <input
          id="intake-food"
          v-model="form.food_item"
          class="food-input"
          :placeholder="$t('intake.food_placeholder')"
          autocomplete="off"
          required
          @focus="onFoodFocus"
          @blur="onFoodBlur"
        />
        <ul v-if="showSuggest" class="suggest">
          <li v-for="(s, i) in suggestions" :key="i" @mousedown.prevent="applyItem(s)">
            <span>{{ s.food_item }}</span>
            <span class="muted">{{ s.calories }} {{ $t('common.kcal') }}</span>
          </li>
        </ul>
      </div>

      <!-- Recent quick-pick chips: only while the empty food field is focused. -->
      <div v-if="recent.length && foodFocused && !form.food_item.trim()" class="chips">
        <button v-for="(r, i) in recent" :key="i" type="button" class="chip" @click="pickChip(r)">
          {{ r.food_item }}
        </button>
      </div>

      <!-- Meal: segmented selector with today's logged kcal per meal -->
      <label>{{ $t('intake.category_label') }}</label>
      <div class="meal-seg" role="group" :aria-label="$t('intake.category_label')">
        <button
          v-for="m in MEALS"
          :key="m.key"
          type="button"
          class="meal-pill"
          :class="[m.key, { active: form.meal_category === m.key, logged: mealKcal[m.key] > 0 }]"
          :aria-pressed="form.meal_category === m.key"
          @click="form.meal_category = m.key"
        >
          <i class="fa-solid" :class="m.icon" />
          <span class="meal-name">{{ $t(m.labelKey) }}</span>
          <small class="meal-stat">{{ mealKcal[m.key] > 0 ? mealKcal[m.key] + ' ' + $t('common.kcal') : '—' }}</small>
        </button>
      </div>

      <!-- Calories -->
      <label for="intake-kcal">{{ $t('intake.calories_label') }}</label>
      <input id="intake-kcal" v-model="form.calories" type="number" min="1" step="any" :placeholder="$t('common.kcal')" required />

      <!-- Optional macros: a light summary row toggles the tray open. -->
      <button type="button" class="macro-toggle" :aria-expanded="showMacros" @click="showMacros = !showMacros">
        <span class="mt-label">{{ $t('intake.macros_optional') }}</span>
        <span v-if="!showMacros && hasMacros" class="mt-chips">
          <span class="mchip p">{{ $t('intake.macro_abbr.protein') }} {{ form.protein || 0 }}</span>
          <span class="mchip c">{{ $t('intake.macro_abbr.carbs') }} {{ form.carbs || 0 }}</span>
          <span class="mchip f">{{ $t('intake.macro_abbr.fat') }} {{ form.fat || 0 }}</span>
        </span>
        <i class="mt-chevron fa-solid" :class="showMacros ? 'fa-chevron-up' : 'fa-chevron-down'" />
      </button>
      <MacroInputs
        v-if="showMacros"
        v-model:protein="form.protein"
        v-model:carbs="form.carbs"
        v-model:fat="form.fat"
      />

      <button type="submit" class="log-btn" :disabled="!canSubmit || saving">
        {{ saving ? $t('intake.logging') : $t('intake.log_entry_btn') }}
      </button>
      <p v-if="success" class="ok">{{ success }}</p>
      <p v-if="error" class="error">{{ error }}</p>
    </form>

    <!-- Today's entries: review + edit/delete what was logged today -->
    <section v-if="entries.length" class="entries card">
      <h2>{{ isPastMode ? $t('intake.entries_for', { date: activeDateLabel }) : $t('intake.todays_entries') }}</h2>
      <ul>
        <li v-for="e in entries" :key="e.id">
          <div class="entry-row">
            <button
              v-if="e.image_path"
              type="button"
              class="entry-thumb-btn"
              @click="lightbox = e.image_path"
              :aria-label="$t('intake.detail.view_photo')"
            >
              <img :src="e.image_path" class="entry-thumb" :alt="$t('intake.food_photo_alt')" />
              <span class="entry-thumb-zoom" aria-hidden="true"><i class="fa-solid fa-magnifying-glass-plus" /></span>
            </button>
            <button type="button" class="entry-body" @click="openDetail(e)" :aria-label="$t('intake.detail.open', { name: e.food_item })">
              <div class="entry-line">
                <span class="entry-name">{{ e.food_item }}</span>
                <strong class="entry-kcal">{{ e.calories }} {{ $t('common.kcal') }}</strong>
              </div>
              <div class="entry-meta">
                <MealBadge :meal="e.meal_category" />
                <span v-if="entryTime(e)" class="entry-time">{{ entryTime(e) }}</span>
                <span v-if="e.protein || e.carbs || e.fat" class="mchips">
                  <span v-if="e.protein" class="mchip p">{{ $t('intake.macro_abbr.protein') }} {{ e.protein }}</span>
                  <span v-if="e.carbs" class="mchip c">{{ $t('intake.macro_abbr.carbs') }} {{ e.carbs }}</span>
                  <span v-if="e.fat" class="mchip f">{{ $t('intake.macro_abbr.fat') }} {{ e.fat }}</span>
                </span>
              </div>
            </button>
            <div class="entry-actions">
              <button type="button" class="icon-btn" @click="relog(e)" :aria-label="$t('intake.row.relog_title')"><i class="fa-solid fa-rotate-right" /></button>
              <button type="button" class="icon-btn" @click="startEdit(e)" :aria-label="$t('intake.row.edit_title')"><i class="fa-solid fa-pen" /></button>
              <button type="button" class="icon-btn danger" @click="removeEntry(e)" :aria-label="$t('intake.row.delete_title')"><i class="fa-solid fa-trash" /></button>
            </div>
          </div>
        </li>
      </ul>
    </section>

    <!-- Barcode scanner modal -->
    <div v-if="showScanner" class="overlay" @click.self="closeScanner">
      <div class="modal">
        <div class="modal-head">
          <strong><i class="fa-solid fa-barcode" /> {{ $t('intake.scan_barcode_chip') }}</strong>
          <button type="button" class="x" @click="closeScanner" :aria-label="$t('common.close')"><i class="fa-solid fa-xmark" /></button>
        </div>
        <div v-if="cameraOn" class="cam"><video ref="videoEl" muted playsinline /></div>
        <label for="manual-code">{{ $t('intake.scan.barcode_number') }}</label>
        <div class="scan-row">
          <input
            id="manual-code"
            v-model="manualCode"
            inputmode="numeric"
            :placeholder="$t('intake.scan.barcode_placeholder')"
            @keydown.enter.prevent="lookupBarcode(manualCode)"
          />
          <button type="button" :disabled="scanBusy" @click="lookupBarcode(manualCode)">
            {{ scanBusy ? '…' : $t('intake.scan.look_up') }}
          </button>
        </div>
        <div v-if="scanResult" class="scan-result">
          <strong>{{ scanResult.product_name || $t('intake.scan.unnamed_product') }}</strong>
          <p v-if="scanResult.brand" class="muted">{{ scanResult.brand }}</p>
          <p class="muted">
            {{ scanResult.kcal_per_serving ?? scanResult.kcal_per_100g ?? '—' }} {{ $t('common.kcal') }}
            <span v-if="scanResult.kcal_per_serving">{{ $t('intake.scan.per_serving') }}</span>
            <span v-else-if="scanResult.kcal_per_100g">{{ $t('intake.scan.per_100g') }}</span>
          </p>
          <button type="button" class="use-btn" @click="useProduct">{{ $t('intake.scan.use_this') }}</button>
        </div>
        <p v-if="scanError" class="error">{{ scanError }}</p>
      </div>
    </div>

    <!-- AI food chat: attach a photo + correct the dish, then Add to log. -->
    <AiFoodChat :open="showAiChat" @close="showAiChat = false" @pick="onAiPick" />

    <!-- Celebratory level-up / achievement-unlock toast after a log. -->
    <UnlockToast />

    <!-- Undo bar after delete (PHP-style): restore the last-deleted entry. -->
    <Transition name="undo">
      <div v-if="undoEntry" class="undo-bar" role="status">
        <span class="undo-msg">{{ $t('intake.deleted_named', { name: undoEntry.food_item }) }}</span>
        <button type="button" class="undo-btn" @click="undoDelete">
          <i class="fa-solid fa-rotate-left" /> {{ $t('intake.undo') }}
        </button>
      </div>
    </Transition>

    <!-- Edit entry: bottom sheet (replaces the old inline edit form). -->
    <BottomSheet :open="showEditSheet" :title="$t('intake.row.edit_title')" @close="cancelEdit">
      <form class="edit-sheet" @submit.prevent="saveEdit">
        <label for="edit-food">{{ $t('intake.what_did_you_eat') }}</label>
        <input id="edit-food" v-model="editForm.food_item" :placeholder="$t('intake.food_placeholder')" />

        <label>{{ $t('intake.category_label') }}</label>
        <div class="meal-seg" role="group" :aria-label="$t('intake.category_label')">
          <button
            v-for="m in MEALS"
            :key="m.key"
            type="button"
            class="meal-pill"
            :class="[m.key, { active: editForm.meal_category === m.key }]"
            :aria-pressed="editForm.meal_category === m.key"
            @click="editForm.meal_category = m.key"
          >
            <i class="fa-solid" :class="m.icon" />
            <span class="meal-name">{{ $t(m.labelKey) }}</span>
          </button>
        </div>

        <label for="edit-kcal">{{ $t('intake.calories_label') }}</label>
        <input id="edit-kcal" v-model="editForm.calories" type="number" min="1" step="any" :placeholder="$t('common.kcal')" />

        <label>{{ $t('intake.macros_optional') }}</label>
        <MacroInputs v-model:protein="editForm.protein" v-model:carbs="editForm.carbs" v-model:fat="editForm.fat" />

        <div class="edit-sheet-actions">
          <button type="button" class="ghost" @click="cancelEdit">{{ $t('common.cancel') }}</button>
          <button type="submit" :disabled="!editForm.food_item.trim() || !(Number(editForm.calories) > 0)">{{ $t('common.save') }}</button>
        </div>
        <p v-if="error" class="error">{{ error }}</p>
      </form>
    </BottomSheet>

    <!-- Entry detail: tap a logged item to view it in a focused sheet (read-only
         view + the same quick actions). -->
    <BottomSheet :open="!!detailEntry" :title="$t('intake.detail.title')" @close="closeDetail">
      <div v-if="detailEntry" class="detail">
        <button
          v-if="detailEntry.image_path"
          type="button"
          class="detail-photo"
          @click="lightbox = detailEntry.image_path"
          :aria-label="$t('intake.detail.view_photo')"
        >
          <img :src="detailEntry.image_path" :alt="$t('intake.food_photo_alt')" />
          <span class="detail-photo-zoom" aria-hidden="true"><i class="fa-solid fa-magnifying-glass-plus" /></span>
        </button>

        <h3 class="detail-name">{{ detailEntry.food_item }}</h3>

        <div class="detail-kcal">
          <strong>{{ detailEntry.calories }}</strong><span>{{ $t('common.kcal') }}</span>
        </div>

        <div class="detail-meta">
          <MealBadge :meal="detailEntry.meal_category" />
          <span v-if="entryTime(detailEntry)" class="entry-time"><i class="fa-solid fa-clock" /> {{ entryTime(detailEntry) }}</span>
        </div>

        <div v-if="detailEntry.protein || detailEntry.carbs || detailEntry.fat" class="detail-macros">
          <div class="dm p"><span class="dm-v">{{ detailEntry.protein || 0 }}<small>g</small></span><span class="dm-k">{{ $t('intake.macro.protein') }}</span></div>
          <div class="dm c"><span class="dm-v">{{ detailEntry.carbs || 0 }}<small>g</small></span><span class="dm-k">{{ $t('intake.macro.carbs') }}</span></div>
          <div class="dm f"><span class="dm-v">{{ detailEntry.fat || 0 }}<small>g</small></span><span class="dm-k">{{ $t('intake.macro.fat') }}</span></div>
        </div>
        <p v-else class="detail-nomacros">{{ $t('intake.detail.no_macros') }}</p>

        <div class="detail-actions">
          <button type="button" class="ghost" @click="relogFromDetail"><i class="fa-solid fa-rotate-right" /> {{ $t('intake.row.relog_title') }}</button>
          <button type="button" class="ghost" @click="editFromDetail"><i class="fa-solid fa-pen" /> {{ $t('common.edit') }}</button>
          <button type="button" class="ghost danger" @click="removeFromDetail"><i class="fa-solid fa-trash" /> {{ $t('common.delete') }}</button>
        </div>
      </div>
    </BottomSheet>

    <!-- Enlarged food photo (mirrors the Dashboard lightbox); teleported with a
         z-index above the bottom sheet so it works when opened from the detail. -->
    <Teleport to="body">
      <div v-if="lightbox" class="lightbox" @click="lightbox = ''">
        <img :src="lightbox" :alt="$t('intake.food_photo_alt')" />
      </div>
    </Teleport>
  </main>
</template>

<style scoped>
.intake { max-width: 560px; margin: 0 auto; padding: 8px 16px; }
.intake h1 { margin: 6px 0 16px; }
.intake-summary { margin-bottom: 16px; }
/* First-load placeholder holding the summary card's height (no shimmer). */
.sk { background: var(--inset); border: 1px solid var(--border); border-radius: 14px; }
.intake-summary-skel { min-height: 134px; margin-bottom: 16px; }

/* Past-day (backdating) banner */
.past-banner {
  display: flex; align-items: center; justify-content: space-between; gap: 12px;
  margin: 0 0 14px; padding: 10px 14px;
  background: rgba(251, 146, 60, 0.12);
  border: 1px solid #fb923c; border-radius: 10px;
  font-size: 13px; font-weight: 600;
}
.past-banner .past-back { color: var(--accent); font-weight: 700; white-space: nowrap; }
.muted { color: var(--muted); font-size: 13px; }
.ok { color: var(--accent); font-size: 13px; margin: 10px 0 0; }
label { font-size: 13px; color: var(--muted); display: block; margin-bottom: 4px; }

.food-field { position: relative; }
.food-input { font-size: 18px; padding: 14px; }
.suggest {
  position: absolute;
  left: 0;
  right: 0;
  top: calc(100% + 4px);
  z-index: 20;
  list-style: none;
  margin: 0;
  padding: 4px;
  background: var(--card);
  border: 1px solid var(--border);
  border-radius: 10px;
  box-shadow: 0 8px 24px rgba(0, 0, 0, 0.4);
}
.suggest li {
  display: flex;
  justify-content: space-between;
  gap: 10px;
  padding: 10px 10px;
  border-radius: 8px;
  cursor: pointer;
}
.suggest li:hover { background: var(--inset); }

.chips { display: flex; flex-wrap: wrap; gap: 8px; margin-top: 12px; }
.chip {
  display: inline-flex;
  align-items: center;
  background: var(--inset);
  color: var(--text);
  border: 1px solid var(--border);
  border-radius: 999px;
  padding: 7px 14px;
  min-height: 44px; /* tap target — recent chips are a primary action */
  font-size: 13px;
  font-weight: 600;
}
.chip:hover { border-color: var(--accent); color: var(--accent); }

/* Macro tray toggle — a light summary row (label + chips when set + chevron). */
.macro-toggle {
  display: flex;
  align-items: center;
  gap: 10px;
  width: 100%;
  margin-top: 14px;
  padding: 10px 12px;
  background: transparent;
  color: var(--muted);
  border: 1px dashed var(--border);
  border-radius: 10px;
  min-height: 46px;
  font-size: 13px;
  font-weight: 600;
}
.macro-toggle:hover { color: var(--text); border-color: var(--field-border); }
.mt-label { flex: none; }
.mt-chips { display: inline-flex; gap: 4px; flex-wrap: wrap; }
.mt-chevron { flex: none; margin-left: auto; font-size: 12px; }

/* Meal segmented selector */
.meal-seg { display: grid; grid-template-columns: repeat(4, 1fr); gap: 8px; margin: 0 0 14px; }
.meal-pill {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  gap: 3px;
  min-height: 64px; /* comfortable tap target */
  padding: 8px 4px;
  background: var(--inset);
  color: var(--muted);
  border: 1px solid var(--border);
  border-radius: 12px;
  font-weight: 600;
}
.meal-pill i { font-size: 16px; }
.meal-name { font-size: 12px; }
.meal-stat { font-size: 10px; opacity: 0.85; }
/* Per-meal identity: the icon always carries the meal colour (matches the badges). */
.meal-pill.breakfast i { color: var(--meal-breakfast); }
.meal-pill.lunch i { color: var(--meal-lunch); }
.meal-pill.dinner i { color: var(--meal-dinner); }
.meal-pill.snack i { color: var(--meal-snack); }
/* Logged-but-not-selected meals read as "done" without stealing focus. */
.meal-pill.logged { color: var(--text); border-color: var(--surface-2); }
/* Selected meal fills with its own colour instead of the generic accent. */
.meal-pill.active { color: var(--text); }
.meal-pill.breakfast.active { border-color: var(--meal-breakfast); background: var(--meal-breakfast-bg); }
.meal-pill.lunch.active { border-color: var(--meal-lunch); background: var(--meal-lunch-bg); }
.meal-pill.dinner.active { border-color: var(--meal-dinner); background: var(--meal-dinner-bg); }
.meal-pill.snack.active { border-color: var(--meal-snack); background: var(--meal-snack-bg); }
#intake-kcal { width: 100%; }

.log-btn { width: 100%; margin-top: 18px; padding: 14px; font-size: 16px; }

/* Capture shortcut chips (Scan barcode / AI Photo) */
.io-actions { display: grid; grid-template-columns: 1fr 1fr; gap: 10px; margin-bottom: 16px; }
.io-chip {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 8px;
  background: var(--inset);
  color: var(--text);
  border: 1px solid var(--border);
  font-size: 14px;
  font-weight: 600;
}
.io-chip:hover { border-color: var(--accent); color: var(--accent); }

/* Scanner modal */
.overlay {
  position: fixed;
  inset: 0;
  background: rgba(0, 0, 0, 0.6);
  display: grid;
  place-items: center;
  padding: 16px;
  z-index: 60;
}
.modal {
  background: var(--card);
  border: 1px solid var(--border);
  border-radius: 14px;
  padding: 18px;
  width: 100%;
  max-width: 420px;
  box-shadow: 0 16px 40px rgba(0, 0, 0, 0.5);
}
.modal-head { display: flex; justify-content: space-between; align-items: center; margin-bottom: 14px; }
.modal-head .x {
  background: transparent;
  color: var(--muted);
  width: 44px;
  height: 44px;
  min-height: 44px;
  padding: 0;
  display: grid;
  place-items: center;
  font-size: 18px;
}
.modal-head .x:hover { color: var(--text); }
.cam { border-radius: 10px; overflow: hidden; margin-bottom: 14px; background: #000; aspect-ratio: 4 / 3; }
.cam video { width: 100%; height: 100%; object-fit: cover; display: block; }
.scan-row { display: flex; gap: 8px; margin-top: 6px; }
.scan-row input { flex: 1; }
.scan-row button { flex: none; }
.scan-result {
  margin-top: 14px;
  padding: 12px;
  background: var(--bg);
  border: 1px solid var(--border);
  border-radius: 10px;
}
.scan-result p { margin: 4px 0; }
.use-btn { width: 100%; margin-top: 10px; }

/* Today's entries list */
.entries { margin-top: 18px; }
.entries h2 { font-size: 16px; margin: 0 0 12px; }
.entries ul { list-style: none; margin: 0; padding: 0; }
.entries li { padding: 12px 0; border-top: 1px solid var(--border); }
.entries li:first-child { border-top: none; padding-top: 0; }
.entry-row { display: flex; align-items: center; gap: 10px; }
/* Thumb is a button now: tap to enlarge the photo (zoom hint on hover/focus). */
.entry-thumb-btn {
  flex: none; position: relative; overflow: hidden;
  width: 44px; height: 44px; min-height: 0; padding: 0; border-radius: 8px;
  background: none; cursor: pointer;
}
.entry-thumb { display: block; width: 44px; height: 44px; border-radius: 8px; object-fit: cover; }
.entry-thumb-zoom {
  position: absolute; inset: 0; display: grid; place-items: center;
  background: rgba(0, 0, 0, 0.45); color: #fff; font-size: 13px;
  opacity: 0; transition: opacity 0.15s ease;
}
.entry-thumb-btn:hover .entry-thumb-zoom,
.entry-thumb-btn:focus-visible .entry-thumb-zoom { opacity: 1; }
/* Body is a button now: tap to open the detail sheet. Reset native chrome. */
.entry-body {
  flex: 1; min-width: 0; min-height: 0; display: flex; flex-direction: column; gap: 6px;
  padding: 0; background: none; color: inherit; font: inherit; text-align: left;
  border-radius: 6px; cursor: pointer;
}
.entry-body:hover .entry-name { color: var(--accent); }
.entry-line { display: flex; align-items: baseline; justify-content: space-between; gap: 10px; }
.entry-name { min-width: 0; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; font-weight: 600; }
.entry-kcal { flex: none; font-weight: 700; }
.entry-meta { display: flex; align-items: center; flex-wrap: wrap; gap: 6px; }
.entry-time { color: var(--muted); font-size: 12px; }
.mchips { display: inline-flex; gap: 4px; }
.mchip { font-size: 11px; font-weight: 700; padding: 2px 7px; border-radius: 8px; }
.mchip.p { background: var(--macro-p-bg); color: var(--macro-p); }
.mchip.c { background: var(--macro-c-bg); color: var(--macro-c); }
.mchip.f { background: var(--macro-f-bg); color: var(--macro-f); }
.entry-actions { flex: none; display: flex; gap: 6px; }
.icon-btn {
  width: 40px; height: 40px; min-height: 40px;
  display: grid; place-items: center;
  background: var(--surface-2); color: var(--text); border: none; border-radius: 10px;
  font-size: 14px;
}
.icon-btn.danger { color: #f87171; }

/* Entry detail sheet */
.detail { display: flex; flex-direction: column; align-items: stretch; gap: 12px; padding-bottom: 4px; }
.detail-photo {
  position: relative; align-self: center; padding: 0; min-height: 0;
  width: 100%; max-width: 280px; aspect-ratio: 4 / 3; overflow: hidden;
  background: var(--inset); border: 1px solid var(--border); border-radius: 12px; cursor: pointer;
}
.detail-photo img { width: 100%; height: 100%; object-fit: cover; display: block; }
.detail-photo-zoom {
  position: absolute; right: 8px; bottom: 8px;
  display: grid; place-items: center; width: 30px; height: 30px;
  background: rgba(0, 0, 0, 0.55); color: #fff; border-radius: 8px; font-size: 13px;
}
.detail-name { margin: 0; font-size: 18px; font-weight: 700; text-align: center; }
.detail-kcal { text-align: center; line-height: 1; }
.detail-kcal strong { font-size: 30px; font-weight: 800; color: var(--accent); }
.detail-kcal span { margin-left: 6px; font-size: 14px; color: var(--muted); }
.detail-meta { display: flex; align-items: center; justify-content: center; flex-wrap: wrap; gap: 8px; }
.detail-meta .entry-time { display: inline-flex; align-items: center; gap: 4px; }
.detail-macros { display: grid; grid-template-columns: repeat(3, 1fr); gap: 8px; }
.dm {
  display: flex; flex-direction: column; align-items: center; gap: 2px;
  padding: 10px 6px; border-radius: 10px; border: 1px solid var(--border);
}
.dm-v { font-size: 18px; font-weight: 800; }
.dm-v small { font-size: 11px; font-weight: 700; opacity: 0.75; margin-left: 1px; }
.dm-k { font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.03em; }
.dm.p { background: var(--macro-p-bg); } .dm.p .dm-v, .dm.p .dm-k { color: var(--macro-p); }
.dm.c { background: var(--macro-c-bg); } .dm.c .dm-v, .dm.c .dm-k { color: var(--macro-c); }
.dm.f { background: var(--macro-f-bg); } .dm.f .dm-v, .dm.f .dm-k { color: var(--macro-f); }
.detail-nomacros { margin: 0; text-align: center; color: var(--muted); font-size: 13px; }
.detail-actions { display: flex; gap: 8px; margin-top: 4px; }
.detail-actions button {
  flex: 1; display: inline-flex; align-items: center; justify-content: center; gap: 6px;
  padding: 11px 8px; font-size: 13px;
  background: var(--surface-2); color: var(--text);
}
.detail-actions .danger { color: #f87171; }

/* Enlarged photo overlay (teleported; above the bottom sheet's z-index 1000). */
.lightbox {
  position: fixed; inset: 0; z-index: 1100; background: rgba(0, 0, 0, 0.85);
  display: grid; place-items: center; padding: 24px;
}
.lightbox img { max-width: 100%; max-height: 100%; border-radius: 12px; }

/* Edit entry bottom sheet */
.edit-sheet { display: flex; flex-direction: column; gap: 4px; padding-bottom: 4px; }
.edit-sheet label { margin-top: 10px; }
.edit-sheet label:first-child { margin-top: 0; }
.edit-sheet .meal-seg { margin: 0; }
.edit-sheet .meal-pill { min-height: 56px; }
.edit-sheet-actions { display: flex; gap: 8px; margin-top: 18px; }
.edit-sheet-actions button { flex: 1; padding: 12px; }
.edit-sheet-actions .ghost { background: var(--surface-2); color: var(--text); }

/* Undo snackbar (after delete) — sits above the fixed bottom tab bar. */
.undo-bar {
  position: fixed;
  left: 50%;
  transform: translateX(-50%);
  bottom: calc(76px + env(safe-area-inset-bottom));
  z-index: 50;
  display: flex;
  align-items: center;
  gap: 14px;
  max-width: calc(100% - 32px);
  padding: 10px 12px 10px 16px;
  background: var(--card);
  border: 1px solid var(--border);
  border-radius: 12px;
  box-shadow: 0 8px 24px rgba(0, 0, 0, 0.45);
  font-size: 13px;
}
.undo-msg { color: var(--text); overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.undo-btn {
  flex: none;
  background: transparent;
  color: var(--accent);
  border: none;
  font-weight: 700;
  padding: 6px 8px;
  min-height: 0;
}
.undo-btn i { margin-right: 4px; }
.undo-enter-active, .undo-leave-active { transition: opacity 0.2s ease; }
.undo-enter-from, .undo-leave-to { opacity: 0; }

</style>
