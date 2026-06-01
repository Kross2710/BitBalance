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

    /** Lazily load the species registry only when a DB helper needs to validate. */
    function mascot_state_require_species()
    {
        if (!function_exists('mascot_species_valid')) {
            require_once __DIR__ . '/mascot_species.php';
        }
    }

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
                    `active_species` VARCHAR(20) NOT NULL DEFAULT 'owl',
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

    /** Add active_species to a table that pre-dates P2 (idempotent, MariaDB). */
    function mascot_ensure_active_species_column(PDO $pdo)
    {
        if (!empty($_SESSION['mascot_active_species_col_ok'])) {
            return;
        }
        mascot_ensure_state_table($pdo);
        try {
            $pdo->exec("ALTER TABLE `mascot_state` ADD COLUMN IF NOT EXISTS `active_species` VARCHAR(20) NOT NULL DEFAULT 'owl'");
            $_SESSION['mascot_active_species_col_ok'] = true;
        } catch (PDOException $e) {
            // If the column already exists (fresh CREATE) the ALTER is a no-op;
            // if ADD COLUMN IF NOT EXISTS is unsupported, reads fall back to 'owl'.
        }
    }

    /** Create the per-species pet-names table lazily. */
    function mascot_ensure_pet_names_table(PDO $pdo)
    {
        if (!empty($_SESSION['mascot_pet_names_table_ok'])) {
            return;
        }
        try {
            $pdo->exec(
                "CREATE TABLE IF NOT EXISTS `mascot_pet_names` (
                    `user_id` INT(11) NOT NULL,
                    `species` VARCHAR(20) NOT NULL,
                    `name` VARCHAR(40) NOT NULL,
                    `updated_at` TIMESTAMP NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
                    PRIMARY KEY (`user_id`, `species`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
            );
            $_SESSION['mascot_pet_names_table_ok'] = true;
        } catch (PDOException $e) {
            // tolerate; callers handle absence
        }
    }

    /** The currently displayed species for a user; defaults to 'owl'. */
    function mascot_get_active_species(PDO $pdo, $userId)
    {
        $userId = (int) $userId;
        if ($userId <= 0) return 'owl';
        mascot_state_require_species();
        mascot_ensure_active_species_column($pdo);
        try {
            $stmt = $pdo->prepare("SELECT active_species FROM `mascot_state` WHERE user_id = ? LIMIT 1");
            $stmt->execute([$userId]);
            $sp = $stmt->fetchColumn();
            if ($sp !== false && $sp !== null && mascot_species_valid($sp)) {
                return (string) $sp;
            }
        } catch (PDOException $e) {
            // fall through
        }
        return 'owl';
    }

    /** Persist the active species (validated). Returns the stored id or ''. */
    function mascot_set_active_species(PDO $pdo, $userId, $species)
    {
        $userId = (int) $userId;
        mascot_state_require_species();
        if ($userId <= 0 || !mascot_species_valid($species)) return '';
        mascot_ensure_active_species_column($pdo);
        try {
            $stmt = $pdo->prepare(
                "INSERT INTO `mascot_state` (user_id, active_species) VALUES (?, ?)
                 ON DUPLICATE KEY UPDATE active_species = VALUES(active_species), updated_at = CURRENT_TIMESTAMP"
            );
            $stmt->execute([$userId, $species]);
            return $species;
        } catch (PDOException $e) {
            return '';
        }
    }

    /** Read the legacy single name (P1 stored it on mascot_state.name = the owl). */
    function mascot_get_legacy_name(PDO $pdo, $userId)
    {
        mascot_ensure_state_table($pdo);
        try {
            $stmt = $pdo->prepare("SELECT name FROM `mascot_state` WHERE user_id = ? LIMIT 1");
            $stmt->execute([(int) $userId]);
            $n = $stmt->fetchColumn();
            if ($n !== false && $n !== null) return (string) $n;
        } catch (PDOException $e) {
            // ignore
        }
        return '';
    }

    /** A species' pet name, or '' if unnamed. Migrates the P1 owl name forward. */
    function mascot_get_name(PDO $pdo, $userId, $species)
    {
        $userId = (int) $userId;
        if ($userId <= 0) return '';
        mascot_state_require_species();
        if (!mascot_species_valid($species)) $species = 'owl';
        mascot_ensure_pet_names_table($pdo);
        try {
            $stmt = $pdo->prepare("SELECT name FROM `mascot_pet_names` WHERE user_id = ? AND species = ? LIMIT 1");
            $stmt->execute([$userId, $species]);
            $name = $stmt->fetchColumn();
            if ($name !== false && $name !== null && $name !== '') {
                return (string) $name;
            }
        } catch (PDOException $e) {
            return '';
        }
        // Legacy migration-on-read: the P1 single name belonged to the owl.
        if ($species === 'owl') {
            $legacy = mascot_get_legacy_name($pdo, $userId);
            if ($legacy !== '') {
                mascot_set_name($pdo, $userId, 'owl', $legacy);
                return $legacy;
            }
        }
        return '';
    }

    /**
     * Sanitize + upsert a species' pet name. Returns the stored (sanitized)
     * name, or '' when the input was empty/invalid (nothing is written then).
     */
    function mascot_set_name(PDO $pdo, $userId, $species, $rawName)
    {
        $userId = (int) $userId;
        mascot_state_require_species();
        if (!mascot_species_valid($species)) $species = 'owl';
        $name = mascot_sanitize_name($rawName);
        if ($userId <= 0 || $name === '') return '';
        mascot_ensure_pet_names_table($pdo);
        try {
            $stmt = $pdo->prepare(
                "INSERT INTO `mascot_pet_names` (user_id, species, name) VALUES (?, ?, ?)
                 ON DUPLICATE KEY UPDATE name = VALUES(name), updated_at = CURRENT_TIMESTAMP"
            );
            $stmt->execute([$userId, $species, $name]);
            return $name;
        } catch (PDOException $e) {
            return '';
        }
    }

    /** Map of species id => stored name ('' if unnamed) for every species. */
    function mascot_get_all_names(PDO $pdo, $userId)
    {
        mascot_state_require_species();
        $out = array();
        foreach (mascot_species_ids() as $sp) {
            $out[$sp] = mascot_get_name($pdo, $userId, $sp);
        }
        return $out;
    }
}
