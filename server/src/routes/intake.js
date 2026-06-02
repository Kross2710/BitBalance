// Intake routes — ports api/intake/history.php and api/intake/create.php.
// NOTE: XP awards + logging-streak updates (include/handlers/xp.php) are NOT
// ported in this scaffold — see MIGRATION.md. The create response keeps the
// same shape with xp.added = 0 so the client contract is stable.
import { Router } from 'express';
import { query } from '../db.js';
import { requireAuth } from '../middleware/auth.js';
import { validateIntake, shapeEntry, dailySummary, fetchEntry, ValidationError } from '../lib/intake.js';
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
      `SELECT intakeLog_id, food_item, calories, protein, carbs, fat, meal_category, date_intake
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

router.post('/create', requireAuth, async (req, res, next) => {
  try {
    const payload = validateIntake(req.body, false);
    const userId = req.user.user_id;

    const result = await query(
      `INSERT INTO intakeLog (user_id, food_item, calories, protein, carbs, fat, meal_category)
       VALUES (?, ?, ?, ?, ?, ?, ?)`,
      [userId, payload.food_item, payload.calories, payload.protein, payload.carbs, payload.fat, payload.meal_category]
    );

    const [entry] = await query(
      `SELECT intakeLog_id, food_item, calories, protein, carbs, fat, meal_category, date_intake
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
