<?php
header("Location: dashboard.php");
exit;


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
<html lang="<?= html_lang_attr() ?>"
    data-theme="<?php echo isset($_SESSION['user']) ? ($_SESSION['user']['theme_preference'] ?? 'system') : 'system'; ?>">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= t('history.title_alt') ?></title>

    <?php
    $pageComponents = ['sidebar', 'fab'];
    $pageCss = ['css/dashboard.css', 'css/components/intake-list.css', 'css/pages/dashboard-history.css'];
    include PROJECT_ROOT . 'views/head_css.php';
    ?>

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
                        <span><strong><?= t('history.demo_note') ?></strong> <?= t('history.demo_body') ?></span>
                        <a href="<?= BASE_URL ?>signup.php" class="demo-banner-cta"><?= t('dashboard.demo.cta') ?></a>
                    </div>
                <?php endif; ?>

                <section class="filter-card">
                    <div class="card-header">
                        <h3><i class="fas fa-history"></i> <?= t('history.heading') ?></h3>
                        <p class="subtitle"><?= t('history.subtitle_short') ?></p>
                    </div>

                    <div class="filter-grid">
                        <div class="filter-item search-box">
                            <i class="fas fa-search search-icon"></i>
                            <input id="searchInput" type="text" placeholder="<?= t('history.search_placeholder') ?>">
                        </div>

                        <div class="filter-item">
                            <div class="select-wrapper">
                                <select id="mealTypeFilter">
                                    <option value=""><?= t('history.all_meals') ?></option>
                                    <option value="breakfast"><?= t('history.meal.breakfast_emoji') ?></option>
                                    <option value="lunch"><?= t('history.meal.lunch_emoji') ?></option>
                                    <option value="dinner"><?= t('history.meal.dinner_emoji') ?></option>
                                    <option value="snack"><?= t('history.meal.snack_emoji') ?></option>
                                </select>
                            </div>
                        </div>

                        <div class="filter-item date-group">
                            <div class="date-input">
                                <span class="date-label"><?= t('history.from') ?></span>
                                <input type="date" id="startDateFilter">
                            </div>
                            <div class="date-input">
                                <span class="date-label"><?= t('history.to') ?></span>
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
                                    <th><?= t('history.col.date') ?></th>
                                    <th><?= t('history.col.food_item') ?></th>
                                    <th><?= t('history.col.calories') ?></th>
                                    <th><?= t('history.col.macros') ?></th>
                                    <th><?= t('history.col.meal_category') ?></th>
                                    <th><?= t('history.col.time') ?></th>
                                    <th class="row-actions-head"><?= t('history.col.action') ?></th>
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
    (function () {
        const HistoryTable = {
            allRows: [],
            currentPage: 1,
            rowsPerPage: 10,

            init() {
                this.allRows = Array.from(document.querySelectorAll('#logs-table tbody tr'));
                
                // Bind Filters
                const search = document.getElementById('searchInput');
                const meal = document.getElementById('mealTypeFilter');
                const start = document.getElementById('startDateFilter');
                const end = document.getElementById('endDateFilter');

                if (search) search.addEventListener('keyup', () => { this.currentPage = 1; this.filterAndPaginate(); });
                if (meal) meal.addEventListener('change', () => { this.currentPage = 1; this.filterAndPaginate(); });
                if (start) start.addEventListener('change', () => { this.currentPage = 1; this.filterAndPaginate(); });
                if (end) end.addEventListener('change', () => { this.currentPage = 1; this.filterAndPaginate(); });

                this.filterAndPaginate();
            },

            filterAndPaginate() {
                const searchVal = (document.getElementById('searchInput')?.value || '').toLowerCase().trim();
                const mealVal = (document.getElementById('mealTypeFilter')?.value || '').toLowerCase();
                const startVal = document.getElementById('startDateFilter')?.value || '';
                const endVal = document.getElementById('endDateFilter')?.value || '';

                const filteredRows = this.allRows.filter(row => {
                    // 1. Search text
                    if (searchVal) {
                        const foodText = (row.querySelector('td.fw-bold')?.textContent || '').toLowerCase();
                        if (!foodText.includes(searchVal)) return false;
                    }

                    // 2. Meal Category
                    if (mealVal) {
                        const badge = row.querySelector('.cat-badge');
                        let category = 'breakfast';
                        if (badge) {
                            badge.classList.forEach(cls => {
                                if (cls.startsWith('cat-') && cls !== 'cat-badge') {
                                    category = cls.slice(4);
                                }
                            });
                        }
                        if (category !== mealVal) return false;
                    }

                    // 3. Date Range
                    const dateCell = row.querySelector('td[data-sort]');
                    if (dateCell) {
                        const timestamp = parseInt(dateCell.getAttribute('data-sort'), 10);
                        const dateVal = new Date(timestamp * 1000);
                        
                        if (startVal) {
                            const startDate = new Date(startVal + "T00:00:00");
                            if (dateVal < startDate) return false;
                        }
                        if (endVal) {
                            const endDate = new Date(endVal + "T23:59:59");
                            if (dateVal > endDate) return false;
                        }
                    }

                    return true;
                });

                const tableContainer = document.querySelector('.table-responsive');
                const paginationContainer = document.getElementById('custom-pagination');
                let emptyState = document.querySelector('.history-list-card .empty-state');

                if (filteredRows.length === 0) {
                    if (!emptyState) {
                        emptyState = document.createElement('div');
                        emptyState.className = 'empty-state';
                        emptyState.innerHTML = `<i class="fas fa-folder-open"></i><p>${<?= json_encode(t_raw('history.empty_records')) ?>}</p>`;
                        tableContainer.parentNode.insertBefore(emptyState, paginationContainer);
                    }
                    tableContainer.style.display = 'none';
                    paginationContainer.style.display = 'none';
                    return;
                } else {
                    if (emptyState) emptyState.remove();
                    tableContainer.style.display = '';
                    paginationContainer.style.display = '';
                }

                const totalRows = filteredRows.length;
                const totalPages = Math.ceil(totalRows / this.rowsPerPage);
                
                if (this.currentPage > totalPages) this.currentPage = Math.max(1, totalPages);

                const startIndex = (this.currentPage - 1) * this.rowsPerPage;
                const endIndex = startIndex + this.rowsPerPage;

                // Show/hide rows
                this.allRows.forEach(row => row.style.display = 'none');
                filteredRows.slice(startIndex, endIndex).forEach(row => row.style.display = '');

                this.renderPagination(totalPages);
            },

            renderPagination(totalPages) {
                const container = document.getElementById('custom-pagination');
                if (!container) return;
                container.innerHTML = '';

                if (totalPages <= 1) return;

                // Prev
                const prevBtn = document.createElement('button');
                prevBtn.className = 'page-btn';
                prevBtn.innerHTML = '<i class="fas fa-chevron-left"></i>';
                prevBtn.disabled = this.currentPage === 1;
                prevBtn.addEventListener('click', () => {
                    if (this.currentPage > 1) {
                        this.currentPage--;
                        this.filterAndPaginate();
                    }
                });
                container.appendChild(prevBtn);

                // Pages
                for (let i = 1; i <= totalPages; i++) {
                    const pageBtn = document.createElement('button');
                    pageBtn.className = 'page-btn' + (i === this.currentPage ? ' active' : '');
                    pageBtn.textContent = i;
                    pageBtn.addEventListener('click', () => {
                        this.currentPage = i;
                        this.filterAndPaginate();
                    });
                    container.appendChild(pageBtn);
                }

                // Next
                const nextBtn = document.createElement('button');
                nextBtn.className = 'page-btn';
                nextBtn.innerHTML = '<i class="fas fa-chevron-right"></i>';
                nextBtn.disabled = this.currentPage === totalPages;
                nextBtn.addEventListener('click', () => {
                    if (this.currentPage < totalPages) {
                        this.currentPage++;
                        this.filterAndPaginate();
                    }
                });
                container.appendChild(nextBtn);
            }
        };

        window.HistoryTable = HistoryTable;

        document.addEventListener('DOMContentLoaded', () => {
            HistoryTable.init();
        });
    })();
    </script>

    <?php if ($isLoggedIn): ?>
        <?php $modalTitle = 'Edit Entry'; include PROJECT_ROOT . 'dashboard/views/_edit-intake-modal.php'; ?>
        <?php include PROJECT_ROOT . 'dashboard/views/_confirm-delete-modal.php'; ?>
        <?php include PROJECT_ROOT . 'dashboard/views/_intake-row-js.php'; ?>
        <script>
        document.addEventListener('DOMContentLoaded', () => {
            const tableController = window.HistoryTable;
            const tbody = document.querySelector('#logs-table tbody');
            if (!tbody || !tableController) return;

            // --- 1. Event Delegation for Row Actions ---
            let deleteRowTarget = null;
            const confirmDeleteModal = document.getElementById('confirmDeleteModal');
            const closeConfirmBtn = document.getElementById('closeConfirmDeleteModal');
            const cancelConfirmBtn = document.getElementById('cancelDeleteBtn');
            const doConfirmDeleteBtn = document.getElementById('confirmDeleteBtn');

            function closeDeleteConfirmModal() {
                if (confirmDeleteModal) confirmDeleteModal.classList.remove('active');
                deleteRowTarget = null;
            }

            if (confirmDeleteModal) {
                closeConfirmBtn.addEventListener('click', closeDeleteConfirmModal);
                cancelConfirmBtn.addEventListener('click', closeDeleteConfirmModal);
                confirmDeleteModal.addEventListener('click', e => {
                    if (e.target === confirmDeleteModal) closeDeleteConfirmModal();
                });
            }

            tbody.addEventListener('click', function (e) {
                // Delete button
                const deleteBtn = e.target.closest('.deleteBtn');
                if (deleteBtn) {
                    e.preventDefault();
                    deleteRowTarget = deleteBtn.closest('tr');
                    if (confirmDeleteModal) confirmDeleteModal.classList.add('active');
                    return;
                }

                // Edit button
                const editBtn = e.target.closest('.btn-edit');
                if (editBtn) {
                    e.preventDefault();
                    currentRow = editBtn.closest('tr');
                    IntakeRow.fillEditForm(currentRow);
                    IntakeRow.openModal();
                    return;
                }

                // Log Again button
                const logAgainBtn = e.target.closest('.btnLogAgain');
                if (logAgainBtn) {
                    e.preventDefault();
                    handleLogAgain(logAgainBtn);
                    return;
                }
            });

            // --- 2. DELETE CONFIRMATION ACTION ---
            const __histDelI18n = {
                deleted: <?= json_encode(current_locale() === 'vi' ? 'Đã xoá' : 'Entry deleted', JSON_UNESCAPED_UNICODE) ?>,
                undo: <?= json_encode(current_locale() === 'vi' ? 'Hoàn tác' : 'Undo', JSON_UNESCAPED_UNICODE) ?>,
                restoreFail: <?= json_encode(current_locale() === 'vi' ? 'Không thể khôi phục' : 'Could not restore', JSON_UNESCAPED_UNICODE) ?>,
                conn: <?= json_encode(current_locale() === 'vi' ? 'Lỗi kết nối' : 'Connection error', JSON_UNESCAPED_UNICODE) ?>
            };

            // Undo a delete on the history page. The table is paginated/filtered via
            // tableController, so the simplest correct re-sync is a reload after the
            // row is restored server-side (with its original date/time).
            async function undoDeleteHistory(snapshot) {
                try {
                    const fd = new FormData();
                    ['food_item', 'calories', 'protein', 'carbs', 'fat', 'meal_category', 'image_path', 'date_intake']
                        .forEach(k => fd.append(k, snapshot[k] != null ? snapshot[k] : ''));
                    fd.append('show_date', '1');
                    const res = await fetch('handlers/restore_intake.php', {
                        method: 'POST',
                        headers: { 'X-Requested-With': 'fetch' },
                        body: fd
                    });
                    const data = await res.json();
                    if (data.ok) {
                        location.reload();
                    } else {
                        showToast(data.error || __histDelI18n.restoreFail, { type: 'error' });
                    }
                } catch (err) {
                    showToast(__histDelI18n.conn, { type: 'error' });
                }
            }

            if (doConfirmDeleteBtn) {
                doConfirmDeleteBtn.addEventListener('click', async function () {
                    if (!deleteRowTarget) return;

                    const row = deleteRowTarget;
                    const id = row.getAttribute('data-id');
                    if (!id) {
                        showToast('Error: Could not find entry ID', { type: 'error' });
                        return;
                    }

                    doConfirmDeleteBtn.disabled = true;
                    const fd = new FormData();
                    fd.append('intake_id', id);

                    try {
                        const res = await fetch('handlers/delete_intake.php', { method: 'POST', body: fd });
                        const data = await res.json();

                        if (data.ok) {
                            const snapshot = data.deleted_row;
                            row.style.transition = 'opacity 0.3s, transform 0.3s';
                            row.style.opacity = '0';
                            row.style.transform = 'scale(0.95)';
                            setTimeout(() => {
                                row.remove();
                                const idx = tableController.allRows.indexOf(row);
                                if (idx > -1) tableController.allRows.splice(idx, 1);
                                tableController.filterAndPaginate();
                            }, 300);
                            closeDeleteConfirmModal();
                            showToast(__histDelI18n.deleted, {
                                type: 'success',
                                duration: 9000,
                                action: snapshot ? { label: __histDelI18n.undo, onClick: () => undoDeleteHistory(snapshot) } : null
                            });
                        } else {
                            showToast(data.error || 'Failed to delete', { type: 'error' });
                        }
                    } catch (err) {
                        console.error(err);
                        showToast('Connection error', { type: 'error' });
                    } finally {
                        doConfirmDeleteBtn.disabled = false;
                    }
                });
            }

            // --- 3. EDIT SUBMIT ACTION ---
            const editForm = document.getElementById('editIntakeForm');
            let currentRow = null;

            IntakeRow.bindCloseHandlers();

            if (editForm) {
                editForm.addEventListener('submit', async function (e) {
                    e.preventDefault();
                    const fd = new FormData(editForm);
                    try {
                        const res = await fetch('handlers/edit_intake.php', { method: 'POST', body: fd });
                        const data = await res.json();
                        if (!data.ok) {
                            showToast(data.error || 'Update failed', { type: 'error' });
                            return;
                        }
                        if (currentRow) {
                            IntakeRow.updateRow(currentRow, data);
                            tableController.filterAndPaginate();
                        }
                        IntakeRow.closeModal();
                    } catch (err) {
                        console.error(err);
                        showToast('Error connecting to server', { type: 'error' });
                    }
                });
            }

            // --- 4. QUICK LOG ACTION ---
            function parseIntakeRowMarkup(markup) {
                const template = document.createElement('template');
                template.innerHTML = '<table><tbody>' + String(markup || '').trim() + '</tbody></table>';
                return template.content.querySelector('tr');
            }

            async function handleLogAgain(btn) {
                const row = btn.closest('tr');
                const id = row.getAttribute('data-id');
                if (!id) {
                    showToast('Error: Could not find entry ID', { type: 'error' });
                    return;
                }

                btn.disabled = true;
                const originalHtml = btn.innerHTML;
                btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';

                const fd = new FormData();
                fd.append('intake_id', id);
                fd.append('show_date', '1'); // Ask handler to render the Date cell!

                try {
                    const res = await fetch('handlers/quick_log_from_history.php', { method: 'POST', body: fd });
                    const data = await res.json();

                    if (data.ok && data.new_row) {
                        // Success toast
                        if (typeof showLoggingToast === 'function') {
                            showLoggingToast('Food logged!', data.food_item + ' • ' + data.calories + ' kcal');
                        }

                        // Parse the compiled row markup. Wrap it in a tbody so
                        // browsers preserve the <tr> instead of dropping it.
                        const newRow = parseIntakeRowMarkup(data.new_row);

                        if (newRow) {
                            // Prepend to tbody
                            tbody.insertBefore(newRow, tbody.firstChild);
                            // Prepend to controller array
                            tableController.allRows.unshift(newRow);
                            // Redraw table
                            tableController.filterAndPaginate();

                            // Soft green flash
                            newRow.style.transition = 'background-color 0.3s';
                            newRow.style.backgroundColor = 'rgba(46, 204, 113, 0.2)';
                            setTimeout(() => { newRow.style.backgroundColor = ''; }, 1000);
                        }

                        // XP Popups
                        if (data.xp) {
                            if (data.xp.added && window.showXpPopup) {
                                window.showXpPopup(data.xp.added, btn);
                            }
                            if (data.xp.summary && window.updateXpChip) {
                                setTimeout(() => window.updateXpChip(data.xp.summary), 200);
                            }
                            if (data.xp.levelup && window.showLevelUpToast) {
                                setTimeout(() => window.showLevelUpToast(data.xp.levelup), 600);
                            }
                        }
                    } else {
                        showToast(data.error || 'Failed to log entry', { type: 'error' });
                    }
                } catch (err) {
                    console.error(err);
                    showToast('Connection error', { type: 'error' });
                } finally {
                    btn.innerHTML = originalHtml;
                    btn.disabled = false;
                }
            }
        });
        </script>
        <?php include PROJECT_ROOT . 'dashboard/views/logging-toast.php'; ?>
    <?php endif; ?>
    <?php include PROJECT_ROOT . 'dashboard/views/local-time-script.php'; ?>
</body>

</html>
