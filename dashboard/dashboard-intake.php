<?php
require_once __DIR__ . '/../include/init.php';
require_once __DIR__ . '/handlers/dashboard_data.php';
require_once __DIR__ . '/../include/handlers/log_attempt.php';
require_once __DIR__ . '/../include/csrf.php';

if ($isLoggedIn) {
    log_attempt($pdo, $user['user_id'], 'view', 'User ' . $user['user_id'] . ' clicked on dashboard food', 'dashboard', null);

    // Fetch the PT feedback for the day currently being viewed. $selectedDate is
    // set + clamped (never the future) by dashboard_data.php, required above — so
    // browsing a past day via ?date= surfaces that day's advice, not just today's.
    $ptFeedback = null;
    try {
        $stmt = $pdo->prepare("
            SELECT pf.content, pf.date_for, u.first_name, u.last_name, u.profile_image
            FROM pt_feedback pf
            JOIN user u ON pf.trainer_id = u.user_id
            WHERE pf.client_id = ? AND pf.date_for = ?
            LIMIT 1
        ");
        $stmt->execute([$user['user_id'], $selectedDate]);
        $ptFeedback = $stmt->fetch();

        // Viewing the day clears its "new advice" notification (Task #4).
        if ($ptFeedback) {
            $pdo->prepare("
                UPDATE pt_feedback SET seen_at = NOW()
                WHERE client_id = ? AND date_for = ? AND seen_at IS NULL
            ")->execute([$user['user_id'], $selectedDate]);
        }
    } catch (PDOException $e) {
        // Table/columns may not exist yet
    }

    // The client's linked trainer — drives the two-way chat (Task #3). A client
    // may technically link multiple trainers; we surface the most recent one.
    $myTrainer = null;
    try {
        $stmt = $pdo->prepare("
            SELECT u.user_id, u.first_name, u.last_name, u.profile_image
            FROM trainer_client tc
            JOIN user u ON tc.trainer_id = u.user_id
            WHERE tc.client_id = ? AND tc.status = 'accepted'
            ORDER BY tc.responded_at DESC
            LIMIT 1
        ");
        $stmt->execute([$user['user_id']]);
        $myTrainer = $stmt->fetch();
    } catch (PDOException $e) {
        // Table may not exist yet
    }
}

$activePage = 'intake';
$activeHeader = 'dashboard';
$bodyClass = 'page-intake';
$displayUser = $isLoggedIn ? $user['user_name'] : "Guest";

// Backdating context (Layers 1 & 3). $selectedDate is set + clamped (never the
// future) by dashboard_data.php. When it's a past day, the page shows a pinned
// banner and the add form/toast make the target date unmistakable.
$isPastDate = $isLoggedIn && ($selectedDate < date('Y-m-d'));
$shortDateLabel = date('j/n', strtotime($selectedDate)); // e.g. 28/5

// Task 1: a per-meal "+" on Overview links here with ?meal=… to pre-select the
// meal and drop the user straight into the food name field.
$prefillMeal = (isset($_GET['meal']) && in_array($_GET['meal'], ['breakfast', 'lunch', 'dinner', 'snack'], true))
    ? $_GET['meal'] : '';

if (!$isLoggedIn) {
    // Guest demo — populate the Food Log with sample data (mirrors the
    // Overview demo) so new visitors get the "this is what a day looks like"
    // hook instead of a login wall. The auth-only actions (logging, scanner,
    // edit/delete) stay gated below and nudge sign-up instead.
    $userGoal = 2200;
    $totalCalories = 1450;
    $progressPercentage = round(($totalCalories / $userGoal) * 100);
    $macroTotals = ['protein' => 85, 'carbs' => 175, 'fat' => 46];
    $macroGoals  = getMacroGoalsFromCalorieGoal($userGoal);
    $intakeLog = [
        ['intakeLog_id' => 0, 'food_item' => 'Pho Bo',               'calories' => 450, 'protein' => 30, 'carbs' => 55, 'fat' => 10, 'meal_category' => 'breakfast', 'date_intake' => date('Y-m-d 08:30:00')],
        ['intakeLog_id' => 0, 'food_item' => 'Iced Coffee',          'calories' => 120, 'protein' => 2,  'carbs' => 18, 'fat' => 4,  'meal_category' => 'snack',     'date_intake' => date('Y-m-d 10:00:00')],
        ['intakeLog_id' => 0, 'food_item' => 'Grilled Chicken Salad', 'calories' => 550, 'protein' => 40, 'carbs' => 35, 'fat' => 20, 'meal_category' => 'lunch',     'date_intake' => date('Y-m-d 12:30:00')],
        ['intakeLog_id' => 0, 'food_item' => 'Apple',                'calories' => 80,  'protein' => 0,  'carbs' => 21, 'fat' => 0,  'meal_category' => 'snack',     'date_intake' => date('Y-m-d 15:00:00')],
        ['intakeLog_id' => 0, 'food_item' => 'Salmon & Rice',         'calories' => 250, 'protein' => 13, 'carbs' => 46, 'fat' => 12, 'meal_category' => 'dinner',    'date_intake' => date('Y-m-d 19:00:00')],
    ];
    // Right-sidebar body metrics
    $userAge = 25;
    $userWeight = 70;
    $userHeight = 175;
}

// Logic trạng thái Goal
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

// Xử lý thông báo lỗi/thành công từ URL
$error_message = isset($_GET['error']) ? htmlspecialchars($_GET['error']) : '';
$success_message = isset($_GET['success']) ? htmlspecialchars($_GET['success']) : '';
?>

<!DOCTYPE html>
<html lang="<?= html_lang_attr() ?>"
    data-theme="<?php echo isset($_SESSION['user']) ? ($_SESSION['user']['theme_preference'] ?? 'system') : 'system'; ?>">

<head>
    <meta charset="UTF-8">
    <meta name="viewport"
        content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no, interactive-widget=resizes-content">
    <title><?= t('intake.title_alt') ?></title>
    <?php
    // Intake IS the logging UI itself, so no quick-log FAB on this page.
    $pageComponents = ['sidebar'];
    $pageCss = ['css/dashboard.css', 'css/components/intake-list.css', 'css/pages/dashboard-intake.css', 'css/components/pt-chat.css'];
    include PROJECT_ROOT . 'views/head_css.php';
    ?>

    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="https://kit.fontawesome.com/b94f65ead2.js" crossorigin="anonymous"></script>
    <script src="https://unpkg.com/html5-qrcode" type="text/javascript"></script>
</head>

<body class="<?= htmlspecialchars($bodyClass ?? '', ENT_QUOTES) ?>">
    <?php
    include PROJECT_ROOT . 'views/header.php';
    include PROJECT_ROOT . 'dashboard/views/sidebar.php';
    ?>

        <?php include PROJECT_ROOT . 'dashboard/views/right-sidebar.php'; ?>

        <main class="dashboard-content">
            <div class="intake-container">

                <?php if ($isPastDate): ?>
                    <?php include PROJECT_ROOT . 'dashboard/views/_past-date-banner.php'; ?>
                <?php endif; ?>

                <?php if (!empty($ptFeedback)):
                    $fbForToday = ($ptFeedback['date_for'] === date('Y-m-d'));
                    $fbDateLabel = date('j/n', strtotime($ptFeedback['date_for']));
                ?>
                    <section class="dashboard-card pt-feedback-card" style="display: flex; align-items: center; gap: 16px; border: 2px solid var(--color-secondary); background: var(--color-surface); padding: 20px; border-radius: var(--radius-lg); box-shadow: 0 8px 0 var(--color-border-subtle), var(--shadow-sm); margin-bottom: 24px;">
                        <div style="width: 48px; height: 48px; border-radius: 50%; overflow: hidden; background: var(--color-surface-alt); border: 2px solid var(--color-border); display: flex; align-items: center; justify-content: center; flex-shrink: 0;">
                            <?php if (!empty($ptFeedback['profile_image'])): ?>
                                <img src="<?= BASE_URL . htmlspecialchars($ptFeedback['profile_image'], ENT_QUOTES) ?>" style="width: 100%; height: 100%; object-fit: cover;">
                            <?php else: ?>
                                <i class="fas fa-dumbbell" style="font-size: 20px; color: var(--color-secondary);"></i>
                            <?php endif; ?>
                        </div>
                        <div style="flex: 1;">
                            <h4 style="margin: 0 0 4px 0; font-weight: 700; color: var(--color-secondary); font-size: 14px;">
                                <i class="fas fa-comment-medical"></i> <?= ($lang === 'vi') ? 'Lời khuyên từ Huấn luyện viên ' : 'Advice from Trainer ' ?><?= htmlspecialchars($ptFeedback['first_name'] . ' ' . $ptFeedback['last_name'], ENT_QUOTES) ?>
                                <?php if (!$fbForToday): ?>
                                    <span style="font-weight: 600; color: var(--color-text-secondary); font-size: 12px;">· <?= ($lang === 'vi') ? 'cho ngày' : 'for' ?> <?= $fbDateLabel ?></span>
                                <?php endif; ?>
                            </h4>
                            <p style="margin: 0; font-size: 13px; color: var(--color-text); line-height: 1.5; font-style: italic;">"<?= htmlspecialchars($ptFeedback['content'], ENT_QUOTES) ?>"</p>
                        </div>
                    </section>
                <?php endif; ?>

                <?php if ($isLoggedIn && !empty($myTrainer)):
                    $trainerName = htmlspecialchars($myTrainer['first_name'] . ' ' . $myTrainer['last_name'], ENT_QUOTES);
                ?>
                    <!-- Two-way chat with the linked trainer (Task #3) -->
                    <section class="dashboard-card" style="margin-bottom: 24px; padding: 20px; border-radius: var(--radius-lg); box-shadow: 0 8px 0 var(--color-border-subtle), var(--shadow-sm);">
                        <h4 style="margin: 0 0 12px 0; font-weight: 700; color: var(--color-secondary); font-size: 14px;">
                            <i class="fas fa-comments"></i> <?= ($lang === 'vi') ? 'Trò chuyện với HLV ' : 'Chat with Trainer ' ?><?= $trainerName ?>
                        </h4>
                        <div class="pt-chat" id="trainerChat" data-auto-init
                             data-endpoint="<?= BASE_URL ?>dashboard/handlers/pt_chat.php"
                             data-csrf="<?= htmlspecialchars(csrf_token(), ENT_QUOTES) ?>"
                             data-self-role="client"
                             data-counterpart-id="<?= (int) $myTrainer['user_id'] ?>"
                             data-empty-text="<?= ($lang === 'vi') ? 'Chưa có tin nhắn. Hãy hỏi HLV của bạn!' : 'No messages yet. Ask your trainer!' ?>">
                            <div class="pt-chat__messages"></div>
                            <form class="pt-chat__form">
                                <textarea class="pt-chat__input" rows="1" placeholder="<?= ($lang === 'vi') ? 'Nhắn cho HLV của bạn...' : 'Message your trainer...' ?>"></textarea>
                                <button type="submit"><i class="fas fa-paper-plane"></i></button>
                            </form>
                        </div>
                    </section>
                <?php endif; ?>

                <?php if (!$isLoggedIn): ?>
                    <div class="demo-banner">
                        <i class="fas fa-flask"></i>
                        <span><strong><?= t('intake.demo_note') ?></strong> <?= t('intake.demo_body') ?></span>
                        <a href="<?= BASE_URL ?>signup.php" class="demo-banner-cta"><?= t('dashboard.demo.cta') ?></a>
                    </div>
                <?php endif; ?>

                <!-- COLUMN 1: Nutrition Summary & Food Form -->
                <div class="flex intake-left-column">
                    <!-- NUTRITION HUB CARD -->
                    <section class="dashboard-card stats-hub-card" id="nutritionHubCard">
                        <!-- 3D Segmented Tabs Switcher -->
                        <div class="stats-hub-tabs">
                            <button type="button" class="tab-btn active" onclick="switchNutritionTab('calories')">
                                <i class="fas fa-bolt"></i> <?= t('dashboard.tabs.nutrition') ?>
                            </button>
                            <button type="button" class="tab-btn" onclick="switchNutritionTab('macros')">
                                <i class="fas fa-chart-pie"></i> <?= t('intake.macros_today') ?>
                            </button>
                        </div>

                        <!-- TAB PANES -->
                        <!-- Pane 1: Calories (Dinh Dưỡng) -->
                        <div class="chart-wrapper-tab active" id="tabPane-calories">
                            <section class="progress-widget">
                                <div class="progress-card">
                                    <div class="progress-card-content">
                                        <div class="progress-header">
                                            <h3><?= t('intake.todays_intake') ?></h3>
                                            <span class="status-badge <?php echo $statusClass; ?>"><?php
                                                $__statusMap = [
                                                    'Unset' => 'intake.status.unset',
                                                    'Ongoing' => 'intake.status.ongoing',
                                                    'Overlimit' => 'intake.status.overlimit',
                                                ];
                                                echo isset($__statusMap[$status]) ? t($__statusMap[$status]) : htmlspecialchars($status);
                                            ?></span>
                                        </div>

                                        <div class="progress-value">
                                            <span class="<?php echo $statusClass; ?>" id="totalDisplay"><?php echo $totalCalories; ?></span>
                                            <small><?= t('intake.calories_unit') ?></small>
                                        </div>

                                        <div class="progress-bar">
                                            <div class="progress-fill <?php echo htmlspecialchars($statusClass); ?>" id="progressFill" style="width: 0%;"></div>
                                        </div>

                                        <div class="progress-labels">
                                            <span><?= t_raw('intake.goal_label', ['value' => '<strong>' . ($userGoal ? number_format($userGoal) : t_raw('intake.status.unset')) . '</strong>']) ?></span>
                                            <span class="pct-label"><?php echo $userGoal ? round(($totalCalories / $userGoal) * 100) . '%' : '0%'; ?></span>
                                        </div>
                                    </div>
                                </div>
                            </section>
                        </div>

                        <!-- Pane 2: Macros (Macronutrients) -->
                        <div class="chart-wrapper-tab" id="tabPane-macros">
                            <?php
                            $macros = $macroTotals ?? ['protein' => 0, 'carbs' => 0, 'fat' => 0];
                            $mGoals = $macroGoals  ?? ['protein' => 0, 'carbs' => 0, 'fat' => 0];
                            $macroDefs = [
                                'protein' => ['label' => t_raw('dashboard.macros.protein'), 'class' => 'p', 'icon' => 'fa-drumstick-bite'],
                                'carbs'   => ['label' => t_raw('dashboard.macros.carbs'),   'class' => 'c', 'icon' => 'fa-bread-slice'],
                                'fat'     => ['label' => t_raw('dashboard.macros.fat'),     'class' => 'f', 'icon' => 'fa-cheese'],
                            ];
                            ?>
                            <section class="chart-section macros-widget meals-card">
                                <div class="doughnut-container">
                                    <canvas id="macrosDonut"></canvas>
                                    <div class="doughnut-center-text">
                                        <span class="center-val" id="donutKcalLabel">0</span>
                                        <span class="center-label"><?= t('intake.grams') ?></span>
                                    </div>
                                </div>

                                <div class="macro-list-container">
                                    <?php foreach ($macroDefs as $key => $def):
                                        $cur = (float) ($macros[$key] ?? 0);
                                        $goal = (int) ($mGoals[$key] ?? 0);
                                        $pct = $goal > 0 ? min(100, round($cur / $goal * 100)) : 0;
                                        $curDisp = rtrim(rtrim(number_format($cur, 1, '.', ''), '0'), '.');
                                        if ($curDisp === '') $curDisp = '0';
                                    ?>
                                    <div class="macro-item <?= $def['class'] ?>">
                                        <div class="macro-icon-box">
                                            <i class="fas <?= $def['icon'] ?>"></i>
                                        </div>
                                        <div class="macro-info">
                                            <div class="macro-info-top">
                                                <span class="macro-name"><?= $def['label'] ?></span>
                                                <span class="macro-numbers">
                                                    <strong id="macroVal_<?= $key ?>"><?= $curDisp ?></strong>g
                                                    <small>/ <span id="macroGoal_<?= $key ?>"><?= $goal ?: '–' ?></span>g</small>
                                                </span>
                                            </div>
                                            <div class="macro-track">
                                                <div class="macro-fill macro-fill-<?= $def['class'] ?>"
                                                     id="macroFill_<?= $key ?>" style="width: <?= $pct ?>%;"></div>
                                            </div>
                                        </div>
                                    </div>
                                    <?php endforeach; ?>

                                    <?php if (!$userGoal): ?>
                                        <p class="macros-hint-empty">
                                            <i class="fas fa-info-circle"></i>
                                            <?= t('intake.set_goal_hint') ?>
                                        </p>
                                    <?php endif; ?>
                                </div>
                            </section>
                        </div>
                    </section>

                    <script>
                        // Initial macro values for donut (grams)
                        window.__macroState = {
                            protein: <?= json_encode((float) ($macros['protein'] ?? 0)) ?>,
                            carbs:   <?= json_encode((float) ($macros['carbs']   ?? 0)) ?>,
                            fat:     <?= json_encode((float) ($macros['fat']     ?? 0)) ?>,
                        };
                    </script>

                    <!-- LOG FOOD FORM -->
                    <?php if ($isLoggedIn): ?>
                    <section class="dashboard-card intake-form-card">
                        <div class="card-header">
                            <h3><i class="fas fa-plus-circle"></i> <?= t('intake.log_food_heading') ?></h3>
                        </div>

                        <div id="alertPlaceholder">
                            <?php if (!empty($error_message)): ?>
                                <div class="alert error"><i class="fas fa-exclamation-triangle"></i>
                                    <?php echo $error_message; ?></div>
                            <?php endif; ?>
                        </div>

                        <div class="quick-actions-row">
                            <button type="button" class="quick-action-chip" id="openScannerChip">
                                <i class="fas fa-barcode"></i> <?= t('intake.scan_barcode_chip') ?>
                            </button>
                            <button type="button" class="quick-action-chip" onclick="toggleChat()">
                                <i class="fas fa-robot"></i> <?= t('intake.ai_photo_chip') ?>
                            </button>
                        </div>

                        <form id="intakeForm" action="handlers/process_intake.php" method="POST"
                            data-is-today="<?= $isPastDate ? '0' : '1' ?>"
                            data-date-label="<?= htmlspecialchars($shortDateLabel, ENT_QUOTES) ?>">
                            <input type="hidden" name="image_path" id="image_path" value="">
                            <input type="hidden" name="date" value="<?= htmlspecialchars($selectedDate, ENT_QUOTES) ?>">
                            <div class="form-group">
                                <label for="food_item"><?= t('intake.food_name') ?></label>
                                <div class="input-icon-wrapper food-name-wrapper">
                                    <i class="fas fa-utensils input-icon"></i>
                                    <input type="text" id="food_item" name="food_item" placeholder="<?= t('intake.food_name_placeholder') ?>"
                                        required>
                                    <button type="button" class="btn-inline-scan" id="openScannerInline" title="<?= t('intake.scan_barcode_inline') ?>">
                                        <i class="fas fa-barcode"></i>
                                    </button>
                                </div>
                            </div>

                            <div class="form-row-split">
                                <div class="form-group">
                                    <label for="calories" id="calorieLabel"><?= t('intake.calories_label') ?></label>
                                    <div class="input-icon-wrapper">
                                        <i class="fas fa-bolt input-icon"></i>
                                        <input type="number" id="calories" name="calories" placeholder="0" required>
                                    </div>
                                </div>
                                <div class="form-group">
                                    <label for="unit_toggle"><?= t('intake.unit') ?></label>
                                    <div class="select-wrapper">
                                        <select id="unit_toggle">
                                            <option value="cal"><?= t('intake.unit.cal') ?></option>
                                            <option value="kj"><?= t('intake.unit.kj') ?></option>
                                        </select>
                                    </div>
                                </div>
                            </div>

                            <div class="form-group macros-input-group">
                                <label class="macros-input-label"><?= t('intake.macros_label') ?> <small><?= t('intake.macros_hint_inline') ?></small></label>
                                <div class="macros-input-row">
                                    <div class="macro-input p">
                                        <label for="protein" title="<?= t('intake.form.protein') ?>">P</label>
                                        <input type="number" id="protein" name="protein" min="0" max="999" step="0.1" placeholder="0">
                                    </div>
                                    <div class="macro-input c">
                                        <label for="carbs" title="<?= t('intake.form.carbs') ?>">C</label>
                                        <input type="number" id="carbs" name="carbs" min="0" max="999" step="0.1" placeholder="0">
                                    </div>
                                    <div class="macro-input f">
                                        <label for="fat" title="<?= t('intake.form.fat') ?>">F</label>
                                        <input type="number" id="fat" name="fat" min="0" max="999" step="0.1" placeholder="0">
                                    </div>
                                </div>
                            </div>

                            <div class="form-group">
                                <label for="meal_category"><?= t('intake.category_label') ?></label>
                                <div class="select-wrapper">
                                    <select id="meal_category" name="meal_category" required>
                                        <option value="" disabled <?= $prefillMeal === '' ? 'selected' : '' ?>><?= t('intake.select_category') ?></option>
                                        <option value="breakfast" <?= $prefillMeal === 'breakfast' ? 'selected' : '' ?>><?= t('intake.meal.breakfast_emoji') ?></option>
                                        <option value="lunch" <?= $prefillMeal === 'lunch' ? 'selected' : '' ?>><?= t('intake.meal.lunch_emoji') ?></option>
                                        <option value="dinner" <?= $prefillMeal === 'dinner' ? 'selected' : '' ?>><?= t('intake.meal.dinner_emoji') ?></option>
                                        <option value="snack" <?= $prefillMeal === 'snack' ? 'selected' : '' ?>><?= t('intake.meal.snack_emoji') ?></option>
                                    </select>
                                </div>
                            </div>

                            <div class="ai-tip">
                                <i class="fas fa-robot"></i>
                                <span><?= t('intake.ai_tip') ?></span>
                            </div>

                            <button type="submit" class="btn-submit">
                                <i class="fas fa-check"></i>
                                <?php if ($isPastDate): ?>
                                    <?= t('intake.log_entry_btn_dated', ['date' => $shortDateLabel]) ?>
                                <?php else: ?>
                                    <?= t('intake.log_entry_btn') ?>
                                <?php endif; ?>
                            </button>
                        </form>
                        <?php if ($prefillMeal !== ''): ?>
                            <script>
                                // Arrived from an Overview "+" (meal already chosen) — drop the
                                // cursor straight into the food name so the user can just type.
                                document.getElementById('food_item')?.focus({ preventScroll: false });
                            </script>
                        <?php endif; ?>
                    </section>
                    <?php else: ?>
                    <section class="dashboard-card intake-form-card intake-form-card--demo">
                        <div class="card-header">
                            <h3><i class="fas fa-plus-circle"></i> <?= t('intake.log_food_heading') ?></h3>
                            <span class="demo-pill"><?= t('intake.live_demo') ?></span>
                        </div>

                        <div class="demo-preview-fields" aria-hidden="true">
                            <div class="form-group">
                                <label><?= t('intake.food_name') ?></label>
                                <div class="input-icon-wrapper">
                                    <i class="fas fa-utensils input-icon"></i>
                                    <input type="text" value="Pho Bo" disabled>
                                </div>
                            </div>
                            <div class="form-row-split">
                                <div class="form-group">
                                    <label><?= t('intake.calories_label') ?></label>
                                    <div class="input-icon-wrapper">
                                        <i class="fas fa-bolt input-icon"></i>
                                        <input type="number" value="450" disabled>
                                    </div>
                                </div>
                                <div class="form-group">
                                    <label><?= t('intake.category_label') ?></label>
                                    <div class="select-wrapper">
                                        <select disabled>
                                            <option><?= t('intake.meal.breakfast_emoji') ?></option>
                                        </select>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="demo-cta">
                            <p><i class="fas fa-lock"></i> <?= t('intake.signup_hint') ?></p>
                            <a href="<?= BASE_URL ?>signup.php" class="btn-submit">
                                <i class="fas fa-user-plus"></i> <?= t('intake.signup_btn') ?>
                            </a>
                            <a href="<?= BASE_URL ?>login.php" class="demo-cta-secondary"><?= t('intake.signin_alt') ?></a>
                        </div>
                    </section>
                    <?php endif; ?>
                </div>

                <!-- COLUMN 2: Today's Food History Table -->
                <div class="flex intake-right-column">
                    <section class="dashboard-card intake-list-card">
                        <div class="card-header">
                            <h3><i class="fas fa-list-ul"></i> <?= t('intake.todays_history') ?></h3>
                        </div>

                        <div class="table-responsive">
                            <table class="modern-table modern-table--today">
                                <thead>
                                    <tr>
                                        <th><?= t('intake.col.food_item') ?></th>
                                        <th><?= t('intake.col.calories') ?></th>
                                        <th><?= t('intake.col.macros') ?></th>
                                        <th><?= t('intake.col.category') ?></th>
                                        <th><?= t('intake.col.time') ?></th>
                                        <th class="row-actions-head"><?= t('intake.col.action') ?></th>
                                    </tr>
                                </thead>
                                <tbody id="intakeTableBody">
                                    <?php if (empty($intakeLog)): ?>
                                        <tr id="noIntakeRow">
                                            <td colspan="6" class="empty-state">
                                                <i class="fas fa-drumstick-bite"></i> <?= t('intake.empty_today') ?>
                                            </td>
                                        </tr>
                                    <?php endif; ?>

                                    <?php foreach ($intakeLog as $log): ?>
                                        <?php $entry = $log; $showDate = false; $hideActions = !$isLoggedIn; include PROJECT_ROOT . 'dashboard/views/_intake-row.php'; ?>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </section>
                </div>
            </div>
        </main>

        <?php if ($isLoggedIn): ?>
        <!-- =============== Barcode Scanner Modal =============== -->
        <div class="scanner-modal" id="scannerModal" role="dialog" aria-modal="true">
            <div class="scanner-modal-content">
                <div class="scanner-modal-header">
                    <h3><i class="fas fa-barcode"></i> <?= t('intake.scanner.title') ?></h3>
                    <button type="button" class="scanner-close" id="scannerCloseBtn" aria-label="<?= t('common.close') ?>">&times;</button>
                </div>

                <div id="scannerReader"></div>

                <div class="scanner-controls">
                    <button type="button" class="scanner-btn-start" id="scannerStartBtn">
                        <i class="fas fa-camera"></i> <?= t('intake.scanner.start_camera') ?>
                    </button>
                    <button type="button" class="scanner-btn-stop" id="scannerStopBtn" style="display:none;">
                        <i class="fas fa-stop"></i> <?= t('intake.scanner.stop') ?>
                    </button>
                </div>

                <div class="scanner-manual">
                    <label for="scannerManualInput"><?= t('intake.scanner.manual_label') ?></label>
                    <div class="scanner-manual-row">
                        <input type="text" id="scannerManualInput" inputmode="numeric" placeholder="<?= t('intake.scanner.manual_placeholder') ?>">
                        <button type="button" id="scannerManualBtn"><?= t('intake.scanner.lookup') ?></button>
                    </div>
                </div>

                <div id="scannerResult"></div>
            </div>
        </div>

        <script>
        (function() {
            const modal       = document.getElementById('scannerModal');
            const closeBtn    = document.getElementById('scannerCloseBtn');
            const startBtn    = document.getElementById('scannerStartBtn');
            const stopBtn     = document.getElementById('scannerStopBtn');
            const manualInput = document.getElementById('scannerManualInput');
            const manualBtn   = document.getElementById('scannerManualBtn');
            const resultEl    = document.getElementById('scannerResult');
            const chipBtn     = document.getElementById('openScannerChip');
            const inlineBtn   = document.getElementById('openScannerInline');

            let html5QrCode   = null;
            let scanning      = false;
            let lastCode      = null;
            let lastCodeTime  = 0;
            let currentProduct = null; // last successful lookup

            function openModal() {
                modal.classList.add('active');
                resultEl.innerHTML = '';
                manualInput.value = '';
            }

            async function closeModal() {
                modal.classList.remove('active');
                await stopScanner();
            }

            chipBtn?.addEventListener('click', openModal);
            inlineBtn?.addEventListener('click', openModal);
            closeBtn.addEventListener('click', closeModal);
            modal.addEventListener('click', (e) => {
                if (e.target === modal) closeModal();
            });

            // ============ Camera ============
            startBtn.addEventListener('click', async () => {
                if (scanning || typeof Html5Qrcode === 'undefined') {
                    if (typeof Html5Qrcode === 'undefined') {
                        alert(<?= json_encode(t_raw('intake.scanner.lib_failed')) ?>);
                    }
                    return;
                }
                try {
                    html5QrCode = new Html5Qrcode("scannerReader");
                    await html5QrCode.start(
                        { facingMode: "environment" },
                        { fps: 10, qrbox: { width: 260, height: 140 } },
                        onScanSuccess,
                        () => {}
                    );
                    scanning = true;
                    startBtn.style.display = 'none';
                    stopBtn.style.display = 'block';
                } catch (err) {
                    alert("Could not access camera: " + (err.message || err) +
                          "\n\nMake sure you allowed camera access and are on HTTPS or localhost.");
                }
            });

            stopBtn.addEventListener('click', stopScanner);

            async function stopScanner() {
                if (!scanning || !html5QrCode) return;
                try { await html5QrCode.stop(); html5QrCode.clear(); } catch (e) {}
                scanning = false;
                startBtn.style.display = 'block';
                stopBtn.style.display = 'none';
            }

            function onScanSuccess(decodedText) {
                const now = Date.now();
                if (decodedText === lastCode && (now - lastCodeTime) < 3000) return;
                lastCode = decodedText;
                lastCodeTime = now;
                if (navigator.vibrate) navigator.vibrate(80);
                lookupBarcode(decodedText);
            }

            // ============ Manual lookup ============
            manualBtn.addEventListener('click', () => {
                const code = manualInput.value.trim();
                if (code) lookupBarcode(code);
            });
            manualInput.addEventListener('keypress', (e) => {
                if (e.key === 'Enter') { e.preventDefault(); manualBtn.click(); }
            });

            // ============ API call ============
            async function lookupBarcode(code) {
                resultEl.innerHTML = '<div class="scanner-result loading">' +
                    '<div class="scanner-result-name">⏳ Looking up ' + escapeHtml(code) + '...</div>' +
                    '</div>';
                const fd = new FormData();
                fd.append('barcode', code);
                try {
                    const res = await fetch('handlers/lookup_barcode.php', {
                        method: 'POST',
                        headers: { 'X-Requested-With': 'fetch' },
                        body: fd
                    });
                    const data = await res.json();
                    if (!data.ok) {
                        resultEl.innerHTML = '<div class="scanner-result not-found">' +
                            '<div class="scanner-result-name">⚠ Error</div>' +
                            '<div class="scanner-result-brand">' + escapeHtml(data.error || 'Unknown error') + '</div>' +
                            '</div>';
                        return;
                    }
                    if (!data.found) {
                        renderNotFound(code);
                        return;
                    }
                    currentProduct = data;
                    renderFound(data);
                } catch (err) {
                    resultEl.innerHTML = '<div class="scanner-result not-found">' +
                        '<div class="scanner-result-name">⚠ Network error</div>' +
                        '<div class="scanner-result-brand">' + escapeHtml(err.message || '') + '</div>' +
                        '</div>';
                }
            }

            // ============ Render ============
            function renderFound(p) {
                const hasServing = p.kcal_per_serving != null;
                const kcalBase   = hasServing ? p.kcal_per_serving : p.kcal_per_100g;
                const baseLabel  = hasServing ? (p.serving_size || '1 serving') : 'per 100g/ml';
                const cachedTag  = p.cache_hit ? '<span style="font-size:.65rem;background:#dcfce7;color:#166534;padding:2px 6px;border-radius:4px;margin-left:6px;">cached</span>' : '';

                resultEl.innerHTML = `
                  <div class="scanner-result">
                    ${p.image_url ? `<img class="scanner-result-img" src="${escapeHtml(p.image_url)}" alt="">` : ''}
                    <div class="scanner-result-name">${escapeHtml(p.product_name || '(no name)')} ${cachedTag}</div>
                    <div class="scanner-result-brand">
                      ${escapeHtml(p.brand || '')} ${p.serving_size ? '· ' + escapeHtml(p.serving_size) : ''}
                    </div>
                    <div class="scanner-nutri">
                      <div class="scanner-nutri-item">
                        <div class="scanner-nutri-label">Kcal</div>
                        <div class="scanner-nutri-value">${kcalBase != null ? Math.round(kcalBase) : '—'}</div>
                      </div>
                      <div class="scanner-nutri-item">
                        <div class="scanner-nutri-label">Protein</div>
                        <div class="scanner-nutri-value">${fmt(p.protein)}<small>g</small></div>
                      </div>
                      <div class="scanner-nutri-item">
                        <div class="scanner-nutri-label">Carbs</div>
                        <div class="scanner-nutri-value">${fmt(p.carbs)}<small>g</small></div>
                      </div>
                      <div class="scanner-nutri-item">
                        <div class="scanner-nutri-label">Fat</div>
                        <div class="scanner-nutri-value">${fmt(p.fat)}<small>g</small></div>
                      </div>
                    </div>

                    <div class="scanner-portion">
                      <label for="portionMult"><?= t('intake.scanner.servings') ?></label>
                      <input type="number" id="portionMult" value="1" min="0.1" step="0.1">
                      <span class="portion-kcal" id="portionKcalDisplay">${kcalBase != null ? Math.round(kcalBase) : 0} kcal</span>
                    </div>

                    <div class="scanner-actions">
                      <button type="button" class="scanner-btn-cancel" id="scannerCancelBtn"><?= t('intake.scanner.cancel') ?></button>
                      <button type="button" class="scanner-btn-confirm" id="scannerConfirmBtn">
                        <i class="fas fa-plus"></i> <?= t('intake.scanner.confirm') ?>
                      </button>
                    </div>
                  </div>
                `;

                // Wire portion calculator
                const portionInput = document.getElementById('portionMult');
                const portionKcal  = document.getElementById('portionKcalDisplay');
                portionInput.addEventListener('input', () => {
                    const m = parseFloat(portionInput.value) || 0;
                    if (kcalBase != null) portionKcal.textContent = Math.round(kcalBase * m) + ' kcal';
                });

                document.getElementById('scannerCancelBtn').addEventListener('click', closeModal);
                document.getElementById('scannerConfirmBtn').addEventListener('click', () => {
                    const m = parseFloat(portionInput.value) || 1;
                    fillForm(p, m, kcalBase, hasServing);
                });
            }

            function renderNotFound(code) {
                resultEl.innerHTML = `
                  <div class="scanner-result not-found">
                    <div class="scanner-result-name">Product not in database</div>
                    <div class="scanner-result-brand">Barcode <code>${escapeHtml(code)}</code> wasn't found.</div>
                    <p class="scanner-not-found-hint">
                      Enter the product manually in the form below — we'll log this attempt to improve coverage.
                    </p>
                    <div class="scanner-actions">
                      <button type="button" class="scanner-btn-cancel" id="scannerCancelBtn"><?= t('intake.scanner.close') ?></button>
                    </div>
                  </div>
                `;
                document.getElementById('scannerCancelBtn').addEventListener('click', closeModal);
            }

            // ============ Fill the existing intake form ============
            function fillForm(p, multiplier, kcalBase, hasServing) {
                const namePieces = [p.product_name];
                if (p.brand) namePieces.unshift(p.brand);
                const displayName = namePieces.filter(Boolean).join(' ');

                const food = document.getElementById('food_item');
                const cal  = document.getElementById('calories');
                const prot = document.getElementById('protein');
                const carb = document.getElementById('carbs');
                const fat  = document.getElementById('fat');

                if (food) food.value = displayName;
                if (cal && kcalBase != null) cal.value = Math.round(kcalBase * multiplier);
                if (hasServing) {
                    if (prot && p.protein != null) prot.value = round1(p.protein * multiplier);
                    if (carb && p.carbs   != null) carb.value = round1(p.carbs   * multiplier);
                    if (fat  && p.fat     != null) fat.value  = round1(p.fat     * multiplier);
                }

                closeModal();

                // Scroll + highlight (reuses existing pattern from autoLogFood)
                setTimeout(() => {
                    document.querySelector('.intake-form-card')?.scrollIntoView({ behavior: 'smooth' });
                }, 300);
                const formCard = document.querySelector('.intake-form-card');
                if (formCard) {
                    formCard.style.transition = "box-shadow 0.5s";
                    formCard.style.boxShadow  = "0 0 20px rgba(74, 126, 227, 0.5)";
                    setTimeout(() => { formCard.style.boxShadow = ""; }, 1500);
                }
            }

            // ============ utils ============
            function fmt(v) {
                if (v == null) return '—';
                return Number.isInteger(v) ? v : (Math.round(v * 10) / 10);
            }
            function round1(v) { return Math.round(v * 10) / 10; }
            function escapeHtml(s) {
                return String(s ?? '').replace(/[&<>"']/g, c => ({
                    '&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'
                }[c]));
            }
        })();
        </script>

        <div id="ai-widget-container">
            <div id="ai-chat-window" class="chat-window">
                <div class="chat-header">
                    <div class="header-info">
                        <div class="ai-avatar"><i class="fas fa-robot"></i></div>
                        <div>
                            <h4><?= t('intake.chat.title') ?></h4>
                            <span class="status-dot"></span> <small>Online</small>
                        </div>
                    </div>
                    <button class="close-chat" onclick="toggleChat()"><i class="fas fa-times"></i></button>
                </div>

                <div class="chat-body" id="chatBody">
                    <div class="message bot-message">
                        Hello! I can help you estimate calories. 📸 <br>
                        Give me a food name or upload a photo!
                    </div>
                </div>

                <div class="chat-footer">
                    <button class="btn-tool" title="<?= t('intake.chat.upload_photo') ?>" onclick="document.getElementById('imgUpload').click()">
                        <i class="fas fa-camera"></i>
                    </button>
                    <input type="file" id="imgUpload" accept="image/*" style="display: none;"
                        onchange="handleImageUpload(this)">

                    <input type="text" id="chatInput" placeholder="<?= t('intake.chat.placeholder') ?>" onkeypress="handleEnter(event)">

                    <button class="btn-send" onclick="sendMessage()">
                        <i class="fas fa-paper-plane"></i>
                    </button>
                </div>
            </div>

            <div id="ai-bubble" class="chat-bubble">
                <i class="fas fa-comment-dots"></i>
            </div>
        </div>
        <script>
            const bubble = document.getElementById('ai-bubble');
            const chatWindow = document.getElementById('ai-chat-window');
            const chatBody = document.getElementById('chatBody');
            const chatInput = document.getElementById('chatInput');
            let currentImageFile = null;

            // --- VIEWPORT SYNC FOR MOBILE KEYBOARD ---
            // Shrinks and shifts the mobile full-screen chat drawer in real time with the on-screen keyboard,
            // keeping the input footer always perfectly visible above the keyboard.
            function syncChatViewport() {
                if (window.innerWidth <= 480 && chatWindow.classList.contains('active')) {
                    const vv = window.visualViewport;
                    const vh = vv ? vv.height : window.innerHeight;
                    const vt = vv ? vv.offsetTop : 0;
                    chatWindow.style.setProperty('--chat-vh', vh + 'px');
                    chatWindow.style.setProperty('--chat-vp-top', vt + 'px');
                } else {
                    chatWindow.style.removeProperty('--chat-vh');
                    chatWindow.style.removeProperty('--chat-vp-top');
                }
            }

            if (window.visualViewport) {
                window.visualViewport.addEventListener('resize', syncChatViewport);
                window.visualViewport.addEventListener('scroll', syncChatViewport);
            }
            window.addEventListener('resize', syncChatViewport);
            window.addEventListener('orientationchange', syncChatViewport);

            // Programmatic safe scroll reset to undo iOS auto-scrolling
            const resetIntakeScroll = () => {
                if (window.innerWidth <= 480) {
                    if (window.scrollY !== 0 || window.scrollX !== 0) {
                        window.scrollTo(0, 0);
                    }
                    syncChatViewport();
                }
            };

            // --- 1. LOGIC GIAO DIỆN (UI) ---

            function toggleChat() {
                const isActive = chatWindow.classList.contains('active');

                if (isActive) {
                    // ĐÓNG CHAT
                    chatWindow.classList.remove('active');

                    // Hiện lại bong bóng (Desktop & Mobile)
                    bubble.classList.remove('hidden');
                    bubble.classList.remove('hidden-mobile'); // Đảm bảo mobile cũng hiện lại

                    // Mobile: Mở khóa cuộn trang
                    if (window.innerWidth <= 480) {
                        document.body.classList.remove('chat-open');
                    }
                } else {
                    // MỞ CHAT
                    chatWindow.classList.add('active');

                    // Ẩn bong bóng
                    bubble.classList.add('hidden');

                    // Mobile: Khóa cuộn trang
                    if (window.innerWidth <= 480) {
                        document.body.classList.add('chat-open');
                        bubble.classList.add('hidden-mobile');
                        scrollToBottom();
                    }

                    // Focus vào ô nhập liệu (Chỉ Desktop)
                    setTimeout(() => {
                        if (window.innerWidth > 480) chatInput.focus();
                    }, 300);
                }
                syncChatViewport(); // Synchronize viewport heights immediately
            }

            // Nút đóng chat
            document.querySelector('.close-chat').onclick = toggleChat;

            // --- 2. LOGIC KÉO THẢ (DRAG) ---

            let isDragging = false;
            let hasMoved = false;
            let offset = { x: 0, y: 0 };
            let startPos = { x: 0, y: 0 }; // Lưu vị trí bắt đầu để tính khoảng cách

            function startDrag(e) {
                // Nếu bong bóng đang ẩn (đang chat) thì không làm gì
                if (bubble.classList.contains('hidden')) return;

                isDragging = true;
                hasMoved = false; // Reset trạng thái

                // Lấy tọa độ chuột/ngón tay
                const clientX = e.clientX || e.touches[0].clientX;
                const clientY = e.clientY || e.touches[0].clientY;

                startPos = { x: clientX, y: clientY }; // Lưu vị trí gốc

                const rect = bubble.getBoundingClientRect();
                offset.x = clientX - rect.left;
                offset.y = clientY - rect.top;

                bubble.style.cursor = 'grabbing';
            }

            function moveDrag(e) {
                if (!isDragging) return;

                const clientX = e.clientX || e.touches[0].clientX;
                const clientY = e.clientY || e.touches[0].clientY;

                // --- FIX LỖI Ở ĐÂY: Tính khoảng cách di chuyển ---
                const moveX = Math.abs(clientX - startPos.x);
                const moveY = Math.abs(clientY - startPos.y);

                // Chỉ coi là "Kéo" nếu di chuyển quá 5 pixel (chống rung tay)
                if (moveX > 5 || moveY > 5) {
                    hasMoved = true;
                    e.preventDefault(); // Chỉ chặn mặc định khi thực sự kéo

                    let newLeft = clientX - offset.x;
                    let newTop = clientY - offset.y;

                    const maxX = window.innerWidth - bubble.offsetWidth;
                    const maxY = window.innerHeight - bubble.offsetHeight;

                    bubble.style.bottom = 'auto';
                    bubble.style.right = 'auto';
                    bubble.style.left = `${Math.min(Math.max(0, newLeft), maxX)}px`;
                    bubble.style.top = `${Math.min(Math.max(0, newTop), maxY)}px`;
                }
            }

            function endDrag() {
                if (isDragging) {
                    isDragging = false;
                    bubble.style.cursor = 'pointer';

                    // Nếu chưa di chuyển quá 5px -> Coi là CLICK -> Mở Chat
                    if (!hasMoved) {
                        toggleChat();
                    }
                }
            }

            // Mouse Events
            bubble.addEventListener('mousedown', startDrag);
            window.addEventListener('mousemove', moveDrag);
            window.addEventListener('mouseup', endDrag);

            // Touch Events
            bubble.addEventListener('touchstart', startDrag, { passive: false });
            window.addEventListener('touchmove', moveDrag, { passive: false });
            window.addEventListener('touchend', endDrag);


            // --- 3. LOGIC CHATBOT API (Giữ nguyên) ---

            function handleEnter(e) {
                if (e.key === 'Enter') sendMessage();
            }

            function scrollToBottom() {
                if (chatBody) {
                    setTimeout(() => { chatBody.scrollTop = chatBody.scrollHeight; }, 100);
                }
            }

            // Mobile focus fix
            if (chatInput) {
                chatInput.addEventListener('focus', () => {
                    scrollToBottom();
                    setTimeout(resetIntakeScroll, 100);
                });
                chatInput.addEventListener('blur', () => {
                    setTimeout(resetIntakeScroll, 100);
                });
            }

            function handleImageUpload(input) {
                if (input.files && input.files[0]) {
                    currentImageFile = input.files[0];
                    const reader = new FileReader();
                    reader.onload = function (e) {
                        addMessage(`<img src="${e.target.result}" style="max-width:100px; border-radius:8px; display:block; margin-bottom:5px;"> <em>Image selected. Type a message or hit send.</em>`, 'user');
                    }
                    reader.readAsDataURL(currentImageFile);
                }
            }

            async function sendMessage() {
                const text = chatInput.value.trim();
                if (!text && !currentImageFile) return;

                if (text) addMessage(text, 'user');
                chatInput.value = '';

                const loadingId = addMessage('<i class="fas fa-robot fa-bounce"></i> Analyzing...', 'bot');

                const formData = new FormData();
                formData.append('message', text);
                if (currentImageFile) formData.append('image', currentImageFile);

                try {
                    const res = await fetch('handlers/ai_chat.php', { method: 'POST', body: formData });
                    const result = await res.json();

                    const loadingEl = document.getElementById(loadingId);
                    if (loadingEl) loadingEl.remove();

                    if (result.ok && result.data) {
                        const info = result.data;
                        if (info.food_name === null) {
                            addMessage("I couldn't identify any food. Please try again.", 'bot');
                        } else {
                            const safeName = String(info.food_name).replace(/'/g, "\\'");
                            const cardHtml = `
                        <div class="nutri-card">
                            <div class="nutri-header">
                                <strong>${info.food_name}</strong>
                                <span class="nutri-cal">${info.calories} kcal</span>
                            </div>
                            <div class="nutri-macros">
                                <span class="macro p">P: ${info.protein}g</span>
                                <span class="macro c">C: ${info.carbs}g</span>
                                <span class="macro f">F: ${info.fat}g</span>
                            </div>
                            <p class="nutri-advice">${info.short_advice}</p>
                             <button class="btn-add-log" onclick="autoLogFood('${safeName}', ${info.calories}, ${info.protein}, ${info.carbs}, ${info.fat}, '${result.image_path || ''}')">
                                 <i class="fas fa-plus"></i> Add to Log
                             </button>
                        </div>`;
                            addMessage(cardHtml, 'bot');
                        }
                    } else {
                        addMessage(`Server Error: ${result.error || 'Unknown error'}`, 'bot');
                    }
                } catch (err) {
                    const loadingEl = document.getElementById(loadingId);
                    if (loadingEl) loadingEl.remove();
                    addMessage("Network error. Please try again.", 'bot');
                } finally {
                    currentImageFile = null;
                    document.getElementById('imgUpload').value = '';
                }
            }

            function addMessage(html, sender) {
                const div = document.createElement('div');
                div.className = `message ${sender}-message`;
                div.id = 'msg-' + Date.now();
                div.innerHTML = html;
                chatBody.appendChild(div);
                scrollToBottom();
                return div.id;
            }

            function autoLogFood(name, calories, protein, carbs, fat, imagePath) {
                document.getElementById('food_item').value = name;
                document.getElementById('calories').value = calories;
                if (protein !== undefined && protein !== null) document.getElementById('protein').value = protein;
                if (carbs   !== undefined && carbs   !== null) document.getElementById('carbs').value   = carbs;
                if (fat     !== undefined && fat     !== null) document.getElementById('fat').value     = fat;
                if (imagePath !== undefined && imagePath !== null) {
                    document.getElementById('image_path').value = imagePath;
                } else {
                    document.getElementById('image_path').value = '';
                }

                // Đóng chat để hiện form nếu trên mobile
                if (window.innerWidth <= 480) {
                    toggleChat();
                } else {
                    // Trên desktop có thể giữ chat mở hoặc đóng tùy ý, ở đây mình đóng cho gọn
                    toggleChat();
                }

                // Cuộn tới form
                setTimeout(() => {
                    document.querySelector('.intake-form-card').scrollIntoView({ behavior: 'smooth' });
                }, 300);

                // Hiệu ứng nháy form
                const formCard = document.querySelector('.intake-form-card');
                formCard.style.transition = "box-shadow 0.5s";
                formCard.style.boxShadow = "0 0 20px rgba(74, 126, 227, 0.5)";
                setTimeout(() => { formCard.style.boxShadow = ""; }, 1500);
            }
        </script>
        <?php endif; ?>


    <?php include PROJECT_ROOT . 'views/footer.php'; ?>

    <script>
        // Holds the Chart.js donut instance once initialised.
        let macrosDonutChart = null;

        function macrosGrams(macros) {
            const p = parseFloat(macros.protein || 0);
            const c = parseFloat(macros.carbs   || 0);
            const f = parseFloat(macros.fat     || 0);
            return { p, c, f, total: p + c + f };
        }

        function refreshMacrosDonut(macros) {
            if (!macrosDonutChart) return;
            const g = macrosGrams(macros);
            macrosDonutChart.data.datasets[0].data = [g.p, g.c, g.f];
            macrosDonutChart.update('none');
            const label = document.getElementById('donutKcalLabel');
            if (label) label.textContent = Math.round(g.total);
        }

        // Update the Macros Today widget when totals change (called by submit/edit/delete).
        function updateMacrosWidget(macros, goals) {
            if (!macros) return;
            ['protein', 'carbs', 'fat'].forEach(key => {
                const cur  = parseFloat(macros[key] || 0);
                const goal = goals ? parseInt(goals[key] || 0, 10) : 0;
                const valEl  = document.getElementById('macroVal_'  + key);
                const goalEl = document.getElementById('macroGoal_' + key);
                const fillEl = document.getElementById('macroFill_' + key);
                if (valEl)  valEl.textContent  = Number.isInteger(cur) ? cur : cur.toFixed(1).replace(/\.?0+$/, '');
                if (goalEl) goalEl.textContent = goal > 0 ? goal : '–';
                if (fillEl) {
                    const pct = goal > 0 ? Math.min(100, Math.round(cur / goal * 100)) : 0;
                    fillEl.style.width = pct + '%';
                }
            });
            // Sync chart
            refreshMacrosDonut(macros);
        }

        // Build the Macros donut once DOM is ready.
        document.addEventListener('DOMContentLoaded', () => {
            const canvas = document.getElementById('macrosDonut');
            if (!canvas || typeof Chart === 'undefined') return;
            const state = window.__macroState || { protein: 0, carbs: 0, fat: 0 };
            const g = macrosGrams(state);
            const rootStyles = getComputedStyle(document.documentElement);
            const macroColors = {
                protein: (rootStyles.getPropertyValue('--color-primary') || '#58CC02').trim(),
                carbs: (rootStyles.getPropertyValue('--color-secondary') || '#1CB0F6').trim(),
                fat: (rootStyles.getPropertyValue('--color-accent') || '#FF9600').trim()
            };
            macrosDonutChart = new Chart(canvas.getContext('2d'), {
                type: 'doughnut',
                data: {
                    labels: ['Protein', 'Carbs', 'Fat'],
                    datasets: [{
                        data: [g.p, g.c, g.f],
                        backgroundColor: [macroColors.protein, macroColors.carbs, macroColors.fat],
                        borderWidth: 2,
                        borderColor: (rootStyles.getPropertyValue('--color-surface') || '#ffffff').trim(),
                        hoverOffset: 6
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    cutout: '72%',
                    plugins: {
                        legend: { display: false },
                        tooltip: {
                            callbacks: {
                                label: (ctx) => {
                                    const total = ctx.dataset.data.reduce((a, b) => a + b, 0);
                                    const val = ctx.parsed || 0;
                                    const pct = total > 0 ? Math.round(val / total * 100) : 0;
                                    const valDisp = Number.isInteger(val) ? val : val.toFixed(1).replace(/\.?0+$/, '');
                                    return ` ${ctx.label}: ${valDisp}g (${pct}%)`;
                                }
                            }
                        }
                    }
                }
            });
            const label = document.getElementById('donutKcalLabel');
            if (label) label.textContent = Math.round(g.total);
        });

        document.addEventListener('DOMContentLoaded', () => {
            // Animation for progress bar
            setTimeout(() => {
                const fill = document.getElementById('progressFill');
                if (fill) fill.style.width = '<?php echo $progressPercentage; ?>%';
            }, 100);

            const form = document.getElementById('intakeForm');
            const body = document.getElementById('intakeTableBody');
            const totalDisplay = document.getElementById('totalDisplay');
            const progressFill = document.getElementById('progressFill');
            const unitToggle = document.getElementById('unit_toggle');
            const calorieLabel = document.getElementById('calorieLabel');
            const pctLabel = document.querySelector('.pct-label');
            const __intakeEmptyTodayText = <?php echo json_encode(t_raw('intake.empty_today')); ?>;

            // Toggle Unit Label
            if (unitToggle && calorieLabel) {
                unitToggle.addEventListener('change', () => {
                    calorieLabel.textContent = unitToggle.value === 'kj' ? 'Kilojoules' : 'Calories';
                });
            }

            // --- Form Submit ---
            if (form) {
                form.addEventListener('submit', async e => {
                    e.preventDefault();

                    // Button Loading State
                    const btn = form.querySelector('button[type="submit"]');
                    const originalBtnText = btn.innerHTML;
                    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Saving...';
                    btn.disabled = true;

                    const fd = new FormData(form);

                    // Convert kJ -> Cal if needed
                    if (unitToggle && unitToggle.value === 'kj') {
                        const kj = parseFloat(fd.get('calories'));
                        const cal = kj / 4.184;
                        fd.set('calories', Math.round(cal));
                    }

                    try {
                        const res = await fetch(form.action, {
                            method: 'POST',
                            headers: { 'X-Requested-With': 'fetch' },
                            body: fd
                        });
                        const data = await res.json();

                        if (data.ok) {
                            // Remove empty row if exists
                            const noRow = document.getElementById('noIntakeRow');
                            if (noRow) noRow.remove();

                            // Insert new row styling
                            body.insertAdjacentHTML('afterbegin', data.new_row);

                            // Convert [data-iso] time cell of the newly inserted row to local TZ
                            // ("Just now" placeholder → "14:30" in visitor's local time)
                            const newRow = body.firstElementChild;
                            if (window.applyLocalTime) window.applyLocalTime(newRow);

                            // Update Totals
                            totalDisplay.textContent = data.total;
                            progressFill.style.width = data.percentage + '%';
                            if (pctLabel) pctLabel.textContent = Math.round(data.percentage) + '%';

                            // Update Macros widget
                            updateMacrosWidget(data.macros, data.macro_goals);

                            // Toast notification (matches Adjust Goal UX)
                            const foodName = fd.get('food_item');
                            const cal = fd.get('calories');
                            const xpAdded = data.xp && data.xp.added;
                            let subtext = foodName + ' • ' + cal + ' kcal' + (xpAdded ? ' • +' + xpAdded + ' XP' : '');
                            // Backdated log: spell out the target day so it's never mistaken for today.
                            if (data && data.is_today === false) {
                                subtext += ' • → ' + (form.dataset.dateLabel || data.date);
                            }
                            showLoggingToast('Food logged!', subtext);

                            // Update XP chip + fire +XP popup + level-up toast if needed
                            if (data.xp) {
                                if (data.xp.added && window.showXpPopup) {
                                    // Anchor at the submit button so the popup rises
                                    // from where the user just tapped — strongest dopamine cue.
                                    window.showXpPopup(data.xp.added, btn);
                                }
                                if (data.xp.summary && window.updateXpChip) {
                                    // Slight delay so the chip pulses AFTER the popup spawns
                                    setTimeout(() => window.updateXpChip(data.xp.summary), 200);
                                }
                                if (data.xp.levelup && window.showLevelUpToast) {
                                    setTimeout(() => window.showLevelUpToast(data.xp.levelup), 600);
                                }
                            }

                            form.reset();
                        } else {
                            alert(data.error || 'An error occurred');
                        }
                    } catch (err) {
                        console.error(err);
                        alert('Connection error');
                    } finally {
                        btn.innerHTML = originalBtnText;
                        btn.disabled = false;
                    }
                });
            }

            // --- Delete Action ---
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

                // Close modal if clicking overlay background
                confirmDeleteModal.addEventListener('click', e => {
                    if (e.target === confirmDeleteModal) {
                        closeDeleteConfirmModal();
                    }
                });
            }

            if (body) {
                body.addEventListener('click', async e => {
                    // 1. Handle Delete Action
                    const deleteBtn = e.target.closest('.deleteBtn');
                    if (deleteBtn) {
                        deleteRowTarget = deleteBtn.closest('tr');
                        if (confirmDeleteModal) {
                            confirmDeleteModal.classList.add('active');
                        }
                        return;
                    }

                    // 2. Handle Quick Log / Clone Action
                    const logAgainBtn = e.target.closest('.btnLogAgain');
                    if (logAgainBtn) {
                        const row = logAgainBtn.closest('tr');
                        const id = row.dataset.id;

                        // Loading state
                        logAgainBtn.disabled = true;
                        const originalHtml = logAgainBtn.innerHTML;
                        logAgainBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';

                        const fd = new FormData();
                        fd.append('intake_id', id);

                        try {
                            const res = await fetch('handlers/quick_log_from_history.php', {
                                method: 'POST',
                                headers: { 'X-Requested-With': 'fetch' },
                                body: fd
                            });
                            const data = await res.json();

                            if (data.ok) {
                                // Remove empty row placeholder if exists
                                const noRow = document.getElementById('noIntakeRow');
                                if (noRow) noRow.remove();

                                // Insert cloned row
                                body.insertAdjacentHTML('afterbegin', data.new_row);

                                // Convert timestamp to visitor's local TZ
                                const newRow = body.firstElementChild;
                                if (window.applyLocalTime) window.applyLocalTime(newRow);

                                // Update Totals display
                                totalDisplay.textContent = data.total;
                                progressFill.style.width = data.percentage + '%';
                                if (pctLabel) pctLabel.textContent = Math.round(data.percentage) + '%';

                                // Update Macros widget
                                updateMacrosWidget(data.macros, data.macro_goals);

                                // Toast notification
                                showLoggingToast('Food logged!', data.food_item + ' • ' + data.calories + ' kcal');

                                // Award XP dopamine cue
                                if (data.xp) {
                                    if (data.xp.added && window.showXpPopup) {
                                        window.showXpPopup(data.xp.added, logAgainBtn);
                                    }
                                    if (data.xp.summary && window.updateXpChip) {
                                        setTimeout(() => window.updateXpChip(data.xp.summary), 200);
                                    }
                                    if (data.xp.levelup && window.showLevelUpToast) {
                                        setTimeout(() => window.showLevelUpToast(data.xp.levelup), 600);
                                    }
                                }
                            } else {
                                alert(data.error || 'Failed to log entry');
                            }
                        } catch (err) {
                            console.error(err);
                            alert('Connection error');
                        } finally {
                            logAgainBtn.innerHTML = originalHtml;
                            logAgainBtn.disabled = false;
                        }
                    }
                });
            }

            // Meal photo modal trigger
            const viewPhotoModal = document.getElementById('viewPhotoModal');
            const viewPhotoImg = document.getElementById('viewPhotoImg');
            const closePhotoBtn = document.getElementById('closeViewPhotoModal');
            
            if (viewPhotoModal && closePhotoBtn) {
                closePhotoBtn.addEventListener('click', () => {
                    viewPhotoModal.classList.remove('active');
                });
                viewPhotoModal.addEventListener('click', e => {
                    if (e.target === viewPhotoModal) {
                        viewPhotoModal.classList.remove('active');
                    }
                });
            }

            // Event delegation for meal photo trigger in table body
            if (body) {
                body.addEventListener('click', e => {
                    const trigger = e.target.closest('.meal-photo-trigger');
                    if (trigger) {
                        const src = trigger.dataset.imgSrc;
                        if (src && viewPhotoModal && viewPhotoImg) {
                            viewPhotoImg.src = src;
                            viewPhotoModal.classList.add('active');
                        }
                    }
                });
            }

            if (doConfirmDeleteBtn) {
                doConfirmDeleteBtn.addEventListener('click', async () => {
                    if (!deleteRowTarget) return;

                    const row = deleteRowTarget;
                    const id = row.dataset.id;
                    const fd = new FormData();
                    fd.append('intake_id', id);

                    // Disable button during execution
                    doConfirmDeleteBtn.disabled = true;

                    try {
                        const res = await fetch('handlers/delete_intake.php', {
                            method: 'POST',
                            headers: { 'X-Requested-With': 'fetch' },
                            body: fd
                        });
                        const data = await res.json();

                        if (data.ok) {
                            // Grab food name from row BEFORE removal for the toast subtext
                            const deletedFood = row.querySelector('.intake-food-cell, td[data-label="Food"]')?.innerText.trim() || '';

                            row.style.opacity = '0';
                            setTimeout(() => {
                                row.remove();
                                if (body.querySelectorAll('tr').length === 0) {
                                    const emptyRowHtml = `
                                        <tr id="noIntakeRow">
                                            <td colspan="6" class="empty-state">
                                                <i class="fas fa-drumstick-bite"></i> ${__intakeEmptyTodayText}
                                            </td>
                                        </tr>
                                    `;
                                    body.insertAdjacentHTML('beforeend', emptyRowHtml);
                                }
                            }, 300);

                            totalDisplay.textContent = data.total;
                            progressFill.style.width = data.percentage + '%';
                            if (pctLabel) pctLabel.textContent = Math.round(data.percentage) + '%';

                            updateMacrosWidget(data.macros, data.macro_goals);

                            // Toast notification
                            showLoggingToast('Entry deleted', deletedFood);

                            closeDeleteConfirmModal();
                        } else {
                            alert(data.error);
                        }
                    } catch (err) {
                        alert('Connection error');
                    } finally {
                        doConfirmDeleteBtn.disabled = false;
                    }
                });
            }
        });

    // Switcher logic for Nutrition Hub
    function switchNutritionTab(tabId) {
        // Remove active class from all tab buttons
        document.querySelectorAll('#nutritionHubCard .tab-btn').forEach(btn => btn.classList.remove('active'));
        // Remove active class from all tab panes
        document.querySelectorAll('#nutritionHubCard .chart-wrapper-tab').forEach(pane => pane.classList.remove('active'));

        // Add active class to clicked button
        const btn = Array.from(document.querySelectorAll('#nutritionHubCard .tab-btn')).find(b => b.getAttribute('onclick').includes(tabId));
        if (btn) btn.classList.add('active');

        // Add active class to corresponding tab pane
        const pane = document.getElementById(`tabPane-${tabId}`);
        if (pane) pane.classList.add('active');

        // Force Chart.js reflows immediately to correct zero-width layout issue
        window.dispatchEvent(new Event('resize'));
    }
    </script>

    <?php if ($isLoggedIn): ?>
        <?php $modalTitle = 'Edit Intake Entry'; include PROJECT_ROOT . 'dashboard/views/_edit-intake-modal.php'; ?>
        <?php include PROJECT_ROOT . 'dashboard/views/_confirm-delete-modal.php'; ?>
        <?php include PROJECT_ROOT . 'dashboard/views/_view-photo-modal.php'; ?>
        <?php include PROJECT_ROOT . 'dashboard/views/_intake-row-js.php'; ?>
        <script>
            // Intake page edit wiring — IntakeRow handles row reading + form filling
            // + row patching; this page also updates the progress bar, macros widget,
            // and shows a toast (history page skips those).
            (function () {
                IntakeRow.bindCloseHandlers();

                // Open: event delegation on body so AJAX-inserted rows work too
                document.body.addEventListener('click', e => {
                    const btn = e.target.closest('.btn-edit');
                    if (!btn) return;
                    const row = btn.closest('tr');
                    if (!row) return;
                    IntakeRow.fillEditForm(row);
                    IntakeRow.openModal();
                });

                // Submit
                const editForm = document.getElementById('editIntakeForm');
                editForm.addEventListener('submit', async e => {
                    e.preventDefault();
                    const fd = new FormData(editForm);
                    try {
                        const res = await fetch('handlers/edit_intake.php', { method: 'POST', body: fd });
                        const data = await res.json();
                        if (!data.ok) {
                            alert(data.error || 'Update failed');
                            return;
                        }

                        // 1. Patch the row
                        const row = document.querySelector(`tr[data-id="${data.intake_id || fd.get('intake_id')}"]`);
                        if (row) IntakeRow.updateRow(row, data);

                        // 2. Update progress bar + total (intake-specific)
                        if (data.total_calories !== undefined) {
                            const totalDisplay = document.getElementById('totalDisplay');
                            if (totalDisplay) totalDisplay.innerText = data.total_calories;
                            const progressFill = document.getElementById('progressFill');
                            if (progressFill) progressFill.style.width = data.percentage + '%';
                            const pctLabel = document.querySelector('.pct-label');
                            if (pctLabel) pctLabel.innerText = data.percentage + '%';
                        }

                        // 3. Update Macros widget
                        if (typeof updateMacrosWidget === 'function') {
                            updateMacrosWidget(data.macros, data.macro_goals);
                        }

                        // 4. Toast
                        const editedFood = data.food_item || fd.get('food_item');
                        const editedCal  = data.calories  || fd.get('calories');
                        if (typeof showLoggingToast === 'function') {
                            showLoggingToast('Entry updated', editedFood + ' • ' + editedCal + ' kcal');
                        }

                        IntakeRow.closeModal();
                    } catch (err) {
                        console.error(err);
                        alert('Error connecting to server');
                    }
                });
            })();
        </script>

        <?php include PROJECT_ROOT . 'dashboard/views/logging-toast.php'; ?>
        <?php include PROJECT_ROOT . 'dashboard/views/local-time-script.php'; ?>
        <?php if (!empty($myTrainer)): ?>
            <script src="<?= BASE_URL ?>js/pt-chat.js"></script>
        <?php endif; ?>
    <?php endif; ?>
</body>

</html>
