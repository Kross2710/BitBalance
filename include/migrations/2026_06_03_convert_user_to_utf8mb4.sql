-- Migration: convert the `user` table (and the rest of the user cluster) to utf8mb4.
-- Date: 2026-06-03
--
-- Why: the 2026-06-01 latin1->utf8mb4 migration converted 16 tables but MISSED the
--      `user` table itself. So user.first_name / user.last_name stayed latin1, and any
--      INSERT of a name containing Vietnamese diacritics or an emoji fails with
--      "Incorrect string value: '\xC6\xB0...' for column 'first_name'". That breaks
--      BOTH email/password signup AND Sign in with Google for anyone whose name (or
--      Google display name) isn't plain ASCII — the user just sees
--      "Something went wrong creating your account." (the catch in routes/auth.js).
--
-- Safe + idempotent: the app connects as utf8mb4, so CONVERT TO on an already-utf8mb4
--      table is a no-op, and existing ASCII data is preserved byte-for-byte. (Any
--      non-ASCII text previously written into a latin1 column was already corrupted at
--      write time and cannot be recovered by this migration.)
--
-- Index key length is fine: utf8mb4 varchar(100) email = 400 bytes, varchar(50)
--      user_name = 200 bytes, both well under the InnoDB limit.
--
-- Backup first on a populated DB:
--   mysqldump -u <user> -p <db> user userStatus userGoal userPhysicalInfo user_themes \
--             > backup_user_utf8mb4.sql

SET FOREIGN_KEY_CHECKS = 0;

ALTER TABLE `user`             CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
ALTER TABLE `userStatus`       CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
ALTER TABLE `userGoal`         CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
ALTER TABLE `userPhysicalInfo` CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
ALTER TABLE `user_themes`      CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
ALTER TABLE `login_attempts`   CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;

-- Verify (expect 0 rows):
-- SELECT table_name, table_collation FROM information_schema.tables
--  WHERE table_schema = DATABASE() AND table_collation NOT LIKE 'utf8mb4%'
--    AND table_name IN ('user','userStatus','userGoal','userPhysicalInfo','user_themes','login_attempts');
