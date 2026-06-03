<script setup>
// Dedicated Food Intake page — the primary "log food" surface (ports the core
// of the PHP Food Intake page minus barcode/AI photo, which come later).
// Big food field with history-backed autocomplete + recent chips, calories,
// meal, optional macros, and a full-width Log Entry button.
import { ref, reactive, computed, nextTick, onMounted, onBeforeUnmount, watch } from 'vue';
import { useRoute, RouterLink } from 'vue-router';
import { api } from '../lib/api.js';
import { t, locale } from '../i18n/index.js';
import CalorieSummaryCard from '../components/CalorieSummaryCard.vue';
import AiFoodChat from '../components/AiFoodChat.vue';

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
let suggestTimer = null;
let justPicked = false;

const canSubmit = computed(() => form.food_item.trim() !== '' && Number(form.calories) > 0);

async function loadRecent() {
  try {
    const data = await api.get('/api/intake/suggest');
    recent.value = data.items;
  } catch {
    /* non-fatal: chips just won't show */
  }
}

// ---- Today's entries (manage what was logged today) ----
// The dashboard shows entries read-only; editing/deleting lives here. We pull
// recent history and keep only today's rows (server tz is +07:00, so compute
// "today" in Asia/Bangkok to avoid a midnight UTC off-by-one).
const route = useRoute();
const entries = ref([]);
const summary = ref(null); // daily total/goal/macros for the CalorieSummaryCard
const todayLocal = new Date().toLocaleDateString('en-CA', { timeZone: 'Asia/Bangkok' });

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
const editingId = ref(null);
const editForm = reactive({ intake_id: 0, food_item: '', calories: '', meal_category: 'snack', protein: '', carbs: '', fat: '' });

async function loadEntries() {
  try {
    const data = await api.get(`/api/intake/history?date=${activeDate.value}`);
    // Server already scopes to the day; the filter is a belt-and-suspenders guard.
    entries.value = data.entries.filter((e) => (e.date_intake ?? '').slice(0, 10) === activeDate.value);
    summary.value = data.daily_summary; // running total for the bar, scoped to activeDate
  } catch {
    /* non-fatal: section just stays empty */
  }
}
// Re-pull when the day changes (e.g. ?date= -> bare /intake), keeping past mode
// non-sticky.
watch(activeDate, loadEntries);

