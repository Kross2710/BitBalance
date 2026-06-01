<?php
// dashboard/handlers/spotify_auth.php
require_once __DIR__ . '/../../include/init.php';

// Check Login
if (!$isLoggedIn) {
    header('Location: ' . BASE_URL . 'login.php');
    exit();
}

// Check Credentials
if (!defined('SPOTIFY_CLIENT_ID') || !defined('SPOTIFY_CLIENT_SECRET') || SPOTIFY_CLIENT_ID === '' || SPOTIFY_CLIENT_SECRET === '') {
    header('Location: ' . BASE_URL . 'dashboard/dashboard-progress.php?error=' . urlencode('Spotify API credentials are not configured in include/secrets.php. Please define SPOTIFY_CLIENT_ID and SPOTIFY_CLIENT_SECRET.'));
    exit();
}

// Generate state for CSRF protection
$state = bin2hex(random_bytes(16));
$_SESSION['spotify_auth_state'] = $state;

// Build dynamic redirect URI
$protocol = (isset($_SERVER['HTTPS']) && ($_SERVER['HTTPS'] === 'on' || $_SERVER['HTTPS'] == 1)) 
          || (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https') ? 'https' : 'http';
$redirectUri = $protocol . '://' . $_SERVER['HTTP_HOST'] . BASE_URL . 'dashboard/handlers/spotify_callback.php';

// Spotify Scopes
// - user-read-recently-played: live DJ mixer + recent tracks list
// - user-top-read: long-term genre/popularity signal for "The Mirror" music fingerprint.
//   Older connections granted only the first scope still work — beats_mirror.php falls
//   back to enriching recently-played artists via the public /v1/artists catalog endpoint.
$scope = 'user-read-recently-played user-top-read';

// Redirect to Spotify Auth
$authorizeUrl = 'https://accounts.spotify.com/authorize?' . http_build_query([
    'client_id' => SPOTIFY_CLIENT_ID,
    'response_type' => 'code',
    'redirect_uri' => $redirectUri,
    'scope' => $scope,
    'state' => $state,
    'show_dialog' => 'true'
]);

header('Location: ' . $authorizeUrl);
exit();
