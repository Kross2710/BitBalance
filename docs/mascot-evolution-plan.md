# 🦉 Pet Mascot — Evolution Plan ("The Companion")

> **Status:** P0 + P1 + P2 SHIPPED (2026-06-01). P2 = **Multi-pet Picker** (owl + cat vertical
> slice; replaces the earlier owl-evolution idea). P3 proposed.
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
up when you stay consistent*, and that you can *make your own* — without ever guilt-tripping.
The emotional hooks: **a name** (ownership), **growth** (the shared Level chip), and **variety**
(pick your species and, later, skins to make it yours).

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

### P2 — Multi-pet Picker (species = the collection axis) — ✅ SHIPPED (owl + cat slice)

> Shipped as a one-species vertical slice (owl + cat). Files: `include/handlers/mascot_species.php`
> (registry + per-species SVG renderers + 39-check `tests/mascot_species_test.php`),
> `mascot_state.php` (active_species column via `ALTER … ADD COLUMN IF NOT EXISTS` + per-species
> `mascot_pet_names` table, with the P1 owl name migrated forward), `dashboard/handlers/mascot_select.php`,
> species-aware `mascot_chat.php`, and the 🐾 picker UI in `dashboard.php` (instant SVG swap — note
> SVGElement needs the `hidden` *attribute* toggled, not the `.hidden` property). Add more species by
> appending to the catalog + drawing one SVG body on the shared scaffold.
>
> Original design decisions (locked 2026-06-01):
> **free choice** (no unlock/gating), **species is the sole collection axis** (no per-species
> life-stages), **per-species names**, **full character** per species (own look + personality +
> `deficit` prop). The `stage-*` CSS hook added in P1 goes dormant (not removed).

**Concept.** The owl is the default. The user freely switches species anytime via an inline
picker. Each species is its own character — own art, own name, own personality, own `deficit`
prop. No unlock logic (free choice) and no life-stages (species *is* the variety). The Level
chip (shared XP) stays the only progression axis.

**Why this shape.** Two product decisions collapsed the complexity:
- *Free choice* → no unlock logic at all; the picker is a pure selector.
- *Species = axis, no stages* → the art matrix is just `species × 4 states`, never
  `species × stages × skins × states`. Avoids combinatorial art blow-up.

**Art architecture — shared scaffold + per-species body** (the cost saver):
- *Shared across all species (drawn once):* the health-aura glow, the floating Zzz, the shadow
  ellipse, the speech bubble, and the `owlBob` / `petBounce` animations.
- *Per species:* body/face/limb paths, the closed-eyes shape (overlimit), and the `deficit`
  prop + its animation.
- The 4 `vibeState` triggers are unchanged — only the *expression* differs per species.

**Data:**
- `mascot_state.active_species` VARCHAR(20) DEFAULT 'owl'. Adding it to the already-existing
  runtime-created table needs an idempotent `ALTER TABLE ... ADD COLUMN IF NOT EXISTS` guard
  (MariaDB supports it), run lazily + session-flagged like the table create.
- NEW table `mascot_pet_names(user_id, species, name)`, PK `(user_id, species)` — names are
  per species. Migrate the existing `mascot_state.name` into `(user, 'owl')` on first access so
  nobody loses the name they set in P1.

  ```sql
  CREATE TABLE IF NOT EXISTS mascot_pet_names (
    user_id    INT(11)     NOT NULL,
    species    VARCHAR(20) NOT NULL,
    name       VARCHAR(40) NOT NULL,
    updated_at TIMESTAMP   NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
    PRIMARY KEY (user_id, species)
  );
  ```

**Species registry** — `include/handlers/mascot_species.php` (pure, no unlock conditions): each
species → `id`, default display name EN/VI, personality tone EN/VI, per-state flavor EN/VI
(incl. its own `deficit` metaphor), an SVG renderer reference, and a CSS class. The current owl
identity/flavor moves out of `mascot_chat.php` into this registry.

**Rendering & picker UX:**
- Render every available species' SVG inline, hidden except the active one; the picker toggles
  visibility → instant swap, no reload. (Fine for a handful of species; switch to AJAX-render
  only when the catalogue grows to dozens.)
- A 🐾 button next to the rename pencil opens a horizontal strip of species icons. Picking one
  persists via a new `mascot_select.php` endpoint (modelled on `mascot_name.php`).

**AI:** `mascot_chat.php` looks up the *active* species' identity + personality + state flavor +
that species' name; `active_species` joins the cache key. The **Body Positivity Mandate** applies
to every species' prompt verbatim.

**Slice example — the Cat (build the pipeline end-to-end on one species first):**
- Look: calico cat — ears, tail, whiskers. Personality: lazy/cozy, a touch sassy.
- States: `neutral` sits with curled tail · `healthy` content + shared aura · `overlimit` curls
  into a loaf, eyes closed + shared Zzz · `deficit` eyes/holds a fish 🐟 (on-message protein
  metaphor). Own name (default suggestion "Mochi").

