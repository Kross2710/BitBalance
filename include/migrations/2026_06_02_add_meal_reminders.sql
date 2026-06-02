-- Migration: Add Meal Reminders
-- Date: 2026-06-02
-- Description: Tùy chọn nhắc ghi nhật ký bữa ăn theo từng người dùng. Một dòng/
--              người: công tắc tổng + bật/giờ cho từng bữa (breakfast/lunch/
--              dinner/snack). Tầng Vue/Express dùng để nhắc in-app khi mở app
--              (so giờ hiện tại với giờ nhắc + bữa chưa log). Push nền (Service
--              Worker / Web Push) là việc về sau, không thuộc bảng này.

CREATE TABLE IF NOT EXISTS `meal_reminder` (
  `user_id` INT(11) NOT NULL,
  `enabled` TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'Công tắc tổng cho mọi nhắc nhở',
  `breakfast_enabled` TINYINT(1) NOT NULL DEFAULT 1,
  `breakfast_time` TIME NOT NULL DEFAULT '08:30:00',
  `lunch_enabled` TINYINT(1) NOT NULL DEFAULT 1,
  `lunch_time` TIME NOT NULL DEFAULT '12:30:00',
  `dinner_enabled` TINYINT(1) NOT NULL DEFAULT 1,
  `dinner_time` TIME NOT NULL DEFAULT '19:00:00',
  `snack_enabled` TINYINT(1) NOT NULL DEFAULT 0,
  `snack_time` TIME NOT NULL DEFAULT '16:00:00',
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`user_id`),
  CONSTRAINT `fk_meal_reminder_user` FOREIGN KEY (`user_id`) REFERENCES `user`(`user_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
