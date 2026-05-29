<?php
require_once __DIR__ . '/include/init.php';
require_once __DIR__ . '/include/handlers/log_attempt.php';

// if ($isLoggedIn) {
//     log_attempt($pdo, $user['user_id'], 'view', 'User ' . $user['user_id'] . ' viewed terms', 'terms', null);
// }

$activeHeader = 'about';
?>

<!DOCTYPE html>
<html lang="en" data-theme="<?php echo isset($_SESSION['user']) ? ($_SESSION['user']['theme_preference'] ?? 'system') : 'system'; ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Terms & Conditions | BitBalance</title>
    
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
            <h1>Terms & Conditions</h1>
            <p>Transparency, Privacy, and Trust</p>
        </header>

        <main class="terms-content-card">
            <div class="highlight-box">
                <p><i class="fas fa-info-circle"></i> <strong>Important:</strong> Even though BitBalance is a student project, we take user trust seriously. This page outlines how we handle your data responsibly.</p>
            </div>

            <h2><i class="fas fa-shield-alt"></i> Data Privacy & Security</h2>
            <p>All user information (such as log-in details, height, and weight) is stored securely in our project database. We do not share this data with third parties—it is strictly used to maintain the site's functionality and help you track your goals.</p>
            <p>You have full control over your data. You can delete, archive, or modify your account details at any time through your profile settings.</p>

            <h2 id="cookies-policy"><i class="fas fa-cookie-bite"></i> Cookies Policy</h2>
            <p>Cookies are small text files stored on your device. We use them to enhance your experience by remembering your preferences.</p>

            <h3>Types of Cookies We Use</h3>

            <div class="cookie-card">
                <h4>Essential Cookies <span class="cookie-tag tag-essential">Required</span></h4>
                <p>Necessary for the website to function properly. Cannot be disabled.</p>
                <ul>
                    <li>User authentication (keeping you logged in)</li>
                    <li>Shopping cart items</li>
                    <li>Security and fraud prevention</li>
                </ul>
            </div>

            <div class="cookie-card">
                <h4>Preference Cookies <span class="cookie-tag tag-preference">Optional</span></h4>
                <p>Remember your settings for a personalized experience.</p>
                <ul>
                    <li>Theme preference (Dark/Light mode)</li>
                    <li>Dashboard layout settings</li>
                </ul>
            </div>

            <div class="cookie-card">
                <h4>Analytics Cookies <span class="cookie-tag tag-analytics">Optional</span></h4>
                <p>Help us understand how visitors interact with our site (anonymously).</p>
                <ul>
                    <li>Page visit counts</li>
                    <li>Traffic sources</li>
                </ul>
            </div>

            <h2><i class="fas fa-users"></i> Community Guidelines</h2>
            <p>Our forum is a space for support and sharing. By using it, you agree to:</p>
            <ul>
                <li>Be respectful to other members.</li>
                <li>Avoid posting harmful or offensive content.</li>
                <li>Keep discussions relevant to health and nutrition.</li>
            </ul>

            <h2><i class="fas fa-university"></i> RMIT Compliance</h2>
            <p>BitBalance is hosted on RMIT's teaching servers. We strictly follow the university's technical and ethical guidelines, including acceptable use policies and security standards.</p>

            <div class="dev-controls">
                <h4 style="margin-top:0; color:var(--text-secondary); font-size:0.9rem; text-transform:uppercase;">Developer Tools</h4>
                <button id="test-show-banner" class="btn-test"><i class="fas fa-eye"></i> Show Cookie Banner</button>
                <button id="test-clear-cookies" class="btn-test"><i class="fas fa-trash"></i> Clear Cookie Data</button>
                <p id="cookie-test-status" style="margin-top:10px; font-size:0.85rem; color:var(--primary-color); min-height:1.2em;"></p>
            </div>

            <div class="last-updated">
                Last updated: <?= date('F j, Y') ?>
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