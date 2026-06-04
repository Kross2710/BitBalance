// Intake routes — ports api/intake/history.php and api/intake/create.php.
// NOTE: XP awards + logging-streak updates (include/handlers/xp.php) are NOT
// ported in this scaffold — see MIGRATION.md. The create response keeps the
// same shape with xp.added = 0 so the client contract is stable.
import { Router } from 'express';
import multer from 'multer';
import { query } from '../db.js';
import { requireAuth } from '../middleware/auth.js';
import { validateIntake, shapeEntry, dailySummary, fetchEntry, parseEstimate, ValidationError } from '../lib/intake.js';
import { lookupBarcode, BarcodeError } from '../lib/barcode.js';
import { chatCompletion } from '../lib/aiProvider.js';
import { saveIntakeImage } from '../lib/uploads.js';
import { aiQuotaExceeded, bumpAiUsage, AI_DAILY_LIMIT } from '../lib/aiUsage.js';
import { fieldVoiceLanguage } from '../lib/aiVoice.js';

// Shared 429 for the per-user daily AI budget (covers Coach + these vision calls).
const aiLimitMsg = `Daily AI limit reached (${AI_DAILY_LIMIT}). Please try again tomorrow.`;

// In-memory upload for AI photo estimation: we forward the bytes to the model
// AND persist a copy so the logged entry can show the photo later. 5MB cap
// mirrors the legacy AI_COACH_MAX_IMAGE_BYTES.
const upload = multer({ storage: multer.memoryStorage(), limits: { fileSize: 5 * 1024 * 1024 } });
const PHOTO_MIMES = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];

// One-shot nutritionist prompt — ports the system prompt of dashboard/handlers/
// ai_chat.php. Forces a raw JSON object describing the food in the photo.
const ESTIMATE_BASE =
  'You are a professional Nutritionist AI. Analyze the food in the image and estimate ' +
  'the nutritional values for the portion shown. If the image is NOT food, set every ' +
  'numeric field to 0 and food_name to "Not food". Reply with ONLY a raw JSON object ' +
  '(no markdown, no code fences) of exactly this shape: ' +
  '{"food_name":"Name","calories":0,"protein":0,"carbs":0,"fat":0,"unit":"1 serving","short_advice":"one short tip"}';

// Chat-oriented nutritionist prompt for /ai-chat — like ESTIMATE_BASE but uses
// the whole conversation so the user can correct the dish over multiple turns.
const CHAT_BASE =
  'You are a professional Nutritionist AI helping a user log a meal. Analyze the food in ' +
  'the image and/or the text the user sends, and use the whole conversation for context — ' +
  'the user may clarify or correct the dish (e.g. "it is actually pho bo"). Estimate the ' +
  'nutrition for the portion described. Reply with ONLY a raw JSON object (no markdown, no ' +
  'code fences) of exactly this shape: ' +
  '{"food_name":"Name","calories":0,"protein":0,"carbs":0,"fat":0,"unit":"1 serving","short_advice":"one short tip"}. ' +
  'If you cannot identify a food yet, set food_name to null and put a short clarifying question in short_advice.';

// Per-user voice/language: append the shared tone+persona+language guidance (from
// Settings) so the Intake AI's short_advice matches the user's chosen AI voice and
// preferred language, the same way the AI Coach does. Built per request from req.user.
const estimatePrompt = (req) =>
  ESTIMATE_BASE + fieldVoiceLanguage(userLang(req), req.user?.ai_tone || 'formal', req.user?.ai_persona || '');
const chatPrompt = (req) =>
  CHAT_BASE + fieldVoiceLanguage(userLang(req), req.user?.ai_tone || 'formal', req.user?.ai_persona || '', { vary: true });
const userLang = (req) => (req.user?.language_preference === 'vi' ? 'Vietnamese' : 'English');
import { awardIntakeLog, awardStreakMilestone, getSummary, consumeLevelupFlash } from '../lib/xp.js';
import { updateLoggingStreak } from '../lib/streak.js';
import { loggingStreak } from '../lib/dashboard.js';
import { seedAchievementBaseline, newlyUnlockedSince } from '../lib/achievements.js';
import { vnLiteralForUserLocalNoon, userLocalDateOf } from '../lib/tz.js';

const router = Router();

