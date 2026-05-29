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
    data-theme="<?php echo isset($_SESSION['user']) ? ($_SESSION['user']['theme_preference'] ?? 'system') : 'system'; ?>">

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
        <h1 class="hero-title">Your Wellness Journey,<br><span class="gradient-text">Gamified</span></h1>
        <p class="hero-subtitle">A calorie tracker that feels like a game — snap a photo, earn XP, keep your streak
            alive, and climb the leaderboard with friends.</p>
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
            <h2>Your dashboard, <span class="gradient-text">supercharged</span></h2>
            <p class="section-sub">The three things that keep you coming back — all one tap away.</p>
        </div>

        <div class="spotlight-grid">
            <!-- AI Logging -->
            <section class="spotlight-card spotlight-ai">
                <div class="spotlight-demo">
                    <div class="demo-photo"><i class="fas fa-camera"></i></div>
                    <div class="demo-arrow"><i class="fas fa-arrow-right"></i></div>
                    <div class="demo-result">
                        <span class="demo-food">🍜 Phở Bò</span>
                        <span class="demo-cal">≈ 450 kcal</span>
                    </div>
                </div>
                <span class="spotlight-tag spotlight-tag--ai">AI-Powered</span>
                <h3>Snap a photo. We do the math.</h3>
                <p>Point your camera at any meal and our Gemini-powered analyzer estimates calories and macros in
                    seconds. Or scan a barcode for instant results.</p>
            </section>

            <!-- Gamification -->
            <section class="spotlight-card spotlight-game">
                <div class="spotlight-demo">
                    <div class="demo-level">Lv 7</div>
                    <div class="demo-xp-row">
                        <span class="demo-xp-bar"><span class="demo-xp-fill"></span></span>
                        <span class="demo-xp-num">+10 XP</span>
                    </div>
                    <div class="demo-streak"><i class="fas fa-fire"></i> 14-day streak</div>
                </div>
                <span class="spotlight-tag spotlight-tag--game">Gamified</span>
                <h3>Level up every single day.</h3>
                <p>Earn XP for logging meals, hitting your goals, and keeping streaks alive. Watch your level climb and
                    unlock that daily dopamine hit.</p>
            </section>

            <!-- Social -->
            <section class="spotlight-card spotlight-social">
                <div class="spotlight-demo demo-social">
                    <div class="demo-rank"><span class="demo-medal">🥇</span><span class="demo-ava">L</span><span
                            class="demo-rank-xp">2,340</span></div>
                    <div class="demo-rank"><span class="demo-medal">🥈</span><span class="demo-ava">T</span><span
                            class="demo-rank-xp">1,980</span></div>
                    <div class="demo-rank"><span class="demo-medal">🥉</span><span class="demo-ava">M</span><span
                            class="demo-rank-xp">1,210</span></div>
                </div>
                <span class="spotlight-tag spotlight-tag--social">Social</span>
                <h3>Bring your crew along.</h3>
                <p>Add friends, compare weekly XP on a private leaderboard, and keep each other accountable through
                    every streak.</p>
            </section>
        </div>

        <div class="user-section-title">
            <h2>Everything else you need</h2>
        </div>

        <div class="features-wrapper">
            <div class="feature-item">
                <i class="fas fa-chart-line"></i>
                <h4>Visual Analytics</h4>
                <p>7-day calorie & macro trends in clean, interactive charts.</p>
            </div>
            <div class="feature-item">
                <i class="fas fa-route"></i>
                <h4>Adaptive Goal Planner</h4>
                <p>TDEE-based targets that adjust to your weight trend.</p>
            </div>
            <div class="feature-item">
                <i class="fas fa-comments"></i>
                <h4>AI Coach</h4>
                <p>Chat for tailored nutrition advice anytime.</p>
            </div>
            <div class="feature-item">
                <i class="fas fa-weight-scale"></i>
                <h4>Weight Tracking</h4>
                <p>Log your weight and watch progress over time.</p>
            </div>
            <div class="feature-item">
                <i class="fas fa-moon"></i>
                <h4>Light & Dark Mode</h4>
                <p>A polished interface that's easy on the eyes, day or night.</p>
            </div>
            <div class="feature-item">
                <i class="fas fa-shield-alt"></i>
                <h4>Privacy First</h4>
                <p>Secure data, granular control over what friends can see.</p>
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