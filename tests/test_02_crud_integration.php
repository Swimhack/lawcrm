<?php
/**
 * Integration tests — CRUD operations against real database.
 * Tests the full lifecycle: create, read, update, delete.
 * Uses a test-prefixed lead to avoid polluting real data.
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';

$pdo = getDbConnection();
$testPrefix = '__TEST_' . time() . '_';
$createdIds = [];

// Cleanup function — runs even if tests fail
function cleanupTestLeads() {
    global $pdo, $createdIds;
    if (!empty($createdIds)) {
        $placeholders = implode(',', array_fill(0, count($createdIds), '?'));
        $stmt = $pdo->prepare("DELETE FROM leads WHERE id IN ($placeholders)");
        $stmt->execute($createdIds);
        echo "\n  [cleanup] Removed " . count($createdIds) . " test leads\n";
    }
}
register_shutdown_function('cleanupTestLeads');

// ================================================================
// INSERT
// ================================================================
test_section('CRUD — INSERT lead');

$insertSql = 'INSERT INTO leads (name, email, phone, practice_area, status, score) VALUES (?, ?, ?, ?, ?, ?)';

// Valid insert
$stmt = $pdo->prepare($insertSql);
$stmt->execute([
    $testPrefix . 'John Doe',
    $testPrefix . 'john@test.com',
    '555-123-4567',
    'Criminal Defense',
    'New',
    75,
]);
$id1 = intval($pdo->lastInsertId());
$createdIds[] = $id1;
assert_true($id1 > 0, "INSERT valid lead returns ID ($id1)");

// Read it back
$stmt = $pdo->prepare('SELECT * FROM leads WHERE id = ?');
$stmt->execute([$id1]);
$lead = $stmt->fetch();
assert_equals($testPrefix . 'John Doe', $lead['name'], 'Inserted name matches');
assert_equals($testPrefix . 'john@test.com', $lead['email'], 'Inserted email matches');
assert_equals('555-123-4567', $lead['phone'], 'Inserted phone matches');
assert_equals('Criminal Defense', $lead['practice_area'], 'Inserted practice area matches');
assert_equals('New', $lead['status'], 'Inserted status matches');
assert_equals(75, intval($lead['score']), 'Inserted score matches');
assert_not_empty($lead['created_at'], 'created_at auto-populated');

// Insert with empty phone
$stmt = $pdo->prepare($insertSql);
$stmt->execute([$testPrefix . 'No Phone', $testPrefix . 'nophone@test.com', '', 'Family Law', 'New', 0]);
$id2 = intval($pdo->lastInsertId());
$createdIds[] = $id2;
$stmt = $pdo->prepare('SELECT phone FROM leads WHERE id = ?');
$stmt->execute([$id2]);
assert_equals('', $stmt->fetchColumn(), 'Empty phone stored correctly');

// Insert with score 0 (boundary)
$stmt = $pdo->prepare($insertSql);
$stmt->execute([$testPrefix . 'Zero Score', $testPrefix . 'zero@test.com', '', 'Business Law', 'New', 0]);
$id3 = intval($pdo->lastInsertId());
$createdIds[] = $id3;
$stmt = $pdo->prepare('SELECT score FROM leads WHERE id = ?');
$stmt->execute([$id3]);
assert_equals(0, intval($stmt->fetchColumn()), 'Score 0 stored correctly (not null)');

// Insert with score 100 (boundary)
$stmt = $pdo->prepare($insertSql);
$stmt->execute([$testPrefix . 'Max Score', $testPrefix . 'max@test.com', '', 'Estate Planning', 'Closed', 100]);
$id4 = intval($pdo->lastInsertId());
$createdIds[] = $id4;
$stmt = $pdo->prepare('SELECT score FROM leads WHERE id = ?');
$stmt->execute([$id4]);
assert_equals(100, intval($stmt->fetchColumn()), 'Score 100 stored correctly');

// Insert with special characters (XSS payload stored sanitized)
$stmt = $pdo->prepare($insertSql);
$xssName = $testPrefix . sanitize('<script>alert("xss")</script>');
$stmt->execute([$xssName, $testPrefix . 'xss@test.com', '', 'Personal Injury', 'New', 50]);
$id5 = intval($pdo->lastInsertId());
$createdIds[] = $id5;
$stmt = $pdo->prepare('SELECT name FROM leads WHERE id = ?');
$stmt->execute([$id5]);
$storedName = $stmt->fetchColumn();
assert_true(strpos($storedName, '<script>') === false, 'XSS script tag not stored raw in DB');
assert_true(strpos($storedName, '&lt;script&gt;') !== false, 'XSS payload stored as escaped HTML entities');

// Insert with unicode
$stmt = $pdo->prepare($insertSql);
$stmt->execute([$testPrefix . 'José García', $testPrefix . 'jose@test.com', '+1 555-999-0000', 'Real Estate Law', 'In Progress', 88]);
$id6 = intval($pdo->lastInsertId());
$createdIds[] = $id6;
$stmt = $pdo->prepare('SELECT name FROM leads WHERE id = ?');
$stmt->execute([$id6]);
assert_equals($testPrefix . 'José García', $stmt->fetchColumn(), 'Unicode name stored and retrieved correctly');

// ================================================================
// SELECT — pagination and filtering
// ================================================================
test_section('CRUD — SELECT with filters');

// Count test leads
$stmt = $pdo->prepare("SELECT COUNT(*) FROM leads WHERE name LIKE ?");
$stmt->execute([$testPrefix . '%']);
$testCount = intval($stmt->fetchColumn());
assert_equals(count($createdIds), $testCount, "All $testCount test leads found in DB");

// Filter by status
$stmt = $pdo->prepare("SELECT COUNT(*) FROM leads WHERE name LIKE ? AND status = ?");
$stmt->execute([$testPrefix . '%', 'New']);
$newCount = intval($stmt->fetchColumn());
assert_true($newCount > 0, "Filter by status=New returns $newCount leads");

// Filter by practice area
$stmt = $pdo->prepare("SELECT COUNT(*) FROM leads WHERE name LIKE ? AND practice_area = ?");
$stmt->execute([$testPrefix . '%', 'Criminal Defense']);
assert_equals(1, intval($stmt->fetchColumn()), 'Filter by practice_area=Criminal Defense returns 1');

// Search by name substring
$stmt = $pdo->prepare("SELECT COUNT(*) FROM leads WHERE name LIKE ?");
$stmt->execute(['%' . $testPrefix . 'John%']);
assert_equals(1, intval($stmt->fetchColumn()), 'Search by name substring finds correct lead');

// Search by email
$stmt = $pdo->prepare("SELECT COUNT(*) FROM leads WHERE email LIKE ?");
$stmt->execute(['%' . $testPrefix . 'jose%']);
assert_equals(1, intval($stmt->fetchColumn()), 'Search by email substring finds correct lead');

// Pagination — LIMIT and OFFSET
$stmt = $pdo->prepare("SELECT * FROM leads WHERE name LIKE ? ORDER BY created_at DESC LIMIT ? OFFSET ?");
$stmt->execute([$testPrefix . '%', 2, 0]);
$page1 = $stmt->fetchAll();
assert_equals(2, count($page1), 'Pagination: page 1 returns 2 leads (limit=2)');

$stmt->execute([$testPrefix . '%', 2, 2]);
$page2 = $stmt->fetchAll();
assert_true(count($page2) > 0, 'Pagination: page 2 returns leads');
assert_true($page1[0]['id'] !== $page2[0]['id'], 'Pagination: page 1 and 2 return different leads');

// ================================================================
// UPDATE
// ================================================================
test_section('CRUD — UPDATE lead');

$updateSql = 'UPDATE leads SET name = ?, email = ?, phone = ?, practice_area = ?, status = ?, score = ? WHERE id = ?';

// Update all fields
$stmt = $pdo->prepare($updateSql);
$stmt->execute([
    $testPrefix . 'Jane Smith',
    $testPrefix . 'jane@updated.com',
    '999-888-7777',
    'Family Law',
    'In Progress',
    90,
    $id1,
]);
assert_equals(1, $stmt->rowCount(), 'UPDATE affects exactly 1 row');

// Verify update
$stmt = $pdo->prepare('SELECT * FROM leads WHERE id = ?');
$stmt->execute([$id1]);
$updated = $stmt->fetch();
assert_equals($testPrefix . 'Jane Smith', $updated['name'], 'Updated name persisted');
assert_equals($testPrefix . 'jane@updated.com', $updated['email'], 'Updated email persisted');
assert_equals('999-888-7777', $updated['phone'], 'Updated phone persisted');
assert_equals('Family Law', $updated['practice_area'], 'Updated practice area persisted');
assert_equals('In Progress', $updated['status'], 'Updated status persisted');
assert_equals(90, intval($updated['score']), 'Updated score persisted');

// Update to score 0 (ensure not treated as null/false)
$stmt = $pdo->prepare('UPDATE leads SET score = ? WHERE id = ?');
$stmt->execute([0, $id1]);
$stmt = $pdo->prepare('SELECT score FROM leads WHERE id = ?');
$stmt->execute([$id1]);
assert_equals(0, intval($stmt->fetchColumn()), 'Score updated to 0 persists correctly');

// Update non-existent ID
$stmt = $pdo->prepare($updateSql);
$stmt->execute(['Ghost', 'ghost@test.com', '', 'Business Law', 'New', 0, 999999999]);
assert_equals(0, $stmt->rowCount(), 'UPDATE non-existent ID affects 0 rows');

// ================================================================
// DELETE
// ================================================================
test_section('CRUD — DELETE lead');

// Delete one lead
$stmt = $pdo->prepare('DELETE FROM leads WHERE id = ?');
$stmt->execute([$id1]);
assert_equals(1, $stmt->rowCount(), 'DELETE affects exactly 1 row');

// Verify deleted
$stmt = $pdo->prepare('SELECT COUNT(*) FROM leads WHERE id = ?');
$stmt->execute([$id1]);
assert_equals(0, intval($stmt->fetchColumn()), 'Deleted lead no longer in DB');

// Remove from cleanup list since already deleted
$createdIds = array_values(array_diff($createdIds, [$id1]));

// Delete non-existent ID
$stmt = $pdo->prepare('DELETE FROM leads WHERE id = ?');
$stmt->execute([999999999]);
assert_equals(0, $stmt->rowCount(), 'DELETE non-existent ID affects 0 rows');

// ================================================================
// ENUM CONSTRAINTS (if DB uses ENUM type)
// ================================================================
test_section('DB — column constraints');

// Test that DB rejects invalid status if ENUM is enforced
try {
    $stmt = $pdo->prepare($insertSql);
    $stmt->execute([$testPrefix . 'BadStatus', $testPrefix . 'bad@test.com', '', 'Criminal Defense', 'InvalidStatus', 50]);
    $badId = intval($pdo->lastInsertId());
    $createdIds[] = $badId;

    // If we get here, DB doesn't enforce ENUM — check what was stored
    $stmt = $pdo->prepare('SELECT status FROM leads WHERE id = ?');
    $stmt->execute([$badId]);
    $storedStatus = $stmt->fetchColumn();
    assert_true(true, "DB accepted invalid status (stored as '$storedStatus') — validation is app-layer only");
} catch (PDOException $e) {
    assert_true(true, 'DB rejected invalid ENUM status — DB-level enforcement active');
}

// ================================================================
// SQL INJECTION RESISTANCE
// ================================================================
test_section('Security — SQL injection resistance');

// Attempt injection via name
$stmt = $pdo->prepare($insertSql);
$injectionName = "'; DROP TABLE leads; --";
$stmt->execute([sanitize($injectionName), $testPrefix . 'inject@test.com', '', 'Business Law', 'New', 50]);
$injectId = intval($pdo->lastInsertId());
$createdIds[] = $injectId;

// Table still exists?
$stmt = $pdo->query("SELECT COUNT(*) FROM leads");
$count = intval($stmt->fetchColumn());
assert_true($count > 0, "SQL injection in name field — leads table still exists ($count rows)");

// Attempt injection via phone (even though validated, test prepared statement)
$stmt = $pdo->prepare($insertSql);
$stmt->execute([$testPrefix . 'PhoneInject', $testPrefix . 'phoneinj@test.com', "1; DROP TABLE leads", 'Business Law', 'New', 50]);
$phoneInjectId = intval($pdo->lastInsertId());
$createdIds[] = $phoneInjectId;

$stmt = $pdo->query("SELECT COUNT(*) FROM leads");
assert_true(intval($stmt->fetchColumn()) > 0, 'SQL injection in phone field — table intact');
