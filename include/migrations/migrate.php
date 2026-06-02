<?php
/**
 * Aggregate migration runner (CLI + local web dashboard).
 *
 * Scans include/migrations/*.sql, sorts by filename (the YYYY_MM_DD_ prefix
 * gives chronological order) and applies every file that has not been applied
 * yet, recording each in a `schema_migrations` table so re-runs only execute
 * NEW migrations.
 *
 * USAGE
 *   CLI:
 *     php include/migrations/migrate.php                # apply all pending
 *     php include/migrations/migrate.php --dry-run      # show what WOULD run
 *     php include/migrations/migrate.php --baseline     # mark all pending as
 *                                                       # applied WITHOUT running
 *   Browser:
 *     Open .../include/migrations/migrate.php for a full HTML dashboard:
 *     list of migrations + their status, "apply all", "dry run", "baseline",
 *     per-file apply and inline SQL preview.
 *
 * ACCESS (browser)
 *   - On localhost the dashboard is open (convenient local dev — no login).
 *   - On any other host an admin session is required.
 *
 * TWO SCENARIOS
 *   - Fresh / empty database: apply all pending to build the whole schema.
 *   - Existing database already migrated by hand (e.g. current prod): first
 *     apply any genuinely-new migration, then "baseline" ONCE so the runner
 *     records all current files as applied; future deploys then only execute
 *     newly-added migrations.
 *
 * CAVEATS
 *   - MySQL auto-commits DDL (CREATE/ALTER), so a file that fails halfway is
 *     NOT rolled back. The file is only marked applied if ALL its statements
 *     succeed; on failure the runner stops so you can fix + clean up + re-run.
 *   - Splitter handles `;`, string literals and -- / # / block comments. It
 *     does NOT support DELIMITER blocks (none are used in this project).
 *   - DELETE this file from production once you are done, or keep it but rely
 *     on the admin guard.
 */

$isCli = (PHP_SAPI === 'cli');

// Shared migration helpers (split_sql_statements, mig_apply, mig_discover, …).
require_once __DIR__ . '/_runner.php';

/* =========================================================================
 * CLI MODE — plain text, identical behaviour to the original runner.
 * ===================================================================== */

if ($isCli) {
    $args     = array_slice($argv, 1);
    $dryRun   = in_array('--dry-run', $args, true);
    $baseline = in_array('--baseline', $args, true);

    require_once __DIR__ . '/../db_config.php'; // $pdo for the active environment

    mig_ensure_table($pdo);
    $applied = mig_applied_map($pdo);

    $pending = [];
    foreach (mig_discover() as $name) {
        if (!isset($applied[$name])) {
            $pending[] = $name;
        }
    }

    try {
        $dbName = $pdo->query('SELECT DATABASE()')->fetchColumn();
        $host   = $pdo->query('SELECT @@hostname')->fetchColumn();
    } catch (Throwable $e) {
        $dbName = '(unknown)';
        $host   = '(unknown)';
    }

    $mode = $dryRun ? 'DRY-RUN' : ($baseline ? 'BASELINE (mark only)' : 'APPLY');
    echo "BitBalance migration runner (aggregate)\n";
    echo "  database : {$dbName}\n";
    echo "  server   : {$host}\n";
    echo "  mode     : {$mode}\n";
    echo "  applied  : " . count($applied) . " already recorded\n";
    echo "  pending  : " . count($pending) . "\n";
    echo str_repeat('-', 56) . "\n";

    if (!$pending) {
        echo "Nothing to do. Database is up to date.\n";
        return;
    }

    if ($dryRun) {
        foreach ($pending as $name) {
            echo "  would apply  {$name}\n";
        }
        echo "\nDry run only - nothing was changed.\n";
        return;
    }

    $appliedN = 0;
    foreach ($pending as $name) {
        if ($baseline) {
            mig_record($pdo, $name);
            echo "  baseline   {$name}\n";
            $appliedN++;
            continue;
        }

        $res = mig_apply($pdo, $name);
        if ($res['ok']) {
            echo "  applied    {$name} (" . mig_plural($res['count']) . ")\n";
            $appliedN++;
        } elseif ($res['index'] === null) {
            echo "  FAILED     {$name}: {$res['error']}\n";
            echo "\nStopped. Fix and re-run.\n";
            exit(1);
        } else {
            echo "  FAILED     {$name} at statement #" . ($res['index'] + 1) . ": {$res['error']}\n";
            echo "\nStopped. This file was NOT recorded as applied. Note that MySQL\n";
            echo "auto-commits DDL, so earlier statements in this file may have run.\n";
            echo "Fix the migration (and clean up any partial change) then re-run.\n";
            exit(1);
        }
    }

    echo str_repeat('-', 56) . "\n";
    echo ($baseline ? "Baselined" : "Applied") . " {$appliedN} migration" . ($appliedN === 1 ? '' : 's') . ".\n";
    if (!$baseline) {
        echo "Remember to delete include/migrations/migrate.php from production when done.\n";
    }
    return;
}

