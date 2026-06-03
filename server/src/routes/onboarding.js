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

// Parse + validate the onboarding payload identically for /preview and /save so
// the previewed plan can never diverge from what /save commits. Returns either
// { error } (a 422 message) or { values } (normalized, ready for the planner).
function readOnboardingInput(body) {
  const gender = (body?.gender ?? '').trim();
  const age = parseIntStrict(body?.age);
  const height = parseIntStrict(body?.height);
  const weight = parseIntStrict(body?.weight);
  const activityLevel = (body?.activity_level ?? '').trim();
  const goalMode = (body?.goal_mode ?? '').trim();
  let weeklyRate = parseFloatLoose(body?.weekly_rate);
  let targetWeight = parseFloatLoose(body?.target_weight);

  if (!VALID_GENDERS.includes(gender)) return { error: 'Please choose a gender.' };
  if (age === null || age < 13 || age > 100) return { error: 'Please choose a valid age.' };
  if (height === null || height < 100 || height > 250) return { error: 'Please choose a valid height.' };
  if (weight === null || weight < 30 || weight > 300) return { error: 'Please choose a valid weight.' };
  if (!ACTIVITY_FACTORS[activityLevel]) return { error: 'Please choose an activity level.' };
  if (!GOAL_MODES.includes(goalMode)) return { error: 'Please choose a goal.' };

  if (goalMode === 'maintain') {
    weeklyRate = 0;
    targetWeight = null;
  } else if (weeklyRate === null) {
    return { error: 'Please choose a weekly pace.' };
  }

  return { values: { gender, age, height, weight, activityLevel, goalMode, weeklyRate, targetWeight } };
}

// Compute the plan for the review screen WITHOUT persisting anything, so the
// wizard can show calories/macros and let the user confirm before /save writes.
router.post('/preview', requireAuth, (req, res) => {
  const { error, values } = readOnboardingInput(req.body);
  if (error) return res.status(422).json({ ok: false, data: null, message: error });

  const plan = buildPersonalPlan(
    values.age,
    values.gender,
    values.weight,
    values.height,
    values.activityLevel,
    values.goalMode,
    values.weeklyRate
  );

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
});

router.post('/save', requireAuth, async (req, res, next) => {
  const { error, values } = readOnboardingInput(req.body);
  if (error) return res.status(422).json({ ok: false, data: null, message: error });
  const { gender, age, height, weight, activityLevel, goalMode, weeklyRate, targetWeight } = values;

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

    // Weight log — same-day upsert. date_logged is a DATE; store the user's local
    // day directly so "same day" matches the user's timezone (req.todayTz).
    const [wlog] = await conn.query(
      'SELECT weight_id FROM weight_log WHERE user_id = ? AND date_logged = ? LIMIT 1',
      [req.user.user_id, req.todayTz]
    );
    if (wlog.length) {
      await conn.query('UPDATE weight_log SET weight = ? WHERE weight_id = ? AND user_id = ?', [
        weight,
        wlog[0].weight_id,
        req.user.user_id,
      ]);
    } else {
      await conn.query('INSERT INTO weight_log (user_id, weight, date_logged) VALUES (?, ?, ?)', [
        req.user.user_id,
        weight,
        req.todayTz,
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
