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

    <?php
    $pageComponents = ['sidebar', 'fab'];
    $pageCss = ['css/dashboard.css?v=' . time(), 'css/pages/dashboard-history.css'];
    include PROJECT_ROOT . 'views/head_css.php';
    ?>

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
                                    <th>Macros (g)</th>
                                    <th>Time</th>
                                    <th style="text-align: right;">Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (!empty($historyData)): ?>
                                    <?php foreach ($historyData as $entry):
                                        $p = (float) ($entry['protein'] ?? 0);
                                        $c = (float) ($entry['carbs']   ?? 0);
                                        $f = (float) ($entry['fat']     ?? 0);
                                        $pD = rtrim(rtrim(number_format($p, 1, '.', ''), '0'), '.'); if ($pD === '') $pD = '0';
                                        $cD = rtrim(rtrim(number_format($c, 1, '.', ''), '0'), '.'); if ($cD === '') $cD = '0';
                                        $fD = rtrim(rtrim(number_format($f, 1, '.', ''), '0'), '.'); if ($fD === '') $fD = '0';
                                    ?>
                                        <tr data-id="<?= $entry['intakeLog_id'] ?>"
                                            data-protein="<?= htmlspecialchars($pD) ?>"
                                            data-carbs="<?= htmlspecialchars($cD) ?>"
                                            data-fat="<?= htmlspecialchars($fD) ?>">
                                            <td data-label="Date" data-sort="<?= strtotime($entry['date_intake']) ?>">
                                                <div class="date-cell">
                                                    <span class="day" data-iso="<?= toIsoVN($entry['date_intake']) ?>"
                                                        data-tz-format="date-day"><?= date('d', strtotime($entry['date_intake'])) ?></span>
                                                    <span class="month" data-iso="<?= toIsoVN($entry['date_intake']) ?>"
                                                        data-tz-format="date-monthyear"><?= date('M Y', strtotime($entry['date_intake'])) ?></span>
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
                                            <td data-label="Macros" class="macros-cell">
                                                <span class="macro-chip p">P <?= $pD ?>g</span>
                                                <span class="macro-chip c">C <?= $cD ?>g</span>
                                                <span class="macro-chip f">F <?= $fD ?>g</span>
                                            </td>
                                            <td data-label="Time" class="text-muted"
                                                data-iso="<?= toIsoVN($entry['date_intake']) ?>"
                                                data-tz-format="time">
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
                    { targets: 4, orderable: false }, // Macros col not sortable
                    { targets: 6, orderable: false }  // Action col not sortable
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
                    <div class="form-group macros-input-group">
                        <label>Macros <small>(grams, optional)</small></label>
                        <div class="macros-input-row">
                            <div class="macro-input p">
                                <label for="edit_protein">P</label>
                                <input type="number" id="edit_protein" name="protein" min="0" max="999" step="0.1" placeholder="0">
                            </div>
                            <div class="macro-input c">
                                <label for="edit_carbs">C</label>
                                <input type="number" id="edit_carbs" name="carbs" min="0" max="999" step="0.1" placeholder="0">
                            </div>
                            <div class="macro-input f">
                                <label for="edit_fat">F</label>
                                <input type="number" id="edit_fat" name="fat" min="0" max="999" step="0.1" placeholder="0">
                            </div>
                        </div>
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
                document.getElementById('edit_protein').value = currentRow.attr('data-protein') || '';
                document.getElementById('edit_carbs').value   = currentRow.attr('data-carbs')   || '';
                document.getElementById('edit_fat').value     = currentRow.attr('data-fat')     || '';

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

                            const fmt = v => {
                                const n = parseFloat(v) || 0;
                                return Number.isInteger(n) ? n : n.toFixed(1).replace(/\.?0+$/, '');
                            };
                            const pDisp = fmt(data.protein), cDisp = fmt(data.carbs), fDisp = fmt(data.fat);
                            currentRow.find('td:eq(4)').html(
                                `<span class="macro-chip p">P ${pDisp}g</span>` +
                                `<span class="macro-chip c">C ${cDisp}g</span>` +
                                `<span class="macro-chip f">F ${fDisp}g</span>`
                            );
                            currentRow.attr('data-protein', pDisp);
                            currentRow.attr('data-carbs',   cDisp);
                            currentRow.attr('data-fat',     fDisp);

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
    <?php include PROJECT_ROOT . 'dashboard/views/local-time-script.php'; ?>
</body>

</html>