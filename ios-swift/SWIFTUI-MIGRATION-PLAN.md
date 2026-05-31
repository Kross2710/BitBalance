# BitBalance — Kế hoạch chuyển dịch sang Swift/SwiftUI

> Tài liệu này gồm 2 phần:
> 1. **Định hướng kiến trúc** (PHP làm gốc, Swift làm ngọn) — ghi lại từ phần trao đổi.
> 2. **Đối chiếu thực tế codebase PHP với app iOS hiện tại** + lộ trình hoàn thiện.
>
> Trạng thái tại thời điểm viết: app SwiftUI đã chạy được với 6 tab, tầng REST API (`/api/`) đã dựng một phần. Đây **không phải** dự án từ con số 0 — phần lớn công việc còn lại là *lấp khoảng trống* giữa web và app.

---

## Phần 1 — Định hướng kiến trúc

### PHP làm gốc, Swift làm ngọn

Không cần đập đi xây lại backend. Toàn bộ logic, database, tính Streak, XP, quản lý bạn bè vẫn nằm nguyên trong PHP/MySQL. Thay đổi duy nhất là **cách PHP trả dữ liệu**:

| | Hiện tại (Hybrid Web) | Tương lai (Native App) |
|---|---|---|
| Logic | PHP xử lý | PHP xử lý (giữ nguyên) |
| Đầu ra | Nhúng thẳng vào HTML trong `views/` | Trả JSON thuần (RESTful API trong `/api/`) |
| Render | Trình duyệt | SwiftUI tự "vẽ" lên màn hình iPhone |

Lộ trình rủi ro thấp nhất (đã ghi trong `ios-swift/README.md`):
1. Thêm endpoint JSON vào app PHP hiện có → **đang làm**.
2. Dựng màn hình SwiftUI gọi các endpoint đó → **đang làm**.
3. Giữ session/cookie auth ban đầu → **đã làm** (xem `api/_bootstrap.php`).
4. Chuyển sang token auth khi cần phân phối qua App Store.
5. Chỉ cân nhắc Express/NestJS/Laravel **sau khi** API contract ổn định.

### CSS 3D → SwiftUI

SwiftUI tư duy declarative, rất giống cách viết CSS theo mô-đun:

```swift
// box-shadow: 4px 4px 0px #000  →
RoundedRectangle(cornerRadius: 12)
    .fill(Color.white)
    .overlay(RoundedRectangle(cornerRadius: 12).stroke(Color.black, lineWidth: 2))
    .shadow(color: .black, radius: 0, x: 4, y: 4) // bóng 3D cứng cáp
```

| CSS | SwiftUI |
|---|---|
| `flex-direction: column` | `VStack` |
| `flex-direction: row` | `HStack` |
| `position: absolute` (xếp lớp / toast / 3D) | `ZStack` |
| `box-shadow`, `border`, `border-radius` | `.shadow()`, `.overlay(.stroke())`, `.cornerRadius()` |
| Gradient nền | `LinearGradient` (đã dùng: `BBColors.backgroundGradient`) |

### Vì sao SwiftUI mở khóa hướng đi kiểu "Locket"

1. **WidgetKit** — Widget mâm cơm của bạn thân hiện thẳng màn hình khóa, không cần mở app.
2. **Camera native** — "Chụp trước, tính sau" mượt, lưu cache rồi đẩy ngầm lên PHP qua background thread.
3. **Spotify iOS SDK** — bắt tay trực tiếp app Spotify trên máy thay vì cURL phía backend (chính xác, real-time).

> ⚠️ Cần máy macOS + Xcode để build & preview.

---

## Phần 2 — Đối chiếu codebase PHP ↔ app iOS hiện tại

### 2.1 Inventory backend PHP

**Trang dashboard web** (`dashboard/`):
`dashboard.php` (home), `dashboard-intake.php` (log), `dashboard-history.php`, `dashboard-friends.php`, `dashboard-progress.php` (cân nặng), `dashboard-plan.php` (kế hoạch mục tiêu), `dashboard-calculator.php` (TDEE), `dashboard-beats.php` (nhạc/Spotify), `dashboard-wiki.php`.

**Handlers nghiệp vụ** (`dashboard/handlers/`, `include/handlers/`):
intake CRUD, AI chat, friends, XP (`xp.php`), achievements, streak (`streak_actions.php`), goal/plan, calculator, weight log, barcode lookup, Spotify (auth/callback/disconnect), **beats mixer** (mới), **mascot chat** (mới), story data.

