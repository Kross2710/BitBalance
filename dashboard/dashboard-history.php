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
$mealTypes = ['breakfast', 'lunch', 'dinner', 'snack'];
$displayUser = $isLoggedIn ? $user['user_name'] : "Guest";

if ($isLoggedIn) {
    $historyData = getUserIntakeHistory($user['user_id'] ?? null);
}
?>

<!DOCTYPE html>
<html lang="en"
    data-theme="<?php echo isset($_SESSION['user']) ? ($_SESSION['user']['theme_preference'] ?? 'light') : 'light'; ?>">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>History Log | BitBalance</title>

    <?php include PROJECT_ROOT . 'views/head_css.php'; ?>
    <link rel="stylesheet" href="<?= BASE_URL ?>css/dashboard.css?v=<?php echo time(); ?>">

    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.8/js/jquery.dataTables.min.js"></script>
    <script src="https://kit.fontawesome.com/b94f65ead2.js" crossorigin="anonymous"></script>
</head>

<body>
    <?php include PROJECT_ROOT . 'views/header.php'; ?>
    <?php include PROJECT_ROOT . 'dashboard/views/sidebar.php'; ?>

    <?php if ($isLoggedIn): ?>
        <?php include PROJECT_ROOT . 'dashboard/views/right-sidebar.php'; ?>

        <main class="dashboard-content">
            <div class="history-container">

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
                        <table id="logs-table" class="modern-table">
                            <thead>
                                <tr>
                                    <th>Date</th>
                                    <th>Meal Category</th>
                                    <th>Food Item</th>
                                    <th>Calories</th>
                                    <th>Time</th>
                                    <th style="text-align: right;">Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (!empty($historyData)): ?>
                                    <?php foreach ($historyData as $entry): ?>
                                        <tr data-id="<?= $entry['intakeLog_id'] ?>">
                                            <td data-label="Date" data-sort="<?= strtotime($entry['date_intake']) ?>">
                                                <div class="date-cell">
                                                    <span class="day"><?= date('d', strtotime($entry['date_intake'])) ?></span>
                                                    <span class="month"><?= date('M Y', strtotime($entry['date_intake'])) ?></span>
                                                </div>
                                            </td>
                                            <td data-label="Category">
                                                <span class="cat-badge cat-<?= strtolower($entry['meal_category']) ?>">
                                                    <?= ucfirst($entry['meal_category']) ?>
                                                </span>
                                            </td>
                                            <td data-label="Food" class="fw-bold text-primary">
                                                <?= htmlspecialchars(ucfirst($entry['food_item'])) ?>
                                            </td>
                                            <td data-label="Calories" class="cal-cell">
                                                <span class="cal-val"><?= htmlspecialchars($entry['calories']) ?></span> kcal
                                            </td>
                                            <td data-label="Time" class="text-muted">
                                                <?= date('H:i', strtotime($entry['date_intake'])) ?>
                                            </td>
                                            <td style="text-align: right;">
                                                <button type="button" class="btn-edit" title="Edit Entry">
                                                    <i class="fas fa-edit"></i>
                                                </button>
                                                <button type="button" class="btn-delete deleteBtn" title="Delete Entry">
                                                    <i class="fas fa-trash-alt"></i>
                                                </button>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>

                    <div id="custom-pagination" class="pagination-container"></div>
                </section>

            </div>
        </main>
    <?php else: ?>
        <main class="dashboard-content" style="text-align:center; margin-top:100px;">
            <h2>Please log in to access your History.</h2>
            <a href="<?= BASE_URL ?>login.php" class="btn-primary">Sign In</a>
        </main>
    <?php endif; ?>

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
                    { targets: 4, orderable: false } // Disable sort for Action column
                ]
            });

            // Move pagination
            $('.bottom-controls').appendTo('#custom-pagination');

            // 1. Search & Filter Logic (Giữ nguyên)
            $('#searchInput').on('keyup', function () { table.search(this.value).draw(); });
            $('#mealTypeFilter').on('change', function () { table.column(1).search(this.value).draw(); });

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

    <div id="editIntakeModal" class="modal">
        <div class="modal-content">
            <span class="close-modal" id="closeEditModal">&times;</span>
            <h3>Edit Entry</h3>
            <form id="editIntakeForm">
                <input type="hidden" id="edit_intake_id" name="intake_id">
                <div class="modal-body">
                    <div class="form-group">
                        <label>Food Name</label>
                        <input type="text" id="edit_food_item" name="food_item" required>
                    </div>
                    <div class="form-group">
                        <label>Calories</label>
                        <input type="number" id="edit_calories" name="calories" required>
                    </div>
                    <div class="form-group">
                        <label>Category</label>
                        <select id="edit_meal_category" name="meal_category" required>
                            <option value="breakfast">Breakfast</option>
                            <option value="lunch">Lunch</option>
                            <option value="dinner">Dinner</option>
                            <option value="snack">Snack</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn-cancel" id="cancelEditBtn">Cancel</button>
                    <button type="submit" class="btn-submit">Save Changes</button>
                </div>
            </form>
        </div>
    </div>
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
                    { targets: 5, orderable: false }
                ]
            });

            // Nếu wrapper pagination đã có nội dung cũ (do re-init), hãy xóa đi trước khi append
            $('#custom-pagination').empty();
            $('.bottom-controls').appendTo('#custom-pagination');

            // Search & Filters
            // Cần unbind sự kiện cũ trước khi bind mới để tránh bị duplicate event khi reload
            $('#searchInput').off('keyup').on('keyup', function () { table.search(this.value).draw(); });
            $('#mealTypeFilter').off('change').on('change', function () { table.column(1).search(this.value).draw(); });

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

            // --- 3. EDIT ACTION (Dynamic) ---
            const editModal = document.getElementById('editIntakeModal');
            const editForm = document.getElementById('editIntakeForm');
            let currentRow = null;

            // Mở Modal
            $('#logs-table tbody').off('click', '.btn-edit').on('click', '.btn-edit', function () {
                currentRow = $(this).closest('tr');
                const id = currentRow.attr('data-id');

                const catTextRaw = currentRow.find('td:eq(1)').text().trim();
                const foodText = currentRow.find('td:eq(2)').text().trim();
                const calText = currentRow.find('td:eq(3)').text().trim().replace(/\D/g, '');

                document.getElementById('edit_intake_id').value = id;
                document.getElementById('edit_food_item').value = foodText;
                document.getElementById('edit_calories').value = calText;
                document.getElementById('edit_meal_category').value = catTextRaw.toLowerCase();

                editModal.style.display = 'block';
            });

            // Đóng Modal
            $('#closeEditModal, #cancelEditBtn').off('click').on('click', function () {
                editModal.style.display = 'none';
            });

            // Xử lý Submit Edit
            // Clone node để remove tất cả event listener cũ (tránh submit nhiều lần)
            const newEditForm = editForm.cloneNode(true);
            editForm.parentNode.replaceChild(newEditForm, editForm);

            newEditForm.addEventListener('submit', async e => {
                e.preventDefault();
                const fd = new FormData(newEditForm);

                try {
                    const res = await fetch('handlers/edit_intake.php', {
                        method: 'POST',
                        body: fd
                    });
                    const data = await res.json();

                    if (data.ok) {
                        if (currentRow) {
                            // Cập nhật DOM
                            const newCat = data.meal_category;
                            const newLabel = newCat.charAt(0).toUpperCase() + newCat.slice(1);
                            currentRow.find('td:eq(1)').html(`<span class="cat-badge cat-${newCat}">${newLabel}</span>`);
                            currentRow.find('td:eq(2)').text(data.food_item);
                            currentRow.find('td:eq(3)').html(`<span class="cal-val">${data.calories}</span> kcal`);

                            // Invalidate row data trong DataTable để search vẫn đúng
                            // table.row(currentRow).invalidate().draw(false); 

                            currentRow.css('background-color', 'rgba(46, 204, 113, 0.2)');
                            setTimeout(() => currentRow.css('background-color', ''), 500);
                        }
                        editModal.style.display = 'none';
                    } else {
                        alert(data.error || 'Update failed');
                    }
                } catch (err) {
                    console.error(err);
                    alert('Error connecting to server');
                }
            });

            window.onclick = function (event) {
                if (event.target == editModal) editModal.style.display = 'none';
            }
        });
    </script>
