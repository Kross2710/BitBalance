# BitBalance — Design System (Vue SPA)

Tài liệu này mô tả **ngôn ngữ thiết kế** của frontend Vue, để mọi page port sau
này (Profile, Coach, Friends, Admin…) trông như cùng một sản phẩm chứ không phải
ghép từ nhiều nơi. Đọc file này trước khi dựng view mới.

> Triết lý: **dark-first, calm, focus vào số liệu**. Ít chrome, ít emoji, icon
> nhất quán (Font Awesome), chuyển trang mượt (SPA, fade nhẹ). Mobile và desktop
> dùng **chung component**, chỉ khác layout khung điều hướng.

### Anti-patterns — KHÔNG làm "thô" như PHP/beats

Định hướng đã chốt: **đồng nhất theo UI Vue hiện tại** (đẹp, gọn, phẳng), không kéo
phong cách trang PHP `dashboard-beats.php` sang. Tránh các thứ sau — chúng là dấu
hiệu "trôi" về phong cách cũ:

| Đừng (phong cách PHP/beats) | Thay bằng (phong cách Vue) |
|---|---|
| Shadow "3D chunky" `box-shadow: 0 4px 0 …` | Viền `1px solid var(--border)`, phẳng |
| Palette Duolingo sáng (`#58CC02`, `#FF9600`) | Token dark — accent **`#4ade80`** |
| Padding to, khối phình | Padding gọn (`20px` card / `12–16px` list), nội dung `max-width: 820px` |
| Trang dài, scroll nhiều | Bố cục đặc, ưu tiên gọn trong tầm nhìn |
| Emoji 🍕🔥 trong UI | Icon Font Awesome `fa-solid` |
| Bo góc quá lớn (`22px+`), gradient loè | Radius `8–14px`, màu phẳng |

> Lý do tồn tại mục này: mockup/đề xuất từng bị dựng theo phong cách beats (shadow
> chunky, emoji, `#58CC02`) → lệch hẳn UI Vue. `client/src/styles.css` + tài liệu
> này là **chuẩn duy nhất**.

---

## 1. Design tokens (CSS variables)

Định nghĩa duy nhất ở `client/src/styles.css` `:root`. **Luôn dùng biến**, không
hardcode hex trong component (trừ vài màu trạng thái dưới đây chưa thành token).

| Token | Giá trị | Dùng cho |
|---|---|---|
| `--bg` | `#0f1115` | nền trang (gần đen, hơi xanh) |
| `--card` | `#1a1d24` | nền card / sidebar / tab bar |
| `--accent` | `#4ade80` | màu thương hiệu (xanh lá), CTA, link, trạng thái active |
| `--text` | `#e8eaed` | chữ chính |
| `--muted` | `#9aa0a6` | chữ phụ, label, caption |
| `--border` | `#2a2e37` | viền card, đường phân cách |

**Màu trạng thái** (chưa token hoá — dùng trực tiếp, giữ nhất quán):

| Mục đích | Màu |
|---|---|
| Lỗi / vượt giới hạn / nút xoá | `#f87171` (đỏ nhạt) |
| XP / level (tím) | `#a78bfa` nền, `#1a1030` chữ |
| Streak / lửa | `#fb923c` (cam) |
| Nền input / rãnh progress bar | `#12151b` |
| Nút phụ (secondary) | nền `#2a2e37`, chữ `var(--text)` |
| Chữ trên nút accent | `#04210f` (xanh đậm) |

> Nợ kỹ thuật: nên token hoá `--danger`, `--xp`, `--streak`, `--inset` khi có
> dịp. Tạm thời giữ literal cho khớp hành vi hiện tại.

---

## 2. Typography

- Font: system stack — `system-ui, -apple-system, "Segoe UI", Roboto, sans-serif`.
  Không nạp web font (nhanh, gọn).
- Chữ thường: `14px`. Caption/label phụ: `11–13px` + `var(--muted)`.
- Số liệu nổi bật (tile stat): `20px`, `font-weight: 700–800`.
- Tiêu đề card: dùng `<strong>` thay vì `<h?>` cho nhịp nhàng, trừ tiêu đề trang.

---

## 3. Spacing & bo góc

