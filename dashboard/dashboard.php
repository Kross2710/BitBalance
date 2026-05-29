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
    $milestoneText = 'Every milestone cleared — legendary!';
} else {
    $streakProgress = (int) round((($streakDays - $prevMilestone) / ($nextMilestone - $prevMilestone)) * 100);
    $daysLeft = $nextMilestone - $streakDays;
    $milestoneText = $daysLeft . ' day' . ($daysLeft === 1 ? '' : 's') . " to {$nextMilestone}-day milestone";
}

if ($streakDays >= 30) {
    $streakFlameColor = '#fbbf24';
    $streakMessage = "You're on fire! 30+ day legend.";
} elseif ($streakDays >= 14) {
    $streakFlameColor = '#fb923c';
    $streakMessage = 'Incredible consistency. Keep going!';
} elseif ($streakDays >= 1) {
    $streakFlameColor = '#ffffff';
    $streakMessage = "You're building serious consistency. Keep it going!";
} else {
    $streakFlameColor = '#ffffff';
    $streakMessage = 'Log a meal today to start your streak!';
}

// --- Today's Focus card data ---
$macroTotals = $macroTotals ?? ['protein' => 0, 'carbs' => 0, 'fat' => 0];
$macroGoals = $macroGoals ?? getMacroGoalsFromCalorieGoal(!empty($userGoal) ? (int) $userGoal : null);
$hasCalorieGoal = !empty($userGoal);
$calorieDiff = $hasCalorieGoal ? ((int) $userGoal - (int) $totalCalories) : null;

if (!$hasCalorieGoal) {
    $focusTitle = 'Set your daily goal';
    $focusCopy = 'Add a target so BitBalance can guide today\'s intake.';
    $focusTone = 'neutral';
} elseif ($calorieDiff > 0) {
    $focusTitle = number_format($calorieDiff) . ' kcal left';
    $focusCopy = 'You still have room to plan the rest of today.';
    $focusTone = 'good';
} elseif ($calorieDiff === 0) {
    $focusTitle = 'Goal matched';
    $focusCopy = 'You are exactly on today\'s calorie target.';
    $focusTone = 'good';
} else {
    $focusTitle = number_format(abs($calorieDiff)) . ' kcal over';
    $focusCopy = 'Keep the next choices lighter and protein-forward.';
    $focusTone = 'alert';
}

$macroFocusDefs = [
    'protein' => ['label' => 'Protein', 'icon' => 'fa-drumstick-bite'],
    'carbs' => ['label' => 'Carbs', 'icon' => 'fa-bread-slice'],
    'fat' => ['label' => 'Fat', 'icon' => 'fa-cheese'],
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
    $macroFocusText = 'On track';
} else {
    $macroFocusIcon = 'fa-bullseye';
    $macroFocusText = 'Needs goal';
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
        $bmiClass = 'Underweight';
    } elseif ($bmi < 25.0) {
        $bmiClass = 'Normal';
    } elseif ($bmi < 30.0) {
        $bmiClass = 'Overweight';
    } else {
        $bmiClass = 'Obese';
    }
}
?>

