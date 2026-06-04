<script setup>
// Square avatar cropper — a lightweight, dependency-free crop-and-compress modal
// (the Facebook-style "reposition + zoom" flow) used by ProfileView before upload.
// The user pans (drag) and zooms (slider / wheel / pinch) over a square viewport;
// on save we render just the visible square to a small canvas and re-encode it to
// a 512px WebP (JPEG fallback). Cropping to the displayed square means we store
// only the pixels the avatar actually shows -> far smaller files than uploading a
// full-frame photo. EXIF orientation is honoured via lib/image.js's decode path,
// and re-drawing through a canvas strips HDR gain maps / colour profiles.
import { ref, computed, watch, onUnmounted } from 'vue';
import { t } from '../i18n/index.js';
import { decodeImage } from '../lib/image.js';

const props = defineProps({
  open: { type: Boolean, default: false },
  file: { type: Object, default: null }, // the picked File
  busy: { type: Boolean, default: false }, // parent's upload in progress
  outputSize: { type: Number, default: 512 }, // longest side of the saved square
  quality: { type: Number, default: 0.8 },
});
const emit = defineEmits(['confirm', 'cancel']);

const viewportEl = ref(null);
const displayUrl = ref('');
const loading = ref(false);
const processing = ref(false);

// Geometry (all in CSS px unless noted). The source canvas is the EXIF-normalised
// image; scale maps source px -> CSS px; offset is the image centre's displacement
// from the viewport centre.
const V = ref(300); // viewport side
const imgW = ref(0);
const imgH = ref(0);
const scale = ref(1);
const offsetX = ref(0);
const offsetY = ref(0);

let srcCanvas = null; // non-reactive: the crop source

const busyAll = computed(() => props.busy || processing.value);
const sMin = computed(() => {
  const m = Math.min(imgW.value, imgH.value);
  return m > 0 ? V.value / m : 1;
});
const sMax = computed(() => sMin.value * 5); // allow up to 5x zoom-in
const zoomStep = computed(() => (sMax.value - sMin.value) / 100 || 0.001);
const dispW = computed(() => imgW.value * scale.value);
const dispH = computed(() => imgH.value * scale.value);
const imgStyle = computed(() => ({
  width: `${dispW.value}px`,
  height: `${dispH.value}px`,
  left: `${V.value / 2 - dispW.value / 2 + offsetX.value}px`,
  top: `${V.value / 2 - dispH.value / 2 + offsetY.value}px`,
}));

function computeV() {
  // Fit the square inside the card on small phones; cap on desktop.
  const w = typeof window !== 'undefined' ? window.innerWidth : 360;
  V.value = Math.max(220, Math.min(320, w - 72));
}

// Keep the image covering the viewport — never reveal empty gutters.
function clamp() {
  const maxX = Math.max(0, (dispW.value - V.value) / 2);
  const maxY = Math.max(0, (dispH.value - V.value) / 2);
  offsetX.value = Math.min(maxX, Math.max(-maxX, offsetX.value));
  offsetY.value = Math.min(maxY, Math.max(-maxY, offsetY.value));
}

// Zoom around the viewport centre: scaling the offsets by the same ratio keeps the
// centred source point fixed (offset/scale stays constant).
function setScale(ns) {
  const s1 = Math.min(sMax.value, Math.max(sMin.value, ns));
  if (scale.value > 0) {
    const ratio = s1 / scale.value;
    offsetX.value *= ratio;
    offsetY.value *= ratio;
  }
  scale.value = s1;
  clamp();
}

function revokeUrl() {
  if (displayUrl.value) {
    URL.revokeObjectURL(displayUrl.value);
    displayUrl.value = '';
  }
}

async function load() {
  if (!props.file) return;
  loading.value = true;
  resetGesture();
  try {
    const src = await decodeImage(props.file);
    const w = src.width || src.naturalWidth;
    const h = src.height || src.naturalHeight;
    if (!w || !h) throw new Error('decode');

    // Normalise to a plain canvas (oriented, no EXIF/HDR) — the single source of
    // truth for both display and the final crop.
    const c = document.createElement('canvas');
    c.width = w;
    c.height = h;
    c.getContext('2d').drawImage(src, 0, 0, w, h);
    src.close?.();
    srcCanvas = c;
    imgW.value = w;
    imgH.value = h;

    revokeUrl();
    displayUrl.value = await new Promise((res) => c.toBlob((b) => res(URL.createObjectURL(b)), 'image/webp', 0.9));

    computeV();
    scale.value = sMin.value; // start covering the square
    offsetX.value = 0;
    offsetY.value = 0;
    clamp();
  } catch {
    emit('cancel'); // unreadable image — bail out gracefully
  } finally {
    loading.value = false;
  }
}

