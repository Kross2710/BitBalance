<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/../include/init.php';
require_once __DIR__ . '/handlers/admin_data.php';
require_once __DIR__ . '/../include/handlers/log_attempt.php';
require_once __DIR__ . '/../include/csrf.php';

if ($isLoggedIn) {
    if ($_SESSION['user']['role'] === 'admin') {
        $admin_id = $_SESSION['user']['user_id'] ?? null;
        if ($admin_id) {
            log_attempt($pdo, $admin_id, 'view', 'admin dashboard', 'admin');
        }
    }
}

$activePage = 'users'; // Set the active page for the sidebar

// Fetch users
$users = getAllUsers(); // Fetch all users for the user list
$roles = ['admin', 'regular'];
$statuses = ['active', 'archived', 'banned'];

// Success message handling
if (isset($_GET['success'])) {
    $success_message = htmlspecialchars($_GET['success'] ?? '');
} else {
    $success_message = '';
}

if (isset($_GET['error'])) {
    $error_message = htmlspecialchars($_GET['error'] ?? '');
} else {
    $error_message = '';
}

$flashReset = $_SESSION['flash_reset_link'] ?? null;
unset($_SESSION['flash_reset_link']);
?>

<!DOCTYPE html>
<html lang="en" data-theme="<?php echo isset($_SESSION['user']) ? ($_SESSION['user']['theme_preference'] ?? 'light') : 'light'; ?>">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>BitBalance Administrator</title>
    <link rel="stylesheet" href="../css/style.css">
    <link rel="stylesheet" href="../css/admin.css?v=<?php echo @filemtime(__DIR__ . '/../css/admin.css'); ?>">
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.8/css/jquery.dataTables.min.css">
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.8/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="https://kit.fontawesome.com/b94f65ead2.js" crossorigin="anonymous"></script>
</head>

