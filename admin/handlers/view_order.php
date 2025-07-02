<?php
require_once __DIR__ . '/../../include/db_config.php';  // $pdo
header('Content-Type: application/json; charset=utf-8');

$order_id = (int)($_GET['order_id'] ?? 0);
if (!$order_id) { http_response_code(400); exit(json_encode(['error'=>'missing id'])); }

$stmt = $pdo->prepare("
    SELECT product_name, price_each, quantity, line_total
    FROM   order_item
    WHERE  order_id = ?
");
$stmt->execute([$order_id]);
echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));