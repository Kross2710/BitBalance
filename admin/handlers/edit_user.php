<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/../../include/handlers/log_attempt.php';
require_once __DIR__ . '/../../include/csrf.php';

$admin_id = (int) ($_SESSION['user']['user_id'] ?? 0);

// Process form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (!csrf_verify($_POST['csrf_token'] ?? '')) {
        header("Location: ../admin/admin-users.php?error=" . urlencode('Invalid security token.'));
        exit;
    }

    $user_id = (int) ($_POST['user_id'] ?? 0);
    $first_name = trim($_POST['first_name'] ?? '');
    $last_name = trim($_POST['last_name'] ?? '');
    $username = trim($_POST['username'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $role = $_POST['role'] ?? 'regular';
    $status = $_POST['status'] ?? 'active';

    $allowedRoles = ['regular', 'admin'];
    $allowedStatuses = ['active', 'banned', 'archived'];
    if (!in_array($role, $allowedRoles, true)) {
        $error_message = "Invalid role.";
    } elseif (!in_array($status, $allowedStatuses, true)) {
        $error_message = "Invalid status.";
    } elseif ($user_id <= 0) {
        $error_message = "Invalid user.";
    } elseif (empty($first_name) || empty($last_name) || empty($username) || empty($email)) {
        $error_message = "Please fill in all fields.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error_message = "Please enter a valid email address.";
    } elseif ($user_id === $admin_id && $role !== 'admin') {
        $error_message = "You cannot demote your own admin account.";
    } elseif ($user_id === $admin_id && $status !== 'active') {
        $error_message = "You cannot ban or archive your own account.";
    } else {
        try {
            // Uniqueness checks
            $dup = $pdo->prepare("SELECT user_id FROM user WHERE email = ? AND user_id <> ? LIMIT 1");
            $dup->execute([$email, $user_id]);
            if ($dup->fetchColumn()) {
                $error_message = "Email already used by another user.";
            } else {
                $dup = $pdo->prepare("SELECT user_id FROM user WHERE user_name = ? AND user_id <> ? LIMIT 1");
                $dup->execute([$username, $user_id]);
                if ($dup->fetchColumn()) {
                    $error_message = "Username already used by another user.";
                }
            }

            if (empty($error_message)) {
                $stmt = $pdo->prepare("
                    UPDATE user
                    SET first_name = ?, last_name = ?, user_name = ?, email = ?, role = ?
                    WHERE user_id = ?
                ");
                $stmt->execute([$first_name, $last_name, $username, $email, $role, $user_id]);

                $statusStmt = $pdo->prepare("
                    UPDATE userStatus
                    SET status = ?
                    WHERE user_id = ?
                ");
                $statusStmt->execute([$status, $user_id]);

                log_attempt($pdo, $admin_id, 'edit_user', 'Admin ' . $admin_id . ' updated user ' . $user_id, 'user', $user_id);

                header("Location: ../admin/admin-users.php?success=" . urlencode('User updated successfully'));
                exit;
            }
        } catch (PDOException $e) {
            error_log('edit_user error: ' . $e->getMessage());
            $error_message = "Error updating user.";
        }
    }
}
?>