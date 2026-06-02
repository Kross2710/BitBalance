<?php
/**
 * Database connection bootstrap — environment-aware and SECRET-FREE.
 *
 * This committed file holds NO production credentials (safe for the public
 * repo). It resolves the connection in two steps:
 *
 *   1. Start from safe local XAMPP defaults (localhost / test / root / no pass)
 *      so a fresh local clone connects with zero configuration.
 *   2. If include/db_config.local.php exists, require it to OVERRIDE the
 *      $host / $dbname / $username / $password / $port for THIS host. That file
 *      is gitignored — production keeps its real RMIT credentials there and it
 *      is never committed, so there is no toggle to comment in/out anymore.
 *
 * SETUP
 *   - Local dev: nothing to do — the XAMPP defaults below just work. (Only if
 *     your local MySQL differs: copy db_config.local.example.php to
 *     db_config.local.php and edit it.)
 *   - Production (RMIT): REQUIRED — create include/db_config.local.php from the
 *     example with the real credentials BEFORE deploying this file. On a
 *     non-local host with no local config the app fails loudly (below) instead
 *     of silently falling back to localhost.
 *
 * The DB connection time zone is forced to +07:00 (Vietnam) per connection.
 */

// 1. Local-development defaults (non-secret; fine in a public repo).
$host     = 'localhost';
$dbname   = 'test';
$username = 'root';
$password = '';
$port     = 3306;

// 2. Per-host credential override (gitignored). Production puts RMIT creds here.
$localCfg    = __DIR__ . '/db_config.local.php';
$hasLocalCfg = is_file($localCfg);
if ($hasLocalCfg) {
    require $localCfg; // may reassign $host/$dbname/$username/$password/$port
}

// 3. Fail loud on a misconfigured non-local host rather than silently trying the
//    XAMPP defaults (which would mask a missing db_config.local.php on a real
//    server and surface as confusing "access denied" noise). CLI is treated as
//    local so migrations/tests/seed run against XAMPP without extra setup.
$serverHost  = $_SERVER['HTTP_HOST'] ?? ($_SERVER['SERVER_NAME'] ?? '');
$isLocalHost = (PHP_SAPI === 'cli')
    || in_array($serverHost, ['localhost', '127.0.0.1', '[::1]'], true)
    || (bool) preg_match('/^(localhost|127\.0\.0\.1|\[::1\])(:\d+)?$/i', $serverHost);

if (!$hasLocalCfg && !$isLocalHost) {
    if (defined('BITBALANCE_API_REQUEST') && BITBALANCE_API_REQUEST) {
        throw new RuntimeException(
            'Database not configured: include/db_config.local.php is missing on a non-local host. '
            . 'Copy include/db_config.local.example.php and add the real credentials.'
        );
    }
    http_response_code(500);
    die('Server misconfiguration: include/db_config.local.php is missing.');
}

try {
    $pdo = new PDO("mysql:host=$host;port=$port;dbname=$dbname;charset=utf8mb4", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    $pdo->exec("SET time_zone = '+07:00';");
} catch (PDOException $e) {
    if (defined('BITBALANCE_API_REQUEST') && BITBALANCE_API_REQUEST) {
        throw $e;
    }
    die("Connection failed: " . $e->getMessage());
}
