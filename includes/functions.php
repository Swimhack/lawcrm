<?php

function sanitize($value) {
    return htmlspecialchars(trim($value), ENT_QUOTES, 'UTF-8');
}

function jsonResponse($data, $status = 200) {
    http_response_code($status);
    header('Content-Type: application/json');
    echo json_encode($data);
    exit;
}

function jsonError($message, $status = 400) {
    jsonResponse(['error' => $message], $status);
}

function requireAuth() {
    session_start();
    if (empty($_SESSION['user_id'])) {
        if (isAjax()) {
            jsonError('Unauthorized', 401);
        }
        header('Location: /views/login.php');
        exit;
    }
}

function isAjax() {
    return !empty($_SERVER['HTTP_X_REQUESTED_WITH'])
        && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
}

function csrfToken() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function verifyCsrf($token) {
    return hash_equals($_SESSION['csrf_token'] ?? '', $token);
}

function validateLead($data) {
    $errors = [];

    if (empty($data['name'])) {
        $errors[] = 'Name is required';
    }
    if (empty($data['email']) || !filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Valid email is required';
    }
    if (!empty($data['phone']) && !preg_match('/^[\d\s\-\(\)\+\.]{7,20}$/', $data['phone'])) {
        $errors[] = 'Invalid phone number format';
    }

    $validAreas = ['Criminal Defense', 'Personal Injury', 'Family Law', 'Estate Planning', 'Business Law', 'Real Estate Law', 'Contact Form'];
    if (empty($data['practice_area']) || !in_array($data['practice_area'], $validAreas)) {
        $errors[] = 'Invalid practice area';
    }

    $validStatuses = ['New', 'In Progress', 'Closed'];
    if (empty($data['status']) || !in_array($data['status'], $validStatuses)) {
        $errors[] = 'Invalid status';
    }

    if (!isset($data['score']) || $data['score'] < 0 || $data['score'] > 100) {
        $errors[] = 'Score must be 0-100';
    }

    // Optional new fields
    $errors = array_merge($errors, validateLeadExtendedFields($data));

    return $errors;
}

function validateIntakeLead($data) {
    $errors = [];

    if (empty($data['name'])) {
        $errors[] = 'Name is required';
    }
    if (empty($data['email']) || !filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Valid email is required';
    }
    if (!empty($data['phone']) && !preg_match('/^[\d\s\-\(\)\+\.]{7,20}$/', $data['phone'])) {
        $errors[] = 'Invalid phone number format';
    }

    $validAreas = ['Criminal Defense', 'Personal Injury', 'Family Law', 'Estate Planning', 'Business Law', 'Real Estate Law', 'Contact Form'];
    if (empty($data['practice_area']) || !in_array($data['practice_area'], $validAreas)) {
        $errors[] = 'Invalid practice area';
    }

    $errors = array_merge($errors, validateLeadExtendedFields($data));

    return $errors;
}

function validateLeadExtendedFields($data) {
    $errors = [];

    if (!empty($data['city']) && strlen($data['city']) > 100) {
        $errors[] = 'City must be 100 characters or less';
    }
    if (!empty($data['state']) && !preg_match('/^[A-Z]{2}$/', strtoupper($data['state']))) {
        $errors[] = 'State must be a 2-letter code (e.g., TX)';
    }
    if (!empty($data['notes']) && strlen($data['notes']) > 5000) {
        $errors[] = 'Notes must be 5000 characters or less';
    }
    if (!empty($data['source']) && strlen($data['source']) > 100) {
        $errors[] = 'Source must be 100 characters or less';
    }
    if (!empty($data['utm_source']) && strlen($data['utm_source']) > 100) {
        $errors[] = 'UTM source must be 100 characters or less';
    }
    if (!empty($data['utm_medium']) && strlen($data['utm_medium']) > 100) {
        $errors[] = 'UTM medium must be 100 characters or less';
    }
    if (!empty($data['utm_campaign']) && strlen($data['utm_campaign']) > 100) {
        $errors[] = 'UTM campaign must be 100 characters or less';
    }

    return $errors;
}
