<?php
/**
 * Authentication System
 * Handles user authentication, sessions, and access control
 */

// Start session if not already started
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Include necessary files
require_once __DIR__ . '/../config/init.php'; // Includes DB and functions
require_once __DIR__ . '/functions.php';

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
    return in_array($role, ['barangay-captain', 'kagawad', 'barangay-secretary', 'barangay-treasurer', 'barangay-tanod'], true);
}

function is_admin_role($role) {
    return $role === 'admin';
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

function register_active_session_with_caps($pdo, $user, $session_id, $session_lifetime_seconds) {
    $role = $user['role'] ?? 'resident';
    $is_tracked_role = is_admin_role($role) || is_official_role_only($role);
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

        clear_expired_active_sessions($pdo);
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
                $pdo->rollBack();
                log_concurrency_denial($pdo, $user, 'admin', $cap, $active_count);
                return ['allowed' => false, 'message' => "Admin concurrent login limit reached ({$cap}). Please wait for a slot to free up."];
            }
        } elseif ($concurrency_caps_enabled) {
            $cap = get_official_max_concurrent();
            $count_stmt = $pdo->prepare("SELECT id
                                         FROM active_user_sessions
                                         WHERE is_active = 1
                                           AND role IN ('barangay-captain', 'kagawad', 'barangay-secretary', 'barangay-treasurer', 'barangay-tanod')
                                           AND expires_at > NOW()
                                         FOR UPDATE");
            $count_stmt->execute();
            $active_count = $count_stmt->rowCount();

            if ($active_count >= $cap) {
                $pdo->rollBack();
                log_concurrency_denial($pdo, $user, 'official', $cap, $active_count);
                return ['allowed' => false, 'message' => "Official concurrent login limit reached ({$cap}). Please wait for a slot to free up."];
            }
        }

        $expires_at = date('Y-m-d H:i:s', time() + max(60, (int) $session_lifetime_seconds));
        $insert_stmt = $pdo->prepare("INSERT INTO active_user_sessions
                                      (session_id, user_id, role, ip_address, user_agent, started_at, last_seen_at, expires_at, is_active, ended_at, ended_reason)
                                      VALUES (?, ?, ?, ?, ?, NOW(), NOW(), ?, 1, NULL, NULL)
                                      ON DUPLICATE KEY UPDATE
                                        user_id = VALUES(user_id),
                                        role = VALUES(role),
                                        ip_address = VALUES(ip_address),
                                        user_agent = VALUES(user_agent),
                                        last_seen_at = NOW(),
                                        expires_at = VALUES(expires_at),
                                        is_active = 1,
                                        ended_at = NULL,
                                        ended_reason = NULL");
        $insert_stmt->execute([
            $session_id,
            $user['id'] ?? null,
            $role,
            $_SERVER['REMOTE_ADDR'] ?? null,
            $_SERVER['HTTP_USER_AGENT'] ?? null,
            $expires_at,
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

    $expires_at = date('Y-m-d H:i:s', time() + max(60, (int) $session_lifetime_seconds));
    $stmt = $pdo->prepare("UPDATE active_user_sessions
                          SET last_seen_at = NOW(),
                              expires_at = ?,
                              is_active = 1
                          WHERE session_id = ?
                            AND is_active = 1
                            AND (ended_reason IS NULL OR ended_reason = '')");
    $stmt->execute([$expires_at, $session_id]);

    return ((int) $stmt->rowCount()) > 0;
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

        $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ? LIMIT 1");
        $stmt->execute([$username]);
        $user = $stmt->fetch();

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
    $is_official = in_array($user_role, ['admin', 'barangay-captain', 'kagawad', 'barangay-secretary', 'barangay-treasurer', 'barangay-tanod'], true);
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
        $is_official = in_array($user_role, ['admin', 'barangay-captain', 'kagawad', 'barangay-secretary', 'barangay-treasurer', 'barangay-tanod']);
        
        if ($is_official) {
            $session_lifetime = (int)env('ADMIN_SESSION_LIFETIME', 30 * 60); // 30 minutes for officials
        } else {
            $session_lifetime = (int)env('SESSION_LIFETIME', 5 * 60); // 5 minutes for regular users
        }
        
        if ((time() - $_SESSION['last_activity']) < $session_lifetime) {
            $_SESSION['last_activity'] = time();

            global $pdo;
            if (isset($pdo) && $pdo instanceof PDO) {
                try {
                    if (is_admin_role($user_role) || is_official_role_only($user_role)) {
                        clear_expired_active_sessions($pdo);
                        $heartbeat_ok = refresh_active_session_heartbeat($pdo, session_id(), $session_lifetime);
                        if (!$heartbeat_ok) {
                            logout('revoked_by_admin');
                            return false;
                        }
                    }
                } catch (Throwable $e) {
                    // Ignore heartbeat update failures to avoid breaking user flow.
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
    return in_array($_SESSION['role'], ['admin', 'barangay-captain', 'kagawad', 'barangay-secretary', 'barangay-treasurer', 'barangay-tanod']);
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