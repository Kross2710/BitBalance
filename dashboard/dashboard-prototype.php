<?php
/**
 * BITBALANCE DASHBOARD UI IMPROVEMENT PROTOTYPE
 * Designed as a premium, highly-interactive, responsive, and gamified experience.
 * Fully compatible with PHP 7.4.33. No external dependencies, purely vanilla JS & CSS.
 */

require_once __DIR__ . '/../include/init.php';

// Prepare variables for global layout
$activePage = 'overview';
$activeHeader = 'dashboard';
$bodyClass = 'page-dashboard proto-theme-green'; // Default to green theme

// Ensure we have a friendly display name
$firstName = isset($user['first_name']) ? $user['first_name'] : 'Champion';

// Setup Mock Initial State
$userGoal = 2000;
$totalCalories = 1250;
$burnedCalories = 350;
$netCalories = $totalCalories - $burnedCalories;
$progressPercentage = round(($netCalories / $userGoal) * 100);

// Mock Mascot Pet Details
$mascotActiveSpecies = 'owl';
$mascotName = 'Chirpy';
$mascotLevel = 4;
?>
<!DOCTYPE html>
<html lang="en" data-theme="<?php echo isset($_SESSION['user']) ? ($_SESSION['user']['theme_preference'] ?? 'system') : 'system'; ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>BitBalance Dashboard UI Prototype</title>
    <?php
    $pageComponents = ['sidebar'];
    // Include baseline layout styles + our brand new prototype styling sheet
    $pageCss = [
        'css/dashboard.css', 
        'css/components/intake-list.css', 
        'css/pages/dashboard-home.css',
        'css/pages/dashboard-prototype.css'
    ];
    include PROJECT_ROOT . 'views/head_css.php';
    ?>
    <script src="https://kit.fontawesome.com/b94f65ead2.js" crossorigin="anonymous"></script>
