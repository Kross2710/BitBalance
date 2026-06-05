# CLAUDE.md — Project memory

## Trọng tâm hiện tại (ưu tiên số 1)
- **Dịch dự án PHP → Vue/Express** (xem `MIGRATION.md`, `HANDOFF.md`). Mọi tính
  năng mới ưu tiên làm trên stack Vue/Express, không đắp thêm vào PHP trừ khi
  có lý do rõ ràng.
- **UI/UX user-oriented** là tiêu chí dẫn dắt: tận dụng đúng chỗ Vue vượt trội so
  với PHP (SPA, reactivity, transition/animation, state giữ qua điều hướng,
  micro-interaction, component tái dùng). Khi cân nhắc giải pháp, ưu tiên trải
  nghiệm người dùng mượt/đã hơn là port 1-1 máy móc từ PHP.

## Design language (đồng nhất theo Vue)
- **Chuẩn DUY NHẤT** = `DESIGN.md` + design tokens trong `BitBalance-App/client/src/styles.css`.
  Đọc trước khi dựng bất kỳ view/mockup nào.
- Tông Vue: **dark, phẳng, gọn** — accent `#4ade80`, viền `1px var(--border)`
  (KHÔNG shadow chunky `0 4px 0`), padding gọn, `max-width: 820px`, icon Font
  Awesome `fa-solid` (**không emoji**).
- **Đừng trôi về phong cách PHP/beats** (`dashboard-beats.php`): Duolingo sáng
  `#58CC02`/`#FF9600`, shadow 3D, padding to, scroll dài, emoji. Xem mục
  "Anti-patterns" trong `DESIGN.md`.

## Ghi chú khác
- Ý tưởng minigame 2048 mới ở mức **khảo sát** (chưa code) — nếu làm thì theo
  Vue/Express, nhúng dữ liệu nutrition thật + nối XP/streak/collection sẵn có,
  và **phải** theo design language Vue ở trên (không dựng kiểu beats).
- Tài liệu chính: `HANDOFF.md` (resume), `MIGRATION.md` (trạng thái port),
  `DESIGN.md` (design system).
