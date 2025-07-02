<?php
require_once __DIR__ . '/include/init.php';
require_once __DIR__ . '/include/handlers/log_attempt.php';
require_once __DIR__ . '/include/db_config.php';

if ($isLoggedIn) {
    // Log the user activity
    log_attempt($pdo, $user['user_id'], 'view', 'User ' . $user['user_id'] . ' viewed their profile', 'profile', null);
} else {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user']['user_id'];
$error_message = '';
$success_message = '';

// Get user data and themes
try {
    // Get user profile data
    $stmt = $pdo->prepare("
        SELECT u.*, us.theme_preference, us.profile_bio, us.status 
        FROM user u 
        JOIN userStatus us ON u.user_id = us.user_id 
        WHERE u.user_id = ?
    ");
    $stmt->execute([$user_id]);
    $profile = $stmt->fetch();
    
    // Get available themes - only light and dark
    $themes = [
        [
            'theme_id' => 1,
            'theme_name' => 'light',
            'theme_display_name' => 'Normal (Light)',
            'primary_color' => '#007bff',
            'secondary_color' => '#6c757d',
            'background_color' => '#ffffff',
            'text_color' => '#212529'
        ],
        [
            'theme_id' => 2,
            'theme_name' => 'dark',
            'theme_display_name' => 'Dark Mode',
            'primary_color' => '#0d6efd',
            'secondary_color' => '#adb5bd',
            'background_color' => '#212529',
            'text_color' => '#ffffff'
        ]
    ];
    
} catch (PDOException $e) {
    $error_message = "Error loading profile data.";
    error_log("Profile load error: " . $e->getMessage());
}

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    
    if (isset($_POST['update_info'])) {
        // Update basic profile information
        $first_name = trim($_POST['first_name']);
        $last_name = trim($_POST['last_name']);
        $email = trim($_POST['email']);
        $bio = trim($_POST['bio']);
        
        if (empty($first_name) || empty($last_name) || empty($email)) {
            $error_message = "Please fill in all required fields.";
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error_message = "Please enter a valid email address.";
        } else {
            try {
                // Check if email is taken by another user
                $stmt = $pdo->prepare("SELECT user_id FROM user WHERE email = ? AND user_id != ?");
                $stmt->execute([$email, $user_id]);
                if ($stmt->fetch()) {
                    $error_message = "This email is already taken by another user.";
                } else {
                    // Update user information
                    $stmt = $pdo->prepare("
                        UPDATE user 
                        SET first_name = ?, last_name = ?, email = ? 
                        WHERE user_id = ?
                    ");
                    $stmt->execute([$first_name, $last_name, $email, $user_id]);
                    
                    // Update bio in userStatus
                    $stmt = $pdo->prepare("UPDATE userStatus SET profile_bio = ? WHERE user_id = ?");
                    $stmt->execute([$bio, $user_id]);
                    
                    // Update session data
                    $_SESSION['user']['first_name'] = $first_name;
                    $_SESSION['user']['last_name'] = $last_name;
                    $_SESSION['user']['email'] = $email;
                    
                    $success_message = "Profile updated successfully!";
                    
                    // Refresh profile data
                    $stmt = $pdo->prepare("
                        SELECT u.*, us.theme_preference, us.profile_bio, us.status 
                        FROM user u 
                        JOIN userStatus us ON u.user_id = us.user_id 
                        WHERE u.user_id = ?
                    ");
                    $stmt->execute([$user_id]);
                    $profile = $stmt->fetch();
                }
            } catch (PDOException $e) {
                $error_message = "Error updating profile.";
                error_log("Profile update error: " . $e->getMessage());
            }
        }
    }
    
    elseif (isset($_POST['change_theme'])) {
        // Update theme preference
        $new_theme = $_POST['theme'];
        
        try {
            // Verify theme is valid (only light or dark)
            $valid_themes = ['light', 'dark'];
            if (in_array($new_theme, $valid_themes)) {
                $stmt = $pdo->prepare("UPDATE userStatus SET theme_preference = ? WHERE user_id = ?");
                $stmt->execute([$new_theme, $user_id]);
                
                // Update session immediately so theme applies across website
                $_SESSION['user']['theme_preference'] = $new_theme;
                
                // Redirect to refresh the page and apply theme immediately
                header("Location: profile.php?theme_updated=1");
                exit();
            } else {
                $error_message = "Invalid theme selected.";
            }
        } catch (PDOException $e) {
            $error_message = "Error updating theme.";
            error_log("Theme update error: " . $e->getMessage());
        }
    }
    
    elseif (isset($_POST['upload_image'])) {
        // Handle profile image upload
        if (isset($_FILES['profile_image']) && $_FILES['profile_image']['error'] == 0) {
            $allowed_types = ['image/jpeg', 'image/png', 'image/gif'];
            $max_size = 5 * 1024 * 1024; // 5MB
            
            $file_type = $_FILES['profile_image']['type'];
            $file_size = $_FILES['profile_image']['size'];
            
            if (!in_array($file_type, $allowed_types)) {
                $error_message = "Only JPEG, PNG, and GIF images are allowed.";
            } elseif ($file_size > $max_size) {
                $error_message = "Image size must be less than 5MB.";
            } else {
                $upload_dir = 'uploads/';
                
                $file_extension = pathinfo($_FILES['profile_image']['name'], PATHINFO_EXTENSION);
                $new_filename = 'profile_' . $user_id . '_' . time() . '.' . $file_extension;
                $upload_path = $upload_dir . $new_filename;
                
                if (move_uploaded_file($_FILES['profile_image']['tmp_name'], $upload_path)) {
                    // Delete old profile image if it exists
                    if ($profile['profile_image'] && file_exists($profile['profile_image'])) {
                        unlink($profile['profile_image']);
                    }
                    
                    // Update database
                    $stmt = $pdo->prepare("UPDATE user SET profile_image = ? WHERE user_id = ?");
                    $stmt->execute([$upload_path, $user_id]);
                    
                    // Update session
                    $_SESSION['user']['profile_image'] = $upload_path;
                    
                    $profile['profile_image'] = $upload_path;
                    $success_message = "Profile image updated successfully!";
                } else {
                    $error_message = "Server permissions error. Upload directory may not be writable.";
                }
            }
        } else {
            $error_message = "Please select an image file.";
        }
    }
    
    elseif (isset($_POST['change_password'])) {
        // Handle password change
        $current_password = $_POST['current_password'];
        $new_password = $_POST['new_password'];
        $confirm_new_password = $_POST['confirm_new_password'];
        
        if (empty($current_password) || empty($new_password) || empty($confirm_new_password)) {
            $error_message = "Please fill in all password fields.";
        } elseif ($new_password !== $confirm_new_password) {
            $error_message = "New passwords do not match.";
        } elseif (strlen($new_password) < 8) {
            $error_message = "New password must be at least 8 characters long.";
        } else {
            // Validate password strength
            $password_errors = [];
            if (!preg_match('/[A-Z]/', $new_password)) $password_errors[] = "uppercase letter";
            if (!preg_match('/[a-z]/', $new_password)) $password_errors[] = "lowercase letter";
            if (!preg_match('/[0-9]/', $new_password)) $password_errors[] = "number";
            if (!preg_match('/[^a-zA-Z0-9]/', $new_password)) $password_errors[] = "special character";
            
            if (!empty($password_errors)) {
                $error_message = "Password must contain at least one: " . implode(", ", $password_errors) . ".";
            } else {
                try {
                    // Get current user's password to verify
                    $stmt = $pdo->prepare("SELECT password FROM user WHERE user_id = ?");
                    $stmt->execute([$user_id]);
                    $current_user = $stmt->fetch();
                    
                    if (!$current_user || !password_verify($current_password, $current_user['password'])) {
                        $error_message = "Current password is incorrect.";
                    } else {
                        // Update password
                        $hashed_new_password = password_hash($new_password, PASSWORD_DEFAULT);
                        $stmt = $pdo->prepare("UPDATE user SET password = ? WHERE user_id = ?");
                        $stmt->execute([$hashed_new_password, $user_id]);
                        
                        // Reset any failed login attempts since password was changed
                        $stmt = $pdo->prepare("UPDATE userStatus SET failed_attempts = 0, locked_until = NULL WHERE user_id = ?");
                        $stmt->execute([$user_id]);
                        
                        $success_message = "Password updated successfully!";
                        
                        // Optional: Log this security event
                        error_log("Password changed for user_id: " . $user_id . " at " . date('Y-m-d H:i:s'));
                    }
                } catch (PDOException $e) {
                    $error_message = "Error updating password. Please try again.";
                    error_log("Password change error: " . $e->getMessage());
                }
            }
        }
    }
    
    elseif (isset($_POST['update_physical_stats'])) {
        // Handle physical stats update
        $age = !empty($_POST['age']) ? (int)$_POST['age'] : null;
        $gender = !empty($_POST['gender']) ? $_POST['gender'] : null;
        $weight = !empty($_POST['weight']) ? (float)$_POST['weight'] : null;
        $height = !empty($_POST['height']) ? (float)$_POST['height'] : null;
        
        try {
            // Check if user already has physical info
            $stmt = $pdo->prepare("SELECT userPhysicalStat_id FROM userPhysicalInfo WHERE user_id = ?");
            $stmt->execute([$user_id]);
            $existing = $stmt->fetch();
            
            if ($existing) {
                // Update existing record
                $stmt = $pdo->prepare("
                    UPDATE userPhysicalInfo 
                    SET age = ?, gender = ?, weight = ?, height = ? 
                    WHERE user_id = ?
                ");
                $stmt->execute([$age, $gender, $weight, $height, $user_id]);
            } else {
                // Insert new record
                $stmt = $pdo->prepare("
                    INSERT INTO userPhysicalInfo (userPhysicalStat_id, user_id, age, gender, weight, height) 
                    VALUES (?, ?, ?, ?, ?, ?)
                ");
                $stmt->execute([$user_id, $user_id, $age, $gender, $weight, $height]);
            }
            
            $success_message = "Physical stats updated successfully!";
            
            // Refresh physical info data
            $stmt = $pdo->prepare("SELECT * FROM userPhysicalInfo WHERE user_id = ?");
            $stmt->execute([$user_id]);
            $physical_info = $stmt->fetch();
            
        } catch (PDOException $e) {
            $error_message = "Error updating physical stats.";
            error_log("Physical stats update error: " . $e->getMessage());
        }
    }
    
    elseif (isset($_POST['archive_account'])) {
        // Archive user account
        $confirm_archive = $_POST['confirm_archive'] ?? '';
        
        if ($confirm_archive !== 'ARCHIVE') {
            $error_message = "Please type 'ARCHIVE' to confirm account archiving.";
        } else {
            try {
                $stmt = $pdo->prepare("
                    UPDATE userStatus 
                    SET status = 'archived', archived_at = NOW() 
                    WHERE user_id = ?
                ");
                $stmt->execute([$user_id]);
                
                // Clear session and redirect
                session_destroy();
                header("Location: index.php?archived=1");
                exit();
            } catch (PDOException $e) {
                $error_message = "Error archiving account.";
                error_log("Archive error: " . $e->getMessage());
            }
        }
    }
}

// Get current calorie goal
$calorie_goal = 'N/A'; // Default
try {
    $stmt = $pdo->prepare("SELECT calorie_goal FROM userGoal WHERE user_id = ?");
    $stmt->execute([$user_id]);
    $goal_data = $stmt->fetch();
    if ($goal_data) {
        $calorie_goal = $goal_data['calorie_goal'];
    }
} catch (PDOException $e) {
    // Use default goal if table doesn't exist yet
}

// Get user physical info
$physical_info = null;
try {
    $stmt = $pdo->prepare("SELECT * FROM userPhysicalInfo WHERE user_id = ?");
    $stmt->execute([$user_id]);
    $physical_info = $stmt->fetch();
} catch (PDOException $e) {
    // Physical info doesn't exist yet
}
?>

<!DOCTYPE html>
<html lang="en" data-theme="<?= htmlspecialchars($profile['theme_preference'] ?? 'light') ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Profile - BitBalance</title>
    <link rel="stylesheet" href="css/style.css">
    <link rel="stylesheet" href="css/themes/global.css">
    <link rel="stylesheet" href="css/themes/header.css">
    <link rel="stylesheet" href="css/themes/profile.css">
    <script src="https://kit.fontawesome.com/b94f65ead2.js" crossorigin="anonymous"></script>
    <style>
        /* Light Theme (Default) */
        :root {
            --bg-color: #f8f9fa;
            --card-bg: #ffffff;
            --text-color: #212529;
            --text-muted: #6c757d;
            --border-color: #e9ecef;
            --primary-color: #4a7ee3;
            --shadow: 0 4px 20px rgba(0,0,0,0.08);
        }
        
        /* Dark Theme */
        [data-theme="dark"] {
            --bg-color: #1a1a1a;
            --card-bg: #2d2d2d;
            --text-color: #ffffff;
            --text-muted: #adb5bd;
            --border-color: #495057;
            --primary-color: #0d6efd;
            --shadow: 0 4px 20px rgba(0,0,0,0.3);
        }
        
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: var(--bg-color);
            color: var(--text-color);
            margin: 0;
            padding: 0;
            transition: background-color 0.3s ease, color 0.3s ease;
        }
        
        .profile-container {
            display: flex;
            max-width: 1200px;
            margin: 40px auto;
            gap: 40px;
            padding: 0 20px;
        }
        
        .profile-sidebar {
            flex: 0 0 300px;
            background: var(--card-bg);
            border-radius: 20px;
            padding: 40px;
            box-shadow: var(--shadow);
            text-align: center;
            height: fit-content;
            border: 1px solid var(--border-color);
        }
        
        .profile-picture-section {
            margin-bottom: 30px;
        }
        
        .profile-picture {
            width: 150px;
            height: 150px;
            border-radius: 50%;
            border: 4px solid var(--border-color);
            object-fit: cover;
            margin-bottom: 20px;
        }
        
        .profile-placeholder {
            width: 150px;
            height: 150px;
            border-radius: 50%;
            background: var(--bg-color);
            border: 4px solid var(--border-color);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 60px;
            color: var(--text-muted);
            margin: 0 auto 20px;
        }
        
        .profile-name {
            font-size: 24px;
            font-weight: 600;
            color: var(--text-color);
            margin-bottom: 5px;
        }
        
        .profile-email {
            color: var(--text-muted);
            margin-bottom: 20px;
        }
        
        .logout-btn {
            background: #dc3545;
            color: white;
            border: none;
            padding: 12px 24px;
            border-radius: 8px;
            font-weight: 500;
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
            transition: background 0.2s ease;
            width: 100%;
            margin-bottom: 20px;
        }
        
        .logout-btn:hover {
            background: #c82333;
        }
        
        .sidebar-card {
            background: var(--bg-color);
            border: 2px solid var(--border-color);
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 20px;
            text-align: center;
        }
        
        .sidebar-card h3 {
            font-size: 16px;
            margin: 0 0 10px 0;
            color: var(--text-color);
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }
        
        .sidebar-card p {
            margin: 5px 0;
            color: var(--text-muted);
            font-size: 14px;
        }
        
        .sidebar-card .current-value {
            font-weight: 600;
            color: var(--primary-color);
            font-size: 18px;
        }
        
        .sidebar-btn {
            background: var(--primary-color);
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 6px;
            font-weight: 500;
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
            transition: background 0.2s ease;
            font-size: 14px;
            margin-top: 10px;
        }
        
        .sidebar-btn:hover {
            background: #3b6bd6;
        }
        
        .profile-main {
            flex: 1;
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 30px;
        }
        
        .profile-card {
            background: var(--card-bg);
            border-radius: 16px;
            padding: 30px;
            box-shadow: var(--shadow);
            transition: transform 0.2s ease, box-shadow 0.2s ease;
            border: 1px solid var(--border-color);
        }
        
        .profile-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 30px rgba(0,0,0,0.15);
        }
        
        .profile-card h2 {
            font-size: 20px;
            font-weight: 600;
            margin: 0 0 20px 0;
            color: var(--text-color);
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .card-icon {
            width: 40px;
            height: 40px;
            background: #e3f2fd;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #1976d2;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 500;
            color: var(--text-color);
        }
        
        .form-group input,
        .form-group textarea,
        .form-group select {
            width: 100%;
            padding: 12px 16px;
            border: 2px solid var(--border-color);
            border-radius: 8px;
            font-size: 14px;
            transition: border-color 0.2s ease;
            box-sizing: border-box;
            background: var(--card-bg);
            color: var(--text-color);
        }
        
        .form-group input:focus,
        .form-group textarea:focus,
        .form-group select:focus {
            outline: none;
            border-color: var(--primary-color);
        }
        
        .form-group textarea {
            resize: vertical;
            height: 80px;
        }
        
        .form-group small {
            color: var(--text-muted);
        }
        
        .btn-primary {
            background: var(--primary-color);
            color: white;
            border: none;
            padding: 12px 24px;
            border-radius: 8px;
            font-weight: 500;
            cursor: pointer;
            transition: background 0.2s ease;
            width: 100%;
        }
        
        .btn-primary:hover {
            background: #3b6bd6;
        }
        
        .btn-danger {
            background: #dc3545;
            color: white;
            border: none;
            padding: 12px 24px;
            border-radius: 8px;
            font-weight: 500;
            cursor: pointer;
            width: 100%;
            transition: background 0.2s ease;
        }
        
        .btn-danger:hover {
            background: #c82333;
        }
        
        .success-message {
            background: #d4edda;
            color: #155724;
            padding: 12px 16px;
            border-radius: 8px;
            margin-bottom: 20px;
            border: 1px solid #c3e6cb;
        }
        
        .error-message {
            background: #f8d7da;
            color: #721c24;
            padding: 12px 16px;
            border-radius: 8px;
            margin-bottom: 20px;
            border: 1px solid #f5c6cb;
        }
        
        [data-theme="dark"] .success-message {
            background: #1e4d2b;
            color: #a3d9a5;
            border-color: #2d5f34;
        }
        
        [data-theme="dark"] .error-message {
            background: #4a1e24;
            color: #f1aeb5;
            border-color: #5c2b30;
        }
        
        .theme-choice {
            border: 3px solid var(--border-color);
            border-radius: 12px;
            padding: 15px;
            cursor: pointer;
            transition: all 0.3s ease;
            text-align: center;
            background: var(--card-bg);
        }
        
        .theme-choice.selected {
            border-color: var(--primary-color);
            background: var(--bg-color);
        }
        
        .theme-label {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            font-weight: 500;
            color: var(--text-color);
        }
        
        .theme-choice.selected .theme-label {
            color: var(--primary-color);
        }
        
        .password-requirements {
            background: var(--bg-color);
            padding: 10px;
            border-radius: 6px;
            margin-bottom: 15px;
            border: 1px solid var(--border-color);
            display: none;
        }
        
        .physical-stats {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px;
            margin-bottom: 20px;
        }
        
        .stat-item {
            background: var(--bg-color);
            padding: 15px;
            border-radius: 8px;
            text-align: center;
            border: 1px solid var(--border-color);
        }
        
        .stat-label {
            font-size: 12px;
            color: var(--text-muted);
            margin-bottom: 5px;
        }
        
        .stat-value {
            font-size: 16px;
            font-weight: 600;
            color: var(--text-color);
        }
        
        .stat-value.not-set {
            color: var(--text-muted);
            font-style: italic;
        }
        
        @media (max-width: 768px) {
            .profile-container {
                flex-direction: column;
                margin: 20px auto;
            }
            
            .profile-sidebar {
                flex: none;
            }
            
            .profile-main {
                grid-template-columns: 1fr;
            }
            
            .physical-stats {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <?php 
    $activeHeader = 'profile';
    include 'views/header.php'; 
    ?>
    
    <div class="profile-container">
        <!-- Profile Sidebar -->
        <div class="profile-sidebar">
            <div class="profile-picture-section">
                <?php if (!empty($profile['profile_image']) && file_exists($profile['profile_image'])): ?>
                    <img src="<?= BASE_URL ?><?= htmlspecialchars($profile['profile_image']) ?>" 
                         alt="Profile Picture" class="profile-picture">
                <?php else: ?>
                    <div class="profile-placeholder">
                        <i class="fas fa-user"></i>
                    </div>
                <?php endif; ?>
                
                <div class="profile-name">
                    <?= htmlspecialchars($profile['first_name'] . ' ' . $profile['last_name']) ?>
                </div>
                <div class="profile-email">
                    <?= htmlspecialchars($profile['email']) ?>
                </div>
            </div>
            
            <a href="<?= BASE_URL ?>logout.php" class="logout-btn">
                <i class="fas fa-sign-out-alt"></i> Logout
            </a>
            
            <!-- Set Calorie Goal Card (moved to sidebar) -->
            <div class="sidebar-card">
                <h3>
                    <i class="fas fa-bullseye"></i>
                    Calorie Goal
                </h3>
                <div class="current-value"><?= $calorie_goal ?></div>
                <p>calories/day</p>
                <a href="dashboard/set-goal.php" class="sidebar-btn">
                    <i class="fas fa-edit"></i> Update Goal
                </a>
            </div>
        </div>
        
        <!-- Profile Main Content -->
        <div class="profile-main">
            <?php if (isset($_GET['theme_updated'])): ?>
                <div class="success-message" style="grid-column: 1 / -1;">
                    <i class="fas fa-check-circle"></i> Theme updated successfully!
                </div>
            <?php endif; ?>
            
            <?php if (!empty($error_message)): ?>
                <div class="error-message" style="grid-column: 1 / -1;">
                    <i class="fas fa-exclamation-triangle"></i> <?= htmlspecialchars($error_message) ?>
                </div>
            <?php endif; ?>
            
            <?php if (!empty($success_message)): ?>
                <div class="success-message" style="grid-column: 1 / -1;">
                    <i class="fas fa-check-circle"></i> <?= htmlspecialchars($success_message) ?>
                </div>
            <?php endif; ?>
            
            <!-- Update Info Card -->
            <div class="profile-card">
                <h2>
                    <div class="card-icon"><i class="fas fa-edit"></i></div>
                    Update Info
                </h2>
                <form method="POST">
                    <div class="form-group">
                        <label>First Name</label>
                        <input type="text" name="first_name" value="<?= htmlspecialchars($profile['first_name']) ?>" required>
                    </div>
                    <div class="form-group">
                        <label>Last Name</label>
                        <input type="text" name="last_name" value="<?= htmlspecialchars($profile['last_name']) ?>" required>
                    </div>
                    <div class="form-group">
                        <label>Email</label>
                        <input type="email" name="email" value="<?= htmlspecialchars($profile['email']) ?>" required>
                    </div>
                    <div class="form-group">
                        <label>Bio</label>
                        <textarea name="bio" placeholder="Tell us about yourself..."><?= htmlspecialchars($profile['profile_bio'] ?? '') ?></textarea>
                    </div>
                    <button type="submit" name="update_info" class="btn-primary">
                        Update Info
                    </button>
                </form>
            </div>
            
            <!-- Physical Stats Card (new) -->
            <div class="profile-card">
                <h2>
                    <div class="card-icon"><i class="fas fa-user-cog"></i></div>
                    Physical Stats
                </h2>
                <form method="POST">
                    <div class="form-group">
                        <label>Age:</label>
                        <input type="number" name="age" value="<?= htmlspecialchars((int)$physical_info['age'] ?? '') ?>" placeholder="Enter your age" min="1" max="120">
                    </div>
                    <div class="form-group">
                        <label>Gender:</label>
                        <select name="gender">
                            <option value="">Select...</option>
                            <option value="male" <?= ($physical_info['gender'] ?? '') === 'male' ? 'selected' : '' ?>>Male</option>
                            <option value="female" <?= ($physical_info['gender'] ?? '') === 'female' ? 'selected' : '' ?>>Female</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Weight (kg):</label>
                        <input type="number" name="weight" value="<?= htmlspecialchars((int)$physical_info['weight'] ?? '') ?>" placeholder="0" step="0.1" min="1" max="500">
                    </div>
                    <div class="form-group">
                        <label>Height (cm):</label>
                        <input type="number" name="height" value="<?= htmlspecialchars((int)$physical_info['height'] ?? '') ?>" placeholder="0" step="0.1" min="50" max="300">
                    </div>
                    <button type="submit" name="update_physical_stats" class="btn-primary">
                        <i class="fas fa-save"></i> Update Physical Stats
                    </button>
                </form>
            </div>
            
            <!-- Choose Theme Card -->
            <div class="profile-card">
                <h2>
                    <div class="card-icon"><i class="fas fa-palette"></i></div>
                    Choose Theme
                </h2>
                <form method="POST">
                    <div class="theme-selector">
                        <!-- Light Theme Option -->
                        <div class="theme-choice <?= ($profile['theme_preference'] === 'light') ? 'selected' : '' ?>" 
                             onclick="selectTheme('light')">
                            <div class="theme-preview light-preview">
                                <div class="preview-header"></div>
                                <div class="preview-content"></div>
                            </div>
                            <div class="theme-label">
                                <i class="fas fa-sun"></i>
                                <span>Light Mode</span>
                            </div>
                        </div>
                        
                        <!-- Dark Theme Option -->
                        <div class="theme-choice <?= ($profile['theme_preference'] === 'dark') ? 'selected' : '' ?>" 
                             onclick="selectTheme('dark')">
                            <div class="theme-preview dark-preview">
                                <div class="preview-header"></div>
                                <div class="preview-content"></div>
                            </div>
                            <div class="theme-label">
                                <i class="fas fa-moon"></i>
                                <span>Dark Mode</span>
                            </div>
                        </div>
                    </div>
                    
                    <input type="hidden" name="theme" id="selectedTheme" value="<?= htmlspecialchars($profile['theme_preference']) ?>">
                    <button type="submit" name="change_theme" class="btn-primary">
                        Apply Theme
                    </button>
                </form>
            </div>
            
            <!-- Upload Image Card -->
            <div class="profile-card">
                <h2>
                    <div class="card-icon"><i class="fas fa-camera"></i></div>
                    Upload Image
                </h2>
                <form method="POST" enctype="multipart/form-data">
                    <div class="form-group">
                        <label>Profile Picture</label>
                        <input type="file" name="profile_image" accept="image/*" required>
            
                    </div>
                    <button type="submit" name="upload_image" class="btn-primary">
                        Upload Image
                    </button>
                </form>
            </div>
            
            <!-- Change Password Card -->
            <div class="profile-card">
                <h2>
                    <div class="card-icon" style="background: #e3f2fd; color: #1976d2;">
                        <i class="fas fa-key"></i>
                    </div>
                    Change Password
                </h2>
                <form method="POST">
                    <div class="form-group">
                        <label>Current Password</label>
                        <input type="password" name="current_password" required placeholder="Enter your current password">
                    </div>
                    <div class="form-group">
                        <label>New Password</label>
                        <input type="password" name="new_password" required minlength="8" placeholder="Enter new password" onkeyup="checkPasswordRequirements(this.value)">
                        <small style="color: var(--text-muted); font-size: 12px;">
                            Must be at least 8 characters with uppercase, lowercase, number, and special character
                        </small>
                    </div>
                    <div class="form-group">
                        <label>Confirm New Password</label>
                        <input type="password" name="confirm_new_password" required minlength="8" placeholder="Confirm new password">
                    </div>
                    
                    <!-- Real-time password requirements indicator -->
                    <div class="password-requirements" id="password-requirements">
                        <div style="font-size: 12px; font-weight: 500; margin-bottom: 5px; color: var(--text-color);">Password Requirements:</div>
                        <div style="font-size: 11px; color: var(--text-muted);">
                            <span id="length-req">✗ At least 8 characters</span><br>
                            <span id="upper-req">✗ One uppercase letter</span><br>
                            <span id="lower-req">✗ One lowercase letter</span><br>
                            <span id="number-req">✗ One number</span><br>
                            <span id="special-req">✗ One special character</span>
                        </div>
                    </div>
                    
                    <button type="submit" name="change_password" class="btn-primary">
                        <i class="fas fa-shield-alt"></i> Update Password
                    </button>
                </form>
            </div>
            
            <!-- Archive Account Card -->
            <div class="profile-card archive-card">
                <h2>
                    <div class="card-icon" style="background: #fff5f5; color: #dc3545;">
                        <i class="fas fa-archive"></i>
                    </div>
                    Archive Account
                </h2>
                <p class="danger-text">
                    Take this action to archive your account and all associated data to preserve access in the future.
                </p>
                <form method="POST" onsubmit="return confirmArchive()">
                    <div class="form-group">
                        <label>Type "ARCHIVE" to confirm</label>
                        <input type="text" name="confirm_archive" placeholder="Type ARCHIVE to confirm" required>
                    </div>
                    <button type="submit" name="archive_account" class="btn-danger">
                        Archive Account
                    </button>
                </form>
            </div>
        </div>
    </div>
    
    <script>
        function selectTheme(themeName) {
            // Update hidden input value
            document.getElementById('selectedTheme').value = themeName;
            
            // Remove selected class from all theme choices
            document.querySelectorAll('.theme-choice').forEach(choice => {
                choice.classList.remove('selected');
            });
            
            // Add selected class to clicked theme
            event.target.closest('.theme-choice').classList.add('selected');
            
            console.log('Selected theme:', themeName); 
        }
        
        function confirmArchive() {
            return confirm('Are you absolutely sure you want to archive your account? This action cannot be undone.');
        }
        
        function checkPasswordRequirements(password) {
            const requirements = document.getElementById('password-requirements');
            
            if (password.length > 0) {
                requirements.style.display = 'block';
                
                // Check each requirement
                document.getElementById('length-req').innerHTML = password.length >= 8 ? '✓ At least 8 characters' : '✗ At least 8 characters';
                document.getElementById('length-req').style.color = password.length >= 8 ? 'green' : 'red';
                
                document.getElementById('upper-req').innerHTML = /[A-Z]/.test(password) ? '✓ One uppercase letter' : '✗ One uppercase letter';
                document.getElementById('upper-req').style.color = /[A-Z]/.test(password) ? 'green' : 'red';
                
                document.getElementById('lower-req').innerHTML = /[a-z]/.test(password) ? '✓ One lowercase letter' : '✗ One lowercase letter';
                document.getElementById('lower-req').style.color = /[a-z]/.test(password) ? 'green' : 'red';
                
                document.getElementById('number-req').innerHTML = /[0-9]/.test(password) ? '✓ One number' : '✗ One number';
                document.getElementById('number-req').style.color = /[0-9]/.test(password) ? 'green' : 'red';
                
                document.getElementById('special-req').innerHTML = /[^a-zA-Z0-9]/.test(password) ? '✓ One special character' : '✗ One special character';
                document.getElementById('special-req').style.color = /[^a-zA-Z0-9]/.test(password) ? 'green' : 'red';
            } else {
                requirements.style.display = 'none';
            }
        }
    </script>
</body>
</html>