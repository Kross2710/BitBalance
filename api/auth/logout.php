<?php
require_once __DIR__ . '/../_bootstrap.php';

api_require_method('POST');

// Revoke the persistent remember-me token (DB row) before clearing the session.
if (!empty($_COOKIE['bb_remember'])) {
    require_once PROJECT_ROOT . 'include/handlers/remember_token.php';
    remember_forget(api_connect_db());
}

api_destroy_session();
api_send(true, null, null);