/* =========================================================================
 * WEB MODE — local-friendly HTML dashboard.
 * ===================================================================== */

// Make db_config throw (instead of die-ing with plain text) so we can render a
// friendly connection error inside the dashboard.
if (!defined('BITBALANCE_API_REQUEST')) {
    define('BITBALANCE_API_REQUEST', true);
}

require_once __DIR__ . '/../init.php'; // session + $isLoggedIn + $user + BASE_URL

// --- Access control ---------------------------------------------------------
$remote  = $_SERVER['REMOTE_ADDR'] ?? '';
$hostHdr = $_SERVER['HTTP_HOST'] ?? ($_SERVER['SERVER_NAME'] ?? '');
$isLocal = in_array($remote, ['127.0.0.1', '::1', 'localhost'], true)
        || (bool) preg_match('/^(localhost|127\.0\.0\.1|\[::1\])(:\d+)?$/i', $hostHdr);
$isAdmin = !empty($isLoggedIn) && (($user['role'] ?? '') === 'admin');

/** Render a minimal standalone HTML page (used for forbidden / DB error). */
function mig_render_notice(string $title, string $body, int $code = 200): void
{
    http_response_code($code);
    header('Content-Type: text/html; charset=utf-8');
    $t = htmlspecialchars($title, ENT_QUOTES, 'UTF-8');
    echo "<!doctype html><html lang=\"en\"><head><meta charset=\"utf-8\">"
       . "<meta name=\"viewport\" content=\"width=device-width, initial-scale=1\">"
       . "<title>{$t} — BitBalance migrations</title>"
       . "<style>body{font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Arial,sans-serif;"
       . "background:#f8fafc;color:#1e2937;margin:0;display:flex;min-height:100vh;align-items:center;justify-content:center;padding:24px}"
       . ".box{background:#fff;border:1px solid #e2e8f0;border-radius:20px;box-shadow:0 10px 30px rgba(15,23,42,.08);"
       . "max-width:560px;padding:32px 36px}h1{margin:0 0 12px;font-size:1.25rem}p{color:#64748b;line-height:1.6;margin:8px 0}"
       . "code{background:#f1f5f9;padding:2px 6px;border-radius:6px}</style></head><body><div class=\"box\">"
       . "<h1>{$t}</h1>{$body}</div></body></html>";
}

if (!$isLocal && !$isAdmin) {
    mig_render_notice(
        'Forbidden',
        '<p>This migration dashboard is open on <code>localhost</code> only. '
        . 'On any other host you must sign in as an <strong>admin</strong> first, '
        . 'or run it from the command line:</p><p><code>php include/migrations/migrate.php</code></p>',
        403
    );
    return;
}

// --- DB connection ----------------------------------------------------------
try {
    require_once __DIR__ . '/../db_config.php'; // defines $pdo (+ $host/$dbname)
} catch (Throwable $e) {
    mig_render_notice(
        'Database connection failed',
        '<p>Could not connect to the database configured in '
        . '<code>include/db_config.php</code>.</p><p><code>'
        . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8')
        . '</code></p><p>Start MySQL in XAMPP and check the host / credentials.</p>'
    );
    return;
}

