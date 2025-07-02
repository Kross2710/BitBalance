<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/../../include/handlers/log_attempt.php';
$admin_id = $_SESSION['user']['user_id'] ?? null;

// Process form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $product_id = $_POST['product_id'] ?? null;
    $productname = trim($_POST['productname'] ?? '');
    $product_price = trim($_POST['product_price'] ?? '');

    // Validate inputs
    if (empty($productname) || empty($product_price)) {
        $error_message = "Please fill in all fields.";
    } else {
        try {
            // Update core product fields
            $stmt = $pdo->prepare("
                UPDATE product
                SET product_name = ?, product_price = ?
                WHERE product_id = ?
            ");

            $stmt->execute([$productname, $product_price, $product_id]);

            // Log the update attempt
            log_attempt($pdo, $admin_id, 'edit_prouduct', 'Admin ' . $admin_id . ' updated product ' . $product_id, 'product', $product_id);

            // Redirect to the user list page with a success message
            $success_message = "Product updated successfully";
            header("Location: ../admin/admin-products.php?success=" . urlencode($success_message));
            exit;

        } catch (PDOException $e) {
            $error_message = "Error updating product: " . $product_id . " " . $e->getMessage();
            header("Location: ../admin/admin-products.php?error=" . urlencode($error_message));
        }
    }
}
?>