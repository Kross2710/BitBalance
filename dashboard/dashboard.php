<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/../include/init.php';
require_once __DIR__ . '/handlers/dashboard_data.php';
require_once __DIR__ . '/handlers/functions.php';
require_once __DIR__ . '/../include/handlers/log_attempt.php';

$leaderboardWidgetRows = [];
if ($isLoggedIn) {
    require_once __DIR__ . '/../include/handlers/friends.php';
    // Real User
    log_attempt($pdo, $user['user_id'], 'view', 'User ' . $user['user_id'] . ' clicked on dashboard', 'dashboard', null);
    $displayUser = $user['user_name']; // Tên thật
    try {
        $leaderboardWidgetRows = leaderboard_friends($pdo, (int) $user['user_id'], 'weekly', 5);
    } catch (PDOException $e) {
        $leaderboardWidgetRows = [];
    }
} else {
    // Guest (Demo): Create mock data
    $displayUser = "Guest";

    // Mock Goal & Progress
    $userGoal = 2200;
    $totalCalories = 1450;
    $progressPercentage = round(($totalCalories / $userGoal) * 100);

    // Mock Streak
    $userStreak = [
        'logging_streak' => 5,
        'longest_logging_streak' => 12
    ];

    // Mock History Chart
    $historyLabels = ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'];
    $historyData = [1800, 2100, 1950, 2200, 2050, 1500, 1450];
    // Mock macros breakdown for the same 7 days (grams)
    $historyProtein = [110, 135, 125, 140, 130, 90, 85];
    $historyCarbs = [200, 250, 220, 260, 230, 180, 175];
    $historyFat = [55, 62, 58, 65, 60, 48, 46];
    $macroTotals = ['protein' => 85, 'carbs' => 175, 'fat' => 46];
    $macroGoals = getMacroGoalsFromCalorieGoal($userGoal);

    // Mock Intake Log
    $intakeLog = [
        ['food_item' => 'Pho Bo', 'calories' => 450, 'meal_category' => 'breakfast', 'date_intake' => date('Y-m-d 08:30:00')],
        ['food_item' => 'Iced Coffee', 'calories' => 120, 'meal_category' => 'snack', 'date_intake' => date('Y-m-d 10:00:00')],
        ['food_item' => 'Grilled Chicken Salad', 'calories' => 550, 'meal_category' => 'lunch', 'date_intake' => date('Y-m-d 12:30:00')],
        ['food_item' => 'Apple', 'calories' => 80, 'meal_category' => 'snack', 'date_intake' => date('Y-m-d 15:00:00')],
        ['food_item' => 'Salmon & Rice', 'calories' => 250, 'meal_category' => 'dinner', 'date_intake' => date('Y-m-d 19:00:00')]
    ];

    // Mock Meal Categories Data
    $mealCategoryData = [
        'breakfast' => 450,
        'lunch' => 550,
        'dinner' => 250,
        'snack' => 200
    ];

    // Mock Right Sidebar Data
    $userAge = 25;
    $userWeight = 70;
    $userHeight = 175;
}
$activePage = 'overview';
$activeHeader = 'dashboard';
$bodyClass = 'page-dashboard';

$status = 'Unset';
$statusClass = 'unset';

if (!empty($userGoal)) {
    if ($totalCalories > $userGoal) {
        $status = 'Overlimit';
        $statusClass = 'overlimit';
    } else {
        $status = 'Ongoing';
        $statusClass = 'ongoing';
    }
}

$error_message = '';
if (isset($_GET['error'])) {
    $error_message = htmlspecialchars($_GET['error']); // Prevent XSS
}
$success_message = '';
if (isset($_GET['success'])) {
    $success_message = htmlspecialchars($_GET['success']); // Prevent XSS
}

$averageCalories = calculateCalorieAverage($historyData);
$averageCalories = $averageCalories ?: 'N/A';

// --- Streak card display data ---
$streakDays = (int) ($userStreak['logging_streak'] ?? 0);
$brokenStreak = (int) ($userStreak['broken_streak'] ?? 0);

$streakMilestones = [7, 14, 30, 60, 100, 180, 365];
$prevMilestone = 0;
$nextMilestone = null;
foreach ($streakMilestones as $m) {
    if ($m > $streakDays) {
        $nextMilestone = $m;
        break;
    }
    $prevMilestone = $m;
}
if ($nextMilestone === null) {
    $streakProgress = 100;
    $milestoneText = t_raw('dashboard.streak.all_clear');
} else {
    $streakProgress = (int) round((($streakDays - $prevMilestone) / ($nextMilestone - $prevMilestone)) * 100);
    $daysLeft = $nextMilestone - $streakDays;
    $milestoneKey = $daysLeft === 1 ? 'dashboard.streak.milestone' : 'dashboard.streak.milestone_plural';
    $milestoneText = t_raw($milestoneKey, ['days' => $daysLeft, 'target' => $nextMilestone]);
}

if ($streakDays >= 30) {
    $streakFlameColor = '#fbbf24';
    $streakMessage = t_raw('dashboard.streak.msg_legend');
} elseif ($streakDays >= 14) {
    $streakFlameColor = '#fb923c';
    $streakMessage = t_raw('dashboard.streak.msg_incredible');
} elseif ($streakDays >= 1) {
    $streakFlameColor = '#ffffff';
    $streakMessage = t_raw('dashboard.streak.msg_building');
} else {
    $streakFlameColor = '#ffffff';
    $streakMessage = t_raw('dashboard.streak.msg_start');
}

// --- Today's Focus card data ---
$macroTotals = $macroTotals ?? ['protein' => 0, 'carbs' => 0, 'fat' => 0];
$macroGoals = $macroGoals ?? getMacroGoalsFromCalorieGoal(!empty($userGoal) ? (int) $userGoal : null);
$hasCalorieGoal = !empty($userGoal);
$calorieDiff = $hasCalorieGoal ? ((int) $userGoal - (int) $totalCalories) : null;

