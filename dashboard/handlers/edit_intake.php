<?php
require_once __DIR__ . '/../../include/init.php';
require_once __DIR__ . '/functions.php';
// require_once __DIR__ . '/../../include/db_config.php'; // Đã có trong init.php

header('Content-Type: application/json');

if (!isset($_SESSION['user']) || $_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['ok' => false, 'error' => 'Unauthorized']);
    exit();
}

$userId = $_SESSION['user']['user_id'];
$intakeId = $_POST['intake_id'] ?? null;
$foodItem = trim($_POST['food_item'] ?? '');
$calories = intval($_POST['calories'] ?? 0);
$category = $_POST['meal_category'] ?? '';
$protein  = isset($_POST['protein']) && $_POST['protein'] !== '' ? (float) $_POST['protein'] : 0;
$carbs    = isset($_POST['carbs'])   && $_POST['carbs']   !== '' ? (float) $_POST['carbs']   : 0;
$fat      = isset($_POST['fat'])     && $_POST['fat']     !== '' ? (float) $_POST['fat']     : 0;

if (!$intakeId || empty($foodItem) || $calories <= 0 || empty($category)) {
    echo json_encode(['ok' => false, 'error' => 'Invalid input']);
    exit();
}

foreach (['protein', 'carbs', 'fat'] as $m) {
    if ($$m < 0 || $$m > 999) {
        echo json_encode(['ok' => false, 'error' => ucfirst($m) . ' must be between 0 and 999 grams.']);
        exit();
    }
}

try {
    // 1. Update bản ghi
    $stmt = $pdo->prepare("UPDATE intakeLog SET food_item = ?, calories = ?, protein = ?, carbs = ?, fat = ?, meal_category = ? WHERE intakeLog_id = ? AND user_id = ?");
    $stmt->execute([$foodItem, $calories, $protein, $carbs, $fat, $category, $intakeId, $userId]);

    if ($stmt->rowCount() > 0) {
        // --- LOGIC MỚI: TÍNH TOÁN LẠI TỔNG CALO SAU KHI SỬA ---
        
        // A. Tính tổng Calo hôm nay
        $tot = $pdo->prepare("
            SELECT COALESCE(SUM(calories), 0)
            FROM intakeLog
            WHERE user_id = ? AND DATE(date_intake) = CURDATE()
        ");
        $tot->execute([$userId]);
        $totalCalories = (int) $tot->fetchColumn();

        // B. Lấy Goal mới nhất
        $goalStmt = $pdo->prepare("
            SELECT calorie_goal
            FROM userGoal
            WHERE user_id = ?
            ORDER BY date_set DESC
            LIMIT 1
        ");
        $goalStmt->execute([$userId]);
        $goal = (int) ($goalStmt->fetchColumn() ?? 0);

        // C. Tính %
        $percentage = $goal ? min(100, round($totalCalories / $goal * 100)) : 0;

        // D. Macro totals + goals
        $macroTotals = getMacroTotalsToday($userId);
        $macroGoals  = resolveMacroGoals($userId);

        // Trả về JSON đầy đủ dữ liệu
        echo json_encode([
            'ok' => true,
            'intake_id' => $intakeId,
            'food_item' => $foodItem,
            'calories' => $calories,
            'protein' => $protein,
            'carbs' => $carbs,
            'fat' => $fat,
            'meal_category' => $category,
            // Dữ liệu để update Progress Bar
            'total_calories' => $totalCalories,
            'percentage' => $percentage,
            'macros' => $macroTotals,
            'macro_goals' => $macroGoals
        ], JSON_UNESCAPED_UNICODE); // Quan trọng: Giữ tiếng Việt không bị lỗi font
    } else {
        echo json_encode(['ok' => true, 'message' => 'No changes made'], JSON_UNESCAPED_UNICODE);
    }
} catch (PDOException $e) {
    echo json_encode(['ok' => false, 'error' => 'Database error: ' . $e->getMessage()]);
}
?>