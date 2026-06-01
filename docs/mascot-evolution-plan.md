# 🦉 Pet Mascot — Evolution Plan ("The Companion")

> **Status:** P0 + P1 SHIPPED (2026-06-01). P2/P3 proposed.
> This document is the master plan for turning the Blue Owl from a *stateless mood widget*
> into a *persistent companion* the user bonds with over time. Phases are independent.
>
> **Key build note:** a full XP/level system already exists (`include/handlers/xp.php`,
> `user_xp` table) and food logging already grants XP. So P0 did **not** build a parallel
> XP store — the mascot **reads** the shared level via `xp_get_summary()`. `mascot_state`
> owns only the genuinely-missing pet attribute (the name) plus a `active_skin` slot for P3.

---

## 1. Where we are today

The mascot is a single hand-drawn SVG **Blue Owl** living in a "Mascot Room" card on the
dashboard right sidebar.

| Piece | File |
|---|---|
| Owl SVG markup, `vibeState` computation, `petMascot()` click handler | `dashboard/dashboard.php` (~807–906, ~2033–2165) |
| AI caption endpoint (state → OpenRouter → Gemini → static) | `dashboard/handlers/mascot_chat.php` |
| OpenRouter payload/parse + UTF-8 helpers | `dashboard/handlers/mascot_ai.php` |
| State CSS: aura / Zzz / sweatband+weights / animations | `css/pages/dashboard-home.css` (~1025–1440) |
| Engine math we can reuse (10 archetypes, shared axes) | `include/handlers/beats_identity.php` |
| Localized mascot strings | `include/i18n/en.php`, `include/i18n/vi.php` |

**How it works now:** every dashboard load recomputes a `vibeState` purely from *today's*
numbers (`dashboard.php`):

```js
if (goal === 0)                         vibeState = 'neutral';   // no logs yet
else if (calories > goal)               vibeState = 'overlimit'; // sleepy Zzz
else if (calories>=goal && protein>=pg) vibeState = 'healthy';   // green aura
else if (protein < proteinGoal*0.7)     vibeState = 'deficit';   // sweatband + weights
```

A click POSTs the metrics to `mascot_chat.php`, which builds a roleplay prompt and returns
one ≤18-word caption, rendered with a typewriter effect. Results are cached in
`$_SESSION['mascot_chat_cache']` by `md5(metrics|lang)`.

### The core gap
**There is no persistence.** No name, no level, no XP, no memory, no evolution, no
collection. Refresh the page and the owl forgets everything. It is an *emotional readout of
today*, not a *pet*. Bonding — the thing that makes pet mechanics drive retention — requires
state that survives across days and rewards the core loop (logging + streaks).

### Hard constraints (must honor — see `BEATS.md`)
- **Body Positivity Mandate:** never comment on weight/shape/fatness; never shame eating. Every
  new caption path inherits this rule verbatim.
- **RMIT server:** PHP **< 8.0** (no `str_contains`, `?->`, `match`, named args), **no
  `mbstring`** (use the iconv/regex UTF-8 helpers already in `mascot_ai.php`), and external
  cURL must set `CURLOPT_SSL_VERIFYPEER/HOST = false`.
- **AI cost discipline** (see `docs/ai-cost-optimization.md`): prefer deterministic logic; when
  AI is used, cache in DB keyed by a content hash so a call is reused until inputs change.

---

## 2. Theme

> **"A companion that grows with you."**

The owl stops being a daily weather-vane and becomes a creature that *remembers you*, *levels
up when you stay consistent*, and *changes its look* as you progress — without ever guilt-
tripping. The emotional hooks are the proven trio: **a name** (ownership), **growth**
(progress you can see), and **collection** (skins worth chasing).

---

## 3. The plan, in phases

Each phase is shippable on its own. Priority order = P0 → P3.

### P0 — Persistence foundation ✅ SHIPPED
**Goal:** give the mascot a home in the DB. Small, boring, mandatory.

