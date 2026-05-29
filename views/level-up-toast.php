<?php
/**
 * Level-up celebration toast.
 *
 * Auto-fires on page load if $_SESSION['xp_levelup_flash'] is set (consumed
 * by xp_consume_levelup_flash from include/handlers/xp.php).
 *
 * Manually trigger from JS:
 *   window.showLevelUpToast({ from: 5, to: 6, xp_added: 50 });
 */
require_once __DIR__ . '/../include/handlers/xp.php';
$levelUpFlash = xp_consume_levelup_flash();
?>
<div id="levelUpToast" class="levelup-toast" role="status" aria-live="polite">
    <div class="levelup-confetti" aria-hidden="true">
        <?php for ($i = 0; $i < 18; $i++): ?>
            <span style="--i: <?= $i ?>;"></span>
        <?php endfor; ?>
    </div>
    <div class="levelup-content">
        <div class="levelup-badge">
            <span class="levelup-badge__icon"><i class="fas fa-star"></i></span>
            <span class="levelup-badge__level" id="levelUpNewLevel">Lv 2</span>
        </div>
        <div class="levelup-text">
            <div class="levelup-title">Level Up!</div>
            <div class="levelup-sub" id="levelUpSub">You reached level 2</div>
        </div>
    </div>
</div>

<style>
    .levelup-toast {
        position: fixed;
        left: 50%;
        bottom: 32px;
        transform: translate(-50%, 140%);
        opacity: 0;
        pointer-events: none;
        display: flex;
        align-items: center;
        gap: 18px;
        padding: 18px 28px 18px 18px;
        background: var(--color-surface);
        border: 3px solid var(--color-primary);
        border-radius: var(--radius-lg);
        box-shadow: 0 12px 0 var(--color-primary-hover), var(--shadow-md);
        z-index: var(--z-toast, 9999);
        transition: transform 0.45s cubic-bezier(0.34, 1.56, 0.64, 1), opacity 0.3s;
        min-width: 280px;
        max-width: 92vw;
        overflow: visible;
    }

    .levelup-toast.show {
        transform: translate(-50%, 0);
        opacity: 1;
        pointer-events: auto;
    }

    .levelup-content {
        display: flex;
        align-items: center;
        gap: 16px;
    }

    .levelup-badge {
        position: relative;
        display: flex;
        align-items: center;
        justify-content: center;
        width: 64px;
        height: 64px;
        background: var(--color-primary);
        border-radius: 50%;
        box-shadow: inset 0 -5px 0 var(--color-primary-hover);
        color: #ffffff;
    }

    .levelup-badge__icon {
        font-size: 28px;
        animation: levelup-spin 1.2s ease-out;
    }

    .levelup-badge__level {
        position: absolute;
        bottom: -8px;
        left: 50%;
        transform: translateX(-50%);
        background: var(--color-accent, #FF9600);
        color: #ffffff;
        font-size: 12px;
        font-weight: 800;
        padding: 2px 10px;
        border-radius: 999px;
        border: 2px solid var(--color-surface);
        white-space: nowrap;
    }

    .levelup-title {
        font-size: 22px;
        font-weight: 800;
        color: var(--color-text);
        line-height: 1.1;
    }

    .levelup-sub {
        font-size: 13px;
        color: var(--color-text-secondary);
        margin-top: 4px;
    }

    /* --- Confetti --- */
    .levelup-confetti {
        position: absolute;
        inset: 0;
        pointer-events: none;
        overflow: visible;
    }

    .levelup-confetti span {
        position: absolute;
        top: 50%;
        left: 50%;
        width: 8px;
        height: 12px;
        background: var(--color-primary);
        opacity: 0;
        border-radius: 2px;
    }

    /* Six bright tokens cycling through 18 spans */
    .levelup-confetti span:nth-child(6n + 1) { background: var(--color-primary); }
    .levelup-confetti span:nth-child(6n + 2) { background: var(--color-secondary); }
    .levelup-confetti span:nth-child(6n + 3) { background: var(--color-accent, #FF9600); }
    .levelup-confetti span:nth-child(6n + 4) { background: #f472b6; }
    .levelup-confetti span:nth-child(6n + 5) { background: #facc15; }
    .levelup-confetti span:nth-child(6n + 6) { background: #a78bfa; }

    .levelup-toast.show .levelup-confetti span {
        animation: levelup-confetti 1.1s cubic-bezier(0.2, 0.7, 0.3, 1) forwards;
        animation-delay: calc(var(--i) * 18ms);
    }

    @keyframes levelup-confetti {
        0%   { transform: translate(-50%, -50%) rotate(0deg); opacity: 1; }
        100% {
            transform:
                translate(calc(-50% + (cos(var(--i) * 20deg) * 140px)),
                          calc(-50% + (sin(var(--i) * 20deg) * 120px) - 40px))
                rotate(720deg);
            opacity: 0;
        }
    }

    @keyframes levelup-spin {
        0%   { transform: rotate(-360deg) scale(0.4); }
        100% { transform: rotate(0deg)    scale(1);   }
    }
</style>

<script>
    window.showLevelUpToast = function (info) {
        const toast = document.getElementById('levelUpToast');
        if (!toast) return;
        const levelEl = document.getElementById('levelUpNewLevel');
        const subEl   = document.getElementById('levelUpSub');
        const newLv = (info && info.to) || 0;
        if (levelEl) levelEl.textContent = 'Lv ' + newLv;
        if (subEl)   subEl.textContent   = 'You reached level ' + newLv
                                         + (info && info.xp_added ? ' (+' + info.xp_added + ' XP)' : '');

        toast.classList.remove('show');
        // Reflow so confetti animation restarts on repeat calls
        void toast.offsetWidth;
        toast.classList.add('show');

        clearTimeout(toast._hideTimer);
        toast._hideTimer = setTimeout(() => toast.classList.remove('show'), 4200);
    };

    <?php if ($levelUpFlash): ?>
    document.addEventListener('DOMContentLoaded', () => {
        showLevelUpToast(<?= json_encode($levelUpFlash, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>);
    });
    <?php endif; ?>
</script>
