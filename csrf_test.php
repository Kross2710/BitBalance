<?php
// Start session
session_start();

// Generate CSRF token if not exist
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$csrfToken = $_SESSION['csrf_token'];

$message = "";

// Check form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    if (!isset($_POST['csrf_token']) || 
        !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {

        $message = "❌ Invalid CSRF token - Possible CSRF attack!";
    } else {
        $message = "✅ CSRF token valid. Form submitted successfully!";
    }
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>CSRF Token Test</title>
</head>
<body>

<h2>CSRF Token Test Form</h2>

<?php if ($message): ?>
<p><strong><?php echo $message; ?></strong></p>
<?php endif; ?>

<form method="POST">

    <label>Username:</label>
    <input type="text" name="username" required>

    <br><br>

    <!-- CSRF token -->
    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken); ?>">

    <button type="submit">Submit</button>

</form>

</body>
</html>