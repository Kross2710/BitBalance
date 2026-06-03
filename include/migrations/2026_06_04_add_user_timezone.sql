-- Migration: add per-user IANA time zone to userStatus
-- Date: 2026-06-04
--
-- Stores the user's browser-resolved IANA zone (e.g. 'Asia/Ho_Chi_Minh',
-- 'Australia/Sydney'). The Express API derives a per-request minute shift from
-- this to reinterpret the +07:00-stored intakeLog/xp_event datetimes in the
-- user's local day. Default 'Asia/Ho_Chi_Minh' (= legacy +07:00) so every
-- existing row keeps today's exact behaviour (shift 0). Lives on userStatus
-- alongside theme_preference / language_preference. Idempotent + MySQL
-- 5.7-compatible (mirrors 2026_06_03_ensure_language_preference.sql).

SET @bb_col_exists := (
  SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.COLUMNS
   WHERE TABLE_SCHEMA = DATABASE()
     AND TABLE_NAME = 'userStatus'
     AND COLUMN_NAME = 'time_zone'
);

SET @bb_sql := IF(
  @bb_col_exists = 0,
  'ALTER TABLE userStatus ADD COLUMN time_zone VARCHAR(64) NOT NULL DEFAULT ''Asia/Ho_Chi_Minh'' AFTER language_preference',
  'SET @bb_noop := 1'
);

PREPARE bb_stmt FROM @bb_sql;
EXECUTE bb_stmt;
DEALLOCATE PREPARE bb_stmt;

UPDATE userStatus
   SET time_zone = 'Asia/Ho_Chi_Minh'
 WHERE time_zone IS NULL OR time_zone = '';
