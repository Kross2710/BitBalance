<?php
/**
 * BitBalance demo-data seeder + local DB reset (dev tool).
 *
 * Populates the local test DB with a realistic demo account so you can develop
 * and QA features without hand-entering data. All demo accounts use the marker
 * email domain "@bitbalance.test" so they can be re-seeded / wiped cleanly.
 *
 * ACTIONS
 *   seed   — wipe any existing demo data, then insert a fresh demo set:
 *            a regular user (loginable), full profile + goal + XP, 14 days of
 *            intake + weight, a friend, and a PT who trains the demo user.
 *   wipe   — remove ONLY the demo accounts and their rows (leaves real data).
 *   reset  — DESTRUCTIVE: clear ALL rows from every table (schema + migration
 *            history preserved), then seed. A clean slate with demo data.
 *
 * SAFETY
 *   - Open on localhost (no login); elsewhere requires an admin session.
 *   - Refuses to run write actions against a production-looking DB
 *     (host contains "rmit" or db name starts with "COSC").
 *
 * USAGE
 *   Browser: .../dev/seed.php
 *   CLI:     php dev/seed.php            # seed
 *            php dev/seed.php --wipe     # wipe demo data
 *            php dev/seed.php --reset    # clear ALL data + seed
 *
 * Demo login after seeding:  demo@bitbalance.test  /  Password1
 */

const DEMO_DOMAIN   = '@bitbalance.test';
const DEMO_PASSWORD = 'Password1';

$isCli = (PHP_SAPI === 'cli');

/* =========================================================================
 * SEED / WIPE / RESET CORE (shared by CLI + web)
 * ===================================================================== */

/** Does $table have column $col in the current database? */
function seed_col_exists(PDO $pdo, string $table, string $col): bool
{
    $stmt = $pdo->prepare(
        "SELECT 1 FROM information_schema.COLUMNS
          WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ? LIMIT 1"
    );
    $stmt->execute([$table, $col]);
    return (bool) $stmt->fetchColumn();
}

/** Does $table exist in the current database? */
function seed_table_exists(PDO $pdo, string $table): bool
{
    $stmt = $pdo->prepare(
        "SELECT 1 FROM information_schema.TABLES
          WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? LIMIT 1"
    );
    $stmt->execute([$table]);
    return (bool) $stmt->fetchColumn();
}

/** user_id list for every demo account (marker email domain). */
function seed_demo_ids(PDO $pdo): array
{
    $stmt = $pdo->prepare("SELECT user_id FROM user WHERE email LIKE ?");
    $stmt->execute(['%' . DEMO_DOMAIN]);
    return array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));
}

/** Remove all demo accounts and their rows. Returns number of users removed. */
function seed_wipe_demo(PDO $pdo): int
{
    $ids = seed_demo_ids($pdo);
    if (!$ids) {
        return 0;
    }
    $in = implode(',', array_fill(0, count($ids), '?'));

    // (table, [columns that reference a user]) — children first, user last.
    $targets = [
        ['intakeLog',         ['user_id']],
        ['weight_log',        ['user_id']],
        ['user_xp',           ['user_id']],
        ['xp_event',          ['user_id']],
        ['mascot_state',      ['user_id']],
        ['userGoal',          ['user_id']],
        ['userPhysicalInfo',  ['user_id']],
        ['pt_goal_proposal',  ['trainer_id', 'client_id']],
        ['pt_message',        ['sender_id']],
        ['pt_thread',         ['trainer_id', 'client_id']],
        ['trainer_client',    ['trainer_id', 'client_id']],
        ['pt_profile',        ['user_id']],
        ['friend_request',    ['requester_id', 'addressee_id']],
        ['userStatus',        ['user_id']],
        ['user',              ['user_id']],
    ];

    $pdo->exec('SET FOREIGN_KEY_CHECKS=0');
    foreach ($targets as [$table, $cols]) {
        if (!seed_table_exists($pdo, $table)) {
            continue;
        }
        foreach ($cols as $col) {
            if (!seed_col_exists($pdo, $table, $col)) {
                continue;
            }
            $pdo->prepare("DELETE FROM `$table` WHERE `$col` IN ($in)")->execute($ids);
        }
    }
    $pdo->exec('SET FOREIGN_KEY_CHECKS=1');

    return count($ids);
}

