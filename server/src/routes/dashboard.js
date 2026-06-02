// Dashboard routes — port api/dashboard/day.php and api/dashboard/summary.php.
import { Router } from 'express';
import { requireAuth } from '../middleware/auth.js';
import { macroGoalsFromCalories, shapeEntry } from '../lib/intake.js';
import {
  DEFAULT_XP_SUMMARY,
  todayVN,
  isValidDate,
  totalCaloriesForDate,
  macroTotalsForDate,
  calorieGoal,
  history7Days,
  mealCategoryTotals,
  intakeLogForDate,
  loggingStreak,
  physicalInfo,
  weightHistory,
  calorieAverage,
  bmiCategory,
  macroFocus,
} from '../lib/dashboard.js';

const router = Router();

const round2 = (n) => Math.round(n * 100) / 100;
const clampPct = (n) => Math.min(100, Math.max(0, n));

// GET /api/dashboard/day?date=YYYY-MM-DD — full day view (defaults to today).
router.get('/day', requireAuth, async (req, res, next) => {
  try {
    const userId = req.user.user_id;
    const today = todayVN();

    let selectedDate = today;
    if (req.query.date) {
      if (!/^\d{4}-\d{2}-\d{2}$/.test(req.query.date)) {
        return res.status(422).json({ ok: false, data: null, message: 'Invalid date format. Use YYYY-MM-DD.' });
      }
      if (!isValidDate(req.query.date)) {
        return res.status(422).json({ ok: false, data: null, message: 'Invalid date.' });
      }
      selectedDate = req.query.date;
    }
    if (selectedDate > today) {
      return res.status(422).json({ ok: false, data: null, message: 'Future dashboard dates are not available.' });
    }

    const totalCalories = await totalCaloriesForDate(userId, selectedDate);
    const goal = await calorieGoal(userId);
    const hasGoal = goal !== null && goal > 0;

    const macros = await macroTotalsForDate(userId, selectedDate);
    const macroGoals = macroGoalsFromCalories(goal);

    const progressPercentage = hasGoal ? clampPct(round2((totalCalories / goal) * 100)) : 0;
    const statusClass = hasGoal ? (totalCalories > goal ? 'overlimit' : 'ongoing') : 'unset';

    const history = await history7Days(userId, selectedDate);
    const mealCategories = await mealCategoryTotals(userId, selectedDate, 'lower');
    const entries = (await intakeLogForDate(userId, selectedDate)).map(shapeEntry);
    const streak = await loggingStreak(userId);
    const physical = await physicalInfo(userId);
    const weights = await weightHistory(userId);

    const latestWeight = weights.length ? weights[weights.length - 1].weight : 0;
    const actualWeight = latestWeight > 0 ? latestWeight : Number(physical.weight ?? 0);
    const actualHeight = Number(physical.height ?? 0);
    let bmi = null;
    if (actualWeight > 0 && actualHeight > 0) {
      const m = actualHeight / 100;
      bmi = Math.round((actualWeight / (m * m)) * 10) / 10;
    }

    let calorieRemaining = null;
    let calorieOverBy = null;
    let focusTone = 'neutral';
    let focusStatus = 'setup';
    if (hasGoal) {
      const diff = goal - totalCalories;
      if (diff >= 0) {
        calorieRemaining = diff;
        focusTone = 'good';
        focusStatus = 'active';
      } else {
        calorieOverBy = Math.abs(diff);
        focusTone = 'alert';
        focusStatus = 'adjust';
      }
    }

    const avg = calorieAverage(history.calories);

    res.json({
      ok: true,
      data: {
        selected_date: selectedDate,
        total_calories: totalCalories,
        calorie_goal: goal,
        progress_percentage: progressPercentage,
        status_class: statusClass,
        macros,
        macro_goals: macroGoals,
        history,
        average_calories: avg || null,
        meal_categories: mealCategories,
        entries,
        current_level: DEFAULT_XP_SUMMARY.current_level,
        total_xp: DEFAULT_XP_SUMMARY.total_xp,
        xp_into_level: DEFAULT_XP_SUMMARY.xp_into_level,
        xp_for_next: DEFAULT_XP_SUMMARY.xp_for_next,
        xp_progress_percentage: DEFAULT_XP_SUMMARY.progress_pct,
        streak,
        focus: {
          tone: focusTone,
          status: focusStatus,
          calorie_remaining: calorieRemaining,
          calorie_over_by: calorieOverBy,
          macro_focus: macroFocus(macros, macroGoals, hasGoal),
        },
        bmi: { value: bmi, category: bmi !== null ? bmiCategory(bmi) : null },
        physical,
        weight_history: weights,
      },
      message: null,
    });
  } catch (err) {
    next(err);
  }
});

// GET /api/dashboard/summary — today's snapshot (lighter than /day).
router.get('/summary', requireAuth, async (req, res, next) => {
  try {
    const userId = req.user.user_id;
    const today = todayVN();

    const totalCalories = await totalCaloriesForDate(userId, today);
    const goal = await calorieGoal(userId);
    const macros = await macroTotalsForDate(userId, today);
    const macroGoals = macroGoalsFromCalories(goal);
    const streak = await loggingStreak(userId);

    const progressPercentage = goal && goal > 0 ? clampPct(round2((totalCalories / goal) * 100)) : 0;

    const history = await history7Days(userId, today);
    // NOTE: summary.php uses Capitalized meal keys (vs lowercase in day.php) —
    // preserved for contract parity.
    const mealCategories = await mealCategoryTotals(userId, today, 'capital');

    res.json({
      ok: true,
      data: {
        total_calories: totalCalories,
        calorie_goal: goal,
        progress_percentage: progressPercentage,
        protein: macros.protein,
        carbs: macros.carbs,
        fat: macros.fat,
        macro_goals: macroGoals,
        current_level: DEFAULT_XP_SUMMARY.current_level,
        total_xp: DEFAULT_XP_SUMMARY.total_xp,
        xp_into_level: DEFAULT_XP_SUMMARY.xp_into_level,
        xp_for_next: DEFAULT_XP_SUMMARY.xp_for_next,
        xp_progress_percentage: DEFAULT_XP_SUMMARY.progress_pct,
        streak,
        history,
        meal_categories: mealCategories,
      },
      message: null,
    });
  } catch (err) {
    next(err);
  }
});

export default router;
