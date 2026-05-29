<?php
/**
 * XP & Level system — state-based awards.
 *
 * Anti-cheat principle: every "daily-capped" award is computed by COUNTing rows
 * in the source table (intakeLog, weight_log, ...) and subtracting how many
 * award events are already in xp_event for the same day. So log-and-delete
 * spam cannot inflate the user's total — only the high-water mark counts.
 *
 * Public surface:
 *   xp_ensure_row($pdo, $user_id)
 *   xp_get_summary($pdo, $user_id) : array  -> for header bar
 *   xp_award_intake_log($pdo, $user_id)
 *   xp_award_weight_log($pdo, $user_id)
 *   xp_award_streak_milestone($pdo, $user_id, $streak)
 *   xp_finalize_yesterday_goals($pdo, $user_id)
 *   xp_consume_levelup_flash()           -> for level-up toast
 *
 * Level curve: xp_for_level(n) = 50 * n * (n - 1)
 *   Level 1 = 0, Level 2 = 100, Level 3 = 300, Level 4 = 600, ...
 */

require_once __DIR__ . '/../db_config.php';

const XP_RULES = [
    'intake_log'   => ['xp' => 10, 'cap' => 4],
    'weight_log'   => ['xp' => 15, 'cap' => 1],
    'calorie_goal' => ['xp' => 50, 'cap' => 1],
    'macro_goal'   => ['xp' => 30, 'cap' => 1],
];

const XP_STREAK_MILESTONES = [
    7   => 100,
    14  => 200,
    30  => 500,
    100 => 2000,
    365 => 10000,
];

// -----------------------------------------------------------------------------
// Level math
// -----------------------------------------------------------------------------

function xp_for_level(int $level): int
{
    if ($level <= 1) return 0;
    return 50 * $level * ($level - 1);
}

function xp_level_for(int $totalXp): int
{
    if ($totalXp <= 0) return 1;
    // Solve 50 * n * (n - 1) <= total  →  n = (1 + sqrt(1 + total / 12.5)) / 2
    $n = (int) floor((1 + sqrt(1 + $totalXp / 12.5)) / 2);
    if ($n < 1) $n = 1;
    // Verify (guard against float drift)
    while (xp_for_level($n + 1) <= $totalXp) $n++;
    while (xp_for_level($n) > $totalXp)      $n--;
    return $n;
}

// -----------------------------------------------------------------------------
// Row bootstrap + read
// -----------------------------------------------------------------------------

function xp_ensure_row(PDO $pdo, int $userId): void
{
    $pdo->prepare(
        "INSERT IGNORE INTO user_xp (user_id, total_xp, current_level) VALUES (?, 0, 1)"
    )->execute([$userId]);
}

function xp_get_summary(PDO $pdo, int $userId): array
{
    xp_ensure_row($pdo, $userId);
    $row = $pdo->prepare("SELECT total_xp, current_level FROM user_xp WHERE user_id = ?");
    $row->execute([$userId]);
    $r = $row->fetch(PDO::FETCH_ASSOC) ?: ['total_xp' => 0, 'current_level' => 1];

    $total = (int) $r['total_xp'];
    $level = (int) $r['current_level'];
    $floor = xp_for_level($level);
    $ceil  = xp_for_level($level + 1);
    $into  = max(0, $total - $floor);
    $span  = max(1, $ceil - $floor);
    $pct   = (int) min(100, round($into / $span * 100));

    return [
        'total_xp'         => $total,
        'current_level'    => $level,
        'xp_into_level'    => $into,
        'xp_for_next'      => $span,
        'progress_pct'     => $pct,
    ];
}

// -----------------------------------------------------------------------------
// Generic state-based awarder
// -----------------------------------------------------------------------------

/**
 * Award XP based on the actual count in a source table for today.
 *
 * @param string $countSql SELECT COUNT(...) ... WHERE user_id = ? AND <today filter>.
 *                         Must take exactly one bound parameter: user_id.
 */
function xp_award_for_count(
    PDO $pdo,
    int $userId,
    string $source,
    int $xpPerUnit,
    int $cap,
    string $countSql
): array {
    xp_ensure_row($pdo, $userId);

    // 1. Actual count today on source table
    $stmt = $pdo->prepare($countSql);
    $stmt->execute([$userId]);
    $actual = (int) $stmt->fetchColumn();

    // 2. Already-awarded count today in xp_event
    $awardedStmt = $pdo->prepare(
        "SELECT COUNT(*) FROM xp_event
         WHERE user_id = ? AND source = ? AND DATE(created_at) = CURDATE()"
    );
    $awardedStmt->execute([$userId, $source]);
    $awarded = (int) $awardedStmt->fetchColumn();

    // 3. Award the delta, clamped at cap
    $target = min($actual, $cap);
    $toAward = $target - $awarded;
    if ($toAward <= 0) {
        return ['xp_added' => 0, 'leveled_up' => false];
    }

    $totalAdded = 0;
    for ($i = 0; $i < $toAward; $i++) {
        $totalAdded += $xpPerUnit;
    }

    return xp_commit($pdo, $userId, $source, $totalAdded, $toAward);
}

