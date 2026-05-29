-- Migration: enforce unique usernames
-- Date: 2026-05-29
--
-- Background: `user.user_name` had NO unique constraint — only an app-level
-- check in signup (race-prone). With the Friends feature (search/add by
-- username) duplicates are confusing. This migration:
--   1. De-duplicates existing usernames (case-insensitive — the column collation
--      is latin1_swedish_ci, so "Bob" and "bob" collide under the unique index).
--   2. Adds a UNIQUE KEY on user_name.
--
-- ⚠️ RUN ORDER MATTERS. Step 1 must succeed before step 2, or the ALTER fails.
-- Run on the RMIT DB via SSH (see AGENTS.md). Safe to re-run: step 1 is a no-op
-- once there are no duplicates.

-- (Optional) inspect duplicates first:
--   SELECT LOWER(user_name) AS name, COUNT(*) c, GROUP_CONCAT(user_id) ids
--   FROM `user` GROUP BY LOWER(user_name) HAVING c > 1;

-- Step 1 — keep the lowest user_id per (case-insensitive) name; rename the rest
-- by appending "_<user_id>" (kept within the varchar(50) limit via LEFT(...,40)).
UPDATE `user` u
JOIN (
    SELECT MIN(user_id) AS keep_id, LOWER(user_name) AS lname
    FROM `user`
    GROUP BY LOWER(user_name)
    HAVING COUNT(*) > 1
) d ON LOWER(u.user_name) = d.lname AND u.user_id <> d.keep_id
SET u.user_name = CONCAT(LEFT(u.user_name, 40), '_', u.user_id);

-- Step 2 — enforce uniqueness going forward.
ALTER TABLE `user`
  ADD UNIQUE KEY `uk_user_name` (`user_name`);
