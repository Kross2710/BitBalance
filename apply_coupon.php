<?php
session_start();
require_once __DIR__ . '/include/db_config.php';
require_once __DIR__ . '/include/fees.php';

header('Content-Type: application/json');

$valid_coupons = [
    'SAVE10' => 10.00,
    'HALFPRICE' => 0.5,
    'FREESHIP' => 'free_shipping'
];

$cart = isset($_SESSION['cart']) ? $_SESSION['cart'] : [];

if (empty($cart)) {
    echo json_encode([
        'success' => false,
        'message' => 'Your cart is empty.',
    ]);
    exit;
}

// Calculate subtotal and items count
$subtotal = 0;
$total_items = 0;
foreach ($cart as $item) {
    $subtotal += $item['price'] * $item['quantity'];
    $total_items += $item['quantity'];
}

$shipping = get_site_fee($pdo, 'shipping');
$tax = get_site_fee($pdo, 'tax');
$discount = 0;
$applied_coupon = '';

if (!isset($_POST['coupon_code'])) {
    echo json_encode([
        'success' => false,
        'message' => 'No coupon code provided.',
    ]);
    exit;
}

$code = strtoupper(trim($_POST['coupon_code']));

if (array_key_exists($code, $valid_coupons)) {
    $_SESSION['applied_coupon'] = $code;
    $applied_coupon = $code;

    $value = $valid_coupons[$applied_coupon];
    if (is_numeric($value)) {
        $discount = ($value < 1) ? $subtotal * $value : $value;
    } elseif ($value === 'free_shipping') {
        $shipping = 0;
    }

    $grand_total = $subtotal - $discount + $shipping + $tax;

    echo json_encode([
        'success' => true,
        'message' => "Coupon '{$applied_coupon}' applied successfully!",
        'discount' => number_format($discount, 2),
        'shipping' => number_format($shipping, 2),
        'tax' => number_format($tax, 2),
        'subtotal' => number_format($subtotal, 2),
        'grand_total' => number_format($grand_total, 2),
        'applied_coupon' => $applied_coupon,
    ]);
} else {
    $_SESSION['applied_coupon'] = '';
    echo json_encode([
        'success' => false,
        'message' => 'Invalid coupon code.',
    ]);
}
