<?php
// include/handlers/google_oauth.php
//
// Shared helpers for "Sign in with Google". The server-side Authorization Code
// flow lives in two root entry points that mirror the proven Spotify pattern:
//   - google_auth.php      builds state + redirects to Google
//   - google_callback.php  exchanges the code, then calls in here
//
// PHP 7.4 compatible (RMIT prod). No external libraries: the user profile is
// read from Google's userinfo endpoint, so we never decode the id_token JWT.

require_once __DIR__ . '/username.php'; // generate_handle()

/**
 * True only when both Google OAuth credentials are present in secrets.php.
 * Used to hide the Google buttons and short-circuit the handlers when the
 * feature has not been configured yet (graceful degradation).
 */
function google_oauth_configured(): bool
{
    return defined('GOOGLE_CLIENT_ID') && defined('GOOGLE_CLIENT_SECRET')
        && GOOGLE_CLIENT_ID !== '' && GOOGLE_CLIENT_SECRET !== '';
}

/**
 * Absolute redirect URI for the callback. Must match the value registered in
 * Google Cloud Console exactly. Built dynamically so the same code works on
 * local XAMPP and the RMIT host (honours the X-Forwarded-Proto proxy header).
 */
function google_oauth_redirect_uri(): string
{
    $isHttps = (isset($_SERVER['HTTPS']) && ($_SERVER['HTTPS'] === 'on' || $_SERVER['HTTPS'] == 1))
        || (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https');
    $protocol = $isHttps ? 'https' : 'http';
    return $protocol . '://' . $_SERVER['HTTP_HOST'] . BASE_URL . 'google_callback.php';
}

/**
 * Resolve a Google profile to a BitBalance user, creating or linking as needed.
 *
 * Matching order:
 *   1. Existing (provider, provider_uid) -> that user (returning Google user).
 *   2. Existing local account with the same verified email -> link Google to it.
 *   3. Otherwise create a fresh account (auto handle + unusable random password)
 *      and link the Google identity.
 *
 * @param array $g  Normalised profile: sub, email, first, last, picture.
 * @return array{user_id:int, is_new:bool}
 */
function google_find_or_create(PDO $pdo, array $g): array
{
    $email = strtolower(trim($g['email']));

    // 1) Known Google identity.
    $stmt = $pdo->prepare(
        "SELECT user_id FROM user_identity WHERE provider = 'google' AND provider_uid = ? LIMIT 1"
    );
    $stmt->execute([$g['sub']]);
    $row = $stmt->fetch();
    if ($row) {
        google_link_identity($pdo, (int) $row['user_id'], $g, $email);
        return ['user_id' => (int) $row['user_id'], 'is_new' => false];
    }

    // 2) Existing local account with the same email -> link (Google email is verified).
    $stmt = $pdo->prepare("SELECT user_id FROM user WHERE LOWER(email) = ? LIMIT 1");
    $stmt->execute([$email]);
    $row = $stmt->fetch();
    if ($row) {
        google_link_identity($pdo, (int) $row['user_id'], $g, $email);
        return ['user_id' => (int) $row['user_id'], 'is_new' => false];
    }

    // 3) Brand-new account.
    $pdo->beginTransaction();
    try {
        $handle  = generate_handle($pdo, $g['first'] !== '' ? $g['first'] : 'user');
        // OAuth accounts have no usable password; store a random hash so the
        // NOT NULL column is satisfied and password login can never match.
        $randomPw = password_hash(bin2hex(random_bytes(32)), PASSWORD_DEFAULT);

        $stmt = $pdo->prepare(
            "INSERT INTO user (user_name, first_name, last_name, email, password, role, profile_image, created_at)
             VALUES (?, ?, ?, ?, ?, 'regular', ?, NOW())"
        );
        $stmt->execute([
            $handle,
            $g['first'],
            $g['last'],
            $email,
            $randomPw,
            ($g['picture'] !== '' ? $g['picture'] : null),
        ]);
        $userId = (int) $pdo->lastInsertId();

        $stmt = $pdo->prepare(
            "INSERT INTO userStatus (user_id, status, theme_preference, failed_attempts, locked_until)
             VALUES (?, 'active', 'system', 0, NULL)"
        );
        $stmt->execute([$userId]);

        google_link_identity($pdo, $userId, $g, $email);

        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }

    return ['user_id' => $userId, 'is_new' => true];
}

/**
 * Insert or refresh the user_identity row for this Google account.
 */
function google_link_identity(PDO $pdo, int $userId, array $g, string $email): void
{
    $stmt = $pdo->prepare(
        "INSERT INTO user_identity (user_id, provider, provider_uid, email)
         VALUES (?, 'google', ?, ?)
         ON DUPLICATE KEY UPDATE user_id = VALUES(user_id), email = VALUES(email)"
    );
    $stmt->execute([$userId, $g['sub'], $email]);
}

/**
 * Build the $_SESSION['user'] payload for a user id, mirroring user_login.php.
 * Returns null if the account is archived/banned (caller should refuse login).
 *
 * @return array|null
 */
function google_build_session_user(PDO $pdo, int $userId): ?array
{
    $stmt = $pdo->prepare(
        "SELECT u.user_id, u.user_name, u.first_name, u.last_name, u.email, u.role, u.profile_image,
                us.status, us.theme_preference
         FROM user u
         JOIN userStatus us ON u.user_id = us.user_id
         WHERE u.user_id = ?"
    );
    $stmt->execute([$userId]);
    $row = $stmt->fetch();
    if (!$row || $row['status'] === 'archived' || $row['status'] === 'banned') {
        return null;
    }

    return [
        'user_id'          => (int) $row['user_id'],
        'user_name'        => $row['user_name'],
        'first_name'       => $row['first_name'],
        'last_name'        => $row['last_name'],
        'email'            => $row['email'],
        'role'             => $row['role'],
        'profile_image'    => $row['profile_image'],
        'theme_preference' => $row['theme_preference'] ?? 'system',
    ];
}
