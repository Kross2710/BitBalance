<?php

require_once __DIR__ . '/include/init.php';
require_once __DIR__ . '/include/db_config.php';

$error_message = '';
$success_message = '';
$step = 'request'; // request, verify, reset

// Create password_resets table if it doesn't exist
try {
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS password_resets (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            token VARCHAR(64) NOT NULL UNIQUE,
            expires_at DATETIME NOT NULL,
            used TINYINT DEFAULT 0,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES user(user_id) ON DELETE CASCADE,
            INDEX idx_token (token),
            INDEX idx_expires (expires_at)
        )
    ");
} catch (PDOException $e) {
    error_log("Password reset table creation error: " . $e->getMessage());
}

if (isset($_GET['token'])) {
    $step = 'reset';
    $token = $_GET['token'];
    
    // Verify token is valid and not expired
    try {
        $stmt = $pdo->prepare("
            SELECT pr.*, u.email, u.user_id 
            FROM password_resets pr 
            JOIN user u ON pr.user_id = u.user_id 
            WHERE pr.token = ? AND pr.expires_at > NOW() AND pr.used = 0
        ");
        $stmt->execute([$token]);
        $reset_data = $stmt->fetch();
        
        if (!$reset_data) {
            $error_message = "Invalid or expired reset token.";
            $step = 'request';
        }
    } catch (PDOException $e) {
        $error_message = "Database error. Please try again.";
        $step = 'request';
    }
}

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    
    if (isset($_POST['request_reset'])) {
        $email = trim($_POST['email']);
        
        if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error_message = "Please enter a valid email address.";
        } else {
            try {
                // Check if user exists
                $stmt = $pdo->prepare("SELECT user_id, first_name FROM user WHERE email = ?");
                $stmt->execute([$email]);
                $user = $stmt->fetch();
                
                if ($user) {
                    // Generate reset token
                    $token = bin2hex(random_bytes(32));
                    $expires_at = date('Y-m-d H:i:s', strtotime('+1 hour'));
                    
                    // Insert reset token
                    $stmt = $pdo->prepare("
                        INSERT INTO password_resets (user_id, token, expires_at) 
                        VALUES (?, ?, ?)
                    ");
                    $stmt->execute([$user['user_id'], $token, $expires_at]);
                    
                    
                    $reset_link = "http://" . $_SERVER['HTTP_HOST'] . $_SERVER['PHP_SELF'] . "?token=" . $token;
                    $success_message = "Hello " . htmlspecialchars($user['first_name']) . "!<br><br>In a real application, an email would be sent to your address with a reset link.<br><br>For demo purposes, here's your reset link:<br><a href='$reset_link' class='reset-demo-link'>$reset_link</a><br><br><strong>This link expires in 1 hour.</strong>";
                } else {
                    // Don't reveal if email exists or not for security
                    $success_message = "If an account with that email exists, a password reset link has been sent.";
                }
            } catch (PDOException $e) {
                $error_message = "Database error. Please try again.";
                error_log("Password reset error: " . $e->getMessage());
            }
        }
    }
    
    elseif (isset($_POST['reset_password'])) {
        // Reset password with token
        $new_password = $_POST['new_password'];
        $confirm_password = $_POST['confirm_password'];
        $token = $_POST['token'];
        
        if (empty($new_password) || empty($confirm_password)) {
            $error_message = "Please fill in all fields.";
        } elseif ($new_password !== $confirm_password) {
            $error_message = "Passwords do not match.";
        } else {
            // Validate password strength
            $password_errors = validatePassword($new_password);
            if (!empty($password_errors)) {
                $error_message = implode("<br>", $password_errors);
            } else {
                try {
                    // Verify token again
                    $stmt = $pdo->prepare("
                        SELECT pr.*, u.user_id 
                        FROM password_resets pr 
                        JOIN user u ON pr.user_id = u.user_id 
                        WHERE pr.token = ? AND pr.expires_at > NOW() AND pr.used = 0
                    ");
                    $stmt->execute([$token]);
                    $reset_data = $stmt->fetch();
                    
                    if ($reset_data) {
                        // Update password
                        $hashed_password = hashPassword($new_password);
                        $stmt = $pdo->prepare("UPDATE user SET password = ? WHERE user_id = ?");
                        $stmt->execute([$hashed_password, $reset_data['user_id']]);
                        
                        // Mark token as used
                        $stmt = $pdo->prepare("UPDATE password_resets SET used = 1 WHERE token = ?");
                        $stmt->execute([$token]);
                        
                        // Reset failed login attempts
                        $stmt = $pdo->prepare("UPDATE userStatus SET failed_attempts = 0, locked_until = NULL WHERE user_id = ?");
                        $stmt->execute([$reset_data['user_id']]);
                        
                        $success_message = "Password reset successfully! You can now login with your new password.";
                        $step = 'complete';
                    } else {
                        $error_message = "Invalid or expired reset token.";
                        $step = 'request';
                    }
                } catch (PDOException $e) {
                    $error_message = "Database error. Please try again.";
                    error_log("Password reset error: " . $e->getMessage());
                }
            }
        }
    }
}

function validatePassword($password) {
    $errors = [];
    if (strlen($password) < 8) $errors[] = "Password must be at least 8 characters long";
    if (strlen($password) > 128) $errors[] = "Password must be less than 128 characters";
    if (!preg_match('/[A-Z]/', $password)) $errors[] = "Password must contain at least one uppercase letter";
    if (!preg_match('/[a-z]/', $password)) $errors[] = "Password must contain at least one lowercase letter";
    if (!preg_match('/[0-9]/', $password)) $errors[] = "Password must contain at least one number";
    if (!preg_match('/[^a-zA-Z0-9]/', $password)) $errors[] = "Password must contain at least one special character";
    return $errors;
}

function hashPassword($password) {
    $options = ['cost' => 12];
    return password_hash($password, PASSWORD_ARGON2ID, $options);
}
?>

<!DOCTYPE html>
<html lang="en" data-theme="<?php echo isset($_SESSION['user']) ? ($_SESSION['user']['theme_preference'] ?? 'system') : 'system'; ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reset Password - BitBalance</title>
    <?php
    $pageCss = ['css/login.css', 'css/pages/reset-password.css'];
    include PROJECT_ROOT . 'views/head_css.php';
    ?>
    <script src="https://kit.fontawesome.com/b94f65ead2.js" crossorigin="anonymous"></script>
</head>
<body>
    <?php include 'views/header.php'; ?>

    <div class="container">
        <div class="form-section">
            <?php if ($step == 'request'): ?>
                <h2><i class="fas fa-key"></i> Reset Password</h2>
                <p class="auth-subtitle">Enter your email and we'll send you a link to reset your password.</p>

                <?php if (!empty($error_message)): ?>
                    <div class="auth-alert auth-alert--error">
                        <i class="fas fa-exclamation-triangle"></i>
                        <div><?= $error_message ?></div>
                    </div>
                <?php endif; ?>

                <?php if (!empty($success_message)): ?>
                    <div class="auth-alert auth-alert--success">
                        <i class="fas fa-check-circle"></i>
                        <div><?= $success_message ?></div>
                    </div>
                <?php endif; ?>

                <form method="POST" class="js-submit-lock">
                    <div class="form-group">
                        <label for="reset-email">Email Address</label>
                        <input type="email" id="reset-email" name="email" required placeholder="Enter your email address">
                    </div>
                    <button type="submit" name="request_reset" class="login-button">
                        <i class="fas fa-paper-plane"></i> Send Reset Link
                    </button>
                </form>

            <?php elseif ($step == 'reset'): ?>
                <h2><i class="fas fa-lock"></i> Set New Password</h2>
                <p class="auth-subtitle">Choose a strong new password below.</p>

                <?php if (!empty($error_message)): ?>
                    <div class="auth-alert auth-alert--error">
                        <i class="fas fa-exclamation-triangle"></i>
                        <div><?= $error_message ?></div>
                    </div>
                <?php endif; ?>

                <div class="password-requirements">
                    <h4>Password Requirements</h4>
                    <ul>
                        <li id="length-req">At least 8 characters</li>
                        <li id="upper-req">One uppercase letter (A-Z)</li>
                        <li id="lower-req">One lowercase letter (a-z)</li>
                        <li id="number-req">One number (0-9)</li>
                        <li id="special-req">One special character (!@#$%^&amp;*)</li>
                    </ul>
                </div>

                <form method="POST" class="js-submit-lock">
                    <input type="hidden" name="token" value="<?= htmlspecialchars($token) ?>">
                    <div class="form-group">
                        <label for="new-password">New Password</label>
                        <input type="password" id="new-password" name="new_password" required minlength="8"
                            onkeyup="checkPasswordRequirements(this.value)">
                    </div>
                    <div class="form-group">
                        <label for="confirm-password">Confirm New Password</label>
                        <input type="password" id="confirm-password" name="confirm_password" required minlength="8">
                    </div>
                    <button type="submit" name="reset_password" class="login-button">
                        <i class="fas fa-save"></i> Reset Password
                    </button>
                </form>

            <?php elseif ($step == 'complete'): ?>
                <h2><i class="fas fa-check-circle"></i> All set!</h2>
                <div class="auth-alert auth-alert--success">
                    <i class="fas fa-check-circle"></i>
                    <div><?= htmlspecialchars($success_message) ?></div>
                </div>
                <a href="login.php" class="login-button">
                    <i class="fas fa-sign-in-alt"></i> Go to Login
                </a>
            <?php endif; ?>

            <div class="login-link">
                <a href="login.php"><i class="fas fa-arrow-left"></i> Back to Login</a>
            </div>
        </div>

        <div class="side-section">
            <img src="images/food.jpg" alt="Healthy food">
            <div class="side-tagline">
                <h3>Back in no time.</h3>
                <p>Reset your password and pick your streak up right where you left off.</p>
            </div>
        </div>
    </div>

    <script>
        function checkPasswordRequirements(password) {
            const toggle = (id, ok) => {
                const el = document.getElementById(id);
                if (el) el.classList.toggle('met', ok);
            };
            toggle('length-req', password.length >= 8);
            toggle('upper-req', /[A-Z]/.test(password));
            toggle('lower-req', /[a-z]/.test(password));
            toggle('number-req', /[0-9]/.test(password));
            toggle('special-req', /[^a-zA-Z0-9]/.test(password));
        }
    </script>
</body>
</html>