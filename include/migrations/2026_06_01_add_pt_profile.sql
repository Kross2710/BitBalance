-- Migration: PT self-serve onboarding profile
-- Date: 2026-06-01
-- Description: Thay vì bật role='pt' bằng 1 click, user phải điền hồ sơ HLV
--   (bio, chuyên môn, kinh nghiệm, sức chứa, đồng ý điều khoản) trước. Hồ sơ này
--   cũng hiển thị cho học viên khi tìm/kết nối, và feed vào PT dashboard.

CREATE TABLE IF NOT EXISTS `pt_profile` (
  `user_id`          INT(11) NOT NULL,
  `bio`              TEXT DEFAULT NULL,
  `specialties`      VARCHAR(255) DEFAULT NULL,
  `experience_years` INT(11) NOT NULL DEFAULT 0,
  `max_clients`      INT(11) NOT NULL DEFAULT 10,
  `accepted_terms`   TINYINT(1) NOT NULL DEFAULT 0,
  `created_at`       TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`       TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`user_id`),
  CONSTRAINT `fk_ptp_user` FOREIGN KEY (`user_id`) REFERENCES `user`(`user_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
