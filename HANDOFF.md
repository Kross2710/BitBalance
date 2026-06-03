# BitBalance — Handoff (tiếp tục ở local)

File này gom mọi thứ cần để **resume công việc migration ở máy local**. Chi tiết
sâu hơn nằm ở [`MIGRATION.md`](./MIGRATION.md) (trạng thái port từng endpoint) và
[`DESIGN.md`](./DESIGN.md) (design system). Cập nhật: 2026-06-03.

## 1. Kéo code về

```bash
git fetch origin
git checkout claude/express-vue-migration-XoMbE   # nhánh đang làm migration
git merge origin/main                             # nếu nhánh tụt sau main
```

**Branch & PR state:**
- Nhánh làm việc: `claude/express-vue-migration-XoMbE` (tái dùng qua nhiều PR).
- **PR #12, #13, #14 đã MERGED** vào `main` (tới merge commit `d8a5b44`, PR #14).
- **Nhánh hiện đi TRƯỚC `origin/main` ~24 commit và CHƯA mở PR** (đã push). **Việc cần
  làm: mở 1 PR mới** gộp các commit này vào `main`. Xem đủ list:
  `git log --oneline origin/main..HEAD`. Các mảng lớn trên nhánh:
  - **PT epic** (slice 1-5 + disconnect + **PT-initiated invites** `72d6fe7`) — xem mục 3/6.
  - **Sign in with Google** (`d4e0aeb`).
  - **i18n (vue-i18n)** — locale vi/en, đa số view đã localize (session song song).
  - **BitBalance Wrapped** — recap API + story UI (session song song).
  - **Deploy kit** — `DEPLOY.md`, serve SPA từ Express, CachyOS/N100; DB fixes
    (utf8mb4, time_zone +07:00, trust proxy).
- Push thẳng `main` bị chặn (branch protection) → luôn đưa qua PR từ nhánh này.
- `client/vite.config.js` **cố ý để uncommitted** (proxy/allowedHosts local) — đừng commit.
- **Nhiều session chạy song song trên cùng nhánh** — trước khi sửa file dùng chung
  (`i18n/en.js`/`vi.js`, ...) check `git status`; đừng commit file đang dirty của session khác.

## 2. Chạy dev ở local

```bash
# Backend
cd server
cp .env.example .env     # điền DB + SESSION_SECRET (xem dưới)
npm install
npm run dev              # http://localhost:3000  (node --watch, tự reload)

# Frontend (terminal khác)
cd client
npm install
npm run dev              # http://localhost:5173  (Vite proxy /api → :3000)
```

**DB local (XAMPP):** `server/.env` có một khối override localhost ở cuối file đè
lên creds RMIT, để dev chạy DB `test` của XAMPP:

```
DB_HOST=127.0.0.1
DB_NAME=test
DB_USER=root
DB_PASSWORD=        # rỗng trên XAMPP mặc định
SESSION_SECRET=<chuỗi random>
CLIENT_ORIGIN=http://localhost:5173
COOKIE_SECURE=false
```

Test nhanh DB: `/Applications/XAMPP/xamppfiles/bin/mysql -u root test`.

**Migrations (QUAN TRỌNG):** Express/Vue dùng **chung schema MySQL**. Khi resume
trên DB chưa mới, apply các file trong `include/migrations/`. Chạy qua
`php include/migrations/migrate.php` hoặc nạp trực tiếp file `.sql`. Các bảng PT
cần có: `2026_05_31_add_pt_features`, `add_pt_chat`, `add_pt_profile`,
`add_pt_goal_proposal`. **Accept goal proposal cần `2026_06_02_add_macro_goals`**
(thêm cột `userGoal.{protein_goal,carbs_goal,fat_goal,set_by,source}`). **PT mời client
cần `2026_06_03_add_trainer_client_initiated_by`** (cột `trainer_client.initiated_by`).
DB test local có thể tụt các cột này — apply trước khi test luồng accept/invite.

