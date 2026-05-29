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
<html lang="en" data-theme="<?php echo isset($_SESSION['user']) ? ($_SESSION['user']['theme_preference'] ?? 'system') : 'system'; ?>">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>BitBalance — Calorie Tracking, Gamified</title>

    <!-- Progressive enhancement: mark JS available BEFORE CSS paints so reveal
         elements only start hidden when JS can actually un-hide them. -->
    <script>document.documentElement.classList.add('js');</script>

    <?php
    $pageCss = ['css/pages/index.css'];
    include PROJECT_ROOT . 'views/head_css.php';
    ?>

    <script src="https://kit.fontawesome.com/b94f65ead2.js" crossorigin="anonymous"></script>
</head>

<body>
    <?php include PROJECT_ROOT . 'views/header.php'; ?>

    <!-- ============================ HERO ============================ -->
    <section class="hero-section">
        <span class="hero-badge"><i class="fas fa-bolt"></i> Track. Earn XP. Level up.</span>
        <h1 class="hero-title">Calorie Tracking That<br><span class="gradient-text">Feels Like a Game</span></h1>
        <p class="hero-subtitle">
            Snap a photo of your meal, earn XP for every log, keep your streak alive, and climb the leaderboard with
            friends. Nutrition tracking you'll actually stick with.
        </p>
        <div class="hero-actions">
            <a href="signup.php" class="btn-start"><i class="fas fa-rocket"></i> Get Started Free</a>
            <a href="login.php" class="btn-secondary">I already have an account</a>
        </div>
        <div class="hero-proof">
            <span class="proof-chip"><i class="fas fa-camera"></i> AI photo logging</span>
            <span class="proof-chip"><i class="fas fa-fire"></i> Daily streaks</span>
            <span class="proof-chip"><i class="fas fa-user-friends"></i> Friend leaderboards</span>
        </div>
    </section>

    <main class="landing-container">

        <!-- ===================== FEATURE SHOWCASE ===================== -->
        <div class="section-title reveal">
            <h2>Why you'll keep <span class="gradient-text">coming back</span></h2>
            <p class="section-sub">The hooks that turn tracking from a chore into a daily habit.</p>
        </div>

        <div class="showcase-grid">
            <!-- AI Logging -->
            <article class="showcase-card showcase-ai reveal">
                <div class="showcase-demo">
                    <div class="demo-photo"><i class="fas fa-camera"></i></div>
                    <div class="demo-arrow"><i class="fas fa-arrow-right"></i></div>
                    <div class="demo-result">
                        <span class="demo-food">🍜 Phở Bò</span>
                        <span class="demo-cal">≈ 450 kcal</span>
                    </div>
                </div>
                <span class="showcase-tag showcase-tag--ai">AI-Powered</span>
                <h3>Snap a photo. We do the math.</h3>
                <p>Our Gemini-powered analyzer estimates calories and macros from a single photo. Or scan a barcode for
                    instant results.</p>
            </article>

            <!-- Gamification -->
            <article class="showcase-card showcase-game reveal">
                <div class="showcase-demo">
                    <div class="demo-level">Lv 7</div>
                    <div class="demo-xp-row">
                        <span class="demo-xp-bar"><span class="demo-xp-fill"></span></span>
                        <span class="demo-xp-num">+10 XP</span>
                    </div>
                    <div class="demo-streak"><i class="fas fa-fire"></i> 14-day streak</div>
                </div>
                <span class="showcase-tag showcase-tag--game">Gamified</span>
                <h3>Level up every single day.</h3>
                <p>Earn XP for logging meals, hitting your goals, and keeping streaks alive. Watch your level climb and
                    unlock that daily dopamine hit.</p>
            </article>

            <!-- Social -->
            <article class="showcase-card showcase-social reveal">
                <div class="showcase-demo demo-social">
                    <div class="demo-rank"><span class="demo-medal">🥇</span><span class="demo-ava">L</span><span
                            class="demo-rank-xp">2,340</span></div>
                    <div class="demo-rank"><span class="demo-medal">🥈</span><span class="demo-ava">T</span><span
                            class="demo-rank-xp">1,980</span></div>
                    <div class="demo-rank"><span class="demo-medal">🥉</span><span class="demo-ava">M</span><span
                            class="demo-rank-xp">1,210</span></div>
                </div>
                <span class="showcase-tag showcase-tag--social">Social</span>
                <h3>Bring your crew along.</h3>
                <p>Add friends, compare weekly XP on a private leaderboard, and keep each other accountable through every
                    streak.</p>
            </article>
        </div>

        <!-- ===================== SECONDARY FEATURES ===================== -->
        <div class="section-title reveal">
            <h2>Everything else in the box</h2>
        </div>

        <div class="mini-grid">
            <div class="mini-card reveal">
                <div class="mini-icon mini-icon--green"><i class="fas fa-chart-line"></i></div>
                <h4>Visual Analytics</h4>
                <p>7-day calorie & macro trends in clean, interactive charts.</p>
            </div>
            <div class="mini-card reveal">
                <div class="mini-icon mini-icon--blue"><i class="fas fa-route"></i></div>
                <h4>Adaptive Goal Planner</h4>
                <p>TDEE-based targets that adjust to your weight trend.</p>
            </div>
            <div class="mini-card reveal">
                <div class="mini-icon mini-icon--orange"><i class="fas fa-comments"></i></div>
                <h4>AI Coach</h4>
                <p>Chat for tailored nutrition advice anytime.</p>
            </div>
            <div class="mini-card reveal">
                <div class="mini-icon mini-icon--green"><i class="fas fa-weight-scale"></i></div>
                <h4>Weight Tracking</h4>
                <p>Log your weight and watch progress over time.</p>
            </div>
            <div class="mini-card reveal">
                <div class="mini-icon mini-icon--blue"><i class="fas fa-moon"></i></div>
                <h4>Light & Dark Mode</h4>
                <p>A polished interface that's easy on the eyes, day or night.</p>
            </div>
            <div class="mini-card reveal">
                <div class="mini-icon mini-icon--orange"><i class="fas fa-shield-alt"></i></div>
                <h4>Privacy First</h4>
                <p>Secure data and granular control over what friends can see.</p>
            </div>
        </div>

        <!-- ============================ FINAL CTA ============================ -->
        <section class="final-cta reveal">
            <h2>Ready to make tracking a habit?</h2>
            <p>Join BitBalance and turn your first meal log into your first level-up.</p>
            <a href="signup.php" class="btn-start btn-start--lg"><i class="fas fa-rocket"></i> Start Your Journey</a>
        </section>

    </main>

    <?php include 'views/footer.php'; ?>

    <script>
        // Scroll-reveal: fade/slide elements in as they enter the viewport.
        // Cascade within a grid row is handled in CSS via nth-child delays.
        (function () {
            const reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
            const items = document.querySelectorAll('.reveal');

            // No observer support or user prefers reduced motion → show everything.
            if (reduceMotion || !('IntersectionObserver' in window)) {
                items.forEach(el => el.classList.add('is-visible'));
                return;
            }

            const observer = new IntersectionObserver((entries, obs) => {
                entries.forEach(entry => {
                    if (entry.isIntersecting) {
                        entry.target.classList.add('is-visible');
                        obs.unobserve(entry.target); // reveal once, then stop watching
                    }
                });
            }, {
                threshold: 0.15,
                rootMargin: '0px 0px -40px 0px'
            });

            items.forEach(el => observer.observe(el));
        })();
    </script>
</body>

</html>
