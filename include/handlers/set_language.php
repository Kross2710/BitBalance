<?php
/**
 * POST handler for the footer language switcher.
 *
 * Inputs:
 *   - lang        (string)  Locale code, must exist in include/i18n/locales.php
 *   - csrf_token  (string)  Session CSRF token
 *   - redirect    (string)  Optional same-origin redirect target (falls back
 *                            to Referer, then index.php)
 *
 * Persists the choice to userStatus.language_preference (logged-in) and a
 * cookie (guests + cross-device cache).
 */

require_once __DIR__ . '/../init.php';
require_once __DIR__ . '/../csrf.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ' . BASE_URL);
    exit;
}

if (!csrf_verify($_POST['csrf_token'] ?? '')) {
    http_response_code(400);
    exit('Invalid request.');
}

$requested = isset($_POST['lang']) ? trim((string) $_POST['lang']) : '';
if (!is_valid_locale($requested)) {
    http_response_code(400);
    exit('Unknown language.');
}

$userId = $isLoggedIn ? (int) $_SESSION['user']['user_id'] : null;
set_locale($requested, $isLoggedIn ? $pdo : null, $userId);

// Same-origin redirect only. We accept either an explicit redirect param or
// the Referer header, sanitised to a path so an attacker can't bounce the
// user to a different host via a crafted form.
$target = $_POST['redirect'] ?? ($_SERVER['HTTP_REFERER'] ?? '');
$path = '';
if ($target !== '') {
    $parts = parse_url($target);
    if (!empty($parts['path'])) {
        $path = $parts['path'];
        if (!empty($parts['query'])) {
            $path .= '?' . $parts['query'];
        }
    }
}
if ($path === '' || $path[0] !== '/') {
    $path = BASE_URL . 'index.php';
}
header('Location: ' . $path);
exit;
