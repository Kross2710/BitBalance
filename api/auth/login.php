<?php
require_once __DIR__ . '/../_bootstrap.php';
require_once PROJECT_ROOT . 'include/handlers/log_attempt.php';

api_require_method('POST');

$data = api_request_data();
$email = isset($data['email']) ? trim($data['email']) : '';
$password = isset($data['password']) ? $data['password'] : '';

if ($email === '' || $password === '') {
    api_error('Please fill in all fields.', 422);
}

$pdo = api_connect_db();

try {
    $stmt = $pdo->prepare("
        SELECT u.user_id, u.user_name, u.first_name, u.last_name, u.email, u.password, u.role, u.profile_image,
               us.status, us.failed_attempts, us.locked_until, us.theme_preference
        FROM user u
        JOIN userStatus us ON u.user_id = us.user_id
        WHERE u.email = ?
        LIMIT 1
    ");
    $stmt->execute([$email]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        api_error('Invalid email or password.', 401);
    }

    $now = new DateTime();
    $lockedUntil = !empty($user['locked_until']) ? new DateTime($user['locked_until']) : null;

    if ($lockedUntil && $now < $lockedUntil) {
        $remainingTime = $lockedUntil->diff($now);
        $minutes = $remainingTime->i;
        $seconds = $remainingTime->s;
        api_error("Account is locked. Try again in {$minutes} minutes and {$seconds} seconds.", 423);
    }

    if ($user['status'] === 'archived') {
        api_error('This account has been archived. Please contact support.', 403);
    }

    if ($user['status'] === 'banned') {
        api_error('This account has been banned. Please contact support.', 403);
    }

    if ($lockedUntil && $now >= $lockedUntil) {
        $stmt = $pdo->prepare("
            UPDATE userStatus
            SET failed_attempts = 0, locked_until = NULL
            WHERE user_id = ?
        ");
        $stmt->execute([(int) $user['user_id']]);
        $user['failed_attempts'] = 0;
    }

    if (!password_verify($password, $user['password'])) {
        $newFailedAttempts = (int) $user['failed_attempts'] + 1;

        if ($newFailedAttempts >= 3) {
            $lockUntil = new DateTime();
            $lockUntil->add(new DateInterval('PT1H'));

            $stmt = $pdo->prepare("
                UPDATE userStatus
                SET failed_attempts = ?, locked_until = ?
                WHERE user_id = ?
            ");
            $stmt->execute([$newFailedAttempts, $lockUntil->format('Y-m-d H:i:s'), (int) $user['user_id']]);

            api_error('Account locked due to 3 failed login attempts. Try again in 1 hour.', 423);
        }

        $stmt = $pdo->prepare("
            UPDATE userStatus
            SET failed_attempts = ?
            WHERE user_id = ?
        ");
        $stmt->execute([$newFailedAttempts, (int) $user['user_id']]);

        $remainingAttempts = 3 - $newFailedAttempts;
        api_error("Invalid email or password. {$remainingAttempts} attempts remaining.", 401);
    }

    $stmt = $pdo->prepare("
        UPDATE userStatus
        SET failed_attempts = 0, locked_until = NULL
        WHERE user_id = ?
    ");
    $stmt->execute([(int) $user['user_id']]);

    session_regenerate_id(true);
    $_SESSION['user'] = api_public_user($user);

    $stmt = $pdo->prepare("UPDATE user SET last_login = NOW() WHERE user_id = ?");
    $stmt->execute([(int) $user['user_id']]);

    log_attempt($pdo, (int) $user['user_id'], 'login', 'User logged in successfully via API');

    api_send(true, api_public_user($user), null);
} catch (PDOException $e) {
    error_log('API login error: ' . $e->getMessage());
    api_error('Database error. Please try again.', 500);
}
