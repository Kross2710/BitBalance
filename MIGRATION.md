# BitBalance — Migration PHP → Express + Vue (SPA)

Tài liệu này theo dõi việc chuyển BitBalance từ PHP server-rendered sang
**Express.js (API) + Vue 3 SPA**, dùng **chung database MySQL hiện có**
(không migrate dữ liệu, chỉ thay tầng ứng dụng).

## Kiến trúc đích

```
client/  → Vue 3 SPA (Vite, vue-router, pinia). Điều hướng không reload trang.
server/  → Express API. Trả JSON theo envelope { ok, data, message }.
            Dùng lại nguyên schema MySQL của app PHP.
DB       → MySQL (giữ nguyên, không đổi).
```

Dev: client chạy ở `:5173`, gọi `/api/...` và Vite proxy sang Express `:3000`
→ same-origin, session cookie chạy ngon, không vướng CORS.

> **Ngôn ngữ thiết kế** của frontend (màu, component, icon, app shell responsive,
> pattern cho page mới): xem [`DESIGN.md`](./DESIGN.md). Đọc trước khi dựng view mới.

## Chạy thử (dev)

```bash
# 1) Backend
cd server
cp .env.example .env        # điền DB_PASSWORD + SESSION_SECRET
npm install
npm run dev                 # http://localhost:3000

# 2) Frontend (terminal khác)
cd client
npm install
npm run dev                 # http://localhost:5173
```

## Hợp đồng API (giữ nguyên với app PHP)

Mọi response: `{ "ok": bool, "data": any, "message": string|null }`.
Đây là đúng định dạng `api_send()` trong `api/_bootstrap.php`, nên logic client
gần như port 1-1.

## Trạng thái port

| Module | Endpoint PHP | Express | Vue | Ghi chú |
|---|---|---|---|---|
| Auth – login | `api/auth/login.php` | ✅ `POST /api/auth/login` | ✅ LoginView | Có port logic khoá tài khoản (3 lần sai → khoá 1h) |
| Auth – logout | `api/auth/logout.php` | ✅ `POST /api/auth/logout` | ✅ | |
| Auth – me | `api/me.php` | ✅ `GET /api/auth/me` | ✅ store | |
| Intake – history | `api/intake/history.php` | ✅ `GET /api/intake/history` | ✅ Dashboard | Kèm daily_summary + macro |
| Intake – create | `api/intake/create.php` | ✅ `POST /api/intake/create` | ✅ Dashboard | Kèm XP award + cập nhật streak + level-up flash |
| XP & Level | `include/handlers/xp.php` | ✅ `lib/xp.js` | ✅ Dashboard | Level curve, award theo state, milestone, finalize hôm qua |
| Logging streak | `updateLoggingStreak()` | ✅ `lib/streak.js` | ✅ Dashboard | Tăng/đóng băng/reset chuỗi |
| Auth – register | `api/auth/register.php` | ✅ `POST /api/auth/register` | ✅ SignupView | Tự sinh handle `Tên#1234`, auto-login |
| Onboarding | `api/onboarding/save.php` | ✅ `POST /api/onboarding/save` | ✅ OnboardingView | Port BMR/TDEE/macro + lưu transaction |
| Intake – update | `api/intake/update.php` | ✅ `POST /api/intake/update` | ✅ Dashboard | Sửa inline |
| Intake – delete | `api/intake/delete.php` | ✅ `POST /api/intake/delete` | ✅ Dashboard | Trả deleted_row cho Undo |
| Intake – suggest | `api/intake/suggest.php` | ✅ `GET /api/intake/suggest?q=` | ✅ IntakeView | Recent chips (món hay log) + autocomplete từ chính lịch sử user; macro lấy từ lần log gần nhất |
| Intake – barcode | `lookup_barcode.php` | ⬜ | ⬜ | gọi API barcode ngoài |
| Intake – page | `intake.php` (trang Food Intake) | — | ✅ IntakeView (`/intake`) | Trang log food hạng nhất: input lớn + recent chips + meal theo giờ + macros optional + Log Entry full-width. Chưa có: Scan Barcode, AI Photo |
| Dashboard – day | `api/dashboard/day.php` | ✅ `GET /api/dashboard/day?date=` | ✅ Dashboard | Điều hướng ngày, BMI, focus, biểu đồ 7 ngày, theo bữa, XP/level thật |
| Dashboard – summary | `api/dashboard/summary.php` | ✅ `GET /api/dashboard/summary` | — | Snapshot hôm nay, XP/level thật |
| Profile – get | `api/profile/get.php` | ✅ `GET /api/profile` | ✅ ProfileView | Payload `{user, bio, status, goal, physical}` |
| Profile – update | `api/profile/update.php` | ✅ `POST /api/profile/update` | ✅ ProfileView | Account/bio/theme/goal/physical trong 1 transaction; check trùng email/handle; đồng bộ session. Chưa port: upload ảnh + đổi ngôn ngữ + `log_attempt` (legacy update.php cũng không xử lý ảnh/ngôn ngữ) |
| AI Coach – conversations | `api/ai-coach/conversations.php` | ✅ `GET /api/ai-coach/conversations` | ✅ CoachView | List 100 hội thoại mới nhất |
| AI Coach – messages | `api/ai-coach/messages.php` | ✅ `GET /api/ai-coach/messages?conversation_id=` | ✅ CoachView | Trả conversation + messages, unpack food-log suggestions |
| AI Coach – send | `api/ai-coach/send.php` | ✅ `POST /api/ai-coach/send` | ✅ CoachView | Rate limit ngày, build context, gọi LLM, tách `[[FOOD_LOG]]`, auto-title, bump usage |
| AI Coach – delete | `api/ai-coach/delete.php` | ✅ `POST /api/ai-coach/delete` | ✅ CoachView | Xoá hội thoại (ai_message cascade qua FK) |
| AI Coach – provider | `call_gemini()` | ✅ `lib/aiProvider.js` | — | Trừu tượng hoá: `AI_PROVIDER=gemini\|openrouter` chọn qua env. Chưa port: **upload ảnh/vision** (text-only v1) — xem nợ kỹ thuật |
| Social/Friends | `api/social/action.php` | ⬜ | ⬜ | |
| Admin panel | `admin/*.php` | ⬜ | ⬜ | module riêng, có auth riêng |
| Captcha | `captcha_image.php` (GD) | ⬜ | ⬜ | thay bằng svg-captcha (Node) |
| App shell / nav | `dashboard/views/sidebar.php` | — | ✅ AppLayout | Sidebar (desktop, hover mở rộng) + bottom tab (mobile), icon Font Awesome 6 |

