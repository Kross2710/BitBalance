<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/include/init.php';
require_once __DIR__ . '/include/handlers/log_attempt.php';

// Nếu đã đăng nhập, chuyển hướng ngay vào Dashboard
if ($isLoggedIn) {
    log_attempt($pdo, $user['user_id'], 'view', 'User ' . $user['user_id'] . ' redirected from index to dashboard', 'dashboard', null);
    header("Location: dashboard/dashboard.php");
    exit();
}
?>

<!DOCTYPE html>
<html lang="en" data-theme="<?php echo isset($_SESSION['user']) ? ($_SESSION['user']['theme_preference'] ?? 'light') : 'light'; ?>">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>BitBalance - Master Your Nutrition</title>
    
    <?php
    $pageCss = ['css/pages/index.css'];
    include PROJECT_ROOT . 'views/head_css.php';
    ?>

    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="https://kit.fontawesome.com/b94f65ead2.js" crossorigin="anonymous"></script>
</head>

<body>
    <?php include PROJECT_ROOT . 'views/header.php'; ?>

    <section class="hero-section">
        <h1 class="hero-title">Track Your Calories <br><span class="gradient-text">At Any Time</span></h1>
        <p class="hero-subtitle">
            BitBalance makes nutrition tracking simple, intuitive, and enjoyable. Join our community today and start your wellness journey.
        </p>
        <a href="signup.php" class="btn-start">
            <i class="fas fa-rocket"></i> Get Started Free
        </a>
    </section>

    <main class="landing-grid">
        
        <div class="grid-card card-welcome">
            <div class="icon"><i class="fas fa-leaf"></i></div>
            <h2>Simple & Enjoyable Tracking</h2>
            <p>
                Forget about complicated spreadsheets. Our dashboard gives you a clear view of your daily intake, streaks, and macro targets at a glance.
            </p>
        </div>

        <div class="grid-card card-forum">
            <div class="icon"><i class="fas fa-comments"></i></div>
            <h3>Join the Discussion</h3>
            <p>Share recipes, ask questions, and find motivation.</p>
            <a href="forum.php" class="text-link">Visit Forums &rarr;</a>
        </div>

        <div class="grid-card card-chart">
            <div style="display:flex; justify-content:space-between; width:100%; margin-bottom:15px;">
                <h3>Your Progress</h3>
                <small style="color:#6c757d;">(Demo Data)</small>
            </div>
            <div class="chart-wrapper">
                <canvas id="calorieChart"></canvas>
            </div>
        </div>

        <div class="grid-card card-video">
            <iframe 
                src="https://www.youtube.com/embed/1-q-nClpmWQ?controls=0&rel=0" 
                frameborder="0" 
                allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture" 
                allowfullscreen>
            </iframe>
        </div>

    </main>

    <?php include 'views/footer.php'; ?>

    <script>
        const ctx = document.getElementById('calorieChart').getContext('2d');
        
        // Gradient for bars
        let gradient = ctx.createLinearGradient(0, 0, 0, 400);
        gradient.addColorStop(0, '#4a7ee3');
        gradient.addColorStop(1, 'rgba(74, 126, 227, 0.2)');

        new Chart(ctx, {
            type: 'bar',
            data: {
                labels: ['Oct', 'Nov', 'Dec', 'Jan', 'Feb'],
                datasets: [{
                    label: 'Consumed',
                    data: [2100, 1950, 2300, 1850, 2000],
                    backgroundColor: gradient,
                    borderRadius: 8,
                    barThickness: 30
                },
                {
                    label: 'Goal',
                    data: [2000, 2000, 2000, 2000, 2000],
                    type: 'line',
                    borderColor: '#ff9966',
                    borderWidth: 3,
                    pointRadius: 4,
                    pointBackgroundColor: '#fff',
                    fill: false,
                    tension: 0.4
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { position: 'bottom' },
                    tooltip: {
                        backgroundColor: 'rgba(255, 255, 255, 0.9)',
                        titleColor: '#333',
                        bodyColor: '#666',
                        borderColor: '#eee',
                        borderWidth: 1,
                        padding: 10
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        grid: { color: '#f0f0f0', borderDash: [5, 5] },
                        border: { display: false }
                    },
                    x: {
                        grid: { display: false },
                        border: { display: false }
                    }
                }
            }
        });
    </script>
</body>
</html>