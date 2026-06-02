// Intake routes — ports api/intake/history.php and api/intake/create.php.
// NOTE: XP awards + logging-streak updates (include/handlers/xp.php) are NOT
// ported in this scaffold — see MIGRATION.md. The create response keeps the
// same shape with xp.added = 0 so the client contract is stable.
import { Router } from 'express';
import multer from 'multer';
import { query } from '../db.js';
import { requireAuth } from '../middleware/auth.js';
import { validateIntake, shapeEntry, dailySummary, fetchEntry, ValidationError } from '../lib/intake.js';
import { lookupBarcode, BarcodeError } from '../lib/barcode.js';
import { chatCompletion } from '../lib/aiProvider.js';
import { saveIntakeImage } from '../lib/uploads.js';

// In-memory upload for AI photo estimation: we forward the bytes to the model
// AND persist a copy so the logged entry can show the photo later. 5MB cap
// mirrors the legacy AI_COACH_MAX_IMAGE_BYTES.
const upload = multer({ storage: multer.memoryStorage(), limits: { fileSize: 5 * 1024 * 1024 } });
const PHOTO_MIMES = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];

// One-shot nutritionist prompt — ports the system prompt of dashboard/handlers/
// ai_chat.php. Forces a raw JSON object describing the food in the photo.
const ESTIMATE_PROMPT =
  'You are a professional Nutritionist AI. Analyze the food in the image and estimate ' +
  'the nutritional values for the portion shown. If the image is NOT food, set every ' +
  'numeric field to 0 and food_name to "Not food". Reply with ONLY a raw JSON object ' +
  '(no markdown, no code fences) of exactly this shape: ' +
  '{"food_name":"Name","calories":0,"protein":0,"carbs":0,"fat":0,"unit":"1 serving","short_advice":"one short tip"}';

function round1(v) {
  return Math.round((Number(v) || 0) * 10) / 10;
}
import { awardIntakeLog, awardStreakMilestone, getSummary, consumeLevelupFlash } from '../lib/xp.js';
import { updateLoggingStreak } from '../lib/streak.js';
import { loggingStreak } from '../lib/dashboard.js';

const router = Router();

router.get('/history', requireAuth, async (req, res, next) => {
  try {
    const userId = req.user.user_id;
    let limit = req.query.limit ? parseInt(req.query.limit, 10) : 50;
    limit = Math.max(1, Math.min(100, Number.isNaN(limit) ? 50 : limit));

    const rows = await query(
      `SELECT intakeLog_id, food_item, calories, protein, carbs, fat, meal_category, image_path, date_intake
         FROM intakeLog
        WHERE user_id = ?
        ORDER BY date_intake DESC, intakeLog_id DESC
        LIMIT ?`,
      [userId, limit]
    );

    res.json({
      ok: true,
      data: { entries: rows.map(shapeEntry), daily_summary: await dailySummary(userId) },
      message: null,
    });
  } catch (err) {
    next(err);
  }
});

// Ports api/intake/suggest.php. The app has no master food DB, so suggestions
// come from the user's own intakeLog. q empty -> most-frequently logged foods
// (recent chips); q present -> name contains q (autocomplete). Each item carries
// macros from the MOST RECENT time that food was logged (MAX(intakeLog_id)).
router.get('/suggest', requireAuth, async (req, res, next) => {
  try {
    const userId = req.user.user_id;
    const q = String(req.query.q ?? '').trim().slice(0, 100);
    // Escape LIKE wildcards so % and _ match literally.
    const like = '%' + q.replace(/[\\%_]/g, (ch) => '\\' + ch) + '%';

    const rows = await query(
      `SELECT i.food_item, i.calories, i.protein, i.carbs, i.fat, c.freq
         FROM intakeLog i
         JOIN (
            SELECT food_item, COUNT(*) AS freq, MAX(intakeLog_id) AS last_id
              FROM intakeLog
             WHERE user_id = ? AND food_item LIKE ?
             GROUP BY food_item
         ) c ON c.last_id = i.intakeLog_id
        ORDER BY c.freq DESC, i.intakeLog_id DESC
        LIMIT 8`,
      [userId, like]
    );

    const items = rows.map((row) => ({
      food_item: row.food_item,
      calories: Number(row.calories) || 0,
      protein: Math.round((Number(row.protein) || 0) * 10) / 10,
      carbs: Math.round((Number(row.carbs) || 0) * 10) / 10,
      fat: Math.round((Number(row.fat) || 0) * 10) / 10,
      freq: Number(row.freq) || 0,
    }));

    res.json({ ok: true, data: { items }, message: null });
  } catch (err) {
    next(err);
  }
});

