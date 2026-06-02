<?php
/**
 * BitBalance PHP 7.4 compatibility linter (token-based).
 *
 * Local XAMPP runs PHP 8.2, but production (RMIT) runs PHP 7.4.33 with a number
 * of extensions removed and functions disabled. Code that works locally can
 * fatal on RMIT ("works locally" trap). This linter parses each file with
 * token_get_all() — far fewer false positives than a plain grep — and flags:
 *
 *   - PHP 8.0+ syntax        : match(), enum, nullsafe ?->
 *   - PHP 8.0+ string helpers: str_contains / str_starts_with / str_ends_with
 *   - Missing extensions     : mb_* (mbstring), new ZipArchive (zip),
 *                              new NumberFormatter / IntlDateFormatter / Collator (intl)
 *   - Disabled functions     : shell_exec, exec, system, passthru, proc_*, popen,
 *                              pclose, set_time_limit, phpinfo, posix_*
 *
 * Calls guarded on the SAME line by function_exists() are skipped (the correct
 * mb_* polyfill pattern). This complements scripts/deploy.sh --check (which is a
 * grep gate at deploy time); this one is meant to run fast on staged files in a
 * git pre-commit hook.
 *
 * USAGE
 *   php scripts/php74-lint.php                 # scan every *.php in the repo
 *   php scripts/php74-lint.php a.php b.php     # scan specific files (used by the hook)
 *
 * EXIT CODE
 *   0 = clean, 1 = landmines found (so a pre-commit hook can block the commit).
 *
 * This file is intentionally written in PHP-7.4-safe style so it can run on the
 * server too, and so it never flags itself.
 */

// --- Blocklists -------------------------------------------------------------

/** function name => short fix hint */
function php74_blocked_functions()
{
    return array(
        'str_contains'     => 'use strpos($s, $p) !== false',
        'str_starts_with'  => 'use strpos($s, $p) === 0',
        'str_ends_with'    => 'use substr($s, -strlen($p)) === $p',
        'shell_exec'       => 'disabled on RMIT — no shell exec',
        'exec'             => 'disabled on RMIT — no process exec',
        'system'           => 'disabled on RMIT — no process exec',
        'passthru'         => 'disabled on RMIT — no process exec',
        'proc_open'        => 'disabled on RMIT — no process control',
        'proc_close'       => 'disabled on RMIT — no process control',
        'proc_terminate'   => 'disabled on RMIT — no process control',
        'popen'            => 'disabled on RMIT — no process exec',
        'pclose'           => 'disabled on RMIT — no process exec',
        'set_time_limit'   => 'disabled on RMIT — web cap is a hard 30s',
        'phpinfo'          => 'disabled on RMIT',
        'getmypid'         => 'disabled on RMIT',
        'getmyuid'         => 'disabled on RMIT',
    );
}

/** new <Class> constructors that need a missing extension. */
function php74_blocked_classes()
{
    return array(
        'ZipArchive'       => 'zip ext missing on RMIT — use Phar / zlib / bz2',
        'NumberFormatter'  => 'intl ext missing on RMIT — hand-roll number formatting',
        'IntlDateFormatter'=> 'intl ext missing on RMIT — hand-roll date formatting',
        'Collator'         => 'intl ext missing on RMIT — hand-roll collation',
    );
}

/**
 * Scan one PHP file. Returns a list of findings:
 *   ['line' => int, 'kind' => string, 'detail' => string]
 */
