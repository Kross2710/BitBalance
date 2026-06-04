// Profile routes — port api/profile/get.php and update.php.
//   GET  /api/profile          → current user's profile payload
//   POST /api/profile/update   → validate + persist account / status / goal / physical
//   POST /api/profile/language → persist the UI language preference (mirrors set_language.php)
//   POST /api/profile/avatar   → upload a profile photo (client compresses first)
import { Router } from 'express';
import multer from 'multer';
import { pool, query } from '../db.js';
import { requireAuth } from '../middleware/auth.js';
import { publicUser } from '../lib/users.js';
import { saveProfileImage, removeProfileImage } from '../lib/uploads.js';
import { fetchUser, payload } from '../lib/profile.js';

const router = Router();

// Avatar upload: in-memory, 5MB cap (mirrors the legacy profile.php limit + the
// intake photo route). The client already downscales to a small JPEG.
const AVATAR_MIMES = new Set(['image/jpeg', 'image/png', 'image/webp']);
const avatarUpload = multer({
  storage: multer.memoryStorage(),
  limits: { fileSize: 5 * 1024 * 1024 },
  fileFilter: (req, file, cb) => cb(null, AVATAR_MIMES.has(file.mimetype)),
});

const HANDLE_RE = /^[A-Za-z0-9_.#\-]{3,30}$/;
const EMAIL_RE = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
const VALID_THEMES = ['light', 'dark', 'system'];
const VALID_GENDERS = ['male', 'female', 'other'];
const VALID_VISIBILITIES = ['private', 'friends', 'public'];
const VALID_AI_TONES = ['formal', 'casual'];
const AI_PERSONA_MAX = 280;
// Locales the Vue client ships catalogs for (client/src/i18n/locales.js). The DB
// column also accepts 'fr' from the PHP app, but the SPA only offers en/vi.
const VALID_LOCALES = ['en', 'vi'];

// Mirror api_profile_nullable_int / nullable_float: '' and null collapse to null.
function nullableInt(v) {
  if (v === null || v === undefined || v === '') return null;
  return parseInt(v, 10);
}
function nullableFloat(v) {
  if (v === null || v === undefined || v === '') return null;
  return Number(v);
}

router.get('/', requireAuth, async (req, res, next) => {
  try {
    const profile = await fetchUser(req.user.user_id);
    if (!profile) {
      return res.status(404).json({ ok: false, data: null, message: 'Profile not found.' });
    }
    res.json({ ok: true, data: await payload(profile), message: null });
  } catch (err) {
    next(err);
  }
});

// Instant language toggle — the canonical writer (mirrors include/handlers/
// set_language.php). The cookie is set client-side; here we persist to the DB so
// the choice survives a fresh /me on another device.
router.post('/language', requireAuth, async (req, res, next) => {
  try {
    const code = String(req.body?.language ?? '').trim();
    if (!VALID_LOCALES.includes(code)) {
      return res.status(422).json({ ok: false, data: null, message: 'Unknown language.' });
    }
    await query('UPDATE userStatus SET language_preference = ? WHERE user_id = ?', [code, req.user.user_id]);
    // Keep the session row fresh like /update does, so the next currentUserRow
    // refresh reflects the change.
    if (req.session?.user) req.session.user.language_preference = code;
    res.json({ ok: true, data: { language: code }, message: null });
  } catch (err) {
    next(err);
  }
});

// System settings (the new Settings page): theme, privacy and AI persona/tone.
// Separate from /update because the Settings page does not edit identity fields
// (name/email), which /update requires. All fields are optional + partial.
router.post('/settings', requireAuth, async (req, res, next) => {
  const fail = (msg, code = 422) => res.status(code).json({ ok: false, data: null, message: msg });
  const d = req.body ?? {};
  const userId = req.user.user_id;

  const theme = 'theme_preference' in d ? String(d.theme_preference).trim() : null;
  const visibility = 'visibility' in d ? String(d.visibility).trim() : null;
  const showFav = 'show_favorite_food' in d ? (d.show_favorite_food ? 1 : 0) : null;
  const aiTone = 'ai_tone' in d ? String(d.ai_tone).trim() : null;
  // Custom persona is free text: '' clears it (NULL). Trim + hard length cap.
  const personaProvided = 'ai_persona' in d;
  const aiPersona = personaProvided ? String(d.ai_persona ?? '').trim().slice(0, AI_PERSONA_MAX) || null : null;

  if (theme !== null && !VALID_THEMES.includes(theme)) return fail('Invalid theme selected.');
  if (visibility !== null && !VALID_VISIBILITIES.includes(visibility)) return fail('Invalid profile visibility.');
  if (aiTone !== null && !VALID_AI_TONES.includes(aiTone)) return fail('Invalid AI tone.');

  try {
    await query(
      `UPDATE userStatus
          SET theme_preference = COALESCE(?, theme_preference),
              profile_visibility = COALESCE(?, profile_visibility),
              show_favorite_food = COALESCE(?, show_favorite_food),
              ai_tone = COALESCE(?, ai_tone),
              ai_persona = ${personaProvided ? '?' : 'ai_persona'}
        WHERE user_id = ?`,
      personaProvided
        ? [theme, visibility, showFav, aiTone, aiPersona, userId]
        : [theme, visibility, showFav, aiTone, userId]
    );
    const fresh = await fetchUser(userId);
    req.session.user = publicUser(fresh);
    res.json({ ok: true, data: await payload(fresh), message: null });
  } catch (err) {
    next(err);
  }
});

router.post('/update', requireAuth, async (req, res, next) => {
  const fail = (msg, code = 422) => res.status(code).json({ ok: false, data: null, message: msg });
  const d = req.body ?? {};
  const userId = req.user.user_id;

  const firstName = (d.first_name ?? '').trim();
  const lastName = (d.last_name ?? '').trim();
  const handle = (d.user_name ?? '').trim();
  const email = (d.email ?? '').trim();
  const bio = (d.bio ?? '').trim();
  // Theme now lives on the Settings page (POST /settings). Treat it as optional
  // here so saving the Profile (which no longer sends it) never resets it.
  const theme = 'theme_preference' in d ? String(d.theme_preference).trim() : null;
  // Optional — only written when present, so a Save that omits it never clobbers
  // a value set by the instant /language toggle (COALESCE leaves it untouched).
  const language = 'language_preference' in d ? String(d.language_preference).trim() : null;
  // Profile privacy — optional, only written when present (COALESCE-guarded below).
  const visibility = 'visibility' in d ? String(d.visibility).trim() : null;
  const showFavoriteFood = 'show_favorite_food' in d ? (d.show_favorite_food ? 1 : 0) : null;
  const calorieGoal = 'calorie_goal' in d ? nullableInt(d.calorie_goal) : null;
  const age = 'age' in d ? nullableInt(d.age) : null;
  let gender = 'gender' in d && d.gender !== null ? String(d.gender).trim() : null;
  const weight = 'weight' in d ? nullableFloat(d.weight) : null;
  const height = 'height' in d ? nullableFloat(d.height) : null;

  if (firstName === '' || lastName === '' || handle === '' || email === '') {
    return fail('Please fill in all required fields.');
  }
  if (!HANDLE_RE.test(handle)) {
    return fail('Username must be 3-30 characters: letters, numbers, and . # - _.');
  }
  if (!EMAIL_RE.test(email)) return fail('Please enter a valid email address.');
  if (theme !== null && !VALID_THEMES.includes(theme)) return fail('Invalid theme selected.');
  if (language !== null && !VALID_LOCALES.includes(language)) return fail('Invalid language selected.');
  if (visibility !== null && !VALID_VISIBILITIES.includes(visibility)) return fail('Invalid profile visibility.');
  if (calorieGoal !== null && (calorieGoal < 800 || calorieGoal > 10000)) {
    return fail('Please enter a valid calorie goal (800-10,000).');
  }
  if (age !== null && (age < 1 || age > 130)) return fail('Age must be between 1 and 130.');
  if (gender === '') gender = null;
  if (gender !== null && !VALID_GENDERS.includes(gender)) return fail('Invalid gender selected.');
  if (weight !== null && (weight <= 0 || weight > 999)) return fail('Weight must be between 1 and 999 kg.');
  if (height !== null && (height <= 0 || height > 300)) return fail('Height must be between 1 and 300 cm.');

  const conn = await pool.getConnection();
  try {
    // Uniqueness checks (excluding self) — mirror the 409s the PHP API returned.
    const [emailDup] = await conn.query('SELECT user_id FROM user WHERE email = ? AND user_id != ?', [email, userId]);
    if (emailDup.length) {
      return fail('This email is already taken by another user.', 409);
    }
    const [handleDup] = await conn.query('SELECT user_id FROM user WHERE user_name = ? AND user_id != ?', [
      handle,
      userId,
    ]);
    if (handleDup.length) {
      return fail('This username is already taken.', 409);
    }

    await conn.beginTransaction();

    await conn.query('UPDATE user SET first_name = ?, last_name = ?, user_name = ?, email = ? WHERE user_id = ?', [
      firstName,
      lastName,
      handle,
      email,
      userId,
    ]);

    await conn.query(
      `UPDATE userStatus
          SET profile_bio = ?,
              theme_preference = COALESCE(?, theme_preference),
              language_preference = COALESCE(?, language_preference),
              profile_visibility = COALESCE(?, profile_visibility),
              show_favorite_food = COALESCE(?, show_favorite_food)
        WHERE user_id = ?`,
      [bio, theme, language, visibility, showFavoriteFood, userId]
    );

    // A new goal is appended (history-preserving), matching the legacy INSERT.
    if (calorieGoal !== null) {
      await conn.query('INSERT INTO userGoal (user_id, calorie_goal, date_set) VALUES (?, ?, NOW())', [
        userId,
        calorieGoal,
      ]);
    }

    // Upsert physical info (PHP reuses user_id as the surrogate PK on insert).
    const [phys] = await conn.query('SELECT userPhysicalStat_id FROM userPhysicalInfo WHERE user_id = ?', [userId]);
    if (phys.length) {
      await conn.query('UPDATE userPhysicalInfo SET age = ?, gender = ?, weight = ?, height = ? WHERE user_id = ?', [
        age,
        gender,
        weight,
        height,
        userId,
      ]);
    } else {
      await conn.query(
        'INSERT INTO userPhysicalInfo (userPhysicalStat_id, user_id, age, gender, weight, height) VALUES (?, ?, ?, ?, ?, ?)',
        [userId, userId, age, gender, weight, height]
      );
    }

    await conn.commit();

    const fresh = await fetchUser(userId);
    // Keep the session row in sync so the next currentUserRow refresh (and the
    // SPA's auth store) reflects the rename/email/theme change immediately.
    req.session.user = publicUser(fresh);
    res.json({ ok: true, data: await payload(fresh), message: null });
  } catch (err) {
    await conn.rollback().catch(() => {});
    next(err);
  } finally {
    conn.release();
  }
});

// Upload / replace the profile avatar. The client compresses to a small JPEG
// first (client/src/lib/image.js); here we just validate, store, and point the
// user row at the new file (deleting the previous avatar if it was ours).
router.post('/avatar', requireAuth, avatarUpload.single('image'), async (req, res, next) => {
  try {
    const userId = req.user.user_id;
    if (!req.file) {
      return res.status(422).json({ ok: false, data: null, message: 'No image (JPEG/PNG/WebP, max 5MB).' });
    }

    const [prev] = await pool.query('SELECT profile_image FROM user WHERE user_id = ?', [userId]);
    const oldPath = prev[0]?.profile_image ?? null;

    // sharp re-encodes + validates the bytes here; a non-image (or spoofed MIME)
    // throws and is rejected as a 422 instead of being stored.
    let newPath;
    try {
      newPath = await saveProfileImage(userId, req.file.buffer);
    } catch {
      return res.status(422).json({ ok: false, data: null, message: 'That image could not be processed.' });
    }
    await query('UPDATE user SET profile_image = ? WHERE user_id = ?', [newPath, userId]);

    // Drop the old file only if it was a Vue-side avatar (never touch legacy
    // PHP uploads/ paths or external/Google URLs).
    if (oldPath && oldPath !== newPath) removeProfileImage(oldPath);

    const fresh = await fetchUser(userId);
    req.session.user = publicUser(fresh);
    res.json({ ok: true, data: { profile_image: req.session.user.profile_image }, message: null });
  } catch (err) {
    next(err);
  }
});

export default router;
