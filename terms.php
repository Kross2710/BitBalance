<?php
require_once __DIR__ . '/include/init.php';
require_once __DIR__ . '/include/handlers/log_attempt.php';

if ($isLoggedIn) {
    // Log the user activity
    log_attempt($pdo, $user['user_id'], 'view', 'User ' . $user['user_id'] . ' viewed terms', 'terms', null);
}
?>

<!DOCTYPE html>
<html lang="en" data-theme="<?php echo isset($_SESSION['user']) ? ($_SESSION['user']['theme_preference'] ?? 'light') : 'light'; ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Terms and Conditions - BitBalance</title>
    <link rel="stylesheet" href="css/style.css">
    <script src="https://kit.fontawesome.com/b94f65ead2.js" crossorigin="anonymous"></script>
    <style>
        /* Light Theme Variables */
        :root {
            --bg-color: #f8f9fa;
            --card-bg: #ffffff;
            --text-color: #212529;
            --text-muted: #6c757d;
            --border-color: #e9ecef;
            --primary-color: #4a7ee3;
            --shadow: 0 4px 20px rgba(0,0,0,0.08);
        }
        
        /* Dark Theme Variables */
        [data-theme="dark"] {
            --bg-color: #1a1a1a;
            --card-bg: #2d2d2d;
            --text-color: #ffffff;
            --text-muted: #adb5bd;
            --border-color: #495057;
            --primary-color: #4a7ee3;
            --shadow: 0 4px 20px rgba(0,0,0,0.3);
        }
        
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: var(--bg-color);
            color: var(--text-color);
            margin: 0;
            padding: 20px;
            transition: background-color 0.3s ease, color 0.3s ease;
            line-height: 1.6;
        }
        
        .terms-container {
            max-width: 800px;
            margin: 0 auto;
            background: var(--card-bg);
            border-radius: 12px;
            padding: 40px;
            box-shadow: var(--shadow);
            border: 1px solid var(--border-color);
        }
        
        .terms-header {
            text-align: center;
            margin-bottom: 30px;
        }
        
        .terms-header h1 {
            color: var(--text-color);
            font-size: 2.5rem;
            margin-bottom: 10px;
        }
        
        .terms-header p {
            color: var(--text-muted);
            font-size: 1.1rem;
        }
        
        .terms-content h2 {
            color: var(--primary-color);
            margin-top: 30px;
            margin-bottom: 15px;
            font-size: 1.5rem;
        }
        
        .terms-content h3 {
            color: var(--text-color);
            margin-top: 25px;
            margin-bottom: 10px;
            font-size: 1.2rem;
        }
        
        .terms-content h4 {
            color: var(--text-color);
            margin-top: 20px;
            margin-bottom: 10px;
            font-size: 1.1rem;
        }
        
        .terms-content p {
            color: var(--text-color);
            margin-bottom: 15px;
            text-align: justify;
        }
        
        .terms-content ul {
            color: var(--text-color);
            margin-bottom: 15px;
        }
        
        .back-link {
            text-align: center;
            margin-top: 30px;
            padding-top: 20px;
            border-top: 1px solid var(--border-color);
        }
        
        .back-link a {
            color: var(--primary-color);
            text-decoration: none;
            font-weight: 500;
        }
        
        .back-link a:hover {
            text-decoration: underline;
        }
        
        .highlight {
            background: var(--bg-color);
            padding: 15px;
            border-radius: 8px;
            border-left: 4px solid var(--primary-color);
            margin: 20px 0;
        }
        
        .last-updated {
            color: var(--text-muted);
            font-size: 0.9rem;
            text-align: center;
            margin-top: 30px;
            font-style: italic;
        }

        /* Cookie Policy Styling */
        .cookie-type {
            background: var(--bg-color);
            border-left: 4px solid #3498db;
            padding: 20px;
            margin: 20px 0;
            border-radius: 0 8px 8px 0;
        }

        .cookie-type h4 {
            color: var(--text-color);
            margin-top: 0;
            margin-bottom: 10px;
            font-size: 1.1em;
            font-weight: 600;
        }

        .cookie-controls {
            background: linear-gradient(135deg, #e8f4fd 0%, #f0f9ff 100%);
            border: 2px solid #3498db;
            padding: 25px;
            border-radius: 12px;
            text-align: center;
            margin: 25px 0;
        }

        [data-theme="dark"] .cookie-controls {
            background: linear-gradient(135deg, #2d4a5c 0%, #3d5a6c 100%);
            border-color: #4a90e2;
        }

        .cookie-settings-btn {
            background: linear-gradient(135deg, #3498db 0%, #2980b9 100%);
            color: white;
            border: none;
            padding: 15px 30px;
            border-radius: 8px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            margin-bottom: 15px;
        }

        .cookie-settings-btn:hover {
            background: linear-gradient(135deg, #2980b9 0%, #1f5582 100%);
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(52, 152, 219, 0.3);
        }

        .warning-box {
            background: #fff3cd;
            border: 1px solid #ffeaa7;
            border-left: 4px solid #f39c12;
            padding: 20px;
            border-radius: 0 8px 8px 0;
            margin: 20px 0;
        }

        .warning-box h4 {
            color: #856404;
            margin-top: 0;
            margin-bottom: 15px;
        }

        .warning-box p,
        .warning-box ul {
            color: #856404;
        }

        [data-theme="dark"] .warning-box {
            background: #4a3c2a;
            border-color: #8b6914;
            border-left-color: #f39c12;
        }

        [data-theme="dark"] .warning-box h4,
        [data-theme="dark"] .warning-box p,
        [data-theme="dark"] .warning-box ul {
            color: #ffd700;
        }

        .contact-info {
            background: #e8f5e8;
            border-left: 4px solid #27ae60;
            padding: 20px;
            border-radius: 0 8px 8px 0;
            margin: 30px 0;
        }

        .contact-info h4 {
            color: #2c5234;
            margin-top: 0;
            margin-bottom: 10px;
        }

        .contact-info p {
            color: #2c5234;
            margin-bottom: 0;
        }

        [data-theme="dark"] .contact-info {
            background: #2c4a2c;
            border-left-color: #27ae60;
        }

        [data-theme="dark"] .contact-info h4,
        [data-theme="dark"] .contact-info p {
            color: #90ee90;
        }

        .cookie-test-section {
            margin-top: 40px;
            padding: 25px;
            background: var(--bg-color);
            border: 2px dashed var(--border-color);
            border-radius: 8px;
            text-align: center;
        }

        .cookie-test-section h3 {
            color: var(--text-muted);
            font-size: 1.1em;
            margin-bottom: 10px;
        }

        .cookie-test-section p {
            color: var(--text-muted);
            font-size: 0.9em;
            margin-bottom: 15px;
        }

        .test-buttons {
            display: flex;
            gap: 15px;
            justify-content: center;
            margin-bottom: 15px;
            flex-wrap: wrap;
        }

        .test-cookie-btn {
            background: linear-gradient(135deg, #3498db 0%, #2980b9 100%);
            color: white;
            border: none;
            padding: 12px 20px;
            border-radius: 6px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .test-cookie-btn:hover {
            background: linear-gradient(135deg, #2980b9 0%, #1f5582 100%);
            transform: translateY(-1px);
        }

        .test-cookie-btn.clear {
            background: linear-gradient(135deg, #e74c3c 0%, #c0392b 100%);
        }

        .test-cookie-btn.clear:hover {
            background: linear-gradient(135deg, #c0392b 0%, #a93226 100%);
        }

        .test-status {
            font-size: 12px;
            color: var(--text-muted);
            font-style: italic;
            min-height: 20px;
        }

        .test-status.success {
            color: #27ae60;
        }

        .test-status.error {
            color: #e74c3c;
        }

        @media (max-width: 768px) {
            .test-buttons {
                flex-direction: column;
                align-items: center;
            }
            
            .test-cookie-btn {
                width: 200px;
            }
        }

        /* Cookie Banner Styles */
        .cookie-banner {
            position: fixed;
            bottom: 0;
            left: 0;
            right: 0;
            background: linear-gradient(135deg, #2c3e50 0%, #34495e 100%);
            color: white;
            padding: 20px;
            box-shadow: 0 -4px 20px rgba(0, 0, 0, 0.3);
            z-index: 10000;
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
        }

        .cookie-content {
            max-width: 1200px;
            margin: 0 auto;
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 20px;
        }

        .cookie-text {
            display: flex;
            align-items: center;
            gap: 15px;
            flex: 1;
        }

        .cookie-icon {
            font-size: 2rem;
            animation: bounce 2s infinite;
        }

        @keyframes bounce {
            0%, 20%, 50%, 80%, 100% { transform: translateY(0); }
            40% { transform: translateY(-10px); }
            60% { transform: translateY(-5px); }
        }

        .cookie-message h4 {
            margin: 0 0 5px 0;
            font-size: 16px;
        }

        .cookie-message p {
            margin: 0;
            font-size: 14px;
        }

        .cookie-message a {
            color: #3498db;
            text-decoration: none;
        }

        .cookie-buttons {
            display: flex;
            gap: 10px;
        }

        .cookie-btn {
            padding: 10px 20px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 14px;
            font-weight: 600;
            transition: all 0.3s ease;
        }

        .cookie-btn.accept {
            background: #27ae60;
            color: white;
        }

        .cookie-btn.essential {
            background: transparent;
            color: white;
            border: 2px solid #7f8c8d;
        }

        .cookie-btn:hover {
            opacity: 0.9;
            transform: translateY(-1px);
        }

        /* Dark mode support for cookie banner */
        [data-theme="dark"] .cookie-banner {
            background: linear-gradient(135deg, #1a1a1a 0%, #2d2d2d 100%);
            border-top: 1px solid #404040;
        }

        @media (max-width: 768px) {
            .cookie-content {
                flex-direction: column;
                gap: 15px;
            }
            
            .cookie-buttons {
                width: 100%;
                justify-content: center;
            }
        }
    </style>
</head>
<body>
    <div class="terms-container">
        <div class="terms-header">
            <h1><i class="fas fa-file-contract"></i> Terms and Conditions</h1>
            <p>BitBalance Student Project</p>
        </div>
        
        <div class="terms-content">
            <div class="highlight">
                <p><strong>Important:</strong> Even though BitBalance is a student project, we still take user trust seriously and would like to show that we understand the importance of ethical and legal requirements in developing a web application.</p>
            </div>
            
            <h2><i class="fas fa-shield-alt"></i> Data Privacy & Security</h2>
            <p>All user information (such as log-in details, height and weight) will be stored securely in our project database. We will not share this data with anyone else—it is solely for the purposes of keeping the site running and helping users record their goals.</p>
            
            <p>Users are free to delete, archive or modify their account details at any time through their profile settings.</p>
            
            <h2 id="cookies-policy"><i class="fas fa-cookie-bite"></i> Cookies Policy</h2>
            
            <h3>What are Cookies?</h3>
            <p>Cookies are small text files that are stored on your device when you visit our website. They help us provide you with a better experience by remembering your preferences and analyzing how you use our site.</p>
            
            <h3>Types of Cookies We Use</h3>
            
            <div class="cookie-type">
                <h4>Essential Cookies (Required)</h4>
                <p><strong>Purpose:</strong> These cookies are necessary for the website to function properly and cannot be disabled.</p>
                <p><strong>What they do:</strong></p>
                <ul>
                    <li>Enable user authentication and login sessions</li>
                    <li>Remember items in your shopping cart</li>
                    <li>Provide security features and prevent fraud</li>
                    <li>Remember your cookie preferences</li>
                </ul>
                <p><strong>Retention:</strong> Session cookies (deleted when you close your browser) or up to 1 year for persistent cookies.</p>
            </div>
            
            <div class="cookie-type">
                <h4>Analytics Cookies (Optional)</h4>
                <p><strong>Purpose:</strong> Help us understand how visitors interact with our website.</p>
                <p><strong>What they do:</strong></p>
                <ul>
                    <li>Count visits and traffic sources to measure and improve site performance</li>
                    <li>Identify which pages are most and least popular</li>
                    <li>Track user navigation patterns (anonymously)</li>
                    <li>Help us identify technical issues</li>
                </ul>
                <p><strong>Retention:</strong> Up to 2 years</p>
                <p><strong>Third parties:</strong> We may use Google Analytics or similar services for our student project analysis.</p>
            </div>
            
            <div class="cookie-type">
                <h4>Preference Cookies (Optional)</h4>
                <p><strong>Purpose:</strong> Remember your settings and preferences for a personalized experience.</p>
                <p><strong>What they do:</strong></p>
                <ul>
                    <li>Remember your theme preference (light/dark mode)</li>
                    <li>Store your language and region settings</li>
                    <li>Remember your dashboard layout preferences</li>
                    <li>Save your fitness goals and settings</li>
                </ul>
                <p><strong>Retention:</strong> Up to 1 year</p>
            </div>
            
            
            
            <h3>Cookie Consent</h3>
            <p>By using our website, you agree to our use of cookies as described in this policy. You can withdraw your consent at any time by:</p>
            <ul>
                <li>Using the cookie settings panel (button above)</li>
                <li>Clearing your browser cookies</li>
                <li>Contacting us directly</li>
            </ul>
            
            <h2><i class="fas fa-comments"></i> Forum & Community Content</h2>
            <p>Users may post in the forum to share tips or ask questions. We'll establish some general community guidelines to promote respect and keep things positive.</p>
            
            <p>Admin users will also be able to moderate posts to remove anything objectionable. By using our forum features, you agree to:</p>
            <ul>
                <li>Be respectful to other community members</li>
                <li>Not post harmful, offensive, or inappropriate content</li>
                <li>Keep discussions relevant and constructive</li>
                <li>Respect others' privacy and personal information</li>
            </ul>
            
            <h2><i class="fas fa-copyright"></i> Copyright & Content Use</h2>
            <p>Any images, code libraries, or icons we use will be either our own or from open-source or free-to-use resources. We'll acknowledge these properly in our code or documentation.</p>
            
            <p>Users retain ownership of any content they post, but grant BitBalance permission to display and moderate this content as necessary for site operation.</p>
            
            <h2><i class="fas fa-university"></i> RMIT Hosting & Compliance</h2>
            <p>Since BitBalance is being built and hosted on RMIT's teaching servers, we're also following the technical and ethical guidelines provided by the course to make sure everything runs smoothly and appropriately.</p>
            
            <p>This includes:</p>
            <ul>
                <li>Following RMIT's acceptable use policies</li>
                <li>Maintaining appropriate security standards</li>
                <li>Ensuring educational compliance requirements are met</li>
                <li>Respecting server resource limitations</li>
            </ul>
            
            <h2><i class="fas fa-info-circle"></i> Additional Terms</h2>
            <h3>Account Responsibility</h3>
            <p>Users are responsible for maintaining the security of their account credentials and for all activities that occur under their account.</p>
            
            <h3>Service Availability</h3>
            <p>As a student project, BitBalance may experience occasional downtime or interruptions. We'll do our best to maintain reliable service during the project period.</p>
            
            <h3>Changes to Terms</h3>
            <p>We may update these terms as needed during the project development. Users will be notified of any significant changes.</p>
            
            <h3>Updates to Cookie Policy</h3>
            <p>We may update our cookie policy from time to time to reflect changes in our practices or for other operational, legal, or regulatory reasons. The date of the last update is shown at the bottom of this page.</p>
            
        
        </div>
        
        <div class="last-updated">
            <p>Last updated: <?= date('F j, Y') ?></p>
        </div>
        
        <div class="back-link">
            <a href="javascript:history.back()">
                <i class="fas fa-arrow-left"></i> Go Back
            </a>
        </div>

        <!-- Testing Section -->
        <div class="cookie-test-section">
            <h3><i class="fas fa-flask"></i> Testing Cookie Banner</h3>
            <p>For development/testing purposes:</p>
            
            <div class="test-buttons">
                <button id="test-show-banner" class="test-cookie-btn">
                    <i class="fas fa-cookie-bite"></i> Show Cookie Banner
                </button>
                
                <button id="test-clear-cookies" class="test-cookie-btn clear">
                    <i class="fas fa-trash-alt"></i> Clear Cookie Data
                </button>
            </div>
            
            <p id="cookie-test-status" class="test-status"></p>
        </div>
    </div>

    <!-- Cookie Banner - Added directly to this page -->
    <div id="cookie-banner" class="cookie-banner" style="display: none;">
        <div class="cookie-content">
            <div class="cookie-text">
                <div class="cookie-icon">🍪</div>
                <div class="cookie-message">
                    <h4>We value your privacy</h4>
                    <p>This website uses cookies to enhance your browsing experience. 
                    <a href="terms.php" target="_blank">Learn more</a></p>
                </div>
            </div>
            <div class="cookie-buttons">
                <button id="accept-all" class="cookie-btn accept">Accept All</button>
                <button id="accept-essential" class="cookie-btn essential">Essential Only</button>
            </div>
        </div>
    </div>

    <script>
        // Cookie banner functionality
        document.addEventListener('DOMContentLoaded', function() {
            const acceptAllBtn = document.getElementById('accept-all');
            const acceptEssentialBtn = document.getElementById('accept-essential');
            const cookieBanner = document.getElementById('cookie-banner');
            
            // Check if user has already consented
            const cookieConsent = getCookie('cookie_consent');
            if (!cookieConsent) {
                // Show banner after 2 seconds for first-time visitors
                setTimeout(() => {
                    cookieBanner.style.display = 'block';
                    cookieBanner.style.opacity = '0';
                    setTimeout(() => {
                        cookieBanner.style.transition = 'opacity 0.5s ease';
                        cookieBanner.style.opacity = '1';
                    }, 100);
                }, 2000);
            }
            
            acceptAllBtn.addEventListener('click', function() {
                setCookie('cookie_consent', 'accepted', 365);
                hideBanner();
                console.log('All cookies accepted');
            });
            
            acceptEssentialBtn.addEventListener('click', function() {
                setCookie('cookie_consent', 'essential', 365);
                hideBanner();
                console.log('Essential cookies only');
            });
            
            function hideBanner() {
                cookieBanner.style.opacity = '0';
                setTimeout(() => {
                    cookieBanner.style.display = 'none';
                }, 300);
            }
        });

        // Test button functionality
        document.addEventListener('DOMContentLoaded', function() {
            const showBannerBtn = document.getElementById('test-show-banner');
            const clearCookiesBtn = document.getElementById('test-clear-cookies');
            const statusEl = document.getElementById('cookie-test-status');
            
            // Show cookie banner
            showBannerBtn.addEventListener('click', function() {
                const cookieBanner = document.getElementById('cookie-banner');
                if (cookieBanner) {
                    // Show the banner
                    cookieBanner.style.display = 'block';
                    cookieBanner.style.opacity = '0';
                    setTimeout(() => {
                        cookieBanner.style.transition = 'opacity 0.5s ease';
                        cookieBanner.style.opacity = '1';
                    }, 100);
                    
                    updateStatus('Cookie banner displayed!', 'success');
                } else {
                    updateStatus('Cookie banner not found.', 'error');
                }
            });
            
            // Clear cookie data
            clearCookiesBtn.addEventListener('click', function() {
                // Clear cookie consent data
                deleteCookie('cookie_consent');
                deleteCookie('cookie_preferences');
                deleteCookie('cookie_consent_date');
                
                updateStatus('Cookie data cleared! Refresh to see banner naturally.', 'success');
            });
            
            function updateStatus(message, type = '') {
                statusEl.textContent = message;
                statusEl.className = 'test-status ' + type;
                
                // Clear status after 5 seconds
                setTimeout(() => {
                    statusEl.textContent = '';
                    statusEl.className = 'test-status';
                }, 5000);
            }
            
            function deleteCookie(name) {
                document.cookie = name + '=; expires=Thu, 01 Jan 1970 00:00:00 UTC; path=/;';
            }
        });

        // Cookie utility functions
        function setCookie(name, value, days) {
            const expires = new Date();
            expires.setTime(expires.getTime() + (days * 24 * 60 * 60 * 1000));
            document.cookie = name + '=' + value + ';expires=' + expires.toUTCString() + ';path=/';
        }
        
        function getCookie(name) {
            const nameEQ = name + "=";
            const ca = document.cookie.split(';');
            for(let i = 0; i < ca.length; i++) {
                let c = ca[i];
                while (c.charAt(0) == ' ') c = c.substring(1, c.length);
                if (c.indexOf(nameEQ) == 0) return c.substring(nameEQ.length, c.length);
            }
            return null;
        }

        // Manage cookies button functionality
        document.addEventListener('DOMContentLoaded', function() {
            const manageCookiesLink = document.getElementById('manage-cookies-link');
            
            if (manageCookiesLink) {
                manageCookiesLink.addEventListener('click', function() {
                    // For now, just show the banner
                    const cookieBanner = document.getElementById('cookie-banner');
                    if (cookieBanner) {
                        cookieBanner.style.display = 'block';
                        cookieBanner.style.opacity = '0';
                        setTimeout(() => {
                            cookieBanner.style.transition = 'opacity 0.5s ease';
                            cookieBanner.style.opacity = '1';
                        }, 100);
                    } else {
                        alert('Cookie banner is available on this page. Use the test buttons below to see it.');
                    }
                });
            }
        });
    </script>
</body>
</html>