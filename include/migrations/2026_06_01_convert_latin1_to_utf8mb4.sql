-- Migration: Convert remaining latin1 tables to utf8mb4
-- Date: 2026-06-01
-- Description: Audit ngày 2026-06-01 phát hiện 16/44 bảng vẫn dùng latin1_swedish_ci
--              (do server MySQL mặc định charset latin1). Chữ tiếng Việt nhập vào các
--              bảng này (forum, sản phẩm, mục tiêu, trạng thái...) sẽ bị mất/hỏng font.
--              Migration này chuyển cả 16 bảng sang utf8mb4 / utf8mb4_unicode_ci cho
--              đồng nhất với phần còn lại của CSDL.
--
-- An toàn dữ liệu: app luôn kết nối bằng charset=utf8mb4, nên dữ liệu ASCII hiện có
--              được chuyển nguyên vẹn. Dữ liệu non-ASCII (nếu từng nhập) đã bị mất ngay
--              khi lưu vào cột latin1 và KHÔNG khôi phục được bằng lệnh này.
--              => Hãy backup trước khi chạy:
--              mysqldump -h talsprddb02.int.its.rmit.edu.au -u COSC3046_2502_G20 -p \
--                        COSC3046_2502_G20 > backup_before_utf8mb4.sql
--
-- Cách chạy (SSH vào RMIT shell trước):
--   mysql -h talsprddb02.int.its.rmit.edu.au -u COSC3046_2502_G20 -p COSC3046_2502_G20 \
--         < include/migrations/2026_06_01_convert_latin1_to_utf8mb4.sql

SET FOREIGN_KEY_CHECKS = 0;

ALTER TABLE `countries`        CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
ALTER TABLE `elements`         CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
ALTER TABLE `forumComment`     CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
ALTER TABLE `forumLike`        CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
ALTER TABLE `forumPost`        CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
ALTER TABLE `login_attempts`   CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
ALTER TABLE `order`            CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
ALTER TABLE `order_item`       CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
ALTER TABLE `product`          CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
ALTER TABLE `productCart`      CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
ALTER TABLE `productCart_item` CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
ALTER TABLE `site_fees`        CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
ALTER TABLE `userGoal`         CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
ALTER TABLE `userPhysicalInfo` CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
ALTER TABLE `userStatus`       CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
ALTER TABLE `user_themes`      CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;

-- Kiểm tra sau khi chạy (kỳ vọng: trả về 0 dòng):
-- SELECT table_name, table_collation FROM information_schema.tables
--  WHERE table_schema = DATABASE() AND table_collation NOT LIKE 'utf8mb4%';
