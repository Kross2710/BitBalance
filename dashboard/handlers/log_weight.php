<?php
require_once __DIR__ . '/../../include/init.php';
require_once __DIR__ . '/../../include/handlers/xp.php';

// 1. Kiểm tra đăng nhập
if (!isset($_SESSION['user']) || $_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: ../dashboard.php");
    exit();
}

$userId = $_SESSION['user']['user_id'];
$weight = filter_input(INPUT_POST, 'weight', FILTER_VALIDATE_FLOAT);
$date = $_POST['weight_date'] ?? date('Y-m-d'); // Nếu không chọn ngày thì lấy hôm nay

// 2. Validate dữ liệu
if ($weight === false || $weight <= 0 || $weight > 500) {
    header("Location: ../dashboard.php?error=" . urlencode("Invalid weight entered."));
    exit();
}

try {
    // 3. Insert vào Database
    // Kiểm tra xem hôm nay đã nhập chưa? Nếu rồi thì Update, chưa thì Insert
    // (Logic đơn giản nhất là cứ Insert dòng mới để lưu lịch sử chi tiết)
    $stmt = $pdo->prepare("INSERT INTO weight_log (user_id, weight, date_logged) VALUES (?, ?, ?)");
    $stmt->execute([$userId, $weight, $date]);

    // 4. Cập nhật luôn bảng userPhysicalInfo để đồng bộ dữ liệu
    $stmtUpdate = $pdo->prepare("UPDATE userPhysicalInfo SET weight = ? WHERE user_id = ?");
    $stmtUpdate->execute([$weight, $userId]);

    // 5. Award XP (state-based — only the first weight log of the day counts).
    try {
        xp_award_weight_log($pdo, $userId);
    } catch (Throwable $e) {
        error_log('xp_award_weight_log: ' . $e->getMessage());
    }

    header("Location: ../dashboard.php?success=" . urlencode("Weight logged successfully!"));

} catch (PDOException $e) {
    error_log($e->getMessage());
    header("Location: ../dashboard.php?error=" . urlencode("Database error."));
}
exit();
?>