<body>
    <?php
    // Include the header file
    include 'views/admin-header.php';
    include 'views/admin-sidebar.php';
    // Include admin-login.php if user is not logged in or not an admin
    if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'admin') {
        include 'admin-login.php';
        exit;
    }
    ?>
    <main>
        <div class="main-content">
            <div class="toolbar">
                <h1 class="page-title"><i class="fa-solid fa-users"></i> User Management</h1>
                <a href="add-user.php" class="btn-add"><i class="fa-solid fa-user-plus"></i> Add User</a>
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

            <div class="user-list">
                <div class="search-filter-bar">
                    <input id="searchInput" type="text" placeholder="Search by name, username, email…">
                    <select id="roleFilter">
                        <option value="">All Roles</option>
                        <?php foreach ($roles as $role): ?>
                            <option value="<?= htmlspecialchars($role); ?>"><?= htmlspecialchars(ucfirst($role)); ?></option>
                        <?php endforeach; ?>
                    </select>
                    <select id="statusFilter">
                        <option value="">All Statuses</option>
                        <?php foreach ($statuses as $status): ?>
                            <option value="<?= htmlspecialchars($status); ?>"><?= htmlspecialchars(ucfirst($status)); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <table id="user-table" class="user-table">
                    <thead>
                        <tr>
                            <th>User ID</th>
                            <th>Username</th>
                            <th>First Name</th>
                            <th>Last Name</th>
                            <th>Email</th>
                            <th>Role</th>
                            <th>Status</th>
                            <th>Created At</th>
                            <th>Last Login</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($users as $user): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($user['user_id']); ?></td>
                                <td><?php echo htmlspecialchars($user['user_name']); ?></td>
                                <td><?php echo htmlspecialchars($user['first_name']); ?></td>
                                <td><?php echo htmlspecialchars($user['last_name']); ?></td>
                                <td><?php echo htmlspecialchars($user['email']); ?></td>
                                <td>
                                    <?php $r = $user['role']; ?>
                                    <span class="badge badge-<?php echo htmlspecialchars($r); ?>">
                                        <i class="fa-solid fa-<?php echo $r === 'admin' ? 'crown' : 'user'; ?>"></i>
                                        <?php echo htmlspecialchars(ucfirst($r)); ?>
                                    </span>
                                </td>
                                <td>
                                    <?php
                                        $st = $user['status'] ?? 'active';
                                        $stIcon = [
                                            'active'   => 'circle-check',
                                            'banned'   => 'ban',
                                            'archived' => 'box-archive',
                                        ][$st] ?? 'circle';
                                    ?>
                                    <span class="badge badge-<?php echo htmlspecialchars($st); ?>">
                                        <i class="fa-solid fa-<?php echo $stIcon; ?>"></i>
                                        <?php echo htmlspecialchars(ucfirst($st)); ?>
                                    </span>
                                </td>
                                <td><?php echo htmlspecialchars(date('d-m-Y H:i:s', strtotime($user['timeCreated']))); ?>
                                </td>
                                <td>
                                    <?php
                                    if (!empty($user['last_login'])) {
                                        echo htmlspecialchars(date('d-m-Y H:i:s', strtotime($user['last_login'])));
                                    } else {
                                        echo '-';
                                    }
                                    ?>
                                </td>
                                <td>
                                    <?php
                                        $uid = (int) $user['user_id'];
                                        $uStatus = $user['status'] ?? 'active';
                                        $isSelf = isset($_SESSION['user']['user_id']) && $uid === (int) $_SESSION['user']['user_id'];
                                        $csrf = htmlspecialchars(csrf_token(), ENT_QUOTES);
                                    ?>
                                    <div class="action-btns">
                                        <a class="icon-btn btn-view" data-tip="View detail" href="view-user.php?user_id=<?php echo $uid; ?>"><i class="fa-solid fa-eye"></i></a>
                                        <a class="icon-btn btn-edit" data-tip="Edit user" href="edit-user.php?user_id=<?php echo $uid; ?>"><i class="fa-solid fa-pen"></i></a>

                                        <?php if (!$isSelf): ?>
                                            <?php if ($uStatus === 'banned'): ?>
                                                <form method="post" action="user-action.php" class="inline-form">
                                                    <input type="hidden" name="csrf_token" value="<?php echo $csrf; ?>">
                                                    <input type="hidden" name="user_id" value="<?php echo $uid; ?>">
                                                    <input type="hidden" name="action" value="unban">
                                                    <button class="icon-btn btn-unban" data-tip="Unban" type="submit"><i class="fa-solid fa-circle-check"></i></button>
                                                </form>
                                            <?php else: ?>
                                                <form method="post" action="user-action.php" class="inline-form"
                                                      onsubmit="return confirm('Ban user <?php echo htmlspecialchars($user['user_name'], ENT_QUOTES); ?>?');">
                                                    <input type="hidden" name="csrf_token" value="<?php echo $csrf; ?>">
                                                    <input type="hidden" name="user_id" value="<?php echo $uid; ?>">
                                                    <input type="hidden" name="action" value="ban">
                                                    <button class="icon-btn btn-ban" data-tip="Ban user" type="submit"><i class="fa-solid fa-ban"></i></button>
                                                </form>
                                            <?php endif; ?>

                                            <?php if ($uStatus === 'archived'): ?>
                                                <form method="post" action="user-action.php" class="inline-form">
                                                    <input type="hidden" name="csrf_token" value="<?php echo $csrf; ?>">
                                                    <input type="hidden" name="user_id" value="<?php echo $uid; ?>">
                                                    <input type="hidden" name="action" value="restore">
                                                    <button class="icon-btn btn-restore" data-tip="Restore" type="submit"><i class="fa-solid fa-rotate-left"></i></button>
                                                </form>
                                            <?php else: ?>
                                                <form method="post" action="user-action.php" class="inline-form"
                                                      onsubmit="return confirm('Archive user <?php echo htmlspecialchars($user['user_name'], ENT_QUOTES); ?>?');">
                                                    <input type="hidden" name="csrf_token" value="<?php echo $csrf; ?>">
                                                    <input type="hidden" name="user_id" value="<?php echo $uid; ?>">
                                                    <input type="hidden" name="action" value="archive">
                                                    <button class="icon-btn btn-archive" data-tip="Archive" type="submit"><i class="fa-solid fa-box-archive"></i></button>
                                                </form>
                                            <?php endif; ?>
                                        <?php endif; ?>

                                        <form method="post" action="user-action.php" class="inline-form"
                                              onsubmit="return confirm('Generate a 1-hour reset link for this user?');">
                                            <input type="hidden" name="csrf_token" value="<?php echo $csrf; ?>">
                                            <input type="hidden" name="user_id" value="<?php echo $uid; ?>">
                                            <input type="hidden" name="action" value="reset_password">
                                            <button class="icon-btn btn-reset" data-tip="Reset password" type="submit"><i class="fa-solid fa-key"></i></button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <script>
                    $(function () {
                        // init DataTable without its own search box
                        const table = $('#user-table').DataTable({
                            dom: 'tip', // just table, info, pagination
                            pagingType: 'simple_numbers',
                            pageLength: 10
                        });

                        // Custom search: only columns 0-4 (User ID, Username, First Name, Last Name, Email)
                        $('#searchInput').on('keyup', function () {
                            const searchTerm = this.value.toLowerCase();
                            table.rows().every(function () {
                                const data = this.data();
                                // Concatenate columns 0-4 and search
                                const rowText = [0, 1, 2, 3, 4].map(i => (data[i] || '').toString().toLowerCase()).join(' ');
                                if (rowText.indexOf(searchTerm) !== -1 || searchTerm === '') {
                                    $(this.node()).show();
                                } else {
                                    $(this.node()).hide();
                                }
                            });
                        });

                        // tie role filter dropdown (column index 4 => Role)
                        $('#roleFilter').on('change', function () {
                            const val = this.value;
                            table.column(5).search(val).draw();
                        });

                        // tie status filter dropdown (column index 5 => Status)
                        $('#statusFilter').on('change', function () {
                            const val = this.value;
                            table.column(6).search(val).draw();
                        });
                    });
                </script>
            </div>
        </div>
    </main>

    <?php if ($flashReset): ?>
        <div class="modal-backdrop" id="resetLinkModal" role="dialog" aria-modal="true" aria-labelledby="resetLinkTitle">
            <div class="modal-card">
                <div class="modal-header">
                    <h3 id="resetLinkTitle"><i class="fa-solid fa-key"></i> Password reset link</h3>
                    <button type="button" class="close-modal" aria-label="Close"
                            onclick="document.getElementById('resetLinkModal').remove()">&times;</button>
                </div>
                <div class="modal-body">
                    <p class="modal-desc">
                        Generated for <strong><?php echo htmlspecialchars($flashReset['user_name']); ?></strong>.
                        Expires <?php echo htmlspecialchars($flashReset['expires']); ?> (1 hour).
                        Share it directly with the user — they can set a new password without email verification.
                    </p>
                    <div class="copy-row">
                        <input type="text" id="resetLinkInput" readonly value="<?php echo htmlspecialchars($flashReset['link']); ?>">
                        <button type="button" id="copyResetLink"><i class="fa-solid fa-copy"></i> Copy</button>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn-secondary" onclick="document.getElementById('resetLinkModal').remove()">Close</button>
                </div>
            </div>
        </div>
        <script>
            (function () {
                const modal = document.getElementById('resetLinkModal');
                const btn   = document.getElementById('copyResetLink');
                const input = document.getElementById('resetLinkInput');
                if (!modal) return;

                requestAnimationFrame(() => modal.classList.add('show'));

                modal.addEventListener('click', (e) => {
                    if (e.target === modal) modal.remove();
                });
                document.addEventListener('keydown', function escClose(e) {
                    if (e.key === 'Escape' && document.body.contains(modal)) {
                        modal.remove();
                        document.removeEventListener('keydown', escClose);
                    }
                });

                if (btn && input) {
                    btn.addEventListener('click', async () => {
                        try {
                            await navigator.clipboard.writeText(input.value);
                        } catch (_) {
                            input.select();
                            document.execCommand('copy');
                        }
                        btn.classList.add('copied');
                        btn.innerHTML = '<i class="fa-solid fa-check"></i> Copied';
                        setTimeout(() => {
                            btn.classList.remove('copied');
                            btn.innerHTML = '<i class="fa-solid fa-copy"></i> Copy';
                        }, 2000);
                    });
                    input.addEventListener('focus', () => input.select());
                    setTimeout(() => input && input.focus(), 350);
                }
            })();
        </script>
    <?php endif; ?>
</body>
<style>
    @media (max-width: 900px) {
        #user-table th:nth-child(1),
        #user-table td:nth-child(1),
        #user-table th:nth-child(8),
        #user-table td:nth-child(8),
        #user-table th:nth-child(9),
        #user-table td:nth-child(9) { display: none; }
    }
</style>