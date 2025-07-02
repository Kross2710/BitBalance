<?php
require_once __DIR__ . '/../include/init.php';
$cart_count = 0;
if ($isLoggedIn) {
    $cartIdStmt = $pdo->prepare("SELECT cart_id FROM productCart WHERE user_id = ?");
    $cartIdStmt->execute([$_SESSION['user']['user_id']]);
    $cartId = $cartIdStmt->fetchColumn();
    if ($cartId) {
        $qtyStmt = $pdo->prepare("SELECT COALESCE(SUM(quantity),0) FROM productCart_item WHERE cart_id = ?");
        $qtyStmt->execute([$cartId]);
        $cart_count = (int)$qtyStmt->fetchColumn();
    }
}

?>

<header>
    <a href="<?= BASE_URL ?>index.php" class="logo">BitBalance</a>
    <nav>
        <div class="menu">
            <a href="<?= BASE_URL ?>dashboard/dashboard.php" class="<?php echo ($activeHeader == 'dashboard') ? 'active' : ''; ?>">Dashboard</a>
            <a href="<?= BASE_URL ?>products.php" class="<?php echo ($activeHeader == 'products') ? 'active' : ''; ?>">Products</a>
            <a href="<?= BASE_URL ?>about.php" class="<?php echo ($activeHeader == 'about') ? 'active' : ''; ?>">About</a>
            <a href="<?= BASE_URL ?>forum.php" class="<?php echo ($activeHeader == 'forum') ? 'active' : ''; ?>">Forums</a>
            <a href="<?= BASE_URL ?>cart.php" class="cart-link">
                <i class="fas fa-shopping-cart"></i>
                <span id="cart-count">
                    <?php echo isset($_SESSION['cart']) ? count($_SESSION['cart']) : 0; ?>
                </span>
            </a>
            <?php if (isset($_SESSION['user'])): // Check if user is logged in ?>
                <!-- Simple clickable profile link -->
                <a href="<?= BASE_URL ?>profile.php" class="profile-link">
                    <?php if (!empty($_SESSION['user']['profile_image'])): ?>
                        <img src="<?= BASE_URL ?><?= htmlspecialchars($_SESSION['user']['profile_image']) ?>" 
                             alt="Profile" class="profile-pic">
                    <?php else: ?>
                        <div class="profile-icon"><i class="fas fa-user"></i></div>
                    <?php endif; ?>
                </a>
            <?php else: ?>
                <a href="<?php echo BASE_URL; ?>login.php" class="login-signup">Sign In / Sign Up</a>
            <?php endif; ?>
        </div>
        <!-- Hamberger menu to toggle nav links -->
        <a href="javascript:void(0);" class="hamburger" onclick="toggleMenu()">
            <i class="fa-solid fa-bars"></i>
        </a>
    </nav>

</header>

    <?php include PROJECT_ROOT . 'views/cookie-banner.php'; ?>


<?php if ($isLoggedIn): ?>
<script>
/* Ensure JS can update the badge after cart changes */
document.addEventListener('DOMContentLoaded', () => {
    const badge = document.getElementById('cart-count');
    if (badge) badge.textContent = <?php echo $cart_count; ?>;
});
</script>
<?php endif; ?>

<style>
    
    :root {
        --bg-color: #ffffff;
        --card-bg: #ffffff;
        --text-color: #212529;
        --text-muted: #6c757d;
        --border-color: #e9ecef;
        --primary-color: #4a7ee3;
        --shadow: 0 2px 8px rgba(0,0,0,0.1);
    }

    [data-theme="dark"] {
        --bg-color: #1a1a1a;
        --card-bg: #2d2d2d;
        --text-color: #ffffff;
        --text-muted: #adb5bd;
        --border-color: #495057;
        --primary-color: #4a7ee3;
        --shadow: 0 4px 16px rgba(0,0,0,0.4);
    }

    header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 15px 30px;
        background: var(--card-bg);
        border-bottom: 1px solid var(--border-color);
        box-shadow: var(--shadow);
        transition: all 0.3s ease;
    }
    
    .logo {
        text-decoration: none;
        color: var(--text-color); 
        font-weight: bold;
        font-size: 20px;
        transition: color 0.3s ease;
    }
    
    nav {
        display: flex;
        gap: 20px;
        align-items: center;
    }
    
    .menu a {
        text-decoration: none;
        color: var(--text-color); 
        transition: color 0.3s ease;
    }
    
    .menu a.active {
        color: var(--primary-color);
    }
    
    .menu a:hover {
        color: var(--primary-color);
        transition: color 0.3s ease;
    }
    
    .cart-link {
        color: var(--text-color); 
        text-decoration: none;
        display: flex;
        align-items: center;
        gap: 5px;
        padding: 8px 12px;
        border-radius: 6px;
        transition: all 0.3s ease;
    }
    
    .cart-link:hover {
        background-color: var(--primary-color);
        color: white;
    }
    
    #cart-count {
        background: #ff4757;
        color: white;
        border-radius: 50%;
        padding: 2px 6px;
        font-size: 12px;
        font-weight: bold;
        min-width: 18px;
        text-align: center;
    }
    
    .icon .fa-cart-shopping {
        font-size: 22px;
    }
    
    .login-signup {
        color: var(--text-color); 
        padding: 8px 16px;
        border-radius: 6px;
        transition: all 0.3s ease;
        text-decoration: none;
    }
    
    .menu .login-signup:hover {
        background-color: var(--primary-color);
        color: white;
        transition: background-color 0.3s ease;
    }
    
    .hamburger {
        color: var(--text-color); 
        cursor: pointer;
        transition: color 0.3s ease;
    }
    
    nav {
        position: relative;
        overflow: hidden;
    }
    
    nav .menu {
        overflow: hidden;
        transition: max-height 0.4s ease-out;
    }
    
    nav .menu.show {
        max-height: 500px;
    }
    
    nav .menu a {
        display: block;
        padding: 12px 16px;
        text-decoration: none;
        color: var(--text-color); 
        border-bottom: 1px solid var(--border-color); 
    }
    
    .profile-link {
        display: inline-block;
        padding: 2px 8px;
        margin-left: 15px;
        text-decoration: none;
        vertical-align: top;
        line-height: 1;
    }
    
    .profile-pic {
        width: 30px;
        height: 30px;
        border-radius: 50%;
        cursor: pointer;
        border: 2px solid var(--border-color); 
        vertical-align: top;
        display: inline-block;
        margin: 0;
        margin-top: -5px;
        transition: border-color 0.3s ease;
    }
    
    .profile-icon {
        width: 30px;
        height: 30px;
        border-radius: 50%;
        background: var(--card-bg); 
        display: inline-flex;
        align-items: center;
        justify-content: center;
        cursor: pointer;
        border: 2px solid var(--border-color); 
        color: var(--text-muted); 
        vertical-align: top;
        margin: 0;
        margin-top: -5px;
        transition: all 0.3s ease;
    }
    
    @media (max-width: 768px) {
        nav .menu {
            max-height: 0;
            background: var(--card-bg);
            border: 1px solid var(--border-color);
            border-radius: 8px;
            margin-top: 10px;
        }
    }
    
    @media (min-width: 768px) {
        nav a.hamburger {
            display: none;
        }
        nav .menu {
            display: flex;
        }
        nav .menu a {
            display: inline-block;
            border-bottom: none;
        }
    }
</style>

<script>
    function toggleMenu() {
        document.querySelector('.menu').classList.toggle('show');
    }
</script>