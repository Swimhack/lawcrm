<?php
/**
 * Integration tests for the public intake endpoint.
 * Tests: no-auth access, honeypot, rate limiting, validation, auto-scoring, new fields.
 */

$baseUrl = 'https://law-crm.com';
$testPrefix = '__INTAKE_' . time() . '_';
$createdIds = [];

function intakeRequest($body) {
    global $baseUrl;
    $opts = [
        'http' => [
            'method' => 'POST',
            'header' => "Content-Type: application/json\r\n",
            'content' => json_encode($body),
            'ignore_errors' => true,
            'timeout' => 10,
        ],
        'ssl' => ['verify_peer' => false, 'verify_peer_name' => false],
    ];
    $context = stream_context_create($opts);
    $response = @file_get_contents($baseUrl . '/api/intake', false, $context);
    preg_match('/(\d{3})/', $http_response_header[0] ?? '', $m);
    return [
        'status' => intval($m[1] ?? 0),
        'body' => json_decode($response, true),
        'raw' => $response,
    ];
}

// Cleanup — delete test leads via authenticated API after tests
function cleanupIntakeLeads() {
    global $testPrefix;
    require_once __DIR__ . '/../config/database.php';
    $pdo = getDbConnection();
    $stmt = $pdo->prepare("DELETE FROM leads WHERE name LIKE ?");
    $stmt->execute([$testPrefix . '%']);
    $count = $stmt->rowCount();
    if ($count > 0) echo "  [cleanup] Removed $count intake test leads\n";
}
register_shutdown_function('cleanupIntakeLeads');

// ================================================================
// NO AUTH REQUIRED
// ================================================================
test_section('Intake — No auth required');

$res = intakeRequest([
    'name' => $testPrefix . 'Public Lead',
    'email' => $testPrefix . 'public@test.com',
    'practice_area' => 'Criminal Defense',
]);
assert_equals(201, $res['status'], 'Intake accepts POST without auth');
assert_true($res['body']['success'] ?? false, 'Returns success: true');
assert_true(!isset($res['body']['id']), 'Does NOT expose lead ID');

// Verify it was actually stored
require_once __DIR__ . '/../config/database.php';
$pdo = getDbConnection();
$stmt = $pdo->prepare("SELECT * FROM leads WHERE email = ?");
$stmt->execute([$testPrefix . 'public@test.com']);
$lead = $stmt->fetch();
assert_not_empty($lead, 'Lead was stored in database');
assert_equals('New', $lead['status'], 'Status defaulted to New');
assert_true(intval($lead['score']) >= 20, 'Score auto-calculated (at least base 20)');

// ================================================================
// HONEYPOT
// ================================================================
test_section('Intake — Honeypot spam protection');

$countBefore = $pdo->query("SELECT COUNT(*) FROM leads")->fetchColumn();

// Wait for rate limit to clear
sleep(11);

$res = intakeRequest([
    'name' => $testPrefix . 'Bot Lead',
    'email' => $testPrefix . 'bot@spam.com',
    'practice_area' => 'Personal Injury',
    'website' => 'http://spam-site.com',  // Honeypot filled = bot
]);
assert_equals(200, $res['status'], 'Honeypot submission returns 200 (bot thinks it succeeded)');
assert_true($res['body']['success'] ?? false, 'Returns success: true (deception)');

$countAfter = $pdo->query("SELECT COUNT(*) FROM leads")->fetchColumn();
assert_equals(intval($countBefore), intval($countAfter), 'Honeypot lead was NOT inserted into DB');

// ================================================================
// VALIDATION
// ================================================================
test_section('Intake — Validation');

sleep(11); // Rate limit cooldown

$res = intakeRequest([]);
assert_equals(400, $res['status'], 'Empty body returns 400');

sleep(11);

$res = intakeRequest(['name' => 'Test']);
assert_equals(400, $res['status'], 'Missing email and practice_area returns 400');
assert_true(strpos($res['body']['error'] ?? '', 'email') !== false, 'Error mentions email');

sleep(11);

$res = intakeRequest([
    'name' => $testPrefix . 'Valid Minimal',
    'email' => $testPrefix . 'minimal@test.com',
    'practice_area' => 'Family Law',
]);
assert_equals(201, $res['status'], 'Minimal valid intake (name + email + practice_area) returns 201');

// ================================================================
// NEW FIELDS
// ================================================================
test_section('Intake — New fields stored correctly');

sleep(11);

$res = intakeRequest([
    'name' => $testPrefix . 'Full Fields',
    'email' => $testPrefix . 'full@test.com',
    'phone' => '713-555-0199',
    'practice_area' => 'Personal Injury',
    'source' => 'situation-dui-houston',
    'city' => 'Houston',
    'state' => 'TX',
    'notes' => 'Rear-ended on I-45, need help ASAP',
    'utm_source' => 'google',
    'utm_medium' => 'organic',
    'utm_campaign' => 'houston-pi',
]);
assert_equals(201, $res['status'], 'Full intake with all fields returns 201');