function startEdit(e) {
  editingId.value = e.id;
  Object.assign(editForm, {
    intake_id: e.id,
    food_item: e.food_item,
    calories: e.calories,
    meal_category: e.meal_category,
    protein: e.protein || '',
    carbs: e.carbs || '',
    fat: e.fat || '',
  });
}
function cancelEdit() {
  editingId.value = null;
}
async function saveEdit() {
  error.value = '';
  try {
    await api.post('/api/intake/update', { ...editForm });
    editingId.value = null;
    await Promise.all([loadEntries(), loadRecent()]);
  } catch (e) {
    error.value = e.message;
  }
}
async function removeEntry(e) {
  if (!confirm(t('intake.confirm_delete_named', { name: e.food_item }))) return;
  error.value = '';
  try {
    await api.post('/api/intake/delete', { intake_id: e.id });
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
        const data = await api.get(`/api/intake/suggest?q=${encodeURIComponent(q)}`);
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
  saving.value = true;
  try {
    await api.post('/api/intake/create', { ...form, date: activeDate.value });
    success.value = t('intake.logged_named', { name: form.food_item });
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

onMounted(() => {
  loadRecent();
  loadEntries();
});
onBeforeUnmount(() => {
  stopCamera();
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

    <!-- Running calorie total (shared with the Dashboard) -->
    <CalorieSummaryCard v-if="summary" :summary="summary" class="intake-summary" />

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
          :class="{ active: form.meal_category === m.key, logged: mealKcal[m.key] > 0 }"
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

      <!-- Optional macros -->
      <button type="button" class="ql-toggle" @click="showMacros = !showMacros">
        <i class="fa-solid" :class="showMacros ? 'fa-chevron-up' : 'fa-plus'" />
        {{ showMacros ? $t('intake.hide_macros') : $t('intake.add_macros_optional') }}
      </button>
      <div v-if="showMacros" class="macros-row">
        <div class="macro-field p">
          <label for="intake-p" class="macro-tag" :title="$t('intake.protein_g')">{{ $t('intake.macro_abbr.protein') }}</label>
          <input id="intake-p" v-model="form.protein" type="number" min="0" step="any" placeholder="0" :aria-label="$t('intake.protein_g')" />
        </div>
        <div class="macro-field c">
          <label for="intake-c" class="macro-tag" :title="$t('intake.carbs_g')">{{ $t('intake.macro_abbr.carbs') }}</label>
          <input id="intake-c" v-model="form.carbs" type="number" min="0" step="any" placeholder="0" :aria-label="$t('intake.carbs_g')" />
        </div>
        <div class="macro-field f">
          <label for="intake-f" class="macro-tag" :title="$t('intake.fat_g')">{{ $t('intake.macro_abbr.fat') }}</label>
          <input id="intake-f" v-model="form.fat" type="number" min="0" step="any" placeholder="0" :aria-label="$t('intake.fat_g')" />
        </div>
      </div>

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
          <div v-if="editingId === e.id" class="edit-grid">
            <input v-model="editForm.food_item" :aria-label="$t('intake.row.food')" />
            <input v-model="editForm.calories" type="number" min="1" step="any" :aria-label="$t('intake.row.calories')" />
            <select v-model="editForm.meal_category" :aria-label="$t('intake.category_label')">
              <option value="breakfast">{{ $t('intake.meal.breakfast_emoji') }}</option>
              <option value="lunch">{{ $t('intake.meal.lunch_emoji') }}</option>
              <option value="dinner">{{ $t('intake.meal.dinner_emoji') }}</option>
              <option value="snack">{{ $t('intake.meal.snack_emoji') }}</option>
            </select>
            <input v-model="editForm.protein" type="number" min="0" step="any" :placeholder="$t('intake.macro_abbr.protein')" :aria-label="$t('dashboard.macros.protein')" />
            <input v-model="editForm.carbs" type="number" min="0" step="any" :placeholder="$t('intake.macro_abbr.carbs')" :aria-label="$t('dashboard.macros.carbs')" />
            <input v-model="editForm.fat" type="number" min="0" step="any" :placeholder="$t('intake.macro_abbr.fat')" :aria-label="$t('dashboard.macros.fat')" />
            <div class="edit-actions">
              <button type="button" @click="saveEdit">{{ $t('common.save') }}</button>
              <button type="button" class="ghost" @click="cancelEdit">{{ $t('common.cancel') }}</button>
            </div>
          </div>
          <div v-else class="entry-row">
            <span class="entry-main">
              <img v-if="e.image_path" :src="e.image_path" class="entry-thumb" :alt="$t('intake.food_photo_alt')" />
              <span class="entry-name">{{ e.food_item }} <small class="muted">· {{ $t('intake.meal.' + e.meal_category + '_emoji') }}</small></span>
            </span>
            <span class="entry-end">
              <strong>{{ e.calories }} {{ $t('common.kcal') }}</strong>
              <button type="button" class="icon-btn" @click="startEdit(e)" :aria-label="$t('intake.row.edit_title')"><i class="fa-solid fa-pen" /></button>
              <button type="button" class="icon-btn danger" @click="removeEntry(e)" :aria-label="$t('intake.row.delete_title')"><i class="fa-solid fa-trash" /></button>
            </span>
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
  </main>
</template>

<style scoped>
.intake { max-width: 560px; margin: 0 auto; padding: 8px 16px; }
.intake h1 { margin: 6px 0 16px; }
.intake-summary { margin-bottom: 16px; }

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

/* Macros: always one row, each color-coded (P red / C amber / F blue) for quick ID. */
.macros-row { display: grid; grid-template-columns: repeat(3, 1fr); gap: 10px; margin-top: 10px; }
.macro-field { display: flex; flex-direction: column; gap: 5px; }
.macro-tag {
  margin: 0;
  align-self: flex-start;
  display: inline-grid;
  place-items: center;
  min-width: 24px;
  height: 22px;
  padding: 0 6px;
  border-radius: 6px;
  font-weight: 800;
  font-size: 12px;
}
.macro-field input { width: 100%; border-left-width: 3px; }
.macro-field.p .macro-tag { background: rgba(239, 68, 68, 0.15); color: #f87171; }
.macro-field.p input { border-left-color: #ef4444; }
.macro-field.c .macro-tag { background: rgba(245, 158, 11, 0.15); color: #fbbf24; }
.macro-field.c input { border-left-color: #f59e0b; }
.macro-field.f .macro-tag { background: rgba(59, 130, 246, 0.15); color: #60a5fa; }
.macro-field.f input { border-left-color: #3b82f6; }

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
/* Logged-but-not-selected meals read as "done" without stealing focus. */
.meal-pill.logged { color: var(--text); border-color: var(--surface-2); }
.meal-pill.active {
  color: var(--accent);
  border-color: var(--accent);
  background: rgba(74, 222, 128, 0.08);
}
#intake-kcal { width: 100%; }

.ql-toggle {
  background: transparent;
  color: var(--muted);
  border: 1px dashed var(--border);
  font-size: 13px;
  font-weight: 600;
  margin-top: 14px;
  padding: 8px 12px;
  min-height: 44px;
}
.ql-toggle:hover { color: var(--text); }

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
.entries li { padding: 10px 0; border-top: 1px solid var(--border); }
.entries li:first-child { border-top: none; padding-top: 0; }
.entry-row { display: flex; justify-content: space-between; align-items: center; gap: 10px; }
.entry-main { display: flex; align-items: center; gap: 10px; min-width: 0; }
.entry-name { min-width: 0; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.entry-thumb { flex: none; width: 40px; height: 40px; border-radius: 8px; object-fit: cover; }
.entry-end { display: flex; align-items: center; gap: 6px; flex: none; }
.icon-btn {
  width: 44px; height: 44px; min-height: 44px;
  display: grid; place-items: center;
  background: var(--surface-2); color: var(--text); border: none; border-radius: 10px;
  font-size: 14px;
}
.icon-btn.danger { color: #f87171; }
.edit-grid { display: grid; grid-template-columns: 2fr 1fr 1fr; gap: 8px; }
.edit-grid input, .edit-grid select { width: 100%; }
.edit-actions { grid-column: 1 / -1; display: flex; gap: 8px; }
.edit-actions .ghost { background: var(--surface-2); color: var(--text); }

</style>