/** Clear ALL rows from every base table except schema_migrations. Returns count. */
function seed_reset_all_data(PDO $pdo): int
{
    $tables = $pdo->query(
        "SELECT TABLE_NAME FROM information_schema.TABLES
          WHERE TABLE_SCHEMA = DATABASE() AND TABLE_TYPE = 'BASE TABLE'"
    )->fetchAll(PDO::FETCH_COLUMN);

    $cleared = 0;
    $pdo->exec('SET FOREIGN_KEY_CHECKS=0');
    foreach ($tables as $t) {
        if ($t === 'schema_migrations') {
            continue; // keep migration history; schema is untouched
        }
        try {
            $pdo->exec("TRUNCATE TABLE `$t`");
        } catch (Throwable $e) {
            $pdo->exec("DELETE FROM `$t`");
        }
        $cleared++;
    }
    $pdo->exec('SET FOREIGN_KEY_CHECKS=1');

    return $cleared;
}

/** Create a demo user + active status row. Returns the new user_id. */
function seed_make_user(PDO $pdo, string $handle, string $first, string $last, string $email, string $role): int
{
    $hash = password_hash(DEMO_PASSWORD, PASSWORD_DEFAULT);
    $pdo->prepare(
        "INSERT INTO user (user_name, first_name, last_name, email, password, role, created_at)
         VALUES (?, ?, ?, ?, ?, ?, NOW())"
    )->execute([$handle, $first, $last, $email, $hash, $role]);
    $id = (int) $pdo->lastInsertId();

    $pdo->prepare(
        "INSERT INTO userStatus (user_id, status, theme_preference, failed_attempts, locked_until)
         VALUES (?, 'active', 'system', 0, NULL)"
    )->execute([$id]);

    return $id;
}

/**
 * Insert the full demo data set. Returns a list of human-readable result lines.
 * Wipes existing demo data first so it is safe to run repeatedly.
 */
