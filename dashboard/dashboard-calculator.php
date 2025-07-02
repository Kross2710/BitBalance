<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/../include/init.php';
require_once __DIR__ . '/handlers/dashboard_data.php';
require_once __DIR__ . '/../include/handlers/log_attempt.php';

if ($isLoggedIn) {
    // Log the user activity
    log_attempt($pdo, $user['user_id'], 'view', 'User ' . $user['user_id'] . ' clicked on dashboard calculator', 'dashboard', null);
}

$activePage = 'calculator';
$activeHeader = 'dashboard';

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
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="https://kit.fontawesome.com/b94f65ead2.js" crossorigin="anonymous"></script>
</head>

<body>
    <?php include PROJECT_ROOT . 'views/header.php'; ?>
    <?php include PROJECT_ROOT . 'dashboard/views/sidebar.php'; ?>
    <?php if ($isLoggedIn): ?>
        <?php include PROJECT_ROOT . 'dashboard/views/right-sidebar.php'; ?>
    <?php endif; ?>

    <main class="dashboard">
        <section class="calculator-widget">
            <form action="handlers/process_calculator.php" method="POST">
                <h3>Calorie Calculator</h3>
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
                <div class="form-row">
                    <label for="age">Age:</label>
                    <input type="number" id="age" name="age" required value="<?php echo htmlspecialchars($userAge); ?>">
                </div>
                <div class="form-row">
                    <label for="gender">Gender:</label>
                    <select id="gender" name="gender" required>
                        <option value="">Select...</option>
                        <option value="male" <?php if ($userGender === 'male') echo 'selected'; ?>>Male</option>
                        <option value="female" <?php if ($userGender === 'female') echo 'selected'; ?>>Female</option>
                    </select>
                </div>
                <div class="form-row">
                    <label for="weight">Weight (kg):</label>
                    <input type="number" id="weight" name="weight" required value="<?php echo (int) $userWeight; ?>">
                </div>
                <div class="form-row">
                    <label for="height">Height (cm):</label>
                    <input type="number" id="height" name="height" required value="<?php echo (int) $userHeight; ?>">
                </div>
                <div class="form-row">
                    <label for="activity-level">Activity Level:</label>
                    <select id="activity-level" name="activity_level" required>
                        <option value="">Select...</option>
                        <option value="sedentary">Sedentary</option>
                        <option value="lightly_active">Lightly Active</option>
                        <option value="moderately_active">Moderately Active</option>
                        <option value="very_active">Very Active</option>
                        <option value="extra_active">Extra Active</option>
                    </select>
                </div>
                <div id="activity-info" style="margin-top: 10px; margin-bottom: 10px; display: none; color: #555;">
                </div>
                <button type="submit" class="btn-primary">Calculate</button>
                <script>
                    const descriptions = {
                        sedentary: "Little to no exercise, desk job, very minimal movement.",
                        lightly_active: "Light exercise 1–3 days/week, mostly sitting.",
                        moderately_active: "Moderate exercise 3–5 days/week.",
                        very_active: "Hard exercise 6–7 days/week, physically demanding job.",
                        extra_active: "Very hard exercise, physical job, or twice daily training."
                    };

                    const select = document.getElementById('activity-level');
                    const info = document.getElementById('activity-info');
                    let timeout;

                    select.addEventListener('change', () => {
                        clearTimeout(timeout); // Reset timer on re-selection
                        info.style.display = 'none'; // Hide immediately
                        const value = select.value;

                        if (descriptions[value]) {
                            timeout = setTimeout(() => {
                                info.textContent = descriptions[value];
                                info.style.display = 'block';
                            }, 0); // Show description after 1 second
                        }
                    });
                </script>
            </form>
        </section>
        <section class="calculator-output">
            <?php if ($calculatorResult): ?>
                <section class="calculator-output">
                    <h3 class="results-title">Calculator Results</h3>
                    <?php if (!empty($error_message)): ?>
                        <div class="error-message"><?= htmlspecialchars($error_message) ?></div>
                    <?php endif; ?>
                    <div class="results-row">
                        <div class="result-box">
                            <div class="result-label">Maintenance</div>
                            <div class="result-value">
                                <span class="big-num">
                                    <?php
                                    echo number_format($calculatorResult['tdee']);
                                    ?>
                                </span>
                                <span class="unit">kcal</span>
                            </div>
                        </div>
                        <div class="result-box">
                            <div class="result-label">BMI</div>
                            <div class="result-value">
                                <span class="big-num">
                                    <?php
                                    echo number_format($calculatorResult['bmi']);
                                    ?>
                                </span>
                            </div>
                        </div>
                        <div class="result-box">
                            <div class="result-label">Ideal Weight</div>
                            <div class="result-value">
                                <span class="big-num">
                                    <?php
                                    echo number_format($calculatorResult['ideal_weight']['min']) . '-' . number_format($calculatorResult['ideal_weight']['max']);
                                    ?>
                                </span><span class="unit">kg</span>
                            </div>
                        </div>
                    </div>
                    <div class="drop-down-bar">
                        <button class="dropdown-toggle" type="button">
                            <span>Calories</span>
                            <span class="arrow">&#9654;</span>
                        </button>
                        <div class="dropdown-content">
                            <p>
                                <?php
                                echo "Based on your stats, the best estimate for your maintenance calories is " . number_format($calculatorResult['tdee']) . " calories per day based on the Mifflin-St Jeor Formula, which is widely known to be the most accurate.";
                                ?>
                            </p>
                            <table class="calorie-table">
                                <thead>
                                    <tr>
                                        <th>Activity Level</th>
                                        <th>Calories</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr>
                                        <td data-label="Activity Level" <?php if ($selectedActivity == 'sedentary')
                                            echo ' class="highlighted-activity"'; ?>>Sedentary</td>
                                        <td data-label="Calories">
                                            <?php
                                            echo number_format($calculatorResult['tdee_all']['sedentary']);
                                            ?>
                                        </td>
                                    </tr>
                                    <tr>
                                        <td data-label="Activity Level" <?php if ($selectedActivity == 'lightly_active')
                                            echo ' class="highlighted-activity"'; ?>>Lightly Active</td>
                                        <td data-label="Calories">
                                            <?php
                                            echo number_format($calculatorResult['tdee_all']['lightly_active']);
                                            ?>
                                        </td>
                                    </tr>
                                    <tr>
                                        <td data-label="Activity Level" <?php if ($selectedActivity == 'moderately_active')
                                            echo ' class="highlighted-activity"'; ?>>Moderately Active</td>
                                        <td data-label="Calories">
                                            <?php
                                            echo number_format($calculatorResult['tdee_all']['moderately_active']);
                                            ?>
                                        </td>
                                    </tr>
                                    <tr>
                                        <td data-label="Activity Level" <?php if ($selectedActivity == 'very_active')
                                            echo ' class="highlighted-activity"'; ?>>Very Active</td>
                                        <td data-label="Calories">
                                            <?php
                                            echo number_format($calculatorResult['tdee_all']['very_active']);
                                            ?>
                                        </td>
                                    </tr>
                                    <tr>
                                        <td data-label="Activity Level" <?php if ($selectedActivity == 'extra_active')
                                            echo ' class="highlighted-activity"'; ?>>Extra Active</td>
                                        <td data-label="Calories">
                                            <?php
                                            echo number_format($calculatorResult['tdee_all']['extra_active']);
                                            ?>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                    <div class="drop-down-bar">
                        <button class="dropdown-toggle" type="button">
                            <span>BMI</span>
                            <span class="arrow">&#9654;</span>
                        </button>
                        <div class="dropdown-content">
                            <p>
                                <?php
                                echo "Your BMI is " . number_format($calculatorResult['bmi'], 1) . ", which is ";
                                if ($calculatorResult['bmi'] < 18.5) {
                                    echo "underweight.";
                                } elseif ($calculatorResult['bmi'] >= 18.5 && $calculatorResult['bmi'] < 25) {
                                    echo "within the normal range (18.5 - 24.9). This means you have a healthy weight for your height.";
                                } elseif ($calculatorResult['bmi'] >= 25 && $calculatorResult['bmi'] < 30) {
                                    echo "overweight.";
                                } else {
                                    echo "obese.";
                                }
                                ?>
                            </p>
                            <table class="bmi-table">
                                <thead>
                                    <tr>
                                        <th>Category</th>
                                        <th>BMI Range</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr>
                                        <td data-label="Category" <?php
                                        if ($calculatorResult['bmi'] < 18.5) {
                                            echo ' class="highlighted-activity"';
                                        }
                                        ?>>Underweight</td>
                                        <td data-label="BMI Range">Less than 18.5</td>
                                    </tr>
                                    <tr>
                                        <td data-label="Category" <?php
                                        if ($calculatorResult['bmi'] >= 18.5 && $calculatorResult['bmi'] < 25) {
                                            echo ' class="highlighted-activity"';
                                        }
                                        ?>>Normal weight</td>
                                        <td data-label="BMI Range">18.5 - 24.9</td>
                                    </tr>
                                    <tr>
                                        <td data-label="Category" <?php
                                        if ($calculatorResult['bmi'] >= 25 && $calculatorResult['bmi'] < 30) {
                                            echo ' class="highlighted-activity"';
                                        }
                                        ?>>Overweight</td>
                                        <td data-label="BMI Range">25 - 29.9</td>
                                    </tr>
                                    <tr>
                                        <td data-label="Category" <?php
                                        if ($calculatorResult['bmi'] >= 30) {
                                            echo ' class="highlighted-activity"';
                                        }
                                        ?>>Obesity</td>
                                        <td data-label="BMI Range">30 or greater</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                    <div class="drop-down-bar">
                        <button class="dropdown-toggle" type="button">
                            <span>Ideal Weight</span>
                            <span class="arrow">&#9654;</span>
                        </button>
                        <div class="dropdown-content">
                            <p>
                                Your ideal weight range is between 70-75 kg based on your height and age.
                                The table below shows the ideal weight ranges for different heights.
                            </p>
                            <table class="ideal-weight-table">
                                <thead>
                                    <tr>
                                        <th>Height (cm)</th>
                                        <th>Ideal Weight Range (kg)</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr>
                                        <td data-label="Height (cm)" <?php
                                        if ($calculatorResult['height'] >= 150 && $calculatorResult['height'] < 160) {
                                            echo ' class="highlighted-activity"';
                                        }
                                        ?>>150-160</td>
                                        <td data-label="Ideal Weight Range (kg)">50-60</td>
                                    </tr>
                                    <tr>
                                        <td data-label="Height (cm)" <?php
                                        if ($calculatorResult['height'] >= 160 && $calculatorResult['height'] < 170) {
                                            echo ' class="highlighted-activity"';
                                        }
                                        ?>>160-170</td>
                                        <td data-label="Ideal Weight Range (kg)">60-70</td>
                                    </tr>
                                    <tr>
                                        <td data-label="Height (cm)" <?php
                                        if ($calculatorResult['height'] >= 170 && $calculatorResult['height'] < 180) {
                                            echo ' class="highlighted-activity"';
                                        }
                                        ?>>170-180</td>
                                        <td data-label="Ideal Weight Range (kg)">70-80</td>
                                    </tr>
                                    <tr>
                                        <td data-label="Height (cm)" <?php
                                        if ($calculatorResult['height'] >= 180 && $calculatorResult['height'] < 190) {
                                            echo ' class="highlighted-activity"';
                                        }
                                        ?>>180-190</td>
                                        <td data-label="Ideal Weight Range (kg)">80-90</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </section>
            <?php endif; ?>
        </section>
    </main>
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
        display: flex;
    }

    .calculator-widget h3 {
        margin-bottom: 20px;
    }

    .calculator-widget {
        width: 100%;
        max-width: 400px;
        border-radius: 10px;
        padding: 20px;
    }

    .calculator-widget .form-row {
        display: flex;
        align-items: center;
        margin-bottom: 16px;
    }

    .calculator-widget .form-row label {
        width: 120px;
        margin: 0;
        font-weight: 600;
        color: #333;
    }

    .calculator-widget .form-row input,
    .calculator-widget .form-row select {
        flex: 1;
        padding: 8px 12px;
        border: 1px solid #ccc;
        border-radius: 4px;
        font-size: 14px;
    }

    .btn-primary {
        margin-top: 5px;
        background-color: #4a7ee3;
        color: white;
        padding: 10px 15px;
        border: none;
        border-radius: 5px;
        cursor: pointer;
        font-size: 13px;
        transition: background-color 0.3s ease;
    }

    .btn-primary:hover {
        background-color: #3a5bb3;
    }

    .calculator-output {
        max-width: 600px;
        margin-left: 60px;
        margin-top: 10px;
        flex: 1 1 0;
    }

    .results-title {
        margin-bottom: 20px;
    }

    .results-row {
        display: flex;
        justify-content: center;
        gap: 24px;
        margin-bottom: 20px;
    }

    .result-box {
        border: 2px solid #222;
        border-radius: 10px;
        width: 220px;
        padding: 15px 18px 15px 18px;
        background: #fff;
        display: flex;
        flex-direction: column;
        align-items: flex-start;
        box-shadow: 0 2px 8px rgba(0, 0, 0, 0.04);
    }

    .result-label {
        font-size: 1.05rem;
        margin-bottom: 12px;
        color: #222;
        font-weight: 500;
    }

    .result-value {
        display: flex;
        align-items: flex-end;
        gap: 5px;
    }

    .big-num {
        font-size: 1.8rem;
        font-weight: 700;
        color: #222;
        line-height: 1;
    }

    .unit {
        font-size: 1rem;
        color: #444;
        margin-left: 2px;
        font-weight: 400;
    }

    .drop-down-bar {
        background-color: #fafbfc;
        border: 1px solid #e1e4e8;
        border-radius: 12px;
        box-shadow: 0 2px 8px rgba(0, 0, 0, 0.06);
        padding: 20px 24px 20px 24px;
        margin-bottom: 12px;
        width: 100%;
        max-width: 700px;
        margin-left: auto;
        margin-right: auto;
    }

    .drop-down-bar h4 {
        display: none;
    }

    .drop-down-bar p {
        margin: 0 0 14px;
        color: #4c4c4c;
        font-size: 1.06rem;
        line-height: 1.5;
    }

    .drop-down-bar table {
        width: 100%;
        border-collapse: collapse;
        margin: 15px 0;
        background: #fff;
        table-layout: auto;
        display: table;
        border-radius: 8px;
        border: 1px solid #e1e4e8;
        box-shadow: 0 2px 8px rgba(0, 0, 0, 0.06);
    }

    .drop-down-bar th,
    .drop-down-bar td {
        padding: 10px 12px;
        text-align: left;
    }

    .drop-down-bar th {
        background: #4a7ee3;
        color: #fff;
        font-weight: 600;
        font-size: 1.04rem;
    }

    .drop-down-bar tr:nth-child(even) td {
        background: #f5f7fa;
    }

    .highlighted-activity {
        background-color: #cbe1ff !important;
        font-weight: bold;
        color: #234;
    }

    @media (max-width: 900px) {
        main.dashboard {
            margin-left: 0;
            margin-right: 0;
            width: 100vw;
        }

        main.dashboard {
            flex-direction: column;
            align-items: stretch;
        }

        .calculator-output {
            width: 95%;
            margin: 20px auto;
        }
    }

    @media (max-width: 768px) {
        .drop-down-bar {
            padding: 8px 5px 8px 5px;
            max-width: 100%;
            min-width: 0;
        }
    }

    @media (max-width: 500px) {
        .calculator-widget .form-row label {
            width: 98px;
            font-size: 0.97rem;
        }

        .calculator-widget h3 {
            font-size: 1.2rem;
        }

        .results-title {
            font-size: 1.12rem;
        }

        .result-label {
            font-size: 0.98rem;
        }

        .big-num {
            font-size: 1.5rem;
        }

        .drop-down-bar th,
        .drop-down-bar td {
            font-size: 0.95rem;
            padding: 7px 4px;
        }
    }

    @media (max-width: 420px) {
        .calculator-widget .form-row label {
            width: 80px;
            font-size: 0.89rem;
        }

        .result-label {
            font-size: 0.85rem;
        }

        .drop-down-bar {
            padding: 4px 2px 4px 2px;
        }

        .result-box {
            padding: 6px 4px 5px 4px;
        }
    }

    .calorie-table,
    .bmi-table,
    .ideal-weight-table {
        display: block;
        width: 100%;
        overflow-x: auto;
        -webkit-overflow-scrolling: touch;
    }

    .calorie-table table,
    .bmi-table table,
    .ideal-weight-table table {
        width: 100%;
        min-width: 360px;
    }

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

    .dropdown-toggle {
        background: none;
        border: none;
        width: 100%;
        text-align: left;
        font-size: 1.25rem;
        font-weight: 600;
        color: #234;
        padding: 0;
        display: flex;
        align-items: center;
        justify-content: space-between;
        cursor: pointer;
        outline: none;
        margin-bottom: 10px;
        transition: color 0.2s;
    }

    .dropdown-toggle .arrow {
        font-size: 1.2em;
        transition: transform 0.3s;
    }

    .drop-down-bar.active .dropdown-toggle .arrow {
        transform: rotate(90deg);
    }

    .dropdown-content {
        max-height: 0;
        overflow: hidden;
        opacity: 0;
        transition: max-height 0.3s cubic-bezier(.4, 0, .2, 1), opacity 0.2s;
    }

    .drop-down-bar.active .dropdown-content {
        max-height: 1000px;
        opacity: 1;
        transition: max-height 0.3s cubic-bezier(.4, 0, .2, 1), opacity 0.3s;
    }
