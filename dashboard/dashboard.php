<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/../include/init.php';
require_once __DIR__ . '/handlers/dashboard_data.php';
require_once __DIR__ . '/handlers/functions.php';
require_once __DIR__ . '/../include/handlers/log_attempt.php';

if ($isLoggedIn) {
    // Real User
    log_attempt($pdo, $user['user_id'], 'view', 'User ' . $user['user_id'] . ' clicked on dashboard', 'dashboard', null);
    $displayUser = $user['user_name']; // Tên thật
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
?>

<!DOCTYPE html>
<html lang="en"
    data-theme="<?php echo isset($_SESSION['user']) ? ($_SESSION['user']['theme_preference'] ?? 'light') : 'light'; ?>">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>BitBalance Dashboard</title>
    <?php include PROJECT_ROOT . 'views/head_css.php'; ?>
    <link rel="stylesheet" href="<?= BASE_URL ?>css/dashboard.css?v=<?php echo time(); ?>">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="https://kit.fontawesome.com/b94f65ead2.js" crossorigin="anonymous"></script>
</head>

<body>
    <?php include PROJECT_ROOT . 'views/header.php'; ?>
    <?php include PROJECT_ROOT . 'dashboard/views/sidebar.php'; ?>
    <?php include PROJECT_ROOT . 'dashboard/views/right-sidebar.php'; ?>

    <main class="dashboard">

        <?php if (!$isLoggedIn): ?>
            <div class="demo-banner">
                <p><i class="fas fa-info-circle"></i> You are viewing a <strong>Demo Dashboard</strong>. Data shown is for
                    illustration only.</p>
                <a href="<?= BASE_URL ?>signup.php" class="btn-demo-signup">Create Account</a>
            </div>
        <?php endif; ?>

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
                                <div class="progress-fill" id="progressFill" style="width: 0%;"></div>
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

                <section class="status-section <?php echo strtolower($status); ?>">
                    <div class="status-bg-icon">
                        <?php if ($status === 'Ongoing'): echo '<i class="fas fa-running"></i>';
                        elseif ($status === 'Overlimit'):
                            echo '<i class="fas fa-trophy"></i>';
                        else:
                            echo '<i class="fas fa-clipboard-list"></i>';
                        endif; ?>
                    </div>

                    <div class="status-content">
                        <h4>
                            <?php if ($status === 'Ongoing'): ?>
                                <span class="status-badge badge-blue"><i class="fas fa-sync-alt spin"></i> Ongoing</span>
                            <?php elseif ($status === 'Overlimit'): ?>
                                <span class="status-badge badge-gold"><i class="fas fa-star"></i> Goal Reached</span>
                            <?php else: ?>
                                <span class="status-badge badge-gray"><i class="fas fa-exclamation-circle"></i> Unset</span>
                            <?php endif; ?>
                        </h4>

                        <p class="status-message">
                            <?php if ($status === 'Ongoing'): ?> Keep pushing! You're on the right track.
                            <?php elseif ($status === 'Overlimit'): ?> Spectacular! You've crushed your goal today.
                            <?php else: ?> Start your journey by setting a daily goal. <?php endif; ?>
                        </p>

                        <button class="btn-action"
                            onclick="<?php echo $isLoggedIn ? 'openGoalModal()' : "window.location.href='" . BASE_URL . "login.php'"; ?>">
                            Adjust Goal
                        </button>
                    </div>
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
                            <div class="meal-item">
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
                <section class="dashboard-card streak-card">
                    <div class="streak-header">
                        <h3>Streak <span class="fire-icon">🔥</span></h3>
                    </div>
                    <div class="streak-stats">
                        <div class="stat-item">
                            <span class="stat-label">Current</span>
                            <span
                                class="stat-value streak-count"><?= htmlspecialchars($userStreak['logging_streak']) ?></span>
                        </div>
                        <div class="stat-divider"></div>
                        <div class="stat-item">
                            <span class="stat-label">Longest</span>
                            <span
                                class="stat-value longest-streak-count"><?= htmlspecialchars($userStreak['longest_logging_streak']) ?></span>
                        </div>
                    </div>
                    <p class="streak-message">Logging your meals to maintain your streak!</p>
                </section>

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

                <section class="dashboard-card wiki-card">
                    <div class="wiki-bg-icon"><i class="fas fa-lightbulb"></i></div>
                    <div class="wiki-content">
                        <div class="wiki-header-row">
                            <h3>Nutrition Wiki</h3><span class="badge-soon">Coming Soon</span>
                        </div>
                        <p class="wiki-desc">Unlock exclusive tips and healthy recipes.</p>
                        <button class="btn-wiki disabled"><i class="fas fa-lock"></i> Explore</button>
                    </div>
                </section>
            </div>
        </div>
    </main>

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

    <?php include PROJECT_ROOT . 'views/footer.php'; ?>
</body>

</html>

<style>
    .demo-banner {
        background: linear-gradient(90deg, #4a7ee3, #764ba2);
        color: white;
        padding: 15px 20px;
        border-radius: 12px;
        margin-bottom: 25px;
        display: flex;
        justify-content: space-between;
        align-items: center;
        box-shadow: 0 4px 15px rgba(74, 126, 227, 0.25);
    }

    .demo-banner p {
        margin: 0;
        font-size: 1rem;
        color: white;
    }

    .btn-demo-signup {
        background: white;
        color: #4a7ee3;
        text-decoration: none;
        padding: 8px 16px;
        border-radius: 20px;
        font-weight: 700;
        font-size: 0.9rem;
        transition: transform 0.2s;
    }

    .btn-demo-signup:hover {
        transform: scale(1.05);
    }

    /* Responsive Banner */
    @media (max-width: 600px) {
        .demo-banner {
            flex-direction: column;
            text-align: center;
            gap: 10px;
        }
    }

    /* --- GLOBAL MODAL STYLES (Goal, Weight, History) --- */

    .modal-overlay {
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background: rgba(0, 0, 0, 0.5);
        /* Dimmed background */
        backdrop-filter: blur(5px);
        /* Blur effect */
        z-index: 2000;
        display: flex;
        justify-content: center;
        align-items: center;
        opacity: 0;
        visibility: hidden;
        transition: all 0.3s ease;
    }

    .modal-overlay.active {
        opacity: 1;
        visibility: visible;
    }

    .modal-box {
        background: var(--card-bg);
        width: 95%;
        max-width: 480px;
        border-radius: 20px;
        box-shadow: 0 15px 40px rgba(0, 0, 0, 0.25);
        transform: translateY(20px) scale(0.98);
        transition: all 0.3s cubic-bezier(0.34, 1.56, 0.64, 1);
        /* Bouncy effect */
        overflow: hidden;
        display: flex;
        flex-direction: column;
        max-height: 90vh;
        /* Prevent overflow on small screens */
    }

    .modal-overlay.active .modal-box {
        transform: translateY(0) scale(1);
    }

    /* Header */
    .modal-header {
        padding: 20px 25px;
        border-bottom: 1px solid rgba(0, 0, 0, 0.05);
        display: flex;
        justify-content: space-between;
        align-items: center;
        background: var(--card-bg);
    }

    .modal-header h3 {
        margin: 0;
        font-size: 1.4rem;
        font-weight: 700;
        color: var(--text-primary);
    }

    .close-modal {
        background: none;
        border: none;
        font-size: 1.8rem;
        color: var(--text-secondary);
        cursor: pointer;
        transition: color 0.2s, transform 0.2s;
        line-height: 1;
    }

    .close-modal:hover {
        color: #e74c3c;
        transform: rotate(90deg);
    }

    /* Body */
    .modal-body {
        padding: 25px;
        overflow-y: auto;
        /* Allow scrolling if content is long (like history) */
    }

    .modal-desc {
        font-size: 0.95rem;
        color: var(--text-secondary);
        margin-bottom: 20px;
    }

    /* Footer */
    .modal-footer {
        padding: 20px 25px;
        border-top: 1px solid rgba(0, 0, 0, 0.05);
        display: flex;
        justify-content: flex-end;
        gap: 10px;
        background: var(--bg-body);
        /* Slight contrast for footer */
    }

    /* --- Large Input Styling (Reused for Goal & Weight) --- */
    .large-input-group label {
        display: block;
        text-align: center;
        font-weight: 600;
        margin-bottom: 10px;
        color: var(--text-secondary);
    }

    .input-wrapper-lg {
        position: relative;
        max-width: 200px;
        margin: 0 auto;
    }

    .input-icon-lg {
        position: absolute;
        left: 15px;
        top: 50%;
        transform: translateY(-50%);
        font-size: 1.2rem;
        color: var(--primary-color);
    }

    .input-wrapper-lg input {
        width: 100%;
        padding: 12px 12px 12px 40px;
        font-size: 1.5rem;
        font-weight: 700;
        text-align: center;
        border: 2px solid var(--border-color);
        border-radius: 12px;
        background: var(--bg-body);
        color: var(--text-primary);
        transition: all 0.2s;
    }

    .input-wrapper-lg input:focus {
        border-color: var(--primary-color);
        box-shadow: 0 0 0 4px rgba(74, 126, 227, 0.15);
        outline: none;
        background: var(--card-bg);
    }

    /* --- Weight History List Specifics --- */
    .history-list-wrapper {
        max-height: 350px;
        overflow-y: auto;
    }

    .history-list-wrapper table {
        width: 100%;
        border-collapse: collapse;
    }

    .history-list-wrapper tr {
        border-bottom: 1px solid var(--border-color);
    }

    .history-list-wrapper td {
        padding: 12px 15px;
        font-size: 0.95rem;
    }

    .btn-delete-icon {
        background: none;
        border: none;
        color: #ff6b6b;
        cursor: pointer;
        padding: 5px;
        border-radius: 50%;
        transition: background 0.2s;
    }

    .btn-delete-icon:hover {
        background: #ffe5e5;
    }
</style>