function seed_run(PDO $pdo): array
{
    $log = [];

    $removed = seed_wipe_demo($pdo);
    if ($removed > 0) {
        $log[] = "Removed $removed existing demo account(s) before re-seeding.";
    }

    // --- Demo user ----------------------------------------------------------
    $demoId = seed_make_user($pdo, 'Demo#1001', 'Demo', 'User', 'demo' . DEMO_DOMAIN, 'regular');
    $log[] = "Created demo user (id $demoId): demo" . DEMO_DOMAIN . " / " . DEMO_PASSWORD;

    // Physical info + calorie/macro goal. userPhysicalStat_id is NOT
    // auto-increment in this schema — the app keys it to user_id (see
    // dashboard/set-goal.php), so mirror that 1:1 convention.
    $pdo->prepare("INSERT INTO userPhysicalInfo (userPhysicalStat_id, user_id, age, gender, weight, height) VALUES (?, ?, ?, ?, ?, ?)")
        ->execute([$demoId, $demoId, 28, 'male', 78.0, 178.0]);

    if (seed_col_exists($pdo, 'userGoal', 'protein_goal')) {
        $sql = "INSERT INTO userGoal (user_id, calorie_goal, protein_goal, carbs_goal, fat_goal, date_set) VALUES (?, ?, ?, ?, ?, NOW())";
        $args = [$demoId, 2200, 150, 240, 70];
        if (seed_col_exists($pdo, 'userGoal', 'source')) {
            $sql = "INSERT INTO userGoal (user_id, calorie_goal, protein_goal, carbs_goal, fat_goal, source, date_set) VALUES (?, ?, ?, ?, ?, 'self', NOW())";
        }
        $pdo->prepare($sql)->execute($args);
    } else {
        $pdo->prepare("INSERT INTO userGoal (user_id, calorie_goal, date_set) VALUES (?, ?, NOW())")
            ->execute([$demoId, 2200]);
    }

    // XP + streak flavour.
    if (seed_table_exists($pdo, 'user_xp')) {
        $pdo->prepare("INSERT INTO user_xp (user_id, total_xp, current_level) VALUES (?, ?, ?)")
            ->execute([$demoId, 540, 4]);
    }
    try {
        $pdo->prepare(
            "UPDATE userStatus SET logging_streak = 7, longest_logging_streak = 12, last_logging_date = CURDATE() WHERE user_id = ?"
        )->execute([$demoId]);
    } catch (Throwable $e) { /* streak columns optional */ }

    // Mascot (optional flavour).
    if (seed_table_exists($pdo, 'mascot_state')) {
        try {
            $pdo->prepare("INSERT INTO mascot_state (user_id, name) VALUES (?, 'Sprout')")->execute([$demoId]);
        } catch (Throwable $e) { /* skip if columns differ */ }
    }

    // --- 14 days of weight + intake ----------------------------------------
    $weightStmt = $pdo->prepare("INSERT INTO weight_log (user_id, weight, date_logged) VALUES (?, ?, ?)");
    $intakeStmt = $pdo->prepare(
        "INSERT INTO intakeLog (user_id, food_item, meal_category, calories, protein, carbs, fat, date_intake)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?)"
    );

    $breakfasts = [
        ['Oatmeal with banana', 350, 12, 60, 8],
        ['Eggs and toast', 420, 24, 30, 22],
        ['Greek yogurt and granola', 300, 20, 35, 6],
    ];
    $lunches = [
        ['Grilled chicken salad', 520, 45, 25, 22],
        ['Beef pho', 600, 35, 70, 15],
        ['Turkey sandwich', 480, 30, 50, 16],
    ];
    $dinners = [
        ['Salmon with rice', 650, 40, 60, 25],
        ['Stir-fry tofu and veggies', 520, 25, 55, 20],
        ['Spaghetti bolognese', 700, 35, 80, 24],
    ];
    $snacks = [
        ['Apple and peanut butter', 220, 6, 25, 12],
        ['Protein shake', 180, 30, 8, 3],
    ];

    $intakeRows = 0;
    $startWeight = 80.0;
    for ($i = 13; $i >= 0; $i--) {
        $day = date('Y-m-d', strtotime("-$i days"));

        // Gentle downward weight trend.
        $w = round($startWeight - ((13 - $i) * 0.13), 1);
        $weightStmt->execute([$demoId, $w, $day]);

        $k = (13 - $i) % 3;
        $meals = [
            ['08:00:00', 'breakfast', $breakfasts[$k]],
            ['12:30:00', 'lunch',     $lunches[$k]],
            ['19:00:00', 'dinner',    $dinners[$k]],
        ];
        if ($i % 2 === 0) {
            $meals[] = ['16:00:00', 'snack', $snacks[$i % 2]];
        }
        foreach ($meals as [$time, $cat, $food]) {
            [$name, $cal, $p, $c, $f] = $food;
            $intakeStmt->execute([$demoId, $name, $cat, $cal, $p, $c, $f, "$day $time"]);
            $intakeRows++;
        }
    }
    $log[] = "Logged 14 days of weight + $intakeRows intake entries.";

    // --- Friend (buddy) -----------------------------------------------------
    try {
        $buddyId = seed_make_user($pdo, 'Buddy#1002', 'Buddy', 'Friend', 'buddy' . DEMO_DOMAIN, 'regular');
        $pdo->prepare("INSERT INTO userPhysicalInfo (userPhysicalStat_id, user_id, age, gender, weight, height) VALUES (?, ?, 31, 'female', 62.0, 165.0)")
            ->execute([$buddyId, $buddyId]);
        if (seed_table_exists($pdo, 'friend_request')) {
            $pdo->prepare(
                "INSERT INTO friend_request (requester_id, addressee_id, status, responded_at) VALUES (?, ?, 'accepted', NOW())"
            )->execute([$demoId, $buddyId]);
        }
        $log[] = "Created friend Buddy#1002 (accepted friendship with demo).";
    } catch (Throwable $e) {
        $log[] = "Skipped friend seeding: " . $e->getMessage();
    }

    // --- Personal trainer ---------------------------------------------------
    try {
        $coachId = seed_make_user($pdo, 'Coach#1003', 'Coach', 'Trainer', 'coach' . DEMO_DOMAIN, 'pt');
        if (seed_table_exists($pdo, 'pt_profile')) {
            $pdo->prepare(
                "INSERT INTO pt_profile (user_id, bio, specialties, experience_years, max_clients, accepted_terms)
                 VALUES (?, 'Certified coach focused on sustainable habits.', 'Weight loss, Strength', 5, 20, 1)"
            )->execute([$coachId]);
        }
        if (seed_table_exists($pdo, 'trainer_client')) {
            $pdo->prepare(
                "INSERT INTO trainer_client (trainer_id, client_id, status, responded_at) VALUES (?, ?, 'accepted', NOW())"
            )->execute([$coachId, $demoId]);
        }
        if (seed_table_exists($pdo, 'pt_goal_proposal')) {
            if (seed_col_exists($pdo, 'pt_goal_proposal', 'protein_goal')) {
                $pdo->prepare(
                    "INSERT INTO pt_goal_proposal (trainer_id, client_id, calorie_goal, protein_goal, carbs_goal, fat_goal, note, status)
                     VALUES (?, ?, 2100, 160, 210, 65, 'Let us aim a little leaner this week.', 'pending')"
                )->execute([$coachId, $demoId]);
            } else {
                $pdo->prepare(
                    "INSERT INTO pt_goal_proposal (trainer_id, client_id, calorie_goal, note, status)
                     VALUES (?, ?, 2100, 'Let us aim a little leaner this week.', 'pending')"
                )->execute([$coachId, $demoId]);
            }
        }
        $log[] = "Created PT Coach#1003 (training demo, with a pending goal proposal).";
    } catch (Throwable $e) {
        $log[] = "Skipped PT seeding: " . $e->getMessage();
    }

    return $log;
}