mig_ensure_table($pdo);

// --- CSRF token -------------------------------------------------------------
if (empty($_SESSION['mig_csrf'])) {
    $_SESSION['mig_csrf'] = bin2hex(random_bytes(16));
}
$csrf = $_SESSION['mig_csrf'];

// --- Handle POST actions ----------------------------------------------------
// $result: ['mode'=>string, 'ok'=>bool, 'entries'=>[['type'=>..,'file'=>..,'text'=>..]], 'note'=>string]
$result = null;

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    $action  = $_POST['action'] ?? '';
    $postCsrf = $_POST['csrf'] ?? '';

    if (!hash_equals($csrf, (string) $postCsrf)) {
        $result = [
            'mode'    => 'Error',
            'ok'      => false,
            'entries' => [['type' => 'failed', 'file' => '', 'text' => 'Invalid or expired session token. Please reload and try again.']],
            'note'    => '',
        ];
    } else {
        $appliedNow = mig_applied_map($pdo);
        $allFiles   = mig_discover();
        $pendingNow = array_values(array_filter($allFiles, fn ($n) => !isset($appliedNow[$n])));

        // Resolve a single-file target (apply_one / baseline_one) safely.
        $target = isset($_POST['file']) ? basename((string) $_POST['file']) : '';
        $targetValid = $target !== '' && in_array($target, $allFiles, true) && !isset($appliedNow[$target]);

        $entries = [];
        $ok      = true;
        $note    = '';
        $mode    = '';

        switch ($action) {
            case 'dry_run':
                $mode = 'Dry run';
                if (!$pendingNow) {
                    $entries[] = ['type' => 'info', 'file' => '', 'text' => 'Nothing pending. Database is up to date.'];
                } else {
                    foreach ($pendingNow as $name) {
                        $cnt = count(split_sql_statements((string) file_get_contents(__DIR__ . '/' . $name)));
                        $entries[] = ['type' => 'would', 'file' => $name, 'text' => 'would apply (' . mig_plural($cnt) . ')'];
                    }
                    $note = 'Dry run only — nothing was changed.';
                }
                break;

            case 'baseline_all':
                $mode = 'Baseline all pending';
                if (!$pendingNow) {
                    $entries[] = ['type' => 'info', 'file' => '', 'text' => 'Nothing pending to baseline.'];
                } else {
                    foreach ($pendingNow as $name) {
                        mig_record($pdo, $name);
                        $entries[] = ['type' => 'baseline', 'file' => $name, 'text' => 'marked applied (not run)'];
                    }
                    $note = 'Baselined ' . count($pendingNow) . ' migration' . (count($pendingNow) === 1 ? '' : 's') . '.';
                }
                break;

            case 'baseline_one':
                $mode = 'Baseline one';
                if (!$targetValid) {
                    $ok = false;
                    $entries[] = ['type' => 'failed', 'file' => $target, 'text' => 'Unknown or already-applied file.'];
                } else {
                    mig_record($pdo, $target);
                    $entries[] = ['type' => 'baseline', 'file' => $target, 'text' => 'marked applied (not run)'];
                }
                break;

            case 'apply_one':
                $mode = 'Apply one';
                if (!$targetValid) {
                    $ok = false;
                    $entries[] = ['type' => 'failed', 'file' => $target, 'text' => 'Unknown or already-applied file.'];
                } else {
                    $res = mig_apply($pdo, $target);
                    if ($res['ok']) {
                        $entries[] = ['type' => 'applied', 'file' => $target, 'text' => 'applied (' . mig_plural($res['count']) . ')'];
                    } else {
                        $ok = false;
                        $where = $res['index'] === null ? '' : ' at statement #' . ($res['index'] + 1);
                        $entries[] = ['type' => 'failed', 'file' => $target, 'text' => 'FAILED' . $where . ': ' . $res['error']];
                        $note = 'MySQL auto-commits DDL, so earlier statements in this file may have run. Fix it, clean up any partial change, then re-run.';
                    }
                }
                break;

            case 'apply_all':
            default:
                $mode = 'Apply all pending';
                if (!$pendingNow) {
                    $entries[] = ['type' => 'info', 'file' => '', 'text' => 'Nothing to do. Database is up to date.'];
                } else {
                    foreach ($pendingNow as $name) {
                        $res = mig_apply($pdo, $name);
                        if ($res['ok']) {
                            $entries[] = ['type' => 'applied', 'file' => $name, 'text' => 'applied (' . mig_plural($res['count']) . ')'];
                        } else {
                            $ok = false;
                            $where = $res['index'] === null ? '' : ' at statement #' . ($res['index'] + 1);
                            $entries[] = ['type' => 'failed', 'file' => $name, 'text' => 'FAILED' . $where . ': ' . $res['error']];
                            $note = 'Stopped — this file was NOT recorded as applied. MySQL auto-commits DDL, so earlier statements may have run. Fix it, clean up, then re-run.';
                            break; // stop at first failure (matches CLI)
                        }
                    }
                    if ($ok) {
                        $applN = count($entries);
                        $note  = 'Applied ' . $applN . ' migration' . ($applN === 1 ? '' : 's') . '.';
                    }
                }
                break;
        }

        $result = ['mode' => $mode, 'ok' => $ok, 'entries' => $entries, 'note' => $note];
    }
}