// ---- gestures: drag to pan, two-finger pinch to zoom ----
const active = new Map();
let last = null;
const distance = (a, b) => Math.hypot(a.x - b.x, a.y - b.y);

function resetGesture() {
  const pts = [...active.values()];
  if (pts.length === 1) last = { type: 'pan', x: pts[0].x, y: pts[0].y };
  else if (pts.length >= 2) {
    last = {
      type: 'pinch',
      dist: distance(pts[0], pts[1]),
      midX: (pts[0].x + pts[1].x) / 2,
      midY: (pts[0].y + pts[1].y) / 2,
    };
  } else last = null;
}

function onPointerDown(e) {
  if (loading.value || busyAll.value) return;
  viewportEl.value?.setPointerCapture?.(e.pointerId);
  active.set(e.pointerId, { x: e.clientX, y: e.clientY });
  resetGesture();
}

function onPointerMove(e) {
  if (!active.has(e.pointerId)) return;
  active.set(e.pointerId, { x: e.clientX, y: e.clientY });
  const pts = [...active.values()];
  if (last?.type === 'pan' && pts.length === 1) {
    offsetX.value += pts[0].x - last.x;
    offsetY.value += pts[0].y - last.y;
    last = { type: 'pan', x: pts[0].x, y: pts[0].y };
    clamp();
  } else if (last?.type === 'pinch' && pts.length >= 2) {
    const d = distance(pts[0], pts[1]);
    const midX = (pts[0].x + pts[1].x) / 2;
    const midY = (pts[0].y + pts[1].y) / 2;
    if (last.dist > 0) setScale(scale.value * (d / last.dist));
    offsetX.value += midX - last.midX;
    offsetY.value += midY - last.midY;
    clamp();
    last = { type: 'pinch', dist: d, midX, midY };
  }
}

function onPointerUp(e) {
  active.delete(e.pointerId);
  resetGesture();
}

function onWheel(e) {
  if (loading.value || busyAll.value) return;
  setScale(scale.value * (e.deltaY < 0 ? 1.08 : 0.926));
}

function onSlider(e) {
  setScale(Number(e.target.value));
}

// ---- output ----
function encode(canvas) {
  return new Promise((resolve) => {
    canvas.toBlob(
      (b) => {
        if (b) return resolve(b);
        // WebP unsupported (old Safari) -> JPEG.
        canvas.toBlob((j) => resolve(j), 'image/jpeg', 0.82);
      },
      'image/webp',
      props.quality
    );
  });
}

async function confirm() {
  if (!srcCanvas || loading.value) return;
  processing.value = true;
  try {
    const cropSide = V.value / scale.value; // source px under the square
    const centerX = imgW.value / 2 - offsetX.value / scale.value;
    const centerY = imgH.value / 2 - offsetY.value / scale.value;
    let sx = centerX - cropSide / 2;
    let sy = centerY - cropSide / 2;
    sx = Math.max(0, Math.min(sx, imgW.value - cropSide));
    sy = Math.max(0, Math.min(sy, imgH.value - cropSide));
    // Never upscale past the cropped source: keeps small originals small.
    const out = Math.max(1, Math.min(props.outputSize, Math.round(cropSide)));

    const oc = document.createElement('canvas');
    oc.width = out;
    oc.height = out;
    const octx = oc.getContext('2d');
    octx.imageSmoothingQuality = 'high';
    octx.drawImage(srcCanvas, sx, sy, cropSide, cropSide, 0, 0, out, out);

    const blob = await encode(oc);
    const ext = blob.type === 'image/webp' ? 'webp' : 'jpg';
    emit('confirm', new File([blob], `avatar.${ext}`, { type: blob.type }));
  } finally {
    processing.value = false;
  }
}

function cancel() {
  if (busyAll.value) return;
  emit('cancel');
}

function onKey(e) {
  if (e.key === 'Escape') cancel();
}

function cleanup() {
  active.clear();
  last = null;
  srcCanvas = null;
  revokeUrl();
}

watch(
  () => props.open,
  (isOpen) => {
    if (isOpen) {
      document.addEventListener('keydown', onKey);
      window.addEventListener('resize', computeV);
      load();
    } else {
      document.removeEventListener('keydown', onKey);
      window.removeEventListener('resize', computeV);
      cleanup();
    }
  }
);
// Re-clamp when the viewport is resized while open.
watch(V, () => {
  if (scale.value < sMin.value) setScale(sMin.value);
  else clamp();
});

onUnmounted(() => {
  document.removeEventListener('keydown', onKey);
  window.removeEventListener('resize', computeV);
  cleanup();
});
</script>

