# BitBalance — Improvements and Unfinished Work

Audited 2026-06-02. Scope: the entire web app (PHP / JS / CSS / SQL / docs).
The iOS app (`ios-swift/`) is **out of scope** by request and is not covered here.

How this was compiled: a multi-pass audit of the codebase (incompleteness markers,
dead code, convention violations), an end-to-end wiring check of the uncommitted
work-in-progress, an i18n parity check, a test-coverage review, a migration/deploy
review, and a sweep of every explicit "not done / pending / TODO" statement in the
project docs. Claims are backed by `file:line` references and, where noted, by live
queries against the local test DB.

Priority legend: **[BLOCKER]** breaks a feature now or would fatal on prod ·
**[HIGH]** · **[MED]** · **[LOW]** · **[BACKLOG]** documented-but-unbuilt feature.

---

## 0. Top priorities (start here)

1. **[BLOCKER]** Macro-goals migration is recorded as applied but never ran — the
   `userGoal` macro columns do not exist, which silently breaks the macro editor and
   the entire PT goal-proposal flow. See 1.1.
2. **[HIGH]** Three new migrations + the migration runner are untracked, so they are
   not deployed; the deploy script never runs migrations. See 7.1 / 7.2.
3. **[HIGH]** CSRF protection is only on the newest handlers; ~12 older
   state-changing endpoints have none. See 4.3.
4. **[HIGH]** Missing security headers and over-short HSTS. See 4.1.
5. **[MED]** French is 72 strings (incl. the whole macro/goal-proposal UI) still in
   English; `login.php` / `signup.php` / `dashboard-pt.php` bypass i18n entirely. See 3.

---

## 1. Blockers — features wired but broken on the live schema

### 1.1 [BLOCKER] `add_macro_goals` migration baselined but never executed
- **Verified by query (local `test` DB):** `SHOW COLUMNS FROM userGoal` returns only
  `userGoal_id`, `calorie_goal`. The migration's columns
  (`protein_goal`, `carbs_goal`, `fat_goal`, `set_by`, `source`) are **absent** —
  yet `schema_migrations` lists `2026_06_02_add_macro_goals.sql` as applied
  (bulk-stamped `2026-06-02 10:05:19`, same as every other row = baselined, not run).
  Evidence: `include/migrations/2026_06_02_add_macro_goals.sql:8-18`,
  `include/migrations/migrate.php` baseline path.
- **Why it is broken:** because the file is recorded as applied, a normal
  `migrate.php` run will **skip** it and never create the columns. Every read/write of
  those columns therefore fails with `ERROR 1054 Unknown column 'protein_goal'`.
- **Impact:**
  - `dashboard/handlers/update_macro_goals.php:50-53` — the save handler (wired to the
    "Save macros" button at `dashboard/dashboard-plan.php:463`) returns
    `{"ok":false,"error":"Database error: ... Unknown column 'protein_goal'"}` to the user.
  - `dashboard/handlers/functions.php:169-197` — `resolveMacroGoals()` wraps the SELECT
    in try/catch and silently falls back to the derived split, so dashboards do not
    crash but **stored macros are silently ignored**. (Used by 8 handlers.)
  - The PT goal-proposal flow shares the same columns on `pt_goal_proposal` and so is
    also non-functional (see 1.2).
- **Fix:** delete the `schema_migrations` row for `add_macro_goals`, then actually
  apply it (MySQL cannot cleanly re-run partially-applied DDL — verify which, if any,
  columns exist first). Confirm columns on both `userGoal` and `pt_goal_proposal`, then
  commit the three untracked migration files (see 7.1).
- [ ] Re-run `add_macro_goals` and verify columns exist on `userGoal` + `pt_goal_proposal`.

### 1.2 [BLOCKER] PT goal-proposal flow blocked by the same missing columns
- Wired end-to-end in code but every step names `protein_goal/carbs_goal/fat_goal`:
  create at `dashboard/handlers/pt_action.php:190-194` (`propose_goal`),
  display fetch at `dashboard/dashboard.php:37-49` (card included at `:324`),
  respond at `dashboard/views/_goal-proposal-card.php:101` →
  `dashboard/handlers/respond_goal_proposal.php:36`.
