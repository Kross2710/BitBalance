// IP rate limiters for a public deployment. Keyed on req.ip, which behind the
// ngrok proxy is the real client IP because index.js sets `trust proxy`. Limits
// are deliberately GENEROUS so a legitimate burst (e.g. a class of students
// behind one campus NAT signing up together) is never blocked — they only stop
// scripted abuse (credential stuffing, mass signup, AI-cost draining), which
// runs orders of magnitude higher. Per-account lockout (auth.js) and the AI
// Coach per-user daily quota (aiCoach.js) remain the primary, finer-grained
// defences; these IP caps are a coarse backstop on top.
import rateLimit from 'express-rate-limit';

const base = { standardHeaders: 'draft-7', legacyHeaders: false };
const handler = (message) => (req, res) =>
  res.status(429).json({ ok: false, data: null, message });

// Broad flood backstop for the dynamic API. Skips static image GETs and the
// health probe so image-heavy pages and uptime checks are never throttled.
export const globalLimiter = rateLimit({
  ...base,
  windowMs: 15 * 60 * 1000,
  max: 1500,
  skip: (req) =>
    req.method === 'GET' &&
    (req.originalUrl.startsWith('/api/uploads') || req.originalUrl === '/api/health'),
  handler: handler('Too many requests. Please slow down and try again shortly.'),
});

// Login + register: stops scripted credential-stuffing and mass-signup. 100 per
// 10 min/IP clears any realistic human burst but is far below a bot's rate.
export const authLimiter = rateLimit({
  ...base,
  windowMs: 10 * 60 * 1000,
  max: 100,
  handler: handler('Too many attempts from this network. Please wait a few minutes and try again.'),
});

// AI endpoints carry a real per-call provider (Gemini) cost, so cap burst use
// per IP. The AI Coach also enforces a per-user daily message quota on top.
export const aiLimiter = rateLimit({
  ...base,
  windowMs: 60 * 60 * 1000,
  max: 100,
  handler: handler('AI usage limit reached for now. Please try again later.'),
});
