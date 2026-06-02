# BitBalance — Handoff (tiếp tục ở local)

File này gom mọi thứ cần để **resume công việc migration ở máy local**. Chi tiết
sâu hơn nằm ở [`MIGRATION.md`](./MIGRATION.md) (trạng thái port) và
[`DESIGN.md`](./DESIGN.md) (design system).

## 1. Kéo code về

```bash
git fetch origin
git checkout claude/express-vue-migration-XoMbE   # nhánh đang làm migration
```

**Branch & PR state:**
- Nhánh làm việc: `claude/express-vue-migration-XoMbE`
- `origin/main` đã đi tiếp riêng: xoá forum runtime, BEATS.md, MASCOT.md
- **PR #13** mở: `claude/express-vue-migration-XoMbE → main` (chỉ DESIGN.md).
  Push thẳng vào main bị chặn (503 / branch protection) nên đưa qua PR.
- PR #12 (migration tới app shell) đã merged vào main trước đó.

> Nhánh feature đang ở base cũ hơn main (chưa có các commit xoá forum). Khi tiện,
> `git merge origin/main` vào nhánh feature để đồng bộ trước khi port tiếp.

## 2. Chạy dev ở local

```bash
# Backend
cd server
cp .env.example .env     # điền DB_PASSWORD + SESSION_SECRET
npm install
npm run dev              # http://localhost:3000

# Frontend (terminal khác)
cd client
npm install
npm run dev              # http://localhost:5173  (Vite proxy /api → :3000)
```

`.env` cần điền (không nằm trong repo):

```
DB_HOST=talsprddb02.int.its.rmit.edu.au
DB_PORT=3306
DB_NAME=COSC3046_2502_G20
DB_USER=<user>
DB_PASSWORD=<điền>
SESSION_SECRET=<chuỗi random>
CLIENT_ORIGIN=http://localhost:5173
COOKIE_SECURE=false
```

## 3. Đã port xong

| Backend (`server/src/`) | Frontend (`client/src/`) |
|---|---|
| `routes/auth.js` (login/logout/me/register) | `views/LoginView.vue`, `SignupView.vue` |
| `routes/onboarding.js` + `lib/plan.js` | `views/OnboardingView.vue` |
| `routes/intake.js` + `lib/intake.js` (CRUD) | `views/DashboardView.vue` |
| `routes/dashboard.js` + `lib/dashboard.js` (day/summary) | `layouts/AppLayout.vue` (sidebar+tab bar) |
| `lib/xp.js`, `lib/streak.js` (gamification) | `stores/auth.js`, `lib/api.js`, `router.js` |
| `lib/dates.js`, `lib/handle.js`, `lib/users.js`, `db.js` | `styles.css`, `index.html` (Font Awesome CDN) |

Chi tiết từng endpoint: xem bảng trạng thái trong `MIGRATION.md`.

## 4. Còn lại (thứ tự đề xuất)

1. **Profile** (`api/profile/*`, ~61KB) — port xong thì bật `enabled: true` cho
   mục Profile trong `navItems` (`AppLayout.vue`) + thêm child route trong `router.js`.
2. **AI Coach** (`api/ai-coach/*`) — tích hợp OpenRouter.
3. **Social/Friends** (`api/social/action.php`).
4. **Admin panel** (`admin/*.php`) — auth riêng.
5. **Captcha** — thay PHP GD bằng `svg-captcha` (Node).
6. Nợ hạ tầng: remember-me token, session store production (Redis/MySQL thay
   MemoryStore), CSRF cho mutation.

> **Forum: bỏ hoàn toàn** (dead code, đã quyết) — không port.

## 5. Quy trình mở 1 page mới

1. Tạo view ở `client/src/views/` (chỉ nội dung, không header/nav — theo `DESIGN.md`).
2. Thêm route con dưới `AppLayout` trong `client/src/router.js`.
3. Đổi `enabled: false → true` cho mục tương ứng trong `navItems` (`AppLayout.vue`).
4. Backend: thêm `routes/<module>.js`, đăng ký trong `server/src/index.js`,
   tách query/logic sang `lib/<module>.js`.
5. Giữ envelope `{ ok, data, message }` cho mọi response.

## 6. Quyết định kỹ thuật quan trọng

- **Envelope API** `{ ok, data, message }` khớp `api_send()` của PHP → client port 1-1.
- **Dùng lại nguyên schema MySQL**, không migrate dữ liệu.
- **bcryptjs verify được hash `$2y$`** của PHP → user cũ login bình thường.
- **Timezone**: mọi phép tính "hôm nay" ở `Asia/Ho_Chi_Minh` (+07:00); số học ngày
  làm ở UTC để tránh lệch — khớp `SET time_zone='+07:00'` của DB.
- **XP anti-cheat**: award tính theo COUNT trong bảng nguồn trừ event đã award
  trong ngày → log-rồi-xoá không farm được XP.
- **AppLayout là route cha** bọc page đã đăng nhập → đổi tab không remount nav.
- **Meal key casing**: `day.php` dùng chữ thường, `summary.php` viết hoa — giữ
  nguyên cho đúng contract.
- **Auth**: `express-session` (tương đương PHP session), cookie same-origin qua
  Vite proxy nên không cần CORS lúc dev.
