<?php
require_once __DIR__ . '/../include/csrf.php';
$__locales = available_locales();
$__currentLocale = current_locale();
$__redirectBack = $_SERVER['REQUEST_URI'] ?? '';
?>
<footer class="dashboard-footer">
    <div class="footer-content">
        <p><?= t('footer.copyright', ['year' => date('Y')]) ?></p>
        <div class="footer-links">
            <a href="<?= BASE_URL ?>terms.php" target="_blank"><?= t('footer.terms') ?></a>
            <i class="fas fa-external-link-alt link-icon footer-link-external"></i>
            <span class="footer-link-separator">|</span>
            <a href="javascript:void(0)" id="footer-cookie-settings" class="footer-cookie-link">
                <i class="fas fa-cookie-bite footer-cookie-icon"></i><?= t('footer.cookie_settings') ?>
            </a>
        </div>

        <form method="post" action="<?= BASE_URL ?>include/handlers/set_language.php" class="footer-lang-switcher" aria-label="<?= t('footer.language') ?>">
            <?= csrf_field() ?>
            <input type="hidden" name="redirect" value="<?= htmlspecialchars($__redirectBack, ENT_QUOTES) ?>">
            <i class="fas fa-globe footer-lang-icon" aria-hidden="true"></i>
            <span class="footer-lang-label"><?= t('footer.language') ?>:</span>
            <?php foreach ($__locales as $__code => $__meta): ?>
                <button type="submit"
                        name="lang"
                        value="<?= htmlspecialchars($__code, ENT_QUOTES) ?>"
                        class="footer-lang-btn<?= $__code === $__currentLocale ? ' is-active' : '' ?>"
                        aria-pressed="<?= $__code === $__currentLocale ? 'true' : 'false' ?>"
                        title="<?= htmlspecialchars($__meta['english'], ENT_QUOTES) ?>">
                    <?= htmlspecialchars($__meta['native']) ?>
                </button>
            <?php endforeach; ?>
        </form>
    </div>
</footer>
