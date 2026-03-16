<?php
/**
 * Permission Checker Helper Functions
 * Provides easy-to-use functions for checking user permissions
 */

require_once __DIR__ . '/../config/permissions.php';

/**
 * Check if user can access a specific permission
 * @param string $permission The permission to check
 * @param string|null $user_role Optional role to check. If null, uses session role.
 * @return bool True if user can access, false otherwise
 */
function require_permission($permission, $user_role = null) {
    if ($user_role === null) {
        if (!isset($_SESSION['role'])) {
            return false;
        }
        $user_role = $_SESSION['role'];
    }
    
    return can_access($user_role, $permission);
}

/**
 * Check if user can access a specific permission (alias for require_permission)
 * @param string $permission The permission to check
 * @param string|null $user_role Optional role to check. If null, uses session role.
 * @return bool True if user can access, false otherwise
 */
function can_access_permission($permission, $user_role = null) {
    return require_permission($permission, $user_role);
}

/**
 * Get user's role display name
 * @param string|null $role Optional role to get display name for. If null, uses session role.
 * @return string Human-readable role name
 */
function get_role_display_name($role = null) {
    if ($role === null) {
        if (!isset($_SESSION['role'])) {
            return 'Unknown';
        }
        $role = $_SESSION['role'];
    }
    
    $role_names = [
        'admin' => 'Administrator',
        'barangay-captain' => 'Barangay Captain',
        'kagawad' => 'Barangay Kagawad',
        'barangay-secretary' => 'Barangay Secretary',
        'barangay-treasurer' => 'Barangay Treasurer',
        'barangay-tanod' => 'Barangay Tanod',
        'resident' => 'Resident'
    ];
    
    return $role_names[$role] ?? ucfirst(str_replace('-', ' ', $role));
}

/**
 * Check if user can override another role's decision
 * @param string $target_role The role to check if current user can override
 * @return bool True if user can override, false otherwise
 */
function can_override_role($target_role) {
    if (!isset($_SESSION['role'])) {
        return false;
    }
    
    return check_role_override($_SESSION['role'], $target_role);
}
?>
