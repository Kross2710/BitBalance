<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/../include/init.php';
require_once __DIR__ . '/../include/db_config.php';
require_once __DIR__ . '/handlers/admin_data.php';
require_once __DIR__ . '/../include/handlers/log_attempt.php';
require_once __DIR__ . '/../include/csrf.php';

if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'admin') {
    http_response_code(403);
    exit('Access denied');
}

$activePage = 'users';
$user_id = (int) ($_GET['user_id'] ?? 0);

if ($user_id <= 0) {
    header('Location: admin-users.php?error=' . urlencode('Missing user id'));
    exit;
}

$stmt = $pdo->prepare("
    SELECT u.user_id, u.user_name, u.first_name, u.last_name, u.email,
           u.role, u.timeCreated, u.last_login, u.profile_image,
           s.status, s.failed_attempts, s.locked_until,
           s.profile_bio, s.logging_streak, s.longest_logging_streak,
           s.last_logging_date
    FROM user u
    LEFT JOIN userStatus s ON s.user_id = u.user_id
    WHERE u.user_id = ?
");
$stmt->execute([$user_id]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user) {
    header('Location: admin-users.php?error=' . urlencode('User not found'));
    exit;
}

$admin_id = (int) $_SESSION['user']['user_id'];
log_attempt($pdo, $admin_id, 'view', 'Admin ' . $admin_id . ' viewed user ' . $user_id . ' detail', 'user', $user_id);

// Latest intake logs
$stmt = $pdo->prepare("SELECT food_item, meal_category, calories, protein, carbs, fat, date_intake
                       FROM intakeLog WHERE user_id = ? ORDER BY date_intake DESC LIMIT 10");
$stmt->execute([$user_id]);
$intakes = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Latest posts
$stmt = $pdo->prepare("SELECT post_id, title, status, date_posted
                       FROM forumPost WHERE user_id = ? ORDER BY date_posted DESC LIMIT 10");
$stmt->execute([$user_id]);
$posts = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Latest comments
$stmt = $pdo->prepare("SELECT comment_id, post_id, content, status, date_posted
                       FROM forumComment WHERE user_id = ? ORDER BY date_posted DESC LIMIT 10");
$stmt->execute([$user_id]);
$comments = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Login attempts (by email — login_attempts has no user_id FK)
$stmt = $pdo->prepare("SELECT ip_address, success, attempted_at
                       FROM login_attempts WHERE email = ? ORDER BY attempted_at DESC LIMIT 15");
$stmt->execute([$user['email']]);
$loginAttempts = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Weight log
$stmt = $pdo->prepare("SELECT weight, date_logged FROM weight_log
                       WHERE user_id = ? ORDER BY date_logged DESC LIMIT 15");
$stmt->execute([$user_id]);
$weights = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Recent admin actions on this user
$stmt = $pdo->prepare("SELECT a.action_type, a.description, a.created_at, u.user_name
                       FROM activity_log a
                       JOIN user u ON u.user_id = a.user_id
                       WHERE a.target_table IN ('user', 'userStatus') AND a.target_id = ?
                       ORDER BY a.created_at DESC LIMIT 10");
$stmt->execute([$user_id]);
$adminActions = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en" data-theme="<?php echo isset($_SESSION['user']) ? ($_SESSION['user']['theme_preference'] ?? 'system') : 'system'; ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Detail — BitBalance Admin</title>
    <?php include __DIR__ . '/../views/theme-init.php'; ?>
    <link rel="stylesheet" href="../css/style.css">
    <link rel="stylesheet" href="../css/admin.css?v=<?php echo @filemtime(__DIR__ . '/../css/admin.css'); ?>">
    <script src="https://kit.fontawesome.com/b94f65ead2.js" crossorigin="anonymous"></script>
</head>
<body>
    <?php
    include 'views/admin-header.php';
    include 'views/admin-sidebar.php';
    ?>
    <main>
        <div class="main-content">
            <a href="admin-users.php" class="back-link"><i class="fa-solid fa-arrow-left"></i> Back to Users</a>

            <div class="user-header">
                <div class="avatar">
                    <?php if (!empty($user['profile_image'])): ?>
                        <img src="<?php echo htmlspecialchars($user['profile_image']); ?>" alt="">
                    <?php else: ?>
                        <i class="fa-solid fa-user"></i>
                    <?php endif; ?>
                </div>
                <div class="user-meta">
                    <h2>
                        <?php echo htmlspecialchars(trim(($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? ''))); ?>
                        <?php $st = $user['status'] ?? 'active'; ?>
                        <span class="badge badge-<?php echo htmlspecialchars($st); ?>">
                            <i class="fa-solid fa-<?php echo ['active'=>'circle-check','banned'=>'ban','archived'=>'box-archive'][$st] ?? 'circle'; ?>"></i>
                            <?php echo htmlspecialchars(ucfirst($st)); ?>
                        </span>
                        <span class="badge badge-<?php echo htmlspecialchars($user['role']); ?>">
                            <i class="fa-solid fa-<?php echo $user['role'] === 'admin' ? 'crown' : 'user'; ?>"></i>
                            <?php echo htmlspecialchars(ucfirst($user['role'])); ?>
                        </span>
                    </h2>
                    <p>@<?php echo htmlspecialchars($user['user_name']); ?> &middot; <?php echo htmlspecialchars($user['email']); ?></p>
                    <p class="muted">
                        <i class="fa-solid fa-calendar"></i> Joined <?php echo date('d-m-Y', strtotime($user['timeCreated'])); ?>
                        &middot; <i class="fa-solid fa-right-to-bracket"></i> Last login: <?php echo !empty($user['last_login']) ? date('d-m-Y H:i', strtotime($user['last_login'])) : '—'; ?>
                        &middot; <i class="fa-solid fa-triangle-exclamation"></i> Failed: <?php echo (int) ($user['failed_attempts'] ?? 0); ?>
                    </p>
                    <?php if (!empty($user['profile_bio'])): ?>
                        <p class="bio"><?php echo nl2br(htmlspecialchars($user['profile_bio'])); ?></p>
                    <?php endif; ?>

                    <?php
                        $isSelf = $user_id === $admin_id;
                        $csrf = htmlspecialchars(csrf_token(), ENT_QUOTES);
                    ?>
                    <div class="user-actions-row">
                        <a class="btn-secondary" href="edit-user.php?user_id=<?php echo $user_id; ?>"><i class="fa-solid fa-pen"></i> Edit</a>

                        <?php if (!$isSelf): ?>
                            <?php if ($st === 'banned'): ?>
                                <form method="post" action="user-action.php" class="inline-form">
                                    <input type="hidden" name="csrf_token" value="<?php echo $csrf; ?>">
                                    <input type="hidden" name="user_id" value="<?php echo $user_id; ?>">
                                    <input type="hidden" name="action" value="unban">
                                    <button class="btn-secondary" type="submit"><i class="fa-solid fa-circle-check"></i> Unban</button>
                                </form>
                            <?php else: ?>
                                <form method="post" action="user-action.php" class="inline-form"
                                      onsubmit="return confirm('Ban this user?');">
                                    <input type="hidden" name="csrf_token" value="<?php echo $csrf; ?>">
                                    <input type="hidden" name="user_id" value="<?php echo $user_id; ?>">
                                    <input type="hidden" name="action" value="ban">
                                    <button class="btn-secondary" type="submit"><i class="fa-solid fa-ban"></i> Ban</button>
                                </form>
                            <?php endif; ?>
                        <?php endif; ?>

                        <form method="post" action="user-action.php" class="inline-form"
                              onsubmit="return confirm('Generate a 1-hour reset link?');">
                            <input type="hidden" name="csrf_token" value="<?php echo $csrf; ?>">
                            <input type="hidden" name="user_id" value="<?php echo $user_id; ?>">
                            <input type="hidden" name="action" value="reset_password">
                            <button class="btn-secondary" type="submit"><i class="fa-solid fa-key"></i> Reset PW</button>
                        </form>
                    </div>
                </div>
            </div>

            <div class="stat-row">
                <div class="stat-card">
                    <div class="stat-icon streak"><i class="fa-solid fa-fire"></i></div>
                    <div class="stat-body">
                        <span class="stat-label">Current streak</span>
                        <span class="stat-value"><?php echo (int) ($user['logging_streak'] ?? 0); ?></span>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon streak"><i class="fa-solid fa-trophy"></i></div>
                    <div class="stat-body">
                        <span class="stat-label">Longest streak</span>
                        <span class="stat-value"><?php echo (int) ($user['longest_logging_streak'] ?? 0); ?></span>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon"><i class="fa-solid fa-calendar-day"></i></div>
                    <div class="stat-body">
                        <span class="stat-label">Last logging</span>
                        <span class="stat-value"><?php echo !empty($user['last_logging_date']) ? htmlspecialchars($user['last_logging_date']) : '—'; ?></span>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon posts"><i class="fa-solid fa-comments"></i></div>
                    <div class="stat-body">
                        <span class="stat-label">Posts (shown)</span>
                        <span class="stat-value"><?php echo count($posts); ?></span>
                    </div>
                </div>
            </div>

            <div class="grid-2">
                <section class="panel">
                    <h3><i class="fa-solid fa-utensils"></i> Recent intake (last 10)</h3>
                    <?php if (empty($intakes)): ?>
                        <p class="muted">No intake logged.</p>
                    <?php else: ?>
                        <table class="mini-table">
                            <thead><tr><th>Date</th><th>Meal</th><th>Food</th><th>Cal</th><th>P/C/F</th></tr></thead>
                            <tbody>
                                <?php foreach ($intakes as $r): ?>
                                    <tr>
                                        <td><?php echo date('d-m H:i', strtotime($r['date_intake'])); ?></td>
                                        <td><?php echo htmlspecialchars(ucfirst($r['meal_category'])); ?></td>
                                        <td><?php echo htmlspecialchars($r['food_item']); ?></td>
                                        <td><?php echo (int) $r['calories']; ?></td>
                                        <td><?php echo number_format($r['protein'], 1) . '/' . number_format($r['carbs'], 1) . '/' . number_format($r['fat'], 1); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>
                </section>

                <section class="panel">
                    <h3><i class="fa-solid fa-weight-scale"></i> Weight log (last 15)</h3>
                    <?php if (empty($weights)): ?>
                        <p class="muted">No weight logged.</p>
                    <?php else: ?>
                        <table class="mini-table">
                            <thead><tr><th>Date</th><th>Weight (kg)</th></tr></thead>
                            <tbody>
                                <?php foreach ($weights as $w): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($w['date_logged']); ?></td>
                                        <td><?php echo number_format((float) $w['weight'], 2); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>
                </section>

                <section class="panel">
                    <h3><i class="fa-solid fa-pen-to-square"></i> Forum posts (last 10)</h3>
                    <?php if (empty($posts)): ?>
                        <p class="muted">No posts.</p>
                    <?php else: ?>
                        <table class="mini-table">
                            <thead><tr><th>Date</th><th>Title</th><th>Status</th></tr></thead>
                            <tbody>
                                <?php foreach ($posts as $p): ?>
                                    <tr>
                                        <td><?php echo date('d-m-Y', strtotime($p['date_posted'])); ?></td>
                                        <td><?php echo htmlspecialchars($p['title']); ?></td>
                                        <td><?php echo htmlspecialchars(ucfirst($p['status'])); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>
                </section>

                <section class="panel">
                    <h3><i class="fa-solid fa-comment"></i> Forum comments (last 10)</h3>
                    <?php if (empty($comments)): ?>
                        <p class="muted">No comments.</p>
                    <?php else: ?>
                        <table class="mini-table">
                            <thead><tr><th>Date</th><th>Post</th><th>Excerpt</th></tr></thead>
                            <tbody>
                                <?php foreach ($comments as $c): ?>
                                    <tr>
                                        <td><?php echo date('d-m-Y', strtotime($c['date_posted'])); ?></td>
                                        <td>#<?php echo (int) $c['post_id']; ?></td>
                                        <td><?php echo htmlspecialchars(mb_strimwidth($c['content'], 0, 70, '…')); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>
                </section>

                <section class="panel">
                    <h3><i class="fa-solid fa-right-to-bracket"></i> Login attempts (last 15)</h3>
                    <?php if (empty($loginAttempts)): ?>
                        <p class="muted">No login attempts.</p>
                    <?php else: ?>
                        <table class="mini-table">
                            <thead><tr><th>When</th><th>IP</th><th>Result</th></tr></thead>
                            <tbody>
                                <?php foreach ($loginAttempts as $a): ?>
                                    <tr>
                                        <td><?php echo date('d-m H:i', strtotime($a['attempted_at'])); ?></td>
                                        <td><?php echo htmlspecialchars($a['ip_address']); ?></td>
                                        <td>
                                            <?php if ($a['success']): ?>
                                                <span class="pill pill-success">Success</span>
                                            <?php else: ?>
                                                <span class="pill pill-fail">Failed</span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>
                </section>

                <section class="panel">
                    <h3><i class="fa-solid fa-shield-halved"></i> Admin actions on this user</h3>
                    <?php if (empty($adminActions)): ?>
                        <p class="muted">No admin actions logged.</p>
                    <?php else: ?>
                        <table class="mini-table">
                            <thead><tr><th>When</th><th>By</th><th>Action</th></tr></thead>
                            <tbody>
                                <?php foreach ($adminActions as $a): ?>
                                    <tr>
                                        <td><?php echo date('d-m H:i', strtotime($a['created_at'])); ?></td>
                                        <td><?php echo htmlspecialchars($a['user_name']); ?></td>
                                        <td>
                                            <strong><?php echo htmlspecialchars($a['action_type']); ?></strong>
                                            <?php if (!empty($a['description'])): ?>
                                                <div class="muted"><?php echo htmlspecialchars($a['description']); ?></div>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>
                </section>
            </div>
        </div>
    </main>

    <?php include '../views/footer.php'; ?>
</body>
</html>
