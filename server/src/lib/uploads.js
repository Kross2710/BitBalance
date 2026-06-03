// Local image storage for logged food photos. The Express app keeps its own
// uploads dir (it does not share PHP's filesystem) and serves it read-only at
// /api/uploads/* — which the Vite dev proxy already forwards and which is
// same-origin in production. Mirrors the PHP intake flow that saves a meal photo
// and stores its path on the intake row so the user can review it later.
import fs from 'fs';
import path from 'path';
import { fileURLToPath } from 'url';

const __dirname = path.dirname(fileURLToPath(import.meta.url));
// <repo>/server/uploads/intake
export const UPLOADS_ROOT = path.resolve(__dirname, '../../uploads');
// <repo>/uploads — legacy PHP upload paths are still stored in MySQL as
// uploads/profile_*. These are served separately at /uploads/* for migration
// compatibility.
export const LEGACY_UPLOADS_ROOT = path.resolve(__dirname, '../../../uploads');
const INTAKE_DIR = path.join(UPLOADS_ROOT, 'intake');
const PROFILE_DIR = path.join(UPLOADS_ROOT, 'profile');

// The public URL prefix the static mount lives under (see index.js).
const PUBLIC_PREFIX = '/api/uploads';
// What a valid stored path looks like — used to sanitise client-supplied values
// so a logged row can never point outside our own uploads.
export const IMAGE_PATH_RE = /^\/api\/uploads\/intake\/[A-Za-z0-9._-]+$/;

const EXT_BY_MIME = {
  'image/jpeg': 'jpg',
  'image/png': 'png',
  'image/webp': 'webp',
  'image/gif': 'gif',
};

fs.mkdirSync(INTAKE_DIR, { recursive: true });
fs.mkdirSync(PROFILE_DIR, { recursive: true });

// Persist a food photo and return its public path (e.g. /api/uploads/intake/x.jpg).
export function saveIntakeImage(userId, buffer, mime) {
  const ext = EXT_BY_MIME[mime] || 'jpg';
  const rand = Math.random().toString(36).slice(2, 10);
  const name = `meal_${userId}_${Date.now()}_${rand}.${ext}`;
  fs.writeFileSync(path.join(INTAKE_DIR, name), buffer);
  return `${PUBLIC_PREFIX}/intake/${name}`;
}

const PROFILE_PATH_RE = /^\/api\/uploads\/profile\/[A-Za-z0-9._-]+$/;

// Persist a profile avatar and return its public path. The client compresses to
// a small JPEG first (see client/src/lib/image.js); this just stores the buffer.
export function saveProfileImage(userId, buffer, mime) {
  const ext = EXT_BY_MIME[mime] || 'jpg';
  const rand = Math.random().toString(36).slice(2, 10);
  const name = `avatar_${userId}_${Date.now()}_${rand}.${ext}`;
  fs.writeFileSync(path.join(PROFILE_DIR, name), buffer);
  return `${PUBLIC_PREFIX}/profile/${name}`;
}

// Best-effort delete of a previously-stored avatar (only our own profile paths).
export function removeProfileImage(imagePath) {
  const v = String(imagePath ?? '').trim();
  if (!PROFILE_PATH_RE.test(v)) return;
  const name = v.slice(`${PUBLIC_PREFIX}/profile/`.length);
  fs.promises.unlink(path.join(PROFILE_DIR, name)).catch(() => {});
}

// Accept only our own well-formed path; anything else collapses to null so it is
// never stored. Returns the sanitised path or null.
export function sanitizeImagePath(value) {
  const v = String(value ?? '').trim();
  return IMAGE_PATH_RE.test(v) ? v : null;
}

// Best-effort delete of a stored image when its log entry is removed.
export function removeIntakeImage(imagePath) {
  if (!sanitizeImagePath(imagePath)) return;
  const name = imagePath.slice(`${PUBLIC_PREFIX}/intake/`.length);
  fs.promises.unlink(path.join(INTAKE_DIR, name)).catch(() => {});
}
