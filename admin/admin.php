<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/../include/init.php';
require_once __DIR__ . '/handlers/admin_data.php';
require_once __DIR__ . '/../include/handlers/log_attempt.php';

if ($isLoggedIn) {
    if ($_SESSION['user']['role'] === 'admin') {
        $admin_id = $_SESSION['user']['user_id'] ?? null;
        if ($admin_id) {
            log_attempt($pdo, $admin_id, 'view', 'admin dashboard overview', 'admin');
        }
    }
}

$activePage = 'dashboard'; // Set the active page for the sidebar

// --- Platform & Activity data ---
$totalUsers = getTotalUsers();
$last7DaysLogCount = Last7DaysLogCount();
$todayNewUsers = getTodayNewUsers();
$userStatusBreakdown = getUserStatusBreakdown();
$recentActivity = getRecentActivity(10);
$totalPosts = getTotalPosts();
$totalComments = getTotalComments();

// --- Diet & Beats data ---
$totalFoodLogged = getTotalFoodLogged();
$avgCaloriesByCategory = getAverageCaloriesByCategory();
$topBeatsVibes = getTopBeatsVibes();
?>
<!DOCTYPE html>
<html lang="en" data-theme="<?php echo isset($_SESSION['user']) ? ($_SESSION['user']['theme_preference'] ?? 'system') : 'system'; ?>">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>BitBalance Administrator - Dashboard</title>
    <?php include __DIR__ . '/../views/theme-init.php'; ?>
    <link rel="stylesheet" href="../css/style.css">
    <link rel="stylesheet" href="../css/admin.css?v=<?php echo @filemtime(__DIR__ . '/../css/admin.css'); ?>">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="https://kit.fontawesome.com/b94f65ead2.js" crossorigin="anonymous"></script>
</head>

