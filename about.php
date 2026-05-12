<?php
require_once __DIR__ . '/include/init.php';
require_once __DIR__ . '/include/handlers/log_attempt.php';
require_once __DIR__ . '/include/db_config.php';

if ($isLoggedIn) {
    log_attempt($pdo, $user['user_id'], 'view', 'User ' . $user['user_id'] . ' viewed about', 'about', null);
}

$activeHeader = 'about';
?>

<!DOCTYPE html>
<html lang="en"
    data-theme="<?php echo isset($_SESSION['user']) ? ($_SESSION['user']['theme_preference'] ?? 'light') : 'light'; ?>">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>About BitBalance</title>

    <?php include PROJECT_ROOT . 'views/head_css.php'; ?>
    <script src="https://kit.fontawesome.com/b94f65ead2.js" crossorigin="anonymous"></script>

    <style>
        /* 1. PAGE-SPECIFIC VARIABLES (dùng tokens chung, chỉ thêm biến riêng) */
        :root {
            --accent-green: #38ef7d;
            --accent-orange: #ff9966;
            --border-light: 1px solid rgba(0, 0, 0, 0.05);
        }

        [data-theme="dark"] {
            --border-light: 1px solid rgba(255, 255, 255, 0.1);
        }

        /* 2. HERO SECTION */
        .about-hero {
            text-align: center;
            padding: 100px 20px 80px;
            border-radius: 32px;
            /* Tăng khoảng cách cho thoáng */
            max-width: 1150px;
            margin: 0 auto;
            background: transparent;
            /* Đảm bảo không bị dính nền trắng */
            margin-top: 20px;
        }

        .hero-title {
            font-size: 3.5rem;
            font-weight: 800;
            margin-bottom: 20px;
            letter-spacing: -1px;
            line-height: 1.2;
        }

        .gradient-text {
            background: var(--primary-gradient);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }

        .hero-subtitle {
            font-size: 1.25rem;
            color: var(--text-secondary);
            max-width: 600px;
            margin: 0 auto;
        }

        /* 3. BENTO GRID LAYOUT */
        .bento-container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 20px 80px;
            display: grid;
            grid-template-columns: repeat(12, 1fr);
            gap: 24px;
            grid-auto-rows: minmax(min-content, auto);
        }

        /* Common Card Style */
        .bento-card {
            background: var(--card-bg);
            border-radius: var(--border-radius);
            padding: 40px;
            box-shadow: var(--shadow-soft);
            border: var(--border-light);
            transition: transform 0.3s ease;
        }

        .bento-card:hover {
            transform: translateY(-5px);
        }

        .card-icon {
            width: 56px;
            height: 56px;
            border-radius: 16px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            margin-bottom: 24px;
        }

        .bento-card h3 {
            font-size: 1.5rem;
            font-weight: 700;
            margin-bottom: 12px;
            color: var(--text-primary);
        }

        .bento-card p {
            color: var(--text-secondary);
            font-size: 1rem;
        }

        /* --- Individual Grid Areas --- */

        /* A. The Big Idea (Nâng cấp giao diện) */
        .card-big-idea {
            grid-column: span 12;
            /* Gradient đậm hơn một chút để tách biệt với nền trang */
            background: linear-gradient(135deg, #ffffff 0%, #f0f7ff 100%);
            border: 1px solid rgba(74, 126, 227, 0.15);
            /* Viền xanh nhẹ */
            display: flex;
            flex-direction: column;
            align-items: center;
            text-align: center;
            position: relative;
            overflow: hidden;
            margin-top: 20px;
            margin-bottom: 20px;
        }

        .card-big-idea::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 5px;
            background: var(--primary-gradient);
            /* Viền màu trên cùng */
        }

        [data-theme="dark"] .card-big-idea {
            background: linear-gradient(135deg, #1f2937, #111827);
        }

        .card-big-idea .card-icon {
            background: #4a7ee3;
            color: white;
        }

        /* B. Mission & Purpose (Half Width) */
        .card-mission {
            grid-column: span 6;
        }

        .card-mission .card-icon {
            background: #e3f2fd;
            color: #4a7ee3;
        }

        .card-purpose {
            grid-column: span 6;
        }

        .card-purpose .card-icon {
            background: #ffebee;
            color: #ff5e62;
        }

        /* C. User Types (Split) */
        .user-section-title {
            grid-column: span 12;
            text-align: center;
            margin-top: 40px;
            margin-bottom: 20px;
        }

        .card-user-regular {
            grid-column: span 6;
            border-top: 4px solid #38ef7d;
            /* Green accent */
        }

        .card-user-admin {
            grid-column: span 6;
            border-top: 4px solid #764ba2;
            /* Purple accent */
        }

        .needs-list {
            list-style: none;
            margin-top: 20px;
            padding: 0;
        }

        .needs-list li {
            padding: 8px 0;
            padding-left: 28px;
            position: relative;
            color: var(--text-secondary);
        }

        .needs-list li::before {
            content: '\f00c';
            font-family: "Font Awesome 5 Free";
            font-weight: 900;
            position: absolute;
            left: 0;
            color: var(--accent-green);
        }

        /* D. Key Features (3 Columns) */
        .features-wrapper {
            grid-column: span 12;
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 24px;
            margin-top: 20px;
        }

        .feature-item {
            background: var(--card-bg);
            border-radius: 20px;
            padding: 30px;
            text-align: center;
            border: var(--border-light);
            transition: all 0.2s;
        }

        .feature-item:hover {
            transform: scale(1.02);
        }

        .feature-item i {
            font-size: 2rem;
            margin-bottom: 15px;
            background: -webkit-linear-gradient(#4a7ee3, #764ba2);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }

        .feature-item h4 {
            font-size: 1.1rem;
            font-weight: 700;
            margin-bottom: 8px;
        }

        .feature-item p {
            font-size: 0.9rem;
            color: var(--text-secondary);
        }

        /* E. CTA */
        .cta-container {
            grid-column: span 12;
            text-align: center;
            padding: 60px 0;
        }

        .btn-cta {
            display: inline-flex;
            align-items: center;
            gap: 10px;
            background: var(--primary-gradient);
            color: white;
            padding: 16px 40px;
            border-radius: 50px;
            font-size: 1.1rem;
            font-weight: 600;
            text-decoration: none;
            box-shadow: 0 10px 25px rgba(74, 126, 227, 0.4);
            transition: all 0.3s ease;
        }

        .btn-cta:hover {
            transform: translateY(-3px);
            box-shadow: 0 15px 35px rgba(74, 126, 227, 0.5);
        }

        /* 4. RESPONSIVE */
        @media (max-width: 900px) {
            .hero-title {
                font-size: 2.5rem;
            }

            .card-mission,
            .card-purpose,
            .card-user-regular,
            .card-user-admin {
                grid-column: span 12;
                /* Full width on tablet */
            }

            .features-wrapper {
                grid-template-columns: repeat(2, 1fr);
            }
        }

        @media (max-width: 600px) {
            .bento-container {
                display: flex;
                flex-direction: column;
            }

            .features-wrapper {
                grid-template-columns: 1fr;
            }

            .hero-title {
                font-size: 2rem;
            }

            .bento-card {
                padding: 25px;
            }
        }
    </style>
</head>

<body>
    <?php include 'views/header.php'; ?>

    <header class="about-hero">
        <h1 class="hero-title">Empowering Your <br><span class="gradient-text">Wellness Journey</span></h1>
        <p class="hero-subtitle">Making calorie tracking straightforward, intuitive, and enjoyable for everyone.</p>
    </header>

    <main class="bento-container">

        <section class="bento-card card-big-idea">
            <div class="card-icon"><i class="fas fa-lightbulb"></i></div>
            <h3>The Big Idea</h3>
            <p style="max-width: 700px;">
                Tracking what we eat shouldn't be confusing or overwhelming. BitBalance is designed to simplify meal
                management with a beautiful, user-friendly interface that turns nutrition tracking into a rewarding
                daily habit.
            </p>
        </section>

        <section class="bento-card card-mission">
            <div class="card-icon"><i class="fas fa-bullseye"></i></div>
            <h3>Our Mission</h3>
            <p>To help people stay on top of their nutrition goals through an easy-to-use, informative, and supportive
                platform.</p>
        </section>

        <section class="bento-card card-purpose">
            <div class="card-icon"><i class="fas fa-heart"></i></div>
            <h3>Product Purpose</h3>
            <p>Developing mindful eating habits and achieving wellness goals through personalized tools and community
                support.</p>
        </section>

        <div class="user-section-title">
            <h2>Everything you need</h2>
        </div>

        <div class="features-wrapper">
            <div class="feature-item">
                <i class="fas fa-utensils"></i>
                <h4>Meal Tracking</h4>
                <p>Intuitive logging with calorie calculation.</p>
            </div>
            <div class="feature-item">
                <i class="fas fa-chart-pie"></i>
                <h4>Visual Analytics</h4>
                <p>Track progress with beautiful charts.</p>
            </div>
            <div class="feature-item">
                <i class="fas fa-fire"></i>
                <h4>Habit Streaks</h4>
                <p>Stay motivated with daily streaks.</p>
            </div>
            <div class="feature-item">
                <i class="fas fa-users"></i>
                <h4>Community</h4>
                <p>Share recipes and get support.</p>
            </div>
            <div class="feature-item">
                <i class="fas fa-calculator"></i>
                <h4>TDEE Calculator</h4>
                <p>Personalized daily calorie goals.</p>
            </div>
            <div class="feature-item">
                <i class="fas fa-shield-alt"></i>
                <h4>Privacy First</h4>
                <p>Secure data and user control.</p>
            </div>
        </div>

        <div class="user-section-title">
            <h2>Who is BitBalance for?</h2>
        </div>

        <section class="bento-card card-user-regular">
            <div class="card-icon" style="background: #e8f5e9; color: #38ef7d;"><i class="fas fa-user"></i></div>
            <h3>Regular Users</h3>
            <p>Students, professionals, or anyone looking to improve their diet.</p>
            <ul class="needs-list">
                <li>Simple meal & calorie tracking</li>
                <li>Customizable profile settings</li>
                <li>Community connection</li>
                <li>Full data control</li>
            </ul>
        </section>

        <section class="bento-card card-user-admin">
            <div class="card-icon" style="background: #f3e5f5; color: #764ba2;"><i class="fas fa-user-shield"></i></div>
            <h3>Administrators</h3>
            <p>Managers ensuring a smooth and safe user experience.</p>
            <ul class="needs-list">
                <li>User account management</li>
                <li>Forum moderation tools</li>
                <li>System activity logs</li>
                <li>Security maintenance</li>
            </ul>
        </section>

        <div class="cta-container">
            <?php if (isset($_SESSION['user'])): ?>
                <a href="<?= BASE_URL ?>dashboard/dashboard.php" class="btn-cta">
                    <i class="fas fa-tachometer-alt"></i> Go to Dashboard
                </a>
            <?php else: ?>
                <a href="<?= BASE_URL ?>signup.php" class="btn-cta">
                    <i class="fas fa-rocket"></i> Start Your Journey
                </a>
            <?php endif; ?>
        </div>

    </main>

    <?php include 'views/footer.php'; ?>
</body>

</html>