### 2.2 Tầng API JSON đã dựng (`/api/`)

```
auth/login, auth/logout, me                     ✅
dashboard/summary                               ✅ (kèm streak, XP, history 7 ngày, meal breakdown)
intake/create, update, delete, history          ✅
intake/lookup_barcode                           ✅
profile/get, profile/update                     ✅
ai-coach/conversations, messages, send, delete  ✅
social/action (poll, leaderboard, search, kết bạn) ✅
```

### 2.3 App SwiftUI hiện tại (`ios-swift/BitBalanceApp/`)

6 tab trong `MainTabView.swift`: **Dashboard, Log, History, AI Coach, Social, Profile**.
`APIClient.swift` đã wire đủ tất cả endpoint ở mục 2.2. Models trong `UserSession.swift` đầy đủ (intake, dashboard, profile, ai-coach, friends, leaderboard, barcode).

⚠️ **Stub chưa code**: `BarcodeScannerView.swift` và `ImagePicker.swift` mới chỉ 1 dòng — endpoint barcode có nhưng UI quét chưa làm.

### 2.4 Bảng đối chiếu Tính năng → API → iOS

| Tính năng (web) | Handler PHP | API JSON | Màn hình iOS | Trạng thái |
|---|---|---|---|---|
| Đăng nhập / phiên | `user_login.php` | `auth/*`, `me` | `LoginView` | ✅ Xong |
| Dashboard (calo, macro, XP, streak) | `dashboard_data.php`, `xp.php` | `dashboard/summary` | `DashboardView` | ✅ Xong |
| Log món ăn | `process_intake.php` | `intake/create` | `LogFoodView` | ✅ Xong |
| Lịch sử / sửa / xóa | `edit_intake`, `delete_intake` | `intake/history,update,delete` | `IntakeHistoryView` | ✅ Xong |
| Profile | `profile.php` | `profile/get,update` | `ProfileView` | ✅ Xong |
| AI Coach (kèm ảnh) | `ai_coach.php` | `ai-coach/*` | `AIChatView` | ✅ Xong |
| Bạn bè / Leaderboard | `friends.php` | `social/action` | `SocialView` | ✅ Xong |
| **Quét barcode** | `lookup_barcode.php` | `intake/lookup_barcode` | `BarcodeScannerView` (stub) | 🟡 API có, UI thiếu |
| **Cân nặng / Progress** | `log_weight`, `delete_weight` | ❌ chưa có | ❌ chưa có | 🔴 Thiếu |
| **Kế hoạch mục tiêu (Plan)** | `goal_plan`, `apply_plan_goal`, `update_goal` | ❌ | ❌ | 🔴 Thiếu |
| **Calculator (TDEE)** | `process_calculator.php` | ❌ | ❌ | 🔴 Thiếu (có thể tính client-side) |
| **Beats / Spotify** | `spotify_*`, `beats_*`, `mascot_chat` | ❌ | ❌ | 🔴 Thiếu (cần Spotify iOS SDK) |
| **Wiki** | `dashboard-wiki.php` | ❌ | ❌ | 🔴 Thiếu (ưu tiên thấp) |
| **Achievements** | `achievements.php` | ❌ (1 phần trong summary) | ❌ màn hình riêng | 🟡 Một phần |
| **Đăng ký** | `user_signup.php` | ❌ | ❌ | 🔴 Thiếu |

### 2.5 Phân tích khoảng trống

- **Core nutrition loop đã hoàn chỉnh** (log → history → dashboard → profile + AI + social). Có thể demo end-to-end ngay.
- Thiếu chủ yếu ở các tính năng "vệ tinh": cân nặng, kế hoạch, calculator, Beats/Spotify, wiki, đăng ký.
- ~~**Hai bản sao code Swift** tồn tại song song và dễ lệch nhau.~~ ✅ **Đã gộp** (2026-05-31): nguồn sự thật duy nhất là `ios-swift/BitBalance/BitBalance/BitBalanceApp/`; bản rời `ios-swift/BitBalanceApp/` đã xóa. *Cần build lại trong Xcode để xác nhận.*

---

## Phần 2.5 — Design layer (đồng bộ với web PHP)

> Mục tiêu: app SwiftUI **dùng chung ngôn ngữ thiết kế** với web hiện có (phong cách Duolingo: xanh lá rực, bo góc tròn, nút/card 3D "tactile").

### Design KHÔNG phải một giai đoạn — nó là 2 tầng

