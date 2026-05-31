<?php
// dashboard/handlers/pt_action.php
require_once __DIR__ . '/../../include/init.php';
require_once __DIR__ . '/../../include/db_config.php';
require_once __DIR__ . '/../../include/csrf.php';

header('Content-Type: application/json');

// Check Login and PT Role
if (!$isLoggedIn || ($user['role'] ?? 'regular') !== 'pt') {
    echo json_encode(['ok' => false, 'error' => 'Unauthorized access. Only PT accounts allowed.']);
    exit();
}

$action = $_POST['action'] ?? '';
$csrfToken = $_POST['csrf_token'] ?? '';

// Verify CSRF for mutation actions
if (in_array($action, ['accept', 'reject', 'terminate', 'save_feedback'], true)) {
    if (!csrf_verify($csrfToken)) {
        echo json_encode(['ok' => false, 'error' => 'CSRF verification failed.']);
        exit();
    }
}

$me = (int) $user['user_id'];

try {
    if ($action === 'accept') {
        $requestId = (int) ($_POST['request_id'] ?? 0);
        if ($requestId <= 0) {
            echo json_encode(['ok' => false, 'error' => 'Invalid request ID']);
            exit();
        }

        // Accept the link request, ensure this trainer is indeed the trainer in the request
        $stmt = $pdo->prepare("
            UPDATE trainer_client 
            SET status = 'accepted', responded_at = NOW() 
            WHERE id = ? AND trainer_id = ? AND status = 'pending'
        ");
        $stmt->execute([$requestId, $me]);

        if ($stmt->rowCount() > 0) {
            echo json_encode(['ok' => true]);
        } else {
            echo json_encode(['ok' => false, 'error' => 'Request not found or already processed.']);
        }
        exit();

    } elseif ($action === 'reject') {
        $requestId = (int) ($_POST['request_id'] ?? 0);
        if ($requestId <= 0) {
            echo json_encode(['ok' => false, 'error' => 'Invalid request ID']);
            exit();
        }

        // Reject the link request
        $stmt = $pdo->prepare("
            DELETE FROM trainer_client 
            WHERE id = ? AND trainer_id = ? AND status = 'pending'
        ");
        $stmt->execute([$requestId, $me]);

        echo json_encode(['ok' => true]);
        exit();

    } elseif ($action === 'terminate') {
        $clientId = (int) ($_POST['client_id'] ?? 0);
        if ($clientId <= 0) {
            echo json_encode(['ok' => false, 'error' => 'Invalid client ID']);
            exit();
        }

        // Hủy liên kết với học viên
        $stmt = $pdo->prepare("
            DELETE FROM trainer_client 
            WHERE trainer_id = ? AND client_id = ? AND status = 'accepted'
        ");
        $stmt->execute([$me, $clientId]);

        echo json_encode(['ok' => true]);
        exit();

    } elseif ($action === 'save_feedback') {
        $clientId = (int) ($_POST['client_id'] ?? 0);
        $dateFor  = $_POST['date_for'] ?? '';
        $content  = trim($_POST['content'] ?? '');

        if ($clientId <= 0 || empty($dateFor)) {
            echo json_encode(['ok' => false, 'error' => 'Missing required fields.']);
            exit();
        }

        // Verify that this client is actually linked to this trainer
        $stmt = $pdo->prepare("
            SELECT id FROM trainer_client 
            WHERE trainer_id = ? AND client_id = ? AND status = 'accepted'
        ");
        $stmt->execute([$me, $clientId]);
        if (!$stmt->fetch()) {
            echo json_encode(['ok' => false, 'error' => 'This client is not linked to you.']);
            exit();
        }

        if ($content === '') {
            // If content is empty, delete the feedback for this day
            $stmt = $pdo->prepare("
                DELETE FROM pt_feedback 
                WHERE trainer_id = ? AND client_id = ? AND date_for = ?
            ");
            $stmt->execute([$me, $clientId, $dateFor]);
        } else {
            // Upsert feedback
            $stmt = $pdo->prepare("
                INSERT INTO pt_feedback (trainer_id, client_id, date_for, content)
                VALUES (?, ?, ?, ?)
                ON DUPLICATE KEY UPDATE content = ?, updated_at = NOW()
            ");
            $stmt->execute([$me, $clientId, $dateFor, $content, $content]);
        }

        echo json_encode(['ok' => true]);
        exit();

    } else {
        echo json_encode(['ok' => false, 'error' => 'Unknown action']);
        exit();
    }

} catch (PDOException $e) {
    echo json_encode(['ok' => false, 'error' => 'Database error: ' . $e->getMessage()]);
    exit();
}
