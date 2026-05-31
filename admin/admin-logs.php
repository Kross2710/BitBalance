<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/../include/init.php';
require_once __DIR__ . '/handlers/admin_data.php';
require_once __DIR__ . '/../include/handlers/log_attempt.php';
require_once __DIR__ . '/../include/csrf.php';

// Check if user is logged in and is an admin
if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'admin') {
    header('Location: admin-login.php');
    exit;
}

$admin_id = $_SESSION['user']['user_id'] ?? null;
if ($admin_id) {
    log_attempt($pdo, $admin_id, 'view', 'admin dashboard - logs', 'admin');
}

$activePage = 'logs'; // Set the active page for the sidebar

// Get filters and pagination parameters from GET
$search = trim($_GET['search'] ?? '');
$actionFilter = trim($_GET['action'] ?? '');
$page = max(1, (int)($_GET['page'] ?? 1));
$limit = 20; // 20 logs per page

// Fetch paginated logs
$paginated = getActivityLogsPaginated($page, $limit, $search, $actionFilter);
$logs = $paginated['logs'];
$totalPages = $paginated['totalPages'];
$totalLogs = $paginated['total'];

// Define the actions to filter by
$actions = ['login', 'signup' ,'logout', 'create', 'update', 'view', 'add', 'remove','delete', 'intake', 'pruning'];

$success_message = $_GET['success'] ?? '';
$error_message = $_GET['error'] ?? '';
?>
<!DOCTYPE html>
<html lang="en" data-theme="<?php echo isset($_SESSION['user']) ? ($_SESSION['user']['theme_preference'] ?? 'system') : 'system'; ?>">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>BitBalance Administrator - Logs</title>
    <?php include __DIR__ . '/../views/theme-init.php'; ?>
    <link rel="stylesheet" href="../css/style.css">
    <link rel="stylesheet" href="../css/admin.css?v=<?php echo @filemtime(__DIR__ . '/../css/admin.css'); ?>">
    <script src="https://kit.fontawesome.com/b94f65ead2.js" crossorigin="anonymous"></script>
</head>

