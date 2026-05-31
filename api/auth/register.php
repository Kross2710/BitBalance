<?php
require_once __DIR__ . '/../_bootstrap.php';
require_once PROJECT_ROOT . 'include/handlers/log_attempt.php';
require_once PROJECT_ROOT . 'include/handlers/username.php';

api_require_method('POST');

$data = api_request_data();
$firstName = isset($data['first_name']) ? trim($data['first_name']) : '';
$lastName = isset($data['last_name']) ? trim($data['last_name']) : '';
$email = isset($data['email']) ? trim($data['email']) : '';
$password = isset($data['password']) ? $data['password'] : '';
$confirmPassword = isset($data['confirm_password']) ? $data['confirm_password'] : '';

// Mirrors include/handlers/user_signup.php validation. CAPTCHA is intentionally
// dropped for the mobile API (no rendered image); add token-based abuse control
// in a later phase if needed.
if ($firstName === '' || $lastName === '' || $email === '' || $password === '' || $confirmPassword === '') {
    api_error('Please fill in all fields.', 422);
}

if ($password !== $confirmPassword) {
    api_error('Passwords do not match.', 422);
}

if (strlen($password) < 8) {
    api_error('Password must be at least 8 characters long.', 422);
}

if (!preg_match('/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d).+$/', $password)) {
    api_error('Password must contain at least one uppercase letter, one lowercase letter, and one number.', 422);
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    api_error('Please enter a valid email address.', 422);
}

$pdo = api_connect_db();

try {
    $stmt = $pdo->prepare("SELECT user_id FROM user WHERE email = ?");
    $stmt->execute([$email]);
    if ($stmt->fetch()) {
        api_error('An account with this email already exists.', 409);
    }

    // Auto-generate a unique Discord-style handle from the first name (Hung → Hung4821).
    $username = generate_handle($pdo, $firstName);
    $hashedPassword = password_hash($password, PASSWORD_DEFAULT);

    // UNIQUE key on user_name is the final guard against a rare race; the catch
    // below surfaces a retry message.
    $stmt = $pdo->prepare("INSERT INTO user (user_name, first_name, last_name, email, password, role, created_at) VALUES (?, ?, ?, ?, ?, 'regular', NOW())");
    $stmt->execute([$username, $firstName, $lastName, $email, $hashedPassword]);

    $userId = (int) $pdo->lastInsertId();

    $stmt = $pdo->prepare("INSERT INTO userStatus (user_id, status, theme_preference, failed_attempts, locked_until) VALUES (?, 'active', 'system', 0, NULL)");
    $stmt->execute([$userId]);

    $userRow = [
        'user_id' => $userId,
        'user_name' => $username,
        'first_name' => $firstName,
        'last_name' => $lastName,
        'email' => $email,
        'role' => 'regular',
        'profile_image' => null,
        'theme_preference' => 'system',
        'needs_onboarding' => 1
    ];

    // Auto-login after successful registration (same as the web flow).
    session_regenerate_id(true);
    $_SESSION['user'] = api_public_user($userRow);

    log_attempt($pdo, $userId, 'signup', 'User signed up successfully via API');

    api_send(true, api_public_user($userRow), null);
} catch (PDOException $e) {
    error_log('API register error: ' . $e->getMessage());
    api_error('Registration failed. Please try again.', 500);
}
