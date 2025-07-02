<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/../../include/handlers/log_attempt.php';
$admin_id = $_SESSION['user']['user_id'] ?? null;

// Process form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $post_id = $_POST['post_id'] ?? null;
    $title = trim($_POST['title'] ?? '');
    $content = trim($_POST['content'] ?? '');
    $status = $_POST['status'] ?? 'active'; // Default to 'active' if not set

    // Validate inputs
    if (empty($title) || empty($content) || empty($status)) {
        $error_message = "Please fill in all fields.";
    } else {
        try {
            // Update core product fields
            $stmt = $pdo->prepare("
                UPDATE forumPost
                SET title = ?, content = ?, status = ?
                WHERE post_id = ?
            ");

            $stmt->execute([$title, $content, $status ,$post_id]);

            // Log the update attempt
            log_attempt($pdo, $admin_id, 'edit_post', 'Admin ' . $admin_id . ' updated post ' . $post_id, 'product', $post_id);

            // Redirect to the user list page with a success message
            $success_message = "Post updated successfully";
            header("Location: ../admin/admin-forums.php?success=" . urlencode($success_message));
            exit;

        } catch (PDOException $e) {
            $error_message = "Error updating post: " . $post_id . " " . $e->getMessage();
            header("Location: ../admin/admin-forums.php?error=" . urlencode($error_message));
        }
    }
}
?>