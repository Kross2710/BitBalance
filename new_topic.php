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
    <link rel="stylesheet" href="css/style.css">
    <link rel="stylesheet" href="css/forum.css">
    <style>
        :root {
            --bg-color: #ffffff;
            --card-bg: #ffffff;
            --text-color: #212529;
            --text-muted: #6c757d;
            --border-color: #ddd;
            --primary-color: #007bff;
            --primary-hover: #0056b3;
            --shadow: 0 2px 8px rgba(0,0,0,0.1);
            --error-color: #dc3545;
        }

        [data-theme="dark"] {
            --bg-color: #1a1a1a;
            --card-bg: #2d2d2d;
            --text-color: #ffffff;
            --text-muted: #adb5bd;
            --border-color: #495057;
            --primary-color: #4a7ee3;
            --primary-hover: #3d6ac7;
            --shadow: 0 4px 16px rgba(0,0,0,0.4);
            --error-color: #ff6b6b;
            --input-bg: #1e1e1e;
        }

        body {
            background-color: var(--bg-color);
            color: var(--text-color);
            transition: all 0.3s ease;
        }

        [data-theme="dark"] header {
            background: var(--card-bg) !important;
            border-bottom: 1px solid var(--border-color);
        }
        
        [data-theme="dark"] .logo,
        [data-theme="dark"] .menu a,
        [data-theme="dark"] .cart-link,
        [data-theme="dark"] .login-signup,
        [data-theme="dark"] .hamburger {
            color: var(--text-color) !important;
        }
        
        [data-theme="dark"] .menu a.active,
        [data-theme="dark"] .menu a:hover {
            color: var(--primary-color) !important;
        }

        .forum-page {
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 80vh;
            padding: 40px 20px;
            font-family: Arial, sans-serif;
        }

        .thread-container {
            width: 100%;
            max-width: 600px;
            background-color: var(--card-bg);
            padding: 40px;
            border-radius: 12px;
            box-shadow: var(--shadow);
            border: 1px solid var(--border-color);
            transition: all 0.3s ease;
        }

        [data-theme="dark"] .thread-container {
            background-color: var(--card-bg) !important;
            border-color: var(--border-color) !important;
        }

        [data-theme="dark"] .forum-page {
            background-color: var(--bg-color) !important;
        }

        [data-theme="dark"] .new-topic-form {
            background-color: transparent !important;
        }

        .thread-container h2 {
            text-align: center;
            font-weight: bold;
            margin-bottom: 30px;
            color: var(--text-color);
            font-size: 1.8rem;
        }

        .error-message {
            color: var(--error-color);
            text-align: center;
            margin-bottom: 20px;
            padding: 12px;
            background-color: rgba(255, 107, 107, 0.1);
            border: 1px solid var(--error-color);
            border-radius: 6px;
            font-weight: 500;
        }

        [data-theme="dark"] .error-message {
            background-color: rgba(255, 107, 107, 0.15);
        }

        .new-topic-form {
            width: 100%;
            display: flex;
            flex-direction: column;
            gap: 20px;
        }

        .new-topic-form label {
            color: var(--text-color);
            font-weight: 600;
            margin-bottom: 5px;
            font-size: 1rem;
        }

        .new-topic-form input,
        .new-topic-form select,
        .new-topic-form textarea {
            width: 100%;
            padding: 12px 16px;
            font-size: 1rem;
            border: 2px solid var(--border-color);
            border-radius: 8px;
            background-color: var(--input-bg, var(--card-bg));
            color: var(--text-color);
            transition: all 0.3s ease;
            box-sizing: border-box;
        }

        [data-theme="dark"] .new-topic-form input,
        [data-theme="dark"] .new-topic-form select,
        [data-theme="dark"] .new-topic-form textarea {
            background-color: var(--input-bg);
            border-color: var(--border-color);
        }

        .new-topic-form input:focus,
        .new-topic-form select:focus,
        .new-topic-form textarea:focus {
            outline: none;
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px rgba(74, 126, 227, 0.1);
        }

        .new-topic-form textarea {
            min-height: 120px;
            resize: vertical;
            font-family: inherit;
        }

        .new-topic-form textarea::placeholder {
            color: var(--text-muted);
        }

        .new-topic-form input[type="file"] {
            padding: 8px 12px;
            border: 2px dashed var(--border-color);
            background-color: var(--input-bg, rgba(74, 126, 227, 0.05));
            cursor: pointer;
        }

        .new-topic-form input[type="file"]:hover {
            border-color: var(--primary-color);
            background-color: rgba(74, 126, 227, 0.1);
        }

        [data-theme="dark"] .new-topic-form input[type="file"] {
            background-color: var(--input-bg);
            color: var(--text-color);
        }

        [data-theme="dark"] .new-topic-form input[type="file"]:hover {
            background-color: rgba(74, 126, 227, 0.15);
        }

        .new-topic-form button {
            width: 100%;
            padding: 15px;
            font-size: 1.1rem;
            font-weight: 600;
            background-color: var(--primary-color);
            color: white;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            transition: all 0.3s ease;
            margin-top: 10px;
        }

        .new-topic-form button:hover {
            background-color: var(--primary-hover);
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(74, 126, 227, 0.3);
        }

        .new-topic-form button:active {
            transform: translateY(0);
        }

        .new-topic-form select option {
            background-color: var(--input-bg, var(--card-bg));
            color: var(--text-color);
        }

        [data-theme="dark"] .new-topic-form select option {
            background-color: var(--input-bg);
            color: var(--text-color);
        }

        @media (max-width: 768px) {
            .forum-page {
                padding: 20px 15px;
            }
            
            .thread-container {
                padding: 30px 20px;
            }
            
            .thread-container h2 {
                font-size: 1.5rem;
            }
        }
    </style>
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