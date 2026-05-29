<?php
require_once __DIR__ . '/../include/init.php';
require_once __DIR__ . '/handlers/dashboard_data.php';
require_once __DIR__ . '/../include/handlers/log_attempt.php';

if ($isLoggedIn) {
    log_attempt($pdo, $user['user_id'], 'view', 'User ' . $user['user_id'] . ' clicked on dashboard calculator', 'dashboard', null);
}

$activePage = 'calculator';
$activeHeader = 'dashboard';
$bodyClass = 'page-calculator';
$displayUser = $isLoggedIn ? $user['user_name'] : "Guest";

if (!$isLoggedIn) {
    $userAge = 25;
    $userGender = 'male';
    $userWeight = 70;
    $userHeight = 175;
    $userGoal = 2200;
    $totalCalories = 1450;
}

$error_message = isset($_GET['error']) ? htmlspecialchars($_GET['error']) : '';
$success_message = isset($_GET['success']) ? htmlspecialchars($_GET['success']) : '';

$formAge = $calculatorResult['age'] ?? $userAge;
$formGender = $calculatorResult['gender'] ?? $userGender;
$formWeight = $calculatorResult['weight'] ?? $userWeight;
$formHeight = $calculatorResult['height'] ?? $userHeight;
$selectedActivity = $calculatorResult['activity_level'] ?? (!$isLoggedIn ? 'moderately_active' : '');
?>

<!DOCTYPE html>
<html lang="en" data-theme="<?php echo isset($_SESSION['user']) ? ($_SESSION['user']['theme_preference'] ?? 'system') : 'system'; ?>">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Calculator | BitBalance</title>
    <?php
    $pageComponents = ['sidebar', 'fab'];
    $pageCss = ['css/dashboard.css', 'css/pages/dashboard-calculator.css'];
    include PROJECT_ROOT . 'views/head_css.php';
    ?>

    <script src="https://kit.fontawesome.com/b94f65ead2.js" crossorigin="anonymous"></script>
</head>