/**
 * Insert N event rows (one per unit) + bump user_xp + check level-up.
 * Wrapped in a transaction so user_xp.total_xp stays in sync with xp_event.
 */
function xp_commit(
    PDO $pdo,
    int $userId,
    string $source,
    int $totalAmount,
    int $unitCount,
    ?string $refTable = null,
    ?int $refId = null
): array {
    if ($totalAmount === 0 || $unitCount === 0) {
        return ['xp_added' => 0, 'leveled_up' => false];
    }
    $perUnit = (int) round($totalAmount / $unitCount);

    $pdo->beginTransaction();
    try {
        $ins = $pdo->prepare(
            "INSERT INTO xp_event (user_id, source, amount, ref_table, ref_id)
             VALUES (?, ?, ?, ?, ?)"
        );
        for ($i = 0; $i < $unitCount; $i++) {
            $ins->execute([$userId, $source, $perUnit, $refTable, $refId]);
        }

        $row = $pdo->prepare("SELECT total_xp, current_level FROM user_xp WHERE user_id = ? FOR UPDATE");
        $row->execute([$userId]);
        $r = $row->fetch(PDO::FETCH_ASSOC) ?: ['total_xp' => 0, 'current_level' => 1];
        $oldLevel = (int) $r['current_level'];
        $newTotal = (int) $r['total_xp'] + $totalAmount;
        $newLevel = xp_level_for($newTotal);

        $leveledUp = $newLevel > $oldLevel;
        $upd = $pdo->prepare(
            "UPDATE user_xp
             SET total_xp = ?, current_level = ?, last_level_up_at = IF(? > current_level, NOW(), last_level_up_at)
             WHERE user_id = ?"
        );
        $upd->execute([$newTotal, $newLevel, $newLevel, $userId]);

        $pdo->commit();
    } catch (Throwable $e) {
        $pdo->rollBack();
        return ['xp_added' => 0, 'leveled_up' => false, 'error' => $e->getMessage()];
    }

    if ($leveledUp) {
        // Stash for the next page render to show a toast.
        $_SESSION['xp_levelup_flash'] = [
            'from'     => $oldLevel,
            'to'       => $newLevel,
            'xp_added' => $totalAmount,
        ];
    }

    return ['xp_added' => $totalAmount, 'leveled_up' => $leveledUp];
}

// -----------------------------------------------------------------------------
// Source-specific awarders
// -----------------------------------------------------------------------------

function xp_award_intake_log(PDO $pdo, int $userId): array
{
    return xp_award_for_count(
        $pdo, $userId, 'intake_log',
        XP_RULES['intake_log']['xp'], XP_RULES['intake_log']['cap'],
        "SELECT COUNT(*) FROM intakeLog WHERE user_id = ? AND DATE(date_intake) = CURDATE()"
    );
}

function xp_award_weight_log(PDO $pdo, int $userId): array
{
    return xp_award_for_count(
        $pdo, $userId, 'weight_log',
        XP_RULES['weight_log']['xp'], XP_RULES['weight_log']['cap'],
        "SELECT COUNT(*) FROM weight_log WHERE user_id = ? AND DATE(date_logged) = CURDATE()"
    );
}

/**
 * One-off milestone award. Idempotent: keyed by ref_id = the milestone value.
 */
function xp_award_streak_milestone(PDO $pdo, int $userId, int $streak): array
{
    if ($streak <= 0) return ['xp_added' => 0, 'leveled_up' => false];
    xp_ensure_row($pdo, $userId);

    $result = ['xp_added' => 0, 'leveled_up' => false];
    foreach (XP_STREAK_MILESTONES as $milestone => $xp) {
        if ($streak < $milestone) continue;

        // Already awarded?
        $chk = $pdo->prepare(
            "SELECT 1 FROM xp_event
             WHERE user_id = ? AND source = 'streak_milestone' AND ref_id = ? LIMIT 1"
        );
        $chk->execute([$userId, $milestone]);
        if ($chk->fetchColumn()) continue;

        $r = xp_commit($pdo, $userId, 'streak_milestone', $xp, 1, 'userStatus', $milestone);
        $result['xp_added'] += $r['xp_added'] ?? 0;
        if (!empty($r['leveled_up'])) $result['leveled_up'] = true;
    }
    return $result;
}

// -----------------------------------------------------------------------------
// Lazy-finalize: calorie / macro goal hit for YESTERDAY
// -----------------------------------------------------------------------------

/**
 * Award goal-hit XP for yesterday, idempotently. Called on dashboard page load.
 *
 * Why yesterday and not today: a user's hit-state is unstable within the day
 * (delete a meal → no longer at goal). Once midnight passes the day is frozen
 * for our purposes (we don't need to lock the rows — we just don't re-award).
 *
 * Multi-day gap: only finalizes yesterday. If the user was away for a week,
 * the missed days do not retroactively grant XP. This keeps comeback bonuses
 * boring & predictable, and avoids a heavy loop on first dashboard load.
 */
