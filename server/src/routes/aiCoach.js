// AI Coach routes — port api/ai-coach/{conversations,messages,send,delete}.php.
//   GET  /api/ai-coach/conversations
//   GET  /api/ai-coach/messages?conversation_id=
//   POST /api/ai-coach/send
//   POST /api/ai-coach/delete
// Text-only in this version: the legacy image upload (multipart → disk → inline
// vision) is NOT ported yet — tracked in MIGRATION.md.
import { Router } from 'express';
import { query } from '../db.js';
import { requireAuth } from '../middleware/auth.js';
import { todayVN } from '../lib/dates.js';
import { buildUserContext } from '../lib/aiContext.js';
import { chatCompletion } from '../lib/aiProvider.js';
import {
  buildClientTimeInfo,
  systemInstruction,
  extractFoodLogBlock,
  packFoodLogSuggestions,
  unpackFoodLogSuggestions,
  fetchOwnedConversation,
  formatMessage,
  formatConversation,
} from '../lib/aiCoach.js';

const router = Router();

const DAILY_LIMIT = Number(process.env.AI_COACH_DAILY_LIMIT || 20);
const HISTORY_TURNS = Number(process.env.AI_COACH_HISTORY_TURNS || 10);

router.get('/conversations', requireAuth, async (req, res, next) => {
  try {
    const rows = await query(
      `SELECT conversation_id, title, created_at, updated_at
         FROM ai_conversation
        WHERE user_id = ?
        ORDER BY updated_at DESC
        LIMIT 100`,
      [req.user.user_id]
    );
    res.json({ ok: true, data: rows.map(formatConversation), message: null });
  } catch (err) {
    next(err);
  }
});

router.get('/messages', requireAuth, async (req, res, next) => {
  try {
    const conversationId = parseInt(req.query.conversation_id ?? 0, 10) || 0;
    if (conversationId <= 0) {
      return res.status(400).json({ ok: false, data: null, message: 'Missing conversation_id.' });
    }
    const conv = await fetchOwnedConversation(req.user.user_id, conversationId);
    if (!conv) {
      return res.status(404).json({ ok: false, data: null, message: 'Conversation not found.' });
    }
    // Bounded read: a conversation can grow without limit, so cap the load at the
    // most recent 500 messages (DESC + LIMIT), then restore chronological order.
    const rows = (
      await query(
        `SELECT message_id, role, content, image_path, created_at
           FROM ai_message
          WHERE conversation_id = ?
          ORDER BY created_at DESC, message_id DESC
          LIMIT 500`,
        [conversationId]
      )
    ).reverse();
    res.json({
      ok: true,
      data: { conversation: formatConversation(conv), messages: rows.map(formatMessage) },
      message: null,
    });
  } catch (err) {
    next(err);
  }
});

