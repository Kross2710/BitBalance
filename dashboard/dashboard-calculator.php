<?php
require_once __DIR__ . '/../include/init.php';
require_once __DIR__ . '/handlers/dashboard_data.php';
require_once __DIR__ . '/../include/handlers/log_attempt.php';

if ($isLoggedIn) {
    log_attempt($pdo, $user['user_id'], 'view', 'User ' . $user['user_id'] . ' clicked on dashboard calculator', 'dashboard', null);
}

$activePage = 'calculator';
$activeHeader = 'dashboard';
$displayUser = $isLoggedIn ? $user['user_name'] : "Guest";

$error_message = isset($_GET['error']) ? htmlspecialchars($_GET['error']) : '';
$success_message = isset($_GET['success']) ? htmlspecialchars($_GET['success']) : '';
?>

<!DOCTYPE html>
<html lang="en" data-theme="<?php echo isset($_SESSION['user']) ? ($_SESSION['user']['theme_preference'] ?? 'light') : 'light'; ?>">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Calculator | BitBalance</title>
    <?php include PROJECT_ROOT . 'views/head_css.php'; ?>
    <link rel="stylesheet" href="<?= BASE_URL ?>css/dashboard.css">
    
    <script src="https://kit.fontawesome.com/b94f65ead2.js" crossorigin="anonymous"></script>
</head>

