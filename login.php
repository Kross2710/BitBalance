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
            <h2>Sign In To BitBalance</h2>

            <?php
            // Detect locked-account error so we can offer an immediate recovery path
            // (reset_password.php clears failed_attempts + locked_until on success).
            $isLocked = !empty($error_message) && stripos($error_message, 'locked') !== false;
            ?>

            <?php if (!empty($error_message)): ?>
                <div class="error-message"
                    style="color: #d32f2f; margin-bottom: 15px; padding: 12px; background-color: #ffebee; border: 1px solid #e57373; border-radius: 5px; font-weight: bold;">
                    <i class="fas fa-exclamation-triangle" style="margin-right: 8px;"></i>
                    <?php echo htmlspecialchars($error_message); ?>

                    <?php if ($isLocked): ?>
                        <div style="font-weight: 400; margin-top: 10px; padding-top: 10px; border-top: 1px solid #e57373;">
                            Don't want to wait? <a href="reset_password.php"
                                style="color: #d32f2f; font-weight: 700; text-decoration: underline;">
                                Reset your password to unlock instantly &rarr;
                            </a>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endif; ?>

            <form action="login.php" method="POST">
                <input type="email" placeholder="Email" name="email" required
                    value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>">
                <input type="password" placeholder="Password" name="password" required>
                <button type="submit" class="login-button">Login</button>

                <div class="forgot-link" style="text-align: center; margin-top: 10px; font-size: 0.9rem;">
                    <a href="reset_password.php" style="color: #4a7ee3; text-decoration: none;">Forgot password?</a>
                </div>

                <div class="signup-link">
                    <p>Don't have an account? <a href="signup.php">Sign Up</a></p>
                </div>

            </form>
        </div>
        <div class="side-section">
            <img src="images/food.jpg" alt="Food Image">
        </div>
    </div>
</body>

</html>