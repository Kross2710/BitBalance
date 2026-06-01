<?php
/**
 * Regenerate the signup CAPTCHA question without a full page reload.
 *
 * GET include/handlers/captcha_refresh.php  ->  { "ok": true, "question": "7 + 5 = ?" }
 *
 * Uses the same session (via init.php) that signup.php verifies against, so the
 * freshly generated answer is stored server-side under the user's session.
 */
require_once __DIR__ . '/../init.php';
require_once __DIR__ . '/captcha.php';

header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');
header('Cache-Control: no-store');

echo json_encode([
    'ok' => true,
    'question' => CustomCaptcha::generateCaptcha(),
]);
