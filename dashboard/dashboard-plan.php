<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/../include/init.php';
require_once __DIR__ . '/handlers/dashboard_data.php';
require_once __DIR__ . '/handlers/goal_plan.php';
require_once __DIR__ . '/../include/csrf.php';
require_once __DIR__ . '/../include/handlers/log_attempt.php';

if ($isLoggedIn) {
    log_attempt($pdo, $user['user_id'], 'view', 'User ' . $user['user_id'] . ' opened Goal Planner', 'dashboard', null);
}

$activePage = 'plan';
$activeHeader = 'dashboard';
$bodyClass = 'page-plan';
$displayUser = $isLoggedIn ? $user['user_name'] : 'Guest';

if (!$isLoggedIn) {
    $userAge = 25;
    $userGender = 'male';
    $userWeight = 70;
    $userHeight = 175;
    $userGoal = 2200;
    $totalCalories = 1450;
}

$error_message = isset($_GET['error']) ? htmlspecialchars($_GET['error'], ENT_QUOTES) : '';
$success_message = isset($_GET['success']) ? htmlspecialchars($_GET['success'], ENT_QUOTES) : '';

$activityOptions = plan_activity_options();
$goalModes = plan_goal_modes();

$defaultActivity = $selectedActivity ?: 'moderately_active';
if (!isset($activityOptions[$defaultActivity])) {
    $defaultActivity = 'moderately_active';
}

$isPlanPost = $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['goal_mode']);
$savedPrefs = $isLoggedIn ? plan_load_preferences($pdo, (int) $user['user_id']) : null;

$goalMode = $_POST['goal_mode'] ?? $savedPrefs['goal_mode'] ?? 'lose';
if (!isset($goalModes[$goalMode])) {
    $goalMode = 'lose';
}

$activityLevel = $_POST['activity_level'] ?? $savedPrefs['activity_level'] ?? $defaultActivity;
if (!isset($activityOptions[$activityLevel])) {
    $activityLevel = $defaultActivity;
}

if (isset($_POST['weekly_rate']) && is_numeric($_POST['weekly_rate'])) {
    $weeklyRate = (float) $_POST['weekly_rate'];
} elseif ($savedPrefs && isset($savedPrefs['weekly_rate'])) {
    $weeklyRate = (float) $savedPrefs['weekly_rate'];
} else {
    $weeklyRate = 0.25;
}
$weeklyRate = max(0.0, min(1.5, $weeklyRate));
if ($goalMode === 'maintain') {
    $weeklyRate = 0.0;
}

$targetWeight = null;
if (isset($_POST['target_weight']) && $_POST['target_weight'] !== '' && is_numeric($_POST['target_weight'])) {
    $tw = (float) $_POST['target_weight'];
    if ($tw > 0 && $tw <= 500) {
        $targetWeight = $tw;
    }
} elseif ($savedPrefs && $savedPrefs['target_weight'] !== null) {
    $targetWeight = $savedPrefs['target_weight'];
}

if ($isPlanPost && $isLoggedIn) {
    plan_save_preferences($pdo, (int) $user['user_id'], [
        'goal_mode'      => $goalMode,
        'weekly_rate'    => $weeklyRate,
        'activity_level' => $activityLevel,
        'target_weight'  => $targetWeight,
    ]);
}

$currentGoal = !empty($userGoal) ? (int) $userGoal : null;
$fallbackWeight = is_numeric($userWeight) && (float) $userWeight > 0 ? (float) $userWeight : null;

$intakeSummary = ['daily' => [], 'logged_days' => 0, 'average_calories' => null];
$weightSummary = ['current' => $fallbackWeight, 'current_date' => null, 'trend' => null, 'chart' => []];

$physicalReady = is_numeric($userAge) && (int) $userAge > 0
    && in_array($userGender, ['male', 'female'], true)
    && is_numeric($userWeight) && (float) $userWeight > 0
    && is_numeric($userHeight) && (float) $userHeight > 0;

$bmr = null;
$tdee = null;
$dailyAdjustment = 0;
$recommendedGoal = null;
$weeklyTarget = null;
$macroPlan = ['protein' => 0, 'carbs' => 0, 'fat' => 0];
$targetEta = null;
$planNotes = [];