// Ports api/intake/lookup_barcode.php. Validates the barcode, then resolves it
// via the local cache or OpenFoodFacts. The client uses the result to prefill
// the log form.
router.post('/lookup-barcode', requireAuth, async (req, res, next) => {
  try {
    const barcode = String(req.body?.barcode ?? '').trim();
    if (!/^\d{6,20}$/.test(barcode)) {
      return res.status(422).json({ ok: false, data: null, message: 'Invalid barcode format.' });
    }
    const payload = await lookupBarcode(req.user.user_id, barcode);
    res.json({ ok: true, data: payload, message: null });
  } catch (err) {
    if (err instanceof BarcodeError) {
      return res.status(502).json({ ok: false, data: null, message: err.message });
    }
    next(err);
  }
});

// AI Photo estimate — port of the image branch of dashboard/handlers/ai_chat.php.
// Forwards the uploaded photo to the vision model and returns one food estimate
// the client uses to prefill the form. The image is never persisted.
router.post('/estimate-photo', requireAuth, upload.single('image'), async (req, res, next) => {
  try {
    if (!req.file) {
      return res.status(400).json({ ok: false, data: null, message: 'No image uploaded.' });
    }
    if (!PHOTO_MIMES.includes(req.file.mimetype)) {
      return res.status(415).json({ ok: false, data: null, message: 'Only JPG, PNG, WEBP or GIF images are allowed.' });
    }

    const image = { mime: req.file.mimetype, data: req.file.buffer.toString('base64') };
    const result = await chatCompletion({
      system: ESTIMATE_PROMPT,
      history: [{ role: 'user', content: 'Estimate the food in this photo.' }],
      image,
    });
    if (!result.ok) {
      return res.status(502).json({ ok: false, data: null, message: result.error || 'AI error' });
    }

    // Models sometimes wrap JSON in prose/fences — extract the first object.
    let txt = result.text.trim().replace(/^```(?:json)?\s*|\s*```$/gi, '');
    const m = /\{[\s\S]*\}/.exec(txt);
    if (m) txt = m[0];
    let parsed;
    try {
      parsed = JSON.parse(txt);
    } catch {
      return res.status(502).json({ ok: false, data: null, message: 'AI returned an unreadable estimate.' });
    }

    // Persist the photo so the user can review it on the logged entry. The
    // client passes this image_path back on /create. Best-effort: if saving
    // fails we still return the estimate (just without a reviewable photo).
    let imagePath = null;
    try {
      imagePath = saveIntakeImage(req.user.user_id, req.file.buffer, req.file.mimetype);
    } catch (e) {
      console.error('intake photo save error:', e);
    }

    res.json({
      ok: true,
      data: {
        food_name: String(parsed.food_name ?? '').slice(0, 80),
        calories: Math.max(0, Math.round(Number(parsed.calories) || 0)),
        protein: round1(parsed.protein),
        carbs: round1(parsed.carbs),
        fat: round1(parsed.fat),
        unit: parsed.unit ? String(parsed.unit).slice(0, 40) : null,
        short_advice: parsed.short_advice ? String(parsed.short_advice).slice(0, 200) : null,
        image_path: imagePath,
      },
      message: null,
    });
  } catch (err) {
    next(err);
  }
});

