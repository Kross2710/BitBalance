#!/usr/bin/env bash
#
# Install BitBalance git hooks by pointing core.hooksPath at scripts/hooks.
# Run once per clone:
#
#     ./scripts/install-hooks.sh
#
# This activates scripts/hooks/pre-commit (PHP 7.4 landmine guard). The setting
# is local to your clone (not committed), so every teammate runs this once.
#
set -euo pipefail

ROOT="$(git rev-parse --show-toplevel)"
cd "$ROOT"

chmod +x scripts/hooks/* 2>/dev/null || true

git config core.hooksPath scripts/hooks

echo "Installed git hooks: core.hooksPath -> scripts/hooks"
echo "Active hooks:"
ls -1 scripts/hooks
echo ""
echo "To disable later:  git config --unset core.hooksPath"
