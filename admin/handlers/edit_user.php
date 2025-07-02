<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/../../include/handlers/log_attempt.php';
$admin_id = $_SESSION['user']['user_id'] ?? null;

// Process form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $user_id = $_POST['user_id'] ?? null;
    $first_name = trim($_POST['first_name'] ?? '');
    $last_name = trim($_POST['last_name'] ?? '');
    $username = trim($_POST['username'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $role = $_POST['role'] ?? 'regular'; // Default to 'regular'
    $status = $_POST['status'] ?? 'active'; // Default to 'active'

    // Validate inputs
    if (empty($first_name) || empty($last_name) || empty($username) || empty($email)) {
        $error_message = "Please fill in all fields.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error_message = "Please enter a valid email address.";
    } else {
        try {
            // Update core user fields
            $stmt = $pdo->prepare("
                UPDATE user
                SET first_name = ?, last_name = ?, user_name = ?, email = ?, role = ?
                WHERE user_id = ?
            ");
            $stmt->execute([$first_name, $last_name, $username, $email, $role, $user_id]);

            // Update status in the userStatus table
            $statusStmt = $pdo->prepare("
                UPDATE userStatus
                SET status = ?
                WHERE user_id = ?
            ");
            $statusStmt->execute([$status, $user_id]);

            // Log the update attempt
            log_attempt($pdo, $admin_id, 'edit_user', 'Admin ' . $admin_id . ' updated user ' . $user_id, 'user', $user_id);

            // Redirect to the user list page with a success message
            $success_message = "User updated successfully";
            header("Location: ../admin/admin-users.php?success=" . urlencode($success_message));
            exit;

        } catch (PDOException $e) {
            $error_message = "Error updating user: " . $user_id . " " . $e->getMessage();
            header("Location: ../admin/admin-users.php?error=" . urlencode($error_message));
        }
    }
}
?>