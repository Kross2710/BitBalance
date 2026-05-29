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
            log_attempt($pdo, $admin_id, 'view', 'admin dashboard', 'admin');
        }
    }
}

$activePage = 'dashboard'; // Set the active page for the sidebar

$totalUsers = getTotalUsers(); // Fetch total users count
$totalOrders = getTotalOrders(); // Fetch total orders count
$last7DaysLogCount = Last7DaysLogCount();

$totalRevenue = getTotalRevenue();
$avgOrderValue = getAverageOrderValue();
$todayNewUsers = getTodayNewUsers();
$userStatusBreakdown = getUserStatusBreakdown();
$orderStatusBreakdown = getOrderStatusBreakdown();
$topProducts = getTopProducts(5);
$recentActivity = getRecentActivity(10);
?>

<!DOCTYPE html>
<html lang="en" data-theme="<?php echo isset($_SESSION['user']) ? ($_SESSION['user']['theme_preference'] ?? 'system') : 'system'; ?>">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>BitBalance Administrator</title>
    <?php include __DIR__ . '/../views/theme-init.php'; ?>
    <link rel="stylesheet" href="../css/style.css">
    <link rel="stylesheet" href="../css/admin.css?v=<?php echo @filemtime(__DIR__ . '/../css/admin.css'); ?>">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="https://kit.fontawesome.com/b94f65ead2.js" crossorigin="anonymous"></script>
</head>

<body>
    <?php
    // Include the header file
    include 'views/admin-header.php';

    // Include admin-login.php if user is not logged in or not an admin
    if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'admin') {
        include 'admin-login.php';
        exit;
    }

    include 'views/admin-sidebar.php';
    ?>
    <main class="dashboard">
        <h1 class="page-title"><i class="fa-solid fa-chart-line"></i> Dashboard</h1>

        <div class="kpi-row">
            <div class="kpi-card">
                <div class="kpi-icon"><i class="fa-solid fa-dollar-sign"></i></div>
                <div class="kpi-body">
                    <span class="kpi-label">Total Revenue</span>
                    <span class="kpi-value">$<?php echo number_format($totalRevenue, 2); ?></span>
                </div>
            </div>
            <div class="kpi-card kpi-orange">
                <div class="kpi-icon"><i class="fa-solid fa-receipt"></i></div>
                <div class="kpi-body">
                    <span class="kpi-label">Avg. Order Value</span>
                    <span class="kpi-value">$<?php echo number_format($avgOrderValue, 2); ?></span>
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
                <div class="kpi-icon"><i class="fa-solid fa-cart-shopping"></i></div>
                <div class="kpi-body">
                    <span class="kpi-label">Total Orders</span>
                    <span class="kpi-value"><?php echo $totalOrders; ?></span>
                </div>
            </div>
        </div>
        <div class="flex-row">
            <div class="flex">
                <div class="box chart">
                    <h3><i class="fa-solid fa-users"></i> Total Users</h3>
                    <p class="big-number"><?php echo $totalUsers; ?></p>
                    <canvas id="userChart"></canvas>
                </div>
            </div>
            <div class="flex">
                <div class="box chart">
                    <h3><i class="fa-solid fa-cart-shopping"></i> Total Orders</h3>
                    <p class="big-number"><?php echo $totalOrders; ?></p>
                    <canvas id="orderChart"></canvas>
                </div>
            </div>
        </div>
        <div class="flex-row">
            <div class="flex">
                <div class="box chart">
                    <h3><i class="fa-solid fa-circle-half-stroke"></i> User Status Breakdown</h3>
                    <canvas id="userStatusChart"></canvas>
                </div>
            </div>
            <div class="flex">
                <div class="box chart">
                    <h3><i class="fa-solid fa-circle-half-stroke"></i> Order Status Breakdown</h3>
                    <canvas id="orderStatusChart"></canvas>
                </div>
            </div>
        </div>
        <div class="flex-row">
            <div class="flex">
                <div class="box info">
                    <h3><i class="fa-solid fa-trophy"></i> Top 5 Best-Selling Products</h3>
                    <?php if (empty($topProducts)): ?>
                        <p>No sales yet.</p>
                    <?php else: ?>
                        <table class="mini-table">
                            <thead>
                                <tr>
                                    <th>Product</th>
                                    <th>Units sold</th>
                                    <th>Revenue</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($topProducts as $p): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($p['product_name']); ?></td>
                                        <td><?php echo (int) $p['units_sold']; ?></td>
                                        <td>$<?php echo number_format((float) $p['revenue'], 2); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>
                </div>
            </div>
            <div class="flex">
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
                                    <th>When</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($recentActivity as $a): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($a['user_name']); ?></td>
                                        <td>
                                            <strong><?php echo htmlspecialchars($a['action_type']); ?></strong>
                                            <?php if (!empty($a['description'])): ?>
                                                <div class="muted"><?php echo htmlspecialchars($a['description']); ?></div>
                                            <?php endif; ?>
                                        </td>
                                        <td><?php echo date('d-m-Y H:i', strtotime($a['created_at'])); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <div class="flex-row">
            <div class="flex">
                <div class="box chart">
                    <h3><i class="fa-solid fa-clipboard-list"></i> Total Logs (Last 7 Days)</h3>
                    <p class="big-number"><?php echo $last7DaysLogCount; ?></p>
                    <canvas id="logChart"></canvas>
                </div>
            </div>
            <div class="flex">
                <div class="box chart">
                    <h3><i class="fa-solid fa-comments"></i> Posts &amp; Comments (Last 7 Days)</h3>
                    <p class="big-number">
                        <?php
                        $totalPosts = getTotalPosts();
                        $totalComments = getTotalComments();
                        echo $totalPosts + $totalComments;
                        ?>
                    </p>
                    <canvas id="postCommentChart"></canvas>
                </div>
            </div>
            <div class="flex">
                <div class="box chart">
                    <h3><i class="fa-solid fa-fire"></i> Streak Updates (Last 7 Days)</h3>
                    <p class="big-number">
                        <?php
                        $streakUpdatedByUser = getStreakUpdatedByUser();
                        echo $streakUpdatedByUser;
                        ?>
                    </p>
                    <canvas id="streakChart"></canvas>
                </div>
            </div>
        </div>
    </main>

    <?php
    // Include the footer file
    include '../views/footer.php';
    ?>
