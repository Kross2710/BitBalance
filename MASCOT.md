# Mascot — Pet Companion Module Developer Guide

Developer guide for the dashboard **pet mascot** (the "Mascot Room" card). Written so a
future dev or AI agent can understand, debug, and extend the feature quickly. The
forward-looking roadmap lives in [`docs/mascot-evolution-plan.md`](docs/mascot-evolution-plan.md);
this file documents **what exists now and how it was built**.

> Status: P0 + P1 + P2 shipped (2026-06-01). P3 (skins) is proposed, not built.

---

## 1. What the mascot is

A small SVG pet on the dashboard right sidebar that reacts to the user's daily nutrition and
talks via a short AI caption. It is **persistent** (named, remembered across days) and the user
can **switch species** (currently Blue Owl or Calico Cat). Its level mirrors the shared XP
system, so the pet visibly "grows with you" as you log meals.

Three things drive the whole feature:

- **`vibeState`** — derived live from today's numbers (no storage). Four states:
  `neutral` (no logs), `overlimit` (over calorie goal), `healthy` (at goal + protein ok),
  `deficit` (low protein). Computed in JS in `dashboard.php`.
- **species** — which creature is shown (free choice, persisted).
- **level** — read from the existing XP system (`user_xp`), never stored by the mascot.

---

## 2. Build history (the three phases)

### P0 — Persistence foundation
- `mascot_state` table (one row per user), created at runtime (no migration runner on RMIT).
- Pure, CLI-testable helpers: `mascot_sanitize_name()` (UTF-8-safe, no mbstring),
  `mascot_stage_from_level()` (a life-stage map kept for possible future use — **dormant**).
- **Key decision:** a full XP/level system already existed (`include/handlers/xp.php`,
  `user_xp`) and logging already grants XP, so the mascot **reads** `xp_get_summary()` instead
  of storing its own XP. One unified progression.

### P1 — Identity & feedback
- Name your pet (inline form -> `mascot_name.php` -> stored name; nameplate + rename pencil).
- The caption prompt gained **memory**: the pet's name + companion level are read server-side
  (un-spoofable) and woven into the prompt; both join the cache key.
- A **Level chip** on the card and a once-per-day "fed" greeting make the logging -> XP -> pet
  link visible (the "feed = log" idea).

### P2 — Multi-pet Picker (owl + cat vertical slice)
- **Species is the collection axis** (free choice, no unlock, no life-stages). This replaced the
  earlier "owl evolves egg -> sage" idea to avoid a combinatorial art matrix.
- Per-species names; the P1 single name migrates forward to the owl on first read.
- A species registry holds art + personality; the caption handler became species-aware; a picker
  swaps the visible SVG instantly and persists the choice.

---

## 3. File architecture & roles

```
include/handlers/
  mascot_state.php     Pure helpers (sanitize name, level->stage) + DB helpers:
                       runtime CREATE for `mascot_state` + `mascot_pet_names`, the
                       `active_species` column ALTER, get/set name (per species),
                       get/set active species, get-all-names, legacy-name migration.
  mascot_species.php   Pure registry: catalog (owl + cat) with localized name/persona +
                       per-state flavor (EN/VI), and mascot_render_svg() per species.
                       No DB / no cURL -> CLI-testable.
  xp.php               PRE-EXISTING. Level curve + awards. The mascot only READS it.

dashboard/handlers/
  mascot_chat.php      AJAX caption endpoint. Builds a species-aware roleplay prompt from
                       the registry + name + level, then OpenRouter -> Gemini -> static
                       fallback. Session-cached by a content hash.
  mascot_ai.php        OpenRouter payload/parse + UTF-8 helpers (pre-existing).
  mascot_name.php      AJAX: set a species' pet name (auth-gated).
  mascot_select.php    AJAX: set the active species (auth-gated, validated).

dashboard/
  dashboard.php        Server: reads species + per-species names + level; renders one SVG
                       per species (only the active one visible), the Level chip, nameplate,
                       naming form, and the paw picker. JS: vibeState, petMascot() caption
                       fetch, mascotCheer(), naming flow, species swap.

css/pages/
  dashboard-home.css   Mascot card, stage, shared scaffold styling (aura/Zzz/eyes/shadow),
                       owl + cat body styling, state animations, picker + chips.

include/i18n/
  en.php, vi.php       dashboard.mascot.* strings.

tests/
  mascot_state_test.php    26 pure-logic checks (sanitize, level->stage).
  mascot_species_test.php  39 checks (catalog, validity, flavor text, SVG renderer).
```

