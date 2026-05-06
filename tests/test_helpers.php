<?php
/**
 * Test helper utilities for CommunaLink bugfix tests.
 * Provides header capture, assertion helpers, and test runner.
 */

// ─── Header capture harness ──────────────────────────────────────────────────

/**
 * Captured headers from redirect_to() calls (populated by the mock).
 * @var array<string>
 */
$GLOBALS['_test_captured_headers'] = [];

/**
 * Whether the test harness has intercepted an exit() call.
 * @var bool
 */
$GLOBALS['_test_exit_called'] = false;

/**
 * Override header() so tests can capture Location values without sending real HTTP headers.
 * This file must be included BEFORE functions.php is loaded.
 */
if (!function_exists('header')) {
    // In CLI mode header() is a no-op, so we need a different approach.
    // We'll use output buffering and a custom redirect_to wrapper instead.
}

// ─── Simple assertion helpers ─────────────────────────────────────────────────

$GLOBALS['_test_pass_count'] = 0;
$GLOBALS['_test_fail_count'] = 0;
$GLOBALS['_test_failures']   = [];

function assert_equals($expected, $actual, string $message = ''): void {
    if ($expected === $actual) {
        $GLOBALS['_test_pass_count']++;
        echo "  ✓ PASS" . ($message ? ": $message" : '') . "\n";
    } else {
        $GLOBALS['_test_fail_count']++;
        $label = $message ?: 'Assertion failed';
        $GLOBALS['_test_failures'][] = $label;
        echo "  ✗ FAIL: $label\n";
        echo "    Expected : " . var_export($expected, true) . "\n";
        echo "    Actual   : " . var_export($actual, true) . "\n";
    }
}

function assert_not_equals($unexpected, $actual, string $message = ''): void {
    if ($unexpected !== $actual) {
        $GLOBALS['_test_pass_count']++;
        echo "  ✓ PASS" . ($message ? ": $message" : '') . "\n";
    } else {
        $GLOBALS['_test_fail_count']++;
        $label = $message ?: 'Values should differ';
        $GLOBALS['_test_failures'][] = $label;
        echo "  ✗ FAIL: $label\n";
        echo "    Unexpected value: " . var_export($unexpected, true) . "\n";
    }
}

function assert_contains(string $needle, string $haystack, string $message = ''): void {
    if (strpos($haystack, $needle) !== false) {
        $GLOBALS['_test_pass_count']++;
        echo "  ✓ PASS" . ($message ? ": $message" : '') . "\n";
    } else {
        $GLOBALS['_test_fail_count']++;
        $label = $message ?: "String should contain '$needle'";
        $GLOBALS['_test_failures'][] = $label;
        echo "  ✗ FAIL: $label\n";
        echo "    Haystack: " . var_export($haystack, true) . "\n";
        echo "    Needle  : " . var_export($needle, true) . "\n";
    }
}

function assert_not_empty($value, string $message = ''): void {
    if (!empty($value)) {
        $GLOBALS['_test_pass_count']++;
        echo "  ✓ PASS" . ($message ? ": $message" : '') . "\n";
    } else {
        $GLOBALS['_test_fail_count']++;
        $label = $message ?: 'Value should not be empty';
        $GLOBALS['_test_failures'][] = $label;
        echo "  ✗ FAIL: $label\n";
        echo "    Value: " . var_export($value, true) . "\n";
    }
}

function assert_true(bool $condition, string $message = ''): void {
    assert_equals(true, $condition, $message);
}

function assert_false(bool $condition, string $message = ''): void {
    assert_equals(false, $condition, $message);
}

function test_summary(): void {
    $total = $GLOBALS['_test_pass_count'] + $GLOBALS['_test_fail_count'];
    echo "\n" . str_repeat('─', 60) . "\n";
    echo "Results: {$GLOBALS['_test_pass_count']} passed, {$GLOBALS['_test_fail_count']} failed (total: $total)\n";
    if (!empty($GLOBALS['_test_failures'])) {
        echo "\nFailed assertions:\n";
        foreach ($GLOBALS['_test_failures'] as $f) {
            echo "  • $f\n";
        }
    }
    echo str_repeat('─', 60) . "\n";
}

function test_section(string $title): void {
    echo "\n" . str_repeat('═', 60) . "\n";
    echo "  $title\n";
    echo str_repeat('═', 60) . "\n";
}

function test_case(string $name): void {
    echo "\n▶ $name\n";
}
