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
 *   ];                                     Pass clean paths — NO ?v=time()
 *                                          here; cache-busting is applied
 *                                          automatically below.
 *
 * Separately, each page should also set:
 *   $bodyClass = 'page-dashboard';         Used as the body element's class
 *                                          so CSS can scope per page:
 *                                          `body.page-dashboard .foo { ... }`.
 *                                          Convention: `page-<slug>`.
 *   <body class="<?= htmlspecialchars($bodyClass ?? '', ENT_QUOTES) ?>">
 *
 * Example:
 *   $pageComponents = ['sidebar', 'fab'];
 *   $pageCss = ['css/dashboard.css', 'css/pages/dashboard-intake.css'];
 *   $bodyClass = 'page-dashboard';
 *   include PROJECT_ROOT . 'views/head_css.php';
 */

$_components = isset($pageComponents) ? (array) $pageComponents : [];
$_extra = isset($pageCss) ? (array) $pageCss : [];

/**
 * Emit a <link rel="stylesheet"> with filemtime() cache-busting.
 * Why filemtime() instead of time():
 *   - time() changes every second → browser/CDN cache useless,
 *     every request re-downloads the file (~60KB+ for dashboard.css).
 *   - filemtime() changes only when the file actually changes → cache
 *     stays warm across requests but invalidates correctly on deploy.
 *
 * Accepts paths that already contain ?v=... (legacy callers) and strips
 * that suffix before re-stamping, so it's safe to migrate gradually.
 */
function bb_css_link(string $relPath): void {
    // Strip any pre-existing query string (legacy ?v=time())
    $clean = strtok($relPath, '?');
    $fsPath = PROJECT_ROOT . $clean;
    $ver = is_file($fsPath) ? filemtime($fsPath) : '0';
    echo '<link rel="stylesheet" href="'
        . BASE_URL . htmlspecialchars($clean, ENT_QUOTES)
        . '?v=' . $ver . '">' . "\n";
}
?>
<?php bb_css_link('css/tokens.css'); ?>
<?php bb_css_link('css/base.css'); ?>
<?php bb_css_link('css/components/forms.css'); ?>
<?php bb_css_link('css/components/header.css'); ?>
<?php bb_css_link('css/components/footer.css'); ?>
<?php bb_css_link('css/components/cookie-banner.css'); ?>
<?php if (in_array('sidebar', $_components, true)) bb_css_link('css/components/sidebar.css'); ?>
<?php if (in_array('fab', $_components, true)) bb_css_link('css/components/fab.css'); ?>
<?php foreach ($_extra as $href) bb_css_link($href); ?>
