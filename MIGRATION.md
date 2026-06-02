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
| Intake – update | `api/intake/update.php` | ✅ `POST /api/intake/update` | ✅ IntakeView | Sửa inline trong "Today's entries" (đã chuyển khỏi Dashboard) |
| Intake – delete | `api/intake/delete.php` | ✅ `POST /api/intake/delete` | ✅ IntakeView | Xoá trong "Today's entries"; trả deleted_row cho Undo |
| Intake – suggest | `api/intake/suggest.php` | ✅ `GET /api/intake/suggest?q=` | ✅ IntakeView | Recent chips (món hay log) + autocomplete từ chính lịch sử user; macro lấy từ lần log gần nhất |
| Intake – barcode | `api/intake/lookup_barcode.php` | ✅ `POST /api/intake/lookup-barcode` | ✅ IntakeView | Cache `barcode_products` → OpenFoodFacts fallback → ghi `barcode_scan_log`. UI: native BarcodeDetector (Android) → **ZXing** fallback (iOS Safari, lazy-load) → nhập tay |
| Intake – AI photo | `dashboard/handlers/ai_chat.php` (nhánh ảnh) | ✅ `POST /api/intake/estimate-photo` | ✅ IntakeView | multer upload → vision (Gemini/OpenRouter) → ước lượng `{food_name,calories,P/C/F,advice}` → prefill form. Chọn từ **thư viện** (bỏ `capture`) + nén ảnh client-side (canvas, ≤1600px) + **preview** ảnh đã chọn. **Lưu ảnh** vào `server/uploads/intake/` (serve `/api/uploads/*`), trả `image_path`, lưu vào `intakeLog.image_path` khi log → Dashboard hiện thumbnail + lightbox xem lại |
| Intake – page | `intake.php` (trang Food Intake) | — | ✅ IntakeView (`/intake`) | Trang log food hạng nhất: input lớn + recent chips + autocomplete + **Scan Barcode** + **AI Photo** + **meal segmented cards** (4 pill, default theo giờ, hiện kcal đã log/bữa hôm nay) + macros optional + Log Entry full-width + **"Today's entries"** (sửa/xoá inline, thumbnail) |
| Dashboard – day | `api/dashboard/day.php` | ✅ `GET /api/dashboard/day?date=` | ✅ Dashboard | Overview: BMI, **Focus tile** (macro cần bù), 7-day avg, biểu đồ 7 ngày, theo bữa, XP/level. Entries hiển thị **read-only** (thumbnail + lightbox) — quản lý chuyển sang Intake |
| Dashboard – summary | `api/dashboard/summary.php` | ✅ `GET /api/dashboard/summary` | — | Snapshot hôm nay, XP/level thật |
| Profile – get | `api/profile/get.php` | ✅ `GET /api/profile` | ✅ ProfileView | Payload `{user, bio, status, goal, physical}` |
| Profile – update | `api/profile/update.php` | ✅ `POST /api/profile/update` | ✅ ProfileView | Account/bio/theme/goal/physical trong 1 transaction; check trùng email/handle; đồng bộ session. Chưa port: upload ảnh + đổi ngôn ngữ + `log_attempt` (legacy update.php cũng không xử lý ảnh/ngôn ngữ) |
| AI Coach – conversations | `api/ai-coach/conversations.php` | ✅ `GET /api/ai-coach/conversations` | ✅ CoachView | List 100 hội thoại mới nhất |
| AI Coach – messages | `api/ai-coach/messages.php` | ✅ `GET /api/ai-coach/messages?conversation_id=` | ✅ CoachView | Trả conversation + messages, unpack food-log suggestions |
| AI Coach – send | `api/ai-coach/send.php` | ✅ `POST /api/ai-coach/send` | ✅ CoachView | Rate limit ngày, build context, gọi LLM, tách `[[FOOD_LOG]]`, auto-title, bump usage |
| AI Coach – delete | `api/ai-coach/delete.php` | ✅ `POST /api/ai-coach/delete` | ✅ CoachView | Xoá hội thoại (ai_message cascade qua FK) |
| AI Coach – provider | `call_gemini()` | ✅ `lib/aiProvider.js` | — | Trừu tượng hoá: `AI_PROVIDER=gemini\|openrouter` chọn qua env. Chưa port: **upload ảnh/vision** (text-only v1) — xem nợ kỹ thuật |
| Meal reminders | — (mới) | ✅ `GET/POST /api/reminders` | ✅ Profile + Dashboard | Bảng `meal_reminder` (1 dòng/user: công tắc tổng + bật/giờ từng bữa). Profile có section "Meal reminders"; Dashboard hiện **nudge in-app** khi giờ bữa đã qua mà chưa log (so giờ Asia/Bangkok, bỏ qua nếu đã dismiss trong ngày — localStorage). Push nền (Service Worker/Web Push) là việc sau |
| Social/Friends | `api/social/action.php` | ✅ `/api/social/*` | ✅ FriendsView | `poll`/`leaderboard`/`pending-count` (GET) + `search`/`send`/`accept`/`reject`/`cancel`/`unfriend` (POST). Cap 20 lời mời pending/24h; upsert lại row rejected/cancelled; weekly_xp tính từ `xp_event` 7 ngày. UI 4 tab (Friends/Pending/Ranks/Find), poll 15s khi mở tab, tab nav thứ 5 + badge lời mời đến. Chưa port: **block** + enforce `profile_visibility` (PHP cũng chưa enforce); `log_attempt` audit (đồng bộ với các route khác) |
| Admin panel | `admin/*.php` | ⬜ | ⬜ | module riêng, có auth riêng |
| Captcha | `captcha_image.php` (GD) | ⬜ | ⬜ | thay bằng svg-captcha (Node) |
| App shell / nav | `dashboard/views/sidebar.php` | — | ✅ AppLayout | Sidebar (desktop, hover mở rộng) + bottom tab (mobile) **4 tab** (Home/Intake/Coach/Friends), icon Font Awesome 6. Topbar = logo "BitBalance" (trái) + avatar (phải, vào Profile); Log out đã chuyển vào Profile; greeting "Hi, {name}" nằm ở Dashboard |

