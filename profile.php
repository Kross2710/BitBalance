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
    <title>Profile Settings | BitBalance</title>
    
    <?php
    $pageCss = ['css/pages/profile.css'];
    include PROJECT_ROOT . 'views/head_css.php';
    ?>
    <script src="https://kit.fontawesome.com/b94f65ead2.js" crossorigin="anonymous"></script>
</head>

<body>
    <?php 
    $activeHeader = 'profile';
    include 'views/header.php'; 
    ?>

    <div class="profile-wrapper">
        
        <aside class="profile-sidebar">
            <div class="avatar-container">
                <?php if (!empty($profile['profile_image']) && file_exists($profile['profile_image'])): ?>
                    <img src="<?= BASE_URL ?><?= htmlspecialchars($profile['profile_image']) ?>" class="profile-avatar">
                <?php else: ?>
                    <div class="avatar-placeholder"><i class="fas fa-user"></i></div>
                <?php endif; ?>
            </div>
            
            <h2 class="profile-name"><?= htmlspecialchars($profile['first_name'] . ' ' . $profile['last_name']) ?></h2>
            <p class="profile-email"><?= htmlspecialchars($profile['email']) ?></p>

            <nav class="sidebar-menu">
                <a href="#basic-info" class="menu-link"><i class="fas fa-user-edit"></i> Personal Info</a>
                <a href="#physical-stats" class="menu-link"><i class="fas fa-child"></i> Body Metrics</a>
                <a href="#appearance" class="menu-link"><i class="fas fa-paint-brush"></i> Appearance</a>
                <a href="#security" class="menu-link"><i class="fas fa-shield-alt"></i> Security</a>
            </nav>

            <a href="<?= BASE_URL ?>logout.php" class="btn-logout">
                <i class="fas fa-sign-out-alt"></i> Sign Out
            </a>
        </aside>

        <main class="profile-content">
            
            <?php if (isset($_GET['theme_updated'])): ?>
                <div class="alert success"><i class="fas fa-check-circle"></i> Theme updated!</div>
            <?php endif; ?>
            <?php if (!empty($success_message)): ?>
                <div class="alert success"><i class="fas fa-check-circle"></i> <?= htmlspecialchars($success_message) ?></div>
            <?php endif; ?>
            <?php if (!empty($error_message)): ?>
                <div class="alert error"><i class="fas fa-exclamation-triangle"></i> <?= htmlspecialchars($error_message) ?></div>
            <?php endif; ?>

            <section id="basic-info" class="settings-card">
                <div class="card-header">
                    <div class="header-icon icon-blue"><i class="fas fa-user"></i></div>
                    <div>
                        <h2>Basic Information</h2>
                        <p>Update your personal details and bio.</p>
                    </div>
                </div>
                <form method="POST">
                    <div class="form-grid">
                        <div class="form-group">
                            <label>First Name</label>
                            <input type="text" name="first_name" value="<?= htmlspecialchars($profile['first_name']) ?>" required>
                        </div>
                        <div class="form-group">
                            <label>Last Name</label>
                            <input type="text" name="last_name" value="<?= htmlspecialchars($profile['last_name']) ?>" required>
                        </div>
                        <div class="form-group full-width">
                            <label>Email Address</label>
                            <input type="email" name="email" value="<?= htmlspecialchars($profile['email']) ?>" required>
                        </div>
                        <div class="form-group full-width">
                            <label>Bio</label>
                            <textarea name="bio" placeholder="Share a little about yourself..."><?= htmlspecialchars($profile['profile_bio'] ?? '') ?></textarea>
                        </div>
                    </div>
                    <button type="submit" name="update_info" class="btn-save">Save Changes</button>
                </form>
            </section>

            <section id="physical-stats" class="settings-card">
                <div class="card-header">
                    <div class="header-icon icon-green"><i class="fas fa-ruler-combined"></i></div>
                    <div>
                        <h2>Body Metrics</h2>
                        <p>Used to calculate your daily calorie goals.</p>
                    </div>
                </div>
                <form method="POST">
                    <div class="form-grid">
                        <div class="form-group">
                            <label>Age</label>
                            <input type="number" name="age" value="<?= htmlspecialchars((int)$physical_info['age'] ?? '') ?>" placeholder="Years">
                        </div>
                        <div class="form-group">
                            <label>Gender</label>
                            <select name="gender">
                                <option value="">Select...</option>
                                <option value="male" <?= ($physical_info['gender'] ?? '') === 'male' ? 'selected' : '' ?>>Male</option>
                                <option value="female" <?= ($physical_info['gender'] ?? '') === 'female' ? 'selected' : '' ?>>Female</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Weight (kg)</label>
                            <input type="number" name="weight" value="<?= htmlspecialchars((int)$physical_info['weight'] ?? '') ?>" placeholder="kg">
                        </div>
                        <div class="form-group">
                            <label>Height (cm)</label>
                            <input type="number" name="height" value="<?= htmlspecialchars((int)$physical_info['height'] ?? '') ?>" placeholder="cm">
                        </div>
                    </div>
                    <button type="submit" name="update_physical_stats" class="btn-save">Update Stats</button>
                </form>
            </section>

            <section id="appearance" class="settings-card">
                <div class="card-header">
                    <div class="header-icon icon-purple"><i class="fas fa-moon"></i></div>
                    <div>
                        <h2>Appearance</h2>
                        <p>Customize how BitBalance looks for you.</p>
                    </div>
                </div>
                <form method="POST">
                    <div class="theme-options">
                        <div class="theme-option <?= ($profile['theme_preference'] === 'light') ? 'active' : '' ?>" onclick="selectTheme('light')">
                            <i class="fas fa-sun"></i>
                            <div>Light Mode</div>
                        </div>
                        <div class="theme-option <?= ($profile['theme_preference'] === 'dark') ? 'active' : '' ?>" onclick="selectTheme('dark')">
                            <i class="fas fa-moon"></i>
                            <div>Dark Mode</div>
                        </div>
                    </div>
                    <input type="hidden" name="theme" id="selectedTheme" value="<?= htmlspecialchars($profile['theme_preference']) ?>">
                    <button type="submit" name="change_theme" class="btn-save" style="margin-top: 20px;">Apply Theme</button>
                </form>
            </section>

            <section class="settings-card">
                <div class="card-header">
                    <div class="header-icon icon-orange"><i class="fas fa-camera"></i></div>
                    <div>
                        <h2>Profile Picture</h2>
                        <p>Personalize your account with a photo.</p>
                    </div>
                </div>
                <form method="POST" enctype="multipart/form-data" style="display: flex; gap: 10px; align-items: center;">
                    <input type="file" name="profile_image" accept="image/*" required style="flex: 1;">
                    <button type="submit" name="upload_image" class="btn-save">Upload</button>
                </form>
            </section>

            <section id="security" class="settings-card danger-zone">
                <div class="card-header" style="border-bottom-color: rgba(220,53,69,0.2);">
                    <div class="header-icon icon-red"><i class="fas fa-shield-alt"></i></div>
                    <div>
                        <h2>Security Zone</h2>
                        <p>Manage password and account status.</p>
                    </div>
                </div>
                
                <form method="POST" style="margin-bottom: 30px;">
                    <h4 style="margin-bottom: 15px;">Change Password</h4>
                    <div class="form-grid">
                        <div class="form-group full-width">
                            <label>Current Password</label>
                            <input type="password" name="current_password" placeholder="********">
                        </div>
                        <div class="form-group">
                            <label>New Password</label>
                            <input type="password" name="new_password" placeholder="********">
                        </div>
                        <div class="form-group">
                            <label>Confirm Password</label>
                            <input type="password" name="confirm_new_password" placeholder="********">
                        </div>
                    </div>
                    <button type="submit" name="change_password" class="btn-save">Update Password</button>
                </form>

                <div style="border-top: 1px solid rgba(0,0,0,0.05); padding-top: 20px;">
                    <h4 style="color: #dc3545; margin-bottom: 10px;">Archive Account</h4>
                    <p style="font-size: 0.9rem; color: var(--text-secondary); margin-bottom: 15px;">
                        This will archive your account. Type <strong>ARCHIVE</strong> to confirm.
                    </p>
                    <form method="POST" onsubmit="return confirm('Are you sure?');" style="display: flex; gap: 10px;">
                        <input type="text" name="confirm_archive" placeholder="Type ARCHIVE" required style="max-width: 200px;">
                        <button type="submit" name="archive_account" class="btn-danger">Archive Account</button>
                    </form>
                </div>
            </section>

        </main>
    </div>

    <script>
        function selectTheme(theme) {
            document.getElementById('selectedTheme').value = theme;
            document.querySelectorAll('.theme-option').forEach(el => el.classList.remove('active'));
            event.currentTarget.classList.add('active');
        }
    </script>
</body>
</html>