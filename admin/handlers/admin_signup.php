<?php
require_once __DIR__ . '/../../include/handlers/log_attempt.php';

$error_message = '';
$success_message = '';

// Generate CAPTCHA question ONLY if not already set or if there was an error
if (!isset($_SESSION['captcha_answer']) || !isset($_SESSION['captcha_question'])) {
    $captcha_question = CustomCaptcha::generateCaptcha();
} else {
    $captcha_question = $_SESSION['captcha_question'];
}

// Process form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $first_name = trim($_POST['first_name']);
    $last_name = trim($_POST['last_name']);
    $username = trim($_POST['username']);
    $email = trim($_POST['email']);
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];
    $captcha_answer = $_POST['captcha_answer'];
    $accept_terms = isset($_POST['accept_terms']);

    // Basic validation including terms acceptance
    if (empty($first_name) || empty($last_name) || empty($username) || empty($email) || empty($password) || empty($confirm_password) || empty($captcha_answer)) {
        $error_message = "Please fill in all fields.";
        $captcha_question = CustomCaptcha::generateCaptcha();
    } elseif (!$accept_terms) {
        $error_message = "You must accept the Terms and Conditions to create an account.";
        $captcha_question = CustomCaptcha::generateCaptcha();
    } elseif (!CustomCaptcha::verifyCaptcha($captcha_answer)) {
        $error_message = "Incorrect CAPTCHA answer. Please try again.";
        $captcha_question = CustomCaptcha::generateCaptcha();
    } elseif ($password !== $confirm_password) {
        $error_message = "Passwords do not match.";
        $captcha_question = CustomCaptcha::generateCaptcha();
    } elseif (strlen($password) < 8) {
        $error_message = "Password must be at least 8 characters long.";
        $captcha_question = CustomCaptcha::generateCaptcha();
    } elseif (!preg_match('/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d).+$/', $password)) {
        $error_message = "Password must contain at least one uppercase letter, one lowercase letter, and one number.";
        $captcha_question = CustomCaptcha::generateCaptcha();
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error_message = "Please enter a valid email address.";
        $captcha_question = CustomCaptcha::generateCaptcha();
    } else {
        try {
            // Check if email already exists
            $stmt = $pdo->prepare("SELECT user_id FROM user WHERE email = ?");
            $stmt->execute([$email]);
            if ($stmt->fetch()) {
                $error_message = "An account with this email already exists.";
                $captcha_question = CustomCaptcha::generateCaptcha();
            } else {
                // Check if username already exists
                $stmt = $pdo->prepare("SELECT user_id FROM user WHERE user_name = ?");
                $stmt->execute([$username]);
                if ($stmt->fetch()) {
                    $error_message = "This username is already taken.";
                    $captcha_question = CustomCaptcha::generateCaptcha();
                } else {
                    // Hash the password
                    $hashed_password = password_hash($password, PASSWORD_DEFAULT);

                    // Insert new user
                    $stmt = $pdo->prepare("INSERT INTO user (user_name, first_name, last_name, email, password, role, created_at) VALUES (?, ?, ?, ?, ?, 'admin', NOW())");
                    $stmt->execute([$username, $first_name, $last_name, $email, $hashed_password]);

                    $user_id = $pdo->lastInsertId();

                    // Insert default user status
                    $stmt = $pdo->prepare("INSERT INTO userStatus (user_id, status, theme_preference, failed_attempts, locked_until) VALUES (?, 'active', 'light', 0, NULL)");
                    $stmt->execute([$user_id]);

                    // Auto-login the user after successful registration
                    $_SESSION['user'] = [
                        'user_id' => $user_id,
                        'user_name' => $username,
                        'first_name' => $first_name,
                        'last_name' => $last_name,
                        'email' => $email,
                        'role' => 'admin'
                    ];

                    // Log the signup attempt
                    log_attempt($pdo, $user_id, 'signup', 'User signed up successfully');

                    // Redirect to homepage
                    header("Location: admin.php");
                    exit();
                }
            }
        } catch (PDOException $e) {
            $error_message = "Registration failed. Please try again.";
            error_log("Signup error: " . $e->getMessage());
            $captcha_question = CustomCaptcha::generateCaptcha();
        }
    }
}
?>