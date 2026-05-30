<?php
require_once __DIR__ . '/../include/init.php';

if (!isset($_SESSION['user'])) {
    header('Location: ' . BASE_URL . 'login.php');
    exit();
}

require_once __DIR__ . '/../include/db_config.php';
require_once __DIR__ . '/../include/csrf.php';
require_once __DIR__ . '/handlers/functions.php';
require_once __DIR__ . '/handlers/goal_plan.php';
require_once __DIR__ . '/../include/handlers/log_attempt.php';

$userId = (int) $_SESSION['user']['user_id'];
$bodyClass = 'page-set-goal';
$error_message = '';

function onboarding_int_post(string $key, int $min, int $max): ?int
{
    $value = filter_input(INPUT_POST, $key, FILTER_VALIDATE_INT, [
        'options' => ['min_range' => $min, 'max_range' => $max],
    ]);

    return ($value === false || $value === null) ? null : (int) $value;
}

function onboarding_float_post(string $key, float $min, float $max): ?float
{
    if (!isset($_POST[$key]) || $_POST[$key] === '') {
        return null;
    }

    $value = filter_input(INPUT_POST, $key, FILTER_VALIDATE_FLOAT);
    if ($value === false || $value === null) {
        return null;
    }

    $value = (float) $value;
    return ($value < $min || $value > $max) ? null : $value;
}

