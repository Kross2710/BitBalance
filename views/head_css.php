<?php
/**
 * Standard CSS chain — include this in <head> of every page after init.
 * Pages add their own page-specific stylesheet AFTER this include.
 *
 * Usage:
 *   <?php include PROJECT_ROOT . 'views/head_css.php'; ?>
 *   <link rel="stylesheet" href="<?= BASE_URL ?>css/pages/dashboard.css">
 */
?>
<link rel="stylesheet" href="<?= BASE_URL ?>css/tokens.css">
<link rel="stylesheet" href="<?= BASE_URL ?>css/base.css">
<link rel="stylesheet" href="<?= BASE_URL ?>css/components/forms.css">
<link rel="stylesheet" href="<?= BASE_URL ?>css/components/header.css">
<link rel="stylesheet" href="<?= BASE_URL ?>css/components/footer.css">
<link rel="stylesheet" href="<?= BASE_URL ?>css/components/sidebar.css">
<link rel="stylesheet" href="<?= BASE_URL ?>css/components/cookie-banner.css">
