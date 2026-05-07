<?php
/**
 * Phase 5 table-driven RBAC authorization matrix.
 *
 * This is intentionally runtime-light: it verifies the permission matrix that
 * admin pages, handlers, and secured APIs now share. Expected statuses model an
 * authenticated caller; unauthenticated 401/302 behavior is covered by the
 * endpoint guards and smoke checks.
 */

require_once __DIR__ . '/../config/permissions.php';

$roles = [
    'admin',
    'barangay-officials',
    'barangay-kagawad',
    'barangay-tanod',
    'resident',
    'official',
    'unknown_role_key',
];

$surfaceCases = [
    [
        'surface' => 'admin/pages/add-user.php',
        'permissions' => ['user_management'],
        'expected' => ['admin' => 200],
    ],
    [
        'surface' => 'admin/pages/add-resident.php',
        'permissions' => ['add_residents'],
        'expected' => ['admin' => 200, 'barangay-officials' => 200, 'barangay-kagawad' => 200],
    ],
    [
        'surface' => 'admin/pages/edit-resident.php',
        'permissions' => ['edit_resident_profile'],
        'expected' => ['admin' => 200],
    ],
    [
        'surface' => 'admin/pages/monitoring-of-request.php',
        'permissions' => ['view_monitoring_requests'],
        'expected' => ['admin' => 200, 'barangay-officials' => 200, 'barangay-kagawad' => 200],
    ],
    [
        'surface' => 'admin/pages/incident-reports.php',
        'permissions' => ['manage_incidents'],
        'expected' => ['admin' => 200, 'barangay-officials' => 200, 'barangay-kagawad' => 200, 'barangay-tanod' => 200],
    ],
    [
        'surface' => 'admin/pages/announcements.php',
        'permissions' => ['manage_announcements'],
        'expected' => ['admin' => 200, 'barangay-officials' => 200],
    ],
    [
        'surface' => 'admin/pages/events.php',
        'permissions' => ['manage_events'],
        'expected' => ['admin' => 200, 'barangay-officials' => 200],
    ],
    [
        'surface' => 'admin/document-handlers',
        'permissions' => ['manage_documents'],
        'expected' => ['admin' => 200, 'barangay-officials' => 200, 'barangay-kagawad' => 200],
    ],
    [
        'surface' => 'admin/business-handlers',
        'permissions' => ['manage_businesses'],
        'expected' => ['admin' => 200, 'barangay-officials' => 200, 'barangay-kagawad' => 200],
    ],
    [
        'surface' => 'admin/payment-handlers',
        'permissions' => ['financial_management'],
        'expected' => ['admin' => 200, 'barangay-officials' => 200],
    ],
    [
        'surface' => 'admin/logs.php',
        'permissions' => ['view_logs'],
        'expected' => ['admin' => 200],
    ],
    [
        'surface' => 'api/incidents.php?action=report_incident',
        'permissions' => ['report_incidents'],
        'expected' => ['resident' => 200],
    ],
    [
        'surface' => 'api/incidents.php?action=get_all_reports',
        'permissions' => ['manage_incidents'],
        'expected' => ['admin' => 200, 'barangay-officials' => 200, 'barangay-kagawad' => 200, 'barangay-tanod' => 200],
    ],
    [
        'surface' => 'api/notifications.php?action=get_admin_sidebar_counts',
        'permissions' => ['manage_incidents', 'manage_events'],
        'expected' => ['admin' => 200, 'barangay-officials' => 200, 'barangay-kagawad' => 200, 'barangay-tanod' => 200],
    ],
    [
        'surface' => 'api/notifications.php?action=get_business_counts',
        'permissions' => ['manage_businesses'],
        'expected' => ['admin' => 200, 'barangay-officials' => 200, 'barangay-kagawad' => 200],
    ],
    [
        'surface' => 'api/post-reactions.php',
        'permissions' => ['view_announcements'],
        'expected' => ['resident' => 200],
    ],
];

$failures = [];
$total = 0;

foreach ($surfaceCases as $case) {
    foreach ($roles as $role) {
        $total++;
        $allowed = false;
        foreach ($case['permissions'] as $permission) {
            if (can_access($role, $permission)) {
                $allowed = true;
                break;
            }
        }

        $actualStatus = $allowed ? 200 : 403;
        $expectedStatus = (int)($case['expected'][$role] ?? 403);

        if ($actualStatus !== $expectedStatus) {
            $failures[] = sprintf(
                'surface=%s role=%s expected=%d actual=%d permissions=%s',
                $case['surface'],
                $role,
                $expectedStatus,
                $actualStatus,
                implode('|', $case['permissions'])
            );
            continue;
        }

        echo sprintf(
            "PASS surface=%s role=%s status=%d\n",
            $case['surface'],
            $role,
            $actualStatus
        );
    }
}

echo "\nSummary:\n";
echo 'Total matrix checks: ' . $total . "\n";
echo 'Failures: ' . count($failures) . "\n";

if (!empty($failures)) {
    echo "\nFailure details:\n";
    foreach ($failures as $failure) {
        echo 'FAIL ' . $failure . "\n";
    }
    exit(1);
}

echo "Phase 5 authorization matrix passed.\n";
