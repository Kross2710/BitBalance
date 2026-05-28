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

$physicalReady = $isLoggedIn
    && is_numeric($userAge) && (int) $userAge > 0
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
        $planNotes[] = 'The recommendation was clamped to BitBalance goal limits.';
    }

    if ($intakeSummary['average_calories'] === null) {
        $planNotes[] = 'Log food for a few days to compare your real intake against this plan.';
    } else {
        $gap = (int) $intakeSummary['average_calories'] - $recommendedGoal;
        if (abs($gap) <= 100) {
            $planNotes[] = 'Your recent logged-day average is already close to this target.';
        } elseif ($gap > 0) {
            $planNotes[] = 'Your recent logged-day average is about ' . abs($gap) . ' kcal above this target.';
        } else {
            $planNotes[] = 'Your recent logged-day average is about ' . abs($gap) . ' kcal below this target.';
        }
    }

    if ($goalMode === 'lose' && $weeklyRate >= 0.75) {
        $planNotes[] = 'This is an aggressive loss rate. Watch hunger, energy, and training performance.';
    }
    if ($goalMode === 'gain' && $weeklyRate >= 0.75) {
        $planNotes[] = 'This is a fast gain rate. A smaller surplus may reduce unwanted fat gain.';
    }
    if ($currentGoal !== null && abs($currentGoal - $recommendedGoal) >= 150) {
        $direction = $recommendedGoal > $currentGoal ? 'higher' : 'lower';
        $planNotes[] = 'This plan is ' . abs($recommendedGoal - $currentGoal) . ' kcal ' . $direction . ' than your current daily goal.';
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
<html lang="en"
    data-theme="<?php echo isset($_SESSION['user']) ? ($_SESSION['user']['theme_preference'] ?? 'light') : 'light'; ?>">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Goal Planner | BitBalance</title>
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

    <?php if ($isLoggedIn): ?>
        <?php include PROJECT_ROOT . 'dashboard/views/right-sidebar.php'; ?>

        <main class="dashboard-content">
            <div class="plan-container">
                <?php if ($error_message): ?>
                    <div class="plan-alert plan-alert-error"><i class="fas fa-triangle-exclamation"></i> <?= $error_message ?></div>
                <?php endif; ?>
                <?php if ($success_message): ?>
                    <div class="plan-alert plan-alert-success"><i class="fas fa-circle-check"></i> <?= $success_message ?></div>
                <?php endif; ?>

                <section class="plan-hero">
                    <div class="plan-hero-copy">
                        <span class="plan-kicker"><i class="fas fa-route"></i> Adaptive Goal Coach</span>
                        <h1>Plan your next calorie target</h1>
                        <p>Use your body metrics, recent intake, and weight trend to turn a goal into a daily calorie number.</p>
                    </div>
                    <div class="plan-hero-metrics">
                        <div class="plan-metric">
                            <span class="plan-metric-label">Current goal</span>
                            <strong><?= $currentGoal ? number_format($currentGoal) : 'Unset' ?></strong>
                            <small>kcal/day</small>
                        </div>
                        <div class="plan-metric">
                            <span class="plan-metric-label">7-day avg</span>
                            <strong><?= $intakeSummary['average_calories'] !== null ? number_format($intakeSummary['average_calories']) : '--' ?></strong>
                            <small><?= (int) $intakeSummary['logged_days'] ?> logged days</small>
                        </div>
                        <div class="plan-metric">
                            <span class="plan-metric-label">Current weight</span>
                            <strong><?= $weightSummary['current'] !== null ? number_format($weightSummary['current'], 1) : '--' ?></strong>
                            <small>kg</small>
                        </div>
                    </div>
                </section>

                <section class="plan-panel plan-intake-panel">
                    <div class="plan-section-head compact">
                        <h2><i class="fas fa-chart-column"></i> Recent Intake vs Plan</h2>
                        <p>
                            <?php if ($currentGoal): ?>
                                Tracking your last 7 days of logged calories against your current daily goal of
                                <strong><?= number_format($currentGoal) ?></strong> kcal.
                            <?php elseif ($recommendedGoal): ?>
                                No daily goal saved yet — comparing against the recommended <strong><?= number_format($recommendedGoal) ?></strong> kcal.
                            <?php else: ?>
                                Log a few days and calculate a plan to see the comparison.
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
                            <h2><i class="fas fa-sliders"></i> Plan Inputs</h2>
                            <p>Adjust the target and activity estimate, then calculate a recommendation.</p>
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
                                    <label for="weekly_rate">Weekly rate</label>
                                    <select id="weekly_rate" name="weekly_rate">
                                        <?php foreach ([0.25, 0.5, 0.75, 1.0] as $rate): ?>
                                            <option value="<?= $rate ?>" <?= abs($weeklyRate - $rate) < 0.001 ? 'selected' : '' ?>>
                                                <?= rtrim(rtrim(number_format($rate, 2), '0'), '.') ?> kg/week
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>

                                <div class="plan-field">
                                    <label for="target_weight">Target weight <span>(optional)</span></label>
                                    <input type="number" step="0.1" min="1" max="500" id="target_weight" name="target_weight"
                                        value="<?= $targetWeight !== null ? htmlspecialchars((string) $targetWeight, ENT_QUOTES) : '' ?>"
                                        placeholder="kg">
                                </div>

                                <div class="plan-field full">
                                    <label for="activity_level">Activity level</label>
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
                                <i class="fas fa-calculator"></i> Calculate Plan
                            </button>
                        </form>
                    </section>

                    <section class="plan-panel plan-result-panel">
                        <div class="plan-section-head">
                            <h2><i class="fas fa-bullseye"></i> Recommendation</h2>
                            <p>Based on your selected plan and current profile data.</p>
                        </div>

                        <?php if (!$physicalReady): ?>
                            <div class="plan-empty">
                                <i class="fas fa-user-pen"></i>
                                <h3>Body metrics needed</h3>
                                <p>Add age, gender, weight, and height in Profile so BitBalance can estimate TDEE.</p>
                                <a href="<?= BASE_URL ?>profile.php#physical-stats" class="plan-secondary-btn">Update Profile</a>
                            </div>
                        <?php else: ?>
                            <div class="plan-target">
                                <span>Recommended daily goal</span>
                                <strong><?= number_format($recommendedGoal) ?></strong>
                                <small>kcal/day</small>
                            </div>

                            <div class="plan-stats-row">
                                <div class="plan-stat">
                                    <span>BMR</span>
                                    <strong><?= number_format((int) round($bmr)) ?></strong>
                                </div>
                                <div class="plan-stat">
                                    <span>TDEE</span>
                                    <strong><?= number_format((int) round($tdee)) ?></strong>
                                </div>
                                <div class="plan-stat">
                                    <span><?= $goalMode === 'gain' ? 'Surplus' : ($goalMode === 'lose' ? 'Deficit' : 'Adjustment') ?></span>
                                    <strong><?= number_format($dailyAdjustment) ?></strong>
                                </div>
                            </div>

                            <div class="plan-macro-strip">
                                <div><span>Protein</span><strong><?= (int) $macroPlan['protein'] ?>g</strong></div>
                                <div><span>Carbs</span><strong><?= (int) $macroPlan['carbs'] ?>g</strong></div>
                                <div><span>Fat</span><strong><?= (int) $macroPlan['fat'] ?>g</strong></div>
                            </div>

                            <?php if ($targetEta && $targetEta['valid']): ?>
                                <div class="plan-eta">
                                    <i class="fas fa-calendar-check"></i>
                                    Estimated target date: <strong><?= htmlspecialchars($targetEta['date']) ?></strong>
                                    <span>(about <?= htmlspecialchars((string) $targetEta['weeks']) ?> weeks)</span>
                                </div>
                            <?php endif; ?>

                            <form action="handlers/apply_plan_goal.php" method="POST">
                                <?= csrf_field() ?>
                                <input type="hidden" name="calorie_goal" value="<?= (int) $recommendedGoal ?>">
                                <button type="submit" class="plan-apply-btn">
                                    <i class="fas fa-check"></i> Apply to Daily Goal
                                </button>
                            </form>
                        <?php endif; ?>
                    </section>
                </div>

                <section class="plan-panel plan-notes-panel">
                    <div class="plan-section-head compact">
                        <h2><i class="fas fa-compass"></i> Coach Notes</h2>
                    </div>
                    <?php if (empty($planNotes)): ?>
                        <div class="plan-note muted">Calculate a plan to see personalized notes.</div>
                    <?php else: ?>
                        <div class="plan-notes">
                            <?php foreach ($planNotes as $note): ?>
                                <div class="plan-note"><i class="fas fa-circle-info"></i><span><?= htmlspecialchars($note) ?></span></div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>

                    <div class="plan-weekly-target">
                        <span>Weekly calorie budget</span>
                        <strong><?= $weeklyTarget ? number_format($weeklyTarget) : '--' ?></strong>
                        <small>kcal/week</small>
                    </div>
                </section>
            </div>
        </main>

        <?php include PROJECT_ROOT . 'dashboard/views/quick-log-fab.php'; ?>
    <?php else: ?>
        <main class="dashboard-content dashboard-empty-state">
            <h2>Please log in to access Goal Planner.</h2>
            <a href="<?= BASE_URL ?>login.php" class="btn-primary">Sign In</a>
        </main>
    <?php endif; ?>

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
                new Chart(chartEl.getContext('2d'), {
                    type: 'bar',
                    data: {
                        labels: <?= json_encode($chartLabels) ?>,
                        datasets: [
                            {
                                label: 'Calories',
                                data: <?= json_encode($chartCalories) ?>,
                                backgroundColor: '#1CB0F6',
                                borderRadius: 6,
                                barThickness: 22
                            },
                            {
                                label: <?= json_encode($goalLineLabel) ?>,
                                data: <?= json_encode($goalLine) ?>,
                                type: 'line',
                                borderColor: '#FF9600',
                                backgroundColor: '#FF9600',
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
                            y: { beginAtZero: true, grid: { color: 'rgba(148, 163, 184, 0.22)' } },
                            x: { grid: { display: false } }
                        }
                    }
                });
            }
        })();
    </script>
</body>

</html>
