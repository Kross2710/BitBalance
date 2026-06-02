// Personal Trainer routes — CLIENT-facing side of the PT feature.
//   GET  /api/pt/my-trainer            → { trainer|null, feedback[], proposal|null }
//   GET  /api/pt/messages?since=        → { messages[], my_role:'client' }
//   POST /api/pt/messages               → { message }                ({ content })
//   POST /api/pt/goal-proposal/respond  → { accepted, calorie_goal? } ({ proposal_id, decision })
//
// Guards are PER-ENDPOINT, not per-prefix: every endpoint here is the client's
// view of their own trainer, so a regular user (the client of a PT) must be able
// to reach them. The PT workspace endpoints (slice 4) get their own role checks
// and reuse the role-agnostic chat helpers in lib/pt.js.
import { Router } from 'express';
import { requireAuth, requirePt } from '../middleware/auth.js';
import {
  PtActionError,
  myTrainer,
  feedbackHistory,
  markFeedbackSeen,
  pendingProposal,
  respondProposal,
  clientChatFetch,
  clientChatSend,
  pendingTrainer,
  ptDirectory,
  sendTrainerRequest,
  cancelTrainerRequest,
  trainerClients,
  clientDetail,
  saveFeedback,
  proposeGoal,
  pendingRequests,
  respondRequest,
  trainerChatFetch,
  trainerChatSend,
} from '../lib/pt.js';

const router = Router();

function handle(fn) {
  return async (req, res, next) => {
    try {
      await fn(req, res);
    } catch (err) {
      if (err instanceof PtActionError) {
        return res.status(422).json({ ok: false, data: null, message: err.message });
      }
      next(err);
    }
  };
}

const ok = (res, data, message = null) => res.json({ ok: true, data, message });
const intParam = (v) => {
  const n = parseInt(v, 10);
  return Number.isFinite(n) ? n : 0;
};

// Bootstrap for the My Trainer panel. No trainer → trainer:null (empty state),
// not a 4xx: "you have no trainer" is a normal state, not an error.
router.get(
  '/my-trainer',
  requireAuth,
  handle(async (req, res) => {
    const me = req.user.user_id;
    const trainer = await myTrainer(me);
    if (!trainer) {
      // No accepted trainer — surface a pending outgoing request if there is one,
      // so the panel can show "awaiting approval" instead of the directory.
      const pending = await pendingTrainer(me);
      return ok(res, { trainer: null, pending, feedback: [], proposal: null });
    }

    const [feedback, proposal] = await Promise.all([feedbackHistory(me), pendingProposal(me)]);
    await markFeedbackSeen(me);
    ok(res, { trainer, pending: null, feedback, proposal });
  })
);

// Browsable trainer directory + send/cancel a connection request (client-side).
router.get(
  '/directory',
  requireAuth,
  handle(async (req, res) => {
    ok(res, { trainers: await ptDirectory(req.user.user_id) });
  })
);

router.post(
  '/request',
  requireAuth,
  handle(async (req, res) => {
    const trainerId = intParam(req.body?.trainer_id);
    const data = await sendTrainerRequest(req.user.user_id, trainerId);
    ok(res, data, 'Request sent — awaiting approval.');
  })
);

router.post(
  '/request/cancel',
  requireAuth,
  handle(async (req, res) => {
    ok(res, await cancelTrainerRequest(req.user.user_id), 'Request cancelled.');
  })
);

router.get(
  '/messages',
  requireAuth,
  handle(async (req, res) => {
    const since = intParam(req.query.since);
    ok(res, await clientChatFetch(req.user.user_id, since));
  })
);

router.post(
  '/messages',
  requireAuth,
  handle(async (req, res) => {
    const content = String(req.body?.content ?? '');
    ok(res, await clientChatSend(req.user.user_id, content));
  })
);

router.post(
  '/goal-proposal/respond',
  requireAuth,
  handle(async (req, res) => {
    const proposalId = intParam(req.body?.proposal_id);
    const decision = req.body?.decision;
    if (proposalId <= 0 || (decision !== 'accept' && decision !== 'decline')) {
      return res.status(422).json({ ok: false, data: null, message: 'Invalid request.' });
    }
    const data = await respondProposal(req.user.user_id, proposalId, decision);
    ok(res, data, decision === 'accept' ? 'Goal updated.' : 'Proposal declined.');
  })
);

// -----------------------------------------------------------------------------
// Trainer workspace (role 'pt' only) — the /trainer route's data.
// -----------------------------------------------------------------------------

router.get(
  '/clients',
  requireAuth,
  requirePt,
  handle(async (req, res) => {
    ok(res, { clients: await trainerClients(req.user.user_id) });
  })
);

router.get(
  '/clients/:id',
  requireAuth,
  requirePt,
  handle(async (req, res) => {
    const clientId = intParam(req.params.id);
    if (clientId <= 0) return res.status(422).json({ ok: false, data: null, message: 'Invalid client id.' });
    ok(res, await clientDetail(req.user.user_id, clientId));
  })
);

router.get(
  '/clients/:id/messages',
  requireAuth,
  requirePt,
  handle(async (req, res) => {
    const clientId = intParam(req.params.id);
    if (clientId <= 0) return res.status(422).json({ ok: false, data: null, message: 'Invalid client id.' });
    ok(res, await trainerChatFetch(req.user.user_id, clientId, intParam(req.query.since)));
  })
);

router.post(
  '/clients/:id/messages',
  requireAuth,
  requirePt,
  handle(async (req, res) => {
    const clientId = intParam(req.params.id);
    if (clientId <= 0) return res.status(422).json({ ok: false, data: null, message: 'Invalid client id.' });
    ok(res, await trainerChatSend(req.user.user_id, clientId, String(req.body?.content ?? '')));
  })
);

router.post(
  '/clients/:id/feedback',
  requireAuth,
  requirePt,
  handle(async (req, res) => {
    const clientId = intParam(req.params.id);
    if (clientId <= 0) return res.status(422).json({ ok: false, data: null, message: 'Invalid client id.' });
    const data = await saveFeedback(req.user.user_id, clientId, req.body?.date_for, req.body?.content);
    ok(res, data, data.saved ? 'Feedback saved.' : 'Feedback cleared.');
  })
);

router.post(
  '/clients/:id/propose-goal',
  requireAuth,
  requirePt,
  handle(async (req, res) => {
    const clientId = intParam(req.params.id);
    if (clientId <= 0) return res.status(422).json({ ok: false, data: null, message: 'Invalid client id.' });
    const data = await proposeGoal(req.user.user_id, clientId, req.body || {});
    ok(res, data, 'Goal proposed.');
  })
);

router.get(
  '/requests',
  requireAuth,
  requirePt,
  handle(async (req, res) => {
    ok(res, { requests: await pendingRequests(req.user.user_id) });
  })
);

router.post(
  '/requests/:id/respond',
  requireAuth,
  requirePt,
  handle(async (req, res) => {
    const requestId = intParam(req.params.id);
    const action = req.body?.action;
    if (requestId <= 0 || (action !== 'accept' && action !== 'reject')) {
      return res.status(422).json({ ok: false, data: null, message: 'Invalid request.' });
    }
    const data = await respondRequest(req.user.user_id, requestId, action);
    ok(res, data, action === 'accept' ? 'Request accepted.' : 'Request declined.');
  })
);

export default router;
