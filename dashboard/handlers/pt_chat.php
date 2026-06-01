<?php
// dashboard/handlers/pt_chat.php
// Two-way PT <-> Client messaging (Task #3). Both sides hit this endpoint;
// the caller's role + an accepted trainer_client link decide who they are.
require_once __DIR__ . '/../../include/init.php';
require_once __DIR__ . '/../../include/db_config.php';
require_once __DIR__ . '/../../include/csrf.php';

header('Content-Type: application/json');

if (!$isLoggedIn) {
    echo json_encode(['ok' => false, 'error' => 'Unauthorized access.']);
    exit();
}

$me      = (int) $user['user_id'];
$iAmPt   = (($user['role'] ?? 'regular') === 'pt');
$action  = $_POST['action'] ?? '';
$counterpart = (int) ($_POST['counterpart_id'] ?? 0);

if ($counterpart <= 0) {
    echo json_encode(['ok' => false, 'error' => 'Missing counterpart.']);
    exit();
}

// Resolve the (trainer_id, client_id) pair from my role, and verify the link is
// accepted — this is the authorization gate for every action below.
$trainerId = $iAmPt ? $me : $counterpart;
$clientId  = $iAmPt ? $counterpart : $me;

try {
    $stmt = $pdo->prepare("
        SELECT id FROM trainer_client
        WHERE trainer_id = ? AND client_id = ? AND status = 'accepted'
        LIMIT 1
    ");
    $stmt->execute([$trainerId, $clientId]);
    if (!$stmt->fetch()) {
        echo json_encode(['ok' => false, 'error' => 'No active trainer-client link.']);
        exit();
    }

    $myRole = $iAmPt ? 'trainer' : 'client';

    if ($action === 'fetch') {
        // Optional cursor: only return messages newer than this id (used by the
        // client-side poll to fetch just the new ones each tick).
        $since = isset($_POST['since']) ? (int) $_POST['since'] : 0;
        $thread = getThread($pdo, $trainerId, $clientId, false);
        $messages = [];
        if ($thread) {
            if ($since > 0) {
                $stmt = $pdo->prepare("
                    SELECT message_id, sender_role, content, created_at
                    FROM pt_message
                    WHERE thread_id = ? AND message_id > ?
                    ORDER BY created_at ASC, message_id ASC
                ");
                $stmt->execute([$thread, $since]);
            } else {
                $stmt = $pdo->prepare("
                    SELECT message_id, sender_role, content, created_at
                    FROM pt_message
                    WHERE thread_id = ?
                    ORDER BY created_at ASC, message_id ASC
                ");
                $stmt->execute([$thread]);
            }
            $messages = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Mark the other side's messages as seen by me (drives unread badges).
            $otherRole = $iAmPt ? 'client' : 'trainer';
            $upd = $pdo->prepare("
                UPDATE pt_message SET seen_at = NOW()
                WHERE thread_id = ? AND sender_role = ? AND seen_at IS NULL
            ");
            $upd->execute([$thread, $otherRole]);
        }
        echo json_encode(['ok' => true, 'messages' => $messages, 'my_role' => $myRole]);
        exit();
    }

    if ($action === 'send') {
        if (!csrf_verify($_POST['csrf_token'] ?? '')) {
            echo json_encode(['ok' => false, 'error' => 'CSRF verification failed.']);
            exit();
        }
        $content = trim($_POST['content'] ?? '');
        if ($content === '') {
            echo json_encode(['ok' => false, 'error' => 'Empty message.']);
            exit();
        }
        // Cap length without mbstring (disabled on host) — walk UTF-8 codepoints.
        if (preg_match_all('/./us', $content, $m) && count($m[0]) > 2000) {
            $content = implode('', array_slice($m[0], 0, 2000));
        }

        $thread = getThread($pdo, $trainerId, $clientId, true);
        $stmt = $pdo->prepare("
            INSERT INTO pt_message (thread_id, sender_role, content)
            VALUES (?, ?, ?)
        ");
        $stmt->execute([$thread, $myRole, $content]);
        $newMessageId = (int) $pdo->lastInsertId();

        // Bump thread updated_at so it can sort by recency later.
        $pdo->prepare("UPDATE pt_thread SET updated_at = NOW() WHERE thread_id = ?")
            ->execute([$thread]);

        echo json_encode([
            'ok' => true,
            'message' => [
                'message_id'  => $newMessageId,
                'sender_role' => $myRole,
                'content'     => $content,
                'created_at'  => date('Y-m-d H:i:s'),
            ],
        ]);
        exit();
    }

    echo json_encode(['ok' => false, 'error' => 'Unknown action']);
    exit();

} catch (PDOException $e) {
    echo json_encode(['ok' => false, 'error' => 'Database error.']);
    exit();
}

/**
 * Return the thread_id for this trainer/client pair. When $create is true and
 * no thread exists yet, lazily create one (mirrors AI Coach's lazy thread).
 */
function getThread(PDO $pdo, int $trainerId, int $clientId, bool $create): ?int
{
    $stmt = $pdo->prepare("
        SELECT thread_id FROM pt_thread WHERE trainer_id = ? AND client_id = ? LIMIT 1
    ");
    $stmt->execute([$trainerId, $clientId]);
    $row = $stmt->fetch();
    if ($row) {
        return (int) $row['thread_id'];
    }
    if (!$create) {
        return null;
    }
    $stmt = $pdo->prepare("
        INSERT INTO pt_thread (trainer_id, client_id) VALUES (?, ?)
    ");
    $stmt->execute([$trainerId, $clientId]);
    return (int) $pdo->lastInsertId();
}