<body>
    <?php
    // Include header and sidebar
    include 'views/admin-header.php';
    include 'views/admin-sidebar.php';
    ?>
    <main class="dashboard">
        <div class="toolbar" style="margin-bottom: 24px;">
            <h1 class="page-title"><i class="fa-solid fa-chart-line"></i> Dashboard</h1>
            <div style="font-weight: 700; color: var(--color-text-secondary); background: var(--color-surface-alt); padding: 8px 14px; border-radius: 10px; border: 2px solid var(--color-border);">
                <i class="fa-solid fa-calendar-day"></i> UTC+7 Connection
            </div>
        </div>

        <!-- Bouncy Tabs Controls (Updated to 2 tabs as products were removed) -->
        <div class="tabs-nav" style="display: flex; gap: 12px; margin-bottom: 24px; border-bottom: 2px solid var(--color-border-subtle); padding-bottom: 12px; flex-wrap: wrap;">
            <button class="tab-btn active" onclick="switchTab(event, 'platform')"><i class="fa-solid fa-chart-line"></i> Platform Overview</button>
            <button class="tab-btn" onclick="switchTab(event, 'diet')"><i class="fa-solid fa-utensils"></i> Diet &amp; Beats</button>
        </div>

        <!-- ==================== TAB 1: PLATFORM OVERVIEW ==================== -->
        <div id="tab-platform" class="tab-content active">
            <div class="kpi-row" style="grid-template-columns: repeat(3, 1fr);">
                <div class="kpi-card">
                    <div class="kpi-icon"><i class="fa-solid fa-users"></i></div>
                    <div class="kpi-body">
                        <span class="kpi-label">Total Users</span>
                        <span class="kpi-value"><?php echo $totalUsers; ?></span>
                    </div>
                </div>
                <div class="kpi-card kpi-blue">
                    <div class="kpi-icon"><i class="fa-solid fa-user-plus"></i></div>
                    <div class="kpi-body">
                        <span class="kpi-label">New Users Today</span>
                        <span class="kpi-value"><?php echo $todayNewUsers; ?></span>
                    </div>
                </div>
                <div class="kpi-card kpi-purple">
                    <div class="kpi-icon"><i class="fa-solid fa-clipboard-list"></i></div>
                    <div class="kpi-body">
                        <span class="kpi-label">Logs (Last 7 Days)</span>
                        <span class="kpi-value"><?php echo $last7DaysLogCount; ?></span>
                    </div>
                </div>
            </div>

            <div class="flex-row" style="display: grid; grid-template-columns: 2fr 1fr; gap: 20px; margin-bottom: 20px;">
                <div class="box chart">
                    <h3><i class="fa-solid fa-users"></i> Total Users Growth</h3>
                    <canvas id="userChart" style="max-height: 280px;"></canvas>
                </div>
                <div class="box chart">
                    <h3><i class="fa-solid fa-circle-half-stroke"></i> User Statuses</h3>
                    <canvas id="userStatusChart" style="max-height: 280px;"></canvas>
                </div>
            </div>

            <div class="flex-row" style="display: grid; grid-template-columns: 1fr; gap: 20px; margin-bottom: 20px;">
                <div class="box info">
                    <h3><i class="fa-solid fa-bolt"></i> Recent Activity</h3>
                    <?php if (empty($recentActivity)): ?>
                        <p>No recent activity.</p>
                    <?php else: ?>
                        <table class="mini-table">
                            <thead>
                                <tr>
                                    <th>User</th>
                                    <th>Action</th>
                                    <th>Target</th>
                                    <th>When</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($recentActivity as $a): ?>
                                    <tr>
                                        <td><strong><?php echo htmlspecialchars($a['user_name']); ?></strong></td>
                                        <td>
                                            <span style="font-family: monospace; color: var(--color-primary); background: var(--color-primary-soft); padding: 2px 6px; border-radius: 4px; font-weight: 600; font-size: 0.85rem;">
                                                <?php echo htmlspecialchars($a['action_type']); ?>
                                            </span>
                                            <?php if (!empty($a['description'])): ?>
                                                <span class="muted" style="margin-left: 6px; font-size: 0.9rem;"><?php echo htmlspecialchars($a['description']); ?></span>
                                            <?php endif; ?>
                                        </td>
                                        <td><?php echo !empty($a['target_table']) ? htmlspecialchars($a['target_table']) . ' (' . (int)$a['target_id'] . ')' : '—'; ?></td>
                                        <td><?php echo date('d-m-Y H:i', strtotime($a['created_at'])); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>
                </div>
            </div>

            <div class="flex-row" style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 20px;">
                <div class="box chart">
                    <h3><i class="fa-solid fa-clipboard-list"></i> Total Logs (Last 7 Days)</h3>
                    <p class="big-number"><?php echo $last7DaysLogCount; ?></p>
                    <canvas id="logChart" style="max-height: 180px;"></canvas>
                </div>
                <div class="box chart">
                    <h3><i class="fa-solid fa-fire"></i> Streak Updates</h3>
                    <p class="big-number">
                        <?php
                        $streakUpdatedByUser = getStreakUpdatedByUser();
                        echo $streakUpdatedByUser;
                        ?>
                    </p>
                    <canvas id="streakChart" style="max-height: 180px;"></canvas>
                </div>
            </div>
        </div>

        <!-- ==================== TAB 2: DIET & BEATS TRENDS ==================== -->
        <div id="tab-diet" class="tab-content" style="display: none;">
            <div class="kpi-row" style="grid-template-columns: repeat(3, 1fr);">
                <div class="kpi-card kpi-orange">
                    <div class="kpi-icon"><i class="fa-solid fa-utensils"></i></div>
                    <div class="kpi-body">
                        <span class="kpi-label">Food Logs Recorded</span>
                        <span class="kpi-value"><?php echo $totalFoodLogged; ?></span>
                    </div>
                </div>
                <div class="kpi-card">
                    <div class="kpi-icon"><i class="fa-solid fa-fire"></i></div>
                    <div class="kpi-body">
                        <span class="kpi-label">Streak Record Holders</span>
                        <span class="kpi-value"><?php echo $streakUpdatedByUser; ?></span>
                    </div>
                </div>
                <div class="kpi-card kpi-blue">
                    <div class="kpi-icon"><i class="fa-solid fa-music"></i></div>
                    <div class="kpi-body">
                        <span class="kpi-label">Beats &amp; Diet Mixes</span>
                        <span class="kpi-value">Active Engine</span>
                    </div>
                </div>
            </div>

            <div class="flex-row" style="display: grid; grid-template-columns: 2fr 1fr; gap: 20px;">
                <div class="box chart">
                    <h3><i class="fa-solid fa-bowl-food"></i> Average Calories by Meal Category</h3>
                    <canvas id="calorieCategoryChart" style="max-height: 280px;"></canvas>
                </div>
                
                <div class="box info">
                    <h3><i class="fa-solid fa-guitar"></i> Top Detected Vibe archetypes</h3>
                    <?php if (empty($topBeatsVibes)): ?>
                        <p class="muted">No DJ mixes recorded yet.</p>
                    <?php else: ?>
                        <table class="mini-table">
                            <thead>
                                <tr>
                                    <th>Vibe archetype</th>
                                    <th>Total Mixes</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($topBeatsVibes as $vibe): ?>
                                    <tr>
                                        <td>
                                            <span style="font-weight: 700; color: var(--color-accent); background: rgba(255, 150, 0, 0.12); padding: 4px 8px; border-radius: 6px; text-transform: capitalize;">
                                                <i class="fa-solid fa-headphones"></i> <?php echo htmlspecialchars($vibe['detected_vibe']); ?>
                                            </span>
                                        </td>
                                        <td><strong><?php echo (int)$vibe['total']; ?></strong> mixes</td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </main>

    <?php include '../views/footer.php'; ?>
</body>

<style>
    /* Bouncy Tab Button CSS Override */
    .tab-btn {
        background: var(--color-surface);
        color: var(--color-text-secondary);
        border: 2px solid var(--color-border);
        border-radius: var(--radius-md, 14px);
        padding: 10px 20px;
        font-weight: 700;
        cursor: pointer;
        box-shadow: 0 4px 0 var(--color-border-subtle);
        transition: all 0.1s ease;
        display: inline-flex;
        align-items: center;
        gap: 8px;
        outline: none;
    }
    
    .tab-btn:hover {
        transform: translateY(-2px);
        box-shadow: 0 6px 0 var(--color-border-subtle);
        color: var(--color-primary);
    }
    
    .tab-btn:active {
        transform: translateY(4px);
        box-shadow: 0 0 0 transparent;
    }
    
    .tab-btn.active {
        background-color: var(--color-primary-soft);
        color: var(--color-primary);
        border-color: var(--color-primary);
        box-shadow: 0 4px 0 var(--color-primary-hover);
    }
