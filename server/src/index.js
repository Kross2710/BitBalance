import 'dotenv/config';
import express from 'express';
import session from 'express-session';
import expressMySQLSession from 'express-mysql-session';
import cors from 'cors';
import compression from 'compression';
import helmet from 'helmet';
import path from 'node:path';
import fs from 'node:fs';
import { fileURLToPath } from 'node:url';

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
import progressRoutes from './routes/progress.js';
import adminRoutes from './routes/admin.js';
import { requireAuth, requireAdmin } from './middleware/auth.js';
import { tzContext } from './middleware/tz.js';
import { globalLimiter, authLimiter, aiLimiter } from './middleware/rateLimit.js';
import { LEGACY_UPLOADS_ROOT, UPLOADS_ROOT } from './lib/uploads.js';
import { tryRememberLogin } from './lib/remember.js';

const app = express();
const PORT = Number(process.env.PORT || 3000);

// Fail fast rather than run a public deployment on the insecure dev fallback
// secret (which would let anyone forge a session cookie). "Production" here =
// NODE_ENV=production OR COOKIE_SECURE=true (the flag the box sets behind TLS).
const SESSION_SECRET = process.env.SESSION_SECRET;
const IS_PROD = process.env.NODE_ENV === 'production' || process.env.COOKIE_SECURE === 'true';
if (IS_PROD && (!SESSION_SECRET || SESSION_SECRET === 'dev-insecure-secret')) {
  throw new Error('SESSION_SECRET must be set to a strong random value when COOKIE_SECURE=true / NODE_ENV=production.');
}

// Behind a TLS-terminating proxy (ngrok, nginx, a PaaS) the connection to this
// process is plain HTTP, so Express sees req.secure=false and a Secure session
// cookie would be silently dropped. Trusting the proxy lets Express read
// X-Forwarded-Proto, so secure cookies are sent when COOKIE_SECURE=true. Set
// TRUST_PROXY=0 to disable (e.g. when exposed directly without a proxy).
if (process.env.TRUST_PROXY !== '0') app.set('trust proxy', 1);

// Security headers (clickjacking, MIME-sniffing, referrer leakage, HSTS, and
// dropping the X-Powered-By version banner). CSP is left off for now: the SPA
// isn't CSP-audited and a wrong policy would silently break it — tracked as a
// follow-up. COEP off + CORP cross-origin so external images (OpenFoodFacts
// product photos, Google avatars) and our own /api/uploads still load.
app.use(
  helmet({
    contentSecurityPolicy: false,
    crossOriginEmbedderPolicy: false,
    crossOriginResourcePolicy: { policy: 'cross-origin' },
    hsts: { maxAge: 15552000 }, // 180 days; ngrok terminates TLS in front of us
  })
);

// Gzip every text response (API JSON + the built SPA's JS/CSS/HTML). The Vue
// bundles are ~550KB uncompressed; gzip cuts that ~75%, which dominates
// first-load time over the ngrok tunnel. Negligible CPU at this traffic level,
// and it skips already-compressed types (images, etc.) automatically.
app.use(compression());

// Cap JSON bodies (the dynamic endpoints only carry small payloads; image
// uploads go through multer, not here, so this never affects them).
app.use(express.json({ limit: '1mb' }));

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
// Persisted in MariaDB (express-mysql-session) instead of the default
// MemoryStore, which leaks memory on a long-running process and drops every
// active session on restart. The store owns a `sessions` table (created on first
// run) and sweeps expired rows, so logins now survive a service restart.
const MySQLStore = expressMySQLSession(session);
const sessionStore = new MySQLStore({
  host: process.env.DB_HOST,
  port: Number(process.env.DB_PORT || 3306),
  user: process.env.DB_USER,
  password: process.env.DB_PASSWORD,
  database: process.env.DB_NAME,
  createDatabaseTable: true,
  clearExpired: true,
  checkExpirationInterval: 1000 * 60 * 60, // sweep expired sessions hourly
  expiration: 1000 * 60 * 60 * 24, // 1 day — matches the cookie maxAge below
});
// A store error (DB blip, etc.) must be logged, never take down the process.
sessionStore.on('error', (err) => console.error('Session store error:', err));

app.use(
  session({
    name: 'bb.sid',
    store: sessionStore,
    secret: SESSION_SECRET || 'dev-insecure-secret',
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

// Attach per-request timezone context (req.tz / req.tzShift / req.todayTz) from
// the client's X-Timezone header, so day-grouping reflects the user's local day.
app.use(tzContext);

app.get('/api/health', (req, res) => res.json({ ok: true, data: { status: 'up' }, message: null }));

// Rate limiting (see middleware/rateLimit.js). Broad backstop on the whole API,
// then tighter caps on the abuse-prone surfaces. Path-specific limiters are
// registered BEFORE the routers so they run first.
app.use('/api', globalLimiter);
app.use('/api/auth/login', authLimiter);
app.use('/api/auth/register', authLimiter);
app.use('/api/ai-coach/send', aiLimiter);
app.use('/api/intake/estimate-photo', aiLimiter);
app.use('/api/intake/ai-chat', aiLimiter);

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
app.use('/api/progress', progressRoutes);
// Admin panel — whole surface is admin-only, so guard at the mount (not
// per-endpoint like PT). requireAuth attaches req.user; requireAdmin gates role.
app.use('/api/admin', requireAuth, requireAdmin, adminRoutes);

// Serve logged food photos read-only. Under /api so the Vite dev proxy forwards
// it and it stays same-origin in production. maxAge: these files are immutable
// (unique filename per upload).
app.use('/api/uploads', express.static(UPLOADS_ROOT, { maxAge: '7d', index: false }));
app.use('/uploads', express.static(LEGACY_UPLOADS_ROOT, { maxAge: '7d', index: false }));
app.use('/uploads', (_req, res) => res.status(404).end());

// Serve the built Vue SPA so production runs on a SINGLE origin (one ngrok
// tunnel, same-origin cookies). Guarded by existsSync: in dev the client is
// served by Vite on :5173 and client/dist doesn't exist, so this is a no-op.
// `npm run build` in client/ emits client/dist.
const CLIENT_DIST = path.resolve(
  path.dirname(fileURLToPath(import.meta.url)),
  '../../client/dist'
);
if (fs.existsSync(CLIENT_DIST)) {
  // Content-hashed bundles under /assets are safe to cache forever: Vite puts a
  // content hash in every filename, so changed content always gets a new URL.
  // `immutable` tells the browser never to revalidate, killing repeat
  // round-trips over the tunnel. Previously these were served with max-age=0.
  app.use(
    '/assets',
    express.static(path.join(CLIENT_DIST, 'assets'), { immutable: true, maxAge: '1y' })
  );
  // Other static files (favicon, etc.). index.html is intentionally excluded
  // (index:false) so the fallback below serves it with its own no-cache header.
  app.use(express.static(CLIENT_DIST, { index: false }));
  // SPA history fallback: any non-/api GET returns index.html so client-side
  // routing (vue-router) works on hard refresh / deep links. index.html must
  // NOT be cached — it references the latest hashed bundles, so a stale copy
  // would pin users to old assets after a deploy.
  app.get(/^\/(?!api\/).*/, (req, res) => {
    res.set('Cache-Control', 'no-cache');
    res.sendFile(path.join(CLIENT_DIST, 'index.html'));
  });
}

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