- The PT-side INSERT and the client-side "accept" path throw the same
  `Unknown column` error. `dashboard.php:51` swallows the fetch error and sets
  `$goalProposal = null`, so the proposal card simply never appears (silent failure).
- **Fix:** resolved by 1.1. After the migration runs, exercise propose → display →
  accept once on a real account.
- [ ] Verify propose/display/accept after 1.1 is fixed.

---

## 2. Half-built / incomplete features

- [ ] **[HIGH] Forum pages never migrated to the 3D theme** — `forum-list.css`,
  `new-topic.css`, `thread.css` are still on legacy `css/forum.css` (~0 design tokens).
  This is explicitly "the last TODO" of the UI migration. Source: `AGENTS.md` CSS
  architecture tree + migration-status note.
- [ ] **[HIGH] `dashboard/dashboard-pt.php` was never finished to project standards** —
  39 inline `style="..."` attributes (should be CSS classes), no i18n (47 inline
  `($lang === 'vi') ? ...` ternaries with **no French branch**), and English-only JS
  literals (`:983` "No meals logged today.", `:988` "Photo"). The single most
  unpolished file in the app. Contrast the sibling new partials `_macro-balance.php`
  and `_goal-proposal-card.php`, which are clean.
- [ ] **[MED] Mailer portability not implemented** — `include/mailer.php` does not
  exist; the password-reset flow does not actually send mail and `password_resets` is
  empty. RMIT firewalls outbound SMTP (25/465/587), so a `MAIL_DRIVER` abstraction
  (`mail` vs `smtp`) is required for host portability. Source:
  `docs/mailer-portability-plan.md` (status: "Chua implement").
- [ ] **[MED] Beats Mixer track energy uses an md5 heuristic, not real genre data** —
  TODO at `dashboard/handlers/beats_mixer.php:290`: ground the pair archetype via
  Last.fm genre (reuse `bb_mirror_lastfm_tags()` + the genre cache) like the Mirror
  does. Source: `docs/beats-next-steps.md` "Next feature work" §2.
- [ ] **[MED] Beats: live unlock + confetti on "Keep" not built** — the discovery dex
  only updates on reload. Needs `beats_mix_save.php` to return
  `archetype_key/icon/rarity`, `data-key` on locked cards, and a shared
  `unlockDexCard()` (transform card in place, bump progress, confetti, "New discovery"
  toast). Source: `docs/beats-next-steps.md` §1 ("highest value").
- [ ] **[LOW] Admin module is a work-in-progress** — user / system-log / content
  management. Source: `readme.md` System Overview.

---

## 3. Internationalization (i18n) gaps

The strict parity test (`tests/suites/I18nParityTest.php`) is green because it only
asserts hard invariants (no missing-vs-fallback keys, no empty values, placeholder
parity). The real gaps live in what it intentionally ignores:

- [ ] **[MED] French is 72 strings still verbatim-English**, including the entire new
  `macrobalance.*` and `goalproposal.*` namespaces (VI translates them fully). `fr.php`
  inherits `en.php` via `array_merge` so these resolve to English at runtime (not
  broken, but untranslated). Examples: `macrobalance.title/hint/save/saved/need100`,
  all `goalproposal.*`, plus much of `dashboard.mascot.*`, `intake.*`, `history.col.*`,
  `plan.*`. Source: `include/i18n/fr.php`.
- [ ] **[MED] 49 keys exist only in `vi.php`**, missing from `en.php` AND `fr.php` — the
  16 Nutrition-Wiki articles (`wiki.art.*.{title,summary,body}`) plus
  `progress.unit.kcal`. EN/FR users see the raw key for those Wiki articles.
  Decide: author EN/FR copy, or confirm VI-only is intended.
- [ ] **[MED] `login.php` and `signup.php` do not use i18n at all** (0 `t()` calls; no
  `login.*` / `signup.*` / `auth.*` namespace exists). Every label, placeholder, and JS
  string is hardcoded English — e.g. `login.php:43` "Welcome back", `:86` "Sign In";
  `signup.php:35` "Create your account", `:122` "Create Account", plus the password-rule
  JS strings `signup.php:219-235`.
