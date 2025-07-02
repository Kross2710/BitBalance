<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/../include/init.php';
require_once __DIR__ . '/handlers/dashboard_data.php';
require_once __DIR__ . '/../include/handlers/log_attempt.php';

if ($isLoggedIn) {
    // Log the user activity
    log_attempt($pdo, $user['user_id'], 'view', 'User ' . $user['user_id'] . ' clicked on dashboard history', 'dashboard', null);
}

$activePage = 'history';
$activeHeader = 'dashboard';
$mealTypes = ['breakfast', 'lunch', 'dinner', 'snack']; // Define meal types for filtering

$error_message = '';
if (isset($_GET['error'])) {
    $error_message = htmlspecialchars($_GET['error']); // Prevent XSS
}
$success_message = '';
if (isset($_GET['success'])) {
    $success_message = htmlspecialchars($_GET['success']); // Prevent XSS
}

if ($isLoggedIn) {
    $historyData = getUserIntakeHistory($user['user_id'] ?? null); // Fetch history data for the logged-in user
}
?>

<!DOCTYPE html>
<html lang="en"
    data-theme="<?php echo isset($_SESSION['user']) ? ($_SESSION['user']['theme_preference'] ?? 'light') : 'light'; ?>">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>BitBalance Dashboard</title>
    <link rel="stylesheet" href="../css/style.css">
    <link rel="stylesheet" href="../css/dashboard.css">
    <link rel="stylesheet" href="../css/themes/global.css">
    <link rel="stylesheet" href="../css/themes/header.css">
    <link rel="stylesheet" href="../css/themes/dashboard.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.8/css/jquery.dataTables.min.css">
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.8/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="https://kit.fontawesome.com/b94f65ead2.js" crossorigin="anonymous"></script>
</head>

<body>
    <?php include PROJECT_ROOT . 'views/header.php'; ?>
    <?php include PROJECT_ROOT . 'dashboard/views/sidebar.php'; ?>

    <?php if ($isLoggedIn): ?>
        <?php include PROJECT_ROOT . 'dashboard/views/right-sidebar.php'; ?>
        <main class="dashboard">
            <?php
            // Group history data by date
            $groupedHistory = [];
            if (!empty($historyData)) {
                foreach ($historyData as $entry) {
                    $date = $entry['date_intake'];
                    if (!isset($groupedHistory[$date])) {
                        $groupedHistory[$date] = [];
                    }
                    $groupedHistory[$date][] = $entry;
                }
            }
            ?>
            <div class="search-filter-bar">
                <input id="searchInput" type="text" placeholder="Search history...">
                <select id="mealTypeFilter">
                    <option value="">All Meal Types</option>
                    <?php foreach ($mealTypes as $mealType): ?>
                        <option value="<?= htmlspecialchars($mealType); ?>"><?= htmlspecialchars(ucfirst($mealType)); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <!-- Date range filter with labels -->
                <div class="date-range-filter">
                    <label for="startDateFilter">From</label>
                    <input type="date" id="startDateFilter">
                    <label for="endDateFilter">To</label>
                    <input type="date" id="endDateFilter">
                </div>
            </div>
            <table id="logs-table">
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Meal</th>
                        <th>Food</th>
                        <th>Calories / Time</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($groupedHistory)): ?>
                        <?php foreach ($groupedHistory as $date => $entries): ?>
                            <?php foreach ($entries as $entry): ?>
                                <tr>
                                    <td data-label="Date"><?= date('d-m-Y', strtotime($entry['date_intake'])) ?></td>
                                    <td data-label="Meal"><?= htmlspecialchars(ucfirst($entry['meal_category'])) ?></td>
                                    <td data-label="Food"><?= htmlspecialchars(ucfirst($entry['food_item'])) ?></td>
                                    <td data-label="Calories / Time" data-order="<?= htmlspecialchars($entry['calories']) ?>">
                                        <?= htmlspecialchars($entry['calories']) ?>
                                        <span style="color: #888; font-size: 0.9em;">
                                            <?= date('H:i', strtotime($entry['date_intake'])) ?>
                                        </span>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr class="no-history">
                            <td>No history data available.</td>
                            <td></td>
                            <td></td>
                            <td></td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
            <script>
                $(function () {
                    // init DataTable without its own search box, default sort by Date DESC
                    var table = $('#logs-table').DataTable({
                        dom: 'tip',
                        pagingType: 'simple_numbers',
                        pageLength: 10,
                        order: [[0, 'desc']], // Sort by first column (Date) descending
                        columnDefs: [
                            { targets: 3, type: 'num' } // ensure Calories column sorts numerically
                        ],
                        rowCallback: function (row, data, index) {
                            if ($(row).hasClass('date-header') || $(row).hasClass('no-history')) {
                                // Don't process this row
                                $(row).removeClass('odd even'); // remove zebra striping
                            }
                        }
                    });
                    // tie external search box
                    $('#searchInput').on('keyup', function () {
                        // Custom search: only apply to first 3 columns (Date, Meal, Food)
                        table.rows().every(function () {
                            var data = this.data();
                            var searchTerm = $('#searchInput').val().toLowerCase();
                            var match = false;
                            for (var i = 0; i < 3; i++) {
                                if (data[i] && data[i].toString().toLowerCase().indexOf(searchTerm) !== -1) {
                                    match = true;
                                    break;
                                }
                            }
                            if (match || searchTerm === '') {
                                $(this.node()).show();
                            } else {
                                $(this.node()).hide();
                            }
                        });
                    });

                    // tie meal type filter dropdown (column index 1 => Meal Type)
                    $('#mealTypeFilter').on('change', function () {
                        const val = this.value;
                        table.column(1).search(val).draw();
                    });

                    // tie date range filters
                    $('#startDateFilter, #endDateFilter').on('change', function () {
                        const startDate = $('#startDateFilter').val();
                        const endDate = $('#endDateFilter').val();
                        table.rows().every(function () {
                            const date = this.data()[0]; // Date column is first
                            const dateObj = new Date(date.split('-').reverse().join('-')); // Convert to Date object
                            const startObj = startDate ? new Date(startDate) : null;
                            const endObj = endDate ? new Date(endDate) : null;
                            if ((startObj && dateObj < startObj) || (endObj && dateObj > endObj)) {
                                $(this.node()).hide();
                            } else {
                                $(this.node()).show();
                            }
                        });
                        table.draw();
                    });
                });
            </script>
            <style>
                /* ---- Meals Table Modern Styling ---- */
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

                .date-header td {
                    background-color: #f4f6fa;
                    font-weight: bold;
                    border-top: 2px solid #ccc;
                }

                /* Responsive */
                @media (max-width: 700px) {
                    table {
                        width: 100%;
                        font-size: 0.95em;
                        border-radius: 0;
                        box-shadow: none;
                    }

                    th,
                    td {
                        padding: 10px 8px;
                    }
                }

                /* ---------- Mobile-friendly history table ---------- */
                @media (max-width: 600px) {
                    table {
                        border-radius: 0;
                    }

                    thead {
                        display: none;
                    }

                    tr.date-header td {
                        padding: 12px;
                        font-size: 1.05rem;
                        background: #eff1f5;
                    }

                    tbody tr {
                        display: block;
                        margin-bottom: 0.75rem;
                        border: 1px solid #e1e4e8;
                        border-radius: 8px;
                        overflow: hidden;
                    }

                    tbody td {
                        display: flex;
                        justify-content: space-between;
                        padding: 10px 12px;
                        border: none;
                        font-size: 0.96rem;
                    }

                    tbody td::before {
                        content: attr(data-label);
                        font-weight: 600;
                        color: #555;
                        margin-right: 1rem;
                    }

                    tbody tr:nth-child(even) td {
                        background: #fafafa;
                    }
                }
            </style>
        </main>
    <?php else: ?>
        <main class="dashboard" style="text-align:center; margin-top:40px;">
            <h2>Please log in to access your Dashboard.</h2>
            <button class="btn-primary"><a href="<?= BASE_URL ?>login.php" class="btn-primary">Sign In</a></button>
        </main>
    <?php endif; ?>
    <?php include PROJECT_ROOT . 'views/footer.php'; ?>
