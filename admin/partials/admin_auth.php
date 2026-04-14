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
$authorized_roles = ['admin', 'barangay-captain', 'kagawad', 'barangay-secretary', 'barangay-treasurer', 'barangay-tanod'];

// Check if user has an authorized role
if (!isset($_SESSION['role']) || !in_array($_SESSION['role'], $authorized_roles)) {
    if (isset($_SESSION['role']) && $_SESSION['role'] === 'resident') {
        header('Location: ' . app_url('/resident/dashboard.php'));
    } else {
        header('Location: ' . app_url('/index.php'));
    }
    exit;
}
?>
