// Intake routes — ports api/intake/history.php and api/intake/create.php.
// NOTE: XP awards + logging-streak updates (include/handlers/xp.php) are NOT
// ported in this scaffold — see MIGRATION.md. The create response keeps the
// same shape with xp.added = 0 so the client contract is stable.
import { Router } from 'express';
import { query } from '../db.js';
import { requireAuth } from '../middleware/auth.js';
import { validateIntake, shapeEntry, dailySummary, ValidationError } from '../lib/intake.js';

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

    res.status(201).json({
      ok: true,
      data: {
        entry: entry ? shapeEntry(entry) : null,
        daily_summary: await dailySummary(userId),
        xp: { added: 0, summary: null, levelup: null }, // TODO: port include/handlers/xp.php
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

export default router;