/* =========================================================================
 * CLI MODE
 * ===================================================================== */

if ($isCli) {
    $args  = array_slice($argv, 1);
    $wipe  = in_array('--wipe', $args, true);
    $reset = in_array('--reset', $args, true);

    require_once __DIR__ . '/../include/db_config.php'; // $pdo

    $dbName = (string) $pdo->query('SELECT DATABASE()')->fetchColumn();
    $dbHost = (string) $pdo->query('SELECT @@hostname')->fetchColumn();
    if (stripos($dbHost, 'rmit') !== false || stripos($dbName, 'COSC') === 0) {
        fwrite(STDERR, "Refusing to run: '$dbName' on '$dbHost' looks like PRODUCTION.\n");
        exit(1);
    }

    echo "BitBalance seeder — database: $dbName\n";
    echo str_repeat('-', 48) . "\n";

    if ($wipe) {
        $n = seed_wipe_demo($pdo);
        echo "Wiped $n demo account(s).\n";
        return;
    }

    if ($reset) {
        $cleared = seed_reset_all_data($pdo);
        echo "Cleared ALL data from $cleared table(s) (schema kept).\n";
    }

    foreach (seed_run($pdo) as $line) {
        echo "  $line\n";
    }
    echo str_repeat('-', 48) . "\n";
    echo "Done. Log in as demo" . DEMO_DOMAIN . " / " . DEMO_PASSWORD . "\n";
    return;
}

