<?php
require_once __DIR__ . '/_helpers.php';

api_require_method('POST');

if (!isset($_SESSION['user']) || !isset($_SESSION['user']['user_id'])) {
    api_error('Authentication required.', 401);
}

$data = api_request_data();
$pdo = api_connect_db();
require_once PROJECT_ROOT . 'include/handlers/log_attempt.php';

$user = api_require_auth($pdo);
$userId = (int) $user['user_id'];

$firstName = isset($data['first_name']) ? trim($data['first_name']) : '';
$lastName = isset($data['last_name']) ? trim($data['last_name']) : '';
$handle = isset($data['user_name']) ? trim($data['user_name']) : '';
$email = isset($data['email']) ? trim($data['email']) : '';
$bio = isset($data['bio']) ? trim($data['bio']) : '';
$theme = isset($data['theme_preference']) ? trim($data['theme_preference']) : 'system';
$calorieGoal = isset($data['calorie_goal']) ? api_profile_nullable_int($data['calorie_goal']) : null;
$age = isset($data['age']) ? api_profile_nullable_int($data['age']) : null;
$gender = isset($data['gender']) ? trim($data['gender']) : null;
$weight = isset($data['weight']) ? api_profile_nullable_float($data['weight']) : null;
$height = isset($data['height']) ? api_profile_nullable_float($data['height']) : null;

if ($firstName === '' || $lastName === '' || $handle === '' || $email === '') {
    api_error('Please fill in all required fields.', 422);
}

if (!preg_match('/^[A-Za-z0-9_.#\-]{3,30}$/', $handle)) {
    api_error('Username must be 3-30 characters: letters, numbers, and . # - _.', 422);
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    api_error('Please enter a valid email address.', 422);
}

if (!in_array($theme, ['light', 'dark', 'system'], true)) {
    api_error('Invalid theme selected.', 422);
}

if ($calorieGoal !== null && ($calorieGoal < 800 || $calorieGoal > 10000)) {
    api_error('Please enter a valid calorie goal (800-10,000).', 422);
}

if ($age !== null && ($age < 1 || $age > 130)) {
    api_error('Age must be between 1 and 130.', 422);
}

if ($gender === '') {
    $gender = null;
}
if ($gender !== null && !in_array($gender, ['male', 'female', 'other'], true)) {
    api_error('Invalid gender selected.', 422);
}

if ($weight !== null && ($weight <= 0 || $weight > 999)) {
    api_error('Weight must be between 1 and 999 kg.', 422);
}

if ($height !== null && ($height <= 0 || $height > 300)) {
    api_error('Height must be between 1 and 300 cm.', 422);
}

try {
    $stmt = $pdo->prepare("SELECT user_id FROM user WHERE email = ? AND user_id != ?");
    $stmt->execute([$email, $userId]);
    if ($stmt->fetch()) {
        api_error('This email is already taken by another user.', 409);
    }

    $stmt = $pdo->prepare("SELECT user_id FROM user WHERE user_name = ? AND user_id != ?");
    $stmt->execute([$handle, $userId]);
    if ($stmt->fetch()) {
        api_error('This username is already taken.', 409);
    }

    $stmt = $pdo->prepare("
        UPDATE user
        SET first_name = ?, last_name = ?, user_name = ?, email = ?
        WHERE user_id = ?
    ");
    $stmt->execute([$firstName, $lastName, $handle, $email, $userId]);

    $stmt = $pdo->prepare("UPDATE userStatus SET profile_bio = ?, theme_preference = ? WHERE user_id = ?");
    $stmt->execute([$bio, $theme, $userId]);

    if ($calorieGoal !== null) {
        $stmt = $pdo->prepare("INSERT INTO userGoal (user_id, calorie_goal, date_set) VALUES (?, ?, NOW())");
        $stmt->execute([$userId, $calorieGoal]);
    }

    $stmt = $pdo->prepare("SELECT userPhysicalStat_id FROM userPhysicalInfo WHERE user_id = ?");
    $stmt->execute([$userId]);
    $physicalExists = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($physicalExists) {
        $stmt = $pdo->prepare("
            UPDATE userPhysicalInfo
            SET age = ?, gender = ?, weight = ?, height = ?
            WHERE user_id = ?
        ");
        $stmt->execute([$age, $gender, $weight, $height, $userId]);
    } else {
        $stmt = $pdo->prepare("
            INSERT INTO userPhysicalInfo (userPhysicalStat_id, user_id, age, gender, weight, height)
            VALUES (?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([$userId, $userId, $age, $gender, $weight, $height]);
    }

    $_SESSION['user']['first_name'] = $firstName;
    $_SESSION['user']['last_name'] = $lastName;
    $_SESSION['user']['user_name'] = $handle;
    $_SESSION['user']['email'] = $email;
    $_SESSION['user']['theme_preference'] = $theme;

    log_attempt($pdo, $userId, 'update_profile', 'User updated profile via API', 'user', $userId);

    $profile = api_profile_fetch_user($pdo, $userId);
    api_send(true, api_profile_payload($pdo, $profile), null);
} catch (Throwable $e) {
    error_log('API profile update error: ' . $e->getMessage());
    api_error('Unable to update profile.', 500);
}
