# Swift Overview Migration Tracker

Goal: make the Swift app overview match the current PHP web dashboard in data behavior, visual hierarchy, and tactile 3D design language.

## Phase 1 - Data Parity Foundation

- [x] Create this migration tracker.
- [x] Extend Swift dashboard models to decode the full overview payload.
- [x] Add an app-native dashboard day API that returns JSON only, no server-rendered HTML.
- [x] Add API client/session methods for date-specific dashboard loading.
- [x] Run syntax checks for touched PHP and Swift-facing files where practical.

## Phase 1 Scope

Build the data contract that SwiftUI can render from:

- Today or selected-day calorie progress.
- Macro totals and macro goals.
- Last 7 days calorie and macro trend arrays.
- Meal category totals for Breakfast, Lunch, Dinner, Snack.
- Intake entries for the selected day.
- XP level and streak summary.
- Focus-card facts: remaining calories, over-limit amount, macro focus, BMI.
- Physical info and weight trend points for the overview widgets.

## Deferred To Phase 2

- Rebuild the SwiftUI layout to mirror the PHP overview order.
- Add the horizontal calendar strip UI.
- Add Nutrition / Weight / Meals segmented stats hub.
- Add bento meal visualization, mascot card, streak card, and focus card.
- Visual verification in light mode, dark mode, and 375px-class iPhone widths.

## Phase 2 - SwiftUI Overview Layout

- [x] Switch `DashboardView` from `DashboardSummary` to `DashboardDayPayload`.
- [x] Add selected-date state and horizontal calendar strip.
- [x] Add welcome banner and today progress card matching the web overview order.
- [x] Add Nutrition / Weight / Meals stats hub.
- [x] Add meal bento, mascot, streak, focus, level, and side-summary equivalents.
- [ ] Build with `xcodebuild` using a writable DerivedData path.

## Phase 2 Completion Log

- Rebuilt `DashboardView.swift` as a mobile-first SwiftUI equivalent of the PHP overview.
- Added guest/mock data fallback so guest mode still renders a full overview.
- Added custom SwiftUI mini charts and ring visualizations without adding dependencies.
- Added durable new-user onboarding routing for Swift by returning `needs_onboarding` from auth/current-user APIs.
- Fixed the mobile onboarding save endpoint to match `dashboard/set-goal.php` persistence, including `userPhysicalInfo`, `weight_log`, preferences, and `userGoal`.
- Aligned the Swift onboarding wizard closer to `set-goal.php`: persistent top progress bar, plan-ready/loading states, first-name welcome, and maintain-goal CTA behavior.
- Matched `IntakeHistoryView` summary progress styling to the dashboard progress card.
- Reworked history meal entry rows into compact meal cards with a bounded food title, meal icon, kcal block, and macro chips.
- Grouped `IntakeHistoryView` meal history into day/month/year timeline sections with per-day kcal and entry counts.
- Limited `IntakeHistoryView` to 7 data-days per page and added Previous / numbered pages / Next pagination.
- Added mock `IntakeHistoryView` data for guest/unauthenticated preview, including 11 days of meals so pagination is visible.
- Fixed Swift AI Coach `Add to Log` parity: food suggestion cards now persist with chat messages, reload from conversation history, and log through the intake API with normalized meal categories.
- Verified Swift syntax with `swiftc -parse`.
- `xcodebuild` now uses the full Xcode developer directory, but the sandboxed build still stops in `actool` because CoreSimulator runtimes are unavailable to the process.

## Phase 1 Completion Log

- Added `api/dashboard/day.php` for date-specific overview data.
- Extended Swift dashboard data models with history, streak, focus, BMI, physical, meal, and weight payloads.
- Added `loadDashboardDay(date:)` to `APIClient` and `SessionStore`.
- Verified `api/dashboard/day.php` with `php -l`.
- Parsed changed Swift files with `swiftc -parse`.
- Full iOS build was not run during Phase 1 because `xcodebuild` required a full Xcode developer directory. The user has since set `/Applications/Xcode.app/Contents/Developer`.

## Notes

- Do not make Swift consume `dashboard/handlers/get_dashboard_day_data.php`; that endpoint returns `rowsHtml` for web DOM syncing.
- Prefer `api/dashboard/day.php` for app use.
- Keep PHP compatible with RMIT PHP 7.4 constraints: no PHP 8 string helpers, no `mb_*`, no process execution.