/* =========================================================================
 * WEB MODE
 * ===================================================================== */

if (!defined('BITBALANCE_API_REQUEST')) {
    define('BITBALANCE_API_REQUEST', true);
}
require_once __DIR__ . '/../include/init.php';

$remote  = $_SERVER['REMOTE_ADDR'] ?? '';
$hostHdr = $_SERVER['HTTP_HOST'] ?? ($_SERVER['SERVER_NAME'] ?? '');
$isLocal = in_array($remote, ['127.0.0.1', '::1', 'localhost'], true)
        || (bool) preg_match('/^(localhost|127\.0\.0\.1|\[::1\])(:\d+)?$/i', $hostHdr);
$isAdmin = !empty($isLoggedIn) && (($user['role'] ?? '') === 'admin');

if (!$isLocal && !$isAdmin) {
    http_response_code(403);
    header('Content-Type: text/plain; charset=utf-8');
    echo "Forbidden. The seeder is open on localhost only; elsewhere sign in as admin.\n";
    return;
}

try {
    require_once __DIR__ . '/../include/db_config.php';
} catch (Throwable $e) {
    http_response_code(500);
    header('Content-Type: text/plain; charset=utf-8');
    echo "Database connection failed: " . $e->getMessage() . "\n";
    return;
}

$dbName   = (string) $pdo->query('SELECT DATABASE()')->fetchColumn();
$dbHost   = (string) $pdo->query('SELECT @@hostname')->fetchColumn();
$isProdDb = (stripos($dbHost, 'rmit') !== false) || (stripos($dbName, 'COSC') === 0);

if (empty($_SESSION['seed_csrf'])) {
    $_SESSION['seed_csrf'] = bin2hex(random_bytes(16));
}
$csrf = $_SESSION['seed_csrf'];

$result = null; // ['mode'=>, 'ok'=>bool, 'lines'=>[], 'note'=>]

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    if (!hash_equals($csrf, (string) ($_POST['csrf'] ?? ''))) {
        $result = ['mode' => 'Error', 'ok' => false, 'lines' => ['Invalid or expired session token. Reload and try again.'], 'note' => ''];
    } elseif ($isProdDb) {
        $result = ['mode' => 'Blocked', 'ok' => false, 'lines' => ["Refusing to modify '$dbName' — it looks like PRODUCTION."], 'note' => ''];
    } else {
        $action = $_POST['action'] ?? '';
        try {
            if ($action === 'wipe') {
                $n = seed_wipe_demo($pdo);
                $result = ['mode' => 'Wipe demo data', 'ok' => true, 'lines' => ["Removed $n demo account(s) and their rows."], 'note' => ''];
            } elseif ($action === 'reset') {
                $cleared = seed_reset_all_data($pdo);
                $lines = ["Cleared ALL data from $cleared table(s) — schema and migration history kept."];
                $lines = array_merge($lines, seed_run($pdo));
                $result = ['mode' => 'Reset (all data) + seed', 'ok' => true, 'lines' => $lines, 'note' => 'Log in as demo' . DEMO_DOMAIN . ' / ' . DEMO_PASSWORD];
            } else { // seed
                $lines = seed_run($pdo);
                $result = ['mode' => 'Seed demo data', 'ok' => true, 'lines' => $lines, 'note' => 'Log in as demo' . DEMO_DOMAIN . ' / ' . DEMO_PASSWORD];
            }
        } catch (Throwable $e) {
            $result = ['mode' => ucfirst($action ?: 'seed'), 'ok' => false, 'lines' => ['Error: ' . $e->getMessage()], 'note' => ''];
        }
    }
}

// Current state.
$totalUsers = (int) $pdo->query("SELECT COUNT(*) FROM user")->fetchColumn();
$demoUsers  = count(seed_demo_ids($pdo));
$selfUrl    = htmlspecialchars(strtok((string) ($_SERVER['REQUEST_URI'] ?? 'seed.php'), '?'), ENT_QUOTES, 'UTF-8');
$h = fn ($s) => htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8');

