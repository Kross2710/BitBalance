-- Migration: add AI Coach personality settings to userStatus
-- Date: 2026-06-04
--
-- Lets the user shape the AI Coach's voice from the new Settings page: a tone
-- toggle (formal | casual) and an optional free-text custom persona. Both live on
-- userStatus next to the other per-user preferences (theme/language/visibility).
-- ai_persona is explicitly utf8mb4 so a Vietnamese persona stores correctly
-- regardless of the table's historical charset. Idempotent + MySQL 5.7-compatible
-- (mirrors 2026_06_04_add_profile_privacy.sql).

SET @bb_col_exists := (
  SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.COLUMNS
   WHERE TABLE_SCHEMA = DATABASE()
     AND TABLE_NAME = 'userStatus'
     AND COLUMN_NAME = 'ai_tone'
);
SET @bb_sql := IF(
  @bb_col_exists = 0,
  "ALTER TABLE userStatus ADD COLUMN ai_tone ENUM('formal','casual') NOT NULL DEFAULT 'formal' AFTER show_favorite_food",
  'SET @bb_noop := 1'
);
PREPARE bb_stmt FROM @bb_sql;
EXECUTE bb_stmt;
DEALLOCATE PREPARE bb_stmt;

SET @bb_col_exists := (
  SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.COLUMNS
   WHERE TABLE_SCHEMA = DATABASE()
     AND TABLE_NAME = 'userStatus'
     AND COLUMN_NAME = 'ai_persona'
);
SET @bb_sql := IF(
  @bb_col_exists = 0,
  'ALTER TABLE userStatus ADD COLUMN ai_persona VARCHAR(280) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL AFTER ai_tone',
  'SET @bb_noop := 1'
);
PREPARE bb_stmt FROM @bb_sql;
EXECUTE bb_stmt;
DEALLOCATE PREPARE bb_stmt;
