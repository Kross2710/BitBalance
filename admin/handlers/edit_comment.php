<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/../../include/handlers/log_attempt.php';
$admin_id = $_SESSION['user']['user_id'] ?? null;

// Process form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $comment_id = $_POST['comment_id'] ?? null;
    $content = trim($_POST['content'] ?? '');
    $status = $_POST['status'] ?? 'active'; // Default to 'active' if not set

    // Validate inputs
    if (empty($content) || empty($status)) {
        $error_message = "Please fill in all fields.";
    } else {
        try {
            // Update core product fields
            $stmt = $pdo->prepare("
                UPDATE forumComment
                SET content = ?, status = ?
                WHERE comment_id = ?
            ");
            $stmt->execute([$content, $status ,$comment_id]);

            // Log the update attempt
            log_attempt($pdo, $admin_id, 'edit_comment', 'Admin ' . $admin_id . ' updated comment ' . $comment_id, 'comment', $comment_id);

            // Redirect to the user list page with a success message
            $success_message = "Comment updated successfully";
            header("Location: ../admin/admin-forums.php?csuccess=" . urlencode($success_message));
            exit;

        } catch (PDOException $e) {
            $error_message = "Error updating comment: " . $comment_id . " " . $e->getMessage();
            header("Location: ../admin/admin-forums.php?cerror=" . urlencode($error_message));
        }
    }
}
?>