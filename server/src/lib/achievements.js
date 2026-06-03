// Achievements — read-only progress computed from existing data. Ports
// include/handlers/achievements.php. No persistence: every badge is derived
// from intakeLog, userStatus, user_xp, xp_event, friend_request and userGoal,
// so the first release needs no extra migration.
//
// Consumed by the Weekly Wrapped builder (lib/wrapped.js) for the top-badge
// slide + favorite-food record; later it can back a full achievements page.
import { query } from '../db.js';
import { getSummary } from './xp.js';
import { leaderboard } from './friends.js';

// First column of the first row as a number (COUNT/SUM scalars).
async function scalar(sql, params = []) {
  const rows = await query(sql, params);
  if (!rows.length) return 0;
  return Number(Object.values(rows[0])[0] ?? 0);
}

async function firstRow(sql, params = []) {
  const rows = await query(sql, params);
  return rows[0] ?? null;
}

// COUNT of intakeLog rows whose food_item LIKE any of the given terms.
async function foodCount(userId, terms) {
  if (!terms.length) return 0;
  const clauses = terms.map(() => 'food_item LIKE ?');
  const params = [userId, ...terms.map((t) => `%${t}%`)];
  return scalar(`SELECT COUNT(*) FROM intakeLog WHERE user_id = ? AND (${clauses.join(' OR ')})`, params);
}

function friendCount(userId) {
  return scalar(
    `SELECT COUNT(*) FROM friend_request
      WHERE status = 'accepted' AND (requester_id = ? OR addressee_id = ?)`,
    [userId, userId]
  );
}

function latestGoal(userId) {
  return scalar('SELECT calorie_goal FROM userGoal WHERE user_id = ? ORDER BY date_set DESC LIMIT 1', [userId]);
}

function balancedDays(userId, goal, shift = 0) {
  if (goal <= 0) return 0;
  return scalar(
    `SELECT COUNT(*) FROM (
        SELECT DATE(date_intake + INTERVAL ? MINUTE) AS d, SUM(calories) AS total_calories
        FROM intakeLog WHERE user_id = ?
        GROUP BY DATE(date_intake + INTERVAL ? MINUTE)
        HAVING total_calories BETWEEN ? AND ?
     ) balanced_days`,
    [shift, userId, shift, Math.floor(goal * 0.9), Math.ceil(goal * 1.1)]
  );
}

function fullPlateDays(userId, shift = 0) {
  return scalar(
    `SELECT COUNT(*) FROM (
        SELECT DATE(date_intake + INTERVAL ? MINUTE) AS d
        FROM intakeLog
        WHERE user_id = ? AND meal_category IN ('breakfast', 'lunch', 'dinner')
        GROUP BY DATE(date_intake + INTERVAL ? MINUTE)
        HAVING COUNT(DISTINCT meal_category) = 3
     ) full_plate_days`,
    [shift, userId, shift]
  );
}

// Number of times the user returned after a 3+ day logging gap.
async function comebackCount(userId, shift = 0) {
  const rows = await query(
    'SELECT DISTINCT DATE(date_intake + INTERVAL ? MINUTE) AS d FROM intakeLog WHERE user_id = ? ORDER BY d ASC',
    [shift, userId]
  );
  let count = 0;
  let prev = null;
  for (const r of rows) {
    const date = String(r.d);
    if (prev !== null) {
      const gap = Math.floor((Date.parse(date) - Date.parse(prev)) / 86400000);
      if (gap >= 3) count++;
    }
    prev = date;
  }
  return count;
}

// Tiered level from a value against ascending thresholds.
function achievementLevel(thresholds, value) {
  let level = 0;
  for (const t of thresholds) if (value >= t) level++;

  const maxLevel = thresholds.length;
  const isComplete = level >= maxLevel;
  const nextTarget = isComplete ? thresholds[maxLevel - 1] : thresholds[level];
  const prevTarget = level > 0 ? thresholds[level - 1] : 0;
  const range = Math.max(1, nextTarget - prevTarget);
  const pct = isComplete ? 100 : Math.min(100, Math.max(0, Math.round(((value - prevTarget) / range) * 100)));

  return { level, max_level: maxLevel, next_target: nextTarget, progress_pct: pct, is_complete: isComplete };
}

