<?php
require_once __DIR__ . '/_bootstrap.php';

api_require_method('GET');

if (!isset($_SESSION['user']) || !isset($_SESSION['user']['user_id'])) {
    api_error('Authentication required.', 401);
}

$pdo = api_connect_db();
$user = api_require_auth($pdo);
api_send(true, api_public_user($user), null);
