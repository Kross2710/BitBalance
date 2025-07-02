<?php
require_once __DIR__ . '/include/init.php';
require_once __DIR__ . '/include/handlers/log_attempt.php';
require_once __DIR__ . '/include/db_config.php';

$search = $_GET['search'] ?? '';
$sort = $_GET['sort'] ?? 'newest';

$query = "
    SELECT forumPost.*, user.user_name 
    FROM forumPost 
    JOIN user ON forumPost.user_id = user.user_id 
    WHERE forumPost.status != 'archived'
";

$params = [];

if (!empty($search)) {
    $query .= " AND title LIKE :search";
    $params[':search'] = "%$search%";
}

$order = "date_posted DESC";
if ($sort === 'oldest') {
    $order = "date_posted ASC";
}

$query .= " ORDER BY $order";

try {
    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $posts = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die("Error fetching forum posts: " . $e->getMessage());
}

$isLoggedIn = isset($_SESSION['user']);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Forum | BitBalance</title>
    <link rel="stylesheet" href="css/style.css">
    <link rel="stylesheet" href="css/forum.css">
    <style>
        
        .forum-container {
            max-width: 800px;
            margin: 0 auto;
            padding: 2rem;
        }

        .forum-controls {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin: 20px auto;
            flex-wrap: wrap;
        }

        .forum-controls-form {
            display: flex;
            gap: 10px;
            justify-content: center;
            flex-wrap: wrap;
        }

        .forum-posts {
            margin-top: 30px;
        }

.forum-post {
    border: 1px solid #ddd;
    border-radius: 10px;
    padding: 15px;
    margin: 20px 0;
    background-color: #fff;
}

.post-meta {
    font-size: 0.9em;
    color: #777;
    display: flex;
    align-items: center;
    margin-top: 10px;
    gap: 8px;
}

.user-icon {
    width: 25px;
    height: 25px;
    border-radius: 50%;
}

.start-topic {
    background-color: #007bff;
    color: white;
    padding: 10px 15px;
    text-decoration: none;
    border-radius: 5px;
}

.start-topic:hover {
    background-color: #0056b3;
}

.search-bar {
    padding: 8px;
    border-radius: 5px;
    border: 1px solid #ccc;
}

img.post-image {
    max-width: 100%;
    margin-top: 10px;
    border-radius: 6px;
}

.archive-btn {
    margin-top: 10px;
}
    </style>
</head>

<body>
    <?php include 'views/header.php'; ?>

    <main class="forum-container">
        <section class="forum-controls">
            <a href="new_topic.php" class="start-topic">+ Start New Topic</a>

            <form method="get" action="forum.php" class="forum-controls-form">
                <input type="text" name="search" placeholder="🔍 Search..." value="<?= htmlspecialchars($search) ?>"
                    class="search-bar">

                <select name="sort">
                    <option value="newest" <?= $sort === 'newest' ? 'selected' : '' ?>>Newest</option>
                    <option value="oldest" <?= $sort === 'oldest' ? 'selected' : '' ?>>Oldest</option>
                </select>

                <button type="submit">Apply</button>
            </form>
        </section>

        <section class="forum-posts">
            <?php if (empty($posts)): ?>
                <p>No posts found.</p>
            <?php else: ?>
                <?php foreach ($posts as $post): ?>
                    <?php
                    $likeStmt = $pdo->prepare("SELECT COUNT(*) FROM forumLike WHERE type = 'post' AND target_id = ?");
                    $likeStmt->execute([$post['post_id']]);
                    $likeCount = $likeStmt->fetchColumn();
                    ?>
                    <div class="forum-post">
                        <h2><a href="thread.php?id=<?= $post['post_id'] ?>"><?= htmlspecialchars($post['title']) ?></a></h2>
                        <p><?= nl2br(htmlspecialchars($post['content'])) ?></p>

                        <?php if (!empty($post['image_path'])): ?>
                            <img src="<?= htmlspecialchars($post['image_path']) ?>" alt="Post Image" class="post-image">
                        <?php endif; ?>

                    <div class="post-meta">
                        <img src="images/default-avatar.png" alt="User Icon" class="user-icon">
                        <span>By <?= htmlspecialchars($post['user_name']) ?></span>
                        <span>• <?= htmlspecialchars($post['date_posted']) ?></span>
                        <span>• 👍 <?= $likeCount ?></span>
                    </div>
                    <?php if ($isLoggedIn): ?>
    <form action="like.php" method="POST" style="display:inline;">
    <input type="hidden" name="type" value="post">
        <input type="hidden" name="id" value="<?= $post['post_id'] ?>">
        <button type="submit">👍 Like</button>
    </form>
<?php endif; ?>
                    <?php if ($isLoggedIn && $_SESSION['user']['user_id'] == $post['user_id']): ?>
                        <form method="post" action="soft_delete.php" class="archive-btn">
                            <input type="hidden" name="type" value="post">
                            <input type="hidden" name="id" value="<?= $post['post_id'] ?>">
                            <button type="submit">Archive Post</button>
                        </form>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </section>
</main>

    <?php include 'views/footer.php'; ?>
</body>

</html>