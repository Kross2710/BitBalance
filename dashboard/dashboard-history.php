<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/../include/init.php';
require_once __DIR__ . '/handlers/dashboard_data.php';
require_once __DIR__ . '/../include/handlers/log_attempt.php';

if ($isLoggedIn) {
    log_attempt($pdo, $user['user_id'], 'view', 'User ' . $user['user_id'] . ' clicked on dashboard history', 'dashboard', null);
}

$activePage = 'history';
$activeHeader = 'dashboard';
$bodyClass = 'page-history';
$mealTypes = ['breakfast', 'lunch', 'dinner', 'snack'];
$displayUser = $isLoggedIn ? $user['user_name'] : "Guest";

if ($isLoggedIn) {
    $historyData = getUserIntakeHistory($user['user_id'] ?? null);
} else {
    $userAge = 25;
    $userWeight = 70;
    $userHeight = 175;
    $userGoal = 2200;
    $totalCalories = 1450;
    $historyData = [
        ['intakeLog_id' => 0, 'food_item' => 'Pho Bo', 'calories' => 450, 'protein' => 30, 'carbs' => 55, 'fat' => 10, 'meal_category' => 'breakfast', 'date_intake' => date('Y-m-d 08:30:00')],
        ['intakeLog_id' => 0, 'food_item' => 'Iced Coffee', 'calories' => 120, 'protein' => 2, 'carbs' => 18, 'fat' => 4, 'meal_category' => 'snack', 'date_intake' => date('Y-m-d 10:00:00')],
        ['intakeLog_id' => 0, 'food_item' => 'Grilled Chicken Salad', 'calories' => 550, 'protein' => 40, 'carbs' => 35, 'fat' => 20, 'meal_category' => 'lunch', 'date_intake' => date('Y-m-d 12:30:00')],
        ['intakeLog_id' => 0, 'food_item' => 'Apple', 'calories' => 80, 'protein' => 0, 'carbs' => 21, 'fat' => 0, 'meal_category' => 'snack', 'date_intake' => date('Y-m-d 15:00:00')],
        ['intakeLog_id' => 0, 'food_item' => 'Salmon & Rice', 'calories' => 250, 'protein' => 13, 'carbs' => 46, 'fat' => 12, 'meal_category' => 'dinner', 'date_intake' => date('Y-m-d 19:00:00')],
        ['intakeLog_id' => 0, 'food_item' => 'Greek Yogurt Bowl', 'calories' => 320, 'protein' => 24, 'carbs' => 42, 'fat' => 8, 'meal_category' => 'breakfast', 'date_intake' => date('Y-m-d 09:00:00', strtotime('-1 day'))],
        ['intakeLog_id' => 0, 'food_item' => 'Chicken Banh Mi', 'calories' => 610, 'protein' => 35, 'carbs' => 70, 'fat' => 18, 'meal_category' => 'lunch', 'date_intake' => date('Y-m-d 13:10:00', strtotime('-1 day'))],
        ['intakeLog_id' => 0, 'food_item' => 'Beef Stir Fry', 'calories' => 690, 'protein' => 45, 'carbs' => 62, 'fat' => 24, 'meal_category' => 'dinner', 'date_intake' => date('Y-m-d 19:25:00', strtotime('-1 day'))],
        ['intakeLog_id' => 0, 'food_item' => 'Oat Latte', 'calories' => 160, 'protein' => 4, 'carbs' => 22, 'fat' => 6, 'meal_category' => 'snack', 'date_intake' => date('Y-m-d 11:15:00', strtotime('-2 days'))],
        ['intakeLog_id' => 0, 'food_item' => 'Tofu Rice Bowl', 'calories' => 580, 'protein' => 28, 'carbs' => 78, 'fat' => 16, 'meal_category' => 'lunch', 'date_intake' => date('Y-m-d 12:45:00', strtotime('-2 days'))],
        ['intakeLog_id' => 0, 'food_item' => 'Banana', 'calories' => 105, 'protein' => 1, 'carbs' => 27, 'fat' => 0, 'meal_category' => 'snack', 'date_intake' => date('Y-m-d 16:00:00', strtotime('-2 days'))],
        ['intakeLog_id' => 0, 'food_item' => 'Prawn Noodle Soup', 'calories' => 520, 'protein' => 32, 'carbs' => 58, 'fat' => 14, 'meal_category' => 'dinner', 'date_intake' => date('Y-m-d 18:50:00', strtotime('-3 days'))],
    ];
}
?>