router.post('/send', requireAuth, async (req, res, next) => {
  const userId = req.user.user_id;
  const message = String(req.body?.message ?? '').trim();
  let conversationId = parseInt(req.body?.conversation_id ?? 0, 10) || 0;

  if (message === '') {
    return res.status(400).json({ ok: false, data: null, message: 'Message is empty.' });
  }

  try {
    // Rate limit (per user per local day, in the user's timezone).
    const today = req.todayTz;
    const usageRows = await query(
      'SELECT message_count FROM ai_usage_daily WHERE user_id = ? AND usage_date = ?',
      [userId, today]
    );
    const used = Number(usageRows[0]?.message_count ?? 0);
    if (used >= DAILY_LIMIT) {
      return res.status(429).json({
        ok: false,
        data: null,
        message: `Daily AI Coach limit reached (${DAILY_LIMIT} messages). Please try again tomorrow.`,
      });
    }

    // Create or verify conversation.
    let isNewConversation = false;
    if (conversationId <= 0) {
      const ins = await query("INSERT INTO ai_conversation (user_id, title) VALUES (?, 'New chat')", [userId]);
      conversationId = ins.insertId;
      isNewConversation = true;
    } else if (!(await fetchOwnedConversation(userId, conversationId))) {
      return res.status(404).json({ ok: false, data: null, message: 'Conversation not found.' });
    }

    // Save user message (image_path null — vision not ported in this version).
    const userIns = await query(
      "INSERT INTO ai_message (conversation_id, role, content, image_path) VALUES (?, 'user', ?, NULL)",
      [conversationId, message]
    );
    const userMessageId = userIns.insertId;

    // Load recent history (chronological), stripping the packed food-log marker
    // so the model never sees our private suggestion payload.
    const historyRows = await query(
      `SELECT role, content
         FROM ai_message
        WHERE conversation_id = ?
        ORDER BY created_at DESC, message_id DESC
        LIMIT ?`,
      [conversationId, HISTORY_TURNS * 2]
    );
    const history = historyRows
      .reverse()
      .map((r) => ({ role: r.role, content: unpackFoodLogSuggestions(r.content ?? '')[0] }));

    // Build context + call the model.
    const userContext = await buildUserContext(userId, req.tzShift, req.todayTz);
    const clientTimeInfo = buildClientTimeInfo(req.body?.client_now, req.body?.client_tz_offset);
    // Preferred language anchors the reply when the latest message is ambiguous;
    // tone + optional custom persona (from Settings) shape the voice.
    const language = req.user?.language_preference === 'vi' ? 'Vietnamese' : 'English';
    const system = systemInstruction(
      userContext,
      clientTimeInfo,
      language,
      req.user?.ai_tone || 'formal',
      req.user?.ai_persona || ''
    );

    const result = await chatCompletion({ system, history });
    if (!result.ok) {
      return res.status(502).json({ ok: false, data: null, message: result.error || 'AI error' });
    }

    // Split the food-log block out of the reply; persist suggestions packed in.
    const [assistantText, foodLogSuggestions] = extractFoodLogBlock(result.text);
    const stored = packFoodLogSuggestions(assistantText, foodLogSuggestions);
    const asstIns = await query(
      "INSERT INTO ai_message (conversation_id, role, content) VALUES (?, 'assistant', ?)",
      [conversationId, stored]
    );
    const assistantMessageId = asstIns.insertId;

    await query('UPDATE ai_conversation SET updated_at = CURRENT_TIMESTAMP WHERE conversation_id = ?', [
      conversationId,
    ]);

    // Auto-title new conversations from the first user message.
    if (isNewConversation) {
      await query('UPDATE ai_conversation SET title = ? WHERE conversation_id = ?', [
        message.slice(0, 60),
        conversationId,
      ]);
    }

    // Bump usage counter (upsert).
    await query(
      `INSERT INTO ai_usage_daily (user_id, usage_date, message_count)
       VALUES (?, ?, 1)
       ON DUPLICATE KEY UPDATE message_count = message_count + 1`,
      [userId, today]
    );

    const [userMsgRow] = await query(
      'SELECT message_id, role, content, image_path, created_at FROM ai_message WHERE message_id = ?',
      [userMessageId]
    );
    const [assistantMsgRow] = await query(
      'SELECT message_id, role, content, image_path, created_at FROM ai_message WHERE message_id = ?',
      [assistantMessageId]
    );

    res.status(201).json({
      ok: true,
      data: {
        conversation_id: conversationId,
        user_message: formatMessage(userMsgRow),
        assistant_message: formatMessage(assistantMsgRow),
        food_log_suggestions: foodLogSuggestions,
        usage_today: used + 1,
        daily_limit: DAILY_LIMIT,
      },
      message: null,
    });
  } catch (err) {
    next(err);
  }
});

router.post('/delete', requireAuth, async (req, res, next) => {
  try {
    const conversationId = parseInt(req.body?.conversation_id ?? 0, 10) || 0;
    if (conversationId <= 0) {
      return res.status(400).json({ ok: false, data: null, message: 'Missing conversation_id.' });
    }
    if (!(await fetchOwnedConversation(req.user.user_id, conversationId))) {
      return res.status(404).json({ ok: false, data: null, message: 'Conversation not found.' });
    }
    // ai_message rows cascade via FK. (No on-disk images to clean in this version.)
    await query('DELETE FROM ai_conversation WHERE conversation_id = ?', [conversationId]);
    res.json({ ok: true, data: { deleted_id: conversationId }, message: null });
  } catch (err) {
    next(err);
  }
});

export default router;
