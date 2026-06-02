// Onboarding route — ports api/onboarding/save.php (and the commit block of
// dashboard/set-goal.php). Validates physical info + goal, builds the personal
// plan, then writes userPhysicalInfo / weight_log / user_plan_preferences /
// userGoal inside one transaction.
import { Router } from 'express';
import { pool } from '../db.js';
import { requireAuth } from '../middleware/auth.js';
import { ACTIVITY_FACTORS, GOAL_MODES, buildPersonalPlan, savePreferences } from '../lib/plan.js';

const router = Router();
const VALID_GENDERS = ['male', 'female', 'other'];

// Strict-ish integer parse to mirror PHP FILTER_VALIDATE_INT (rejects "12.5", "abc").
function parseIntStrict(v) {
  if (typeof v === 'number') return Number.isInteger(v) ? v : null;
  if (typeof v === 'string' && /^-?\d+$/.test(v.trim())) return parseInt(v, 10);
  return null;
}
function parseFloatLoose(v) {
  if (v === '' || v == null) return null;
  const n = Number(v);
  return Number.isFinite(n) ? n : null;
}

router.post('/save', requireAuth, async (req, res, next) => {
  const fail = (msg, code = 422) => res.status(code).json({ ok: false, data: null, message: msg });

  const gender = (req.body?.gender ?? '').trim();
  const age = parseIntStrict(req.body?.age);
  const height = parseIntStrict(req.body?.height);
  const weight = parseIntStrict(req.body?.weight);
  const activityLevel = (req.body?.activity_level ?? '').trim();
  const goalMode = (req.body?.goal_mode ?? '').trim();
  let weeklyRate = parseFloatLoose(req.body?.weekly_rate);
  let targetWeight = parseFloatLoose(req.body?.target_weight);

  if (!VALID_GENDERS.includes(gender)) return fail('Please choose a gender.');
  if (age === null || age < 13 || age > 100) return fail('Please choose a valid age.');
  if (height === null || height < 100 || height > 250) return fail('Please choose a valid height.');
  if (weight === null || weight < 30 || weight > 300) return fail('Please choose a valid weight.');
  if (!ACTIVITY_FACTORS[activityLevel]) return fail('Please choose an activity level.');
  if (!GOAL_MODES.includes(goalMode)) return fail('Please choose a goal.');

  if (goalMode === 'maintain') {
    weeklyRate = 0;
    targetWeight = null;
  } else if (weeklyRate === null) {
    return fail('Please choose a weekly pace.');
  }

  const conn = await pool.getConnection();
  try {
    const plan = buildPersonalPlan(age, gender, weight, height, activityLevel, goalMode, weeklyRate);

    await conn.beginTransaction();

    // Upsert physical info (PHP reuses user_id as the surrogate PK on insert).
    const [phys] = await conn.query('SELECT userPhysicalStat_id FROM userPhysicalInfo WHERE user_id = ? LIMIT 1', [
      req.user.user_id,
    ]);
    if (phys.length) {
      await conn.query('UPDATE userPhysicalInfo SET age=?, gender=?, weight=?, height=? WHERE user_id=?', [
        age,
        gender,
        weight,
        height,
        req.user.user_id,
      ]);
    } else {
      await conn.query(
        'INSERT INTO userPhysicalInfo (userPhysicalStat_id, user_id, age, gender, weight, height) VALUES (?,?,?,?,?,?)',
        [req.user.user_id, req.user.user_id, age, gender, weight, height]
      );
    }

    // Weight log — same-day upsert.
    const [wlog] = await conn.query(
      'SELECT weight_id FROM weight_log WHERE user_id = ? AND date_logged = CURDATE() LIMIT 1',
      [req.user.user_id]
    );
    if (wlog.length) {
      await conn.query('UPDATE weight_log SET weight = ? WHERE weight_id = ? AND user_id = ?', [
        weight,
        wlog[0].weight_id,
        req.user.user_id,
      ]);
    } else {
      await conn.query('INSERT INTO weight_log (user_id, weight, date_logged) VALUES (?, ?, CURDATE())', [
        req.user.user_id,
        weight,
      ]);
    }

    // PHP wraps this in its own try/catch: a prefs write failure is logged and
    // does NOT abort the rest of the onboarding commit.
    try {
      await savePreferences(req.user.user_id, {
        goal_mode: goalMode,
        weekly_rate: weeklyRate,
        activity_level: activityLevel,
        target_weight: targetWeight,
      });
    } catch (e) {
      console.error('Onboarding plan prefs save failed:', e);
    }

    await conn.query('INSERT INTO userGoal (user_id, calorie_goal, date_set) VALUES (?, ?, NOW())', [
      req.user.user_id,
      plan.calorie_goal,
    ]);

    await conn.commit();

    res.json({
      ok: true,
      data: {
        calorie_goal: plan.calorie_goal,
        bmr: Math.round(plan.bmr),
        tdee: Math.round(plan.tdee),
        macros: plan.macros,
        hydration_ml: plan.hydration_ml,
      },
      message: null,
    });
  } catch (err) {
    await conn.rollback().catch(() => {});
    next(err);
  } finally {
    conn.release();
  }
});

export default router;
