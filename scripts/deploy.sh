#!/usr/bin/env bash
#
# scripts/deploy.sh — one-shot deploy to the RMIT production server.
#
# Prod IS a git checkout at ~/public_html/bitbalance on branch `main`
# (see AGENTS.md). "Deploy" therefore = SSH in and fast-forward `git pull`.
# This wraps that single round-trip and adds a few guard rails so you stop
# typing the same ssh/cd/pull dance every time.
#
# It NEVER force-pulls or resets, so the server's own (uncommitted, gitignored)
#   include/db_config.php   and   include/secrets.php
# are safe: a plain --ff-only pull leaves untracked/dirty files alone and aborts
# cleanly if an incoming change would clobber them — fix those by hand, on purpose.
#
# Before every deploy it also scans tracked .php for PHP-7.4 "prod landmines" —
# calls that work on local XAMPP (PHP 8.2 + mbstring) but FATAL on RMIT
# (PHP 7.4.33): str_contains/str_starts_with/str_ends_with, mb_*, posix_*, and
# the nullsafe ?-> operator. Deploy is refused if any are found (see AGENTS.md).
#
# Usage:
#   ./scripts/deploy.sh              Deploy: scan for PHP-7.4 landmines, then ff-only pull.
#   ./scripts/deploy.sh -c|--check   Scan only: list PHP-7.4 landmines, exit non-zero if any.
#   ./scripts/deploy.sh -s|--status  Peek: prod's current commit + how far behind origin.
#   ./scripts/deploy.sh -l|--log     Show prod's last 8 commits.
#   ./scripts/deploy.sh -r|--run CMD Run an arbitrary command in the prod project dir
#                                    (escape hatch for migrations etc. — manual & explicit).
#   ./scripts/deploy.sh -y|--yes     Don't prompt, even if local has unpushed commits.
#   ./scripts/deploy.sh --no-check   Deploy without the PHP-7.4 scan (not recommended).
#   ./scripts/deploy.sh -h|--help    This help.
#
# Override the target without editing the file:
#   DEPLOY_HOST=s3974781@coreteaching04.csit.rmit.edu.au ./scripts/deploy.sh
#
# Requires RMIT internal network / VPN — the host is unreachable otherwise.

set -euo pipefail

# ---- config (override via env) ------------------------------------------------
HOST="${DEPLOY_HOST:-s3974781@titan.csit.rmit.edu.au}"
REMOTE_DIR="${DEPLOY_DIR:-~/public_html/bitbalance}"   # tilde expands on the remote shell
BRANCH="${DEPLOY_BRANCH:-main}"
REMOTE="${DEPLOY_REMOTE:-origin}"

# ---- pretty output ------------------------------------------------------------
if [ -t 1 ]; then
  B=$'\033[1m'; DIM=$'\033[2m'; G=$'\033[32m'; Y=$'\033[33m'; R=$'\033[31m'; C=$'\033[36m'; N=$'\033[0m'
else
  B=''; DIM=''; G=''; Y=''; R=''; C=''; N=''
fi
say()  { printf '%s\n' "$*"; }
info() { printf '%s➤%s %s\n' "$C" "$N" "$*"; }
ok()   { printf '%s✔%s %s\n' "$G" "$N" "$*"; }
warn() { printf '%s⚠%s  %s\n' "$Y" "$N" "$*" >&2; }
die()  { printf '%s✗%s %s\n' "$R" "$N" "$*" >&2; exit 1; }

# ssh wrapper: run a command inside the prod project dir, single round-trip.
remote() { ssh "$HOST" "cd $REMOTE_DIR && $*"; }

ssh_hint() {
  warn "SSH to ${B}${HOST}${N} failed."
  warn "RMIT prod is internal-only — are you on the RMIT network / VPN?"
  warn "Wrong host? try: ${DIM}DEPLOY_HOST=s3974781@coreteaching04.csit.rmit.edu.au $0${N}"
}

# ---- PHP-7.4 prod-landmine guard ---------------------------------------------
# Functions/operators that exist on local XAMPP (PHP 8.2 + mbstring) but FATAL
# on RMIT (PHP 7.4.33, no mbstring, posix disabled). See AGENTS.md "PHP
# extensions / functions disabled". We match call-sites only (name followed by
# "(", plus the literal "?->") and drop two safe cases: comment lines, and calls
# guarded by function_exists() (the correct mb_* polyfill pattern).
PHP74_PATTERN='(\b(str_contains|str_starts_with|str_ends_with|mb_[a-z_]+|posix_[a-z_]+)[[:space:]]*\()|(\?->)'

php74_guard() {
  git ls-files '*.php' -z 2>/dev/null \
    | xargs -0 grep -nE "$PHP74_PATTERN" 2>/dev/null \
    | grep -vE ':[0-9]+:[[:space:]]*(//|#|\*|/\*)' \
    | grep -v 'function_exists' || true
}

print_guard_hits() {
  warn "PHP-7.4 prod landmines (run on local XAMPP 8.2, but ${B}fatal on RMIT${N}):"
  printf '%s\n' "$1" | sed 's/^/    /' >&2
  {
    printf '\n  Fixes (AGENTS.md):\n'
    printf '    str_contains($s,$p)    -> strpos($s,$p) !== false\n'
    printf '    str_starts_with($s,$p) -> strpos($s,$p) === 0\n'
    printf '    str_ends_with($s,$p)   -> substr($s,-strlen($p)) === $p\n'
    printf '    mb_*()                 -> iconv_* / preg polyfill, guarded by function_exists()\n'
    printf '    posix_*()              -> unavailable on RMIT; avoid\n'
    printf '    $x?->y                 -> ($x !== null ? $x->y : null)\n'
  } >&2
}