function xp_finalize_yesterday_goals(PDO $pdo, int $userId): array
{
    xp_ensure_row($pdo, $userId);

    $lastFinalized = $pdo->prepare("SELECT last_finalized_date FROM user_xp WHERE user_id = ?");
    $lastFinalized->execute([$userId]);
    $last = $lastFinalized->fetchColumn();

    $yesterdayStmt = $pdo->query("SELECT DATE_SUB(CURDATE(), INTERVAL 1 DAY) AS d");
    $yesterday = $yesterdayStmt->fetchColumn();

    if ($last && $last >= $yesterday) {
        return ['xp_added' => 0, 'leveled_up' => false];
    }

    // Pull yesterday's totals + goal in force at that moment.
    $sum = $pdo->prepare(
        "SELECT
            COALESCE(SUM(calories), 0) AS cal,
            COALESCE(SUM(protein),  0) AS p,
            COALESCE(SUM(carbs),    0) AS c,
            COALESCE(SUM(fat),      0) AS f
         FROM intakeLog
         WHERE user_id = ? AND DATE(date_intake) = ?"
    );
    $sum->execute([$userId, $yesterday]);
    $totals = $sum->fetch(PDO::FETCH_ASSOC) ?: ['cal' => 0, 'p' => 0, 'c' => 0, 'f' => 0];

    $goalStmt = $pdo->prepare(
        "SELECT calorie_goal FROM userGoal
         WHERE user_id = ? AND DATE(date_set) <= ?
         ORDER BY date_set DESC LIMIT 1"
    );
    $goalStmt->execute([$userId, $yesterday]);
    $calGoal = (int) ($goalStmt->fetchColumn() ?: 0);

    $totalXp = 0;
    $leveledUp = false;

    if ($calGoal > 0 && (int) $totals['cal'] > 0) {
        $cal = (int) $totals['cal'];
        $lo = $calGoal * 0.90;
        $hi = $calGoal * 1.10;
        if ($cal >= $lo && $cal <= $hi) {
            $r = _xp_award_one_off_for_date($pdo, $userId, 'calorie_goal', XP_RULES['calorie_goal']['xp'], $yesterday);
            $totalXp += $r['xp_added'];
            if (!empty($r['leveled_up'])) $leveledUp = true;
        }

        // Macro hit: each macro within ±15% of its target counts; need all 3.
        $macroGoals = [
            'protein' => (int) round(($calGoal * 0.30) / 4),
            'carbs'   => (int) round(($calGoal * 0.45) / 4),
            'fat'     => (int) round(($calGoal * 0.25) / 9),
        ];
        $within = function (float $actual, int $target): bool {
            if ($target <= 0) return false;
            return $actual >= $target * 0.85 && $actual <= $target * 1.15;
        };
        if (
            $within((float) $totals['p'], $macroGoals['protein']) &&
            $within((float) $totals['c'], $macroGoals['carbs']) &&
            $within((float) $totals['f'], $macroGoals['fat'])
        ) {
            $r = _xp_award_one_off_for_date($pdo, $userId, 'macro_goal', XP_RULES['macro_goal']['xp'], $yesterday);
            $totalXp += $r['xp_added'];
            if (!empty($r['leveled_up'])) $leveledUp = true;
        }
    }

    // Mark the day as finalized regardless of whether anything was awarded —
    // we never want to retry this date.
    $pdo->prepare("UPDATE user_xp SET last_finalized_date = ? WHERE user_id = ?")
        ->execute([$yesterday, $userId]);

    return ['xp_added' => $totalXp, 'leveled_up' => $leveledUp];
}

/**
 * Internal: idempotent one-off award keyed by (source, ref_id=DATE-as-int).
 * Uses YYYYMMDD packed into ref_id so the natural unique check is a row lookup.
 */
function _xp_award_one_off_for_date(PDO $pdo, int $userId, string $source, int $xp, string $date): array
{
    $refId = (int) str_replace('-', '', $date); // e.g. 20260528
    $chk = $pdo->prepare(
        "SELECT 1 FROM xp_event WHERE user_id = ? AND source = ? AND ref_id = ? LIMIT 1"
    );
    $chk->execute([$userId, $source, $refId]);
    if ($chk->fetchColumn()) {
        return ['xp_added' => 0, 'leveled_up' => false];
    }
    return xp_commit($pdo, $userId, $source, $xp, 1, 'intakeLog', $refId);
}

// -----------------------------------------------------------------------------
// Toast flash
// -----------------------------------------------------------------------------

function xp_consume_levelup_flash(): ?array
{
    if (empty($_SESSION['xp_levelup_flash'])) return null;
    $f = $_SESSION['xp_levelup_flash'];
    unset($_SESSION['xp_levelup_flash']);
    return $f;
}