</body>

</html>
<style>
    main.dashboard {
        margin-top: 20px;
        margin-left: 220px;
        margin-right: 220px;
        border-radius: 10px;
        width: calc(100% - 440px);
    }

    @media (max-width: 900px) {
        .dashboard {
            margin-left: 0;
            margin-right: 0;
            width: 100vw;
        }
    }

    .flex-row {
        display: flex;
        flex-wrap: wrap;
        justify-content: start;
        gap: 30px;
    }

    .progress-card h3 {
        font-size: 1.3em;
    }

    .progress-widget {
        width: 100%;
        max-width: 480px;
        box-shadow: 0 2px 5px rgba(0, 0, 0, 0.05);
        border-radius: 10px;
        border: 1px solid #666;
        background: #fff;
        padding: 18px;
    }

    .progress-value {
        margin: 8px 0;
    }

    .progress-value span {
        font-size: 2em;
        font-weight: bold;
        color:
            <?= $statusClass === 'ongoing' ? '#388e3c' : ($statusClass === 'overlimit' ? 'black' : '#999') ?>
        ;
    }

    .progress-bar {
        height: 25px;
        background-color: #e6e6e6;
        border-radius: 5px;
        overflow: hidden;
        margin-bottom: 10px;
        min-width: 90px;
    }

    .progress-fill {
        background-color: #eba434;
        height: 100%;
        transition: width 1s cubic-bezier(0.4, 0, 0.2, 1);
        will-change: width;
    }

    .progress-labels {
        display: flex;
        justify-content: space-between;
        font-size: 14px;
        color: #666;
    }

    .status-section {
        padding: 20px;
        border: 1px solid #ccc;
        border-radius: 10px;
        box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
        max-width: 480px;
        margin-top: 15px;
        margin-bottom: 15px;
    }

    .info-section {
        padding: 20px;
        border: 1px solid #ccc;
        border-radius: 10px;
        box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
        max-width: 480px;
        margin-bottom: 15px;
    }

    .status-section h4 {
        margin-bottom: 10px;
    }

    .status-section span.ongoing {
        color: #388e3c;
        /* Green */
    }

    .status-section span.overlimit {
        color: #d32f2f;
        /* Red */
    }

    .status-section span.unset {
        color: #999;
        /* Grey */
    }

    /* ---- Button Style ---- */
    .btn-primary {
        background-color: #4a7ee3;
        color: white;
        padding: 8px 15px;
        border: none;
        margin: 20px 0;
        border-radius: 5px;
        cursor: pointer;
        font-size: 16px;
    }

    .btn-primary a {
        color: white;
        text-decoration: none;
    }

    .btn-primary:hover {
        background-color: #3a5bb3;
        transition: all 0.3s ease;
    }

    .chart-section {
        border-radius: 10px;
        padding: 20px;
        box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
        max-width: 480px;
    }
