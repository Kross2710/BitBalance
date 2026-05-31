<?php
require_once PROJECT_ROOT . '/include/db_config.php'; // Include database configuration

// Fetch total user created that are role is regular user
function getTotalUsers()
{
    global $pdo;

    $stmt = $pdo->prepare("
        SELECT COUNT(*) AS total_users 
        FROM user
        WHERE role = 'regular'
    ");
    $stmt->execute();
    return $stmt->fetchColumn();
}

// Prepare data for the total users chart
$usersData = [];
$historyUserLabels = [];

// Get the past 6 months
for ($i = 5; $i >= 0; $i--) {
    $date = date('Y-m', strtotime("-$i months"));
    $start = $date . '-01';
    $end = date('Y-m-t', strtotime($start)); // Last day of the month

    $stmt = $pdo->prepare("
        SELECT COUNT(*) as total 
        FROM user 
        WHERE role = 'regular' AND DATE(timeCreated) BETWEEN ? AND ?
    ");
    $stmt->execute([$start, $end]);
    $total = $stmt->fetchColumn();
    $usersData[] = (int) $total;
    $historyUserLabels[] = date('M', strtotime($date));
}

// Fetch all users for the user list
function getAllUsers()
{
    global $pdo;

    $stmt = $pdo->prepare("
    SELECT u.user_id, u.user_name, u.first_name, u.last_name, u.email, u.role, u.timeCreated, u.last_login, s.status
    FROM `user` AS u
    LEFT JOIN `userStatus` AS s
    ON u.user_id = s.user_id
    ORDER BY u.timeCreated DESC;
");
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Function to get user based on search term and filter
function getUsersBySearchAndFilter($searchTerm = '', $filterRole = '')
{
    global $pdo;

    $query = "
        SELECT u.user_id, u.user_name, u.first_name, u.last_name, u.email, u.role, u.timeCreated, u.last_login, s.status
        FROM `user` AS u
        LEFT JOIN `userStatus` AS s ON u.user_id = s.user_id
        WHERE u.role = 'regular'
    ";

    $params = [];
    if ($searchTerm) {
        $query .= " AND (u.user_name LIKE ? OR u.first_name LIKE ? OR u.last_name LIKE ?)";
        $params[] = "%$searchTerm%";
        $params[] = "%$searchTerm%";
        $params[] = "%$searchTerm%";
    }

    if ($filterRole) {
        $query .= " AND u.role = ?";
        $params[] = $filterRole;
    }

    $query .= " ORDER BY u.timeCreated DESC";

    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Workin on this part
function getActivityLogs($searchTerm = '', $filterAction = '', $filterRole = '')
{
    global $pdo; // Use the global PDO instance
    $query = "SELECT u.user_name, u.role, a.action_type, a.description, a.target_table, a.target_id, a.created_at 
              FROM activity_log as a 
              JOIN user as u ON a.user_id = u.user_id 
              WHERE 1=1";
    $params = [];

    if ($searchTerm) {
        $query .= " AND (u.user_name LIKE ? OR a.target_table LIKE ?)";
        $params[] = "%$searchTerm%";
        $params[] = "%$searchTerm%";
    }

    if ($filterAction) {
        $query .= " AND a.action_type = ?";
        $params[] = $filterAction;
    }

    if ($filterRole) {
        $query .= " AND u.role = ?";
        $params[] = $filterRole;
    }

    $query .= " ORDER BY a.created_at DESC"; // Order by timestamp descending
    $stmt = $pdo->prepare($query);
    $stmt->execute($params);

    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function getActivityLogsPaginated($page, $limit, $searchTerm = '', $filterAction = '', $filterRole = '')
{
    global $pdo;
    
    $queryBase = "FROM activity_log as a 
                  JOIN user as u ON a.user_id = u.user_id 
                  WHERE 1=1";
    $params = [];

    if ($searchTerm) {
        $queryBase .= " AND (u.user_name LIKE ? OR u.first_name LIKE ? OR u.last_name LIKE ? OR u.email LIKE ? OR a.action_type LIKE ? OR a.target_table LIKE ? OR a.description LIKE ?)";
        $params[] = "%$searchTerm%";
        $params[] = "%$searchTerm%";
        $params[] = "%$searchTerm%";
        $params[] = "%$searchTerm%";
        $params[] = "%$searchTerm%";
        $params[] = "%$searchTerm%";
        $params[] = "%$searchTerm%";
    }

    if ($filterAction) {
        $queryBase .= " AND a.action_type = ?";
        $params[] = $filterAction;
    }

    if ($filterRole) {
        $queryBase .= " AND u.role = ?";
        $params[] = $filterRole;
    }

    // 1. Get total count
    $countQuery = "SELECT COUNT(*) " . $queryBase;
    $countStmt = $pdo->prepare($countQuery);
    $countStmt->execute($params);
    $totalCount = (int)$countStmt->fetchColumn();

    // 2. Get paginated results
    $page = max(1, (int)$page);
    $limit = max(1, (int)$limit);
    $offset = ($page - 1) * $limit;

    $dataQuery = "SELECT u.user_name, u.role, a.action_type, a.description, a.target_table, a.target_id, a.created_at " 
                 . $queryBase 
                 . " ORDER BY a.created_at DESC LIMIT " . $limit . " OFFSET " . $offset;
                 
    $dataStmt = $pdo->prepare($dataQuery);
    $dataStmt->execute($params);
    $logs = $dataStmt->fetchAll(PDO::FETCH_ASSOC);

    return [
        'logs' => $logs,
        'total' => $totalCount,
        'totalPages' => (int)ceil($totalCount / $limit)
    ];
}

function getAllPosts()
{
    global $pdo;

    $stmt = $pdo->prepare("
        SELECT p.post_id, p.title, p.content, p.user_id, p.date_posted, p.status, u.user_name 
        FROM forumPost p 
        JOIN user u ON p.user_id = u.user_id 
        ORDER BY p.date_posted DESC
    ");
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function getAllComments()
{
    global $pdo;

    $stmt = $pdo->prepare("
        SELECT c.comment_id, c.post_id, c.user_id, c.content, c.status, c.date_posted, u.user_name 
        FROM forumComment c 
        JOIN user u ON c.user_id = u.user_id 
        ORDER BY c.date_posted DESC
    ");
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function getAllLikes()
{
    global $pdo;

    $stmt = $pdo->prepare("
        SELECT l.like_id, u.user_name, l.type, l.target_id, l.date_liked
        FROM forumLike l
        JOIN user u ON l.user_id = u.user_id
        ORDER BY l.date_liked DESC
    ");
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function Last7DaysLogCount()
{
    global $pdo;

    $stmt = $pdo->prepare("
        SELECT COUNT(*) AS log_count 
        FROM activity_log 
        WHERE created_at >= NOW() - INTERVAL 7 DAY
    ");
    $stmt->execute();
    return $stmt->fetchColumn();
}

// Prepare data for the last 7 days log count chart (Optimized to a single query!)
$logData = array_fill(0, 7, 0);
$historyLogLabels = [];
$logDateMap = [];

for ($i = 6; $i >= 0; $i--) {
    $date = date('Y-m-d', strtotime("-$i days"));
    $logDateMap[$date] = 6 - $i;
    $historyLogLabels[] = date('D', strtotime($date));
}

$startDate = date('Y-m-d 00:00:00', strtotime("-6 days"));
$stmt = $pdo->prepare("
    SELECT DATE(created_at) as log_date, COUNT(*) as total 
    FROM activity_log 
    WHERE created_at >= ?
    GROUP BY DATE(created_at)
");
$stmt->execute([$startDate]);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

foreach ($rows as $row) {
    $d = $row['log_date'];
    if (isset($logDateMap[$d])) {
        $logData[$logDateMap[$d]] = (int)$row['total'];
    }
}

function getTotalPosts()
{
    global $pdo;

    $stmt = $pdo->prepare("SELECT COUNT(*) AS total_posts FROM forumPost");
    $stmt->execute();
    return $stmt->fetchColumn();
}

function getTotalComments()
{
    global $pdo;

    $stmt = $pdo->prepare("SELECT COUNT(*) AS total_comments FROM forumComment");
    $stmt->execute();
    return $stmt->fetchColumn();
}

// Prepare data for the total posts and comments chart (Optimized to single queries!)
$postData = array_fill(0, 7, 0);
$commentData = array_fill(0, 7, 0);
$postCommentLabels = [];
$postCommentDateMap = [];

for ($i = 6; $i >= 0; $i--) {
    $date = date('Y-m-d', strtotime("-$i days"));
    $postCommentDateMap[$date] = 6 - $i;
    $postCommentLabels[] = date('D', strtotime($date));
}

// Single high-performance query for posts
$stmtPosts = $pdo->prepare("
    SELECT DATE(date_posted) as post_date, COUNT(*) as total
    FROM forumPost
    WHERE date_posted >= ?
    GROUP BY DATE(date_posted)
");
$stmtPosts->execute([$startDate]);
$postRows = $stmtPosts->fetchAll(PDO::FETCH_ASSOC);

foreach ($postRows as $row) {
    $d = $row['post_date'];
    if (isset($postCommentDateMap[$d])) {
        $postData[$postCommentDateMap[$d]] = (int)$row['total'];
    }
}

// Single high-performance query for comments
$stmtComments = $pdo->prepare("
    SELECT DATE(date_posted) as comment_date, COUNT(*) as total
    FROM forumComment
    WHERE date_posted >= ?
    GROUP BY DATE(date_posted)
");
$stmtComments->execute([$startDate]);
$commentRows = $stmtComments->fetchAll(PDO::FETCH_ASSOC);

foreach ($commentRows as $row) {
    $d = $row['comment_date'];
    if (isset($postCommentDateMap[$d])) {
        $commentData[$postCommentDateMap[$d]] = (int)$row['total'];
    }
}

function getStreakUpdatedByUser()
{
    global $pdo;

    $stmt = $pdo->prepare("
        SELECT COUNT(*) AS total_streaks
        FROM activity_log
        WHERE action_type = 'streak_update'
          AND created_at >= NOW() - INTERVAL 7 DAY
    ");
    $stmt->execute();
    return $stmt->fetchColumn();
}

// Prepare data for the streaks chart (Optimized to a single query!)
$streakData = array_fill(0, 7, 0);
$streakLabels = [];
$streakDateMap = [];

for ($i = 6; $i >= 0; $i--) {
    $date = date('Y-m-d', strtotime("-$i days"));
    $streakDateMap[$date] = 6 - $i;
    $streakLabels[] = date('D', strtotime($date));
}

$stmt = $pdo->prepare("
    SELECT DATE(created_at) as streak_date, COUNT(*) as total
    FROM activity_log
    WHERE action_type = 'streak_update' AND created_at >= ?
    GROUP BY DATE(created_at)
");
$stmt->execute([$startDate]);
$streakRows = $stmt->fetchAll(PDO::FETCH_ASSOC);

foreach ($streakRows as $row) {
    $d = $row['streak_date'];
    if (isset($streakDateMap[$d])) {
        $streakData[$streakDateMap[$d]] = (int)$row['total'];
    }
}

function getTodayNewUsers()
{
    global $pdo;
    $stmt = $pdo->prepare("
        SELECT COUNT(*) FROM user
        WHERE role = 'regular' AND DATE(timeCreated) = CURDATE()
    ");
    $stmt->execute();
    return (int) $stmt->fetchColumn();
}

function getUserStatusBreakdown()
{
    global $pdo;
    $stmt = $pdo->prepare("
        SELECT s.status, COUNT(*) AS total
        FROM userStatus s
        JOIN user u ON u.user_id = s.user_id
        WHERE u.role = 'regular'
        GROUP BY s.status
    ");
    $stmt->execute();
    $rows = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
    return [
        'active'   => (int) ($rows['active']   ?? 0),
        'banned'   => (int) ($rows['banned']   ?? 0),
        'archived' => (int) ($rows['archived'] ?? 0),
    ];
}

function getRecentActivity($limit = 10)
{
    global $pdo;
    $limit = max(1, min(100, (int) $limit));
    $stmt = $pdo->prepare("
        SELECT u.user_name, u.role, a.action_type, a.description,
               a.target_table, a.target_id, a.created_at
        FROM activity_log a
        JOIN user u ON u.user_id = a.user_id
        ORDER BY a.created_at DESC
        LIMIT $limit
    ");
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function getAverageCaloriesByCategory()
{
    global $pdo;
    $stmt = $pdo->prepare("
        SELECT meal_category, ROUND(AVG(calories)) as avg_cal
        FROM intakeLog
        GROUP BY meal_category
    ");
    $stmt->execute();
    $rows = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
    
    $categories = ['breakfast', 'lunch', 'dinner', 'snack'];
    $out = [];
    foreach ($categories as $cat) {
        $out[$cat] = (int)($rows[$cat] ?? 0);
    }
    return $out;
}

function getTopBeatsVibes()
{
    global $pdo;
    $stmt = $pdo->prepare("
        SELECT detected_vibe, COUNT(*) as total
        FROM beats_mix_log
        WHERE detected_vibe != ''
        GROUP BY detected_vibe
        ORDER BY total DESC
        LIMIT 5
    ");
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function getTotalFoodLogged()
{
    global $pdo;
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM intakeLog");
    $stmt->execute();
    return (int)$stmt->fetchColumn();
}
?>
