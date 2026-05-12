<?php
/**
 * Standard CSS loader — include in <head> of every page after init.
 *
 * Set BEFORE include (all optional):
 *   $pageComponents = ['sidebar', 'fab'];  Extra components to load on top
 *                                          of the always-loaded foundation.
 *                                          Valid: 'sidebar', 'fab'.
 *
 *   $pageCss = [                           Page-specific stylesheets loaded
 *       'css/dashboard.css',               LAST (highest cascade priority).
 *       'css/pages/dashboard-intake.css',  Paths relative to BASE_URL.
 *   ];
 *
 * Example:
 *   $pageComponents = ['sidebar', 'fab'];
 *   $pageCss = ['css/dashboard.css', 'css/pages/dashboard-intake.css'];
 *   include PROJECT_ROOT . 'views/head_css.php';
 */

$_components = isset($pageComponents) ? (array) $pageComponents : [];
$_extra = isset($pageCss) ? (array) $pageCss : [];
?>
<link rel="stylesheet" href="<?= BASE_URL ?>css/tokens.css">
<link rel="stylesheet" href="<?= BASE_URL ?>css/base.css">
<link rel="stylesheet" href="<?= BASE_URL ?>css/components/forms.css">
<link rel="stylesheet" href="<?= BASE_URL ?>css/components/header.css">
<link rel="stylesheet" href="<?= BASE_URL ?>css/components/footer.css">
<link rel="stylesheet" href="<?= BASE_URL ?>css/components/cookie-banner.css">
<?php if (in_array('sidebar', $_components, true)): ?>
<link rel="stylesheet" href="<?= BASE_URL ?>css/components/sidebar.css">
<?php endif; ?>
<?php if (in_array('fab', $_components, true)): ?>
<link rel="stylesheet" href="<?= BASE_URL ?>css/components/fab.css">
<?php endif; ?>
<?php foreach ($_extra as $href): ?>
<link rel="stylesheet" href="<?= BASE_URL . htmlspecialchars($href, ENT_QUOTES) ?>">
<?php endforeach; ?>
