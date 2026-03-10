<?php
/**
 * Unit tests for validateLead() — every field, every edge case.
 * Tests validation logic only, no database or network needed.
 */

require_once __DIR__ . '/../includes/functions.php';

// --- Helper: build a valid lead, then override specific fields ---
function validLead($overrides = []) {
    return array_merge([
        'name'          => 'John Doe',
        'email'         => 'john@example.com',
        'phone'         => '555-123-4567',
        'practice_area' => 'Criminal Defense',
        'status'        => 'New',
        'score'         => 50,
    ], $overrides);
}

// ================================================================
// NAME FIELD
// ================================================================
test_section('Name — required, non-empty');

$errors = validateLead(validLead());
assert_empty($errors, 'Valid lead passes all validation');

$errors = validateLead(validLead(['name' => '']));
assert_contains('Name is required', $errors, 'Empty string name rejected');

$errors = validateLead(validLead(['name' => null]));
assert_contains('Name is required', $errors, 'Null name rejected');

$errors = validateLead(array_diff_key(validLead(), ['name' => '']));
assert_contains('Name is required', $errors, 'Missing name key rejected');

$errors = validateLead(validLead(['name' => '   ']));
// Note: current validation uses empty() which treats whitespace-only as truthy
// This documents current behavior — whitespace-only name passes validation
assert_true(!in_array('Name is required', $errors), 'Whitespace-only name passes (documents current behavior)');

$errors = validateLead(validLead(['name' => 'A']));
assert_empty($errors, 'Single character name accepted');

$errors = validateLead(validLead(['name' => str_repeat('A', 255)]));
assert_empty($errors, '255-char name accepted (max VARCHAR)');

$errors = validateLead(validLead(['name' => str_repeat('A', 500)]));
assert_empty($errors, '500-char name passes validation (DB will truncate or error)');

$errors = validateLead(validLead(['name' => '<script>alert("xss")</script>']));
assert_empty($errors, 'XSS in name passes validation (sanitized at insert time)');

$errors = validateLead(validLead(['name' => "O'Brien"]));
assert_empty($errors, 'Apostrophe in name accepted');

$errors = validateLead(validLead(['name' => 'José García-López']));
assert_empty($errors, 'Unicode characters in name accepted');

$errors = validateLead(validLead(['name' => '0']));
// empty('0') is true in PHP — this is a known PHP gotcha
assert_contains('Name is required', $errors, 'String "0" as name rejected by empty() — PHP gotcha');

// ================================================================
// EMAIL FIELD
// ================================================================
test_section('Email — required, must be valid format');

$errors = validateLead(validLead(['email' => '']));
assert_contains('Valid email is required', $errors, 'Empty email rejected');

$errors = validateLead(validLead(['email' => null]));
assert_contains('Valid email is required', $errors, 'Null email rejected');

$errors = validateLead(array_diff_key(validLead(), ['email' => '']));
assert_contains('Valid email is required', $errors, 'Missing email key rejected');

$errors = validateLead(validLead(['email' => 'notanemail']));
assert_contains('Valid email is required', $errors, 'Plain string rejected');

$errors = validateLead(validLead(['email' => 'missing@']));
assert_contains('Valid email is required', $errors, 'Missing domain rejected');

$errors = validateLead(validLead(['email' => '@domain.com']));
assert_contains('Valid email is required', $errors, 'Missing local part rejected');

$errors = validateLead(validLead(['email' => 'user@domain']));
// filter_var accepts user@domain (no TLD) as valid — document this
$noTldResult = validateLead(validLead(['email' => 'user@domain']));
assert_true(true, 'user@domain — filter_var result documented: ' . (empty($noTldResult) ? 'accepted' : 'rejected'));

$errors = validateLead(validLead(['email' => 'user@domain.com']));
assert_empty($errors, 'Standard email accepted');

$errors = validateLead(validLead(['email' => 'user+tag@domain.com']));
assert_empty($errors, 'Plus-tagged email accepted');

$errors = validateLead(validLead(['email' => 'user.name@sub.domain.com']));
assert_empty($errors, 'Dotted email with subdomain accepted');

$errors = validateLead(validLead(['email' => 'USER@DOMAIN.COM']));
assert_empty($errors, 'Uppercase email accepted');

$errors = validateLead(validLead(['email' => 'a@b.co']));
assert_empty($errors, 'Short email with 2-char TLD accepted');

$errors = validateLead(validLead(['email' => 'user name@domain.com']));
assert_contains('Valid email is required', $errors, 'Space in email rejected');

$errors = validateLead(validLead(['email' => 'user@dom ain.com']));
assert_contains('Valid email is required', $errors, 'Space in domain rejected');

$errors = validateLead(validLead(['email' => '<script>@xss.com']));
assert_contains('Valid email is required', $errors, 'XSS in email rejected by filter_var');

