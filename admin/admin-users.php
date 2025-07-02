<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/../include/init.php';
require_once __DIR__ . '/handlers/admin_data.php';
require_once __DIR__ . '/../include/handlers/log_attempt.php';

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
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>BitBalance Administrator</title>
    <link rel="stylesheet" href="../css/style.css">
    <link rel="stylesheet" href="../css/admin.css">
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
            <div class="user-list">
                <div class="search-filter-bar">
                    <input id="searchInput" type="text" placeholder="Search users...">
                    <select id="roleFilter">
                        <option value="">All Roles</option>
                        <?php foreach ($roles as $role): ?>
                            <option value="<?= htmlspecialchars($role); ?>"><?= htmlspecialchars(ucfirst($role)); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <select id="statusFilter">
                        <option value="">All Statuses</option>
                        <?php foreach ($statuses as $status): ?>
                            <option value="<?= htmlspecialchars($status); ?>"><?= htmlspecialchars(ucfirst($status)); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

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
                                <td><?php echo htmlspecialchars(ucfirst($user['role'])); ?></td>
                                <td><?php echo htmlspecialchars(ucfirst($user['status'])); ?></td>
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
                                    <button type="submit"
                                        style="background:#e55039;color:#fff;border:none;border-radius:4px;padding:4px 10px;cursor:pointer;">
                                        <a href="edit-user.php?user_id=<?php echo htmlspecialchars($user['user_id']); ?>"
                                            style="color: white; text-decoration: none;">Edit</a>
                                    </button>
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
            <button id="addUser"
                style="background: #4CAF50; color: #fff; border: none; border-radius: 4px; padding: 4px 10px; cursor: pointer;"><a
                    href="add-user.php" style="color: #fff; text-decoration: none;">Add User</a></button>
    </main>
</body>
<style>
    .main-content {
        margin-left: 220px;
        padding: 20px;
    }

    /* ---- Intake Table Modern Styling ---- */
    table {
        width: 100%;
        border-collapse: separate;
        border-spacing: 0;
        background: #ffffff;
        border-radius: 12px;
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05);
        overflow: hidden;
        margin: 20px 0;
        font-family: 'Segoe UI', sans-serif;
    }

    th,
    td {
        padding: 14px 16px;
        text-align: left;
        font-size: 1rem;
        color: #333;
        border-bottom: 1px solid #eee;
    }

    th {
        background-color: #f8f9fb;
        font-weight: 600;
        color: #555;
    }

    tr:hover {
        background-color: #f1f7ff;
        transition: background-color 0.2s ease;
    }

    tr:last-child td {
        border-bottom: none;
    }

    /* Search and Filter Bar */
    .search-filter-bar {
        display: flex;
        flex-wrap: wrap;
        gap: 10px;
        align-items: center;
        border-radius: 10px;
        margin-bottom: 20px;
    }

    .search-filter-bar input[type="text"],
    .search-filter-bar select {
        padding: 10px 14px;
        font-size: 1rem;
        border: 1px solid #ccc;
        border-radius: 6px;
        background-color: #fff;
        outline: none;
        transition: border-color 0.2s ease;
    }

    .search-filter-bar input[type="text"] {
        width: 230px;
    }

    .search-filter-bar select {
        width: 150px;
    }

    .search-filter-bar input[type="text"]:focus,
    .search-filter-bar select:focus {
        border-color: #4a90e2;
    }

@media (max-width: 900px) {
    .main-content {
        margin: 80px 12px 24px 12px;    /* space for toggle   */
    }

    /* Search / filter bar stacks vertically */
    .search-filter-bar {
        flex-direction: column;
        align-items: stretch;
    }
    .search-filter-bar input[type="text"],
    .search-filter-bar select {
        width: 100%;
    }

    /* Table font & paddings slightly smaller */
    th, td { padding: 10px 8px; font-size: 0.95rem; }

    /* Hide less-important columns on very small screens
       (IDs & timestamps can be toggled back if needed) */
    @media (max-width: 900px) {
        #user-table th:nth-child(1),
        #user-table td:nth-child(1),    /* User ID */
        #user-table th:nth-child(8),
        #user-table td:nth-child(8),    /* Created At */
        #user-table th:nth-child(9),
        #user-table td:nth-child(9)     /* Last Login */
        { display: none; }
    }
}
</style>