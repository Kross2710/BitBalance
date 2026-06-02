-- Migration: PT goal proposals (Phase 1 - calorie only)
-- Date: 2026-06-02
-- Description: Cho phep PT de xuat muc tieu calo cho hoc vien; hoc vien chap nhan
--              thi muc tieu moi duoc ghi vao userGoal. Mo hinh dong thuan (consent):
--              PT de xuat -> hoc vien duyet. Moi cap (trainer, client) chi co mot
--              de xuat 'pending' tai mot thoi diem (de xuat moi lam cu thanh 'superseded').

CREATE TABLE IF NOT EXISTS `pt_goal_proposal` (
  `id`           INT(11) NOT NULL AUTO_INCREMENT,
  `trainer_id`   INT(11) NOT NULL,
  `client_id`    INT(11) NOT NULL,
  `calorie_goal` INT(11) NOT NULL,
  `note`         VARCHAR(255) NULL DEFAULT NULL,
  `status`       ENUM('pending','accepted','declined','superseded') NOT NULL DEFAULT 'pending',
  `created_at`   TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `responded_at` TIMESTAMP NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_client_status` (`client_id`, `status`),
  KEY `idx_trainer_client` (`trainer_id`, `client_id`),
  CONSTRAINT `fk_gp_trainer` FOREIGN KEY (`trainer_id`) REFERENCES `user`(`user_id`) ON DELETE CASCADE,
  CONSTRAINT `fk_gp_client`  FOREIGN KEY (`client_id`)  REFERENCES `user`(`user_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
