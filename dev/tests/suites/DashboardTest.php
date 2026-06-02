<?php
/**
 * Test suite for dashboard summaries, timezones, logging streaks, and Gemini API.
 */

require_once __DIR__ . '/../../../dashboard/handlers/functions.php';

class DashboardTest {
    public $useDatabase = true; // Use transactional isolation for streak DB updates

    public function testTimezoneIsoFormatting() {
        // Datetime in DB is Vietnam time (+07:00)
        $dbDate = "2026-05-31 22:15:00";
        $iso = toIsoVN($dbDate);
        
        Assert::equals("2026-05-31T22:15:00+07:00", $iso);
        Assert::equals("", toIsoVN(""));
        Assert::equals("", toIsoVN(null));
    }

    public function testMacroGoalFormulas() {
        // For 2000 kcal: 30% Protein (150g), 45% Carbs (225g), 25% Fat (56g)
        $goals = getMacroGoalsFromCalorieGoal(2000);
        Assert::equals(150, $goals['protein']);
        Assert::equals(225, $goals['carbs']);
        Assert::equals(56, $goals['fat']);
        
        // Zero/negative checks
        $zeros = getMacroGoalsFromCalorieGoal(0);
        Assert::equals(0, $zeros['protein']);
        Assert::equals(0, $zeros['carbs']);
        Assert::equals(0, $zeros['fat']);
    }

    public function testCalculateCalorieAverage() {
        // Standard average: (1800 + 2200) / 2 = 2000
        Assert::equals(2000, (int)calculateCalorieAverage([1800, 2200]));
        
        // Zeros should be ignored (e.g. non-logging days)
        Assert::equals(2000, (int)calculateCalorieAverage([1800, 0, 2200, 0]));
        
        // Empty array
        Assert::equals(0, (int)calculateCalorieAverage([]));
    }

    public function testLoggingStreakTransitions() {
        global $pdo;
        
        $userId = test_create_user($pdo, "StreakUser");
        
        // Set streak = 5, last logged = yesterday
        $yesterday = (new DateTimeImmutable('yesterday'))->format('Y-m-d H:i:s');
        $upd = $pdo->prepare("UPDATE userStatus SET logging_streak = 5, longest_logging_streak = 5, last_logging_date = ? WHERE user_id = ?");
        $upd->execute([$yesterday, $userId]);
        
        // 1. Logging today: streak increments to 6
        updateLoggingStreak($pdo, $userId);
        
        $stmt = $pdo->prepare("SELECT logging_streak, streak_freezes FROM userStatus WHERE user_id = ?");
        $stmt->execute([$userId]);
        $res = $stmt->fetch(PDO::FETCH_ASSOC);
        Assert::equals(6, (int)$res['logging_streak']);
        Assert::equals(0, (int)$res['streak_freezes']);
    }

    public function testLoggingStreakFreezeConsumption() {
        global $pdo;
        
        $userId = test_create_user($pdo, "FreezeUser");
        
        // Set streak = 10, freezes = 2, last logged = 2 days ago (missed yesterday)
        $twoDaysAgo = (new DateTimeImmutable('-2 days'))->format('Y-m-d H:i:s');
        $upd = $pdo->prepare("UPDATE userStatus SET logging_streak = 10, streak_freezes = 2, last_logging_date = ? WHERE user_id = ?");
        $upd->execute([$twoDaysAgo, $userId]);
        
        // Missed yesterday but has freezes → consumes 1 freeze, streak preserved & increments to 11
        updateLoggingStreak($pdo, $userId);
        
        $stmt = $pdo->prepare("SELECT logging_streak, streak_freezes FROM userStatus WHERE user_id = ?");
        $stmt->execute([$userId]);
        $res = $stmt->fetch(PDO::FETCH_ASSOC);
        
        Assert::equals(11, (int)$res['logging_streak'], "Streak should be preserved and incremented by streak freeze");
        Assert::equals(1, (int)$res['streak_freezes'], "Streak freezes left should decrease by 1");
    }

    public function testLoggingStreakReset() {
        global $pdo;
        
        $userId = test_create_user($pdo, "ResetUser");
        
        // Set streak = 8, freezes = 0, last logged = 2 days ago (missed yesterday)
        $twoDaysAgo = (new DateTimeImmutable('-2 days'))->format('Y-m-d H:i:s');
        $upd = $pdo->prepare("UPDATE userStatus SET logging_streak = 8, streak_freezes = 0, last_logging_date = ? WHERE user_id = ?");
        $upd->execute([$twoDaysAgo, $userId]);
        
        // Missed yesterday and no freezes left → resets to 1, stores broken streak
        updateLoggingStreak($pdo, $userId);
        
        $stmt = $pdo->prepare("SELECT logging_streak, broken_streak FROM userStatus WHERE user_id = ?");
        $stmt->execute([$userId]);
        $res = $stmt->fetch(PDO::FETCH_ASSOC);
        
        Assert::equals(1, (int)$res['logging_streak'], "Streak should reset to 1 due to missed consecutive log");
        Assert::equals(8, (int)$res['broken_streak'], "Previous streak of 8 should be saved in broken_streak");
    }

    public function testGeminiApiStatus() {
        // Load helpers containing call_gemini
        require_once __DIR__ . '/../../../api/ai-coach/_helpers.php';
        
        // Verify key is defined
        Assert::true(defined('GEMINI_API_KEY') && GEMINI_API_KEY !== '', "GEMINI_API_KEY is missing or empty in secrets.php");
        
        // Call gemini with a tiny message
        $history = [['role' => 'user', 'content' => 'ping']];
        $userContext = 'Calorie goal: 2000. Today\'s intake: 0.';
        $clientTimeInfo = 'Monday 2026-06-01, 10:00 local time';
        
        $res = call_gemini($history, $userContext, $clientTimeInfo, null, null);
        
        // Assert API responded successfully
        Assert::true($res['ok'], "Gemini API connection check failed: " . ($res['error'] ?? 'unknown error'));
        Assert::notEquals("", $res['text'], "Gemini responded with empty text");
    }
}