> **Forum**: bỏ hoàn toàn theo yêu cầu — không port (đang là dead code bên PHP).

## TODO / nợ kỹ thuật cần xử lý khi tiếp tục

- [x] **XP + logging streak**: đã port `include/handlers/xp.php` (`lib/xp.js`) +
      `updateLoggingStreak()` (`lib/streak.js`). Còn nợ nhỏ: chưa ghi `log_attempt`
      (activity_log) cho sự kiện streak/award — chỉ là audit, không ảnh hưởng hành vi.
      `xp_award_weight_log` đã port nhưng chưa được gọi (chờ endpoint weight-log).
- [ ] **Remember-me token**: port `include/handlers/remember_token.php` + bảng token
      (login hiện chưa cấp cookie ghi nhớ dài hạn).
- [ ] **Session store production**: thay MemoryStore của express-session bằng
      store bền (Redis hoặc MySQL session store).
- [ ] **CSRF**: app PHP có `include/csrf.php`. SPA dùng cookie → cân nhắc
      double-submit token hoặc SameSite=strict cho các mutation.
- [ ] **AI Coach vision (ảnh)**: bản port hiện chỉ text. PHP `send.php` nhận
      multipart `image`, lưu `images/ai_coach/{userId}/`, gửi base64 inline cho
      Gemini, và `delete.php` xoá ảnh trên đĩa. Cần thêm: multer cho upload,
      static serve thư mục ảnh, nhánh image trong `lib/aiProvider.js` (Gemini
      `inline_data` / OpenRouter `image_url`), và cleanup ảnh khi xoá hội thoại.
- [ ] **Captcha** signup/login: thay GD image bằng thư viện Node.
- [ ] **Password hash**: PHP dùng `password_hash` (bcrypt `$2y$`). `bcryptjs`
      verify được hash `$2y$` sẵn có — đăng ký mới cũng dùng bcryptjs để đồng nhất.
- [ ] **i18n**: app PHP có cơ chế i18n + test parity (`tests/framework/I18nParity.php`).
- [ ] **Deploy**: môi trường cần Node runtime (RMIT chỉ có PHP/Apache → cần host khác
      cho phần Node, hoặc giữ PHP chạy song song trong giai đoạn chuyển tiếp).

## Chiến lược chuyển tiếp (strangler pattern)

Port dần từng module; module nào chưa port vẫn để PHP chạy. Reverse proxy định
tuyến: `/api/v2/*` → Express, phần còn lại → PHP, cho tới khi port hết.
