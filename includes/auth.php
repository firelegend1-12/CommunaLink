<?php
/**
 * Authentication System
 * Handles user authentication, sessions, and access control
 */

// Include necessary files
if (defined('AUTH_LIGHTWEIGHT_BOOTSTRAP') && AUTH_LIGHTWEIGHT_BOOTSTRAP === true) {
    require_once __DIR__ . '/../config/database.php';
    require_once __DIR__ . '/rate_limiter.php';

    if (!function_exists('configure_session_cookie_security')) {
        function configure_session_cookie_security() {
            if (session_status() !== PHP_SESSION_NONE) {
                return;
            }

            // Cloud environments might require a specific session save path (e.g., /tmp for App Engine)
            $session_path = '';
            if (function_exists('env')) {
                $session_path = env('SESSION_SAVE_PATH', '');
            } else {
                $session_path = getenv('SESSION_SAVE_PATH') ?: ($_ENV['SESSION_SAVE_PATH'] ?? $_SERVER['SESSION_SAVE_PATH'] ?? '');
            }
            
            if (!empty($session_path)) {
                if (!is_dir($session_path)) {
                    @mkdir($session_path, 0777, true);
                }
                session_save_path($session_path);
            }

            $is_https = (
                (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ||
                (isset($_SERVER['SERVER_PORT']) && (int) $_SERVER['SERVER_PORT'] === 443) ||
                (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && strpos(strtolower((string) $_SERVER['HTTP_X_FORWARDED_PROTO']), 'https') !== false)
            );

            ini_set('session.use_only_cookies', '1');
            ini_set('session.use_strict_mode', '1');
            ini_set('session.cookie_httponly', '1');
            ini_set('session.cookie_secure', $is_https ? '1' : '0');
            ini_set('session.cookie_samesite', 'Lax');

            session_set_cookie_params([
                'lifetime' => 0,
                'path' => '/',
                'secure' => $is_https,
                'httponly' => true,
                'samesite' => 'Lax'
            ]);
        }
    }
} else {
    require_once __DIR__ . '/../config/init.php'; // Includes DB and functions
}
require_once __DIR__ . '/functions.php';

// Fallback session start for direct execution paths that bypass init bootstrap.
if (session_status() === PHP_SESSION_NONE) {
    if (function_exists('configure_session_cookie_security')) {
        configure_session_cookie_security();
    }
    session_start();
}

function env_to_bool($value, $default = false) {
    if ($value === null || $value === false) {
        return $default;
    }

    $normalized = strtolower(trim((string) $value));
    if (in_array($normalized, ['1', 'true', 'yes', 'on'], true)) {
        return true;
    }
    if (in_array($normalized, ['0', 'false', 'no', 'off'], true)) {
        return false;
    }

    return $default;
}

function is_official_role_only($role) {
    return in_array($role, ['official', 'barangay-captain', 'kagawad', 'barangay-secretary', 'barangay-treasurer', 'barangay-tanod'], true);
}

function is_admin_role($role) {
    return $role === 'admin';
}

function is_session_policy_tracked_role($role) {
    // Policy decision: session concurrency and duplicate-login auto-kick apply only to admin/official roles.
    return is_admin_role($role) || is_official_role_only($role);
}

function is_concurrency_caps_enabled() {
    if (!function_exists('env')) {
        require_once __DIR__ . '/../config/env_loader.php';
    }

    return env_to_bool(env('ENABLE_CONCURRENCY_CAPS', 'true'), true);
}

function is_auto_kick_duplicate_sessions_enabled() {
    if (!function_exists('env')) {
        require_once __DIR__ . '/../config/env_loader.php';
    }

    return env_to_bool(env('AUTO_KICK_DUPLICATE_SESSIONS', 'false'), false);
}

function get_official_max_concurrent() {
    if (!function_exists('env')) {
        require_once __DIR__ . '/../config/env_loader.php';
    }

    $value = (int) env('OFFICIAL_MAX_CONCURRENT', 5);
    return max(1, min(100, $value));
}

function get_admin_max_concurrent() {
    if (!function_exists('env')) {
        require_once __DIR__ . '/../config/env_loader.php';
    }

    $value = (int) env('ADMIN_MAX_CONCURRENT', 2);
    return max(1, min(100, $value));
}

function clear_expired_active_sessions($pdo) {
    $stmt = $pdo->prepare("UPDATE active_user_sessions
                          SET is_active = 0,
                              ended_at = NOW(),
                              ended_reason = 'expired'
                          WHERE is_active = 1
                            AND expires_at IS NOT NULL
                            AND expires_at < NOW()");
    $stmt->execute();

    return (int) $stmt->rowCount();
}

function clear_expired_active_sessions_with_audit($pdo, $source = 'runtime') {
    $expired_count = clear_expired_active_sessions($pdo);
    if ($expired_count > 0) {
        log_session_event_row(
            $pdo,
            null,
            'system',
            'expire',
            sprintf('Expired active sessions cleanup (%s): %d row(s) marked inactive.', (string) $source, $expired_count),
            'warning',
            null
        );
    }

    return $expired_count;
}

function log_concurrency_denial($pdo, $user, $scope, $cap, $active_count) {
    try {
        $username = $user['username'] ?? 'unknown';
        $role = $user['role'] ?? 'unknown';
        $details = sprintf(
            'Concurrency admission denied (%s cap reached): role=%s active=%d cap=%d',
            $scope,
            $role,
            (int) $active_count,
            (int) $cap
        );

        log_activity_db_system(
            $pdo,
            'deny',
            'session',
            null,
            $details,
            null,
            null,
            'warning',
            uniqid('req_', true),
            $user['id'] ?? null,
            $username,
            null,
            $_SERVER['HTTP_USER_AGENT'] ?? null,
            $_SERVER['REMOTE_ADDR'] ?? null,
        );
    } catch (Throwable $e) {
        // Do not block authentication flow if logging fails.
    }
}

function log_session_event_row($pdo, $user_id, $username, $action, $details, $severity = 'warning', $session_id = null) {
    // Log session lifecycle events (login, logout, auto-kick, expiration, revocation).
    // Default severity is 'warning' for forced terminations; can be overridden to 'info' for voluntary logouts.
    try {
        log_activity_db_system(
            $pdo,
            $action,
            'session',
            null,
            $details,
            null,
            null,
            $severity,
            uniqid('req_', true),
            $user_id,
            $username ?: 'unknown',
            $session_id,
            $_SERVER['HTTP_USER_AGENT'] ?? null,
            $_SERVER['REMOTE_ADDR'] ?? null
        );
    } catch (Throwable $e) {
        // Do not block auth/session flow if logging fails.
    }
}

function kick_duplicate_sessions_for_user($pdo, $user, $current_session_id) {
    if (!is_auto_kick_duplicate_sessions_enabled()) {
        return 0;
    }

    $user_id = isset($user['id']) ? (int) $user['id'] : 0;
    if ($user_id <= 0) {
        return 0;
    }

    $select_stmt = $pdo->prepare("SELECT id FROM active_user_sessions
                                  WHERE is_active = 1
                                    AND user_id = ?
                                    AND session_id <> ?
                                    AND expires_at > NOW()
                                  FOR UPDATE");
    $select_stmt->execute([$user_id, $current_session_id]);
    $session_ids = $select_stmt->fetchAll(PDO::FETCH_COLUMN);

    if (empty($session_ids)) {
        return 0;
    }

    $update_stmt = $pdo->prepare("UPDATE active_user_sessions
                                  SET is_active = 0,
                                      ended_at = NOW(),
                                      ended_reason = 'duplicate_kick'
                                  WHERE user_id = ?
                                    AND session_id <> ?
                                    AND is_active = 1
                                    AND expires_at > NOW()");
    $update_stmt->execute([$user_id, $current_session_id]);
    $kicked = (int) $update_stmt->rowCount();

    if ($kicked > 0) {
        $details = sprintf(
            'Duplicate login policy kicked %d existing session(s) for user_id=%d',
            $kicked,
            $user_id
        );

        log_session_event_row(
            $pdo,
            $user_id,
            $user['username'] ?? 'unknown',
            'duplicate_kick',
            $details,
            'warning',
            $current_session_id
        );
    }

    return $kicked;
}

function release_active_sessions_for_user_role($pdo, $user_id, $role, $reason = 'self_replaced_login') {
    $user_id = (int) $user_id;
    $role = trim((string) $role);

    if ($user_id <= 0 || $role === '') {
        return 0;
    }

    $stmt = $pdo->prepare("UPDATE active_user_sessions
                          SET is_active = 0,
                              ended_at = NOW(),
                              ended_reason = ?
                          WHERE user_id = ?
                            AND role = ?
                            AND is_active = 1
                            AND expires_at > NOW()");
    $stmt->execute([$reason, $user_id, $role]);

    return (int) $stmt->rowCount();
}

function register_active_session_with_caps($pdo, $user, $session_id, $session_lifetime_seconds) {
    $role = $user['role'] ?? 'resident';
    $is_tracked_role = is_session_policy_tracked_role($role);
    if (!$is_tracked_role) {
        return ['allowed' => true];
    }

    $concurrency_caps_enabled = is_concurrency_caps_enabled();
    $auto_kick_duplicates_enabled = is_auto_kick_duplicate_sessions_enabled();
    if (!$concurrency_caps_enabled && !$auto_kick_duplicates_enabled) {
        return ['allowed' => true];
    }

    try {
        $pdo->beginTransaction();

        clear_expired_active_sessions_with_audit($pdo, 'session_admission');
        kick_duplicate_sessions_for_user($pdo, $user, $session_id);

        if ($concurrency_caps_enabled && is_admin_role($role)) {
            $cap = get_admin_max_concurrent();
            $count_stmt = $pdo->prepare("SELECT id
                                         FROM active_user_sessions
                                         WHERE is_active = 1
                                           AND role = 'admin'
                                           AND expires_at > NOW()
                                         FOR UPDATE");
            $count_stmt->execute();
            $active_count = $count_stmt->rowCount();

            if ($active_count >= $cap) {
                // Self-heal: if this same admin has stale active sessions, release them and retry admission.
                $released = release_active_sessions_for_user_role($pdo, $user['id'] ?? 0, 'admin');
                if ($released > 0) {
                    $count_stmt->execute();
                    $active_count = $count_stmt->rowCount();
                }

                if ($active_count >= $cap) {
                    $pdo->rollBack();
                    log_concurrency_denial($pdo, $user, 'admin', $cap, $active_count);
                    return ['allowed' => false, 'message' => "Admin concurrent login limit reached ({$cap}). Please wait for a slot to free up."];
                }
            }
        } elseif ($concurrency_caps_enabled) {
            $cap = get_official_max_concurrent();
            $count_stmt = $pdo->prepare("SELECT id
                                         FROM active_user_sessions
                                         WHERE is_active = 1
                                                                                     AND role IN ('official', 'barangay-captain', 'kagawad', 'barangay-secretary', 'barangay-treasurer', 'barangay-tanod')
                                           AND expires_at > NOW()
                                         FOR UPDATE");
            $count_stmt->execute();
            $active_count = $count_stmt->rowCount();

            if ($active_count >= $cap) {
                // Self-heal for official roles too: release same-user same-role stale sessions first.
                $released = release_active_sessions_for_user_role($pdo, $user['id'] ?? 0, $role);
                if ($released > 0) {
                    $count_stmt->execute();
                    $active_count = $count_stmt->rowCount();
                }

                if ($active_count >= $cap) {
                    $pdo->rollBack();
                    log_concurrency_denial($pdo, $user, 'official', $cap, $active_count);
                    return ['allowed' => false, 'message' => "Official concurrent login limit reached ({$cap}). Please wait for a slot to free up."];
                }
            }
        }

                $session_lifetime_seconds = max(60, (int) $session_lifetime_seconds);
        $insert_stmt = $pdo->prepare("INSERT INTO active_user_sessions
                                      (session_id, user_id, role, ip_address, user_agent, started_at, last_seen_at, expires_at, is_active, ended_at, ended_reason)
                                                                            VALUES (?, ?, ?, ?, ?, NOW(), NOW(), DATE_ADD(NOW(), INTERVAL ? SECOND), 1, NULL, NULL)
                                      ON DUPLICATE KEY UPDATE
                                        user_id = VALUES(user_id),
                                        role = VALUES(role),
                                        ip_address = VALUES(ip_address),
                                        user_agent = VALUES(user_agent),
                                        last_seen_at = NOW(),
                                                                                expires_at = DATE_ADD(NOW(), INTERVAL ? SECOND),
                                        is_active = 1,
                                        ended_at = NULL,
                                        ended_reason = NULL");
        $insert_stmt->execute([
            $session_id,
            $user['id'] ?? null,
            $role,
            $_SERVER['REMOTE_ADDR'] ?? null,
            $_SERVER['HTTP_USER_AGENT'] ?? null,
                        $session_lifetime_seconds,
                        $session_lifetime_seconds,
        ]);

        $pdo->commit();
        return ['allowed' => true];
    } catch (PDOException $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        return ['allowed' => false, 'message' => 'Unable to establish session right now. Please try again.'];
    }
}

function refresh_active_session_heartbeat($pdo, $session_id, $session_lifetime_seconds) {
    if (!is_concurrency_caps_enabled() && !is_auto_kick_duplicate_sessions_enabled()) {
        return true;
    }

    $session_lifetime_seconds = max(60, (int) $session_lifetime_seconds);
    $stmt = $pdo->prepare("UPDATE active_user_sessions
                          SET last_seen_at = NOW(),
                              expires_at = DATE_ADD(NOW(), INTERVAL ? SECOND),
                              is_active = 1
                          WHERE session_id = ?
                            AND is_active = 1
                            AND (ended_reason IS NULL OR ended_reason = '')");
    $stmt->execute([$session_lifetime_seconds, $session_id]);

    $affected = (int) $stmt->rowCount();
    
    // If no rows were affected, check if the session exists but is_active = 0 (revoked)
    if ($affected === 0) {
        $check_stmt = $pdo->prepare("SELECT is_active, ended_reason FROM active_user_sessions WHERE session_id = ? LIMIT 1");
        $check_stmt->execute([$session_id]);
        $session_record = $check_stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($session_record) {
            // Session exists but is inactive (revoked/expired by admin)
            if ((int) $session_record['is_active'] === 0) {
                return false;  // Signal that user should be logged out
            }
        }
        // Session doesn't exist in table yet (e.g., from older session or registration skipped)
        // Gracefully allow the user to continue; tracking will be re-registered on next page
        return true;
    }

    return true;
}

function deactivate_active_session($pdo, $session_id, $reason = 'logout') {
    if (!is_concurrency_caps_enabled() && !is_auto_kick_duplicate_sessions_enabled()) {
        return;
    }

    $lookup_stmt = $pdo->prepare("SELECT aus.user_id, aus.role, u.username
                                  FROM active_user_sessions aus
                                  LEFT JOIN users u ON u.id = aus.user_id
                                  WHERE aus.session_id = ?
                                  LIMIT 1");
    $lookup_stmt->execute([$session_id]);
    $session_row = $lookup_stmt->fetch(PDO::FETCH_ASSOC);

    $stmt = $pdo->prepare("UPDATE active_user_sessions
                          SET is_active = 0,
                              ended_at = NOW(),
                              ended_reason = ?
                          WHERE session_id = ?");
    $stmt->execute([$reason, $session_id]);

    if ($stmt->rowCount() > 0) {
        $action_map = [
            'expired' => 'expire',
            'revoked_by_admin' => 'revoke',
            'duplicate_kick' => 'duplicate_kick',
            'logout' => 'logout',
        ];
        $event_action = $action_map[$reason] ?? 'session_end';
        // Severity mapping: normal logout is 'info', forced terminations are 'warning'
        $event_severity = in_array($reason, ['expired', 'revoked_by_admin', 'duplicate_kick'], true) ? 'warning' : 'info';
        $event_details = sprintf('Session ended with reason=%s role=%s', $reason, (string) ($session_row['role'] ?? 'unknown'));

        log_session_event_row(
            $pdo,
            isset($session_row['user_id']) ? (int) $session_row['user_id'] : null,
            $session_row['username'] ?? 'unknown',
            $event_action,
            $event_details,
            $event_severity,
            $session_id
        );
    }
}

/**
 * Authenticate a user
 * 
 * @param string $username Username
 * @param string $password Password
 * @return array|bool User data array on success, false on failure
 */
function authenticate_user($username, $password, $pdo) {
    try {
        // Sanitize username just in case
        $username = sanitize_input($username);

        // Accept both username OR email for login
        $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ? OR email = ? LIMIT 1");
        $stmt->execute([$username, $username]);
        $user = $stmt->fetch();

        if ($user) {
            $status = strtolower(trim((string) ($user['status'] ?? 'active')));
            if ($status !== 'active') {
                $_SESSION['login_error_message'] = 'Account is pending activation or inactive. Please complete your invitation link or contact an administrator.';
                return false;
            }

            if (isset($user['password_change_required']) && (int) $user['password_change_required'] === 1) {
                $_SESSION['login_error_message'] = 'Password setup is required before sign-in. Please use your activation/reset link.';
                return false;
            }
        }

        if ($user && password_verify($password, $user['password'])) {
            // Update last_login timestamp
            $update_stmt = $pdo->prepare("UPDATE users SET last_login = CURRENT_TIMESTAMP WHERE id = ?");
            $update_stmt->execute([$user['id']]);
            
            return $user;
        }
        
        return false;

    } catch (PDOException $e) {
        // In a real application, you would log this error, not die.
        // error_log("Authentication error: " . $e->getMessage());
        return false;
    }
}

/**
 * Create session for authenticated user
 * 
 * @param array $user User data array
 * @param PDO $pdo Database connection (optional)
 * @return array{success: bool, message?: string}
 */
function create_session($user, $pdo = null) {
    session_regenerate_id(true); // Prevent session fixation

    $user_role = $user['role'] ?? 'resident';
    $is_official = is_session_policy_tracked_role($user_role);
    $session_lifetime = $is_official
        ? (int) env('ADMIN_SESSION_LIFETIME', 30 * 60)
        : (int) env('SESSION_LIFETIME', 5 * 60);

    if ($pdo) {
        $session_registration = register_active_session_with_caps($pdo, $user, session_id(), $session_lifetime);
        if (!$session_registration['allowed']) {
            return [
                'success' => false,
                'message' => $session_registration['message'] ?? 'Session limit reached.',
            ];
        }
    }

    $_SESSION['user_id'] = $user['id'];
    $_SESSION['username'] = $user['username'];
    $_SESSION['fullname'] = $user['fullname'];
    $_SESSION['role'] = $user['role'];
    $_SESSION['is_logged_in'] = true;
    $_SESSION['last_activity'] = time();
    
    // If user is a resident, also set the resident_id
    if ($user['role'] === 'resident' && $pdo) {
        try {
            $stmt = $pdo->prepare("SELECT id FROM residents WHERE user_id = ?");
            $stmt->execute([$user['id']]);
            $resident = $stmt->fetch();
            if ($resident) {
                $_SESSION['resident_id'] = $resident['id'];
            }
        } catch (PDOException $e) {
            // Log error but don't fail the login
            error_log("Error fetching resident ID: " . $e->getMessage());
        }
    }

    return ['success' => true];
}

/**
 * Check if user is logged in
 * 
 * @return bool True if logged in, false otherwise
 */
function is_logged_in() {
    if (isset($_SESSION['is_logged_in']) && $_SESSION['is_logged_in'] === true) {
        // Load environment variables for session timeout
        if (!function_exists('env')) {
            require_once __DIR__ . '/../config/env_loader.php';
        }
        
        // Get session timeout from environment or use defaults
        // Officials (including admin) get longer timeout (30 minutes), regular users get 5 minutes
        $user_role = $_SESSION['role'] ?? 'resident';
        $is_official = is_session_policy_tracked_role($user_role);
        
        if ($is_official) {
            $session_lifetime = (int)env('ADMIN_SESSION_LIFETIME', 30 * 60); // 30 minutes for officials
        } else {
            $session_lifetime = (int)env('SESSION_LIFETIME', 5 * 60); // 5 minutes for regular users
        }
        
        // Ensure session_lifetime has a reasonable minimum
        $session_lifetime = max(60, (int) $session_lifetime);
        
        if ((time() - $_SESSION['last_activity']) < $session_lifetime) {
            $_SESSION['last_activity'] = time();

            global $pdo;
            if (isset($pdo) && $pdo instanceof PDO) {
                try {
                    if (is_session_policy_tracked_role($user_role)) {
                        // Only enforce heartbeat for tracked roles (admin/official)
                        // Silence all errors to prevent unexpected logouts
                        @clear_expired_active_sessions_with_audit($pdo, 'heartbeat');
                        @$heartbeat_ok = refresh_active_session_heartbeat($pdo, session_id(), $session_lifetime);
                        
                        // Only logout if heartbeat explicitly returns FALSE (session revoked)
                        // This prevents logouts due to missing session records or other transient issues
                        if ($heartbeat_ok === false) {
                            logout('revoked_by_admin');
                            return false;
                        }
                    }
                } catch (Throwable $e) {
                    // Log the error for debugging but don't break the user session
                    error_log("Session heartbeat error for user {$_SESSION['username']}: " . $e->getMessage());
                    // Continue - user session remains valid even if heartbeat fails
                }
            }

            return true;
        } else {
            logout('expired');
            return false;
        }
    }
    return false;
}

/**
 * Check if current user is an admin or barangay official
 * 
 * @return bool
 */
function is_admin_or_official() {
    if (!isset($_SESSION['role'])) return false;
    return in_array($_SESSION['role'], ['admin', 'official', 'barangay-captain', 'kagawad', 'barangay-secretary', 'barangay-treasurer', 'barangay-tanod']);
}

/**
 * Log out user
 * 
 * @return void
 */
function logout($reason = 'logout') {
    $current_session_id = session_id();

    global $pdo;
    if (!empty($current_session_id) && isset($pdo) && $pdo instanceof PDO) {
        try {
            deactivate_active_session($pdo, $current_session_id, $reason);
        } catch (Throwable $e) {
            // Ignore deactivation failures during logout.
        }
    }

    $_SESSION = array();
    
    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $params["path"], $params["domain"],
            $params["secure"], $params["httponly"]
        );
    }
    
    session_destroy();
}

/**
 * Require authentication to access page
 * Redirects to login page if not logged in
 * 
 * @return void
 */
function require_login() {
    if (!is_logged_in()) {
        // Detect AJAX request
        $is_ajax = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest';
        if ($is_ajax) {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'error' => 'Session expired. Please log in again.']);
            exit;
        } else {
            redirect_to('../index.php');
        }
    }
}