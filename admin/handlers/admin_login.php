<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

$error_message = '';

// Process form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $email = trim($_POST['email']);
    $password = $_POST['password'];

    // Basic validation
    if (empty($email) || empty($password)) {
        $error_message = "Please fill in all fields.";
    } else {
        try {
            // Check if user exists in database
            $stmt = $pdo->prepare("SELECT user_id, user_name, first_name, last_name, email, password, role FROM user WHERE email = ?");
            $stmt->execute([$email]);
            $user = $stmt->fetch();

            if ($user && password_verify($password, $user['password'])) {
                // Login successful - set session variables
                $_SESSION['user'] = [
                    'user_id' => $user['user_id'],
                    'user_name' => $user['user_name'],
                    'first_name' => $user['first_name'],
                    'last_name' => $user['last_name'],
                    'email' => $user['email'],
                    'role' => $user['role']
                ];

                // Update last login time
                $stmt = $pdo->prepare("UPDATE `user` SET last_login = NOW() WHERE user_id = ?");
                $stmt->execute([$user['user_id']]);

                // Redirect to homepage
                header("Location: admin.php");
                exit();
            } else {
                $error_message = "Invalid email or password.";

                // Redirect to login page with error message
                header("Location: admin.php?error=" . urlencode($error_message));
            }
        } catch (PDOException $e) {
            $error_message = "Database error. Please try again.";
            // Log the actual error for debugging
            error_log("Login error: " . $e->getMessage());
        }
    }
}
?>
