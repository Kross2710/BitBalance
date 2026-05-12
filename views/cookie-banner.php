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