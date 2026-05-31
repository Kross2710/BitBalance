<?php
/**
 * Test suite for gamified XP and leveling system.
 */

require_once __DIR__ . '/../../include/handlers/xp.php';

class XPTest {
    public $useDatabase = true; // Use transactional isolation

    public function testLevelMathCurves() {
        // Curve formula: 50 * level * (level - 1)
        Assert::equals(0, xp_for_level(1));
        Assert::equals(100, xp_for_level(2));
        Assert::equals(300, xp_for_level(3));
        Assert::equals(600, xp_for_level(4));
        
        // Inverse calculation: xp_level_for
        Assert::equals(1, xp_level_for(0));
        Assert::equals(1, xp_level_for(99));
        Assert::equals(2, xp_level_for(100));
        Assert::equals(2, xp_level_for(299));
        Assert::equals(3, xp_level_for(300));
        Assert::equals(4, xp_level_for(600));
        Assert::equals(4, xp_level_for(750));
    }

    public function testXpEnsureRowAndSummary() {
        global $pdo;
        
        $userId = test_create_user($pdo, "XPUser");
        
        // Before ensure, check user_xp doesn't exist
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM user_xp WHERE user_id = ?");
        $stmt->execute([$userId]);
        Assert::equals(0, (int)$stmt->fetchColumn());
        
        // Call ensure
        xp_ensure_row($pdo, $userId);
        
        // Assert row exists
        $stmt->execute([$userId]);
        Assert::equals(1, (int)$stmt->fetchColumn());
        
        // Read summary
        $summary = xp_get_summary($pdo, $userId);
        Assert::equals(0, $summary['total_xp']);
        Assert::equals(1, $summary['current_level']);
        Assert::equals(0, $summary['xp_into_level']);
        Assert::equals(100, $summary['xp_for_next']); // Level 1 -> 2 needs 100 XP
        Assert::equals(0, $summary['progress_pct']);
    }

    public function testXpCommitAndLevelUpRewards() {
        global $pdo;
        
        $userId = test_create_user($pdo, "XPRewards");
        xp_ensure_row($pdo, $userId);
        
        // Check initial streak freezes
        $stmt = $pdo->prepare("SELECT streak_freezes FROM userStatus WHERE user_id = ?");
        $stmt->execute([$userId]);
        $initialFreezes = (int)$stmt->fetchColumn();
        Assert::equals(0, $initialFreezes);
        
        // Award 150 XP. This should level them up from Level 1 to Level 2 (requires 100 XP)
        // $_SESSION leveling toasts should be set
        $result = xp_commit($pdo, $userId, 'test_award', 150, 1);
        
        Assert::equals(150, $result['xp_added']);
        Assert::true($result['leveled_up']);
        
        // Verify user_xp row updated
        $summary = xp_get_summary($pdo, $userId);
        Assert::equals(150, $summary['total_xp']);
        Assert::equals(2, $summary['current_level']); // Level 2
        Assert::equals(50, $summary['xp_into_level']); // 150 - 100
        
        // Verify streak freeze reward (+1 freeze per level gained)
        $stmt->execute([$userId]);
        $newFreezes = (int)$stmt->fetchColumn();
        Assert::equals($initialFreezes + 1, $newFreezes, "User should get a free streak freeze when leveling up");
        
        // Check leveling toast flash stashed in session
        $flash = xp_consume_levelup_flash();
        Assert::notNull($flash);
        Assert::equals(1, $flash['from']);
        Assert::equals(2, $flash['to']);
        Assert::equals(150, $flash['xp_added']);
        
        // Double check it was consumed
        Assert::null(xp_consume_levelup_flash());
    }

    public function testXpDeductAndClamping() {
        global $pdo;
        
        $userId = test_create_user($pdo, "XPDeduct");
        xp_ensure_row($pdo, $userId);
        
        // 1. Award some XP to reach Level 3 (needs 300 XP)
        xp_commit($pdo, $userId, 'test_award', 350, 1);
        $summary = xp_get_summary($pdo, $userId);
        Assert::equals(3, $summary['current_level']);
        Assert::equals(350, $summary['total_xp']);
        
        // 2. Spend/Deduct 200 XP. Remaining total: 150 XP
        // Standard math: 150 XP is Level 2.
        // Clamping Rule: Levels should NEVER drop when spending XP!
        $deducted = xp_deduct($pdo, $userId, 'spent_xp', 200);
        Assert::true($deducted, "XP deduction should succeed");
        
        $summary = xp_get_summary($pdo, $userId);
        Assert::equals(150, $summary['total_xp']);
        Assert::equals(3, $summary['current_level'], "Level should stay clamped at 3, no level downs!");
        
        // 3. Deducting more than they have should fail
        $insufficient = xp_deduct($pdo, $userId, 'spent_xp', 500);
        Assert::false($insufficient, "XP deduction should fail if balance is insufficient");
    }

    public function testXpAwardIntakeLogWithDailyCap() {
        global $pdo;
        
        $userId = test_create_user($pdo, "XPIntake");
        xp_ensure_row($pdo, $userId);
        
        // Verify initially no logged events for today
        $summaryBefore = xp_get_summary($pdo, $userId);
        Assert::equals(0, $summaryBefore['total_xp']);
        
        // Insert 1 meal into intakeLog for today
        $stmt = $pdo->prepare("INSERT INTO intakeLog (user_id, food_item, calories, date_intake) VALUES (?, 'Apple', 80, NOW())");
        $stmt->execute([$userId]);
        
        // Trigger award calculation
        $result = xp_award_intake_log($pdo, $userId);
        Assert::equals(10, $result['xp_added']); // 1 meal = 10 XP (XP_RULES['intake_log']['xp'])
        
        // Insert 5 more meals today (total = 6)
        for ($i = 0; $i < 5; $i++) {
            $stmt->execute([$userId]);
        }
        
        // Trigger award calculation again. Daily cap is 4 units (so max 40 XP today).
        // Since 1 unit (10 XP) was already awarded, it should only award 3 more units (30 XP).
        $result2 = xp_award_intake_log($pdo, $userId);
        Assert::equals(30, $result2['xp_added'], "Should clamp additional awards to reach the daily cap of 40 XP");
        
        // Triggering again immediately should yield 0 new XP
        $result3 = xp_award_intake_log($pdo, $userId);
        Assert::equals(0, $result3['xp_added'], "Subsequent triggers should yield 0 XP as the cap is reached");
        
        // Verify final total XP is 40
        $summaryAfter = xp_get_summary($pdo, $userId);
        Assert::equals(40, $summaryAfter['total_xp']);
    }
}
