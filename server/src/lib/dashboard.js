// Dashboard read helpers — port the query/compute logic from
// api/dashboard/day.php and api/dashboard/summary.php (and the functions they
// pull from dashboard/handlers/functions.php).
//
// XP is NOT ported yet (include/handlers/xp.php). Both endpoints already have a
// fallback XP block in PHP; we return that same default shape so the client
// contract is stable until XP lands. See MIGRATION.md.
import { query } from '../db.js';
import { macroGoalsFromCalories } from './intake.js';
import { addDays, weekdayLabel } from './dates.js';

// XP fallback shape, mirroring the catch block in the PHP dashboard endpoints.
// Used only when the XP subsystem throws; otherwise real XP data is returned.
export const DEFAULT_XP_SUMMARY = {
  total_xp: 0,
  current_level: 1,
  xp_into_level: 0,
  xp_for_next: 100,
  progress_pct: 0,
};

export async function totalCaloriesForDate(userId, date, shift = 0) {
  const rows = await query(
    'SELECT COALESCE(SUM(calories), 0) AS total FROM intakeLog WHERE user_id = ? AND DATE(date_intake + INTERVAL ? MINUTE) = ?',
    [userId, shift, date]
  );
  return Number(rows[0].total);
}

export async function macroTotalsForDate(userId, date, shift = 0) {
  const rows = await query(
    `SELECT COALESCE(SUM(protein),0) AS protein, COALESCE(SUM(carbs),0) AS carbs, COALESCE(SUM(fat),0) AS fat
       FROM intakeLog WHERE user_id = ? AND DATE(date_intake + INTERVAL ? MINUTE) = ?`,
    [userId, shift, date]
  );
  return { protein: Number(rows[0].protein), carbs: Number(rows[0].carbs), fat: Number(rows[0].fat) };
}

export async function calorieGoal(userId) {
  const rows = await query(
    'SELECT calorie_goal FROM userGoal WHERE user_id = ? ORDER BY date_set DESC LIMIT 1',
    [userId]
  );
  return rows[0]?.calorie_goal ? Number(rows[0].calorie_goal) : null;
}

// 7-day rolling window ending on `endDate` (inclusive), oldest first — matches
// the i=6..0 loop in both PHP endpoints.
export async function history7Days(userId, endDate, shift = 0) {
  const history = { labels: [], calories: [], protein: [], carbs: [], fat: [] };
  for (let i = 6; i >= 0; i--) {
    const date = addDays(endDate, -i);
    const [row] = await query(
      `SELECT COALESCE(SUM(calories),0) AS total, COALESCE(SUM(protein),0) AS protein,
              COALESCE(SUM(carbs),0) AS carbs, COALESCE(SUM(fat),0) AS fat
         FROM intakeLog WHERE user_id = ? AND DATE(date_intake + INTERVAL ? MINUTE) = ?`,
      [userId, shift, date]
    );
    history.labels.push(weekdayLabel(date));
    history.calories.push(Number(row.total));
    history.protein.push(Number(row.protein));
    history.carbs.push(Number(row.carbs));
    history.fat.push(Number(row.fat));
  }
  return history;
}

// Per-meal calorie totals for one date. `keyCase` controls the output keys to
// mirror the (inconsistent!) legacy endpoints: day.php uses lowercase keys,
// summary.php uses capitalized keys.
export async function mealCategoryTotals(userId, date, keyCase = 'lower', shift = 0) {
  const categories = ['Breakfast', 'Lunch', 'Dinner', 'Snack'];
  const out = {};
  for (const category of categories) {
    const rows = await query(
      'SELECT COALESCE(SUM(calories),0) AS total FROM intakeLog WHERE user_id = ? AND meal_category = ? AND DATE(date_intake + INTERVAL ? MINUTE) = ?',
      [userId, category, shift, date]
    );
    const key = keyCase === 'lower' ? category.toLowerCase() : category;
    out[key] = Number(rows[0].total);
  }
  return out;
}

