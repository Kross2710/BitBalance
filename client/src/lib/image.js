// Shared client-side image compressor — the Vue counterpart of the PHP app's
// js/image-compress.js (window.BitBalanceImage.compressImage).
//
// Downscales + re-encodes a user-picked image to a small sRGB JPEG by drawing it
// through a 2D canvas. Drawing through the canvas drops iPhone HDR gain maps and
// embedded color profiles (which look over-saturated and bloat the file), and the
// resize + quality cap keep uploads light — important on the self-hosted box
// (ngrok free) and cheaper for the vision model.
//
// Defaults are TIGHTER than the PHP util (PHP: 1600px / 0.85) since the migrated
// app is self-hosted; callers can override per upload (e.g. avatars go smaller).
// EXIF orientation is honoured so portrait iPhone shots aren't rotated sideways
// (the old inline IntakeView version used createImageBitmap without the
// imageOrientation hint, which dropped EXIF rotation).

export const DEFAULTS = {
  maxEdge: 1280, // longest side in px after downscale (PHP: 1600)
  quality: 0.72, // JPEG quality, 0..1 (PHP: 0.85)
  filename: 'photo.jpg',
  mimeType: 'image/jpeg',
};

// Decode to something drawImage can take, honouring EXIF orientation. Prefers
// createImageBitmap({imageOrientation:'from-image'}); falls back to a plain
// bitmap, then to an <img> element (browsers apply image-orientation:from-image
// to <img> by default), so it works even where the option is unsupported.
// Exported so the avatar cropper reuses the same EXIF-safe decode path.
export async function decodeImage(file) {
  if (typeof createImageBitmap === 'function') {
    try {
      return await createImageBitmap(file, { imageOrientation: 'from-image' });
    } catch {
      try {
        return await createImageBitmap(file);
      } catch {
        /* fall through to the <img> path */
      }
    }
  }
  const url = URL.createObjectURL(file);
  try {
    return await new Promise((resolve, reject) => {
      const img = new Image();
      img.onload = () => resolve(img);
      img.onerror = () => reject(new Error('decode'));
      img.src = url;
    });
  } finally {
    URL.revokeObjectURL(url);
  }
}

/**
 * Compress an image File/Blob. Non-images (or any failure) are returned
 * unchanged so a compression hiccup never blocks the upload.
 * @param {File|Blob} file
 * @param {{maxEdge?:number, quality?:number, filename?:string, mimeType?:string}} [opts]
 * @returns {Promise<File|Blob>}
 */
export async function compressImage(file, opts = {}) {
  const cfg = { ...DEFAULTS, ...opts };
  if (!file?.type?.startsWith('image/')) return file;

  try {
    const src = await decodeImage(file);
    const sw = src.width || src.naturalWidth;
    const sh = src.height || src.naturalHeight;
    if (!sw || !sh) return file;

    const scale = Math.min(1, cfg.maxEdge / Math.max(sw, sh));
    const w = Math.round(sw * scale);
    const h = Math.round(sh * scale);

    const canvas = document.createElement('canvas');
    canvas.width = w;
    canvas.height = h;
    canvas.getContext('2d').drawImage(src, 0, 0, w, h);
    src.close?.(); // free the ImageBitmap (no-op for <img>)

    const blob = await new Promise((resolve) => canvas.toBlob(resolve, cfg.mimeType, cfg.quality));
    return blob ? new File([blob], cfg.filename, { type: cfg.mimeType }) : file;
  } catch {
    return file;
  }
}
