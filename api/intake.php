<?php
/**
 * Public lead intake endpoint — no auth required.
 * Accepts form submissions from external lead gen sites.
 * Rate-limited, honeypot-protected, auto-scored.
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';

$method = $_SERVER['REQUEST_METHOD'];

// Handle CORS preflight
if ($method === 'OPTIONS') {
    sendCorsHeaders();
    http_response_code(204);
    exit;
}

if ($method !== 'POST') {
    sendCorsHeaders();
    jsonError('Method not allowed', 405);
}

sendCorsHeaders();

// Rate limiting — file-based, 10 second cooldown per IP
$ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
$rateLimitFile = sys_get_temp_dir() . '/intake_' . md5($ip);

if (file_exists($rateLimitFile)) {
    $lastRequest = filemtime($rateLimitFile);
    if (time() - $lastRequest < 10) {
        jsonError('Too many requests. Please wait a moment.', 429);
    }
}
touch($rateLimitFile);

// Parse input
$data = json_decode(file_get_contents('php://input'), true);
if (!$data) {
    jsonError('Invalid request');
}

// Honeypot check — bots fill the hidden "website" field, humans leave it blank
if (!empty($data['website'])) {
    // Silently accept but don't insert — bot doesn't know it failed
    jsonResponse(['success' => true], 200);
}

// Validate required fields
$errors = validateIntakeLead($data);
if ($errors) {
    jsonError(implode(', ', $errors));
}

// Auto-calculate lead score
$score = 20; // Base score for submitting a form
if (!empty($data['phone'])) $score += 15;
if (!empty($data['city']) || !empty($data['state'])) $score += 10;
if (!empty($data['notes'])) $score += 10;
if (!empty($data['utm_source'])) $score += 5;

// Urgency boost (if provided by the form)
$urgency = $data['urgency'] ?? '';
switch ($urgency) {
    case 'today':       $score += 40; break;
    case 'this_week':   $score += 25; break;
    case 'this_month':  $score += 10; break;
    case 'researching': $score += 0;  break;
}

$score = min(100, $score);

// Insert lead
$pdo = getDbConnection();

$sql = 'INSERT INTO leads (name, email, phone, practice_area, status, score, source, city, state, notes, utm_source, utm_medium, utm_campaign) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)';
$stmt = $pdo->prepare($sql);
$stmt->execute([
    sanitize($data['name']),
    sanitize($data['email']),
    sanitize($data['phone'] ?? ''),
    $data['practice_area'],
    'New',
    $score,
    sanitize($data['source'] ?? 'website'),
    sanitize($data['city'] ?? ''),
    strtoupper(sanitize($data['state'] ?? '')),
    sanitize($data['notes'] ?? ''),
    sanitize($data['utm_source'] ?? ''),
    sanitize($data['utm_medium'] ?? ''),
    sanitize($data['utm_campaign'] ?? ''),
]);

// Never expose lead ID or data to public
jsonResponse(['success' => true], 201);

// --- Helper ---

function sendCorsHeaders() {
    loadEnv(__DIR__ . '/../.env');
    $allowed = getenv('ALLOWED_ORIGIN') ?: '*';
    header("Access-Control-Allow-Origin: $allowed");
    header('Access-Control-Allow-Methods: POST, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type');
}
