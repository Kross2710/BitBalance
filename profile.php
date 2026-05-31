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
        $username = trim($_POST['username'] ?? '');
        $email = trim($_POST['email']);
        $bio = trim($_POST['bio']);

        if (empty($first_name) || empty($last_name) || empty($username) || empty($email)) {
            $error_message = "Please fill in all required fields.";
        } elseif (!preg_match('/^[A-Za-z0-9_.#\-]{3,30}$/', $username)) {
            $error_message = "Username must be 3–30 characters: letters, numbers, and . # - _";
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
                    // Check if username is taken by another user (collation is _ci → case-insensitive)
                    $stmt = $pdo->prepare("SELECT user_id FROM user WHERE user_name = ? AND user_id != ?");
                    $stmt->execute([$username, $user_id]);
                    if ($stmt->fetch()) {
                        $error_message = "This username is already taken.";
                    } else {
                        // Update user information
                        $stmt = $pdo->prepare("
                            UPDATE user
                            SET first_name = ?, last_name = ?, user_name = ?, email = ?
                            WHERE user_id = ?
                        ");
                        $stmt->execute([$first_name, $last_name, $username, $email, $user_id]);

                        // Update bio in userStatus
                        $stmt = $pdo->prepare("UPDATE userStatus SET profile_bio = ? WHERE user_id = ?");
                        $stmt->execute([$bio, $user_id]);

                        // Update session data
                        $_SESSION['user']['first_name'] = $first_name;
                        $_SESSION['user']['last_name'] = $last_name;
                        $_SESSION['user']['user_name'] = $username;
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
                }
            } catch (PDOException $e) {
                $error_message = "Error updating profile.";
                error_log("Profile update error: " . $e->getMessage());
            }
        }
    } elseif (isset($_POST['change_theme'])) {
        // Update theme preference
        $new_theme = $_POST['theme'];

        try {
            // Verify theme is valid. 'system' follows the OS color scheme;
            // 'light'/'dark' are explicit overrides.
            $valid_themes = ['light', 'dark', 'system'];
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
    } elseif (isset($_POST['change_language'])) {
        // Update language preference. Mirrors theme: persist to userStatus,
        // refresh session + cookie via set_locale(), then redirect so the
        // new locale paints on the next request.
        $new_lang = $_POST['language'] ?? '';
        if (is_valid_locale($new_lang)) {
            set_locale($new_lang, $pdo, $user_id);
            header('Location: profile.php?lang_updated=1#appearance');
            exit();
        } else {
            $error_message = 'Invalid language selected.';
        }
    } elseif (isset($_POST['upload_image'])) {
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
                $upload_dir_fs = __DIR__ . '/uploads/';   // filesystem path
                $upload_dir_url = 'uploads/';              // URL path (relative)
                if (!is_dir($upload_dir_fs)) {
                    mkdir($upload_dir_fs, 0775, true);
                }

                $file_extension = pathinfo($_FILES['profile_image']['name'], PATHINFO_EXTENSION);
                $new_filename = 'profile_' . $user_id . '_' . time() . '.' . $file_extension;
                $fs_path = $upload_dir_fs . $new_filename;
                $url_path = $upload_dir_url . $new_filename;

                if (move_uploaded_file($_FILES['profile_image']['tmp_name'], $fs_path)) {
                    // Xóa ảnh cũ — DB lưu URL path, cần ghép lại với __DIR__ để có FS path
                    if ($profile['profile_image']) {
                        $old_fs = __DIR__ . '/' . ltrim($profile['profile_image'], '/');
                        if (file_exists($old_fs)) {
                            unlink($old_fs);
                        }
                    }
                    $stmt = $pdo->prepare("UPDATE user SET profile_image = ? WHERE user_id = ?");
                    $stmt->execute([$url_path, $user_id]);   // <-- lưu URL path, không phải FS path

                    // Update session
                    $_SESSION['user']['profile_image'] = $url_path;

                    $profile['profile_image'] = $url_path;
                    $success_message = "Profile image updated successfully!";
                } else {
                    $error_message = "Server permissions error. Upload directory may not be writable.";
                }
            }
        } else {
            $error_message = "Please select an image file.";
        }
    } elseif (isset($_POST['change_password'])) {
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
            if (!preg_match('/[A-Z]/', $new_password))
                $password_errors[] = "uppercase letter";
            if (!preg_match('/[a-z]/', $new_password))
                $password_errors[] = "lowercase letter";
            if (!preg_match('/[0-9]/', $new_password))
                $password_errors[] = "number";
            if (!preg_match('/[^a-zA-Z0-9]/', $new_password))
                $password_errors[] = "special character";

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
    } elseif (isset($_POST['update_physical_stats'])) {
        // Handle physical stats update
        $age = !empty($_POST['age']) ? (int) $_POST['age'] : null;
        $gender = !empty($_POST['gender']) ? $_POST['gender'] : null;
        $weight = !empty($_POST['weight']) ? (float) $_POST['weight'] : null;
        $height = !empty($_POST['height']) ? (float) $_POST['height'] : null;
        $valid_genders = ['male', 'female', 'other'];
        if ($gender !== null && !in_array($gender, $valid_genders, true)) {
            $gender = null;
        }

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
    } elseif (isset($_POST['archive_account'])) {
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
    } elseif (isset($_POST['change_role'])) {
        $new_role = $_POST['role'] ?? 'regular';
        if (in_array($new_role, ['regular', 'pt'], true)) {
            try {
                $stmt = $pdo->prepare("UPDATE user SET role = ? WHERE user_id = ?");
                $stmt->execute([$new_role, $user_id]);
                $_SESSION['user']['role'] = $new_role;
                $success_message = "Account role updated successfully!";
                
                // Refresh profile data immediately
                $stmt = $pdo->prepare("
                    SELECT u.*, us.theme_preference, us.profile_bio, us.status 
                    FROM user u 
                    JOIN userStatus us ON u.user_id = us.user_id 
                    WHERE u.user_id = ?
                ");
                $stmt->execute([$user_id]);
                $profile = $stmt->fetch();
            } catch (PDOException $e) {
                $error_message = "Error updating account role: " . $e->getMessage();
            }
        } else {
            $error_message = "Invalid account role selected.";
        }
    } elseif (isset($_POST['send_trainer_request'])) {
        $trainer_handle = trim($_POST['trainer_handle'] ?? '');
        if (empty($trainer_handle)) {
            $error_message = "Please enter a trainer username.";
        } else {
            try {
                // Find the trainer user
                $stmt = $pdo->prepare("SELECT user_id, role FROM user WHERE user_name = ?");
                $stmt->execute([$trainer_handle]);
                $trainer = $stmt->fetch();
                if (!$trainer) {
                    $error_message = "Trainer handle not found. Please double check (e.g. Name#1234).";
                } elseif ($trainer['role'] !== 'pt') {
                    $error_message = "This user is not registered as a Personal Trainer (PT).";
                } elseif ((int)$trainer['user_id'] === $user_id) {
                    $error_message = "You cannot link with yourself.";
                } else {
                    // Insert pending link request
                    $stmt = $pdo->prepare("
                        INSERT INTO trainer_client (trainer_id, client_id, status)
                        VALUES (?, ?, 'pending')
                        ON DUPLICATE KEY UPDATE status = 'pending'
                    ");
                    $stmt->execute([$trainer['user_id'], $user_id]);
                    $success_message = "Trainer request sent successfully! Waiting for approval.";
                }
            } catch (PDOException $e) {
                $error_message = "Error sending request: " . $e->getMessage();
            }
        }
    } elseif (isset($_POST['disconnect_trainer'])) {
        try {
            $stmt = $pdo->prepare("DELETE FROM trainer_client WHERE client_id = ?");
            $stmt->execute([$user_id]);
            $success_message = "Trainer connection removed.";
        } catch (PDOException $e) {
            $error_message = "Error disconnecting trainer.";
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

// Get trainer connection if role is regular
$trainer_connection = null;
if (($profile['role'] ?? 'regular') === 'regular') {
    try {
        $stmt = $pdo->prepare("
            SELECT tc.*, u.user_name AS trainer_name, u.first_name, u.last_name, u.profile_image 
            FROM trainer_client tc
            JOIN user u ON tc.trainer_id = u.user_id
            WHERE tc.client_id = ? AND tc.status IN ('pending', 'accepted')
        ");
        $stmt->execute([$user_id]);
        $trainer_connection = $stmt->fetch();
    } catch (PDOException $e) {
        // Table/columns may not exist yet
    }
}
?>

<!DOCTYPE html>
<html lang="<?= html_lang_attr() ?>" data-theme="<?= htmlspecialchars($profile['theme_preference'] ?? 'system') ?>">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= t('profile.title_alt') ?></title>

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
            <form id="avatarUploadForm" method="POST" enctype="multipart/form-data">
                <div class="avatar-container" onclick="document.getElementById('avatarFileInput').click()" title="<?= t('profile.avatar.title') ?>">
                    <?php if (!empty($profile['profile_image']) && file_exists($profile['profile_image'])): ?>
                        <img src="<?= BASE_URL ?><?= htmlspecialchars($profile['profile_image']) ?>" class="profile-avatar" alt="Avatar">
                    <?php else: ?>
                        <div class="avatar-placeholder"><i class="fas fa-user"></i></div>
                    <?php endif; ?>
                    <div class="avatar-overlay">
                        <i class="fas fa-camera"></i>
                        <span><?= t('profile.avatar.change') ?></span>
                    </div>
                </div>
                <input type="file" name="profile_image" id="avatarFileInput" accept="image/*" style="display: none;" onchange="document.getElementById('avatarSubmitBtn').click()">
                <button type="submit" name="upload_image" id="avatarSubmitBtn" style="display: none;"></button>
            </form>

            <h2 class="profile-name"><?= htmlspecialchars($profile['first_name'] . ' ' . $profile['last_name']) ?></h2>
            <p class="profile-email"><?= htmlspecialchars($profile['email']) ?></p>

            <nav class="sidebar-menu">
                <a href="#basic-info" class="menu-link"><i class="fas fa-user-edit"></i> <?= t('profile.nav.personal') ?></a>
                <a href="#physical-stats" class="menu-link"><i class="fas fa-child"></i> <?= t('profile.nav.body') ?></a>
                <a href="#appearance" class="menu-link"><i class="fas fa-paint-brush"></i> <?= t('profile.nav.appearance') ?></a>
                <a href="#language" class="menu-link"><i class="fas fa-globe"></i> <?= t('profile.nav.language') ?></a>
                <a href="#trainer-settings" class="menu-link"><i class="fas fa-dumbbell"></i> <?= t('profile.nav.trainer') ?></a>
                <a href="#security" class="menu-link"><i class="fas fa-shield-alt"></i> <?= t('profile.nav.security') ?></a>
            </nav>

            <a href="<?= BASE_URL ?>logout.php" class="btn-logout">
                <i class="fas fa-sign-out-alt"></i> <?= t('profile.sign_out') ?>
            </a>
        </aside>

        <main class="profile-content">

            <?php if (isset($_GET['theme_updated'])): ?>
                <div class="alert success"><i class="fas fa-check-circle"></i> <?= t('profile.alert.theme_updated') ?></div>
            <?php endif; ?>
            <?php if (isset($_GET['lang_updated'])): ?>
                <div class="alert success"><i class="fas fa-check-circle"></i> <?= t('profile.alert.lang_updated') ?></div>
            <?php endif; ?>
            <?php if (!empty($success_message)): ?>
                <div class="alert success"><i class="fas fa-check-circle"></i> <?= htmlspecialchars($success_message) ?>
                </div>
            <?php endif; ?>
            <?php if (!empty($error_message)): ?>
                <div class="alert error"><i class="fas fa-exclamation-triangle"></i> <?= htmlspecialchars($error_message) ?>
                </div>
            <?php endif; ?>

            <section id="basic-info" class="settings-card">
                <div class="card-header">
                    <div class="header-icon icon-blue"><i class="fas fa-user"></i></div>
                    <div>
                        <h2><?= t('profile.basic.title') ?></h2>
                        <p><?= t('profile.basic.sub') ?></p>
                    </div>
                </div>
                <form method="POST">
                    <div class="form-grid">
                        <div class="form-group">
                            <label><?= t('profile.field.first_name') ?></label>
                            <input type="text" name="first_name" value="<?= htmlspecialchars($profile['first_name']) ?>"
                                required>
                        </div>
                        <div class="form-group">
                            <label><?= t('profile.field.last_name') ?></label>
                            <input type="text" name="last_name" value="<?= htmlspecialchars($profile['last_name']) ?>"
                                required>
                        </div>
                        <div class="form-group full-width">
                            <label><?= t('profile.field.username') ?></label>
                            <input type="text" name="username" id="usernameInput"
                                value="<?= htmlspecialchars($profile['user_name'] ?? '') ?>"
                                pattern="[A-Za-z0-9_.#\-]{3,30}"
                                title="<?= t('profile.field.username_title') ?>"
                                autocomplete="off" required>
                            <small class="field-hint"><?= t_raw('profile.field.username_hint') ?></small>
                        </div>
                        <div class="form-group full-width">
                            <label><?= t('profile.field.email') ?></label>
                            <input type="email" name="email" value="<?= htmlspecialchars($profile['email']) ?>"
                                required>
                        </div>
                        <div class="form-group full-width">
                            <label><?= t('profile.field.bio') ?></label>
                            <textarea name="bio"
                                placeholder="<?= t('profile.field.bio_placeholder') ?>"><?= htmlspecialchars($profile['profile_bio'] ?? '') ?></textarea>
                        </div>
                    </div>
                    <button type="submit" name="update_info" class="btn-save"><?= t('profile.basic.save') ?></button>
                </form>
            </section>

            <section id="physical-stats" class="settings-card">
                <div class="card-header">
                    <div class="header-icon icon-green"><i class="fas fa-ruler-combined"></i></div>
                    <div>
                        <h2><?= t('profile.body.title') ?></h2>
                        <p><?= t('profile.body.sub') ?></p>
                    </div>
                </div>
                <form method="POST">
                    <div class="form-grid">
                        <div class="form-group">
                            <label><?= t('profile.body.age') ?></label>
                            <input type="number" name="age"
                                value="<?= htmlspecialchars((int) $physical_info['age'] ?? '') ?>" placeholder="<?= t('profile.body.age_placeholder') ?>">
                        </div>
                        <div class="form-group">
                            <label><?= t('profile.body.gender') ?></label>
                            <select name="gender">
                                <option value=""><?= t('profile.body.gender.select') ?></option>
                                <option value="male" <?= ($physical_info['gender'] ?? '') === 'male' ? 'selected' : '' ?>>
                                    <?= t('profile.body.gender.male') ?></option>
                                <option value="female" <?= ($physical_info['gender'] ?? '') === 'female' ? 'selected' : '' ?>><?= t('profile.body.gender.female') ?></option>
                                <option value="other" <?= ($physical_info['gender'] ?? '') === 'other' ? 'selected' : '' ?>><?= t('profile.body.gender.other') ?></option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label><?= t('profile.body.weight') ?></label>
                            <input type="number" name="weight"
                                value="<?= htmlspecialchars((int) $physical_info['weight'] ?? '') ?>" placeholder="kg">
                        </div>
                        <div class="form-group">
                            <label><?= t('profile.body.height') ?></label>
                            <input type="number" name="height"
                                value="<?= htmlspecialchars((int) $physical_info['height'] ?? '') ?>" placeholder="cm">
                        </div>
                    </div>
                    <button type="submit" name="update_physical_stats" class="btn-save"><?= t('profile.body.save') ?></button>
                </form>
            </section>

            <section id="appearance" class="settings-card">
                <div class="card-header">
                    <div class="header-icon icon-purple"><i class="fas fa-moon"></i></div>
                    <div>
                        <h2><?= t('profile.appearance.title') ?></h2>
                        <p><?= t('profile.appearance.sub') ?></p>
                    </div>
                </div>
                <form method="POST">
                    <div class="theme-options">
                        <div class="theme-option <?= ($profile['theme_preference'] === 'light') ? 'active' : '' ?>"
                            onclick="selectTheme('light')">
                            <i class="fas fa-sun"></i>
                            <div><?= t('profile.theme.light') ?></div>
                        </div>
                        <div class="theme-option <?= ($profile['theme_preference'] === 'dark') ? 'active' : '' ?>"
                            onclick="selectTheme('dark')">
                            <i class="fas fa-moon"></i>
                            <div><?= t('profile.theme.dark') ?></div>
                        </div>
                        <div class="theme-option <?= (($profile['theme_preference'] ?? 'system') === 'system') ? 'active' : '' ?>"
                            onclick="selectTheme('system')">
                            <i class="fas fa-desktop"></i>
                            <div><?= t('profile.theme.system') ?></div>
                        </div>
                    </div>
                    <input type="hidden" name="theme" id="selectedTheme"
                        value="<?= htmlspecialchars($profile['theme_preference'] ?? 'system') ?>">
                    <button type="submit" name="change_theme" class="btn-save btn-save--theme"><?= t('profile.theme.apply') ?></button>
                </form>
            </section>

            <section id="language" class="settings-card">
                <div class="card-header">
                    <div class="header-icon icon-blue"><i class="fas fa-globe"></i></div>
                    <div>
                        <h2><?= t('profile.language.title') ?></h2>
                        <p><?= t('profile.language.sub') ?></p>
                    </div>
                </div>
                <form method="POST">
                    <div class="theme-options">
                        <?php
                        // Render one tile per registered locale. The tile mirrors the
                        // theme-tile UX so we don't need new CSS.
                        $__currentLocale = current_locale();
                        foreach (available_locales() as $__code => $__meta):
                        ?>
                            <div class="theme-option <?= $__code === $__currentLocale ? 'active' : '' ?>"
                                 onclick="selectLanguage('<?= htmlspecialchars($__code, ENT_QUOTES) ?>')">
                                <i class="fas fa-language"></i>
                                <div><?= htmlspecialchars($__meta['native']) ?></div>
                                <small><?= htmlspecialchars($__meta['english']) ?></small>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <input type="hidden" name="language" id="selectedLanguage"
                        value="<?= htmlspecialchars($__currentLocale) ?>">
                    <button type="submit" name="change_language" class="btn-save btn-save--theme"><?= t('profile.language.apply') ?></button>
                </form>
            </section>

            <section id="trainer-settings" class="settings-card">
                <div class="card-header">
                    <div class="header-icon icon-green" style="background-color: var(--color-primary-soft); color: var(--color-primary);"><i class="fas fa-dumbbell"></i></div>
                    <div>
                        <h2><?= t('profile.trainer.title') ?></h2>
                        <p><?= t('profile.trainer.sub') ?></p>
                    </div>
                </div>

                <!-- 1. Cấu hình vai trò tài khoản (Role Setup) -->
                <form method="POST" style="margin-bottom: 24px; padding-bottom: 24px; border-bottom: 2px dashed var(--color-border);">
                    <div class="form-group full-width">
                        <label><?= t('profile.trainer.role_label') ?></label>
                        <div class="theme-options" style="margin-top: 8px;">
                            <div class="theme-option <?= (($profile['role'] ?? 'regular') === 'regular') ? 'active' : '' ?>"
                                onclick="selectRole('regular')">
                                <i class="fas fa-user"></i>
                                <div><?= t('profile.trainer.role_regular') ?></div>
                                <small style="display: block; font-size: 11px; margin-top: 4px; color: var(--color-text-secondary);"><?= t('profile.trainer.role_regular_sub') ?></small>
                            </div>
                            <div class="theme-option <?= (($profile['role'] ?? 'regular') === 'pt') ? 'active' : '' ?>"
                                onclick="selectRole('pt')">
                                <i class="fas fa-dumbbell"></i>
                                <div><?= t('profile.trainer.role_pt') ?></div>
                                <small style="display: block; font-size: 11px; margin-top: 4px; color: var(--color-text-secondary);"><?= t('profile.trainer.role_pt_sub') ?></small>
                            </div>
                        </div>
                        <input type="hidden" name="role" id="selectedRole" value="<?= htmlspecialchars($profile['role'] ?? 'regular') ?>">
                    </div>
                    <button type="submit" name="change_role" class="btn-save" style="margin-top: 12px;"><?= t('profile.trainer.role_save') ?></button>
                </form>

                <!-- 2. Quản lý liên kết Trainer (Chỉ dành cho regular user) -->
                <?php if (($profile['role'] ?? 'regular') === 'regular'): ?>
                    <h4 style="margin-bottom: 12px; font-weight: 700; color: var(--color-text);"><?= t('profile.trainer.connection_title') ?></h4>
                    
                    <?php if ($trainer_connection): ?>
                        <div class="friend-card" style="display: flex; align-items: center; justify-content: space-between; border: 2px solid var(--color-border); border-radius: var(--radius-md); padding: 16px; background: var(--color-surface-alt); margin-bottom: 16px;">
                            <div style="display: flex; align-items: center; gap: 12px;">
                                <div class="friend-card__avatar" style="width: 48px; height: 48px; border-radius: 50%; overflow: hidden; background: var(--color-surface); display: flex; align-items: center; justify-content: center; border: 2px solid var(--color-border); position: relative;">
                                    <?php if (!empty($trainer_connection['profile_image'])): ?>
                                        <img src="<?= BASE_URL . htmlspecialchars($trainer_connection['profile_image'], ENT_QUOTES) ?>" style="width: 100%; height: 100%; object-fit: cover;">
                                    <?php else: ?>
                                        <i class="fas fa-user" style="font-size: 20px; color: var(--color-text-secondary);"></i>
                                    <?php endif; ?>
                                </div>
                                <div>
                                    <h3 style="font-size: 16px; font-weight: 700; margin: 0; color: var(--color-text);"><?= htmlspecialchars($trainer_connection['first_name'] . ' ' . $trainer_connection['last_name'], ENT_QUOTES) ?></h3>
                                    <span style="font-size: 13px; color: var(--color-text-secondary);"><?= htmlspecialchars($trainer_connection['trainer_name'], ENT_QUOTES) ?></span>
                                    <div style="margin-top: 4px;">
                                        <?php if ($trainer_connection['status'] === 'pending'): ?>
                                            <span class="friend-card__hint" style="background: var(--color-surface); color: var(--color-text-secondary); padding: 2px 8px; border-radius: 12px; font-size: 11px; font-weight: 700; border: 2px solid var(--color-border);"><?= t('profile.trainer.status_pending') ?></span>
                                        <?php else: ?>
                                            <span class="friend-card__hint friend-card__hint--ok" style="background: var(--color-primary-soft); color: var(--color-primary); padding: 2px 8px; border-radius: 12px; font-size: 11px; font-weight: 700; border: 2px solid var(--color-primary);"><i class="fas fa-check"></i> <?= t('profile.trainer.status_connected') ?></span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                            <form method="POST" style="margin: 0;">
                                <button type="submit" name="disconnect_trainer" class="btn-tactile btn-tactile--ghost" style="border: 2px solid var(--color-border); padding: 8px 16px; border-radius: var(--radius-md); font-weight: 700; cursor: pointer; background: var(--color-surface); color: var(--color-text);" onclick="return confirm('<?= t_raw('profile.trainer.disconnect_confirm') ?>');">
                                    <i class="fas fa-unlink"></i> <?= t('profile.trainer.btn_disconnect') ?>
                                </button>
                            </form>
                        </div>
                    <?php else: ?>
                        <div class="friends-empty" style="border: 2px dashed var(--color-border); border-radius: var(--radius-lg); padding: 24px; text-align: center; background: var(--color-surface); margin-bottom: 16px;">
                            <div class="friends-empty__icon" style="font-size: 32px; color: var(--color-text-secondary); margin-bottom: 12px;"><i class="fas fa-user-plus"></i></div>
                            <h3 style="font-size: 16px; font-weight: 700; margin-bottom: 8px; color: var(--color-text);"><?= t('profile.trainer.no_trainer_title') ?></h3>
                            <p style="font-size: 13px; color: var(--color-text-secondary); margin-bottom: 16px;"><?= t('profile.trainer.no_trainer_body') ?></p>
                            
                            <form method="POST" style="display: flex; gap: 8px; max-width: 480px; margin: 0 auto;">
                                <input type="text" name="trainer_handle" placeholder="e.g. TrainerHung#1234" required style="flex: 1; border: 2px solid var(--color-border); border-radius: var(--radius-md); padding: 10px 16px; font-size: 14px; outline: none; background: var(--color-surface-alt); color: var(--color-text);">
                                <button type="submit" name="send_trainer_request" class="btn-tactile btn-tactile--primary" style="background: var(--color-primary); color: #ffffff; border: none; border-radius: var(--radius-md); padding: 10px 20px; font-weight: 700; cursor: pointer; box-shadow: 0 4px 0 var(--color-primary-hover);">
                                    <?= t('profile.trainer.btn_connect') ?>
                                </button>
                            </form>
                        </div>
                    <?php endif; ?>
                <?php else: ?>
                    <div style="background: var(--color-primary-soft); color: var(--color-text); border: 2px solid var(--color-primary); border-radius: var(--radius-md); padding: 16px; display: flex; align-items: center; gap: 12px;">
                        <i class="fas fa-info-circle" style="font-size: 20px; color: var(--color-primary);"></i>
                        <div>
                            <strong style="display: block; font-weight: 700; margin-bottom: 4px; color: var(--color-text);"><?= t('profile.trainer.is_pt_title') ?></strong>
                            <span style="font-size: 13px; color: var(--color-text-secondary);"><?= t('profile.trainer.is_pt_body') ?></span>
                        </div>
                    </div>
                <?php endif; ?>
            </section>

            <section id="security" class="settings-card danger-zone">
                <div class="card-header card-header--danger">
                    <div class="header-icon icon-red"><i class="fas fa-shield-alt"></i></div>
                    <div>
                        <h2><?= t('profile.security.title') ?></h2>
                        <p><?= t('profile.security.sub') ?></p>
                    </div>
                </div>

                <form method="POST" class="security-form">
                    <h4 class="security-form__title"><?= t('profile.security.change_password') ?></h4>
                    <div class="form-grid">
                        <div class="form-group full-width">
                            <label><?= t('profile.security.current') ?></label>
                            <input type="password" name="current_password" placeholder="********">
                        </div>
                        <div class="form-group">
                            <label><?= t('profile.security.new') ?></label>
                            <input type="password" name="new_password" placeholder="********">
                        </div>
                        <div class="form-group">
                            <label><?= t('profile.security.confirm') ?></label>
                            <input type="password" name="confirm_new_password" placeholder="********">
                        </div>
                    </div>
                    <button type="submit" name="change_password" class="btn-save"><?= t('profile.security.update') ?></button>
                </form>

                <div class="archive-section">
                    <h4 class="archive-section__title"><?= t('profile.archive.title') ?></h4>
                    <p class="archive-section__desc">
                        <?= t_raw('profile.archive.desc') ?>
                    </p>
                    <form method="POST" onsubmit="return confirm(<?= json_encode(t_raw('profile.archive.confirm')) ?>);" class="archive-section__form">
                        <input type="text" name="confirm_archive" placeholder="<?= t('profile.archive.placeholder') ?>" required
                            class="archive-section__input">
                        <button type="submit" name="archive_account" class="btn-danger"><?= t('profile.archive.btn') ?></button>
                    </form>
                </div>
            </section>

        </main>
    </div>

    <script>
        // Each settings section has its own form/tile set, so scope the
        // "active" toggle to the clicked tile's parent rather than nuking
        // every .theme-option on the page (which used to clear Language
        // when you picked a Theme and vice versa).
        function selectTheme(theme) {
            document.getElementById('selectedTheme').value = theme;
            const tile = event.currentTarget;
            const parent = tile.closest('.theme-options') || document;
            parent.querySelectorAll('.theme-option').forEach(el => el.classList.remove('active'));
            tile.classList.add('active');
        }
        function selectLanguage(code) {
            document.getElementById('selectedLanguage').value = code;
            const tile = event.currentTarget;
            const parent = tile.closest('.theme-options') || document;
            parent.querySelectorAll('.theme-option').forEach(el => el.classList.remove('active'));
            tile.classList.add('active');
        }
        function selectRole(role) {
            document.getElementById('selectedRole').value = role;
            const tile = event.currentTarget;
            const parent = tile.closest('.theme-options') || document;
            parent.querySelectorAll('.theme-option').forEach(el => el.classList.remove('active'));
            tile.classList.add('active');
        }
    </script>
</body>

</html>