**Open / defaulted (non-blocking):** unnamed → prompt uses the generic species identity ("the
Calico Cat"); initial catalogue = owl + cat, more species after the pipeline proves out.

**Tradeoff accepted:** free choice means no "unlock chase" — the Level chip carries progression;
species is pure personalisation/variety.

**Effort:** M engineering (registry, `active_species` + names table, SVG extraction/refactor, CSS
namespacing, picker UI + endpoint, species-aware prompts) + art/content that scales with the
number of species (the real cost). **Impact:** high (variety + ownership; sets up P3 skins).

### P3 — Collectible skins from Beats archetypes (reuse what's built)
**Goal:** a collection chase with zero new identity engine.

- `beats_identity.php` already defines **10 hand-designed archetypes** (sprinter 🏋️,
  romantic 🌙, cozy 🧸, explorer 🌀, hype 🔥, minimalist 🍃, maestro 🎯, dreamer 🍰,
  snacker ⚡, strategist ♟️). Each becomes an **unlockable owl outfit/skin**.
- When a user reaches an archetype (already computed in `beats_mirror.php`), unlock its skin and
  let them equip it via `mascot_state.active_skin`. Skins are CSS overlays on whichever species
  is active (headband, vinyl-disc halo, chef hat…), not new SVGs — they ride on the shared
  scaffold from P2, so one skin works across every species.
- A small "Wardrobe" grid shows owned vs. locked skins → the collection rarity that `BEATS.md`
  says makes archetypes feel valuable. (Sits alongside the P2 species picker.)

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

- **XP/level** (`xp.php`, `user_xp`) → the Level chip and the only progression axis. No new store.
- **Beats archetypes** (`beats_identity.php`) → collectible skins (P3). No new identity engine.
- **`vibeState` + `state-*` CSS** → the 4-state triggers stay; P2 adds `species-*` namespacing
  and P3 adds `skin-*` overlays, all riding the shared scaffold.
- **3-tier AI fallback + body-positive prompts** (`mascot_chat.php`) → unchanged plumbing; P2
  moves the hardcoded owl identity into the species registry and adds `active_species` to the key.
- **Runtime `CREATE TABLE IF NOT EXISTS`** pattern (`beats_mirror_cache`) → how `mascot_state`,
  `mascot_pet_names`, and the `active_species` column ship without a manual RMIT migration.

---

## 5. Risks / open questions
- **Scope creep into a full game.** Mitigation: phases are independent; P0+P1 alone already
  improve the feel. Build P2 as a one-species vertical slice before scaling the catalogue.
- **Per-species art/content cost (P2).** Each species needs body + closed-eyes + a `deficit` prop
  and personality text ×EN/VI. Mitigation: the shared scaffold (aura/Zzz/shadow/bubble/anims) is
  drawn once; only the body layer is per-species, and there are no life-stages to multiply it.
- **Migration of an existing column (P2).** `active_species` is added to a table that may already
  exist → needs `ALTER TABLE ... ADD COLUMN IF NOT EXISTS`, lazy + session-flagged (MariaDB OK).
- **Body Positivity Mandate across species.** Every species' personality prompt inherits the rule
  verbatim; a sassier voice (e.g. the cat) must never tip into judging food or body.
- **AI cost as species/states multiply.** More combos = more unique cache keys. Mitigation: ship
  the DB caption cache before the catalogue grows large.
- **Picker rendering at scale.** Inlining every species' SVG is fine for a handful; switch to
  AJAX-render or reload-on-select once the catalogue reaches dozens.
- **Migration safety.** `mascot_state` / `mascot_pet_names` must self-create idempotently and
  tolerate the missing-table case — copy the `beats_mirror_cache` guard exactly.

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

**Next — P2 Multi-pet Picker, as a one-species vertical slice (owl + cat):**
1. `include/handlers/mascot_species.php` registry (owl + cat: identity, personality, per-state
   flavor EN/VI, SVG renderer ref, CSS class) + a CLI test like `tests/mascot_state_test.php`.
2. Add `active_species` (ALTER guard) + `mascot_pet_names` table (migrate the P1 owl name in).
3. Extract the inline owl SVG into a per-species renderer; draw the cat (body + closed-eyes +
   fish 🐟 `deficit` prop) on the shared scaffold; namespace the CSS per species.
4. Make `mascot_chat.php` species-aware (identity/flavor/name from the registry; species in key).
5. `mascot_select.php` endpoint + the 🐾 picker UI (inline strip, instant toggle, no reload).
6. Verify, then scale the catalogue (dog, …) once the slice proves the pipeline.

**Then — P3 + cost:**
7. Wardrobe: unlock + equip Beats archetype skins via `mascot_state.active_skin` (skins ride the
   shared scaffold, so they work across species).
8. Promote the mascot caption cache from session to a `mascot_chat_cache` table.
