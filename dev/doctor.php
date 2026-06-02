<?php
/**
 * BitBalance "Prod Parity Doctor" — local dev dashboard.
 *
 * One screen that compares your local XAMPP (PHP 8.2) against the RMIT
 * production runtime (PHP 7.4.33, no mbstring/intl/zip, functions disabled,
 * 30s/128M/5M caps, latin1-default DB) and tells you "what will break on
 * deploy". It checks:
 *
 *   - PHP version + runtime caps vs RMIT
 *   - Extensions present locally that are MISSING on RMIT (danger if used)
 *   - PHP 7.4 landmines in the code (reuses scripts/php74-lint.php)
 *   - secrets.php completeness (against include/secrets.example.php)
 *   - DB connection + tables still on latin1 (Vietnamese-text corruption risk)
 *   - Pending migrations
 *   - Timezone sanity (PHP vs DB)
 *
 * ACCESS: open on localhost (no login). On any other host an admin session is
 * required. This is a dev tool — delete or guard it on production.
 *
 *   Open: .../dev/doctor.php
 */

if (!defined('BITBALANCE_API_REQUEST')) {
    define('BITBALANCE_API_REQUEST', true); // make db_config throw, not die()
}

require_once __DIR__ . '/../include/init.php';            // session + $isLoggedIn + $user
require_once __DIR__ . '/../scripts/php74-lint.php';      // php74_collect_php / php74_scan_files

// --- Access control (same policy as the migration dashboard) ----------------
$remote  = $_SERVER['REMOTE_ADDR'] ?? '';
$hostHdr = $_SERVER['HTTP_HOST'] ?? ($_SERVER['SERVER_NAME'] ?? '');
$isLocal = in_array($remote, ['127.0.0.1', '::1', 'localhost'], true)
        || (bool) preg_match('/^(localhost|127\.0\.0\.1|\[::1\])(:\d+)?$/i', $hostHdr);
$isAdmin = !empty($isLoggedIn) && (($user['role'] ?? '') === 'admin');

if (!$isLocal && !$isAdmin) {
    http_response_code(403);
    header('Content-Type: text/plain; charset=utf-8');
    echo "Forbidden. The Doctor is open on localhost only; elsewhere sign in as admin.\n";
    return;
}

$ROOT = dirname(__DIR__);
$h    = fn ($s) => htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8');

/* =========================================================================
 * GATHER DATA
 * ===================================================================== */

$warnings = 0; // running count of things that need attention

// --- 1. Runtime -------------------------------------------------------------
$phpVersion = PHP_VERSION;
$phpMajor   = PHP_MAJOR_VERSION;
$phpIs8     = $phpMajor >= 8;
if ($phpIs8) {
    $warnings++; // not a blocker, but worth showing — prod is 7.4
}

$caps = [
    ['memory_limit',        ini_get('memory_limit'),        '128M'],
    ['max_execution_time',  ini_get('max_execution_time'),  '30 (web)'],
    ['upload_max_filesize', ini_get('upload_max_filesize'), '5M'],
    ['post_max_size',       ini_get('post_max_size'),       '10M'],
    ['date.timezone',       ini_get('date.timezone') ?: '(unset)', 'Australia/Melbourne'],
];

// --- 2. Extensions vs RMIT --------------------------------------------------
$rmitMissing = ['mbstring', 'intl', 'zip', 'bcmath', 'gmp', 'ldap', 'imap', 'soap'];
$extDanger   = [];
foreach ($rmitMissing as $ext) {
    $loaded = extension_loaded($ext);
    if ($loaded) {
        $warnings++;
    }
    $extDanger[$ext] = $loaded; // loaded locally == danger (absent on RMIT)
}
$rmitPresent = ['pdo_mysql', 'curl', 'gd', 'iconv', 'exif', 'fileinfo', 'openssl', 'sodium', 'json', 'gettext'];
$extPresent  = [];
foreach ($rmitPresent as $ext) {
    $extPresent[$ext] = extension_loaded($ext);
}

// --- 3. PHP 7.4 landmine scan (reuse the linter) ----------------------------
$scanFiles  = php74_collect_php($ROOT);
$scanReport = php74_scan_files($scanFiles);
$landmineTotal = 0;
foreach ($scanReport as $f) {
    $landmineTotal += count($f);
}
if ($landmineTotal > 0) {
    $warnings++;
}

// --- 4. secrets.php completeness --------------------------------------------
$secretsExample = $ROOT . '/include/secrets.example.php';
$secretsReal    = $ROOT . '/include/secrets.php';
$optionalKeys   = ['SPOTIFY_CLIENT_ID', 'SPOTIFY_CLIENT_SECRET', 'LASTFM_API_KEY', 'GOOGLE_CLIENT_ID', 'GOOGLE_CLIENT_SECRET'];

