// Goal-planner math — ports dashboard/handlers/goal_plan.php (the parts the
// onboarding endpoint needs). BMR/TDEE → calorie goal, macros, hydration, and
// the user_plan_preferences upsert.
import { query } from '../db.js';
import { macroGoalsFromCalories } from './intake.js';

export const ACTIVITY_FACTORS = {
  sedentary: 1.2,
  lightly_active: 1.375,
  moderately_active: 1.55,
  very_active: 1.725,
  extra_active: 1.9,
};

export const GOAL_MODES = ['lose', 'maintain', 'gain'];

const GOAL_ADJUSTMENTS = { lose: -350, maintain: 0, gain: 300 };

function weeklyRateFromGoalMode(goalMode) {
  const daily = Math.abs(GOAL_ADJUSTMENTS[goalMode] ?? 0);
  if (daily <= 0) return 0;
  return Math.round(((daily * 7) / 7700) * 100) / 100;
}

function dailyAdjustmentFromWeeklyRate(goalMode, weeklyRate) {
  if (goalMode === 'maintain') return 0;
  const rate = Math.max(0, Math.min(1.5, weeklyRate));
  const daily = Math.round((rate * 7700) / 7);
  return goalMode === 'lose' ? -daily : daily;
}

// Mifflin-St Jeor, with the same gender offsets PHP uses.
function calculateBmr(age, gender, weightKg, heightCm) {
  const base = 10 * weightKg + 6.25 * heightCm - 5 * age;
  if (gender === 'female') return base - 161;
  if (gender === 'other') return base - 78;
  return base + 5;
}

function calculateTdee(bmr, activityLevel) {
  const factor = ACTIVITY_FACTORS[activityLevel] ?? ACTIVITY_FACTORS.moderately_active;
  return bmr * factor;
}

const clampGoal = (goal) => Math.max(800, Math.min(10000, goal));

// Mirrors plan_build_personal_plan().
export function buildPersonalPlan(age, gender, weightKg, heightCm, activityLevel, goalMode, weeklyRate = null) {
  if (!GOAL_MODES.includes(goalMode)) goalMode = 'maintain';
  if (!ACTIVITY_FACTORS[activityLevel]) activityLevel = 'moderately_active';

  const bmr = calculateBmr(age, gender, weightKg, heightCm);
  const tdee = calculateTdee(bmr, activityLevel);
  if (weeklyRate === null) weeklyRate = weeklyRateFromGoalMode(goalMode);
  const dailyAdjustment = dailyAdjustmentFromWeeklyRate(goalMode, weeklyRate);
  const calorieGoal = clampGoal(Math.round(tdee + dailyAdjustment));

  return {
    bmr,
    tdee,
    daily_adjustment: dailyAdjustment,
    calorie_goal: calorieGoal,
    macros: macroGoalsFromCalories(calorieGoal),
    hydration_ml: Math.round((weightKg * 35) / 250) * 250,
  };
}

// Mirrors plan_save_preferences() (INSERT ... ON DUPLICATE KEY UPDATE).
export async function savePreferences(userId, prefs) {
  let goalMode = GOAL_MODES.includes(prefs.goal_mode) ? prefs.goal_mode : 'lose';
  let activityLevel = ACTIVITY_FACTORS[prefs.activity_level] ? prefs.activity_level : 'moderately_active';

  let weeklyRate = Number.isFinite(Number(prefs.weekly_rate)) ? Number(prefs.weekly_rate) : 0.25;
  weeklyRate = Math.max(0, Math.min(1.5, weeklyRate));
  if (goalMode === 'maintain') weeklyRate = 0;

  let targetWeight = null;
  const tw = Number(prefs.target_weight);
  if (prefs.target_weight !== '' && prefs.target_weight != null && Number.isFinite(tw) && tw > 0 && tw <= 500) {
    targetWeight = tw;
  }

  await query(
    `INSERT INTO user_plan_preferences (user_id, goal_mode, weekly_rate, activity_level, target_weight)
     VALUES (?, ?, ?, ?, ?)
     ON DUPLICATE KEY UPDATE
       goal_mode = VALUES(goal_mode),
       weekly_rate = VALUES(weekly_rate),
       activity_level = VALUES(activity_level),
       target_weight = VALUES(target_weight)`,
    [userId, goalMode, weeklyRate, activityLevel, targetWeight]
  );
}
