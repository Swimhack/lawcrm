<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';

session_start();

$route = $_GET['route'] ?? '';
$method = $_SERVER['REQUEST_METHOD'];

// Auth endpoints don't require session (login/logout)
if ($route === 'auth/login' || $route === 'auth/logout') {
    require_once __DIR__ . '/auth.php';
    exit;
}

// Public intake endpoint — no auth required
if ($route === 'intake') {
    require_once __DIR__ . '/intake.php';
    exit;
}

// Everything else requires auth
if (empty($_SESSION['user_id'])) {
    jsonError('Unauthorized', 401);
}

// Route to handler
switch ($route) {
    case 'leads':
    case 'leads/':
        require_once __DIR__ . '/leads.php';
        break;
    default:
        jsonError('Not found', 404);
}
