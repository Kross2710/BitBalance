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

// XP chip in header (always read-only — XP awarding happens in handlers)
$xpChip = null;
if ($isLoggedIn) {
    require_once __DIR__ . '/../include/handlers/xp.php';
    try {
        $xpChip = xp_get_summary($pdo, (int) $_SESSION['user']['user_id']);
    } catch (Throwable $e) {
        error_log('xp_get_summary header: ' . $e->getMessage());
        $xpChip = null;
    }
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
                    <?php if ($isLoggedIn): ?>
                        <a href="<?= BASE_URL ?>ai-coach.php"
                            class="nav-item <?php echo ($activeHeader == 'ai-coach') ? 'active' : ''; ?>">
                            <i class="fas fa-sparkles"></i> AI Coach
                        </a>
                    <?php endif; ?>
                    <!-- <a href="<?= BASE_URL ?>products.php"
                        class="nav-item <?php echo ($activeHeader == 'products') ? 'active' : ''; ?>">Products</a>
                    <a href="<?= BASE_URL ?>forum.php"
                        class="nav-item <?php echo ($activeHeader == 'forum') ? 'active' : ''; ?>">Forums</a> -->
                    <a href="<?= BASE_URL ?>about.php"
                        class="nav-item <?php echo ($activeHeader == 'about') ? 'active' : ''; ?>">About</a>
                </div>

                <div class="user-actions">
                    <!-- <a href="<?= BASE_URL ?>cart.php" class="cart-btn" title="View Cart">
                        <i class="fas fa-shopping-bag"></i>
                        <?php if ($cart_count > 0): ?>
                            <span id="cart-count" class="badge-pulse"><?= $cart_count ?></span>
                        <?php endif; ?>
                    </a> -->

                    <?php if (isset($_SESSION['user']) && $xpChip): ?>
                        <a href="<?= BASE_URL ?>dashboard/dashboard-progress.php"
                           id="headerXpChip"
                           class="xp-chip"
                           data-xp-numbers="<?= number_format($xpChip['xp_into_level']) ?>/<?= number_format($xpChip['xp_for_next']) ?>"
                           style="--xp-pct: <?= (int) $xpChip['progress_pct'] ?>;"
                           title="<?= number_format($xpChip['xp_into_level']) ?> / <?= number_format($xpChip['xp_for_next']) ?> XP toward Lv <?= (int) $xpChip['current_level'] + 1 ?>">
                            <span class="xp-chip__level-wrap">
                                <svg class="xp-chip__ring" viewBox="0 0 44 44" aria-hidden="true">
                                    <circle class="xp-chip__ring-track" cx="22" cy="22" r="19" pathLength="100"></circle>
                                    <circle class="xp-chip__ring-fill"  cx="22" cy="22" r="19" pathLength="100"></circle>
                                </svg>
                                <span class="xp-chip__level">Lv <?= (int) $xpChip['current_level'] ?></span>
                            </span>
                            <span class="xp-chip__bar">
                                <span class="xp-chip__fill" style="width: <?= (int) $xpChip['progress_pct'] ?>%;"></span>
                            </span>
                            <span class="xp-chip__numbers"><?= number_format($xpChip['xp_into_level']) ?>/<?= number_format($xpChip['xp_for_next']) ?></span>
                        </a>
                    <?php endif; ?>

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
    <?php include PROJECT_ROOT . 'views/level-up-toast.php'; ?>
<?php endif; ?>

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

    // Update XP chip in place (called by AJAX handlers after a successful award)
    window.updateXpChip = function (summary) {
        const chip = document.getElementById('headerXpChip');
        if (!chip || !summary) return;
        const levelEl = chip.querySelector('.xp-chip__level');
        const fillEl  = chip.querySelector('.xp-chip__fill');
        const numsEl  = chip.querySelector('.xp-chip__numbers');
        const into = (summary.xp_into_level || 0).toLocaleString();
        const ceil = (summary.xp_for_next   || 0).toLocaleString();
        if (levelEl) levelEl.textContent = 'Lv ' + summary.current_level;
        if (fillEl)  fillEl.style.width  = summary.progress_pct + '%';
        if (numsEl)  numsEl.textContent  = into + '/' + ceil;
        chip.style.setProperty('--xp-pct', summary.progress_pct);
        chip.setAttribute('data-xp-numbers', into + '/' + ceil);
        chip.title = into + ' / ' + ceil + ' XP toward Lv ' + (summary.current_level + 1);
        chip.classList.remove('xp-chip--bumped');
        void chip.offsetWidth;
        chip.classList.add('xp-chip--bumped');
    };

    /**
     * Spawn a floating "+N XP" popup. Anchors at (anchorEl|XP chip|center).
     * Auto-removes after the rise animation finishes.
     */
    window.showXpPopup = function (amount, anchorEl) {
        if (!amount || amount <= 0) return;
        const isMobile = window.matchMedia('(max-width: 720px)').matches;
        const anchor = anchorEl
            || (isMobile ? null : document.getElementById('headerXpChip'));

        let x, y;
        if (anchor) {
            const r = anchor.getBoundingClientRect();
            x = r.left + r.width / 2;
            y = r.top + r.height; // just below the anchor — rises through it
        } else {
            // Mobile fallback: dopamine center — upper-middle of viewport
            x = window.innerWidth / 2;
            y = window.innerHeight * 0.35;
        }

        const pop = document.createElement('div');
        pop.className = 'xp-popup';
        pop.innerHTML = '<i class="fas fa-bolt xp-popup__icon"></i>+' + amount + ' XP';
        pop.style.left = x + 'px';
        pop.style.top  = y + 'px';
        document.body.appendChild(pop);
        pop.addEventListener('animationend', () => pop.remove(), { once: true });
        // Safety net in case animationend doesn't fire
        setTimeout(() => pop.remove(), 2000);
    };
</script>