**Reused, not rebuilt:** XP/level/progression already live in `include/handlers/xp.php`
(`user_xp` table, level curve `50·n·(n−1)`, intake-log awards with a daily cap, streak
milestones, level-up rewards). The mascot reads `xp_get_summary()['current_level']` — there
is **no** mascot-specific XP store.

- New table `mascot_state` (one row per user), created at runtime like `beats_mirror_cache`
  (RMIT has no migration runner). It holds only what XP doesn't:

  ```sql
  CREATE TABLE IF NOT EXISTS mascot_state (
    user_id     INT(11)      NOT NULL PRIMARY KEY,
    name        VARCHAR(40)  DEFAULT NULL,   -- user-chosen pet name (P1)
    active_skin VARCHAR(30)  DEFAULT NULL,   -- equipped archetype skin (P3, unused yet)
    created_at  TIMESTAMP    NOT NULL DEFAULT current_timestamp(),
    updated_at  TIMESTAMP    NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
  );
  ```
- Helper `include/handlers/mascot_state.php`: pure CLI-testable core
  (`mascot_sanitize_name()`, `mascot_stage_from_level()` — egg→baby→adult→sage from the XP
  level) plus thin DB helpers (`mascot_ensure_state_table()`, `mascot_get_name()`,
  `mascot_set_name()`). No mbstring (UTF-8 via `/./us`); PHP 7.4-safe.
- Tests: `tests/mascot_state_test.php` (26 pure-logic checks, CLI).

**Effort:** S · **Impact:** foundational (nothing below works without it).

### P1 — Identity & feedback ✅ SHIPPED
**Goal:** make it *yours* and make it *react*.

1. **Name your owl.** ✅ Inline form on the mascot card (logged-in only) → `mascot_name.php`
   → `mascot_state.name`. A nameplate + rename pencil replace the form once named.
2. **Short memory.** ✅ `mascot_chat.php` reads the name + XP level server-side (un-spoofable)
   and weaves both — plus the streak it already had — into the EN/VI prompts. Name + level
   join the cache key so a level-up or rename refreshes the line. Still ≤18 words, body-positive.
3. **Feed = log (made visible).** ✅ XP is already granted on log by `xp_award_intake_log`. The
   visible tie-in: a **Level chip** on the card (the owl grows as you log) + a once-per-day/session
   "thanks for feeding me" cheer (`mascotCheer()`) when the user has logged that day. A fuller
   per-log eat animation on the Intake page is a future touch.

**Effort:** S–M · **Impact:** high (ownership + responsiveness, almost no new infra).

### P2 — Evolution by streak (the retention engine)
**Goal:** tie visible growth directly to the habit we already reward.

- The owl hatches and matures along `logging_streak` (table `streaks`):
  `egg (0) → baby (3) → adult (14) → sage (30+)`, thresholds tunable.
- Each stage = a variant of the existing SVG (bigger eyes → glasses/wise look for *sage*).
  Reuse the current `state-*` CSS class pattern; add `stage-*` classes layered on top.
- XP sources (all deterministic): +X for a daily log, bonus for hitting calorie *and* protein,
  streak-milestone bumps. Level-up shows a small celebration (reuse `petBounce` + a burst).
- **Anti-shame guardrail:** evolution only ever moves *forward or pauses* — a broken streak
  never visibly "kills" or downgrades the pet (it just naps until you return). This is a
  product rule, not a technical one, and it protects the Body Positivity Mandate.

**Effort:** M (mostly SVG variants + CSS) · **Impact:** high (growth you can see, on the core loop).

### P3 — Collectible skins from Beats archetypes (reuse what's built)
**Goal:** a collection chase with zero new identity engine.

- `beats_identity.php` already defines **10 hand-designed archetypes** (sprinter 🏋️,
  romantic 🌙, cozy 🧸, explorer 🌀, hype 🔥, minimalist 🍃, maestro 🎯, dreamer 🍰,
  snacker ⚡, strategist ♟️). Each becomes an **unlockable owl outfit/skin**.
