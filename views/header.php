<?php
require_once __DIR__ . '/../include/init.php';

// Xử lý đếm giỏ hàng
$cart_count = 0;
if ($isLoggedIn) {
    $cartIdStmt = $pdo->prepare("SELECT cart_id FROM productCart WHERE user_id = ?");
    $cartIdStmt->execute([$_SESSION['user']['user_id']]);
    $cartId = $cartIdStmt->fetchColumn();
    if ($cartId) {
        $qtyStmt = $pdo->prepare("SELECT COALESCE(SUM(quantity),0) FROM productCart_item WHERE cart_id = ?");
        $qtyStmt->execute([$cartId]);
        $cart_count = (int) $qtyStmt->fetchColumn();
    }
} else {
    // Nếu chưa login thì đếm từ session
    $cart_count = isset($_SESSION['cart']) ? count($_SESSION['cart']) : 0;
}
?>

<header class="main-header">
    <div class="header-container">
        <a href="<?= BASE_URL ?>index.php" class="logo">
            <i class="fas fa-chart-pie logo-icon"></i> BitBalance
        </a>

        <nav>
            <div class="menu" id="navMenu">
                <div class="nav-links">
                    <a href="<?= BASE_URL ?>dashboard/dashboard.php"
                        class="nav-item <?php echo ($activeHeader == 'dashboard') ? 'active' : ''; ?>">Dashboard</a>
                    <a href="<?= BASE_URL ?>products.php"
                        class="nav-item <?php echo ($activeHeader == 'products') ? 'active' : ''; ?>">Products</a>
                    <a href="<?= BASE_URL ?>about.php"
                        class="nav-item <?php echo ($activeHeader == 'about') ? 'active' : ''; ?>">About</a>
                    <a href="<?= BASE_URL ?>forum.php"
                        class="nav-item <?php echo ($activeHeader == 'forum') ? 'active' : ''; ?>">Forums</a>
                </div>

                <div class="user-actions">
                    <!-- <a href="<?= BASE_URL ?>cart.php" class="cart-btn" title="View Cart">
                        <i class="fas fa-shopping-bag"></i>
                        <?php if ($cart_count > 0): ?>
                            <span id="cart-count" class="badge-pulse"><?= $cart_count ?></span>
                        <?php endif; ?>
                    </a> -->

                    <?php if (isset($_SESSION['user'])): ?>
                        <a href="<?= BASE_URL ?>profile.php" class="profile-btn" title="My Profile">
                            <?php if (!empty($_SESSION['user']['profile_image'])): ?>
                                <img src="<?= BASE_URL ?><?= htmlspecialchars($_SESSION['user']['profile_image']) ?>"
                                    alt="Avatar">
                            <?php else: ?>
                                <div class="profile-placeholder"><i class="fas fa-user"></i></div>
                            <?php endif; ?>
                        </a>
                    <?php else: ?>
                        <a href="<?php echo BASE_URL; ?>login.php" class="btn-login">Sign In</a>
                    <?php endif; ?>
                </div>
            </div>

            <button class="hamburger" onclick="toggleMenu()" aria-label="Toggle menu">
                <i class="fa-solid fa-bars"></i>
            </button>
        </nav>
    </div>
</header>

<?php include PROJECT_ROOT . 'views/cookie-banner.php'; ?>

<?php if ($isLoggedIn): ?>
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const badge = document.getElementById('cart-count');
            if (badge) badge.textContent = <?php echo $cart_count; ?>;
        });
    </script>
<?php endif; ?>

<script>
    function toggleMenu() {
        document.getElementById('navMenu').classList.toggle('show');
    }
</script>