$requiredKeys = [];
if (is_file($secretsExample)) {
    if (preg_match_all('/define\(\s*[\'"]([A-Z0-9_]+)[\'"]/', (string) file_get_contents($secretsExample), $m)) {
        $requiredKeys = $m[1];
    }
}
$secretsLoaded = is_file($secretsReal);
if ($secretsLoaded) {
    require_once $secretsReal; // defines the real constants (gitignored file)
}

$secretRows = [];
foreach ($requiredKeys as $key) {
    $optional = in_array($key, $optionalKeys, true);
    if (!defined($key)) {
        $status = $secretsLoaded ? 'missing' : 'no-file';
    } else {
        $val = constant($key);
        if (is_string($val) && strpos($val, 'YOUR_') === 0) {
            $status = 'placeholder';
        } elseif ($val === '' || $val === null) {
            $status = 'empty';
        } else {
            $status = 'ok';
        }
    }
    if ($status !== 'ok' && !$optional) {
        $warnings++;
    }
    $secretRows[] = ['key' => $key, 'status' => $status, 'optional' => $optional];
}

// --- 5 & 6. Database: connection, charset audit, migrations -----------------
$dbError    = null;
$dbName = $dbHost = $dbVersion = '(n/a)';
$latinTables = [];
$migApplied  = 0;
$migPending  = [];
$dbTz = $dbNow = '(n/a)';
try {
    require_once $ROOT . '/include/db_config.php'; // $pdo
    $dbName    = (string) $pdo->query('SELECT DATABASE()')->fetchColumn();
    $dbHost    = (string) $pdo->query('SELECT @@hostname')->fetchColumn();
    $dbVersion = (string) $pdo->query('SELECT VERSION()')->fetchColumn();
    $dbTz      = (string) $pdo->query('SELECT @@session.time_zone')->fetchColumn();
    $dbNow     = (string) $pdo->query('SELECT NOW()')->fetchColumn();

    // Tables whose collation is not utf8mb4 (latin1 etc.) — corruption risk.
    $stmt = $pdo->query(
        "SELECT TABLE_NAME, TABLE_COLLATION
           FROM information_schema.TABLES
          WHERE TABLE_SCHEMA = DATABASE()
            AND TABLE_TYPE = 'BASE TABLE'
            AND (TABLE_COLLATION IS NULL OR TABLE_COLLATION NOT LIKE 'utf8mb4%')
          ORDER BY TABLE_NAME"
    );
    $latinTables = $stmt->fetchAll(PDO::FETCH_ASSOC);
    if ($latinTables) {
        $warnings++;
    }

    // Migrations: applied (if tracking table exists) vs files on disk.
    $hasTracking = (bool) $pdo->query("SHOW TABLES LIKE 'schema_migrations'")->fetchColumn();
    $appliedSet  = [];
    if ($hasTracking) {
        $appliedSet = array_flip($pdo->query("SELECT filename FROM schema_migrations")->fetchAll(PDO::FETCH_COLUMN));
        $migApplied = count($appliedSet);
    }
    foreach (glob($ROOT . '/include/migrations/*.sql') as $f) {
        $base = basename($f);
        if (!isset($appliedSet[$base])) {
            $migPending[] = $base;
        }
    }
    sort($migPending);
    if ($migPending) {
        $warnings++;
    }
} catch (Throwable $e) {
    $dbError = $e->getMessage();
    $warnings++;
}

// --- 7. Timezone ------------------------------------------------------------
$phpTz  = date_default_timezone_get();
$phpNow = date('Y-m-d H:i:s');

/* =========================================================================
 * RENDER
 * ===================================================================== */

/** status badge: kind in {ok, warn, danger, info} */
function doc_badge(string $text, string $kind): string
{
    $t = htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
    return "<span class=\"badge {$kind}\"><span class=\"dot\"></span>{$t}</span>";
}

