<?php
/**
 * Goal Planner helper functions.
 *
 * Keeps the planning math and read-only summary queries out of the page so the
 * UI stays mostly presentation-focused.
 */

function plan_activity_options(): array
{
    return [
        'sedentary' => [
            'label' => 'Sedentary',
            'detail' => 'Little or no exercise',
            'factor' => 1.2,
        ],
        'lightly_active' => [
            'label' => 'Lightly active',
            'detail' => 'Light exercise 1-3 days/week',
            'factor' => 1.375,
        ],
        'moderately_active' => [
            'label' => 'Moderately active',
            'detail' => 'Exercise 3-5 days/week',
            'factor' => 1.55,
        ],
        'very_active' => [
            'label' => 'Very active',
            'detail' => 'Hard exercise 6-7 days/week',
            'factor' => 1.725,
        ],
        'extra_active' => [
            'label' => 'Extra active',
            'detail' => 'Physical job or intense training',
            'factor' => 1.9,
        ],
    ];
}

function plan_goal_modes(): array
{
    return [
        'lose' => [
            'label' => 'Lose weight',
            'icon' => 'fa-arrow-trend-down',
            'copy' => 'Create a controlled calorie deficit.',
        ],
        'maintain' => [
            'label' => 'Maintain',
            'icon' => 'fa-scale-balanced',
            'copy' => 'Hold your current weight steady.',
        ],
        'gain' => [
            'label' => 'Gain weight',
            'icon' => 'fa-arrow-trend-up',
            'copy' => 'Use a steady surplus for growth.',
        ],
    ];
}

function plan_calculate_bmr(int $age, string $gender, float $weightKg, float $heightCm): float
{
    $base = 10 * $weightKg + 6.25 * $heightCm - 5 * $age;
    return $gender === 'female' ? $base - 161 : $base + 5;
}

function plan_calculate_tdee(float $bmr, string $activityLevel): float
{
    $options = plan_activity_options();
    $factor = $options[$activityLevel]['factor'] ?? $options['moderately_active']['factor'];
    return $bmr * $factor;
}

function plan_clamp_goal(int $goal): int
{
    return max(800, min(10000, $goal));
}

function plan_recent_intake_summary(PDO $pdo, int $userId, int $days = 7): array
{
    $days = max(1, min(30, $days));
    $startDate = date('Y-m-d', strtotime('-' . ($days - 1) . ' days'));

    $stmt = $pdo->prepare("
        SELECT DATE(date_intake) AS d,
               COALESCE(SUM(calories), 0) AS calories,
               COALESCE(SUM(protein), 0) AS protein,
               COALESCE(SUM(carbs), 0) AS carbs,
               COALESCE(SUM(fat), 0) AS fat
        FROM intakeLog
        WHERE user_id = ?
          AND DATE(date_intake) BETWEEN ? AND CURDATE()
        GROUP BY DATE(date_intake)
    ");
    $stmt->execute([$userId, $startDate]);

    $byDate = [];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $byDate[$row['d']] = $row;
    }

    $daily = [];
    $loggedDays = 0;
    $totalCalories = 0;
    $totalProtein = 0.0;
    $totalCarbs = 0.0;
    $totalFat = 0.0;

    for ($i = $days - 1; $i >= 0; $i--) {
        $date = date('Y-m-d', strtotime("-$i days"));
        $row = $byDate[$date] ?? null;
        $calories = $row ? (int) $row['calories'] : 0;
        $protein = $row ? (float) $row['protein'] : 0.0;
        $carbs = $row ? (float) $row['carbs'] : 0.0;
        $fat = $row ? (float) $row['fat'] : 0.0;

        if ($calories > 0) {
            $loggedDays++;
            $totalCalories += $calories;
            $totalProtein += $protein;
            $totalCarbs += $carbs;
            $totalFat += $fat;
        }

        $daily[] = [
            'date' => $date,
            'label' => date('D', strtotime($date)),
            'calories' => $calories,
            'protein' => $protein,
            'carbs' => $carbs,
            'fat' => $fat,
        ];
    }

    return [
        'daily' => $daily,
        'logged_days' => $loggedDays,
        'average_calories' => $loggedDays > 0 ? (int) round($totalCalories / $loggedDays) : null,
        'average_protein' => $loggedDays > 0 ? round($totalProtein / $loggedDays, 1) : null,
        'average_carbs' => $loggedDays > 0 ? round($totalCarbs / $loggedDays, 1) : null,
        'average_fat' => $loggedDays > 0 ? round($totalFat / $loggedDays, 1) : null,
    ];
}