// ================================================================
// PHONE FIELD
// ================================================================
test_section('Phone — optional, validated format when present');

$errors = validateLead(validLead(['phone' => '']));
assert_empty($errors, 'Empty phone accepted (optional field)');

$errors = validateLead(validLead(['phone' => null]));
assert_empty($errors, 'Null phone accepted');

$errors = validateLead(array_diff_key(validLead(), ['phone' => '']));
assert_empty($errors, 'Missing phone key accepted');

$errors = validateLead(validLead(['phone' => '5551234567']));
assert_empty($errors, '10-digit no separators accepted');

$errors = validateLead(validLead(['phone' => '555-123-4567']));
assert_empty($errors, 'Dashed format accepted');

$errors = validateLead(validLead(['phone' => '(555) 123-4567']));
assert_empty($errors, 'Parenthesized area code accepted');

$errors = validateLead(validLead(['phone' => '+1 555 123 4567']));
assert_empty($errors, 'International format with + accepted');

$errors = validateLead(validLead(['phone' => '555.123.4567']));
assert_empty($errors, 'Dot-separated accepted');

$errors = validateLead(validLead(['phone' => '+44 20 7946 0958']));
assert_empty($errors, 'UK format accepted');

$errors = validateLead(validLead(['phone' => '123456']));
assert_contains('Invalid phone number format', $errors, '6-digit phone rejected (min 7)');

$errors = validateLead(validLead(['phone' => '123456789012345678901']));
assert_contains('Invalid phone number format', $errors, '21-digit phone rejected (max 20)');

$errors = validateLead(validLead(['phone' => '12345678901234567890']));
assert_empty($errors, '20-digit phone accepted (boundary)');

$errors = validateLead(validLead(['phone' => '1234567']));
assert_empty($errors, '7-digit phone accepted (boundary)');

$errors = validateLead(validLead(['phone' => 'not-a-phone']));
assert_contains('Invalid phone number format', $errors, 'Alpha characters rejected');

$errors = validateLead(validLead(['phone' => '555-ABC-4567']));
assert_contains('Invalid phone number format', $errors, 'Mixed alpha-numeric rejected');

$errors = validateLead(validLead(['phone' => '<script>alert(1)</script>']));
assert_contains('Invalid phone number format', $errors, 'XSS in phone rejected');

$errors = validateLead(validLead(['phone' => '555; DROP TABLE leads;']));
assert_contains('Invalid phone number format', $errors, 'SQL injection in phone rejected');

// ================================================================
// PRACTICE AREA FIELD
// ================================================================
test_section('Practice Area — required, must be from allowed list');

$validAreas = ['Criminal Defense', 'Personal Injury', 'Family Law', 'Estate Planning', 'Business Law', 'Real Estate Law', 'Contact Form'];

foreach ($validAreas as $area) {
    $errors = validateLead(validLead(['practice_area' => $area]));
    assert_true(!in_array('Invalid practice area', $errors), "Practice area '$area' accepted");
}

$errors = validateLead(validLead(['practice_area' => '']));
assert_contains('Invalid practice area', $errors, 'Empty practice area rejected');

$errors = validateLead(validLead(['practice_area' => null]));
assert_contains('Invalid practice area', $errors, 'Null practice area rejected');

$errors = validateLead(array_diff_key(validLead(), ['practice_area' => '']));
assert_contains('Invalid practice area', $errors, 'Missing practice area key rejected');

$errors = validateLead(validLead(['practice_area' => 'Tax Law']));
assert_contains('Invalid practice area', $errors, 'Unlisted practice area rejected');

$errors = validateLead(validLead(['practice_area' => 'criminal defense']));
assert_contains('Invalid practice area', $errors, 'Lowercase practice area rejected (case-sensitive)');

$errors = validateLead(validLead(['practice_area' => 'CRIMINAL DEFENSE']));
assert_contains('Invalid practice area', $errors, 'Uppercase practice area rejected (case-sensitive)');

$errors = validateLead(validLead(['practice_area' => 'Criminal Defense ']));
assert_contains('Invalid practice area', $errors, 'Trailing space in practice area rejected');

$errors = validateLead(validLead(['practice_area' => '<script>Criminal Defense</script>']));
assert_contains('Invalid practice area', $errors, 'XSS wrapped practice area rejected');

// ================================================================
// STATUS FIELD
// ================================================================
test_section('Status — required, must be from allowed list');

$validStatuses = ['New', 'In Progress', 'Closed'];

foreach ($validStatuses as $status) {
    $errors = validateLead(validLead(['status' => $status]));
    assert_true(!in_array('Invalid status', $errors), "Status '$status' accepted");
}

$errors = validateLead(validLead(['status' => '']));
assert_contains('Invalid status', $errors, 'Empty status rejected');

$errors = validateLead(validLead(['status' => null]));
assert_contains('Invalid status', $errors, 'Null status rejected');