router.get('/history', requireAuth, async (req, res, next) => {
  try {
    const userId = req.user.user_id;
    let limit = req.query.limit ? parseInt(req.query.limit, 10) : 50;
    limit = Math.max(1, Math.min(100, Number.isNaN(limit) ? 50 : limit));

    // Optional day scope (YYYY-MM-DD) so the Intake page can manage a past day's
    // entries, which may be older than the default recent window.
    const date = String(req.query.date ?? '').trim();
    const byDate = /^\d{4}-\d{2}-\d{2}$/.test(date);

    const rows = await query(
      `SELECT intakeLog_id, food_item, calories, protein, carbs, fat, meal_category, image_path, date_intake
         FROM intakeLog
        WHERE user_id = ?${byDate ? ' AND DATE(date_intake + INTERVAL ? MINUTE) = ?' : ''}
        ORDER BY date_intake DESC, intakeLog_id DESC
        LIMIT ?`,
      byDate ? [userId, req.tzShift, date, limit] : [userId, limit]
    );

    res.json({
      ok: true,
      data: {
        entries: rows.map(shapeEntry),
        daily_summary: await dailySummary(userId, byDate ? date : null, req.tzShift),
      },
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
    // Per-user daily AI budget (shared with the Coach) — check BEFORE the paid
    // model call so an over-quota user never incurs cost.
    if (await aiQuotaExceeded(req.user.user_id, req.todayTz)) {
      return res.status(429).json({ ok: false, data: null, message: aiLimitMsg });
    }

    const image = { mime: req.file.mimetype, data: req.file.buffer.toString('base64') };
    const result = await chatCompletion({
      system: estimatePrompt(req),
      history: [{ role: 'user', content: 'Estimate the food in this photo.' }],
      image,
    });
    if (!result.ok) {
      return res.status(502).json({ ok: false, data: null, message: result.error || 'AI error' });
    }
    // The model call succeeded (cost incurred) — count it against the budget.
    await bumpAiUsage(req.user.user_id, req.todayTz);

    const parsed = parseEstimate(result.text);
    if (!parsed) {
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

    res.json({ ok: true, data: { ...parsed, image_path: imagePath }, message: null });
  } catch (err) {
    next(err);
  }
});

// AI food chat — multi-turn port of dashboard/handlers/ai_chat.php. The client
// owns the conversation (stateless server, like the rest of the app) and re-sends
// the recent text turns + the active photo each call, so corrections like "it's
// actually pho bo" keep context. Returns one nutrition card per reply.
router.post('/ai-chat', requireAuth, upload.single('image'), async (req, res, next) => {
  try {
    let image = null;
    if (req.file) {
      if (!PHOTO_MIMES.includes(req.file.mimetype)) {
        return res.status(415).json({ ok: false, data: null, message: 'Only JPG, PNG, WEBP or GIF images are allowed.' });
      }
      image = { mime: req.file.mimetype, data: req.file.buffer.toString('base64') };
    }

    const message = String(req.body?.message ?? '').trim().slice(0, 1000);
    if (!message && !image) {
      return res.status(400).json({ ok: false, data: null, message: 'Send a photo or a message.' });
    }
    // Per-user daily AI budget (shared with the Coach), checked before the call.
    if (await aiQuotaExceeded(req.user.user_id, req.todayTz)) {
      return res.status(429).json({ ok: false, data: null, message: aiLimitMsg });
    }

    // Prior turns from the client (text only); cap + sanitize.
    let history = [];
    try {
      const raw = JSON.parse(req.body?.history ?? '[]');
      if (Array.isArray(raw)) {
        history = raw
          .filter((m) => m && (m.role === 'user' || m.role === 'assistant') && typeof m.content === 'string')
          .slice(-8)
          .map((m) => ({ role: m.role, content: m.content.slice(0, 1000) }));
      }
    } catch {
      /* ignore malformed history */
    }
    // Current user turn — the image attaches to the last user message in aiProvider.
    history.push({ role: 'user', content: message || 'Estimate the food in this photo.' });

    const result = await chatCompletion({ system: chatPrompt(req), history, image });
    if (!result.ok) {
      return res.status(502).json({ ok: false, data: null, message: result.error || 'AI error' });
    }
    // Successful model call (cost incurred) — count it against the daily budget.
    await bumpAiUsage(req.user.user_id, req.todayTz);

    // Prefer the structured card; fall back to the raw reply as a chat message so
    // a non-JSON answer still shows up instead of erroring.
    const parsed = parseEstimate(result.text) || {
      food_name: null, calories: 0, protein: 0, carbs: 0, fat: 0, unit: null,
      short_advice: String(result.text).trim().slice(0, 200) || 'Sorry, I could not read that.',
    };

    let imagePath = null;
    if (req.file) {
      try {
        imagePath = saveIntakeImage(req.user.user_id, req.file.buffer, req.file.mimetype);
      } catch (e) {
        console.error('intake ai-chat image save error:', e);
      }
    }

    res.json({ ok: true, data: { ...parsed, image_path: imagePath }, message: null });
  } catch (err) {
    next(err);
  }
});

router.post('/create', requireAuth, async (req, res, next) => {
  try {
    const payload = validateIntake(req.body, false);
    const userId = req.user.user_id;

    // Seed the achievement baseline BEFORE the log so the very first log of a
    // session (which may unlock First Bite) still has a "before" to diff
    // against. No-op once seeded, so only the first log of a session pays the
    // extra cost. Best-effort: never let it fail the log.
    try {
      await seedAchievementBaseline(userId, req.session, req.tzShift);
    } catch (e) {
      console.error('intake achievement seed error:', e);
    }

    // Optional backdating (ports process_intake.php): clamp future -> today, and
    // only today's logs bump the streak. "today" is the user's local day; the
    // stored date_intake is always a +07:00 wall-clock literal.
    const today = req.todayTz;
    const logDate = payload.date && payload.date <= today ? payload.date : today;
    const isToday = logDate === today;

    const baseCols = [userId, payload.food_item, payload.calories, payload.protein, payload.carbs, payload.fat, payload.meal_category, payload.image_path];
    let result;
    if (isToday) {
      // DEFAULT CURRENT_TIMESTAMP (+07:00). The instant is now, so its user-local
      // date is always today regardless of shift.
      result = await query(
        `INSERT INTO intakeLog (user_id, food_item, calories, protein, carbs, fat, meal_category, image_path)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?)`,
        baseCols
      );
    } else if (req.tzShift === 0) {
      // VN: keep the real +07:00 time-of-day so backdated entries order sensibly.
      result = await query(
        `INSERT INTO intakeLog (user_id, food_item, calories, protein, carbs, fat, meal_category, image_path, date_intake)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, CONCAT(?, ' ', CURTIME()))`,
        [...baseCols, logDate]
      );
    } else {
      // Non-VN: stamp the user-local NOON of logDate, expressed as a +07:00 literal,
      // so DATE(date_intake + INTERVAL shift MINUTE) === logDate (no midnight cross).
      result = await query(
        `INSERT INTO intakeLog (user_id, food_item, calories, protein, carbs, fat, meal_category, image_path, date_intake)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)`,
        [...baseCols, vnLiteralForUserLocalNoon(req.tz, logDate)]
      );
    }

    const [entry] = await query(
      `SELECT intakeLog_id, food_item, calories, protein, carbs, fat, meal_category, image_path, date_intake
         FROM intakeLog WHERE user_id = ? AND intakeLog_id = ? LIMIT 1`,
      [userId, result.insertId]
    );

    // XP + streak side effects (ports api/intake/create.php). Each step is
    // best-effort: a failure here must not fail the log itself.
    let xpAdded = 0;
    try {
      const r = await awardIntakeLog(userId, req.session, req.tzShift);
      xpAdded += r.xp_added ?? 0;
    } catch (e) {
      console.error('intake xp award error:', e);
    }
    // Streak + milestone bump only for today's logs — backdating must not inflate
    // the current streak (recomputing historical streaks is out of scope).
    if (isToday) {
      try {
        await updateLoggingStreak(userId, req.todayTz);
        const streak = await loggingStreak(userId);
        const m = await awardStreakMilestone(userId, streak.current, req.session);
        xpAdded += m.xp_added ?? 0;
      } catch (e) {
        console.error('intake streak update error:', e);
      }
    }

    let xpSummary = null;
    try {
      xpSummary = await getSummary(userId);
    } catch (e) {
      console.error('intake xp summary error:', e);
    }

    // Achievements gained by THIS log, for the celebratory unlock toast. Diffs
    // against the session baseline seeded above; best-effort so a failure here
    // never breaks the log itself.
    let newlyUnlocked = [];
    try {
      newlyUnlocked = await newlyUnlockedSince(userId, req.session, req.tzShift);
    } catch (e) {
      console.error('intake achievement unlock diff error:', e);
    }

    res.status(201).json({
      ok: true,
      data: {
        entry: entry ? shapeEntry(entry) : null,
        daily_summary: await dailySummary(userId, logDate, req.tzShift),
        date: logDate,
        is_today: isToday,
        xp: { added: xpAdded, summary: xpSummary, levelup: consumeLevelupFlash(req.session) },
        newly_unlocked: newlyUnlocked,
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
      data: {
        entry: shapeEntry(entry),
        daily_summary: await dailySummary(
          userId,
          entry.date_intake ? userLocalDateOf(entry.date_intake, req.tzShift) : null,
          req.tzShift
        ),
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
        daily_summary: await dailySummary(
          userId,
          snapshot[0]?.date_intake ? userLocalDateOf(snapshot[0].date_intake, req.tzShift) : null,
          req.tzShift
        ),
      },
      message: null,
    });
  } catch (err) {
    next(err);
  }
});

export default router;
