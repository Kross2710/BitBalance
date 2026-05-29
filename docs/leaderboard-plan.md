# 🏆 Leaderboard — Implementation Handoff

> Bản kế hoạch để implement tính năng Leaderboard ở session sau. Viết để đọc nguội
> (không cần context của session trước). Cập nhật: 2026-05-29.

---

## Bối cảnh: hạ tầng đã có sẵn (KHÔNG cần xây lại)

**XP system** — `include/handlers/xp.php`, migration `include/migrations/2026_05_29_add_xp_system.sql`:

- Bảng `user_xp(user_id, total_xp, current_level, last_level_up_at, last_finalized_date)`
- Bảng `xp_event(event_id, user_id, source, amount, ref_table, ref_id, created_at)` — ledger để tính XP theo khoảng thời gian
- Index sẵn: `idx_user_date (user_id, created_at)`, `idx_user_source_date (user_id, source, created_at)`
- Hàm sẵn: `xp_get_summary($pdo, $uid)`, `xp_for_level()`, `xp_level_for()`

**Friends system** — `include/handlers/friends.php`, migration `include/migrations/2026_05_29_add_friends_system.sql`:

- Bảng `friend_request(request_id, requester_id, addressee_id, status, created_at, responded_at)` — bạn bè = `status='accepted'`
- Bảng `friend_block`, cột `userStatus.profile_visibility ENUM('private','friends','public')`
- ⭐ **`friends_list($pdo, $me)` ĐÃ tính sẵn `weekly_xp`** (SUM `xp_event` 7 ngày) + `current_level`, `total_xp`, `logging_streak`, và **đã `ORDER BY weekly_xp DESC`** → query leaderboard về cơ bản đã tồn tại
- Page `dashboard/dashboard-friends.php` (3 tab: My Friends / Pending / Find People)
- AJAX dispatcher `dashboard/handlers/friends_action.php`

→ **Leaderboard chủ yếu là tầng hiển thị + thêm chính mình vào bảng xếp hạng**, không phải xây data mới.

---

## Phạm vi đề xuất

| # | Việc | File |
|---|---|---|
| 1 | Helper `leaderboard_friends($pdo, $me, $period, $limit)` — gồm **cả chính mình** + bạn bè, rank, weekly/all-time | `include/handlers/friends.php` (thêm hàm) |
| 2 | Widget top-5 trên dashboard (right sidebar) | sửa `dashboard/dashboard.php` + `dashboard/views/right-sidebar.php` |
| 3 | Tab "Leaderboard" đầy đủ trên trang Friends (rank đầy đủ, highlight dòng của mình, toggle Weekly/All-time) | `dashboard/dashboard-friends.php` + AJAX action mới trong `friends_action.php` |
| 4 | CSS 3D cho leaderboard (medal 🥇🥈🥉, hàng "you" nổi bật) | `css/pages/dashboard-friends.css` |

---

## Query lõi (đã sketch, chỉ cần thêm chính mình)

```sql
SELECT u.user_id, u.user_name, u.profile_image,
       COALESCE(ux.current_level, 1) AS current_level,
       COALESCE(ux.total_xp, 0)      AS total_xp,
       COALESCE(us.logging_streak, 0) AS logging_streak,
       COALESCE((
           SELECT SUM(xe.amount) FROM xp_event xe
           WHERE xe.user_id = u.user_id
             AND xe.created_at >= NOW() - INTERVAL 7 DAY
       ), 0) AS weekly_xp
FROM user u
LEFT JOIN user_xp    ux ON ux.user_id = u.user_id
LEFT JOIN userStatus us ON us.user_id = u.user_id
WHERE u.user_id = :me
   OR u.user_id IN (
       SELECT CASE WHEN requester_id = :me THEN addressee_id ELSE requester_id END
       FROM friend_request
       WHERE status = 'accepted' AND (:me IN (requester_id, addressee_id))
   )
ORDER BY weekly_xp DESC, total_xp DESC
LIMIT :limit;
```

- **All-time**: bỏ subquery weekly, `ORDER BY total_xp DESC` (nhanh hơn, đọc thẳng `user_xp`)
- **Rank** tính ở PHP sau khi fetch (loop +1), highlight `user_id === $me`

---

## Quyết định cần chốt đầu session sau

1. **Scope**: chỉ bạn bè + mình (riêng tư, mặc định) **hay** thêm global top (cần cân nhắc privacy + index `user_xp.total_xp`)?
2. **Period**: chỉ Weekly, hay Weekly + All-time toggle?
3. **Vị trí làm trước**: widget dashboard hay tab Friends?
4. **Tie-break** khi bằng XP: `total_xp` → `logging_streak` → tên?

---

## Gotchas / lưu ý

- **DB nội bộ RMIT** → không render/test local off-campus (xem MEMORY). Chỉ `php -l` + đọc code; test thật khi lên server.
- **CSRF**: leaderboard read-only → action GET trong `friends_action.php` không cần CSRF (chỉ mutations cần).
- **Privacy**: trong vòng bạn bè, level/streak/weekly_xp coi là public (đã thống nhất khi thiết kế Friends). Nếu làm **global** leaderboard phải lọc theo `userStatus.profile_visibility`.
- **Hiệu năng**: weekly subquery quét `xp_event` — đã có index `idx_user_date`. Vài chục bạn thì OK; global cần cân nhắc cache.
- **Reuse UI**: đã có `.friend-card` trong `css/pages/dashboard-friends.css`; leaderboard tái dùng + thêm cột rank/medal thay vì tạo component mới.
- **Empty state**: user chưa có bạn → leaderboard chỉ hiện mình ở rank #1, kèm CTA "Add friends to compete".

---

## Bước khởi động gợi ý

1. Đọc `include/handlers/friends.php` (xem `friends_list` đã làm gì) + `dashboard/views/right-sidebar.php`
2. Chốt 4 quyết định ở trên
3. Viết `leaderboard_friends()` → test query → widget dashboard → tab đầy đủ → CSS

---

## Design system reminder (theo AGENTS.md)

- 3D tactile: card `border: 2px solid var(--color-border)` + `box-shadow: 0 8px 0 var(--color-border-subtle)`
- Dùng token, KHÔNG hardcode hex; test cả light + dark
- Brand: green `--color-primary #58CC02`, blue `--color-secondary #1CB0F6`, orange `--color-accent #FF9600`