header('Content-Type: text/html; charset=utf-8');
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Prod Parity Doctor — BitBalance</title>
    <style>
        :root {
            --bg:#f8fafc; --surface:#fff; --alt:#f1f5f9; --text:#1e2937; --muted:#64748b;
            --faint:#94a3b8; --border:#e2e8f0; --primary:#58CC02; --primary-h:#4CAF00;
            --blue:#1CB0F6; --orange:#FF9600; --danger:#ef4444; --danger-bg:#fee2e2;
            --ok-bg:#e8f5e9; --warn:#f59e0b; --warn-bg:#fef3c7; --info-bg:#dbeafe;
            --shadow:0 10px 30px rgba(15,23,42,.08); --radius:20px; --radius-sm:12px;
        }
        @media (prefers-color-scheme: dark){:root{
            --bg:#0f172a; --surface:#1e2937; --alt:#334155; --text:#f1f5f9; --muted:#94a3b8;
            --faint:#64748b; --border:#475569; --danger-bg:#7f1d1d; --ok-bg:#14532d;
            --warn-bg:#78350f; --info-bg:#1e3a8a; --shadow:0 10px 40px rgba(0,0,0,.5);
        }}
        *{box-sizing:border-box}
        body{font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,Arial,sans-serif;
            background:var(--bg);color:var(--text);margin:0;line-height:1.6;padding:32px 20px 80px}
        .wrap{max-width:980px;margin:0 auto}
        h1{font-size:1.6rem;margin:0 0 4px}
        .sub{color:var(--muted);margin:0 0 22px;font-size:.95rem}
        .card{background:var(--surface);border:1px solid var(--border);border-radius:var(--radius);
            box-shadow:var(--shadow);padding:20px 24px;margin-bottom:18px}
        .card h2{font-size:1.05rem;margin:0 0 14px;display:flex;align-items:center;gap:8px}
        .summary{display:flex;gap:12px;align-items:center;font-size:1.05rem;font-weight:700}
        .summary.clean{color:var(--primary-h)}
        .summary.warn{color:var(--orange)}
        table{width:100%;border-collapse:collapse}
        th,td{text-align:left;padding:9px 10px;border-bottom:1px solid var(--border);vertical-align:top;font-size:.9rem}
        th{font-size:.7rem;text-transform:uppercase;letter-spacing:.04em;color:var(--faint)}
        td.k{font-family:ui-monospace,SFMono-Regular,Menlo,monospace;font-size:.84rem;font-weight:600}
        .mono{font-family:ui-monospace,SFMono-Regular,Menlo,monospace;font-size:.84rem}
        .muted{color:var(--muted)}
        .badge{display:inline-flex;align-items:center;gap:6px;font-size:.76rem;font-weight:700;
            padding:3px 10px;border-radius:999px;white-space:nowrap}
        .badge .dot{width:8px;height:8px;border-radius:50%;display:inline-block;background:currentColor;opacity:.7}
        .badge.ok{background:var(--ok-bg);color:var(--primary-h)}
        .badge.warn{background:var(--warn-bg);color:#b45309}
        .badge.danger{background:var(--danger-bg);color:var(--danger)}
        .badge.info{background:var(--info-bg);color:#1d4ed8}
        .note{color:var(--muted);font-size:.84rem;margin:12px 0 0}
        .pill-row{display:flex;flex-wrap:wrap;gap:8px}
        code{background:var(--alt);padding:2px 6px;border-radius:6px;font-size:.82rem}
        a{color:var(--blue)}
        .findings li{list-style:none;padding:4px 0;border-bottom:1px solid var(--border);font-size:.85rem}
        .findings .loc{color:var(--danger);font-weight:700;font-family:ui-monospace,Menlo,monospace}
        .toolbar{display:flex;gap:10px;flex-wrap:wrap;margin-bottom:18px}
        .toolbar a{display:inline-block;text-decoration:none;font-weight:700;font-size:.85rem;
            padding:8px 14px;border-radius:var(--radius-sm);background:var(--alt);color:var(--text);
            border:1px solid var(--border)}
    </style>
</head>
<body>
<div class="wrap">
    <h1>Prod Parity Doctor</h1>
    <p class="sub">Local XAMPP (PHP <?= $h($phpVersion) ?>) vs RMIT production (PHP 7.4.33). What breaks on deploy?</p>

    <div class="toolbar">
        <a href="doctor.php">Re-run checks</a>
        <a href="../include/migrations/migrate.php">Migrations</a>
        <a href="../tests/index.php">Test Lab</a>
    </div>

    <div class="card">
        <div class="summary <?= $warnings === 0 ? 'clean' : 'warn' ?>">
            <?php if ($warnings === 0): ?>
                <span class="badge ok"><span class="dot"></span>All clear</span>
                No prod-parity issues detected.
            <?php else: ?>
                <span class="badge warn"><span class="dot"></span><?= $warnings ?></span>
                item<?= $warnings === 1 ? '' : 's' ?> need attention (see below).
            <?php endif; ?>
        </div>
    </div>

    <!-- 1. Runtime -->
    <div class="card">
        <h2>Runtime &amp; caps</h2>
        <table>
            <tr><th>Setting</th><th>Local</th><th>RMIT prod</th><th>Status</th></tr>
            <tr>
                <td class="k">php version</td>
                <td class="mono"><?= $h($phpVersion) ?></td>
                <td class="mono">7.4.33</td>
                <td><?= $phpIs8
                    ? doc_badge('PHP 8 locally — avoid 8.x syntax', 'warn')
                    : doc_badge('matches', 'ok') ?></td>
            </tr>
            <?php foreach ($caps as $c): ?>
                <tr>
                    <td class="k"><?= $h($c[0]) ?></td>
                    <td class="mono"><?= $h($c[1]) ?></td>
                    <td class="mono"><?= $h($c[2]) ?></td>
                    <td><?= doc_badge('prod cap', 'info') ?></td>
                </tr>
            <?php endforeach; ?>
        </table>
        <p class="note">Production web requests are hard-capped at 30s / 128M and uploads at 5M/file, 10M/POST. Keep handlers fast and lean.</p>
    </div>

    <!-- 2. Extensions -->
    <div class="card">
        <h2>Extensions missing on RMIT</h2>
        <table>
            <tr><th>Extension</th><th>Local</th><th>Status</th></tr>
            <?php foreach ($extDanger as $ext => $loaded): ?>
                <tr>
                    <td class="k"><?= $h($ext) ?></td>
                    <td class="mono"><?= $loaded ? 'loaded' : 'absent' ?></td>
                    <td><?= $loaded
                        ? doc_badge('present locally, MISSING on RMIT', 'danger')
                        : doc_badge('absent here too (good)', 'ok') ?></td>
                </tr>
            <?php endforeach; ?>
        </table>
        <p class="note">Anything <strong>loaded locally but missing on RMIT</strong> is a trap: code that uses it works here and fatals on prod. The landmine scan below flags actual usage (<code>mb_*</code>, <code>ZipArchive</code>, <code>NumberFormatter</code>, …).</p>
        <p class="note">RMIT-present essentials locally:
            <span class="pill-row" style="display:inline-flex">
            <?php foreach ($extPresent as $ext => $ok): ?>
                <?= $ok ? doc_badge($ext, 'ok') : doc_badge($ext . ' MISSING', 'danger') ?>
            <?php endforeach; ?>
            </span>
        </p>
    </div>

    <!-- 3. Landmine scan -->
    <div class="card">
        <h2>PHP 7.4 landmine scan</h2>
        <?php if ($landmineTotal === 0): ?>
            <?= doc_badge('clean', 'ok') ?>
            <span class="muted">Scanned <?= count($scanFiles) ?> file(s) — no PHP 8 syntax / missing-extension / disabled-function usage.</span>
        <?php else: ?>
            <p><?= doc_badge($landmineTotal . ' landmine(s) in ' . count($scanReport) . ' file(s)', 'danger') ?>
            <span class="muted">— would fatal on RMIT.</span></p>
            <ul class="findings">
                <?php
                $shown = 0;
                foreach ($scanReport as $path => $findings):
                    $rel = str_replace($ROOT . DIRECTORY_SEPARATOR, '', $path);
                    foreach ($findings as $f):
                        if ($shown++ >= 25) { break 2; }
                        ?>
                        <li><span class="loc"><?= $h($rel) ?>:<?= (int) $f['line'] ?></span>
                            <span class="muted">[<?= $h($f['kind']) ?>]</span> <?= $h($f['detail']) ?></li>
                    <?php endforeach;
                endforeach; ?>
            </ul>
            <?php if ($landmineTotal > 25): ?><p class="note">Showing first 25. Full list: <code>php scripts/php74-lint.php</code></p><?php endif; ?>
        <?php endif; ?>
        <p class="note">Same engine as the pre-commit hook (<code>scripts/php74-lint.php</code>). Install it with <code>./scripts/install-hooks.sh</code>.</p>
    </div>

    <!-- 4. Secrets -->
    <div class="card">
        <h2>secrets.php completeness</h2>
        <?php if (!$secretsLoaded): ?>
            <p><?= doc_badge('include/secrets.php not found', 'danger') ?></p>
            <p class="note">Create it: <code>cp include/secrets.example.php include/secrets.php</code> and fill in the keys.</p>
        <?php elseif (!$requiredKeys): ?>
            <p><?= doc_badge('secrets.example.php missing / empty', 'warn') ?> <span class="muted">— cannot determine required keys.</span></p>
        <?php else: ?>
            <table>
                <tr><th>Constant</th><th>Status</th></tr>
                <?php foreach ($secretRows as $r):
                    $st = $r['status'];
                    if ($st === 'ok')          { $b = doc_badge('set', 'ok'); }
                    elseif ($st === 'empty')   { $b = doc_badge($r['optional'] ? 'empty (optional — feature off)' : 'empty', $r['optional'] ? 'info' : 'warn'); }
                    elseif ($st === 'placeholder') { $b = doc_badge('still placeholder', 'warn'); }
                    elseif ($st === 'missing') { $b = doc_badge('MISSING from secrets.php', $r['optional'] ? 'warn' : 'danger'); }
                    else                       { $b = doc_badge('no secrets.php', 'danger'); }
                    ?>
                    <tr><td class="k"><?= $h($r['key']) ?></td><td><?= $b ?></td></tr>
                <?php endforeach; ?>
            </table>
            <p class="note">Required keys are read from <code>include/secrets.example.php</code>. When a PR adds a new secret, add it there too so every environment gets flagged.</p>
        <?php endif; ?>
    </div>

    <!-- 5. Database -->
    <div class="card">
        <h2>Database</h2>
        <?php if ($dbError !== null): ?>
            <p><?= doc_badge('connection failed', 'danger') ?></p>
            <p class="mono muted"><?= $h($dbError) ?></p>
            <p class="note">Start MySQL in XAMPP and check <code>include/db_config.php</code>.</p>
        <?php else: ?>
            <table>
                <tr><td class="k">database</td><td class="mono"><?= $h($dbName) ?></td></tr>
                <tr><td class="k">server</td><td class="mono"><?= $h($dbHost) ?> &middot; MySQL <?= $h($dbVersion) ?></td></tr>
                <tr><td class="k">credentials</td><td class="mono"><?= is_file($ROOT . '/include/db_config.local.php')
                    ? 'db_config.local.php (per-host override)'
                    : 'XAMPP defaults (no db_config.local.php on this host)' ?></td></tr>
            </table>
            <h3 style="font-size:.92rem;margin:16px 0 8px">Charset audit (latin1 corruption risk)</h3>
            <?php if (!$latinTables): ?>
                <?= doc_badge('all tables utf8mb4', 'ok') ?>
            <?php else: ?>
                <p><?= doc_badge(count($latinTables) . ' table(s) not utf8mb4', 'danger') ?>
                <span class="muted">— non-ASCII (Vietnamese) text here can corrupt.</span></p>
                <div class="pill-row">
                    <?php foreach ($latinTables as $t): ?>
                        <span class="badge warn"><span class="dot"></span><?= $h($t['TABLE_NAME']) ?> <span class="muted" style="font-weight:400">(<?= $h($t['TABLE_COLLATION']) ?>)</span></span>
                    <?php endforeach; ?>
                </div>
                <p class="note">Fix with <code>CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci</code> (see migration <code>2026_06_01_convert_latin1_to_utf8mb4.sql</code>).</p>
            <?php endif; ?>
        <?php endif; ?>
    </div>

    <!-- 6. Migrations -->
    <?php if ($dbError === null): ?>
    <div class="card">
        <h2>Migrations</h2>
        <p>
            <?= doc_badge($migApplied . ' applied', 'ok') ?>
            <?= $migPending
                ? doc_badge(count($migPending) . ' pending', 'warn')
                : doc_badge('0 pending', 'ok') ?>
        </p>
        <?php if ($migPending): ?>
            <div class="pill-row">
                <?php foreach ($migPending as $p): ?>
                    <span class="badge warn"><span class="dot"></span><?= $h($p) ?></span>
                <?php endforeach; ?>
            </div>
            <p class="note">Apply them on the <a href="../include/migrations/migrate.php">migration dashboard</a>.</p>
        <?php endif; ?>
    </div>
    <?php endif; ?>

    <!-- 7. Timezone -->
    <div class="card">
        <h2>Timezone</h2>
        <table>
            <tr><td class="k">PHP default</td><td class="mono"><?= $h($phpTz) ?> &middot; now <?= $h($phpNow) ?></td></tr>
            <tr><td class="k">DB session</td><td class="mono"><?= $h($dbTz) ?> &middot; now <?= $h($dbNow) ?></td></tr>
        </table>
        <p class="note">Prod PHP runs in <code>Australia/Melbourne</code>; the DB connection is forced to <code>+07:00</code>. Don't trust server <code>date('H')</code> for user-local time — send the time from the browser.</p>
    </div>

    <p class="note" style="text-align:center">This is a local dev tool. Remove or admin-guard <code>dev/doctor.php</code> on production.</p>
</div>
</body>
</html>
