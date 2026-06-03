// Progress / achievements — GET /api/progress returns the full achievement
// snapshot ({ summary, records, achievements }) for the logged-in user. The
// heavy lifting lives in lib/achievements.js (already ported from the PHP
// dashboard-progress.php); this route just exposes it and refreshes the
// session baseline so the intake "unlock toast" diffs against what the user
// just saw.
import { Router } from 'express';
import { requireAuth } from '../middleware/auth.js';
import { achievementsProgress, snapshotAchievementLevels } from '../lib/achievements.js';

const router = Router();

router.get('/', requireAuth, async (req, res, next) => {
  try {
    const data = await achievementsProgress(req.user.user_id, req.tzShift);
    // The user has now seen their current standing — reset the unlock-toast
    // baseline so only achievements earned AFTER this view celebrate later.
    snapshotAchievementLevels(req.session, data.achievements);
    res.json({ ok: true, data, message: null });
  } catch (err) {
    next(err);
  }
});

export default router;