- When a user reaches an archetype (already computed in `beats_mirror.php`), unlock its skin and
  let them equip it via `mascot_state.active_skin`. Skins are CSS overlays on the base owl
  (headband, vinyl-disc halo, chef hat…), not new SVGs.
- A small "Owl Wardrobe" grid shows owned vs. locked skins → the collection rarity that
  `BEATS.md` says makes archetypes feel valuable.

**Effort:** M · **Impact:** medium–high (collection loop; bridges the Mascot and Beats features).

### Later / nice-to-have
- **Contextual moods** beyond the 4 nutrition states: morning greeting, "time for bed" at night,
  hydration nudge, milestone party (streak 7/30, first 100 logs). Each is a deterministic
  trigger + a state CSS class.
- **Proactive mascot:** a gentle push ("Owly is waiting for your dinner log!") before a streak
  lapses — fits the existing notification surface, not a new channel.
- **DB caption cache** (cost): promote `mascot_chat`'s session cache to a `mascot_chat_cache`
  table keyed by `md5(user|date|vibe|stage|lang)` so a day's lines survive across sessions —
  same pattern as `beats_mirror_cache`. Cuts repeat API calls to ~one per state-change per day.

---

## 4. How it builds on what already exists

This is **additive**, not a rewrite. Every phase reuses current assets:

- **Streaks** (`streaks.logging_streak`) → evolution thresholds (P2). No new tracking.
- **Beats archetypes** (`beats_identity.php`) → collectible skins (P3). No new identity engine.
- **`vibeState` + `state-*` CSS** → extended with `stage-*` / `skin-*` layers (P2/P3).
- **3-tier AI fallback + body-positive prompts** (`mascot_chat.php`) → unchanged plumbing; we
  only enrich the prompt inputs (name, streak, yesterday) and add a DB cache later.
- **Runtime `CREATE TABLE IF NOT EXISTS`** pattern (`functions.php` / `beats_mirror_cache`) →
  how `mascot_state` ships without a manual migration on the RMIT box.

---

## 5. Risks / open questions
- **Scope creep into a full game.** Mitigation: phases are independent; P0+P1 alone already
  improve the feel. Don't build P2/P3 until P1 lands.
- **SVG variant cost (P2).** Four stages × possible skins could balloon art work. Mitigation:
  one base owl + additive CSS overlays; only the *stage* silhouettes are true variants.
- **Body Positivity Mandate under "evolution".** Growth/regression framing can read as
  judgmental. Mitigation: forward-or-pause only, never downgrade; "napping," never "dying."
- **AI cost as states multiply.** More moods = more unique cache keys. Mitigation: ship the DB
  caption cache (P-later) before adding many new contextual moods.
- **Migration safety.** `mascot_state` must self-create idempotently and tolerate the missing-
  table case (RMIT has no migration runner) — copy the `beats_mirror_cache` guard exactly.

---

## 6. Build status & next steps

**Done (P0 + P1):**
- ✅ `include/handlers/mascot_state.php` (pure name/stage helpers + DB helpers) + 26-check CLI
  test `tests/mascot_state_test.php`.
- ✅ Runtime `CREATE TABLE IF NOT EXISTS mascot_state` guard (mirrors `beats_mirror_cache`).
- ✅ `dashboard/handlers/mascot_name.php` (auth-gated name endpoint).
- ✅ `mascot_chat.php` reads name + XP level server-side and injects them (memory) into the
  EN/VI prompts; cache key includes them.
- ✅ `dashboard.php`: Level chip, nameplate + rename, inline name form, `stage-*` CSS hook,
  `mascotCheer()` + once-per-session "fed today" greeting. New i18n keys (en/vi) + CSS.

**Next (P2 → P3):**
1. SVG stage variants (egg/baby/adult/sage) keyed on the `stage-*` class the card already
   emits, driven by `mascot_stage_from_level()` (P2). Honour the forward-or-pause rule.
2. Owl Wardrobe: unlock + equip Beats archetype skins via `mascot_state.active_skin` (P3).
3. Cost: promote the mascot caption cache from session to a `mascot_chat_cache` table.
