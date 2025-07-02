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
                    $success_message = "Hello " . htmlspecialchars($user['first_name']) . "!<br><br>In a real application, an email would be sent to your address with a reset link.<br><br>For demo purposes, here's your reset link:<br><a href='$reset_link' style='color: #4a7ee3;'>$reset_link</a><br><br><strong>This link expires in 1 hour.</strong>";
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
<html lang="en" data-theme="<?php echo isset($_SESSION['user']) ? ($_SESSION['user']['theme_preference'] ?? 'light') : 'light'; ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reset Password - BitBalance</title>
    <link rel="stylesheet" href="css/style.css">
    <script src="https://kit.fontawesome.com/b94f65ead2.js" crossorigin="anonymous"></script>
    <style>
        /* Light Theme Variables */
        :root {
            --bg-color: #f8f9fa;
            --card-bg: #ffffff;
            --text-color: #212529;
            --text-muted: #6c757d;
            --border-color: #e9ecef;
            --primary-color: #4a7ee3;
            --shadow: 0 4px 20px rgba(0,0,0,0.08);
        }
        
        /* Dark Theme Variables */
        [data-theme="dark"] {
            --bg-color: #1a1a1a;
            --card-bg: #2d2d2d;
            --text-color: #ffffff;
            --text-muted: #adb5bd;
            --border-color: #495057;
            --primary-color: #4a7ee3;
            --shadow: 0 4px 20px rgba(0,0,0,0.3);
        }
        
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: var(--bg-color);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0;
            color: var(--text-color);
            transition: background-color 0.3s ease, color 0.3s ease;
        }
        
        .reset-container {
            background: var(--card-bg);
            padding: 40px;
            border-radius: 20px;
            box-shadow: var(--shadow);
            width: 100%;
            max-width: 500px;
            text-align: center;
            border: 1px solid var(--border-color);
        }
        
        .reset-container h1 {
            color: var(--text-color);
            margin-bottom: 10px;
            font-size: 28px;
        }
        
        .reset-container p {
            color: var(--text-muted);
            margin-bottom: 30px;
        }
        
        .form-group {
            margin-bottom: 20px;
            text-align: left;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 500;
            color: var(--text-color);
        }
        
        .form-group input {
            width: 100%;
            padding: 12px 16px;
            border: 2px solid var(--border-color);
            border-radius: 8px;
            font-size: 14px;
            transition: border-color 0.2s ease;
            box-sizing: border-box;
            background: var(--card-bg);
            color: var(--text-color);
        }
        
        .form-group input:focus {
            outline: none;
            border-color: var(--primary-color);
        }
        
        .btn-primary {
            background: var(--primary-color);
            color: white;
            border: none;
            padding: 12px 24px;
            border-radius: 8px;
            font-weight: 500;
            cursor: pointer;
            width: 100%;
            font-size: 16px;
            transition: background 0.2s ease;
        }
        
        .btn-primary:hover {
            background: #3b6bd6;
        }
        
        .success-message {
            background: #d4edda;
            color: #155724;
            padding: 12px 16px;
            border-radius: 8px;
            margin-bottom: 20px;
            border: 1px solid #c3e6cb;
            text-align: left;
        }
        
        .error-message {
            background: #f8d7da;
            color: #721c24;
            padding: 12px 16px;
            border-radius: 8px;
            margin-bottom: 20px;
            border: 1px solid #f5c6cb;
            text-align: left;
        }
        
        [data-theme="dark"] .success-message {
            background: #1e4d2b;
            color: #a3d9a5;
            border-color: #2d5f34;
        }
        
        [data-theme="dark"] .error-message {
            background: #4a1e24;
            color: #f1aeb5;
            border-color: #5c2b30;
        }
        
        .login-link {
            margin-top: 20px;
            color: var(--text-muted);
        }
        
        .login-link a {
            color: var(--primary-color);
            text-decoration: none;
        }
        
        .login-link a:hover {
            text-decoration: underline;
        }
        
        .password-requirements {
            background: var(--bg-color);
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            text-align: left;
            border: 1px solid var(--border-color);
        }
        
        .password-requirements h4 {
            margin: 0 0 10px 0;
            color: var(--text-color);
            font-size: 14px;
        }
        
        .password-requirements ul {
            margin: 0;
            padding-left: 20px;
        }
        
        .password-requirements li {
            font-size: 12px;
            color: var(--text-muted);
            margin-bottom: 5px;
        }
    </style>
