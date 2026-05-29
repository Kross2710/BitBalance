# BitBalance Story Share - Implementation Handoff

> Kế hoạch implement tính năng share ảnh 9:16 kiểu Instagram Story cho BitBalance.
> Cập nhật: 2026-05-29.

---

## Mục tiêu

Cho user bấm **Share** từ Progress/Achievements để generate một ảnh PNG 9:16
(`1080x1920`) có thể đăng Instagram Story/Facebook Story/TikTok.

Tinh thần: Strava achievement share + Spotify Wrapped, nhưng hợp BitBalance:
food-first, vui, gamified, 3D tactile, có badge như `Rice Goddess`,
`Banh Mi Baron`, `Pho Real`.

Prototype đã có:

- HTML: `docs/prototypes/bitbalance-story-prototype.html`
- PNG export: `docs/prototypes/bitbalance-story-prototype.png`

Prototype chỉ để tham khảo visual; implementation thật nên render từ data user.

---

## Scope MVP đề xuất

### 1. Entry points

Thêm nút share ở:

- `dashboard/dashboard-progress.php`
  - Hero/top area: `Share Weekly Wrapped`
  - Mỗi `.achievement-card`: `Share` achievement cụ thể

Không cần thêm vào dashboard overview ở MVP.

### 2. Story templates

MVP làm 2 template:

| Template | Use case | Data |
|---|---|---|
| `weekly_wrapped` | Tổng kết tuần | level, weekly XP, foods logged, streak, friend rank, top badge |
| `achievement_unlock` | Share một badge | achievement name, level, progress value, icon/tone |

Có thể thêm sau:

- `level_up`
- `streak`
- `food_personality`
- `leaderboard_rank`

### 3. Output

- Generate PNG `1080x1920`
- Nút chính: `Download Story`
- Nếu browser hỗ trợ Web Share API + file share:
  - hiện thêm `Share`
  - fallback vẫn download

---

## Data sources hiện có

Không cần migration mới cho MVP.

### XP / level

File:

- `include/handlers/xp.php`

Function:

- `xp_get_summary($pdo, $userId)`

Trả:

- `total_xp`
- `current_level`
- `xp_into_level`
- `xp_for_next`
- `progress_pct`

### Achievements

File mới đã có:

- `include/handlers/achievements.php`

Function:

- `bb_achievements_progress($pdo, $userId)`

Trả:

- `summary`
- `records`
- `achievements`

Các achievement MVP:

- `first_bite`
- `daily_logger`
- `streak_cooker`
- `full_plate`
- `balanced_bowl`
- `xp_grinder`
- `rice_goddess`
- `pho_real`
- `banh_mi_baron`
- `friend_fuel`
- `leaderboard_menace`
- `comeback_meal`

### Weekly stats cần cho `weekly_wrapped`

Query gợi ý:

```sql
SELECT
  COUNT(*) AS foods_logged,
  COUNT(DISTINCT DATE(date_intake)) AS logged_days
FROM intakeLog
WHERE user_id = ?
  AND date_intake >= DATE_SUB(NOW(), INTERVAL 7 DAY);
```

Weekly XP:

```sql
SELECT COALESCE(SUM(amount), 0)
FROM xp_event
WHERE user_id = ?
  AND created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY);
```

Top food:

```sql
SELECT food_item, COUNT(*) AS c
FROM intakeLog
WHERE user_id = ?
  AND date_intake >= DATE_SUB(NOW(), INTERVAL 7 DAY)
GROUP BY food_item
ORDER BY c DESC, food_item ASC
LIMIT 1;
```

Friend rank:

- Reuse `leaderboard_friends($pdo, $userId, 'weekly', 500)`
- Find row where `is_current_user === true`

Top badge:

- Use highest unlocked achievement by:
  - complete first
  - then highest `level / max_level`
  - then fun priority: `banh_mi_baron`, `rice_goddess`, `pho_real`

---

## Architecture đề xuất

### Files to add

```text
dashboard/handlers/story_data.php
css/components/story-share.css
js/story-share.js
```

Optional if keeping page-specific:

```text
css/pages/dashboard-progress.css  # extend existing file
```

### Files to edit

```text
dashboard/dashboard-progress.php
views/head_css.php
```

If using JS file loader manually, add script tag in `dashboard-progress.php`.

### Suggested responsibilities

#### `dashboard/handlers/story_data.php`

AJAX JSON endpoint.

Actions:

- `action=weekly_wrapped`
- `action=achievement_unlock&achievement_id=banh_mi_baron`

Must:

- require login
- return JSON only
- read-only, no CSRF required if GET; if POST, CSRF optional but consistent with app
- never expose private friend data beyond current user's rank

Response example:

```json
{
  "ok": true,
  "template": "weekly_wrapped",
  "user": {
    "name": "Hung",
    "level": 12
  },
  "stats": {
    "weekly_xp": 780,
    "foods_logged": 32,
    "streak": 14,
    "friend_rank": 1,
    "top_food": "Banh mi"
  },
  "badge": {
    "id": "banh_mi_baron",
    "name": "Banh Mi Baron",
    "icon": "fa-bread-slice",
    "tone": "accent"
  }
}
```

