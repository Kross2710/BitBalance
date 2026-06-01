<?php
require_once __DIR__ . '/../include/init.php';
require_once __DIR__ . '/handlers/dashboard_data.php';
require_once __DIR__ . '/../include/handlers/log_attempt.php';
require_once __DIR__ . '/../include/csrf.php';

// Locale for the handful of inline VI/EN strings on this page (PT feedback card,
// trainer chat). Most copy uses t(); these few predate that, so expose $lang.
$lang = current_locale();

if ($isLoggedIn) {
    log_attempt($pdo, $user['user_id'], 'view', 'User ' . $user['user_id'] . ' clicked on dashboard food', 'dashboard', null);
    // PT feedback + two-way chat moved to the dedicated "My Trainer" page
    // (dashboard-coach.php) to keep the Intake page focused on logging.
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
    $pageCss = ['css/dashboard.css', 'css/components/intake-list.css', 'css/pages/dashboard-intake.css'];
    include PROJECT_ROOT . 'views/head_css.php';
    ?>

    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="https://kit.fontawesome.com/b94f65ead2.js" crossorigin="anonymous"></script>
    <!-- ZXing: fallback 1D barcode decoder for browsers without native BarcodeDetector (e.g. iOS Safari) -->
    <script src="https://cdn.jsdelivr.net/npm/@zxing/library@0.21.3/umd/index.min.js" type="text/javascript"></script>
    <script src="<?= BASE_URL ?>js/image-compress.js?v=<?= @filemtime(PROJECT_ROOT . 'js/image-compress.js') ?>"></script>
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
                                        required autocomplete="off" role="combobox" aria-autocomplete="list"
                                        aria-expanded="false" aria-controls="foodSuggest">
                                    <button type="button" class="btn-inline-scan" id="openScannerInline" title="<?= t('intake.scan_barcode_inline') ?>">
                                        <i class="fas fa-barcode"></i>
                                    </button>
                                    <ul class="food-suggest" id="foodSuggest" role="listbox"
                                        aria-label="<?= t('intake.food_name') ?>" hidden></ul>
                                </div>
                                <div class="food-chips" id="foodChips" hidden></div>
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

                        <script>
                        // Food suggestions from the user's own history (no master food DB).
                        // Autocomplete-as-you-type + "recent items" quick chips. Picking one
                        // pre-fills calories + macros the way the user usually logs that food.
                        (function () {
                            const input = document.getElementById('food_item');
                            const list = document.getElementById('foodSuggest');
                            const chips = document.getElementById('foodChips');
                            if (!input || !list) return;

                            const ENDPOINT = '<?= BASE_URL ?>api/intake/suggest.php';
                            const CHIPS_HEADING = <?= json_encode(t('intake.recent.heading'), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
                            const fmt = (n) => (Math.round(n * 10) / 10).toString();
                            let items = [];
                            let active = -1;
                            let debounce = null;
                            let skipNextInput = false;

                            function setField(id, val) {
                                const el = document.getElementById(id);
                                if (el) el.value = (val === 0 || val) ? val : '';
                            }

                            function fill(item) {
                                skipNextInput = true;
                                input.value = item.food_item;
                                setField('calories', item.calories);
                                setField('protein', item.protein);
                                setField('carbs', item.carbs);
                                setField('fat', item.fat);
                                close();
                                hideChips();
                                const cat = document.getElementById('meal_category');
                                if (cat && !cat.value) cat.focus();
                            }

                            function close() {
                                list.hidden = true;
                                list.innerHTML = '';
                                active = -1;
                                input.setAttribute('aria-expanded', 'false');
                            }

                            function render() {
                                list.innerHTML = '';
                                if (!items.length) { close(); return; }
                                items.forEach((it, i) => {
                                    const li = document.createElement('li');
                                    li.className = 'food-suggest__item';
                                    li.setAttribute('role', 'option');
                                    li.id = 'foodSuggest-' + i;
                                    li.innerHTML =
                                        '<span class="food-suggest__name"></span>'
                                        + '<span class="food-suggest__meta">' + it.calories + ' kcal · P' + fmt(it.protein) + ' C' + fmt(it.carbs) + ' F' + fmt(it.fat) + '</span>';
                                    li.querySelector('.food-suggest__name').textContent = it.food_item;
                                    li.addEventListener('mousedown', (e) => { e.preventDefault(); fill(it); });
                                    list.appendChild(li);
                                });
                                list.hidden = false;
                                input.setAttribute('aria-expanded', 'true');
                            }

                            function highlight() {
                                Array.from(list.children).forEach((li, i) => {
                                    li.classList.toggle('is-active', i === active);
                                    if (i === active) li.scrollIntoView({ block: 'nearest' });
                                });
                                input.setAttribute('aria-activedescendant', active >= 0 ? 'foodSuggest-' + active : '');
                            }

                            function fetchSuggest(q) {
                                return fetch(ENDPOINT + '?q=' + encodeURIComponent(q), { headers: { 'X-Requested-With': 'fetch' } })
                                    .then(r => r.ok ? r.json() : null)
                                    .then(d => (d && d.ok && d.data && d.data.items) ? d.data.items : [])
                                    .catch(() => []);
                            }

                            // ---- Autocomplete ----
                            input.addEventListener('input', () => {
                                if (skipNextInput) { skipNextInput = false; return; }
                                const q = input.value.trim();
                                if (debounce) clearTimeout(debounce);
                                if (q.length < 1) { close(); showChips(); return; }
                                hideChips();
                                debounce = setTimeout(() => {
                                    fetchSuggest(q).then(res => {
                                        if (input.value.trim() !== q) return; // stale response
                                        items = res;
                                        render();
                                    });
                                }, 200);
                            });

                            input.addEventListener('keydown', (e) => {
                                if (list.hidden) return;
                                if (e.key === 'ArrowDown') { e.preventDefault(); active = Math.min(active + 1, items.length - 1); highlight(); }
                                else if (e.key === 'ArrowUp') { e.preventDefault(); active = Math.max(active - 1, 0); highlight(); }
                                else if (e.key === 'Enter' && active >= 0) { e.preventDefault(); fill(items[active]); }
                                else if (e.key === 'Escape') { close(); }
                            });

                            input.addEventListener('blur', () => { setTimeout(close, 120); });
                            input.addEventListener('focus', () => { if (!input.value.trim()) showChips(); });

                            // ---- Recent chips ----
                            function hideChips() { if (chips) chips.hidden = true; }
                            function showChips() { if (chips && chips.dataset.ready === '1' && chips.children.length) chips.hidden = false; }

                            function buildChips() {
                                if (!chips) return;
                                fetchSuggest('').then(res => {
                                    chips.innerHTML = '';
                                    if (!res.length) { chips.dataset.ready = '1'; return; }
                                    const head = document.createElement('span');
                                    head.className = 'food-chips__heading';
                                    head.textContent = CHIPS_HEADING;
                                    chips.appendChild(head);
                                    res.slice(0, 6).forEach(it => {
                                        const b = document.createElement('button');
                                        b.type = 'button';
                                        b.className = 'food-chip';
                                        b.textContent = it.food_item;
                                        b.title = it.calories + ' kcal';
                                        b.addEventListener('click', () => fill(it));
                                        chips.appendChild(b);
                                    });
                                    chips.dataset.ready = '1';
                                    if (!input.value.trim()) chips.hidden = false;
                                });
                            }
                            buildChips();
                        })();
                        </script>
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

            let scanning      = false;
            let lastCode      = null;
            let lastCodeTime  = 0;
            let currentProduct = null; // last successful lookup

            // ---- decoder state (we own ONE camera stream; one backend decodes it) ----
            let videoEl        = null;  // <video> injected into #scannerReader on start
            let mediaStream    = null;  // the single getUserMedia stream
            let nativeDetector = null;  // window.BarcodeDetector instance (Android Chrome)
            let zxingReader    = null;  // ZXing.BrowserMultiFormatReader (iOS Safari fallback)
            let scanTimer      = null;  // setTimeout handle for the decode loop
            let scanCanvas     = null;  // offscreen canvas for ZXing frame grabs
            let scanCtx        = null;
            let activeBackend  = null;  // 'native' | 'zxing'
            let frameCount     = 0;
            let debugEl        = null;  // on-screen diagnostics (temporary)

            const SCAN_FORMATS_NATIVE = ['ean_13', 'ean_8', 'upc_a', 'upc_e'];
            // Request a high-res rear camera: sharper frames decode 1D barcodes far
            // more reliably. Matters most on iOS Safari, which has no BarcodeDetector
            // and must lean on ZXing — low-res frames are the #1 cause of missed scans.
            const VIDEO_CONSTRAINTS = {
                audio: false,
                video: {
                    facingMode: { ideal: 'environment' },
                    width:  { ideal: 1920 },
                    height: { ideal: 1080 }
                }
            };
            const SCAN_INTERVAL_MS = 120; // ~8 decode attempts/sec — responsive, not CPU-bound

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
            startBtn.addEventListener('click', startScanner);
            stopBtn.addEventListener('click', stopScanner);

            async function startScanner() {
                if (scanning) return;

                // Pick the backend up front so we can fail fast if neither is usable.
                activeBackend = (await useNativeDetector()) ? 'native' : 'zxing';
                if (activeBackend === 'zxing' && typeof ZXing === 'undefined') {
                    showToast(<?= json_encode(t_raw('intake.scanner.lib_failed')) ?>, { type: 'error' });
                    return;
                }

                // Inject a fresh <video> (+ aiming guide + debug line) now, so the CSS
                // :empty placeholder stays visible until the camera actually starts.
                const reader = document.getElementById('scannerReader');
                videoEl = document.createElement('video');
                videoEl.setAttribute('playsinline', '');   // iOS: stay inline instead of going fullscreen
                videoEl.setAttribute('autoplay', '');
                videoEl.setAttribute('muted', '');
                videoEl.muted = true;
                reader.appendChild(videoEl);
                reader.insertAdjacentHTML('beforeend',
                    '<div class="scanner-guide"></div><div class="scanner-debug">…</div>');
                debugEl = reader.querySelector('.scanner-debug');

                try {
                    // We own the stream for BOTH backends — no library lifecycle surprises.
                    mediaStream = await navigator.mediaDevices.getUserMedia(VIDEO_CONSTRAINTS);
                    videoEl.srcObject = mediaStream;
                    await videoEl.play();
                    await waitForVideoReady(videoEl);

                    if (activeBackend === 'zxing') setupZxing();

                    scanning = true;
                    frameCount = 0;
                    startBtn.style.display = 'none';
                    stopBtn.style.display = 'block';
                    scanLoop();
                } catch (err) {
                    await teardown();
                    showToast("Could not access camera: " + ((err && err.message) || err) +
                          "\n\nMake sure you allowed camera access and are on HTTPS or localhost.", { type: 'error' });
                }
            }

            // Native BarcodeDetector is preferred when present (hardware-accelerated,
            // very reliable on 1D). Available on Android Chrome; absent on iOS Safari.
            async function useNativeDetector() {
                if (!('BarcodeDetector' in window)) return false;
                try {
                    const supported = await window.BarcodeDetector.getSupportedFormats();
                    const formats = SCAN_FORMATS_NATIVE.filter(f => supported.includes(f));
                    if (!formats.length) return false;
                    nativeDetector = new window.BarcodeDetector({ formats });
                    return true;
                } catch (e) {
                    return false;
                }
            }

            function setupZxing() {
                const hints = new Map();
                hints.set(ZXing.DecodeHintType.POSSIBLE_FORMATS, [
                    ZXing.BarcodeFormat.EAN_13,
                    ZXing.BarcodeFormat.EAN_8,
                    ZXing.BarcodeFormat.UPC_A,
                    ZXing.BarcodeFormat.UPC_E
                ]);
                hints.set(ZXing.DecodeHintType.TRY_HARDER, true); // spend more effort per frame on 1D
                zxingReader = new ZXing.BrowserMultiFormatReader(hints, 100);
                scanCanvas = document.createElement('canvas');
                scanCtx = scanCanvas.getContext('2d', { willReadFrequently: true });
            }

            // iOS Safari can report videoWidth === 0 for a beat after play(); decoding
            // a 0-sized frame never succeeds, so wait for real dimensions first.
            function waitForVideoReady(v) {
                return new Promise((resolve) => {
                    if (v.videoWidth > 0) return resolve();
                    const done = () => {
                        v.removeEventListener('loadedmetadata', done);
                        v.removeEventListener('playing', done);
                        resolve();
                    };
                    v.addEventListener('loadedmetadata', done);
                    v.addEventListener('playing', done);
                    setTimeout(done, 1500); // safety net
                });
            }

            // Single decode loop, throttled, driving whichever backend is active.
            async function scanLoop() {
                if (!scanning || !videoEl) return;
                frameCount++;
                const dims = videoEl.videoWidth + '×' + videoEl.videoHeight;
                try {
                    if (activeBackend === 'native') {
                        const codes = await nativeDetector.detect(videoEl);
                        setDebug('native · #' + frameCount + ' · ' + dims);
                        if (codes && codes.length) onScanSuccess(codes[0].rawValue);
                    } else {
                        const text = decodeZxingFrame();
                        setDebug('zxing · #' + frameCount + ' · ' + dims + (text ? ' · HIT' : ''));
                        if (text) onScanSuccess(text);
                    }
                } catch (e) {
                    setDebug(activeBackend + ' · #' + frameCount + ' · ' + dims + ' · ' + ((e && e.name) || 'err'));
                }
                if (scanning) scanTimer = setTimeout(scanLoop, SCAN_INTERVAL_MS);
            }

            // Grab the center band of the current video frame and run ZXing on it.
            // Cropping to the guide region speeds decoding and ignores background
            // clutter (logos, table, hands) that throws off the 1D reader.
            function decodeZxingFrame() {
                const vw = videoEl.videoWidth, vh = videoEl.videoHeight;
                if (!vw || !vh) return null;
                const cw = Math.floor(vw * 0.85), ch = Math.floor(vh * 0.5);
                const sx = Math.floor((vw - cw) / 2), sy = Math.floor((vh - ch) / 2);
                scanCanvas.width = cw; scanCanvas.height = ch;
                scanCtx.drawImage(videoEl, sx, sy, cw, ch, 0, 0, cw, ch);
                const source = new ZXing.HTMLCanvasElementLuminanceSource(scanCanvas);
                const bitmap = new ZXing.BinaryBitmap(new ZXing.HybridBinarizer(source));
                try {
                    const result = zxingReader.decodeBitmap(bitmap);
                    return result ? result.getText() : null;
                } catch (e) {
                    return null; // NotFoundException — no barcode in this frame
                }
            }

            function setDebug(msg) { if (debugEl) debugEl.textContent = msg; }

            async function stopScanner() {
                await teardown();
                startBtn.style.display = 'block';
                stopBtn.style.display = 'none';
            }

            // Tear down the loop + release the single camera stream.
            async function teardown() {
                scanning = false;
                if (scanTimer !== null) { clearTimeout(scanTimer); scanTimer = null; }
                nativeDetector = null;
                if (zxingReader) { try { zxingReader.reset(); } catch (e) {} zxingReader = null; }
                if (mediaStream) { mediaStream.getTracks().forEach(t => t.stop()); mediaStream = null; }
                if (videoEl) {
                    try { videoEl.pause(); } catch (e) {}
                    videoEl.srcObject = null;
                    videoEl = null;
                }
                scanCanvas = null; scanCtx = null; debugEl = null;
                const reader = document.getElementById('scannerReader');
                if (reader) reader.innerHTML = ''; // restores the :empty placeholder
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
            let msgSeq = 0; // ensures unique message IDs even within the same millisecond

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

            // Image compression is shared with the AI Coach composer — see
            // js/image-compress.js (loaded in the page <head>). It downscales +
            // re-encodes to a clean sRGB JPEG; we still cap the *result* size.
            const CHAT_IMG_MAX_BYTES = 5 * 1024 * 1024;

            async function handleImageUpload(input) {
                if (!input.files || !input.files[0]) return;
                const raw = input.files[0];
                try {
                    const processed = await BitBalanceImage.compressImage(raw, { filename: 'meal.jpg' });
                    if (processed.size > CHAT_IMG_MAX_BYTES) {
                        addMessage('Image is too large (max 5MB after compression). Please choose another.', 'bot');
                        currentImageFile = null;
                        input.value = '';
                        return;
                    }
                    currentImageFile = processed;
                    const reader = new FileReader();
                    reader.onload = function (e) {
                        addMessage(`<img src="${e.target.result}" style="max-width:100px; border-radius:8px; display:block; margin-bottom:5px;"> <em>Image selected. Type a message or hit send.</em>`, 'user');
                    }
                    reader.readAsDataURL(processed);
                } catch (err) {
                    addMessage("Couldn't read that image. Please try another.", 'bot');
                    currentImageFile = null;
                    input.value = '';
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
                div.id = 'msg-' + Date.now() + '-' + (++msgSeq);
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

                // Gợi ý chọn bữa: AI chỉ điền món + macro, người dùng vẫn phải chọn bữa.
                promptMealSelection();
            }

            // Đoán bữa theo giờ hiện tại để gợi ý sẵn.
            function suggestMealByTime() {
                const h = new Date().getHours();
                if (h >= 4  && h < 10) return 'breakfast';
                if (h >= 10 && h < 14) return 'lunch';
                if (h >= 17 && h < 22) return 'dinner';
                return 'snack';
            }

            // Sau khi "Add to Log", làm nổi bật ô chọn bữa và đặt sẵn gợi ý theo giờ
            // để người dùng xác nhận hoặc đổi trước khi lưu.
            function promptMealSelection() {
                const select = document.getElementById('meal_category');
                if (!select) return;

                // Chỉ điền gợi ý khi người dùng chưa tự chọn bữa.
                if (!select.value) select.value = suggestMealByTime();

                const wrapper = select.closest('.select-wrapper');
                setTimeout(() => {
                    if (wrapper) wrapper.classList.add('meal-needs-pick');
                    select.focus({ preventScroll: true });
                }, 450);

                // Tắt hiệu ứng khi người dùng đã chọn, hoặc sau vài giây.
                const clear = () => {
                    if (wrapper) wrapper.classList.remove('meal-needs-pick');
                    select.removeEventListener('change', clear);
                };
                select.addEventListener('change', clear);
                setTimeout(clear, 5000);
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
                            showToast(data.error || 'An error occurred', { type: 'error' });
                        }
                    } catch (err) {
                        console.error(err);
                        showToast('Connection error', { type: 'error' });
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
                                showToast(data.error || 'Failed to log entry', { type: 'error' });
                            }
                        } catch (err) {
                            console.error(err);
                            showToast('Connection error', { type: 'error' });
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

            const __delI18n = {
                deleted: <?= json_encode($lang === 'vi' ? 'Đã xoá' : 'Entry deleted', JSON_UNESCAPED_UNICODE) ?>,
                undo: <?= json_encode($lang === 'vi' ? 'Hoàn tác' : 'Undo', JSON_UNESCAPED_UNICODE) ?>,
                restored: <?= json_encode($lang === 'vi' ? 'Đã khôi phục mục vừa xoá' : 'Entry restored', JSON_UNESCAPED_UNICODE) ?>,
                restoreFail: <?= json_encode($lang === 'vi' ? 'Không thể khôi phục' : 'Could not restore', JSON_UNESCAPED_UNICODE) ?>,
                conn: <?= json_encode($lang === 'vi' ? 'Lỗi kết nối' : 'Connection error', JSON_UNESCAPED_UNICODE) ?>
            };

            // Re-insert a just-deleted entry (Undo). Re-renders the row with its
            // original date/time and reverts the daily totals.
            async function undoDeleteEntry(snapshot) {
                try {
                    const fd = new FormData();
                    fd.append('food_item', snapshot.food_item || '');
                    fd.append('calories', snapshot.calories || 0);
                    fd.append('protein', snapshot.protein || 0);
                    fd.append('carbs', snapshot.carbs || 0);
                    fd.append('fat', snapshot.fat || 0);
                    fd.append('meal_category', snapshot.meal_category || '');
                    if (snapshot.image_path) fd.append('image_path', snapshot.image_path);
                    fd.append('date_intake', snapshot.date_intake || '');
                    const res = await fetch('handlers/restore_intake.php', {
                        method: 'POST',
                        headers: { 'X-Requested-With': 'fetch' },
                        body: fd
                    });
                    const data = await res.json();
                    if (data.ok) {
                        const noRow = document.getElementById('noIntakeRow');
                        if (noRow) noRow.remove();
                        body.insertAdjacentHTML('afterbegin', data.new_row);
                        const newRow = body.firstElementChild;
                        if (window.applyLocalTime) window.applyLocalTime(newRow);
                        totalDisplay.textContent = data.total;
                        progressFill.style.width = data.percentage + '%';
                        if (pctLabel) pctLabel.textContent = Math.round(data.percentage) + '%';
                        updateMacrosWidget(data.macros, data.macro_goals);
                        showToast(__delI18n.restored, { type: 'success' });
                    } else {
                        showToast(data.error || __delI18n.restoreFail, { type: 'error' });
                    }
                } catch (err) {
                    showToast(__delI18n.conn, { type: 'error' });
                }
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
                            const snapshot = data.deleted_row;

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

                            // Toast with an Undo action (re-inserts the row with its original time)
                            showToast(
                                __delI18n.deleted + (deletedFood ? ' · ' + deletedFood : ''),
                                {
                                    type: 'success',
                                    duration: 9000,
                                    action: snapshot ? { label: __delI18n.undo, onClick: () => undoDeleteEntry(snapshot) } : null
                                }
                            );

                            closeDeleteConfirmModal();
                        } else {
                            showToast(data.error, { type: 'error' });
                        }
                    } catch (err) {
                        showToast('Connection error', { type: 'error' });
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
                            showToast(data.error || 'Update failed', { type: 'error' });
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
                        showToast('Error connecting to server', { type: 'error' });
                    }
                });
            })();
        </script>

        <?php include PROJECT_ROOT . 'dashboard/views/logging-toast.php'; ?>
        <?php include PROJECT_ROOT . 'dashboard/views/local-time-script.php'; ?>
    <?php endif; ?>
</body>

</html>
