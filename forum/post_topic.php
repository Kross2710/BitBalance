<?php
require_once __DIR__ . '/include/init.php';
require_once __DIR__ . '/include/handlers/log_attempt.php';
require_once __DIR__ . '/include/db_config.php';

if (!isset($_SESSION['user'])) {
    header("Location: login.php");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = trim($_POST['title'] ?? '');
    $content = trim($_POST['content'] ?? '');
    $category = trim($_POST['category'] ?? '');
    $userId = $_SESSION['user']['user_id'];
    $imagePath = null;

    
    if (!empty($_FILES['image']['name']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
        $uploadDir = __DIR__ . '/uploads/';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0777, true);
        }

        $fileName = time() . '_' . basename($_FILES['image']['name']);
        $targetPath = $uploadDir . $fileName;

        if (move_uploaded_file($_FILES['image']['tmp_name'], $targetPath)) {
            $imagePath = 'uploads/' . $fileName; 
        } else {
            echo "<p style='color:red;'>Failed to move uploaded file to target directory.</p>";
        }
    }

    
    if ($title && $content && $category) {
        $stmt = $pdo->prepare("INSERT INTO forumPost (user_id, title, content, category, image_path, date_posted, status)
                               VALUES (?, ?, ?, ?, ?, NOW(), 'active')");
        $stmt->execute([$userId, $title, $content, $category, $imagePath]);
        $newPostId = $pdo->lastInsertId();

        // Log the new post creation
        log_attempt($pdo, $userId, 'create', "User $userId created a new forum post with ID $newPostId", 'forumPost', null);
        $stmt = $pdo->prepare("");
        header("Location: thread.php?id=$newPostId");
        exit;
    } else {
        echo "<p style='color:red; text-align:center;'>All fields are required.</p>";
    }
}
?>