<!DOCTYPE html>
<html lang="en"
    data-theme="<?php echo isset($_SESSION['user']) ? ($_SESSION['user']['theme_preference'] ?? 'system') : 'system'; ?>">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>History Log | BitBalance</title>

    <?php
    $pageComponents = ['sidebar', 'fab'];
    $pageCss = ['css/dashboard.css', 'css/components/intake-list.css', 'css/pages/dashboard-history.css'];
    include PROJECT_ROOT . 'views/head_css.php';
    ?>

    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.8/js/jquery.dataTables.min.js"></script>
    <script src="https://kit.fontawesome.com/b94f65ead2.js" crossorigin="anonymous"></script>
</head>

<body class="<?= htmlspecialchars($bodyClass ?? '', ENT_QUOTES) ?>">
    <?php include PROJECT_ROOT . 'views/header.php'; ?>
    <?php include PROJECT_ROOT . 'dashboard/views/sidebar.php'; ?>
    <?php include PROJECT_ROOT . 'dashboard/views/right-sidebar.php'; ?>

        <main class="dashboard-content">
            <div class="history-container">
                <?php if (!$isLoggedIn): ?>
                    <div class="demo-banner">
                        <i class="fas fa-flask"></i>
                        <span><strong>You're exploring a live demo.</strong> This is sample history — create a free account to save and edit your own food log.</span>
                        <a href="<?= BASE_URL ?>signup.php" class="demo-banner-cta">Get started free</a>
                    </div>
                <?php endif; ?>

                <section class="filter-card">
                    <div class="card-header">
                        <h3><i class="fas fa-history"></i> Intake History</h3>
                        <p class="subtitle">Review your past meals and nutritional data.</p>
                    </div>

                    <div class="filter-grid">
                        <div class="filter-item search-box">
                            <i class="fas fa-search search-icon"></i>
                            <input id="searchInput" type="text" placeholder="Search food, meal type...">
                        </div>

                        <div class="filter-item">
                            <div class="select-wrapper">
                                <select id="mealTypeFilter">
                                    <option value="">🍽️ All Meals</option>
                                    <option value="breakfast">🌅 Breakfast</option>
                                    <option value="lunch">☀️ Lunch</option>
                                    <option value="dinner">🌙 Dinner</option>
                                    <option value="snack">🍪 Snack</option>
                                </select>
                            </div>
                        </div>

                        <div class="filter-item date-group">
                            <div class="date-input">
                                <span class="date-label">From</span>
                                <input type="date" id="startDateFilter">
                            </div>
                            <div class="date-input">
                                <span class="date-label">To</span>
                                <input type="date" id="endDateFilter">
                            </div>
                        </div>
                    </div>
                </section>

                <section class="history-list-card">
                    <div class="table-responsive">
                        <!-- Column order matches dashboard-intake (Food → Cal → Macros → Cat → Time → Action),
                             with Date prepended. `modern-table--with-date` enables the mobile card layout
                             variant that adds a Date row at the top of each card. -->
                        <table id="logs-table" class="modern-table modern-table--with-date">
                            <thead>
                                <tr>
                                    <th>Date</th>
                                    <th>Food Item</th>
                                    <th>Calories</th>
                                    <th>Macros (g)</th>
                                    <th>Meal Category</th>
                                    <th>Time</th>
                                    <th class="row-actions-head">Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (!empty($historyData)): ?>
                                    <?php foreach ($historyData as $historyEntry): ?>
                                        <?php
                                        $entry = $historyEntry;
                                        $showDate = true;
                                        $hideActions = !$isLoggedIn;
                                        include PROJECT_ROOT . 'dashboard/views/_intake-row.php';
                                        unset($hideActions);
                                        ?>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>

                    <div id="custom-pagination" class="pagination-container"></div>
                </section>

            </div>
        </main>

    <?php if ($isLoggedIn): include PROJECT_ROOT . 'dashboard/views/quick-log-fab.php'; endif; ?>

    <?php include PROJECT_ROOT . 'views/footer.php'; ?>

    <script>
        $(document).ready(function () {
            // Init DataTable
            var table = $('#logs-table').DataTable({
                dom: 't<"bottom-controls"p>',
                pagingType: 'simple_numbers',
                pageLength: 10,
                order: [[0, 'desc']], // Sort by hidden timestamp
                language: {
                    emptyTable: "<div class='empty-state'><i class='fas fa-folder-open'></i><p>No records found.</p></div>"
                },
                columnDefs: [
                    { targets: 0, type: 'num' }, // Sort date as number
                    { targets: 3, orderable: false }, // Macros col not sortable
                    { targets: 6, orderable: false }  // Action col not sortable
                ]
            });

            // Move pagination
            $('.bottom-controls').appendTo('#custom-pagination');

            // 1. Search & Filter Logic (Giữ nguyên)
            $('#searchInput').on('keyup', function () { table.search(this.value).draw(); });
            $('#mealTypeFilter').on('change', function () { table.column(4).search(this.value).draw(); });

            $.fn.dataTable.ext.search.push(function (settings, data, dataIndex) {
                var min = $('#startDateFilter').val();
                var max = $('#endDateFilter').val();
                var dateTimestamp = $(table.cell(dataIndex, 0).node()).attr('data-sort'); // Lấy timestamp từ attribute
                var dateVal = new Date(parseInt(dateTimestamp) * 1000); // Chuyển sang Date object

                if (
                    (min === "" && max === "") ||
                    (min === "" && dateVal <= new Date(max + "T23:59:59")) ||
                    (new Date(min) <= dateVal && max === "") ||
                    (new Date(min) <= dateVal && dateVal <= new Date(max + "T23:59:59"))
                ) { return true; }
                return false;
            });

            $('#startDateFilter, #endDateFilter').on('change', function () { table.draw(); });

            // --- 2. DELETE LOGIC (AJAX) ---
            // Sử dụng Event Delegation để bắt sự kiện click cho cả các trang sau của bảng
            $('#logs-table tbody').on('click', '.deleteBtn', async function (e) {
                e.preventDefault();

                if (!confirm('Are you sure you want to delete this entry?')) return;

                const btn = $(this);
                const row = btn.closest('tr');
                // Lấy ID từ attribute data-id của tr
                const id = row.attr('data-id');

                // Tạo FormData
                const fd = new FormData();
                fd.append('intake_id', id);

                try {
                    // Gọi API xóa (Dùng lại handler của trang Intake)
                    const res = await fetch('handlers/delete_intake.php', {
                        method: 'POST',
                        headers: { 'X-Requested-With': 'fetch' },
                        body: fd
                    });
                    const data = await res.json();

                    if (data.ok) {
                        // Hiệu ứng mờ dần
                        row.fadeOut(300, function () {
                            // Quan trọng: Xóa dòng khỏi DataTables chứ không chỉ xóa khỏi DOM
                            // Nếu không DataTables sẽ bị lỗi phân trang
                            table.row(row).remove().draw(false);
                        });
                    } else {
                        alert(data.error || 'Failed to delete');
                    }
                } catch (err) {
                    console.error(err);
                    alert('Connection error');
                }
            });
        });
    </script>

    <?php if ($isLoggedIn): ?>
        <?php $modalTitle = 'Edit Entry'; include PROJECT_ROOT . 'dashboard/views/_edit-intake-modal.php'; ?>
        <?php include PROJECT_ROOT . 'dashboard/views/_intake-row-js.php'; ?>
        <script>
        $(document).ready(function () {
            // 1. Init DataTable
            var table = $('#logs-table').DataTable({
                destroy: true, // <--- QUAN TRỌNG: Thêm dòng này để fix lỗi reinitialise
                dom: 't<"bottom-controls"p>',
                pagingType: 'simple_numbers',
                pageLength: 10,
                order: [[0, 'desc']],
                language: { emptyTable: "<div class='empty-state'><p>No records found.</p></div>" },
                columnDefs: [
                    { targets: 0, type: 'num' },
                    { targets: 4, orderable: false },
                    { targets: 6, orderable: false }
                ]
            });

            // Nếu wrapper pagination đã có nội dung cũ (do re-init), hãy xóa đi trước khi append
            $('#custom-pagination').empty();
            $('.bottom-controls').appendTo('#custom-pagination');

            // Search & Filters
            // Cần unbind sự kiện cũ trước khi bind mới để tránh bị duplicate event khi reload
            $('#searchInput').off('keyup').on('keyup', function () { table.search(this.value).draw(); });
            $('#mealTypeFilter').off('change').on('change', function () { table.column(4).search(this.value).draw(); });

            // Xóa các search function cũ nếu có để tránh bị chồng chéo
            $.fn.dataTable.ext.search = [];

            // Date Filter Logic
            $.fn.dataTable.ext.search.push(function (settings, data, dataIndex) {
                var min = $('#startDateFilter').val();
                var max = $('#endDateFilter').val();
                // Kiểm tra an toàn để tránh lỗi undefined
                var cell = table.cell(dataIndex, 0);
                if (!cell) return false;

                var dateTimestamp = $(cell.node()).attr('data-sort');
                if (!dateTimestamp) return false;

                var dateVal = new Date(parseInt(dateTimestamp) * 1000);

                if (
                    (min === "" && max === "") ||
                    (min === "" && dateVal <= new Date(max + "T23:59:59")) ||
                    (new Date(min) <= dateVal && max === "") ||
                    (new Date(min) <= dateVal && dateVal <= new Date(max + "T23:59:59"))
                ) { return true; }
                return false;
            });

            $('#startDateFilter, #endDateFilter').off('change').on('change', function () { table.draw(); });


// --- 2. DELETE ACTION ---
            $('#logs-table tbody').off('click', '.deleteBtn').on('click', '.deleteBtn', async function(e) {
                e.preventDefault();
                
                // 1. Lấy dòng chứa nút bấm
                const btn = $(this);
                const row = btn.closest('tr');
                
                // 2. Lấy ID chuẩn xác từ attribute data-id
                const id = row.attr('data-id');

                // Debug: Kiểm tra xem ID có lấy được không
                console.log("Deleting ID:", id); 

                if (!id) {
                    alert('Error: Could not find entry ID');
                    return;
                }

                if (!confirm('Delete this entry?')) return;

                const fd = new FormData();
                fd.append('intake_id', id);

                try {
                    const res = await fetch('handlers/delete_intake.php', { 
                        method: 'POST', 
                        body: fd 
                    });

                    // 3. Kiểm tra phản hồi thô trước khi parse JSON (Để debug lỗi cú pháp PHP nếu có)
                    const textResponse = await res.text();
                    console.log("Server Response:", textResponse); 

                    let data;
                    try {
                        data = JSON.parse(textResponse);
                    } catch (e) {
                        console.error("Invalid JSON:", textResponse);
                        alert('Server error: Invalid response format');
                        return;
                    }

                    if (data.ok) {
                        // Hiệu ứng mờ dần và xóa khỏi DataTable
                        row.fadeOut(300, function() { 
                            // Xóa khỏi dữ liệu DataTable để không bị lỗi phân trang
                            table.row(row).remove().draw(false); 
                        });
                    } else { 
                        alert(data.error || 'Failed to delete'); 
                    }
                } catch (err) { 
                    console.error(err);
                    alert('Connection error'); 
                }
            });

            // --- 3. EDIT ACTION ---
            // Uses the shared IntakeRow helpers (loaded via _intake-row-js.php below)
            // for row reading + form filling + row patching. Column-index-based selectors
            // have been replaced with data-label selectors so they're order-independent.
            const editForm  = document.getElementById('editIntakeForm');
            let currentRow = null;

            // Open Modal
            $('#logs-table tbody').off('click.editopen').on('click.editopen', '.btn-edit', function () {
                currentRow = $(this).closest('tr');
                IntakeRow.fillEditForm(currentRow);
                IntakeRow.openModal();
            });

            // Close handlers (Cancel / X / backdrop) wired via the shared helper.
            IntakeRow.bindCloseHandlers();

            // Submit
            $(editForm).off('submit.editsave').on('submit.editsave', async function (e) {
                e.preventDefault();
                const fd = new FormData(editForm);
                try {
                    const res = await fetch('handlers/edit_intake.php', { method: 'POST', body: fd });
                    const data = await res.json();
                    if (!data.ok) {
                        alert(data.error || 'Update failed');
                        return;
                    }
                    if (currentRow) IntakeRow.updateRow(currentRow, data);
                    IntakeRow.closeModal();
                } catch (err) {
                    console.error(err);
                    alert('Error connecting to server');
                }
            });
        });
        </script>
    <?php endif; ?>
    <?php include PROJECT_ROOT . 'dashboard/views/local-time-script.php'; ?>
</body>

</html>