if (!$hasCalorieGoal) {
    $focusTitle = t_raw('dashboard.focus.title.set_goal');
    $focusCopy = t_raw('dashboard.focus.copy.set_goal');
    $focusTone = 'neutral';
} elseif ($calorieDiff > 0) {
    $focusTitle = t_raw('dashboard.focus.title.left', ['n' => number_format($calorieDiff)]);
    $focusCopy = t_raw('dashboard.focus.copy.left');
    $focusTone = 'good';
} elseif ($calorieDiff === 0) {
    $focusTitle = t_raw('dashboard.focus.title.matched');
    $focusCopy = t_raw('dashboard.focus.copy.matched');
    $focusTone = 'good';
} else {
    $focusTitle = t_raw('dashboard.focus.title.over', ['n' => number_format(abs($calorieDiff))]);
    $focusCopy = t_raw('dashboard.focus.copy.over');
    $focusTone = 'alert';
}

$macroFocusDefs = [
    'protein' => ['label' => t_raw('dashboard.macros.protein'), 'icon' => 'fa-drumstick-bite'],
    'carbs' => ['label' => t_raw('dashboard.macros.carbs'), 'icon' => 'fa-bread-slice'],
    'fat' => ['label' => t_raw('dashboard.macros.fat'), 'icon' => 'fa-cheese'],
];
$macroFocusKey = null;
$macroFocusGap = 0;
$macroFocusRatio = -1;
foreach ($macroFocusDefs as $key => $def) {
    $goal = (float) ($macroGoals[$key] ?? 0);
    $current = (float) ($macroTotals[$key] ?? 0);
    $gap = max(0, $goal - $current);
    $ratio = $goal > 0 ? $gap / $goal : 0;
    if ($gap > 0 && $ratio > $macroFocusRatio) {
        $macroFocusKey = $key;
        $macroFocusGap = $gap;
        $macroFocusRatio = $ratio;
    }
}

if ($macroFocusKey) {
    $macroFocusIcon = $macroFocusDefs[$macroFocusKey]['icon'];
    $macroFocusText = $macroFocusDefs[$macroFocusKey]['label'] . ' +' . number_format((int) round($macroFocusGap)) . 'g';
} elseif ($hasCalorieGoal) {
    $macroFocusIcon = 'fa-circle-check';
    $macroFocusText = t_raw('dashboard.focus.on_track');
} else {
    $macroFocusIcon = 'fa-bullseye';
    $macroFocusText = t_raw('dashboard.focus.needs_goal');
}

if ($isLoggedIn) {
    // FETCH FULL WEIGHT HISTORY (Cho Modal)
    $weightHistoryList = [];
    try {
        // Lấy 30 lần cân gần nhất
        $stmt = $pdo->prepare("SELECT * FROM weight_log WHERE user_id = ? ORDER BY date_logged DESC, weight_id DESC LIMIT 30");
        $stmt->execute([$user['user_id']]);
        $weightHistoryList = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
    }
}

// Calculate BMI dynamically
$actualWeight = $currentWeight > 0 ? (float)$currentWeight : (!empty($userWeight) ? (float)$userWeight : 0);
$actualHeight = !empty($userHeight) ? (float)$userHeight : 0;
$bmi = 0;
$bmiClass = '';
if ($actualWeight > 0 && $actualHeight > 0) {
    $heightInMeters = $actualHeight / 100;
    $bmi = round($actualWeight / ($heightInMeters * $heightInMeters), 1);
    if ($bmi < 18.5) {
        $bmiClass = t_raw('dashboard.bmi.under');
    } elseif ($bmi < 25.0) {
        $bmiClass = t_raw('dashboard.bmi.normal');
    } elseif ($bmi < 30.0) {
        $bmiClass = t_raw('dashboard.bmi.over');
    } else {
        $bmiClass = t_raw('dashboard.bmi.obese');
    }
}
?>

<!DOCTYPE html>
<html lang="<?= html_lang_attr() ?>"
    data-theme="<?php echo isset($_SESSION['user']) ? ($_SESSION['user']['theme_preference'] ?? 'system') : 'system'; ?>">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= t('dashboard.title_tag') ?></title>
    <?php
    $pageComponents = ['sidebar', 'fab'];
    $pageCss = ['css/dashboard.css', 'css/pages/dashboard-home.css'];
    include PROJECT_ROOT . 'views/head_css.php';
    ?>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="https://kit.fontawesome.com/b94f65ead2.js" crossorigin="anonymous"></script>
</head>

