<?php
require_once __DIR__ . '/include/init.php';
require_once __DIR__ . '/include/handlers/log_attempt.php';

// if ($isLoggedIn) {
//     log_attempt($pdo, $user['user_id'], 'view', 'User ' . $user['user_id'] . ' viewed terms', 'terms', null);
// }

$activeHeader = 'about';
$isAdmin = $isLoggedIn && (($_SESSION['user']['role'] ?? '') === 'admin');
?>

<!DOCTYPE html>
<html lang="<?= html_lang_attr() ?>" data-theme="<?php echo isset($_SESSION['user']) ? ($_SESSION['user']['theme_preference'] ?? 'system') : 'system'; ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= t('terms.title') ?> | <?= t('common.app_name') ?></title>
    
    <?php
    $pageCss = ['css/pages/terms.css'];
    include PROJECT_ROOT . 'views/head_css.php';
    ?>

    <script src="https://kit.fontawesome.com/b94f65ead2.js" crossorigin="anonymous"></script>
</head>
<body>
    <?php include 'views/header.php'; ?>

    <div class="terms-wrapper">
        <header class="terms-header-card">
            <h1><?= t('terms.title') ?></h1>
            <p><?= t('terms.subtitle') ?></p>
        </header>

        <main class="terms-content-card">
            <div class="highlight-box">
                <p><i class="fas fa-info-circle"></i> <?= t_raw('terms.intro') ?></p>
            </div>

            <h2><i class="fas fa-shield-alt"></i> <?= t('terms.privacy.heading') ?></h2>
            <p><?= t('terms.privacy.p1') ?></p>
            <p><?= t('terms.privacy.p2') ?></p>

            <h2 id="cookies-policy"><i class="fas fa-cookie-bite"></i> <?= t('terms.cookies.heading') ?></h2>
            <p><?= t('terms.cookies.intro') ?></p>

            <h3><?= t('terms.cookies.types_heading') ?></h3>

            <div class="cookie-card">
                <h4><?= t('terms.cookies.essential.title') ?> <span class="cookie-tag tag-essential"><?= t('common.required') ?></span></h4>
                <p><?= t('terms.cookies.essential.desc') ?></p>
                <ul>
                    <li><?= t('terms.cookies.essential.item1') ?></li>
                    <li><?= t('terms.cookies.essential.item2') ?></li>
                    <li><?= t('terms.cookies.essential.item3') ?></li>
                </ul>
            </div>

            <div class="cookie-card">
                <h4><?= t('terms.cookies.preference.title') ?> <span class="cookie-tag tag-preference"><?= t('common.optional') ?></span></h4>
                <p><?= t('terms.cookies.preference.desc') ?></p>
                <ul>
                    <li><?= t('terms.cookies.preference.item1') ?></li>
                    <li><?= t('terms.cookies.preference.item2') ?></li>
                </ul>
            </div>

            <div class="cookie-card">
                <h4><?= t('terms.cookies.analytics.title') ?> <span class="cookie-tag tag-analytics"><?= t('common.optional') ?></span></h4>
                <p><?= t('terms.cookies.analytics.desc') ?></p>
                <ul>
                    <li><?= t('terms.cookies.analytics.item1') ?></li>
                    <li><?= t('terms.cookies.analytics.item2') ?></li>
                </ul>
            </div>

            <h2><i class="fas fa-users"></i> <?= t('terms.community.heading') ?></h2>
            <p><?= t('terms.community.intro') ?></p>
            <ul>
                <li><?= t('terms.community.item1') ?></li>
                <li><?= t('terms.community.item2') ?></li>
                <li><?= t('terms.community.item3') ?></li>
            </ul>

            <h2><i class="fas fa-university"></i> <?= t('terms.rmit.heading') ?></h2>
            <p><?= t('terms.rmit.body') ?></p>

            <?php if ($isAdmin): ?>
            <div class="dev-controls">
                <h4 class="dev-controls-title"><?= t('terms.dev.title') ?></h4>
                <button id="test-show-banner" class="btn-test"><i class="fas fa-eye"></i> <?= t('terms.dev.show_banner') ?></button>
                <button id="test-clear-cookies" class="btn-test"><i class="fas fa-trash"></i> <?= t('terms.dev.clear_cookies') ?></button>
                <p id="cookie-test-status" class="cookie-test-status"></p>
            </div>
            <?php endif; ?>

            <div class="last-updated">
                <?= t('terms.last_updated', ['date' => date('F j, Y')]) ?>
            </div>
        </main>
    </div>

    <?php include 'views/footer.php'; ?>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const statusEl = document.getElementById('cookie-test-status');

            // Hook up Developer Controls to the official cookie banner
            const showBannerBtn = document.getElementById('test-show-banner');
            const clearCookiesBtn = document.getElementById('test-clear-cookies');

            if (showBannerBtn) {
                showBannerBtn.addEventListener('click', () => {
                    const banner = document.getElementById('cookie-banner');
                    if (banner) {
                        banner.style.display = 'block';
                        banner.style.opacity = '0';
                        setTimeout(() => {
                            banner.style.transition = 'opacity 0.5s ease';
                            banner.style.opacity = '1';
                        }, 10);
                        statusEl.textContent = 'Official Cookie Banner displayed.';
                    } else {
                        statusEl.textContent = 'Error: Official Cookie Banner element not found on page.';
                    }
                });
            }

            if (clearCookiesBtn) {
                clearCookiesBtn.addEventListener('click', () => {
                    // Clear all official cookie system variables
                    document.cookie = 'cookie_consent=; Path=/; Expires=Thu, 01 Jan 1970 00:00:01 GMT;';
                    document.cookie = 'cookie_preferences=; Path=/; Expires=Thu, 01 Jan 1970 00:00:01 GMT;';
                    document.cookie = 'cookie_consent_date=; Path=/; Expires=Thu, 01 Jan 1970 00:00:01 GMT;';
                    statusEl.textContent = 'BitBalance cookies cleared. Refresh the page to see banner naturally.';
                });
            }
        });
    </script>
</body>
</html>