</body>

</html>

<style>
    .history-container {
        max-width: 1000px;
        margin: 0 auto;
        display: flex;
        flex-direction: column;
        gap: 20px;
    }

    /* 3. FILTER CARD */
    .filter-card {
        background: var(--card-bg);
        border-radius: var(--border-radius);
        padding: 25px;
        box-shadow: var(--shadow-soft);
    }

    .card-header h3 {
        margin: 0;
        color: var(--text-primary);
        font-size: 1.4rem;
    }

    .subtitle {
        margin: 5px 0 20px 0;
        color: var(--text-secondary);
        font-size: 0.9rem;
    }

    .filter-grid {
        display: grid;
        grid-template-columns: 2fr 1fr 2fr;
        /* Search rộng hơn, Meal nhỏ, Date rộng */
        gap: 15px;
        align-items: center;
    }

    /* Inputs Styling */
    .filter-item input,
    .filter-item select {
        width: 100%;
        padding: 12px 15px;
        border: 1px solid #e1e4e8;
        border-radius: 10px;
        font-size: 0.95rem;
        background: var(--bg-body);
        color: var(--text-primary);
        transition: all 0.2s;
    }

    .filter-item input:focus,
    .filter-item select:focus {
        border-color: var(--primary-color);
        outline: none;
        background: var(--card-bg);
        box-shadow: 0 0 0 3px rgba(74, 126, 227, 0.1);
    }

    .search-box {
        position: relative;
    }

    .search-box input {
        padding-left: 40px;
    }

    .search-icon {
        position: absolute;
        left: 15px;
        top: 50%;
        transform: translateY(-50%);
        color: #adb5bd;
    }

    .date-group {
        display: flex;
        gap: 10px;
    }

    .date-input {
        position: relative;
        flex: 1;
    }

    .date-label {
        position: absolute;
        top: -8px;
        left: 10px;
        background: var(--card-bg);
        padding: 0 5px;
        font-size: 0.75rem;
        color: var(--text-secondary);
        font-weight: 600;
    }

    /* 4. HISTORY TABLE CARD */
    .history-list-card {
        background: var(--card-bg);
        border-radius: var(--border-radius);
        padding: 20px;
        box-shadow: var(--shadow-soft);
    }

    .modern-table {
        width: 100%;
        border-collapse: separate;
        border-spacing: 0;
    }

    .modern-table th {
        text-align: left;
        padding: 15px;
        color: var(--text-secondary);
        font-size: 0.8rem;
        text-transform: uppercase;
        border-bottom: 2px solid #f0f0f0;
    }

    .modern-table td {
        padding: 15px;
        border-bottom: 1px solid #f0f0f0;
        vertical-align: middle;
        color: var(--text-primary);
    }

    .modern-table tr:hover td {
        background-color: rgba(0, 0, 0, 0.01);
    }

    /* Date Cell Styling */
    .date-cell {
        display: flex;
        flex-direction: column;
        line-height: 1.2;
    }

    .date-cell .day {
        font-size: 1.1rem;
        font-weight: 700;
        color: var(--text-primary);
    }

    .date-cell .month {
        font-size: 0.8rem;
        color: var(--text-secondary);
        text-transform: uppercase;
    }

    /* Badges */
    .cat-badge {
        font-size: 0.75rem;
        padding: 5px 12px;
        border-radius: 20px;
        font-weight: 700;
        text-transform: uppercase;
        display: inline-block;
    }

    .cat-breakfast {
        background: #ffebee;
        color: #ef5350;
    }

    .cat-lunch {
        background: #e3f2fd;
        color: #42a5f5;
    }

    .cat-dinner {
        background: #f3e5f5;
        color: #ab47bc;
    }

    .cat-snack {
        background: #fff3e0;
        color: #ffa726;
    }

    .fw-bold {
        font-weight: 600;
    }

    .text-primary {
        color: var(--primary-color);
    }

    .cal-val {
        font-weight: 800;
        font-size: 1rem;
    }

    /* 5. PAGINATION CUSTOMIZATION (DataTables override) */
    .pagination-container {
        display: flex;
        justify-content: flex-end;
        padding-top: 20px;
    }

    .dataTables_paginate .paginate_button {
        padding: 6px 12px;
        margin-left: 5px;
        border-radius: 6px;
        cursor: pointer;
        color: var(--text-secondary);
        background: var(--bg-body);
        border: none;
    }

    .dataTables_paginate .paginate_button.current {
        background: var(--primary-color);
        color: white !important;
        font-weight: bold;
    }

    .dataTables_paginate .paginate_button:hover:not(.current) {
        background: #e9ecef;
        color: var(--text-primary) !important;
    }

    .empty-state {
        text-align: center;
        padding: 40px;
        color: var(--text-secondary);
    }

    .empty-state i {
        font-size: 3rem;
        margin-bottom: 10px;
        opacity: 0.5;
    }

    /* 6. RESPONSIVE DESIGN */
    @media (max-width: 1200px) {
        .dashboard-content {
            margin-left: 0;
            margin-right: 0;
        }
    }

    @media (max-width: 900px) {
        .filter-grid {
            grid-template-columns: 1fr;
        }

        /* Stack filters vertically */

        /* Mobile Card View for Table */
        .modern-table thead {
            display: none;
        }

        .modern-table tr {
            display: block;
            margin-bottom: 15px;
            background: var(--bg-body);
            border-radius: 12px;
            padding: 15px;
            border: 1px solid rgba(0, 0, 0, 0.05);
        }

        .modern-table td {
            display: flex;
            justify-content: space-between;
            padding: 8px 0;
            border: none;
            align-items: center;
        }

        .modern-table td::before {
            content: attr(data-label);
            font-weight: 600;
            color: var(--text-secondary);
            font-size: 0.85rem;
        }
    }
</style>