<?php
require_once __DIR__ . '/_helpers.php';

api_require_method('GET');

if (!isset($_SESSION['user']) || !isset($_SESSION['user']['user_id'])) {
    api_error('Authentication required.', 401);
}

$pdo = api_connect_db();
$user = api_require_auth($pdo);
$profile = api_profile_fetch_user($pdo, (int) $user['user_id']);

if (!$profile) {
    api_error('Profile not found.', 404);
}

api_send(true, api_profile_payload($pdo, $profile), null);
