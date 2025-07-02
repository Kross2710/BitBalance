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
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>BitBalance Administrator</title>
    <link rel="stylesheet" href="../css/style.css">
    <link rel="stylesheet" href="../css/admin.css">
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
        <div class="flex">
            <div class="box info">
                <h3>Welcome to the Admin Dashboard</h3>
                <p>Here you can manage users, products, orders, and view activity logs.</p>
                <p>Use the sidebar to navigate through different sections.</p>
            </div>
        </div>
        <div class="flex-row">
            <div class="flex">
                <div class="box chart">
                    <h3>Total Users</h3>
                    <p class="big-number"><?php echo $totalUsers; ?></p>
                    <canvas id="userChart"></canvas>
                </div>
            </div>
            <div class="flex">
                <div class="box chart">
                    <h3>Total Orders</h3>
                    <p class="big-number"><?php echo $totalOrders; ?></p>
                    <canvas id="orderChart"></canvas>
                </div>
            </div>
        </div>
        <div class="flex">
            <div class="box info">
                <h3>Recent Activity</h3>
                <p>Monitor the latest activities on the platform.</p>
                <p>Check user registrations, orders, and logs for insights.</p>
            </div>
        </div>
        <div class="flex-row">
            <div class="flex">
                <div class="box chart">
                    <h3>Total Logs (Last 7 Days)</h3>
                    <p class="big-number"><?php echo $last7DaysLogCount; ?></p>
                    <canvas id="logChart"></canvas>
                </div>
            </div>
            <div class="flex">
                <div class="box chart">
                    <h3>Total Posts & Comments (Last 7 Days)</h3>
                    <p class="big-number">
                        <?php
                        $totalPosts = getTotalPosts(); // Fetch total posts count
                        $totalComments = getTotalComments(); // Fetch total comments count
                        echo $totalPosts + $totalComments;
                        ?>
                    </p>
                    <canvas id="postCommentChart"></canvas>
                </div>
            </div>
            <div class="flex">
                <div class="box chart">
                    <h3>Total Streak Updated By User (Last 7 Days)</h3>
                    <p class="big-number">
                        <?php
                        $streakUpdatedByUser = getStreakUpdatedByUser(); // Fetch streak updated by user count
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

    const ctx = document.getElementById('userChart').getContext('2d');
    const userChart = new Chart(ctx, {
        type: 'bar',
        data: {
            labels: historyUserLabels,
            datasets: [{
                label: 'New Users',
                data: usersData,
                backgroundColor: 'rgba(153, 102, 255, 1)',
                borderColor: 'rgba(153, 102, 255, 1)',
                borderWidth: 1
            }]
        },
        options: {
            scales: {
                y: {
                    beginAtZero: true
                }
            }
        }
    });

    const ordersData = <?php echo json_encode($ordersData); ?>;
    const historyOrderLabels = <?php echo json_encode($historyOrderLabels); ?>;

    const orderCtx = document.getElementById('orderChart').getContext('2d');
    const orderChart = new Chart(orderCtx, {
        type: 'bar',
        data: {
            labels: historyOrderLabels,
            datasets: [{
                label: 'Total Orders',
                data: ordersData,
                backgroundColor: 'rgba(255, 99, 132, 1)',
                borderColor: 'rgba(255, 99, 132, 1)',
                borderWidth: 1
            }]
        },
        options: {
            scales: {
                y: {
                    beginAtZero: true
                }
            }
        }
    });

    const logData = <?php echo json_encode($logData); ?>;
    const historyLogLabels = <?php echo json_encode($historyLogLabels); ?>;

    const logCtx = document.getElementById('logChart').getContext('2d');
    const logChart = new Chart(logCtx, {
        type: 'bar',
        data: {
            labels: historyLogLabels,
            datasets: [{
                label: 'Total Logs',
                data: logData,
                backgroundColor: 'rgba(75, 192, 192, 1)',
                borderColor: 'rgba(75, 192, 192, 1)',
                borderWidth: 1
            }]
        },
        options: {
            scales: {
                y: {
                    beginAtZero: true
                }
            }
        }
    });

    const postData = <?php echo json_encode($postData); ?>;
    const commentData = <?php echo json_encode($commentData); ?>;
    const postCommentLabels = <?php echo json_encode($postCommentLabels); ?>;

    const postCommentCtx = document.getElementById('postCommentChart').getContext('2d');
    const postCommentChart = new Chart(postCommentCtx, {
        type: 'bar',
        data: {
            labels: postCommentLabels,
            datasets: [
                {
                    label: 'Posts',
                    data: postData,
                    backgroundColor: 'rgba(54, 162, 235, 0.8)',
                    borderColor: 'rgba(54, 162, 235, 1)',
                    borderWidth: 1
                },
                {
                    label: 'Comments',
                    data: commentData,
                    backgroundColor: 'rgba(255, 206, 86, 0.8)',
                    borderColor: 'rgba(255, 206, 86, 1)',
                    borderWidth: 1
                }
            ]
        },
        options: {
            scales: {
                y: {
                    beginAtZero: true
                }
            }
        }
    });

    const streakData = <?php echo json_encode($streakData); ?>;
    const streakLabels = <?php echo json_encode($streakLabels); ?>;

    const streakCtx = document.getElementById('streakChart').getContext('2d');
    const streakChart = new Chart(streakCtx, {
        type: 'bar',
        data: {
            labels: streakLabels,
            datasets: [{
                label: 'Streaks Updated',
                data: streakData,
                backgroundColor: 'rgba(255, 159, 64, 0.8)',
                borderColor: 'rgba(255, 159, 64, 1)',
                borderWidth: 1
            }]
        },
        options: {
            scales: {
                y: {
                    beginAtZero: true
                }
            }
        }
    });
</script>

<style>
    canvas {
        width: 100% !important;
        height: auto !important;
        display: block;
        margin: 0 auto;
    }

    .big-number {
        font-size: 2.5em;
        font-weight: bold;
        text-align: center;
        color: #333;
        margin: 20px 0;
    }

    main.dashboard {
        margin-top: 20px;
        margin-left: 220px;
        border-radius: 10px;
        width: calc(100% - 240px);
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
        flex-direction: row;
        flex: 1 1 0;
        min-width: 0;
    }

    /* For the right column's info section */
    .flex:last-child {
        display: flex;
        flex-direction: column;
        justify-content: stretch;
    }

    @media (max-width: 900px) {

        /* Main area fills width */
        main.dashboard {
            margin: 80px 12px 20px 12px;
            /* space for toggle */
            width: auto;
        }

        /* Stack all flex rows vertically */
        .flex-row {
            flex-direction: column;
            gap: 18px;
        }

        .flex {
            flex: 1 1 100%;
        }

        .box.chart,
        .box.info {
            padding: 14px;
        }

        .big-number {
            font-size: 2rem;
        }
    }

    /* Ultra-small phones */
    @media (max-width: 600px) {
        .big-number {
            font-size: 1.7rem;
        }

        canvas {
            /* make charts readable */
            max-height: 260px;
        }
    }
</style>