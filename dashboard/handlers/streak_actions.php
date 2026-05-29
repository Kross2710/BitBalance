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
    require_once __DIR__ . '/../../include/handlers/xp.php';
    require_once __DIR__ . '/../../include/handlers/log_attempt.php';

    if (!isset($_SESSION['user'])) {
        throw new Exception('Not authorised');
    }

    $userId = $_SESSION['user']['user_id'];
    $action = $_POST['action'] ?? '';

    if (empty($action)) {
        throw new Exception('Missing action');
    }

    // Ensure status row exists
    $stmt = $pdo->prepare("SELECT 1 FROM userStatus WHERE user_id = ?");
    $stmt->execute([$userId]);
    if (!$stmt->fetchColumn()) {
        $pdo->prepare("INSERT INTO userStatus (user_id, logging_streak, longest_logging_streak, last_logging_date, streak_freezes, broken_streak) VALUES (?, 1, 1, CURDATE(), 0, 0)")
            ->execute([$userId]);
    }

    $res = ['ok' => true];

    if ($action === 'purchase_freeze') {
        $cost = 100;
        
        // Let's get current XP to verify
        $xpSummary = xp_get_summary($pdo, $userId);
        if ($xpSummary['total_xp'] < $cost) {
            throw new Exception(t('dashboard.streak.ajax.insufficient_xp_buy'));
        }

        // Deduct XP
        $ok = xp_deduct($pdo, $userId, 'purchase_freeze', $cost);
        if (!$ok) {
            throw new Exception(t('dashboard.streak.ajax.deduct_failed_buy'));
        }

        // Update streak_freezes
        $upd = $pdo->prepare("UPDATE userStatus SET streak_freezes = streak_freezes + 1 WHERE user_id = ?");
        $upd->execute([$userId]);

        log_attempt($pdo, $userId, 'purchase_freeze', "User purchased a streak freeze for $cost XP");

        // Fetch updated status and XP summary
        $stmtStatus = $pdo->prepare("SELECT streak_freezes FROM userStatus WHERE user_id = ?");
        $stmtStatus->execute([$userId]);
        $freezes = (int)$stmtStatus->fetchColumn();

        $res['streak_freezes'] = $freezes;
        $res['xp_summary'] = xp_get_summary($pdo, $userId);
        $res['message'] = t('dashboard.streak.ajax.success_buy');

    } elseif ($action === 'restore_streak') {
        $cost = 150;

        // Get status
        $stmtStatus = $pdo->prepare("SELECT broken_streak, longest_logging_streak FROM userStatus WHERE user_id = ? FOR UPDATE");
        $stmtStatus->execute([$userId]);
        $status = $stmtStatus->fetch(PDO::FETCH_ASSOC);

        $broken = isset($status['broken_streak']) ? (int)$status['broken_streak'] : 0;
        $longest = isset($status['longest_logging_streak']) ? (int)$status['longest_logging_streak'] : 0;

        if ($broken <= 1) {
            throw new Exception(t('dashboard.streak.ajax.no_broken_streak'));
        }

        // Check XP
        $xpSummary = xp_get_summary($pdo, $userId);
        if ($xpSummary['total_xp'] < $cost) {
            throw new Exception(t('dashboard.streak.ajax.insufficient_xp_res'));
        }

        // Deduct XP
        $ok = xp_deduct($pdo, $userId, 'restore_streak', $cost);
        if (!$ok) {
            throw new Exception(t('dashboard.streak.ajax.deduct_failed_res'));
        }

        // Restore streak: new streak = broken_streak + 1 (since they log today)
        $newStreak = $broken + 1;
        if ($newStreak > $longest) {
            $longest = $newStreak;
        }

        $now = new DateTimeImmutable('now', new DateTimeZone('Australia/Sydney'));

        $upd = $pdo->prepare("
            UPDATE userStatus 
            SET logging_streak = ?, longest_logging_streak = ?, last_logging_date = ?, broken_streak = 0 
            WHERE user_id = ?
        ");
        $upd->execute([$newStreak, $longest, $now->format('Y-m-d H:i:s'), $userId]);

        log_attempt($pdo, $userId, 'restore_streak', "User restored broken streak of $broken days to $newStreak days for $cost XP");

        $res['logging_streak'] = $newStreak;
        $res['broken_streak'] = 0;
        $res['xp_summary'] = xp_get_summary($pdo, $userId);
        $res['message'] = t('dashboard.streak.ajax.success_res', ['n' => $newStreak]);

    } elseif ($action === 'dismiss_broken') {
        $upd = $pdo->prepare("UPDATE userStatus SET broken_streak = 0 WHERE user_id = ?");
        $upd->execute([$userId]);

        log_attempt($pdo, $userId, 'dismiss_broken', "User dismissed broken streak rescue popup");

        $res['broken_streak'] = 0;
        $res['message'] = t('dashboard.streak.ajax.success_dismiss');

    } else {
        throw new Exception('Invalid action');
    }

    ob_clean();
    echo json_encode($res, JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
    ob_clean();
    echo json_encode([
        'ok' => false,
        'error' => $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}
exit;
