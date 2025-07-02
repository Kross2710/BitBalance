<?php
require_once __DIR__ . '/../../include/init.php';
require_once __DIR__ . '/../../include/fees.php';
require_once __DIR__ . '/../../include/handlers/log_attempt.php';

if (empty($_SESSION['user']['user_id']) || $_SESSION['user']['role'] !== 'admin') {
    http_response_code(403);
    exit('Forbidden');
}

$shipping = isset($_POST['shipping_fee']) ? (float) $_POST['shipping_fee'] : null;
$tax      = isset($_POST['tax_fee']) ? (float) $_POST['tax_fee'] : null;

if ($shipping === null || $tax === null) {
    header('Location: ../admin-products.php?error=Missing+values');
    exit;
}

set_site_fee($pdo, 'shipping', $shipping);
set_site_fee($pdo, 'tax', $tax);

log_attempt($pdo, $_SESSION['user']['user_id'], 'update_fees',
            "Set shipping={$shipping}, tax={$tax}", 'site_fees', null);

header('Location: ../admin-products.php?success=Fees+updated');
exit;