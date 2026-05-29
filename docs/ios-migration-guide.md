# BitBalance iOS Migration Guide

## Backend Choice

Recommended now: keep the current PHP/MySQL backend and add JSON API endpoints.

Express.js is not the best first move for this project because it would duplicate auth, database access, calorie/intake logic, XP logic, AI coach integration, and RMIT hosting assumptions. A rewrite also creates two backend sources of truth while the iOS app is still being built.

Use this order instead:

1. PHP JSON API over the existing database and handlers.
2. SwiftUI iOS app consuming that API.
3. Stable API contract.
4. Optional backend rewrite later if deployment, maintainability, or real-time features justify it.

Good future backend choices:

- Keep PHP: fastest, least risky, works with current hosting.
- Laravel: best PHP upgrade path if the app grows and you can host it elsewhere.
- Express.js: fine for a small API, but easy to become messy without discipline.
- NestJS: better than raw Express for a larger typed backend.
- Supabase/Firebase: useful only if you are willing to move auth/database architecture.

## First API Endpoints

Already added:

```text
POST /api/auth/login.php
POST /api/auth/logout.php
GET  /api/me.php
GET  /api/dashboard/summary.php
```

Build these next before translating every screen:

```text
POST /api/intake/create.php
GET  /api/intake/history.php
POST /api/intake/update.php
POST /api/intake/delete.php
GET  /api/profile.php
POST /api/profile/update.php
```

Response shape:

```json
{
  "ok": true,
  "data": {},
  "message": null
}
```

Error shape:

```json
{
  "ok": false,
  "data": null,
  "message": "Human readable error"
}
```

## Development Environment

### Required

- Xcode from the Mac App Store
- XAMPP running Apache and MySQL
- Current BitBalance project at:

```text
/Applications/XAMPP/xamppfiles/htdocs/BitBalance-2.0---Calorie-Tracker
```

### Verify PHP App

Start Apache/MySQL in XAMPP, then open:

```text
http://localhost/BitBalance-2.0---Calorie-Tracker/
```

Make sure `include/db_config.php` points to local XAMPP while developing locally:

```php
$host = 'localhost';
$dbname = 'test';
$username = 'root';
$password = '';
```

Do not commit the local DB toggle if production credentials are expected in the repo state.

### Create the iOS Project

1. Open Xcode.
2. File -> New -> Project.
3. Choose iOS -> App.
4. Product Name: `BitBalance`.
5. Interface: SwiftUI.
6. Language: Swift.
7. Save under `ios-swift/`.
8. Add the starter Swift files from `ios-swift/BitBalanceApp/`.

### Local HTTP Exception

For Simulator testing against XAMPP HTTP, add this to `Info.plist`:

```xml
<key>NSAppTransportSecurity</key>
<dict>
    <key>NSAllowsArbitraryLoads</key>
    <true/>
</dict>
```

Remove broad HTTP allowances before production. Production should use HTTPS.

## Screen Translation Order

1. Login
2. Dashboard summary
3. Intake create/edit/delete
4. History
5. Profile
6. AI coach
7. Friends/social
8. Products/cart/purchase if still needed on mobile

## Testing On A Real iPhone

Simulator can call:

```text
http://localhost/BitBalance-2.0---Calorie-Tracker/
```

A real iPhone needs the Mac's Wi-Fi IP because `localhost` on the phone means the phone itself.

Find the Mac IP:

```bash
ipconfig getifaddr en0
```

If needed:

```bash
ipconfig getifaddr en1
```

Then verify from iPhone Safari:

```text
http://<your-mac-ip>/BitBalance-2.0---Calorie-Tracker/
```

Update Swift:

```swift
static let baseURL = URL(string: "http://<your-mac-ip>/BitBalance-2.0---Calorie-Tracker")!
```

Checklist:

- Mac and iPhone are on the same Wi-Fi.
- XAMPP Apache and MySQL are running.
- iPhone Safari can open the PHP site before testing the Swift app.
- macOS firewall allows incoming connections to Apache.
- Xcode has trusted the iPhone developer device.
- App Transport Security has a local HTTP exception for development.

## Notes for Swift Translation

- Use `user_id` for identity and links, not `user_name`.
- Show `first_name` as the friendly display name.
- Treat `user_name` as a handle only.
- Never place raw handles containing `#` into URLs.
- Keep date-sensitive logic client-aware: send ISO date/time and timezone offset when needed.