---

## 4. Data model

All tables self-create at runtime (mirroring `beats_mirror_cache`), guarded by a `$_SESSION`
flag so the `CREATE` / `ALTER` runs at most once per session. RMIT has no migration runner.

```sql
-- One row per user. Holds pet-only attributes XP doesn't.
mascot_state(
  user_id PK, name (legacy single name from P1),
  active_species DEFAULT 'owl',   -- added via ALTER ... ADD COLUMN IF NOT EXISTS
  active_skin (reserved for P3), created_at, updated_at
)

-- Per-species names (P2). The P1 `mascot_state.name` is migrated into (user,'owl') on read.
mascot_pet_names(
  user_id, species, name, updated_at,
  PRIMARY KEY (user_id, species)
)
```

XP/level is **not** here — it lives in the pre-existing `user_xp` / `xp_event` tables.

---

## 5. How it works

### Caption flow (`mascot_chat.php`)
1. JS in `dashboard.php` computes `vibeState` from the card's data attributes and POSTs the
   metrics + `vibe_state`.
2. The handler reads, server-side: the active species, that species' name, and the XP level.
3. It pulls the species' identity + persona + state flavor from `mascot_species.php` and builds
   one EN or VI prompt. The **Body Positivity Mandate** is appended verbatim.
4. Fallback chain: OpenRouter -> local Gemini -> static strings.
5. Result cached in `$_SESSION['mascot_chat_cache']`, keyed by
   `md5(metrics | lang | species | name | level)` so a swap/rename/level-up refreshes the line.

### Species swap (the picker)
- Every species' SVG is rendered inline; only the active one lacks the `hidden` attribute.
- The paw button toggles a strip of species options. Clicking one:
  toggles which SVG is visible, updates the nameplate/form to that species' stored name, plays a
  little bounce, then POSTs to `mascot_select.php` to persist. No page reload.

### Naming
- Names are per species. The form/rename sends the active species; `mascot_name.php` stores it
  in `mascot_pet_names`. Switching species shows that species' own name (or the naming form if
  it's still unnamed).

---

## 6. Art architecture: shared scaffold + per-species body

The cost-saver that makes "species x 4 states" cheap:

