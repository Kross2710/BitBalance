-- Migration: ensure userStatus.language_preference exists
-- Date: 2026-06-03
--
-- Some cloned/dev databases can have schema_migrations recording
-- 2026_05_29_user_language_preference.sql even when the actual column is
-- missing. Keep this repair migration idempotent and MySQL 5.7-compatible.

SET @bb_col_exists := (
  SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.COLUMNS
   WHERE TABLE_SCHEMA = DATABASE()
     AND TABLE_NAME = 'userStatus'
     AND COLUMN_NAME = 'language_preference'
);

SET @bb_sql := IF(
  @bb_col_exists = 0,
  'ALTER TABLE userStatus ADD COLUMN language_preference VARCHAR(8) NOT NULL DEFAULT ''en'' AFTER theme_preference',
  'SELECT 1'
);

PREPARE bb_stmt FROM @bb_sql;
EXECUTE bb_stmt;
DEALLOCATE PREPARE bb_stmt;

UPDATE userStatus
   SET language_preference = 'en'
 WHERE language_preference IS NULL OR language_preference = '';
