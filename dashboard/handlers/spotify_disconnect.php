<?php
// dashboard/handlers/spotify_disconnect.php
require_once __DIR__ . '/../../include/init.php';

// Check Login
if (!$isLoggedIn) {
    header('Location: ' . BASE_URL . 'login.php');
    exit();
}

$userId = (int) $user['user_id'];
$lang = (function_exists('current_locale') && current_locale() === 'vi') ? 'vi' : 'en';

try {
    // Delete user token details
    $stmt = $pdo->prepare("DELETE FROM user_spotify WHERE user_id = ?");
    $stmt->execute([$userId]);
    
    // Clear Weekly Wrapped Cache so the next open will refresh without Spotify
    $clearCache = $pdo->prepare("DELETE FROM weekly_wrapped_cache WHERE user_id = ?");
    $clearCache->execute([$userId]);

    $msg = ($lang === 'vi') 
        ? 'Đã hủy liên kết tài khoản Spotify thành công!' 
        : 'Spotify account unlinked successfully!';
        
    header('Location: ' . BASE_URL . 'dashboard/dashboard-beats.php?success=' . urlencode($msg));
    exit();

} catch (PDOException $e) {
    $errMsg = ($lang === 'vi')
        ? 'Lỗi cơ sở dữ liệu khi hủy liên kết: ' . $e->getMessage()
        : 'Database error unlinking account: ' . $e->getMessage();
        
    header('Location: ' . BASE_URL . 'dashboard/dashboard-beats.php?error=' . urlencode($errMsg));
    exit();
}
?>