- Khoảng cách dọc giữa các section: `14px` (`margin-top: 14px`).
- Padding card: `20px` (card thường) / `12–16px` (card dạng list item).
- Bo góc thang đo:
  - input / button: `8px`
  - card / pill: `10–14px`
  - brand mark, nav-link: `8–10px`
- Bề rộng nội dung tối đa: **`820px`** (page có data), **`380px`** (form auth).
  Căn giữa bằng `margin: 0 auto`.

---

## 4. Components

### 4.1 Card — `.card`
Khối nội dung cơ bản. `background: var(--card)`, viền `--border`, bo `14px`,
padding `20px`. Mọi section dashboard đều là card.

### 4.2 Button
- **Primary** (mặc định `<button>`): nền `--accent`, chữ `#04210f`, bo `8px`,
  `font-weight: 600`. Disabled → `opacity: 0.6`.
- **Secondary**: nền `#2a2e37`, chữ `--text` (nút Cancel, Log out).
- **Icon button** `.icon-btn`: nhỏ (`6–10px` padding, `12px`), nền `#2a2e37`.
  Biến thể `.icon-btn.danger` → chữ đỏ (`#f87171`).

### 4.3 Input / select
Full-width, nền `#12151b`, viền `--border`, bo `8px`, padding `10–12px`.
Form dùng `display: grid` với cột tỉ lệ (vd quick-log: `2fr 1fr 1fr`).

### 4.4 Progress bar — `.bar`
Rãnh `height: 10px`, nền `#12151b`, bo `6px`, `overflow: hidden`. Thanh trong
`transition: width 0.3s`. Màu thanh đổi theo ngữ cảnh:
- calo bình thường → `var(--accent)`; vượt giới hạn → `#f87171`.
- thanh XP → `#a78bfa`.

### 4.5 Stat tile — `.tile`
Card mini trong grid `repeat(3, 1fr)`. Cấu trúc: label muted → số lớn
(`<strong>`) → caption muted. Dùng cho Streak / BMI / 7-day avg.

### 4.6 Toast (thông báo nổi)
`position: fixed`, đáy giữa màn hình, dùng cho XP/level-up. Nền tím `#a78bfa`.
Bọc trong `<Transition name="fade">`. Tự ẩn sau ~2.8s. Nội dung = icon FA + text.

### 4.7 Bar chart (mini)
Cột flex `align-items: flex-end`, cao `100px`. Mỗi cột chiều cao theo tỉ lệ với
max, bo trên `4px 4px 0 0`, màu `--accent`, `transition: height 0.3s`. Nhãn dưới
mỗi cột là `--muted` nhỏ.

### 4.8 Choice card — `.choice` / `.choice-grid`
Ô lựa chọn **phẳng** cho wizard onboarding (gender / goal / pace) và list dọc
(activity). Render trên `<button type="button">` (override nền nút accent mặc
định). Phẳng theo design Vue: viền `1px var(--border)`, nền `var(--inset)`; khi
chọn → viền `var(--accent)` + nền `var(--focus-ring)` — **KHÔNG** shadow
`0 8px 0` kiểu PHP. Toàn token nên tự hợp light/dark.
- Lưới: `.choice-grid` (gap `10px`); biến thể `.choice-grid.cols-3` cho 3 cột.
- Mỗi ô: icon FA + nhãn; dòng phụ dùng `.sub` (muted, `12px`).
- Biến thể `.choice.row`: icon trái + khối `.choice-text` (label + `.sub`) phải —
  dùng cho list activity.
- Trạng thái chọn: thêm class `.is-selected`.

---

## 5. Iconography — Font Awesome 6 (KHÔNG dùng emoji)

Nạp 1 lần qua CDN ở `client/index.html` (FA 6.5.0, `all.min.css`). Dùng class
`fa-solid` (đồng bộ với app PHP cũ vốn dùng `fas`).

```html
<i class="fa-solid fa-house" />
```

Bộ icon đang dùng (mở rộng khi port thêm page, giữ phong cách `solid`):

| Ngữ cảnh | Icon |
|---|---|
| Home / Dashboard | `fa-house` |
| Coach (AI) | `fa-dumbbell` |
| Friends / Social | `fa-user-group` |
| Profile | `fa-user` |
| Đăng xuất | `fa-right-from-bracket` |
| Điều hướng ngày | `fa-chevron-left` / `fa-chevron-right` |
| Streak | `fa-fire` (màu `#fb923c`) |
| Level up | `fa-trophy` |
| +XP | `fa-arrow-up` |

