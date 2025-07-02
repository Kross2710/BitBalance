<?php
require_once __DIR__ . '/include/init.php';
require_once __DIR__ . '/include/handlers/log_attempt.php';
require_once __DIR__ . '/include/db_config.php';

if ($isLoggedIn) {
    // Log the user activity
    log_attempt($pdo, $user['user_id'], 'view', 'User ' . $user['user_id'] . ' viewed a thread', 'forum', null);
} else {
    header("Location: login.php");
    exit;
}

$id = $_GET['id'] ?? 0;

$postStmt = $pdo->prepare("SELECT forumPost.*, user.user_name FROM forumPost JOIN user ON forumPost.user_id = user.user_id WHERE post_id = ? AND forumPost.status != 'archived'");
$postStmt->execute([$id]);
$post = $postStmt->fetch(PDO::FETCH_ASSOC);

if (!$post) {
    die("Post not found or archived.");
}

$comments = $pdo->prepare("SELECT forumComment.*, user.user_name FROM forumComment JOIN user ON forumComment.user_id = user.user_id WHERE post_id = ? AND forumComment.status != 'archived' ORDER BY date_posted ASC");
$comments->execute([$id]);
$replies = $comments->fetchAll(PDO::FETCH_ASSOC);


if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['reply'])) {
    $reply = $_POST['reply'];
    $userId = $_SESSION['user']['user_id'] ?? null;
    $time = date('Y-m-d H:i:s');

    $pdo->prepare("INSERT INTO forumComment (post_id, user_id, content, date_posted) VALUES (?, ?, ?, ?)")
        ->execute([$id, $userId, $reply, $time]);

    // Log the new comment creation
    log_attempt($pdo, $userId, 'create', "User $userId replied to post $id", 'forumComment', null);

    header("Location: thread.php?id=$id");
    exit;
}


if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['like_comment_id'])) {
    $userId = $_SESSION['user']['user_id'] ?? null;
    $commentId = $_POST['like_comment_id'];

    $pdo->prepare("INSERT IGNORE INTO forumLike (user_id, type, target_id) VALUES (?, 'comment', ?)")
        ->execute([$userId, $commentId]);

    // Log the like action
    log_attempt($pdo, $userId, 'like', "User $userId liked comment $commentId", 'forumLike', null);

    header("Location: thread.php?id=$id");
    exit;
}
?>
<!DOCTYPE html>
<html lang="en" data-theme="<?php echo isset($_SESSION['user']) ? ($_SESSION['user']['theme_preference'] ?? 'light') : 'light'; ?>">
<head>
    <meta charset="UTF-8">
    <title><?= htmlspecialchars($post['title']) ?> | BitBalance</title>
    <link rel="stylesheet" href="css/style.css">
    <link rel="stylesheet" href="css/forum.css">
    <style>
        /* Dark mode variables */
        :root {
            --bg-color: #ffffff;
            --card-bg: #ffffff;
            --text-color: #212529;
            --text-muted: #6c757d;
            --border-color: #ddd;
            --primary-color: #007bff;
            --primary-hover: #0056b3;
            --shadow: 0 2px 8px rgba(0,0,0,0.1);
            --reply-bg: #f8f9fa;
            --input-bg: #ffffff;
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
            --reply-bg: #1e1e1e;
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
            max-width: 800px;
            margin: 0 auto;
            padding: 2rem;
            min-height: 80vh;
        }

        .thread-container {
            background-color: var(--card-bg);
            border-radius: 12px;
            padding: 30px;
            box-shadow: var(--shadow);
            border: 1px solid var(--border-color);
            transition: all 0.3s ease;
        }

        [data-theme="dark"] .thread-container {
            background-color: var(--card-bg) !important;
            border-color: var(--border-color) !important;
        }

        .post-box {
            background-color: var(--card-bg);
            padding: 25px;
            margin-bottom: 30px;
            border: 2px solid var(--border-color);
            border-radius: 10px;
            box-shadow: var(--shadow);
            transition: all 0.3s ease;
        }

        [data-theme="dark"] .post-box {
            background-color: var(--card-bg) !important;
            border-color: var(--border-color) !important;
        }

        .post-box h2 {
            color: var(--text-color);
            margin: 0 0 15px 0;
            font-size: 1.8rem;
            line-height: 1.3;
        }

        .post-meta {
            color: var(--text-muted);
            font-size: 0.9rem;
            margin-bottom: 20px;
            font-style: italic;
        }

        .post-box p {
            color: var(--text-color);
            line-height: 1.6;
            margin: 15px 0;
        }

        .post-box img {
            max-width: 100%;
            height: auto;
            margin-top: 15px;
            border-radius: 8px;
            box-shadow: var(--shadow);
        }

        h3 {
            color: var(--text-color);
            margin: 30px 0 20px 0;
            padding-bottom: 10px;
            border-bottom: 2px solid var(--border-color);
            font-size: 1.4rem;
        }

        .reply-box {
            background-color: var(--reply-bg);
            padding: 20px;
            margin-bottom: 15px;
            border: 1px solid var(--border-color);
            border-radius: 8px;
            border-left: 4px solid var(--primary-color);
            transition: all 0.3s ease;
        }

        [data-theme="dark"] .reply-box {
            background-color: var(--reply-bg) !important;
            border-color: var(--border-color) !important;
        }

        .reply-author {
            font-weight: bold;
            color: var(--primary-color);
            margin-bottom: 8px;
            font-size: 1rem;
        }

        .reply-box p {
            color: var(--text-color);
            margin: 10px 0;
            line-height: 1.5;
        }

        .reply-box small {
            color: var(--text-muted);
            font-size: 0.85rem;
            display: block;
            margin: 10px 0;
        }

        button, .delete-button {
            background-color: var(--primary-color);
            color: white;
            border: none;
            padding: 8px 16px;
            border-radius: 6px;
            cursor: pointer;
            font-size: 0.9rem;
            margin-right: 8px;
            margin-top: 8px;
            transition: all 0.3s ease;
            display: inline-block;
        }

        button:hover, .delete-button:hover {
            background-color: var(--primary-hover);
            transform: translateY(-1px);
        }

        .delete-button {
            background-color: #dc3545;
        }

        .delete-button:hover {
            background-color: #c82333;
        }

        form textarea {
            width: 100%;
            min-height: 100px;
            padding: 15px;
            border: 2px solid var(--border-color);
            border-radius: 8px;
            background-color: var(--input-bg);
            color: var(--text-color);
            font-family: inherit;
            font-size: 1rem;
            resize: vertical;
            transition: all 0.3s ease;
            box-sizing: border-box;
            margin-bottom: 15px;
        }

        [data-theme="dark"] form textarea {
            background-color: var(--input-bg) !important;
            border-color: var(--border-color) !important;
        }

        form textarea:focus {
            outline: none;
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px rgba(74, 126, 227, 0.1);
        }

        form textarea::placeholder {
            color: var(--text-muted);
        }

        .thread-container > p {
            text-align: center;
            padding: 20px;
            background-color: var(--reply-bg);
            border-radius: 8px;
            border: 1px solid var(--border-color);
            margin-top: 20px;
        }

        [data-theme="dark"] .thread-container > p {
            background-color: var(--reply-bg) !important;
        }

        .thread-container > p a {
            color: var(--primary-color);
            text-decoration: none;
            font-weight: 600;
        }

        .thread-container > p a:hover {
            text-decoration: underline;
        }

        form {
            margin-top: 20px;
        }

        form[style*="inline"] {
            display: inline !important;
            margin-top: 5px;
        }

        @media (max-width: 768px) {
            .forum-page {
                padding: 1rem;
            }
            
            .thread-container {
                padding: 20px;
            }
            
            .post-box {
                padding: 20px;
            }
            
            .reply-box {
                padding: 15px;
            }
            
            .post-box h2 {
                font-size: 1.5rem;
            }
        }
    </style>