router.post('/create', requireAuth, async (req, res, next) => {
  try {
    const payload = validateIntake(req.body, false);
    const userId = req.user.user_id;

    const result = await query(
      `INSERT INTO intakeLog (user_id, food_item, calories, protein, carbs, fat, meal_category, image_path)
       VALUES (?, ?, ?, ?, ?, ?, ?, ?)`,
      [userId, payload.food_item, payload.calories, payload.protein, payload.carbs, payload.fat, payload.meal_category, payload.image_path]
    );

    const [entry] = await query(
      `SELECT intakeLog_id, food_item, calories, protein, carbs, fat, meal_category, image_path, date_intake
         FROM intakeLog WHERE user_id = ? AND intakeLog_id = ? LIMIT 1`,
      [userId, result.insertId]
    );

    // XP + streak side effects (ports api/intake/create.php). Each step is
    // best-effort: a failure here must not fail the log itself.
    let xpAdded = 0;
    try {
      const r = await awardIntakeLog(userId, req.session);
      xpAdded += r.xp_added ?? 0;
    } catch (e) {
      console.error('intake xp award error:', e);
    }
    try {
      await updateLoggingStreak(userId);
      const streak = await loggingStreak(userId);
      const m = await awardStreakMilestone(userId, streak.current, req.session);
      xpAdded += m.xp_added ?? 0;
    } catch (e) {
      console.error('intake streak update error:', e);
    }

    let xpSummary = null;
    try {
      xpSummary = await getSummary(userId);
    } catch (e) {
      console.error('intake xp summary error:', e);
    }

    res.status(201).json({
      ok: true,
      data: {
        entry: entry ? shapeEntry(entry) : null,
        daily_summary: await dailySummary(userId),
        xp: { added: xpAdded, summary: xpSummary, levelup: consumeLevelupFlash(req.session) },
      },
      message: null,
    });
  } catch (err) {
    if (err instanceof ValidationError) {
      return res.status(err.status).json({ ok: false, data: null, message: err.message });
    }
    next(err);
  }
});

// Ports api/intake/update.php (POST for parity with the legacy contract).
router.post('/update', requireAuth, async (req, res, next) => {
  try {
    const payload = validateIntake(req.body, true);
    const userId = req.user.user_id;

    await query(
      `UPDATE intakeLog
          SET food_item = ?, calories = ?, protein = ?, carbs = ?, fat = ?, meal_category = ?
        WHERE intakeLog_id = ? AND user_id = ?`,
      [payload.food_item, payload.calories, payload.protein, payload.carbs, payload.fat, payload.meal_category, payload.id, userId]
    );

    const entry = await fetchEntry(userId, payload.id);
    if (!entry) {
      return res.status(404).json({ ok: false, data: null, message: 'Intake entry not found.' });
    }

    res.json({
      ok: true,
      data: { entry: shapeEntry(entry), daily_summary: await dailySummary(userId) },
      message: null,
    });
  } catch (err) {
    if (err instanceof ValidationError) {
      return res.status(err.status).json({ ok: false, data: null, message: err.message });
    }
    next(err);
  }
});

// Ports api/intake/delete.php. Returns the deleted row so the client can offer Undo.
router.post('/delete', requireAuth, async (req, res, next) => {
  try {
    const userId = req.user.user_id;
    const intakeId = req.body?.intake_id ? parseInt(req.body.intake_id, 10) : 0;
    if (!intakeId || intakeId <= 0) {
      return res.status(422).json({ ok: false, data: null, message: 'Missing intake ID.' });
    }

    const snapshot = await query(
      `SELECT food_item, calories, protein, carbs, fat, meal_category, image_path, date_intake
         FROM intakeLog WHERE intakeLog_id = ? AND user_id = ?`,
      [intakeId, userId]
    );

    const result = await query('DELETE FROM intakeLog WHERE intakeLog_id = ? AND user_id = ?', [intakeId, userId]);
    if (result.affectedRows < 1) {
      return res.status(404).json({ ok: false, data: null, message: 'Intake entry not found.' });
    }

    res.json({
      ok: true,
      data: {
        deleted_id: intakeId,
        deleted_row: snapshot[0] ?? null,
        daily_summary: await dailySummary(userId),
      },
      message: null,
    });
  } catch (err) {
    next(err);
  }
});

export default router;