// --- Gather current state (after any POST so the table reflects it) ---------
$appliedMap = mig_applied_map($pdo);
$allFiles   = mig_discover();

$rows         = [];
$appliedCount = 0;
$pendingCount = 0;
foreach ($allFiles as $name) {
    $path     = __DIR__ . '/' . $name;
    $isApplied = isset($appliedMap[$name]);
    $sql      = (string) @file_get_contents($path);
    $rows[]   = [
        'name'       => $name,
        'applied'    => $isApplied,
        'applied_at' => $isApplied ? $appliedMap[$name] : null,
        'size'       => @filesize($path) ?: 0,
        'stmts'      => count(split_sql_statements($sql)),
        'sql'        => $sql,
    ];
    if ($isApplied) {
        $appliedCount++;
    } else {
        $pendingCount++;
    }
}

// --- DB info banner ---------------------------------------------------------
try {
    $dbName    = (string) $pdo->query('SELECT DATABASE()')->fetchColumn();
    $dbHost    = (string) $pdo->query('SELECT @@hostname')->fetchColumn();
    $dbVersion = (string) $pdo->query('SELECT VERSION()')->fetchColumn();
} catch (Throwable $e) {
    $dbName = $dbHost = $dbVersion = '(unknown)';
}
// Heuristic: are we pointed at the RMIT production DB rather than local XAMPP?
$isProdDb = (stripos($dbHost, 'rmit') !== false) || (stripos($dbName, 'COSC') === 0);

// Form posts back to this script without any query string.
$selfUrl = htmlspecialchars(strtok((string) ($_SERVER['REQUEST_URI'] ?? 'migrate.php'), '?'), ENT_QUOTES, 'UTF-8');

/** Human-readable file size. */
function mig_bytes(int $b): string
{
    if ($b >= 1024) {
        return number_format($b / 1024, 1) . ' KB';
    }
    return $b . ' B';
}

$h = fn ($s) => htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8');

