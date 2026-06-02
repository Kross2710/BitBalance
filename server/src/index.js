import 'dotenv/config';
import express from 'express';
import session from 'express-session';
import cors from 'cors';

import authRoutes from './routes/auth.js';
import intakeRoutes from './routes/intake.js';
import onboardingRoutes from './routes/onboarding.js';
import dashboardRoutes from './routes/dashboard.js';
import profileRoutes from './routes/profile.js';
import aiCoachRoutes from './routes/aiCoach.js';
import friendsRoutes from './routes/friends.js';
import reminderRoutes from './routes/reminders.js';
import ptRoutes from './routes/pt.js';
import wrappedRoutes from './routes/wrapped.js';
import { UPLOADS_ROOT } from './lib/uploads.js';
import { tryRememberLogin } from './lib/remember.js';

const app = express();
const PORT = Number(process.env.PORT || 3000);

app.use(express.json());

// CORS for dev when the Vue client talks to the API cross-origin. When the
// client uses the Vite proxy (recommended, see client/vite.config.js) requests
// are same-origin and this is a no-op. credentials:true lets the session cookie flow.
app.use(
  cors({
    origin: process.env.CLIENT_ORIGIN || 'http://localhost:5173',
    credentials: true,
  })
);

// Session — the Express equivalent of PHP's session_start() + hardened cookie.
// NOTE: the default MemoryStore is for DEV ONLY (leaks memory, single process).
// For production swap in a persistent store (e.g. connect-redis or a MySQL
// session store) — tracked in MIGRATION.md.
app.use(
  session({
    name: 'bb.sid',
    secret: process.env.SESSION_SECRET || 'dev-insecure-secret',
    resave: false,
    saveUninitialized: false,
    cookie: {
      httpOnly: true,
      sameSite: 'lax',
      secure: process.env.COOKIE_SECURE === 'true',
      maxAge: 1000 * 60 * 60 * 24, // 1 day
    },
  })
);

// Auto-login from a "remember me" cookie when the session has lapsed. Mirrors
// PHP's remember_login() in init.php: only touches the DB when a guest request
// actually carries the cookie, so it's a no-op for normal traffic.
app.use(async (req, res, next) => {
  try {
    if (!req.session?.user) await tryRememberLogin(req, res);
  } catch {
    /* a remember-me failure must never break the request */
  }
  next();
});

app.get('/api/health', (req, res) => res.json({ ok: true, data: { status: 'up' }, message: null }));

app.use('/api/auth', authRoutes);
app.use('/api/intake', intakeRoutes);
app.use('/api/onboarding', onboardingRoutes);
app.use('/api/dashboard', dashboardRoutes);
app.use('/api/profile', profileRoutes);
app.use('/api/ai-coach', aiCoachRoutes);
app.use('/api/social', friendsRoutes);
app.use('/api/reminders', reminderRoutes);
app.use('/api/pt', ptRoutes);
app.use('/api/wrapped', wrappedRoutes);

// Serve logged food photos read-only. Under /api so the Vite dev proxy forwards
// it and it stays same-origin in production. maxAge: these files are immutable
// (unique filename per upload).
app.use('/api/uploads', express.static(UPLOADS_ROOT, { maxAge: '7d', index: false }));

// 404 + error handlers in the same { ok, data, message } envelope the SPA expects.
app.use((req, res) => {
  res.status(404).json({ ok: false, data: null, message: 'Not found.' });
});

app.use((err, req, res, next) => {
  console.error('API error:', err);
  res.status(500).json({ ok: false, data: null, message: 'Server error. Please try again.' });
});

app.listen(PORT, () => {
  console.log(`BitBalance API listening on http://localhost:${PORT}`);
});