if ($isLoggedIn) {
    $intakeSummary = plan_recent_intake_summary($pdo, (int) $user['user_id'], 7);
    $weightSummary = plan_weight_summary($pdo, (int) $user['user_id'], $fallbackWeight);
} else {
    $demoCalories = [1800, 2100, 1950, 2200, 2050, 1500, 1450];
    $demoProtein = [110, 135, 125, 140, 130, 90, 85];
    $demoCarbs = [200, 250, 220, 260, 230, 180, 175];
    $demoFat = [55, 62, 58, 65, 60, 48, 46];
    $demoDaily = [];
    $demoTotal = 0;

    foreach ($demoCalories as $i => $calories) {
        $date = date('Y-m-d', strtotime('-' . (6 - $i) . ' days'));
        $demoTotal += $calories;
        $demoDaily[] = [
            'date' => $date,
            'label' => date('D', strtotime($date)),
            'calories' => $calories,
            'protein' => $demoProtein[$i],
            'carbs' => $demoCarbs[$i],
            'fat' => $demoFat[$i],
        ];
    }

    $intakeSummary = [
        'daily' => $demoDaily,
        'logged_days' => count($demoDaily),
        'average_calories' => (int) round($demoTotal / count($demoDaily)),
        'average_protein' => round(array_sum($demoProtein) / count($demoProtein), 1),
        'average_carbs' => round(array_sum($demoCarbs) / count($demoCarbs), 1),
        'average_fat' => round(array_sum($demoFat) / count($demoFat), 1),
    ];
    $weightSummary = [
        'current' => 70.0,
        'current_date' => date('Y-m-d'),
        'trend' => -0.8,
        'chart' => [
            ['label' => date('d/m', strtotime('-21 days')), 'weight' => 70.8],
            ['label' => date('d/m', strtotime('-14 days')), 'weight' => 70.5],
            ['label' => date('d/m', strtotime('-7 days')), 'weight' => 70.2],
            ['label' => date('d/m'), 'weight' => 70.0],
        ],
    ];
}

if ($physicalReady) {
    $bmr = plan_calculate_bmr((int) $userAge, $userGender, (float) $userWeight, (float) $userHeight);
    $tdee = plan_calculate_tdee($bmr, $activityLevel);
    $dailyAdjustment = $goalMode === 'maintain' ? 0 : (int) round(($weeklyRate * 7700) / 7);

    if ($goalMode === 'lose') {
        $rawGoal = (int) round($tdee - $dailyAdjustment);
    } elseif ($goalMode === 'gain') {
        $rawGoal = (int) round($tdee + $dailyAdjustment);
    } else {
        $rawGoal = (int) round($tdee);
    }

    $recommendedGoal = plan_clamp_goal($rawGoal);
    $weeklyTarget = $recommendedGoal * 7;
    $macroPlan = getMacroGoalsFromCalorieGoal($recommendedGoal);
    $targetEta = plan_target_eta($weightSummary['current'], $targetWeight, $goalMode, $weeklyRate);

    if ($rawGoal !== $recommendedGoal) {
        $planNotes[] = t_raw('plan.note.clamped');
    }

    if ($intakeSummary['average_calories'] === null) {
        $planNotes[] = t_raw('plan.note.need_logs');
    } else {
        $gap = (int) $intakeSummary['average_calories'] - $recommendedGoal;
        if (abs($gap) <= 100) {
            $planNotes[] = t_raw('plan.note.close');
        } elseif ($gap > 0) {
            $planNotes[] = t_raw('plan.note.above', ['n' => abs($gap)]);
        } else {
            $planNotes[] = t_raw('plan.note.below', ['n' => abs($gap)]);
        }
    }

    if ($goalMode === 'lose' && $weeklyRate >= 0.75) {
        $planNotes[] = t_raw('plan.note.aggressive_lose');
    }
    if ($goalMode === 'gain' && $weeklyRate >= 0.75) {
        $planNotes[] = t_raw('plan.note.aggressive_gain');
    }
    if ($currentGoal !== null && abs($currentGoal - $recommendedGoal) >= 150) {
        $delta = abs($recommendedGoal - $currentGoal);
        $planNotes[] = $recommendedGoal > $currentGoal
            ? t_raw('plan.note.delta_higher', ['n' => $delta])
            : t_raw('plan.note.delta_lower', ['n' => $delta]);
    }
    if ($targetEta && !$targetEta['valid']) {
        $planNotes[] = $targetEta['message'];
    }
}

$chartLabels = array_map(fn($d) => $d['label'], $intakeSummary['daily']);
$chartCalories = array_map(fn($d) => $d['calories'], $intakeSummary['daily']);
$goalLineValue = $currentGoal ?: ($recommendedGoal ?: 0);
$goalLine = array_fill(0, count($chartLabels), $goalLineValue);
$goalLineLabel = $currentGoal ? 'Current goal' : ($recommendedGoal ? 'Recommended goal' : 'Goal');
$weightLabelsForChart = array_map(fn($d) => $d['label'], $weightSummary['chart']);
$weightValuesForChart = array_map(fn($d) => $d['weight'], $weightSummary['chart']);
?>

