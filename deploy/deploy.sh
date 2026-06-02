#!/usr/bin/env bash
# BitBalance — pull + build + restart, idempotent. Run on the CachyOS box.
#   ./deploy/deploy.sh            # pull current branch, build, restart service
#   BRANCH=main ./deploy/deploy.sh
# Safe to re-run. Exits non-zero on any failure (set -e).
set -euo pipefail

# Repo root = parent of this script's dir, regardless of where it's called from.
REPO_ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$REPO_ROOT"

BRANCH="${BRANCH:-$(git rev-parse --abbrev-ref HEAD)}"
SERVICE="${SERVICE:-bitbalance}"

echo "==> Repo:    $REPO_ROOT"
echo "==> Branch:  $BRANCH"

echo "==> Fetching + fast-forwarding origin/$BRANCH"
git fetch origin "$BRANCH"
# Fast-forward only: refuse to clobber local divergence. If this fails, log in
# and reconcile by hand rather than letting the script guess.
git merge --ff-only "origin/$BRANCH"

echo "==> Installing server deps"
( cd server && npm ci --omit=dev 2>/dev/null || npm install --omit=dev )

echo "==> Installing client deps + building SPA -> client/dist"
( cd client && { npm ci || npm install; } && npm run build )

# Apply any pending SQL migrations via the PHP migrator if php is present.
if command -v php >/dev/null 2>&1 && [ -f include/migrations/migrate.php ]; then
  echo "==> Running DB migrations (php migrate.php)"
  php include/migrations/migrate.php || echo "   (migrate.php returned non-zero — check manually)"
else
  echo "==> Skipping migrations (php or migrate.php not found) — apply include/migrations/*.sql manually if DB is behind"
fi

echo "==> Restarting service: $SERVICE"
if systemctl --user list-unit-files "${SERVICE}.service" >/dev/null 2>&1 && \
   systemctl --user cat "${SERVICE}.service" >/dev/null 2>&1; then
  systemctl --user restart "$SERVICE"
  systemctl --user --no-pager status "$SERVICE" | head -n 5
elif systemctl cat "${SERVICE}.service" >/dev/null 2>&1; then
  sudo systemctl restart "$SERVICE"
  systemctl --no-pager status "$SERVICE" | head -n 5
else
  echo "   No systemd unit '$SERVICE' found. Start manually: (cd server && npm start)"
fi

echo "==> Done."
