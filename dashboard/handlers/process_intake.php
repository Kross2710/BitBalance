<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/../../include/init.php';
require_once __DIR__ . '/../../include/db_config.php';
require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/../../include/handlers/log_attempt.php';


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
        $stmt = $pdo->prepare("INSERT INTO intakeLog (user_id, food_item, calories, meal_category) VALUES (?, ?, ?, ?)");
        if (!$stmt) {
            $error_message = 'Database error: ' . implode(' ', $pdo->errorInfo());
            if ($isAjax) {
                header('Content-Type: application/json');
                echo json_encode(['ok' => false, 'error' => $error_message]);
                exit;
            }
        } else {
            // Use array-style execute
            if ($stmt->execute([$user_id, $food_item, $calories, $meal_category])) {
                // Update logging streak
                try {
                    updateLoggingStreak($pdo, $user_id);
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

                    $newRow = '
                        <tr data-id="' . $newId . '">
                            <td data-label="Food Item" style="font-weight:bold;">' . htmlspecialchars($food_item) . '</td>
                            <td data-label="Calories">' . htmlspecialchars($calories) . '</td>
                            <td data-label="Category">' . htmlspecialchars(ucfirst($meal_category)) . '</td>
                            <td data-label="Logged At" data-utc="' . gmdate('Y-m-d\TH:i:s\Z') . '"></td>
                            <td>
                                <button type="button" class="deleteBtn"
                                    style="background:#e55039;color:#fff;border:none;border-radius:4px;padding:4px 10px;cursor:pointer;">
                                    Delete
                                </button>
                            </td>
                        </tr>';

                    // query new daily total + percentage
                    $totalStmt = $pdo->prepare("SELECT COALESCE(SUM(calories),0) FROM intakeLog WHERE user_id = ? AND DATE(date_intake)=CURDATE()");
                    $totalStmt->execute([$user_id]);
                    $totalCalories = (int) $totalStmt->fetchColumn();
                    $goal = $userGoal;
                    $pct = $goal ? min(100, round($totalCalories / $goal * 100)) : 0;

                    // Log the attempt
                    log_attempt($pdo, $user_id, 'log_intake', 'User logged intake' , 'intakeLog', $newId);

                    header('Content-Type: application/json');
                    echo json_encode([
                        'ok' => true,
                        'new_row' => $newRow,
                        'total' => $totalCalories,
                        'percentage' => $pct
                    ]);
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