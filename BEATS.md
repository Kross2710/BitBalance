# 🎵 Diet & Beats — Spotify & AI Nutrition Module Developer Guide

This guide is written for future AI agents and developers to quickly understand, debug, and improve the **Diet & Beats** gamified music-diet integration module in BitBalance.

---

## 📋 Module Overview

**Diet & Beats** is a premium, highly interactive module that connects a user's real-time music taste (via Spotify API) with their nutritional logging habits. It utilizes **Gemini AI** to calculate compatibility, recommend mood-based foods, and generate custom dietary archetypes.

### Key Visual Language
Following the **Duolingo-inspired 3D tactile system**, the UI uses:
- Chunky borders (`border: 2px solid var(--color-border)`) and raised shadows (`box-shadow: 0 8px 0 var(--color-border-subtle)`).
- satisfies `:active` buttons that depress vertically (`transform: translateY(4px)`).
- Animated mechanical record players, rotating vinyl plates, and pulsing audio equalizers built with Vanilla CSS and JS.

---

## 🗄️ Database & Schema

Three main database components support this module:

1. **`user_spotify` Table:**
   Stores connection tokens for Spotify accounts.
   - `user_id` (INT, Primary Key, Foreign Key -> `user.user_id`)
   - `access_token` (TEXT)
   - `refresh_token` (VARCHAR(255))
   - `expires_at` (TIMESTAMP)

2. **`weekly_wrapped_cache` Table:**
   Caches weekly AI-generated story slides and Spotify archetypes (to prevent massive redundant Gemini API bills). It stores a JSON payload containing the user's top track, artist, top food, weekly streak, and localized archetype captions.

3. **`intakeLog` Table:**
   Feeds the diet logs (food name, calories, meal category, datetime) into the AI analysis, querying the last 7 days of logs for fuel suggestions and 30 days for archetypes.

---

## 📂 File Architecture & Roles

```
/
├── dashboard/
│   ├── dashboard-beats.php          ← Main page showing Connected state vs. Spotify Auth Promo.
│   │                                  Hosts the interactive HTML5 3D DJ Mixer board & JS loops.
│   ├── dashboard-progress.php       ← Displays "Weekly Wrapped Story" utilizing the Spotify archetype.
│   └── handlers/
│       ├── spotify_auth.php         ← Starts Spotify OAuth2 redirection flow.
│       ├── spotify_callback.php     ← Spotify callback receiving authorization code, exchanging for tokens.
│       ├── spotify_disconnect.php   ← Deletes token rows from user_spotify.
│       ├── story_data.php           ← Fetches overall AI Dietary & Spotify Archetypes (cached weekly).
│       ├── beats_fuel.php           ← AI Food Pairings based on recent songs & remaining daily calories.
│       └── beats_mixer.php          ← core DJ Mixer combination endpoint (song + food -> matching score & witty caption).
│
├── css/
│   └── pages/
│       └── dashboard-beats.css      ← 3D boards, rotating vinyl animation keyframes, equalizers, flip cards.
│
└── BEATS.md                         ← This developer guide.
```

---

## 🔄 Core Architectural Workflows

### 1. Suggested Fuel for Your Current Beats (`beats_fuel.php`)
- **Frontend:** `dashboard-beats.php` fetches recent Spotify tracks on boot. If tracks exist, it passes them via POST to `/handlers/beats_fuel.php`.
- **Backend:** 
  - Retrieves the user's remaining calorie budget for the day.
  - Feeds the tracks and calorie budget to Gemini AI.
  - AI returns **exactly 3 food recommendations** matching the musical mood (Chill, Energetic, Sad, Happy, Focus) that fit within the remaining calorie budget.

### 2. AI Auto-Vibe DJ Mixer (`beats_mixer.php`)
- **Frontend:** 
  - Users select a track from their recent list and a food from their weekly log by clicking the `+` loader buttons.
  - Decks pulse with glowing CSS waveforms.
  - Clicking **MIX IT UP!** triggers the tonearm lowering, vinyl plates spinning, a Canvas 2D floating-notes animation, and synthesizes chiptune audio via the **Web Audio API**.
  - Sends a POST request containing only the selected song details and food calories.
- **Backend:** 
  - Gemini AI dynamically analyzes the song title and artist to detect the real musical genre/energy (e.g. *Upbeat Synthpop ⚡, Melancholic Ballad 🌧️*).
  - Rates the compatibility out of 100 between the detected vibe and the food item.
  - Returns: `detected_vibe`, `match_score`, and `comment`.

---

## 🛡️ AI Prompt Safeguards & Safety Rules

To keep the application welcoming and friendly, all prompts in this module must strictly adhere to the **Body Positivity Rule**:

> [!IMPORTANT]
> **BODY POSITIVITY MANDATE**
> - **ABSOLUTELY NO** comments about weight gain, weight loss, fatness, or shape.
> - **NO** negative shaming or judgment of the user's eating habits, calories, or late-night logs.
> - **INSTEAD:** Instruct Gemini AI to write motivating, positive, and witty comparisons between the *artistic properties* of the song (key, energy, tempo) and the *sensory features* of the food (taste, warmth, protein, comfort).

### Caching Layers
API calls to Gemini are shielded by robust local caching to minimize network request latency and token expenses:
- **Mixer Caching:** `$_SESSION['beats_mixer_cache']` caches the combination results using `md5($track|$artist|$food|$calories|$lang)`.
- **Fuel Caching:** `$_SESSION['beats_fuel']` caches daily food suggestions, keyed by date and the top track played.
- **Wrapped Caching:** `weekly_wrapped_cache` freezes AI stories for the entire ISO week (`date('W-Y')`).

---

## ⚠️ RMIT Server Constraints (Must Follow)

When modifying these handlers, you must bypass common server gotchas or the page will crash:
- **Strict PHP < 8.0 Compliance:** No `str_contains()`, `str_starts_with()`, `str_ends_with()`, null-safe operators (`?->`), `match` blocks, or named parameters.
- **No `mbstring` Extension:** Avoid all `mb_*` functions. Use the custom iconv polyfill helper `mixer_utf8_substr()` or regex matching `/./us` to count/slice UTF-8 string lengths safely.
- **Bypass SSL Verification:** External cURL calls to Spotify and Google Generative APIs must have SSL verification disabled or they will fail due to outdated system bundles:
  ```php
  curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
  curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
  ```

---

## 🚀 Future Roadmap & Improvement Ideas

Future developers or AI agents can easily expand the module in these areas:

1. **"Soundtrack Your Snack" (Song Logging Integration):**
   - Bind the current Spotify track ID and artwork directly to the food logs inside the database (`intakeLog.spotify_track`).
   - Create a beautiful scrollable **Spotify-Nutrition Timeline** displaying what song was playing during breakfast, lunch, or late-night logs.
2. **"Vibe Match Duel" (Social Comparisons):**
   - Query a friend's weekly cached Spotify archetype.
   - Let users "duel" their music-diet vibes against friends on the Leaderboard, with Gemini acting as a playful commentator deciding who has the most balanced lifestyle!