</body>

<script>
    const usersData = <?php echo json_encode($usersData); ?>;
    const historyUserLabels = <?php echo json_encode($historyUserLabels); ?>;

    const chartDefaults = {
        plugins: { legend: { labels: { color: getComputedStyle(document.documentElement).getPropertyValue('--text-main').trim() || '#1e2937' } } },
        scales: {
            y: { beginAtZero: true, grid: { color: 'rgba(0,0,0,0.05)' }, ticks: { color: 'rgba(0,0,0,0.6)' } },
            x: { grid: { display: false }, ticks: { color: 'rgba(0,0,0,0.6)' } }
        }
    };

    const ctx = document.getElementById('userChart').getContext('2d');
    new Chart(ctx, {
        type: 'bar',
        data: {
            labels: historyUserLabels,
            datasets: [{
                label: 'New Users',
                data: usersData,
                backgroundColor: 'rgba(88, 204, 2, 0.75)',
                borderColor: 'rgba(88, 204, 2, 1)',
                borderRadius: 6,
                borderWidth: 0
            }]
        },
        options: chartDefaults
    });

    const ordersData = <?php echo json_encode($ordersData); ?>;
    const historyOrderLabels = <?php echo json_encode($historyOrderLabels); ?>;

    const orderCtx = document.getElementById('orderChart').getContext('2d');
    new Chart(orderCtx, {
        type: 'bar',
        data: {
            labels: historyOrderLabels,
            datasets: [{
                label: 'Total Orders',
                data: ordersData,
                backgroundColor: 'rgba(28, 176, 246, 0.75)',
                borderColor: 'rgba(28, 176, 246, 1)',
                borderRadius: 6,
                borderWidth: 0
            }]
        },
        options: chartDefaults
    });

    const logData = <?php echo json_encode($logData); ?>;
    const historyLogLabels = <?php echo json_encode($historyLogLabels); ?>;

    const logCtx = document.getElementById('logChart').getContext('2d');
    new Chart(logCtx, {
        type: 'bar',
        data: {
            labels: historyLogLabels,
            datasets: [{
                label: 'Total Logs',
                data: logData,
                backgroundColor: 'rgba(78, 205, 196, 0.75)',
                borderColor: 'rgba(78, 205, 196, 1)',
                borderRadius: 6,
                borderWidth: 0
            }]
        },
        options: chartDefaults
    });

    const postData = <?php echo json_encode($postData); ?>;
    const commentData = <?php echo json_encode($commentData); ?>;
    const postCommentLabels = <?php echo json_encode($postCommentLabels); ?>;

    const postCommentCtx = document.getElementById('postCommentChart').getContext('2d');
    new Chart(postCommentCtx, {
        type: 'bar',
        data: {
            labels: postCommentLabels,
            datasets: [
                {
                    label: 'Posts',
                    data: postData,
                    backgroundColor: 'rgba(28, 176, 246, 0.78)',
                    borderRadius: 6,
                    borderWidth: 0
                },
                {
                    label: 'Comments',
                    data: commentData,
                    backgroundColor: 'rgba(255, 230, 109, 0.85)',
                    borderRadius: 6,
                    borderWidth: 0
                }
            ]
        },
        options: chartDefaults
    });

    const doughnutOpts = {
        cutout: '62%',
        plugins: {
            legend: {
                position: 'bottom',
                labels: { padding: 14, usePointStyle: true, font: { size: 12 } }
            }
        }
    };

    const userStatusBreakdown = <?php echo json_encode($userStatusBreakdown); ?>;
    const userStatusCtx = document.getElementById('userStatusChart').getContext('2d');
    new Chart(userStatusCtx, {
        type: 'doughnut',
        data: {
            labels: ['Active', 'Banned', 'Archived'],
            datasets: [{
                data: [userStatusBreakdown.active, userStatusBreakdown.banned, userStatusBreakdown.archived],
                backgroundColor: ['#58CC02', '#ef4444', '#9e9e9e'],
                borderWidth: 0
            }]
        },
        options: doughnutOpts
    });

    const orderStatusBreakdown = <?php echo json_encode($orderStatusBreakdown); ?>;
    const orderStatusCtx = document.getElementById('orderStatusChart').getContext('2d');
    new Chart(orderStatusCtx, {
        type: 'doughnut',
        data: {
            labels: Object.keys(orderStatusBreakdown).map(s => s.charAt(0).toUpperCase() + s.slice(1)),
            datasets: [{
                data: Object.values(orderStatusBreakdown),
                backgroundColor: ['#FF9600', '#1CB0F6', '#6366f1', '#58CC02', '#ef4444'],
                borderWidth: 0
            }]
        },
        options: doughnutOpts
    });

    const streakData = <?php echo json_encode($streakData); ?>;
    const streakLabels = <?php echo json_encode($streakLabels); ?>;

    const streakCtx = document.getElementById('streakChart').getContext('2d');
    new Chart(streakCtx, {
        type: 'bar',
        data: {
            labels: streakLabels,
            datasets: [{
                label: 'Streaks Updated',
                data: streakData,
                backgroundColor: 'rgba(255, 150, 0, 0.8)',
                borderRadius: 6,
                borderWidth: 0
            }]
        },
        options: chartDefaults
    });
</script>

<style>
    .flex-row {
        display: grid;
        grid-template-columns: repeat(2, 1fr);
        gap: 20px;
        margin-bottom: 20px;
    }

    .flex-row:has(.box:nth-child(3)) { grid-template-columns: repeat(3, 1fr); }

    .flex { display: flex; min-width: 0; }
    .flex > .box { width: 100%; }

    @media (max-width: 900px) {
        .flex-row,
        .flex-row:has(.box:nth-child(3)) { grid-template-columns: 1fr; }
        canvas { max-height: 280px; }
    }
</style>