# DEPLOY.md — BitBalance (Express + Vue) on the N100 / CachyOS box

Operational runbook for the **Express API + built Vue SPA** deployment (NOT the
RMIT PHP app). For first-time provisioning from scratch see
[`deploy/DEPLOY-CachyOS.md`](deploy/DEPLOY-CachyOS.md). This file is the day-2
guide plus the gotchas learned in production.

> No secrets here (public repo). Credentials live in `server/.env` on the box
> (gitignored) and in the operator's private notes. Never commit passwords,
> ngrok tokens, or API keys.

## Architecture

```
Browser --HTTPS--> ngrok edge --tunnel--> Express :3000 (serves /api + client/dist) --> MariaDB
Operator --Tailscale (100.x)--> ssh into the box --> ./deploy/deploy.sh
```

- **One origin**: Express serves both `/api/*` and the built SPA from
  `client/dist` (see `server/src/index.js`, guarded by `existsSync`). So a single
  ngrok HTTP tunnel to `:3000` covers the whole app. ngrok free = 1 tunnel, so do
  NOT split client/api across ports.
- **Public URL** (static, pinned in the ngrok service): `https://cusp-ammonium-zipfile.ngrok-free.dev`
- **Remote access**: the box is on a closed-port/CGNAT LAN. SSH/deploy go over
  **Tailscale** (box IP `100.127.38.40`, hostname `cachyos-x8664`, user `kross`).
  LAN-only fallback: `192.168.0.194`.

## Stack on the box

- Node 26, PHP 8.5 (`pdo_mysql`/`mysqli` enabled in `/etc/php/php.ini`).
- MariaDB 12: DB `bitbalance`, app user `bituser` (creds in `server/.env`).
- **DB data = clone of RMIT production** (see "Clone production DB" below).
- Two systemd **user** services (linger enabled → survive logout/reboot,
  auto-restart on crash):
  - `bitbalance` → `node src/index.js` (WorkingDirectory `~/BitBalance-2.0---Calorie-Tracker/server`)
  - `ngrok` → `ngrok http 3000 --domain=cusp-ammonium-zipfile.ngrok-free.dev`
- `tailscaled` is a **system** service. Sleep/suspend/hibernate are masked
  (runs 24/7).

## Redeploy after pushing code (the normal flow)

```bash
# 1. Push to the branch the box tracks (currently claude/express-vue-migration-XoMbE)
git push origin <branch>

# 2. One command from anywhere on the tailnet:
ssh kross@100.127.38.40 'cd ~/BitBalance-2.0---Calorie-Tracker && ./deploy/deploy.sh'
```

`deploy/deploy.sh` does: `git fetch` + `merge --ff-only` → `npm ci` server →
`npm ci` + `npm run build` client → `php include/migrations/migrate.php` →
restart the `bitbalance` user service. It is idempotent and ff-only (refuses to
clobber local divergence — reconcile by hand if it stops).

Deploy a different branch: `BRANCH=main ./deploy/deploy.sh` (or `git checkout` it
on the box once).

## Operate

```bash
systemctl --user status bitbalance ngrok      # service state
journalctl --user -u bitbalance -f            # live server logs
systemctl --user restart bitbalance           # restart after editing .env
curl -s https://cusp-ammonium-zipfile.ngrok-free.dev/api/health   # {"ok":true}
```

Changing `server/.env` (e.g. enabling Google/AI keys) needs only a
`systemctl --user restart bitbalance` — no rebuild.

## Gotchas (learned the hard way)

1. **Login shell on the box is `fish`.** Wrap remote commands in `bash`:
   `ssh kross@... 'bash -lc "..."'` or `ssh ... 'bash -s' <<'EOF'`.
2. **`sudo -S` consumes stdin.** Don't pipe a heredoc into a `sudo -S` command —
   the heredoc gets eaten as the password. Cache first: `echo <pw> | sudo -S -v`,
   then run plain `sudo ...`; pass SQL via `-e` not stdin.
3. **`trust proxy` is required** (`server/src/index.js`). ngrok terminates TLS and
   forwards plain HTTP, so without it Express sees `req.secure=false` and drops
   the `Secure` session cookie → login never persists. `COOKIE_SECURE=true` in
   `.env` only works because of this.
4. **DB session timezone must be +07:00.** `server/src/db.js` runs
   `SET time_zone='+07:00'` on every pooled connection (mysql2's `timezone`
   option does NOT do this). The box is on AEST; without the SET, `NOW()`/
   `CURDATE()` use AEST while the app computes "today" at +07:00 (`todayVN`), so
   logged food lands on the wrong day and appears to vanish near the day boundary.
5. **Sessions are MemoryStore** → a restart drops active sessions, but the 30-day
   remember-me cookie re-logs users in. Acceptable for now; swap to Redis/MySQL
   store for real durability.
6. **No password reset in Express/Vue yet.** To reset/unlock an account, run a
   one-off Node script in `server/` (so it resolves `node_modules` + `.env`):
   `bcrypt.hash(pw,10)` → `UPDATE user SET password=?` and
   `UPDATE userStatus SET failed_attempts=0, locked_until=NULL, status='active'`.
   Lockout state lives in `userStatus` (`failed_attempts` / `locked_until`).

## Clone production DB from RMIT

Production DB `talsprddb02.int.its.rmit.edu.au` (`COSC3046_2502_G20`) is only
reachable from inside RMIT, so dump via the `titan` SSH jump host:

```bash
ssh s3974781@titan.csit.rmit.edu.au \
  "mysqldump -h talsprddb02.int.its.rmit.edu.au -u COSC3046_2502_G20 -p<pw> \
   --single-transaction --no-tablespaces --routines --triggers \
   --default-character-set=utf8mb4 COSC3046_2502_G20" > prod_dump.sql
```

Then on the box: back up current DB → `DROP/CREATE DATABASE bitbalance` →
import → run migrations. **IMPORTANT:** production's `schema_migrations` table
falsely records some migrations as applied while the actual columns/tables are
missing, so `migrate.php` skips them. After importing prod, re-apply these
idempotently (they are what the Express app needs):

- `include/migrations/2026_06_02_add_macro_goals.sql` (macro cols on
  `userGoal` / `intakeLog`; and on `pt_goal_proposal` once it exists)
- `include/migrations/2026_06_02_add_pt_goal_proposal.sql` (prod is missing the
  `pt_goal_proposal` table entirely)

Use `ADD COLUMN IF NOT EXISTS` / `CREATE TABLE IF NOT EXISTS`. Verify by diffing
the column set of a known-good dev schema against `bitbalance` (no missing
tables/columns). `migrate.php` reads `include/db_config.local.php` (gitignored,
points at `bitbalance`/`bituser`) — create it if absent.

## Don't

- Don't make the deploy depend on the RMIT PHP runtime (separate target).
- Don't expose client and API on separate ports (breaks one-tunnel + cookies).
- Don't commit `server/.env`, `include/db_config.local.php`, ngrok tokens, or
  `client/vite.config.js`.