header('Content-Type: text/html; charset=utf-8');
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Demo seeder — BitBalance</title>
    <style>
        :root{--bg:#f8fafc;--surface:#fff;--alt:#f1f5f9;--text:#1e2937;--muted:#64748b;--faint:#94a3b8;
            --border:#e2e8f0;--primary:#58CC02;--primary-h:#4CAF00;--blue:#1CB0F6;--orange:#FF9600;
            --danger:#ef4444;--danger-bg:#fee2e2;--ok-bg:#e8f5e9;--warn:#f59e0b;--warn-bg:#fef3c7;
            --shadow:0 10px 30px rgba(15,23,42,.08);--radius:20px;--radius-sm:12px}
        @media (prefers-color-scheme:dark){:root{--bg:#0f172a;--surface:#1e2937;--alt:#334155;--text:#f1f5f9;
            --muted:#94a3b8;--faint:#64748b;--border:#475569;--danger-bg:#7f1d1d;--ok-bg:#14532d;--warn-bg:#78350f;
            --shadow:0 10px 40px rgba(0,0,0,.5)}}
        *{box-sizing:border-box}
        body{font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,Arial,sans-serif;background:var(--bg);
            color:var(--text);margin:0;line-height:1.6;padding:32px 20px 80px}
        .wrap{max-width:880px;margin:0 auto}
        h1{font-size:1.6rem;margin:0 0 4px}
        .sub{color:var(--muted);margin:0 0 22px;font-size:.95rem}
        .card{background:var(--surface);border:1px solid var(--border);border-radius:var(--radius);
            box-shadow:var(--shadow);padding:20px 24px;margin-bottom:18px}
        .toolbar{display:flex;gap:10px;flex-wrap:wrap;margin-bottom:18px}
        .toolbar a{display:inline-block;text-decoration:none;font-weight:700;font-size:.85rem;padding:8px 14px;
            border-radius:var(--radius-sm);background:var(--alt);color:var(--text);border:1px solid var(--border)}
        .stats{display:flex;gap:28px;flex-wrap:wrap}
        .stat .n{font-size:1.9rem;font-weight:800;line-height:1}
        .stat .l{color:var(--muted);font-size:.85rem}
        .banner{border-radius:var(--radius-sm);padding:12px 16px;margin-bottom:18px;font-size:.9rem;display:flex;gap:10px}
        .banner.prod{background:var(--danger-bg);border:1px solid var(--danger)}
        .banner.local{background:var(--ok-bg);border:1px solid var(--primary)}
        .dot{width:8px;height:8px;border-radius:50%;display:inline-block;margin-top:7px}
        .actions{display:flex;gap:10px;flex-wrap:wrap}
        button{font:inherit;font-weight:700;cursor:pointer;border:none;border-radius:var(--radius-sm);
            padding:11px 18px;color:#fff;transition:filter .15s,transform .1s}
        button:hover{filter:brightness(1.05)}button:active{transform:translateY(1px)}
        button:disabled{opacity:.45;cursor:not-allowed;filter:none}
        .btn-primary{background:var(--primary);box-shadow:0 4px 0 var(--primary-h)}
        .btn-ghost{background:var(--alt);color:var(--text);border:1px solid var(--border)}
        .btn-danger{background:var(--danger);box-shadow:0 4px 0 #b91c1c}
        form.inline{display:inline}
        .result{border-radius:var(--radius-sm);padding:4px 0}
        .result.ok{border-left:4px solid var(--primary)}
        .result.err{border-left:4px solid var(--danger)}
        .log{font-family:ui-monospace,SFMono-Regular,Menlo,monospace;font-size:.85rem;margin:0;padding-left:0}
        .log li{list-style:none;padding:4px 0}
        .note{color:var(--muted);margin:10px 0 0;font-size:.88rem}
        code{background:var(--alt);padding:2px 6px;border-radius:6px}
    </style>
</head>
<body>
<div class="wrap">
    <h1>Demo data seeder</h1>
    <p class="sub">Populate the local DB with a demo account, 14 days of logs, a friend and a PT.</p>

    <div class="toolbar">
        <a href="seed.php">Refresh</a>
        <a href="doctor.php">Doctor</a>
        <a href="../include/migrations/migrate.php">Migrations</a>
    </div>

    <?php if ($isProdDb): ?>
        <div class="banner prod"><span class="dot" style="background:#ef4444"></span>
            <div><strong>Production database detected</strong> (<?= $h($dbName) ?> on <?= $h($dbHost) ?>). Write actions are disabled.</div>
        </div>
    <?php elseif ($isLocal): ?>
        <div class="banner local"><span class="dot" style="background:#58CC02"></span>
            <div><strong>Local environment</strong> — safe to seed and reset.</div>
        </div>
    <?php endif; ?>

    <?php if ($result !== null): ?>
        <div class="card result <?= $result['ok'] ? 'ok' : 'err' ?>">
            <h3 style="margin:0 0 10px"><?= $h($result['mode']) ?> — <?= $result['ok'] ? 'done' : 'failed' ?></h3>
            <ul class="log">
                <?php foreach ($result['lines'] as $line): ?><li><?= $h($line) ?></li><?php endforeach; ?>
            </ul>
            <?php if ($result['note'] !== ''): ?><p class="note"><?= $h($result['note']) ?></p><?php endif; ?>
        </div>
    <?php endif; ?>

    <div class="card">
        <div class="stats">
            <div class="stat"><div class="n"><?= $totalUsers ?></div><div class="l">total users</div></div>
            <div class="stat"><div class="n"><?= $demoUsers ?></div><div class="l">demo users</div></div>
            <div class="stat"><div class="n" style="font-size:1.1rem"><?= $h($dbName) ?></div><div class="l">database</div></div>
        </div>
    </div>

    <div class="card">
        <div class="actions">
            <form class="inline" method="post" action="<?= $selfUrl ?>">
                <input type="hidden" name="csrf" value="<?= $h($csrf) ?>">
                <input type="hidden" name="action" value="seed">
                <button class="btn-primary" type="submit" <?= $isProdDb ? 'disabled' : '' ?>>Seed demo data</button>
            </form>
            <form class="inline" method="post" action="<?= $selfUrl ?>"
                  onsubmit="return confirm('Remove all demo accounts (@bitbalance.test) and their rows?');">
                <input type="hidden" name="csrf" value="<?= $h($csrf) ?>">
                <input type="hidden" name="action" value="wipe">
                <button class="btn-ghost" type="submit" <?= ($isProdDb || $demoUsers === 0) ? 'disabled' : '' ?>>Wipe demo data</button>
            </form>
            <form class="inline" method="post" action="<?= $selfUrl ?>"
                  onsubmit="return confirm('DESTRUCTIVE: clear ALL rows from EVERY table in <?= $h($dbName) ?> (schema kept), then seed demo data.\n\nThis deletes any real local data. Continue?');">
                <input type="hidden" name="csrf" value="<?= $h($csrf) ?>">
                <input type="hidden" name="action" value="reset">
                <button class="btn-danger" type="submit" <?= $isProdDb ? 'disabled' : '' ?>>Reset all data + seed</button>
            </form>
        </div>
        <p class="note"><strong>Seed</strong> re-creates the demo set (wipes old demo data first). <strong>Wipe</strong> removes only demo accounts. <strong>Reset</strong> clears every table then seeds — a clean slate.</p>
    </div>

    <p class="note" style="text-align:center">Local dev tool. Remove or admin-guard <code>dev/seed.php</code> on production.</p>
</div>
</body>
</html>