<template>
  <Teleport to="body">
    <Transition name="fade">
      <div v-if="open" class="cr-backdrop" @click.self="cancel">
        <div class="cr-card" role="dialog" aria-modal="true" :aria-label="t('profile.crop.title')">
          <strong class="cr-title">{{ t('profile.crop.title') }}</strong>
          <p class="cr-hint">{{ t('profile.crop.hint') }}</p>

          <div
            ref="viewportEl"
            class="cr-viewport"
            :style="{ width: V + 'px', height: V + 'px' }"
            @pointerdown="onPointerDown"
            @pointermove="onPointerMove"
            @pointerup="onPointerUp"
            @pointercancel="onPointerUp"
            @pointerleave="onPointerUp"
            @wheel.prevent="onWheel"
          >
            <img v-if="displayUrl" :src="displayUrl" class="cr-img" :style="imgStyle" alt="" draggable="false" />
            <div class="cr-mask" />
            <div v-if="loading" class="cr-loading"><i class="fa-solid fa-spinner fa-spin" /></div>
          </div>

          <div class="cr-zoom">
            <i class="fa-solid fa-image cr-zoom-ic small" />
            <input
              class="cr-range"
              type="range"
              :min="sMin"
              :max="sMax"
              :step="zoomStep"
              :value="scale"
              :disabled="loading || busyAll"
              :aria-label="t('profile.crop.zoom')"
              @input="onSlider"
            />
            <i class="fa-solid fa-image cr-zoom-ic" />
          </div>

          <div class="cr-actions">
            <button class="cr-cancel" :disabled="busyAll" @click="cancel">{{ t('common.cancel') }}</button>
            <button class="cr-save" :disabled="busyAll || loading" @click="confirm">
              {{ busyAll ? t('profile.crop.saving') : t('common.save') }}
            </button>
          </div>
        </div>
      </div>
    </Transition>
  </Teleport>
</template>

<style scoped>
.cr-backdrop {
  position: fixed;
  inset: 0;
  z-index: 90;
  display: grid;
  place-items: center;
  padding: 16px;
  background: rgba(0, 0, 0, 0.6);
}
.cr-card {
  width: 100%;
  max-width: 380px;
  background: var(--card);
  border: 1px solid var(--border);
  border-radius: 16px;
  padding: 20px;
  box-shadow: 0 8px 24px rgba(0, 0, 0, 0.4);
}
.cr-title {
  display: block;
  font-size: 16px;
  font-weight: 800;
  color: var(--text);
}
.cr-hint {
  color: var(--muted);
  font-size: 13px;
  line-height: 1.5;
  margin: 6px 0 14px;
}
.cr-viewport {
  position: relative;
  margin: 0 auto;
  overflow: hidden;
  border-radius: 14px;
  background: var(--inset);
  touch-action: none; /* we own pan + pinch */
  cursor: grab;
  user-select: none;
}
.cr-viewport:active {
  cursor: grabbing;
}
.cr-img {
  position: absolute;
  pointer-events: none;
  -webkit-user-drag: none;
  max-width: none;
}
/* Circular guide: the box-shadow dims everything OUTSIDE the avatar circle. */
.cr-mask {
  position: absolute;
  inset: 0;
  border-radius: 50%;
  pointer-events: none;
  box-shadow: 0 0 0 9999px rgba(0, 0, 0, 0.42);
  border: 2px solid rgba(255, 255, 255, 0.7);
}
.cr-loading {
  position: absolute;
  inset: 0;
  display: grid;
  place-items: center;
  color: var(--muted);
  font-size: 1.4rem;
}
.cr-zoom {
  display: flex;
  align-items: center;
  gap: 12px;
  margin: 16px 2px 4px;
  color: var(--muted);
}
.cr-zoom-ic {
  flex: none;
}
.cr-zoom-ic.small {
  font-size: 11px;
}
.cr-range {
  flex: 1;
  min-width: 0;
  height: 28px;
  accent-color: var(--accent);
  cursor: pointer;
}
.cr-actions {
  display: flex;
  justify-content: flex-end;
  gap: 8px;
  margin-top: 14px;
}
.cr-actions button {
  min-height: 40px;
  padding: 8px 18px;
  border-radius: 10px;
  font-weight: 700;
  font-size: 14px;
  border: 1px solid var(--border);
  cursor: pointer;
}
.cr-cancel {
  background: var(--surface-2);
  color: var(--text);
}
.cr-save {
  background: var(--accent);
  color: var(--on-accent);
  border-color: transparent;
}
button:disabled {
  opacity: 0.6;
  cursor: default;
}

.fade-enter-active,
.fade-leave-active {
  transition: opacity 0.18s ease;
}
.fade-enter-from,
.fade-leave-to {
  opacity: 0;
}
</style>
