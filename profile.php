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
$lang = current_locale();
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
    } elseif (isset($_POST['become_pt']) || isset($_POST['update_pt_profile'])) {
        // Self-serve onboarding: a user becomes a PT only after filling a real
        // profile (bio + specialties + accepting terms). Same form edits it later.
        $bio         = trim($_POST['pt_bio'] ?? '');
        $specialties = trim($_POST['pt_specialties'] ?? '');
        $expYears    = max(0, (int) ($_POST['pt_experience'] ?? 0));
        $maxClients  = max(1, (int) ($_POST['pt_max_clients'] ?? 10));
        $acceptTerms = !empty($_POST['pt_terms']);
        $isBecoming  = isset($_POST['become_pt']);

        if ($bio === '' || $specialties === '') {
            $error_message = ($lang === 'vi') ? "Vui lòng điền giới thiệu và chuyên môn." : "Please fill in your bio and specialties.";
        } elseif ($isBecoming && !$acceptTerms) {
            $error_message = ($lang === 'vi') ? "Bạn cần đồng ý điều khoản huấn luyện viên." : "You must accept the trainer terms.";
        } else {
            try {
                $stmt = $pdo->prepare("
                    INSERT INTO pt_profile (user_id, bio, specialties, experience_years, max_clients, accepted_terms)
                    VALUES (?, ?, ?, ?, ?, ?)
                    ON DUPLICATE KEY UPDATE bio = VALUES(bio), specialties = VALUES(specialties),
                        experience_years = VALUES(experience_years), max_clients = VALUES(max_clients),
                        accepted_terms = GREATEST(accepted_terms, VALUES(accepted_terms))
                ");
                $stmt->execute([$user_id, $bio, $specialties, $expYears, $maxClients, $acceptTerms ? 1 : 0]);

                if ($isBecoming) {
                    $pdo->prepare("UPDATE user SET role = 'pt' WHERE user_id = ?")->execute([$user_id]);
                    $_SESSION['user']['role'] = 'pt';
                    $success_message = ($lang === 'vi') ? "Chúc mừng! Bạn đã trở thành Huấn luyện viên." : "Congrats! You're now a Personal Trainer.";
                } else {
                    $success_message = ($lang === 'vi') ? "Đã cập nhật hồ sơ huấn luyện viên." : "Trainer profile updated.";
                }

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
                $error_message = "Error saving trainer profile: " . $e->getMessage();
            }
        }
    } elseif (isset($_POST['revert_regular'])) {
        // Guard: don't strip the PT role out from under active clients.
        try {
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM trainer_client WHERE trainer_id = ? AND status = 'accepted'");
            $stmt->execute([$user_id]);
            $activeClients = (int) $stmt->fetchColumn();
        } catch (PDOException $e) {
            $activeClients = 0;
        }
        if ($activeClients > 0) {
            $error_message = ($lang === 'vi')
                ? "Bạn còn $activeClients học viên đang liên kết. Hãy hủy liên kết tất cả trước khi quay lại tài khoản thường."
                : "You still have $activeClients linked client(s). Disconnect them all before switching back.";
        } else {
            try {
                $pdo->prepare("UPDATE user SET role = 'regular' WHERE user_id = ?")->execute([$user_id]);
                $_SESSION['user']['role'] = 'regular';
                $success_message = ($lang === 'vi') ? "Đã quay lại tài khoản thường." : "Switched back to a regular account.";
                $stmt = $pdo->prepare("
                    SELECT u.*, us.theme_preference, us.profile_bio, us.status
                    FROM user u JOIN userStatus us ON u.user_id = us.user_id WHERE u.user_id = ?
                ");
                $stmt->execute([$user_id]);
                $profile = $stmt->fetch();
            } catch (PDOException $e) {
                $error_message = "Error switching role: " . $e->getMessage();
            }
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

// PT onboarding profile + active-client count (for the Coaching pane)
$pt_profile = null;
$pt_active_clients = 0;
try {
    $stmt = $pdo->prepare("SELECT * FROM pt_profile WHERE user_id = ?");
    $stmt->execute([$user_id]);
    $pt_profile = $stmt->fetch();
} catch (PDOException $e) {
    // Table may not exist yet
}
if (($profile['role'] ?? 'regular') === 'pt') {
    try {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM trainer_client WHERE trainer_id = ? AND status = 'accepted'");
        $stmt->execute([$user_id]);
        $pt_active_clients = (int) $stmt->fetchColumn();
    } catch (PDOException $e) {
        $pt_active_clients = 0;
    }
}

// PT directory: onboarded trainers a regular, unattached user can browse + connect
// to (no need to know the exact handle). Only loaded when relevant.
$pt_directory = [];
if (($profile['role'] ?? 'regular') === 'regular' && empty($trainer_connection)) {
    try {
        $stmt = $pdo->prepare("
            SELECT u.user_id, u.user_name, u.first_name, u.last_name, u.profile_image,
                   p.bio, p.specialties, p.experience_years, p.max_clients,
                   (SELECT COUNT(*) FROM trainer_client tc
                    WHERE tc.trainer_id = u.user_id AND tc.status = 'accepted') AS client_count
            FROM user u
            JOIN pt_profile p ON p.user_id = u.user_id
            WHERE u.role = 'pt' AND u.user_id != ?
            ORDER BY client_count ASC, u.first_name ASC
            LIMIT 60
        ");
        $stmt->execute([$user_id]);
        $pt_directory = $stmt->fetchAll();
    } catch (PDOException $e) {
        // pt_profile table may not exist yet
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
    <!-- Without JS the pane switcher can't run, so reveal every section -->
    <noscript>
        <style>.profile-content .settings-card { display: block !important; }</style>
    </noscript>
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

            <section id="basic-info" class="settings-card is-active">
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
                    <div class="header-icon icon-green"><i class="fas fa-dumbbell"></i></div>
                    <div>
                        <h2><?= t('profile.trainer.title') ?></h2>
                        <p><?= t('profile.trainer.sub') ?></p>
                    </div>
                </div>

                <!-- Coaching role: self-serve onboarding (regular) or manage profile (pt) -->
                <?php $ptp = $pt_profile ?: []; ?>
                <?php if (($profile['role'] ?? 'regular') === 'pt'): ?>
                    <h4 class="coaching-subtitle"><i class="fas fa-id-card"></i> <?= ($lang === 'vi') ? 'Hồ sơ Huấn luyện viên' : 'Trainer profile' ?></h4>
                    <p class="coaching-hint"><?= ($lang === 'vi') ? 'Thông tin này hiển thị cho học viên khi họ kết nối với bạn.' : 'This is shown to clients when they connect with you.' ?></p>
                    <form method="POST" class="coaching-block">
                        <div class="form-grid">
                            <div class="form-group full-width">
                                <label for="pt_specialties"><?= ($lang === 'vi') ? 'Chuyên môn' : 'Specialties' ?></label>
                                <input type="text" name="pt_specialties" id="pt_specialties" maxlength="255" required value="<?= htmlspecialchars($ptp['specialties'] ?? '', ENT_QUOTES) ?>" placeholder="<?= ($lang === 'vi') ? 'VD: Giảm cân, tăng cơ, dinh dưỡng thể thao' : 'e.g. Weight loss, hypertrophy, sports nutrition' ?>">
                            </div>
                            <div class="form-group">
                                <label for="pt_experience"><?= ($lang === 'vi') ? 'Số năm kinh nghiệm' : 'Years of experience' ?></label>
                                <input type="number" name="pt_experience" id="pt_experience" min="0" max="60" value="<?= (int) ($ptp['experience_years'] ?? 0) ?>">
                            </div>
                            <div class="form-group">
                                <label for="pt_max_clients"><?= ($lang === 'vi') ? 'Số học viên tối đa' : 'Max clients' ?></label>
                                <input type="number" name="pt_max_clients" id="pt_max_clients" min="1" max="500" value="<?= (int) ($ptp['max_clients'] ?? 10) ?>">
                            </div>
                            <div class="form-group full-width">
                                <label for="pt_bio"><?= ($lang === 'vi') ? 'Giới thiệu' : 'Bio' ?></label>
                                <textarea name="pt_bio" id="pt_bio" rows="4" required placeholder="<?= ($lang === 'vi') ? 'Kinh nghiệm, phong cách huấn luyện, thành tích...' : 'Experience, coaching style, achievements...' ?>"><?= htmlspecialchars($ptp['bio'] ?? '', ENT_QUOTES) ?></textarea>
                            </div>
                        </div>
                        <button type="submit" name="update_pt_profile" class="btn-save"><?= ($lang === 'vi') ? 'Lưu hồ sơ' : 'Save profile' ?></button>
                    </form>

                    <h4 class="coaching-subtitle coaching-subtitle--spaced"><i class="fas fa-rotate-left"></i> <?= ($lang === 'vi') ? 'Quay lại tài khoản thường' : 'Switch back to regular' ?></h4>
                    <?php if ($pt_active_clients > 0): ?>
                        <div class="coaching-note coaching-note--warn">
                            <i class="fas fa-triangle-exclamation"></i>
                            <span><?= ($lang === 'vi') ? "Bạn đang có <strong>$pt_active_clients</strong> học viên liên kết. Hãy hủy liên kết tất cả ở PT Dashboard trước khi quay lại." : "You have <strong>$pt_active_clients</strong> linked client(s). Disconnect them all in the PT Dashboard first." ?></span>
                        </div>
                    <?php else: ?>
                        <form method="POST" data-confirm="<?= ($lang === 'vi') ? 'Quay lại tài khoản thường? Hồ sơ HLV vẫn được lưu lại.' : 'Switch back to a regular account? Your trainer profile is kept.' ?>">
                            <button type="submit" name="revert_regular" class="btn-tactile btn-tactile--ghost coaching-revert-btn">
                                <i class="fas fa-user"></i> <?= ($lang === 'vi') ? 'Quay lại tài khoản thường' : 'Switch back to regular' ?>
                            </button>
                        </form>
                    <?php endif; ?>
                <?php else: ?>
                    <h4 class="coaching-subtitle"><i class="fas fa-dumbbell"></i> <?= ($lang === 'vi') ? 'Trở thành Huấn luyện viên' : 'Become a Personal Trainer' ?></h4>
                    <p class="coaching-hint"><?= ($lang === 'vi') ? 'Điền hồ sơ bên dưới để mở tài khoản HLV và bắt đầu nhận học viên.' : 'Fill in the profile below to unlock a trainer account and start taking clients.' ?></p>
                    <form method="POST" class="coaching-block coaching-block--divider">
                        <div class="form-grid">
                            <div class="form-group full-width">
                                <label for="pt_specialties"><?= ($lang === 'vi') ? 'Chuyên môn' : 'Specialties' ?></label>
                                <input type="text" name="pt_specialties" id="pt_specialties" maxlength="255" required value="<?= htmlspecialchars($ptp['specialties'] ?? '', ENT_QUOTES) ?>" placeholder="<?= ($lang === 'vi') ? 'VD: Giảm cân, tăng cơ, dinh dưỡng thể thao' : 'e.g. Weight loss, hypertrophy, sports nutrition' ?>">
                            </div>
                            <div class="form-group">
                                <label for="pt_experience"><?= ($lang === 'vi') ? 'Số năm kinh nghiệm' : 'Years of experience' ?></label>
                                <input type="number" name="pt_experience" id="pt_experience" min="0" max="60" value="<?= (int) ($ptp['experience_years'] ?? 0) ?>">
                            </div>
                            <div class="form-group">
                                <label for="pt_max_clients"><?= ($lang === 'vi') ? 'Số học viên tối đa' : 'Max clients' ?></label>
                                <input type="number" name="pt_max_clients" id="pt_max_clients" min="1" max="500" value="<?= (int) ($ptp['max_clients'] ?? 10) ?>">
                            </div>
                            <div class="form-group full-width">
                                <label for="pt_bio"><?= ($lang === 'vi') ? 'Giới thiệu' : 'Bio' ?></label>
                                <textarea name="pt_bio" id="pt_bio" rows="4" required placeholder="<?= ($lang === 'vi') ? 'Kinh nghiệm, phong cách huấn luyện, thành tích...' : 'Experience, coaching style, achievements...' ?>"><?= htmlspecialchars($ptp['bio'] ?? '', ENT_QUOTES) ?></textarea>
                            </div>
                        </div>
                        <label class="coaching-terms">
                            <input type="checkbox" name="pt_terms" value="1" required>
                            <span><?= ($lang === 'vi') ? 'Tôi cam kết hướng dẫn dinh dưỡng có trách nhiệm và đồng ý điều khoản huấn luyện viên.' : 'I commit to responsible nutrition guidance and accept the trainer terms.' ?></span>
                        </label>
                        <button type="submit" name="become_pt" class="btn-save"><i class="fas fa-dumbbell"></i> <?= ($lang === 'vi') ? 'Trở thành Huấn luyện viên' : 'Become a Trainer' ?></button>
                    </form>
                <?php endif; ?>

                <!-- 2. Quản lý liên kết Trainer (Chỉ dành cho regular user) -->
                <?php if (($profile['role'] ?? 'regular') === 'regular'): ?>
                    <h4 class="coaching-subtitle"><?= t('profile.trainer.connection_title') ?></h4>

                    <?php if ($trainer_connection): ?>
                        <div class="trainer-conn-card">
                            <div class="trainer-conn-card__main">
                                <div class="trainer-conn-card__avatar">
                                    <?php if (!empty($trainer_connection['profile_image'])): ?>
                                        <img src="<?= BASE_URL . htmlspecialchars($trainer_connection['profile_image'], ENT_QUOTES) ?>" alt="">
                                    <?php else: ?>
                                        <i class="fas fa-user"></i>
                                    <?php endif; ?>
                                </div>
                                <div>
                                    <h3 class="trainer-conn-card__name"><?= htmlspecialchars($trainer_connection['first_name'] . ' ' . $trainer_connection['last_name'], ENT_QUOTES) ?></h3>
                                    <span class="trainer-conn-card__handle"><?= htmlspecialchars($trainer_connection['trainer_name'], ENT_QUOTES) ?></span>
                                    <div class="trainer-conn-card__status">
                                        <?php if ($trainer_connection['status'] === 'pending'): ?>
                                            <span class="trainer-conn-badge"><?= t('profile.trainer.status_pending') ?></span>
                                        <?php else: ?>
                                            <span class="trainer-conn-badge trainer-conn-badge--ok"><i class="fas fa-check"></i> <?= t('profile.trainer.status_connected') ?></span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                            <form method="POST" class="trainer-conn-card__form" data-confirm="<?= htmlspecialchars(t_raw('profile.trainer.disconnect_confirm'), ENT_QUOTES) ?>" data-confirm-danger>
                                <button type="submit" name="disconnect_trainer" class="coaching-revert-btn">
                                    <i class="fas fa-unlink"></i> <?= t('profile.trainer.btn_disconnect') ?>
                                </button>
                            </form>
                        </div>
                    <?php else: ?>
                        <!-- PT Directory: browse onboarded trainers + connect without the handle -->
                        <div class="pt-directory">
                            <?php if (empty($pt_directory)): ?>
                                <div class="friends-empty pt-dir-empty">
                                    <div class="friends-empty__icon"><i class="fas fa-user-plus"></i></div>
                                    <h3><?= t('profile.trainer.no_trainer_title') ?></h3>
                                    <p><?= ($lang === 'vi') ? 'Hiện chưa có huấn luyện viên nào trong hệ thống. Bạn vẫn có thể kết nối bằng handle nếu biết.' : 'No trainers are available yet. You can still connect by handle if you know one.' ?></p>
                                </div>
                            <?php else: ?>
                                <div class="pt-dir-head">
                                    <h4 class="coaching-subtitle"><i class="fas fa-people-arrows"></i> <?= ($lang === 'vi') ? 'Tìm huấn luyện viên' : 'Find a trainer' ?></h4>
                                    <input type="search" id="ptDirSearch" class="pt-dir-search" placeholder="<?= ($lang === 'vi') ? 'Tìm theo tên hoặc chuyên môn...' : 'Search by name or specialty...' ?>">
                                </div>
                                <div class="pt-dir-grid" id="ptDirGrid">
                                    <?php foreach ($pt_directory as $pt):
                                        $ptName   = htmlspecialchars(trim($pt['first_name'] . ' ' . $pt['last_name']), ENT_QUOTES);
                                        $ptHandle = htmlspecialchars($pt['user_name'], ENT_QUOTES);
                                        $ptSpec   = trim($pt['specialties'] ?? '');
                                        $ptBio    = trim($pt['bio'] ?? '');
                                        $cap      = max(1, (int) $pt['max_clients']);
                                        $cnt      = (int) $pt['client_count'];
                                        $isFull   = ($cnt >= $cap);
                                        $searchKey = strtolower($ptName . ' ' . $ptHandle . ' ' . $ptSpec);
                                        $tags = array_slice(array_filter(array_map('trim', preg_split('/[,;]+/', $ptSpec))), 0, 4);
                                    ?>
                                        <article class="pt-dir-card" data-search="<?= htmlspecialchars($searchKey, ENT_QUOTES) ?>">
                                            <div class="pt-dir-card__top">
                                                <div class="pt-dir-card__avatar">
                                                    <?php if (!empty($pt['profile_image'])): ?>
                                                        <img src="<?= BASE_URL . htmlspecialchars($pt['profile_image'], ENT_QUOTES) ?>" alt="">
                                                    <?php else: ?>
                                                        <i class="fas fa-dumbbell"></i>
                                                    <?php endif; ?>
                                                </div>
                                                <div class="pt-dir-card__id">
                                                    <h3><?= $ptName ?></h3>
                                                    <span><?= $ptHandle ?></span>
                                                </div>
                                            </div>
                                            <?php if (!empty($tags)): ?>
                                                <div class="pt-dir-card__tags">
                                                    <?php foreach ($tags as $tag): ?>
                                                        <span class="pt-dir-tag"><?= htmlspecialchars($tag, ENT_QUOTES) ?></span>
                                                    <?php endforeach; ?>
                                                </div>
                                            <?php endif; ?>
                                            <?php if ($ptBio !== ''): ?>
                                                <p class="pt-dir-card__bio"><?= htmlspecialchars($ptBio, ENT_QUOTES) ?></p>
                                            <?php endif; ?>
                                            <div class="pt-dir-card__meta">
                                                <span><i class="fas fa-medal"></i> <?= (int) $pt['experience_years'] ?> <?= ($lang === 'vi') ? 'năm KN' : 'yrs' ?></span>
                                                <span class="<?= $isFull ? 'is-full' : '' ?>"><i class="fas fa-users"></i> <?= $cnt ?>/<?= $cap ?></span>
                                            </div>
                                            <form method="POST" class="pt-dir-card__action">
                                                <input type="hidden" name="trainer_handle" value="<?= $ptHandle ?>">
                                                <?php if ($isFull): ?>
                                                    <button type="button" class="btn-save pt-dir-connect" disabled><?= ($lang === 'vi') ? 'Đã đầy chỗ' : 'Full' ?></button>
                                                <?php else: ?>
                                                    <button type="submit" name="send_trainer_request" class="btn-save pt-dir-connect"><i class="fas fa-link"></i> <?= t('profile.trainer.btn_connect') ?></button>
                                                <?php endif; ?>
                                            </form>
                                        </article>
                                    <?php endforeach; ?>
                                </div>
                                <p class="pt-dir-noresult" id="ptDirNoResult" hidden><?= ($lang === 'vi') ? 'Không tìm thấy HLV phù hợp.' : 'No matching trainers.' ?></p>
                            <?php endif; ?>

                            <!-- Fallback: connect by exact handle -->
                            <details class="pt-dir-handle">
                                <summary><?= ($lang === 'vi') ? 'Hoặc kết nối bằng handle' : 'Or connect by handle' ?></summary>
                                <form method="POST" class="pt-dir-handle__form">
                                    <input type="text" name="trainer_handle" placeholder="e.g. TrainerHung#1234" required>
                                    <button type="submit" name="send_trainer_request" class="btn-save"><?= t('profile.trainer.btn_connect') ?></button>
                                </form>
                            </details>
                        </div>
                    <?php endif; ?>
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
                    <form method="POST" data-confirm="<?= htmlspecialchars(t_raw('profile.archive.confirm'), ENT_QUOTES) ?>" data-confirm-danger class="archive-section__form">
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
        // Tabbed panes: the sidebar menu shows one settings card at a time
        // instead of anchor-scrolling a long page. Pane is preserved across the
        // POST reloads that saving a form triggers (sessionStorage).
        (function () {
            const links = Array.from(document.querySelectorAll('.profile-sidebar .menu-link'));
            const sections = Array.from(document.querySelectorAll('.profile-content .settings-card'));
            if (!links.length || !sections.length) return;
            const ids = sections.map(s => s.id);

            function activate(id, save) {
                if (!ids.includes(id)) id = ids[0];
                sections.forEach(s => s.classList.toggle('is-active', s.id === id));
                links.forEach(l => l.classList.toggle('active', l.getAttribute('href') === '#' + id));
                if (save) { try { sessionStorage.setItem('profilePane', id); } catch (e) {} }
            }

            links.forEach(l => l.addEventListener('click', e => {
                e.preventDefault();
                activate(l.getAttribute('href').slice(1), true);
                window.scrollTo({ top: 0, behavior: 'smooth' });
            }));

            // Keep the user on the same pane after a form save reloads the page
            document.querySelectorAll('.profile-content form').forEach(f => {
                f.addEventListener('submit', () => {
                    const sec = f.closest('.settings-card');
                    if (sec) { try { sessionStorage.setItem('profilePane', sec.id); } catch (e) {} }
                });
            });

            let initial = (location.hash || '').slice(1);
            if (!ids.includes(initial)) {
                try { initial = sessionStorage.getItem('profilePane') || ids[0]; } catch (e) { initial = ids[0]; }
            }
            activate(initial, false);
        })();

        // PT directory: live filter cards by name / handle / specialty
        (function () {
            const search = document.getElementById('ptDirSearch');
            const grid = document.getElementById('ptDirGrid');
            if (!search || !grid) return;
            const cards = Array.from(grid.querySelectorAll('.pt-dir-card'));
            const noResult = document.getElementById('ptDirNoResult');
            search.addEventListener('input', () => {
                const q = search.value.toLowerCase().trim();
                let visible = 0;
                cards.forEach(c => {
                    const match = !q || (c.dataset.search || '').includes(q);
                    c.style.display = match ? '' : 'none';
                    if (match) visible++;
                });
                if (noResult) noResult.hidden = visible !== 0;
            });
        })();
    </script>
</body>

</html>
