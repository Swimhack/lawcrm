<?php
/**
 * Minimal test runner — no dependencies required.
 * Usage: php tests/runner.php
 */

$passed = 0;
$failed = 0;
$errors = [];

function assert_true($condition, $label) {
    global $passed, $failed, $errors;
    if ($condition) {
        $passed++;
        echo "  PASS  $label\n";
    } else {
        $failed++;
        $errors[] = $label;
        echo "  FAIL  $label\n";
    }
}

function assert_false($condition, $label) {
    assert_true(!$condition, $label);
}

function assert_equals($expected, $actual, $label) {
    global $passed, $failed, $errors;
    if ($expected === $actual) {
        $passed++;
        echo "  PASS  $label\n";
    } else {
        $failed++;
        $msg = "$label — expected " . var_export($expected, true) . ", got " . var_export($actual, true);
        $errors[] = $msg;
        echo "  FAIL  $msg\n";
    }
}

function assert_contains($needle, $haystack, $label) {
    assert_true(in_array($needle, $haystack), "$label — expected '$needle' in [" . implode(', ', $haystack) . "]");
}

function assert_empty($value, $label) {
    assert_true(empty($value), "$label — expected empty, got " . var_export($value, true));
}

function assert_not_empty($value, $label) {
    assert_true(!empty($value), $label);
}

function test_section($name) {
    echo "\n=== $name ===\n";
}

// Run all test files
$testFiles = glob(__DIR__ . '/test_*.php');
sort($testFiles);

echo "Law CRM Test Suite\n";
echo str_repeat('=', 60) . "\n";

foreach ($testFiles as $file) {
    require $file;
}

echo "\n" . str_repeat('=', 60) . "\n";
echo "Results: $passed passed, $failed failed\n";

if ($failed > 0) {
    echo "\nFailures:\n";
    foreach ($errors as $e) {
        echo "  - $e\n";
    }
    exit(1);
}

echo "\nAll tests passed.\n";
exit(0);
