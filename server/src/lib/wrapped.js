// BitBalance Wrapped — Spotify-Wrapped-style recap of the user's nutrition +
// gamification. Ports dashboard/handlers/story_data.php MINUS the Spotify slide
// (deferred to the Beats epic; payload keeps a `spotify: null` slot for it).
//
// Fork decisions (see docs/wrapped-vue-port.md): the recap is ON-DEMAND over the
// last 7/30 days, cached once per Bangkok-day per lang (one AI call/day/user) in
// weekly_wrapped_cache — no ISO-week freeze. v1 narrates in English only; the
// cache key carries lang so 'vi' can be added later.
import { query } from '../db.js';
import { chatCompletion } from './aiProvider.js';
import { achievementsProgress, topBadge } from './achievements.js';
import { leaderboard } from './friends.js';
import { todayVN } from './dates.js';

// Bump when the payload shape or prompt changes so stale caches regenerate.
const CACHE_VERSION = 1;

function readCache(userId, periodKey, lang) {
  return query(
    'SELECT generated_json FROM weekly_wrapped_cache WHERE user_id = ? AND week_year = ? AND lang = ? LIMIT 1',
    [userId, periodKey, lang]
  );
}

function writeCache(userId, periodKey, lang, json) {
  return query(
    `INSERT INTO weekly_wrapped_cache (user_id, week_year, lang, generated_json)
     VALUES (?, ?, ?, ?)
     ON DUPLICATE KEY UPDATE generated_json = VALUES(generated_json), created_at = CURRENT_TIMESTAMP`,
    [userId, periodKey, lang, JSON.stringify(json)]
  );
}

// Top 15 most-logged foods over the last 30 days → "name: n times, ..." for the prompt.
async function foodListString(userId) {
  const rows = await query(
    `SELECT food_item, COUNT(*) AS c FROM intakeLog
      WHERE user_id = ? AND date_intake >= DATE_SUB(NOW(), INTERVAL 30 DAY)
      GROUP BY food_item ORDER BY c DESC, food_item ASC LIMIT 15`,
    [userId]
  );
  if (!rows.length) return 'No foods logged yet';
  return rows.map((r) => `${r.food_item}: ${Number(r.c)} times`).join(', ');
}

// Extract the first JSON object from an LLM reply (tolerates ``` fences / prose).
function parseAiJson(text) {
  if (!text) return null;
  const clean = text.replace(/```json/gi, '').replace(/```/g, '').trim();
  try {
    const o = JSON.parse(clean);
    if (o && typeof o === 'object') return o;
  } catch {
    /* fall through to brace extraction */
  }
  const m = clean.match(/\{[\s\S]*\}/);
  if (m) {
    try {
      const o = JSON.parse(m[0]);
      if (o && typeof o === 'object') return o;
    } catch {
      /* give up */
    }
  }
  return null;
}

function buildPrompt(username, stats, favoriteFood, topBadgeName, foodList) {
  const system =
    "You are the witty, smart AI Storyteller of the BitBalance calorie tracking app. " +
    'Return ONLY a raw JSON object (no markdown, no ``` wrappers). ' +
    'RULES: no body shaming, no judging weight/calories — keep it playful and positive.';

  const user =
    `Nutrition and habits for user "${username}":\n` +
    `- Current Level: ${stats.level}\n` +
    `- Total XP earned: ${stats.total_xp} XP\n` +
    `- Total foods logged: ${stats.total_foods}\n` +
    `- Logging streak: ${stats.streak} days\n` +
    `- Friend leaderboard rank: ${stats.leaderboard_rank}\n` +
    `- Favorite logged food: ${favoriteFood}\n` +
    `- Current Top Badge: ${topBadgeName}\n` +
    `- Foods logged in the last 30 days: [${foodList}]\n\n` +
    'Your tasks:\n' +
    '1. Analyze the 30-day food list and give a highly creative, humorous "Dietary Archetype" ' +
    '(e.g. "Part-Time Chicken Breast Warrior", "Yin-Yang Sweet Tooth Master").\n' +
    '2. Write a 1-sentence funny explanation for it (point out any playful contrast in the foods).\n' +
    '3. Write very short, witty captions (under 20 words each) for the Story slides:\n' +
    '   - slide1_aura (their weekly aura and vibe)\n' +
    '   - slide2_topfood (highlight their favorite food or top badge)\n' +
    '   - slide3_streak (fuel the discipline flame / streak)\n' +
    '   - slide4_leaderboard (playful teasing about friends ranking)\n\n' +
    'Return exactly this JSON shape:\n' +
    '{ "diet_archetype": "", "archetype_desc": "", "slide1_aura": "", "slide2_topfood": "", ' +
    '"slide3_streak": "", "slide4_leaderboard": "" }';

  return { system, history: [{ role: 'user', content: user }] };
}

