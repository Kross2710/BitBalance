# Idea: Quick-reply trên thẻ học viên (PT dashboard)

> Trạng thái: **đề xuất, chưa làm** (deferred 2026-06-01). Ghi lại để sau triển khai.

## Mục tiêu
Cho PT trả lời **1 câu ngay trên thẻ học viên** ở [dashboard-pt.php](../dashboard/dashboard-pt.php),
không cần mở drawer/sheet. Giảm bước cho tác vụ hay làm nhất: "có tin → rep nhanh".

## Hiện ở đâu
Chỉ trên `client-card` **có tin chưa đọc** (đang có badge `.client-card__unread`).
Thẻ không có tin chưa đọc thì **không** hiện (giữ lưới gọn).

Một dải nhỏ mọc ở đáy card:
- (Bản đầy đủ) dòng preview tin cuối của client, rút gọn 1 dòng.
- Ô input nhỏ + nút gửi: `[ Trả lời nhanh… ] (➤)`

## Flow
1. PT mở dashboard → thẻ client có badge + dải quick-reply.
2. PT gõ → Enter (hoặc bấm ➤).
3. JS POST thẳng tới `dashboard/handlers/pt_chat.php` với
   `action=send`, `counterpart_id=<client_id>`, `content`, `csrf_token`
   — **đúng endpoint chat hiện tại, không đổi backend.**
4. Thành công:
   - ô clear, dải đổi "Đã gửi ✓" (hoặc append nhẹ).
   - **xoá badge thẻ + giảm hero "tin mới"** → tái dùng `clearCardUnread()` đã có.
   - đánh dấu `seen_at` cho tin client trong thread đó (gửi rep = đã đọc).
5. Muốn xem cả hội thoại / nhật ký → vẫn bấm thẻ mở drawer như thường.
   Quick-reply chỉ là lối tắt 1 câu.

## Tận dụng hạ tầng sẵn có
- **Backend: 0 thay đổi** — `pt_chat.php` `send` đã nhận `counterpart_id/content/csrf`,
  trả `{ok, message:{sender_role,content,created_at}}`.
- **Frontend**: dùng lại pattern của `PTChat` (đã có `notify()` toast, chống double-send, CSRF),
  hoặc 1 hàm `quickReply(cardEl)` gọn.
- **Dữ liệu preview tin cuối** (chỉ bản đầy đủ): cần thêm 1 query lấy *tin cuối của client*
  mỗi thread (rẻ — group theo client), shape `$clientLastMsg[client_id] = {content, sender_role}`.

## Điểm cần chốt khi làm
1. **Mark seen khi quick-reply?** → Đề xuất: có (gửi rep = đã đọc), mark toàn bộ tin client
   trong thread. Nhất quán với "mở thẻ thì mark seen".
   - Lưu ý: hiện `pt_chat.php` chỉ mark seen ở `action=fetch`. Quick-reply cần mark seen ở
     phía `send` (hoặc gọi fetch ngầm).
2. **Bản đầy đủ (có preview tin cuối) vs tối giản (chỉ ô input)?**
   - Đầy đủ: PT biết đang trả lời gì, nhưng +1 query và +1 dòng/card.
   - Tối giản: nhẹ, ít rối lưới.
3. **Enter để gửi** (giống widget chat) + giữ **guard IME tiếng Việt** (`isComposing`/keyCode 229)
   để Enter không gửi giữa lúc gõ Telex.

## Liên quan
- Pattern chat dùng chung: [js/pt-chat.js](../js/pt-chat.js), [pt_chat.php](../dashboard/handlers/pt_chat.php).
- Badge chưa đọc + hero tally: xem `clientUnread` / `clearCardUnread()` trong dashboard-pt.php.
- Roadmap tổng: memory `pt-interaction-roadmap`.
