<!-- Cookie Consent Banner -->
<div id="cookie-banner" class="cookie-banner" style="display: none;">
    <div class="cookie-content">
        <div class="cookie-text">
            <div class="cookie-icon" aria-hidden="true">
                <svg viewBox="0 0 48 48" focusable="false">
                    <circle cx="24" cy="24" r="18" fill="currentColor" opacity="0.18"></circle>
                    <circle cx="24" cy="24" r="15" fill="none" stroke="currentColor" stroke-width="4"></circle>
                    <circle cx="18" cy="18" r="2.5" fill="currentColor"></circle>
                    <circle cx="29" cy="17" r="2" fill="currentColor"></circle>
                    <circle cx="30" cy="30" r="2.5" fill="currentColor"></circle>
                    <circle cx="19" cy="31" r="2" fill="currentColor"></circle>
                </svg>
            </div>
            <div class="cookie-message">
                <h4><?= t('cookie.title') ?></h4>
                <p><?= t('cookie.body') ?>
                <a href="terms.php" target="_blank"><?= t('footer.terms') ?></a>.</p>
            </div>
        </div>
        <div class="cookie-buttons">
            <button id="manage-cookies" class="cookie-btn manage"><?= t('cookie.customize') ?></button>
            <button id="accept-essential" class="cookie-btn essential"><?= t('cookie.essential_only') ?></button>
            <button id="accept-all" class="cookie-btn accept"><?= t('cookie.accept_all') ?></button>
        </div>
    </div>
</div>

<!-- Cookie Settings Modal -->
<div id="cookie-modal" class="cookie-modal" style="display: none;">
    <div class="cookie-modal-content">
        <div class="cookie-modal-header">
            <h3><?= t('cookie.settings_title') ?></h3>
            <button id="close-modal" class="close-modal" aria-label="<?= t('common.close') ?>">&times;</button>
        </div>
        <div class="cookie-modal-body">
            <p><?= t('cookie.body') ?></p>

            <div class="cookie-category">
                <div class="cookie-category-header">
                    <input type="checkbox" id="essential-cookies" checked disabled>
                    <label for="essential-cookies">
                        <strong><?= t('cookie.essential.title') ?></strong>
                        <span class="required">(<?= t('common.required') ?>)</span>
                    </label>
                </div>
                <p class="cookie-description"><?= t('cookie.essential.desc') ?></p>
            </div>

            <div class="cookie-category">
                <div class="cookie-category-header">
                    <input type="checkbox" id="analytics-cookies">
                    <label for="analytics-cookies">
                        <strong><?= t('cookie.analytics.title') ?></strong>
                    </label>
                </div>
                <p class="cookie-description"><?= t('cookie.analytics.desc') ?></p>
            </div>

            <div class="cookie-category">
                <div class="cookie-category-header">
                    <input type="checkbox" id="preference-cookies">
                    <label for="preference-cookies">
                        <strong><?= t('cookie.marketing.title') ?></strong>
                    </label>
                </div>
                <p class="cookie-description"><?= t('cookie.marketing.desc') ?></p>
            </div>

            <div class="terms-link">
                <p><?= t('cookie.learn_more') ?>:
                <a href="terms.php" target="_blank"><?= t('footer.terms') ?></a>.</p>
            </div>
        </div>
        <div class="cookie-modal-footer">
            <button id="save-preferences" class="cookie-btn accept"><?= t('cookie.save_preferences') ?></button>
            <button id="accept-all-modal" class="cookie-btn accept"><?= t('cookie.accept_all') ?></button>
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
    if (manageCookiesBtn) {
        manageCookiesBtn.addEventListener('click', function() {
            showModal();
        });
    }

    // Connect footer settings link
    const footerCookieSettings = document.getElementById('footer-cookie-settings');
    if (footerCookieSettings) {
        footerCookieSettings.addEventListener('click', function(e) {
            e.preventDefault();
            showModal();
        });
    }
    
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
        // Add your analytics initialization code here
        // Example: gtag('config', 'GA_MEASUREMENT_ID');
    }
    
    function enableEssentialCookies() {
        // Disable analytics and other non-essential tracking
    }
    
    function applyCookiePreferences(preferences) {
        if (preferences.analytics) {
            // Initialize analytics
        } else {
            // Disable analytics
        }
        
        if (preferences.preferences) {
            // Enable preference storage
        } else {
            // Disable preference storage
        }
    }
    
    // Cookie utility functions with Secure & SameSite settings
    function setCookie(name, value, days) {
        const expires = new Date();
        expires.setTime(expires.getTime() + (days * 24 * 60 * 60 * 1000));
        let cookieString = name + '=' + encodeURIComponent(value) + ';expires=' + expires.toUTCString() + ';path=/;SameSite=Strict';
        if (location.protocol === 'https:') {
            cookieString += ';Secure';
        }
        document.cookie = cookieString;
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
