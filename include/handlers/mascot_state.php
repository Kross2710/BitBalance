<?php
/**
 * include/handlers/mascot_state.php
 *
 * Persistent per-user state for the pet mascot (the Blue Owl).
 *
 * SCOPE SPLIT — important:
 *   • XP / level / progression already live in include/handlers/xp.php
 *     (the `user_xp` table). The mascot READS that; it never stores its own XP.
 *   • This module owns only the pet-specific attributes that have no home
 *     elsewhere: the user-chosen NAME (P1) and a slot for a future cosmetic
 *     skin (P3 — not used yet).
 *
 * The naming + life-stage MATH is dependency-free pure PHP (no DB, no cURL) so
 * it can be unit-tested from the CLI (see tests/mascot_state_test.php). The DB
 * helpers below are thin wrappers around that pure core.
 *
 * RMIT server rules honoured: PHP 7.4 syntax only (no match / str_contains /
 * ?-> / named args), and NO mbstring — UTF-8 length is counted via the
 * documented /./us regex approach, never mb_*.
 */

if (!function_exists('mascot_sanitize_name')) {

    /** Max visible characters for a pet name (UTF-8 aware). */
    function mascot_name_max_len()
    {
        return 20;
    }

    /**
     * Count UTF-8 characters in a string without mbstring.
     * Falls back to byte length on invalid byte sequences.
     */
    function mascot_utf8_len($s)
    {
        $s = (string) $s;
        if ($s === '') return 0;
        $n = preg_match_all('/./us', $s, $tmp);
        if ($n === false || $n === null) {
            return strlen($s); // last resort
        }
        return (int) $n;
    }

    /**
     * Return the first $len UTF-8 characters of a string without mbstring.
     */
    function mascot_utf8_head($s, $len)
    {
        $s = (string) $s;
        $len = (int) $len;
        if ($s === '' || $len <= 0) return '';
        if (preg_match_all('/./us', $s, $m) && isset($m[0])) {
            $chars = $m[0];
            if (count($chars) <= $len) return $s;
            return implode('', array_slice($chars, 0, $len));
        }
        return substr($s, 0, $len); // last resort
    }

    /**
     * Clean a raw, user-submitted pet name into something safe to store,
     * render, and drop into an AI prompt:
     *
     *   - collapses any whitespace run (incl. newlines/tabs) to a single space
     *   - strips ASCII control chars and the markup / prompt-injection-prone
     *     delimiters  < > { } `  (emoji & ordinary punctuation are kept — cute)
     *   - trims and caps to mascot_name_max_len() UTF-8 characters
     *
     * Returns '' when nothing usable remains; callers treat '' as "unnamed".
     */
    function mascot_sanitize_name($raw)
    {
        $s = (string) $raw;

        // Normalise every whitespace run (incl. \n, \t) to a single space.
        $collapsed = preg_replace('/\s+/u', ' ', $s);
        $s = ($collapsed === null) ? '' : $collapsed; // preg failure on bad bytes

        // Strip control chars + markup / prompt-prone delimiters.
        $clean = preg_replace('/[\x00-\x1F\x7F<>{}`]/u', '', $s);
        $s = ($clean === null) ? '' : $clean;

        $s = trim($s);
        if ($s === '') return '';

        return mascot_utf8_head($s, mascot_name_max_len());
    }

    /**
     * Map an XP level (from xp.php) to the mascot's life stage.
     *
     * Forward-looking for P2 evolution visuals; the dashboard already emits the
     * matching `stage-*` CSS hook today so P2 only has to add the artwork.
     *
     *   level 1      → egg
     *   level 2–3    → baby
     *   level 4–6    → adult
     *   level 7+     → sage
     */
    function mascot_stage_from_level($level)
    {
        $level = (int) $level;
        if ($level >= 7) return 'sage';
        if ($level >= 4) return 'adult';
        if ($level >= 2) return 'baby';
        return 'egg';
    }
}

// -----------------------------------------------------------------------------
// DB helpers (thin; failures are swallowed so the mascot never breaks a page)
// -----------------------------------------------------------------------------

if (!function_exists('mascot_ensure_state_table')) {

    /** Create the pet-state table lazily (once per session), mirroring the
     *  beats_mirror_cache pattern — RMIT has no migration runner. */
    function mascot_ensure_state_table(PDO $pdo)
    {
        if (!empty($_SESSION['mascot_state_table_ok'])) {
            return;
        }
        try {
            $pdo->exec(
                "CREATE TABLE IF NOT EXISTS `mascot_state` (
                    `user_id` INT(11) NOT NULL,
                    `name` VARCHAR(40) DEFAULT NULL,
                    `active_skin` VARCHAR(30) DEFAULT NULL,
                    `created_at` TIMESTAMP NOT NULL DEFAULT current_timestamp(),
                    `updated_at` TIMESTAMP NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
                    PRIMARY KEY (`user_id`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
            );
            $_SESSION['mascot_state_table_ok'] = true;
        } catch (PDOException $e) {
            // Leave the flag unset to retry next time; callers tolerate absence.
        }
    }

    /** The pet's name, or '' if unnamed / unavailable. */
    function mascot_get_name(PDO $pdo, $userId)
    {
        $userId = (int) $userId;
        if ($userId <= 0) return '';
        mascot_ensure_state_table($pdo);
        try {
            $stmt = $pdo->prepare("SELECT name FROM `mascot_state` WHERE user_id = ? LIMIT 1");
            $stmt->execute([$userId]);
            $name = $stmt->fetchColumn();
            if ($name === false || $name === null) return '';
            return (string) $name;
        } catch (PDOException $e) {
            return '';
        }
    }

    /**
     * Sanitize + upsert the pet's name. Returns the stored (sanitized) name,
     * or '' when the input was empty/invalid (nothing is written in that case).
     */
    function mascot_set_name(PDO $pdo, $userId, $rawName)
    {
        $userId = (int) $userId;
        $name = mascot_sanitize_name($rawName);
        if ($userId <= 0 || $name === '') return '';
        mascot_ensure_state_table($pdo);
        try {
            $stmt = $pdo->prepare(
                "INSERT INTO `mascot_state` (user_id, name) VALUES (?, ?)
                 ON DUPLICATE KEY UPDATE name = VALUES(name), updated_at = CURRENT_TIMESTAMP"
            );
            $stmt->execute([$userId, $name]);
            return $name;
        } catch (PDOException $e) {
            return '';
        }
    }
}