# Run all local git ops + the scan from the repo root, wherever we were invoked.
REPO_ROOT="$(git rev-parse --show-toplevel 2>/dev/null || true)"
[ -n "$REPO_ROOT" ] && cd "$REPO_ROOT"

# ---- arg parsing --------------------------------------------------------------
ACTION="deploy"; RUN_CMD=""; ASSUME_YES=0; DO_CHECK=1
while [ $# -gt 0 ]; do
  case "$1" in
    -c|--check)  ACTION="check" ;;
    -s|--status) ACTION="status" ;;
    -l|--log)    ACTION="log" ;;
    -r|--run)    ACTION="run"; shift; RUN_CMD="${1:-}"; [ -n "$RUN_CMD" ] || die "--run needs a command" ;;
    -y|--yes)    ASSUME_YES=1 ;;
    --no-check)  DO_CHECK=0 ;;
    -h|--help)   awk 'NR==1{next} /^#/{sub(/^# ?/,"");print;next} {exit}' "$0"; exit 0 ;;
    *)           die "Unknown option: $1  (try --help)" ;;
  esac
  shift
done

say "${B}BitBalance deploy${N} ${DIM}→ ${HOST}:${REMOTE_DIR} (${BRANCH})${N}"

# ---- check: scan only, no SSH -------------------------------------------------
if [ "$ACTION" = "check" ]; then
  info "Scanning tracked .php for PHP-7.4 prod landmines…"
  hits="$(php74_guard)"
  if [ -n "$hits" ]; then print_guard_hits "$hits"; exit 1; fi
  ok "Clean — no PHP-7.4 landmines."
  exit 0
fi

# ---- status / log / run: read-only-ish, no local checks needed ----------------
case "$ACTION" in
  status)
    info "Fetching prod state…"
    remote "git fetch -q $REMOTE $BRANCH; \
            echo; echo 'Prod HEAD:'; git --no-pager log -1 --format='  %h  %ci  %s'; \
            echo; behind=\$(git rev-list --count HEAD..$REMOTE/$BRANCH); \
            if [ \"\$behind\" -gt 0 ]; then echo \"  Behind $REMOTE/$BRANCH by \$behind commit(s) — deploy to catch up:\"; \
              git --no-pager log --oneline HEAD..$REMOTE/$BRANCH | sed 's/^/    /'; \
            else echo '  Up to date with '$REMOTE/$BRANCH'.'; fi; \
            echo; echo 'Working tree:'; git status --short || true" \
      || { ssh_hint; exit 1; }
    exit 0 ;;
  log)
    remote "git --no-pager log -8 --format='  %h  %ci  %s'" || { ssh_hint; exit 1; }
    exit 0 ;;
  run)
    warn "Running on PROD: ${B}${RUN_CMD}${N}"
    remote "$RUN_CMD" || { ssh_hint; exit 1; }
    exit 0 ;;
esac

# ---- deploy: PHP-7.4 guard → unpushed-commit warning → ff-only pull ------------
# Refuse to ship code that fatals on RMIT's PHP 7.4 unless explicitly bypassed.
if [ "$DO_CHECK" -eq 1 ]; then
  hits="$(php74_guard)"
  if [ -n "$hits" ]; then
    print_guard_hits "$hits"
    die "Refusing to deploy — fix the above, or bypass with ${DIM}--no-check${N} (not recommended)."
  fi
  ok "PHP-7.4 guard: clean."
else
  warn "Skipping PHP-7.4 landmine scan (--no-check)."
fi

# The server pulls from $REMOTE, so anything you haven't pushed there won't ship.
if git rev-parse --git-dir >/dev/null 2>&1; then
  if git fetch -q "$REMOTE" "$BRANCH" 2>/dev/null; then
    ahead=$(git rev-list --count "${REMOTE}/${BRANCH}..${BRANCH}" 2>/dev/null || echo 0)
    if [ "${ahead:-0}" -gt 0 ]; then
      warn "${ahead} local commit(s) on '${BRANCH}' not yet pushed to ${REMOTE} — the server pull will NOT include them."
      git --no-pager log --oneline "${REMOTE}/${BRANCH}..${BRANCH}" | sed 's/^/    /' >&2 || true
      if [ "$ASSUME_YES" -ne 1 ]; then
        printf '%s' "Push them first? you'll deploy stale code otherwise. Continue anyway? [y/N] " >&2
        read -r reply </dev/tty || reply=""
        case "$reply" in y|Y|yes|YES) ;; *) die "Aborted. Run: git push ${REMOTE} ${BRANCH}" ;; esac
      fi
    fi
  else
    warn "Couldn't fetch ${REMOTE} locally (offline?) — skipping the unpushed-commit check."
  fi
fi

info "Fast-forward pull on prod…"
if remote "git pull --ff-only $REMOTE $BRANCH && echo && echo 'Now live:' && git --no-pager log -1 --format='  %h  %ci  %s'"; then
  ok "Deployed to ${HOST}."
else
  rc=$?
  ssh_hint
  warn "If the pull was refused for 'local changes' it's likely the prod-only db_config.php/secrets.php."
  warn "Inspect with: ${DIM}$0 --run 'git status'${N} and resolve by hand — do NOT force."
  exit "$rc"
fi
