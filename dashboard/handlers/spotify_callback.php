<?php
// dashboard/handlers/spotify_callback.php
require_once __DIR__ . '/../../include/init.php';

// Check Login
if (!$isLoggedIn) {
    header('Location: ' . BASE_URL . 'login.php');
    exit();
}

$userId = (int) $user['user_id'];

// Check state to prevent CSRF
$state = $_GET['state'] ?? '';
$savedState = $_SESSION['spotify_auth_state'] ?? '';
unset($_SESSION['spotify_auth_state']);

if (empty($state) || $state !== $savedState) {
    header('Location: ' . BASE_URL . 'dashboard/dashboard-progress.php?error=' . urlencode('State mismatch error (CSRF protection). Please try connecting again.'));
    exit();
}

// Check errors returned by Spotify
if (isset($_GET['error'])) {
    header('Location: ' . BASE_URL . 'dashboard/dashboard-progress.php?error=' . urlencode('Spotify authorization failed: ' . $_GET['error']));
    exit();
}

$code = $_GET['code'] ?? '';
if (empty($code)) {
    header('Location: ' . BASE_URL . 'dashboard/dashboard-progress.php?error=' . urlencode('Authorization code missing.'));
    exit();
}

// Build dynamic redirect URI (must match exactly with spotify_auth.php)
$protocol = (isset($_SERVER['HTTPS']) && ($_SERVER['HTTPS'] === 'on' || $_SERVER['HTTPS'] == 1)) 
          || (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https') ? 'https' : 'http';
$redirectUri = $protocol . '://' . $_SERVER['HTTP_HOST'] . BASE_URL . 'dashboard/handlers/spotify_callback.php';

// Request Tokens from Spotify
$tokenUrl = 'https://accounts.spotify.com/api/token';
$body = [
    'grant_type' => 'authorization_code',
    'code' => $code,
    'redirect_uri' => $redirectUri
];

$authHeader = 'Basic ' . base64_encode(SPOTIFY_CLIENT_ID . ':' . SPOTIFY_CLIENT_SECRET);

$ch = curl_init($tokenUrl);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($body));
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Authorization: ' . $authHeader,
    'Content-Type: application/x-www-form-urlencoded'
]);

// SSL configurations for local XAMPP & RMIT shared hosting compatibility
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);

$response = curl_exec($ch);

if (curl_errno($ch)) {
    header('Location: ' . BASE_URL . 'dashboard/dashboard-progress.php?error=' . urlencode('Connection error to Spotify: ' . curl_error($ch)));
    exit();
}

$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

$resData = json_decode($response, true);

if ($httpCode !== 200 || !isset($resData['access_token'])) {
    $errMessage = $resData['error_description'] ?? ($resData['error'] ?? 'Unknown token exchange error');
    header('Location: ' . BASE_URL . 'dashboard/dashboard-progress.php?error=' . urlencode('Token exchange failed: ' . $errMessage));
    exit();
}

// Token success! Parse and save
$accessToken = $resData['access_token'];
$refreshToken = $resData['refresh_token'];
$expiresIn = (int) $resData['expires_in'];
// Calculate expires_at timestamp
$expiresAt = date('Y-m-d H:i:s', time() + $expiresIn);

try {
    $stmt = $pdo->prepare(
        "INSERT INTO user_spotify (user_id, access_token, refresh_token, expires_at)
         VALUES (?, ?, ?, ?)
         ON DUPLICATE KEY UPDATE 
             access_token = VALUES(access_token),
             refresh_token = VALUES(refresh_token),
             expires_at = VALUES(expires_at)"
    );
    $stmt->execute([$userId, $accessToken, $refreshToken, $expiresAt]);
    
    // Clear Weekly Wrapped Cache so the next open will refresh with new Spotify insights!
    $clearCache = $pdo->prepare("DELETE FROM weekly_wrapped_cache WHERE user_id = ?");
    $clearCache->execute([$userId]);

    header('Location: ' . BASE_URL . 'dashboard/dashboard-progress.php?success=' . urlencode('Spotify linked successfully! Enjoy your Diet & Beats wrapped insights! 🎵'));
    exit();

} catch (PDOException $e) {
    header('Location: ' . BASE_URL . 'dashboard/dashboard-progress.php?error=' . urlencode('Database error saving tokens: ' . $e->getMessage()));
    exit();
}