<body>
    <?php include PROJECT_ROOT . 'views/header.php'; ?>
    <?php include PROJECT_ROOT . 'dashboard/views/sidebar.php'; ?>

    <?php if ($isLoggedIn): ?>
        <?php include PROJECT_ROOT . 'dashboard/views/right-sidebar.php'; ?>
        
        <main class="dashboard-content">
            <div class="calculator-container">
                
                <section class="calc-form-card">
                    <div class="card-header">
                        <h3><i class="fas fa-calculator"></i> Calorie Calculator</h3>
                        <p class="subtitle">Calculate your TDEE & BMI instantly.</p>
                    </div>

                    <?php if (!empty($error_message)): ?>
                        <div class="alert error"><i class="fas fa-exclamation-triangle"></i> <?= $error_message ?></div>
                    <?php endif; ?>

                    <form action="handlers/process_calculator.php" method="POST" id="calcForm">
                        <div class="form-grid">
                            <div class="form-group">
                                <label for="age">Age</label>
                                <div class="input-icon-wrapper">
                                    <i class="fas fa-birthday-cake input-icon"></i>
                                    <input type="number" id="age" name="age" required value="<?= htmlspecialchars($userAge); ?>" placeholder="Years">
                                </div>
                            </div>
                            
                            <div class="form-group">
                                <label for="gender">Gender</label>
                                <div class="input-icon-wrapper">
                                    <i class="fas fa-venus-mars input-icon"></i>
                                    <select id="gender" name="gender" required>
                                        <option value="">Select...</option>
                                        <option value="male" <?= $userGender === 'male' ? 'selected' : ''; ?>>Male</option>
                                        <option value="female" <?= $userGender === 'female' ? 'selected' : ''; ?>>Female</option>
                                    </select>
                                </div>
                            </div>

                            <div class="form-group">
                                <label for="weight">Weight (kg)</label>
                                <div class="input-icon-wrapper">
                                    <i class="fas fa-weight input-icon"></i>
                                    <input type="number" id="weight" name="weight" required value="<?= (int)$userWeight; ?>" placeholder="kg">
                                </div>
                            </div>

                            <div class="form-group">
                                <label for="height">Height (cm)</label>
                                <div class="input-icon-wrapper">
                                    <i class="fas fa-ruler-vertical input-icon"></i>
                                    <input type="number" id="height" name="height" required value="<?= (int)$userHeight; ?>" placeholder="cm">
                                </div>
                            </div>
                        </div>

                        <div class="form-group full-width">
                            <label for="activity-level">Activity Level</label>
                            <div class="input-icon-wrapper">
                                <i class="fas fa-running input-icon"></i>
                                <select id="activity-level" name="activity_level" required>
                                    <option value="">Select Activity Level...</option>
                                    <option value="sedentary">Sedentary (Little/no exercise)</option>
                                    <option value="lightly_active">Lightly Active (1–3 days/week)</option>
                                    <option value="moderately_active">Moderately Active (3–5 days/week)</option>
                                    <option value="very_active">Very Active (6–7 days/week)</option>
                                    <option value="extra_active">Extra Active (Physical job/Training)</option>
                                </select>
                            </div>
                            <small id="activity-info" class="hint-text"></small>
                        </div>

                        <button type="submit" class="btn-calculate">
                            <i class="fas fa-bolt"></i> Calculate Stats
                        </button>
                    </form>
                </section>

                <section class="calc-results-container">
                    <?php if ($calculatorResult): ?>
                        <div class="results-header">
                            <h3><i class="fas fa-chart-pie"></i> Your Results</h3>
                        </div>

                        <div class="metrics-row">
                            <div class="metric-card card-blue">
                                <div class="metric-icon"><i class="fas fa-fire"></i></div>
                                <div class="metric-info">
                                    <span class="metric-label">Maintenance</span>
                                    <span class="metric-value"><?= number_format($calculatorResult['tdee']); ?> <small>kcal</small></span>
                                </div>
                            </div>
                            
                            <div class="metric-card card-purple">
                                <div class="metric-icon"><i class="fas fa-weight-hanging"></i></div>
                                <div class="metric-info">
                                    <span class="metric-label">BMI Score</span>
                                    <span class="metric-value"><?= number_format($calculatorResult['bmi'], 1); ?></span>
                                </div>
                            </div>

                            <div class="metric-card card-green">
                                <div class="metric-icon"><i class="fas fa-bullseye"></i></div>
                                <div class="metric-info">
                                    <span class="metric-label">Ideal Weight</span>
                                    <span class="metric-value">
                                        <?= number_format($calculatorResult['ideal_weight']['min']) . '-' . number_format($calculatorResult['ideal_weight']['max']); ?>
                                        <small>kg</small>
                                    </span>
                                </div>
                            </div>
                        </div>

                        <div class="details-section">
                            
                            <div class="accordion-item">
                                <button class="accordion-header">
                                    <span><i class="fas fa-list-alt"></i> Calorie Breakdown</span>
                                    <i class="fas fa-chevron-down arrow"></i>
                                </button>
                                <div class="accordion-content">
                                    <div class="content-inner">
                                        <p class="desc-text">Based on the <strong>Mifflin-St Jeor</strong> formula, here is your estimated daily calorie needs:</p>
                                        <table class="modern-table small-table">
                                            <thead><tr><th>Activity Level</th><th>Calories</th></tr></thead>
                                            <tbody>
                                                <?php foreach($calculatorResult['tdee_all'] as $level => $cal): ?>
                                                    <tr class="<?= $selectedActivity == $level ? 'highlight-row' : '' ?>">
                                                        <td><?= ucwords(str_replace('_', ' ', $level)) ?></td>
                                                        <td><strong><?= number_format($cal) ?></strong></td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>

                            <div class="accordion-item">
                                <button class="accordion-header">
                                    <span><i class="fas fa-info-circle"></i> BMI Analysis</span>
                                    <i class="fas fa-chevron-down arrow"></i>
                                </button>
                                <div class="accordion-content">
                                    <div class="content-inner">
                                        <p class="desc-text">Your BMI is <strong><?= number_format($calculatorResult['bmi'], 1) ?></strong>.</p>
                                        <table class="modern-table small-table">
                                            <thead><tr><th>Category</th><th>Range</th></tr></thead>
                                            <tbody>
                                                <tr class="<?= $calculatorResult['bmi'] < 18.5 ? 'highlight-row' : '' ?>"><td>Underweight</td><td>< 18.5</td></tr>
                                                <tr class="<?= ($calculatorResult['bmi'] >= 18.5 && $calculatorResult['bmi'] < 25) ? 'highlight-row' : '' ?>"><td>Normal</td><td>18.5 - 24.9</td></tr>
                                                <tr class="<?= ($calculatorResult['bmi'] >= 25 && $calculatorResult['bmi'] < 30) ? 'highlight-row' : '' ?>"><td>Overweight</td><td>25 - 29.9</td></tr>
                                                <tr class="<?= $calculatorResult['bmi'] >= 30 ? 'highlight-row' : '' ?>"><td>Obese</td><td>30+</td></tr>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>

                        </div> <?php else: ?>
                        <div class="empty-calc-state">
                            <i class="fas fa-calculator"></i>
                            <h4>Ready to Calculate?</h4>
                            <p>Fill in your details to get your personalized nutrition stats.</p>
                        </div>
                    <?php endif; ?>
                </section>
            </div>
        </main>
    <?php else: ?>
        <main class="dashboard-content" style="text-align:center; margin-top:100px;">
            <h2>Please log in to access the Calculator.</h2>
            <a href="<?= BASE_URL ?>login.php" class="btn-calculate" style="display:inline-block; width:auto; margin-top:20px;">Sign In</a>
        </main>
    <?php endif; ?>

    <?php include PROJECT_ROOT . 'views/footer.php'; ?>

    <script>
        document.addEventListener('DOMContentLoaded', () => {
            // 1. Activity Level Hint
            const descriptions = {
                sedentary: "Desk job, little to no exercise.",
                lightly_active: "Light exercise 1–3 days/week.",
                moderately_active: "Moderate exercise 3–5 days/week.",
                very_active: "Hard exercise 6–7 days/week.",
                extra_active: "Very hard exercise & physical job."
            };
            const select = document.getElementById('activity-level');
            const info = document.getElementById('activity-info');

            if(select) {
                select.addEventListener('change', () => {
                    const val = select.value;
                    if (descriptions[val]) {
                        info.textContent = descriptions[val];
                        info.style.opacity = '1';
                    } else {
                        info.textContent = '';
                    }
                });
            }

            // 2. Accordion Logic
            const accordions = document.querySelectorAll('.accordion-header');
            accordions.forEach(acc => {
                acc.addEventListener('click', function() {
                    this.classList.toggle('active');
                    const content = this.nextElementSibling;
                    const arrow = this.querySelector('.arrow');
                    
                    if (content.style.maxHeight) {
                        content.style.maxHeight = null;
                        arrow.style.transform = 'rotate(0deg)';
                    } else {
                        content.style.maxHeight = content.scrollHeight + "px";
                        arrow.style.transform = 'rotate(180deg)';
                    }
                });
            });
            
            // Open first accordion by default if exists
            if(accordions.length > 0) {
                accordions[0].click();
            }
        });
    </script>
