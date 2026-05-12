<?php
require_once __DIR__ . '/include/init.php';
require_once __DIR__ . '/include/handlers/log_attempt.php';

// if ($isLoggedIn) {
//     log_attempt($pdo, $user['user_id'], 'view', 'User ' . $user['user_id'] . ' viewed terms', 'terms', null);
// }

$activeHeader = 'about';
?>

<!DOCTYPE html>
<html lang="en" data-theme="<?php echo isset($_SESSION['user']) ? ($_SESSION['user']['theme_preference'] ?? 'light') : 'light'; ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Terms & Conditions | BitBalance</title>
    
    <?php include PROJECT_ROOT . 'views/head_css.php'; ?>

    <script src="https://kit.fontawesome.com/b94f65ead2.js" crossorigin="anonymous"></script>
    
    <style>
        /* Page-specific extras (dùng tokens chung) */
        :root {
            --border-light: 1px solid rgba(0,0,0,0.05);
        }
        [data-theme="dark"] {
            --border-light: 1px solid rgba(255,255,255,0.1);
        }

        body {
            line-height: 1.7;
        }

        /* 2. PAGE LAYOUT */
        .terms-wrapper {
            max-width: 900px;
            margin: 40px auto;
            padding: 0 20px;
        }

        /* 3. HEADER CARD */
        .terms-header-card {
            text-align: center;
            margin-bottom: 40px;
            padding: 60px 20px;
            background: linear-gradient(135deg, #4a7ee3, #764ba2);
            border-radius: 24px;
            color: white;
            box-shadow: 0 10px 30px rgba(74, 126, 227, 0.3);
        }

        .terms-header-card h1 {
            font-size: 2.5rem;
            font-weight: 800;
            margin-bottom: 10px;
            letter-spacing: -0.5px;
        }

        .terms-header-card p {
            font-size: 1.1rem;
            opacity: 0.9;
        }

        /* 4. CONTENT CARD */
        .terms-content-card {
            background: var(--card-bg);
            border-radius: var(--border-radius);
            padding: 50px;
            box-shadow: var(--shadow-soft);
            border: var(--border-light);
        }

        /* Typography within content */
        .terms-content-card h2 {
            font-size: 1.5rem;
            color: var(--text-primary);
            margin-top: 40px;
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 2px solid rgba(0,0,0,0.05);
            display: flex;
            align-items: center;
            gap: 12px;
        }
        
        [data-theme="dark"] .terms-content-card h2 {
            border-bottom-color: rgba(255,255,255,0.1);
        }

        .terms-content-card h2 i {
            color: var(--primary-color);
            font-size: 1.2rem;
        }

        .terms-content-card h3 {
            font-size: 1.2rem;
            color: var(--text-primary);
            margin-top: 25px;
            margin-bottom: 10px;
        }

        .terms-content-card p {
            margin-bottom: 15px;
            color: var(--text-secondary);
        }

        .terms-content-card ul {
            margin-bottom: 20px;
            padding-left: 20px;
            color: var(--text-secondary);
        }

        .terms-content-card li {
            margin-bottom: 8px;
            position: relative;
            list-style: none;
        }

        .terms-content-card li::before {
            content: "•";
            color: var(--primary-color);
            font-weight: bold;
            display: inline-block;
            width: 1em;
            margin-left: -1em;
        }

        /* Highlight Box */
        .highlight-box {
            background: rgba(74, 126, 227, 0.08);
            border-left: 4px solid var(--primary-color);
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 30px;
        }
        
        .highlight-box p {
            margin: 0;
            color: var(--text-primary);
            font-weight: 500;
        }

        /* Cookie Section Styling */
        .cookie-card {
            background: var(--bg-body);
            border: 1px solid rgba(0,0,0,0.05);
            border-radius: 12px;
            padding: 25px;
            margin-bottom: 20px;
            transition: transform 0.2s;
        }
        
        [data-theme="dark"] .cookie-card {
            border-color: rgba(255,255,255,0.1);
        }

        .cookie-card:hover {
            transform: translateY(-2px);
        }

        .cookie-card h4 {
            margin-top: 0;
            color: var(--primary-color);
            font-size: 1.1rem;
            margin-bottom: 10px;
        }

        .cookie-tag {
            display: inline-block;
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 700;
            text-transform: uppercase;
            margin-left: 10px;
            vertical-align: middle;
        }
        
        .tag-essential { background: #ffebee; color: #ef5350; }
        .tag-analytics { background: #e3f2fd; color: #42a5f5; }
        .tag-preference { background: #e8f5e9; color: #66bb6a; }

        /* Testing Controls */
        .dev-controls {
            margin-top: 40px;
            padding: 20px;
            border: 2px dashed rgba(0,0,0,0.1);
            border-radius: 12px;
            text-align: center;
        }
        
        [data-theme="dark"] .dev-controls {
            border-color: rgba(255,255,255,0.1);
        }

        .btn-test {
            background: var(--bg-body);
            border: 1px solid var(--text-secondary);
            color: var(--text-secondary);
            padding: 8px 16px;
            border-radius: 6px;
            cursor: pointer;
            font-size: 0.9rem;
            margin: 5px;
            transition: all 0.2s;
        }

        .btn-test:hover {
            background: var(--text-secondary);
            color: var(--card-bg);
        }

        /* Footer Info */
        .last-updated {
            text-align: center;
            margin-top: 30px;
            color: var(--text-muted);
            font-size: 0.9rem;
            font-style: italic;
        }

        /* Responsive */
        @media (max-width: 768px) {
            .terms-content-card { padding: 30px 20px; }
            .terms-header-card { padding: 40px 20px; border-radius: 16px; }
            .terms-header-card h1 { font-size: 2rem; }
        }
    </style>
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

    <div id="cookie-banner" class="cookie-banner" style="display: none; position: fixed; bottom: 0; left: 0; right: 0; background: #2c3e50; color: white; padding: 20px; z-index: 9999; box-shadow: 0 -5px 20px rgba(0,0,0,0.2);">
        <div style="max-width: 1200px; margin: 0 auto; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 15px;">
            <div style="flex: 1; min-width: 280px;">
                <h4 style="margin: 0 0 5px 0; font-size: 1rem;">We value your privacy 🍪</h4>
                <p style="margin: 0; font-size: 0.9rem; color: #ecf0f1;">This website uses cookies to enhance your experience. <a href="#" style="color: #3498db; text-decoration: underline;">Read Policy</a></p>
            </div>
            <div style="display: flex; gap: 10px;">
                <button id="accept-essential" style="background: transparent; border: 1px solid rgba(255,255,255,0.5); color: white; padding: 8px 16px; border-radius: 6px; cursor: pointer;">Essential Only</button>
                <button id="accept-all" style="background: #27ae60; border: none; color: white; padding: 8px 20px; border-radius: 6px; font-weight: 600; cursor: pointer;">Accept All</button>
            </div>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Cookie Banner Logic
            const banner = document.getElementById('cookie-banner');
            const statusEl = document.getElementById('cookie-test-status');

            function showBanner() {
                banner.style.display = 'block';
                banner.style.opacity = '0';
                setTimeout(() => { 
                    banner.style.transition = 'opacity 0.3s'; 
                    banner.style.opacity = '1'; 
                }, 10);
            }

            function hideBanner() {
                banner.style.opacity = '0';
                setTimeout(() => { banner.style.display = 'none'; }, 300);
            }

            function setCookie(name, val) {
                document.cookie = name + "=" + val + "; path=/; max-age=" + (60*60*24*365);
            }

            function getCookie(name) {
                const v = document.cookie.match('(^|;) ?' + name + '=([^;]*)(;|$)');
                return v ? v[2] : null;
            }

            // Init Check
            if (!getCookie('cookie_consent')) {
                setTimeout(showBanner, 1500);
            }

            // Button Actions
            document.getElementById('accept-all').addEventListener('click', () => {
                setCookie('cookie_consent', 'full');
                hideBanner();
            });

            document.getElementById('accept-essential').addEventListener('click', () => {
                setCookie('cookie_consent', 'essential');
                hideBanner();
            });

            // Dev Controls
            document.getElementById('test-show-banner').addEventListener('click', () => {
                showBanner();
                statusEl.textContent = 'Banner displayed.';
            });

            document.getElementById('test-clear-cookies').addEventListener('click', () => {
                document.cookie = 'cookie_consent=; Path=/; Expires=Thu, 01 Jan 1970 00:00:01 GMT;';
                statusEl.textContent = 'Cookies cleared. Refresh to see banner naturally.';
            });
        });
    </script>
</body>
</html>