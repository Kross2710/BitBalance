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
<html lang="<?= html_lang_attr() ?>" data-theme="<?php echo isset($_SESSION['user']) ? ($_SESSION['user']['theme_preference'] ?? 'system') : 'system'; ?>">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= t('index.title_tag') ?></title>

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
        <span class="hero-badge"><i class="fas fa-bolt"></i> <?= t('index.hero.badge') ?></span>
        <h1 class="hero-title"><?= t_raw('index.hero.title_html') ?></h1>
        <p class="hero-subtitle">
            <?= t('index.hero.subtitle') ?>
        </p>
        <div class="hero-actions">
            <a href="signup.php" class="btn-start"><i class="fas fa-rocket"></i> <?= t('index.hero.cta_primary') ?></a>
            <a href="login.php" class="btn-secondary"><?= t('index.hero.cta_secondary') ?></a>
        </div>
        <div class="hero-proof">
            <span class="proof-chip"><i class="fas fa-camera"></i> <?= t('index.hero.proof.ai') ?></span>
            <span class="proof-chip"><i class="fas fa-fire"></i> <?= t('index.hero.proof.streaks') ?></span>
            <span class="proof-chip"><i class="fas fa-user-friends"></i> <?= t('index.hero.proof.leaderboards') ?></span>
        </div>
    </section>

    <main class="landing-container">

        <!-- ===================== FEATURE SHOWCASE ===================== -->
        <div class="section-title reveal">
            <h2><?= t_raw('index.showcase.title_html') ?></h2>
            <p class="section-sub"><?= t('index.showcase.subtitle') ?></p>
        </div>

        <div class="showcase-grid">
            <!-- AI Logging -->
            <article class="showcase-card showcase-ai reveal">
                <div class="showcase-demo">
                    <div class="demo-photo"><i class="fas fa-camera"></i></div>
                    <div class="demo-arrow"><i class="fas fa-arrow-right"></i></div>
                    <div class="demo-result">
                        <span class="demo-food"><?= t('index.showcase.ai.demo_food') ?></span>
                        <span class="demo-cal"><?= t('index.showcase.ai.demo_cal') ?></span>
                    </div>
                </div>
                <span class="showcase-tag showcase-tag--ai"><?= t('index.showcase.ai.tag') ?></span>
                <h3><?= t('index.showcase.ai.title') ?></h3>
                <p><?= t('index.showcase.ai.body') ?></p>
            </article>

            <!-- Gamification -->
            <article class="showcase-card showcase-game reveal">
                <div class="showcase-demo">
                    <div class="demo-level"><?= t('index.showcase.game.level') ?></div>
                    <div class="demo-xp-row">
                        <span class="demo-xp-bar"><span class="demo-xp-fill"></span></span>
                        <span class="demo-xp-num"><?= t('index.showcase.game.xp') ?></span>
                    </div>
                    <div class="demo-streak"><i class="fas fa-fire"></i> <?= t('index.showcase.game.streak') ?></div>
                </div>
                <span class="showcase-tag showcase-tag--game"><?= t('index.showcase.game.tag') ?></span>
                <h3><?= t('index.showcase.game.title') ?></h3>
                <p><?= t('index.showcase.game.body') ?></p>
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
                <span class="showcase-tag showcase-tag--social"><?= t('index.showcase.social.tag') ?></span>
                <h3><?= t('index.showcase.social.title') ?></h3>
                <p><?= t('index.showcase.social.body') ?></p>
            </article>
        </div>

        <!-- ===================== SECONDARY FEATURES ===================== -->
        <div class="section-title reveal">
            <h2><?= t('index.mini.heading') ?></h2>
        </div>

        <div class="mini-grid">
            <div class="mini-card reveal">
                <div class="mini-icon mini-icon--green"><i class="fas fa-chart-line"></i></div>
                <h4><?= t('index.mini.analytics.title') ?></h4>
                <p><?= t('index.mini.analytics.body') ?></p>
            </div>
            <div class="mini-card reveal">
                <div class="mini-icon mini-icon--blue"><i class="fas fa-route"></i></div>
                <h4><?= t('index.mini.planner.title') ?></h4>
                <p><?= t('index.mini.planner.body') ?></p>
            </div>
            <div class="mini-card reveal">
                <div class="mini-icon mini-icon--orange"><i class="fas fa-comments"></i></div>
                <h4><?= t('index.mini.coach.title') ?></h4>
                <p><?= t('index.mini.coach.body') ?></p>
            </div>
            <div class="mini-card reveal">
                <div class="mini-icon mini-icon--green"><i class="fas fa-weight-scale"></i></div>
                <h4><?= t('index.mini.weight.title') ?></h4>
                <p><?= t('index.mini.weight.body') ?></p>
            </div>
            <div class="mini-card reveal">
                <div class="mini-icon mini-icon--blue"><i class="fas fa-moon"></i></div>
                <h4><?= t('index.mini.theme.title') ?></h4>
                <p><?= t('index.mini.theme.body') ?></p>
            </div>
            <div class="mini-card reveal">
                <div class="mini-icon mini-icon--orange"><i class="fas fa-shield-alt"></i></div>
                <h4><?= t('index.mini.privacy.title') ?></h4>
                <p><?= t('index.mini.privacy.body') ?></p>
            </div>
        </div>

        <!-- ============================ FINAL CTA ============================ -->
        <section class="final-cta reveal">
            <h2><?= t('index.final.heading') ?></h2>
            <p><?= t('index.final.body') ?></p>
            <a href="signup.php" class="btn-start btn-start--lg"><i class="fas fa-rocket"></i> <?= t('index.final.button') ?></a>
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
