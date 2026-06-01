<?php
require_once __DIR__ . '/include/init.php';
require_once __DIR__ . '/include/handlers/remember_token.php';

// Revoke the persistent remember-me token (DB row + browser cookie) so the user
// is not silently signed back in after logging out. Only touch the DB when a
// remember cookie is actually present.
$pdoForForget = null;
if (!empty($_COOKIE[REMEMBER_COOKIE])) {
    require_once __DIR__ . '/include/db_config.php';
    $pdoForForget = $pdo;
}
remember_forget($pdoForForget);

setcookie(session_name(), '', 100);
session_unset();       // Clear all $_SESSION variables
session_destroy();     // Destroy the session

// Redirect to homepage
header("Location:" . BASE_URL . "index.php");
exit();