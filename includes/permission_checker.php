<?php
/**
 * Permission Checker Helper Functions
 * Provides easy-to-use functions for checking user permissions
 */

require_once __DIR__ . '/../config/permissions.php';

/**
 * Write warning-level RBAC telemetry and mirror to activity logs when available.
 *
 * @param string $event
 * @param array $context
 * @return void
 */
function log_rbac_warning($event, array $context = []) {
    $context_json = json_encode($context);
    error_log('RBAC_WARNING [' . $event . '] ' . ($context_json !== false ? $context_json : '{}'));

    global $pdo;
    if (!isset($pdo) || !($pdo instanceof PDO) || !function_exists('log_activity_db_system')) {
        return;
    }

    try {
        $user_id = isset($_SESSION['user_id']) ? (int) $_SESSION['user_id'] : null;
        $username = (string) ($_SESSION['username'] ?? ($_SESSION['fullname'] ?? 'unknown'));
        $details = 'RBAC warning [' . $event . ']: ' . ($context_json !== false ? $context_json : '{}');

        log_activity_db_system(
            $pdo,
            'deny',
            'authorization',
            null,
            $details,
            null,
            null,
            'warning',
            uniqid('req_', true),
            $user_id,
            $username,
            function_exists('session_id') ? session_id() : null,
            $_SERVER['HTTP_USER_AGENT'] ?? null,
            $_SERVER['REMOTE_ADDR'] ?? null
        );
    } catch (Throwable $e) {
        error_log('RBAC_WARNING [log_mirror_failed] ' . $e->getMessage());
    }
}

/**
 * Resolve the role to use for permission checks.
 *
 * @param string|null $user_role Optional explicit role.
 * @return string|null Normalized role key or null if unavailable.
 */
function resolve_permission_role($user_role = null) {
    if ($user_role === null) {
        if (!isset($_SESSION['role'])) {
            return null;
        }
        $user_role = $_SESSION['role'];
    }

    $normalized_role = normalize_rbac_key($user_role);

    if ($normalized_role === 'official') {
        // Strict RBAC policy: legacy umbrella role is blocked in permission guards.
        return null;
    }

    return $normalized_role === '' ? null : $normalized_role;
}

/**
 * Compute a safe redirect path for denied admin-page access.
 * Residents go to resident dashboard; non-resident roles go to admin dashboard.
 *
 * @param string|null $user_role
 * @return string
 */
function get_rbac_page_redirect_path($user_role = null) {
    $raw_role = $user_role;
    if ($raw_role === null) {
        $raw_role = $_SESSION['role'] ?? null;
    }

    $normalized_role = normalize_rbac_key($raw_role);
    $current_parent_dir = basename(dirname($_SERVER['PHP_SELF'] ?? ''));
    $is_pages_context = ($current_parent_dir === 'pages');

    if ($normalized_role === 'resident') {
        return $is_pages_context ? '../../resident/dashboard.php' : '../resident/dashboard.php';
    }

    return $is_pages_context ? '../index.php' : './index.php';
}

/**
 * Enforce a single permission for admin-page access with role-aware redirect.
 *
 * @param string $permission
 * @param string|null $user_role
 * @return bool
 */
function require_permission_for_admin_page($permission, $user_role = null) {
    return require_permission_or_redirect($permission, get_rbac_page_redirect_path($user_role), $user_role);
}

/**
 * Enforce any permission in a list for admin-page access with role-aware redirect.
 *
 * @param array $permissions
 * @param string|null $user_role
 * @return bool
 */
function require_any_permission_for_admin_page(array $permissions, $user_role = null) {
    return require_any_permission_or_redirect($permissions, get_rbac_page_redirect_path($user_role), $user_role);
}

/**
 * Check if any permission in the provided list is allowed for the role context.
 *
 * @param array $permissions
 * @param string|null $user_role
 * @return bool
 */
function require_any_permission(array $permissions, $user_role = null) {
    $resolved_role = resolve_permission_role($user_role);
    if ($resolved_role === null || !rbac_is_known_role($resolved_role)) {
        return false;
    }

    foreach ($permissions as $permission) {
        $permission_key = normalize_rbac_key($permission);
        if ($permission_key === '' || !rbac_is_known_permission($permission_key)) {
            continue;
        }

        if (can_access($resolved_role, $permission_key)) {
            return true;
        }
    }

    return false;
}

/**
 * Check if user can access a specific permission
 * @param string $permission The permission to check
 * @param string|null $user_role Optional role to check. If null, uses session role.
 * @return bool True if user can access, false otherwise
 */