export async function intakeLogForDate(userId, date, shift = 0) {
  return query(
    `SELECT intakeLog_id, food_item, meal_category, calories, protein, carbs, fat, image_path, date_intake
       FROM intakeLog WHERE user_id = ? AND DATE(date_intake + INTERVAL ? MINUTE) = ? ORDER BY date_intake DESC`,
    [userId, shift, date]
  );
}

export async function loggingStreak(userId) {
  const rows = await query(
    'SELECT logging_streak, longest_logging_streak, streak_freezes, broken_streak FROM userStatus WHERE user_id = ?',
    [userId]
  );
  const r = rows[0];
  return {
    current: Number(r?.logging_streak ?? 0),
    longest: Number(r?.longest_logging_streak ?? 0),
    freezes: Number(r?.streak_freezes ?? 0),
    broken: Number(r?.broken_streak ?? 0),
  };
}

export async function physicalInfo(userId) {
  const rows = await query('SELECT age, gender, weight, height FROM userPhysicalInfo WHERE user_id = ?', [userId]);
  const r = rows[0];
  return {
    age: r?.age != null ? Number(r.age) : null,
    gender: r?.gender ?? null,
    weight: r?.weight != null ? Number(r.weight) : null,
    height: r?.height != null ? Number(r.height) : null,
  };
}

export async function weightHistory(userId) {
  const rows = await query(
    'SELECT weight_id, weight, date_logged FROM weight_log WHERE user_id = ? ORDER BY date_logged DESC, weight_id DESC LIMIT 7',
    [userId]
  );
  return rows.reverse().map((row) => {
    const dateLogged = String(row.date_logged ?? '');
    return {
      id: Number(row.weight_id ?? 0),
      weight: Number(row.weight ?? 0),
      date_logged: dateLogged,
      label: dateLogged ? `${dateLogged.slice(8, 10)}/${dateLogged.slice(5, 7)}` : '',
    };
  });
}

// Average of non-zero calorie days (mirrors calculateCalorieAverage).
export function calorieAverage(arr) {
  const nonZero = arr.filter((v) => v > 0);
  if (!nonZero.length) return 0;
  return Math.round(nonZero.reduce((a, b) => a + b, 0) / nonZero.length);
}

export function bmiCategory(bmi) {
  if (bmi <= 0) return null;
  if (bmi < 18.5) return 'Underweight';
  if (bmi < 25.0) return 'Normal';
  if (bmi < 30.0) return 'Overweight';
  return 'Obese';
}

// Picks the macro furthest (proportionally) from its goal — mirrors api_dashboard_macro_focus.
export function macroFocus(macroTotals, macroGoals, hasCalorieGoal) {
  const defs = {
    protein: { label: 'Protein', icon: 'drumstick' },
    carbs: { label: 'Carbs', icon: 'bread-slice' },
    fat: { label: 'Fat', icon: 'cheese' },
  };

  let focusKey = null;
  let focusGap = 0;
  let focusRatio = -1;

  for (const key of Object.keys(defs)) {
    const goal = Number(macroGoals[key] ?? 0);
    const current = Number(macroTotals[key] ?? 0);
    const gap = Math.max(0, goal - current);
    const ratio = goal > 0 ? gap / goal : 0;
    if (gap > 0 && ratio > focusRatio) {
      focusKey = key;
      focusGap = gap;
      focusRatio = ratio;
    }
  }

  if (focusKey !== null) {
    return { key: focusKey, label: defs[focusKey].label, gap: Math.round(focusGap * 10) / 10, icon: defs[focusKey].icon };
  }
  if (hasCalorieGoal) {
    return { key: 'complete', label: 'On track', gap: 0, icon: 'checkmark-circle' };
  }
  return { key: 'neutral', label: 'Set a goal', gap: 0, icon: 'target' };
}
