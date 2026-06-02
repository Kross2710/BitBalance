<?php
// google_callback.php
//
// Step 2 of "Sign in with Google": Google redirects here with ?code & ?state.
// We verify state, exchange the code for an access token, read the user's
// profile, then find-or-create the BitBalance account and start a session.
// Runs logged-out, so it loads db_config.php + secrets.php itself.

require_once __DIR__ . '/include/init.php';
require_once __DIR__ . '/include/db_config.php';      // defines $pdo
require_once __DIR__ . '/include/secrets.php';
require_once __DIR__ . '/include/handlers/google_oauth.php';
require_once __DIR__ . '/include/handlers/log_attempt.php';

// Where to send the user on failure (back to whichever page started the flow).
$origin = $_SESSION['google_auth_origin'] ?? 'login';
unset($_SESSION['google_auth_origin']);
$errorReturn = BASE_URL . ($origin === 'signup' ? 'signup.php' : 'login.php');

$fail = function (string $message) use ($errorReturn) {
    header('Location: ' . $errorReturn . '?error=' . urlencode($message));
    exit();
};

// Already signed in elsewhere? Just go to the dashboard.
if (isset($_SESSION['user'])) {
    header('Location: ' . BASE_URL . 'dashboard/dashboard.php');
    exit();
}

if (!google_oauth_configured()) {
    $fail('Google sign-in is not configured yet.');
}

// --- CSRF state check -------------------------------------------------------
$state      = $_GET['state'] ?? '';
$savedState = $_SESSION['google_auth_state'] ?? '';
unset($_SESSION['google_auth_state']);
if ($state === '' || !hash_equals($savedState, $state)) {
    $fail('Sign-in session expired. Please try again.');
}

// --- Errors handed back by Google -------------------------------------------
if (isset($_GET['error'])) {
    $fail('Google sign-in was cancelled.');
}

$code = $_GET['code'] ?? '';
if ($code === '') {
    $fail('Authorization code missing. Please try again.');
}

$redirectUri = google_oauth_redirect_uri();

// --- Exchange the authorization code for an access token --------------------
$ch = curl_init('https://oauth2.googleapis.com/token');
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST           => true,
    CURLOPT_POSTFIELDS     => http_build_query([
        'code'          => $code,
        'client_id'     => GOOGLE_CLIENT_ID,
        'client_secret' => GOOGLE_CLIENT_SECRET,
        'redirect_uri'  => $redirectUri,
        'grant_type'    => 'authorization_code',
    ]),
    CURLOPT_HTTPHEADER     => ['Content-Type: application/x-www-form-urlencoded'],
    // Matches the Spotify integration: RMIT shared hosting lacks an up-to-date
    // CA bundle, so peer verification is disabled for outbound API calls.
    CURLOPT_SSL_VERIFYPEER => false,
    CURLOPT_SSL_VERIFYHOST => false,
]);
$response = curl_exec($ch);
if (curl_errno($ch)) {
    $err = curl_error($ch);
    curl_close($ch);
    $fail('Could not reach Google: ' . $err);
}
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

$token = json_decode($response, true);
if ($httpCode !== 200 || empty($token['access_token'])) {
    $detail = $token['error_description'] ?? ($token['error'] ?? 'token exchange failed');
    $fail('Google sign-in failed: ' . $detail);
}

// --- Read the user's profile from the userinfo endpoint ---------------------
$ch = curl_init('https://openidconnect.googleapis.com/v1/userinfo');
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTPHEADER     => ['Authorization: Bearer ' . $token['access_token']],
    CURLOPT_SSL_VERIFYPEER => false,
    CURLOPT_SSL_VERIFYHOST => false,
]);
$profileRaw = curl_exec($ch);
if (curl_errno($ch)) {
    $err = curl_error($ch);
    curl_close($ch);
    $fail('Could not read your Google profile: ' . $err);
}
$profileHttp = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

$profile = json_decode($profileRaw, true);
if ($profileHttp !== 200 || !is_array($profile) || empty($profile['sub']) || empty($profile['email'])) {
    $fail('Google did not return your account details. Please try again.');
}

// Google must have verified the email before we trust it for account linking.
$verified = $profile['email_verified'] ?? false;
if ($verified !== true && $verified !== 'true') {
    $fail('Your Google email is not verified, so we cannot sign you in.');
}

// Derive first/last name. Fall back to splitting the display name, then email.
$first = trim($profile['given_name'] ?? '');
$last  = trim($profile['family_name'] ?? '');
if ($first === '') {
    $name = trim($profile['name'] ?? '');
    if ($name !== '') {
        $parts = preg_split('/\s+/', $name);
        $first = $parts[0];
        if ($last === '' && count($parts) > 1) {
            $last = implode(' ', array_slice($parts, 1));
        }
    } else {
        $first = strstr($profile['email'], '@', true) ?: 'Friend';
    }
}

$g = [
    'sub'     => (string) $profile['sub'],
    'email'   => (string) $profile['email'],
    'first'   => $first,
    'last'    => $last,
    'picture' => (string) ($profile['picture'] ?? ''),
];

// --- Find or create, then start the session ---------------------------------
try {
    $result = google_find_or_create($pdo, $g);
} catch (Throwable $e) {
    error_log('Google login find_or_create: ' . $e->getMessage());
    $fail('Something went wrong creating your account. Please try again.');
}

$sessionUser = google_build_session_user($pdo, $result['user_id']);
if ($sessionUser === null) {
    $fail('This account is not available. Please contact support.');
}

// Prevent session fixation: rotate the id now that we are authenticated.
session_regenerate_id(true);

if ($result['is_new']) {
    // Mirrors user_signup.php so the dashboard greets new users with "Welcome"
    // and the goal wizard kicks in.
    $sessionUser['is_new_signup'] = true;
}
$_SESSION['user'] = $sessionUser;

// Record the login (last_login + audit trail), mirroring user_login.php.
$pdo->prepare("UPDATE user SET last_login = NOW() WHERE user_id = ?")->execute([$result['user_id']]);
log_attempt($pdo, $result['user_id'], 'login', 'User logged in with Google');

// New accounts go through the personalized plan wizard; everyone else lands
// on their dashboard.
$destination = $result['is_new'] ? 'dashboard/set-goal.php' : 'dashboard/dashboard.php';
header('Location: ' . BASE_URL . $destination);
exit();
