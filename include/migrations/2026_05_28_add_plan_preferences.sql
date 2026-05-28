-- Migration: Goal Planner — persist user plan inputs
-- One row per user; upserted whenever they submit the Plan form.
-- Date: 2026-05-28

CREATE TABLE IF NOT EXISTS `user_plan_preferences` (
  `user_id`        INT(11)      NOT NULL,
  `goal_mode`      ENUM('lose','maintain','gain') NOT NULL DEFAULT 'lose',
  `weekly_rate`    DECIMAL(4,2) NOT NULL DEFAULT 0.25,
  `activity_level` VARCHAR(32)  NOT NULL DEFAULT 'moderately_active',
  `target_weight`  DECIMAL(5,1) DEFAULT NULL,
  `updated_at`     TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`user_id`),
  CONSTRAINT `fk_plan_prefs_user` FOREIGN KEY (`user_id`) REFERENCES `user`(`user_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
