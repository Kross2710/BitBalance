<?php
/**
 * AI Coach — user context builder.
 *
 * Pulls everything the AI needs to give personalized advice:
 *   - profile (age, gender, height, weight)
 *   - current calorie goal
 *   - streak info
 *   - today's intake (full list)
 *   - last 7 days intake totals
 *   - last 5 weight log entries
 *
 * Returns a compact, human-readable string designed to be injected
 * into a Gemini system prompt. Keeps token usage low.
 */

if (!function_exists('build_user_context')) {

    function build_user_context(PDO $pdo, int $user_id): string
    {
        $lines = [];

        // ---- Basic profile ----
        $stmt = $pdo->prepare("
            SELECT u.user_name, u.first_name, u.last_name,
                   p.age, p.gender, p.weight, p.height
            FROM user u
            LEFT JOIN userPhysicalInfo p ON p.user_id = u.user_id
            WHERE u.user_id = ?
        ");
        $stmt->execute([$user_id]);
        $profile = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($profile) {
            $name = trim(($profile['first_name'] ?? '') . ' ' . ($profile['last_name'] ?? ''));
            if ($name === '') {
                $name = $profile['user_name'];
            }
            $lines[] = "Name: {$name}";

            $bits = [];
            if (!empty($profile['age']))    $bits[] = "{$profile['age']} years old";
            if (!empty($profile['gender'])) $bits[] = $profile['gender'];
            if (!empty($profile['weight'])) $bits[] = "{$profile['weight']} kg";
            if (!empty($profile['height'])) $bits[] = "{$profile['height']} cm";
            if ($bits) $lines[] = 'Profile: ' . implode(', ', $bits);
        }

        // ---- Current calorie goal (latest userGoal row) ----
        $stmt = $pdo->prepare("
            SELECT calorie_goal, date_set
            FROM userGoal
            WHERE user_id = ?
            ORDER BY date_set DESC
            LIMIT 1
        ");
        $stmt->execute([$user_id]);
        $goal = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($goal) {
            $lines[] = "Calorie goal: {$goal['calorie_goal']} kcal/day (set on " . date('Y-m-d', strtotime($goal['date_set'])) . ")";
        } else {
            $lines[] = "Calorie goal: not set yet";
        }

        // ---- Streak ----
        $stmt = $pdo->prepare("
            SELECT logging_streak, longest_logging_streak, last_logging_date
            FROM userStatus
            WHERE user_id = ?
        ");
        $stmt->execute([$user_id]);
        $status = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($status) {
            $lines[] = "Logging streak: {$status['logging_streak']} days (longest: {$status['longest_logging_streak']})";
        }

        // ---- Today's intake (full list) ----
        $today = date('Y-m-d');
        $stmt = $pdo->prepare("
            SELECT food_item, meal_category, calories, protein, carbs, fat
            FROM intakeLog
            WHERE user_id = ? AND DATE(date_intake) = ?
            ORDER BY date_intake ASC
        ");
        $stmt->execute([$user_id, $today]);
        $todayItems = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if ($todayItems) {
            $totalCal = $totalP = $totalC = $totalF = 0;
            $lines[] = "\nToday ({$today}) intake:";
            foreach ($todayItems as $item) {
                $lines[] = sprintf(
                    "  - [%s] %s: %d kcal, P %.1fg, C %.1fg, F %.1fg",
                    $item['meal_category'],
                    $item['food_item'],
                    (int)$item['calories'],
                    (float)$item['protein'],
                    (float)$item['carbs'],
                    (float)$item['fat']
                );
                $totalCal += (int)$item['calories'];
                $totalP   += (float)$item['protein'];
                $totalC   += (float)$item['carbs'];
                $totalF   += (float)$item['fat'];
            }
            $lines[] = sprintf(
                "  TOTAL today: %d kcal, P %.1fg, C %.1fg, F %.1fg",
                $totalCal, $totalP, $totalC, $totalF
            );
        } else {
            $lines[] = "\nToday ({$today}) intake: nothing logged yet";
        }

        // ---- Last 7 days totals (excluding today, for trend) ----
        $stmt = $pdo->prepare("
            SELECT DATE(date_intake) AS d,
                   SUM(calories) AS cal,
                   SUM(protein)  AS p,
                   SUM(carbs)    AS c,
                   SUM(fat)      AS f
            FROM intakeLog
            WHERE user_id = ?
              AND DATE(date_intake) >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
              AND DATE(date_intake) < CURDATE()
            GROUP BY DATE(date_intake)
            ORDER BY d DESC
        ");
        $stmt->execute([$user_id]);
        $weekDays = $stmt->fetchAll(PDO::FETCH_ASSOC);
        if ($weekDays) {
            $lines[] = "\nPast 7 days (daily totals):";
            foreach ($weekDays as $d) {
                $lines[] = sprintf(
                    "  - %s: %d kcal (P %.0fg, C %.0fg, F %.0fg)",
                    $d['d'], (int)$d['cal'], (float)$d['p'], (float)$d['c'], (float)$d['f']
                );
            }
        }

        // ---- Recent weight log (last 5) ----
        $stmt = $pdo->prepare("
            SELECT weight, date_logged
            FROM weight_log
            WHERE user_id = ?
            ORDER BY date_logged DESC
            LIMIT 5
        ");
        $stmt->execute([$user_id]);
        $weights = $stmt->fetchAll(PDO::FETCH_ASSOC);
        if ($weights) {
            $lines[] = "\nRecent weight log:";
            foreach ($weights as $w) {
                $lines[] = "  - {$w['date_logged']}: {$w['weight']} kg";
            }
        }

        return implode("\n", $lines);
    }
}