</head>
<body>
<?php include 'views/header.php'; ?>

<main class="forum-page">
    <div class="thread-container">
        <div class="post-box">
            <h2><?= htmlspecialchars($post['title']) ?></h2>
            <p class="post-meta">by <em><?= htmlspecialchars($post['user_name']) ?></em> • <?= $post['date_posted'] ?></p>
            <p><?= nl2br(htmlspecialchars($post['content'])) ?></p>

            <?php if (!empty($post['image_path'])): ?>
                <img src="<?= htmlspecialchars($post['image_path']) ?>" alt="Post Image">
            <?php endif; ?>

            <?php if (isset($_SESSION['user']) && $_SESSION['user']['user_id'] == $post['user_id']): ?>
                <form method="post" action="soft_delete.php">
                    <input type="hidden" name="type" value="post">
                    <input type="hidden" name="id" value="<?= $post['post_id'] ?>">
                    <button type="submit" name="delete" class="delete-button">Archive Post</button>
                </form>
            <?php endif; ?>
        </div>

        <h3>Replies</h3>

        <?php foreach ($replies as $r): ?>
            <?php
                $likeStmt = $pdo->prepare("SELECT COUNT(*) FROM forumLike WHERE type = 'comment' AND target_id = ?");
                $likeStmt->execute([$r['comment_id']]);
                $commentLikes = $likeStmt->fetchColumn();
            ?>
            <div class="reply-box">
                <div class="reply-author"><?= htmlspecialchars($r['user_name']) ?></div>
                <p><?= nl2br(htmlspecialchars($r['content'])) ?></p>
                <small><?= $r['date_posted'] ?> • 👍 <?= $commentLikes ?></small>

                <?php if (isset($_SESSION['user'])): ?>
                    <form method="post" style="display:inline;">
                        <input type="hidden" name="like_comment_id" value="<?= $r['comment_id'] ?>">
                        <button type="submit">Like</button>
                    </form>
                <?php endif; ?>

                <?php if (isset($_SESSION['user']) && $_SESSION['user']['user_id'] == $r['user_id']): ?>
                    <form method="post" action="soft_delete.php" style="display:inline;">
                        <input type="hidden" name="type" value="comment">
                        <input type="hidden" name="id" value="<?= $r['comment_id'] ?>">
                        <button type="submit" name="delete" class="delete-button">Archive</button>
                    </form>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>

        <?php if (isset($_SESSION['user'])): ?>
            <form method="post">
                <textarea name="reply" required placeholder="Write your reply..."></textarea>
                <button type="submit">Post Reply</button>
            </form>
        <?php else: ?>
            <p><a href="login.php">Log in</a> to reply.</p>
        <?php endif; ?>
    </div>
</main>

<?php include 'views/footer.php'; ?>
</body>
</html>