</head>
<body>
    <div class="reset-container">
        <?php if ($step == 'request'): ?>
            <h1><i class="fas fa-key"></i> Reset Password</h1>
            <p>Enter your email address and we'll send you a link to reset your password.</p>
            
            <?php if (!empty($error_message)): ?>
                <div class="error-message"><i class="fas fa-exclamation-triangle"></i> <?= $error_message ?></div>
            <?php endif; ?>
            
            <?php if (!empty($success_message)): ?>
                <div class="success-message"><i class="fas fa-check-circle"></i> <?= $success_message ?></div>
            <?php endif; ?>
            
            <form method="POST">
                <div class="form-group">
                    <label>Email Address</label>
                    <input type="email" name="email" required placeholder="Enter your email address">
                </div>
                <button type="submit" name="request_reset" class="btn-primary">
                    <i class="fas fa-paper-plane"></i> Send Reset Link
                </button>
            </form>
            
        <?php elseif ($step == 'reset'): ?>
            <h1><i class="fas fa-lock"></i> Set New Password</h1>
            <p>Enter your new password below.</p>
            
            <?php if (!empty($error_message)): ?>
                <div class="error-message"><i class="fas fa-exclamation-triangle"></i> <?= $error_message ?></div>
            <?php endif; ?>
            
            <div class="password-requirements">
                <h4>Password Requirements:</h4>
                <ul>
                    <li id="length-req">At least 8 characters</li>
                    <li id="upper-req">One uppercase letter (A-Z)</li>
                    <li id="lower-req">One lowercase letter (a-z)</li>
                    <li id="number-req">One number (0-9)</li>
                    <li id="special-req">One special character (!@#$%^&*)</li>
                </ul>
            </div>
            
            <form method="POST">
                <input type="hidden" name="token" value="<?= htmlspecialchars($token) ?>">
                <div class="form-group">
                    <label>New Password</label>
                    <input type="password" name="new_password" required minlength="8" onkeyup="checkPasswordRequirements(this.value)">
                </div>
                <div class="form-group">
                    <label>Confirm New Password</label>
                    <input type="password" name="confirm_password" required minlength="8">
                </div>
                <button type="submit" name="reset_password" class="btn-primary">
                    <i class="fas fa-save"></i> Reset Password
                </button>
            </form>
            
        <?php elseif ($step == 'complete'): ?>
            <h1><i class="fas fa-check-circle"></i> Password Reset Complete</h1>
            <div class="success-message"><?= htmlspecialchars($success_message) ?></div>
            <a href="login.php" class="btn-primary" style="display: inline-block; text-decoration: none;">
                <i class="fas fa-sign-in-alt"></i> Go to Login
            </a>
        <?php endif; ?>
        
        <div class="login-link">
            <a href="login.php"><i class="fas fa-arrow-left"></i> Back to Login</a>
        </div>
    </div>
    
    <script>
        function checkPasswordRequirements(password) {
            if (!password) return;
            
            document.getElementById('length-req').style.color = password.length >= 8 ? 'green' : 'red';
            document.getElementById('upper-req').style.color = /[A-Z]/.test(password) ? 'green' : 'red';
            document.getElementById('lower-req').style.color = /[a-z]/.test(password) ? 'green' : 'red';
            document.getElementById('number-req').style.color = /[0-9]/.test(password) ? 'green' : 'red';
            document.getElementById('special-req').style.color = /[^a-zA-Z0-9]/.test(password) ? 'green' : 'red';
        }
    </script>
</body>
</html>