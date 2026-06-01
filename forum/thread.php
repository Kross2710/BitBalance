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
<html lang="en" data-theme="<?php echo isset($_SESSION['user']) ? ($_SESSION['user']['theme_preference'] ?? 'system') : 'system'; ?>">
<head>
    <meta charset="UTF-8">
    <title><?= htmlspecialchars($post['title']) ?> | BitBalance</title>
    <?php
    $pageCss = ['css/forum.css', 'css/pages/thread.css'];
    include PROJECT_ROOT . 'views/head_css.php';
    ?>
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