function require_permission($permission, $user_role = null) {
    $permission_key = normalize_rbac_key($permission);
    $resolved_role = resolve_permission_role($user_role);

    if ($permission_key === '' || $resolved_role === null) {
        log_rbac_warning('invalid_permission_context', [
            'permission' => $permission_key,
            'resolved_role' => $resolved_role,
        ]);
        return false;
    }

    if (!rbac_is_known_role($resolved_role) || !rbac_is_known_permission($permission_key)) {
        log_rbac_warning('unknown_role_or_permission', [
            'permission' => $permission_key,
            'role' => $resolved_role,
            'role_known' => rbac_is_known_role($resolved_role),
            'permission_known' => rbac_is_known_permission($permission_key),
        ]);
        return false;
    }

    return can_access($resolved_role, $permission_key);
}

/**
 * Enforce permission and redirect when denied.
 *
 * @param string $permission
 * @param string $redirect_path
 * @param string|null $user_role
 * @return bool True when access is allowed
 */
function require_permission_or_redirect($permission, $redirect_path = '../index.php', $user_role = null) {
    if (require_permission($permission, $user_role)) {
        return true;
    }

    log_rbac_warning('permission_denied_redirect', [
        'permission' => normalize_rbac_key($permission),
        'role' => resolve_permission_role($user_role),
        'redirect_path' => $redirect_path,
    ]);

    if (!headers_sent()) {
        header('Location: ' . $redirect_path);
    }
    exit;
}

/**
 * Enforce any permission in a list and redirect when denied.
 *
 * @param array $permissions
 * @param string $redirect_path
 * @param string|null $user_role
 * @return bool True when access is allowed
 */
function require_any_permission_or_redirect(array $permissions, $redirect_path = '../index.php', $user_role = null) {
    if (require_any_permission($permissions, $user_role)) {
        return true;
    }

    $normalized_permissions = [];
    foreach ($permissions as $permission) {
        $key = normalize_rbac_key($permission);
        if ($key !== '') {
            $normalized_permissions[] = $key;
        }
    }

    log_rbac_warning('all_permissions_denied_redirect', [
        'permissions' => $normalized_permissions,
        'role' => resolve_permission_role($user_role),
        'redirect_path' => $redirect_path,
    ]);

    if (!headers_sent()) {
        header('Location: ' . $redirect_path);
    }
    exit;
}

/**
 * Enforce permission and emit JSON 403-style response when denied.
 *
 * @param string $permission
 * @param int $status_code
 * @param string $error_message
 * @param string|null $user_role
 * @return bool True when access is allowed
 */
function require_permission_or_json($permission, $status_code = 403, $error_message = 'Forbidden', $user_role = null) {
    if (require_permission($permission, $user_role)) {
        return true;
    }

    $permission_key = normalize_rbac_key($permission);
    log_rbac_warning('permission_denied_json', [
        'permission' => $permission_key,
        'role' => resolve_permission_role($user_role),
        'status_code' => (int) $status_code,
    ]);

    if (!headers_sent()) {
        http_response_code((int) $status_code);
        header('Content-Type: application/json');
    }

    echo json_encode([
        'success' => false,
        'error' => $error_message,
        'required_permission' => $permission_key,
    ]);
    exit;
}

/**
 * Enforce any permission in a list and emit JSON response when denied.
 *
 * @param array $permissions
 * @param int $status_code
 * @param string $error_message
 * @param string|null $user_role
 * @return bool True when access is allowed
 */
function require_any_permission_or_json(array $permissions, $status_code = 403, $error_message = 'Forbidden', $user_role = null) {
    if (require_any_permission($permissions, $user_role)) {
        return true;
    }

    $normalized_permissions = [];
    foreach ($permissions as $permission) {
        $permission_key = normalize_rbac_key($permission);
        if ($permission_key !== '') {
            $normalized_permissions[] = $permission_key;
        }
    }

    log_rbac_warning('all_permissions_denied_json', [
        'permissions' => $normalized_permissions,
        'role' => resolve_permission_role($user_role),
        'status_code' => (int) $status_code,
    ]);

    if (!headers_sent()) {
        http_response_code((int) $status_code);
        header('Content-Type: application/json');
    }

    echo json_encode([
        'success' => false,
        'error' => $error_message,
        'required_permission' => implode('|', $normalized_permissions),
    ]);
    exit;
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
        'barangay-officials' => 'Barangay Officials',
        'barangay-kagawad' => 'Barangay Kagawad',
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