</style>

<script>
    function switchTab(event, tabId) {
        document.querySelectorAll('.tab-content').forEach(el => el.style.display = 'none');
        document.querySelectorAll('.tab-btn').forEach(el => el.classList.remove('active'));
        
        document.getElementById('tab-' + tabId).style.display = 'block';
        event.currentTarget.classList.add('active');
    }

    // Chart Defaults
    const chartDefaults = {
        responsive: true,
        maintainAspectRatio: false,
        plugins: { legend: { display: false } },
        scales: {
            y: { beginAtZero: true, grid: { color: 'rgba(0,0,0,0.04)' }, ticks: { color: 'rgba(0,0,0,0.5)' } },
            x: { grid: { display: false }, ticks: { color: 'rgba(0,0,0,0.5)' } }
        }
    };

    // User growth chart
    const usersData = <?php echo json_encode($usersData); ?>;
    const historyUserLabels = <?php echo json_encode($historyUserLabels); ?>;
    const ctx = document.getElementById('userChart').getContext('2d');
    new Chart(ctx, {
        type: 'bar',
        data: {
            labels: historyUserLabels,
            datasets: [{
                label: 'New Users',
                data: usersData,
                backgroundColor: '#58CC02',
                borderRadius: 8,
                borderWidth: 0
            }]
        },
        options: chartDefaults
    });

    // User status chart (doughnut)
    const userStatusBreakdown = <?php echo json_encode($userStatusBreakdown); ?>;
    const userStatusCtx = document.getElementById('userStatusChart').getContext('2d');
    new Chart(userStatusCtx, {
        type: 'doughnut',
        data: {
            labels: ['Active', 'Banned', 'Archived'],
            datasets: [{
                data: [userStatusBreakdown.active, userStatusBreakdown.banned, userStatusBreakdown.archived],
                backgroundColor: ['#58CC02', '#ef4444', '#94a3b8'],
                borderWidth: 0
            }]
        },
        options: { cutout: '65%', plugins: { legend: { position: 'bottom', labels: { boxWidth: 12, usePointStyle: true } } } }
    });

    // Activity log chart
    const logData = <?php echo json_encode($logData); ?>;
    const historyLogLabels = <?php echo json_encode($historyLogLabels); ?>;
    const logCtx = document.getElementById('logChart').getContext('2d');
    new Chart(logCtx, {
        type: 'line',
        data: {
            labels: historyLogLabels,
            datasets: [{
                data: logData,
                borderColor: '#1CB0F6',
                backgroundColor: 'rgba(28, 176, 246, 0.08)',
                fill: true,
                tension: 0.35,
                borderWidth: 3,
                pointRadius: 4
            }]
        },
        options: chartDefaults
    });

    // Posts & Comments chart
    const postData = <?php echo json_encode($postData); ?>;
    const commentData = <?php echo json_encode($commentData); ?>;
    const postCommentLabels = <?php echo json_encode($postCommentLabels); ?>;
    const postCommentCtx = document.getElementById('postCommentChart').getContext('2d');
    new Chart(postCommentCtx, {
        type: 'bar',
        data: {
            labels: postCommentLabels,
            datasets: [
                { label: 'Posts', data: postData, backgroundColor: '#1CB0F6', borderRadius: 5 },
                { label: 'Comments', data: commentData, backgroundColor: '#FF9600', borderRadius: 5 }
            ]
        },
        options: {
            ...chartDefaults,
            scales: { y: { stacked: true }, x: { stacked: true } }
        }
    });

    // Streaks chart
    const streakData = <?php echo json_encode($streakData); ?>;
    const streakLabels = <?php echo json_encode($streakLabels); ?>;
    const streakCtx = document.getElementById('streakChart').getContext('2d');
    new Chart(streakCtx, {
        type: 'bar',
        data: {
            labels: streakLabels,
            datasets: [{
                data: streakData,
                backgroundColor: '#FF9600',
                borderRadius: 5
            }]
        },
        options: chartDefaults
    });

    // Calorie category chart (Bar Chart)
    const avgCalories = <?php echo json_encode($avgCaloriesByCategory); ?>;
    const calorieCtx = document.getElementById('calorieCategoryChart').getContext('2d');
    new Chart(calorieCtx, {
        type: 'bar',
        data: {
            labels: ['Breakfast', 'Lunch', 'Dinner', 'Snacks'],
            datasets: [{
                data: [avgCalories.breakfast, avgCalories.lunch, avgCalories.dinner, avgCalories.snack],
                backgroundColor: ['#1CB0F6', '#FF9600', '#3b82f6', '#9c27b0'],
                borderRadius: 8,
                borderWidth: 0
            }]
        },
        options: chartDefaults
    });
</script>
</html>