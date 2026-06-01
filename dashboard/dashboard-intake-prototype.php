<?php
/**
 * BITBALANCE FOOD INTAKE LOGGING PROTOTYPE
 * Fully-interactive, responsive, and gamified food logger.
 * PHP 7.4.33 compatible.
 */

require_once __DIR__ . '/../include/init.php';

// Prepare variables for global layout
$activePage = 'intake';
$activeHeader = 'dashboard';
$bodyClass = 'page-intake proto-theme-green'; // Default to green theme

// Ensure display name is set
$firstName = isset($user['first_name']) ? $user['first_name'] : 'Champion';

// Setup Mock Initial State (Synchronized with default logged items)
$userGoal = 2200;
$initialItems = [
    ['id' => 1, 'name' => 'Pho Bo', 'kcal' => 450, 'protein' => 30, 'carbs' => 55, 'fat' => 10, 'category' => 'breakfast', 'emoji' => '🍲', 'time' => '08:30 AM'],
    ['id' => 2, 'name' => 'Iced Coffee', 'kcal' => 120, 'protein' => 2, 'carbs' => 18, 'fat' => 4, 'category' => 'snack', 'emoji' => '☕', 'time' => '10:00 AM'],
    ['id' => 3, 'name' => 'Grilled Chicken Salad', 'kcal' => 550, 'protein' => 40, 'carbs' => 35, 'fat' => 20, 'category' => 'lunch', 'emoji' => '🥗', 'time' => '12:30 PM']
];

$totalCalories = 0;
$totalProtein = 0;
$totalCarbs = 0;
$totalFat = 0;

foreach ($initialItems as $item) {
    $totalCalories += $item['kcal'];
    $totalProtein += $item['protein'];
    $totalCarbs += $item['carbs'];
    $totalFat += $item['fat'];
}

$macroGoals = [
    'protein' => 140, // standard healthy ratios
    'carbs' => 240,
    'fat' => 70
];
?>
<!DOCTYPE html>
<html lang="en" data-theme="<?php echo isset($_SESSION['user']) ? ($_SESSION['user']['theme_preference'] ?? 'system') : 'system'; ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>BitBalance Food Intake Prototype</title>
    <?php
    $pageComponents = ['sidebar'];
    $pageCss = [
        'css/dashboard.css', 
        'css/components/intake-list.css', 
        'css/pages/dashboard-intake.css',
        'css/pages/dashboard-intake-prototype.css'
    ];
    include PROJECT_ROOT . 'views/head_css.php';
    ?>
    <script src="https://kit.fontawesome.com/b94f65ead2.js" crossorigin="anonymous"></script>