> **Forum**: bỏ hoàn toàn theo yêu cầu — không port (đang là dead code bên PHP).

## TODO / nợ kỹ thuật cần xử lý khi tiếp tục

- [x] **XP + logging streak**: đã port `include/handlers/xp.php` (`lib/xp.js`) +
      `updateLoggingStreak()` (`lib/streak.js`). Còn nợ nhỏ: chưa ghi `log_attempt`
      (activity_log) cho sự kiện streak/award — chỉ là audit, không ảnh hưởng hành vi.
      `xp_award_weight_log` đã port nhưng chưa được gọi (chờ endpoint weight-log).
- [x] **Remember-me token**: đã port `include/handlers/remember_token.php` →
      `server/src/lib/remember.js` (selector/validator, bảng `auth_token` có sẵn).
      Login nhận cờ `remember` → cấp cookie `bb_remember` 30 ngày; middleware
      tự đăng nhập lại khi session hết hạn; logout thu hồi token + xoá cookie.
      UI: checkbox "Keep me signed in for 30 days" ở Login + nút Log out trong
      Profile. Còn nợ nhỏ: "Log out of all devices" (revoke_all đã có sẵn hàm).
- [ ] **Log out of all devices**: gắn UI gọi revoke-all (hàm thu hồi mọi
      `auth_token` của user đã có sẵn trong `lib/remember.js`) — ví dụ nút trong
      Profile + endpoint `POST /api/auth/logout-all`. Backlog từ nhát auth bundle.
- [ ] **Session store production**: thay MemoryStore của express-session bằng
      store bền (Redis hoặc MySQL session store).
- [ ] **CSRF**: app PHP có `include/csrf.php`. SPA dùng cookie → cân nhắc
      double-submit token hoặc SameSite=strict cho các mutation.
- [ ] **AI Coach chat vision (ảnh)**: `lib/aiProvider.js` ĐÃ hỗ trợ ảnh (Gemini
      `inline_data` / OpenRouter `image_url`) và `/api/intake/estimate-photo` đã
      dùng. Còn nợ: gắn ảnh vào **chat** AI Coach (`routes/aiCoach.js` `send`) như
      PHP `send.php` — multer upload + lưu `images/ai_coach/{userId}/` + static
      serve + cleanup ảnh khi xoá hội thoại (estimate-photo hiện không lưu ảnh).
- [x] **Barcode iOS**: ĐÃ thêm ZXing (`@zxing/browser`, lazy-load) làm fallback
      khi không có native `BarcodeDetector` (iOS Safari). Ưu tiên native trên Android.
- [ ] **Captcha** signup/login: thay GD image bằng thư viện Node.
- [ ] **Password hash**: PHP dùng `password_hash` (bcrypt `$2y$`). `bcryptjs`
      verify được hash `$2y$` sẵn có — đăng ký mới cũng dùng bcryptjs để đồng nhất.
- [ ] **i18n**: app PHP có cơ chế i18n + test parity (`tests/framework/I18nParity.php`).
- [ ] **Deploy**: môi trường cần Node runtime (RMIT chỉ có PHP/Apache → cần host khác
      cho phần Node, hoặc giữ PHP chạy song song trong giai đoạn chuyển tiếp).

## Chiến lược chuyển tiếp (strangler pattern)

Port dần từng module; module nào chưa port vẫn để PHP chạy. Reverse proxy định
tuyến: `/api/v2/*` → Express, phần còn lại → PHP, cho tới khi port hết.
