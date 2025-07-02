<?php
require_once __DIR__ . '/include/init.php';
require_once __DIR__ . '/include/db_config.php';
require_once __DIR__ . '/include/handlers/captcha.php';
require_once __DIR__ . '/include/handlers/user_signup.php';

if ($isLoggedIn) {
    header("Location: index.php");
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sign Up - BitBalance</title>
    <link rel="stylesheet" href="css/style.css">
    <link rel="stylesheet" href="css/login.css">
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

        .captcha-section {
            background: #f5f5f5;
            padding: 15px;
            border-radius: 5px;
            margin: 15px 0;
            text-align: center;
        }

        .captcha-question {
            font-size: 18px;
            font-weight: bold;
            color: #333;
            margin-bottom: 10px;
        }

        .captcha-input {
            width: 100px;
            text-align: center;
            font-size: 16px;
            padding: 8px;
            border: 2px solid #ddd;
            border-radius: 4px;
        }

        .refresh-captcha {
            margin-left: 10px;
            background: #007bff;
            color: white;
            border: none;
            padding: 8px 12px;
            border-radius: 4px;
            cursor: pointer;
        }

        .refresh-captcha:hover {
            background: #0056b3;
        }

        /* Terms and Conditions Styling */
        .terms-checkbox {
            margin: 15px 0;
            display: flex;
            align-items: flex-start;
            gap: 10px;
            font-size: 14px;
            color: #333;
        }

        .terms-checkbox input[type="checkbox"] {
            width: 16px;
            height: 16px;
            flex-shrink: 0;
            margin-top: 2px;
            accent-color: #007bff;
        }

        .terms-checkbox label {
            cursor: pointer;
            line-height: 1.4;
        }

        .terms-checkbox a {
            color: #007bff;
            text-decoration: none;
        }

        .terms-checkbox a:hover {
            text-decoration: underline;
        }

        .login-button:disabled {
            background-color: #6c757d !important;
            cursor: not-allowed !important;
            opacity: 0.6 !important;
        }

        .login-button:disabled:hover {
            background-color: #6c757d !important;
        }

        @media (max-width: 768px) {
    body {
        padding-top: 70px;
    }
    
    .container {
        margin-top: 20px;
        flex-direction: column;
    }
    
    .form-section {
        width: 100%;
        max-width: none;
    }
    

    .side-section {
        display: none;
    }
    
    
    
    .form-section {
        width: 100%;
        max-width: none;
    }
}
    </style>
</head>

<body>
    <?php include 'views/header.php'; ?>

    <div class="container">
        <div class="form-section">
            <h2>Sign Up To BitBalance</h2>

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

            <form action="signup.php" method="POST">
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

                <!-- CAPTCHA Section -->
                <div class="captcha-section">
                    <div class="captcha-question">
                        <i class="fas fa-robot" style="margin-right: 8px;"></i>
                        Solve this math problem: <?php echo htmlspecialchars($captcha_question); ?>
                    </div>
                    <input type="number" name="captcha_answer" class="captcha-input" placeholder="Answer" required>
                    <button type="button" class="refresh-captcha" onclick="refreshCaptcha()">
                        <i class="fas fa-sync-alt"></i> New Problem
                    </button>
                </div>

                <!-- Terms and Conditions Checkbox -->
                <div class="terms-checkbox">
                    <input type="checkbox" name="accept_terms" id="accept_terms" required>
                    <label for="accept_terms">
                        I agree to the
                        <a href="terms.php" target="_blank">Terms and Conditions</a>
                        <i class="fas fa-external-link-alt"
                            style="font-size: 10px; margin-left: 3px; opacity: 0.7;"></i>
                    </label>
                </div>

                <button type="submit" class="login-button" id="signup-btn" disabled>Create Account</button>

                <div class="signup-link">
                    <p>Already have an account? <a href="login.php">Sign In</a></p>
                </div>
            </form>
        </div>
        <div class="side-section">
            <img src="images/food.jpg" alt="Food Image">
        </div>
    </div>

    <script>
        // Terms checkbox functionality
        document.addEventListener('DOMContentLoaded', function () {
            const termsCheckbox = document.getElementById('accept_terms');
            const signupBtn = document.getElementById('signup-btn');

            if (termsCheckbox && signupBtn) {
                termsCheckbox.addEventListener('change', function () {
                    signupBtn.disabled = !this.checked;
                    if (this.checked) {
                        signupBtn.style.opacity = '1';
                        signupBtn.style.cursor = 'pointer';
                    } else {
                        signupBtn.style.opacity = '0.6';
                        signupBtn.style.cursor = 'not-allowed';
                    }
                });

                // Initial state
                signupBtn.style.opacity = '0.6';
                signupBtn.style.cursor = 'not-allowed';
            }
        });

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

        // Refresh CAPTCHA function
        function refreshCaptcha() {
            window.location.reload();
        }

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