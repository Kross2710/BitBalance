<?php
// dashboard/handlers/lookup_barcode.php
// Looks up a barcode: checks local cache first, falls back to OpenFoodFacts.
// Always writes a row to barcode_scan_log so we can measure real coverage.

require_once __DIR__ . '/../../include/init.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user']) || $_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['ok' => false, 'error' => 'Unauthorized']);
    exit();
}

$userId  = (int) $_SESSION['user']['user_id'];
$barcode = trim($_POST['barcode'] ?? '');

// Sanitize: only digits, 6-20 chars (EAN-8/13, UPC-A/E)
if (!preg_match('/^\d{6,20}$/', $barcode)) {
    echo json_encode(['ok' => false, 'error' => 'Invalid barcode format']);
    exit();
}

$tStart = microtime(true);

function log_scan(PDO $pdo, int $userId, string $barcode, string $result, int $ms): void {
    try {
        $stmt = $pdo->prepare(
            "INSERT INTO barcode_scan_log (user_id, barcode, result, latency_ms) VALUES (?, ?, ?, ?)"
        );
        $stmt->execute([$userId, $barcode, $result, $ms]);
    } catch (PDOException $e) {
        // Don't fail the request if logging fails
        error_log('barcode_scan_log insert failed: ' . $e->getMessage());
    }
}

function product_payload(array $row): array {
    return [
        'ok'                => true,
        'found'             => true,
        'barcode'           => $row['barcode'],
        'product_name'      => $row['product_name'],
        'brand'             => $row['brand'],
        'serving_size'      => $row['serving_size'],
        'kcal_per_serving'  => $row['kcal_per_serving'] !== null ? (int) $row['kcal_per_serving'] : null,
        'kcal_per_100g'     => $row['kcal_per_100g'] !== null ? (float) $row['kcal_per_100g'] : null,
        'protein'           => $row['protein_per_serving'] !== null ? (float) $row['protein_per_serving'] : null,
        'carbs'             => $row['carbs_per_serving']   !== null ? (float) $row['carbs_per_serving']   : null,
        'fat'               => $row['fat_per_serving']     !== null ? (float) $row['fat_per_serving']     : null,
        'sugar'             => $row['sugar_per_serving']   !== null ? (float) $row['sugar_per_serving']   : null,
        'image_url'         => $row['image_url'],
        'source'            => $row['source'],
    ];
}

// ============ 1. Cache lookup ============
try {
    $stmt = $pdo->prepare("SELECT * FROM barcode_products WHERE barcode = ? LIMIT 1");
    $stmt->execute([$barcode]);
    $cached = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    echo json_encode(['ok' => false, 'error' => 'DB error']);
    exit();
}

if ($cached) {
    // Bump lookup_count (fire and forget)
    try {
        $pdo->prepare("UPDATE barcode_products SET lookup_count = lookup_count + 1 WHERE barcode = ?")
            ->execute([$barcode]);
    } catch (PDOException $e) { /* ignore */ }

    $ms = (int) round((microtime(true) - $tStart) * 1000);
    log_scan($pdo, $userId, $barcode, 'cache_hit', $ms);

    $payload = product_payload($cached);
    $payload['cache_hit'] = true;
    $payload['latency_ms'] = $ms;
    echo json_encode($payload);
    exit();
}

// ============ 2. Fallback: OpenFoodFacts ============
$apiUrl = "https://world.openfoodfacts.org/api/v2/product/" . rawurlencode($barcode) . ".json";

$ch = curl_init($apiUrl);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 6);
curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 3);
curl_setopt($ch, CURLOPT_USERAGENT, 'BitBalance/1.0 (calorie tracker)');
// RMIT shared host CA bundle is outdated
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);

$response = curl_exec($ch);
$curlErr  = curl_error($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($response === false || $httpCode !== 200) {
    $ms = (int) round((microtime(true) - $tStart) * 1000);
    log_scan($pdo, $userId, $barcode, 'api_error', $ms);
    echo json_encode([
        'ok'    => false,
        'error' => 'OpenFoodFacts unreachable: ' . ($curlErr ?: "HTTP $httpCode"),
    ]);
    exit();
}

$data = json_decode($response, true);
if (!is_array($data) || ($data['status'] ?? 0) !== 1 || empty($data['product'])) {
    // Not found
    $ms = (int) round((microtime(true) - $tStart) * 1000);
    log_scan($pdo, $userId, $barcode, 'api_miss', $ms);
    echo json_encode([
        'ok'         => true,
        'found'      => false,
        'barcode'    => $barcode,
        'latency_ms' => $ms,
    ]);
    exit();
}

// ============ 3. Parse + save to cache ============
$p = $data['product'];
$n = $p['nutriments'] ?? [];

$name        = $p['product_name']       ?? null;
$brand       = $p['brands']             ?? null;
$servingSize = $p['serving_size']       ?? null;
$imageUrl    = $p['image_url']          ?? ($p['image_front_url'] ?? null);

$kcalServing = isset($n['energy-kcal_serving']) ? (int)   round($n['energy-kcal_serving']) : null;
$kcal100     = isset($n['energy-kcal_100g'])    ? (float) $n['energy-kcal_100g']           : null;
$proteinS    = isset($n['proteins_serving'])      ? (float) $n['proteins_serving']         : null;
$carbS       = isset($n['carbohydrates_serving']) ? (float) $n['carbohydrates_serving']    : null;
$fatS        = isset($n['fat_serving'])           ? (float) $n['fat_serving']              : null;
$sugarS      = isset($n['sugars_serving'])        ? (float) $n['sugars_serving']           : null;

// If per-serving missing but per-100g present and serving_size has a gram/ml number, derive it
if ($kcalServing === null && $kcal100 !== null && $servingSize) {
    if (preg_match('/(\d+(?:\.\d+)?)\s*(g|ml)/i', $servingSize, $m)) {
        $kcalServing = (int) round($kcal100 * ((float) $m[1]) / 100);
    }
}

try {
    $ins = $pdo->prepare(
        "INSERT INTO barcode_products
         (barcode, product_name, brand, serving_size,
          kcal_per_serving, kcal_per_100g,
          protein_per_serving, carbs_per_serving, fat_per_serving, sugar_per_serving,
          image_url, source)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'openfoodfacts')"
    );
    $ins->execute([
        $barcode, $name, $brand, $servingSize,
        $kcalServing, $kcal100,
        $proteinS, $carbS, $fatS, $sugarS,
        $imageUrl,
    ]);
} catch (PDOException $e) {
    // Cache write failed — still serve the user
    error_log('barcode_products insert failed: ' . $e->getMessage());
}

$ms = (int) round((microtime(true) - $tStart) * 1000);
log_scan($pdo, $userId, $barcode, 'api_found', $ms);

echo json_encode([
    'ok'               => true,
    'found'            => true,
    'barcode'          => $barcode,
    'product_name'     => $name,
    'brand'            => $brand,
    'serving_size'     => $servingSize,
    'kcal_per_serving' => $kcalServing,
    'kcal_per_100g'    => $kcal100,
    'protein'          => $proteinS,
    'carbs'            => $carbS,
    'fat'              => $fatS,
    'sugar'            => $sugarS,
    'image_url'        => $imageUrl,
    'source'           => 'openfoodfacts',
    'cache_hit'        => false,
    'latency_ms'       => $ms,
]);