</style>

<script>
    document.querySelectorAll('.drop-down-bar .dropdown-toggle').forEach(button => {
        button.addEventListener('click', function () {
            const bar = this.closest('.drop-down-bar');
            bar.classList.toggle('active');
        });
    });
    // Optionally expand the first section by default:
    document.querySelectorAll('.drop-down-bar')[0]?.classList.add('active');
</script>

<style>
    [data-theme="dark"] body {
        background: #1a1a1a !important;
        color: #ffffff !important;
    }

    [data-theme="dark"] html {
        background: #1a1a1a !important;
    }

    [data-theme="dark"] .dashboard-calculator {
        background: #2d2d2d !important;
        border-color: #495057 !important;
        color: #ffffff !important;
    }

    /* Calculator Form Dark Mode */
    [data-theme="dark"] .calculator-widget {
        background: #2d2d2d !important;
        color: #ffffff !important;
    }

    [data-theme="dark"] .calculator-widget h3 {
        color: #ffffff !important;
    }

    [data-theme="dark"] .calculator-widget .form-row label {
        color: #ffffff !important;
    }

    [data-theme="dark"] .calculator-widget .form-row input,
    [data-theme="dark"] .calculator-widget .form-row select {
        background: #343a40 !important;
        color: #ffffff !important;
        border-color: #495057 !important;
    }

    [data-theme="dark"] .calculator-widget .form-row input:focus,
    [data-theme="dark"] .calculator-widget .form-row select:focus {
        border-color: #4a7ee3 !important;
        outline: none !important;
    }

    /* Results Section Dark Mode */
    [data-theme="dark"] .calculator-output {
        background: #2d2d2d !important;
        color: #ffffff !important;
    }

    [data-theme="dark"] .results-title {
        color: #ffffff !important;
    }

    [data-theme="dark"] .result-box {
        background: #343a40 !important;
        border-color: #495057 !important;
        color: #ffffff !important;
    }

    [data-theme="dark"] .result-label {
        color: #ffffff !important;
    }

    [data-theme="dark"] .big-num {
        color: #ffffff !important;
    }

    [data-theme="dark"] .unit {
        color: #adb5bd !important;
    }

    /* Dropdown Sections Dark Mode */
    [data-theme="dark"] .drop-down-bar {
        background-color: #343a40 !important;
        border-color: #495057 !important;
        color: #ffffff !important;
    }

    [data-theme="dark"] .dropdown-toggle {
        color: #ffffff !important;
    }

    [data-theme="dark"] .drop-down-bar p {
        color: #adb5bd !important;
    }

    [data-theme="dark"] .drop-down-bar table {
        background: #2d2d2d !important;
    }

    [data-theme="dark"] .drop-down-bar th {
        background: #4a7ee3 !important;
        color: #ffffff !important;
    }

    [data-theme="dark"] .drop-down-bar td {
        background: #2d2d2d !important;
        color: #ffffff !important;
        border-color: #495057 !important;
    }

    [data-theme="dark"] .drop-down-bar tr:nth-child(even) td {
        background: #343a40 !important;
    }

    [data-theme="dark"] .highlighted-activity {
        background-color: #1e4d2b !important;
        color: #a3d9a5 !important;
    }

    [data-theme="dark"] .btn-primary {
        background-color: #4a7ee3 !important;
        color: white !important;
    }

    [data-theme="dark"] .btn-primary:hover {
        background-color: #3b6bd6 !important;
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

    /* Footer Dark Mode */
    [data-theme="dark"] footer {
        background: #343a40 !important;
        color: #adb5bd !important;
        border-top: 1px solid #495057 !important;
    }

    [data-theme="dark"] footer p {
        color: #adb5bd !important;
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
</style>

<style>
    /* ---------- Mobile-friendly result tables ---------- */
    @media (max-width: 600px) {

        /* hide header row */
        .calorie-table thead,
        .bmi-table thead,
        .ideal-weight-table thead {
            display: none;
        }

        /* each row behaves like a card */
        .calorie-table tr,
        .bmi-table tr,
        .ideal-weight-table tr {
            display: block;
            margin-bottom: 0.75rem;
            border: 1px solid #e1e4e8;
            border-radius: 8px;
            overflow: hidden;
        }

        /* cell with label/value */
        .calorie-table td,
        .bmi-table td,
        .ideal-weight-table td {
            display: flex;
            justify-content: space-between;
            padding: 8px 12px;
            border: none;
            font-size: 0.95rem;
        }

        /* show header text via data-label */
        .calorie-table td::before,
        .bmi-table td::before,
        .ideal-weight-table td::before {
            content: attr(data-label);
            font-weight: 600;
            color: #555;
            margin-right: 1rem;
        }

        /* zebra for clarity */
        .calorie-table tr:nth-child(even) td,
        .bmi-table tr:nth-child(even) td,
        .ideal-weight-table tr:nth-child(even) td {
            background: #f8f9fa;
        }
    }

    /* --- Extra mobile tweaks (≤600 px) -------------------- */
    @media (max-width: 600px) {

        /* Make the form take full width */
        .calculator-widget {
            width: 95%;
            max-width: none;
            padding: 12px 16px;
            margin: 0 auto;
        }

        /* Labels above inputs for narrow screens */
        .calculator-widget .form-row {
            flex-direction: column;
            align-items: stretch;
        }

        .calculator-widget .form-row label {
            width: 100%;
            margin-bottom: 6px;
            font-size: 0.92rem;
        }

        /* Results stack vertically */
        .results-row {
            flex-direction: column;
            gap: 12px;
            align-items: stretch;
        }

        .result-box {
            width: 100%;
            max-width: 100%;
            padding: 12px 14px;
        }

        /* Drop‑down sections fit edge‑to‑edge */
        .drop-down-bar {
            width: 100%;
            margin-left: 0;
            margin-right: 0;
        }

        /* Smaller toggle text */
        .dropdown-toggle {
            font-size: 1.05rem;
        }
    }

    /* Ultra‑small phones (≤380 px) */
    @media (max-width: 380px) {
        .calculator-widget .form-row label { font-size: 0.82rem; }
        .big-num { font-size: 1.3rem; }
    }
</style>