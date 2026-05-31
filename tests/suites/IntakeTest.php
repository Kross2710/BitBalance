<?php
/**
 * Test suite for food logging intake, macro calculations, and CRUD logic.
 */

require_once __DIR__ . '/../../dashboard/handlers/functions.php';

class IntakeTest {
    public $useDatabase = true; // Safe transactional database logic

    public function testIntakeCalculations() {
        global $pdo;
        
        $userId = test_create_user($pdo, "IntakeCalcUser");
        $today = date('Y-m-d');
        
        // 1. Initial checks (empty)
        Assert::equals(null, getTotalCaloriesToday($userId, $today));
        
        $macros = getMacroTotalsToday($userId, $today);
        Assert::equals(0.0, $macros['protein']);
        Assert::equals(0.0, $macros['carbs']);
        Assert::equals(0.0, $macros['fat']);
        
        // 2. Add an item: Banana (105 kcal, 1.3g protein, 27g carbs, 0.4g fat)
        $stmt = $pdo->prepare("
            INSERT INTO intakeLog (user_id, food_item, meal_category, calories, protein, carbs, fat, date_intake)
            VALUES (?, 'Banana', 'breakfast', 105, 1.30, 27.00, 0.40, NOW())
        ");
        $stmt->execute([$userId]);
        
        // 3. Verify updated totals
        Assert::equals(105, (int)getTotalCaloriesToday($userId, $today));
        
        $macros = getMacroTotalsToday($userId, $today);
        Assert::equals(1.30, $macros['protein']);
        Assert::equals(27.00, $macros['carbs']);
        Assert::equals(0.40, $macros['fat']);
        
        // 4. Add another item: Chicken Breast (230 kcal, 43g protein, 0g carbs, 5g fat)
        $stmt = $pdo->prepare("
            INSERT INTO intakeLog (user_id, food_item, meal_category, calories, protein, carbs, fat, date_intake)
            VALUES (?, 'Chicken Breast', 'lunch', 230, 43.00, 0.00, 5.00, NOW())
        ");
        $stmt->execute([$userId]);
        
        // Total: 335 kcal, 44.3g protein, 27g carbs, 5.4g fat
        Assert::equals(335, (int)getTotalCaloriesToday($userId, $today));
        
        $macros = getMacroTotalsToday($userId, $today);
        Assert::equals(44.30, $macros['protein']);
        Assert::equals(27.00, $macros['carbs']);
        Assert::equals(5.40, $macros['fat']);
    }

    public function testIntakeCrudLifecycle() {
        global $pdo;
        
        $userId = test_create_user($pdo, "IntakeCrudUser");
        
        // 1. Create (Log food)
        $ins = $pdo->prepare("
            INSERT INTO intakeLog (user_id, food_item, meal_category, calories, protein, carbs, fat, date_intake)
            VALUES (?, 'Oatmeal', 'breakfast', 150, 6.00, 27.00, 3.00, NOW())
        ");
        $ins->execute([$userId]);
        $intakeId = (int)$pdo->lastInsertId();
        
        // Verify created
        $stmt = $pdo->prepare("SELECT * FROM intakeLog WHERE intakeLog_id = ?");
        $stmt->execute([$intakeId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        
        Assert::notNull($row);
        Assert::equals('Oatmeal', $row['food_item']);
        Assert::equals(150, (int)$row['calories']);
        
        // 2. Update (Edit food entry)
        $upd = $pdo->prepare("
            UPDATE intakeLog
            SET food_item = 'Double Oatmeal', calories = 300, protein = 12.00
            WHERE intakeLog_id = ? AND user_id = ?
        ");
        $upd->execute([$intakeId, $userId]);
        
        // Verify updated
        $stmt->execute([$intakeId]);
        $updatedRow = $stmt->fetch(PDO::FETCH_ASSOC);
        Assert::equals('Double Oatmeal', $updatedRow['food_item']);
        Assert::equals(300, (int)$updatedRow['calories']);
        Assert::equals(12.00, (float)$updatedRow['protein']);
        
        // 3. Delete (Remove food entry)
        $del = $pdo->prepare("DELETE FROM intakeLog WHERE intakeLog_id = ? AND user_id = ?");
        $del->execute([$intakeId, $userId]);
        
        // Verify deleted
        $stmt->execute([$intakeId]);
        Assert::false((bool)$stmt->fetchColumn(), "Food entry should no longer exist after deletion");
    }
}
