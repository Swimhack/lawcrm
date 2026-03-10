<?php
/**
 * API endpoint tests — hits the live HTTPS endpoints.
 * Tests the full HTTP request/response cycle including auth, CSRF, and error handling.
 */

$baseUrl = 'https://law-crm.com';
$testPrefix = '__APITEST_' . time() . '_';
$createdLeadIds = [];

// --- HTTP helper ---
function apiRequest($method, $path, $body = null, $cookies = '') {
    global $baseUrl;
    $url = $baseUrl . $path;

    $opts = [
        'http' => [
            'method' => $method,
            'header' => "Content-Type: application/json\r\nX-Requested-With: XMLHttpRequest\r\n",
            'ignore_errors' => true,
            'timeout' => 10,
        ],
        'ssl' => [
            'verify_peer' => false,
            'verify_peer_name' => false,
        ],
    ];

    if ($cookies) {
        $opts['http']['header'] .= "Cookie: $cookies\r\n";
    }
    if ($body !== null) {
        $opts['http']['content'] = json_encode($body);
    }

    $context = stream_context_create($opts);
    $response = @file_get_contents($url, false, $context);
    $statusLine = $http_response_header[0] ?? '';
    preg_match('/(\d{3})/', $statusLine, $m);
    $status = intval($m[1] ?? 0);

    // Extract Set-Cookie
    $setCookies = [];
    foreach ($http_response_header as $header) {
        if (stripos($header, 'Set-Cookie:') === 0) {
            preg_match('/Set-Cookie:\s*([^;]+)/', $header, $cm);
            if ($cm) $setCookies[] = $cm[1];
        }
    }

    return [
        'status' => $status,
        'body' => json_decode($response, true),
        'raw' => $response,
        'cookies' => implode('; ', $setCookies),
    ];
}

// ================================================================
// AUTH — unauthenticated access
// ================================================================
test_section('API — Authentication required');

$res = apiRequest('GET', '/api/leads');
assert_equals(401, $res['status'], 'GET /api/leads without auth returns 401');
assert_equals('Unauthorized', $res['body']['error'] ?? '', '401 error message is "Unauthorized"');

$res = apiRequest('POST', '/api/leads', ['name' => 'test']);
assert_equals(401, $res['status'], 'POST /api/leads without auth returns 401');

$res = apiRequest('PUT', '/api/leads', ['id' => 1, 'name' => 'test']);
assert_equals(401, $res['status'], 'PUT /api/leads without auth returns 401');

$res = apiRequest('DELETE', '/api/leads', ['id' => 1]);
assert_equals(401, $res['status'], 'DELETE /api/leads without auth returns 401');

// ================================================================
// AUTH — login
// ================================================================
test_section('API — Login flow');

$res = apiRequest('POST', '/api/auth/login', ['email' => 'wrong@test.com', 'password' => 'wrong']);
assert_equals(401, $res['status'], 'Login with bad credentials returns 401');

$res = apiRequest('POST', '/api/auth/login', ['email' => '', 'password' => '']);
assert_equals(400, $res['status'], 'Login with empty fields returns 400');

$res = apiRequest('POST', '/api/auth/login', ['email' => 'admin@law-crm.com', 'password' => 'changeme123']);
assert_equals(200, $res['status'], 'Login with valid credentials returns 200');
assert_not_empty($res['body']['csrf_token'] ?? '', 'Login returns CSRF token');
assert_equals('admin@law-crm.com', $res['body']['email'] ?? '', 'Login returns correct email');

$sessionCookie = $res['cookies'];
$csrfToken = $res['body']['csrf_token'];
assert_not_empty($sessionCookie, 'Login sets session cookie');

// ================================================================
// LEADS — CRUD with auth
// ================================================================
test_section('API — Create lead (POST)');

// Missing CSRF
$res = apiRequest('POST', '/api/leads', [
    'name' => $testPrefix . 'NoCsrf',
    'email' => 'nocsrf@test.com',
    'practice_area' => 'Business Law',
    'status' => 'New',
    'score' => 50,
], $sessionCookie);
assert_equals(403, $res['status'], 'POST without CSRF token returns 403');

// Invalid data
$res = apiRequest('POST', '/api/leads', [
    'name' => '',
    'email' => 'invalid',
    'practice_area' => 'Fake',
    'status' => 'Fake',
    'score' => -1,
    'csrf_token' => $csrfToken,
], $sessionCookie);
assert_equals(400, $res['status'], 'POST with all invalid fields returns 400');
$errorMsg = $res['body']['error'] ?? '';
assert_true(strpos($errorMsg, 'Name is required') !== false, 'Error includes name validation');
assert_true(strpos($errorMsg, 'Valid email is required') !== false, 'Error includes email validation');

// Valid create
$res = apiRequest('POST', '/api/leads', [
    'name' => $testPrefix . 'API Lead',
    'email' => $testPrefix . 'api@test.com',
    'phone' => '555-000-1111',
    'practice_area' => 'Criminal Defense',
    'status' => 'New',
    'score' => 65,
    'csrf_token' => $csrfToken,
], $sessionCookie);
assert_equals(201, $res['status'], 'POST valid lead returns 201');
assert_not_empty($res['body']['id'] ?? '', 'Created lead has ID');
assert_equals($testPrefix . 'API Lead', $res['body']['name'] ?? '', 'Created lead name matches');
$apiLeadId = intval($res['body']['id']);
$createdLeadIds[] = $apiLeadId;

// ================================================================
test_section('API — Read leads (GET)');