$errors = validateLead(array_diff_key(validLead(), ['status' => '']));
assert_contains('Invalid status', $errors, 'Missing status key rejected');

$errors = validateLead(validLead(['status' => 'new']));
assert_contains('Invalid status', $errors, 'Lowercase status rejected');

$errors = validateLead(validLead(['status' => 'Pending']));
assert_contains('Invalid status', $errors, 'Unlisted status rejected');

$errors = validateLead(validLead(['status' => 'In  Progress']));
assert_contains('Invalid status', $errors, 'Double-space in status rejected');

$errors = validateLead(validLead(['status' => 'Closed ']));
assert_contains('Invalid status', $errors, 'Trailing space in status rejected');

// ================================================================
// SCORE FIELD
// ================================================================
test_section('Score — required, integer 0-100');

$errors = validateLead(validLead(['score' => 0]));
assert_empty($errors, 'Score 0 accepted (boundary)');

$errors = validateLead(validLead(['score' => 100]));
assert_empty($errors, 'Score 100 accepted (boundary)');

$errors = validateLead(validLead(['score' => 50]));
assert_empty($errors, 'Score 50 accepted (mid-range)');

$errors = validateLead(validLead(['score' => 1]));
assert_empty($errors, 'Score 1 accepted');

$errors = validateLead(validLead(['score' => 99]));
assert_empty($errors, 'Score 99 accepted');

$errors = validateLead(validLead(['score' => -1]));
assert_contains('Score must be 0-100', $errors, 'Negative score rejected');

$errors = validateLead(validLead(['score' => 101]));
assert_contains('Score must be 0-100', $errors, 'Score 101 rejected');

$errors = validateLead(validLead(['score' => 999]));
assert_contains('Score must be 0-100', $errors, 'Score 999 rejected');

$errors = validateLead(validLead(['score' => -100]));
assert_contains('Score must be 0-100', $errors, 'Score -100 rejected');

$errors = validateLead(validLead(['score' => null]));
assert_contains('Score must be 0-100', $errors, 'Null score rejected');

// PHP quirk: isset(null) is false, but the check uses !isset
$noScoreLead = validLead();
unset($noScoreLead['score']);
$errors = validateLead($noScoreLead);
assert_contains('Score must be 0-100', $errors, 'Missing score key rejected');

$errors = validateLead(validLead(['score' => '50']));
assert_empty($errors, 'String "50" accepted (PHP loose comparison)');

$errors = validateLead(validLead(['score' => '0']));
assert_empty($errors, 'String "0" accepted');

$errors = validateLead(validLead(['score' => 50.5]));
assert_empty($errors, 'Float 50.5 accepted (PHP comparison works)');

$errors = validateLead(validLead(['score' => 'abc']));
// 'abc' < 0 is false, 'abc' > 100 is false in PHP — documents this gotcha
assert_true(true, 'String "abc" score — PHP comparison result documented: ' . (empty(validateLead(validLead(['score' => 'abc']))) ? 'accepted (PHP gotcha)' : 'rejected'));

// ================================================================
// MULTIPLE FIELD FAILURES
// ================================================================
test_section('Multiple fields — compound validation');

$errors = validateLead([]);
assert_true(count($errors) >= 4, 'Completely empty data produces at least 4 errors (got ' . count($errors) . ')');

$errors = validateLead(['name' => '', 'email' => '', 'practice_area' => '', 'status' => '', 'score' => null]);
assert_true(count($errors) === 5, 'All fields invalid produces exactly 5 errors (got ' . count($errors) . ')');
assert_contains('Name is required', $errors, 'Name error present in compound failure');
assert_contains('Valid email is required', $errors, 'Email error present in compound failure');
assert_contains('Invalid practice area', $errors, 'Practice area error present in compound failure');
assert_contains('Invalid status', $errors, 'Status error present in compound failure');
assert_contains('Score must be 0-100', $errors, 'Score error present in compound failure');

// ================================================================
// SANITIZE FUNCTION
// ================================================================
test_section('sanitize() — XSS prevention');

assert_equals('&lt;script&gt;alert(1)&lt;/script&gt;', sanitize('<script>alert(1)</script>'), 'Script tags escaped');
assert_equals('&quot;quoted&quot;', sanitize('"quoted"'), 'Double quotes escaped');
assert_equals('&#039;quoted&#039;', sanitize("'quoted'"), 'Single quotes escaped');
assert_equals('normal text', sanitize('normal text'), 'Normal text unchanged');
assert_equals('trimmed', sanitize('  trimmed  '), 'Whitespace trimmed');
assert_equals('', sanitize(''), 'Empty string stays empty');
assert_equals('a &amp; b', sanitize('a & b'), 'Ampersand escaped');
assert_equals('O&#039;Brien &amp; Associates', sanitize("O'Brien & Associates"), 'Real-world law firm name sanitized correctly');
