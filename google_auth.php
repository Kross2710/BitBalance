<?php
// google_auth.php
//
// Step 1 of "Sign in with Google": create a CSRF state token and redirect the
// browser to Google's consent screen. Runs for logged-out visitors, so it loads
// secrets.php itself (init.php only loads secrets when already signed in).

require_once __DIR__ . '/include/init.php';
require_once __DIR__ . '/include/secrets.php';
require_once __DIR__ . '/include/handlers/google_oauth.php';

// Already signed in? Nothing to do.
if (isset($_SESSION['user'])) {
    header('Location: ' . BASE_URL . 'dashboard/dashboard.php');
    exit();
}

if (!google_oauth_configured()) {
    header('Location: ' . BASE_URL . 'login.php?error=' . urlencode('Google sign-in is not configured yet.'));
    exit();
}

// CSRF state, verified in google_callback.php.
$state = bin2hex(random_bytes(16));
$_SESSION['google_auth_state'] = $state;

// Remember which page started the flow so we can show a relevant error there.
$_SESSION['google_auth_origin'] = (isset($_GET['from']) && $_GET['from'] === 'signup') ? 'signup' : 'login';

$authorizeUrl = 'https://accounts.google.com/o/oauth2/v2/auth?' . http_build_query([
    'client_id'     => GOOGLE_CLIENT_ID,
    'redirect_uri'  => google_oauth_redirect_uri(),
    'response_type' => 'code',
    'scope'         => 'openid email profile',
    'state'         => $state,
    'access_type'   => 'online',
    'prompt'        => 'select_account',
]);

header('Location: ' . $authorizeUrl);
exit();
