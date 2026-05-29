-- Migration: add language_preference to userStatus
-- Date: 2026-05-29
--
-- Adds per-user language preference so the i18n layer (include/i18n/i18n.php)
-- can render BitBalance in the user's language across devices. VARCHAR(8)
-- rather than ENUM so contributors can add new locales by dropping a file
-- under include/i18n/ and registering it in include/i18n/locales.php — no
-- schema change required.
--
-- 'en' default matches the fallback locale; existing rows are backfilled to
-- the same value. Run on the RMIT DB via SSH (see AGENTS.md). Safe to re-run:
-- the ADD COLUMN is idempotent on MySQL only via the IF NOT EXISTS extension
-- on 8.0.29+. On older MySQL, run the column check manually before re-running.

ALTER TABLE userStatus
  ADD COLUMN language_preference VARCHAR(8) NOT NULL DEFAULT 'en' AFTER theme_preference;

-- Backfill is implicit via the DEFAULT, but make it explicit for clarity:
UPDATE userStatus SET language_preference = 'en' WHERE language_preference IS NULL OR language_preference = '';
