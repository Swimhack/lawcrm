<?php
require_once __DIR__ . '/includes/functions.php';

session_start();

// Authenticated users get the dashboard
if (!empty($_SESSION['user_id'])) {
    require_once __DIR__ . '/views/dashboard.php';
    exit;
}

// Everyone else gets the login page
require_once __DIR__ . '/views/login.php';
