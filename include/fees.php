<?php
//fees.php
function get_site_fee(PDO $pdo, string $name): float {
    $stmt = $pdo->prepare("SELECT value FROM site_fees WHERE name = ?");
    $stmt->execute([$name]);
    return (float) $stmt->fetchColumn();
}

function set_site_fee(PDO $pdo, string $name, float $value): void {
    $stmt = $pdo->prepare("
        INSERT INTO site_fees (name, value, updated_at)
        VALUES (?, ?, NOW())
        ON DUPLICATE KEY UPDATE value = VALUES(value), updated_at = NOW()
    ");
    $stmt->execute([$name, $value]);
}
