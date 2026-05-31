<?php
// include/handlers/pt_notify.php
// Notification counts for the PT <-> client relationship (Task #4).
// All helpers swallow errors and return 0 so they're safe to call before the
// PT migrations have run (mirrors the friends_*_count pattern).

if (!function_exists('pt_unread_chat_count')) {
    /**
     * Unread chat messages addressed to $userId.
     *  - PT ($isPt = true): messages clients sent them.
     *  - Client: messages their trainer sent.
     */
    function pt_unread_chat_count(PDO $pdo, int $userId, bool $isPt): int
    {
        $col        = $isPt ? 'trainer_id' : 'client_id';
        $senderRole = $isPt ? 'client' : 'trainer';
        try {
            $stmt = $pdo->prepare("
                SELECT COUNT(*)
                FROM pt_message m
                JOIN pt_thread t ON m.thread_id = t.thread_id
                WHERE t.$col = ? AND m.sender_role = ? AND m.seen_at IS NULL
            ");
            $stmt->execute([$userId, $senderRole]);
            return (int) $stmt->fetchColumn();
        } catch (Throwable $e) {
            return 0;
        }
    }
}

if (!function_exists('pt_unseen_feedback_count')) {
    /** Per-day PT feedback this client hasn't viewed yet. */
    function pt_unseen_feedback_count(PDO $pdo, int $clientId): int
    {
        try {
            $stmt = $pdo->prepare("
                SELECT COUNT(*) FROM pt_feedback WHERE client_id = ? AND seen_at IS NULL
            ");
            $stmt->execute([$clientId]);
            return (int) $stmt->fetchColumn();
        } catch (Throwable $e) {
            return 0;
        }
    }
}

if (!function_exists('pt_sidebar_badge_count')) {
    /**
     * Combined badge shown on the sidebar:
     *  - PT: unread chat from clients (lands on PT Dashboard).
     *  - Client: unread chat from trainer + unseen feedback (lands on Intake).
     */
    function pt_sidebar_badge_count(PDO $pdo, int $userId, bool $isPt): int
    {
        $n = pt_unread_chat_count($pdo, $userId, $isPt);
        if (!$isPt) {
            $n += pt_unseen_feedback_count($pdo, $userId);
        }
        return $n;
    }
}