function plan_weight_summary(PDO $pdo, int $userId, ?float $fallbackWeight = null): array
{
    $stmt = $pdo->prepare("
        SELECT weight, date_logged
        FROM weight_log
        WHERE user_id = ?
        ORDER BY date_logged DESC, weight_id DESC
        LIMIT 8
    ");
    $stmt->execute([$userId]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $current = null;
    $currentDate = null;
    $trend = null;
    $chart = [];

    if ($rows) {
        $current = (float) $rows[0]['weight'];
        $currentDate = $rows[0]['date_logged'];
        $oldest = (float) $rows[count($rows) - 1]['weight'];
        $trend = round($current - $oldest, 1);

        foreach (array_reverse($rows) as $row) {
            $chart[] = [
                'label' => date('d/m', strtotime($row['date_logged'])),
                'weight' => (float) $row['weight'],
            ];
        }
    } elseif ($fallbackWeight !== null && $fallbackWeight > 0) {
        $current = $fallbackWeight;
    }

    return [
        'current' => $current,
        'current_date' => $currentDate,
        'trend' => $trend,
        'chart' => $chart,
    ];
}

function plan_load_preferences(PDO $pdo, int $userId): ?array
{
    $stmt = $pdo->prepare('SELECT goal_mode, weekly_rate, activity_level, target_weight FROM user_plan_preferences WHERE user_id = ?');
    $stmt->execute([$userId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$row) {
        return null;
    }

    return [
        'goal_mode'      => $row['goal_mode'],
        'weekly_rate'    => (float) $row['weekly_rate'],
        'activity_level' => $row['activity_level'],
        'target_weight'  => $row['target_weight'] !== null ? (float) $row['target_weight'] : null,
    ];
}

function plan_save_preferences(PDO $pdo, int $userId, array $prefs): void
{
    $goalModes = plan_goal_modes();
    $activityOptions = plan_activity_options();

    $goalMode = isset($prefs['goal_mode'], $goalModes[$prefs['goal_mode']]) ? $prefs['goal_mode'] : 'lose';
    $activityLevel = isset($prefs['activity_level'], $activityOptions[$prefs['activity_level']])
        ? $prefs['activity_level']
        : 'moderately_active';

    $weeklyRate = isset($prefs['weekly_rate']) && is_numeric($prefs['weekly_rate']) ? (float) $prefs['weekly_rate'] : 0.25;
    $weeklyRate = max(0.0, min(1.5, $weeklyRate));
    if ($goalMode === 'maintain') {
        $weeklyRate = 0.0;
    }

    $targetWeight = null;
    if (isset($prefs['target_weight']) && $prefs['target_weight'] !== '' && is_numeric($prefs['target_weight'])) {
        $tw = (float) $prefs['target_weight'];
        if ($tw > 0 && $tw <= 500) {
            $targetWeight = $tw;
        }
    }

    $stmt = $pdo->prepare('
        INSERT INTO user_plan_preferences (user_id, goal_mode, weekly_rate, activity_level, target_weight)
        VALUES (:user_id, :goal_mode, :weekly_rate, :activity_level, :target_weight)
        ON DUPLICATE KEY UPDATE
            goal_mode = VALUES(goal_mode),
            weekly_rate = VALUES(weekly_rate),
            activity_level = VALUES(activity_level),
            target_weight = VALUES(target_weight)
    ');
    $stmt->execute([
        ':user_id'        => $userId,
        ':goal_mode'      => $goalMode,
        ':weekly_rate'    => $weeklyRate,
        ':activity_level' => $activityLevel,
        ':target_weight'  => $targetWeight,
    ]);
}

function plan_target_eta(?float $currentWeight, ?float $targetWeight, string $mode, float $weeklyRate): ?array
{
    if ($currentWeight === null || $targetWeight === null || $weeklyRate <= 0 || $mode === 'maintain') {
        return null;
    }

    if ($mode === 'lose' && $targetWeight >= $currentWeight) {
        return ['valid' => false, 'message' => 'Target weight should be below your current weight for a loss plan.'];
    }

    if ($mode === 'gain' && $targetWeight <= $currentWeight) {
        return ['valid' => false, 'message' => 'Target weight should be above your current weight for a gain plan.'];
    }

    $kgChange = abs($targetWeight - $currentWeight);
    if ($kgChange <= 0) {
        return null;
    }

    $weeks = $kgChange / $weeklyRate;
    $days = (int) ceil($weeks * 7);
    $date = (new DateTimeImmutable('today'))->modify('+' . $days . ' days');

    return [
        'valid' => true,
        'weeks' => round($weeks, 1),
        'date' => $date->format('M j, Y'),
    ];
}
?>