| Tầng | Là gì | Khi nào | Trạng thái |
|---|---|---|---|
| **Design system (nền)** | Token + modifier dùng chung (`BBColors`, `BBRadius`, `BBCardModifier`...) | Một lần, ở **Giai đoạn 0**, sau đó chỉ *bổ sung* khi gặp pattern mới | ✅ Gần như xong |
| **UI từng màn hình** | Layout cụ thể của mỗi view | **Đi kèm ngay** khi build tính năng đó (GĐ 1→3), tái dùng modifier sẵn có | Làm tới đâu thiết kế tới đó |

**Nguyên tắc:** không tách "làm UI" thành sprint riêng sau khi xong logic. Mỗi màn hình mới build UI + wire API cùng lúc, dùng lại `BBCardModifier`/`BBButtonStyle` → giao diện tự đồng bộ.

### Nguồn sự thật: `css/tokens.css` ↔ `BBColors` trong `BitBalanceApp.swift`

Web khai báo token ở [`css/tokens.css`](../css/tokens.css). Swift đã **port gần như 1:1** vào `struct BBColors`/`BBRadius` (trong `BitBalanceApp.swift:73-142`). Hai bên hiện **khớp giá trị**:

| Token | Web (`tokens.css`) light / dark | Swift (`BBColors`) light / dark | Khớp |
|---|---|---|---|
| primary | `58CC02` / `4ade80` | `58CC02` / `4ADE80` | ✅ |
| primaryHover | `4CAF00` / `22c55e` | `4CAF00` / `22C55E` | ✅ |
| secondary | `1CB0F6` / `60a5fa` | `1CB0F6` / `60A5FA` | ✅ |
| accent | `FF9600` / `fb923c` | `FF9600` / `FB923C` | ✅ |
| bg / surface / surfaceAlt | `f8fafc` `ffffff` `f1f5f9` / `0f172a` `1e2937` `334155` | giống hệt | ✅ |
| text / textSecondary / textMuted | `1e2937` `64748b` `94a3b8` (+dark) | giống hệt | ✅ |
| border / borderSubtle | `e2e8f0` `f1f5f9` (+dark) | giống hệt | ✅ |
| danger / success / warning (+bg/border) | giống | giống | ✅ |
| gradient streak | `135deg FF6B00→FF9600` | `FF6B00→FF9600` topLeading→bottomTrailing | ✅ |
| radius sm/md/lg/xl/pill | `8/14/20/28/9999` | `8/14/20/28/9999` | ✅ |

> Bóng 3D "tactile" của web (`--lesson-card-shadow`, nút có viền dày + bóng cứng) đã được tái hiện bằng `BBCardModifier` (lớp offset `borderSubtle` + drop shadow) và `BBButtonStyle` (khối bóng 4pt, lún xuống khi nhấn). Đây chính là bản "dịch" CSS 3D → SwiftUI.

### Khoảng trống còn lại để đồng bộ 100%

| Hạng mục | Web có | Swift | Việc cần làm |
|---|---|---|---|
| **Typography scale** | `--font-size-xs..xl`, `line-height 1.6` | hard-code `.system(size: 16, .bold)` rải rác | 🔴 Tạo `BBFont`/`BBText` token (xs/sm/base/lg/xl) thay cho size cứng |
| **Thang shadow** | `--shadow-xs..xl` (5 mức) | chỉ 1–2 mức trong modifier | 🟡 Thêm `BBShadow` (sm/md/lg) để card/sheet thống nhất |
| **Token màu còn thiếu** | `surfaceHover`, `info`/`infoBg`, `accentHover`, `primarySoft`, `overlay` | chưa có | 🟡 Bổ sung vào `BBColors` khi màn hình cần (vd. overlay cho modal/toast) |
| **Motion** | `--transition-fast/base/slow` | dùng `interactiveSpring` | ✅ Chấp nhận khác biệt nền tảng (animation iOS-native tốt hơn) |
| **Font chữ** | system font (`-apple-system`) | system font | ✅ Đã cùng họ chữ |

> Khi đổi token: sửa **cả hai** nơi (`tokens.css` và `BBColors`) để không lệch. Cân nhắc ghi chú "đồng bộ với tokens.css" ngay trên đầu `struct BBColors`.

---

## Phần 3 — Lộ trình đề xuất

