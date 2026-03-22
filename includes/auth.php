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
        $stmt = $pdo->prepare("INSERT INTO activity_logs
                              (user_id, username, action, target_type, target_id, details, ip_address, user_agent, session_id, request_id, severity)
                              VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");

        $username = $user['username'] ?? 'unknown';
        $role = $user['role'] ?? 'unknown';
        $details = sprintf(
            'Concurrency admission denied (%s cap reached): role=%s active=%d cap=%d',
            $scope,
            $role,
            (int) $active_count,
            (int) $cap
        );

        $stmt->execute([
            $user['id'] ?? null,
            $username,
            'deny',
            'session',
            null,
            $details,
            $_SERVER['REMOTE_ADDR'] ?? null,
            $_SERVER['HTTP_USER_AGENT'] ?? null,
            null,
            uniqid('req_', true),
            'warning',
        ]);
    } catch (Throwable $e) {
        // Do not block authentication flow if logging fails.
    }
}

function register_active_session_with_caps($pdo, $user, $session_id, $session_lifetime_seconds) {
    if (!is_concurrency_caps_enabled()) {
        return ['allowed' => true];
    }

    $role = $user['role'] ?? 'resident';
    if (!is_admin_role($role) && !is_official_role_only($role)) {
        return ['allowed' => true];
    }

    try {
        $pdo->beginTransaction();

        clear_expired_active_sessions($pdo);

        if (is_admin_role($role)) {
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
        } else {
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
    if (!is_concurrency_caps_enabled()) {
        return;
    }

    $expires_at = date('Y-m-d H:i:s', time() + max(60, (int) $session_lifetime_seconds));
    $stmt = $pdo->prepare("UPDATE active_user_sessions
                          SET last_seen_at = NOW(),
                              expires_at = ?,
                              is_active = 1,
                              ended_at = NULL,
                              ended_reason = NULL
                          WHERE session_id = ?");
    $stmt->execute([$expires_at, $session_id]);
}

function deactivate_active_session($pdo, $session_id, $reason = 'logout') {
    if (!is_concurrency_caps_enabled()) {
        return;
    }

    $stmt = $pdo->prepare("UPDATE active_user_sessions
                          SET is_active = 0,
                              ended_at = NOW(),
                              ended_reason = ?
                          WHERE session_id = ?");
    $stmt->execute([$reason, $session_id]);
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
                    clear_expired_active_sessions($pdo);
                    refresh_active_session_heartbeat($pdo, session_id(), $session_lifetime);
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