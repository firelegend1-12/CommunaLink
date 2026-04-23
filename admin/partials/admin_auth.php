<?php
/**
 * Admin Authentication & Access Control
 * Included at the very top of admin pages to enforce permissions before any HTML output
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/permission_checker.php';

// First, ensure they are logged in at all
require_login();

// Define authorized roles for the admin section
$authorized_roles = [
    'admin',
    'barangay-officials',
    'barangay-kagawad',
    'barangay-tanod'
];

// Check if user has an authorized role
if (!isset($_SESSION['role']) || !in_array($_SESSION['role'], $authorized_roles)) {
    if (isset($_SESSION['role']) && $_SESSION['role'] === 'resident') {
        header('Location: ' . app_url('/resident/dashboard.php'));
    } else {
        header('Location: ' . app_url('/index.php'));
    }
    exit;
}

// Phase 2 page-level permission map for admin UI entry points.
$admin_page_permissions = [
    'add-resident.php' => ['add_residents'],
    'edit-resident.php' => ['edit_resident_profile'],
    'resident-id.php' => ['edit_resident_profile'],
    'residents.php' => ['view_residents'],
    'monitoring-of-request.php' => ['view_monitoring_requests'],
    'incident-reports.php' => ['manage_incidents'],
    'update-incident.php' => ['manage_incidents'],
    'maps.php' => ['manage_incidents'],
    'announcements.php' => ['manage_announcements'],
    'events.php' => ['manage_events'],
    'new-barangay-clearance.php' => ['manage_documents'],
    'new-certificate-of-indigency.php' => ['manage_documents'],
    'new-certificate-of-residency.php' => ['manage_documents'],
    'barangay-clearance-template.php' => ['manage_documents'],
    'certificate-of-indigency-template.php' => ['manage_documents'],
    'certificate-of-indigency-special-template.php' => ['manage_documents'],
    'certificate-of-residency-template.php' => ['manage_documents'],
    'new-barangay-business-clearance.php' => ['manage_documents'],
    'business-application-form.php' => ['manage_businesses'],
    'business-clearance.php' => ['manage_businesses'],
    'business-clearance-template.php' => ['manage_businesses'],
    'business-permit-application-template.php' => ['manage_businesses'],
    'generate-business-permit.php' => ['manage_businesses'],
    'add-user.php' => ['user_management'],
    'edit-user.php' => ['user_management'],
    'user-management.php' => ['user_management'],
    'logs.php' => ['view_logs']
];

$current_page = basename($_SERVER['PHP_SELF'] ?? '');
$required_permissions = $admin_page_permissions[$current_page] ?? [];
if (!empty($required_permissions)) {
    require_any_permission_for_admin_page($required_permissions);
}
?>
