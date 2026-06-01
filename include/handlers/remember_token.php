<?php
// Persistent "Remember Me" login tokens.
//
// Implements the selector/validator pattern (split-cookie tokens):
//   - The cookie holds "<selector>:<validator>".
//   - The DB stores the selector in clear and only a SHA-256 hash of the
//     validator, so a database leak cannot be replayed as a login.
//   - Lookups are by the unique selector (fast, indexed); the validator is
//     compared with hash_equals() to avoid timing attacks.
//
// This lets a returning user be signed back in automatically for up to
// REMEMBER_LIFETIME, long after their short-lived PHP session has expired.

if (!defined('REMEMBER_COOKIE')) {
    // NOTE: this literal is also referenced in include/init.php and
    // api/_bootstrap.php to cheaply gate the auto-login attempt before this
    // file is loaded. Keep all three in sync.
    define('REMEMBER_COOKIE', 'bb_remember');
}
if (!defined('REMEMBER_LIFETIME')) {
    define('REMEMBER_LIFETIME', 60 * 60 * 24 * 30); // 30 days, in seconds
}

/**
 * Whether the current request is over HTTPS (mirrors include/init.php).
 */
function remember_is_secure()
{
    return (isset($_SERVER['HTTPS']) && ($_SERVER['HTTPS'] === 'on' || $_SERVER['HTTPS'] == 1))
        || (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https');
}

/**
 * Write (or refresh) the remember-me cookie on the browser.
 */
function remember_set_cookie($value, $expires)
{
    if (headers_sent()) {
        return; // Can't set a cookie after output has started; skip silently.
    }
    setcookie(REMEMBER_COOKIE, $value, [
        'expires' => $expires,
        'path' => '/',
        'domain' => '',
        'secure' => remember_is_secure(),
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    $_COOKIE[REMEMBER_COOKIE] = $value;
}

/**
 * Remove the remember-me cookie from the browser.
 */
function remember_delete_cookie()
{
    if (!headers_sent()) {
        setcookie(REMEMBER_COOKIE, '', [
            'expires' => time() - 42000,
            'path' => '/',
            'domain' => '',
            'secure' => remember_is_secure(),
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
    }
    unset($_COOKIE[REMEMBER_COOKIE]);
}

/**
 * Issue a fresh remember-me token for $userId and send it to the browser.
 * Call this right after a successful password login when the user opted in.
 */
function remember_create(PDO $pdo, $userId)
{
    try {
        $selector = bin2hex(random_bytes(12));    // 24 hex chars, stored in clear
        $validator = bin2hex(random_bytes(32));   // 64 hex chars, secret
        $validatorHash = hash('sha256', $validator);
        $expiresTs = time() + REMEMBER_LIFETIME;
        $userAgent = isset($_SERVER['HTTP_USER_AGENT'])
            ? substr($_SERVER['HTTP_USER_AGENT'], 0, 255)
            : null;

        $stmt = $pdo->prepare("
            INSERT INTO auth_token (user_id, selector, validator_hash, expires, user_agent)
            VALUES (?, ?, ?, ?, ?)
        ");
        $stmt->execute([(int) $userId, $selector, $validatorHash, date('Y-m-d H:i:s', $expiresTs), $userAgent]);

        remember_set_cookie($selector . ':' . $validator, $expiresTs);
        return true;
    } catch (Exception $e) {
        // A failed remember-me token must never block an otherwise good login.
        error_log('remember_create failed: ' . $e->getMessage());
        return false;
    }
}

/**
 * Parse the incoming cookie into [selector, validator], or null if malformed.
 */
function remember_parse_cookie()
{
    if (empty($_COOKIE[REMEMBER_COOKIE])) {
        return null;
    }
    $parts = explode(':', $_COOKIE[REMEMBER_COOKIE], 2);
    if (count($parts) !== 2) {
        return null;
    }
    list($selector, $validator) = $parts;
    if (strlen($selector) !== 24 || strlen($validator) !== 64
        || !ctype_xdigit($selector) || !ctype_xdigit($validator)) {
        return null;
    }
    return [$selector, $validator];
}

/**
 * Attempt to sign the user in from their remember-me cookie.
 *
 * On success this populates $_SESSION['user'] (same shape as a normal login),
 * slides the token's expiry forward, refreshes the cookie, and returns true.
 * On any failure it clears the offending cookie and returns false.
 */
function remember_login(PDO $pdo)
{
    if (isset($_SESSION['user'])) {
        return true; // Already authenticated.
    }

    $parsed = remember_parse_cookie();
    if ($parsed === null) {
        return false;
    }
    list($selector, $validator) = $parsed;

    try {
        $stmt = $pdo->prepare("
            SELECT t.id, t.user_id, t.validator_hash,
                   u.user_name, u.first_name, u.last_name, u.email, u.role, u.profile_image,
                   us.status, us.theme_preference
            FROM auth_token t
            JOIN user u ON u.user_id = t.user_id
            JOIN userStatus us ON us.user_id = u.user_id
            WHERE t.selector = ? AND t.expires > NOW()
            LIMIT 1
        ");
        $stmt->execute([$selector]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$row) {
            // Unknown or expired selector — drop the stale cookie.
            remember_delete_cookie();
            return false;
        }

        // Constant-time comparison of the secret validator.
        if (!hash_equals($row['validator_hash'], hash('sha256', $validator))) {
            // Selector matched but validator did not: the cookie is corrupt or
            // forged. Revoke this token so it can't be retried.
            remember_revoke_id($pdo, (int) $row['id']);
            remember_delete_cookie();
            return false;
        }

        // Never auto-login a disabled account; revoke all of its tokens.
        if ($row['status'] === 'archived' || $row['status'] === 'banned') {
            remember_revoke_all($pdo, (int) $row['user_id']);
            remember_delete_cookie();
            return false;
        }

        // Re-establish the session (same shape as include/handlers/user_login.php).
        if (!headers_sent()) {
            session_regenerate_id(true);
        }
        $_SESSION['user'] = [
            'user_id' => (int) $row['user_id'],
            'user_name' => $row['user_name'],
            'first_name' => $row['first_name'],
            'last_name' => $row['last_name'],
            'email' => $row['email'],
            'role' => $row['role'],
            'profile_image' => $row['profile_image'],
            'theme_preference' => $row['theme_preference'] ?? 'system',
        ];

        // Slide the expiry forward so active users stay signed in, and refresh
        // the cookie to match. The validator is intentionally NOT rotated: that
        // keeps parallel requests (page + AJAX) from racing each other out.
        $expiresTs = time() + REMEMBER_LIFETIME;
        $upd = $pdo->prepare("UPDATE auth_token SET expires = ?, last_used_at = NOW() WHERE id = ?");
        $upd->execute([date('Y-m-d H:i:s', $expiresTs), (int) $row['id']]);
        remember_set_cookie($selector . ':' . $validator, $expiresTs);

        return true;
    } catch (Exception $e) {
        error_log('remember_login failed: ' . $e->getMessage());
        return false;
    }
}

/**
 * Revoke a single token row by id.
 */
function remember_revoke_id(PDO $pdo, $id)
{
    try {
        $stmt = $pdo->prepare("DELETE FROM auth_token WHERE id = ?");
        $stmt->execute([(int) $id]);
    } catch (Exception $e) {
        error_log('remember_revoke_id failed: ' . $e->getMessage());
    }
}

/**
 * Revoke every remember-me token for a user (e.g. on ban or "log out everywhere").
 */
function remember_revoke_all(PDO $pdo, $userId)
{
    try {
        $stmt = $pdo->prepare("DELETE FROM auth_token WHERE user_id = ?");
        $stmt->execute([(int) $userId]);
    } catch (Exception $e) {
        error_log('remember_revoke_all failed: ' . $e->getMessage());
    }
}

/**
 * Revoke the token referenced by the current request's cookie (used on logout),
 * then delete the cookie from the browser. Safe to call without a $pdo.
 */
function remember_forget(PDO $pdo = null)
{
    $parsed = remember_parse_cookie();
    if ($parsed !== null && $pdo instanceof PDO) {
        try {
            $stmt = $pdo->prepare("DELETE FROM auth_token WHERE selector = ?");
            $stmt->execute([$parsed[0]]);
        } catch (Exception $e) {
            error_log('remember_forget failed: ' . $e->getMessage());
        }
    }
    remember_delete_cookie();
}
