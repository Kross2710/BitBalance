<?php
require_once __DIR__ . '/../include/init.php';
require_once __DIR__ . '/handlers/dashboard_data.php';
require_once __DIR__ . '/../include/handlers/log_attempt.php';

if ($isLoggedIn) {
    log_attempt($pdo, $user['user_id'], 'view', 'User ' . $user['user_id'] . ' clicked on dashboard food', 'dashboard', null);
}

$activePage = 'intake';
$activeHeader = 'dashboard';
$bodyClass = 'page-intake';
$displayUser = $isLoggedIn ? $user['user_name'] : "Guest";

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
<html lang="en"
    data-theme="<?php echo isset($_SESSION['user']) ? ($_SESSION['user']['theme_preference'] ?? 'light') : 'light'; ?>">

<head>
    <meta charset="UTF-8">
    <meta name="viewport"
        content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no, interactive-widget=resizes-content">
    <title>Food Log | BitBalance</title>
    <?php
    // Intake IS the logging UI itself, so no quick-log FAB on this page.
    $pageComponents = ['sidebar'];
    $pageCss = ['css/dashboard.css', 'css/components/intake-list.css', 'css/pages/dashboard-intake.css'];
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

    <?php if ($isLoggedIn): ?>
        <?php include PROJECT_ROOT . 'dashboard/views/right-sidebar.php'; ?>

        <main class="dashboard-content">
            <div class="intake-container">

                <section class="progress-widget">
                    <div class="progress-card">
                        <div class="progress-card-content">
                            <div class="progress-header">
                                <h3>Today's Intake</h3>
                                <span class="status-badge <?php echo $statusClass; ?>"><?php echo $status; ?></span>
                            </div>

                            <div class="progress-value">
                                <span class="<?php echo $statusClass; ?>" id="totalDisplay"><?php echo $totalCalories; ?></span>
                                <small>calories</small>
                            </div>

                            <div class="progress-bar">
                                <div class="progress-fill <?php echo htmlspecialchars($statusClass); ?>" id="progressFill" style="width: 0%;"></div>
                            </div>

                            <div class="progress-labels">
                                <span>Goal: <strong><?php echo $userGoal ? number_format($userGoal) : 'Unset'; ?></strong></span>
                                <span class="pct-label"><?php echo $userGoal ? round(($totalCalories / $userGoal) * 100) . '%' : '0%'; ?></span>
                            </div>
                        </div>
                    </div>
                </section>

                <?php
                $macros = $macroTotals ?? ['protein' => 0, 'carbs' => 0, 'fat' => 0];
                $mGoals = $macroGoals  ?? ['protein' => 0, 'carbs' => 0, 'fat' => 0];
                $macroDefs = [
                    'protein' => ['label' => 'Protein', 'class' => 'p', 'icon' => 'fa-drumstick-bite'],
                    'carbs'   => ['label' => 'Carbs',   'class' => 'c', 'icon' => 'fa-bread-slice'],
                    'fat'     => ['label' => 'Fat',     'class' => 'f', 'icon' => 'fa-cheese'],
                ];
                ?>
                <section class="chart-section macros-widget meals-card">
                    <div class="card-header">
                        <h4><i class="fas fa-chart-pie"></i> Macros Today</h4>
                    </div>

                    <div class="doughnut-container">
                        <canvas id="macrosDonut"></canvas>
                        <div class="doughnut-center-text">
                            <span class="center-val" id="donutKcalLabel">0</span>
                            <span class="center-label">grams</span>
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
                                Set a calorie goal to see daily macro targets.
                            </p>
                        <?php endif; ?>
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

                <div class="content-split">
                    <section class="dashboard-card intake-form-card">
                        <div class="card-header">
                            <h3><i class="fas fa-plus-circle"></i> Log Food</h3>
                        </div>

                        <div id="alertPlaceholder">
                            <?php if (!empty($error_message)): ?>
                                <div class="alert error"><i class="fas fa-exclamation-triangle"></i>
                                    <?php echo $error_message; ?></div>
                            <?php endif; ?>
                            <?php /* Success now handled by logging-toast partial (slide-up toast). */ ?>
                        </div>

                        <div class="quick-actions-row">
                            <button type="button" class="quick-action-chip" id="openScannerChip">
                                <i class="fas fa-barcode"></i> Scan Barcode
                            </button>
                            <button type="button" class="quick-action-chip" onclick="toggleChat()">
                                <i class="fas fa-robot"></i> AI Photo
                            </button>
                        </div>

                        <form id="intakeForm" action="handlers/process_intake.php" method="POST">
                            <div class="form-group">
                                <label for="food_item">Food Name</label>
                                <div class="input-icon-wrapper food-name-wrapper">
                                    <i class="fas fa-utensils input-icon"></i>
                                    <input type="text" id="food_item" name="food_item" placeholder="e.g., Pho Bo, Apple..."
                                        required>
                                    <button type="button" class="btn-inline-scan" id="openScannerInline" title="Scan barcode">
                                        <i class="fas fa-barcode"></i>
                                    </button>
                                </div>
                            </div>

                            <div class="form-row-split">
                                <div class="form-group">
                                    <label for="calories" id="calorieLabel">Calories</label>
                                    <div class="input-icon-wrapper">
                                        <i class="fas fa-bolt input-icon"></i>
                                        <input type="number" id="calories" name="calories" placeholder="0" required>
                                    </div>
                                </div>
                                <div class="form-group">
                                    <label for="unit_toggle">Unit</label>
                                    <div class="select-wrapper">
                                        <select id="unit_toggle">
                                            <option value="cal">Cal (kcal)</option>
                                            <option value="kj">kJ</option>
                                        </select>
                                    </div>
                                </div>
                            </div>

                            <div class="form-group macros-input-group">
                                <label class="macros-input-label">Macros <small>(grams, optional)</small></label>
                                <div class="macros-input-row">
                                    <div class="macro-input p">
                                        <label for="protein" title="Protein (g)">P</label>
                                        <input type="number" id="protein" name="protein" min="0" max="999" step="0.1" placeholder="0">
                                    </div>
                                    <div class="macro-input c">
                                        <label for="carbs" title="Carbs (g)">C</label>
                                        <input type="number" id="carbs" name="carbs" min="0" max="999" step="0.1" placeholder="0">
                                    </div>
                                    <div class="macro-input f">
                                        <label for="fat" title="Fat (g)">F</label>
                                        <input type="number" id="fat" name="fat" min="0" max="999" step="0.1" placeholder="0">
                                    </div>
                                </div>
                            </div>

                            <div class="form-group">
                                <label for="meal_category">Category</label>
                                <div class="select-wrapper">
                                    <select id="meal_category" name="meal_category" required>
                                        <option value="" disabled selected>Select Category</option>
                                        <option value="breakfast">Breakfast 🌅</option>
                                        <option value="lunch">Lunch ☀️</option>
                                        <option value="dinner">Dinner 🌙</option>
                                        <option value="snack">Snack 🍪</option>
                                    </select>
                                </div>
                            </div>

                            <!-- <div class="ai-tip">
                                <i class="fas fa-robot"></i>
                                <span>Not sure? Ask <a href="https://chat.openai.com/" target="_blank">ChatGPT</a> to
                                    estimate.</span>
                            </div> -->
                            <div class="ai-tip">
                                <i class="fas fa-robot"></i>
                                <span>Not sure? Ask our BitBalance AI</a> to
                                    estimate.</span>
                            </div>

                            <button type="submit" class="btn-submit">
                                <i class="fas fa-check"></i> Log Entry
                            </button>
                        </form>
                    </section>

                    <section class="dashboard-card intake-list-card">
                        <div class="card-header">
                            <h3><i class="fas fa-list-ul"></i> Today's History</h3>
                        </div>

                        <div class="table-responsive">
                            <table class="modern-table">
                                <thead>
                                    <tr>
                                        <th>Food Item</th>
                                        <th>Calories</th>
                                        <th>Macros (g)</th>
                                        <th>Category</th>
                                        <th>Time</th>
                                        <th style="text-align: right;">Action</th>
                                    </tr>
                                </thead>
                                <tbody id="intakeTableBody">
                                    <?php if (empty($intakeLog)): ?>
                                        <tr id="noIntakeRow">
                                            <td colspan="6" class="empty-state">
                                                <i class="fas fa-drumstick-bite"></i> No food logged yet today.
                                            </td>
                                        </tr>
                                    <?php endif; ?>

                                    <?php foreach ($intakeLog as $log): ?>
                                        <?php $entry = $log; $showDate = false; include PROJECT_ROOT . 'dashboard/views/_intake-row.php'; ?>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </section>
                </div>
            </div>
        </main>

        <!-- =============== Barcode Scanner Modal =============== -->
        <div class="scanner-modal" id="scannerModal" role="dialog" aria-modal="true">
            <div class="scanner-modal-content">
                <div class="scanner-modal-header">
                    <h3><i class="fas fa-barcode"></i> Scan Product Barcode</h3>
                    <button type="button" class="scanner-close" id="scannerCloseBtn" aria-label="Close">&times;</button>
                </div>

                <div id="scannerReader"></div>

                <div class="scanner-controls">
                    <button type="button" class="scanner-btn-start" id="scannerStartBtn">
                        <i class="fas fa-camera"></i> Start Camera
                    </button>
                    <button type="button" class="scanner-btn-stop" id="scannerStopBtn" style="display:none;">
                        <i class="fas fa-stop"></i> Stop
                    </button>
                </div>

                <div class="scanner-manual">
                    <label for="scannerManualInput">Or enter barcode manually</label>
                    <div class="scanner-manual-row">
                        <input type="text" id="scannerManualInput" inputmode="numeric" placeholder="e.g., 0884394009401">
                        <button type="button" id="scannerManualBtn">Lookup</button>
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
                        alert('Scanner library failed to load. Check your internet connection.');
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
                      <label for="portionMult">Servings:</label>
                      <input type="number" id="portionMult" value="1" min="0.1" step="0.1">
                      <span class="portion-kcal" id="portionKcalDisplay">${kcalBase != null ? Math.round(kcalBase) : 0} kcal</span>
                    </div>

                    <div class="scanner-actions">
                      <button type="button" class="scanner-btn-cancel" id="scannerCancelBtn">Cancel</button>
                      <button type="button" class="scanner-btn-confirm" id="scannerConfirmBtn">
                        <i class="fas fa-plus"></i> Fill Form
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
                      <button type="button" class="scanner-btn-cancel" id="scannerCancelBtn">Close</button>
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
                            <h4>BitBalance AI Nutritionist</h4>
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
                    <button class="btn-tool" title="Upload Photo" onclick="document.getElementById('imgUpload').click()">
                        <i class="fas fa-camera"></i>
                    </button>
                    <input type="file" id="imgUpload" accept="image/*" style="display: none;"
                        onchange="handleImageUpload(this)">

                    <input type="text" id="chatInput" placeholder="Ex: 1 bowl of Pho..." onkeypress="handleEnter(event)">

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
                chatInput.addEventListener('focus', scrollToBottom);
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
                            <button class="btn-add-log" onclick="autoLogFood('${safeName}', ${info.calories}, ${info.protein}, ${info.carbs}, ${info.fat})">
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

            function autoLogFood(name, calories, protein, carbs, fat) {
                document.getElementById('food_item').value = name;
                document.getElementById('calories').value = calories;
                if (protein !== undefined && protein !== null) document.getElementById('protein').value = protein;
                if (carbs   !== undefined && carbs   !== null) document.getElementById('carbs').value   = carbs;
                if (fat     !== undefined && fat     !== null) document.getElementById('fat').value     = fat;

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
    <?php else: ?>
        <main class="dashboard-content dashboard-empty-state">
            <h2>Please log in to access your Food Log.</h2>
            <a href="<?= BASE_URL ?>login.php" class="btn-submit"
                style="display:inline-block; width:auto; margin-top:20px;">Sign In</a>
        </main>
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
                            showLoggingToast('Food logged!', foodName + ' • ' + cal + ' kcal');

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
            if (body) {
                body.addEventListener('click', async e => {
                    const btn = e.target.closest('.deleteBtn');
                    if (!btn) return;

                    const row = btn.closest('tr');
                    const id = row.dataset.id;
                    const fd = new FormData();
                    fd.append('intake_id', id);

                    try {
                        const res = await fetch('handlers/delete_intake.php', {
                            method: 'POST',
                            headers: { 'X-Requested-With': 'fetch' },
                            body: fd
                        });
                        const data = await res.json();

                        if (data.ok) {
                            // Grab food name from row BEFORE removal for the toast subtext
                            const deletedFood = row.querySelector('td[data-label="Food"]')?.innerText.trim() || '';

                            row.style.opacity = '0';
                            setTimeout(() => row.remove(), 300);

                            totalDisplay.textContent = data.total;
                            progressFill.style.width = data.percentage + '%';
                            if (pctLabel) pctLabel.textContent = Math.round(data.percentage) + '%';

                            updateMacrosWidget(data.macros, data.macro_goals);

                            // Toast notification
                            showLoggingToast('Entry deleted', deletedFood);

                            // Show empty state if needed
                            if (body.children.length <= 1) { // 1 because row is removed after timeout
                                // Logic to re-add empty row could go here
                            }
                        } else {
                            alert(data.error);
                        }
                    } catch (err) {
                        alert('Connection error');
                    }
                });
            }
        });
    </script>

    <?php if ($isLoggedIn): ?>
        <?php $modalTitle = 'Edit Intake Entry'; include PROJECT_ROOT . 'dashboard/views/_edit-intake-modal.php'; ?>
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
    <?php endif; ?>
</body>

</html>