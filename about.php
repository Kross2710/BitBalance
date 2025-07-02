<?php
require_once __DIR__ . '/include/init.php';
require_once __DIR__ . '/include/handlers/log_attempt.php';
require_once __DIR__ . '/include/db_config.php';

if ($isLoggedIn) {
    // Log the user activity
    log_attempt($pdo, $user['user_id'], 'view', 'User ' . $user['user_id'] . ' viewed about', 'about', null);
}

// Get current theme from session if user is logged in
$current_theme = 'light';
if (isset($_SESSION['user']['theme_preference'])) {
    $current_theme = $_SESSION['user']['theme_preference'];
}
?>

<!DOCTYPE html>
<html lang="en" data-theme="<?= htmlspecialchars($current_theme) ?>">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>About - BitBalance</title>
    <link rel="stylesheet" href="css/style.css">
    <link rel="stylesheet" href="css/themes/global.css">
    <link rel="stylesheet" href="css/themes/header.css">
    <script src="https://kit.fontawesome.com/b94f65ead2.js" crossorigin="anonymous"></script>
    <style>
        /* Light Theme (Default) */
        :root {
            --bg-color: #f8f9fa;
            --card-bg: #ffffff;
            --text-color: #212529;
            --text-muted: #6c757d;
            --border-color: #e9ecef;
            --primary-color: #4a7ee3;
            --shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
        }

        /* Dark Theme */
        [data-theme="dark"] {
            --bg-color: #1a1a1a;
            --card-bg: #2d2d2d;
            --text-color: #ffffff;
            --text-muted: #adb5bd;
            --border-color: #495057;
            --primary-color: #0d6efd;
            --shadow: 0 4px 20px rgba(0, 0, 0, 0.3);
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: var(--bg-color);
            color: var(--text-color);
            line-height: 1.6;
            transition: background-color 0.3s ease, color 0.3s ease;
        }

        /* Header */
        .page-header {
            background: var(--primary-color) !important;
            color: white !important;
            padding: 60px 0;
            text-align: center;
            margin-top: 70px;
            /* Account for fixed header */
        }

        .header-content {
            max-width: 800px;
            margin: 0 auto;
            padding: 0 20px;
        }

        .page-header .header-content .logo {
            font-size: 56px !important;
            font-weight: 700 !important;
            margin-bottom: 15px;
            color: white !important;
            text-shadow: 2px 2px 4px rgba(0, 0, 0, 0.3);
        }

        .page-header .tagline {
            font-size: 22px !important;
            margin-bottom: 8px;
            color: white !important;
            opacity: 0.95;
        }

        .page-header .subtitle {
            font-size: 16px !important;
            color: white !important;
            opacity: 0.9;
        }

        /* Main Content */
        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 60px 20px;
        }

        .section {
            margin-bottom: 80px;
        }

        .section-title {
            font-size: 32px;
            font-weight: 600;
            text-align: center;
            margin-bottom: 50px;
            color: var(--text-color);
            position: relative;
        }

        .section-title::after {
            content: '';
            position: absolute;
            bottom: -10px;
            left: 50%;
            transform: translateX(-50%);
            width: 60px;
            height: 3px;
            background: var(--primary-color);
            border-radius: 2px;
        }

        /* Big Idea Section */
        .big-idea {
            background: var(--card-bg);
            border-radius: 20px;
            padding: 50px;
            box-shadow: var(--shadow);
            text-align: center;
            border: 1px solid var(--border-color);
        }

        .big-idea-icon {
            width: 60px;
            height: 60px;
            background: var(--primary-color);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 25px;
            font-size: 24px;
            color: white;
        }

        .big-idea h2 {
            font-size: 28px;
            margin-bottom: 20px;
            color: var(--primary-color);
        }

        .big-idea p {
            font-size: 18px;
            color: var(--text-muted);
            max-width: 600px;
            margin: 0 auto;
        }

        /* Challenge & Purpose Grid */
        .info-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 40px;
            margin-bottom: 60px;
        }

        .info-card {
            background: var(--card-bg);
            border-radius: 16px;
            padding: 40px;
            box-shadow: var(--shadow);
            border: 1px solid var(--border-color);
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }

        .info-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 30px rgba(0, 0, 0, 0.15);
        }

        .info-card-icon {
            width: 50px;
            height: 50px;
            background: var(--primary-color);
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 20px;
            font-size: 20px;
            color: white;
        }

        .info-card h3 {
            font-size: 22px;
            margin-bottom: 15px;
            color: var(--text-color);
        }

        .info-card p {
            color: var(--text-muted);
            line-height: 1.7;
        }

        /* User Types */
        .user-types {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 40px;
        }

        .user-type {
            background: var(--card-bg);
            border-radius: 16px;
            padding: 40px;
            box-shadow: var(--shadow);
            border: 1px solid var(--border-color);
            position: relative;
            overflow: hidden;
        }

        .user-type::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: var(--primary-color);
        }

        .user-header {
            display: flex;
            align-items: center;
            gap: 15px;
            margin-bottom: 20px;
        }

        .user-icon {
            width: 40px;
            height: 40px;
            background: var(--primary-color);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 16px;
            color: white;
        }

        .user-type h3 {
            font-size: 24px;
            color: var(--text-color);
        }

        .user-description {
            color: var(--text-muted);
            margin-bottom: 25px;
            font-style: italic;
        }

        .needs-list {
            list-style: none;
        }

        .needs-list li {
            padding: 10px 0;
            padding-left: 30px;
            position: relative;
            color: var(--text-color);
        }

        .needs-list li::before {
            content: '✓';
            position: absolute;
            left: 0;
            color: var(--primary-color);
            font-weight: bold;
            font-size: 16px;
        }

        /* Value Proposition */
        .value-prop {
            background: var(--primary-color);
            color: white;
            border-radius: 16px;
            padding: 50px;
            text-align: center;
        }

        .value-prop h2 {
            font-size: 28px;
            margin-bottom: 20px;
        }

        .value-prop p {
            font-size: 18px;
            opacity: 0.95;
            max-width: 700px;
            margin: 0 auto;
        }

        /* Features Grid */
        .features-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 30px;
            margin-top: 60px;
        }

        .feature-card {
            background: var(--card-bg);
            border-radius: 12px;
            padding: 30px;
            text-align: center;
            box-shadow: var(--shadow);
            border: 1px solid var(--border-color);
            transition: transform 0.3s ease;
        }

        .feature-card:hover {
            transform: translateY(-3px);
        }

        .feature-icon {
            width: 50px;
            height: 50px;
            background: var(--primary-color);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 20px;
            font-size: 20px;
            color: white;
        }

        .feature-card h4 {
            font-size: 18px;
            margin-bottom: 10px;
            color: var(--text-color);
        }

        .feature-card p {
            color: var(--text-muted);
            font-size: 14px;
        }

        /* Call to Action */
        .cta {
            text-align: center;
            margin-top: 80px;
        }

        .cta-button {
            display: inline-block;
            background: var(--primary-color);
            color: white;
            padding: 15px 30px;
            border-radius: 8px;
            text-decoration: none;
            font-weight: 500;
            font-size: 16px;
            transition: background 0.3s ease;
        }

        .cta-button:hover {
            background: #3b6bd6;
        }

        /* Theme Info */
        .theme-info {
            text-align: center;
            margin-top: 40px;
            padding: 20px;
            background: var(--card-bg);
            border-radius: 12px;
            border: 1px solid var(--border-color);
        }

        .theme-info p {
            color: var(--text-muted);
            margin: 0;
        }

        /* Responsive Design */
        @media (max-width: 768px) {
            .page-header {
                padding: 40px 0;
                margin-top: 60px;
            }

            .logo {
                font-size: 40px !important;
            }

            .tagline {
                font-size: 18px;
            }

            .container {
                padding: 40px 20px;
            }

            .section-title {
                font-size: 28px;
            }

            .info-grid,
            .user-types {
                grid-template-columns: 1fr;
                gap: 30px;
            }

            .big-idea,
            .info-card,
            .user-type,
            .value-prop {
                padding: 30px;
            }

            .features-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>

<body>
    <?php
    $activeHeader = 'about';
    include 'views/header.php';
    ?>

    <!-- Page Header -->
    <header class="page-header">
        <div class="header-content">
            <h1 class="logo">BitBalance</h1>
            <p class="tagline">Empowering Your Wellness Journey</p>
            <p class="subtitle">Making calorie tracking straightforward, intuitive, and enjoyable</p>
        </div>
    </header>

    <!-- Main Content -->
    <main class="container">
        <!-- Big Idea Section -->
        <section class="section">
            <div class="big-idea">
                <div class="big-idea-icon">
                    <i class="fas fa-lightbulb"></i>
                </div>
                <h2>The Big Idea</h2>
                <p>
                    We're living in a time where more people are becoming conscious of their health and diet.
                    However, those who want to improve their eating habits often feel discouraged by the lack of
                    accessible and easy-to-use resources. Tracking what we eat can still be confusing, overwhelming,
                    and time-consuming. BitBalance's website is designed to make calorie tracking and meal management
                    more straightforward, intuitive, and enjoyable.
                </p>
            </div>
        </section>

        <!-- Challenge & Purpose -->
        <section class="section">
            <h2 class="section-title">Our Mission</h2>
            <div class="info-grid">
                <div class="info-card">
                    <div class="info-card-icon">
                        <i class="fas fa-target"></i>
                    </div>
                    <h3>Essential Question</h3>
                    <p>
                        How can we help people stay on top of their nutrition goals in an easy-to-use,
                        informative, and supportive way?
                    </p>
                </div>
                <div class="info-card">
                    <div class="info-card-icon">
                        <i class="fas fa-puzzle-piece"></i>
                    </div>
                    <h3>Challenge Statement</h3>
                    <p>
                        Create a user-friendly application that helps people track their meals/calories,
                        monitor their progress, and stay motivated while meeting all technical and
                        functional project requirements.
                    </p>
                </div>
            </div>
        </section>

        <!-- Product Purpose -->
        <section class="section">
            <h2 class="section-title">Product Purpose</h2>
            <div class="info-card" style="max-width: 800px; margin: 0 auto;">
                <div class="info-card-icon">
                    <i class="fas fa-heart"></i>
                </div>
                <h3>Supporting Your Wellness Goals</h3>
                <p>
                    The purpose is to support users in developing mindful eating habits and achieving their wellness
                    goals through a personalised, interactive and secure system. This is done through the tools we
                    give users, such as logging their meals, setting daily calorie goals, and tracking their progress
                    over time. The discussion forum allows users to support, share recipes, and motivate each other.
                </p>
            </div>
        </section>

        <!-- User Types -->
        <section class="section">
            <h2 class="section-title">Who We Serve</h2>
            <div class="user-types">
                <div class="user-type">
                    <div class="user-header">
                        <div class="user-icon">
                            <i class="fas fa-users"></i>
                        </div>
                        <h3>Regular Users</h3>
                    </div>
                    <p class="user-description">
                        Everyday people - students, busy professionals, or anyone looking to improve their diet.
                    </p>
                    <h4 style="color: var(--text-color); margin-bottom: 15px;">What they need:</h4>
                    <ul class="needs-list">
                        <li>A simple way to track their meals and calories</li>
                        <li>Customisable profile settings</li>
                        <li>A space to connect with other users and share advice</li>
                        <li>Control over their data</li>
                    </ul>
                </div>
                <div class="user-type">
                    <div class="user-header">
                        <div class="user-icon">
                            <i class="fas fa-user-shield"></i>
                        </div>
                        <h3>Admin Users</h3>
                    </div>
                    <p class="user-description">
                        The people managing the site and ensuring a smooth user experience.
                    </p>
                    <h4 style="color: var(--text-color); margin-bottom: 15px;">What they need:</h4>
                    <ul class="needs-list">
                        <li>Tools to manage user accounts</li>
                        <li>The ability to moderate posts in the forum</li>
                        <li>Access to logs that track user activity</li>
                        <li>Features to keep the site running smoothly and securely</li>
                    </ul>
                </div>
            </div>
        </section>

        <!-- Value Proposition -->
        <section class="section">
            <div class="value-prop">
                <h2>More Than Just a Calorie Tracker</h2>
                <p>
                    BitBalance is designed to support users meaningfully through features like a discussion forum,
                    customizable user themes, administrative tools, and real-time feedback. It's built to be reliable,
                    secure, and user-friendly. Developed on the RMIT core teaching servers, it meets all course
                    requirements while delivering a clean and professional user experience.
                </p>
            </div>
        </section>

        <!-- Key Features -->
        <section class="section">
            <h2 class="section-title">Key Features</h2>
            <div class="features-grid">
                <div class="feature-card">
                    <div class="feature-icon">
                        <i class="fas fa-utensils"></i>
                    </div>
                    <h4>Meal Tracking</h4>
                    <p>Simple and intuitive meal logging with calorie calculation</p>
                </div>
                <div class="feature-card">
                    <div class="feature-icon">
                        <i class="fas fa-bullseye"></i>
                    </div>
                    <h4>Goal Setting</h4>
                    <p>Personalized daily calorie goals based on your profile</p>
                </div>
                <div class="feature-card">
                    <div class="feature-icon">
                        <i class="fas fa-chart-line"></i>
                    </div>
                    <h4>Progress Tracking</h4>
                    <p>Visual analytics to monitor your wellness journey</p>
                </div>
                <div class="feature-card">
                    <div class="feature-icon">
                        <i class="fas fa-comments"></i>
                    </div>
                    <h4>Community Forum</h4>
                    <p>Connect with others, share recipes, and find motivation</p>
                </div>
                <div class="feature-card">
                    <div class="feature-icon">
                        <i class="fas fa-palette"></i>
                    </div>
                    <h4>Custom Themes</h4>
                    <p>Personalize your experience with light and dark modes</p>
                </div>
                <div class="feature-card">
                    <div class="feature-icon">
                        <i class="fas fa-shield-alt"></i>
                    </div>
                    <h4>Secure & Reliable</h4>
                    <p>Built with security and privacy as top priorities</p>
                </div>
            </div>
        </section>

        <!-- Call to Action -->
        <div class="cta">
            <?php if (isset($_SESSION['user'])): ?>
                <a href="<?= BASE_URL ?>dashboard/" class="cta-button">
                    <i class="fas fa-tachometer-alt"></i> Go to Dashboard
                </a>
            <?php else: ?>
                <a href="<?= BASE_URL ?>signup.php" class="cta-button">
                    <i class="fas fa-rocket"></i> Start Your Journey Today
                </a>
            <?php endif; ?>
        </div>

        <!-- Theme Info -->
        <?php if (isset($_SESSION['user'])): ?>
            <div class="theme-info">
                <p><i class="fas fa-info-circle"></i> You can change your theme preference in your <a
                        href="<?= BASE_URL ?>profile.php" style="color: var(--primary-color);">Profile Settings</a></p>
            </div>
        <?php endif; ?>
    </main>
    <?php include 'views/footer.php'; ?>


    <script>
        // Smooth scrolling for anchor links
        document.querySelectorAll('a[href^="#"]').forEach(anchor => {
            anchor.addEventListener('click', function (e) {
                e.preventDefault();
                document.querySelector(this.getAttribute('href')).scrollIntoView({
                    behavior: 'smooth'
                });
            });
        });
    </script>
</body>

</html>