#### `js/story-share.js`

Client-side flow:

1. User clicks share button.
2. Fetch story data from `story_data.php`.
3. Render hidden story DOM at fixed `1080x1920`.
4. Export PNG.
5. Download or native share.

Recommended library:

- `html2canvas`

Implementation options:

1. CDN script in MVP:
   ```html
   <script src="https://cdn.jsdelivr.net/npm/html2canvas@1.4.1/dist/html2canvas.min.js"></script>
   ```
2. No build system needed.

Use:

```js
const canvas = await html2canvas(storyEl, {
  width: 1080,
  height: 1920,
  scale: 1,
  backgroundColor: null,
  useCORS: true
});
```

Convert:

```js
canvas.toBlob(blob => { ... }, 'image/png');
```

Download fallback:

```js
const a = document.createElement('a');
a.href = URL.createObjectURL(blob);
a.download = 'bitbalance-story.png';
a.click();
```

Native share if available:

```js
const file = new File([blob], 'bitbalance-story.png', { type: 'image/png' });
if (navigator.canShare && navigator.canShare({ files: [file] })) {
  await navigator.share({ files: [file], title: 'My BitBalance story' });
}
```

---

## Story DOM structure

Use one hidden container appended to body:

```html
<div id="storyExportRoot" class="story-export-root" aria-hidden="true">
  <article class="bb-story bb-story--weekly">
    ...
  </article>
</div>
```

Important CSS:

```css
.story-export-root {
  position: fixed;
  left: -99999px;
  top: 0;
  width: 1080px;
  height: 1920px;
  pointer-events: none;
}

.bb-story {
  width: 1080px;
  height: 1920px;
}
```

Do not use viewport units inside `.bb-story`; use fixed px so export is stable.

---

## Visual direction

Follow `AGENTS.md` design system:

- 3D tactile cards:
  - `border: 2px/4px solid var(--color-border)`
  - chunky bottom shadows
- Vibrant tokens:
  - `--color-primary`
  - `--color-secondary`
  - `--color-accent`
- Friendly rounded UI
- Dark mode support is nice in-app, but exported story MVP can be light mode only.

Prototype visual cues:

- large hero card
- top brand lockup
- badge burst
- XP bar
- 2x2 stat cards
- bright achievement strip

Avoid:

- flat Bootstrap styling
- tiny unreadable text
- generic fitness-bro tone
- gamifying under-eating or restriction

---

## Copy / tone examples

Weekly:

- `This week on BitBalance`
- `Still eating. Still leveling.`
- `32 foods logged`
- `780 XP earned`
- `14-day streak`
- `Top badge: Banh Mi Baron`

Achievement:

- `Achievement unlocked`
- `Rice Goddess`
- `The bowl recognizes your authority.`
- `Banh Mi Baron`
- `Diacritics optional. Devotion required.`
- `Pho Real`
- `Broth behavior: legendary.`

Keep humor light. Do not shame eating habits.

---

## Privacy / safety

- Friend leaderboard share should show only current user's rank by default.
- Do not include friends' names/photos in exported image in MVP.
- Do not include email, real name, weight, BMI, calorie goal, or exact calories unless user explicitly opts in.
- Prefer fun stats:
  - XP
  - foods logged
  - streak
  - achievement
  - rank

---

## RMIT hosting constraints

Do **not** server-render PNG with PHP extensions.

Reasons:

- GD/Imagick availability unknown.
- Shell/process functions are disabled.
- Long server requests risk timeout.

Use client-side render with browser APIs instead.

No `exec`, `shell_exec`, `proc_open`, `set_time_limit`, or `mb_*`.

If string matching Vietnamese text in PHP:

- avoid `mb_strtolower`
- current food keyword matching uses SQL `LIKE`
- if PHP Unicode normalization is needed later, follow `handlers/ai_coach.php` iconv/preg polyfill pattern

---

## Implementation checklist

- [ ] Add `story_data.php` endpoint
- [ ] Add story share buttons on `dashboard-progress.php`
- [ ] Add `story-share.css`
- [ ] Add `story-share.js`
- [ ] Load `html2canvas`
- [ ] Render hidden fixed `1080x1920` story DOM
- [ ] Export PNG with `html2canvas`
- [ ] Add download fallback
- [ ] Add Web Share API if available
- [ ] Test mobile width around 375px
- [ ] Test exported PNG dimensions exactly `1080x1920`
- [ ] Test logged-out page does not hit DB unnecessarily
- [ ] `php -l` changed PHP files

---

## Suggested first implementation path

1. Start with `achievement_unlock` only for one card.
2. Export PNG locally in browser.
3. Add `weekly_wrapped`.
4. Add native share.
5. Only then polish modal/template picker.

This reduces risk: if `html2canvas` has layout issues, they are found before
the data layer gets too broad.
