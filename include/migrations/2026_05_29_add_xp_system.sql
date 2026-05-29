-- Migration: XP & Level system (PR1 — MVP + goal-hit)
-- Date: 2026-05-29
--
-- user_xp:   snapshot read-fast for header bar (1 row per user).
-- xp_event:  immutable ledger of every award. Daily caps are enforced by
--            counting rows in this table vs. COUNT() on the source table
--            (see include/handlers/xp.php — state-based award logic).

CREATE TABLE IF NOT EXISTS `user_xp` (
  `user_id`          INT(11)   NOT NULL,
  `total_xp`         INT(11)   NOT NULL DEFAULT 0,
  `current_level`    INT(11)   NOT NULL DEFAULT 1,
  `last_level_up_at` TIMESTAMP NULL DEFAULT NULL,
  `last_finalized_date` DATE   DEFAULT NULL,
  `updated_at`       TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`user_id`),
  CONSTRAINT `fk_user_xp_user` FOREIGN KEY (`user_id`) REFERENCES `user`(`user_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `xp_event` (
  `event_id`   INT(11)     NOT NULL AUTO_INCREMENT,
  `user_id`    INT(11)     NOT NULL,
  `source`     VARCHAR(40) NOT NULL,
  `amount`     INT(11)     NOT NULL,
  `ref_table`  VARCHAR(40) DEFAULT NULL,
  `ref_id`     INT(11)     DEFAULT NULL,
  `created_at` TIMESTAMP   NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`event_id`),
  KEY `idx_user_date` (`user_id`, `created_at`),
  KEY `idx_user_source_date` (`user_id`, `source`, `created_at`),
  CONSTRAINT `fk_xp_event_user` FOREIGN KEY (`user_id`) REFERENCES `user`(`user_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Backfill: give every existing user a user_xp row at level 1 with 0 XP.
INSERT INTO `user_xp` (`user_id`, `total_xp`, `current_level`)
SELECT u.`user_id`, 0, 1
FROM `user` u
LEFT JOIN `user_xp` ux ON ux.`user_id` = u.`user_id`
WHERE ux.`user_id` IS NULL;
