<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/../include/init.php';
require_once __DIR__ . '/../include/db_config.php';
require_once __DIR__ . '/handlers/edit_comment.php';

if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'admin') {
    http_response_code(403);
    exit('Access denied');
}

$comment_id = (int)$_GET['comment_id'] ?? null;

$stmt = $pdo->prepare("SELECT content, status FROM forumComment WHERE comment_id = ?");
$stmt->execute([$comment_id]);
$comment = $stmt->fetch(PDO::FETCH_ASSOC);

$content = $comment["content"] ?? '';
$status = $comment["status"] ?? '';
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Forum Post Edit</title>
    <link rel="stylesheet" href="../css/style.css">
    <link rel="stylesheet" href="../css/login.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <style>
        .form-row {
            display: flex;
            gap: 10px;
        }

        .form-row input {
            flex: 1;
        }

        .login-button:disabled:hover {
            background-color: #6c757d !important;
        }

        select {
            font-size: 14px;
            padding: 10px;
            border: 1px solid #333;
            border-radius: 4px;
        }

        textarea {
            width: 100%;
            padding: 10px;
            border: 1px solid #333;
            border-radius: 4px;
            font-size: 14px;
            resize: vertical;
        }

        .back-link {
            position: absolute;
            top: 20px;
            left: 20px;
            color: black;
            text-decoration: none;
        }

        .back-link:hover {
            text-decoration: underline;
        }
    </style>
</head>

<body>
    <!-- Back arrow -->
    <a href="admin-forums.php" class="back-link">
        <i class="fa-solid fa-arrow-left"></i> Back to Forums List
    </a>

    <div class="container">
        <div class="form-section">
            <h2>Comment Edit</h2>

            <?php if (!empty($error_message)): ?>

                <div class="error-message"
                    style="color: #d32f2f; margin-bottom: 15px; padding: 12px; background-color: #ffebee; border: 1px solid #e57373; border-radius: 5px; font-weight: bold;">
                    <i class="fas fa-exclamation-triangle" style="margin-right: 8px;"></i>

                    <?php echo htmlspecialchars($error_message); ?>
                </div>
            <?php endif; ?>

            <?php if (!empty($success_message)): ?>

                <div class="success-message"
                    style="color: #2e7d32; margin-bottom: 15px; padding: 12px; background-color: #e8f5e8; border: 1px solid #4caf50; border-radius: 5px; font-weight: bold;">
                    <i class="fas fa-check-circle" style="margin-right: 8px;"></i>

                    <?php echo htmlspecialchars($success_message); ?>
                </div>
            <?php endif; ?>

            <form action="edit-comment.php" method="POST">
                <input type="hidden" name="comment_id" value="<?php echo (int) $comment_id ?? ''; ?>">

                <textarea placeholder="Content" name="content" required
                    style="flex: 1; min-height: 100px;"><?php echo htmlspecialchars($content ?? ''); ?></textarea>

                <select name="status" required>
                    <option value="active" <?php echo $status === 'active' ? 'selected' : ''; ?>>Active</option>
                    <option value="archived" <?php echo $status === 'archived' ? 'selected' : ''; ?>>Archived</option>
                </select>

                <button type="submit" class="login-button" id="signup-btn">Edit Post</button>

            </form>
        </div>
        <div class="side-section">
            <img src="../images/food.jpg" alt="Food Image">
        </div>
    </div>
</body>

</html>