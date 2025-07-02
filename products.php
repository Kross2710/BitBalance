<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/include/init.php';
require_once __DIR__ . '/include/handlers/log_attempt.php';
require_once __DIR__ . '/include/db_config.php';

if ($isLoggedIn) {
    // Log the user activity
    log_attempt($pdo, $user['user_id'], 'view', 'User ' . $user['user_id'] . ' viewed products', 'products', null);
}

$activeHeader = 'products';
?>
<!DOCTYPE html>
<html lang="en"
    data-theme="<?php echo isset($_SESSION['user']) ? ($_SESSION['user']['theme_preference'] ?? 'light') : 'light'; ?>">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>BitBalance</title>
    <link rel="stylesheet" href="css/products.css">
    <link rel="stylesheet" href="css/style.css">
    <link rel="stylesheet" href="css/themes/global.css">
    <link rel="stylesheet" href="css/themes/header.css">
    <link rel="stylesheet" href="css/themes/products.css">
    <script src="https://kit.fontawesome.com/b94f65ead2.js" crossorigin="anonymous"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            document.querySelectorAll('.add-cart-form').forEach(form => {
                form.addEventListener('submit', function (e) {
                    e.preventDefault();
                    const formData = new FormData(form);
                    fetch('add_to_cart.php', {
                        method: 'POST',
                        body: formData
                    })
                        .then(res => res.json())
                        .then(data => {
                            if (data.success) {
                                const cartCount = document.getElementById('cart-count');
                                if (cartCount) {
                                    cartCount.textContent = data.cart_count;
                                }

                                const animation = document.getElementById('cart-animation');
                                if (animation) {
                                    animation.classList.add('show');
                                    setTimeout(() => {
                                        animation.classList.remove('show');
                                    }, 2000);
                                }
                            } else {
                                alert('Failed to add to cart, please log in to continue.');
                            }
                        });
                });
            });
        });
    </script>
    <style>[data-theme="dark"] .product {
    background-color: #2d2d2d !important;
    border: 1px solid #404040 !important;
    color: #ffffff !important;
}

[data-theme="dark"] .product h2 {
    color: #ffffff !important;
}

[data-theme="dark"] .product .price {
    color: #4CAF50 !important; 
}

[data-theme="dark"] .product img {
    background-color: #3d3d3d;
    border-radius: 8px;
}

[data-theme="dark"] .add-to-cart {
    background-color: #4CAF50 !important;
    color: white !important;
    border: none !important;
}

[data-theme="dark"] .add-to-cart:hover {
    background-color: #45a049 !important;
}

[data-theme="dark"] .title {
    color: #ffffff !important;
}

[data-theme="dark"] .product {
    background-color: #1e1e1e !important;
    border: 1px solid #333333 !important;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.3) !important;
}
        
    </style>
</head>

<body>
    <div id="cart-animation" class="cart-added-animation">✔ Added to cart!</div>
    <?php include PROJECT_ROOT . 'views/header.php'; ?>
    <main>
        <h1 class="title">Shop Our Products</h1>
        <div class="products">
            <!-- read products from database and read image source based on the product name -->
            <?php
            $stmt = $pdo->query("SELECT * FROM product");
            while ($row = $stmt->fetch()) {
                echo '<div class="product">';
                echo '<form class="add-cart-form">';
                echo '<img src="images/' . htmlspecialchars($row['product_name']) . '.png" alt="' . htmlspecialchars($row['product_name']) . '">';
                echo '<h2>' . htmlspecialchars($row['product_name']) . '</h2>';
                echo '<p class="price">$' . htmlspecialchars($row['product_price']) . '</p>';
                echo '<input type="hidden" name="product_id" value="' . htmlspecialchars($row['product_id']) . '">';
                echo '<input type="hidden" name="product_name" value="' . htmlspecialchars($row['product_name']) . '">';
                echo '<input type="hidden" name="product_price" value="' . htmlspecialchars($row['product_price']) . '">';
                echo '<button type="submit" class="add-to-cart">Add to Cart</button>';
                echo '</form>';
                echo '</div>';
            }
            ?>
        </div>
    </main>
    <?php include PROJECT_ROOT . 'views/footer.php'; ?>
</body>

</html>
