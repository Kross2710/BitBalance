<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start(); // Make sure session is started for coupon session storage

require_once __DIR__ . '/include/init.php';
require_once __DIR__ . '/include/handlers/log_attempt.php';
require_once __DIR__ . '/include/db_config.php';
require_once __DIR__ . '/include/fees.php';

$shipping = get_site_fee($pdo, 'shipping');
$tax = get_site_fee($pdo, 'tax');

if ($isLoggedIn) {
    log_attempt($pdo, $user['user_id'], 'view', 'User ' . $user['user_id'] . ' checked out their products', 'checkout', null);
}

$cart = $_SESSION['cart'] ?? [];
$subtotal = 0;
$total_items = 0;

foreach ($cart as $item) {
    $subtotal += $item['price'] * $item['quantity'];
    $total_items += $item['quantity'];
}

$valid_coupons = [
    'SAVE10' => 10.00,
    'HALFPRICE' => 0.5,
    'FREESHIP' => 'free_shipping'
];

// Get applied coupon from session and calculate discount & adjust shipping
$applied_coupon = $_SESSION['applied_coupon'] ?? '';
$discount = 0;

if ($applied_coupon && array_key_exists($applied_coupon, $valid_coupons)) {
    $value = $valid_coupons[$applied_coupon];
    if (is_numeric($value)) {
        $discount = ($value < 1) ? $subtotal * $value : $value;
    } elseif ($value === 'free_shipping') {
        $shipping = 0;
    }
}

$grand_total = $subtotal - $discount + $shipping + $tax;

// Retrieve cart_id for order
$cartIdStmt = $pdo->prepare("SELECT cart_id FROM productCart WHERE user_id = ?");
$cartIdStmt->execute([$user['user_id']]);
$cartId = $cartIdStmt->fetchColumn();

if (!$cartId) {
    $_SESSION['flash_error'] = 'Your cart is empty.';
    header('Location: cart.php');
    exit;
}

