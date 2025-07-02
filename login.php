<?php
require_once __DIR__ . '/include/init.php';
require_once __DIR__ . '/include/db_config.php';
require_once __DIR__ . '/include/handlers/user_login.php';

if (isset($_SESSION['user'])) {
    header("Location: index.php");
    exit();
}

if (isset($_GET['error'])) {
    $error_message = htmlspecialchars($_GET['error']);
} else {
    $error_message = '';
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sign In</title>
    <link rel="stylesheet" href="css/style.css">
    <link rel="stylesheet" href="css/login.css">
    <script src="https://kit.fontawesome.com/b94f65ead2.js" crossorigin="anonymous"></script>
</head>

<body>
    <?php include 'views/header.php'; ?>

    <div class="container">
        <div class="form-section">
            <h2>Sign In To BitBalance</h2>

            <?php if (!empty($error_message)): ?>
                <div class="error-message"
                    style="color: #d32f2f; margin-bottom: 15px; padding: 12px; background-color: #ffebee; border: 1px solid #e57373; border-radius: 5px; font-weight: bold;">
                    <i class="fas fa-exclamation-triangle" style="margin-right: 8px;"></i>
                    <?php echo htmlspecialchars($error_message); ?>
                </div>
            <?php endif; ?>

            <form action="login.php" method="POST">
                <input type="email" placeholder="Email" name="email" required
                    value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>">
                <input type="password" placeholder="Password" name="password" required>
                <button type="submit" class="login-button">Login</button>
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