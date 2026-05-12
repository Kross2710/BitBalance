<?php
require_once __DIR__ . '/include/init.php';
require_once __DIR__ . '/include/handlers/log_attempt.php';
require_once __DIR__ . '/include/db_config.php';
if (!isset($_SESSION['user'])) {
    header("Location: login.php");
    exit;
}
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = trim($_POST['title'] ?? '');
    $content = trim($_POST['content'] ?? '');
    $category = trim($_POST['category'] ?? '');
    $userId = $_SESSION['user']['user_id'];
    $imagePath = null;

    // Handle image upload
    if (!empty($_FILES['image']['name']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
        $uploadDir = 'uploads/';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0777, true);
        }
        $imageName = time() . '_' . basename($_FILES['image']['name']);
        $targetPath = $uploadDir . $imageName;
        if (move_uploaded_file($_FILES['image']['tmp_name'], $targetPath)) {
            $imagePath = $targetPath;
        }
    }

    // Validate and insert into database
    if ($title && $content && $category) {
        $stmt = $pdo->prepare("INSERT INTO forumPost (user_id, title, content, category, image_path, date_posted, status)
                               VALUES (?, ?, ?, ?, ?, NOW(), 'active')");
        $stmt->execute([$userId, $title, $content, $category, $imagePath]);
        $newPostId = $pdo->lastInsertId();

        // Log the new post creation
        log_attempt($pdo, $userId, 'create', "User $userId created a new forum post with ID $newPostId", 'forumPost', null);

        header("Location: thread.php?id=$newPostId");
        exit;
    } else {
        $error = "All fields are required.";
    }
}
?>
<!DOCTYPE html>
<html lang="en" data-theme="<?php echo isset($_SESSION['user']) ? ($_SESSION['user']['theme_preference'] ?? 'light') : 'light'; ?>">
<head>
    <meta charset="UTF-8">
    <title>Create New Discussion | BitBalance</title>
    <?php
    $pageCss = ['css/forum.css', 'css/pages/new-topic.css'];
    include PROJECT_ROOT . 'views/head_css.php';
    ?>
</head>
<body>
<?php include 'views/header.php'; ?>
<main class="forum-page">
    <div class="thread-container">
        <h2>Create New Discussion</h2>
        <?php if ($error): ?>
            <div class="error-message"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>
        <form method="post" enctype="multipart/form-data" class="new-topic-form">
            <label for="title">Title:</label>
            <input type="text" id="title" name="title" required placeholder="Enter a descriptive title...">
            
            <label for="category">Category:</label>
            <select name="category" id="category" required>
                <option value="">Select a category</option>
                <option value="general">General</option>
                <option value="help">Help</option>
                <option value="ideas">Ideas</option>
                <option value="feedback">Feedback</option>
            </select>
            
            <label for="content">Content:</label>
            <textarea id="content" name="content" required placeholder="What's on your mind?"></textarea>
            
            <label for="image">Attach Image (optional):</label>
            <input type="file" id="image" name="image" accept="image/*">
            
            <button type="submit">Post Topic</button>
        </form>
    </div>
</main>
<?php include 'views/footer.php'; ?>
</body>
</html>