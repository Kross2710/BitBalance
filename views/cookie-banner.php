<!-- Cookie Consent Banner -->
<div id="cookie-banner" class="cookie-banner" style="display: none;">
    <div class="cookie-content">
        <div class="cookie-text">
            <div class="cookie-icon">🍪</div>
            <div class="cookie-message">
                <h4>We value your privacy</h4>
                <p>This website uses cookies to enhance your browsing experience, analyze site traffic, and provide personalized content. 
                By clicking "Accept All", you consent to our use of cookies as described in our 
                <a href="terms.php" target="_blank">Terms and Conditions</a>.</p>
            </div>
        </div>
        <div class="cookie-buttons">
            <button id="manage-cookies" class="cookie-btn manage">Cookie Settings</button>
            <button id="accept-essential" class="cookie-btn essential">Essential Only</button>
            <button id="accept-all" class="cookie-btn accept">Accept All</button>
        </div>
    </div>
</div>

<!-- Cookie Settings Modal -->
<div id="cookie-modal" class="cookie-modal" style="display: none;">
    <div class="cookie-modal-content">
        <div class="cookie-modal-header">
            <h3>Cookie Preferences</h3>
            <button id="close-modal" class="close-modal">&times;</button>
        </div>
        <div class="cookie-modal-body">
            <p>We use different types of cookies to optimize your experience on our website. Choose which cookies you want to allow:</p>
            
            <div class="cookie-category">
                <div class="cookie-category-header">
                    <input type="checkbox" id="essential-cookies" checked disabled>
                    <label for="essential-cookies">
                        <strong>Essential Cookies</strong>
                        <span class="required">(Required)</span>
                    </label>
                </div>
                <p class="cookie-description">These cookies are necessary for the website to function and cannot be disabled. They enable core functionality such as security, authentication, and accessibility.</p>
            </div>
            
            <div class="cookie-category">
                <div class="cookie-category-header">
                    <input type="checkbox" id="analytics-cookies">
                    <label for="analytics-cookies">
                        <strong>Analytics Cookies</strong>
                    </label>
                </div>
                <p class="cookie-description">These cookies help us understand how visitors interact with our website by collecting anonymous information about usage patterns.</p>
            </div>
            
            <div class="cookie-category">
                <div class="cookie-category-header">
                    <input type="checkbox" id="preference-cookies">
                    <label for="preference-cookies">
                        <strong>Preference Cookies</strong>
                    </label>
                </div>
                <p class="cookie-description">These cookies remember your preferences and settings to provide a more personalized experience on future visits.</p>
            </div>
            
            <div class="terms-link">
                <p>For more detailed information about our data practices, please read our 
                <a href="terms.php" target="_blank">Terms and Conditions</a>.</p>
            </div>
        </div>
        <div class="cookie-modal-footer">
            <button id="save-preferences" class="cookie-btn accept">Save Preferences</button>
            <button id="accept-all-modal" class="cookie-btn accept">Accept All</button>
        </div>
    </div>
</div>

<style>
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
    backdrop-filter: blur(10px);
}

.cookie-content {
    max-width: 1200px;
    margin: 0 auto;
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 30px;
}

.cookie-text {
    display: flex;
    align-items: center;
    gap: 20px;
    flex: 1;
}

.cookie-icon {
    font-size: 2.5rem;
    animation: bounce 2s infinite;
}

@keyframes bounce {
    0%, 20%, 50%, 80%, 100% { transform: translateY(0); }
    40% { transform: translateY(-10px); }
    60% { transform: translateY(-5px); }
}

.cookie-message h4 {
    margin: 0 0 8px 0;
    font-size: 18px;
    font-weight: 600;
    color: #fff;
}

.cookie-message p {
    margin: 0;
    font-size: 14px;
    line-height: 1.5;
    color: #ecf0f1;
}

.cookie-message a {
    color: #3498db;
    text-decoration: none;
    font-weight: 500;
}