- [ ] **[LOW] `dashboard-pt.php` uses EN/VI ternaries with no French path** (see 2) and
  has English-only JS literals.
- [ ] **[LOW] VI: ~7 genuinely-untranslated tokens** beyond legit shared ones —
  `dashboard.sidebar.wiki`/`wiki_short` ("Wiki"), `fab.ai_coach` ("AI Coach"),
  `intake.edit.macros_label` ("Macros"), `intake.unit.cal`, `profile.field.username`,
  `wiki.section.macros`, `wiki.title_tag`.
- [ ] **[LOW] Server-side JSON error strings are hardcoded English** and surfaced to
  users via alerts: `dashboard/handlers/respond_goal_proposal.php:11,15,19,28,49` and
  `dashboard/handlers/update_macro_goals.php:14,18,22,33,45`. Also JS fallbacks
  `dashboard/views/_goal-proposal-card.php:120,132` and the `' kcal'` suffix at
  `js/macro-balance.js:90`. Documented as a known follow-up in `docs/i18n.md`.

---

## 4. Security and hardening

- [ ] **[HIGH] Missing security headers** — add `X-Content-Type-Options: nosniff`,
  `X-Frame-Options` (or CSP `frame-ancestors`), a `Content-Security-Policy`, and
  `Referrer-Policy`. `Strict-Transport-Security` is present but only `max-age=300`
  (5 min — raise to ~6 months once HTTPS is confirmed stable). Source: `AGENTS.md`
  HTTP/headers section (verified 2026-06-01). Note: no `.htaccess`/`mod_rewrite` on
  RMIT, so set these via `header()` in a shared bootstrap include.
- [ ] **[HIGH] CSRF protection is half-rolled-out** — the newest handlers verify CSRF
  (`update_macro_goals.php:21`, `respond_goal_proposal.php:18`, `handlers/ai_coach.php`)
  but these older state-changing POST handlers do not, despite the infra existing in
  `include/csrf.php`: `delete_intake.php`, `restore_intake.php`, `edit_intake.php`,
  `process_intake.php`, `log_weight.php`, `delete_weight.php`, `update_goal.php`,
  `mascot_name.php`, `mascot_select.php`, `streak_actions.php`,
  `quick_log_from_history.php`, `beats_mix_delete.php`, and `soft_delete.php:27`
  (forum archive). Ownership scoping (`AND user_id = ?`) is present, but CSRF is not.
- [ ] **[MED] Raw DB errors leaked to the client** — `update_macro_goals.php:58`,
  `respond_goal_proposal.php:89`, and the `delete_intake.php` catch return
  `$e->getMessage()` to the browser. `display_errors` is **On** in prod, so this
  discloses DB internals. Return a generic message; log the detail server-side.
- [ ] **[MED] latin1 -> utf8mb4 migration not applied to prod** —
  `include/migrations/2026_06_01_convert_latin1_to_utf8mb4.sql` is committed but, since
  nothing runs migrations automatically (see 7.2), the 16 latin1 tables on prod
  (`forumPost`, `forumComment`, `userGoal`, `product`, `order`, `login_attempts`, ...)
  still corrupt non-ASCII text. Apply it on RMIT (after a `mysqldump`). Already-mangled
  data is unrecoverable. Also standardise the utf8mb4 tables on `utf8mb4_unicode_ci`
  (currently mixed `_general_ci`/`_unicode_ci`). Source: `AGENTS.md` Database section.
- [ ] **[LOW] No far-future cache headers on static assets** — `css/`, `js/`, `images/`
  are served gzip with ETag/Last-Modified but no `Cache-Control`/`Expires`, so browsers
  revalidate (304) every load. Add far-future caching for static assets.

---

## 5. Code quality, conventions, and cleanup

### 5.1 Dead / orphaned files (tracked in git, referenced nowhere)
- [ ] **[MED] `csrf_test.php`** — standalone scratch CSRF page, zero references; also
  contains emoji. Remove.
