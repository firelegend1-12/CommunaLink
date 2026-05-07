<?php
/**
 * Phase 3/4 guard regression check.
 *
 * Verifies the RBAC roadmap invariants for admin partial handlers and API files:
 * - no broad is_admin_or_official() authorization shortcut in admin partials
 * - selected mutating admin handlers have auth, permission, method, and CSRF guards
 * - secured API files avoid require_role() and use normalized JSON denial helpers
 */

$root = realpath(__DIR__ . '/..');
if ($root === false) {
    fwrite(STDERR, "Unable to resolve project root.\n");
    exit(1);
}

function read_file_checked(string $path): string
{
    $contents = file_get_contents($path);
    if ($contents === false) {
        throw new RuntimeException("Unable to read {$path}");
    }

    return $contents;
}

function rel(string $root, string $path): string
{
    return str_replace('\\', '/', ltrim(substr($path, strlen($root)), DIRECTORY_SEPARATOR));
}

$failures = [];

$adminPartials = glob($root . '/admin/partials/*.php') ?: [];
foreach ($adminPartials as $file) {
    $text = read_file_checked($file);
    if (strpos($text, 'is_admin_or_official(') !== false) {
        $failures[] = rel($root, $file) . ': uses broad is_admin_or_official() in admin partial.';
    }
}

$mutatingHandlers = [
    'admin/partials/add-user-handler.php' => 'user_management',
    'admin/partials/add-resident-handler.php' => 'add_residents',
    'admin/partials/delete-resident-handler.php' => 'manage_residents',
    'admin/partials/delete-user-handler.php' => 'delete_users',
    'admin/partials/event-handler.php' => 'manage_events',
    'admin/partials/business-application-handler.php' => 'manage_businesses',
    'admin/partials/new-business-permit-handler.php' => 'manage_businesses',
    'admin/partials/new-barangay-clearance-handler.php' => 'manage_documents',
    'admin/partials/new-certificate-of-indigency-handler.php' => 'manage_documents',
    'admin/partials/new-certificate-of-residency-handler.php' => 'manage_documents',
    'admin/partials/update-document-request-status.php' => 'manage_documents',
    'admin/partials/update-business-status.php' => 'manage_businesses',
    'admin/partials/make-cash-payment.php' => 'financial_management',
    'admin/partials/update-payment-info.php' => 'financial_management',
    'admin/partials/update-incident-status-ajax.php' => 'manage_incidents',
    'admin/partials/update-incident-remarks.php' => 'manage_incidents',
    'admin/partials/bulk-action-requests.php' => 'dynamic_permission_check',
    'admin/partials/cancel-request.php' => 'dynamic_permission_check',
    'admin/partials/save-admin-notes.php' => 'manage_documents',
    'admin/partials/regenerate-qr-token.php' => 'edit_resident_profile',
];

foreach ($mutatingHandlers as $relativePath => $permission) {
    $file = $root . '/' . $relativePath;
    if (!is_file($file)) {
        $failures[] = "{$relativePath}: file missing.";
        continue;
    }

    $text = read_file_checked($file);
    if (!preg_match('/require_login\s*\(/', $text)) {
        $failures[] = "{$relativePath}: missing require_login().";
    }
    if (!preg_match('/REQUEST_METHOD[^\n]+POST|POST[^\n]+REQUEST_METHOD/s', $text)) {
        $failures[] = "{$relativePath}: missing POST method guard.";
    }
    if (!preg_match('/csrf_(validate|require)\s*\(/', $text)) {
        $failures[] = "{$relativePath}: missing CSRF guard.";
    }
    if ($permission === 'dynamic_permission_check') {
        if (!preg_match('/require_permission\s*\(/', $text) && !preg_match('/require_permission_or_json\s*\(/', $text)) {
            $failures[] = "{$relativePath}: missing dynamic permission checks.";
        }
    } elseif (strpos($text, $permission) === false) {
        $failures[] = "{$relativePath}: missing expected permission {$permission}.";
    }
}

$securedApis = [
    'api/incidents.php',
    'api/notifications.php',
    'api/post-reactions.php',
    'api/check-expiring-permits.php',
    'api/cleanup-active-sessions.php',
    'api/process-public-post-queue.php',
];

foreach ($securedApis as $relativePath) {
    $file = $root . '/' . $relativePath;
    if (!is_file($file)) {
        $failures[] = "{$relativePath}: file missing.";
        continue;
    }

    $text = read_file_checked($file);
    if (preg_match('/require_role\s*\(/', $text)) {
        $failures[] = "{$relativePath}: uses require_role() instead of permission/API-specific guard.";
    }
    if (!preg_match('/required_permission/', $text)) {
        $failures[] = "{$relativePath}: missing normalized required_permission denial contract.";
    }
}

if (!empty($failures)) {
    foreach ($failures as $failure) {
        echo "FAIL {$failure}\n";
    }
    echo "Phase 3/4 guard check failed: " . count($failures) . " issue(s).\n";
    exit(1);
}

echo "Phase 3/4 guard check passed.\n";
echo "Admin partials scanned: " . count($adminPartials) . "\n";
echo "Mutating handlers checked: " . count($mutatingHandlers) . "\n";
echo "Secured APIs checked: " . count($securedApis) . "\n";
