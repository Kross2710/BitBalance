<?php
require_once __DIR__ . '/include/init.php';
setcookie(session_name(), '', 100);
session_unset();       // Clear all $_SESSION variables
session_destroy();     // Destroy the session

// Redirect to homepage
header("Location:" . BASE_URL . "index.php");
exit();