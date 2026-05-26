-- Migration: AI Coach feature
-- Adds tables for conversation history and per-user daily rate limit
-- Date: 2026-05-26

-- ---------------------------------------------------------
-- ai_conversation: one row per chat thread
-- ---------------------------------------------------------
CREATE TABLE IF NOT EXISTS `ai_conversation` (
  `conversation_id` INT(11) NOT NULL AUTO_INCREMENT,
  `user_id`         INT(11) NOT NULL,
  `title`           VARCHAR(120) NOT NULL DEFAULT 'New chat',
  `created_at`      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`conversation_id`),
  KEY `idx_user_updated` (`user_id`, `updated_at`),
  CONSTRAINT `fk_ai_conv_user` FOREIGN KEY (`user_id`) REFERENCES `user`(`user_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------
-- ai_message: one row per message (user or assistant)
-- ---------------------------------------------------------
CREATE TABLE IF NOT EXISTS `ai_message` (
  `message_id`      INT(11) NOT NULL AUTO_INCREMENT,
  `conversation_id` INT(11) NOT NULL,
  `role`            ENUM('user','assistant') NOT NULL,
  `content`         TEXT NOT NULL,
  `image_path`      VARCHAR(500) DEFAULT NULL,
  `created_at`      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`message_id`),
  KEY `idx_conv_created` (`conversation_id`, `created_at`),
  CONSTRAINT `fk_ai_msg_conv` FOREIGN KEY (`conversation_id`) REFERENCES `ai_conversation`(`conversation_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------
-- ai_usage_daily: rate-limit counter, one row per (user, day)
-- ---------------------------------------------------------
CREATE TABLE IF NOT EXISTS `ai_usage_daily` (
  `usage_id`      INT(11) NOT NULL AUTO_INCREMENT,
  `user_id`       INT(11) NOT NULL,
  `usage_date`    DATE NOT NULL,
  `message_count` INT(11) NOT NULL DEFAULT 0,
  PRIMARY KEY (`usage_id`),
  UNIQUE KEY `uk_user_date` (`user_id`, `usage_date`),
  CONSTRAINT `fk_ai_usage_user` FOREIGN KEY (`user_id`) REFERENCES `user`(`user_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