</head>
<body class="<?php echo htmlspecialchars($bodyClass); ?>">
    <?php include PROJECT_ROOT . 'views/header.php'; ?>
    <?php include PROJECT_ROOT . 'dashboard/views/sidebar.php'; ?>

    <main class="dashboard">
        <div class="proto-container">
            
            <!-- Prototype Controls Bar -->
            <div class="proto-controls">
                <h3>
                    <i class="fas fa-magic" style="color: var(--color-primary);"></i>
                    <span>UI/UX Improvements Dashboard Prototype</span>
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

            <!-- Welcome Gamified Banner -->
            <div class="welcome-banner proto-welcome">
                <div class="welcome-text">
                    <h2>Hello, <?php echo htmlspecialchars($firstName); ?>!</h2>
                    <p>Welcome to your improved dashboard prototype. Click components to test interactivity, adjust goals, add logs, or pet your mascot companion.</p>
                </div>
                <div class="welcome-stats">
                    <div class="welcome-stat-chip">
                        <i class="fas fa-bullseye" style="color: #60a5fa;"></i>
                        <span>Goal Progress: <strong id="progressPctText"><?php echo $progressPercentage; ?>%</strong></span>
                    </div>
                    <div class="welcome-stat-chip">
                        <i class="fas fa-star" style="color: #f59e0b;"></i>
                        <span>Level <?php echo $mascotLevel; ?> Companion</span>
                    </div>
                </div>
            </div>

            <!-- Core Dashboard Grid Layout -->
            <div class="proto-main-grid">
                
                <!-- COLUMN 1: Calorie Goal Dial and Meal Bento Grid -->
                <div style="display: flex; flex-direction: column; gap: var(--stack-gap);">
                    
                    <!-- Calorie Goal Dial Card -->
                    <div class="dial-widget-card">
                        <div class="dial-visual-area">
                            <svg class="dial-svg-ring" viewBox="0 0 160 160">
                                <circle class="dial-track" cx="80" cy="80" r="68"></circle>
                                <circle class="dial-fill dial-glow-fill" id="calDialFill" cx="80" cy="80" r="68" 
                                        stroke-dasharray="427" stroke-dashoffset="427"></circle>
                            </svg>
                            <div class="dial-center-content">
                                <span class="dial-center-val" id="netCaloriesText"><?php echo $netCalories; ?></span>
                                <span class="dial-center-lbl">Net kcal</span>
                            </div>
                        </div>
                        <div class="dial-stats-area">
                            <div class="dial-stat-row">
                                <div class="dial-stat-info">
                                    <div class="dial-stat-icon ic-intake"><i class="fas fa-plus"></i></div>
                                    <span class="dial-stat-lbl">Calories Logged</span>
                                </div>
                                <span class="dial-stat-val" id="loggedCaloriesText"><?php echo $totalCalories; ?> kcal</span>
                            </div>
                            <div class="dial-stat-row">
                                <div class="dial-stat-info">
                                    <div class="dial-stat-icon ic-burn"><i class="fas fa-fire"></i></div>
                                    <span class="dial-stat-lbl">Active Burned</span>
                                </div>
                                <span class="dial-stat-val" id="burnedCaloriesText"><?php echo $burnedCalories; ?> kcal</span>
                            </div>
                            <div class="dial-stat-row">
                                <div class="dial-stat-info">
                                    <div class="dial-stat-icon ic-net"><i class="fas fa-bullseye"></i></div>
                                    <span class="dial-stat-lbl">Daily Calorie Goal</span>
                                </div>
                                <span class="dial-stat-val" style="color: var(--color-primary); font-weight: 850;" id="goalCalText"><?php echo $userGoal; ?> kcal</span>
                            </div>
                        </div>
                    </div>

                    <!-- Bento Grid Meal Slots Section -->
                    <div>
                        <h4 style="margin: 0 0 16px 0; font-weight: 850; font-size: 1.15rem; color: var(--color-text); display: flex; align-items: center; gap: 8px;">
                            <i class="fas fa-utensils" style="color: var(--color-primary);"></i>
                            <span>Bento Diet Plate Compartments</span>
                        </h4>
                        
                        <div class="proto-bento-grid">
                            
                            <!-- Breakfast Slot -->
                            <div class="bento-slot-card" onclick="simulateBentoLog('breakfast', 'Breakfast', '🌅', 350)">
                                <div class="bento-slot-card-header">
                                    <span class="bento-slot-card-title">🌅 Breakfast</span>
                                    <span class="bento-slot-card-kcal" id="kcal-breakfast">0 kcal</span>
                                </div>
                                <div class="bento-slot-card-body" id="body-breakfast">
                                    <div class="bento-empty-prompt">
                                        <i class="fas fa-mug-hot bento-empty-icon"></i>
                                        <span>Click to simulate Breakfast Log</span>
                                    </div>
                                </div>
                            </div>

                            <!-- Lunch Slot -->
                            <div class="bento-slot-card" onclick="simulateBentoLog('lunch', 'Lunch', '☀️', 550)">
                                <div class="bento-slot-card-header">
                                    <span class="bento-slot-card-title">☀️ Lunch</span>
                                    <span class="bento-slot-card-kcal" id="kcal-lunch">0 kcal</span>
                                </div>
                                <div class="bento-slot-card-body" id="body-lunch">
                                    <div class="bento-empty-prompt">
                                        <i class="fas fa-hamburger bento-empty-icon"></i>
                                        <span>Click to simulate Lunch Log</span>
                                    </div>
                                </div>
                            </div>

                            <!-- Dinner Slot -->
                            <div class="bento-slot-card" onclick="simulateBentoLog('dinner', 'Dinner', '🌙', 450)">
                                <div class="bento-slot-card-header">
                                    <span class="bento-slot-card-title">🌙 Dinner</span>
                                    <span class="bento-slot-card-kcal" id="kcal-dinner">0 kcal</span>
                                </div>
                                <div class="bento-slot-card-body" id="body-dinner">
                                    <div class="bento-empty-prompt">
                                        <i class="fas fa-utensils bento-empty-icon"></i>
                                        <span>Click to simulate Dinner Log</span>
                                    </div>
                                </div>
                            </div>

                            <!-- Snack Slot -->
                            <div class="bento-slot-card" onclick="simulateBentoLog('snack', 'Snack', '🍎', 150)">
                                <div class="bento-slot-card-header">
                                    <span class="bento-slot-card-title">🍎 Snack</span>
                                    <span class="bento-slot-card-kcal" id="kcal-snack">0 kcal</span>
                                </div>
                                <div class="bento-slot-card-body" id="body-snack">
                                    <div class="bento-empty-prompt">
                                        <i class="fas fa-apple-alt bento-empty-icon"></i>
                                        <span>Click to simulate Snack Log</span>
                                    </div>
                                </div>
                            </div>

                        </div>
                    </div>

                </div>

                <!-- COLUMN 2: Mascot Room and Habit Integration -->
                <div style="display: flex; flex-direction: column; gap: var(--stack-gap);">
                    
                    <!-- Mascot Companion Widget -->
                    <div class="proto-mascot-card">
                        <h4 style="margin: 0; font-weight: 850; font-size: 1.05rem; display: flex; align-items: center; gap: 8px; justify-content: center;">
                            <i class="fas fa-ghost" style="color: var(--color-primary);"></i>
                            <span><?php echo htmlspecialchars($mascotName); ?>'s Room</span>
                        </h4>
                        
                        <div class="mascot-pet-room" id="mascotRoom">
                            <div class="mascot-speech-proto" id="mascotSpeech">
                                Pet me to say hello! 🐾
                            </div>
                            
                            <div class="mascot-avatar-wrapper" id="mascotAvatar" onclick="handlePetMascot(event)">
                                <svg class="mascot-svg-avatar" viewBox="0 0 100 100">
                                    <!-- Friendly Mascot Character (SVG representation) -->
                                    <circle cx="50" cy="55" r="30" fill="var(--color-primary)" stroke="var(--color-text)" stroke-width="4"></circle>
                                    <!-- Eyes -->
                                    <circle cx="40" cy="48" r="7" fill="#fff" stroke="var(--color-text)" stroke-width="3"></circle>
                                    <circle cx="40" cy="48" r="3" fill="#000"></circle>
                                    <circle cx="60" cy="48" r="7" fill="#fff" stroke="var(--color-text)" stroke-width="3"></circle>
                                    <circle cx="60" cy="48" r="3" fill="#000"></circle>
                                    <!-- Beak / Smile -->
                                    <polygon points="50,54 46,58 54,58" fill="#FF9600" stroke="var(--color-text)" stroke-width="2"></polygon>
                                    <!-- Wings -->
                                    <path d="M 16,55 Q 8,50 18,40" fill="none" stroke="var(--color-text)" stroke-width="4" stroke-linecap="round"></path>
                                    <path d="M 84,55 Q 92,50 82,40" fill="none" stroke="var(--color-text)" stroke-width="4" stroke-linecap="round"></path>
                                    <!-- Little Feet -->
                                    <circle cx="42" cy="85" r="4" fill="#FF9600" stroke="var(--color-text)" stroke-width="2"></circle>
                                    <circle cx="58" cy="85" r="4" fill="#FF9600" stroke="var(--color-text)" stroke-width="2"></circle>
                                </svg>
                            </div>
                        </div>
                        
                        <span style="font-size: 0.78rem; font-weight: 800; text-transform: uppercase; color: var(--color-text-secondary);">
                            Interactive Pet Widget
                        </span>
                    </div>

                    <!-- Streak Card Widget -->
                    <div class="proto-mascot-card" style="text-align: left;">
                        <div style="display: flex; align-items: center; justify-content: space-between;">
                            <div>
                                <h4 style="margin: 0; font-weight: 850; font-size: 1.05rem;">Logging Streak</h4>
                                <span style="font-size: 2.2rem; font-weight: 850; color: var(--color-accent);">
                                    5 <span style="font-size: 1rem; font-weight: 750; color: var(--color-text-secondary);">days</span>
                                </span>
                            </div>
                            <i class="fas fa-fire" style="font-size: 2.8rem; color: var(--color-accent); filter: drop-shadow(0 4px 8px rgba(255, 150, 0, 0.3));"></i>
                        </div>
                        <p style="font-size: 0.85rem; line-height: 1.5; color: var(--color-text-secondary); margin: 12px 0 0 0;">
                            Keep tracking your meals to maintain your streak! A freeze protector shield is active.
                        </p>
                    </div>

                </div>

            </div>

        </div>
    </main>

    <!-- Interactive FAB floating quick log -->
    <div class="proto-fab-wrapper" id="protoFab">
        <button class="proto-fab-btn" onclick="toggleFabMenu()" aria-label="Toggle Quick Log menu">
            <i class="fas fa-plus"></i>
        </button>
        <div class="proto-fab-menu">
            <div class="proto-fab-item" onclick="handleFabQuickLog('Log Apple Snack', 80)">
                <i class="fas fa-apple-alt"></i> Log Apple Snack (+80g)
            </div>
            <div class="proto-fab-item" onclick="handleFabQuickLog('Log Protein Shake', 200)">
                <i class="fas fa-drumstick-bite"></i> Log Protein Shake (+200g)
            </div>
            <div class="proto-fab-item" onclick="handleFabQuickLog('Simulate Active Walk', -150, true)">
                <i class="fas fa-running"></i> Simulate Active Walk (-150 kcal)
            </div>
        </div>
    </div>

    <!-- Simulator Toast Notification -->
    <div class="sim-toast" id="simToast">
        <i class="fas fa-check-circle"></i>
        <span class="sim-toast-msg" id="simToastMsg">Log updated successfully!</span>
    </div>

    <!-- Interactive Logic scripts -->
    <script>
        // Store current states
        let state = {
            goal: <?php echo $userGoal; ?>,
            total: <?php echo $totalCalories; ?>,
            burn: <?php echo $burnedCalories; ?>,
            net: <?php echo $netCalories; ?>,
            breakfast: 0,
            lunch: 0,
            dinner: 0,
            snack: 0
        };

        // UI Initialization
        document.addEventListener('DOMContentLoaded', () => {
            updateDial();
        });

        // 1. Switch Theme Variant
        function switchProtoTheme(themeName) {
            const body = document.body;
            body.classList.remove('proto-theme-green', 'proto-theme-blue', 'proto-theme-orange', 'proto-theme-purple');
            body.classList.add('proto-theme-' + themeName);
            
            // Toggle active status in strip
            document.querySelectorAll('.theme-btn-opt').forEach(btn => {
                btn.classList.remove('active');
            });
            const activeBtn = document.querySelector('.theme-btn-opt.opt-' + themeName);
            if (activeBtn) activeBtn.classList.add('active');

            showToast("Giao diện chuyển sang tông màu: " + themeName.toUpperCase() + "!");
        }

        // 2. Pet Mascot Interaction
        const companionPhrases = [
            "You are doing amazing today! 🌟",
            "Remember to log water too! 💧",
            "Consistency is key to success! 🔑",
            "Pet me again, that felt good! 🥰",
            "Ready for a wonderful day? Let's go! 🚀",
            "Eating balanced meals makes me grow! 🥗"
        ];

        function handlePetMascot(event) {
            const avatar = document.getElementById('mascotAvatar');
            const speech = document.getElementById('mascotSpeech');
            const room = document.getElementById('mascotRoom');

            // Apply bounce class
            avatar.classList.add('bounce-active');
            setTimeout(() => avatar.classList.remove('bounce-active'), 500);

            // Set speech text
            const randomPhrase = companionPhrases[Math.floor(Math.random() * companionPhrases.length)];
            speech.textContent = randomPhrase;

            // Spawn floating hearts
            for(let i=0; i<3; i++) {
                const heart = document.createElement('i');
                heart.className = 'fas fa-heart floating-heart';
                
                // Get offset coords relative to room container
                const rect = avatar.getBoundingClientRect();
                const roomRect = room.getBoundingClientRect();
                
                const leftOffset = (rect.left - roomRect.left) + 40 + (Math.random() * 30 - 15);
                const topOffset = (rect.top - roomRect.top) + 20 + (Math.random() * 20 - 10);
                
                heart.style.left = leftOffset + 'px';
                heart.style.top = topOffset + 'px';
                
                room.appendChild(heart);
                setTimeout(() => heart.remove(), 800);
            }
        }

        // 3. Simulate logging through Bento Grid cards
        function simulateBentoLog(slot, slotLabel, iconEmoji, amount) {
            // Update slot calories
            state[slot] += amount;
            document.getElementById('kcal-' + slot).textContent = state[slot] + ' kcal';
            
            // Show logged state inside card
            const body = document.getElementById('body-' + slot);
            body.innerHTML = `
                <div style="display:flex; align-items:center; gap:8px; animation: bounceIn 0.3s ease;">
                    <span style="font-size:1.4rem;">${iconEmoji}</span>
                    <div>
                        <strong style="color:var(--color-text); font-size:0.9rem;">Logged Meal</strong>
                        <span style="display:block; font-size:0.75rem; color:var(--color-text-secondary);">+${amount} kcal added</span>
                    </div>
                </div>
            `;

            // Adjust global totals
            state.total += amount;
            state.net = state.total - state.burn;
            
            updateDial();
            showToast(`Added ${amount} kcal to ${slotLabel}!`);
        }

        // 4. FAB floating action menu
        function toggleFabMenu() {
            document.getElementById('protoFab').classList.toggle('active');
        }

        function handleFabQuickLog(label, kcalAmount, isExercise = false) {
            if(isExercise) {
                state.burn += Math.abs(kcalAmount);
                showToast(`Exercise simulated: ${label}!`);
            } else {
                state.total += kcalAmount;
                showToast(`Log simulated: ${label}!`);
            }
            state.net = state.total - state.burn;
            updateDial();
            
            // Close menu
            document.getElementById('protoFab').classList.remove('active');
        }

        // 5. Update circular Goal Dial display
        function updateDial() {
            // Recalculate progress percentage
            const pct = Math.min(100, Math.max(0, Math.round((state.net / state.goal) * 100)));
            
            // Update text labels
            document.getElementById('netCaloriesText').textContent = state.net;
            document.getElementById('loggedCaloriesText').textContent = state.total + ' kcal';
            document.getElementById('burnedCaloriesText').textContent = state.burn + ' kcal';
            document.getElementById('progressPctText').textContent = pct + '%';

            // Calculate SVG dashoffset (stroke-dasharray is 427 for radius 68)
            const circumference = 427;
            const offset = circumference - (pct / 100) * circumference;
            document.getElementById('calDialFill').style.strokeDashoffset = offset;
        }

        // 6. Simulator Toast alerts
        function showToast(message) {
            const toast = document.getElementById('simToast');
            document.getElementById('simToastMsg').textContent = message;
            
            toast.classList.add('active');
            
            // Auto close after 2.5s
            if(window.toastTimer) clearTimeout(window.toastTimer);
            window.toastTimer = setTimeout(() => {
                toast.classList.remove('active');
            }, 2500);
        }
    </script>
</body>
</html>