function build(id, name, description, icon, tone, value, unit, thresholds) {
  return { ...achievementLevel(thresholds, value), id, name, description, icon, tone, value, unit, thresholds };
}

// Full progress snapshot: { summary, records, achievements }. Mirrors the PHP
// payload shape so a later achievements page ports 1-1.
export async function achievementsProgress(userId, shift = 0) {
  const xp = await getSummary(userId);
  const goal = await latestGoal(userId);

  const status =
    (await firstRow('SELECT logging_streak, longest_logging_streak FROM userStatus WHERE user_id = ? LIMIT 1', [
      userId,
    ])) ?? {};

  const dailyLogger = await scalar(
    'SELECT COUNT(DISTINCT DATE(date_intake + INTERVAL ? MINUTE)) FROM intakeLog WHERE user_id = ?',
    [shift, userId]
  );
  const totalFoods = await scalar('SELECT COUNT(*) FROM intakeLog WHERE user_id = ?', [userId]);
  const fullPlate = await fullPlateDays(userId, shift);
  const balanced = await balancedDays(userId, goal, shift);
  const friends = await friendCount(userId);
  const comebacks = await comebackCount(userId, shift);

  const riceCount = await foodCount(userId, ['rice', 'cơm', 'com tam', 'com ga', 'com suon', 'com rang']);
  const phoCount = await foodCount(userId, ['pho', 'phở']);
  const banhMiCount = await foodCount(userId, ['banh mi', 'ban mi', 'bánh mì', 'bánh mỳ', 'banh my']);

  let weeklyRankOne = 0;
  if (friends > 0) {
    const top = await leaderboard(userId, 'weekly', 1);
    weeklyRankOne = top[0]?.is_current_user ? 1 : 0;
  }

  const longestStreak = Number(status.longest_logging_streak ?? 0);
  const currentStreak = Number(status.logging_streak ?? 0);

  const achievements = [
    build('first_bite', 'First Bite', 'Log your first food. The fork has entered the chat.', 'fa-utensils', 'primary', totalFoods, 'food logged', [1]),
    build('daily_logger', 'Daily Logger', 'Log food on different days.', 'fa-calendar-check', 'secondary', dailyLogger, 'logged days', [1, 3, 7, 14, 30]),
    build('streak_cooker', 'Streak Cooker', 'Keep your logging streak hot.', 'fa-fire', 'accent', longestStreak, 'best streak days', [3, 7, 14, 30, 60, 100]),
    build('full_plate', 'Full Plate', 'Log breakfast, lunch, and dinner in the same day.', 'fa-clipboard-check', 'success', fullPlate, 'full days', [1, 5, 15, 30]),
    build('balanced_bowl', 'Balanced Bowl', 'Finish a day within 10% of your calorie goal.', 'fa-bullseye', 'primary', balanced, 'balanced days', [1, 7, 30]),
    build('xp_grinder', 'XP Grinder', 'Earn XP across BitBalance.', 'fa-bolt', 'warning', Number(xp.total_xp), 'total XP', [100, 500, 1000, 5000, 10000, 30000]),
    build('rice_goddess', 'Rice Goddess', 'Log rice dishes until the bowl starts recognizing you.', 'fa-bowl-rice', 'warning', riceCount, 'rice logs', [5, 20, 50, 100]),
    build('pho_real', 'Pho Real', 'Log pho enough times that the broth becomes a personality trait.', 'fa-bowl-food', 'secondary', phoCount, 'pho logs', [3, 10, 25]),
    build('banh_mi_baron', 'Banh Mi Baron', 'Log banh mi in any spelling. Diacritics are optional; devotion is not.', 'fa-bread-slice', 'accent', banhMiCount, 'banh mi logs', [3, 10, 25, 50, 100]),
    build('friend_fuel', 'Friend Fuel', 'Build a crew for accountability and leaderboard chaos.', 'fa-user-friends', 'secondary', friends, 'friends', [1, 3, 10]),
    build('leaderboard_menace', 'Leaderboard Menace', 'Hold rank 1 on your current weekly friend leaderboard.', 'fa-trophy', 'warning', weeklyRankOne, 'rank 1 status', [1]),
    build('comeback_meal', 'Comeback Meal', 'Return after a 2+ day logging gap. No drama, just dinner.', 'fa-rotate-left', 'success', comebacks, 'comebacks', [1, 3, 10]),
  ];

  const unlocked = achievements.filter((a) => a.level > 0).length;

  const favorite = await firstRow(
    `SELECT food_item, COUNT(*) AS c FROM intakeLog WHERE user_id = ?
      GROUP BY food_item ORDER BY c DESC, food_item ASC LIMIT 1`,
    [userId]
  );

  const mostFoodsDay = await scalar(
    `SELECT COALESCE(MAX(day_count), 0) FROM (
        SELECT COUNT(*) AS day_count FROM intakeLog WHERE user_id = ? GROUP BY DATE(date_intake + INTERVAL ? MINUTE)
     ) food_days`,
    [userId, shift]
  );
  const mostXpDay = await scalar(
    `SELECT COALESCE(MAX(day_xp), 0) FROM (
        SELECT SUM(amount) AS day_xp FROM xp_event WHERE user_id = ? GROUP BY DATE(created_at + INTERVAL ? MINUTE)
     ) xp_days`,
    [userId, shift]
  );

  return {
    summary: {
      xp,
      unlocked,
      total_achievements: achievements.length,
      current_streak: currentStreak,
      longest_streak: longestStreak,
      total_foods: totalFoods,
      logged_days: dailyLogger,
      goal,
    },
    records: [
      { key: 'longest_streak', label: 'Longest streak', value: longestStreak, unit: 'days', icon: 'fa-fire' },
      { key: 'most_xp_day', label: 'Most XP in a day', value: mostXpDay, unit: 'XP', icon: 'fa-bolt' },
      { key: 'most_foods_day', label: 'Most foods in a day', value: mostFoodsDay, unit: 'foods', icon: 'fa-utensils' },
      {
        key: 'favorite_food',
        label: 'Favorite food',
        value: favorite?.food_item ?? 'Not enough data',
        unit: favorite?.c ? `${Number(favorite.c)} logs` : '',
        icon: 'fa-star',
      },
    ],
    achievements,
  };
}