$res = apiRequest('GET', '/api/leads', null, $sessionCookie);
assert_equals(200, $res['status'], 'GET /api/leads returns 200');
assert_true(isset($res['body']['leads']), 'Response contains leads array');
assert_true(isset($res['body']['total']), 'Response contains total count');
assert_true(isset($res['body']['page']), 'Response contains page number');
assert_true(isset($res['body']['pages']), 'Response contains page count');
assert_true($res['body']['total'] > 0, 'Total leads > 0');

// Search
$res = apiRequest('GET', '/api/leads?search=' . urlencode($testPrefix), null, $sessionCookie);
assert_equals(200, $res['status'], 'GET with search filter returns 200');
assert_true($res['body']['total'] >= 1, 'Search finds test lead');

// Filter by status
$res = apiRequest('GET', '/api/leads?status=New', null, $sessionCookie);
assert_equals(200, $res['status'], 'GET with status filter returns 200');
foreach ($res['body']['leads'] as $l) {
    assert_equals('New', $l['status'], "Filtered lead status is 'New' (got {$l['name']})");
    break; // Just check first one
}

// Pagination
$res = apiRequest('GET', '/api/leads?limit=1&page=1', null, $sessionCookie);
assert_equals(200, $res['status'], 'GET with limit=1 returns 200');
assert_equals(1, count($res['body']['leads']), 'Limit=1 returns exactly 1 lead');
assert_true($res['body']['pages'] >= 1, 'Pages count is at least 1');

// ================================================================
test_section('API — Update lead (PUT)');

// Missing CSRF
$res = apiRequest('PUT', '/api/leads', [
    'id' => $apiLeadId,
    'name' => 'Updated',
    'email' => 'updated@test.com',
    'practice_area' => 'Family Law',
    'status' => 'In Progress',
    'score' => 80,
], $sessionCookie);
assert_equals(403, $res['status'], 'PUT without CSRF returns 403');

// Missing ID
$res = apiRequest('PUT', '/api/leads', [
    'name' => 'Updated',
    'email' => 'updated@test.com',
    'practice_area' => 'Family Law',
    'status' => 'In Progress',
    'score' => 80,
    'csrf_token' => $csrfToken,
], $sessionCookie);
assert_equals(400, $res['status'], 'PUT without ID returns 400');

// Valid update
$res = apiRequest('PUT', '/api/leads', [
    'id' => $apiLeadId,
    'name' => $testPrefix . 'Updated Lead',
    'email' => $testPrefix . 'updated@test.com',
    'phone' => '999-000-2222',
    'practice_area' => 'Estate Planning',
    'status' => 'In Progress',
    'score' => 95,
    'csrf_token' => $csrfToken,
], $sessionCookie);
assert_equals(200, $res['status'], 'PUT valid update returns 200');
assert_equals($testPrefix . 'Updated Lead', $res['body']['name'] ?? '', 'Updated name returned');
assert_equals('Estate Planning', $res['body']['practice_area'] ?? '', 'Updated practice area returned');
assert_equals(95, intval($res['body']['score'] ?? 0), 'Updated score returned');

// Update non-existent lead
$res = apiRequest('PUT', '/api/leads', [
    'id' => 999999999,
    'name' => 'Ghost',
    'email' => 'ghost@test.com',
    'practice_area' => 'Business Law',
    'status' => 'New',
    'score' => 0,
    'csrf_token' => $csrfToken,
], $sessionCookie);
assert_equals(404, $res['status'], 'PUT non-existent lead returns 404');

// ================================================================
test_section('API — Delete lead (DELETE)');

// Missing CSRF
$res = apiRequest('DELETE', '/api/leads', ['id' => $apiLeadId], $sessionCookie);
assert_equals(403, $res['status'], 'DELETE without CSRF returns 403');

// Missing ID
$res = apiRequest('DELETE', '/api/leads', ['csrf_token' => $csrfToken], $sessionCookie);
assert_equals(400, $res['status'], 'DELETE without ID returns 400');

// Valid delete
$res = apiRequest('DELETE', '/api/leads', ['id' => $apiLeadId, 'csrf_token' => $csrfToken], $sessionCookie);
assert_equals(200, $res['status'], 'DELETE valid lead returns 200');
assert_true($res['body']['success'] ?? false, 'DELETE returns success=true');
$createdLeadIds = array_diff($createdLeadIds, [$apiLeadId]);

// Delete already-deleted lead
$res = apiRequest('DELETE', '/api/leads', ['id' => $apiLeadId, 'csrf_token' => $csrfToken], $sessionCookie);
assert_equals(404, $res['status'], 'DELETE already-deleted lead returns 404');

// ================================================================
test_section('API — Logout');

$res = apiRequest('POST', '/api/auth/logout', null, $sessionCookie);
assert_equals(200, $res['status'], 'Logout returns 200');

// Verify session is dead
$res = apiRequest('GET', '/api/leads', null, $sessionCookie);
assert_equals(401, $res['status'], 'GET after logout returns 401');

// ================================================================
test_section('API — Route handling');

$res = apiRequest('GET', '/api/nonexistent', null, $sessionCookie);
// After logout, should get 401 before 404
assert_true(in_array($res['status'], [401, 404]), 'Non-existent route returns 401 or 404 (got ' . $res['status'] . ')');

// Cleanup any remaining test leads via DB
if (!empty($createdLeadIds)) {
    require_once __DIR__ . '/../config/database.php';
    $pdo = getDbConnection();
    $placeholders = implode(',', array_fill(0, count($createdLeadIds), '?'));
    $stmt = $pdo->prepare("DELETE FROM leads WHERE id IN ($placeholders)");
    $stmt->execute(array_values($createdLeadIds));
    echo "  [cleanup] Removed " . count($createdLeadIds) . " API test leads via DB\n";
}
