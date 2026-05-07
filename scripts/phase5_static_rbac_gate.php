<?php
/**
 * Phase 5 CI-style static RBAC gate.
 *
 * Flags missing admin page bootstraps, mutation guard markers, secured API
 * denial contracts, and missing RBAC denial telemetry hooks.
 */

$root = realpath(__DIR__ . '/..');
if ($root === false) {
    fwrite(STDERR, "Unable to resolve project root.\n");
    exit(1);
}

function read_required(string $root, string $relativePath): string
{
    $path = $root . '/' . $relativePath;
    if (!is_file($path)) {
        throw new RuntimeException("Missing file: {$relativePath}");
    }

    $contents = file_get_contents($path);
    if ($contents === false) {
        throw new RuntimeException("Unable to read file: {$relativePath}");
    }

    return $contents;
}

$failures = [];

foreach (glob($root . '/admin/pages/*.php') ?: [] as $pagePath) {
    $relativePath = str_replace('\\', '/', substr($pagePath, strlen($root) + 1));
    $text = (string)file_get_contents($pagePath);
    if (strpos($text, 'admin_auth.php') === false) {
        $failures[] = "{$relativePath}: missing admin_auth.php bootstrap.";
    }
}

$adminAuth = read_required($root, 'admin/partials/admin_auth.php');
$pagePermissionMarkers = [
    "'add-resident.php' => ['add_residents']",
    "'edit-resident.php' => ['edit_resident_profile']",
    "'monitoring-of-request.php' => ['view_monitoring_requests']",
    "'incident-reports.php' => ['manage_incidents']",
    "'announcements.php' => ['manage_announcements']",
    "'events.php' => ['manage_events']",
    "'add-user.php' => ['user_management']",
    "'logs.php' => ['view_logs']",
];

foreach ($pagePermissionMarkers as $marker) {
    if (strpos($adminAuth, $marker) === false) {
        $failures[] = "admin/partials/admin_auth.php: missing page permission marker {$marker}.";
    }
}

$mutatingHandlers = [
    'admin/partials/add-user-handler.php',
    'admin/partials/add-resident-handler.php',
    'admin/partials/delete-resident-handler.php',
    'admin/partials/delete-user-handler.php',
    'admin/partials/event-handler.php',
    'admin/partials/business-application-handler.php',
    'admin/partials/new-business-permit-handler.php',
    'admin/partials/new-barangay-clearance-handler.php',
    'admin/partials/new-certificate-of-indigency-handler.php',
    'admin/partials/new-certificate-of-residency-handler.php',
    'admin/partials/update-document-request-status.php',
    'admin/partials/update-business-status.php',
    'admin/partials/make-cash-payment.php',
    'admin/partials/update-payment-info.php',
    'admin/partials/update-incident-status-ajax.php',
    'admin/partials/update-incident-remarks.php',
    'admin/partials/bulk-action-requests.php',
    'admin/partials/cancel-request.php',
    'admin/partials/save-admin-notes.php',
    'admin/partials/regenerate-qr-token.php',
];

foreach ($mutatingHandlers as $handler) {
    $text = read_required($root, $handler);
    if (!preg_match('/require_login\s*\(/', $text)) {
        $failures[] = "{$handler}: missing require_login().";
    }
    if (!preg_match('/csrf_(validate|require)\s*\(/', $text)) {
        $failures[] = "{$handler}: missing CSRF guard.";
    }
    if (!preg_match('/require_(any_)?permission(_or_(json|redirect))?\s*\(/', $text)) {
        $failures[] = "{$handler}: missing permission guard.";
    }
    if (strpos($text, 'is_admin_or_official(') !== false) {
        $failures[] = "{$handler}: broad is_admin_or_official() authorization shortcut found.";
    }
}

$publicApis = [
    'api/health.php',
    'api/ready.php',
    'api/csp-report.php',
];

foreach (glob($root . '/api/*.php') ?: [] as $apiPath) {
    $relativePath = str_replace('\\', '/', substr($apiPath, strlen($root) + 1));
    if (in_array($relativePath, $publicApis, true)) {
        continue;
    }

    $text = (string)file_get_contents($apiPath);
    if (preg_match('/require_role\s*\(/', $text)) {
        $failures[] = "{$relativePath}: uses require_role() instead of permission/API-specific guard.";
    }
    if (strpos($text, 'required_permission') === false) {
        $failures[] = "{$relativePath}: missing normalized required_permission JSON denial contract.";
    }
}

$permissionChecker = read_required($root, 'includes/permission_checker.php');
$denyTelemetryMarkers = [
    "log_rbac_warning('permission_denied_redirect'",
    "log_rbac_warning('all_permissions_denied_redirect'",
    "log_rbac_warning('permission_denied_json'",
    "log_rbac_warning('all_permissions_denied_json'",
];

foreach ($denyTelemetryMarkers as $marker) {
    if (strpos($permissionChecker, $marker) === false) {
        $failures[] = "includes/permission_checker.php: missing deny telemetry marker {$marker}.";
    }
}

if (!empty($failures)) {
    echo "Phase 5 static RBAC gate: FAILED\n";
    foreach ($failures as $failure) {
        echo " - {$failure}\n";
    }
    exit(1);
}

echo "Phase 5 static RBAC gate: PASSED\n";
echo "Admin pages checked: " . count(glob($root . '/admin/pages/*.php') ?: []) . "\n";
echo "Mutating handlers checked: " . count($mutatingHandlers) . "\n";
echo "API endpoints checked: " . (count(glob($root . '/api/*.php') ?: []) - count($publicApis)) . "\n";
