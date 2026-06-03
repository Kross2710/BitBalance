// BitBalance Wrapped — GET /api/wrapped returns the recap payload (Spotify-
// Wrapped-style story slides) for the logged-in user. The heavy lifting (stats
// + 1 AI call + caching) lives in lib/wrapped.js.
import { Router } from 'express';
import { requireAuth } from '../middleware/auth.js';
import { buildWrapped } from '../lib/wrapped.js';

const router = Router();

router.get('/', requireAuth, async (req, res, next) => {
  try {
    const requestedLang = req.query?.lang === 'vi' ? 'vi' : req.user.language_preference;
    const data = await buildWrapped(req.user.user_id, req.user.user_name, requestedLang, req.tzShift, req.todayTz);
    res.json({ ok: true, data, message: null });
  } catch (err) {
    next(err);
  }
});

export default router;
