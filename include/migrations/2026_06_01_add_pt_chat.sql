-- Migration: Two-way PT <-> Client messaging (Task #3)
-- Date: 2026-06-01
-- Description: Cho phép học viên trả lời/hỏi lại PT. Mô phỏng pattern
--   ai_conversation / ai_message: một thread cho mỗi cặp (trainer, client),
--   mỗi tin nhắn một dòng kèm vai trò người gửi.

-- 1. pt_thread: one conversation thread per trainer-client pair
CREATE TABLE IF NOT EXISTS `pt_thread` (
  `thread_id`  INT(11) NOT NULL AUTO_INCREMENT,
  `trainer_id` INT(11) NOT NULL,
  `client_id`  INT(11) NOT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`thread_id`),
  UNIQUE KEY `uk_pt_thread` (`trainer_id`, `client_id`),
  CONSTRAINT `fk_ptt_trainer` FOREIGN KEY (`trainer_id`) REFERENCES `user`(`user_id`) ON DELETE CASCADE,
  CONSTRAINT `fk_ptt_client`  FOREIGN KEY (`client_id`)  REFERENCES `user`(`user_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2. pt_message: one row per message; sender_role marks who wrote it.
--    seen_at is filled in by the reader (groundwork for Task #4 unread badges).
CREATE TABLE IF NOT EXISTS `pt_message` (
  `message_id`  INT(11) NOT NULL AUTO_INCREMENT,
  `thread_id`   INT(11) NOT NULL,
  `sender_role` ENUM('trainer','client') NOT NULL,
  `content`     TEXT NOT NULL,
  `created_at`  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `seen_at`     TIMESTAMP NULL DEFAULT NULL,
  PRIMARY KEY (`message_id`),
  KEY `idx_thread_created` (`thread_id`, `created_at`),
  CONSTRAINT `fk_ptm_thread` FOREIGN KEY (`thread_id`) REFERENCES `pt_thread`(`thread_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
