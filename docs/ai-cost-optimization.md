# 💸 AI API Cost — Optimization Notes

Current model: **`gemini-3.1-flash-lite`** (cheapest tier) — see `include/secrets.php`.
Overall cost is **low** today: derived features are cache-shielded (~1 call/user/day or /week)
and the only uncached calls are user-initiated chats.

## Done
- **Killed the duplicate music-mood call.** `beats_fuel.php` used to call Gemini to infer mood
  from track names — redundant with The Mirror's genre fingerprint. Fuel suggestions are now a
  **deterministic** rule-map (`bb_beats_fuel_suggestions()`), folded into the single Mirror call.
  Saves ~1 Gemini call / user / day.
- **Moved The Mirror cache to the DB** (`beats_mirror_cache`) so the narration survives session
  loss → genuinely ~1 call / user / period instead of per session.

## Parked — Idea #3: prompt caching / prompt trimming for the CHAT endpoints

> **Status:** PARKED until usage scales. At flash-lite prices the saving is marginal today; revisit
> when chat volume (not the beats module) becomes the dominant line item.

The structurally largest future cost is the **uncached, per-message** chat/coach endpoints:
- `dashboard/handlers/ai_chat.php`
- `dashboard/handlers/mascot_chat.php`
- `handlers/ai_coach.php`
- `api/ai-coach/_helpers.php`

These cannot be result-cached (each turn is unique), but two levers help at scale:

1. **Context / prompt caching.** Each call resends a large fixed system preamble (persona, rules,
   formatting instructions). Gemini context caching lets that static prefix be cached server-side and
   billed at a reduced rate, so only the variable user turn is full-price input. Biggest win where the
   system prompt ≫ the user message.
2. **Prompt trimming.** Audit each chat prompt for redundant boilerplate; move stable instructions into
   the cached prefix; cap conversation history sent per turn (sliding window) so input tokens don't grow
   unboundedly with a long chat.

### When to revisit
- Chat/coach becomes the top cost line in Google AI Studio / Cloud Billing → API usage, **or**
- Active user base reaches the low thousands (per `docs/beats-tribe-plan.md`'s scale threshold).

### Measure first
There is no billing readout in code — before optimizing, pull real numbers from
**Google AI Studio / Cloud Billing → API usage** to confirm chat is actually the driver.
