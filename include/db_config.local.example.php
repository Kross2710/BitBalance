<?php
/**
 * TEMPLATE for include/db_config.local.php (gitignored, per-host credentials).
 *
 * include/db_config.php starts from XAMPP localhost defaults and then requires
 * this file (if present) to OVERRIDE them for the current host. Copy it:
 *
 *     cp include/db_config.local.example.php include/db_config.local.php
 *
 * WHEN YOU NEED IT
 *   - Local dev: usually NOT needed — the XAMPP defaults (localhost / test /
 *     root / empty password) already work. Only create it if your local MySQL
 *     uses a different host/db/user/password.
 *   - Production (RMIT): REQUIRED. Without it a non-local host refuses to start
 *     (it will not silently fall back to localhost). Create this file on the
 *     server with the real credentials before deploying the new db_config.php.
 *
 * Only set the variables you need to change; anything left out keeps the
 * default from db_config.php. NEVER commit include/db_config.local.php.
 */

// --- Production (RMIT) example — fill in the real values on the server -------
$host     = 'talsprddb02.int.its.rmit.edu.au';
$dbname   = 'COSC3046_2502_G20';
$username = 'COSC3046_2502_G20';
$password = 'YOUR_DB_PASSWORD';
$port     = 3306;

// --- Alternate local example (uncomment + edit if your XAMPP differs) --------
// $host     = '127.0.0.1';
// $dbname   = 'bitbalance';
// $username = 'root';
// $password = '';
// $port     = 3307;
