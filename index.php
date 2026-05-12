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
    
    <?php include PROJECT_ROOT . 'views/head_css.php'; ?>

    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="https://kit.fontawesome.com/b94f65ead2.js" crossorigin="anonymous"></script>

    <style>
        /* 1. PAGE-SPECIFIC VARIABLES (dùng tokens chung, chỉ thêm biến riêng) */
        :root {
            --border-light: 1px solid rgba(0,0,0,0.05);
        }

        [data-theme="dark"] {
            --border-light: 1px solid rgba(255,255,255,0.1);
        }

        /* 2. HERO SECTION */
        .hero-section {
            text-align: center;
            padding: 100px 20px 60px;
            max-width: 900px;
            margin: 0 auto;
        }

        .hero-title {
            font-size: 3.5rem;
            font-weight: 800;
            margin-bottom: 20px;
            line-height: 1.2;
            letter-spacing: -1px;
        }

        .gradient-text {
            background: var(--primary-gradient);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }

        .hero-subtitle {
            font-size: 1.2rem;
            color: var(--text-secondary);
            margin-bottom: 40px;
            max-width: 600px;
            margin-left: auto;
            margin-right: auto;
        }

        .btn-start {
            display: inline-flex;
            align-items: center;
            gap: 10px;
            background: var(--primary-gradient);
            color: white;
            padding: 16px 40px;
            border-radius: 50px;
            font-size: 1.1rem;
            font-weight: 700;
            text-decoration: none;
            box-shadow: 0 10px 25px rgba(74, 126, 227, 0.4);
            transition: transform 0.2s, box-shadow 0.2s;
        }

        .btn-start:hover {
            transform: translateY(-3px);
            box-shadow: 0 15px 35px rgba(74, 126, 227, 0.5);
        }

        /* 3. LANDING GRID (BENTO) */
        .landing-grid {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 20px 80px;
            display: grid;
            grid-template-columns: repeat(12, 1fr);
            gap: 24px;
            grid-auto-rows: minmax(200px, auto);
        }

        /* Card Styles */
        .grid-card {
            background: var(--card-bg);
            border-radius: var(--border-radius);
            padding: 30px;
            box-shadow: var(--shadow-soft);
            border: var(--border-light);
            display: flex;
            flex-direction: column;
            justify-content: center;
            position: relative;
            overflow: hidden;
        }

        .grid-card h2, .grid-card h3 {
            font-size: 1.5rem;
            font-weight: 700;
            margin-bottom: 15px;
            color: var(--text-primary);
        }

        .grid-card p {
            color: var(--text-secondary);
            font-size: 1rem;
            margin-bottom: 20px;
        }

        /* A. Welcome Card */
        .card-welcome {
            grid-column: span 7;
            background: linear-gradient(135deg, #e3f2fd, #ffffff);
        }
        [data-theme="dark"] .card-welcome { background: linear-gradient(135deg, #1f2937, #111827); }

        .card-welcome .icon {
            font-size: 3rem;
            color: #4a7ee3;
            margin-bottom: 20px;
        }

        /* B. Forum Card */
        .card-forum {
            grid-column: span 5;
            text-align: center;
            align-items: center;
            background: #fff8e1; /* Light Yellow */
        }
        [data-theme="dark"] .card-forum { background: #2c2a20; }

        .card-forum .icon {
            font-size: 3rem;
            color: #ffb300;
            margin-bottom: 15px;
        }

        /* C. Chart Card */
        .card-chart {
            grid-column: span 8;
            height: 350px;
            justify-content: flex-start;
        }

        .chart-wrapper {
            position: relative;
            height: 100%;
            width: 100%;
        }

        /* D. Video Card */
        .card-video {
            grid-column: span 4;
            padding: 0; /* Remove padding for video */
            background: #000;
        }

        .card-video iframe {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        /* Link styled as button in text */
        .text-link {
            color: #4a7ee3;
            font-weight: 600;
            text-decoration: none;
        }
        .text-link:hover { text-decoration: underline; }

        /* RESPONSIVE */
        @media (max-width: 900px) {
            .hero-title { font-size: 2.5rem; }
            .card-welcome, .card-forum, .card-chart, .card-video {
                grid-column: span 12; /* Full width on mobile */
            }
            .card-chart { height: auto; min-height: 300px; }
            .card-video { min-height: 250px; }
        }
    </style>
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