- [ ] **[MED] `captcha_image.php`** — calls `CustomCaptcha::generateImageCaptcha()`, but
  the live captcha is now a math-text question; zero references. Dead remnant.
- [ ] **[MED] `dashboard/dashboard-prototype.php` and
  `dashboard/dashboard-intake-prototype.php`** — orphaned design prototypes (emoji +
  placeholder data), never linked from any nav. Remove or move out of the web root.

### 5.2 Swallowed / missing error handling
- [ ] **[MED] `dashboard/handlers/get_dashboard_day_data.php:205`** — empty
  `catch (PDOException $e) {}` silently swallows the weight-log query failure; the chart
  renders empty with no signal.
- [ ] **[MED] `dashboard/handlers/update_goal.php:22-23`** — `$stmt->execute(...)` with
  no try/catch on a real form endpoint (`dashboard.php:1006`); a DB error throws an
  uncaught fatal instead of the redirect-with-error pattern used elsewhere in the file.

### 5.3 Convention violations — no inline styles
- [ ] **[MED]** `dashboard/dashboard-pt.php` — 39 inline `style=` blocks (see 2).
- [ ] **[LOW]** `dashboard/dashboard-intake.php:158,212,587,869,1015,1233` (line 869 also
  hardcodes `#dcfce7`/`#166534`); `dashboard/views/_intake-row.php:68,71,72`;
  `dashboard/dashboard-plan.php:424` + JS toggling `btn.style.opacity/cursor` at
  `:447-448` instead of a CSS class.

### 5.4 Convention violations — design tokens not used
- [ ] **[LOW]** `css/components/macro-balance.css:23,43,98,107,111-119` — macro colors
  hardcoded (`#f4b740`, `#4aa3f0`, `#46c46a`, `#dc2626`, ...) in a brand-new file;
  should use color tokens.
- [ ] **[LOW]** `css/pages/dashboard-home.css` — many literal brand colors (e.g.
  `#1cb0f6`, `#009be3`, `#ff6b6b`, `#1e2937` at lines 68, 362, 388, 1166) where
  `--color-*` tokens exist.

### 5.5 Convention violations — no emoji
- [ ] **[MED]** Live UI: `ai-coach.php` meal-icon emoji map (`:298`, rendered `:309`)
  and emoji baked into user-facing AI error messages (`:704,706,720`); `index.php:100-104`
  and `about.php:76,105-109` (medal/food emoji in the demo leaderboard);
  `css/components/intake-list.css:369-387` (emoji in CSS `content:` pseudo-elements).
- [ ] **[MED]** Mascot known deviations (`MASCOT.md` §11): emoji in
  `dashboard.mascot.pet_action/named_cheer/fed_today` i18n strings, the `emoji` field in
  `mascot_species_catalog()`, and the paw glyph on the picker toggle. Replace with
  SVG/icon components.

### 5.6 Stale / misleading code
- [ ] **[LOW] Wrong comment in `dashboard/handlers/functions.php`** —
  `bb_beats_ai_text()` claims "Gemini direct ... blocked on RMIT". Per `AGENTS.md`
  (verified 2026-06-01) Gemini direct returns HTTP 200 from RMIT; the original failure
  was the SSL/CA issue (fixed via `CURLOPT_SSL_VERIFY* false`). Correct the comment.
- [ ] **[LOW] Leftover Vietnamese dev comments** in otherwise-polished handlers:
  `dashboard/handlers/delete_intake.php`, `edit_intake.php`, `update_goal.php:18-21`.
- [ ] **[LOW] `terms.php:5`** — commented-out `log_attempt(...)` call; clarify whether
  it is permanently disabled or pending re-enable.
- [ ] **[LOW] `mascot_stage_from_level()`** is dormant/unused (kept for a future
  life-stage feature); the `stage-*` CSS hook is likewise dormant. Document or remove.

