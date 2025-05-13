<?php
session_start();       // Start or resume session
session_unset();       // Clear all $_SESSION variables
session_destroy();     // Destroy the session

// Redirect to homepage or login page
header("Location: index.php");
exit();