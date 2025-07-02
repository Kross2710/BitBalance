<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/../include/init.php';
require_once __DIR__ . '/handlers/dashboard_data.php';
require_once __DIR__ . '/handlers/functions.php';
require_once __DIR__ . '/../include/handlers/log_attempt.php';
require_once __DIR__ . '/../include/weather.php';

$lat = $_SESSION['user']['lat'] ?? -37.81;   // default Melbourne
$lon = $_SESSION['user']['lon'] ?? 144.96;

$weather = fetch_weather($lat, $lon);

if ($isLoggedIn) {
    // Log the user activity
    log_attempt($pdo, $user['user_id'], 'view', 'User ' . $user['user_id'] . ' clicked on dashboard', 'dashboard', null);
}

$activePage = 'overview';
$activeHeader = 'dashboard';

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
?>

<!DOCTYPE html>
<html lang="en"
    data-theme="<?php echo isset($_SESSION['user']) ? ($_SESSION['user']['theme_preference'] ?? 'light') : 'light'; ?>">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>BitBalance Dashboard</title>
    <link rel="stylesheet" href="../css/style.css">
    <link rel="stylesheet" href="../css/dashboard.css">
    <link rel="stylesheet" href="../css/themes/global.css">
    <link rel="stylesheet" href="../css/themes/header.css">
    <link rel="stylesheet" href="../css/themes/dashboard.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="https://kit.fontawesome.com/b94f65ead2.js" crossorigin="anonymous"></script>
</head>

