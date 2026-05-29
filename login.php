<?php
require_once __DIR__ . '/include/init.php';
require_once __DIR__ . '/include/db_config.php';
require_once __DIR__ . '/include/handlers/user_login.php';

if (isset($_SESSION['user'])) {
    header("Location: index.php");
    exit();
}

// $error_message may have been set by user_login.php above (failed credentials,
// locked account, DB error, etc). Only initialize to '' if nothing set it yet —
// otherwise the form re-renders silently and hides the real cause.
if (isset($_GET['error'])) {
    $error_message = htmlspecialchars($_GET['error']);
} elseif (!isset($error_message)) {
    $error_message = '';
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sign In</title>
    <?php
    $pageCss = ['css/login.css'];
    include PROJECT_ROOT . 'views/head_css.php';
    ?>
    <script src="https://kit.fontawesome.com/b94f65ead2.js" crossorigin="anonymous"></script>
</head>

<body>
    <?php include 'views/header.php'; ?>

    <div class="container">
        <div class="form-section">
            <h2>Welcome back</h2>
            <p class="auth-subtitle">Sign in to keep your streak alive.</p>

            <?php
            // Detect locked-account error so we can offer an immediate recovery path
            // (reset_password.php clears failed_attempts + locked_until on success).
            $isLocked = !empty($error_message) && stripos($error_message, 'locked') !== false;
            ?>

            <?php if (!empty($error_message)): ?>
                <div class="auth-alert auth-alert--error">
                    <i class="fas fa-exclamation-triangle"></i>
                    <div>
                        <?php echo htmlspecialchars($error_message); ?>
                        <?php if ($isLocked): ?>
                            <div class="auth-locked-recovery">
                                Don't want to wait?
                                <a href="reset_password.php">Reset your password to unlock instantly &rarr;</a>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endif; ?>

            <form action="login.php" method="POST">
                <input type="email" placeholder="Email" name="email" required
                    value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>">
                <input type="password" placeholder="Password" name="password" required>
                <button type="submit" class="login-button">Sign In</button>

                <div class="forgot-link">
                    <a href="reset_password.php">Forgot password?</a>
                </div>

                <div class="signup-link">
                    <p>Don't have an account? <a href="signup.php">Sign Up</a></p>
                </div>
            </form>
        </div>
        <div class="side-section">
            <img src="images/food.jpg" alt="Healthy food">
            <div class="side-tagline">
                <h3>Track. Earn XP. Level up.</h3>
                <p>Your wellness journey, gamified.</p>
            </div>
        </div>
    </div>
</body>

</html>