**Tài khoản test (DB `test` XAMPP, password đã set `Test1234!`):** client `24`
(`kross2710@gmail.com`), client `34` (`vukhanhhung2710@gmail.com`); trainer `39`
(`pt@gmail.com`), trainer `414` (`coach@bitbalance.test`). 24 là client của 39.
**Lưu ý:** MemoryStore (dev) **mất session mỗi khi `node --watch` reload** — sửa
file server xong phải đăng nhập lại.

## 3. Đã port xong

Phần lớn đã ở `main` (qua PR #12-14). **Coach Hub, PT, Sign in with Google ở trên
nhánh, CHƯA merge** (7 commit chờ PR — xem mục 1).

| Module | Backend `server/src/` | Frontend `client/src/` |
|---|---|---|
| Auth + **remember-me 30 ngày** + **Sign in with Google** | `routes/auth.js`, `lib/remember.js`, `lib/google.js`, `middleware/auth.js` | `views/LoginView.vue` (checkbox + Google), `SignupView.vue`, `components/GoogleSignInButton.vue`, `stores/auth.js` |
| Onboarding | `routes/onboarding.js`, `lib/plan.js` | `views/OnboardingView.vue` |
| Intake (CRUD + suggest + **barcode** + **AI photo** + **meal cards** + today's entries) | `routes/intake.js`, `lib/intake.js`, `lib/uploads.js` | `views/IntakeView.vue` |
| Dashboard (day/summary, **Focus tile**, entries read-only + lightbox, **nudge reminder**) | `routes/dashboard.js`, `lib/dashboard.js` | `views/DashboardView.vue` |
| Profile (get/update + **Meal reminders** + **Log out**) | `routes/profile.js`, `routes/reminders.js` | `views/ProfileView.vue` |
| **Coach Hub** (segmented **AI Coach** / **My Trainer**) | `routes/aiCoach.js`, `lib/aiProvider.js` | `views/CoachView.vue` (hub), `components/AiCoachPanel.vue`, `ConversationList.vue`, `BottomSheet.vue` |
| **Personal Trainer (PT)** — client (My Trainer: hero/chat/advice/accept goal · tìm+xin trainer · **accept lời mời PT** · disconnect) + trainer (`/trainer` workspace: clients/detail/diary/trend/feedback/goal/chat/requests · **Find clients = mời client** · terminate). **Connect 2 chiều** (client xin HLV ↔ PT mời client, bên kia accept). | `routes/pt.js`, `lib/pt.js`, `middleware/auth.js` (`requirePt`) | `components/MyTrainerPanel.vue`, `TrainerDirectory.vue`, `TrainerFindClients.vue`, `ChatRoom.vue`, `views/TrainerView.vue`, `components/TrainerClientDetail.vue`, `layouts/AppLayout.vue` (avatar menu) |
| Friends/Social | `routes/friends.js`, `lib/friends.js` | `views/FriendsView.vue` |
| Gamification | `lib/xp.js`, `lib/streak.js` | (dashboard) |
| App shell: topbar (brand + **avatar dropdown**: Trainer workspace[pt]/Profile) + **4-tab** nav + badge | — | `layouts/AppLayout.vue`, `stores/badges.js` |

Chi tiết từng endpoint: bảng trạng thái trong `MIGRATION.md`.

## 4. Còn lại (thứ tự đề xuất)

> **PT epic (hạng mục #1 cũ) đã XONG** (xem mục 3 + mục 6). Còn vài nice-to-have nhỏ
> so với PHP, KHÔNG chặn: Activity feed phía PT, "needs attention" signals giàu hơn,
> nhập handle thủ công để connect (hiện chỉ qua directory), `pt_notify` badge phía PT.

1. **Web Push / nhắc nền** — reminders hiện CHỈ in-app (khi mở app). Push nền cần
   Service Worker + HTTPS + VAPID + lưu subscription + **host Node production**
   (deploy chưa chốt); iOS còn phải cài PWA ra home screen.
2. **Log out of all devices** — helper `revokeAllForUser()` đã có sẵn trong
   `lib/remember.js`, chỉ cần thêm endpoint + nút trong Profile.
3. **Nợ hạ tầng:** session store production (Redis/MySQL thay MemoryStore — hiện
   MemoryStore mất session khi reload), CSRF cho mutation, captcha (svg-captcha thay
   PHP GD), AI Coach chat nhận ảnh (vision — provider đã hỗ trợ, route `send`
   chưa nhận upload). **i18n đã xong** (vue-i18n, locale vi/en) — session song song.
   - **Google OAuth đã xong** (`lib/google.js` + routes `GET /api/auth/google`,
     `/google/callback`, `/providers`). Server-side Authorization Code flow port
     1-1 từ PHP, dùng lại bảng `user_identity` + `fetch` (không thêm dependency).
     Bật bằng cách điền `GOOGLE_CLIENT_ID`/`GOOGLE_CLIENT_SECRET` vào `server/.env`
     (đăng ký redirect URI `.../api/auth/google/callback`; ở local đặt
     `GOOGLE_REDIRECT_URI=http://localhost:3000/api/auth/google/callback`). Chưa
     điền creds thì nút Google tự ẩn (graceful degradation qua `/api/auth/providers`).
4. **Admin panel** (`admin/*.php`) — auth riêng.
5. **CI Tier B (test tự động)** — *Tier A đã ship* (`.github/workflows/ci.yml`,
   GitHub Actions, gate xanh trước PR vào main): 3 job không cần secret/DB —
   `client` (`npm ci` + `vite build`), `server` (`npm ci` + `node --check` toàn
   `src`), `php-lint` (`php -l` mọi `*.php`, ghim **PHP 7.4** = bản RMIT prod).
   Action ghim `@v6` (checkout/setup-node, Node 24-native), Node build = 24
   (box prod chạy 26 → matrix nếu cần parity). **Tier B = test thật, CHƯA làm:**
   - **Vue units**: thêm **Vitest** (+ `@vue/test-utils`, `jsdom`) và script
     `"test"` vào `client/package.json` (hiện CHỈ có `dev`/`build`/`preview`).
   - **Express endpoints**: **Vitest + supertest**, thêm script `"test"` vào
     `server/package.json` (hiện CHỈ có `dev`/`start`). Biến các **probe admin/PT
     đã test tay** (list/ban/edit/unlock, logs/prune, self-guard, CSRF
     `X-Requested-With`, PT connect 2 chiều) thành test tự động.
   - **DB cho test**: job CI thêm **service container MariaDB** + chạy migration
     (`include/migrations/`) trước khi test. Khung:
     ```yaml
     services:
       mariadb:
         image: mariadb:12
         env: { MARIADB_DATABASE: bb_test, MARIADB_ROOT_PASSWORD: root }
         ports: ['3306:3306']
         options: >-
           --health-cmd="healthcheck.sh --connect --innodb_initialized"
           --health-interval=5s --health-timeout=5s --health-retries=10
     ```
     Test chạy với env trỏ vào service (`DB_HOST=127.0.0.1 DB_NAME=bb_test
     DB_USER=root DB_PASSWORD=root`), KHÔNG cần secret thật (DB throwaway).
   - Khi có script `test`, thêm job `test` vào `ci.yml` (gọi `npm test` cho
     client + server). Xem chi tiết quyết định CI ở memory `ci-setup.md`.

> **Forum: bỏ hoàn toàn** (dead code, đã quyết) — không port.

## 5. Quy trình mở 1 page mới

1. Tạo view ở `client/src/views/` (chỉ nội dung, không header/nav — theo `DESIGN.md`).
2. Thêm route con dưới `AppLayout` trong `client/src/router.js`.
3. Nếu là đích bottom-nav: thêm mục vào `navItems` trong `AppLayout.vue`
   (hiện 4 tab: Home/Intake/Coach/Friends — Profile vào qua avatar topbar).
4. Backend: thêm `routes/<module>.js`, đăng ký trong `server/src/index.js`,
   tách query/logic sang `lib/<module>.js`.
5. Giữ envelope `{ ok, data, message }` cho mọi response.

## 6. Quyết định kỹ thuật + tradeoff quan trọng

- **Envelope API** `{ ok, data, message }` khớp `api_send()` của PHP → client port 1-1.
- **Dùng lại nguyên schema MySQL**, không migrate dữ liệu.
- **bcryptjs verify được hash `$2y$`** của PHP → user cũ login bình thường.
- **Timezone**: mọi phép tính "hôm nay" ở **+07:00** (`Asia/Bangkok` trong code mới),
  khớp `SET time_zone='+07:00'` của DB; số học ngày làm ở UTC để tránh lệch.
- **Remember-me**: selector/validator (`auth_token`), cookie `bb_remember` 30 ngày,
  middleware auto-login khôi phục session hết hạn; DB chỉ lưu SHA-256 của validator.
- **Badges trong Pinia** (`stores/badges.js`): bất kỳ view nào (vd Profile sau khi
  lưu reminders) gọi `refresh()` → badge nav cập nhật tức thì. Badge Intake **khớp
  nudge** reminder (bữa đã bật + qua giờ + chưa log; 0 khi reminders tắt).
- **Reminders in-app**: nudge ở Dashboard, dismiss lưu `localStorage` key
  `bb_rd_{date}_{meal}` (không ăn theo trạng thái đã log — đó là badge/nudge tính lại).
- **AI photo**: lưu vào `server/uploads/intake/` (gitignored), serve read-only ở
  `/api/uploads/*` (qua `/api` để Vite proxy forward + prod cùng origin).
- **AppLayout là route cha** bọc page đã đăng nhập → đổi tab không remount nav.
- **Meal key casing**: `day.php` chữ thường, `summary.php` viết hoa — giữ nguyên contract.
- **Coach = hub 2 segment** (`[AI Coach] [My Trainer]`); panel dùng `v-show` để **giữ
  state** khi đổi segment. **PT role KHÔNG swap bottom nav** (vẫn 4 tab) — workspace là
  route `/trainer` vào qua **avatar dropdown** (chỉ hiện cho role `pt`). Quyết định IA
  này thay cho ghi chú cũ "Coach thành tabs AI/My Trainer/Plans".
- **`/api/pt` guard PER-ENDPOINT** (không per-prefix): endpoint client (`/my-trainer`,
  `/messages`, `/goal-proposal/respond`, `/directory`, `/request`, `/request/cancel`,
  `/disconnect`) cho regular user là **client của một PT**; endpoint trainer
  (`/clients*`, `/requests*`, `/clients/:id/{messages,feedback,propose-goal,terminate}`)
  bọc `requirePt`. Chat helper trong `lib/pt.js` **role-agnostic** (truyền
  trainerId/clientId/myRole) → 2 phía dùng chung.
- **`ChatRoom.vue` dùng chung** (props `path` + `my-role`) cho cả My Trainer (client)
  lẫn `TrainerClientDetail` (trainer): optimistic send + poll 12s. **`BottomSheet.vue`**
  tái dùng (mirror `pt-drawer` của PHP).
- **PT quan hệ 1-1 một lúc**: `sendTrainerRequest` chặn nếu đã accepted/pending + check
  capacity; directory chỉ hiện khi client chưa có trainer; **disconnect** (client) /
  **terminate** (trainer) đều có **confirm inline** (không dùng popup trình duyệt).
- **Connect 2 chiều + `trainer_client.initiated_by`**: client xin HLV (initiated_by
  `client`) → trainer accept ở tab Requests; PT mời client (initiated_by `trainer`) →
  client accept ở card lời mời trong My Trainer. Query lọc theo direction nên lời mời
  KHÔNG lẫn vào Requests và ngược lại. Legacy row `initiated_by=NULL` coi là `client`.
  `respondInvite` accept chạy trong transaction + dọn link in-flight khác (giữ 1-1).
- **Accept goal proposal** ghi 1 row `userGoal` mới `source='pt'` (latest-wins) trong
  transaction; số liệu chuẩn theo `respond_goal_proposal.php`.
- **TRADEOFF đã biết:** Dashboard **read-only mọi ngày**; sửa/xoá món chuyển hẳn sang
  Intake và Intake **chỉ quản lý hôm nay** → **không sửa được món của ngày cũ**. Muốn
  khôi phục thì cho Intake nhận `?date=`.