<body>
    <?php include PROJECT_ROOT . 'views/header.php'; ?>
    <?php include PROJECT_ROOT . 'dashboard/views/sidebar.php'; ?>

    <?php if ($isLoggedIn): ?>
        <?php include PROJECT_ROOT . 'dashboard/views/right-sidebar.php'; ?>
        <main class="dashboard">
            <div class="flex-row">
                <div class="flex">
                    <!-- Top widget: progress and gauge -->
                    <section class="progress-widget">
                        <div class="progress-card">
                            <h3>Today</h3>
                            <div class="progress-value">
                                <span><?php echo $totalCalories; ?> calories</span>
                            </div>
                            <div class="progress-bar">
                                <div class="progress-fill" id="progressFill" style="width: 0%;"></div>
                            </div>
                            <script>
                                document.addEventListener('DOMContentLoaded', function () {
                                    var fill = document.getElementById('progressFill');
                                    setTimeout(function () {
                                        fill.style.width = '<?php echo $progressPercentage; ?>%';
                                    }, 100); // slight delay for smooth transition
                                });
                            </script>
                            <div class="progress-labels">
                                <span>Goal</span>
                                <span>
                                    <?php echo $userGoal ? $userGoal . '' : 'Set your goal'; ?>
                                </span>
                            </div>
                        </div>
                    </section>

                    <!-- Status and button -->
                    <section class="status-section">
                        <h4>Status: <span class="<?= $statusClass ?>"><?= $status ?></span></h4>
                        <?php if ($status === 'Ongoing'): ?>
                            <p>Keep up the good work! You're on track to meet your goal.</p>
                            <button class="btn-primary"><a href="set-goal.php">Change Daily Goal</a></button>
                        <?php elseif ($status === 'Overlimit'): ?>
                            <p>Great Job! You've hit your daily goal.</p>
                            <button class="btn-primary"><a href="set-goal.php">Change Daily Goal</a></button>
                        <?php else: ?>
                            <p>You haven't set a daily goal yet. Please set your goal to track your progress.</p>
                            <button class="btn-primary"><a href="set-goal.php">Set My Daily Goal</a></button>
                        <?php endif; ?>
                        <?php if (!empty($error_message)): ?>
                            <div class="error-message"
                                style="color: #d32f2f; margin-bottom: 15px; padding: 12px; background-color: #ffebee; border: 1px solid #e57373; border-radius: 5px; font-weight: bold;">
                                <i class="fas fa-exclamation-triangle" style="margin-right: 8px;"></i>
                                <?php echo htmlspecialchars($error_message); ?>
                            </div>
                        <?php endif; ?>
                        <?php if (!empty($success_message)): ?>
                            <div class="success-message"
                                style="color: #388e3c; margin-bottom: 15px; padding: 12px; background-color: #e8f5e9; border: 1px solid #81c784; border-radius: 5px; font-weight: bold;">
                                <i class="fas fa-check-circle" style="margin-right: 8px;"></i>
                                <?php echo htmlspecialchars($success_message); ?>
                            </div>
                        <?php endif; ?>
                    </section>
                    <section class="chart-section">
                        <h4>History (Last 7 days)</h4>
                        <!-- History chart -->
                        <canvas id="historyChart"></canvas>
                        <p>Average: <span class="average-calories"><?php echo htmlspecialchars($averageCalories); ?></span>
                            calories</p>
                        <style>
                            .chart-section h4 {
                                margin-bottom: 15px;
                                font-size: 1.2em;
                                color: #333;
                            }

                            .chart-section p {
                                margin-top: 10px;
                                font-size: 1em;
                                color: #555;
                                text-align: center;
                            }

                            .average-calories {
                                font-weight: bold;
                            }
                        </style>
                        <script>
                            const historyLabels = <?php echo json_encode($historyLabels); ?>;
                            const historyData = <?php echo json_encode($historyData); ?>;

                            // History bar chart
                            const historyCtx = document.getElementById('historyChart').getContext('2d');
                            new Chart(historyCtx, {
                                type: 'bar',
                                data: {
                                    labels: historyLabels,
                                    datasets: [{
                                        label: 'Calories',
                                        data: historyData,
                                        backgroundColor: '#72A9F3'
                                    }]
                                },
                                options: {
                                    scales: { y: { beginAtZero: true } },
                                    plugins: { legend: { display: false } }
                                }
                            });
                        </script>
                    </section>
                </div>
                <div class="flex">
                    <section class="chart-section">
                        <!-- Meal categories doughnut chart -->
                        <canvas id="mealCategoriesChart"></canvas>
                        <?php
                        if (empty($intakeLog)) {
                            echo '<p style="text-align:center;">A doughnut chart will be displayed here when meals are logged.</p>';
                        }
                        ?>
                        <script>
                            const mealCategories = <?php echo json_encode(array_keys($mealCategoryData)); ?>;
                            const mealCategoriesData = <?php echo json_encode(array_values($mealCategoryData)); ?>;

                            // Intake doughnut chart
                            const intakeCtx = document.getElementById('mealCategoriesChart').getContext('2d');
                            new Chart(intakeCtx, {
                                type: 'doughnut',
                                data: {
                                    labels: mealCategories,
                                    datasets: [{
                                        label: 'Meals',
                                        data: mealCategoriesData,
                                        backgroundColor: ['#FF6384', '#36A2EB', '#FFCE56', '#FF9F40']
                                    }]
                                },
                                options: {
                                    responsive: true,
                                    plugins: {
                                        legend: {
                                            position: 'top',
                                        },
                                        tooltip: {
                                            callbacks: {
                                                label: function (tooltipItem) {
                                                    const label = tooltipItem.label || '';
                                                    const value = tooltipItem.raw || 0;
                                                    return `${label}: ${value} calories`;
                                                }
                                            }
                                        }
                                    }
                                }
                            });
                        </script>
                        <section class="info-section">
                            <div style="display: flex; flex-direction: column; gap: 10px;">
                                <table class="meals-table" style="width:100%; border-collapse:collapse;">
                                    <thead>
                                        <tr>
                                            <th style="text-align:left; padding:8px; border-bottom:1px solid #ccc;">Meal
                                                Type
                                            </th>
                                            <th style="text-align:left; padding:8px; border-bottom:1px solid #ccc;">Food
                                                Item
                                            </th>
                                            <th style="text-align:left; padding:8px; border-bottom:1px solid #ccc;">Calories
                                            </th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php
                                        $mealTypes = [
                                            'breakfast' => 'Breakfast',
                                            'lunch' => 'Lunch',
                                            'dinner' => 'Dinner',
                                            'snack' => 'Snacks'
                                        ];

                                        if (empty($intakeLog)) {
                                            echo '<tr><td colspan="3" style="text-align:center; padding:8px;">No meals logged today.</td></tr>';
                                        }

                                        foreach ($mealTypes as $typeKey => $typeLabel):
                                            $hasMeal = false;
                                            foreach ($intakeLog as $meal) {
                                                if ($meal['meal_category'] === $typeKey) {
                                                    $hasMeal = true;
                                                    break;
                                                }
                                            }
                                            if ($hasMeal):
                                                foreach ($intakeLog as $meal):
                                                    if ($meal['meal_category'] === $typeKey): ?>
                                                        <tr>
                                                            <td data-label="Meal"
                                                                style="padding:8px; border-bottom:1px solid #eee; font-weight: bold;">
                                                                <?php echo htmlspecialchars($typeLabel); ?>
                                                            </td>
                                                            <td data-label="Food" style="padding:8px; border-bottom:1px solid #eee;">
                                                                <?php echo htmlspecialchars($meal['food_item']); ?>
                                                            </td>
                                                            <td data-label="Calories" style="padding:8px; border-bottom:1px solid #eee;">
                                                                <?php echo htmlspecialchars($meal['calories']); ?>
                                                            </td>
                                                        </tr>
                                                    <?php endif;
                                                endforeach;
                                            endif;
                                        endforeach;
                                        ?>
                                    </tbody>
                                </table>
                            </div>
                            <style>
                                .info-section {
                                    margin-top: 15px;
                                    border-radius: 10px;
                                    box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
                                }

                                /* ---- Meals Table Modern Styling ---- */
                                table.meals-table {
                                    width: 100%;
                                    margin-top: 5px;
                                    border-collapse: separate;
                                    border-spacing: 0;
                                    border-radius: 12px;
                                    overflow: hidden;
                                }

                                th,
                                td {
                                    padding: 14px 16px;
                                    text-align: left;
                                    font-size: 1rem;
                                    color: #333;
                                    border-bottom: 1px solid #eee;
                                }

                                th {
                                    font-weight: 600;
                                    color: #555;
                                }

                                tr:hover {
                                    background-color: #f1f7ff;
                                    transition: background-color 0.2s ease;
                                }

                                tr:last-child td {
                                    border-bottom: none;
                                }

                                /* Responsive */
                                @media (max-width: 700px) {
                                    .intake-table {
                                        width: 100%;
                                        font-size: 0.95em;
                                        border-radius: 0;
                                        box-shadow: none;
                                    }

                                    th,
                                    td {
                                        padding: 10px 8px;
                                    }

                                    .progress-value {
                                        font-size: 1em;
                                    }
                                }
                            </style>
                        </section>
                    </section>
                </div>
                <div class="flex">
                    <section class="streak-box">
                        <div class="flex-row">
                            <div class="flex">
                                <div class="streak-title" style="text-align: center;">
                                    <h3>Streak <span class="fire-icon" aria-hidden="true">🔥</span></h3>
                                </div>
                            </div>
                            <div class="streak-info-container">
                                <div class="streak-info">
                                    <p>Current Streak: <span
                                            class="streak-count"><?= htmlspecialchars($userStreak['logging_streak']) ?></span>
                                    </p>
                                </div>
                                <div class="streak-info">
                                    <p>Longest Streak: <span
                                            class="longest-streak-count"><?= htmlspecialchars($userStreak['longest_logging_streak']) ?></span>
                                    </p>
                                </div>
                            </div>
                        </div>
                        <p>Logging your meals to maintain your streak!</p>
                        <style>
                            .streak-title {
                                display: flex;
                                align-items: center;
                                font-weight: bold;
                                color: #333;
                            }

                            .streak-box {
                                padding: 15px;
                                border-radius: 10px;
                                box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
                                background: #fff;
                            }

                            .streak-box p {
                                margin: 10px 0;
                                color: #555;
                            }

                            .streak-info p {
                                margin: 5px 0;
                            }

                            .streak-count {
                                font-weight: bold;
                                color: #388e3c;
                            }

                            .longest-streak-count {
                                font-weight: bold;
                                color: #f44336;
                            }

                            .streak-info-container {
                                display: flex;
                                justify-content: space-between;
                                align-items: center;
                                gap: 20px;
                                padding: 10px 0;
                            }

                            .streak-info p {
                                margin: 0;
                                font-size: 1.1em;
                                text-align: center;
                                width: 100%;
                            }

                            /* Responsive adjustments */
                            @media (max-width: 900px) {
                                .streak-box {
                                    padding: 10px;
                                    box-shadow: none;
                                }

                                .streak-title h3 {
                                    font-size: 1.2em;
                                }

                                .streak-info p {
                                    font-size: 0.9em;
                                }

                                .fire-icon {
                                    font-size: 1.2em;
                                }
                            }
                        </style>
                        <script>
                            document.addEventListener('DOMContentLoaded', () => {
                                // Wait 10 000 ms (10 s), then pause the flame
                                setTimeout(() => {
                                    const flame = document.querySelector('.fire-icon');
                                    if (flame) flame.classList.add('paused');
                                }, 1000);
                            });
                        </script>
                    </section>
                    <section class="streak-box" style="margin-top: 20px;">
                        <div class="flex-row">
                            <div class="flex">
                                <?php if ($weather): ?>
                                    <div class="weather-widget">
                                        <h3>Local Weather</h3>
                                        <div class="current">
                                            <img src="<?= htmlspecialchars($weather['current']['icon']); ?>" alt="" width="64"
                                                height="64">
                                            <span class="temp"><?= round($weather['current']['temp']); ?>&deg;C</span>
                                            <small><?= htmlspecialchars($weather['current']['text']); ?></small>
                                        </div>

                                        <canvas id="weeklyTemp"></canvas>
                                    </div>

                                    <script>
                                        const labels = <?= json_encode(array_column($weather['daily'], 'date')); ?>;
                                        const highs = <?= json_encode(array_column($weather['daily'], 'max')); ?>;
                                        const lows = <?= json_encode(array_column($weather['daily'], 'min')); ?>;

                                        new Chart(document.getElementById('weeklyTemp').getContext('2d'), {
                                            type: 'line',
                                            data: {
                                                labels: labels.map(d => d.slice(5)),  // show MM-DD
                                                datasets: [
                                                    { label: 'High', data: highs, fill: false },
                                                    { label: 'Low', data: lows, fill: false }
                                                ]
                                            },
                                            options: {
                                                plugins: { legend: { display: false } },
                                                scales: { y: { beginAtZero: false } }
                                            }
                                        });
                                    </script>
                                <?php else: ?>
                                    <div class="weather-widget error">
                                        Weather service temporarily unavailable.
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </section>
                </div>
        </main>
    <?php else: ?>
        <main class="dashboard" style="text-align:center; margin-top:40px;">
            <h2>Please log in to access your Dashboard.</h2>
            <button class="btn-primary"><a href="<?= BASE_URL ?>login.php" class="btn-primary">Sign In</a></button>
        </main>
    <?php endif; ?>
    <?php include PROJECT_ROOT . 'views/footer.php'; ?>