- **Shared (styled once, reused by every species' SVG):** the health-aura glow
  (`.health-aura`), floating `.zzz-text`, `.mascot-shadow`, the eye stack
  (`.mascot-eye-outer/inner`, `.mascot-pupil`, `.mascot-shine`, `.mascot-eyes-closed`), and the
  `owlBob` / `petBounce` animations. Because the cat reuses these eye + aura + Zzz classes, the
  `healthy` (aura) and `overlimit` (closed eyes + Zzz) states work for it with zero extra CSS.
- **Per species:** body/face/limb paths, plus the `deficit` prop and its animation
  (owl: sweatband + dumbbells; cat: a fish, the protein metaphor).

The 4 `vibeState` triggers never change per species — only the visual expression does.

---

## 7. Constraints honored

- **RMIT PHP 7.4** (local XAMPP is 8.2): no `match` / `str_contains` / `?->` / named args.
- **No `mbstring`:** UTF-8 length/slicing via the `/./us` regex helpers in `mascot_state.php`.
- **External cURL:** `CURLOPT_SSL_VERIFYPEER/HOST = false` (outdated CA bundle on RMIT).
- **Body Positivity Mandate:** every species' prompt inherits the "never comment on weight /
  shape / shame eating" rule. A sassier voice (the cat) must never tip into judging.
- **AI cost discipline:** prompts are cached; deterministic logic is preferred elsewhere.

---

## 8. Gotchas (learned the hard way)

- **`SVGElement` does not reflect the `.hidden` IDL property.** Setting `svg.hidden = true`
  on an `<svg>` is a silent no-op for the content attribute, so the species swap must use
  `setAttribute('hidden','')` / `removeAttribute('hidden')`. (HTML elements like the picker
  `<div>` are fine with `.hidden`.) The `:not([hidden])` selector reads correctly either way.
- **Adding a column to a runtime-created table** needs `ALTER TABLE ... ADD COLUMN IF NOT
  EXISTS` (MariaDB supports it; the fresh `CREATE` also includes the column). If the ALTER is
  unsupported, reads fall back to `'owl'`.
- **Legacy name migration:** the P1 single `mascot_state.name` is read-and-copied into
  `mascot_pet_names` for the owl on first `mascot_get_name(..., 'owl')`, so nobody loses a name.
- **Guest/demo mode:** the picker, level chip, and naming are login-gated; guests see a default
  owl with no DB reads.

---

## 9. Tests & local run

```
# Pure CLI unit tests (no DB):
/Applications/XAMPP/xamppfiles/bin/php tests/mascot_state_test.php     # 26 checks
/Applications/XAMPP/xamppfiles/bin/php tests/mascot_species_test.php   # 39 checks
```

Use the **XAMPP php binary** (PHP 8.2) for local runs; the app itself is served via XAMPP at
`http://localhost/BitBalance-2.0---Calorie-Tracker/`. DB round-trips were validated with
throwaway CLI scripts against the local XAMPP DB (table auto-create, sanitize, per-species names,
legacy migration). Browser checks confirmed the owl and cat render across states and the
attribute-based swap works.

---

## 10. How to add a new species

1. Append an entry to `mascot_species_catalog()` in `mascot_species.php`: `id`, `emoji`,
   `name_en/vi`, `persona_en/vi`, `default_name`, `css` (e.g. `species-dog`), and the four
   `states` flavor strings (EN + VI), honoring the Body Positivity Mandate.
2. Add a `mascot_svg_<id>_body()` returning the SVG inner markup. **Reuse** the shared classes
   (`.mascot-shadow`, `.health-aura`, `.mascot-eye-*`, `.mascot-eyes-closed`, `.zzz-text`) so the
   healthy/overlimit states work automatically; add a `deficit` prop group with its own class.
   Wire it into `mascot_render_svg()`.
3. Add the species' CSS in `dashboard-home.css` (body parts + the `deficit` prop show/animation
   under `.state-deficit .species-<id> ...`).
4. That's it — the picker, naming, caption handler, and schema are all species-agnostic and pick
   it up automatically.

Note: every species' SVG is inlined on the page. This is fine for a handful; beyond ~a dozen,
switch to AJAX-rendering or reload-on-select (see the plan doc).

---

## 11. Known deviation to clean up: no-emoji rule

`AGENTS.md` forbids emoji **anywhere** in the project (code, UI copy, docs) — use SVG/icon
components instead. P0–P2 introduced emoji that violate this and should be remediated:

- **i18n UI copy** (`en.php` / `vi.php`): `dashboard.mascot.pet_action`, `named_cheer`,
  `fed_today` contain emoji glyphs.
- **Species picker**: the `emoji` field in `mascot_species_catalog()` and the paw glyph on the
  picker toggle button are rendered to users.
- **Static fallback captions** in `mascot_chat.php` (some predate this work).

Recommended fix: replace picker/species emoji with the existing SVG/icon system and strip emoji
from the mascot i18n strings. Tracked here so it isn't forgotten.

---

## 12. Roadmap

See [`docs/mascot-evolution-plan.md`](docs/mascot-evolution-plan.md). Next up:

- **P3** — collectible skins from the Beats archetypes, equipped via `mascot_state.active_skin`,
  layered as CSS overlays on the shared scaffold (so one skin works across all species).
- **Cost** — promote the caption cache from session to a `mascot_chat_cache` DB table.
- More species (dog, ...) once the slice has proven the pipeline.
```