<body>
    <?php
    // Include the header and sidebar files
    include 'views/admin-header.php';
    include 'views/admin-sidebar.php';
    ?>
    <main>
        <div class="main-content">
            <div class="toolbar">
                <h1 class="page-title"><i class="fa-solid fa-clipboard-list"></i> System Logs</h1>
                
                <form method="post" action="prune-logs-action.php" class="inline-form" 
                      onsubmit="return confirm('Prune all logs older than 30 days? This action cannot be undone.');">
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(csrf_token(), ENT_QUOTES); ?>">
                    <input type="hidden" name="days" value="30">
                    <button class="btn-primary" style="background-color: var(--color-danger, #ef4444); color: white; border: none; padding: 10px 16px; border-radius: var(--radius-md, 14px); cursor: pointer; font-weight: 600;" type="submit">
                        <i class="fa-solid fa-trash-can"></i> Prune > 30 Days
                    </button>
                </form>
            </div>

            <?php if (!empty($error_message)): ?>
                <div class="alert alert-error">
                    <i class="fas fa-exclamation-triangle"></i>
                    <div class="alert-body"><?php echo htmlspecialchars($error_message); ?></div>
                    <button type="button" class="alert-close" onclick="this.parentNode.style.display='none'">&times;</button>
                </div>
            <?php endif; ?>

            <?php if (!empty($success_message)): ?>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle"></i>
                    <div class="alert-body"><?php echo htmlspecialchars($success_message); ?></div>
                    <button type="button" class="alert-close" onclick="this.parentNode.style.display='none'">&times;</button>
                </div>
            <?php endif; ?>

            <form method="GET" action="admin-logs.php" class="search-filter-bar">
                <input name="search" type="text" placeholder="Search by name, email, action, description, target..." value="<?php echo htmlspecialchars($search); ?>">
                
                <select name="action" onchange="this.form.submit()">
                    <option value="">All Actions</option>
                    <?php foreach ($actions as $act): ?>
                        <option value="<?= htmlspecialchars($act); ?>" <?php echo $actionFilter === $act ? 'selected' : ''; ?>>
                            <?= htmlspecialchars(ucfirst($act)); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                
                <button type="submit" class="btn-primary" style="padding: 12px 20px; border-radius: 10px;">
                    <i class="fa-solid fa-magnifying-glass"></i> Filter
                </button>
                
                <?php if (!empty($search) || !empty($actionFilter)): ?>
                    <a href="admin-logs.php" class="btn-secondary" style="border-radius: 10px; display: inline-flex; align-items: center; justify-content: center; height: 44px; text-decoration: none; padding: 0 16px;">Clear</a>
                <?php endif; ?>
            </form>

            <table id="logs-table" class="user-table">
                <thead>
                    <tr>
                        <th>User</th>
                        <th>Role</th>
                        <th>Action Type</th>
                        <th>Description</th>
                        <th>Target Table</th>
                        <th>Target ID</th>
                        <th>Timestamp</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    if (count($logs) > 0):
                        foreach ($logs as $log): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($log['user_name']); ?></td>
                                <td>
                                    <?php $r = $log['role']; ?>
                                    <span class="badge badge-<?php echo htmlspecialchars($r); ?>">
                                        <i class="fa-solid fa-<?php echo $r === 'admin' ? 'crown' : 'user'; ?>"></i>
                                        <?php echo htmlspecialchars(ucfirst($r)); ?>
                                    </span>
                                </td>
                                <td>
                                    <span style="font-weight: 600; font-family: monospace; color: var(--color-primary); background: var(--color-primary-soft); padding: 4px 8px; border-radius: 6px;">
                                        <?php echo htmlspecialchars($log['action_type']); ?>
                                    </span>
                                </td>
                                <td><?php echo htmlspecialchars($log['description']); ?></td>
                                <td><?php echo $log['target_table'] !== null ? htmlspecialchars($log['target_table']) : ''; ?></td>
                                <td><?php echo $log['target_id'] !== null ? htmlspecialchars($log['target_id']) : ''; ?></td>
                                <td><?php echo date('d-m-Y H:i:s', strtotime($log['created_at'])); ?></td>
                            </tr>
                        <?php endforeach;
                    else: ?>
                        <tr>
                            <td colspan="7">No logs found.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>

            <?php if ($totalPages > 1): ?>
                <div class="pagination" style="display: flex; gap: 8px; justify-content: center; align-items: center; margin-top: 24px;">
                    <?php if ($page > 1): ?>
                        <a href="?page=<?php echo $page - 1; ?>&search=<?php echo urlencode($search); ?>&action=<?php echo urlencode($actionFilter); ?>" class="btn-secondary" style="padding: 8px 14px; border-radius: 8px; text-decoration: none;"><i class="fa-solid fa-chevron-left"></i> Prev</a>
                    <?php endif; ?>
                    
                    <span style="color: var(--color-text-secondary); font-size: 0.95rem; font-weight: 600; padding: 0 10px;">
                        Page <?php echo $page; ?> of <?php echo $totalPages; ?> (<?php echo $totalLogs; ?> total)
                    </span>
                    
                    <?php if ($page < $totalPages): ?>
                        <a href="?page=<?php echo $page + 1; ?>&search=<?php echo urlencode($search); ?>&action=<?php echo urlencode($actionFilter); ?>" class="btn-secondary" style="padding: 8px 14px; border-radius: 8px; text-decoration: none;">Next <i class="fa-solid fa-chevron-right"></i></a>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>
    </main>
    <?php include '../views/footer.php'; ?>
</body>
<style>
    .main-content {
        margin-left: 220px;
        padding: 20px;
    }

    table {
        width: 100%;
        margin-top: 5px;
        border-collapse: separate;
        border-spacing: 0;
        border-radius: 12px;
        overflow: hidden;
    }

    th,
    td {
        padding: 14px 16px;
        text-align: left;
        font-size: 1rem;
        border-bottom: 1px solid var(--color-border-subtle);
    }

    th {
        background-color: var(--color-surface-alt);
        font-weight: 600;
        color: var(--color-text-secondary);
    }

    tr:hover {
        background-color: var(--color-surface-hover);
        transition: background-color 0.2s ease;
    }

    tr:last-child td {
        border-bottom: none;
    }

    /* Responsive */
    @media (max-width: 900px) {
        .main-content {
            margin: 80px 12px 24px 12px;
        }

        .search-filter-bar {
            flex-direction: column;
            align-items: stretch;
        }

        .search-filter-bar input[type="text"],
        .search-filter-bar select {
            width: 100%;
        }

        th, td {
            padding: 10px 8px;
            font-size: 0.95rem;
        }

        /* Hide less important columns on mobile */
        #logs-table th:nth-child(2),
        #logs-table td:nth-child(2),
        #logs-table th:nth-child(5),
        #logs-table td:nth-child(5),
        #logs-table th:nth-child(6),
        #logs-table td:nth-child(6) {
            display: none;
        }
    }
</style>
</html>