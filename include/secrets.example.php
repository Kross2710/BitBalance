<?php
/**
 * TEMPLATE for include/secrets.php (which is gitignored).
 *
 * Copy this file to include/secrets.php and fill in real values:
 *
 *     cp include/secrets.example.php include/secrets.php
 *
 * Every constant defined here is REQUIRED by the app and is what
 * dev/doctor.php validates against (it reads the define() names from this
 * file). When a PR adds a new secret/config constant, add it here too so the
 * Doctor flags any environment that is still missing it.
 *
 * Leave optional integration keys as '' to gracefully disable that feature
 * (Google sign-in buttons hide, Last.fm falls back to Gemini, etc.).
 *
 * NEVER commit real keys. include/secrets.php stays out of git.
 */

// --- Google Gemini (AI Coach / mascot / beats narration) --------------------
define('GEMINI_API_KEY', 'YOUR_GEMINI_API_KEY');

// --- AI Coach behaviour -----------------------------------------------------
define('AI_COACH_MODEL', 'gemini-3.1-flash-lite');
define('AI_COACH_DAILY_LIMIT', 20);                  // max messages per user per day
define('AI_COACH_MAX_IMAGE_BYTES', 5 * 1024 * 1024); // 5MB (RMIT upload cap is 5M/file)
define('AI_COACH_HISTORY_TURNS', 10);                // past messages kept as context

// --- Spotify (Diet & Beats) -------------------------------------------------
define('SPOTIFY_CLIENT_ID', 'YOUR_SPOTIFY_CLIENT_ID');
define('SPOTIFY_CLIENT_SECRET', 'YOUR_SPOTIFY_CLIENT_SECRET');

// --- Last.fm (artist genre/tags for "The Mirror") ---------------------------
// Free key: https://www.last.fm/api/account/create  — leave '' to disable.
define('LASTFM_API_KEY', 'YOUR_LASTFM_API_KEY');

// --- OpenRouter (free-tier AI fallback) -------------------------------------
define('OPENROUTER_API_KEY', 'YOUR_OPENROUTER_API_KEY');
define('OPENROUTER_MODEL', 'google/gemma-4-31b-it:free');

// --- Google OAuth (Sign in with Google) -------------------------------------
// Web-application OAuth 2.0 client; register redirect .../google_callback.php
// for BOTH local and the RMIT host. Leave '' to hide the Google buttons.
define('GOOGLE_CLIENT_ID', 'YOUR_GOOGLE_CLIENT_ID');
define('GOOGLE_CLIENT_SECRET', 'YOUR_GOOGLE_CLIENT_SECRET');