$stmt = $pdo->prepare("SELECT * FROM leads WHERE email = ?");
$stmt->execute([$testPrefix . 'full@test.com']);
$lead = $stmt->fetch();
assert_equals('situation-dui-houston', $lead['source'], 'Source field stored');
assert_equals('Houston', $lead['city'], 'City field stored');
assert_equals('TX', $lead['state'], 'State field stored');
assert_true(strpos($lead['notes'], 'Rear-ended') !== false, 'Notes field stored');
assert_equals('google', $lead['utm_source'], 'UTM source stored');
assert_equals('organic', $lead['utm_medium'], 'UTM medium stored');
assert_equals('houston-pi', $lead['utm_campaign'], 'UTM campaign stored');

// ================================================================
// AUTO-SCORING
// ================================================================
test_section('Intake — Auto-scoring');

// Lead with just name + email + practice_area = base 20
$stmt = $pdo->prepare("SELECT score FROM leads WHERE email = ?");
$stmt->execute([$testPrefix . 'minimal@test.com']);
$minScore = intval($stmt->fetchColumn());
assert_equals(20, $minScore, 'Minimal lead gets base score of 20');

// Lead with all fields = 20 (base) + 15 (phone) + 10 (location) + 10 (notes) + 5 (utm) = 60
$stmt->execute([$testPrefix . 'full@test.com']);
$fullScore = intval($stmt->fetchColumn());
assert_equals(60, $fullScore, 'Full lead gets score of 60 (20+15+10+10+5)');

// Test urgency scoring
sleep(11);

$res = intakeRequest([
    'name' => $testPrefix . 'Urgent Lead',
    'email' => $testPrefix . 'urgent@test.com',
    'practice_area' => 'Criminal Defense',
    'urgency' => 'today',
]);
assert_equals(201, $res['status'], 'Urgent intake returns 201');

$stmt = $pdo->prepare("SELECT score FROM leads WHERE email = ?");
$stmt->execute([$testPrefix . 'urgent@test.com']);
$urgentScore = intval($stmt->fetchColumn());
assert_equals(60, $urgentScore, 'Urgent lead gets 60 (20 base + 40 urgency)');

// ================================================================
// RATE LIMITING
// ================================================================
test_section('Intake — Rate limiting');

sleep(11); // Clear rate limit

// First request should succeed
$res = intakeRequest([
    'name' => $testPrefix . 'Rate1',
    'email' => $testPrefix . 'rate1@test.com',
    'practice_area' => 'Business Law',
]);
assert_equals(201, $res['status'], 'First request succeeds');

// Immediate second request should be rate-limited
$res = intakeRequest([
    'name' => $testPrefix . 'Rate2',
    'email' => $testPrefix . 'rate2@test.com',
    'practice_area' => 'Business Law',
]);
assert_equals(429, $res['status'], 'Rapid second request returns 429');

// ================================================================
// EXTENDED FIELD VALIDATION
// ================================================================
test_section('Intake — Extended field validation');

sleep(11);

$res = intakeRequest([
    'name' => $testPrefix . 'Bad State',
    'email' => $testPrefix . 'badstate@test.com',
    'practice_area' => 'Family Law',
    'state' => 'Texas',  // Should be TX
]);
assert_equals(400, $res['status'], 'Full state name rejected (must be 2-letter code)');

sleep(11);

$res = intakeRequest([
    'name' => $testPrefix . 'Long Notes',
    'email' => $testPrefix . 'longnotes@test.com',
    'practice_area' => 'Family Law',
    'notes' => str_repeat('A', 5001),
]);
assert_equals(400, $res['status'], 'Notes over 5000 chars rejected');

sleep(11);

$res = intakeRequest([
    'name' => $testPrefix . 'XSS Intake',
    'email' => $testPrefix . 'xss@test.com',
    'practice_area' => 'Business Law',
    'notes' => '<script>alert("xss")</script>',
    'city' => '<b>Houston</b>',
]);
assert_equals(201, $res['status'], 'XSS payloads accepted (sanitized on insert)');

$stmt = $pdo->prepare("SELECT city, notes FROM leads WHERE email = ?");
$stmt->execute([$testPrefix . 'xss@test.com']);
$xssLead = $stmt->fetch();
assert_true(strpos($xssLead['city'], '<b>') === false, 'XSS in city was sanitized');
assert_true(strpos($xssLead['notes'], '<script>') === false, 'XSS in notes was sanitized');