<body class="<?= htmlspecialchars($bodyClass ?? '', ENT_QUOTES) ?>">
    <?php include PROJECT_ROOT . 'views/header.php'; ?>
    <?php include PROJECT_ROOT . 'dashboard/views/sidebar.php'; ?>
    <?php include PROJECT_ROOT . 'dashboard/views/right-sidebar.php'; ?>

    <main class="dashboard-content">
        <div class="calculator-container">

            <?php if (!$isLoggedIn): ?>
                <div class="demo-banner">
                    <i class="fas fa-flask"></i>
                    <span><strong>You're exploring a live demo.</strong> Calculator works without an account — create a free account to save goals and track progress.</span>
                    <a href="<?= BASE_URL ?>signup.php" class="demo-banner-cta">Get started free</a>
                </div>
            <?php endif; ?>

            <section class="calc-form-card">
                <div class="card-header">
                    <h3><i class="fas fa-calculator"></i> Calorie Calculator</h3>
                    <p class="subtitle">Calculate your TDEE & BMI instantly.</p>
                </div>

                <?php if (!empty($error_message)): ?>
                    <div class="alert error"><i class="fas fa-exclamation-triangle"></i> <?= $error_message ?></div>
                <?php endif; ?>

                <form action="handlers/process_calculator.php" method="POST" id="calcForm">
                    <div class="form-grid">
                        <div class="form-group">
                            <label for="age">Age</label>
                            <div class="input-icon-wrapper">
                                <i class="fas fa-birthday-cake input-icon"></i>
                                <input type="number" id="age" name="age" min="1" max="120" required value="<?= htmlspecialchars((string)$formAge); ?>" placeholder="Years">
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="gender">Gender</label>
                            <div class="input-icon-wrapper">
                                <i class="fas fa-venus-mars input-icon"></i>
                                <select id="gender" name="gender" required>
                                    <option value="">Select...</option>
                                    <option value="male" <?= $formGender === 'male' ? 'selected' : ''; ?>>Male</option>
                                    <option value="female" <?= $formGender === 'female' ? 'selected' : ''; ?>>Female</option>
                                </select>
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="weight">Weight (kg)</label>
                            <div class="input-icon-wrapper">
                                <i class="fas fa-weight input-icon"></i>
                                <input type="number" id="weight" name="weight" min="1" step="0.1" required value="<?= htmlspecialchars((string)$formWeight); ?>" placeholder="kg">
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="height">Height (cm)</label>
                            <div class="input-icon-wrapper">
                                <i class="fas fa-ruler-vertical input-icon"></i>
                                <input type="number" id="height" name="height" min="1" step="0.1" required value="<?= htmlspecialchars((string)$formHeight); ?>" placeholder="cm">
                            </div>
                        </div>
                    </div>

                    <div class="form-group full-width">
                        <label for="activity-level">Activity Level</label>
                        <div class="input-icon-wrapper">
                            <i class="fas fa-running input-icon"></i>
                            <select id="activity-level" name="activity_level" required>
                                <option value="">Select Activity Level...</option>
                                <option value="sedentary" <?= $selectedActivity === 'sedentary' ? 'selected' : ''; ?>>Sedentary (Little/no exercise)</option>
                                <option value="lightly_active" <?= $selectedActivity === 'lightly_active' ? 'selected' : ''; ?>>Lightly Active (1-3 days/week)</option>
                                <option value="moderately_active" <?= $selectedActivity === 'moderately_active' ? 'selected' : ''; ?>>Moderately Active (3-5 days/week)</option>
                                <option value="very_active" <?= $selectedActivity === 'very_active' ? 'selected' : ''; ?>>Very Active (6-7 days/week)</option>
                                <option value="extra_active" <?= $selectedActivity === 'extra_active' ? 'selected' : ''; ?>>Extra Active (Physical job/Training)</option>
                            </select>
                        </div>
                        <small id="activity-info" class="hint-text"></small>
                    </div>

                    <button type="submit" class="btn-calculate">
                        <i class="fas fa-bolt"></i> Calculate Stats
                    </button>
                </form>
            </section>

            <section class="calc-results-container">
                <?php if ($calculatorResult): ?>
                    <div class="results-header">
                        <h3><i class="fas fa-chart-pie"></i> Your Results</h3>
                    </div>

                    <div class="metrics-row">
                        <div class="metric-card card-blue">
                            <div class="metric-icon"><i class="fas fa-fire"></i></div>
                            <div class="metric-info">
                                <span class="metric-label">Maintenance</span>
                                <span class="metric-value"><?= number_format($calculatorResult['tdee']); ?> <small>kcal</small></span>
                            </div>
                        </div>

                        <div class="metric-card card-purple">
                            <div class="metric-icon"><i class="fas fa-weight-hanging"></i></div>
                            <div class="metric-info">
                                <span class="metric-label">BMI Score</span>
                                <span class="metric-value"><?= number_format($calculatorResult['bmi'], 1); ?></span>
                            </div>
                        </div>

                        <div class="metric-card card-green">
                            <div class="metric-icon"><i class="fas fa-bullseye"></i></div>
                            <div class="metric-info">
                                <span class="metric-label">Ideal Weight</span>
                                <span class="metric-value">
                                    <?= number_format($calculatorResult['ideal_weight']['min']) . '-' . number_format($calculatorResult['ideal_weight']['max']); ?>
                                    <small>kg</small>
                                </span>
                            </div>
                        </div>
                    </div>

                    <div class="details-section">

                        <div class="accordion-item">
                            <button class="accordion-header" type="button">
                                <span><i class="fas fa-list-alt"></i> Calorie Breakdown</span>
                                <i class="fas fa-chevron-down arrow"></i>
                            </button>
                            <div class="accordion-content">
                                <div class="content-inner">
                                    <p class="desc-text">Based on the <strong>Mifflin-St Jeor</strong> formula, here is your estimated daily calorie needs:</p>
                                    <table class="modern-table small-table">
                                        <thead><tr><th>Activity Level</th><th>Calories</th></tr></thead>
                                        <tbody>
                                            <?php foreach($calculatorResult['tdee_all'] as $level => $cal): ?>
                                                <tr class="<?= $selectedActivity == $level ? 'highlight-row' : '' ?>">
                                                    <td><?= ucwords(str_replace('_', ' ', $level)) ?></td>
                                                    <td><strong><?= number_format($cal) ?></strong></td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>

                        <div class="accordion-item">
                            <button class="accordion-header" type="button">
                                <span><i class="fas fa-info-circle"></i> BMI Analysis</span>
                                <i class="fas fa-chevron-down arrow"></i>
                            </button>
                            <div class="accordion-content">
                                <div class="content-inner">
                                    <p class="desc-text">Your BMI is <strong><?= number_format($calculatorResult['bmi'], 1) ?></strong>.</p>
                                    <table class="modern-table small-table">
                                        <thead><tr><th>Category</th><th>Range</th></tr></thead>
                                        <tbody>
                                            <tr class="<?= $calculatorResult['bmi'] < 18.5 ? 'highlight-row' : '' ?>"><td>Underweight</td><td>&lt; 18.5</td></tr>
                                            <tr class="<?= ($calculatorResult['bmi'] >= 18.5 && $calculatorResult['bmi'] < 25) ? 'highlight-row' : '' ?>"><td>Normal</td><td>18.5 - 24.9</td></tr>
                                            <tr class="<?= ($calculatorResult['bmi'] >= 25 && $calculatorResult['bmi'] < 30) ? 'highlight-row' : '' ?>"><td>Overweight</td><td>25 - 29.9</td></tr>
                                            <tr class="<?= $calculatorResult['bmi'] >= 30 ? 'highlight-row' : '' ?>"><td>Obese</td><td>30+</td></tr>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>

                    </div>
                <?php else: ?>
                    <div class="empty-calc-state">
                        <i class="fas fa-calculator"></i>
                        <h4>Ready to Calculate?</h4>
                        <p>Fill in your details to get your personalized nutrition stats.</p>
                    </div>
                <?php endif; ?>
            </section>
        </div>
    </main>

    <?php if ($isLoggedIn): include PROJECT_ROOT . 'dashboard/views/quick-log-fab.php'; endif; ?>

    <?php include PROJECT_ROOT . 'views/footer.php'; ?>

    <script>
        document.addEventListener('DOMContentLoaded', () => {
            // 1. Activity Level Hint
            const descriptions = {
                sedentary: "Desk job, little to no exercise.",
                lightly_active: "Light exercise 1–3 days/week.",
                moderately_active: "Moderate exercise 3–5 days/week.",
                very_active: "Hard exercise 6–7 days/week.",
                extra_active: "Very hard exercise & physical job."
            };
            const select = document.getElementById('activity-level');
            const info = document.getElementById('activity-info');

            if(select) {
                const updateActivityInfo = () => {
                    const val = select.value;
                    info.textContent = descriptions[val] || '';
                };

                select.addEventListener('change', updateActivityInfo);
                updateActivityInfo();
            }

            // 2. Accordion Logic
            const accordions = document.querySelectorAll('.accordion-header');
            accordions.forEach(acc => {
                acc.addEventListener('click', function() {
                    this.classList.toggle('active');
                    const content = this.nextElementSibling;
                    
                    if (content.style.maxHeight) {
                        content.style.maxHeight = null;
                    } else {
                        content.style.maxHeight = content.scrollHeight + "px";
                    }
                });
            });
            
            // Open first accordion by default if exists
            if(accordions.length > 0) {
                accordions[0].click();
            }
        });
    </script>
</body>
</html>
