-- Migration: Add Remember-Me Tokens (persistent login)
-- Date: 2026-06-01
-- Description: Lưu token "ghi nhớ đăng nhập" theo cơ chế selector/validator để tự
--              đăng nhập lại sau khi session ngắn hạn hết hạn, giữ người dùng đăng
--              nhập tới 30 ngày. Cookie chứa "<selector>:<validator>"; CSDL chỉ lưu
--              selector dạng rõ và bản băm SHA-256 của validator nên rò rỉ DB không
--              thể tái sử dụng làm token đăng nhập.

CREATE TABLE IF NOT EXISTS `auth_token` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `user_id` INT(11) NOT NULL,
  `selector` CHAR(24) NOT NULL COMMENT 'Khóa tra cứu công khai (24 hex = 12 bytes)',
  `validator_hash` CHAR(64) NOT NULL COMMENT 'SHA-256 của validator bí mật',
  `expires` DATETIME NOT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `last_used_at` TIMESTAMP NULL DEFAULT NULL,
  `user_agent` VARCHAR(255) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_auth_token_selector` (`selector`),
  KEY `idx_auth_token_user` (`user_id`),
  KEY `idx_auth_token_expires` (`expires`),
  CONSTRAINT `fk_auth_token_user` FOREIGN KEY (`user_id`) REFERENCES `user`(`user_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Dọn token quá hạn theo lịch (tùy chọn). Việc tra cứu đã luôn lọc `expires > NOW()`
-- và xóa selector hỏng khi gặp, nên bảng tự co lại; dòng dưới chỉ để dọn chủ động:
-- DELETE FROM `auth_token` WHERE `expires` < NOW();
