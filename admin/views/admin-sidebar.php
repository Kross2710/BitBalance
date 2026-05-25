<div class="sidebar admin-sidebar">
    <a href="admin.php" class="nav-link <?php echo ($activePage == 'dashboard') ? 'active' : ''; ?>">
        <i class="fa-solid fa-chart-line"></i> Dashboard
    </a>
    <a href="admin-users.php" class="nav-link <?php echo ($activePage == 'users') ? 'active' : ''; ?>">
        <i class="fa-solid fa-users"></i> Users
    </a>
    <a href="admin-products.php" class="nav-link <?php echo ($activePage == 'products') ? 'active' : ''; ?>">
        <i class="fa-solid fa-box"></i> Products
    </a>
    <a href="admin-orders.php" class="nav-link <?php echo ($activePage == 'orders') ? 'active' : ''; ?>">
        <i class="fa-solid fa-cart-shopping"></i> Orders
    </a>
    <a href="admin-forums.php" class="nav-link <?php echo ($activePage == 'forums') ? 'active' : ''; ?>">
        <i class="fa-solid fa-comments"></i> Forums
    </a>
    <a href="admin-logs.php" class="nav-link <?php echo ($activePage == 'logs') ? 'active' : ''; ?>">
        <i class="fa-solid fa-clipboard-list"></i> Logs
    </a>
</div>