.cookie-message a:hover {
    text-decoration: underline;
}

.cookie-buttons {
    display: flex;
    gap: 12px;
    flex-shrink: 0;
}

.cookie-btn {
    padding: 12px 20px;
    border: none;
    border-radius: 8px;
    cursor: pointer;
    font-size: 14px;
    font-weight: 600;
    transition: all 0.3s ease;
    white-space: nowrap;
}

.cookie-btn.accept {
    background: linear-gradient(135deg, #27ae60 0%, #2ecc71 100%);
    color: white;
}

.cookie-btn.accept:hover {
    background: linear-gradient(135deg, #229954 0%, #27ae60 100%);
    transform: translateY(-1px);
}

.cookie-btn.essential {
    background: transparent;
    color: #ecf0f1;
    border: 2px solid #7f8c8d;
}

.cookie-btn.essential:hover {
    background: #7f8c8d;
    color: white;
}

.cookie-btn.manage {
    background: transparent;
    color: #3498db;
    border: 2px solid #3498db;
}

.cookie-btn.manage:hover {
    background: #3498db;
    color: white;
}

/* Cookie Settings Modal */
.cookie-modal {
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0, 0, 0, 0.7);
    z-index: 10001;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 20px;
    box-sizing: border-box;
}

.cookie-modal-content {
    background: white;
    border-radius: 12px;
    max-width: 600px;
    width: 100%;
    max-height: 80vh;
    overflow-y: auto;
    box-shadow: 0 10px 40px rgba(0, 0, 0, 0.3);
}

.cookie-modal-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 24px 24px 0 24px;
    border-bottom: 1px solid #eee;
    margin-bottom: 20px;
}

.cookie-modal-header h3 {
    margin: 0;
    color: #2c3e50;
    font-size: 24px;
}

.close-modal {
    background: none;
    border: none;
    font-size: 28px;
    cursor: pointer;
    color: #7f8c8d;
    padding: 0;
    width: 32px;
    height: 32px;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 50%;
    transition: all 0.3s ease;
}

.close-modal:hover {
    background: #f8f9fa;
    color: #2c3e50;
}

.cookie-modal-body {
    padding: 0 24px 20px 24px;
}

.cookie-modal-body > p {
    margin-bottom: 24px;
    color: #555;
    line-height: 1.6;
}

.cookie-category {
    margin-bottom: 24px;
    padding: 16px;
    border: 1px solid #e9ecef;
    border-radius: 8px;
    background: #f8f9fa;
}

.cookie-category-header {
    display: flex;
    align-items: center;
    gap: 12px;
    margin-bottom: 8px;
}

.cookie-category-header input[type="checkbox"] {
    width: 18px;
    height: 18px;
    accent-color: #3498db;
}

.cookie-category-header label {
    display: flex;
    align-items: center;
    gap: 8px;
    cursor: pointer;
    font-size: 16px;
    color: #2c3e50;
}

.required {
    background: #e74c3c;
    color: white;
    padding: 2px 8px;
    border-radius: 12px;
    font-size: 11px;
    font-weight: 600;
}

.cookie-description {
    margin: 0;
    color: #666;
    font-size: 14px;
    line-height: 1.5;
    margin-left: 30px;
}

.terms-link {
    margin-top: 20px;
    padding-top: 20px;
    border-top: 1px solid #eee;
}

.terms-link p {
    margin: 0;
    color: #666;
    font-size: 14px;
}

.terms-link a {
    color: #3498db;
    text-decoration: none;
    font-weight: 500;
}

.terms-link a:hover {
    text-decoration: underline;
}

.cookie-modal-footer {
    padding: 20px 24px 24px 24px;
    border-top: 1px solid #eee;
    display: flex;
    gap: 12px;
    justify-content: flex-end;
}

