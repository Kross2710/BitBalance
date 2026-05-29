# BitBalance iOS Migration

This folder is the starting point for translating the current PHP/MySQL web app into a SwiftUI iOS app.

## Recommended Architecture

Use SwiftUI for the iOS client and keep the current PHP/MySQL app as the backend at first.

Do not rewrite the backend in Express.js yet. The existing project already has the database schema, auth logic, XP logic, intake handlers, AI coach handlers, and RMIT deployment constraints. The lowest-risk path is:

1. Add JSON API endpoints to the existing PHP app.
2. Build SwiftUI screens that call those endpoints.
3. Keep session/cookie auth initially.
4. Move to token auth later if App Store/distributed mobile usage needs it.
5. Only consider Express.js/NestJS/Laravel after the API contract is stable.

## Local URLs

For iOS Simulator:

```text
http://localhost/BitBalance-2.0---Calorie-Tracker/
```

For a real iPhone on the same Wi-Fi:

```text
http://<your-mac-lan-ip>/BitBalance-2.0---Calorie-Tracker/
```

Production:

```text
https://titan.csit.rmit.edu.au/~s3974781/bitbalance/
```

## Xcode Setup

1. Install Xcode from the Mac App Store.
2. Open Xcode once and accept the license/components install.
3. In Xcode, create a new project:
   - iOS
   - App
   - Product Name: `BitBalance`
   - Interface: `SwiftUI`
   - Language: `Swift`
4. Save it inside this `ios-swift/` folder.
5. Copy or drag the files in `BitBalanceApp/` into the Xcode project.
6. In `AppConfig.swift`, choose the correct backend base URL.

## HTTP During Local Development

iOS blocks plain HTTP by default. For local XAMPP testing, add an App Transport Security exception in the Xcode project `Info.plist`.

Development-only option:

```xml
<key>NSAppTransportSecurity</key>
<dict>
    <key>NSAllowsArbitraryLoads</key>
    <true/>
</dict>
```

Use HTTPS for production.

## Backend API Available Now

The Swift starter now uses these JSON endpoints:

```text
POST /api/auth/login.php
POST /api/auth/logout.php
GET  /api/me.php
GET  /api/dashboard/summary.php
POST /api/intake/create.php
GET  /api/intake/history.php
POST /api/intake/update.php
POST /api/intake/delete.php
GET  /api/profile/get.php
POST /api/profile/update.php
```

These endpoints should reuse the existing PHP functions and PDO connection instead of duplicating business logic.

The Xcode project currently has three tabs after login:

- Dashboard
- Log
- History
- Profile

## Test On iPhone

Simulator can use `localhost`, but a real iPhone cannot. A real iPhone needs your Mac's LAN IP.

1. Make sure your Mac and iPhone are on the same Wi-Fi.
2. Start Apache and MySQL in XAMPP.
3. Find your Mac IP:

```bash
ipconfig getifaddr en0
```

If that prints nothing, try:

```bash
ipconfig getifaddr en1
```

4. Open this URL in Safari on the iPhone:

```text
http://<your-mac-ip>/BitBalance-2.0---Calorie-Tracker/
```

Example:

```text
http://192.168.1.10/BitBalance-2.0---Calorie-Tracker/
```

5. In `BitBalanceApp/AppConfig.swift`, change `baseURL` to that same IP.
6. Connect iPhone by USB or use Xcode wireless debugging.
7. In Xcode, select your iPhone as the run target and press Run.

If the phone cannot connect:

- Confirm the iPhone can open the PHP app in Safari first.
- Check that macOS firewall is not blocking incoming connections to Apache.
- Make sure `include/db_config.php` points to the local XAMPP database.
- Keep the local HTTP App Transport Security exception while testing.