> Verified clean (no action): no PHP 7.4 prod landmines remain — no bare
> `str_contains`/`str_starts_with`/`str_ends_with`, `match()`, nullsafe `?->`, or
> enums; all `mb_*` calls are `function_exists`-guarded. The deploy lint
> (`scripts/php74-lint.php`) is doing its job. No `var_dump`/`console.log` debug
> leftovers or dead `href="#"` links were found.

---

## 6. Test coverage gaps

All existing tests pass (50/50 suite methods + 127 standalone checks, run on XAMPP PHP),
and none are skipped/TODO. Gaps:

- [ ] **[HIGH] PT interaction has zero test coverage** — no suite touches PT chat,
  feedback, goal proposals, or acceptance.
- [ ] **[MED] Google OAuth has zero test coverage** — `include/handlers/google_oauth.php`
  is untested.
- [ ] **[MED] Signup/login flow barely tested** — only `CaptchaTest` + `UsernameTest`
  (handle generation); nothing covers the actual signup/login path, password hashing,
  sessions, or remember-me tokens.
- [ ] **[MED] Macro-goal DB path untested** — `DashboardTest::testMacroGoalFormulas`
  covers only the pure `getMacroGoalsFromCalorieGoal()` derivation; nothing exercises
  reading `userGoal.protein_goal/...`. (This is why the 1.1 blocker did not surface as a
  test failure.)
- [ ] **[LOW] AI-coach offline logic untested** — `DashboardTest::testGeminiApiStatus`
  makes a real network call to Gemini (needs a key, flaky); the `api/ai-coach` logic has
  no mocked coverage.
- [ ] **[LOW] Standalone harnesses are not auto-discovered** — `beats_identity_test.php`,
  `mascot_species_test.php`, `mascot_state_test.php` live in `tests/` root and must be
  run individually (the runner only scans `tests/suites/*Test.php`). Consider moving them
  under `suites/` or adding them to a CI entrypoint.

---

## 7. Migrations and deployment process

- [ ] **[HIGH] Three new migrations + the runner are untracked (not deployed)** —
  `2026_06_02_add_macro_goals.sql`, `2026_06_02_add_pt_goal_proposal.sql`,
  `2026_06_02_create_user_identity.sql`, plus `include/migrations/_runner.php` and
  `migrate.php` are all `??` in `git status`. Since deploy = `git pull`, none reach prod.
- [ ] **[HIGH] Deploy never runs migrations or tests** — `scripts/deploy.sh` is a
  `git pull --ff-only` + PHP-7.4 lint; migrations are applied by hand. Prod likely has
  **no `schema_migrations` table and no runner**. Recommended next-deploy order:
  1. Commit/push `_runner.php` + `migrate.php`, then `--baseline` once on prod to record
     the already-hand-applied migrations.
  2. Apply `add_macro_goals`, `add_pt_goal_proposal`, `create_user_identity` (and the
     committed latin1 conversion, with a backup) on prod **before** the dependent code
     goes live — otherwise `functions.php`, PT proposals, and Google OAuth fatal.
  3. Then push the dependent PHP.
- [ ] **[HIGH] RMIT `secrets.php` still points `OPENROUTER_MODEL` at a retired model**
  (`google/gemini-2.5-flash:free`, returns HTTP 404). Update it to
  `google/gemma-4-31b-it:free` on RMIT (also fixes the mascot, which shares the
  constant). `secrets.php` is gitignored, so this is a manual prod step. Source:
  `docs/beats-next-steps.md` deploy note.
- [ ] **[LOW] Other AI handlers still call Gemini-direct** — `ai_chat.php`,
  `story_data.php`, `handlers/ai_coach.php`, `api/ai-coach/_helpers.php`. The
  beats-next-steps doc lists migrating them to `bb_beats_ai_text()`; note the firewall
  diagnosis was wrong (the real fix was `CURLOPT_SSL_VERIFY* false`), so confirm whether
  they currently work on RMIT before investing in the migration.

---

## 8. Planned / backlog features (documented, not built)

These are explicitly deferred in the docs — listed so nothing is lost, not as immediate work.