> Quy tắc: **không emoji trong UI**. Mọi biểu tượng đi qua Font Awesome để đồng
> nhất nét vẽ, căn chỉnh và màu.

---

## 6. Khung điều hướng (App shell) — responsive

Đây là điểm nhấn của framework. `client/src/layouts/AppLayout.vue` là **route
cha** bọc mọi page đã đăng nhập → đổi tab **không remount** nav, điều hướng mượt.

### Desktop (≥ 768px) — Sidebar icon, hover mở rộng
- Cố định trái, `width: 64px` (chỉ icon). Hover → `220px` (icon + nhãn),
  `transition: width 0.18s`.
- Nhãn (`.nav-label`, `.brand-text`) `opacity: 0` → `1` khi hover.
- Item active: nền `#12151b`, chữ `--accent` (`.router-link-active`).
- `.main` chừa `margin-left: 64px`.

### Mobile (< 768px) — Bottom tab bar
- Sidebar `display: none`; tab bar cố định đáy, các tab chia đều (`flex: 1`),
  icon trên + nhãn nhỏ dưới.
- Nội dung chừa `padding-bottom: 76px` để không bị tab bar che.

### Top bar (cả hai)
Sticky, nền bán trong suốt `rgba(15,17,21,0.85)` + `backdrop-filter: blur(8px)`,
viền dưới. Trái: lời chào (`Hi, {first_name|handle}`). Phải: nút Log out.

### Item chưa port
Render dạng `<span class="nav-link disabled">` (mờ `opacity: 0.4`, không click,
`title="Coming soon"`). **Quy trình mở page mới**: port xong → thêm child route
trong `router.js` → đổi `enabled: false → true` trong `navItems`.

```js
// AppLayout.vue — nguồn dữ liệu nav duy nhất cho cả sidebar lẫn tab bar
const navItems = [
  { to: '/dashboard', icon: 'fa-house',       label: 'Home',    enabled: true  },
  { to: '/coach',     icon: 'fa-dumbbell',    label: 'Coach',   enabled: false },
  { to: '/friends',   icon: 'fa-user-group',  label: 'Friends', enabled: false },
  { to: '/profile',   icon: 'fa-user',        label: 'Profile', enabled: false },
];
```

---

## 7. Chuyển động (motion)

Giữ tối giản — chuyển động phục vụ cảm giác liền mạch, không trang trí.

| Hiệu ứng | Thời lượng | Dùng ở |
|---|---|---|
| Fade chuyển trang (`.fade`) | `0.18s` | `<RouterView>` (mode `out-in`) |
| Sidebar mở rộng | `0.18s ease` | width + opacity nhãn |
| Progress/chart bar | `0.3s` | width / height |

---

## 8. Layout pattern cho page mới

```vue
<template>
  <main style="max-width: 820px; margin: 0 auto; padding: 8px 16px">
    <!-- mỗi khối là một .card, cách nhau margin-top: 14px -->
    <section class="card">…</section>
    <section class="card" style="margin-top: 14px">…</section>
  </main>
</template>
```

- Trạng thái: `loading` → `<p class="muted">Loading…</p>`; lỗi → `<p class="error">`.
- Gọi API qua `lib/api.js` (envelope `{ ok, data, message }`, throw khi `!ok`).
- KHÔNG tự render header/nav trong page — đã có `AppLayout` lo.
- Form auth (login/signup): `max-width: 380px`, căn giữa dọc (`margin: 12vh auto`),
  bọc trong `.card`.

---

## 9. Checklist khi thêm page mới

- [ ] View đặt ở `client/src/views/`, chỉ chứa nội dung (không header/nav).
- [ ] Dùng token màu + component có sẵn (`.card`, `.bar`, `.tile`, `.icon-btn`).
- [ ] Icon = Font Awesome `fa-solid`, **không emoji**.
- [ ] Thêm child route dưới `AppLayout` trong `router.js`.
- [ ] Bật `enabled: true` cho mục tương ứng trong `navItems`.
- [ ] Bề rộng nội dung `820px`, các section cách nhau `14px`.
- [ ] Kiểm thử cả desktop (sidebar) lẫn mobile (< 768px, tab bar).
