<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/include/init.php';
require_once __DIR__ . '/include/handlers/log_attempt.php';
require_once __DIR__ . '/include/db_config.php';

if ($isLoggedIn) {
    // Log the user activity
    log_attempt($pdo, $user['user_id'], 'view', 'User ' . $user['user_id'] . ' viewed the cart', 'cart', null);
}

$cart = isset($_SESSION['cart']) ? $_SESSION['cart'] : [];
$shipping = ($isLoggedIn) ? 4.99 : 0.00;
$tax = ($isLoggedIn) ? 2.99 : 0;

// Handle quantity update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_index'], $_POST['new_quantity'])) {
    $update_index = (int) $_POST['update_index'];
    $new_quantity = (int) $_POST['new_quantity'];

    if (isset($_SESSION['cart'][$update_index])) {
        $_SESSION['cart'][$update_index]['quantity'] = $new_quantity;

        if ($isLoggedIn) {
            $cartIdStmt = $pdo->prepare("SELECT cart_id FROM productCart WHERE user_id = ?");
            $cartIdStmt->execute([$user['user_id']]);
            $cartId = $cartIdStmt->fetchColumn();

            if ($cartId) {
                $updateStmt = $pdo->prepare("
                    UPDATE productCart_item
                    SET quantity = ?
                    WHERE cart_id = ? AND product_id = ?
                ");
                $updateStmt->execute([$new_quantity, $cartId, $_SESSION['cart'][$update_index]['id']]);
            }
        }
    }

    header("Location: cart.php");
    exit;
}

// Handle item removal
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['remove_index'])) {
    $remove_index = (int) $_POST['remove_index'];

    if (isset($_SESSION['cart'][$remove_index])) {
        // Grab and remove from session
        $removedItem = $_SESSION['cart'][$remove_index];
        array_splice($_SESSION['cart'], $remove_index, 1);

        // If logged in, also remove from DB (productCart_item)
        if ($isLoggedIn) {
            // Get the user's open cart_id
            $cartIdStmt = $pdo->prepare("SELECT cart_id FROM productCart WHERE user_id = ?");
            $cartIdStmt->execute([$user['user_id']]);
            $cartId = $cartIdStmt->fetchColumn();

            if ($cartId) {
                $del = $pdo->prepare("DELETE FROM productCart_item WHERE cart_id = ? AND product_id = ?");
                $del->execute([$cartId, $removedItem['id']]);
            }
        }

        // Log already did but you can log here too (fallback)
        // log_attempt($pdo, $user['user_id'], 'remove_from_cart', 'User removed product from cart', 'product', $removedItem['id']);
    }

    header("Location: cart.php");
    exit;
}

// Calculate subtotal and total items
$subtotal = 0;
$total_items = 0;
foreach ($cart as $item) {
    $subtotal += $item['price'] * $item['quantity'];
    $total_items += $item['quantity'];
}
$grand_total = $subtotal + $shipping + $tax;
?>
<!DOCTYPE html>

<html lang="en"
    data-theme="<?php echo isset($_SESSION['user']) ? ($_SESSION['user']['theme_preference'] ?? 'light') : 'light'; ?>">

<head>
    <meta charset="UTF-8">
    <title>My Cart - BitBalance</title>
    <link rel="stylesheet" href="css/cart.css">
    <link rel="stylesheet" href="css/style.css">
    <script src="https://kit.fontawesome.com/b94f65ead2.js" crossorigin="anonymous"></script>
</head>