<!DOCTYPE html>
<html lang="<?= html_lang_attr() ?>"
    data-theme="<?php echo isset($_SESSION['user']) ? ($_SESSION['user']['theme_preference'] ?? 'system') : 'system'; ?>">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= t('plan.title_alt') ?></title>
    <?php
    $pageComponents = ['sidebar', 'fab'];
    $pageCss = ['css/dashboard.css', 'css/pages/dashboard-plan.css'];
    include PROJECT_ROOT . 'views/head_css.php';
    ?>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="https://kit.fontawesome.com/b94f65ead2.js" crossorigin="anonymous"></script>
</head>

<body class="<?= htmlspecialchars($bodyClass, ENT_QUOTES) ?>">
    <?php include PROJECT_ROOT . 'views/header.php'; ?>
    <?php include PROJECT_ROOT . 'dashboard/views/sidebar.php'; ?>
    <?php include PROJECT_ROOT . 'dashboard/views/right-sidebar.php'; ?>

        <main class="dashboard-content">
            <div class="plan-container">
                <?php if (!$isLoggedIn): ?>
                    <div class="demo-banner">
                        <i class="fas fa-flask"></i>
                        <span><strong><?= t('plan.demo_note') ?></strong> <?= t('plan.demo_body') ?></span>
                        <a href="<?= BASE_URL ?>signup.php" class="demo-banner-cta"><?= t('dashboard.demo.cta') ?></a>
                    </div>
                <?php endif; ?>

                <?php if ($error_message): ?>
                    <div class="plan-alert plan-alert-error"><i class="fas fa-triangle-exclamation"></i> <?= $error_message ?></div>
                <?php endif; ?>
                <?php if ($success_message): ?>
                    <div class="plan-alert plan-alert-success"><i class="fas fa-circle-check"></i> <?= $success_message ?></div>
                <?php endif; ?>

                <section class="plan-hero">
                    <div class="plan-hero-copy">
                        <span class="plan-kicker"><i class="fas fa-route"></i> <?= t('plan.kicker') ?></span>
                        <h1><?= t('plan.hero.title') ?></h1>
                        <p><?= t('plan.hero.subtitle') ?></p>
                    </div>
                    <div class="plan-hero-metrics">
                        <div class="plan-metric">
                            <span class="plan-metric-label"><?= t('plan.metric.current_goal') ?></span>
                            <strong><?= $currentGoal ? number_format($currentGoal) : t('plan.metric.unset') ?></strong>
                            <small><?= t('plan.metric.kcal_day') ?></small>
                        </div>
                        <div class="plan-metric">
                            <span class="plan-metric-label"><?= t('plan.metric.7day_avg') ?></span>
                            <strong><?= $intakeSummary['average_calories'] !== null ? number_format($intakeSummary['average_calories']) : '--' ?></strong>
                            <small><?= t('plan.metric.logged_days', ['n' => (int) $intakeSummary['logged_days']]) ?></small>
                        </div>
                        <div class="plan-metric">
                            <span class="plan-metric-label"><?= t('plan.metric.current_weight') ?></span>
                            <strong><?= $weightSummary['current'] !== null ? number_format($weightSummary['current'], 1) : '--' ?></strong>
                            <small>kg</small>
                        </div>
                    </div>
                </section>

                <section class="plan-panel plan-intake-panel">
                    <div class="plan-section-head compact">
                        <h2><i class="fas fa-chart-column"></i> <?= t('plan.recent.heading') ?></h2>
                        <p>
                            <?php if ($currentGoal): ?>
                                <?= t_raw('plan.recent.with_goal', ['n' => number_format($currentGoal)]) ?>
                            <?php elseif ($recommendedGoal): ?>
                                <?= t_raw('plan.recent.with_rec', ['n' => number_format($recommendedGoal)]) ?>
                            <?php else: ?>
                                <?= t('plan.recent.empty') ?>
                            <?php endif; ?>
                        </p>
                    </div>
                    <div class="plan-chart-wrap">
                        <canvas id="planIntakeChart"></canvas>
                    </div>
                </section>

                <div class="plan-grid">
                    <section class="plan-panel plan-form-panel">
                        <div class="plan-section-head">
                            <h2><i class="fas fa-sliders"></i> <?= t('plan.inputs.heading') ?></h2>
                            <p><?= t('plan.inputs.subtitle') ?></p>
                        </div>

                        <form method="POST" id="planForm">
                            <div class="plan-goal-options">
                                <?php foreach ($goalModes as $key => $mode): ?>
                                    <label class="goal-option <?= $goalMode === $key ? 'active' : '' ?>">
                                        <input type="radio" name="goal_mode" value="<?= htmlspecialchars($key) ?>" <?= $goalMode === $key ? 'checked' : '' ?>>
                                        <i class="fas <?= htmlspecialchars($mode['icon']) ?>"></i>
                                        <span><?= htmlspecialchars($mode['label']) ?></span>
                                        <small><?= htmlspecialchars($mode['copy']) ?></small>
                                    </label>
                                <?php endforeach; ?>
                            </div>

                            <div class="plan-form-grid">
                                <div class="plan-field">
                                    <label for="weekly_rate"><?= t('plan.field.weekly_rate') ?></label>
                                    <select id="weekly_rate" name="weekly_rate">
                                        <?php foreach ([0.25, 0.5, 0.75, 1.0] as $rate):
                                            $rateLabel = rtrim(rtrim(number_format($rate, 2), '0'), '.');
                                        ?>
                                            <option value="<?= $rate ?>" <?= abs($weeklyRate - $rate) < 0.001 ? 'selected' : '' ?>>
                                                <?= t('plan.field.weekly_rate_unit', ['n' => $rateLabel]) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>

                                <div class="plan-field">
                                    <label for="target_weight"><?= t('plan.field.target_weight') ?> <span><?= t('plan.field.target_weight_hint') ?></span></label>
                                    <input type="number" step="0.1" min="1" max="500" id="target_weight" name="target_weight"
                                        value="<?= $targetWeight !== null ? htmlspecialchars((string) $targetWeight, ENT_QUOTES) : '' ?>"
                                        placeholder="kg">
                                </div>

                                <div class="plan-field full">
                                    <label for="activity_level"><?= t('plan.field.activity_level') ?></label>
                                    <select id="activity_level" name="activity_level">
                                        <?php foreach ($activityOptions as $key => $option): ?>
                                            <option value="<?= htmlspecialchars($key) ?>" <?= $activityLevel === $key ? 'selected' : '' ?>>
                                                <?= htmlspecialchars($option['label'] . ' - ' . $option['detail']) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>

                            <button type="submit" class="plan-primary-btn">
                                <i class="fas fa-calculator"></i> <?= t('plan.calc_btn') ?>
                            </button>
                        </form>
                    </section>

                    <section class="plan-panel plan-result-panel">
                        <div class="plan-section-head">
                            <h2><i class="fas fa-bullseye"></i> <?= t('plan.recommendation.heading') ?></h2>
                            <p><?= t('plan.recommendation.subtitle') ?></p>
                        </div>

                        <?php if (!$physicalReady): ?>
                            <div class="plan-empty">
                                <i class="fas fa-user-pen"></i>
                                <h3><?= t('plan.needs_metrics.title') ?></h3>
                                <p><?= t('plan.needs_metrics.body') ?></p>
                                <a href="<?= BASE_URL ?>profile.php#physical-stats" class="plan-secondary-btn"><?= t('plan.needs_metrics.cta') ?></a>
                            </div>
                        <?php else: ?>
                            <div class="plan-target">
                                <span><?= t('plan.rec_goal') ?></span>
                                <strong><?= number_format($recommendedGoal) ?></strong>
                                <small><?= t('plan.metric.kcal_day') ?></small>
                            </div>

                            <div class="plan-stats-row">
                                <div class="plan-stat">
                                    <span><?= t('plan.stat.bmr') ?></span>
                                    <strong><?= number_format((int) round($bmr)) ?></strong>
                                </div>
                                <div class="plan-stat">
                                    <span><?= t('plan.stat.tdee') ?></span>
                                    <strong><?= number_format((int) round($tdee)) ?></strong>
                                </div>
                                <div class="plan-stat">
                                    <span><?= $goalMode === 'gain' ? t('plan.stat.surplus') : ($goalMode === 'lose' ? t('plan.stat.deficit') : t('plan.stat.adjustment')) ?></span>
                                    <strong><?= number_format($dailyAdjustment) ?></strong>
                                </div>
                            </div>

                            <div class="plan-macro-strip">
                                <div><span><?= t('dashboard.macros.protein') ?></span><strong><?= (int) $macroPlan['protein'] ?>g</strong></div>
                                <div><span><?= t('dashboard.macros.carbs') ?></span><strong><?= (int) $macroPlan['carbs'] ?>g</strong></div>
                                <div><span><?= t('dashboard.macros.fat') ?></span><strong><?= (int) $macroPlan['fat'] ?>g</strong></div>
                            </div>

                            <?php if ($targetEta && $targetEta['valid']): ?>
                                <div class="plan-eta">
                                    <i class="fas fa-calendar-check"></i>
                                    <?= t_raw('plan.eta', ['date' => htmlspecialchars($targetEta['date']), 'weeks' => htmlspecialchars((string) $targetEta['weeks'])]) ?>
                                </div>
                            <?php endif; ?>

                            <?php if ($isLoggedIn): ?>
                                <form action="handlers/apply_plan_goal.php" method="POST">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="calorie_goal" value="<?= (int) $recommendedGoal ?>">
                                    <button type="submit" class="plan-apply-btn">
                                        <i class="fas fa-check"></i> <?= t('plan.apply_goal') ?>
                                    </button>
                                </form>
                            <?php else: ?>
                                <div class="plan-demo-apply">
                                    <p><i class="fas fa-lock"></i> <?= t('plan.demo_apply_hint') ?></p>
                                    <a href="<?= BASE_URL ?>signup.php" class="plan-apply-btn"><?= t('plan.demo_apply_cta') ?></a>
                                </div>
                            <?php endif; ?>
                        <?php endif; ?>
                    </section>
                </div>

                <section class="plan-panel plan-notes-panel">
                    <div class="plan-section-head compact">
                        <h2><i class="fas fa-compass"></i> <?= t('plan.notes.heading') ?></h2>
                    </div>
                    <?php if (empty($planNotes)): ?>
                        <div class="plan-note muted"><?= t('plan.notes.empty') ?></div>
                    <?php else: ?>
                        <div class="plan-notes">
                            <?php foreach ($planNotes as $note): ?>
                                <div class="plan-note"><i class="fas fa-circle-info"></i><span><?= htmlspecialchars($note) ?></span></div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>

                    <div class="plan-weekly-target">
                        <span><?= t('plan.weekly_budget') ?></span>
                        <strong><?= $weeklyTarget ? number_format($weeklyTarget) : '--' ?></strong>
                        <small><?= t('plan.weekly_budget_unit') ?></small>
                    </div>
                </section>
            </div>
        </main>

    <?php if ($isLoggedIn): include PROJECT_ROOT . 'dashboard/views/quick-log-fab.php'; endif; ?>

    <?php include PROJECT_ROOT . 'views/footer.php'; ?>

    <script>
        (function () {
            const form = document.getElementById('planForm');
            const rate = document.getElementById('weekly_rate');
            const options = Array.from(document.querySelectorAll('.goal-option'));

            function syncGoalMode() {
                const selected = form?.querySelector('input[name="goal_mode"]:checked')?.value || 'lose';
                options.forEach(option => {
                    option.classList.toggle('active', option.querySelector('input')?.value === selected);
                });
                if (rate) {
                    rate.disabled = selected === 'maintain';
                }
            }

            options.forEach(option => {
                option.addEventListener('click', syncGoalMode);
                option.querySelector('input')?.addEventListener('change', syncGoalMode);
            });
            syncGoalMode();

            const chartEl = document.getElementById('planIntakeChart');
            if (chartEl && typeof Chart !== 'undefined') {
                const styles = getComputedStyle(document.documentElement);
                const chartBar = styles.getPropertyValue('--color-secondary').trim();
                const chartLine = styles.getPropertyValue('--color-accent').trim();
                const chartGrid = styles.getPropertyValue('--color-border').trim();

                new Chart(chartEl.getContext('2d'), {
                    type: 'bar',
                    data: {
                        labels: <?= json_encode($chartLabels) ?>,
                        datasets: [
                            {
                                label: <?= json_encode(t_raw('plan.chart.calories')) ?>,
                                data: <?= json_encode($chartCalories) ?>,
                                backgroundColor: chartBar,
                                borderRadius: 6,
                                barThickness: 22
                            },
                            {
                                label: <?= json_encode($goalLineLabel) ?>,
                                data: <?= json_encode($goalLine) ?>,
                                type: 'line',
                                borderColor: chartLine,
                                backgroundColor: chartLine,
                                borderWidth: 2,
                                pointRadius: 2,
                                tension: 0.25
                            }
                        ]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: { legend: { display: true, position: 'bottom' } },
                        scales: {
                            y: { beginAtZero: true, grid: { color: chartGrid } },
                            x: { grid: { display: false } }
                        }
                    }
                });
            }
        })();
    </script>
</body>

</html>