// The highest-level unlocked badge (name + icon + tone) for the Wrapped slide.
// Falls back to First Bite, mirroring story_data.php's default.
export function topBadge(achievements) {
  const unlocked = achievements.filter((a) => a.level > 0).sort((a, b) => b.level - a.level);
  const top = unlocked[0];
  return {
    id: top?.id ?? 'first_bite',
    name: top?.name ?? 'First Bite',
    icon: top?.icon ?? 'fa-star',
    tone: top?.tone ?? 'primary',
  };
}

// --- Unlock-toast support ----------------------------------------------------
// The achievement levels at a point in time, keyed by id, stored on the session
// so a later log can tell what the user *gained* since they last saw their
// progress. Tiny ({id: level}), so it's cheap to keep in the session store.
function levelMap(achievements) {
  const m = {};
  for (const a of achievements) m[a.id] = a.level;
  return m;
}

// Overwrite the session baseline with the user's current achievement levels.
// Call this whenever the user has just *seen* their progress (the /progress
// page) so the next log diffs against what they already know about.
export function snapshotAchievementLevels(session, achievements) {
  if (session) session.ach_levels = levelMap(achievements);
}

// Ensure the session has a baseline WITHOUT a redundant recompute: no-op once
// seeded. Called before a log so the very first log of a session (which may
// unlock First Bite) still has a "before" to diff against. Only the first log
// of a session pays the extra achievementsProgress() cost.
export async function seedAchievementBaseline(userId, session, shift = 0) {
  if (!session || session.ach_levels) return;
  const { achievements } = await achievementsProgress(userId, shift);
  session.ach_levels = levelMap(achievements);
}

// Achievements that climbed a tier since the session baseline. Updates the
// baseline to "now" and returns the freshly-gained tiers (for the unlock toast).
// Defensive: with no baseline it just seeds and reports nothing, so a returning
// user is never spammed with everything they already earned.
export async function newlyUnlockedSince(userId, session, shift = 0) {
  const { achievements } = await achievementsProgress(userId, shift);
  const prev = session?.ach_levels;
  if (session) session.ach_levels = levelMap(achievements);
  if (!prev) return [];
  return achievements
    .filter((a) => a.level > (prev[a.id] ?? 0))
    .map((a) => ({ id: a.id, name: a.name, icon: a.icon, tone: a.tone, level: a.level, max_level: a.max_level }));
}
