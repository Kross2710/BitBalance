# 🌐 Diet & Beats — Direction 2: "The Tribe" (deferred until user base grows)

> **Status:** PARKED. Build **Direction 1 "The Mirror"** first (see `BEATS.md` → *The Mirror*).
> This document captures the agreed v2 so it can be picked up when the population is large enough.

---

## Why this is parked

"The Tribe" leans on **cross-user statistics** (percentiles, data-driven clusters). With a small
user base (the app DB is RMIT-internal, currently few users) percentiles are meaningless and
embarrassing — *"top 5% of 18 users"* — and k-means clusters are just noise. So this direction
unlocks only once there is a real population to compare against.

**Trigger to revisit:** roughly **a few hundred active Spotify-connected loggers** with ≥ 4 weeks
of data each. Until then, "The Mirror" (self-contained, cold-start-proof) carries the feature.

---

## Theme

> **"Who are you among the community?"**

Where The Mirror asks *"do you eat the way you listen?"* (you vs you), The Tribe asks
*"how do you compare to everyone, and which tribe do you belong to?"* (you vs the population).
It is the BitBalance answer to the **Spotify Wrapped** end-of-year ritual: a deck of
surprising-but-true numbers, several benchmarked against other users, revealed as an **event**.

---

## Three components

### 1. Stats deck with percentiles (option "B")
A scrollable deck of independent stat cards, several comparative:
- "Your protein intake is higher than **82%** of users who listen to workout music."
- "You tried **17** new foods this month — **top 9%** for adventurousness."
- "Top genre: **Lo-fi (42%** of listening)."
- "Night-owl: **64%** of meals logged after 9pm."

Requires **anonymous cross-user aggregate queries**. Never expose other users' identities — only
counts/percentile positions.

### 2. Data-driven tribes (k-means)
Cluster all users' shared-axis fingerprints (reuse The Mirror's engine vectors:
`energy / comfort / diversity / nocturnal`) to discover **real** archetypes instead of
hand-designed ones. A user is assigned to the nearest learned centroid.

- The cluster itself becomes a *stat*: "You belong to the **'Night-Owl Carb-Lover'** tribe — 6% of users."
- k-means has **no native PHP/MySQL support** → run it **offline** (cron/CLI job), store centroids in
  a table, assign users at read time via nearest-centroid.
- **Continuity hazard:** retraining renames/reshuffles clusters → a user's archetype can silently
  change, breaking the "collection". Mitigation: hand-label learned clusters and keep a stable
  cluster→name map across retrains; only add new clusters, never reorder existing IDs.
- Feature scaling required (normalize each axis) or one axis dominates the distance.

### 3. Monthly "Beats Wrapped" event
Compute over a long window and **reveal as a ritual**, monthly (year is too slow for a fitness app
and too data-sparse).
- Reuses the **existing Weekly Wrapped story rail** (`dashboard-progress.php?story=open`,
  `story_data.php`, `weekly_wrapped_cache`, `css/components/story-share.css`) — so this is mostly a
  longer-window variant + a "your new Wrapped is ready" push, not new infrastructure.
- Scarcity + accumulation + a shareable artifact = the emotional weight people loved about Wrapped.
- Archive past Wrappeds so users build a timeline of evolving identity.

---

## How it builds on Direction 1

The Mirror ships the reusable foundation The Tribe needs:
- **Shared-axis fingerprint engine** (`include/handlers/beats_identity.php`): the same
  `energy/comfort/diversity/nocturnal` vectors feed k-means and the percentile stats.
- **Archetype concept + collection UI**: hand-designed catalog becomes the *fallback / naming layer*
  for learned clusters.
- **Narration pattern** (deterministic numbers → Gemini writes the voice): unchanged; just fed
  population-level numbers instead of personal ones.

So The Tribe is an **additive upgrade**, not a rewrite.

---

## First concrete steps when triggered

1. Add a nightly CLI job that snapshots every connected user's fingerprint vector into a
   `beats_fingerprint_snapshot` table (date-stamped) — needed for both percentiles and k-means.
2. Build anonymous percentile queries over that table (gated behind a `MIN_POPULATION` constant so
   cards hide themselves when the cohort is too small).
3. Implement offline k-means (PHP CLI) → `beats_tribe_centroid` table with a stable
   `cluster_id → {name, emoji}` map.
4. Add a `time_range=long_term` monthly window to the fingerprint and slot a "Monthly Beats Wrapped"
   story variant onto the existing rail.

---

## Risks / open questions
- **Cold start everywhere** — every comparative number needs a `MIN_POPULATION` guard.
- **Privacy** — percentiles must be computed from anonymized aggregates; never leak per-user rows.
- **Cluster drift** — see continuity hazard above; needs a stable naming map.
- **Cost** — population batch jobs are cron-side (cheap); avoid per-request cross-user scans.
