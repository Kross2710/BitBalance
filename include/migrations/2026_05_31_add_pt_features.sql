-- Migration: Add PT Features and Meal Photo Storage
-- Date: 2026-05-31
-- Description: Mở rộng cơ sở dữ liệu để hỗ trợ vai trò huấn luyện viên (PT), lưu trữ hình ảnh đồ ăn và quản lý mối quan hệ PT-Học viên.

-- 1. Thêm vai trò 'pt' vào bảng user
ALTER TABLE `user` MODIFY COLUMN `role` ENUM('regular', 'admin', 'pt') NOT NULL DEFAULT 'regular';

-- 2. Thêm cột lưu đường dẫn ảnh đồ ăn thực tế vào nhật ký ăn uống
ALTER TABLE `intakeLog` ADD COLUMN `image_path` VARCHAR(255) DEFAULT NULL AFTER `fat`;

-- 3. Tạo bảng liên kết Trainer - Client
CREATE TABLE IF NOT EXISTS `trainer_client` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `trainer_id` INT(11) NOT NULL,
  `client_id` INT(11) NOT NULL,
  `status` ENUM('pending', 'accepted', 'rejected', 'terminated') NOT NULL DEFAULT 'pending',
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `responded_at` TIMESTAMP NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_trainer_client` (`trainer_id`, `client_id`),
  CONSTRAINT `fk_tc_trainer` FOREIGN KEY (`trainer_id`) REFERENCES `user`(`user_id`) ON DELETE CASCADE,
  CONSTRAINT `fk_tc_client` FOREIGN KEY (`client_id`) REFERENCES `user`(`user_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 4. Tạo bảng lưu trữ phản hồi của PT cho từng ngày của học viên
CREATE TABLE IF NOT EXISTS `pt_feedback` (
  `feedback_id` INT(11) NOT NULL AUTO_INCREMENT,
  `trainer_id` INT(11) NOT NULL,
  `client_id` INT(11) NOT NULL,
  `date_for` DATE NOT NULL,
  `content` TEXT NOT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`feedback_id`),
  UNIQUE KEY `uk_trainer_client_date` (`trainer_id`, `client_id`, `date_for`),
  CONSTRAINT `fk_pf_trainer` FOREIGN KEY (`trainer_id`) REFERENCES `user`(`user_id`) ON DELETE CASCADE,
  CONSTRAINT `fk_pf_client` FOREIGN KEY (`client_id`) REFERENCES `user`(`user_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
