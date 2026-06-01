<?php
// 1. CẤM PHP in lỗi/warning ra màn hình làm hỏng JSON
ini_set('display_errors', 0);
error_reporting(E_ALL);

// 2. Bắt đầu bộ đệm để hứng bất kỳ khoảng trắng thừa nào
ob_start();

// Set Header JSON ngay lập tức
header('Content-Type: application/json; charset=utf-8');

try {
    require_once __DIR__ . '/../../include/init.php';
    require_once __DIR__ . '/functions.php';
    // require_once __DIR__ . '/../../include/db_config.php'; // Bỏ comment nếu init chưa gọi
    require_once __DIR__ . '/../../include/handlers/log_attempt.php';

    $isAjax = isset($_SERVER['HTTP_X_REQUESTED_WITH']) &&
              strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'fetch';

    if (!isset($_SESSION['user'])) {
        throw new Exception('Not authorised');
    }

    $userId   = $_SESSION['user']['user_id'];
    $intakeId = $_POST['intake_id'] ?? null;

    if (!$intakeId) {
        throw new Exception('Missing intake ID');
    }

    // Snapshot the row BEFORE deleting so the client can offer an Undo that
    // re-inserts it with its original date/time (see restore_intake.php).
    $rowStmt = $pdo->prepare("SELECT food_item, calories, protein, carbs, fat, meal_category, image_path, date_intake
                              FROM intakeLog WHERE intakeLog_id = ? AND user_id = ?");
    $rowStmt->execute([$intakeId, $userId]);
    $deletedRow = $rowStmt->fetch(PDO::FETCH_ASSOC) ?: null;

    // Thực hiện Xóa
    $del = $pdo->prepare("DELETE FROM intakeLog WHERE intakeLog_id = ? AND user_id = ?");
    $ok  = $del->execute([$intakeId, $userId]);

    if (!$ok) {
        throw new Exception('Delete operation failed');
    }

    /* --- Tính toán lại Total --- */
    $tot = $pdo->prepare("SELECT COALESCE(SUM(calories),0) FROM intakeLog WHERE user_id = ? AND DATE(date_intake) = CURDATE()");
    $tot->execute([$userId]);
    $totalCalories = (int)$tot->fetchColumn();

    // Lấy Goal
    $goalStmt = $pdo->prepare("SELECT calorie_goal FROM userGoal WHERE user_id = ? ORDER BY date_set DESC LIMIT 1");
    $goalStmt->execute([$userId]);
    $goal = (int)($goalStmt->fetchColumn() ?? 0);

    $percentage = $goal ? min(100, round($totalCalories / $goal * 100)) : 0;

    // Macro totals + goals
    $macroTotals = getMacroTotalsToday($userId);
    $macroGoals  = getMacroGoalsFromCalorieGoal($goal);

    /* --- Ghi log --- */
    log_attempt($pdo, $userId, 'delete_intake', 'User deleted intake', 'intakeLog', $intakeId);

    // 3. Xóa sạch bộ đệm chứa rác (nếu có) trước khi in JSON
    ob_clean();

    // 4. Trả về kết quả thành công
    echo json_encode([
        'ok' => true,
        'total' => $totalCalories,
        'percentage' => $percentage,
        'macros' => $macroTotals,
        'macro_goals' => $macroGoals,
        'deleted_row' => $deletedRow
    ], JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
    // Nếu có bất kỳ lỗi nào (Database, Logic, Login...) nó sẽ nhảy vào đây
    ob_clean(); // Xóa rác
    
    echo json_encode([
        'ok' => false,
        'error' => $e->getMessage() // Trả về nguyên nhân lỗi
    ], JSON_UNESCAPED_UNICODE);
}

// Kết thúc script
exit;
?>