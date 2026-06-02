// Intake helpers — ports api/intake/_helpers.php and the macro functions from
// dashboard/handlers/functions.php (getMacroTotalsToday / getMacroGoalsFromCalorieGoal).
import { query } from '../db.js';

const ALLOWED_CATEGORIES = ['breakfast', 'lunch', 'dinner', 'snack'];

class ValidationError extends Error {
  constructor(message, status = 422) {
    super(message);
    this.status = status;
  }
}
export { ValidationError };

function toFloat(v, def = 0) {
  if (v === null || v === undefined || v === '') return def;
  return Number(v);
}

// Mirrors api_intake_validate().
export function validateIntake(data, requireId = false) {
  const id = data.intake_id ? Number(data.intake_id) : 0;
  const foodItem = (data.food_item ?? '').trim();
  const calories = data.calories ? parseInt(data.calories, 10) : 0;
  const category = (data.meal_category ?? '').toLowerCase().trim();
  const protein = toFloat(data.protein);
  const carbs = toFloat(data.carbs);
  const fat = toFloat(data.fat);

  if (requireId && id <= 0) throw new ValidationError('Missing intake ID.');
  if (foodItem === '' || calories <= 0 || category === '') throw new ValidationError('Please fill in all fields.');
  if (calories > 5000) throw new ValidationError('Calories must be a number between 1 and 5000.');
  if (!ALLOWED_CATEGORIES.includes(category)) throw new ValidationError('Invalid meal category.');

  for (const [name, value] of Object.entries({ protein, carbs, fat })) {
    if (value < 0 || value > 999) {
      throw new ValidationError(`${name[0].toUpperCase() + name.slice(1)} must be between 0 and 999 grams.`);
    }
  }

  return { id, food_item: foodItem, calories, meal_category: category, protein, carbs, fat };
}

// Mirrors api_intake_entry(). date_intake comes back as a string (dateStrings:true).
export function shapeEntry(row) {
  return {
    id: Number(row.intakeLog_id),
    food_item: row.food_item ?? '',
    calories: Number(row.calories),
    protein: Number(row.protein ?? 0),
    carbs: Number(row.carbs ?? 0),
    fat: Number(row.fat ?? 0),
    meal_category: row.meal_category ?? '',
    date_intake: row.date_intake ?? null,
    iso_date: row.date_intake ? new Date(row.date_intake.replace(' ', 'T') + '+07:00').toISOString() : null,
  };
}

// 30% protein / 45% carbs / 25% fat split — identical formula to PHP
// getMacroGoalsFromCalorieGoal(). Exported so the plan builder reuses it.
export function macroGoalsFromCalories(goal) {
  if (!goal || goal <= 0) return { protein: 0, carbs: 0, fat: 0 };
  return {
    protein: Math.round((goal * 0.3) / 4),
    carbs: Math.round((goal * 0.45) / 4),
    fat: Math.round((goal * 0.25) / 9),
  };
}

// Mirrors api_intake_fetch(): one entry scoped to its owner.
export async function fetchEntry(userId, intakeId) {
  const rows = await query(
    `SELECT intakeLog_id, food_item, calories, protein, carbs, fat, meal_category, date_intake
       FROM intakeLog WHERE user_id = ? AND intakeLog_id = ? LIMIT 1`,
    [userId, intakeId]
  );
  return rows[0] ?? null;
}

// Mirrors api_intake_daily_summary().
export async function dailySummary(userId) {
  const [{ total }] = await query(
    'SELECT COALESCE(SUM(calories), 0) AS total FROM intakeLog WHERE user_id = ? AND DATE(date_intake) = CURDATE()',
    [userId]
  );
  const totalCalories = Number(total);

  const goalRows = await query(
    'SELECT calorie_goal FROM userGoal WHERE user_id = ? ORDER BY date_set DESC LIMIT 1',
    [userId]
  );
  const goal = goalRows[0]?.calorie_goal ? Number(goalRows[0].calorie_goal) : null;

  let percentage = 0;
  if (goal && goal > 0) {
    percentage = Math.min(100, Math.max(0, Math.round((totalCalories / goal) * 100 * 100) / 100));
  }

  const [macroRow] = await query(
    `SELECT COALESCE(SUM(protein),0) AS protein, COALESCE(SUM(carbs),0) AS carbs, COALESCE(SUM(fat),0) AS fat
       FROM intakeLog WHERE user_id = ? AND DATE(date_intake) = CURDATE()`,
    [userId]
  );

  return {
    total_calories: totalCalories,
    calorie_goal: goal,
    progress_percentage: percentage,
    macros: {
      protein: Number(macroRow.protein),
      carbs: Number(macroRow.carbs),
      fat: Number(macroRow.fat),
    },
    macro_goals: macroGoalsFromCalories(goal),
  };
}
