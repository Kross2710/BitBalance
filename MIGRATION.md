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
| Intake – create | `api/intake/create.php` | ✅ `POST /api/intake/create` | ✅ Dashboard | **Chưa port XP/streak** (xem TODO) |
| Auth – register | `api/auth/register.php` | ✅ `POST /api/auth/register` | ✅ SignupView | Tự sinh handle `Tên#1234`, auto-login |
| Onboarding | `api/onboarding/save.php` | ✅ `POST /api/onboarding/save` | ✅ OnboardingView | Port BMR/TDEE/macro + lưu transaction |
| Intake – update | `api/intake/update.php` | ✅ `POST /api/intake/update` | ✅ Dashboard | Sửa inline |
| Intake – delete | `api/intake/delete.php` | ✅ `POST /api/intake/delete` | ✅ Dashboard | Trả deleted_row cho Undo |
| Intake – suggest/barcode | `suggest.php`, `lookup_barcode.php` | ⬜ | ⬜ | gọi AI / barcode ngoài |
| Dashboard – day/summary | `api/dashboard/*` | ⬜ | ⬜ | |
| Profile | `api/profile/*` | ⬜ | ⬜ | `profile.php` rất lớn (61KB) |
| AI Coach | `api/ai-coach/*` | ⬜ | ⬜ | tích hợp OpenRouter |
| Social/Friends | `api/social/action.php` | ⬜ | ⬜ | |
| Forum | `forum/*.php` | ⬜ | ⬜ | module riêng |
| Admin panel | `admin/*.php` | ⬜ | ⬜ | module riêng, có auth riêng |
| Captcha | `captcha_image.php` (GD) | ⬜ | ⬜ | thay bằng svg-captcha (Node) |

## TODO / nợ kỹ thuật cần xử lý khi tiếp tục

- [ ] **XP + logging streak**: port `include/handlers/xp.php` + `updateLoggingStreak()`
      (hiện `POST /api/intake/create` trả `xp.added = 0`).
- [ ] **Remember-me token**: port `include/handlers/remember_token.php` + bảng token
      (login hiện chưa cấp cookie ghi nhớ dài hạn).
- [ ] **Session store production**: thay MemoryStore của express-session bằng
      store bền (Redis hoặc MySQL session store).
- [ ] **CSRF**: app PHP có `include/csrf.php`. SPA dùng cookie → cân nhắc
      double-submit token hoặc SameSite=strict cho các mutation.
- [ ] **Captcha** signup/login: thay GD image bằng thư viện Node.
- [ ] **Password hash**: PHP dùng `password_hash` (bcrypt `$2y$`). `bcryptjs`
      verify được hash `$2y$` sẵn có — đăng ký mới cũng dùng bcryptjs để đồng nhất.
- [ ] **i18n**: app PHP có cơ chế i18n + test parity (`tests/framework/I18nParity.php`).
- [ ] **Deploy**: môi trường cần Node runtime (RMIT chỉ có PHP/Apache → cần host khác
      cho phần Node, hoặc giữ PHP chạy song song trong giai đoạn chuyển tiếp).

## Chiến lược chuyển tiếp (strangler pattern)

Port dần từng module; module nào chưa port vẫn để PHP chạy. Reverse proxy định
tuyến: `/api/v2/*` → Express, phần còn lại → PHP, cho tới khi port hết.