<body class="<?= htmlspecialchars($bodyClass ?? '', ENT_QUOTES) ?>">
    <?php include PROJECT_ROOT . 'views/header.php'; ?>
    <?php include PROJECT_ROOT . 'dashboard/views/sidebar.php'; ?>
    <?php include PROJECT_ROOT . 'dashboard/views/right-sidebar.php'; ?>

    <main class="dashboard">

        <?php if (!$isLoggedIn): ?>
            <div class="demo-banner">
                <i class="fas fa-flask"></i>
                <span><strong><?= t('dashboard.demo.heading') ?></strong> <?= t('dashboard.demo.body') ?></span>
                <a href="<?= BASE_URL ?>signup.php" class="demo-banner-cta"><?= t('dashboard.demo.cta') ?></a>
            </div>
        <?php endif; ?>

        <div class="welcome-banner">
            <div class="welcome-text">
                <h2><?= t('dashboard.welcome.greeting', ['name' => htmlspecialchars($user['first_name'] ?? 'Champion')]) ?></h2>
                <p><?php
                    if (!$hasCalorieGoal) {
                        echo t('dashboard.welcome.set_goal');
                    } elseif ($totalCalories > 0) {
                        if ($progressPercentage >= 100) {
                            echo t('dashboard.welcome.achieved');
                        } else {
                            // Contains <strong> — render raw so the tag is preserved.
                            echo t_raw('dashboard.welcome.progress', ['pct' => (int) $progressPercentage]);
                        }
                    } else {
                        echo t('dashboard.welcome.no_intake');
                    }
                ?></p>
            </div>
            <div class="welcome-stats">
                <div class="welcome-stat-chip">
                    <i class="fas fa-bullseye" style="color: #60a5fa;"></i>
                    <span><?= t('dashboard.welcome.goal_met', ['pct' => (int) $progressPercentage]) ?></span>
                </div>
                <div class="welcome-stat-chip">
                    <i class="fas fa-trophy" style="color: #FFD700;"></i>
                    <span><?= t('dashboard.welcome.level_active') ?></span>
                </div>
            </div>
        </div>

        <div class="flex-row">
            <div class="flex">
                <section class="progress-widget">
                    <div class="progress-card">
                        <div class="progress-card-content">
                            <h3><?= t('dashboard.today.heading') ?></h3>
                            <div class="progress-value">
                                <span class="<?= $statusClass ?>"><?= t('dashboard.today.calories', ['n' => $totalCalories]) ?></span>
                            </div>
                            <div class="progress-bar">
                                <div class="progress-fill <?= htmlspecialchars($statusClass) ?>" id="progressFill" style="width: 0%;"></div>
                            </div>
                            <div class="progress-labels">
                                <span><?= t('dashboard.today.goal') ?></span>
                                <span><?php echo $userGoal; ?></span>
                            </div>
                        </div>
                    </div>
                    <script>
                        document.addEventListener('DOMContentLoaded', function () {
                            setTimeout(function () {
                                document.getElementById('progressFill').style.width = '<?php echo $progressPercentage; ?>%';
                            }, 300);
                        });
                    </script>
                </section>


                <!-- STATS HUB CARD -->
                <section class="dashboard-card stats-hub-card" id="statsHubCard">
                    <!-- 3D Segmented Tabs Switcher -->
                    <div class="stats-hub-tabs">
                        <button type="button" class="tab-btn active" onclick="switchStatsTab('intake')">
                            <i class="fas fa-chart-bar"></i> <?= t('dashboard.tabs.nutrition') ?>
                        </button>
                        <button type="button" class="tab-btn" onclick="switchStatsTab('weight')">
                            <i class="fas fa-weight"></i> <?= t('dashboard.tabs.weight') ?>
                        </button>
                        <button type="button" class="tab-btn" onclick="switchStatsTab('meals')">
                            <i class="fas fa-pie-chart"></i> <?= t('dashboard.tabs.meals') ?>
                        </button>
                    </div>

                    <!-- TAB PANES -->
                    <!-- Pane 1: Intake (Dinh dưỡng) -->
                    <div class="chart-wrapper-tab active" id="tabPane-intake">
                        <section class="chart-section history-card">
                            <div class="chart-header-row">
                                <h4><i class="fas fa-chart-bar"></i> <?= t('dashboard.last7.heading') ?></h4>
                                <div class="chart-average-badge">
                                    <span class="label"><?= t('dashboard.last7.avg') ?></span>
                                    <span class="value"><?php echo $averageCalories; ?></span>
                                </div>
                            </div>
                            <div class="chart-container-wrapper">
                                <canvas id="historyChart"></canvas>
                            </div>
                            <div class="macros-trend-wrap">
                                <div class="macros-trend-header">
                                    <h5><i class="fas fa-layer-group"></i> <?= t('dashboard.macros_trend.heading') ?></h5>
                                    <div class="macros-trend-legend">
                                        <span><i class="dot p"></i> <?= t('dashboard.macros.protein') ?></span>
                                        <span><i class="dot c"></i> <?= t('dashboard.macros.carbs') ?></span>
                                        <span><i class="dot f"></i> <?= t('dashboard.macros.fat') ?></span>
                                    </div>
                                </div>
                                <div class="chart-container-wrapper macros-trend-canvas">
                                    <canvas id="macrosTrendChart"></canvas>
                                </div>
                            </div>
                            <script>
                                document.addEventListener('DOMContentLoaded', () => {
                                    // 1. Intake History Chart
                                    const hCtx = document.getElementById('historyChart').getContext('2d');
                                    let gradientH = hCtx.createLinearGradient(0, 0, 0, 150);
                                    gradientH.addColorStop(0, 'rgba(88, 204, 2, 0.2)');
                                    gradientH.addColorStop(1, 'rgba(88, 204, 2, 0.0)');

                                    new Chart(hCtx, {
                                        type: 'line',
                                        data: {
                                            labels: <?php echo json_encode($historyLabels); ?>,
                                            datasets: [{
                                                label: 'Calories',
                                                data: <?php echo json_encode($historyData); ?>,
                                                borderColor: '#58cc02',
                                                backgroundColor: gradientH,
                                                borderWidth: 3,
                                                pointBackgroundColor: '#fff',
                                                pointBorderColor: '#58cc02',
                                                pointRadius: 4,
                                                pointHoverRadius: 6,
                                                fill: true,
                                                tension: 0.4
                                            }]
                                        },
                                        options: {
                                            responsive: true,
                                            maintainAspectRatio: false,
                                            plugins: { legend: { display: false } },
                                            scales: {
                                                y: { display: false },
                                                x: { grid: { display: false }, border: { display: false }, ticks: { font: { size: 10 }, color: '#999' } }
                                            }
                                        }
                                    });

                                    // 2. Macros Trend Chart
                                    const mtCtx = document.getElementById('macrosTrendChart');
                                    if (mtCtx && typeof Chart !== 'undefined') {
                                        const rootStyles = getComputedStyle(document.documentElement);
                                        const macroColors = {
                                            protein: (rootStyles.getPropertyValue('--color-primary') || '#58CC02').trim(),
                                            carbs: (rootStyles.getPropertyValue('--color-secondary') || '#1CB0F6').trim(),
                                            fat: (rootStyles.getPropertyValue('--color-accent') || '#FF9600').trim()
                                        };
                                        const textColor = (rootStyles.getPropertyValue('--color-text-secondary') || '#64748b').trim();
                                        const gridColor = (rootStyles.getPropertyValue('--color-border-subtle') || '#f1f5f9').trim();

                                        new Chart(mtCtx.getContext('2d'), {
                                            type: 'bar',
                                            data: {
                                                labels: <?php echo json_encode($historyLabels); ?>,
                                                datasets: [
                                                    {
                                                        label: 'Protein',
                                                        data: <?php echo json_encode($historyProtein); ?>,
                                                        backgroundColor: macroColors.protein,
                                                        borderRadius: 8,
                                                        maxBarThickness: 12,
                                                        categoryPercentage: 0.58,
                                                        barPercentage: 0.82
                                                    },
                                                    {
                                                        label: 'Carbs',
                                                        data: <?php echo json_encode($historyCarbs); ?>,
                                                        backgroundColor: macroColors.carbs,
                                                        borderRadius: 8,
                                                        maxBarThickness: 12,
                                                        categoryPercentage: 0.58,
                                                        barPercentage: 0.82
                                                    },
                                                    {
                                                        label: 'Fat',
                                                        data: <?php echo json_encode($historyFat); ?>,
                                                        backgroundColor: macroColors.fat,
                                                        borderRadius: 8,
                                                        maxBarThickness: 12,
                                                        categoryPercentage: 0.58,
                                                        barPercentage: 0.82
                                                    }
                                                ]
                                            },
                                            options: {
                                                responsive: true,
                                                maintainAspectRatio: false,
                                                layout: { padding: { top: 6, right: 6, bottom: 0, left: 0 } },
                                                interaction: { mode: 'index', intersect: false },
                                                plugins: {
                                                    legend: { display: false },
                                                    tooltip: {
                                                        mode: 'index',
                                                        intersect: false,
                                                        backgroundColor: 'rgba(15, 23, 42, 0.92)',
                                                        titleColor: '#ffffff',
                                                        bodyColor: '#e2e8f0',
                                                        borderColor: 'rgba(255, 255, 255, 0.12)',
                                                        borderWidth: 1,
                                                        padding: 10,
                                                        displayColors: true,
                                                        callbacks: {
                                                            label: (ctx) => ` ${ctx.dataset.label}: ${Math.round(ctx.parsed.y)}g`
                                                        }
                                                    }
                                                },
                                                scales: {
                                                    x: {
                                                        grid: { display: false },
                                                        border: { display: false },
                                                        ticks: {
                                                            color: textColor,
                                                            font: { size: 11, weight: '600' }
                                                        }
                                                    },
                                                    y: {
                                                        beginAtZero: true,
                                                        grid: { color: gridColor },
                                                        border: { display: false },
                                                        ticks: {
                                                            color: textColor,
                                                            maxTicksLimit: 4,
                                                            padding: 8,
                                                            callback: (value) => `${value}g`
                                                        }
                                                    }
                                                }
                                            }
                                        });
                                    }
                                });
                            </script>
                        </section>
                    </div>

                    <!-- Pane 2: Weight (Cân nặng) -->
                    <div class="chart-wrapper-tab" id="tabPane-weight">
                        <section class="dashboard-card weight-card">
                            <div class="card-header-row">
                                <div class="weight-info">
                                    <h3><?= t('dashboard.weight.heading') ?></h3>
                                    <div class="current-weight">
                                        <span class="weight-val"><?php echo $currentWeight > 0 ? $currentWeight : '--'; ?></span>
                                        <span class="weight-unit">kg</span>
                                        <?php if ($weightTrend !== 'flat'): ?>
                                            <span class="trend-badge <?php echo $weightTrend; ?>">
                                                <i class="fas fa-arrow-<?php echo $weightTrend; ?>"></i>
                                                <?php echo abs($weightDiff); ?> kg
                                            </span>
                                        <?php endif; ?>
                                    </div>
                                </div>

                                <div class="action-buttons" style="display: flex; gap: 8px;">
                                    <button class="btn-icon-small btn-secondary" onclick="openWeightHistoryModal()"
                                        title="<?= t('dashboard.weight.view_history') ?>">
                                        <i class="fas fa-list-ul"></i>
                                    </button>
                                    <button class="btn-icon-small" onclick="openWeightModal()" title="<?= t('dashboard.weight.log_weight') ?>">
                                        <i class="fas fa-plus"></i>
                                    </button>
                                </div>
                            </div>

                            <div class="weight-chart-wrapper">
                                <canvas id="weightChart"></canvas>
                            </div>

                            <script>
                                document.addEventListener('DOMContentLoaded', () => {
                                    const ctxW = document.getElementById('weightChart').getContext('2d');

                                    let gradientW = ctxW.createLinearGradient(0, 0, 0, 150);
                                    gradientW.addColorStop(0, 'rgba(155, 89, 182, 0.2)');
                                    gradientW.addColorStop(1, 'rgba(155, 89, 182, 0.0)');

                                    new Chart(ctxW, {
                                        type: 'line',
                                        data: {
                                            labels: <?php echo json_encode($weightLabels); ?>,
                                            datasets: [{
                                                label: 'Weight',
                                                data: <?php echo json_encode($weightData); ?>,
                                                borderColor: '#9b59b6',
                                                backgroundColor: gradientW,
                                                borderWidth: 3,
                                                pointBackgroundColor: '#fff',
                                                pointBorderColor: '#9b59b6',
                                                pointRadius: 4,
                                                pointHoverRadius: 6,
                                                fill: true,
                                                tension: 0.4
                                            }]
                                        },
                                        options: {
                                            responsive: true,
                                            maintainAspectRatio: false,
                                            plugins: { legend: { display: false } },
                                            scales: {
                                                y: {
                                                    display: false,
                                                    min: (<?php echo count($weightData); ?> > 0) ? Math.min(...<?php echo json_encode($weightData); ?>) - 1 : 40,
                                                    max: (<?php echo count($weightData); ?> > 0) ? Math.max(...<?php echo json_encode($weightData); ?>) + 1 : 120
                                                },
                                                x: {
                                                    grid: { display: false },
                                                    border: { display: false },
                                                    ticks: { font: { size: 10 }, color: '#999' }
                                                }
                                            }
                                        }
                                    });
                                });
                            </script>
                        </section>
                    </div>

                    <!-- Pane 3: Meals (Bữa ăn) -->
                    <div class="chart-wrapper-tab" id="tabPane-meals">
                        <section class="chart-section meals-card">
                            <div class="card-header">
                                <h4><i class="fas fa-utensils"></i> <?= t('dashboard.intake.heading') ?></h4>
                            </div>
                            <div class="doughnut-container">
                                <canvas id="mealCategoriesChart"></canvas>
                                <div class="doughnut-center-text">
                                    <span class="center-val"><?php echo $totalCalories; ?></span>
                                    <span class="center-label"><?= t('common.kcal') ?></span>
                                </div>
                            </div>

                            <?php
                            $mealConfig = [
                                'breakfast' => ['icon' => 'fa-mug-hot', 'color' => '#FF6384', 'label' => t_raw('dashboard.meal.breakfast')],
                                'lunch' => ['icon' => 'fa-hamburger', 'color' => '#36A2EB', 'label' => t_raw('dashboard.meal.lunch')],
                                'dinner' => ['icon' => 'fa-utensils', 'color' => '#FFCE56', 'label' => t_raw('dashboard.meal.dinner')],
                                'snack' => ['icon' => 'fa-apple-alt', 'color' => '#4BC0C0', 'label' => t_raw('dashboard.meal.snack')]
                            ];
                            ?>

                            <script>
                                document.addEventListener('DOMContentLoaded', () => {
                                    const ctx = document.getElementById('mealCategoriesChart').getContext('2d');
                                    new Chart(ctx, {
                                        type: 'doughnut',
                                        data: {
                                            labels: ['Breakfast', 'Lunch', 'Dinner', 'Snack'],
                                            datasets: [{
                                                data: [
                                                    <?php echo (int)($mealCategoryData['Breakfast'] ?? 0); ?>,
                                                    <?php echo (int)($mealCategoryData['Lunch'] ?? 0); ?>,
                                                    <?php echo (int)($mealCategoryData['Dinner'] ?? 0); ?>,
                                                    <?php echo (int)($mealCategoryData['Snack'] ?? 0); ?>
                                                ],
                                                backgroundColor: ['#FF6384', '#36A2EB', '#FFCE56', '#4BC0C0'],
                                                hoverOffset: 4,
                                                borderWidth: 2,
                                                borderColor: 'var(--color-surface)'
                                            }]
                                        },
                                        options: { responsive: true, maintainAspectRatio: false, cutout: '80%', plugins: { legend: { display: false } } }
                                    });
                                });
                            </script>

                            <div class="meal-list-container">
                                <?php foreach ($intakeLog as $meal):
                                    $cat = strtolower($meal['meal_category']);
                                    $config = $mealConfig[$cat] ?? ['icon' => 'fa-circle', 'color' => '#999', 'label' => $cat];
                                    ?>
                                    <div class="meal-item" style="--meal-accent-color: <?php echo $config['color']; ?>; --meal-hover-bg: <?php echo $config['color']; ?>08;">
                                        <div class="meal-icon-box"
                                            style="background-color: <?php echo $config['color']; ?>20; color: <?php echo $config['color']; ?>;">
                                            <i class="fas <?php echo $config['icon']; ?>"></i>
                                        </div>
                                        <div class="meal-info">
                                            <span class="meal-name"><?php echo htmlspecialchars($meal['food_item']); ?></span>
                                            <span class="meal-type"
                                                style="color: <?php echo $config['color']; ?>"><?php echo htmlspecialchars($config['label']); ?></span>
                                        </div>
                                        <div class="meal-calories">
                                            <span class="cal-val"><?php echo htmlspecialchars($meal['calories']); ?></span>
                                            <small><?= t('common.kcal') ?></small>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </section>
                    </div>
                </section>
            </div>

            <!-- COLUMN 2: Habit (Streak) & Focus Bento Grid -->
            <div class="flex dashboard-bento-column">
                <div class="bento-grid-mobile">
                    <!-- STREAK CARD -->
                    <section class="dashboard-card streak-card" id="streakCard">
                        <div class="streak-header">
                            <div class="streak-flame-wrapper">
                                <i class="fas fa-fire streak-flame" id="streakFlame"
                                    style="color: <?= htmlspecialchars($streakFlameColor) ?>;"></i>
                            </div>
                            <div class="streak-info">
                                <h3><?= t('dashboard.streak.heading') ?></h3>
                                <div class="streak-main">
                                    <span class="streak-number" id="streakNumber"><?= (int) $streakDays ?></span>
                                    <span class="streak-label"><?= t('dashboard.streak.days') ?></span>
                                </div>
                            </div>
                        </div>

                        <div class="streak-body">
                            <p class="streak-message" id="streakMessage">
                                <?= htmlspecialchars($streakMessage) ?>
                            </p>

                            <!-- Progress to next milestone -->
                            <div class="streak-progress">
                                <div class="streak-progress-bar">
                                    <div class="streak-progress-fill" id="streakProgressFill"
                                        style="width: <?= (int) $streakProgress ?>%;"></div>
                                </div>
                                <div class="streak-progress-text">
                                    <span><?= htmlspecialchars($milestoneText) ?></span>
                                </div>
                            </div>

                            <!-- STREAK FREEZE WIDGET -->
                            <div class="streak-freeze-widget">
                                <div class="freeze-status">
                                    <i class="fas fa-snowflake freeze-icon"></i>
                                    <span class="freeze-text"><?= t_raw('dashboard.streak.freeze_count', ['n' => (int)($userStreak['streak_freezes'] ?? 0)]) ?></span>
                                </div>
                                <button class="btn btn-buy-freeze" id="btnBuyFreeze" onclick="purchaseStreakFreeze()">
                                    <span><?= t('dashboard.streak.buy_freeze') ?></span>
                                </button>
                            </div>
                        </div>
                    </section>

                    <!-- TODAY'S FOCUS CARD -->
                    <section class="dashboard-card focus-card">
                        <div class="focus-card-header">
                            <span class="focus-kicker"><i class="fas fa-compass"></i> <?= t('dashboard.focus.kicker') ?></span>
                            <span class="focus-status <?= htmlspecialchars($focusTone) ?>">
                                <?= $status === 'Overlimit' ? t('dashboard.focus.status.adjust') : ($hasCalorieGoal ? t('dashboard.focus.status.active') : t('dashboard.focus.status.setup')) ?>
                            </span>
                        </div>

                        <div class="focus-main">
                            <strong><?= htmlspecialchars($focusTitle) ?></strong>
                            <p><?= htmlspecialchars($focusCopy) ?></p>
                        </div>

                        <div class="focus-insights">
                            <div class="focus-insight macro-focus <?= htmlspecialchars($macroFocusKey ?? 'neutral') ?>">
                                <i class="fas <?= htmlspecialchars($macroFocusIcon) ?>"></i>
                                <div>
                                    <span><?= t('dashboard.focus.macro_focus') ?></span>
                                    <strong><?= htmlspecialchars($macroFocusText) ?></strong>
                                </div>
                            </div>
                            <div class="focus-insight bmi-focus">
                                <i class="fas fa-heart-pulse"></i>
                                <div>
                                    <span><?= t('dashboard.focus.bmi_status') ?></span>
                                    <strong><?= $bmi > 0 ? htmlspecialchars($bmi) . ' (' . htmlspecialchars($bmiClass) . ')' : t('dashboard.focus.needs_info') ?></strong>
                                </div>
                            </div>
                        </div>

                        <div class="focus-actions">
                            <?php if ($hasCalorieGoal): ?>
                                <a href="dashboard-plan.php" class="focus-btn primary">
                                    <i class="fas fa-route"></i> <?= t('dashboard.focus.view_plan') ?>
                                </a>
                                <button type="button" class="focus-btn ghost"
                                    onclick="<?php echo $isLoggedIn ? 'openGoalModal()' : "window.location.href='" . BASE_URL . "login.php'"; ?>">
                                    <i class="fas fa-bullseye"></i> <?= t('dashboard.focus.adjust_goal') ?>
                                </button>
                            <?php else: ?>
                                <button type="button" class="focus-btn primary"
                                    onclick="<?php echo $isLoggedIn ? 'openGoalModal()' : "window.location.href='" . BASE_URL . "login.php'"; ?>">
                                    <i class="fas fa-bullseye"></i> <?= t('dashboard.right.set_goal') ?>
                                </button>
                                <a href="dashboard-plan.php" class="focus-btn ghost">
                                    <i class="fas fa-route"></i> <?= t('dashboard.focus.view_plan') ?>
                                </a>
                            <?php endif; ?>
                        </div>
                    </section>
                </div>
            </div>
        </div>
    </main>

    <?php if ($isLoggedIn):
        include PROJECT_ROOT . 'dashboard/views/quick-log-fab.php';
    endif; ?>

    <?php if ($isLoggedIn): ?>
        <div id="goalModal" class="modal-overlay">
            <div class="modal-box">
                <div class="modal-header">
                    <h3><?= t('dashboard.modal.set_goal_title') ?></h3><button class="close-modal" onclick="closeGoalModal()" aria-label="<?= t('common.close') ?>">&times;</button>
                </div>
                <form action="handlers/update_goal.php" method="POST">
                    <div class="modal-body">
                        <div class="form-group large-input-group">
                            <label for="modal_calorie_goal"><?= t('dashboard.modal.calorie_goal') ?></label>
                            <div class="input-wrapper-lg">
                                <i class="fas fa-bullseye input-icon-lg"></i>
                                <input type="number" id="modal_calorie_goal" name="calorie_goal"
                                    value="<?php echo htmlspecialchars($userGoal); ?>" required>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn-cancel" onclick="closeGoalModal()"><?= t('common.cancel') ?></button>
                        <button type="submit" class="btn-save"><?= t('dashboard.modal.save_goal') ?></button>
                    </div>
                </form>
            </div>
        </div>

        <div id="weightModal" class="modal-overlay">
            <div class="modal-box">
                <div class="modal-header">
                    <h3><?= t('dashboard.modal.log_weight_title') ?></h3>
                    <button class="close-modal" onclick="closeWeightModal()" aria-label="<?= t('common.close') ?>">&times;</button>
                </div>

                <form action="handlers/log_weight.php" method="POST">
                    <div class="modal-body">
                        <p class="modal-desc"><?= t('dashboard.modal.log_weight_desc') ?></p>

                        <div class="form-group large-input-group">
                            <label for="weight_input"><?= t('dashboard.modal.weight_kg') ?></label>
                            <div class="input-wrapper-lg">
                                <i class="fas fa-weight input-icon-lg"></i>
                                <input type="number" id="weight_input" name="weight" step="0.1" min="1" max="500" required
                                    placeholder="0.0">
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="weight_date"><?= t('dashboard.modal.date') ?></label>
                            <input type="date" id="weight_date" name="weight_date" value="<?php echo date('Y-m-d'); ?>">
                        </div>
                    </div>

                    <div class="modal-footer">
                        <button type="button" class="btn-cancel" onclick="closeWeightModal()"><?= t('common.cancel') ?></button>
                        <button type="submit" class="btn-save"><?= t('dashboard.modal.save_weight') ?></button>
                    </div>
                </form>
            </div>
        </div>

        <div id="weightHistoryModal" class="modal-overlay">
            <div class="modal-box">
                <div class="modal-header">
                    <h3><?= t('dashboard.modal.weight_history') ?></h3>
                    <button class="close-modal" onclick="closeWeightHistoryModal()" aria-label="<?= t('common.close') ?>">&times;</button>
                </div>

                <div class="modal-body">
                    <div class="weight-history-list">
                        <?php if (empty($weightHistoryList)): ?>
                            <p class="weight-history-empty"><?= t('dashboard.modal.no_records') ?></p>
                        <?php else: ?>
                            <table>
                                <tbody id="weightTableBody">
                                    <?php foreach ($weightHistoryList as $wLog): ?>
                                        <tr data-id="<?= $wLog['weight_id'] ?>">
                                            <td>
                                                <div class="weight-history-value">
                                                    <?= htmlspecialchars($wLog['weight']) ?> kg
                                                </div>
                                            </td>
                                            <td>
                                                <div class="weight-history-date">
                                                    <?= date('d M, Y', strtotime($wLog['date_logged'])) ?>
                                                </div>
                                            </td>
                                            <td style="text-align: right;">
                                                <button class="btn-delete-icon" onclick="deleteWeight(<?= $wLog['weight_id'] ?>)">
                                                    <i class="fas fa-trash-alt"></i>
                                                </button>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <?php include PROJECT_ROOT . 'dashboard/views/_confirm-delete-modal.php'; ?>
        <script>
            // --- 1. MODAL MANAGEMENT (Unified) ---

            // Function to open any modal by ID
            function openModal(modalId) {
                const modal = document.getElementById(modalId);
                if (modal) {
                    modal.classList.add('active');
                    // Auto-focus input if it exists (nice UX)
                    const input = modal.querySelector('input[type="number"]');
                    if (input) setTimeout(() => input.focus(), 100);
                }
            }

            // Function to close any modal by ID
            function closeModal(modalId) {
                const modal = document.getElementById(modalId);
                if (modal) {
                    modal.classList.remove('active');
                }
            }

            // Specific Open Functions (called by your buttons)
            function openGoalModal() { openModal('goalModal'); }
            function closeGoalModal() { closeModal('goalModal'); }

            function openWeightModal() { openModal('weightModal'); }
            function closeWeightModal() { closeModal('weightModal'); }

            function openWeightHistoryModal() { openModal('weightHistoryModal'); }
            function closeWeightHistoryModal() { closeModal('weightHistoryModal'); }

            // Unified Window Click Handler (Closes any active modal when clicking outside)
            window.onclick = function (event) {
                if (event.target.classList.contains('modal-overlay')) {
                    event.target.classList.remove('active');
                }
            }

            // --- 2. WEIGHT DELETE FUNCTION ---
            let weightDeleteId = null;
            const confirmDeleteModal = document.getElementById('confirmDeleteModal');
            const closeConfirmBtn = document.getElementById('closeConfirmDeleteModal');
            const cancelConfirmBtn = document.getElementById('cancelDeleteBtn');
            const doConfirmDeleteBtn = document.getElementById('confirmDeleteBtn');

            function closeWeightDeleteConfirmModal() {
                if (confirmDeleteModal) confirmDeleteModal.classList.remove('active');
                weightDeleteId = null;
            }

            if (confirmDeleteModal) {
                closeConfirmBtn.addEventListener('click', closeWeightDeleteConfirmModal);
                cancelConfirmBtn.addEventListener('click', closeWeightDeleteConfirmModal);

                // Close modal if clicking overlay background
                confirmDeleteModal.addEventListener('click', e => {
                    if (e.target === confirmDeleteModal) {
                        closeWeightDeleteConfirmModal();
                    }
                });
            }

            const __weightDeleteI18n = {
                failed: <?= json_encode(t_raw('dashboard.modal.failed_delete')) ?>,
                connection: <?= json_encode(t_raw('dashboard.modal.connection_error')) ?>
            };

            function deleteWeight(id) {
                weightDeleteId = id;
                if (confirmDeleteModal) {
                    confirmDeleteModal.classList.add('active');
                }
            }

            if (doConfirmDeleteBtn) {
                doConfirmDeleteBtn.addEventListener('click', async () => {
                    if (weightDeleteId === null) return;

                    const id = weightDeleteId;
                    doConfirmDeleteBtn.disabled = true;

                    const formData = new FormData();
                    formData.append('weight_id', id);

                    try {
                        const res = await fetch('handlers/delete_weight.php', {
                            method: 'POST',
                            body: formData
                        });
                        const data = await res.json();

                        if (data.ok) {
                            // Remove row from UI immediately
                            const row = document.querySelector(`tr[data-id="${id}"]`);
                            if (row) {
                                row.style.opacity = '0';
                                setTimeout(() => row.remove(), 300);
                            }
                            closeWeightDeleteConfirmModal();
                            // Reload to update chart
                            setTimeout(() => location.reload(), 500);
                        } else {
                            alert(data.error || __weightDeleteI18n.failed);
                        }
                    } catch (err) {
                        console.error(err);
                        alert(__weightDeleteI18n.connection);
                    } finally {
                        doConfirmDeleteBtn.disabled = false;
                    }
                });
            }

            // --- 3. PROGRESS BAR ANIMATION ---
            document.addEventListener('DOMContentLoaded', () => {
                setTimeout(() => {
                    const fill = document.getElementById('progressFill');
                    if (fill) fill.style.width = '<?php echo $progressPercentage; ?>%';
                }, 100);
            });
        </script>
    <?php endif; ?>

    <?php if ($isLoggedIn && $brokenStreak > 0): ?>
        <!-- STREAK RESCUE OVERLAY MODAL -->
        <div class="streak-rescue-overlay active" id="streakRescueOverlay">
            <div class="streak-rescue-modal">
                <div class="rescue-icon-wrapper">
                    <i class="fas fa-snowflake"></i>
                </div>
                <h2><?= t('dashboard.streak.rescue_title') ?></h2>
                <p>
                    <?= t_raw('dashboard.streak.rescue_body', ['broken_streak' => $brokenStreak]) ?>
                </p>
                <div class="rescue-actions">
                    <button class="btn btn-rescue-action btn-restore" onclick="restoreStreak()">
                        <span><?= t('dashboard.streak.rescue_btn') ?></span>
                    </button>
                    <button class="btn btn-rescue-action btn-dismiss-broken" onclick="dismissRescueModal()">
                        <span><?= t('dashboard.streak.dismiss_btn') ?></span>
                    </button>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <script src="https://cdn.jsdelivr.net/npm/canvas-confetti@1.6.0/dist/confetti.browser.min.js"></script>
    <script>
        // Streak flame celebration — quick scale + glow pulse.
        // Defined BEFORE logging-toast partial so its auto-fire can detect it.
        function celebrateStreak() {
            const flame = document.getElementById('streakFlame');
            const card = document.getElementById('streakCard');
            if (!flame || !card) return;

            flame.style.transform = 'scale(1.3)';
            flame.style.filter = 'drop-shadow(0 0 12px #ff9600)';
            card.style.transition = 'transform 0.2s ease';
            card.style.transform = 'scale(1.02)';

            setTimeout(() => {
                flame.style.transform = '';
                flame.style.filter = '';
                card.style.transform = '';
            }, 600);
        }

        // AJAX Fetch helper for streak actions
        async function callStreakAction(action) {
            const formData = new FormData();
            formData.append('action', action);

            try {
                const response = await fetch('handlers/streak_actions.php', {
                    method: 'POST',
                    body: formData,
                    headers: {
                        'X-Requested-With': 'fetch'
                    }
                });
                return await response.json();
            } catch (err) {
                console.error(err);
                return { ok: false, error: 'Lỗi kết nối mạng.' };
            }
        }

        function showStreakNotice(message, subtext, type) {
            if (typeof showLoggingToast === 'function') {
                showLoggingToast(message, subtext || '', type || 'success');
                return;
            }

            // Fallback for unexpected partial-load failures: still avoid browser alert().
            const notice = document.createElement('div');
            notice.className = 'logging-toast show ' + (type || 'success');
            notice.setAttribute('role', 'status');
            notice.setAttribute('aria-live', 'polite');
            notice.innerHTML =
                '<div class="toast-content">' +
                    '<div class="toast-icon"><i class="fas fa-info-circle"></i></div>' +
                    '<div class="toast-text"><span></span><span class="toast-subtext"></span></div>' +
                '</div>';
            notice.querySelector('.toast-text span').textContent = message;
            notice.querySelector('.toast-subtext').textContent = subtext || '';
            document.body.appendChild(notice);
            setTimeout(() => notice.remove(), 3500);
        }

        // Purchase Streak Freeze directly from the card; no confirmation modal.
        async function purchaseStreakFreeze() {
            const btn = document.getElementById('btnBuyFreeze');
            const originalHtml = btn ? btn.innerHTML : '';
            if (btn) {
                btn.disabled = true;
                btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';
            }

            const data = await callStreakAction('purchase_freeze');
            if (data.ok) {
                const countEl = document.getElementById('freezeCount');
                if (countEl) countEl.textContent = data.streak_freezes;

                if (data.xp_summary && window.updateXpChip) {
                    window.updateXpChip(data.xp_summary);
                }

                showStreakNotice(data.message || 'Streak Freeze equipped! 🥶', '+1 Streak Freeze', 'success');
            } else {
                showStreakNotice(data.error || 'Failed to purchase.', '', 'error');
            }

            if (btn) {
                btn.disabled = false;
                btn.innerHTML = originalHtml || '<span><?= t('dashboard.streak.buy_freeze') ?></span>';
            }
        }

        // Restore Broken Streak from modal
        async function restoreStreak() {
            const data = await callStreakAction('restore_streak');
            if (data.ok) {
                // Hide Modal
                const overlay = document.getElementById('streakRescueOverlay');
                if (overlay) overlay.classList.remove('active');

                // Update Streak count display
                updateStreakDisplay(data.logging_streak);

                // Trigger streak flame celebration!
                celebrateStreak();

                // Trigger confetti!
                if (typeof confetti === 'function') {
                    try {
                        confetti({
                            particleCount: 150,
                            spread: 80,
                            origin: { y: 0.6 }
                        });
                    } catch (e) {}
                }

                alert(data.message || 'Khôi phục chuỗi thành công!');

                // Reload page to update header bar XP displays
                setTimeout(() => {
                    location.reload();
                }, 1200);
            } else {
                alert(data.error || 'Không thể khôi phục chuỗi.');
            }
        }

        // Dismiss broken streak modal
        async function dismissRescueModal() {
            const data = await callStreakAction('dismiss_broken');
            if (data.ok) {
                // Hide Modal
                const overlay = document.getElementById('streakRescueOverlay');
                if (overlay) overlay.classList.remove('active');

                // Reset display
                updateStreakDisplay(1);

                // Reload page to update header bar displays
                setTimeout(() => {
                    location.reload();
                }, 1000);
            } else {
                alert(data.error || 'Lỗi thao tác.');
            }
        }

        // Switcher logic for Stats Hub Cards
        function switchStatsTab(tabId) {
            // Remove active class from all tab buttons
            document.querySelectorAll('#statsHubCard .tab-btn').forEach(btn => btn.classList.remove('active'));
            // Remove active class from all tab panes
            document.querySelectorAll('#statsHubCard .chart-wrapper-tab').forEach(pane => pane.classList.remove('active'));

            // Add active class to clicked button
            const btn = Array.from(document.querySelectorAll('#statsHubCard .tab-btn')).find(b => b.getAttribute('onclick').includes(tabId));
            if (btn) btn.classList.add('active');

            // Add active class to corresponding tab pane
            const pane = document.getElementById(`tabPane-${tabId}`);
            if (pane) pane.classList.add('active');

            // Force Chart.js reflows immediately to correct zero-width layout issue
            window.dispatchEvent(new Event('resize'));
        }
    </script>

    <?php include PROJECT_ROOT . 'dashboard/views/logging-toast.php'; ?>
    <?php include PROJECT_ROOT . 'dashboard/views/local-time-script.php'; ?>

    <?php include PROJECT_ROOT . 'views/footer.php'; ?>
</body>

</html>
