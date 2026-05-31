<header class="main-header">
    <div class="header-container" style="max-width: 100%; padding: 15px 40px; display: flex; justify-content: space-between; align-items: center;">
        <a href="admin.php" class="logo" style="text-decoration: none; font-size: 1.4rem; font-weight: 800; color: var(--color-text); display: flex; align-items: center; gap: 8px; letter-spacing: -0.5px;">
            <i class="fas fa-chart-pie logo-icon" style="background: var(--gradient-primary); -webkit-background-clip: text; -webkit-text-fill-color: transparent; font-size: 1.6rem;"></i> 
            BitBalance <span style="color: var(--color-primary); font-weight: 700; font-size: 0.95rem; margin-left: 2px;">Admin</span>
        </a>

        <nav style="display: flex; gap: 20px; align-items: center;">
            <div class="menu" id="navMenu" style="display: flex; align-items: center; gap: 30px;">
                <div class="nav-links" style="display: flex; gap: 20px;">
                    <a href="../dashboard/dashboard.php" class="nav-item" style="text-decoration: none; color: var(--color-text-secondary); font-weight: 600; font-size: 0.95rem; transition: color var(--transition-fast);">
                        <i class="fa-solid fa-arrow-left"></i> User Dashboard
                    </a>
                </div>
                <div class="user-actions" style="border-left: 2px solid var(--color-border-subtle); padding-left: 20px; display: flex; align-items: center; gap: 14px;">
                    <?php if (isset($_SESSION['user'])): ?>
                        <span class="user-greeting" style="color: var(--color-text-secondary); font-size: 0.9rem; font-weight: 700;">Hi, <?= htmlspecialchars($_SESSION['user']['first_name']) ?></span>
                        <a href="admin-logout.php" class="btn-primary" style="padding: 10px 18px; border-radius: var(--radius-md, 14px); font-size: 0.85rem; text-decoration: none; font-weight: 700; background-color: var(--color-primary); color: white; box-shadow: 0 4px 0 var(--color-primary-hover); transition: all 0.1s ease; cursor: pointer; display: inline-flex; align-items: center; gap: 6px;">
                            <i class="fa-solid fa-right-from-bracket"></i> Logout
                        </a>
                    <?php else: ?>
                        <a href="admin-login.php" class="btn-primary" style="padding: 10px 18px; border-radius: var(--radius-md, 14px); font-size: 0.85rem; text-decoration: none; font-weight: 700; background-color: var(--color-primary); color: white; box-shadow: 0 4px 0 var(--color-primary-hover); transition: all 0.1s ease; cursor: pointer; display: inline-flex; align-items: center; gap: 6px;">
                            Sign In
                        </a>
                    <?php endif; ?>
                </div>
            </div>
            
            <button class="hamburger" onclick="toggleMenu()" aria-label="Toggle Navigation" style="display: none; background: none; border: none; font-size: 1.5rem; color: var(--color-text); cursor: pointer;">
                <i class="fa-solid fa-bars"></i>
            </button>
        </nav>
    </div>
</header>
<script>
    function toggleMenu() {
        document.getElementById('navMenu').classList.toggle('show');
    }
</script>