function fallbackCaptions(stats, topBadgeName) {
  return {
    diet_archetype: 'Dedicated Food Tracker',
    archetype_desc: 'Consistently logging meals and building habits every single day!',
    slide1_aura: 'Your nutrition aura is glowing brightly with discipline this week!',
    slide2_topfood: `Your top badge ${topBadgeName} is ready to shine on your story.`,
    slide3_streak: `Keeping the fire hot with an awesome ${stats.streak}-day streak!`,
    slide4_leaderboard: `Secured rank ${stats.leaderboard_rank} on the leaderboard. A formidable position!`,
  };
}

// Build (or serve cached) the Wrapped payload for a user.
// Returns the payload merged with { cached: boolean }.
export async function buildWrapped(userId, username, lang = 'en') {
  lang = lang === 'vi' ? 'vi' : 'en';
  const periodKey = todayVN(); // 'YYYY-MM-DD' (Bangkok) — recap regenerates once/day

  // 1. Cache hit (same day + lang + payload version) → serve as-is.
  const cachedRows = await readCache(userId, periodKey, lang);
  if (cachedRows.length) {
    try {
      const data = JSON.parse(cachedRows[0].generated_json);
      if (data && Number(data._v) === CACHE_VERSION) return { ...data, cached: true };
    } catch {
      /* corrupt cache → regenerate */
    }
  }

  // 2. Gather stats from the already-ported libs.
  const { summary, records, achievements } = await achievementsProgress(userId);
  const favoriteRecord = records.find((r) => r.key === 'favorite_food');
  const favoriteFood = favoriteRecord?.value || 'Not enough data';
  const badge = topBadge(achievements);

  // leaderboard() always includes the caller; no friends → they're rank 1.
  const board = await leaderboard(userId, 'weekly', 500);
  const me = board.find((r) => r.is_current_user);
  const leaderboardRank = me ? `#${me.rank}` : '#1';

  const stats = {
    total_foods: summary.total_foods,
    logged_days: summary.logged_days,
    streak: summary.current_streak,
    leaderboard_rank: leaderboardRank,
    favorite_food: favoriteFood,
    level: summary.xp.current_level,
    total_xp: summary.xp.total_xp,
  };

  // 3. One AI call for archetype + slide captions, with a deterministic fallback.
  const foodList = await foodListString(userId);
  const { system, history } = buildPrompt(username, stats, favoriteFood, badge.name, foodList);

  let captions = null;
  const ai = await chatCompletion({ system, history });
  if (ai.ok) captions = parseAiJson(ai.text);
  const aiOk = captions !== null && typeof captions.diet_archetype === 'string' && captions.diet_archetype !== '';
  if (!aiOk) captions = fallbackCaptions(stats, badge.name);

  // 4. Assemble payload (slide 6 Spotify deferred → null).
  const payload = {
    _v: CACHE_VERSION,
    user: {
      username,
      level: summary.xp.current_level,
      progress_pct: summary.xp.progress_pct,
      total_xp: summary.xp.total_xp,
    },
    stats: {
      total_foods: stats.total_foods,
      logged_days: stats.logged_days,
      streak: stats.streak,
      leaderboard_rank: stats.leaderboard_rank,
      favorite_food: stats.favorite_food,
    },
    badge,
    spotify: null,
    ai: aiOk,
    ...captions,
  };

  // 5. Cache + return.
  await writeCache(userId, periodKey, lang, payload);
  return { ...payload, cached: false };
}