// On final purchase submission
if (
    $_SERVER['REQUEST_METHOD'] === 'POST' &&
    isset($_POST['card_number']) &&
    $subtotal > 0
) {
    if ($isLoggedIn) {
        try {
            $pdo->beginTransaction();

            $stmt = $pdo->prepare("
                INSERT INTO `order`
                    (user_id, cart_id, order_status, subtotal, discount,
                     shipping_cost, tax, grand_total)
                VALUES
                    (?, ?, 'completed', ?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $user['user_id'],
                $cartId,
                $subtotal,
                $discount,
                $shipping,
                $tax,
                $grand_total
            ]);
            $order_id = $pdo->lastInsertId();

            $insert_item = $pdo->prepare("
                INSERT INTO order_item
                    (order_id, product_id, product_name, price_each, quantity, line_total)
                VALUES (?, ?, ?, ?, ?, ?)
            ");
            foreach ($cart as $item) {
                $line_total = $item['price'] * $item['quantity'];
                $insert_item->execute([
                    $order_id,
                    $item['id'],
                    $item['name'],
                    $item['price'],
                    $item['quantity'],
                    $line_total
                ]);
            }

            $pdo->commit();

            log_attempt($pdo, $user['user_id'], 'order_created', "Order #{$order_id} created with {$total_items} items totaling \${$grand_total}", 'order', $order_id);

            $pdo->prepare("DELETE FROM productCart_item WHERE cart_id = ?")->execute([$cartId]);
            $pdo->prepare("UPDATE productCart SET closed_at = NOW() WHERE cart_id = ?")->execute([$cartId]);

            // Clear cart and coupon after successful purchase
            unset($_SESSION['cart'], $_SESSION['applied_coupon']);

            log_attempt($pdo, $user['user_id'], 'purchase_complete', sprintf(
                'User %d completed purchase: %d items, $%.2f total',
                $user['user_id'], $total_items, $grand_total
            ));
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }

        header('Location: index.php');
        exit;
    }
}

?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <?php include PROJECT_ROOT . 'views/header.php'; ?>
    <title>Final Step - BitBalance</title>
    <link rel="stylesheet" href="css/products.css">
    <link rel="stylesheet" href="css/style.css">
    <link rel="stylesheet" href="css/purchase.css">
    <script src="https://kit.fontawesome.com/b94f65ead2.js" crossorigin="anonymous"></script>
    <style>
        .input-error {
            border: 2px solid red !important;
            background-color: #ffe6e6;
        }

        .error-message {
            color: red;
            font-size: 0.9em;
            margin-top: 2px;
            margin-bottom: 10px;
            display: block;
        }
    </style>
</head>

<body>
    <main>
        <h1 class="purchase-title">Final Step, complete your purchase</h1>
        <div class="purchase-container">
            <form class="purchase-form" method="post" novalidate>
                <h2 class="section-heading">Shipping Information</h2>
                <input type="text" name="name" placeholder="Full Name" required>
                <input type="email" name="email" placeholder="Email Address" required>
                <input type="text" name="address" placeholder="Street Address" required>
                <input type="text" name="city" placeholder="City" required>
                <input type="text" name="state" placeholder="State/Province" required>
                <input type="text" name="zip" placeholder="ZIP/Postal Code" required>
                <input type="text" name="country" placeholder="Country" required>

                <h2 class="section-heading">Have a Coupon?</h2>
                <div class="coupon-row">
                    <input type="text" id="coupon-code" name="coupon_code" placeholder="Enter Coupon Code" value="">
                    <button type="button" id="apply-coupon" class="apply-coupon-button">Apply</button>
                </div>
                <p id="coupon-message" class="coupon-message"></p>

                <h2 class="section-heading">Payment Information</h2>
                <input type="text" id="card_number" name="card_number" placeholder="Card Number" inputmode="numeric"
                    maxlength="16" required>
                <span class="error-message" id="card-error"></span>

                <div class="row">
                    <input type="text" id="expiry_date" name="expiry_date" placeholder="MM/YY" inputmode="numeric"
                        maxlength="5" required>
                    <span class="error-message" id="expiry-error"></span>

                    <input type="text" id="cvv" name="cvv" placeholder="CVV" inputmode="numeric" maxlength="3" required>
                    <span class="error-message" id="cvv-error"></span>
                </div>

                <button type="submit" class="purchase-button">Complete Purchase</button>
            </form>

            <div class="summary-box">
                <h2>Summary</h2>
                <?php foreach ($cart as $item): ?>
                    <div class="summary-product">
                        <img class="summary-img" src="images/<?php echo htmlspecialchars($item['name']); ?>.png"
                            alt="<?php echo htmlspecialchars($item['name']); ?>">
                        <div class="summary-details">
                            <strong><?php echo htmlspecialchars($item['name']); ?></strong>
                            <p>Qty: <?php echo $item['quantity']; ?></p>
                            <p>$<?php echo number_format($item['price'] * $item['quantity'], 2); ?></p>
                        </div>
                    </div>
                <?php endforeach; ?>
                <div class="summary-row">
                    <span>Subtotal (<?php echo $total_items; ?> Items):</span>
                    <span id="summary-subtotal">$<?php echo number_format($subtotal, 2); ?></span>
                </div>
                <div class="summary-row">
                    <span>Shipping:</span>
                    <span id="summary-shipping">$<?php echo number_format($shipping, 2); ?></span>
                </div>
                <div class="summary-row">
                    <span>Tax (Calculated at checkout):</span>
                    <span id="summary-tax">$<?php echo number_format($tax, 2); ?></span>
                </div>
                <?php if ($discount > 0): ?>
                    <div class="summary-row coupon-discount">
                        <span>Coupon (<?php echo htmlspecialchars($applied_coupon); ?>):</span>
                        <span id="summary-discount">-$<?php echo number_format($discount, 2); ?></span>
                    </div>
                <?php elseif ($applied_coupon === 'FREESHIP'): ?>
                    <div class="summary-row coupon-discount">
                        <span>Coupon (<?php echo htmlspecialchars($applied_coupon); ?>):</span>
                        <span>Free Shipping Applied</span>
                    </div>
                <?php else: ?>
                    <div class="summary-row coupon-discount" style="display:none;">
                        <span>Coupon:</span><span id="summary-discount"></span>
                    </div>
                <?php endif; ?>
                <hr>
                <div class="summary-row summary-total">
                    <span>Grand total:</span>
                    <span id="summary-grandtotal">$<?php echo number_format($grand_total, 2); ?></span>
                </div>
            </div>
        </div>
    </main>

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const form = document.querySelector('.purchase-form');
            const cardInput = document.getElementById('card_number');
            const expiryInput = document.getElementById('expiry_date');
            const cvvInput = document.getElementById('cvv');

            const cardError = document.getElementById('card-error');
            const expiryError = document.getElementById('expiry-error');
            const cvvError = document.getElementById('cvv-error');

            const couponBtn = document.getElementById('apply-coupon');
            const couponInput = document.getElementById('coupon-code');
            const message = document.getElementById('coupon-message');

            // Validate payment form on submit
            form.addEventListener('submit', function (e) {
                cardError.textContent = '';
                expiryError.textContent = '';
                cvvError.textContent = '';
                cardInput.classList.remove('input-error');
                expiryInput.classList.remove('input-error');
                cvvInput.classList.remove('input-error');

                let valid = true;

                if (cardInput.value.length !== 16) {
                    cardInput.classList.add('input-error');
                    cardError.textContent = 'Card number must be 16 digits.';
                    valid = false;
                }

                const expiryRaw = expiryInput.value.replace(/\D/g, '');
                const mm = parseInt(expiryRaw.slice(0, 2), 10);
                if (expiryRaw.length !== 4 || mm < 1 || mm > 12) {
                    expiryInput.classList.add('input-error');
                    expiryError.textContent = 'Expiry must be in MM/YY format with a valid month.';
                    valid = false;
                }

                if (cvvInput.value.length !== 3) {
                    cvvInput.classList.add('input-error');
                    cvvError.textContent = 'CVV must be 3 digits.';
                    valid = false;
                }

                if (!valid) {
                    e.preventDefault();
                }
            });

            // Limit input to numbers only
            [cardInput, cvvInput].forEach(input => {
                input.addEventListener('input', () => {
                    input.value = input.value.replace(/\D/g, '');
                });
            });

            // Auto-format expiry date MM/YY
            expiryInput.addEventListener('input', () => {
                let val = expiryInput.value.replace(/\D/g, '');
                if (val.length > 2) {
                    val = val.slice(0, 2) + '/' + val.slice(2, 4);
                }
                expiryInput.value = val;
            });

            // Coupon apply AJAX
            couponBtn.addEventListener('click', function () {
                const code = couponInput.value.trim();
                if (!code) {
                    message.textContent = "Please enter a coupon code.";
                    return;
                }

                message.textContent = 'Applying coupon...';

                fetch('apply_coupon.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: 'coupon_code=' + encodeURIComponent(code)
                })
                    .then(res => res.json())
                    .then(data => {
                        message.textContent = data.message;

                        if (data.success) {
                            // Show or update discount row
                            const discountRow = document.querySelector('.coupon-discount');
                            discountRow.style.display = 'flex';

                            if (data.applied_coupon === 'FREESHIP') {
                                discountRow.innerHTML = `<span>Coupon (${data.applied_coupon}):</span><span>Free Shipping Applied</span>`;
                            } else {
                                discountRow.innerHTML = `<span>Coupon (${data.applied_coupon}):</span><span id="summary-discount">-$${data.discount}</span>`;
                            }

                            // Update summary values
                            document.getElementById('summary-subtotal').textContent = `$${data.subtotal}`;
                            document.getElementById('summary-shipping').textContent = `$${data.shipping}`;
                            document.getElementById('summary-tax').textContent = `$${data.tax}`;
                            document.getElementById('summary-grandtotal').textContent = `$${data.grand_total}`;
                        }
                    })
                    .catch(() => {
                        message.textContent = 'Error applying coupon. Please try again later.';
                    });
            });
        });
    </script>
</body>

</html>
