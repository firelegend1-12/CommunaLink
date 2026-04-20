<?php

/**
 * RBAC baseline verification script.
 *
 * Usage:
 *   D:\xampp\php\php.exe scripts/rbac_baseline_check.php
 */

require_once __DIR__ . '/../config/permissions.php';
require_once __DIR__ . '/../includes/permission_checker.php';

$failures = [];
$total = 0;

$cases = [
    ['role' => 'admin', 'permission' => 'manage_incidents', 'expected' => true],
    ['role' => 'admin', 'permission' => 'user_management', 'expected' => true],
    ['role' => 'barangay-officials', 'permission' => 'approve_applications', 'expected' => false],
    ['role' => 'barangay-officials', 'permission' => 'view_monitoring_requests', 'expected' => true],
    ['role' => 'barangay-officials', 'permission' => 'manage_businesses', 'expected' => true],
    ['role' => 'barangay-officials', 'permission' => 'manage_documents', 'expected' => true],
    ['role' => 'barangay-officials', 'permission' => 'financial_management', 'expected' => true],
    ['role' => 'barangay-officials', 'permission' => 'add_residents', 'expected' => true],
    ['role' => 'barangay-officials', 'permission' => 'edit_resident_profile', 'expected' => false],
    ['role' => 'barangay-kagawad', 'permission' => 'manage_documents', 'expected' => true],
    ['role' => 'barangay-kagawad', 'permission' => 'manage_businesses', 'expected' => true],
    ['role' => 'barangay-kagawad', 'permission' => 'view_monitoring_requests', 'expected' => true],
    ['role' => 'barangay-kagawad', 'permission' => 'view_residents', 'expected' => true],
    ['role' => 'barangay-kagawad', 'permission' => 'add_residents', 'expected' => true],
    ['role' => 'barangay-kagawad', 'permission' => 'edit_resident_profile', 'expected' => false],
    ['role' => 'barangay-kagawad', 'permission' => 'manage_announcements', 'expected' => false],
    ['role' => 'barangay-tanod', 'permission' => 'manage_incidents', 'expected' => true],
    ['role' => 'barangay-tanod', 'permission' => 'manage_documents', 'expected' => false],
    ['role' => 'barangay-tanod', 'permission' => 'add_residents', 'expected' => false],
    ['role' => 'resident', 'permission' => 'submit_applications', 'expected' => true],
    ['role' => 'resident', 'permission' => 'manage_announcements', 'expected' => false],
    ['role' => 'official', 'permission' => 'manage_incidents', 'expected' => false],
    ['role' => 'kagawad', 'permission' => 'manage_incidents', 'expected' => false],
    ['role' => 'barangay-captain', 'permission' => 'manage_incidents', 'expected' => false],
    ['role' => 'admin', 'permission' => 'unknown_permission_key', 'expected' => false],
    ['role' => 'unknown_role_key', 'permission' => 'manage_incidents', 'expected' => false],
];

foreach ($cases as $case) {
    $total++;
    $actual = require_permission($case['permission'], $case['role']);

    if ($actual !== $case['expected']) {
        $failures[] = sprintf(
            'FAIL role=%s permission=%s expected=%s actual=%s',
            $case['role'],
            $case['permission'],
            $case['expected'] ? 'true' : 'false',
            $actual ? 'true' : 'false'
        );
    } else {
        echo sprintf(
            "PASS role=%s permission=%s expected=%s\n",
            $case['role'],
            $case['permission'],
            $case['expected'] ? 'true' : 'false'
        );
    }
}

$total++;
$known_roles = get_rbac_supported_roles();
if (!in_array('admin', $known_roles, true) || !in_array('resident', $known_roles, true)) {
    $failures[] = 'FAIL expected canonical roles admin and resident to be present';
} else {
    echo "PASS canonical roles include admin and resident\n";
}

$total++;
if (!rbac_is_known_permission('manage_incidents')) {
    $failures[] = 'FAIL expected permission manage_incidents to be known';
} else {
    echo "PASS manage_incidents is known\n";
}

$total++;
if (rbac_is_known_permission('not_a_real_permission')) {
    $failures[] = 'FAIL expected not_a_real_permission to be unknown';
} else {
    echo "PASS unknown permission is denied\n";
}

echo "\nSummary:\n";
echo 'Total checks: ' . $total . "\n";
echo 'Failures: ' . count($failures) . "\n";

if (!empty($failures)) {
    echo "\nFailure details:\n";
    foreach ($failures as $failure) {
        echo $failure . "\n";
    }
    exit(1);
}

echo "RBAC baseline checks passed.\n";
exit(0);
