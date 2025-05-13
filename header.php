    <header>
        <a href="index.php" class="logo">BitBalance</a>
        <nav>
            <a href="index.php">Dasboard</a>
            <a href="products.php">Products</a>
            <a href="#">About</a>
            <a href="#" class="forums">Forums</a>

            <?php if (isset($_SESSION['user'])): // Check if user is logged in ?>
            <a href="#" class="login-signup">Hi, <?= htmlspecialchars($_SESSION['user']['firstname']) ?></a>
            <a href="logout.php" class="login-signup">Logout</a>
            <?php else: ?>
            <a href="login.php" class="login-signup">Sign In / Sign Up</a>
            <?php endif; ?>

            <a href="#" class="icon shopping-cart">
                <i class="fa-regular fa-cart-shopping"></i> Basket 0
            </a>
        </nav>
    </header>