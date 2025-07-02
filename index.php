<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/include/init.php';
require_once __DIR__ . '/include/handlers/log_attempt.php';

if ($isLoggedIn) {
    // Log the user activity
    log_attempt($pdo, $user['user_id'], 'view', 'User ' . $user['user_id'] . ' clicked on dashboard', 'dashboard', null);
    // Redirect to dashboard if user is logged in
    header("Location: dashboard/dashboard.php");
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>BitBalance</title>
    <link rel="stylesheet" href="css/style.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="https://kit.fontawesome.com/b94f65ead2.js" crossorigin="anonymous"></script>
</head>

<body class="index">
    <?php include PROJECT_ROOT . 'views/header.php'; ?>

    <main class="index">
        <?php if ($isLoggedIn): ?>
            <h1>Welcome <?php echo htmlspecialchars($user['first_name']); ?>.</h1>
            <h1>Track Your Calories <br>At Any Time</h1>
        <?php else: ?>
            <h1>Track Your Calories</h1>
            <h1>At Any Time</h1>
            <a href="login.php"><button class="get-started">Get Started</button></a>
        <?php endif; ?>

        <div class="content">
            <div class="box welcome">
                <h2>Welcome to BitBalance!</h2>
                <div class="text">
                    <p>
                        Our website makes calorie tracking simple and enjoyable.
                        <?php if (!$isLoggedIn): ?>
                            Join our community to reach your nutrition goals.
                        <?php else: ?>
                            Continue your journey to reach your nutrition goals.
                        <?php endif; ?>
                    </p>
                </div>
            </div>

            <div class="box forum">
                <h2>Discussion Forum</h2>
                <div class="text">
                    <p>
                        Share tips, ask questions, and connect with others. Try it now!
                    </p>
                </div>
            </div>

            <?php if ($isLoggedIn): ?>
                <div class="box chart">
                    <h3>Your Month on Month Calories</h3>
                    <canvas id="calorieChart" width="400" height="250" style="width: 100%; height: auto; max-width: 100%;"></canvas>
                </div>
            <?php else: ?>
                <div class="box chart">
                    <h3>Sample: Month on Month Calories</h3>
                    <canvas id="calorieChart" style="width: 100%; height: auto; max-width: 100%;"></canvas>
                    <p style="text-align: center; margin-top: 10px; font-style: italic;">
                        <a href="login.php">Sign in</a> to see your personal data
                    </p>
                </div>
            <?php endif; ?>

            <div class="box chart">
                <iframe width="100%" height="300"
                    src="https://www.youtube.com/embed/1-q-nClpmWQ" frameborder="0"
                    allow="autoplay; encrypted-media; picture-in-picture" allowfullscreen>
                </iframe>
            </div>
        </div>
    </main>

    <script>
        const ctx = document.getElementById('calorieChart').getContext('2d');
        const calorieChart = new Chart(ctx, {
            type: 'bar',
            data: {
                labels: ['October', 'November', 'December', 'January', 'February'],
                datasets: [{
                    label: 'Consumed',
                    data: [62300, 53000, 56000, 65000, 76000],
                    backgroundColor: '#72A9F3'
                },
                {
                    label: 'Goals',
                    data: [60000, 55000, 58000, 70000, 80000],
                    backgroundColor: '#FF6384'
                }]
            },
            options: {
                plugins: {
                    legend: {
                        display: true,
                        labels: {
                            font: {
                                weight: 'bold'
                            }
                        }
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            callback: function (value) {
                                return value.toLocaleString();
                            }
                        }
                    }
                }
            }
        });
    </script>
</body>
<?php include 'views/footer.php'; ?>

</html>