// AI Coach user-context builder — ports include/handlers/ai_context.php.
// Produces a compact, human-readable snapshot (profile, goal, streak, today's
// intake, 7-day trend, recent weights) injected into the model system prompt.
// "Today" is resolved in Asia/Ho_Chi_Minh (todayVN) and passed as a bound param
// so it matches the legacy +07:00 behaviour regardless of the DB server tz.
import { query } from '../db.js';
import { todayVN } from './dates.js';

function num(v, digits = 1) {
  return Number(v || 0).toFixed(digits);
}

export async function buildUserContext(userId, shift = 0, today = todayVN()) {
  const lines = [];

  // ---- Basic profile ----
  const profileRows = await query(
    `SELECT u.user_name, u.first_name, u.last_name,
            p.age, p.gender, p.weight, p.height
       FROM user u
       LEFT JOIN userPhysicalInfo p ON p.user_id = u.user_id
      WHERE u.user_id = ?`,
    [userId]
  );
  const profile = profileRows[0];
  if (profile) {
    let name = `${profile.first_name ?? ''} ${profile.last_name ?? ''}`.trim();
    if (name === '') name = profile.user_name;
    lines.push(`Name: ${name}`);

    const bits = [];
    if (profile.age) bits.push(`${profile.age} years old`);
    if (profile.gender) bits.push(profile.gender);
    if (profile.weight) bits.push(`${profile.weight} kg`);
    if (profile.height) bits.push(`${profile.height} cm`);
    if (bits.length) lines.push('Profile: ' + bits.join(', '));
  }

  // ---- Current calorie + macro goal (latest userGoal row) ----
  const goalRows = await query(
    `SELECT calorie_goal, protein_goal, carbs_goal, fat_goal, source, date_set
       FROM userGoal
      WHERE user_id = ?
      ORDER BY date_set DESC
      LIMIT 1`,
    [userId]
  );
  let calGoal = 0;
  let pGoal = 0;
  let cGoal = 0;
  let fGoal = 0;
  if (goalRows[0]) {
    calGoal = Number(goalRows[0].calorie_goal) || 0;
    pGoal = Number(goalRows[0].protein_goal) || 0;
    cGoal = Number(goalRows[0].carbs_goal) || 0;
    fGoal = Number(goalRows[0].fat_goal) || 0;
    const setOn = String(goalRows[0].date_set).slice(0, 10);
    const macroBits = [];
    if (pGoal) macroBits.push(`P ${pGoal}g`);
    if (cGoal) macroBits.push(`C ${cGoal}g`);
    if (fGoal) macroBits.push(`F ${fGoal}g`);
    const macroStr = macroBits.length ? ` (target ${macroBits.join(' / ')})` : '';
    const src = goalRows[0].source && goalRows[0].source !== 'self' ? `, set by ${goalRows[0].source}` : '';
    lines.push(`Calorie goal: ${calGoal} kcal/day${macroStr} (set on ${setOn}${src})`);
  } else {
    lines.push('Calorie goal: not set yet');
  }

  // ---- Goal direction (user_plan_preferences) — the single biggest signal for
  // tailoring advice (lose/maintain/gain). Best-effort: never break the Coach if
  // the table is absent on an older DB.
  try {
    const planRows = await query(
      `SELECT goal_mode, weekly_rate, activity_level, target_weight
         FROM user_plan_preferences WHERE user_id = ?`,
      [userId]
    );
    if (planRows[0]) {
      const pl = planRows[0];
      const modeLabel =
        { lose: 'lose weight', maintain: 'maintain weight', gain: 'gain weight' }[pl.goal_mode] || pl.goal_mode;
      const pbits = [`aiming to ${modeLabel}`];
      if (pl.target_weight != null) pbits.push(`target ${pl.target_weight} kg`);
      if (pl.goal_mode !== 'maintain' && pl.weekly_rate != null) pbits.push(`~${num(pl.weekly_rate, 2)} kg/week`);
      if (pl.activity_level) pbits.push(`activity: ${String(pl.activity_level).replace(/_/g, ' ')}`);
      lines.push('Goal direction: ' + pbits.join(', '));
    }
  } catch {
    /* user_plan_preferences not present on this DB — skip */
  }

  // ---- Streak ----
  const statusRows = await query(
    `SELECT logging_streak, longest_logging_streak, last_logging_date
       FROM userStatus
      WHERE user_id = ?`,
    [userId]
  );
  if (statusRows[0]) {
    lines.push(
      `Logging streak: ${statusRows[0].logging_streak} days (longest: ${statusRows[0].longest_logging_streak})`
    );
  }

  // ---- Today's intake (full list) ----
  const todayItems = await query(
    `SELECT food_item, meal_category, calories, protein, carbs, fat
       FROM intakeLog
      WHERE user_id = ? AND DATE(date_intake + INTERVAL ? MINUTE) = ?
      ORDER BY date_intake ASC`,
    [userId, shift, today]
  );
  if (todayItems.length) {
    let cal = 0;
    let p = 0;
    let c = 0;
    let f = 0;
    lines.push(`\nToday (${today}) intake:`);
    for (const item of todayItems) {
      lines.push(
        `  - [${item.meal_category}] ${item.food_item}: ${Number(item.calories) | 0} kcal, ` +
          `P ${num(item.protein)}g, C ${num(item.carbs)}g, F ${num(item.fat)}g`
      );
      cal += Number(item.calories) || 0;
      p += Number(item.protein) || 0;
      c += Number(item.carbs) || 0;
      f += Number(item.fat) || 0;
    }
    lines.push(`  TOTAL today: ${cal | 0} kcal, P ${num(p)}g, C ${num(c)}g, F ${num(f)}g`);
    if (calGoal) {
      const remBits = [`${(calGoal - cal) | 0} kcal`];
      if (pGoal) remBits.push(`P ${num(pGoal - p, 0)}g`);
      if (cGoal) remBits.push(`C ${num(cGoal - c, 0)}g`);
      if (fGoal) remBits.push(`F ${num(fGoal - f, 0)}g`);
      lines.push(`  REMAINING vs goal today: ${remBits.join(', ')}`);
    }
  } else {
    lines.push(`\nToday (${today}) intake: nothing logged yet`);
    if (calGoal) lines.push(`  REMAINING vs goal today: full ${calGoal} kcal budget`);
  }

  // ---- Last 7 days totals (excluding today, for trend) ----
  const weekDays = await query(
    `SELECT DATE(date_intake + INTERVAL ? MINUTE) AS d,
            SUM(calories) AS cal, SUM(protein) AS p, SUM(carbs) AS c, SUM(fat) AS f
       FROM intakeLog
      WHERE user_id = ?
        AND DATE(date_intake + INTERVAL ? MINUTE) >= DATE_SUB(?, INTERVAL 7 DAY)
        AND DATE(date_intake + INTERVAL ? MINUTE) < ?
      GROUP BY DATE(date_intake + INTERVAL ? MINUTE)
      ORDER BY d DESC`,
    [shift, userId, shift, today, shift, today, shift]
  );
  if (weekDays.length) {
    lines.push('\nPast 7 days (daily totals):');
    for (const d of weekDays) {
      lines.push(
        `  - ${String(d.d).slice(0, 10)}: ${Number(d.cal) | 0} kcal ` +
          `(P ${num(d.p, 0)}g, C ${num(d.c, 0)}g, F ${num(d.f, 0)}g)`
      );
    }
  }

  // ---- Recent weight log (last 5) ----
  const weights = await query(
    `SELECT weight, date_logged
       FROM weight_log
      WHERE user_id = ?
      ORDER BY date_logged DESC
      LIMIT 5`,
    [userId]
  );
  if (weights.length) {
    lines.push('\nRecent weight log:');
    for (const w of weights) {
      lines.push(`  - ${String(w.date_logged).slice(0, 10)}: ${w.weight} kg`);
    }
    if (weights.length >= 2) {
      // weights are DESC (newest first); compare newest vs oldest of the window.
      const delta = Number(weights[0].weight) - Number(weights[weights.length - 1].weight);
      const dir = delta > 0.05 ? 'up' : delta < -0.05 ? 'down' : 'roughly flat';
      lines.push(`  Trend over these ${weights.length} entries: ${dir} ${num(Math.abs(delta), 1)} kg`);
    }
  }

  return lines.join('\n');
}