<body>
    <?php include 'views/header.php'; ?>
    <main>
        <h1 class="cart-title">My Cart</h1>
        <div class="cart-container">
            <div class="cart-items">
                <?php if (empty($cart)): ?>
                    <p>Your cart is empty.</p>
                <?php else: ?>
                    <table>
                        <tr>
                            <th></th>
                            <th>Item</th>
                            <th>Price</th>
                            <th>Quantity</th>
                            <th>Total</th>
                            <th></th>
                        </tr>
                        <?php foreach ($cart as $index => $item): ?>
                            <tr>
                                <td>
                                    <img class="cart-img" src="images/<?php echo htmlspecialchars($item['name']); ?>.png"
                                        alt="<?php echo htmlspecialchars($item['name']); ?>">
                                </td>
                                <td><?php echo htmlspecialchars($item['name']); ?></td>
                                <td>$<?php echo htmlspecialchars($item['price']); ?></td>
                                <td>
                                    <form method="post" action="cart.php" class="quantity-form">
                                        <input type="hidden" name="update_index" value="<?php echo $index; ?>">
                                        <select name="new_quantity" onchange="this.form.submit()">
                                            <?php for ($i = 1; $i <= 10; $i++): ?>
                                                <option value="<?php echo $i; ?>" <?php echo ($item['quantity'] == $i) ? 'selected' : ''; ?>>
                                                    <?php echo $i; ?>
                                                </option>
                                            <?php endfor; ?>
                                        </select>
                                    </form>
                                </td>
                                <td>$<?php echo number_format($item['price'] * $item['quantity'], 2); ?></td>
                                <td>
                                    <form method="post" action="cart.php" style="display:inline;">
                                        <input type="hidden" name="remove_index" value="<?php echo $index; ?>">
                                        <button type="submit" class="remove-btn" data-product-id="<?php echo $item['id']; ?>"
                                            onclick="return confirm('Remove this product from cart?');">
                                            Remove
                                        </button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </table>
                    <form action="products.php" method="get">
                        <button class="add-more-btn" type="submit">Add More</button>
                    </form>
                <?php endif; ?>
            </div>

            <div class="cart-summary">
                <h2>Summary</h2>
                <div class="summary-row">
                    <span>Subtotal (<?php echo $total_items; ?> Items):</span>
                    <span>$<?php echo number_format($subtotal, 2); ?></span>
                </div>
                <div class="summary-row">
                    <span>Shipping:</span>
                    <span>$<?php echo number_format($shipping, 2); ?></span>
                </div>
                <div class="summary-row">
                    <span>Tax (Calculated at checkout):</span>
                    <span>$<?php echo number_format($tax, 2); ?></span>
                </div>
                <hr>
                <div class="summary-row" style="font-weight:bold;">
                    <span>Grand total:</span>
                    <span>$<?php echo number_format($grand_total, 2); ?></span>
                </div>

                <form id="checkout-form" action="purchase.php" method="post">
                    <input type="hidden" name="cart" value="<?php echo htmlspecialchars(serialize($cart)); ?>">
                    <input type="hidden" name="subtotal" value="<?php echo htmlspecialchars($subtotal); ?>">
                    <input type="hidden" name="shipping" value="<?php echo htmlspecialchars($shipping); ?>">
                    <input type="hidden" name="tax" value="<?php echo htmlspecialchars($tax); ?>">
                    <input type="hidden" name="grand_total" value="<?php echo htmlspecialchars($grand_total); ?>">
                    <button type="submit" class="checkout-btn">Checkout</button>
                </form>
            </div>
        </div>
    </main>
</body>

</html>
<script>
    // Send log_event.php fetch before item removal
    document.querySelectorAll('.remove-btn').forEach(btn => {
        btn.addEventListener('click', () => {
            const productId = btn.dataset.productId;
            fetch('include/handlers/log_event.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    action_type: 'remove_from_cart',
                    description: 'User removed product from cart',
                    target_table: 'product',
                    target_id: productId
                })
            });
        });
    });
</script>

<style>
    /* Dark Mode Styles for Cart Page */
    [data-theme="dark"] body {
        background-color: #1a1a1a !important;
        color: #ffffff !important;
    }

    [data-theme="dark"] main {
        background-color: #1a1a1a !important;
        color: #ffffff !important;
    }

    [data-theme="dark"] .cart-title {
        color: #ffffff !important;
    }

    [data-theme="dark"] .cart-container {
        background-color: #1a1a1a !important;
    }

    [data-theme="dark"] .cart-items {
        background-color: #2d2d2d !important;
        border-color: #495057 !important;
        color: #ffffff !important;
    }

    [data-theme="dark"] .cart-items p {
        color: #adb5bd !important;
    }

    [data-theme="dark"] table {
        background-color: #2d2d2d !important;
        color: #ffffff !important;
    }

    [data-theme="dark"] th {
        background-color: #495057 !important;
        color: #ffffff !important;
        border-bottom-color: #495057 !important;
    }

    [data-theme="dark"] td {
        border-bottom-color: #495057 !important;
        color: #ffffff !important;
    }

    [data-theme="dark"] tr:hover {
        background-color: #495057 !important;
    }

    [data-theme="dark"] .cart-img {
        border-color: #495057 !important;
    }

    [data-theme="dark"] select {
        background-color: #2d2d2d !important;
        color: #ffffff !important;
        border-color: #495057 !important;
    }

    [data-theme="dark"] .remove-btn {
        background-color: #dc3545 !important;
        color: white !important;
    }

    [data-theme="dark"] .add-more-btn {
        background-color: #6c757d !important;
        color: white !important;
    }

    [data-theme="dark"] .add-more-btn:hover {
        background-color: #5a6268 !important;
    }

    [data-theme="dark"] .cart-summary {
        background-color: #2d2d2d !important;
        border-color: #495057 !important;
        color: #ffffff !important;
    }

    [data-theme="dark"] .cart-summary h2 {
        color: #ffffff !important;
    }

    [data-theme="dark"] .summary-row {
        color: #ffffff !important;
        border-bottom-color: #495057 !important;
    }

    [data-theme="dark"] .summary-row span {
        color: #ffffff !important;
    }

    [data-theme="dark"] hr {
        border-top-color: #495057 !important;
    }

    [data-theme="dark"] .checkout-btn {
        background-color: #4a7ee3 !important;
        color: white !important;
    }

    [data-theme="dark"] .checkout-btn:hover {
        background-color: #3b6bd6 !important;
    }
</style>