function onboarding_upsert_physical_info(PDO $pdo, int $userId, int $age, string $gender, float $weight, float $height): void
{
    $stmt = $pdo->prepare('SELECT userPhysicalStat_id FROM userPhysicalInfo WHERE user_id = ? LIMIT 1');
    $stmt->execute([$userId]);
    $existing = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($existing) {
        $stmt = $pdo->prepare('
            UPDATE userPhysicalInfo
            SET age = ?, gender = ?, weight = ?, height = ?
            WHERE user_id = ?
        ');
        $stmt->execute([$age, $gender, $weight, $height, $userId]);
        return;
    }

    $stmt = $pdo->prepare('
        INSERT INTO userPhysicalInfo (userPhysicalStat_id, user_id, age, gender, weight, height)
        VALUES (?, ?, ?, ?, ?, ?)
    ');
    $stmt->execute([$userId, $userId, $age, $gender, $weight, $height]);
}

function onboarding_save_weight_log(PDO $pdo, int $userId, float $weight): void
{
    $stmt = $pdo->prepare('SELECT weight_id FROM weight_log WHERE user_id = ? AND date_logged = CURDATE() LIMIT 1');
    $stmt->execute([$userId]);
    $weightId = $stmt->fetchColumn();

    if ($weightId) {
        $stmt = $pdo->prepare('UPDATE weight_log SET weight = ? WHERE weight_id = ? AND user_id = ?');
        $stmt->execute([$weight, (int) $weightId, $userId]);
        return;
    }

    $stmt = $pdo->prepare('INSERT INTO weight_log (user_id, weight, date_logged) VALUES (?, ?, CURDATE())');
    $stmt->execute([$userId, $weight]);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['commit_personal_plan'])) {
    $validGenders = ['male', 'female', 'other'];
    $activityOptions = plan_activity_options();
    $goalModes = plan_goal_modes();

    $gender = trim((string) ($_POST['gender'] ?? ''));
    $age = onboarding_int_post('age', 13, 100);
    $height = onboarding_int_post('height', 100, 250);
    $weight = onboarding_int_post('weight', 30, 300);
    $activityLevel = trim((string) ($_POST['activity_level'] ?? ''));
    $goalMode = trim((string) ($_POST['goal_mode'] ?? ''));
    $weeklyRate = onboarding_float_post('weekly_rate', 0.1, 1.5);
    $targetWeight = onboarding_float_post('target_weight', 30, 300);

    if ($goalMode === 'maintain') {
        $weeklyRate = 0.0;
        $targetWeight = null;
    }

    if (!csrf_verify($_POST['csrf_token'] ?? '')) {
        $error_message = 'This plan session expired. Please try again.';
    } elseif (!in_array($gender, $validGenders, true)) {
        $error_message = 'Please choose who this plan is for.';
    } elseif ($age === null) {
        $error_message = 'Please choose a valid age.';
    } elseif ($height === null) {
        $error_message = 'Please choose a valid height.';
    } elseif ($weight === null) {
        $error_message = 'Please choose a valid weight.';
    } elseif (!isset($activityOptions[$activityLevel])) {
        $error_message = 'Please choose a valid activity level.';
    } elseif (!isset($goalModes[$goalMode])) {
        $error_message = 'Please choose a valid goal.';
    } elseif ($goalMode !== 'maintain' && $weeklyRate === null) {
        $error_message = 'Please choose a valid weekly pace.';
    } else {
        $personalPlan = plan_build_personal_plan($age, $gender, (float) $weight, (float) $height, $activityLevel, $goalMode, $weeklyRate);

        try {
            $pdo->beginTransaction();

            onboarding_upsert_physical_info($pdo, $userId, $age, $gender, (float) $weight, (float) $height);
            onboarding_save_weight_log($pdo, $userId, (float) $weight);

            try {
                plan_save_preferences($pdo, $userId, [
                    'goal_mode' => $goalMode,
                    'weekly_rate' => $weeklyRate,
                    'activity_level' => $activityLevel,
                    'target_weight' => $targetWeight,
                ]);
            } catch (PDOException $prefError) {
                error_log('Onboarding plan preferences save failed: ' . $prefError->getMessage());
            }

            $stmt = $pdo->prepare('INSERT INTO userGoal (user_id, calorie_goal, date_set) VALUES (?, ?, NOW())');
            $stmt->execute([$userId, (int) $personalPlan['calorie_goal']]);

            log_attempt($pdo, $userId, 'onboarding_plan_commit', 'User committed onboarding personal plan', 'userGoal', (int) $pdo->lastInsertId());

            $pdo->commit();

            header('Location: dashboard.php?success=' . urlencode('Your personal plan is ready.'));
            exit();
        } catch (PDOException $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $error_message = 'Could not save your plan. Please try again.';
            error_log('Onboarding plan commit failed: ' . $e->getMessage());
        }
    }
}

$physicalInfo = [];
try {
    $physicalInfo = getPhysicalInfo($userId) ?: [];
} catch (PDOException $e) {
    error_log('Onboarding physical info load failed: ' . $e->getMessage());
}

$savedPrefs = null;
try {
    $savedPrefs = plan_load_preferences($pdo, $userId);
} catch (PDOException $e) {
    error_log('Onboarding preferences load failed: ' . $e->getMessage());
}

$validGenders = ['male', 'female', 'other'];
$defaultGender = $_POST['gender'] ?? ($physicalInfo['gender'] ?? 'male');
if (!in_array($defaultGender, $validGenders, true)) {
    $defaultGender = 'male';
}

$defaultAge = isset($_POST['age']) ? (int) $_POST['age'] : (int) ($physicalInfo['age'] ?? 25);
$defaultAge = max(13, min(100, $defaultAge > 0 ? $defaultAge : 25));

$defaultHeight = isset($_POST['height']) ? (int) $_POST['height'] : (int) ($physicalInfo['height'] ?? 170);
$defaultHeight = max(100, min(250, $defaultHeight > 0 ? $defaultHeight : 170));

$defaultWeight = isset($_POST['weight']) ? (int) $_POST['weight'] : (int) ($physicalInfo['weight'] ?? 65);
$defaultWeight = max(30, min(300, $defaultWeight > 0 ? $defaultWeight : 65));

$activityOptions = plan_activity_options();
$defaultActivity = $_POST['activity_level'] ?? ($savedPrefs['activity_level'] ?? 'moderately_active');
if (!isset($activityOptions[$defaultActivity])) {
    $defaultActivity = 'moderately_active';
}

$goalModes = plan_goal_modes();
$defaultGoal = $_POST['goal_mode'] ?? ($savedPrefs['goal_mode'] ?? 'lose');
if (!isset($goalModes[$defaultGoal])) {
    $defaultGoal = 'lose';
}

$defaultWeeklyRate = isset($_POST['weekly_rate']) && is_numeric($_POST['weekly_rate'])
    ? (float) $_POST['weekly_rate']
    : (float) ($savedPrefs['weekly_rate'] ?? 0.5);
if ($defaultGoal === 'maintain') {
    $defaultWeeklyRate = 0.0;
} else {
    $defaultWeeklyRate = max(0.1, min(1.5, $defaultWeeklyRate > 0 ? $defaultWeeklyRate : 0.5));
    $paceChoices = [0.25, 0.5, 0.75];
    $nearestPace = $paceChoices[0];
    foreach ($paceChoices as $paceChoice) {
        if (abs($defaultWeeklyRate - $paceChoice) < abs($defaultWeeklyRate - $nearestPace)) {
            $nearestPace = $paceChoice;
        }
    }
    $defaultWeeklyRate = $nearestPace;
}

$defaultTargetWeight = '';
if (isset($_POST['target_weight']) && $_POST['target_weight'] !== '' && is_numeric($_POST['target_weight'])) {
    $defaultTargetWeight = (string) max(30, min(300, (float) $_POST['target_weight']));
} elseif ($savedPrefs && $savedPrefs['target_weight'] !== null) {
    $defaultTargetWeight = (string) $savedPrefs['target_weight'];
}

$firstName = trim((string) ($_SESSION['user']['first_name'] ?? ''));
$displayName = $firstName !== '' ? $firstName : 'there';
$activityFactors = [];
foreach ($activityOptions as $key => $option) {
    $activityFactors[$key] = (float) $option['factor'];
}
$planConfig = [
    'activityFactors' => $activityFactors,
    'goalAdjustments' => plan_goal_adjustments(),
];
?>

<!DOCTYPE html>
<html lang="<?= html_lang_attr() ?>" data-theme="<?= htmlspecialchars($_SESSION['user']['theme_preference'] ?? 'system', ENT_QUOTES) ?>">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Your Personal Plan - BitBalance</title>
    <?php
    $pageCss = ['css/pages/set-goal.css'];
    include PROJECT_ROOT . 'views/head_css.php';
    ?>
    <script src="https://kit.fontawesome.com/b94f65ead2.js" crossorigin="anonymous"></script>
</head>

<body class="<?= htmlspecialchars($bodyClass, ENT_QUOTES) ?>">
    <main class="onboarding-shell">
        <form method="POST" id="onboardingForm" class="onboarding-card" novalidate>
            <?= csrf_field() ?>
            <input type="hidden" name="commit_personal_plan" value="1">
            <input type="hidden" name="gender" id="genderInput" value="<?= htmlspecialchars($defaultGender, ENT_QUOTES) ?>">
            <input type="hidden" name="age" id="ageInput" value="<?= (int) $defaultAge ?>">
            <input type="hidden" name="height" id="heightInput" value="<?= (int) $defaultHeight ?>">
            <input type="hidden" name="weight" id="weightInput" value="<?= (int) $defaultWeight ?>">
            <input type="hidden" name="activity_level" id="activityInput" value="<?= htmlspecialchars($defaultActivity, ENT_QUOTES) ?>">
            <input type="hidden" name="goal_mode" id="goalInput" value="<?= htmlspecialchars($defaultGoal, ENT_QUOTES) ?>">
            <input type="hidden" name="weekly_rate" id="weeklyRateInput" value="<?= htmlspecialchars((string) $defaultWeeklyRate, ENT_QUOTES) ?>">
            <input type="hidden" name="target_weight" id="targetWeightInput" value="<?= htmlspecialchars($defaultTargetWeight, ENT_QUOTES) ?>">

            <div class="onboarding-topbar">
                <button type="button" class="back-button is-hidden" id="wizardBack" aria-label="Back">
                    <i class="fas fa-arrow-left"></i>
                </button>
                <div class="progress-wrap" aria-label="Personalization progress">
                    <div class="progress-label">
                        <span id="progressTitle">Personalize</span>
                        <strong id="progressCount">1/6</strong>
                    </div>
                    <div class="progress-track">
                        <div class="progress-fill" id="progressFill"></div>
                    </div>
                </div>
            </div>

            <?php if ($error_message): ?>
                <div class="onboarding-alert">
                    <i class="fas fa-triangle-exclamation"></i>
                    <span><?= htmlspecialchars($error_message, ENT_QUOTES) ?></span>
                </div>
            <?php endif; ?>

            <section class="wizard-step is-active" data-step="0" data-kind="input">
                <span class="step-kicker">Welcome, <?= htmlspecialchars($displayName, ENT_QUOTES) ?></span>
                <h1>Who are you?</h1>
                <p>BitBalance uses this to estimate your daily energy burn.</p>

                <div class="choice-grid gender-grid" data-choice-group="gender">
                    <button type="button" class="choice-card <?= $defaultGender === 'male' ? 'is-selected' : '' ?>" data-choice="gender" data-value="male">
                        <i class="fas fa-mars"></i>
                        <span>Male</span>
                    </button>
                    <button type="button" class="choice-card <?= $defaultGender === 'female' ? 'is-selected' : '' ?>" data-choice="gender" data-value="female">
                        <i class="fas fa-venus"></i>
                        <span>Female</span>
                    </button>
                    <button type="button" class="choice-card <?= $defaultGender === 'other' ? 'is-selected' : '' ?>" data-choice="gender" data-value="other">
                        <i class="fas fa-circle"></i>
                        <span>Other</span>
                    </button>
                </div>

                <button type="button" class="wizard-primary" data-next>Continue</button>
            </section>

            <section class="wizard-step" data-step="1" data-kind="input">
                <span class="step-kicker">Body metrics</span>
                <h1>What is your age?</h1>
                <p>Scroll and tap your age.</p>

                <div class="scroll-picker" data-picker="age" aria-label="Choose age">
                    <?php for ($age = 13; $age <= 100; $age++): ?>
                        <button type="button" class="picker-option <?= $age === $defaultAge ? 'is-selected' : '' ?>" data-value="<?= $age ?>">
                            <strong><?= $age ?></strong>
                        </button>
                    <?php endfor; ?>
                </div>

                <button type="button" class="wizard-primary" data-next>Continue</button>
            </section>

            <section class="wizard-step" data-step="2" data-kind="input">
                <span class="step-kicker">Body metrics</span>
                <h1>How tall are you?</h1>
                <p>Scroll to choose your height.</p>

                <div class="scroll-picker" data-picker="height" aria-label="Choose height">
                    <?php for ($height = 100; $height <= 250; $height++): ?>
                        <button type="button" class="picker-option <?= $height === $defaultHeight ? 'is-selected' : '' ?>" data-value="<?= $height ?>">
                            <strong><?= $height ?></strong>
                        </button>
                    <?php endfor; ?>
                </div>

                <button type="button" class="wizard-primary" data-next>Continue</button>
            </section>

            <section class="wizard-step" data-step="3" data-kind="input">
                <span class="step-kicker">Body metrics</span>
                <h1>What is your weight?</h1>
                <p>Scroll to choose your current weight.</p>

                <div class="scroll-picker" data-picker="weight" aria-label="Choose weight">
                    <?php for ($weight = 30; $weight <= 300; $weight++): ?>
                        <button type="button" class="picker-option <?= $weight === $defaultWeight ? 'is-selected' : '' ?>" data-value="<?= $weight ?>">
                            <strong><?= $weight ?></strong>
                        </button>
                    <?php endfor; ?>
                </div>

                <button type="button" class="wizard-primary" data-next>Continue</button>
            </section>

            <section class="wizard-step" data-step="4" data-kind="input">
                <span class="step-kicker">Daily routine</span>
                <h1>Activity level</h1>
                <p>Choose the option that looks most like a normal week.</p>

                <div class="choice-list" data-choice-group="activity_level">
                    <?php foreach ($activityOptions as $key => $option): ?>
                        <button type="button" class="choice-card wide <?= $key === $defaultActivity ? 'is-selected' : '' ?>" data-choice="activity_level" data-value="<?= htmlspecialchars($key, ENT_QUOTES) ?>">
                            <i class="fas fa-person-running"></i>
                            <span>
                                <strong><?= htmlspecialchars($option['label'], ENT_QUOTES) ?></strong>
                                <small><?= htmlspecialchars($option['detail'], ENT_QUOTES) ?></small>
                            </span>
                        </button>
                    <?php endforeach; ?>
                </div>

                <button type="button" class="wizard-primary" data-next>Continue</button>
            </section>

            <section class="wizard-step" data-step="5" data-kind="input">
                <span class="step-kicker">Goal setup</span>
                <h1>What is your goal?</h1>
                <p>This sets the calorie target we will use on your dashboard.</p>

                <div class="choice-grid goal-grid" data-choice-group="goal_mode">
                    <?php foreach ($goalModes as $key => $mode): ?>
                        <button type="button" class="choice-card goal-card <?= $key === $defaultGoal ? 'is-selected' : '' ?>" data-choice="goal_mode" data-value="<?= htmlspecialchars($key, ENT_QUOTES) ?>">
                            <i class="fas <?= htmlspecialchars($mode['icon'], ENT_QUOTES) ?>"></i>
                            <span><?= htmlspecialchars($key === 'lose' ? 'Lose' : ($key === 'gain' ? 'Gain' : 'Maintain'), ENT_QUOTES) ?></span>
                            <small><?= htmlspecialchars($mode['copy'], ENT_QUOTES) ?></small>
                        </button>
                    <?php endforeach; ?>
                </div>

                <button type="button" class="wizard-primary" id="goalContinueButton">Continue</button>
            </section>

            <section class="wizard-step pace-step" data-step="6" data-kind="input">
                <span class="step-kicker">Goal pace</span>
                <h1>How fast?</h1>
                <p id="paceDescription">Pick a weekly pace. Target weight is optional.</p>

                <div class="pace-options" data-choice-group="weekly_rate">
                    <button type="button" class="choice-card pace-card <?= abs($defaultWeeklyRate - 0.25) < 0.001 ? 'is-selected' : '' ?>" data-choice="weekly_rate" data-value="0.25">
                        <i class="fas fa-feather"></i>
                        <span>Gentle</span>
                        <small>0.25 kg/week</small>
                    </button>
                    <button type="button" class="choice-card pace-card <?= abs($defaultWeeklyRate - 0.5) < 0.001 ? 'is-selected' : '' ?>" data-choice="weekly_rate" data-value="0.5">
                        <i class="fas fa-bolt"></i>
                        <span>Steady</span>
                        <small>0.5 kg/week</small>
                    </button>
                    <button type="button" class="choice-card pace-card <?= abs($defaultWeeklyRate - 0.75) < 0.001 ? 'is-selected' : '' ?>" data-choice="weekly_rate" data-value="0.75">
                        <i class="fas fa-fire"></i>
                        <span>Fast</span>
                        <small>0.75 kg/week</small>
                    </button>
                </div>

                <label class="optional-weight-field" for="targetWeightField">
                    <span>Target weight <small>optional</small></span>
                    <input type="number" inputmode="decimal" min="30" max="300" step="0.1" id="targetWeightField"
                        value="<?= htmlspecialchars($defaultTargetWeight, ENT_QUOTES) ?>" placeholder="e.g. 60">
                </label>

                <button type="button" class="wizard-primary" id="getPlanButton">Get my personal plan</button>
            </section>

            <section class="wizard-step loading-step" data-step="7" data-kind="loading" aria-live="polite">
                <div class="loading-ring" id="loadingRing">
                    <span id="loadingPercent">0%</span>
                </div>
                <h1>AI is calculating...</h1>
                <p>Building a personalized plan for you...</p>

                <div class="loading-checklist">
                    <div class="loading-item" data-loading-item>
                        <i class="fas fa-check"></i>
                        <span>Analyzing body metrics</span>
                    </div>
                    <div class="loading-item" data-loading-item>
                        <i class="fas fa-check"></i>
                        <span>Estimating daily energy burn</span>
                    </div>
                    <div class="loading-item" data-loading-item>
                        <i class="fas fa-check"></i>
                        <span>Setting calorie target</span>
                    </div>
                    <div class="loading-item" data-loading-item>
                        <i class="fas fa-check"></i>
                        <span>Distributing macronutrients</span>
                    </div>
                    <div class="loading-item" data-loading-item>
                        <i class="fas fa-check"></i>
                        <span>Personalizing hydration</span>
                    </div>
                </div>
            </section>

            <section class="wizard-step overview-step" data-step="8" data-kind="overview">
                <span class="ready-pill"><i class="fas fa-wand-magic-sparkles"></i> Your personal plan is ready</span>
                <h1>Your Plan</h1>

                <div class="overview-summary">
                    <div class="summary-icon"><i class="fas fa-equals"></i></div>
                    <p>Based on body metrics and personal goals</p>
                </div>

                <div class="metric-grid">
                    <div class="plan-metric-card calories">
                        <span><i class="fas fa-fire"></i> Calories</span>
                        <strong><span id="overviewCalories">0</span> <small>kcal</small></strong>
                    </div>
                    <div class="plan-metric-card protein">
                        <span><i class="fas fa-bolt"></i> Protein</span>
                        <strong><span id="overviewProtein">0</span> <small>g/day</small></strong>
                    </div>
                    <div class="plan-metric-card carbs">
                        <span><i class="fas fa-leaf"></i> Carbs</span>
                        <strong><span id="overviewCarbs">0</span> <small>g/day</small></strong>
                    </div>
                    <div class="plan-metric-card fat">
                        <span><i class="fas fa-circle"></i> Fat</span>
                        <strong><span id="overviewFat">0</span> <small>g/day</small></strong>
                    </div>
                </div>

                <div class="plan-context">
                    <div>
                        <span>BMR</span>
                        <strong><span id="overviewBmr">0</span> kcal</strong>
                    </div>
                    <div>
                        <span>Energy burn</span>
                        <strong><span id="overviewTdee">0</span> kcal</strong>
                    </div>
                    <div>
                        <span>Water target</span>
                        <strong><span id="overviewWater">0</span> ml</strong>
                    </div>
                </div>

                <div class="plan-disclaimer">
                    <i class="fas fa-circle-info"></i>
                    <span>Data and advice from the app are for general reference only. Please consult a doctor or healthcare professional before changing your diet.</span>
                </div>

                <button type="submit" class="wizard-primary commit-button">
                    <i class="fas fa-check"></i>
                    Commit to my goal
                </button>
            </section>
        </form>
    </main>

    <script>
        window.BitBalancePlanConfig = <?= json_encode($planConfig) ?>;
    </script>
    <script>
        (function () {
            var form = document.getElementById('onboardingForm');
            var steps = Array.prototype.slice.call(document.querySelectorAll('.wizard-step'));
            var backButton = document.getElementById('wizardBack');
            var progressFill = document.getElementById('progressFill');
            var progressCount = document.getElementById('progressCount');
            var progressTitle = document.getElementById('progressTitle');
            var goalContinueButton = document.getElementById('goalContinueButton');
            var getPlanButton = document.getElementById('getPlanButton');
            var targetWeightField = document.getElementById('targetWeightField');
            var paceDescription = document.getElementById('paceDescription');
            var loadingItems = Array.prototype.slice.call(document.querySelectorAll('[data-loading-item]'));
            var loadingRing = document.getElementById('loadingRing');
            var loadingPercent = document.getElementById('loadingPercent');
            var config = window.BitBalancePlanConfig || {};
            var currentStep = 0;
            var values = {
                gender: document.getElementById('genderInput').value,
                age: parseInt(document.getElementById('ageInput').value, 10),
                height: parseInt(document.getElementById('heightInput').value, 10),
                weight: parseInt(document.getElementById('weightInput').value, 10),
                activity_level: document.getElementById('activityInput').value,
                goal_mode: document.getElementById('goalInput').value,
                weekly_rate: parseFloat(document.getElementById('weeklyRateInput').value) || 0.5,
                target_weight: document.getElementById('targetWeightInput').value
            };
            var hiddenInputs = {
                gender: document.getElementById('genderInput'),
                age: document.getElementById('ageInput'),
                height: document.getElementById('heightInput'),
                weight: document.getElementById('weightInput'),
                activity_level: document.getElementById('activityInput'),
                goal_mode: document.getElementById('goalInput'),
                weekly_rate: document.getElementById('weeklyRateInput'),
                target_weight: document.getElementById('targetWeightInput')
            };

            function shouldUsePaceStep() {
                return values.goal_mode === 'lose' || values.goal_mode === 'gain';
            }

            function inputStepTotal() {
                return shouldUsePaceStep() ? 7 : 6;
            }

            function inputStepPosition(stepIndex) {
                return stepIndex <= 5 ? stepIndex + 1 : inputStepTotal();
            }

            function setStep(stepIndex) {
                currentStep = stepIndex;
                steps.forEach(function (step) {
                    step.classList.toggle('is-active', parseInt(step.getAttribute('data-step'), 10) === stepIndex);
                });

                var isLoading = stepIndex === 7;
                backButton.classList.toggle('is-hidden', stepIndex === 0 || isLoading);

                if (stepIndex <= 6) {
                    progressTitle.textContent = 'Personalize';
                    progressCount.textContent = inputStepPosition(stepIndex) + '/' + inputStepTotal();
                    progressFill.style.width = ((inputStepPosition(stepIndex) / inputStepTotal()) * 100) + '%';
                } else if (isLoading) {
                    progressTitle.textContent = 'Calculating';
                    progressCount.textContent = '99%';
                    progressFill.style.width = '99%';
                } else {
                    progressTitle.textContent = 'Plan ready';
                    progressCount.textContent = '100%';
                    progressFill.style.width = '100%';
                }

                window.requestAnimationFrame(function () {
                    centerActivePicker(stepIndex);
                });
            }

            function setValue(name, value) {
                values[name] = value;
                if (hiddenInputs[name]) {
                    hiddenInputs[name].value = value;
                }

                document.querySelectorAll('[data-choice="' + name + '"]').forEach(function (button) {
                    button.classList.toggle('is-selected', button.getAttribute('data-value') === String(value));
                });

                if (name === 'goal_mode') {
                    syncGoalButton();
                    syncPaceCopy();
                }
            }

            function syncGoalButton() {
                if (goalContinueButton) {
                    goalContinueButton.textContent = shouldUsePaceStep() ? 'Continue' : 'Get my personal plan';
                }
            }

            function syncPaceCopy() {
                if (!paceDescription) {
                    return;
                }
                paceDescription.textContent = values.goal_mode === 'gain'
                    ? 'Pick a weekly gain pace. Target weight is optional.'
                    : 'Pick a weekly deficit pace. Target weight is optional.';
            }

            function selectPickerOption(picker, option, shouldCenter) {
                var name = picker.getAttribute('data-picker');
                if (!name || !option) {
                    return;
                }

                picker.querySelectorAll('.picker-option').forEach(function (item) {
                    item.classList.toggle('is-selected', item === option);
                });
                setValue(name, parseInt(option.getAttribute('data-value'), 10));

                if (shouldCenter) {
                    option.scrollIntoView({ block: 'center', behavior: 'smooth' });
                }
            }

            function syncPickerFromScroll(picker) {
                var pickerRect = picker.getBoundingClientRect();
                var center = pickerRect.top + pickerRect.height / 2;
                var closest = null;
                var closestDistance = Infinity;

                picker.querySelectorAll('.picker-option').forEach(function (option) {
                    var rect = option.getBoundingClientRect();
                    var optionCenter = rect.top + rect.height / 2;
                    var distance = Math.abs(optionCenter - center);
                    if (distance < closestDistance) {
                        closestDistance = distance;
                        closest = option;
                    }
                });

                selectPickerOption(picker, closest, false);
            }

            function centerActivePicker(stepIndex) {
                var activeStep = document.querySelector('.wizard-step[data-step="' + stepIndex + '"]');
                if (!activeStep) {
                    return;
                }
                var picker = activeStep.querySelector('.scroll-picker');
                var selected = picker ? picker.querySelector('.picker-option.is-selected') : null;
                if (selected) {
                    selected.scrollIntoView({ block: 'center' });
                    window.setTimeout(function () {
                        syncPickerFromScroll(picker);
                    }, 80);
                }
            }

            function syncVisiblePickers() {
                document.querySelectorAll('.wizard-step.is-active .scroll-picker').forEach(syncPickerFromScroll);
            }

            document.querySelectorAll('[data-next]').forEach(function (button) {
                button.addEventListener('click', function () {
                    setStep(Math.min(currentStep + 1, 5));
                });
            });

            backButton.addEventListener('click', function () {
                if (currentStep > 0 && currentStep !== 7) {
                    setStep(currentStep > 5 ? 5 : currentStep - 1);
                }
            });

            document.querySelectorAll('[data-choice]').forEach(function (button) {
                button.addEventListener('click', function () {
                    setValue(button.getAttribute('data-choice'), button.getAttribute('data-value'));
                });
            });

            if (targetWeightField) {
                targetWeightField.addEventListener('input', function () {
                    setValue('target_weight', targetWeightField.value.trim());
                });
            }

            document.querySelectorAll('.scroll-picker').forEach(function (picker) {
                var scrollTimer = null;
                picker.querySelectorAll('.picker-option').forEach(function (option) {
                    option.addEventListener('click', function () {
                        selectPickerOption(picker, option, true);
                    });
                });
                picker.addEventListener('scroll', function () {
                    window.clearTimeout(scrollTimer);
                    scrollTimer = window.setTimeout(function () {
                        syncPickerFromScroll(picker);
                    }, 80);
                }, { passive: true });

            });

            function clamp(value, min, max) {
                return Math.max(min, Math.min(max, value));
            }

            function calculatePlan() {
                var genderOffset = values.gender === 'female' ? -161 : (values.gender === 'other' ? -78 : 5);
                var bmr = 10 * values.weight + 6.25 * values.height - 5 * values.age + genderOffset;
                var factors = config.activityFactors || {};
                var factor = parseFloat(factors[values.activity_level] || 1.55);
                var weeklyRate = shouldUsePaceStep() ? parseFloat(values.weekly_rate || 0.5) : 0;
                var adjustment = Math.round((weeklyRate * 7700) / 7);
                if (values.goal_mode === 'lose') {
                    adjustment = -adjustment;
                } else if (values.goal_mode === 'maintain') {
                    adjustment = 0;
                }
                var tdee = bmr * factor;
                var calories = clamp(Math.round(tdee + adjustment), 800, 10000);

                return {
                    bmr: Math.round(bmr),
                    tdee: Math.round(tdee),
                    calories: calories,
                    protein: Math.round((calories * 0.30) / 4),
                    carbs: Math.round((calories * 0.45) / 4),
                    fat: Math.round((calories * 0.25) / 9),
                    water: Math.round((values.weight * 35) / 250) * 250
                };
            }

            function fillOverview(plan) {
                document.getElementById('overviewCalories').textContent = plan.calories;
                document.getElementById('overviewProtein').textContent = plan.protein;
                document.getElementById('overviewCarbs').textContent = plan.carbs;
                document.getElementById('overviewFat').textContent = plan.fat;
                document.getElementById('overviewBmr').textContent = plan.bmr;
                document.getElementById('overviewTdee').textContent = plan.tdee;
                document.getElementById('overviewWater').textContent = plan.water;
            }

            function runLoading(plan) {
                var percent = 0;
                loadingItems.forEach(function (item) {
                    item.classList.remove('is-complete');
                });
                setStep(7);

                var timer = window.setInterval(function () {
                    percent += 4;
                    if (percent > 100) {
                        percent = 100;
                    }
                    loadingPercent.textContent = percent + '%';
                    loadingRing.style.setProperty('--progress', percent + '%');
                    loadingItems.forEach(function (item, index) {
                        if (percent >= (index + 1) * 18) {
                            item.classList.add('is-complete');
                        }
                    });

                    if (percent >= 100) {
                        window.clearInterval(timer);
                        window.setTimeout(function () {
                            fillOverview(plan);
                            setStep(8);
                        }, 350);
                    }
                }, 90);
            }

            goalContinueButton.addEventListener('click', function () {
                if (shouldUsePaceStep()) {
                    setStep(6);
                    return;
                }

                setValue('weekly_rate', '0');
                setValue('target_weight', '');
                runLoading(calculatePlan());
            });

            getPlanButton.addEventListener('click', function () {
                syncVisiblePickers();
                runLoading(calculatePlan());
            });

            form.addEventListener('submit', function () {
                syncVisiblePickers();
            });

            syncGoalButton();
            syncPaceCopy();
            setStep(0);
        })();
    </script>
</body>

</html>
