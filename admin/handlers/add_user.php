<?php
require_once __DIR__ . '/../../include/handlers/log_attempt.php';
$admin_id = $_SESSION['user']['user_id'] ?? null;

$error_message = '';
$success_message = '';

// Process form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $first_name = trim($_POST['first_name']);
    $last_name = trim($_POST['last_name']);
    $username = trim($_POST['username']);
    $email = trim($_POST['email']);
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];
    $role = $_POST['role'];

    // Basic validation including terms acceptance
    if (empty($first_name) || empty($last_name) || empty($username) || empty($email) || empty($password) || empty($confirm_password) || empty($role)) {
        $error_message = "Please fill in all fields.";
    } elseif ($password !== $confirm_password) {
        $error_message = "Passwords do not match.";
    } elseif (strlen($password) < 8) {
        $error_message = "Password must be at least 8 characters long.";
    } elseif (!preg_match('/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d).+$/', $password)) {
        $error_message = "Password must contain at least one uppercase letter, one lowercase letter, and one number.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error_message = "Please enter a valid email address.";
    } else {
        try {
            // Check if email already exists
            $stmt = $pdo->prepare("SELECT user_id FROM user WHERE email = ?");
            $stmt->execute([$email]);
            if ($stmt->fetch()) {
                $error_message = "An account with this email already exists.";
            } else {
                // Check if username already exists
                $stmt = $pdo->prepare("SELECT user_id FROM user WHERE user_name = ?");
                $stmt->execute([$username]);
                if ($stmt->fetch()) {
                    $error_message = "This username is already taken.";
                } else {
                    // Hash the password
                    $hashed_password = password_hash($password, PASSWORD_DEFAULT);

                    // Insert new user
                    $stmt = $pdo->prepare("INSERT INTO user (user_name, first_name, last_name, email, password, role, created_at) VALUES (?, ?, ?, ?, ?, ?, NOW())");
                    $stmt->execute([$username, $first_name, $last_name, $email, $hashed_password, $role]);

                    $user_id = $pdo->lastInsertId();

                    // Insert default user status
                    $stmt = $pdo->prepare("INSERT INTO userStatus (user_id, status, theme_preference, failed_attempts, locked_until) VALUES (?, 'active', 'light', 0, NULL)");
                    $stmt->execute([$user_id]);

                    // Log the user creation attempt by the admin
                    log_attempt($pdo, $admin_id, 'create_user',  'User ' . $user_id . ' created by Admin ' . $admin_id, 'user', $user_id);

                    // Redirect to homepage
                    header("Location: admin-users.php");
                    exit();
                }
            }
        } catch (PDOException $e) {
            $error_message = "Registration failed. Please try again.";
            error_log("Signup error: " . $e->getMessage());
        }
    }
}
?>