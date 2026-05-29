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
$bodyClass = 'page-set-goal';

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
<html lang="<?= html_lang_attr() ?>" data-theme="<?php echo isset($_SESSION['user']) ? ($_SESSION['user']['theme_preference'] ?? 'system') : 'system'; ?>">
<head>
    <meta charset="UTF-8">
    <title><?= t('setgoal.title_tag') ?></title>
    <?php
    $pageComponents = ['fab'];
    $pageCss = ['css/pages/set-goal.css'];
    include PROJECT_ROOT . 'views/head_css.php';
    ?>
    <script src="https://kit.fontawesome.com/b94f65ead2.js" crossorigin="anonymous"></script>
</head>
<body class="<?= htmlspecialchars($bodyClass ?? '', ENT_QUOTES) ?>">
    <?php include PROJECT_ROOT . 'views/header.php'; ?>

    <div class="container" style="max-width: 400px; margin: 40px auto;">
        <h2><?= t('setgoal.page_title') ?></h2>
        <?php if ($error_message): ?>
            <div class="alert alert-danger"><?= htmlspecialchars($error_message) ?></div>
        <?php endif; ?>
        <form method="POST">
            <label for="calorie_goal"><?= t('setgoal.calorie_label') ?></label>
            <input type="number" min="800" max="10000" name="calorie_goal" id="calorie_goal" required>
            <button type="submit" class="btn-primary"><?= t('setgoal.submit') ?></button>
        </form>
        <?php if ($success_message): ?>
            <div class="alert alert-success"><?= htmlspecialchars($success_message) ?></div>
        <?php endif; ?>
    </div>

    <?php if ($isLoggedIn): include PROJECT_ROOT . 'dashboard/views/quick-log-fab.php'; endif; ?>

    <?php include PROJECT_ROOT . 'views/footer.php'; ?>
</body>
</html>