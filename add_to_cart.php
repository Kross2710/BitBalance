<?php
/**
 * add_to_cart.php
 *
 * Accepts POST (product_id, product_name, product_price) and:
 *   1. Ensures the user has one open cart in productCart (creates if missing)
 *   2. Inserts / updates the product in productCart_item (increments quantity)
 *   3. Mirrors the change in the session cart array
 *   4. Logs the action via log_attempt()
 *   5. Returns JSON { success: bool, cart_count: int }
 */

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/include/init.php';
require_once __DIR__ . '/include/handlers/log_attempt.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request']);
    exit;
}

if (empty($_SESSION['user']['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Not logged in']);
    exit;
}

$userId = (int) $_SESSION['user']['user_id'];
$productId = isset($_POST['product_id']) ? (int) $_POST['product_id'] : 0;
$productName = isset($_POST['product_name']) ? trim($_POST['product_name']) : '';
$price = isset($_POST['product_price']) ? (float) $_POST['product_price'] : 0.0;

if (!$productId || !$productName) {
    echo json_encode(['success' => false, 'message' => 'Missing product data']);
    exit;
}

//  1. Get (or create) the user’s open cart in productCart
$cartId = null;
$stmt = $pdo->prepare("SELECT cart_id FROM productCart WHERE user_id = ?");
$stmt->execute([$userId]);
$cartId = $stmt->fetchColumn();

if (!$cartId) {
    $pdo->prepare("INSERT INTO productCart (user_id) VALUES (?)")->execute([$userId]);
    $cartId = $pdo->lastInsertId();
}

// 2. Insert / update productCart_item
$upsert = $pdo->prepare("
    INSERT INTO productCart_item (cart_id, product_id, quantity)
    VALUES (?, ?, 1)
    ON DUPLICATE KEY UPDATE quantity = quantity + 1
");
$upsert->execute([$cartId, $productId]);

//3. Mirror the cart in session (optional but keeps header badge in sync)
if (!isset($_SESSION['cart'])) {
    $_SESSION['cart'] = [];
}

$found = false;
foreach ($_SESSION['cart'] as &$item) {
    if ($item['id'] === $productId) {
        $item['quantity']++;
        $found = true;
        break;
    }
}
unset($item);

if (!$found) {
    $_SESSION['cart'][] = [
        'id' => $productId,
        'name' => $productName,
        'price' => $price,
        'quantity' => 1
    ];
}

// 4. Log activity
if ($isLoggedIn) {
    log_attempt(
        $pdo,
        $userId,
        'add_to_cart',
        "Added $productName to cart",
        'product',
        $productId
    );
}

// 5. Get total item count for badge
$totalStmt = $pdo->prepare("
    SELECT COALESCE(SUM(quantity),0)
    FROM productCart_item
    WHERE cart_id = ?
");
$totalStmt->execute([$cartId]);
$cartCount = (int) $totalStmt->fetchColumn();

// 6. Return JSON
echo json_encode([
    'success' => true,
    'cart_count' => $cartCount
]);