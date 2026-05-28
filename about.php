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

    <?php
    $pageCss = ['css/pages/about.css'];
    include PROJECT_ROOT . 'views/head_css.php';
    ?>
    <script src="https://kit.fontawesome.com/b94f65ead2.js" crossorigin="anonymous"></script>
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
            <div class="card-icon"><i class="fas fa-user"></i></div>
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
            <div class="card-icon"><i class="fas fa-user-shield"></i></div>
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