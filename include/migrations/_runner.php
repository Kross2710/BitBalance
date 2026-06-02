<?php
/**
 * Shared migration helpers, used by both migrate.php (CLI + web dashboard) and
 * dev/seed.php (reset → re-migrate → seed). No side effects on include — just
 * function definitions, so it is safe to require from anywhere.
 */

if (!function_exists('split_sql_statements')) {
    /**
     * Split a SQL script into individual statements. Aware of string literals
     * ('...', "...", `...`) and -- / # / block comments so a ; inside a string
     * or comment does not split a statement.
     */
    function split_sql_statements(string $sql): array
    {
        $stmts = [];
        $buf   = '';
        $len   = strlen($sql);
        $i     = 0;
        $inSingle = $inDouble = $inBacktick = false;

        while ($i < $len) {
            $ch   = $sql[$i];
            $next = ($i + 1 < $len) ? $sql[$i + 1] : '';

            if ($inSingle || $inDouble || $inBacktick) {
                $buf .= $ch;
                // Backslash escape inside '...' / "..."
                if (($inSingle || $inDouble) && $ch === '\\' && $next !== '') {
                    $buf .= $next;
                    $i   += 2;
                    continue;
                }
                $isQuote = ($inSingle && $ch === "'") || ($inDouble && $ch === '"') || ($inBacktick && $ch === '`');
                if ($isQuote) {
                    if ($next === $ch) {        // doubled-quote escape ('' "" ``)
                        $buf .= $next;
                        $i   += 2;
                        continue;
                    }
                    $inSingle = $inDouble = $inBacktick = false;
                }
                $i++;
                continue;
            }

            // Line comment: -- followed by whitespace/EOL (MySQL rule), or #
            if ($ch === '-' && $next === '-') {
                $third = ($i + 2 < $len) ? $sql[$i + 2] : "\n";
                if ($i + 2 >= $len || $third === ' ' || $third === "\t" || $third === "\n" || $third === "\r") {
                    $j = $i + 2;
                    while ($j < $len && $sql[$j] !== "\n") {
                        $j++;
                    }
                    $i = $j;
                    continue;
                }
            }
            if ($ch === '#') {
                $j = $i + 1;
                while ($j < $len && $sql[$j] !== "\n") {
                    $j++;
                }
                $i = $j;
                continue;
            }
            // Block comment /* ... */
            if ($ch === '/' && $next === '*') {
                $j = $i + 2;
                while ($j + 1 < $len && !($sql[$j] === '*' && $sql[$j + 1] === '/')) {
                    $j++;
                }
                $i = $j + 2;
                continue;
            }

            if ($ch === "'") { $inSingle   = true; $buf .= $ch; $i++; continue; }
            if ($ch === '"') { $inDouble   = true; $buf .= $ch; $i++; continue; }
            if ($ch === '`') { $inBacktick = true; $buf .= $ch; $i++; continue; }

            if ($ch === ';') {
                $t = trim($buf);
                if ($t !== '') {
                    $stmts[] = $t;
                }
                $buf = '';
                $i++;
                continue;
            }

            $buf .= $ch;
            $i++;
        }

        $t = trim($buf);
        if ($t !== '') {
            $stmts[] = $t;
        }
        return $stmts;
    }
}

if (!function_exists('mig_ensure_table')) {
    /** Create the tracking table if it does not exist yet. */
    function mig_ensure_table(PDO $pdo): void
    {
        $pdo->exec(
            "CREATE TABLE IF NOT EXISTS schema_migrations (
                filename   VARCHAR(255) NOT NULL PRIMARY KEY,
                applied_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );
    }
}

if (!function_exists('mig_applied_map')) {
    /** Map of filename => applied_at for every recorded migration. */
    function mig_applied_map(PDO $pdo): array
    {
        $map  = [];
        $rows = $pdo->query("SELECT filename, applied_at FROM schema_migrations")->fetchAll(PDO::FETCH_ASSOC);
        foreach ($rows as $r) {
            $map[$r['filename']] = $r['applied_at'];
        }
        return $map;
    }
}

if (!function_exists('mig_discover')) {
    /** All migration filenames (basenames) sorted chronologically. */
    function mig_discover(): array
    {
        $files = glob(__DIR__ . '/*.sql');
        sort($files); // lexicographic == chronological thanks to the YYYY_MM_DD_ prefix
        return array_map('basename', $files);
    }
}

if (!function_exists('mig_record')) {
    /** Record a migration as applied without running it. */
    function mig_record(PDO $pdo, string $name): void
    {
        $stmt = $pdo->prepare("INSERT INTO schema_migrations (filename) VALUES (?)");
        $stmt->execute([$name]);
    }
}

if (!function_exists('mig_apply')) {
    /**
     * Apply a single migration file: run every statement, then record it.
     * Returns ['ok' => bool, 'count' => int, 'index' => ?int, 'error' => ?string].
     * On failure the file is NOT recorded (matching the original behaviour).
     */
    function mig_apply(PDO $pdo, string $name): array
    {
        $path = __DIR__ . '/' . $name;
        $sql  = is_file($path) ? file_get_contents($path) : false;
        if ($sql === false) {
            return ['ok' => false, 'count' => 0, 'index' => null, 'error' => 'could not read file'];
        }

        $statements = split_sql_statements($sql);
        foreach ($statements as $idx => $stmt) {
            try {
                $pdo->exec($stmt);
            } catch (PDOException $e) {
                return ['ok' => false, 'count' => count($statements), 'index' => $idx, 'error' => $e->getMessage()];
            }
        }
        mig_record($pdo, $name);
        return ['ok' => true, 'count' => count($statements), 'index' => null, 'error' => null];
    }
}

if (!function_exists('mig_plural')) {
    /** "1 statement" / "3 statements" */
    function mig_plural(int $n): string
    {
        return $n . ' statement' . ($n === 1 ? '' : 's');
    }
}
