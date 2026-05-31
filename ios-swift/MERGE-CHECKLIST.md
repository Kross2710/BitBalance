# Checklist: Gộp 2 bản Swift về 1 nguồn sự thật

> **Mục tiêu:** giữ bản `ios-swift/BitBalance/` (Xcode project) làm nơi build, cập nhật các file đã cũ hơn trong đó bằng file mới hơn từ `ios-swift/BitBalanceApp/` (bản rời), sau đó xóa bản rời.
>
> **Thời gian ước tính:** 30–45 phút.
>
> **Trước khi bắt đầu:** commit hoặc stash mọi thay đổi đang dở để có thể rollback nếu cần.

---

## Bước 0 — Kiểm tra lần cuối

- [ ] Chạy `git status` — không có file staged lẫn lộn.
- [ ] Trong Xcode, project `ios-swift/BitBalance/BitBalance.xcodeproj` **build thành công** trước khi merge.

---

## Bước 1 — Merge các file đã lệch nhau

Khi diff 2 bản, có **5 file thực sự khác nhau**. Xử lý từng file:

### 1a. `BitBalanceApp.swift` — bản rời MỚI HƠN

Bản Xcode đang thiếu: `BBFont`, `BBShadow`, các token màu mới (`info`, `infoBg`, `surfaceHover`, `accentHover`, `primarySoft`, `overlay`), `.bbShadow()` extension. Bản Xcode lại thêm 2 import mà bản rời không có.

**Làm:** Copy file từ bản rời sang bản Xcode, sau đó bổ sung 2 dòng import vào đầu file.

```bash
# Terminal (chạy từ thư mục gốc dự án)
cp ios-swift/BitBalanceApp/BitBalanceApp.swift \
   ios-swift/BitBalance/BitBalance/BitBalanceApp/BitBalanceApp.swift
```

Sau đó mở file vừa copy, thêm 2 dòng sau dòng `import UIKit`:
```swift
import AVFoundation
import AudioToolbox
```

- [ ] Copy file
- [ ] Thêm 2 import còn thiếu
- [ ] Build trong Xcode — không báo lỗi

---

### 1b. `Views/LogFoodView.swift` — bản Xcode MỚI HƠN

Bản Xcode có `MealCategoryItem` struct với emoji (🌅☀️🌙🍿) — đây là cải tiến UI mới hơn. Bản rời còn dùng tuple `(String, String)` cũ.

**Làm:** Copy file từ bản Xcode sang bản rời (ngược chiều với 1a).

```bash
cp ios-swift/BitBalance/BitBalance/BitBalanceApp/Views/LogFoodView.swift \
   ios-swift/BitBalanceApp/Views/LogFoodView.swift
```

- [ ] Copy file
- [ ] Build trong Xcode — không báo lỗi

---

### 1c. `Models/UserSession.swift`, `Services/APIClient.swift`, `Services/SessionStore.swift`

Chỉ khác nhau ở **dòng trắng thừa** hoặc comment nhỏ — không có logic khác biệt.

**Làm:** Dùng bản rời (sạch hơn) cho cả 3 file.

```bash
cp ios-swift/BitBalanceApp/Models/UserSession.swift \
   ios-swift/BitBalance/BitBalance/BitBalanceApp/Models/UserSession.swift

cp ios-swift/BitBalanceApp/Services/APIClient.swift \
   ios-swift/BitBalance/BitBalance/BitBalanceApp/Services/APIClient.swift

cp ios-swift/BitBalanceApp/Services/SessionStore.swift \
   ios-swift/BitBalance/BitBalance/BitBalanceApp/Services/SessionStore.swift
```

- [ ] Copy 3 file
- [ ] Build trong Xcode — không báo lỗi

---

## Bước 2 — Kiểm tra file chỉ tồn tại ở 1 bên

Các file này **đã khớp** (không cần làm gì thêm — chỉ tick xác nhận):

- [ ] `AppConfig.swift` — giống nhau ✅
- [ ] `Theme/Theme.swift` — chỉ là file ghi chú, giống nhau ✅
- [ ] `Views/DashboardView.swift` ✅
- [ ] `Views/MainTabView.swift` ✅
- [ ] `Views/ProfileView.swift` ✅
- [ ] `Views/LoginView.swift` ✅
- [ ] `Views/IntakeHistoryView.swift` ✅
- [ ] `Views/AIChatView.swift` ✅
- [ ] `Views/RootView.swift` ✅
- [ ] `Views/BarcodeScannerView.swift` ✅ (stub 1 dòng ở cả 2 — BarcodeScannerView thực được implement trong BitBalanceApp.swift)
- [ ] `Views/ImagePicker.swift` ✅ (stub 1 dòng ở cả 2)

---

## Bước 3 — Build + smoke test toàn bộ app

- [ ] Build `BitBalance` scheme trong Xcode — **0 errors, 0 warnings quan trọng**.
- [ ] Chạy Simulator, đăng nhập được.
- [ ] Mở tab Log → chọn meal category → thấy emoji (🌅☀️🌙🍿) → xác nhận `LogFoodView` mới đang chạy.
- [ ] Mở tab Dashboard → tải được summary.
- [ ] Mở tab AI Coach → gửi được tin nhắn.

---

## Bước 4 — Xóa bản rời

Sau khi bước 3 pass hoàn toàn:

```bash
rm -rf ios-swift/BitBalanceApp/
```

- [ ] Xóa thư mục `ios-swift/BitBalanceApp/`
- [ ] Build lại lần cuối trong Xcode — vẫn pass.

---

## Bước 5 — Cập nhật quy ước làm việc

Từ giờ **chỉ có 1 thư mục nguồn Swift**: `ios-swift/BitBalance/BitBalance/BitBalanceApp/`.

- [ ] Cập nhật `SWIFTUI-MIGRATION-PLAN.md` — gạch "chốt 1 thư mục nguồn" ở Giai đoạn 0.
- [ ] Mỗi khi thêm file Swift mới → thêm trực tiếp vào project Xcode (kéo thả hoặc File → New) để nó tự vào đúng compile sources, **không** tạo file rời ngoài thư mục.

---

## Rollback nếu có vấn đề

```bash
git restore ios-swift/
```

Lấy lại trạng thái trước khi merge.
