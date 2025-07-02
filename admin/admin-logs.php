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

$activePage = 'logs'; // Set the active page for the sidebar

$logs = getActivityLogs(); // Fetch all activity logs
// Define the actions to filter by
$actions = ['login', 'signup' ,'logout', 'create', 'update', 'view', 'add', 'remove','delete', 'intake'];
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
            <div class="search-filter-bar">
                <input id="searchInput" type="text" placeholder="Search logs...">
                <select id="actionFilter">
                    <option value="">All Actions</option>
                    <?php foreach ($actions as $action): ?>
                        <option value="<?= htmlspecialchars($action); ?>"><?= htmlspecialchars(ucfirst($action)); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
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
                                <td><?php echo htmlspecialchars(ucfirst($log['role'])); ?></td>
                                <td><?php echo htmlspecialchars(ucfirst($log['action_type'])); ?></td>
                                <td><?php echo htmlspecialchars($log['description']); ?></td>
                                <td><?php echo $log['target_table'] !== null ? htmlspecialchars($log['target_table']) : ''; ?>
                                </td>
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
            <script>
                $(function () {
                    // init DataTable without its own search box
                    const table = $('#logs-table').DataTable({
                        dom: 'tip', // just table, info, pagination
                        pagingType: 'simple_numbers',
                        pageLength: 15
                    });

                    // tie external search box
                    $('#searchInput').on('keyup', function () {
                        table.search(this.value).draw();
                    });

                    // tie action filter dropdown (column index 2 => Action Type)
                    $('#actionFilter').on('change', function () {
                        const val = this.value;
                        table.column(2).search(val).draw();
                    });
                });
            </script>
        </div>
    </main>
    <?php include '../views/footer.php'; ?>
</body>
<style>
    .main-content {
        margin-left: 220px;
        padding: 20px;
    }

    /* ---- Intake Table Responsive ---- */
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

    /* Responsive */
    @media (max-width: 700px) {
        .intake-table {
            width: 100%;
            font-size: 0.95em;
            border-radius: 0;
            box-shadow: none;
        }

        th,
        td {
            padding: 10px 8px;
        }

        .progress-value {
            font-size: 1em;
        }
    }

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