### Giai đoạn 0 — Dọn nền + chốt design system (1 buổi)
- [x] Chốt **một** thư mục nguồn Swift, xóa bản trùng để tránh sửa nhầm. ✅ Đã gộp về `ios-swift/BitBalance/BitBalance/BitBalanceApp/`.
- [x] Quét file Swift "rỗng" (`BarcodeScannerView`, `ImagePicker`). ✅ Thực ra chúng **đã được implement đầy đủ** trong `BitBalanceApp.swift`; 2 file trong `Views/` chỉ là comment placeholder, không xung đột.
- [ ] Xác nhận `AppConfig.baseURL` trỏ đúng (localhost cho simulator / LAN IP cho iPhone thật / `titan.csit.rmit.edu.au` cho prod).
- [x] **Design (xem Phần 2.5)**: thêm `BBFont`, `BBShadow`, token màu còn thiếu. ✅ Xong (token đã có trong `BitBalanceApp.swift`).

### Giai đoạn 1 — Lấp tính năng đã có API (nhanh)
- [x] **Barcode**: ✅ **Đã có sẵn** — `LogFoodView` có nút "Scan Food Barcode" → `.sheet(BarcodeScannerView)` (AVCaptureSession) → `lookupBarcode` → điền form.
- [x] **Camera/ảnh**: ✅ `ImagePicker` đã implement (dùng trong AI Coach gửi ảnh).

#### Áp `BBFont` vào view — quy ước (KHÔNG sweep mù)
- [x] `LogFoodView` refactor sang `BBFont` làm **bản mẫu** (16 chỗ).
- [ ] Các view khác: áp **dần khi đụng vào**, chỉ cho **text thông thường** (label/caption/body/heading) map vào thang `xs/sm/base/lg/xl`.
- ⚠️ **Giữ nguyên** các size đặc biệt (số calo lớn 38–56pt, icon, badge 8–11pt) — chúng cố ý nằm ngoài thang token; ép vào thang sẽ phá thiết kế. App hiện có ~150 chỗ `.system(size:)`, phần lớn là size bespoke → **không** find-replace hàng loạt.

### Giai đoạn 2 — Thêm API còn thiếu (PHP) + màn hình iOS
Ưu tiên theo giá trị người dùng:
1. **Đăng ký** — `api/auth/register.php` (bọc `user_signup.php`).
2. **Cân nặng/Progress** — `api/progress/log.php`, `history.php`, `delete.php` (bọc `log_weight`/`delete_weight`) → `ProgressView` (chart cân nặng).
3. **Mục tiêu/Plan + Calculator** — `api/plan/*` (bọc `goal_plan`, `apply_plan_goal`, `process_calculator`) → `PlanView`. Phần tính TDEE có thể làm client-side trong Swift cho mượt.
4. **Achievements** — `api/achievements/list.php` → màn hình huy hiệu.

> Mẫu khi viết endmpoint mới: copy cấu trúc `api/dashboard/summary.php` — `require _bootstrap.php` → `api_require_method` → `api_require_auth` → gọi lại **đúng** hàm handler cũ → `api_send`. **Không** viết lại logic nghiệp vụ.

### Giai đoạn 3 — Tính năng "Locket" native
- [ ] **Spotify iOS SDK** thay cho luồng cURL `spotify_*` → `BeatsView`.
- [ ] **WidgetKit** — widget mâm cơm bạn bè trên màn hình chính.
- [ ] **Camera "chụp trước tính sau"** + upload nền.
- [ ] Push notification khi bạn bè log món / nhắc streak.

### Giai đoạn 4 — Cứng hóa cho phát hành
- [ ] Chuyển session-cookie → **token auth** (Bearer) để chạy ổn ngoài WebView.
- [ ] Bật HTTPS, gỡ ngoại lệ `NSAllowsArbitraryLoads` của ATS.
- [ ] Chuẩn hóa key JSON (snake_case ↔ `convertFromSnakeCase`); một số model đã phải khai báo `CodingKeys` thủ công — nên thống nhất.

---

## Phần 4 — Lưu ý kỹ thuật

- **DB nằm trong mạng RMIT** → không render/test được local khi off-campus. Khi test app, dùng XAMPP local + `include/db_config.php` trỏ DB local, hoặc ở trong mạng trường.
- **iPhone thật** không dùng được `localhost` — cần LAN IP của Mac (`ipconfig getifaddr en0`) và mở firewall cho Apache.
- **API contract là hợp đồng** giữa 2 team (PHP & Swift). Mỗi khi đổi shape JSON, cập nhật cả `_helpers.php` phía PHP lẫn struct trong `UserSession.swift`.
