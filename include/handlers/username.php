<?php
/**
 * Username handle generation.
 *
 * Model: a single searchable handle stored in user.user_name, shaped like
 * "<firstNameSlug>#<number>" e.g. "Hung#2117" (Discord/Riot-style). The friendly
 * display name everywhere is first_name; user_name is just the unique handle
 * people search by on the Friends page.
 *
 * RMIT note: mbstring is unavailable, so diacritics are stripped with iconv
 * (verified to work on the server). "Hưng" → "Hung", "Nguyễn" → "Nguyen".
 */

/**
 * Turn a (possibly accented / spaced) name into an ASCII alphanumeric slug.
 * Returns '' if nothing usable remains (caller should fall back to a default).
 */
function slugify_name(string $name): string
{
    $name = trim($name);
    if ($name === '') return '';

    // Transliterate accented UTF-8 → ASCII. //IGNORE drops anything untranslatable
    // instead of failing. Suppress notices: iconv can warn on odd input.
    $ascii = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $name);
    if ($ascii === false) {
        $ascii = $name; // fall back to raw; the regex below still sanitises it
    }

    // Keep only letters and digits (drops spaces, apostrophes TRANSLIT may add, etc.)
    $slug = preg_replace('/[^A-Za-z0-9]/', '', $ascii);

    // Cap the base so base + number stays within user_name varchar(50).
    return substr($slug, 0, 20);
}

/**
 * Generate a unique handle: "<slug>#<random>". Falls back to base "user" when
 * the first name yields no usable slug (e.g. all non-Latin characters).
 *
 * Tries 4-digit numbers first; if those keep colliding it widens to 6 digits.
 * The DB UNIQUE key on user_name is the final guard against races (the caller's
 * INSERT will throw on the rare duplicate and can retry).
 */
function generate_handle(PDO $pdo, string $firstName): string
{
    $base = slugify_name($firstName);
    if ($base === '') {
        $base = 'user';
    }

    $check = $pdo->prepare("SELECT 1 FROM user WHERE user_name = ? LIMIT 1");

    // 4-digit attempts → "Hung#2117"
    for ($i = 0; $i < 25; $i++) {
        $candidate = $base . '#' . random_int(1000, 9999);
        $check->execute([$candidate]);
        if (!$check->fetchColumn()) {
            return $candidate;
        }
    }

    // Widen to 6 digits if the 4-digit space around this base is crowded.
    for ($i = 0; $i < 25; $i++) {
        $candidate = $base . '#' . random_int(100000, 999999);
        $check->execute([$candidate]);
        if (!$check->fetchColumn()) {
            return $candidate;
        }
    }

    // Extremely unlikely: timestamp-based fallback (still bounded to 50 chars).
    return substr($base, 0, 12) . '#' . random_int(1000, 9999) . substr((string) time(), -4);
}
