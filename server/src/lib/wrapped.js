// BitBalance Wrapped — Spotify-Wrapped-style recap of the user's nutrition +
// gamification. Ports dashboard/handlers/story_data.php MINUS the Spotify slide
// (deferred to the Beats epic; payload keeps a `spotify: null` slot for it).
//
// Fork decisions (see docs/wrapped-vue-port.md): the recap is ON-DEMAND over the
// last 7/30 days, cached once per ISO week per lang (matching the PHP runtime)
// in weekly_wrapped_cache.
import { query } from '../db.js';
import { chatCompletion } from './aiProvider.js';
import { achievementsProgress, topBadge } from './achievements.js';
import { leaderboard } from './friends.js';
import { todayVN, addDays } from './dates.js';

// Bump when the payload shape or prompt changes so stale caches regenerate.
const CACHE_VERSION = 3;

function isoWeekYear(dateStr) {
  const d = new Date(`${dateStr}T00:00:00Z`);
  d.setUTCDate(d.getUTCDate() + 4 - (d.getUTCDay() || 7));
  const yearStart = new Date(Date.UTC(d.getUTCFullYear(), 0, 1));
  const week = Math.ceil(((d - yearStart) / 86400000 + 1) / 7);
  return `${String(week).padStart(2, '0')}-${d.getUTCFullYear()}`;
}

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
async function foodListString(userId, lang, shift = 0, cutoff) {
  const rows = await query(
    `SELECT food_item, COUNT(*) AS c FROM intakeLog
      WHERE user_id = ? AND DATE(date_intake + INTERVAL ? MINUTE) >= ?
      GROUP BY food_item ORDER BY c DESC, food_item ASC LIMIT 15`,
    [userId, shift, cutoff]
  );
  if (!rows.length) return lang === 'vi' ? 'Chưa có món ăn nào được ghi nhận' : 'No foods logged yet';
  const unit = lang === 'vi' ? 'lần' : 'times';
  return rows.map((r) => `${r.food_item}: ${Number(r.c)} ${unit}`).join(', ');
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

function buildPrompt(username, stats, favoriteFood, topBadgeName, foodList, lang) {
  if (lang === 'vi') {
    const system =
      'Bạn là trợ lý AI kể chuyện thông minh, dí dỏm của ứng dụng theo dõi calo BitBalance. ' +
      'Chỉ trả về một đối tượng JSON thuần, không markdown, không wrapper. ' +
      'Không body shaming, không phán xét cân nặng/calo; giữ giọng vui, tích cực, trẻ trung kiểu Việt Nam.';

    const user =
      `Dữ liệu dinh dưỡng và thói quen ăn uống của người dùng "${username}":\n` +
      `- Cấp độ hiện tại: ${stats.level}\n` +
      `- Điểm kinh nghiệm hiện có: ${stats.total_xp} XP\n` +
      `- Tổng số món ăn đã ghi nhận: ${stats.total_foods} món\n` +
      `- Chuỗi ghi bữa liên tục: ${stats.streak} ngày\n` +
      `- Xếp hạng bạn bè: ${stats.leaderboard_rank}\n` +
      `- Món ăn yêu thích nhất: ${favoriteFood}\n` +
      `- Huy hiệu nổi bật tuần này: ${topBadgeName}\n` +
      `- Danh sách món ăn đã log trong 30 ngày qua: [${foodList}]\n\n` +
      'Nhiệm vụ:\n' +
      '1. Phân tích danh sách món ăn để đặt một "Hình mẫu ẩm thực" thật hài hước ' +
      '(ví dụ: "Chiến binh ức gà nửa mùa", "Đại sứ bánh mì kẹp", "Chúa tể hảo ngọt trà sữa").\n' +
      '2. Viết 1 dòng ngắn giải thích lý do hài hước cho hình mẫu này.\n' +
      '3. Viết caption ngắn dưới 20 từ bằng tiếng Việt cho các slide:\n' +
      '   - slide1_aura: Hào quang tuần qua\n' +
      '   - slide2_topfood: Món ăn hoặc huy hiệu nổi bật nhất\n' +
      '   - slide3_streak: Chuỗi streak hoặc kỷ luật rực lửa\n' +
      '   - slide4_leaderboard: Cạnh tranh xếp hạng bạn bè đầy tính tấu hài\n\n' +
      'Trả về đúng JSON shape này:\n' +
      '{ "diet_archetype": "", "archetype_desc": "", "slide1_aura": "", "slide2_topfood": "", ' +
      '"slide3_streak": "", "slide4_leaderboard": "" }';

    return { system, history: [{ role: 'user', content: user }] };
  }

  const system =
    "You are the witty, smart AI Storyteller of the BitBalance calorie tracking app. " +
    'Return ONLY a raw JSON object (no markdown, no ``` wrappers). ' +
    'RULES: no body shaming, no judging weight/calories; keep it playful and positive.';

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

function fallbackCaptions(stats, topBadgeName, lang) {
  if (lang === 'vi') {
    return {
      diet_archetype: 'Tín Đồ Ăn Uống Độc Lập',
      archetype_desc: 'Ghi calo đều đặn và kiên trì chinh phục mọi đỉnh cao ẩm thực.',
      slide1_aura: 'Hào quang kỷ luật đang tỏa sáng khắp căn bếp của bạn tuần này.',
      slide2_topfood: `Huy hiệu ${topBadgeName} đã sẵn sàng tỏa sáng trên story của bạn.`,
      slide3_streak: `Giữ lửa cực tốt với chuỗi ${stats.streak} ngày ghi bữa.`,
      slide4_leaderboard: `Đứng hạng ${stats.leaderboard_rank} trên bảng xếp hạng. Rất đáng gờm.`,
    };
  }
  return {
    diet_archetype: 'Dedicated Food Tracker',
    archetype_desc: 'Consistently logging meals and building habits every single day!',
    slide1_aura: 'Your nutrition aura is glowing brightly with discipline this week!',
    slide2_topfood: `Your top badge ${topBadgeName} is ready to shine on your story.`,
    slide3_streak: `Keeping the fire hot with an awesome ${stats.streak}-day streak!`,
    slide4_leaderboard: `Secured rank ${stats.leaderboard_rank} on the leaderboard. A formidable position!`,
  };
}

const VI_BADGE_NAMES = {
  first_bite: 'Miếng đầu tiên',
  daily_logger: 'Ghi đều mỗi ngày',
  streak_cooker: 'Giữ lửa chuỗi ngày',
  full_plate: 'Mâm đầy đủ',
  balanced_bowl: 'Tô cân bằng',
  xp_grinder: 'Thợ cày XP',
  rice_goddess: 'Nữ thần cơm',
  pho_real: 'Phở thứ thiệt',
  banh_mi_baron: 'Trùm bánh mì',
  friend_fuel: 'Nhiên liệu bạn bè',
  leaderboard_menace: 'Khắc tinh bảng xếp hạng',
  comeback_meal: 'Bữa trở lại',
};

function localizeBadge(badge, lang) {
  if (lang !== 'vi') return badge;
  return { ...badge, name: VI_BADGE_NAMES[badge.id] || badge.name };
}

// Build (or serve cached) the Wrapped payload for a user.
// Returns the payload merged with { cached: boolean }.
export async function buildWrapped(userId, username, lang = 'en', shift = 0, today = todayVN()) {
  lang = lang === 'vi' ? 'vi' : 'en';
  const periodKey = isoWeekYear(today); // PHP-compatible "W-Y" cache key (user-local)

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
  const { summary, records, achievements } = await achievementsProgress(userId, shift);
  const favoriteRecord = records.find((r) => r.key === 'favorite_food');
  const favoriteFood = favoriteRecord?.value || (lang === 'vi' ? 'Chưa đủ dữ liệu' : 'Not enough data');
  const badge = localizeBadge(topBadge(achievements), lang);

  // leaderboard() always includes the caller; no friends → they're rank 1. It
  // returns ONLY the caller + accepted friends, XP/level/streak (never calories).
  const board = await leaderboard(userId, 'weekly', 500);
  const me = board.find((r) => r.is_current_user);
  const leaderboardRank = me ? `#${me.rank}` : '#1';

  // Mini friend leaderboard for the Wrapped slide: top 3, plus the user's own row
  // if they sit outside it. friends_count drives the solo fallback on the client.
  // PRIVACY: the Wrapped story is shareable. When image EXPORT ships (P3),
  // anonymize/exclude other people's name+avatar before rendering into a shared
  // frame — a shared card must not expose friends' identities without consent.
  const lbRow = (r) => ({
    rank: r.rank,
    user_name: r.user_name,
    profile_image: r.profile_image,
    weekly_xp: r.weekly_xp,
    is_current_user: !!r.is_current_user,
  });
  const friendsTop = board.slice(0, 3).map(lbRow);
  if (me && me.rank > 3) friendsTop.push(lbRow(me));
  const friendsCount = Math.max(0, board.length - 1);

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
  const foodList = await foodListString(userId, lang, shift, addDays(today, -30));
  const { system, history } = buildPrompt(username, stats, favoriteFood, badge.name, foodList, lang);

  let captions = null;
  const ai = await chatCompletion({ system, history });
  if (ai.ok) captions = parseAiJson(ai.text);
  const aiOk = captions !== null && typeof captions.diet_archetype === 'string' && captions.diet_archetype !== '';
  if (!aiOk) captions = fallbackCaptions(stats, badge.name, lang);

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
      friends_count: friendsCount,
    },
    friends_top: friendsTop,
    badge,
    spotify: null,
    ai: aiOk,
    ...captions,
  };

  // 5. Cache + return.
  await writeCache(userId, periodKey, lang, payload);
  return { ...payload, cached: false };
}