</style>
<style>
    canvas {
        width: 90% !important;
        height: auto !important;
        display: block;
        margin: 0 auto;
    }

    canvas#mealCategoriesChart {
        width: 55% !important;

    }

    /* Responsive improvements for dashboard */
    @media (max-width: 700px) {
        main.dashboard {
            margin: 0;
            width: 100vw;
            box-shadow: none;
            border: none;
            padding: 12px 0 0 0;
        }

        .progress-widget,
        .status-section,
        .chart-section {
            width: 100vw;
            min-width: 0;
            max-width: 100vw;
            padding: 0 2vw;
            margin: 0 auto 12px auto;
            box-shadow: none;
            border-radius: 0;
            border: none;
        }

        .dashboard {
            flex-direction: column;
        }
    }

    @media (max-width: 480px) {
        main.dashboard {
            padding: 4px 0 0 0;
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
    .search-filter-bar input[type="date"],
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
    .search-filter-bar input[type="date"]:focus,
    .search-filter-bar select:focus {
        border-color: #4a90e2;
    }

    .date-range-filter {
        display: flex;
        gap: 10px;
        align-items: center;
    }
</style>

<style>
    [data-theme="dark"] .search-filter-bar {
        background: #2d2d2d !important;
        color: #ffffff !important;
    }
    
    [data-theme="dark"] .search-filter-bar input[type="text"],
    [data-theme="dark"] .search-filter-bar input[type="date"],
    [data-theme="dark"] .search-filter-bar select {
        background-color: #3d3d3d !important;
        border: 1px solid #555555 !important;
        color: #ffffff !important;
    }
    
    [data-theme="dark"] main.dashboard {
        background: #2d2d2d !important;
        border-color: #495057 !important;
        color: #ffffff !important;
    }

    [data-theme="dark"] .progress-widget {
        background: #2d2d2d !important;
        border-color: #495057 !important;
        color: #ffffff !important;
    }

    [data-theme="dark"] .progress-card {
        background-color: #2d2d2d !important;
        color: #ffffff !important;
    }

    [data-theme="dark"] .progress-info h3 {
        color: #ffffff !important;
    }

    [data-theme="dark"] .progress-value {
        color: #ffffff !important;
    }

    [data-theme="dark"] .progress-bar {
        background-color: #495057 !important;
        border-color: #495057 !important;
    }

    [data-theme="dark"] .progress-labels {
        color: #adb5bd !important;
    }

    [data-theme="dark"] .status-section {
        background: #2d2d2d !important;
        border-color: #495057 !important;
        color: #ffffff !important;
    }

    [data-theme="dark"] .status-section h4 {
        color: #ffffff !important;
    }

    [data-theme="dark"] .status-section p {
        color: #ffffff !important;
    }

    [data-theme="dark"] .chart-section {
        background: #2d2d2d !important;
        border-color: #495057 !important;
        color: #ffffff !important;
    }

    [data-theme="dark"] .chart-section h4 {
        color: #ffffff !important;
    }

    [data-theme="dark"] canvas {
        background: #2d2d2d !important;
    }

    [data-theme="dark"] .btn-primary {
        background-color: #4a7ee3 !important;
    }

    [data-theme="dark"] .btn-primary a {
        color: white !important;
    }

    [data-theme="dark"] .sidebar {
        background-color: #343a40 !important;
        border-right: 1px solid #495057 !important;
        color: #ffffff !important;
    }

    [data-theme="dark"] .sidebar a {
        color: #adb5bd !important;
        background: transparent !important;
    }

    [data-theme="dark"] .sidebar a:hover {
        background-color: #495057 !important;
        color: #ffffff !important;
    }

    [data-theme="dark"] .sidebar a.active {
        background-color: #4a7ee3 !important;
        color: white !important;
    }

    [data-theme="dark"] .sidebar h3,
    [data-theme="dark"] .sidebar h4,
    [data-theme="dark"] .sidebar p {
        color: #ffffff !important;
    }

    [data-theme="dark"] .sidebar .nav-item {
        color: #adb5bd !important;
    }

    [data-theme="dark"] .sidebar .nav-item:hover {
        background: #495057 !important;
        color: #ffffff !important;
    }
</style>