header('Content-Type: text/html; charset=utf-8');
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Migrations — BitBalance</title>
    <style>
        :root {
            --bg: #f8fafc; --surface: #ffffff; --alt: #f1f5f9; --text: #1e2937;
            --muted: #64748b; --faint: #94a3b8; --border: #e2e8f0;
            --primary: #58CC02; --primary-hover: #4CAF00; --blue: #1CB0F6;
            --orange: #FF9600; --danger: #ef4444; --danger-bg: #fee2e2;
            --success-bg: #e8f5e9; --warn: #f59e0b; --warn-bg: #fef3c7;
            --shadow: 0 10px 30px rgba(15,23,42,.08); --shadow-sm: 0 2px 8px rgba(0,0,0,.06);
            --radius: 20px; --radius-sm: 12px;
        }
        @media (prefers-color-scheme: dark) {
            :root {
                --bg: #0f172a; --surface: #1e2937; --alt: #334155; --text: #f1f5f9;
                --muted: #94a3b8; --faint: #64748b; --border: #475569;
                --danger-bg: #7f1d1d; --success-bg: #14532d; --warn-bg: #78350f;
                --shadow: 0 10px 40px rgba(0,0,0,.5);
            }
        }
        * { box-sizing: border-box; }
        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
            background: var(--bg); color: var(--text); margin: 0; line-height: 1.6;
            padding: 32px 20px 80px;
        }
        .wrap { max-width: 960px; margin: 0 auto; }
        h1 { font-size: 1.6rem; margin: 0 0 4px; }
        .sub { color: var(--muted); margin: 0 0 24px; font-size: .95rem; }
        .card {
            background: var(--surface); border: 1px solid var(--border);
            border-radius: var(--radius); box-shadow: var(--shadow);
            padding: 20px 24px; margin-bottom: 20px;
        }
        .info-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 14px; }
        .info-grid .k { font-size: .72rem; text-transform: uppercase; letter-spacing: .04em; color: var(--faint); }
        .info-grid .v { font-weight: 600; word-break: break-word; }
        .stats { display: flex; gap: 28px; flex-wrap: wrap; align-items: baseline; }
        .stat .n { font-size: 1.9rem; font-weight: 800; line-height: 1; }
        .stat .l { color: var(--muted); font-size: .85rem; }
        .stat.pending .n { color: var(--orange); }
        .stat.applied .n { color: var(--primary); }
        .banner {
            border-radius: var(--radius-sm); padding: 12px 16px; margin-bottom: 20px;
            font-size: .9rem; display: flex; gap: 10px; align-items: flex-start;
        }
        .banner.local { background: var(--success-bg); border: 1px solid var(--primary); }
        .banner.prod { background: var(--danger-bg); border: 1px solid var(--danger); }
        .banner strong { font-weight: 700; }
        .actions { display: flex; gap: 10px; flex-wrap: wrap; }
        button {
            font: inherit; font-weight: 700; cursor: pointer; border: none;
            border-radius: var(--radius-sm); padding: 11px 18px; color: #fff;
            transition: transform .1s ease, filter .15s ease;
        }
        button:hover { filter: brightness(1.05); }
        button:active { transform: translateY(1px); }
        button:disabled { opacity: .45; cursor: not-allowed; filter: none; }
        .btn-primary { background: var(--primary); box-shadow: 0 4px 0 var(--primary-hover); }
        .btn-blue { background: var(--blue); }
        .btn-ghost { background: var(--alt); color: var(--text); border: 1px solid var(--border); }
        .btn-warn { background: var(--warn); }
        .btn-sm { padding: 6px 12px; font-size: .8rem; border-radius: 8px; }
        form.inline { display: inline; }
        table { width: 100%; border-collapse: collapse; }
        th, td { text-align: left; padding: 11px 10px; border-bottom: 1px solid var(--border); vertical-align: middle; }
        th { font-size: .72rem; text-transform: uppercase; letter-spacing: .04em; color: var(--faint); }
        td.file { font-family: ui-monospace, SFMono-Regular, Menlo, monospace; font-size: .85rem; word-break: break-all; }
        tr.is-pending td.file { font-weight: 700; }
        .pill { display: inline-flex; align-items: center; gap: 6px; font-size: .78rem; font-weight: 700; padding: 3px 10px; border-radius: 999px; white-space: nowrap; }
        .pill.applied { background: var(--success-bg); color: var(--primary-hover); }
        .pill.pending { background: var(--warn-bg); color: #b45309; }
        .dot { width: 8px; height: 8px; border-radius: 50%; display: inline-block; }
        .pill.applied .dot { background: var(--primary); }
        .pill.pending .dot { background: var(--orange); }
        .meta { color: var(--muted); font-size: .82rem; white-space: nowrap; }
        details.sql { margin-top: 6px; }
        details.sql summary { cursor: pointer; color: var(--blue); font-size: .8rem; font-weight: 600; }
        details.sql pre {
            background: var(--alt); border: 1px solid var(--border); border-radius: 8px;
            padding: 12px; overflow-x: auto; font-size: .8rem; margin: 8px 0 0;
            font-family: ui-monospace, SFMono-Regular, Menlo, monospace;
        }
        .result { border-radius: var(--radius-sm); padding: 4px 0; }
        .result.ok { border-left: 4px solid var(--primary); }
        .result.err { border-left: 4px solid var(--danger); }
        .log { font-family: ui-monospace, SFMono-Regular, Menlo, monospace; font-size: .85rem; margin: 0; }
        .log li { list-style: none; padding: 4px 0; }
        .log .tag { display: inline-block; min-width: 86px; font-weight: 700; }
        .log .applied .tag, .log .baseline .tag { color: var(--primary-hover); }
        .log .would .tag, .log .info .tag { color: var(--blue); }
        .log .failed .tag { color: var(--danger); }
        .note { color: var(--muted); margin: 10px 0 0; font-size: .88rem; }
        .empty { color: var(--muted); text-align: center; padding: 28px; }
        .footnote { color: var(--faint); font-size: .8rem; margin-top: 24px; text-align: center; }
        .footnote code { background: var(--alt); padding: 2px 6px; border-radius: 6px; }
    </style>
</head>
<body>
<div class="wrap">
    <h1>Database migrations</h1>
    <p class="sub">BitBalance migration runner — apply, preview and baseline schema changes.</p>

    <?php if ($isProdDb): ?>
        <div class="banner prod">
            <span class="dot" style="background:#ef4444;margin-top:7px"></span>
            <div><strong>Heads up — this looks like the production database</strong>
            (<?= $h($dbName) ?> on <?= $h($dbHost) ?>). Double-check before applying anything.</div>
        </div>
    <?php elseif ($isLocal): ?>
        <div class="banner local">
            <span class="dot" style="background:#58CC02;margin-top:7px"></span>
            <div><strong>Local environment</strong> — open access on localhost. Safe to experiment.</div>
        </div>
    <?php endif; ?>

    <?php if ($result !== null): ?>
        <div class="card result <?= $result['ok'] ? 'ok' : 'err' ?>">
            <h3 style="margin:0 0 10px"><?= $h($result['mode']) ?> — <?= $result['ok'] ? 'done' : 'failed' ?></h3>
            <ul class="log">
                <?php foreach ($result['entries'] as $e): ?>
                    <li class="<?= $h($e['type']) ?>">
                        <span class="tag"><?= $h($e['type']) ?></span>
                        <?php if ($e['file'] !== ''): ?><strong><?= $h($e['file']) ?></strong> <?php endif; ?>
                        <?= $h($e['text']) ?>
                    </li>
                <?php endforeach; ?>
            </ul>
            <?php if ($result['note'] !== ''): ?><p class="note"><?= $h($result['note']) ?></p><?php endif; ?>
        </div>
    <?php endif; ?>

    <div class="card">
        <div class="info-grid">
            <div><div class="k">Database</div><div class="v"><?= $h($dbName) ?></div></div>
            <div><div class="k">Server</div><div class="v"><?= $h($dbHost) ?></div></div>
            <div><div class="k">MySQL</div><div class="v"><?= $h($dbVersion) ?></div></div>
        </div>
        <hr style="border:none;border-top:1px solid var(--border);margin:18px 0">
        <div class="stats">
            <div class="stat"><div class="n"><?= count($rows) ?></div><div class="l">total</div></div>
            <div class="stat applied"><div class="n"><?= $appliedCount ?></div><div class="l">applied</div></div>
            <div class="stat pending"><div class="n"><?= $pendingCount ?></div><div class="l">pending</div></div>
        </div>
    </div>

    <div class="card">
        <div class="actions">
            <form class="inline" method="post" action="<?= $selfUrl ?>"
                  onsubmit="return confirm('Apply all <?= $pendingCount ?> pending migration(s) to <?= $h($dbName) ?>?');">
                <input type="hidden" name="csrf" value="<?= $h($csrf) ?>">
                <input type="hidden" name="action" value="apply_all">
                <button class="btn-primary" type="submit" <?= $pendingCount === 0 ? 'disabled' : '' ?>>
                    Apply all pending<?= $pendingCount ? ' (' . $pendingCount . ')' : '' ?>
                </button>
            </form>
            <form class="inline" method="post" action="<?= $selfUrl ?>">
                <input type="hidden" name="csrf" value="<?= $h($csrf) ?>">
                <input type="hidden" name="action" value="dry_run">
                <button class="btn-blue" type="submit" <?= $pendingCount === 0 ? 'disabled' : '' ?>>Dry run</button>
            </form>
            <form class="inline" method="post" action="<?= $selfUrl ?>"
                  onsubmit="return confirm('Mark all pending migrations as applied WITHOUT running them?\n\nUse this only when the schema already matches these files.');">
                <input type="hidden" name="csrf" value="<?= $h($csrf) ?>">
                <input type="hidden" name="action" value="baseline_all">
                <button class="btn-warn" type="submit" <?= $pendingCount === 0 ? 'disabled' : '' ?>>Baseline all</button>
            </form>
            <a href="<?= $selfUrl ?>"><button type="button" class="btn-ghost">Refresh</button></a>
        </div>
    </div>

    <div class="card" style="padding:0;overflow:hidden">
        <?php if (!$rows): ?>
            <p class="empty">No <code>.sql</code> migration files found in this folder.</p>
        <?php else: ?>
        <table>
            <thead>
                <tr><th>Status</th><th>Migration</th><th>Details</th><th style="text-align:right">Actions</th></tr>
            </thead>
            <tbody>
                <?php foreach ($rows as $r): ?>
                    <tr class="<?= $r['applied'] ? 'is-applied' : 'is-pending' ?>">
                        <td>
                            <?php if ($r['applied']): ?>
                                <span class="pill applied"><span class="dot"></span>Applied</span>
                            <?php else: ?>
                                <span class="pill pending"><span class="dot"></span>Pending</span>
                            <?php endif; ?>
                        </td>
                        <td class="file">
                            <?= $h($r['name']) ?>
                            <details class="sql">
                                <summary>View SQL</summary>
                                <pre><?= $h($r['sql']) ?></pre>
                            </details>
                        </td>
                        <td class="meta">
                            <?= mig_plural($r['stmts']) ?> · <?= mig_bytes($r['size']) ?>
                            <?php if ($r['applied'] && $r['applied_at']): ?>
                                <br><?= $h($r['applied_at']) ?>
                            <?php endif; ?>
                        </td>
                        <td style="text-align:right">
                            <?php if (!$r['applied']): ?>
                                <form class="inline" method="post" action="<?= $selfUrl ?>"
                                      onsubmit="return confirm('Apply <?= $h($r['name']) ?>?');">
                                    <input type="hidden" name="csrf" value="<?= $h($csrf) ?>">
                                    <input type="hidden" name="action" value="apply_one">
                                    <input type="hidden" name="file" value="<?= $h($r['name']) ?>">
                                    <button class="btn-primary btn-sm" type="submit">Apply</button>
                                </form>
                                <form class="inline" method="post" action="<?= $selfUrl ?>"
                                      onsubmit="return confirm('Mark <?= $h($r['name']) ?> as applied WITHOUT running it?');">
                                    <input type="hidden" name="csrf" value="<?= $h($csrf) ?>">
                                    <input type="hidden" name="action" value="baseline_one">
                                    <input type="hidden" name="file" value="<?= $h($r['name']) ?>">
                                    <button class="btn-ghost btn-sm" type="submit">Baseline</button>
                                </form>
                            <?php else: ?>
                                <span class="meta">—</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php endif; ?>
    </div>

    <p class="footnote">
        Tracking table: <code>schema_migrations</code>.
        CLI equivalent: <code>php include/migrations/migrate.php [--dry-run|--baseline]</code>.
        Remember to remove this file from production when done.
    </p>
</div>
</body>
</html>
