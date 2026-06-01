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
    <?php
    $pageCss = ['css/login.css', 'css/pages/signup.css'];
    include PROJECT_ROOT . 'views/head_css.php';
    ?>
    <script src="https://kit.fontawesome.com/b94f65ead2.js" crossorigin="anonymous"></script>
</head>

<body>
    <?php include 'views/header.php'; ?>

    <div class="container">
        <div class="form-section">
            <h2>Create your account</h2>
            <p class="auth-subtitle">Start your first streak in under a minute.</p>

            <?php if (!empty($error_message)): ?>
                <div class="auth-alert auth-alert--error">
                    <i class="fas fa-exclamation-triangle"></i>
                    <div><?php echo htmlspecialchars($error_message); ?></div>
                </div>
            <?php endif; ?>

            <?php if (!empty($success_message)): ?>
                <div class="auth-alert auth-alert--success">
                    <i class="fas fa-check-circle"></i>
                    <div><?php echo htmlspecialchars($success_message); ?></div>
                </div>
            <?php endif; ?>

            <form action="signup.php" method="POST" class="js-submit-lock">
                <div class="form-row">
                    <input type="text" placeholder="First Name" name="first_name" required
                        value="<?php echo isset($_POST['first_name']) ? htmlspecialchars($_POST['first_name']) : ''; ?>">
                    <input type="text" placeholder="Last Name" name="last_name" required
                        value="<?php echo isset($_POST['last_name']) ? htmlspecialchars($_POST['last_name']) : ''; ?>">
                </div>

                <input type="email" placeholder="Email" name="email" required
                    value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>">

                <div class="password-field">
                    <input type="password" placeholder="Password" name="password" required
                        value="<?php echo isset($_POST['password']) ? htmlspecialchars($_POST['password']) : ''; ?>">
                    <button type="button" class="password-toggle" aria-label="Show password" aria-pressed="false">
                        <i class="fas fa-eye" aria-hidden="true"></i>
                    </button>
                </div>

                <div class="password-requirements">
                    Password must be at least 8 characters long and contain at least one uppercase letter, one lowercase
                    letter, and one number.
                </div>

                <div class="password-field">
                    <input type="password" placeholder="Confirm Password" name="confirm_password" required
                        value="<?php echo isset($_POST['confirm_password']) ? htmlspecialchars($_POST['confirm_password']) : ''; ?>">
                    <button type="button" class="password-toggle" aria-label="Show password" aria-pressed="false">
                        <i class="fas fa-eye" aria-hidden="true"></i>
                    </button>
                </div>

                <!-- CAPTCHA Section -->
                <div class="captcha-section">
                    <div class="captcha-question">
                        <i class="fas fa-robot"></i>
                        Solve this math problem: <?php echo htmlspecialchars($captcha_question); ?>
                    </div>
                    <input type="number" name="captcha_answer" class="captcha-input" placeholder="Answer" required>
                    <button type="button" class="refresh-captcha" onclick="refreshCaptcha(this)">
                        <i class="fas fa-sync-alt"></i> New Problem
                    </button>
                </div>

                <!-- Terms and Conditions Checkbox -->
                <div class="terms-checkbox">
                    <input type="checkbox" name="accept_terms" id="accept_terms" required>
                    <label for="accept_terms">
                        I agree to the
                        <a href="terms.php" target="_blank">Terms and Conditions
                            <i class="fas fa-external-link-alt"></i></a>
                    </label>
                </div>

                <button type="submit" class="login-button" id="signup-btn" disabled>Create Account</button>

                <div class="signup-link">
                    <p>Already have an account? <a href="login.php">Sign In</a></p>
                </div>
            </form>
        </div>
        <div class="side-section">
            <img src="images/food.jpg" alt="Healthy food">
            <div class="side-tagline">
                <h3>Join the crew.</h3>
                <p>Log meals, earn XP, and climb the leaderboard with friends.</p>
            </div>
        </div>
    </div>

    <script>
        // Terms checkbox functionality
        document.addEventListener('DOMContentLoaded', function () {
            const termsCheckbox = document.getElementById('accept_terms');
            const signupBtn = document.getElementById('signup-btn');

            if (termsCheckbox && signupBtn) {
                // Button appearance is driven entirely by the :disabled CSS state.
                termsCheckbox.addEventListener('change', function () {
                    signupBtn.disabled = !this.checked;
                });
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

            // Re-run match check on load in case the server preserved both
            // password fields after a validation error (e.g. wrong captcha).
            validatePasswords();
        });

        // Show / hide password toggle (one button per password field).
        document.addEventListener('DOMContentLoaded', function () {
            document.querySelectorAll('.password-toggle').forEach(function (btn) {
                btn.addEventListener('click', function () {
                    const input = btn.parentElement.querySelector('input');
                    const icon = btn.querySelector('i');
                    const reveal = input.type === 'password';
                    input.type = reveal ? 'text' : 'password';
                    btn.setAttribute('aria-pressed', reveal ? 'true' : 'false');
                    btn.setAttribute('aria-label', reveal ? 'Hide password' : 'Show password');
                    icon.classList.toggle('fa-eye', !reveal);
                    icon.classList.toggle('fa-eye-slash', reveal);
                });
            });
        });

        // Refresh CAPTCHA without reloading the page (preserves everything the
        // user already typed). Falls back gracefully if the request fails.
        function refreshCaptcha(btn) {
            const q = document.querySelector('.captcha-question');
            const input = document.querySelector('.captcha-input');
            if (btn) btn.disabled = true;
            fetch('include/handlers/captcha_refresh.php', { headers: { 'X-Requested-With': 'fetch' } })
                .then(r => r.json())
                .then(d => {
                    if (d && d.ok && d.question && q) {
                        q.innerHTML = '<i class="fas fa-robot"></i> Solve this math problem: ';
                        q.appendChild(document.createTextNode(d.question));
                        if (input) { input.value = ''; input.focus(); }
                    }
                })
                .catch(() => {
                    if (window.showToast) showToast('Could not refresh — please try again.', { type: 'error' });
                })
                .finally(() => { if (btn) btn.disabled = false; });
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
                requirements.classList.add('is-valid');
                requirements.innerHTML = '<i class="fas fa-check"></i> Password meets all requirements';
            } else {
                requirements.classList.remove('is-valid');
                requirements.innerHTML = 'Password needs: ' + feedback.join(', ');
            }
        });
    </script>
</body>

</html>