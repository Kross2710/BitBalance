<?php

session_start();
unset($_SESSION['cart']);
echo json_encode(['success' => true]);