function php74_scan_file($path)
{
    $src = @file_get_contents($path);
    if ($src === false) {
        return array();
    }

    $lines = preg_split('/\r\n|\r|\n/', $src);

    // Tokens that only exist on PHP 8+ — resolve by name so this file stays 7.4-safe.
    $synTokens = array();
    foreach (array(
        'T_NULLSAFE_OBJECT_OPERATOR' => 'nullsafe operator ?->  (PHP 8.0+) — use ($x !== null ? $x->y : null)',
        'T_MATCH'                    => 'match expression (PHP 8.0+) — use switch',
        'T_ENUM'                     => 'enum (PHP 8.1+) — use class constants',
    ) as $name => $hint) {
        if (defined($name)) {
            $synTokens[constant($name)] = $hint;
        }
    }

    $blockedFns = php74_blocked_functions();
    $blockedCls = php74_blocked_classes();

    $tokens   = token_get_all($src);
    $findings = array();

    // Build a stream of "significant" tokens (skip whitespace) with their index
    // so we can look at the previous significant token cheaply.
    $n = count($tokens);
    $prevSig = null; // previous significant token (array or string)

    for ($i = 0; $i < $n; $i++) {
        $tok = $tokens[$i];

        if (is_array($tok)) {
            $id   = $tok[0];
            $text = $tok[1];
            $line = $tok[2];

            // Skip pure whitespace / comments for "previous significant" tracking.
            if ($id === T_WHITESPACE || $id === T_COMMENT || $id === T_DOC_COMMENT) {
                continue;
            }

            // PHP 8 syntax tokens.
            if (isset($synTokens[$id])) {
                $findings[] = array('line' => $line, 'kind' => 'syntax', 'detail' => $synTokens[$id]);
                $prevSig = $tok;
                continue;
            }

            // Constructor of a missing-extension class:  new ClassName
            if ($id === T_STRING && is_array($prevSig) && $prevSig[0] === T_NEW) {
                if (isset($blockedCls[$text])) {
                    $findings[] = array('line' => $line, 'kind' => 'extension', 'detail' => 'new ' . $text . ' — ' . $blockedCls[$text]);
                    $prevSig = $tok;
                    continue;
                }
            }

            // Function call: T_STRING immediately followed (skipping ws) by '('.
            if ($id === T_STRING) {
                // Not a method/static call and not a function/const definition.
                $prevBlocksCall = false;
                if (is_array($prevSig)) {
                    if ($prevSig[0] === T_OBJECT_OPERATOR
                        || $prevSig[0] === T_DOUBLE_COLON
                        || $prevSig[0] === T_FUNCTION
                        || $prevSig[0] === T_NEW
                        || $prevSig[0] === T_CONST
                        || (defined('T_NULLSAFE_OBJECT_OPERATOR') && $prevSig[0] === constant('T_NULLSAFE_OBJECT_OPERATOR'))) {
                        $prevBlocksCall = true;
                    }
                }

                if (!$prevBlocksCall && php74_next_is_paren($tokens, $i, $n)) {
                    $reason = null;
                    if (isset($blockedFns[$text])) {
                        $reason = $blockedFns[$text];
                    } elseif (strpos($text, 'mb_') === 0) {
                        $reason = 'mbstring missing on RMIT — use iconv_* / preg polyfill (guard with function_exists)';
                    } elseif (strpos($text, 'posix_') === 0) {
                        $reason = 'posix_* disabled on RMIT';
                    }

                    if ($reason !== null) {
                        // Skip if guarded by function_exists() on the same line.
                        $lineText = isset($lines[$line - 1]) ? $lines[$line - 1] : '';
                        if (strpos($lineText, 'function_exists') === false) {
                            $findings[] = array('line' => $line, 'kind' => 'compat', 'detail' => $text . '() — ' . $reason);
                        }
                    }
                }
            }

            $prevSig = $tok;
        } else {
            // Single-char token like '(' ')' ';' — significant.
            $prevSig = $tok;
        }
    }

    return $findings;
}

/** True if the next significant token after index $i is '('. */
function php74_next_is_paren($tokens, $i, $n)
{
    for ($j = $i + 1; $j < $n; $j++) {
        $t = $tokens[$j];
        if (is_array($t)) {
            if ($t[0] === T_WHITESPACE || $t[0] === T_COMMENT || $t[0] === T_DOC_COMMENT) {
                continue;
            }
            return false; // some other token before '('
        }
        return $t === '(';
    }
    return false;
}

/** Recursively collect *.php under $root, skipping non-source / runtime dirs. */
function php74_collect_php($root)
{
    $skip = array('.git', 'node_modules', 'vendor', 'uploads', 'screenshots', 'ios-swift', '.claude');
    $out  = array();
    $it = new RecursiveIteratorIterator(
        new RecursiveCallbackFilterIterator(
            new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS),
            function ($current) use ($skip) {
                $name = $current->getFilename();
                if ($current->isDir()) {
                    return !in_array($name, $skip, true);
                }
                return substr($name, -4) === '.php';
            }
        )
    );
    foreach ($it as $file) {
        if ($file->isFile()) {
            $out[] = $file->getPathname();
        }
    }
    sort($out);
    return $out;
}

/** Scan a list of files. Returns [path => findings[]] for files with findings. */
function php74_scan_files($paths)
{
    $report = array();
    foreach ($paths as $p) {
        if (!is_file($p) || substr($p, -4) !== '.php') {
            continue;
        }
        $f = php74_scan_file($p);
        if ($f) {
            $report[$p] = $f;
        }
    }
    return $report;
}

// --- CLI entry point (only when run directly, not when included) ------------

if (PHP_SAPI === 'cli' && isset($argv) && realpath($argv[0]) === realpath(__FILE__)) {
    $root  = dirname(__DIR__);
    $args  = array_slice($argv, 1);
    $files = $args ? $args : php74_collect_php($root);

    $report = php74_scan_files($files);

    $useColor = function_exists('posix_isatty') ? @posix_isatty(STDOUT) : (DIRECTORY_SEPARATOR === '/');
    $red   = $useColor ? "\033[0;31m" : '';
    $grn   = $useColor ? "\033[0;32m" : '';
    $yel   = $useColor ? "\033[1;33m" : '';
    $dim   = $useColor ? "\033[2m"    : '';
    $rst   = $useColor ? "\033[0m"    : '';

    if (!$report) {
        echo $grn . "PHP 7.4 lint: clean — no prod landmines in " . count($files) . " file(s).\n" . $rst;
        exit(0);
    }

    $total = 0;
    foreach ($report as $path => $findings) {
        $rel = str_replace($root . DIRECTORY_SEPARATOR, '', $path);
        echo $yel . $rel . $rst . "\n";
        foreach ($findings as $f) {
            $total++;
            echo "  " . $red . "line " . $f['line'] . $rst . " " . $dim . "[" . $f['kind'] . "]" . $rst . " " . $f['detail'] . "\n";
        }
    }
    echo "\n" . $red . "PHP 7.4 lint: " . $total . " landmine(s) in " . count($report) . " file(s) — would fatal on RMIT (7.4.33).\n" . $rst;
    echo $dim . "Fix before committing, or bypass once with: git commit --no-verify\n" . $rst;
    exit(1);
}
