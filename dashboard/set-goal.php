<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Include the initialization file
require_once __DIR__ . '/../include/init.php';
// Include the database configuration (only when needed, instead of including it in init.php to save resources)
require_once __DIR__ . '/../include/db_config.php';

if (!isset($_SESSION['user'])) {
    header("Location: login.php");
    exit();
}

$userId = $_SESSION['user']['user_id'];
$error_message = '';
$success_message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $goal = filter_input(INPUT_POST, 'calorie_goal', FILTER_VALIDATE_INT, [
        "options" => ["min_range" => 800, "max_range" => 10000]
    ]);
    if ($goal === false || $goal === null) {
        $error_message = "Please enter a valid calorie goal (800–10,000).";
        // Redirect back to the form with error message
        header("Location: dashboard.php?error=" . urlencode($error_message));
        exit();
    } else {
        // Insert new goal into userGoal table
        $stmt = $pdo->prepare("INSERT INTO userGoal (user_id, calorie_goal, date_set) VALUES (?, ?, NOW())");
        $stmt->execute([$userId, $goal]);
        $success_message = "Calorie goal updated!";
        // Redirect back to dashboard
        header("Location: dashboard.php?success=" . urlencode($success_message));
        exit();
    }
}
?>

<!DOCTYPE html>
<html lang="en" data-theme="<?php echo isset($_SESSION['user']) ? ($_SESSION['user']['theme_preference'] ?? 'light') : 'light'; ?>">
<head>
    <meta charset="UTF-8">
    <title>Set My Calorie Goal</title>
    <link rel="stylesheet" href="../css/style.css">
    <script src="https://kit.fontawesome.com/b94f65ead2.js" crossorigin="anonymous"></script>
</head>
<body>
    <?php include PROJECT_ROOT . 'views/header.php'; ?>

    <div class="container" style="max-width: 400px; margin: 40px auto;">
        <h2>Set Your Daily Calorie Goal</h2>
        <?php if ($error_message): ?>
            <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>
        <form method="POST">
            <label for="calorie_goal">Calorie Goal (kcal):</label>
            <input type="number" min="800" max="10000" name="calorie_goal" id="calorie_goal" required>
            <button type="submit" class="btn-primary">Save Goal</button>
        </form>
        <?php if ($success_message): ?>
            <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
        <?php endif; ?>
    </div>

    <?php include PROJECT_ROOT . 'views/footer.php'; ?>
</body>
</html>

<style>
body {
    background-color: #f8f9fa;
    color: #212529;
    min-height: 100vh;
    font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
}

html {
    background-color: #f8f9fa;
}

.container {
    background-color: #ffffff;
    border: 1px solid #e9ecef;
    border-radius: 10px;
    padding: 30px;
    box-shadow: 0 4px 20px rgba(0,0,0,0.08);
}

h2 {
    color: #212529;
    text-align: center;
    margin-bottom: 25px;
    font-weight: 600;
}

label {
    color: #212529;
    font-weight: 500;
    margin-bottom: 8px;
    display: block;
}

input[type="number"] {
    background-color: #ffffff;
    border: 2px solid #e9ecef;
    color: #212529;
    padding: 12px 16px;
    border-radius: 8px;
    width: 100%;
    box-sizing: border-box;
    font-size: 16px;
    margin-bottom: 20px;
    transition: border-color 0.2s ease;
}

input[type="number"]:focus {
    border-color: #4a7ee3;
    outline: none;
}

.btn-primary {
    background-color: #4a7ee3;
    color: white;
    border: none;
    padding: 12px 24px;
    border-radius: 8px;
    font-weight: 500;
    cursor: pointer;
    width: 100%;
    font-size: 16px;
    transition: background-color 0.2s ease;
}

.btn-primary:hover {
    background-color: #3b6bd6;
}

.alert {
    padding: 12px 16px;
    border-radius: 8px;
    margin-bottom: 20px;
    border: 1px solid;
}

.alert-danger {
    background-color: #f8d7da;
    color: #721c24;
    border-color: #f5c6cb;
}

.alert-success {
    background-color: #d4edda;
    color: #155724;
    border-color: #c3e6cb;
}

form {
    margin-top: 20px;
}

input::placeholder {
    color: #6c757d;
}


input[type="number"]::-webkit-outer-spin-button,
input[type="number"]::-webkit-inner-spin-button {
    -webkit-appearance: none;
    margin: 0;
}

input[type="number"] {
    -moz-appearance: textfield;
}
    

/* Dark Mode Styles for Set Goal Page */
[data-theme="dark"] body {
    background-color: #1a1a1a !important;
    color: #ffffff !important;
}

[data-theme="dark"] .container {
    background-color: #2d2d2d !important;
    border: 1px solid #495057 !important;
    border-radius: 10px !important;
    padding: 30px !important;
    box-shadow: 0 4px 20px rgba(0,0,0,0.3) !important;
}

[data-theme="dark"] h2 {
    color: #ffffff !important;
    text-align: center !important;
    margin-bottom: 25px !important;
}

[data-theme="dark"] label {
    color: #ffffff !important;
    font-weight: 500 !important;
    margin-bottom: 8px !important;
    display: block !important;
}

[data-theme="dark"] input[type="number"] {
    background-color: #2d2d2d !important;
    border: 2px solid #495057 !important;
    color: #ffffff !important;
    padding: 12px 16px !important;
    border-radius: 8px !important;
    width: 100% !important;
    box-sizing: border-box !important;
    font-size: 16px !important;
    margin-bottom: 20px !important;
}

[data-theme="dark"] input[type="number"]:focus {
    border-color: #4a7ee3 !important;
    outline: none !important;
}

[data-theme="dark"] .btn-primary {
    background-color: #4a7ee3 !important;
    color: white !important;
    border: none !important;
    padding: 12px 24px !important;
    border-radius: 8px !important;
    font-weight: 500 !important;
    cursor: pointer !important;
    width: 100% !important;
    font-size: 16px !important;
}

[data-theme="dark"] .btn-primary:hover {
    background-color: #3b6bd6 !important;
}

[data-theme="dark"] .alert {
    padding: 12px 16px !important;
    border-radius: 8px !important;
    margin-bottom: 20px !important;
    border: 1px solid !important;
}

[data-theme="dark"] .alert-danger {
    background-color: #4a1e24 !important;
    color: #f1aeb5 !important;
    border-color: #5c2b30 !important;
}

[data-theme="dark"] .alert-success {
    background-color: #1e4d2b !important;
    color: #a3d9a5 !important;
    border-color: #2d5f34 !important;
}


[data-theme="dark"] form {
    margin-top: 20px !important;
}

[data-theme="dark"] input::placeholder {
    color: #adb5bd !important;
}

[data-theme="dark"] input[type="number"]::-webkit-outer-spin-button,
[data-theme="dark"] input[type="number"]::-webkit-inner-spin-button {
    -webkit-appearance: none !important;
    margin: 0 !important;
}

[data-theme="dark"] input[type="number"] {
    -moz-appearance: textfield !important;
}

[data-theme="dark"] html {
    background-color: #1a1a1a !important;
}

[data-theme="dark"] body {
    background-color: #1a1a1a !important;
    color: #ffffff !important;
    min-height: 100vh !important;
}

[data-theme="dark"] * {
    background-color: inherit;
}

[data-theme="dark"] .page-wrapper,
[data-theme="dark"] .main-content,
[data-theme="dark"] main {
    background-color: #1a1a1a !important;
}

[data-theme="dark"] footer {
    background-color: #1a1a1a !important;
    color: #adb5bd !important;
}
</style>