</body>

</html>
<style>
    main.dashboard {
        margin-top: 20px;
        margin-left: 220px;
        margin-right: 220px;
        border-radius: 10px;
        width: calc(100% - 440px);
    }

    @media (max-width: 900px) {
        .dashboard {
            margin-left: 0;
            margin-right: 0;
            width: 100vw;
        }
    }

    .flex-row {
        display: flex;
        flex-wrap: wrap;
        justify-content: start;
        gap: 30px;
    }

    .flex {
        display: flex;
        flex-direction: column;
        flex: 1 1 0;
        min-width: 0;
    }

    /* For the right column's info section */
    .flex:last-child {
        display: flex;
        flex-direction: column;
        justify-content: stretch;
    }

    .chart-section {
        flex-grow: 1;
        display: flex;
        flex-direction: column;
        justify-content: space-between;
    }

    .info-section {
        flex-grow: 1;
        display: flex;
        flex-direction: column;
        justify-content: space-between;
    }

    .progress-card h3 {
        font-size: 1.3em;
    }

    .progress-widget {
        width: 100%;
        max-width: 480px;
        box-shadow: 0 2px 5px rgba(0, 0, 0, 0.05);
        border-radius: 10px;
        border: 1px solid #666;
        background: #fff;
        padding: 18px;
    }

    .progress-value {
        margin: 8px 0;
    }

    .progress-value span {
        font-size: 2em;
        font-weight: bold;
        color:
            <?= $statusClass === 'ongoing' ? '#388e3c' : ($statusClass === 'overlimit' ? 'black' : '#999') ?>
        ;
    }

    .progress-bar {
        height: 25px;
        background-color: #e6e6e6;
        border-radius: 5px;
        overflow: hidden;
        margin-bottom: 10px;
        min-width: 90px;
    }

    .progress-fill {
        background-color: #eba434;
        height: 100%;
        transition: width 1s cubic-bezier(0.4, 0, 0.2, 1);
        will-change: width;
    }

    .progress-labels {
        display: flex;
        justify-content: space-between;
        font-size: 14px;
        color: #666;
    }

    .status-section {
        padding: 20px;
        border: 1px solid #ccc;
        border-radius: 10px;
        box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
        max-width: 480px;
        margin-top: 15px;
        margin-bottom: 15px;
    }

    .info-section {
        padding: 20px;
        border: 1px solid #ccc;
        border-radius: 10px;
        box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
        max-width: 480px;
        margin-bottom: 15px;
    }

    .status-section h4 {
        margin-bottom: 10px;
    }

    .status-section span.ongoing {
        color: #388e3c;
        /* Green */
    }

    .status-section span.overlimit {
        color: #d32f2f;
        /* Red */
    }

    .status-section span.unset {
        color: #999;
        /* Grey */
    }

    .btn-primary {
        background-color: #4a7ee3;
        color: white;
        padding: 8px 15px;
        border: none;
        margin: 20px 0;
        border-radius: 5px;
        cursor: pointer;
        font-size: 16px;
    }

    .btn-primary a {
        color: white;
        text-decoration: none;
    }

    .btn-primary:hover {
        background-color: #3a5bb3;
        transition: all 0.3s ease;
    }

    .chart-section {
        border-radius: 10px;
        padding: 20px;
        box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
        max-width: 480px;
    }

    canvas {
        width: 100% !important;
        height: auto !important;
        display: block;
        margin: 0 auto;
    }

    canvas#mealCategoriesChart {
        width: 60% !important;

    }

    /* Responsive improvements for dashboard */
    @media (max-width: 900px) {
        main.dashboard {
            margin: 0;
            width: 100vw;
            box-shadow: none;
            border: none;
            padding: 12px 0 0 0;
        }

        .progress-widget,
        .status-section,
        .chart-section {
            width: 100vw;
            min-width: 0;
            max-width: 100vw;
            padding: 0 2vw;
            margin: 0 auto 12px auto;
            box-shadow: none;
            border-radius: 0;
            border: none;
        }
    }

    @media (max-width: 480px) {
        main.dashboard {
            padding: 4px 0 0 0;
        }
    }

    /* Adjust flex for mobile devies */
    @media (max-width: 900px) {
        .flex {
            flex: 1 1 100%;
        }

        .flex-row {
            flex-direction: column;
            gap: 20px;
        }
    }

    @media (max-width: 900px) {

        /* Make widgets edge‑to‑edge */
        .progress-widget,
        .status-section,
        .chart-section,
        .info-section {
            border: none;
            box-shadow: none;
            padding: 0 3vw;
            width: 100%;
            max-width: 100%;
        }

        /* Smaller headings & big numbers */
        .progress-card h3 {
            font-size: 1.1rem;
        }

        .progress-value span {
            font-size: 1.6rem;
        }

        /* Stack the two big columns neatly */
        .flex-row {
            gap: 20px;
        }

        /* Meals table → card style */
        .meals-table thead {
            display: none;
        }

        .meals-table tr {
            display: block;
            margin-bottom: 0.75rem;
            border: 1px solid #e1e4e8;
            border-radius: 8px;
            overflow: hidden;
        }

        .meals-table td {
            display: flex;
            justify-content: space-between;
            padding: 8px 12px;
            font-size: 0.95rem;
            border: none;
        }

        .meals-table td::before {
            content: attr(data-label);
            font-weight: 600;
            color: #555;
            margin-right: 1rem;
        }

        .meals-table tr:nth-child(even) td {
            background: #fafafa;
        }
    }

    /* Weather widget alignment */
    .weather-widget .current{
        display:flex;
        align-items:center;
        gap:10px;
    }
    .weather-widget .temp{
        font-size:1.6rem;
        font-weight:600;
    }

    /* Animated flame for streak */
    .fire-icon {
        display: inline-block;
        font-size: 1.6rem;
        margin-left: 6px;
        transform-origin: center bottom;
        animation: flicker 1s infinite ease-in-out alternate;
        text-shadow: 0 0 6px rgba(255, 102, 0, 0.8),
            0 0 12px rgba(255, 140, 0, 0.6);
    }

    @keyframes flicker {

        0%,
        100% {
            transform: scale(1) rotate(-2deg);
            opacity: 0.9;
        }

        50% {
            transform: scale(1.15) rotate(2deg);
            opacity: 1;
        }
    }

    .fire-icon.paused {
        animation-play-state: paused;
    }

    /* Responsive two-column layout for medium screens */
    @media (max-width: 1500px) {
        .flex-row {
            flex-wrap: wrap;
            flex-direction: row;
            gap: 20px;
        }

        .flex {
            flex: 1 1 48%;
            min-width: 300px;
        }
    }
