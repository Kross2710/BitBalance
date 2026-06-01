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
<html lang="<?= html_lang_attr() ?>" data-theme="<?php echo isset($_SESSION['user']) ? ($_SESSION['user']['theme_preference'] ?? 'system') : 'system'; ?>">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= t('calc.title_alt') ?></title>
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
                    <span><strong><?= t('calc.demo_note') ?></strong> <?= t('calc.demo_body') ?></span>
                    <a href="<?= BASE_URL ?>signup.php" class="demo-banner-cta"><?= t('dashboard.demo.cta') ?></a>
                </div>
            <?php endif; ?>

            <section class="calc-form-card surface-card">
                <div class="card-header">
                    <h3><i class="fas fa-calculator"></i> <?= t('calc.card_title') ?></h3>
                    <p class="subtitle"><?= t('calc.card_sub') ?></p>
                </div>

                <?php if (!empty($error_message)): ?>
                    <div class="alert error"><i class="fas fa-exclamation-triangle"></i> <?= $error_message ?></div>
                <?php endif; ?>

                <form action="handlers/process_calculator.php" method="POST" id="calcForm">
                    <div class="form-grid">
                        <div class="form-group">
                            <label for="age"><?= t('calc.input.age') ?></label>
                            <div class="input-icon-wrapper">
                                <i class="fas fa-birthday-cake input-icon"></i>
                                <input type="number" id="age" name="age" min="1" max="120" required value="<?= htmlspecialchars((string)$formAge); ?>" placeholder="<?= t('calc.placeholder.years') ?>">
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="gender"><?= t('calc.input.gender') ?></label>
                            <div class="input-icon-wrapper">
                                <i class="fas fa-venus-mars input-icon"></i>
                                <select id="gender" name="gender" required>
                                    <option value=""><?= t('calc.gender.select') ?></option>
                                    <option value="male" <?= $formGender === 'male' ? 'selected' : ''; ?>><?= t('calc.input.gender.male') ?></option>
                                    <option value="female" <?= $formGender === 'female' ? 'selected' : ''; ?>><?= t('calc.input.gender.female') ?></option>
                                    <option value="other" <?= $formGender === 'other' ? 'selected' : ''; ?>><?= t('calc.input.gender.other') ?></option>
                                </select>
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="weight"><?= t('calc.input.weight') ?></label>
                            <div class="input-icon-wrapper">
                                <i class="fas fa-weight input-icon"></i>
                                <input type="number" id="weight" name="weight" min="1" step="0.1" required value="<?= htmlspecialchars((string)$formWeight); ?>" placeholder="<?= t('calc.placeholder.kg') ?>">
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="height"><?= t('calc.input.height') ?></label>
                            <div class="input-icon-wrapper">
                                <i class="fas fa-ruler-vertical input-icon"></i>
                                <input type="number" id="height" name="height" min="1" step="0.1" required value="<?= htmlspecialchars((string)$formHeight); ?>" placeholder="<?= t('calc.placeholder.cm') ?>">
                            </div>
                        </div>
                    </div>

                    <div class="form-group full-width">
                        <label for="activity-level"><?= t('calc.input.activity') ?></label>
                        <div class="input-icon-wrapper">
                            <i class="fas fa-running input-icon"></i>
                            <select id="activity-level" name="activity_level" required>
                                <option value=""><?= t('calc.activity.select') ?></option>
                                <option value="sedentary" <?= $selectedActivity === 'sedentary' ? 'selected' : ''; ?>><?= t('calc.activity.sedentary_full') ?></option>
                                <option value="lightly_active" <?= $selectedActivity === 'lightly_active' ? 'selected' : ''; ?>><?= t('calc.activity.light_full') ?></option>
                                <option value="moderately_active" <?= $selectedActivity === 'moderately_active' ? 'selected' : ''; ?>><?= t('calc.activity.moderate_full') ?></option>
                                <option value="very_active" <?= $selectedActivity === 'very_active' ? 'selected' : ''; ?>><?= t('calc.activity.very_full') ?></option>
                                <option value="extra_active" <?= $selectedActivity === 'extra_active' ? 'selected' : ''; ?>><?= t('calc.activity.extra_full') ?></option>
                            </select>
                        </div>
                        <small id="activity-info" class="hint-text"></small>
                    </div>

                    <button type="submit" class="btn-calculate">
                        <i class="fas fa-bolt"></i> <?= t('calc.calc_stats') ?>
                    </button>
                </form>
            </section>

            <section class="calc-results-container">
                <?php if ($calculatorResult): ?>
                    <div class="results-header">
                        <h3><i class="fas fa-chart-pie"></i> <?= t('calc.results.title') ?></h3>
                    </div>

                    <div class="metrics-row">
                        <div class="metric-card card-blue surface-card">
                            <div class="metric-icon"><i class="fas fa-fire"></i></div>
                            <div class="metric-info">
                                <span class="metric-label"><?= t('calc.results.maintenance') ?></span>
                                <span class="metric-value"><?= number_format($calculatorResult['tdee']); ?> <small><?= t('common.kcal') ?></small></span>
                            </div>
                        </div>

                        <div class="metric-card card-purple surface-card">
                            <div class="metric-icon"><i class="fas fa-weight-hanging"></i></div>
                            <div class="metric-info">
                                <span class="metric-label"><?= t('calc.results.bmi_score') ?></span>
                                <span class="metric-value"><?= number_format($calculatorResult['bmi'], 1); ?></span>
                            </div>
                        </div>

                        <div class="metric-card card-green surface-card">
                            <div class="metric-icon"><i class="fas fa-bullseye"></i></div>
                            <div class="metric-info">
                                <span class="metric-label"><?= t('calc.results.ideal_weight') ?></span>
                                <span class="metric-value">
                                    <?= number_format($calculatorResult['ideal_weight']['min']) . '-' . number_format($calculatorResult['ideal_weight']['max']); ?>
                                    <small>kg</small>
                                </span>
                            </div>
                        </div>
                    </div>

                    <div class="details-section">

                        <div class="accordion-item surface-card">
                            <button class="accordion-header" type="button">
                                <span><i class="fas fa-list-alt"></i> <?= t('calc.acc.breakdown') ?></span>
                                <i class="fas fa-chevron-down arrow"></i>
                            </button>
                            <div class="accordion-content">
                                <div class="content-inner">
                                    <p class="desc-text"><?= t_raw('calc.acc.breakdown_desc') ?></p>
                                    <table class="modern-table small-table">
                                        <thead><tr><th><?= t('calc.col.activity') ?></th><th><?= t('calc.col.calories') ?></th></tr></thead>
                                        <tbody>
                                            <?php
                                            // Map internal activity-level slugs to translated labels.
                                            $__activityLabels = [
                                                'sedentary' => t_raw('calc.activity.sedentary_full'),
                                                'lightly_active' => t_raw('calc.activity.light_full'),
                                                'moderately_active' => t_raw('calc.activity.moderate_full'),
                                                'very_active' => t_raw('calc.activity.very_full'),
                                                'extra_active' => t_raw('calc.activity.extra_full'),
                                            ];
                                            foreach($calculatorResult['tdee_all'] as $level => $cal): ?>
                                                <tr class="<?= $selectedActivity == $level ? 'highlight-row' : '' ?>">
                                                    <td><?= htmlspecialchars($__activityLabels[$level] ?? ucwords(str_replace('_', ' ', $level))) ?></td>
                                                    <td><strong><?= number_format($cal) ?></strong></td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>

                        <div class="accordion-item surface-card">
                            <button class="accordion-header" type="button">
                                <span><i class="fas fa-info-circle"></i> <?= t('calc.acc.bmi_analysis') ?></span>
                                <i class="fas fa-chevron-down arrow"></i>
                            </button>
                            <div class="accordion-content">
                                <div class="content-inner">
                                    <p class="desc-text"><?= t_raw('calc.acc.bmi_desc', ['value' => number_format($calculatorResult['bmi'], 1)]) ?></p>
                                    <table class="modern-table small-table">
                                        <thead><tr><th><?= t('calc.col.category') ?></th><th><?= t('calc.col.range') ?></th></tr></thead>
                                        <tbody>
                                            <tr class="<?= $calculatorResult['bmi'] < 18.5 ? 'highlight-row' : '' ?>"><td><?= t('dashboard.bmi.under') ?></td><td>&lt; 18.5</td></tr>
                                            <tr class="<?= ($calculatorResult['bmi'] >= 18.5 && $calculatorResult['bmi'] < 25) ? 'highlight-row' : '' ?>"><td><?= t('dashboard.bmi.normal') ?></td><td>18.5 - 24.9</td></tr>
                                            <tr class="<?= ($calculatorResult['bmi'] >= 25 && $calculatorResult['bmi'] < 30) ? 'highlight-row' : '' ?>"><td><?= t('dashboard.bmi.over') ?></td><td>25 - 29.9</td></tr>
                                            <tr class="<?= $calculatorResult['bmi'] >= 30 ? 'highlight-row' : '' ?>"><td><?= t('dashboard.bmi.obese') ?></td><td>30+</td></tr>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>

                    </div>
                <?php else: ?>
                    <div class="empty-calc-state surface-card">
                        <i class="fas fa-calculator"></i>
                        <h4><?= t('calc.empty.title') ?></h4>
                        <p><?= t('calc.empty.body') ?></p>
                    </div>
                <?php endif; ?>
            </section>
        </div>
    </main>

    <?php if ($isLoggedIn): include PROJECT_ROOT . 'dashboard/views/quick-log-fab.php'; endif; ?>

    <?php include PROJECT_ROOT . 'views/footer.php'; ?>

    <script>
        document.addEventListener('DOMContentLoaded', () => {
            // 1. Activity Level Hint — strings come from the active locale.
            const descriptions = {
                sedentary: <?= json_encode(t_raw('calc.hint.sedentary')) ?>,
                lightly_active: <?= json_encode(t_raw('calc.hint.light')) ?>,
                moderately_active: <?= json_encode(t_raw('calc.hint.moderate')) ?>,
                very_active: <?= json_encode(t_raw('calc.hint.very')) ?>,
                extra_active: <?= json_encode(t_raw('calc.hint.extra')) ?>
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