</head>
<body class="<?php echo htmlspecialchars($bodyClass); ?>">
    <?php include PROJECT_ROOT . 'views/header.php'; ?>
    <?php include PROJECT_ROOT . 'dashboard/views/sidebar.php'; ?>

    <main class="dashboard-content">
        <div class="intake-proto-container">

            <!-- Prototype Theme Controller Strip -->
            <div class="intake-proto-header">
                <h3>
                    <i class="fas fa-barcode" style="color: var(--color-primary);"></i>
                    <span>Food Intake & AI Scanner Prototype</span>
                </h3>
                <div style="display: flex; align-items: center; gap: 16px;">
                    <span style="font-size: 0.8rem; font-weight: 800; text-transform: uppercase; color: var(--color-text-secondary);">
                        Interactive Themes:
                    </span>
                    <div class="theme-options-strip">
                        <button class="theme-btn-opt opt-green active" onclick="switchProtoTheme('green')" title="Duolingo Green"></button>
                        <button class="theme-btn-opt opt-blue" onclick="switchProtoTheme('blue')" title="Ocean Blue"></button>
                        <button class="theme-btn-opt opt-orange" onclick="switchProtoTheme('orange')" title="Sunset Orange"></button>
                        <button class="theme-btn-opt opt-purple" onclick="switchProtoTheme('purple')" title="Cyberpunk Purple"></button>
                    </div>
                </div>
            </div>

            <!-- Dynamic Visual Diet Plate Builder -->
            <div class="diet-plate-card">
                <div class="diet-plate-flex">
                    <div class="plate-canvas-area" id="plateCanvas">
                        <div class="plate-main-circle">
                            <span class="plate-center-kcal" id="plateKcalText">0 kcal</span>
                        </div>
                        <!-- Dynamic floating foods will be injected here by JS -->
                    </div>
                    <div class="plate-info-details">
                        <h4>Visual Diet Plate Sandbox</h4>
                        <p>Your food choices populate on the plate in real-time. Toggle food logs to observe active macronutrient distribution in your sandbox budget below.</p>
                        
                        <div class="macro-sandbox-bars">
                            <div class="sandbox-bar-item">
                                <div class="sandbox-lbl-row">
                                    <span>Protein (Cơ bắp)</span>
                                    <span><strong id="barTextProtein">0</strong> / <?php echo $macroGoals['protein']; ?>g</span>
                                </div>
                                <div class="sandbox-track">
                                    <div class="sandbox-fill p" id="barFillProtein" style="width: 0%;"></div>
                                </div>
                            </div>
                            <div class="sandbox-bar-item">
                                <div class="sandbox-lbl-row">
                                    <span>Carbohydrates (Năng lượng)</span>
                                    <span><strong id="barTextCarbs">0</strong> / <?php echo $macroGoals['carbs']; ?>g</span>
                                </div>
                                <div class="sandbox-track">
                                    <div class="sandbox-fill c" id="barFillCarbs" style="width: 0%;"></div>
                                </div>
                            </div>
                            <div class="sandbox-bar-item">
                                <div class="sandbox-lbl-row">
                                    <span>Fats (Trí não)</span>
                                    <span><strong id="barTextFat">0</strong> / <?php echo $macroGoals['fat']; ?>g</span>
                                </div>
                                <div class="sandbox-track">
                                    <div class="sandbox-fill f" id="barFillFat" style="width: 0%;"></div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Main Layout Grid -->
            <div class="intake-main-grid">

                <!-- COLUMN 1: AI Scanner and Log Input Forms -->
                <div>
                    
                    <!-- AI Camera Scan Panel -->
                    <div class="ai-scan-panel" onclick="simulateAIScan()">
                        <i class="fas fa-camera ai-scan-icon"></i>
                        <h4 class="ai-scan-title">Simulate AI Meal Photo Analyzer</h4>
                        <p class="ai-scan-desc">Click to simulate taking a photo of a meal. AI automatically identifies ingredients and macro counts.</p>
                        
                        <!-- Scanning Laser Overlay -->
                        <div class="sim-scan-overlay" id="scanOverlay">
                            <div class="laser-beam"></div>
                            <!-- A beautiful preloaded mock image of Salmon Meal -->
                            <img src="https://images.unsplash.com/photo-1546069901-ba9599a7e63c?w=300&auto=format&fit=crop" class="scanning-photo" alt="Scanning meal">
                            <h4 style="color:#fff; margin:16px 0 4px 0; font-weight:850; font-size:1rem;">AI Scanning Dish...</h4>
                            <p style="color:var(--color-primary); font-size:0.75rem; font-weight:700; margin:0;">Analyzing macro proportions</p>
                        </div>
                    </div>

                    <!-- Intake Form Card -->
                    <div class="form-premium-card">
                        <h4 style="margin: 0 0 16px 0; font-weight: 850; font-size: 1.05rem; display: flex; align-items: center; gap: 8px;">
                            <i class="fas fa-plus-circle" style="color: var(--color-primary);"></i>
                            <span>Food Intake Log Entry</span>
                        </h4>

                        <!-- Suggested Food Strip (Chips) -->
                        <span style="font-size: 0.75rem; font-weight: 800; color: var(--color-text-secondary); text-transform: uppercase; display: block; margin-bottom: 6px;">
                            Quick Logging Suggestions:
                        </span>
                        <div class="suggested-food-strip">
                            <button type="button" class="suggested-food-chip" onclick="prefillChip('Pho Bo 🍲', 450, 30, 55, 10, 'breakfast')">
                                🍲 Pho Bo (+450)
                            </button>
                            <button type="button" class="suggested-food-chip" onclick="prefillChip('Avocado Toast 🥑', 280, 8, 30, 14, 'breakfast')">
                                🥑 Avocado Toast (+280)
                            </button>
                            <button type="button" class="suggested-food-chip" onclick="prefillChip('Chicken Breast 🍗', 165, 31, 0, 3.6, 'lunch')">
                                🍗 Chicken Breast (+165)
                            </button>
                            <button type="button" class="suggested-food-chip" onclick="prefillChip('Banana 🍌', 105, 1.3, 27, 0.3, 'snack')">
                                🍌 Banana (+105)
                            </button>
                        </div>

                        <!-- Main Logging Form -->
                        <form id="protoIntakeForm" onsubmit="handleFormSubmit(event)" autocomplete="off">
                            <div class="form-group" style="margin-bottom: 16px;">
                                <label for="foodName" style="font-weight: 750; font-size: 0.88rem; color: var(--color-text); margin-bottom: 6px; display: block;">Food Name</label>
                                <div class="input-grp-glass">
                                    <i class="fas fa-utensils input-grp-icon"></i>
                                    <input type="text" id="foodName" placeholder="e.g. Rice with Salmon" required>
                                </div>
                            </div>

                            <div class="form-row-split" style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px; margin-bottom: 16px;">
                                <div class="form-group">
                                    <label for="foodKcal" style="font-weight: 750; font-size: 0.88rem; color: var(--color-text); margin-bottom: 6px; display: block;">Calories (kcal)</label>
                                    <div class="input-grp-glass">
                                        <i class="fas fa-bolt input-grp-icon"></i>
                                        <input type="number" id="foodKcal" placeholder="0" min="1" required>
                                    </div>
                                </div>
                                <div class="form-group">
                                    <label for="mealCategory" style="font-weight: 750; font-size: 0.88rem; color: var(--color-text); margin-bottom: 6px; display: block;">Meal Category</label>
                                    <div class="input-grp-glass">
                                        <i class="fas fa-filter input-grp-icon"></i>
                                        <select id="mealCategory" required>
                                            <option value="" disabled selected>Select Category</option>
                                            <option value="breakfast">🌅 Breakfast</option>
                                            <option value="lunch">☀️ Lunch</option>
                                            <option value="dinner">🌙 Dinner</option>
                                            <option value="snack">🍎 Snack</option>
                                        </select>
                                    </div>
                                </div>
                            </div>

                            <div class="form-group" style="margin-bottom: 20px;">
                                <label style="font-weight: 750; font-size: 0.88rem; color: var(--color-text); margin-bottom: 6px; display: block;">Macros (optional)</label>
                                <div style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 12px;">
                                    <div class="input-grp-glass">
                                        <span style="position: absolute; left: 12px; font-weight: 850; font-size: 0.8rem; color: var(--color-primary);">P</span>
                                        <input type="number" id="macroP" placeholder="Pro (g)" style="padding-left: 28px;">
                                    </div>
                                    <div class="input-grp-glass">
                                        <span style="position: absolute; left: 12px; font-weight: 850; font-size: 0.8rem; color: var(--color-secondary);">C</span>
                                        <input type="number" id="macroC" placeholder="Car (g)" style="padding-left: 28px;">
                                    </div>
                                    <div class="input-grp-glass">
                                        <span style="position: absolute; left: 12px; font-weight: 850; font-size: 0.8rem; color: var(--color-accent);">F</span>
                                        <input type="number" id="macroF" placeholder="Fat (g)" style="padding-left: 28px;">
                                    </div>
                                </div>
                            </div>

                            <button type="submit" class="btn-submit" style="width: 100%; border: none;">
                                <i class="fas fa-check"></i> Complete Log Entry
                            </button>
                        </form>
                    </div>

                </div>

                <!-- COLUMN 2: Today's Intake History Board -->
                <div>
                    <div class="logs-board-card">
                        <h4 style="margin: 0 0 20px 0; font-weight: 850; font-size: 1.05rem; display: flex; align-items: center; gap: 8px;">
                            <i class="fas fa-list-ul" style="color: var(--color-primary);"></i>
                            <span>Today's Logged History</span>
                        </h4>

                        <div id="logsListArea">
                            <!-- Dynamic log items injected by JS -->
                        </div>
                    </div>
                </div>

            </div>

        </div>
    </main>

    <!-- Interactive FAB alerts toast -->
    <div class="sim-toast" id="simToast">
        <i class="fas fa-check-circle"></i>
        <span class="sim-toast-msg" id="simToastMsg">Log updated successfully!</span>
    </div>

    <!-- Interactive script logic -->
    <script>
        // Set standard state variables
        let state = {
            goal: <?php echo $userGoal; ?>,
            macroGoals: <?php echo json_encode($macroGoals); ?>,
            totalCalories: 0,
            protein: 0,
            carbs: 0,
            fat: 0,
            logs: <?php echo json_encode($initialItems); ?>
        };

        // UI Initialization
        document.addEventListener('DOMContentLoaded', () => {
            calculateTotals();
            renderLogs();
        });

        // 1. Switch Theme Variant
        function switchProtoTheme(themeName) {
            const body = document.body;
            body.classList.remove('proto-theme-green', 'proto-theme-blue', 'proto-theme-orange', 'proto-theme-purple');
            body.classList.add('proto-theme-' + themeName);
            
            document.querySelectorAll('.theme-btn-opt').forEach(btn => {
                btn.classList.remove('active');
            });
            const activeBtn = document.querySelector('.theme-btn-opt.opt-' + themeName);
            if (activeBtn) activeBtn.classList.add('active');

            showToast("Giao diện chuyển sang tông màu: " + themeName.toUpperCase() + "!");
        }

        // 2. Prefill form using suggested chips
        function prefillChip(name, kcal, p, c, f, category) {
            document.getElementById('foodName').value = name;
            document.getElementById('foodKcal').value = kcal;
            document.getElementById('macroP').value = p;
            document.getElementById('macroC').value = c;
            document.getElementById('macroF').value = f;
            document.getElementById('mealCategory').value = category;
            
            showToast(`Prefilled form fields for ${name}!`);
        }

        // 3. Simulated AI Camera Scanner
        function simulateAIScan() {
            const overlay = document.getElementById('scanOverlay');
            overlay.classList.add('active');

            setTimeout(() => {
                overlay.classList.remove('active');
                
                // Pre-fill form fields
                document.getElementById('foodName').value = "Grilled Salmon Rice Bowl 🍱";
                document.getElementById('foodKcal').value = 650;
                document.getElementById('macroP').value = 45;
                document.getElementById('macroC').value = 55;
                document.getElementById('macroF').value = 15;
                document.getElementById('mealCategory').value = "dinner";

                showToast("AI phân tích hình ảnh hoàn tất! Đã tự động điền các trường.");
            }, 2500);
        }

        // 4. Form Log Submit Action
        function handleFormSubmit(event) {
            event.preventDefault();

            const name = document.getElementById('foodName').value;
            const kcal = parseInt(document.getElementById('foodKcal').value);
            const category = document.getElementById('mealCategory').value;
            const p = parseFloat(document.getElementById('macroP').value) || 0;
            const c = parseFloat(document.getElementById('macroC').value) || 0;
            const f = parseFloat(document.getElementById('macroF').value) || 0;

            const categoryEmojiMap = {
                breakfast: '🌅',
                lunch: '☀️',
                dinner: '🌙',
                snack: '🍎'
            };

            const now = new Date();
            const timeString = now.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });

            const newLog = {
                id: Date.now(),
                name: name,
                kcal: kcal,
                protein: p,
                carbs: c,
                fat: f,
                category: category,
                emoji: categoryEmojiMap[category] || '🥗',
                time: timeString
            };

            state.logs.unshift(newLog);
            
            // Recalculate totals and render views
            calculateTotals();
            renderLogs();

            // Reset form inputs
            document.getElementById('protoIntakeForm').reset();
            showToast(`Logged successfully: ${name}!`);
        }

        // 5. Delete Log Item simulation
        function deleteLogItem(id) {
            const card = document.getElementById('log-card-' + id);
            if (card) {
                card.classList.add('removing');
                
                setTimeout(() => {
                    state.logs = state.logs.filter(log => log.id !== id);
                    calculateTotals();
                    renderLogs();
                    showToast("Đã xóa mục ăn uống thành công!");
                }, 300);
            }
        }

        // 6. Recalculate Totals
        function calculateTotals() {
            state.totalCalories = 0;
            state.protein = 0;
            state.carbs = 0;
            state.fat = 0;

            state.logs.forEach(log => {
                state.totalCalories += log.kcal;
                state.protein += log.protein;
                state.carbs += log.carbs;
                state.fat += log.fat;
            });

            // Update UI widgets
            document.getElementById('plateKcalText').textContent = state.totalCalories + ' kcal';
            
            // Update sandbox numeric texts
            document.getElementById('barTextProtein').textContent = Math.round(state.protein);
            document.getElementById('barTextCarbs').textContent = Math.round(state.carbs);
            document.getElementById('barTextFat').textContent = Math.round(state.fat);

            // Update sandbox fills
            const pPct = Math.min(100, Math.round((state.protein / state.macroGoals.protein) * 100));
            const cPct = Math.min(100, Math.round((state.carbs / state.macroGoals.carbs) * 100));
            const fPct = Math.min(100, Math.round((state.fat / state.macroGoals.fat) * 100));

            document.getElementById('barFillProtein').style.width = pPct + '%';
            document.getElementById('barFillCarbs').style.width = cPct + '%';
            document.getElementById('barFillFat').style.width = fPct + '%';

            // Redraw floating items on visual plate
            updatePlateVisuals();
        }

        // 7. Update visual representations on Diet Plate canvas
        function updatePlateVisuals() {
            const canvas = document.getElementById('plateCanvas');
            
            // Remove existing floatings
            document.querySelectorAll('.plate-food-floating').forEach(el => el.remove());

            // Build map of unique category emojis currently in active logs
            const activeEmojis = [];
            state.logs.forEach(log => {
                if (log.emoji && !activeEmojis.includes(log.emoji)) {
                    activeEmojis.push(log.emoji);
                }
            });

            // Map emojis into circular positions inside plate bounding circles
            activeEmojis.forEach((emoji, index) => {
                const floatWrapper = document.createElement('div');
                floatWrapper.className = 'plate-food-floating';
                
                // Calculate radial coordinate layout
                const total = activeEmojis.length;
                const angle = (index / total) * 2 * Math.PI - (Math.PI / 2);
                const radius = 60; // radius placement
                
                const leftPos = 100 + radius * Math.cos(angle) - 18; // offset center
                const topPos = 100 + radius * Math.sin(angle) - 18;
                
                floatWrapper.style.left = leftPos + 'px';
                floatWrapper.style.top = topPos + 'px';
                
                // Apply slight delay animation to randomize floating
                floatWrapper.style.animationDelay = (index * 0.5) + 's';

                floatWrapper.innerHTML = `<span class="plate-food-emoji">${emoji}</span>`;
                canvas.appendChild(floatWrapper);
            });
        }

        // 8. Render logs history card
        function renderLogs() {
            const listArea = document.getElementById('logsListArea');
            listArea.innerHTML = '';

            if (state.logs.length === 0) {
                listArea.innerHTML = `
                    <div style="text-align:center; padding:32px 0; color:var(--color-text-muted);">
                        <i class="fas fa-drumstick-bite" style="font-size:2rem; margin-bottom:8px; opacity:0.6;"></i>
                        <p style="font-weight:700; font-size:0.85rem; margin:0;">Chưa có đồ ăn thức uống nào được ghi nhận hôm nay</p>
                    </div>
                `;
                return;
            }

            state.logs.forEach(log => {
                const item = document.createElement('div');
                item.className = 'interactive-log-item';
                item.id = 'log-card-' + log.id;

                const categoryLabelMap = {
                    breakfast: 'Breakfast',
                    lunch: 'Lunch',
                    dinner: 'Dinner',
                    snack: 'Snack'
                };

                const friendlyCategory = categoryLabelMap[log.category] || 'Meal';

                item.innerHTML = `
                    <div class="log-item-details">
                        <div class="log-item-icon">${log.emoji}</div>
                        <div>
                            <span class="log-item-name">${log.name}</span>
                            <span class="log-item-sub">
                                ${friendlyCategory} · ${log.time} · P: ${Math.round(log.protein)}g C: ${Math.round(log.carbs)}g F: ${Math.round(log.fat)}g
                            </span>
                        </div>
                    </div>
                    <div class="log-item-stats">
                        <span class="log-item-kcal">${log.kcal} kcal</span>
                        <button type="button" class="log-btn-delete" onclick="deleteLogItem(${log.id})" title="Delete entry">
                            <i class="fas fa-trash-alt"></i>
                        </button>
                    </div>
                `;
                listArea.appendChild(item);
            });
        }

        // 9. Simulator Toast alerts
        function showToast(message) {
            const toast = document.getElementById('simToast');
            document.getElementById('simToastMsg').textContent = message;
            
            toast.classList.add('active');
            
            if(window.toastTimer) clearTimeout(window.toastTimer);
            window.toastTimer = setTimeout(() => {
                toast.classList.remove('active');
            }, 2500);
        }
    </script>
</body>
</html>
