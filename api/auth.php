<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';

$method = $_SERVER['REQUEST_METHOD'];
$route = $_GET['route'] ?? '';

if ($method === 'POST' && $route === 'auth/login') {
    $data = json_decode(file_get_contents('php://input'), true);
    $email = trim($data['email'] ?? '');
    $password = $data['password'] ?? '';

    if (empty($email) || empty($password)) {
        jsonError('Email and password are required');
    }

    $pdo = getDbConnection();
    $stmt = $pdo->prepare('SELECT id, name, email, password_hash FROM users WHERE email = ?');
    $stmt->execute([$email]);
    $user = $stmt->fetch();

    if (!$user || !password_verify($password, $user['password_hash'])) {
        jsonError('Invalid email or password', 401);
    }

    $_SESSION['user_id'] = $user['id'];
    $_SESSION['user_name'] = $user['name'];
    $_SESSION['user_email'] = $user['email'];
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));

    jsonResponse([
        'id' => $user['id'],
        'name' => $user['name'],
        'email' => $user['email'],
        'csrf_token' => $_SESSION['csrf_token'],
    ]);
}

if ($method === 'POST' && $route === 'auth/logout') {
    session_destroy();
    jsonResponse(['success' => true]);
}

jsonError('Method not allowed', 405);
