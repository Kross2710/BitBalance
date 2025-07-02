<?php
require_once __DIR__ . '/../include/init.php';
require_once __DIR__ . '/handlers/dashboard_data.php';
require_once __DIR__ . '/../include/handlers/log_attempt.php';

if ($isLoggedIn) {
    // Log the user activity
    log_attempt($pdo, $user['user_id'], 'view', 'User ' . $user['user_id'] . ' clicked on dashboard food', 'dashboard', null);
}

$activePage = 'intake';
$activeHeader = 'dashboard';

$status = 'Unset';
$statusClass = 'unset';

if (!empty($userGoal)) {
    if ($totalCalories > $userGoal) {
        $status = 'Overlimit';
        $statusClass = 'overlimit';
    } else {
        $status = 'Ongoing';
        $statusClass = 'ongoing';
    }
}

$error_message = '';
if (isset($_GET['error'])) {
    $error_message = htmlspecialchars($_GET['error']); // Prevent XSS
}
$success_message = '';
if (isset($_GET['success'])) {
    $success_message = htmlspecialchars($_GET['success']); // Prevent XSS
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
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="https://kit.fontawesome.com/b94f65ead2.js" crossorigin="anonymous"></script>
</head>

<body>
    <?php
    include PROJECT_ROOT . 'views/header.php';
    include PROJECT_ROOT . 'dashboard/views/sidebar.php';
    ?>

    <?php if ($isLoggedIn): ?>
        <?php include PROJECT_ROOT . 'dashboard/views/right-sidebar.php'; ?>
        <main class="dashboard">
            <!-- Top widget: progress and gauge -->
            <section class="progress-widget">
                <div class="progress-card">
                    <h3>Today</h3>
                    <div class="progress-value">
                        <span><?php echo $totalCalories; ?> calories</span>
                    </div>
                    <div class="progress-bar">
                        <div class="progress-fill" id="progressFill" style="width: 0%;"></div>
                    </div>
                    <script>
                        document.addEventListener('DOMContentLoaded', function () {
                            var fill = document.getElementById('progressFill');
                            setTimeout(function () {
                                fill.style.width = '<?php echo $progressPercentage; ?>%';
                            }, 100); // slight delay for smooth transition
                        });
                    </script>
                    <div class="progress-labels">
                        <span>Goal</span>
                        <span>
                            <?php echo $userGoal ? $userGoal . '' : 'Set your goal'; ?>
                        </span>
                    </div>
                </div>
            </section>
            <section class="intake-form">
                <?php if (!empty($error_message)): ?>
                    <div class="error-message"
                        style="color: #d32f2f; margin-bottom: 15px; padding: 12px; background-color: #ffebee; border: 1px solid #e57373; border-radius: 5px; font-weight: bold;">
                        <i class="fas fa-exclamation-triangle" style="margin-right: 8px;"></i>
                        <?php echo htmlspecialchars($error_message); ?>
                    </div>
                <?php endif; ?>
                <?php if (!empty($success_message)): ?>
                    <div class="success-message"
                        style="color: #388e3c; margin-bottom: 15px; padding: 12px; background-color: #e8f5e9; border: 1px solid #81c784; border-radius: 5px; font-weight: bold;">
                        <i class="fas fa-check-circle" style="margin-right: 8px;"></i>
                        <?php echo htmlspecialchars($success_message); ?>
                    </div>
                <?php endif; ?>
                <h3>Log Your Intake</h3>
                <form id="intakeForm" action="handlers/process_intake.php" method="POST">
                    <div class="form-row">
                        <label for="food_item">Food Item:</label>
                        <input type="text" id="food_item" name="food_item" required>
                    </div>
                    <div class="form-row">
                        <label for="unit_toggle">Unit:</label>
                        <select id="unit_toggle">
                            <option value="cal">Calories</option>
                            <option value="kj">Kilojoules</option>
                        </select>
                    </div>
                    <div class="form-row">
                        <label for="calories">Calories:</label>
                        <input type="number" id="calories" name="calories" required>
                    </div>
                    <div class="form-row">
                        <label for="meal_category">Category:</label>
                        <select id="meal_category" name="meal_category" required>
                            <option value="" disabled selected>Select a category</option>
                            <option value="breakfast">Breakfast</option>
                            <option value="lunch">Lunch</option>
                            <option value="dinner">Dinner</option>
                            <option value="snack">Snack</option>
                        </select>
                    </div>
                    <p>
                        <strong>Note:</strong> If unsure about the calories, you can use
                        <a href="https://chat.openai.com/chat" target="_blank" rel="noopener noreferrer"
                            style="color: #4a7ee3; text-decoration: underline;">
                            ChatGPT</a> to estimate them.
                        <br>
                    </p>
                    <button type="submit" class="btn-primary">Log Intake</button>
                </form>
            </section>
            <!-- Table to display logged food items -->
            <section class="intake-table">
                <div class="table-responsive">
                    <table>
                        <thead>
                            <tr>
                                <th>Food Item</th>
                                <th>Calories</th>
                                <th>Category</th>
                                <th>Logged At</th>
                                <th>Options</th>
                            </tr>
                        </thead>
                        <tbody id="intakeTableBody">
                            <?php if (empty($intakeLog)): ?>
                                <!-- placeholder row will be injected by JS -->
                            <?php endif; ?>
                            <?php foreach ($intakeLog as $log): ?>
                                <tr data-id="<?= $log['intakeLog_id']; ?>">
                                    <td data-label="Food Item" style="font-weight:bold;">
                                        <?= htmlspecialchars($log['food_item']); ?>
                                    </td>
                                    <td data-label="Calories"><?= htmlspecialchars($log['calories']); ?></td>
                                    <td data-label="Category"><?= htmlspecialchars(ucfirst($log['meal_category'])); ?></td>
                                    <td data-label="Logged At" class="logged-at" data-utc="<?= htmlspecialchars($log['date_intake']); ?>"><?= date('H:i', strtotime($log['date_intake'])); ?></td>
                                    <td>
                                        <button type="button" class="deleteBtn"
                                            style="background:#e55039;color:#fff;border:none;border-radius:4px;padding:4px 10px;cursor:pointer;">
                                            Delete
                                        </button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </section>
        </main>
    <?php else: ?>
        <main class="dashboard" style="text-align:center; margin-top:40px;">
            <h2>Please log in to access your Dashboard.</h2>
            <button class="btn-primary"><a href="<?= BASE_URL ?>login.php" class="btn-primary">Sign In</a></button>
        </main>
    <?php endif; ?>

    <?php
    include PROJECT_ROOT . 'views/footer.php';
    ?>

    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const form = document.getElementById('intakeForm');
            const body = document.getElementById('intakeTableBody');
            const total = document.querySelector('.progress-value span');
            const fill = document.getElementById('progressFill');
            const unitToggle = document.getElementById('unit_toggle');
            const calorieLabel = document.querySelector('label[for="calories"]');

            // --- Helper for dynamic "No intake logged yet." row ---
            const noRowHTML = `
              <tr id="noIntakeRow">
                <td colspan="5">No intake logged yet.</td>
              </tr>`;
            function updateNoIntakeRow() {
                // count rows that have an intake id
                const hasRows = body.querySelector('tr[data-id]') !== null;
                const currentPlaceholder = document.getElementById('noIntakeRow');

                if (!hasRows && !currentPlaceholder) {
                    body.insertAdjacentHTML('beforeend', noRowHTML);
                } else if (hasRows && currentPlaceholder) {
                    currentPlaceholder.remove();
                }
            }
            // initial check
            updateNoIntakeRow();

            // Unit label dynamic update
            if (unitToggle && calorieLabel) {
                unitToggle.addEventListener('change', () => {
                    calorieLabel.textContent = unitToggle.value === 'kj' ? 'Kilojoules:' : 'Calories:';
                });
            }

            // Form submission handler
            form.addEventListener('submit', async e => {
                e.preventDefault(); // Prevent page from reloading
                const fd = new FormData(form); // Collect form data
            
                // Convert calories to kilojoules if unit is kj
                if (unitToggle && unitToggle.value === 'kj') {
                    const kj = parseFloat(fd.get('calories'));
                    const cal = kj / 4.184;
                    fd.set('calories', Math.round(cal));
                }
            
                const res = await fetch(form.action, {
                    method: 'POST',
                    headers: { 'X-Requested-With': 'fetch' }, // indicate this is an AJAX request
                    body: fd
                });
                const data = await res.json(); // Expect JSON response
            
                if (data.ok) {

                    body.insertAdjacentHTML('afterbegin', data.new_row); // Add new row to the table
                    // Convert UTC timestamp in new row to user's local time
                    const newRow = body.firstElementChild;
                    const dateCell = newRow.querySelector('td[data-label="Logged At"]');
                    if (dateCell && dateCell.dataset.utc) {
                        const utcDate = new Date(dateCell.dataset.utc);
                        if (!isNaN(utcDate)) {
                            const options = {
                                hour: '2-digit',
                                minute: '2-digit',
                                hour12: false
                            };
                            dateCell.textContent = utcDate.toLocaleTimeString('en-GB', options);
                        }
                    }

                    total.textContent = data.total + ' calories'; // Update total calories
                    fill.style.width = data.percentage + '%'; // Update progress bar width
                    form.reset(); // Clear the form inputs
                    // Reset label if needed after form reset
                    if (unitToggle && calorieLabel) {
                        calorieLabel.textContent = unitToggle.value === 'kj' ? 'Kilojoules:' : 'Calories:';
                    }
                    updateNoIntakeRow();
                } else {
                    alert(data.error); // Show if any error occurred
                }
            });

            // Delete button handler
            body.addEventListener('click', async e => {
                if (!e.target.classList.contains('deleteBtn')) return;
                e.preventDefault();

                const row = e.target.closest('tr');
                const id = row.dataset.id;

                const fd = new FormData();
                fd.append('intake_id', id);

                const res = await fetch('handlers/delete_intake.php', {
                    method: 'POST',
                    headers: { 'X-Requested-With': 'fetch' },
                    body: fd
                });

                const data = await res.json();
                if (data.ok) {
                    row.remove();
                    total.textContent = data.total + ' calories';
                    fill.style.width = data.percentage + '%';
                    updateNoIntakeRow();
                } else {
                    alert(data.error);
                }
            });
        });
    </script>
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

    /* ---- Progress Widget & Card ---- */
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

    /* ---- Form Responsive ---- */
    .intake-form {
        margin-top: 15px;
        width: 100%;
        max-width: 400px;
        padding: 12px 4px;
    }

    .intake-form h3 {
        margin-bottom: 15px;
        font-size: 1.4em;
    }

    .intake-form .form-row {
        display: flex;
        align-items: center;
        margin-bottom: 13px;
    }

    .intake-form label {
        width: 100px;
        font-weight: bold;
        font-size: 1em;
    }

    .intake-form input[type="text"],
    .intake-form input[type="number"] {
        flex: 1;
        padding: 7px;
        border: 1px solid #ccc;
        border-radius: 5px;
    }

    .intake-form input[type="text"]:focus,
    .intake-form input[type="number"]:focus {
        border-color: #4a7ee3;
        outline: none;
    }

    .intake-form select {
        flex: 1;
        padding: 7px;
        border: 1px solid #ccc;
        border-radius: 5px;
        background-color: #fff;
    }

    .intake-form select:focus {
        border-color: #4a7ee3;
        outline: none;
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

    /* Responsive */
    @media (max-width: 900px) {
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

        .progress-value {
            font-size: 1em;
        }

        main.dashboard {
            flex-direction: column;
        }

        .progress-widget,
        .intake-form,
        .intake-table {
            width: 100%;
        }
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

    @media (max-width: 900px) {

        .dashboard-intake,
        .intake-form,
        .progress-widget {
            width: 98%;
            padding: 8px;
            max-width: 100%;
        }

        .intake-form .form-row label {
            width: 100px;
        }
    }

    @media (max-width: 700px) {
        .sidebar {
            width: 100%;
            position: static;
            display: flex;
            flex-direction: row;
            justify-content: space-around;
            padding: 8px 0;
        }

        .sidebar a {
            margin-bottom: 0;
            margin-right: 6px;
            font-size: 1.1rem;
            padding: 8px 6px;
        }

        .dashboard {
            margin-left: 0;
            margin-right: 0;
            box-shadow: none;
            border: none;
            padding: 5px 0 0 0;
        }

        .progress-widget {
            flex-direction: column;
            gap: 18px;
            align-items: stretch;
        }

        .progress-card {
            width: 100%;
            min-width: 0;
            box-sizing: border-box;
            align-items: flex-start;
        }

        .intake-form {
            width: 100%;
            max-width: 100%;
            padding: 10px;
        }
    }

    @media (max-width: 900px) {
        .intake-table th,
        .intake-table td {
            font-size: 0.95rem;
            padding: 7px 4px;
        }
    }

    @media (max-width: 420px) {
        .intake-form .form-row label {
            width: 65px;
            font-size: 0.89rem;
        }

        .progress-value {
            font-size: 1.1rem;
        }

        .intake-form {
            padding: 4px 2px 4px 2px;
        }
    }

    /* Responsive improvements for dashboard */
    @media (max-width: 900px) {
        .sidebar {
            width: 100vw;
            position: static;
            display: flex;
            flex-direction: row;
            justify-content: space-around;
            padding: 8px 0;
            border-radius: 0;
            box-shadow: none;
            border: none;
            top: 0;
            left: 0;
            z-index: 100;
        }

        .sidebar a {
            flex: 1;
            margin: 0;
            font-size: 1.05rem;
            padding: 14px 0;
            text-align: center;
            border-radius: 0;
            border: none;
        }

        .sidebar a.active {
            background: #4a7ee3;
            color: #fff;
        }

        main.dashboard {
            margin: 0;
            width: 100vw;
            box-shadow: none;
            border: none;
            padding: 12px 0 0 0;
        }

        .progress-widget,
        .status-section,
        .history-section {
            width: 100vw;
            min-width: 0;
            max-width: 100vw;
            padding: 0 2vw;
            margin: 0 auto 12px auto;
            box-shadow: none;
            border-radius: 0;
            border: none;
        }
    }

    @media (max-width: 480px) {
        .sidebar a {
            font-size: 0.95rem;
            padding: 10px 0;
        }

        main.dashboard {
            padding: 4px 0 0 0;
        }
    }
</style>

<style>
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
        background: #2d2d2d !important;
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

    [data-theme="dark"] .progress-caption {
        color: #adb5bd !important;
    }

    /* Intake Form Dark Mode */
    [data-theme="dark"] .intake-form {
        background: #2d2d2d !important;
        color: #ffffff !important;
    }

    [data-theme="dark"] .intake-form h3 {
        color: #ffffff !important;
    }

    [data-theme="dark"] .intake-form label {
        color: #ffffff !important;
    }

    [data-theme="dark"] .intake-form input[type="text"],
    [data-theme="dark"] .intake-form input[type="number"] {
        background: #343a40 !important;
        color: #ffffff !important;
        border-color: #495057 !important;
    }

    [data-theme="dark"] .intake-form input[type="text"]:focus,
    [data-theme="dark"] .intake-form input[type="number"]:focus {
        border-color: #4a7ee3 !important;
        outline: none !important;
    }

    /* Intake Table Dark Mode */
    [data-theme="dark"] .intake-table {
        background: #2d2d2d !important;
        color: #ffffff !important;
    }

    [data-theme="dark"] .intake-table th {
        background-color: #343a40 !important;
        color: #ffffff !important;
        border-color: #495057 !important;
    }

    [data-theme="dark"] .intake-table td {
        background-color: #2d2d2d !important;
        color: #ffffff !important;
        border-color: #495057 !important;
    }

    [data-theme="dark"] .intake-table tbody tr:nth-child(even) {
        background-color: #343a40 !important;
    }

    [data-theme="dark"] .intake-table tbody tr:hover {
        background-color: #495057 !important;
    }

    [data-theme="dark"] .btn-primary {
        background-color: #4a7ee3 !important;
        color: white !important;
    }

    [data-theme="dark"] .btn-primary:hover {
        background-color: #3b6bd6 !important;
    }

    [data-theme="dark"] button[style*="background: #e55039"] {
        background: #dc3545 !important;
        color: white !important;
    }

    [data-theme="dark"] .error-message {
        background-color: #4a1e24 !important;
        color: #f1aeb5 !important;
        border-color: #5c2b30 !important;
    }

    [data-theme="dark"] .success-message {
        background-color: #1e4d2b !important;
        color: #a3d9a5 !important;
        border-color: #2d5f34 !important;
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

    @media (max-width: 700px) {
        [data-theme="dark"] .sidebar {
            background-color: #343a40 !important;
            border-bottom: 1px solid #495057 !important;
        }

        [data-theme="dark"] .sidebar a {
            color: #adb5bd !important;
        }

        [data-theme="dark"] .sidebar a.active {
            background: #4a7ee3 !important;
            color: #fff !important;
        }
    }

    [data-theme="dark"] body {
        background: #1a1a1a !important;
        color: #ffffff !important;
    }

    [data-theme="dark"] footer {
        background: #343a40 !important;
        color: #adb5bd !important;
        border-top: 1px solid #495057 !important;
    }

    [data-theme="dark"] footer p {
        color: #adb5bd !important;
    }

    [data-theme="dark"] * {
        box-sizing: border-box;
    }

    [data-theme="dark"] html {
        background: #1a1a1a !important;
    }
</style>

<style>
    /* ---------- Mobile-friendly intake table ---------- */
    .table-responsive {
        overflow-x: auto;
    }

    @media (max-width: 600px) {
        .intake-table thead {
            display: none;
        }

        .intake-table table,
        .intake-table tr {
            width: 100%;
        }

        .intake-table tr {
            display: block;
            margin-bottom: 0.75rem;
            border: 1px solid #e1e4e8;
            border-radius: 8px;
            overflow: hidden;
        }

        .intake-table td {
            display: flex;
            justify-content: space-between;
            padding: 10px 12px;
            border: none;
            font-size: 0.95rem;
        }

        .intake-table td::before {
            content: attr(data-label);
            font-weight: 600;
            color: #555;
            margin-right: 1rem;
        }

        .intake-table tr:nth-child(even) td {
            background: #fafafa;
        }
    }
</style>