</body>
</html>

<style>
    /* Page-specific layout — colors/typography từ tokens chung */

    .dashboard-content {
        margin-top: 20px;
        margin-left: 240px;
        margin-right: 240px;
        padding: 0 20px;
        min-height: 80vh;
    }

    .calculator-container {
        display: grid;
        grid-template-columns: 1fr 1.5fr; /* Cột kết quả rộng hơn cột form */
        gap: 25px;
        max-width: 1100px;
        margin: 0 auto;
        align-items: start;
    }

    /* 2. FORM CARD */
    .calc-form-card {
        background: var(--card-bg);
        border-radius: var(--border-radius);
        padding: 25px;
        box-shadow: var(--shadow-soft);
    }

    .card-header h3 { margin: 0; font-size: 1.3rem; display: flex; align-items: center; gap: 10px; }
    .subtitle { color: var(--text-secondary); margin: 5px 0 20px 0; font-size: 0.9rem; }

    .form-grid {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 15px;
    }

    .form-group { margin-bottom: 15px; }
    .form-group.full-width { grid-column: span 2; }
    
    .form-group label { display: block; margin-bottom: 6px; font-weight: 600; font-size: 0.9rem; color: var(--text-secondary); }
    
    .input-icon-wrapper { position: relative; }
    .input-icon { position: absolute; left: 12px; top: 50%; transform: translateY(-50%); color: #adb5bd; }

    .calc-form-card input, .calc-form-card select {
        width: 100%;
        padding: 10px 12px 10px 35px;
        border: 1px solid #e1e4e8;
        border-radius: 8px;
        background: var(--bg-body);
        color: var(--text-primary);
        font-size: 0.95rem;
        transition: all 0.2s;
    }

    .calc-form-card input:focus, .calc-form-card select:focus {
        border-color: var(--primary-color);
        outline: none;
        box-shadow: 0 0 0 3px rgba(74, 126, 227, 0.1);
        background: var(--card-bg);
    }

    .hint-text { font-size: 0.8rem; color: var(--primary-color); display: block; margin-top: 5px; min-height: 1rem; }

    .btn-calculate {
        width: 100%;
        padding: 12px;
        background: var(--primary-color);
        color: white;
        border: none;
        border-radius: 50px; /* Pill shape */
        font-size: 1rem;
        font-weight: 600;
        cursor: pointer;
        display: flex;
        justify-content: center;
        align-items: center;
        gap: 8px;
        transition: transform 0.2s, box-shadow 0.2s;
        margin-top: 10px;
    }
    .btn-calculate:hover { transform: translateY(-2px); box-shadow: 0 4px 10px rgba(74, 126, 227, 0.3); }

    /* 3. RESULTS SECTION */
    .calc-results-container {
        display: flex;
        flex-direction: column;
        gap: 20px;
    }

    .metrics-row {
        display: grid;
        grid-template-columns: repeat(3, 1fr);
        gap: 15px;
    }

    .metric-card {
        background: var(--card-bg);
        border-radius: var(--border-radius);
        padding: 20px;
        box-shadow: var(--shadow-soft);
        display: flex;
        flex-direction: column;
        align-items: center;
        text-align: center;
        border-bottom: 4px solid transparent; /* Colored bottom border */
    }

    .card-blue { border-color: #4a7ee3; }
    .card-purple { border-color: #9b59b6; }
    .card-green { border-color: #2ecc71; }

    .metric-icon { font-size: 1.5rem; margin-bottom: 10px; opacity: 0.8; }
    .card-blue .metric-icon { color: #4a7ee3; }
    .card-purple .metric-icon { color: #9b59b6; }
    .card-green .metric-icon { color: #2ecc71; }

    .metric-label { font-size: 0.8rem; text-transform: uppercase; color: var(--text-secondary); font-weight: 700; }
    .metric-value { font-size: 1.4rem; font-weight: 800; color: var(--text-primary); margin-top: 5px; }
    .metric-value small { font-size: 0.9rem; font-weight: 500; color: var(--text-secondary); }

    /* 4. ACCORDION DETAILS */
    .accordion-item {
        background: var(--card-bg);
        border-radius: 12px;
        margin-bottom: 12px;
        box-shadow: var(--shadow-soft);
        overflow: hidden;
    }

    .accordion-header {
        width: 100%;
        background: none;
        border: none;
        padding: 15px 20px;
        display: flex;
        justify-content: space-between;
        align-items: center;
        font-size: 1rem;
        font-weight: 600;
        color: var(--text-primary);
        cursor: pointer;
        transition: background 0.2s;
    }
    .accordion-header:hover { background: rgba(0,0,0,0.02); }
    .accordion-header span i { margin-right: 8px; color: var(--primary-color); }
    .arrow { transition: transform 0.3s; font-size: 0.9rem; color: var(--text-secondary); }

    .accordion-content { max-height: 0; overflow: hidden; transition: max-height 0.3s ease-out; }
    .content-inner { padding: 0 20px 20px 20px; }

    .desc-text { font-size: 0.9rem; color: var(--text-secondary); margin-bottom: 10px; line-height: 1.5; }

    /* Tables inside accordion */
    .modern-table.small-table { width: 100%; border-collapse: collapse; font-size: 0.9rem; }
    .small-table th { text-align: left; padding: 8px; border-bottom: 2px solid #f0f0f0; color: var(--text-secondary); font-size: 0.8rem; }
    .small-table td { padding: 10px 8px; border-bottom: 1px solid #f0f0f0; color: var(--text-primary); }
    
    .highlight-row { background-color: rgba(74, 126, 227, 0.1); }
    .highlight-row td { color: var(--primary-color); font-weight: 700; }

    /* 5. EMPTY STATE */
    .empty-calc-state {
        background: var(--card-bg);
        border-radius: var(--border-radius);
        padding: 50px;
        text-align: center;
        box-shadow: var(--shadow-soft);
        color: var(--text-secondary);
    }
    .empty-calc-state i { font-size: 3rem; margin-bottom: 15px; opacity: 0.3; }
    .empty-calc-state h4 { margin: 0 0 5px 0; color: var(--text-primary); }

    /* 6. RESPONSIVE */
    @media (max-width: 1200px) {
        .dashboard-content { margin-left: 0; margin-right: 0; }
    }

    @media (max-width: 900px) {
        .calculator-container { grid-template-columns: 1fr; } /* Stack vertically */
        .metrics-row { grid-template-columns: 1fr 1fr 1fr; } /* Keep 3 items if space allows, or use 1fr */
    }

    @media (max-width: 600px) {
        .metrics-row { grid-template-columns: 1fr; } /* 1 column on mobile */
        .form-grid { grid-template-columns: 1fr; }
        .form-group.full-width { grid-column: span 1; }
    }
</style>