<!DOCTYPE html>
<html lang="en"
    data-theme="<?php echo isset($_SESSION['user']) ? ($_SESSION['user']['theme_preference'] ?? 'system') : 'system'; ?>">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>BitBalance Dashboard</title>
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
                <span><strong>You're exploring a live demo.</strong> This is sample data — create a free account to start your own dashboard.</span>
                <a href="<?= BASE_URL ?>signup.php" class="demo-banner-cta">Get started free</a>
            </div>
        <?php endif; ?>

        <div class="welcome-banner">
            <div class="welcome-text">
                <h2>Welcome back, <?= htmlspecialchars($user['first_name'] ?? 'Champion') ?>! 👋</h2>
                <p><?php
                    if (!$hasCalorieGoal) {
                        echo "Set a daily calorie goal to customize your dashboard and guide your intake! 🎯";
                    } elseif ($totalCalories > 0) {
                        if ($progressPercentage >= 100) {
                            echo "You have achieved your calorie goal for today! Spectacular job! 🌟";
                        } else {
                            echo "You have met <strong>" . (int)$progressPercentage . "%</strong> of your daily calorie goal today. Let's fuel your body! 🚀";
                        }
                    } else {
                        echo "Start tracking your meals today to stay on target and build a healthy habit! 🎯";
                    }
                ?></p>
            </div>
            <div class="welcome-stats">
                <div class="welcome-stat-chip">
                    <i class="fas fa-bullseye" style="color: #60a5fa;"></i>
                    <span><?= (int) $progressPercentage ?>% Goal Met</span>
                </div>
                <div class="welcome-stat-chip">
                    <i class="fas fa-trophy" style="color: #FFD700;"></i>
                    <span>Level Active</span>
                </div>
            </div>
        </div>

        <div class="flex-row">
            <div class="flex">
                <section class="progress-widget">
                    <div class="progress-card">
                        <div class="progress-card-content">
                            <h3>Today</h3>
                            <div class="progress-value">
                                <span class="<?= $statusClass ?>"><?php echo $totalCalories; ?> calories</span>
                            </div>
                            <div class="progress-bar">
                                <div class="progress-fill <?= htmlspecialchars($statusClass) ?>" id="progressFill" style="width: 0%;"></div>
                            </div>
                            <div class="progress-labels">
                                <span>Goal</span>
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

                <section class="chart-section history-card">
                    <div class="chart-header-row">
                        <h4><i class="fas fa-chart-bar"></i> Last 7 Days</h4>
                        <div class="chart-average-badge">
                            <span class="label">Avg:</span>
                            <span class="value"><?php echo $averageCalories; ?></span>
                        </div>
                    </div>
                    <div class="chart-container-wrapper">
                        <canvas id="historyChart"></canvas>
                    </div>
                    <div class="macros-trend-wrap">
                        <div class="macros-trend-header">
                            <h5><i class="fas fa-layer-group"></i> Macros Trend (g)</h5>
                            <div class="macros-trend-legend">
                                <span><i class="dot p"></i> Protein</span>
                                <span><i class="dot c"></i> Carbs</span>
                                <span><i class="dot f"></i> Fat</span>
                            </div>
                        </div>
                        <div class="chart-container-wrapper macros-trend-canvas">
                            <canvas id="macrosTrendChart"></canvas>
                        </div>
                    </div>
                    <script>
                        document.addEventListener('DOMContentLoaded', function () {
                            const mtCtx = document.getElementById('macrosTrendChart');
                            if (!mtCtx || typeof Chart === 'undefined') return;

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
                        });
                    </script>
                    <script>
                        // Chart JS Logic
                        document.addEventListener('DOMContentLoaded', function () {
                            const ctx = document.getElementById('historyChart').getContext('2d');
                            let gradient = ctx.createLinearGradient(0, 0, 0, 300);
                            gradient.addColorStop(0, '#4facfe'); gradient.addColorStop(1, 'rgba(0, 242, 254, 0.2)');
                            new Chart(ctx, {
                                type: 'bar',
                                data: {
                                    labels: <?php echo json_encode($historyLabels); ?>,
                                    datasets: [{
                                        label: 'Calories',
                                        data: <?php echo json_encode($historyData); ?>,
                                        backgroundColor: gradient,
                                        borderRadius: 6,
                                        barThickness: 15
                                    }]
                                },
                                options: {
                                    responsive: true,
                                    maintainAspectRatio: false,
                                    plugins: { legend: { display: false } },
                                    scales: {
                                        y: { beginAtZero: true, grid: { color: '#f0f0f0', borderDash: [5, 5] }, border: { display: false } },
                                        x: { grid: { display: false }, border: { display: false } }
                                    }
                                }
                            });
                        });
                    </script>
                </section>
            </div>

            <div class="flex">
                <section class="chart-section meals-card">
                    <div class="card-header">
                        <h4><i class="fas fa-utensils"></i> Intake Breakdown</h4>
                    </div>
                    <div class="doughnut-container">
                        <canvas id="mealCategoriesChart"></canvas>
                        <div class="doughnut-center-text">
                            <span class="center-val"><?php echo $totalCalories; ?></span>
                            <span class="center-label">kcal</span>
                        </div>
                    </div>

                    <?php
                    $mealConfig = [
                        'breakfast' => ['icon' => 'fa-mug-hot', 'color' => '#FF6384', 'label' => 'Breakfast'],
                        'lunch' => ['icon' => 'fa-hamburger', 'color' => '#36A2EB', 'label' => 'Lunch'],
                        'dinner' => ['icon' => 'fa-utensils', 'color' => '#FFCE56', 'label' => 'Dinner'],
                        'snack' => ['icon' => 'fa-cookie-bite', 'color' => '#FF9F40', 'label' => 'Snack']
                    ];
                    ?>
                    <script>
                        const mealDataRaw = <?php echo json_encode($mealCategoryData); ?>;
                        const mealConfig = <?php echo json_encode($mealConfig); ?>;
                        const labels = Object.keys(mealDataRaw);
                        const dataValues = Object.values(mealDataRaw);
                        const bgColors = labels.map(cat => {
                            const key = cat.toLowerCase();
                            return mealConfig[key] ? mealConfig[key].color : '#e0e0e0';
                        });

                        new Chart(document.getElementById('mealCategoriesChart'), {
                            type: 'doughnut',
                            data: {
                                labels: labels,
                                datasets: [{ data: dataValues, backgroundColor: bgColors, borderWidth: 2, borderColor: '#ffffff', borderRadius: 20, hoverOffset: 4 }]
                            },
                            options: { responsive: true, maintainAspectRatio: false, cutout: '80%', plugins: { legend: { display: false } } }
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
                                    <small>kcal</small>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </section>
            </div>

            <div class="flex">
                <!-- STREAK CARD -->
                <section class="dashboard-card streak-card" id="streakCard">
                    <div class="streak-header">
                        <div class="streak-flame-wrapper">
                            <i class="fas fa-fire streak-flame" id="streakFlame"
                                style="color: <?= htmlspecialchars($streakFlameColor) ?>;"></i>
                        </div>
                        <div class="streak-info">
                            <h3>Streak</h3>
                            <div class="streak-main">
                                <span class="streak-number" id="streakNumber"><?= (int) $streakDays ?></span>
                                <span class="streak-label">days</span>
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
                    </div>
                </section>

                <script>
                    // Streak Card Interactions
                    function updateStreakDisplay(currentStreak) {
                        const numberEl = document.getElementById('streakNumber');
                        const flameEl = document.getElementById('streakFlame');
                        const messageEl = document.getElementById('streakMessage');

                        if (!numberEl || !flameEl) return;

                        numberEl.textContent = currentStreak;

                        // Milestone logic
                        if (currentStreak >= 30) {
                            flameEl.style.color = '#fbbf24'; // Gold
                            messageEl.textContent = "You're on fire! 30+ day legend.";
                        } else if (currentStreak >= 14) {
                            flameEl.style.color = '#fb923c';
                            messageEl.textContent = "Incredible consistency. Keep going!";
                        } else {
                            flameEl.style.color = 'white';
                            messageEl.textContent = "You're building serious consistency. Keep it going!";
                        }
                    }

                    // Trigger when user successfully logs a meal and maintains streak
                    function celebrateStreakMaintenance() {
                        const flame = document.getElementById('streakFlame');
                        const card = document.getElementById('streakCard');

                        if (!flame || !card) return;

                        // Glow + scale animation
                        flame.style.transform = 'scale(1.3)';
                        flame.style.filter = 'drop-shadow(0 0 12px #ff9600)';

                        card.style.transition = 'transform 0.2s ease';
                        card.style.transform = 'scale(1.02)';

                        setTimeout(() => {
                            flame.style.transform = 'scale(1)';
                            flame.style.filter = 'none';
                            card.style.transform = 'scale(1)';
                        }, 600);

                        // Optional: Show toast
                        showLoggingToast("Streak maintained! 🔥 +1 day", "You're on fire 🔥");
                    }

                    // Call this on page load with real data
                    // updateStreakDisplay(12);
                </script>

                <section class="dashboard-card weight-card">
                    <div class="card-header-row">
                        <div class="weight-info">
                            <h3>Weight Journey</h3>
                            <div class="current-weight">
                                <span
                                    class="weight-val"><?php echo $currentWeight > 0 ? $currentWeight : '--'; ?></span>
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
                                title="View History">
                                <i class="fas fa-list-ul"></i>
                            </button>
                            <button class="btn-icon-small" onclick="openWeightModal()" title="Log Weight">
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

                            // Gradient màu tím nhạt cho vùng dưới đường kẻ
                            let gradientW = ctxW.createLinearGradient(0, 0, 0, 150);
                            gradientW.addColorStop(0, 'rgba(155, 89, 182, 0.2)'); // Tím
                            gradientW.addColorStop(1, 'rgba(155, 89, 182, 0.0)');

                            new Chart(ctxW, {
                                type: 'line',
                                data: {
                                    labels: <?php echo json_encode($weightLabels); ?>,
                                    datasets: [{
                                        label: 'Weight',
                                        data: <?php echo json_encode($weightData); ?>,
                                        borderColor: '#9b59b6', // Màu tím chủ đạo
                                        backgroundColor: gradientW,
                                        borderWidth: 3,
                                        pointBackgroundColor: '#fff',
                                        pointBorderColor: '#9b59b6',
                                        pointRadius: 4,
                                        pointHoverRadius: 6,
                                        fill: true,
                                        tension: 0.4 // Đường cong mềm mại
                                    }]
                                },
                                options: {
                                    responsive: true,
                                    maintainAspectRatio: false,
                                    plugins: { legend: { display: false } },
                                    scales: {
                                        y: {
                                            display: false, // Ẩn trục Y để giao diện sạch
                                            min: Math.min(...<?php echo json_encode($weightData); ?>) - 1, // Tự động scale
                                            max: Math.max(...<?php echo json_encode($weightData); ?>) + 1
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

                <section class="dashboard-card focus-card">
                    <div class="focus-card-header">
                        <span class="focus-kicker"><i class="fas fa-compass"></i> Today's Focus</span>
                        <span class="focus-status <?= htmlspecialchars($focusTone) ?>">
                            <?= htmlspecialchars($status === 'Overlimit' ? 'Adjust' : ($hasCalorieGoal ? 'Active' : 'Setup')) ?>
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
                                <span>Macro focus</span>
                                <strong><?= htmlspecialchars($macroFocusText) ?></strong>
                            </div>
                        </div>
                        <div class="focus-insight bmi-focus">
                            <i class="fas fa-heart-pulse"></i>
                            <div>
                                <span>BMI Status</span>
                                <strong><?= $bmi > 0 ? htmlspecialchars($bmi) . ' (' . htmlspecialchars($bmiClass) . ')' : 'Needs info' ?></strong>
                            </div>
                        </div>
                    </div>

                    <div class="focus-actions">
                        <?php if ($hasCalorieGoal): ?>
                            <a href="dashboard-plan.php" class="focus-btn primary">
                                <i class="fas fa-route"></i> View Plan
                            </a>
                            <button type="button" class="focus-btn ghost"
                                onclick="<?php echo $isLoggedIn ? 'openGoalModal()' : "window.location.href='" . BASE_URL . "login.php'"; ?>">
                                <i class="fas fa-bullseye"></i> Adjust Goal
                            </button>
                        <?php else: ?>
                            <button type="button" class="focus-btn primary"
                                onclick="<?php echo $isLoggedIn ? 'openGoalModal()' : "window.location.href='" . BASE_URL . "login.php'"; ?>">
                                <i class="fas fa-bullseye"></i> Set Goal
                            </button>
                            <a href="dashboard-plan.php" class="focus-btn ghost">
                                <i class="fas fa-route"></i> View Plan
                            </a>
                        <?php endif; ?>
                    </div>
                </section>
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
                    <h3>Set Daily Goal</h3><button class="close-modal" onclick="closeGoalModal()">&times;</button>
                </div>
                <form action="handlers/update_goal.php" method="POST">
                    <div class="modal-body">
                        <div class="form-group large-input-group">
                            <label for="modal_calorie_goal">Calorie Goal (kcal)</label>
                            <div class="input-wrapper-lg">
                                <i class="fas fa-bullseye input-icon-lg"></i>
                                <input type="number" id="modal_calorie_goal" name="calorie_goal"
                                    value="<?php echo htmlspecialchars($userGoal); ?>" required>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn-cancel" onclick="closeGoalModal()">Cancel</button>
                        <button type="submit" class="btn-save">Save Goal</button>
                    </div>
                </form>
            </div>
        </div>

        <div id="weightModal" class="modal-overlay">
            <div class="modal-box">
                <div class="modal-header">
                    <h3>Log Current Weight</h3>
                    <button class="close-modal" onclick="closeWeightModal()">&times;</button>
                </div>

                <form action="handlers/log_weight.php" method="POST">
                    <div class="modal-body">
                        <p class="modal-desc">Track your progress regularly for better insights.</p>

                        <div class="form-group large-input-group">
                            <label for="weight_input">Weight (kg)</label>
                            <div class="input-wrapper-lg">
                                <i class="fas fa-weight input-icon-lg"></i>
                                <input type="number" id="weight_input" name="weight" step="0.1" min="1" max="500" required
                                    placeholder="0.0">
                            </div>
                        </div>

                        <div class="form-group" style="margin-top: 15px;">
                            <label style="font-size: 0.9rem; font-weight: 600; color: var(--text-secondary);">Date</label>
                            <input type="date" name="weight_date" value="<?php echo date('Y-m-d'); ?>"
                                style="width: 100%; padding: 10px; border-radius: 8px; border: 1px solid #e1e4e8; background: var(--bg-body); color: var(--text-primary);">
                        </div>
                    </div>

                    <div class="modal-footer">
                        <button type="button" class="btn-cancel" onclick="closeWeightModal()">Cancel</button>
                        <button type="submit" class="btn-save">Save Weight</button>
                    </div>
                </form>
            </div>
        </div>

        <div id="weightHistoryModal" class="modal-overlay">
            <div class="modal-box">
                <div class="modal-header">
                    <h3>Weight History</h3>
                    <button class="close-modal" onclick="closeWeightHistoryModal()">&times;</button>
                </div>

                <div class="modal-body" style="padding: 0;">
                    <div class="history-list-wrapper" style="max-height: 400px; overflow-y: auto;">
                        <?php if (empty($weightHistoryList)): ?>
                            <p style="padding: 20px; text-align: center; color: #999;">No records found.</p>
                        <?php else: ?>
                            <table class="modern-table" style="margin: 0; width: 100%;">
                                <tbody id="weightTableBody">
                                    <?php foreach ($weightHistoryList as $wLog): ?>
                                        <tr data-id="<?= $wLog['weight_id'] ?>" style="border-bottom: 1px solid #eee;">
                                            <td style="padding: 15px 25px;">
                                                <div style="font-weight: 600; font-size: 1.1rem; color: var(--text-primary);">
                                                    <?= htmlspecialchars($wLog['weight']) ?> kg
                                                </div>
                                            </td>
                                            <td style="padding: 15px 25px; color: var(--text-secondary); font-size: 0.9rem;">
                                                <?= date('d M, Y', strtotime($wLog['date_logged'])) ?>
                                            </td>
                                            <td style="padding: 15px 25px; text-align: right;">
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
            async function deleteWeight(id) {
                if (!confirm('Are you sure you want to delete this entry?')) return;

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
                        // Reload to update chart (optional but recommended for consistency)
                        setTimeout(() => location.reload(), 500);
                    } else {
                        alert(data.error || 'Failed to delete');
                    }
                } catch (err) {
                    console.error(err);
                    alert('Connection error');
                }
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
    </script>

    <?php include PROJECT_ROOT . 'dashboard/views/logging-toast.php'; ?>
    <?php include PROJECT_ROOT . 'dashboard/views/local-time-script.php'; ?>

    <?php include PROJECT_ROOT . 'views/footer.php'; ?>
</body>

</html>