</style>

<style>
/* Dark mode styles */
     [data-theme="dark"] main.dashboard {
    background: #1a1a1a !important;
    color: #ffffff !important;
}

[data-theme="dark"] .progress-widget,
[data-theme="dark"] .status-section,
[data-theme="dark"] .chart-section,
[data-theme="dark"] .info-section,
[data-theme="dark"] .streak-box {
    background: #2d2d2d !important;
    border: 1px solid #404040 !important;
    color: #ffffff !important;
}

[data-theme="dark"] .progress-card {
    background: #2d2d2d !important;
    color: #ffffff !important;
}

[data-theme="dark"] .progress-card h3 {
    color: #ffffff !important;
}

[data-theme="dark"] .progress-value span {
    color: #ffffff !important;
}

[data-theme="dark"] .progress-bar {
    background-color: #404040 !important;
}

[data-theme="dark"] .progress-labels {
    color: #cccccc !important;
}

[data-theme="dark"] .status-section h4 {
    color: #ffffff !important;
}

[data-theme="dark"] .status-section p {
    color: #cccccc !important;
}

[data-theme="dark"] .chart-section h4 {
    color: #ffffff !important;
}

[data-theme="dark"] .chart-section p {
    color: #cccccc !important;
}

[data-theme="dark"] .meals-table {
    background: #2d2d2d !important;
    color: #ffffff !important;
}

