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

function getAllProducts()
{
    global $pdo;

    $stmt = $pdo->prepare("SELECT * FROM product");
    $stmt->execute();
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

function getAllOrders()
{
    global $pdo;

    $stmt = $pdo->prepare("SELECT o.order_id, u.email, o.user_id, o.cart_id, o.order_status, 
                                  o.subtotal, o.discount, o.shipping_cost, o.tax, o.grand_total, o.created_at 
                                  FROM `order` o
                                  Join user u ON o.user_id = u.user_id
                                  ORDER BY o.created_at DESC
                                ");
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function getTotalOrders()
{
    global $pdo;

    $stmt = $pdo->prepare("
        SELECT COUNT(*) AS total_orders 
        FROM `order`
    ");
    $stmt->execute();
    return $stmt->fetchColumn();
}

// Prepare data for the total orders chart
$ordersData = [];
$historyOrderLabels = [];

// Get the past 6 months
for ($i = 5; $i >= 0; $i--) {
    $date = date('Y-m', strtotime("-$i months"));
    $start = $date . '-01';
    $end = date('Y-m-t', strtotime($start)); // Last day of the month

    $stmt = $pdo->prepare("
        SELECT COUNT(*) as total 
        FROM `order` 
        WHERE DATE(created_at) BETWEEN ? AND ?
    ");
    $stmt->execute([$start, $end]);
    $total = $stmt->fetchColumn();
    $ordersData[] = (int) $total;
    $historyOrderLabels[] = date('M', strtotime($date));
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

// Prepare data for the last 7 days log count chart
$logData = [];
$historyLogLabels = [];

for ($i = 6; $i >= 0; $i--) {
    $date = date('Y-m-d', strtotime("-$i days"));
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as total 
        FROM activity_log 
        WHERE DATE(created_at) = ?
    ");
    $stmt->execute([$date]);
    $total = $stmt->fetchColumn();
    $logData[] = (int) $total;
    $historyLogLabels[] = date('D', strtotime($date));
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

// Prepare data for the total posts and comments chart
$postData = [];
$commentData = [];
$postCommentLabels = [];

for ($i = 6; $i >= 0; $i--) {
    $date = date('Y-m-d', strtotime("-$i days"));
    $stmt = $pdo->prepare("
        SELECT 
            (SELECT COUNT(*) FROM forumPost WHERE DATE(date_posted) = ?) AS total_posts,
            (SELECT COUNT(*) FROM forumComment WHERE DATE(date_posted) = ?) AS total_comments
    ");
    $stmt->execute([$date, $date]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    
    $postData[] = (int) $result['total_posts'];
    $commentData[] = (int) $result['total_comments'];
    $postCommentLabels[] = date('D', strtotime($date));
}

function getStreakUpdatedByUser()
{
    global $pdo;

    $stmt = $pdo->prepare("
        SELECT COUNT(*) AS total_streaks 
        FROM activity_log 
        WHERE action_type = 'streak_update'
    ");
    $stmt->execute();
    return $stmt->fetchColumn();
}

// Prepare data for the streaks chart
$streakData = [];
$streakLabels = [];
for ($i = 6; $i >= 0; $i--) {
    $date = date('Y-m-d', strtotime("-$i days"));
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as total 
        FROM activity_log 
        WHERE action_type = 'streak_update' AND DATE(created_at) = ?
    ");
    $stmt->execute([$date]);
    $total = $stmt->fetchColumn();
    $streakData[] = (int) $total;
    $streakLabels[] = date('D', strtotime($date));
}
?>
