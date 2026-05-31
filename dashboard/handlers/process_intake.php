<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/../../include/init.php';
require_once __DIR__ . '/../../include/db_config.php';
require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/../../include/handlers/log_attempt.php';
require_once __DIR__ . '/../../include/handlers/xp.php';


$error_message = '';
$success_message = '';

// Listen for form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Detect AJAX (fetch)
    $isAjax = isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'fetch';
    $user_id = $_SESSION['user']['user_id']; // Get user ID from session
    $food_item = $_POST['food_item'];
    $calories = $_POST['calories'];
    $meal_category = $_POST['meal_category']; // Optional meal category
    $image_path = isset($_POST['image_path']) && $_POST['image_path'] !== '' ? trim($_POST['image_path']) : null;
    if ($image_path !== null && strpos($image_path, 'uploads/intake/') !== 0) {
        $image_path = null;
    }

    // Macros (optional, default 0). Clamp to a sane range to avoid garbage.
    $protein = isset($_POST['protein']) && $_POST['protein'] !== '' ? (float) $_POST['protein'] : 0;
    $carbs   = isset($_POST['carbs'])   && $_POST['carbs']   !== '' ? (float) $_POST['carbs']   : 0;
    $fat     = isset($_POST['fat'])     && $_POST['fat']     !== '' ? (float) $_POST['fat']     : 0;
    foreach (['protein', 'carbs', 'fat'] as $m) {
        if ($$m < 0 || $$m > 999) {
            $error_message = ucfirst($m) . ' must be between 0 and 999 grams.';
            if ($isAjax) {
                header('Content-Type: application/json');
                echo json_encode(['ok' => false, 'error' => $error_message]);
                exit;
            }
        }
    }

    // Optional backdating: log to a chosen day (defaults to today). The Intake
    // page passes the date it's currently showing. Rules: never the future, and
    // the logging streak is only bumped for *today's* logs (see below).
    $today = date('Y-m-d');
    $logDate = $today;
    if (isset($_POST['date']) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $_POST['date'])) {
        $logDate = $_POST['date'];
    }
    if ($logDate > $today) {
        $logDate = $today;
    }
    $isToday = ($logDate === $today);
    // Keep the real time-of-day so entries within a backdated day still order sensibly.
    $logDateTime = $logDate . ' ' . date('H:i:s');

    // Validate input
    if ($food_item == '' || $calories == '' || $meal_category == '') {
        $error_message = 'Please fill in all fields.';
        if ($isAjax) {
            header('Content-Type: application/json');
            echo json_encode(['ok' => false, 'error' => $error_message]);
            exit;
        }
    } elseif (!is_numeric($calories) || $calories <= 0) {
        $error_message = 'Calories must be a positive number.';
        if ($isAjax) {
            header('Content-Type: application/json');
            echo json_encode(['ok' => false, 'error' => $error_message]);
            exit;
        }
    } elseif (!is_numeric($calories) || $calories > 5000) {
        $error_message = 'Calories must be a number between 1 and 5000.';
        if ($isAjax) {
            header('Content-Type: application/json');
            echo json_encode(['ok' => false, 'error' => $error_message]);
            exit;
        }
    } elseif (!in_array($meal_category, ['breakfast', 'lunch', 'dinner', 'snack'])) {
        $error_message = 'Invalid meal category.';
        if ($isAjax) {
            header('Content-Type: application/json');
            echo json_encode(['ok' => false, 'error' => $error_message]);
            exit;
        }
    } else {
        // Prepare and execute the SQL statement
        $stmt = $pdo->prepare("INSERT INTO intakeLog (user_id, food_item, calories, protein, carbs, fat, meal_category, image_path, date_intake) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
        if (!$stmt) {
            $error_message = 'Database error: ' . implode(' ', $pdo->errorInfo());
            if ($isAjax) {
                header('Content-Type: application/json');
                echo json_encode(['ok' => false, 'error' => $error_message]);
                exit;
            }
        } else {
            // Use array-style execute
            if ($stmt->execute([$user_id, $food_item, $calories, $protein, $carbs, $fat, $meal_category, $image_path, $logDateTime])) {
                // Award XP (state-based, idempotent — log+delete spam can't farm).
                $xpResult = ['xp_added' => 0, 'leveled_up' => false];
                try {
                    $xpResult = xp_award_intake_log($pdo, $user_id);
                } catch (Throwable $e) {
                    // Don't fail the request if XP system errors — it's secondary.
                    error_log('xp_award_intake_log: ' . $e->getMessage());
                }

                // Update logging streak — ONLY for today's logs. Backdated entries
                // must not inflate the current streak (recomputing historical
                // streaks is out of scope).
                if ($isToday) {
                    try {
                        updateLoggingStreak($pdo, $user_id);
                        // Streak milestones may have just been crossed
                        $streakRow = $pdo->prepare("SELECT logging_streak FROM userStatus WHERE user_id = ?");
                        $streakRow->execute([$user_id]);
                        $newStreak = (int) $streakRow->fetchColumn();
                        $milestoneRes = xp_award_streak_milestone($pdo, $user_id, $newStreak);
                        $xpResult['xp_added']   += $milestoneRes['xp_added'] ?? 0;
                        $xpResult['leveled_up'] = $xpResult['leveled_up'] || !empty($milestoneRes['leveled_up']);
                    } catch (Throwable $e) {
                        if ($isAjax) {
                            header('Content-Type: application/json');
                            echo json_encode([
                                'ok' => false,
                                'error' => 'Failed to update logging streak: ' . $e->getMessage()
                            ]);
                            exit;
                        } else {
                            $error_message = 'Failed to update logging streak: ' . $e->getMessage();
                        }
                    }
                }

                // Successful insert – if AJAX return JSON
                // Get latest goal from userGoal table
                $goalStmt = $pdo->prepare("
                    SELECT calorie_goal
                    FROM userGoal
                    WHERE user_id = ?
                    ORDER BY date_set DESC
                    LIMIT 1
                ");
                $goalStmt->execute([$user_id]);
                $userGoal = (int) ($goalStmt->fetchColumn() ?? 0);
                if ($isAjax) {
                    $idStmt = $pdo->prepare("
                        SELECT intakeLog_id
                        FROM intakeLog
                        WHERE user_id = ?
                        ORDER BY intakeLog_id DESC
                        LIMIT 1
                    ");
                    $idStmt->execute([$user_id]);
                    $newId = (int) $idStmt->fetchColumn();

                    // Render the new row via the shared partial so its markup stays
                    // identical to rows produced by dashboard-intake.php / dashboard-history.php.
                    $entry = [
                        'intakeLog_id'  => $newId,
                        'food_item'     => $food_item,
                        'calories'      => $calories,
                        'protein'       => $protein,
                        'carbs'         => $carbs,
                        'fat'           => $fat,
                        'meal_category' => $meal_category,
                        'image_path'    => $image_path,
                        'date_intake'   => $logDateTime,
                    ];
                    $showDate  = false;
                    $timeLabel = 'Just now';
                    ob_start();
                    include PROJECT_ROOT . 'dashboard/views/_intake-row.php';
                    $newRow = ob_get_clean();

                    // query new daily total + percentage for the LOGGED day
                    $totalStmt = $pdo->prepare("SELECT COALESCE(SUM(calories),0) FROM intakeLog WHERE user_id = ? AND DATE(date_intake)=?");
                    $totalStmt->execute([$user_id, $logDate]);
                    $totalCalories = (int) $totalStmt->fetchColumn();
                    $goal = $userGoal;
                    $pct = $goal ? min(100, round($totalCalories / $goal * 100)) : 0;

                    // Daily macro totals for the LOGGED day
                    $macroTotals = getMacroTotalsToday($user_id, $logDate);
                    $macroGoals  = getMacroGoalsFromCalorieGoal($goal);

                    // Log the attempt
                    log_attempt($pdo, $user_id, 'log_intake', 'User logged intake', 'intakeLog', $newId);

                    $xpSummary = xp_get_summary($pdo, $user_id);
                    $levelUpFlash = xp_consume_levelup_flash();

                    header('Content-Type: application/json');
                    echo json_encode([
                        'ok' => true,
                        'new_row' => $newRow,
                        'date' => $logDate,
                        'is_today' => $isToday,
                        'total' => $totalCalories,
                        'percentage' => $pct,
                        'macros' => $macroTotals,
                        'macro_goals' => $macroGoals,
                        'xp' => [
                            'added'   => (int) ($xpResult['xp_added'] ?? 0),
                            'summary' => $xpSummary,
                            'levelup' => $levelUpFlash,
                        ],
                    ], JSON_UNESCAPED_UNICODE);
                    exit;
                }
                // fallback: regular redirect
                $success_message = 'Intake logged successfully.';
                header("Location: ../dashboard-intake.php?success=" . urlencode($success_message));
                exit();
            } else {
                $error_message = 'Database error: ' . implode(' ', $stmt->errorInfo());
                if ($isAjax) {
                    header('Content-Type: application/json');
                    echo json_encode(['ok' => false, 'error' => $error_message]);
                    exit;
                }
            }
        }
    }

    // If there was an error, redirect back with the error message (non-AJAX fallback)
    if (isset($error_message)) {
        header("Location: ../dashboard-intake.php?error=" . urlencode($error_message));
        exit();
    }
}