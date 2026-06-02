<?php
// dashboard/handlers/respond_goal_proposal.php
// Client accepts or declines a PT-proposed calorie goal (Phase 1).
require_once __DIR__ . '/../../include/init.php';
require_once __DIR__ . '/../../include/db_config.php';
require_once __DIR__ . '/../../include/csrf.php';

header('Content-Type: application/json');

if (!$isLoggedIn) {
    echo json_encode(['ok' => false, 'error' => 'Not logged in.']);
    exit();
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['ok' => false, 'error' => 'Invalid method.']);
    exit();
}
if (!csrf_verify($_POST['csrf_token'] ?? '')) {
    echo json_encode(['ok' => false, 'error' => 'CSRF verification failed.']);
    exit();
}

$me         = (int) $user['user_id'];
$proposalId = (int) ($_POST['proposal_id'] ?? 0);
$decision   = $_POST['decision'] ?? '';

if ($proposalId <= 0 || !in_array($decision, ['accept', 'decline'], true)) {
    echo json_encode(['ok' => false, 'error' => 'Invalid request.']);
    exit();
}

try {
    // Fetch the pending proposal addressed to me, and confirm the trainer is
    // still linked (a stale proposal from an unlinked trainer is not actionable).
    $stmt = $pdo->prepare("
        SELECT p.id, p.trainer_id, p.calorie_goal, p.protein_goal, p.carbs_goal, p.fat_goal
        FROM pt_goal_proposal p
        JOIN trainer_client tc
            ON tc.trainer_id = p.trainer_id
           AND tc.client_id = p.client_id
           AND tc.status = 'accepted'
        WHERE p.id = ? AND p.client_id = ? AND p.status = 'pending'
        LIMIT 1
    ");
    $stmt->execute([$proposalId, $me]);
    $proposal = $stmt->fetch();

    if (!$proposal) {
        echo json_encode(['ok' => false, 'error' => 'Proposal not found or no longer active.']);
        exit();
    }

    if ($decision === 'accept') {
        $calorie = (int) $proposal['calorie_goal'];
        // Explicit macros carry through only if the PT set all three (Phase 2);
        // otherwise stay NULL so resolveMacroGoals() derives from the calories.
        $p = $proposal['protein_goal'];
        $c = $proposal['carbs_goal'];
        $f = $proposal['fat_goal'];
        $hasMacros = ($p !== null && $c !== null && $f !== null);

        // userGoal is a history table (latest row wins) — insert a new goal,
        // attributed to the trainer (source='pt') for transparency.
        $ins = $pdo->prepare("
            INSERT INTO userGoal (user_id, calorie_goal, protein_goal, carbs_goal, fat_goal, set_by, source, date_set)
            VALUES (?, ?, ?, ?, ?, ?, 'pt', NOW())
        ");
        $ins->execute([
            $me, $calorie,
            $hasMacros ? (int) $p : null,
            $hasMacros ? (int) $c : null,
            $hasMacros ? (int) $f : null,
            (int) $proposal['trainer_id'],
        ]);

        $upd = $pdo->prepare("UPDATE pt_goal_proposal SET status = 'accepted', responded_at = NOW() WHERE id = ?");
        $upd->execute([$proposalId]);

        echo json_encode(['ok' => true, 'accepted' => true, 'calorie_goal' => $calorie]);
        exit();
    } else {
        $upd = $pdo->prepare("UPDATE pt_goal_proposal SET status = 'declined', responded_at = NOW() WHERE id = ?");
        $upd->execute([$proposalId]);

        echo json_encode(['ok' => true, 'accepted' => false]);
        exit();
    }
} catch (PDOException $e) {
    echo json_encode(['ok' => false, 'error' => 'Database error: ' . $e->getMessage()]);
    exit();
}
