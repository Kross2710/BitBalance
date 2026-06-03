-- Migration: add show_favorite_food toggle to userStatus
-- Date: 2026-06-04
--
-- Per-user control over whether the "favorite food" record appears on the
-- public profile peek (Vue /friends profile sheet). The profile_visibility
-- column (private/friends/public) already exists from the friends system
-- migration; this adds a finer-grained opt-out for the single semi-personal
-- field. Default 1 = shown (users opt out), so existing rows keep showing it.
-- Idempotent + MySQL 5.7-compatible (mirrors 2026_06_04_add_user_timezone.sql).

SET @bb_col_exists := (
  SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.COLUMNS
   WHERE TABLE_SCHEMA = DATABASE()
     AND TABLE_NAME = 'userStatus'
     AND COLUMN_NAME = 'show_favorite_food'
);

SET @bb_sql := IF(
  @bb_col_exists = 0,
  'ALTER TABLE userStatus ADD COLUMN show_favorite_food TINYINT(1) NOT NULL DEFAULT 1 AFTER profile_visibility',
  'SET @bb_noop := 1'
);

PREPARE bb_stmt FROM @bb_sql;
EXECUTE bb_stmt;
DEALLOCATE PREPARE bb_stmt;
