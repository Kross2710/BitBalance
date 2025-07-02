<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/../include/init.php';
require_once __DIR__ . '/../include/db_config.php';
require_once __DIR__ . '/handlers/add_user.php';

if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'admin') {
    http_response_code(403);
    exit('Access denied');
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Creation</title>
    <link rel="stylesheet" href="../css/style.css">
    <link rel="stylesheet" href="../css/login.css">
    <script src="https://kit.fontawesome.com/b94f65ead2.js" crossorigin="anonymous"></script>
    <style>
        .form-row {
            display: flex;
            gap: 10px;
        }

        .form-row input {
            flex: 1;
        }

        .password-requirements {
            font-size: 12px;
            color: #666;
            margin-top: 5px;
        }

        /* Disabled button styling */
        .login-button:disabled {
            background-color: #6c757d !important;
            cursor: not-allowed !important;
            opacity: 0.6 !important;
        }

        .login-button:disabled:hover {
            background-color: #6c757d !important;
        }

        select {
            font-size: 14px;
            padding: 10px;
            border: 1px solid #333;
            border-radius: 4px;
        }
    </style>
</head>

<body>
    <div class="container">
        <div class="form-section">
            <h2>User Creation</h2>

            <?php if (!empty($error_message)): ?>

                <div class="error-message"
                    style="color: #d32f2f; margin-bottom: 15px; padding: 12px; background-color: #ffebee; border: 1px solid #e57373; border-radius: 5px; font-weight: bold;">
                    <i class="fas fa-exclamation-triangle" style="margin-right: 8px;"></i>

                    <?php echo htmlspecialchars($error_message); ?>
                </div>
            <?php endif; ?>

            <?php if (!empty($success_message)): ?>

                <div class="success-message"
                    style="color: #2e7d32; margin-bottom: 15px; padding: 12px; background-color: #e8f5e8; border: 1px solid #4caf50; border-radius: 5px; font-weight: bold;">
                    <i class="fas fa-check-circle" style="margin-right: 8px;"></i>

                    <?php echo htmlspecialchars($success_message); ?>
                </div>
            <?php endif; ?>

            <form action="add-user.php" method="POST">
                <div class="form-row">
                    <input type="text" placeholder="First Name" name="first_name" required
                        value="<?php echo isset($_POST['first_name']) ? htmlspecialchars($_POST['first_name']) : ''; ?>">
                    <input type="text" placeholder="Last Name" name="last_name" required
                        value="<?php echo isset($_POST['last_name']) ? htmlspecialchars($_POST['last_name']) : ''; ?>">
                </div>

                <input type="text" placeholder="Username" name="username" required
                    value="<?php echo isset($_POST['username']) ? htmlspecialchars($_POST['username']) : ''; ?>">

                <input type="email" placeholder="Email" name="email" required
                    value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>">

                <input type="password" placeholder="Password" name="password" required>

                <div class="password-requirements">
                    Password must be at least 8 characters long and contain at least one uppercase letter, one lowercase
                    letter, and one number.
                </div>

                <input type="password" placeholder="Confirm Password" name="confirm_password" required>

                <select name="role" required>
                    <option value="" disabled selected>Select Role</option>
                    <option value="admin">Admin</option>
                    <option value="regular">User</option>
                </select>

                <button type="submit" class="login-button" id="signup-btn">Create Account</button>

            </form>
        </div>
        <div class="side-section">
            <img src="../images/food.jpg" alt="Food Image">
        </div>
    </div>

    <script>
        // Client-side password confirmation validation
        document.addEventListener('DOMContentLoaded', function () {
            const password = document.querySelector('input[name="password"]');
            const confirmPassword = document.querySelector('input[name="confirm_password"]');

            function validatePasswords() {
                if (password.value !== confirmPassword.value) {
                    confirmPassword.setCustomValidity('Passwords do not match');
                } else {
                    confirmPassword.setCustomValidity('');
                }
            }

            password.addEventListener('input', validatePasswords);
            confirmPassword.addEventListener('input', validatePasswords);
        });

        // Password strength indicator
        document.querySelector('input[name="password"]').addEventListener('input', function () {
            const password = this.value;
            const requirements = document.querySelector('.password-requirements');

            let strength = 0;
            let feedback = [];

            if (password.length >= 8) strength++;
            else feedback.push('at least 8 characters');

            if (/[a-z]/.test(password)) strength++;
            else feedback.push('lowercase letter');

            if (/[A-Z]/.test(password)) strength++;
            else feedback.push('uppercase letter');

            if (/\d/.test(password)) strength++;
            else feedback.push('number');

            if (strength === 4) {
                requirements.style.color = 'green';
                requirements.innerHTML = '<i class="fas fa-check"></i> Password meets all requirements';
            } else {
                requirements.style.color = '#666';
                requirements.innerHTML = 'Password needs: ' + feedback.join(', ');
            }
        });
    </script>
</body>

</html>