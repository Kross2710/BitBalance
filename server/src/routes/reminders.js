// Meal reminder preferences — per-user opt-in nudges to log each meal.
// Backed by the meal_reminder table (one row/user). The actual reminding is
// done client-side (in-app, when the app is open): the client compares the
// current time with each enabled meal's time and whether that meal is logged
// today. Background Web Push is future work (needs a Service Worker + host).
import { Router } from 'express';
import { query } from '../db.js';
import { requireAuth } from '../middleware/auth.js';

const router = Router();

const MEALS = ['breakfast', 'lunch', 'dinner', 'snack'];
const DEFAULTS = {
  enabled: false,
  meals: {
    breakfast: { enabled: true, time: '08:30' },
    lunch: { enabled: true, time: '12:30' },
    dinner: { enabled: true, time: '19:00' },
    snack: { enabled: false, time: '16:00' },
  },
};

const TIME_RE = /^([01]\d|2[0-3]):[0-5]\d$/; // HH:MM 24h

function shape(row) {
  if (!row) return DEFAULTS;
  const meals = {};
  for (const m of MEALS) {
    meals[m] = {
      enabled: Boolean(row[`${m}_enabled`]),
      // TIME comes back as 'HH:MM:SS' (dateStrings) — the <input type=time> wants HH:MM.
      time: String(row[`${m}_time`] ?? '').slice(0, 5),
    };
  }
  return { enabled: Boolean(row.enabled), meals };
}

router.get('/', requireAuth, async (req, res, next) => {
  try {
    const rows = await query('SELECT * FROM meal_reminder WHERE user_id = ? LIMIT 1', [req.user.user_id]);
    res.json({ ok: true, data: shape(rows[0]), message: null });
  } catch (err) {
    next(err);
  }
});

router.post('/', requireAuth, async (req, res, next) => {
  try {
    const body = req.body || {};
    const enabled = body.enabled ? 1 : 0;
    const meals = body.meals || {};

    // Validate every meal up front so a bad time never gets a partial write.
    const vals = {};
    for (const m of MEALS) {
      const incoming = meals[m] || {};
      const time = String(incoming.time ?? DEFAULTS.meals[m].time);
      if (!TIME_RE.test(time)) {
        return res.status(422).json({ ok: false, data: null, message: `Invalid time for ${m}.` });
      }
      vals[m] = { enabled: incoming.enabled ? 1 : 0, time: `${time}:00` };
    }

    await query(
      `INSERT INTO meal_reminder
         (user_id, enabled,
          breakfast_enabled, breakfast_time, lunch_enabled, lunch_time,
          dinner_enabled, dinner_time, snack_enabled, snack_time)
       VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
       ON DUPLICATE KEY UPDATE
         enabled = VALUES(enabled),
         breakfast_enabled = VALUES(breakfast_enabled), breakfast_time = VALUES(breakfast_time),
         lunch_enabled = VALUES(lunch_enabled), lunch_time = VALUES(lunch_time),
         dinner_enabled = VALUES(dinner_enabled), dinner_time = VALUES(dinner_time),
         snack_enabled = VALUES(snack_enabled), snack_time = VALUES(snack_time)`,
      [
        req.user.user_id, enabled,
        vals.breakfast.enabled, vals.breakfast.time,
        vals.lunch.enabled, vals.lunch.time,
        vals.dinner.enabled, vals.dinner.time,
        vals.snack.enabled, vals.snack.time,
      ]
    );

    const rows = await query('SELECT * FROM meal_reminder WHERE user_id = ? LIMIT 1', [req.user.user_id]);
    res.json({ ok: true, data: shape(rows[0]), message: null });
  } catch (err) {
    next(err);
  }
});

export default router;