- [ ] **[BACKLOG] Leaderboard** — full plan exists (`docs/leaderboard-plan.md`); mostly a
  display layer over existing XP+Friends infra. Four decisions open: friends-only vs
  global, weekly vs weekly+all-time, widget vs Friends tab first, tie-break order.
- [ ] **[BACKLOG] Story Share (Instagram-story PNG export)** —
  `docs/bitbalance-story-plan.md` checklist is fully unchecked, though some artifacts
  (`story_data.php`, `weekly_wrapped_cache`, `story-share.css`) may already exist;
  reconcile plan vs reality.
- [ ] **[BACKLOG] PT quick-reply on client cards** (PT-interaction roadmap #6) —
  `docs/pt-quick-reply-idea.md`, proposed/deferred 2026-06-01. Reuses `pt_chat.php`
  (`action=send`), backend ~0 changes; open decisions: mark-seen on send, full vs
  minimal, Enter-to-send with Vietnamese IME guard.
- [ ] **[BACKLOG] Mascot P3 — collectible skins** from Beats archetypes, equipped via
  `mascot_state.active_skin` (column reserved/unused). Plus: promote caption cache from
  session to a `mascot_chat_cache` table; more species; contextual moods (morning/night,
  hydration, milestones); proactive streak-lapse nudge. Sources: `MASCOT.md` §12,
  `docs/mascot-evolution-plan.md`.
- [ ] **[BACKLOG] Beats — "Soundtrack Your Snack"** (bind Spotify track to food logs,
  `intakeLog.spotify_track`) — `BEATS.md` §2. **"The Tribe"** (percentiles, k-means
  tribes, monthly Beats Wrapped) — parked, `docs/beats-tribe-plan.md`. **Pokedex v2** and
  **Card 2 "Weekly Fuel" redesign** — parked, `docs/beats-next-steps.md`.
- [ ] **[BACKLOG] AI cost optimization idea #3** (prompt/context caching + sliding-window
  history trim for chat endpoints) — parked until usage scales, `docs/ai-cost-optimization.md`.
- [ ] **[DECISION] Commerce pages (products / cart / purchase) were never built** —
  dropped from the original plan per `AGENTS.md`, but `docs/i18n.md` and
  `docs/ios-migration-guide.md` still reference them as existing/optional. Decide: build,
  or remove the stale references (see 9).
- [ ] **[LOW] cron usability on RMIT is unverified** — likely usable but the account has
  no crontab; verify before relying on scheduled jobs. Source: `AGENTS.md`.

---

## 9. Documentation cleanup

- [ ] **[LOW] Emoji in dev docs** violate the no-emoji rule: `BEATS.md`,
  `docs/beats-tribe-plan.md`, `docs/ai-cost-optimization.md` use emoji in section
  headings. Source: `docs/beats-next-steps.md` §3.
- [ ] **[LOW] Doc inconsistencies to reconcile:** products/cart referenced as existing in
  `docs/i18n.md` + `docs/ios-migration-guide.md` though never built (see 8); the Story
  Share and Leaderboard handoff checklists are unticked even though some of their
  artifacts appear to exist — verify status against code.

---

## Appendix — verified NOT issues (so they are not re-investigated)
- Google OAuth is complete and wired end-to-end (entry buttons gated by
  `google_oauth_configured()` on login/signup, full callback with state check +
  account linking + session regeneration; `user_identity` table genuinely applied).
- The migration runner (`_runner.php` / `migrate.php`) is complete (tracking table,
  quote/comment-aware splitter, dry-run/baseline, CSRF, access guard). The baseline
  feature is what produced the 1.1 state — a process risk, not a code bug.
- Dev tooling (`dev/doctor.php`, `dev/seed.php`, `scripts/hooks/`, `php74-lint.php`) is
  functional; `seed.php` is already macro-column-aware.
- `fr.php` inheriting `en.php` via `array_merge` is by design (0 missing keys); the
  untranslated French strings in 3 are the real gap, not a parity break.
- `soft_delete.php` is live (forum archive), not dead — but it lacks CSRF (see 4.3).
- Deprecated CSS shims (`css/themes/*.css`) are intentional no-op back-compat files.
