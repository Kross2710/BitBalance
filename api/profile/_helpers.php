<?php
require_once __DIR__ . '/../_bootstrap.php';

function api_profile_current_goal(PDO $pdo, $userId)
{
    $stmt = $pdo->prepare("
        SELECT calorie_goal, date_set
        FROM userGoal
        WHERE user_id = ?
        ORDER BY date_set DESC, userGoal_id DESC
        LIMIT 1
    ");
    $stmt->execute([(int) $userId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$row) {
        return null;
    }

    return [
        'calorie_goal' => (int) $row['calorie_goal'],
        'date_set' => isset($row['date_set']) ? $row['date_set'] : null
    ];
}

function api_profile_physical(PDO $pdo, $userId)
{
    $stmt = $pdo->prepare("
        SELECT age, gender, weight, height
        FROM userPhysicalInfo
        WHERE user_id = ?
        LIMIT 1
    ");
    $stmt->execute([(int) $userId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$row) {
        return [
            'age' => null,
            'gender' => null,
            'weight' => null,
            'height' => null
        ];
    }

    return [
        'age' => $row['age'] !== null ? (int) $row['age'] : null,
        'gender' => $row['gender'],
        'weight' => $row['weight'] !== null ? (float) $row['weight'] : null,
        'height' => $row['height'] !== null ? (float) $row['height'] : null
    ];
}

function api_profile_payload(PDO $pdo, array $user)
{
    return [
        'user' => api_public_user($user),
        'bio' => isset($user['profile_bio']) ? $user['profile_bio'] : '',
        'status' => isset($user['status']) ? $user['status'] : 'active',
        'goal' => api_profile_current_goal($pdo, (int) $user['user_id']),
        'physical' => api_profile_physical($pdo, (int) $user['user_id'])
    ];
}

function api_profile_nullable_int($value)
{
    if ($value === null || $value === '') {
        return null;
    }
    return (int) $value;
}

function api_profile_nullable_float($value)
{
    if ($value === null || $value === '') {
        return null;
    }
    return (float) $value;
}

function api_profile_fetch_user(PDO $pdo, $userId)
{
    $stmt = $pdo->prepare("
        SELECT u.user_id, u.user_name, u.first_name, u.last_name, u.email, u.role, u.profile_image,
               us.status, us.theme_preference, us.profile_bio
        FROM user u
        JOIN userStatus us ON u.user_id = us.user_id
        WHERE u.user_id = ?
        LIMIT 1
    ");
    $stmt->execute([(int) $userId]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}