[data-theme="dark"] .meals-table th {
    background: #404040 !important;
    color: #ffffff !important;
    border-bottom: 1px solid #555555 !important;
}

[data-theme="dark"] .meals-table td {
    background: #2d2d2d !important;
    color: #ffffff !important;
    border-bottom: 1px solid #404040 !important;
}

[data-theme="dark"] .meals-table tr:hover {
    background-color: #3d3d3d !important;
}

[data-theme="dark"] .meals-table tr:hover td {
    background-color: #3d3d3d !important;
}

[data-theme="dark"] .streak-box {
    background: #2d2d2d !important;
}

[data-theme="dark"] .streak-title h3 {
    color: #ffffff !important;
}

[data-theme="dark"] .streak-box p {
    color: #cccccc !important;
}

[data-theme="dark"] .streak-count {
    color: #4CAF50 !important; 
}

[data-theme="dark"] .longest-streak-count {
    color: #ff6b6b !important; 
}

[data-theme="dark"] .btn-primary {
    background-color: #4a7ee3 !important;
    color: white !important;
}

[data-theme="dark"] .btn-primary a {
    color: white !important;
}

[data-theme="dark"] .btn-primary:hover {
    background-color: #3a5bb3 !important;
}

[data-theme="dark"] canvas {
    background: transparent !important;
}

[data-theme="dark"] .error-message {
    background-color: #4a2c2c !important;
    border-color: #8b4a4a !important;
    color: #ffcccc !important;
}

[data-theme="dark"] .success-message {
    background-color: #2c4a2c !important;
    border-color: #4a8b4a !important;
    color: #ccffcc !important;
}


[data-theme="dark"] .average-calories {
    color: #ffffff !important;
}

@media (max-width: 900px) {
    [data-theme="dark"] .meals-table tr:nth-child(even) td {
        background: #3d3d3d !important;
    }
    
    [data-theme="dark"] .meals-table td::before {
        color: #cccccc !important;
    }
}
</style>