/* Mobile responsive */
@media (max-width: 768px) {
    .cookie-content {
        flex-direction: column;
        text-align: center;
        gap: 20px;
    }
    
    .cookie-text {
        flex-direction: column;
        gap: 15px;
    }
    
    .cookie-buttons {
        justify-content: center;
        flex-wrap: wrap;
        width: 100%;
    }
    
    .cookie-btn {
        flex: 1;
        min-width: 120px;
    }
    
    .cookie-modal-content {
        margin: 10px;
        max-height: 90vh;
    }
    
    .cookie-modal-footer {
        flex-direction: column;
    }
    
    .cookie-modal-footer .cookie-btn {
        width: 100%;
    }
}

/* Dark mode support */
[data-theme="dark"] .cookie-banner {
    background: linear-gradient(135deg, #1a1a1a 0%, #2d2d2d 100%);
    border-top: 1px solid #404040;
}

[data-theme="dark"] .cookie-modal-content {
    background: #2d2d2d;
    color: #ffffff;
}

[data-theme="dark"] .cookie-modal-header {
    border-bottom-color: #404040;
}

[data-theme="dark"] .cookie-modal-header h3 {
    color: #ffffff;
}

[data-theme="dark"] .close-modal {
    color: #cccccc;
}

[data-theme="dark"] .close-modal:hover {
    background: #404040;
    color: #ffffff;
}

[data-theme="dark"] .cookie-modal-body > p {
    color: #cccccc;
}

[data-theme="dark"] .cookie-category {
    background: #3d3d3d;
    border-color: #555555;
}

[data-theme="dark"] .cookie-category-header label {
    color: #ffffff;
}

[data-theme="dark"] .cookie-description {
    color: #cccccc;
}

[data-theme="dark"] .terms-link {
    border-top-color: #404040;
}

[data-theme="dark"] .terms-link p {
    color: #cccccc;
}

[data-theme="dark"] .cookie-modal-footer {
    border-top-color: #404040;
}
</style>

<script>
// Cookie Consent JavaScript
document.addEventListener('DOMContentLoaded', function() {
    const cookieBanner = document.getElementById('cookie-banner');
    const cookieModal = document.getElementById('cookie-modal');
    const acceptAllBtn = document.getElementById('accept-all');
    const acceptEssentialBtn = document.getElementById('accept-essential');
    const manageCookiesBtn = document.getElementById('manage-cookies');
    const closeModalBtn = document.getElementById('close-modal');
    const savePreferencesBtn = document.getElementById('save-preferences');
    const acceptAllModalBtn = document.getElementById('accept-all-modal');
    
    // Check if user has already made a choice
    const cookieConsent = getCookie('cookie_consent');
    
    if (!cookieConsent) {
        // Show banner after 1.5 seconds if no consent recorded
        setTimeout(() => {
            cookieBanner.style.display = 'block';
            cookieBanner.style.opacity = '0';
            setTimeout(() => {
                cookieBanner.style.transition = 'opacity 0.5s ease';
                cookieBanner.style.opacity = '1';
            }, 100);
        }, 1500);
    }
    
    // Accept all cookies
    acceptAllBtn.addEventListener('click', function() {
        const preferences = {
            essential: true,
            analytics: true,
            preferences: true
        };
        saveCookiePreferences('accepted', preferences);
        hideBanner();
        enableAllCookies();
    });
    
    // Accept essential only
    acceptEssentialBtn.addEventListener('click', function() {
        const preferences = {
            essential: true,
            analytics: false,
            preferences: false
        };
        saveCookiePreferences('essential', preferences);
        hideBanner();
        enableEssentialCookies();
    });
    
    // Show cookie settings modal
    manageCookiesBtn.addEventListener('click', function() {
        showModal();
    });
    
    // Close modal
    closeModalBtn.addEventListener('click', function() {
        hideModal();
    });
    
    // Close modal when clicking outside
    cookieModal.addEventListener('click', function(e) {
        if (e.target === cookieModal) {
            hideModal();
        }
    });
    
    // Save custom preferences
    savePreferencesBtn.addEventListener('click', function() {
        const preferences = {
            essential: true, // Always true
            analytics: document.getElementById('analytics-cookies').checked,
            preferences: document.getElementById('preference-cookies').checked
        };
        
        const consentType = preferences.analytics && preferences.preferences ? 'accepted' : 
                           (!preferences.analytics && !preferences.preferences) ? 'essential' : 'custom';
        
        saveCookiePreferences(consentType, preferences);
        hideModal();
        hideBanner();
        applyCookiePreferences(preferences);
    });
    
    // Accept all from modal
    acceptAllModalBtn.addEventListener('click', function() {
        document.getElementById('analytics-cookies').checked = true;
        document.getElementById('preference-cookies').checked = true;
        
        const preferences = {
            essential: true,
            analytics: true,
            preferences: true
        };
        
        saveCookiePreferences('accepted', preferences);
        hideModal();
        hideBanner();
        enableAllCookies();
    });
    
    function showModal() {
        cookieModal.style.display = 'flex';
        cookieModal.style.opacity = '0';
        setTimeout(() => {
            cookieModal.style.transition = 'opacity 0.3s ease';
            cookieModal.style.opacity = '1';
        }, 10);
        
        // Load current preferences if they exist
        const savedPreferences = getCookie('cookie_preferences');
        if (savedPreferences) {
            const preferences = JSON.parse(savedPreferences);
            document.getElementById('analytics-cookies').checked = preferences.analytics;
            document.getElementById('preference-cookies').checked = preferences.preferences;
        }
    }
    
    function hideModal() {
        cookieModal.style.opacity = '0';
        setTimeout(() => {
            cookieModal.style.display = 'none';
        }, 300);
    }
    
    function hideBanner() {
        cookieBanner.style.opacity = '0';
        setTimeout(() => {
            cookieBanner.style.display = 'none';
        }, 500);
    }
    
    function saveCookiePreferences(consentType, preferences) {
        setCookie('cookie_consent', consentType, 365);
        setCookie('cookie_preferences', JSON.stringify(preferences), 365);
        setCookie('cookie_consent_date', new Date().toISOString(), 365);
    }
    
    function enableAllCookies() {
        console.log('All cookies enabled - you can initialize analytics, tracking, etc.');
        // Add your analytics initialization code here
        // Example: gtag('config', 'GA_MEASUREMENT_ID');
    }
    
    function enableEssentialCookies() {
        console.log('Only essential cookies enabled');
        // Disable analytics and other non-essential tracking
    }
    
    function applyCookiePreferences(preferences) {
        if (preferences.analytics) {
            console.log('Analytics cookies enabled');
            // Initialize analytics
        } else {
            console.log('Analytics cookies disabled');
            // Disable analytics
        }
        
        if (preferences.preferences) {
            console.log('Preference cookies enabled');
            // Enable preference storage
        } else {
            console.log('Preference cookies disabled');
            // Disable preference storage
        }
    }
    
    // Cookie utility functions
    function setCookie(name, value, days) {
        const expires = new Date();
        expires.setTime(expires.getTime() + (days * 24 * 60 * 60 * 1000));
        document.cookie = name + '=' + encodeURIComponent(value) + ';expires=' + expires.toUTCString() + ';path=/;SameSite=Strict';
    }
    
    function getCookie(name) {
        const nameEQ = name + "=";
        const ca = document.cookie.split(';');
        for(let i = 0; i < ca.length; i++) {
            let c = ca[i];
            while (c.charAt(0) == ' ') c = c.substring(1, c.length);
            if (c.indexOf(nameEQ) == 0) return decodeURIComponent(c.substring(nameEQ.length, c.length));
        }
        return null;
    }
    
    // Apply existing preferences on page load
    if (cookieConsent) {
        const savedPreferences = getCookie('cookie_preferences');
        if (savedPreferences) {
            const preferences = JSON.parse(savedPreferences);
            applyCookiePreferences(preferences);
        }
    }
});
</script>