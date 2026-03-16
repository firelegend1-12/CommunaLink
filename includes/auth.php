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
 * @return void
 */
function create_session($user, $pdo = null) {
    session_regenerate_id(true); // Prevent session fixation
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
}

/**
 * Check if user is logged in
 * 
 * @return bool True if logged in, false otherwise
 */
function is_logged_in() {
    if (isset($_SESSION['is_logged_in']) && $_SESSION['is_logged_in'] === true) {
        // Load environment variables for session timeout (if not already loaded)
        if (!function_exists('env')) {
            require_once __DIR__ . '/../config/env_loader.php';
        }
        
        // Get session timeout from environment or use defaults
        // Admins get longer session timeout (30 minutes), regular users get 5 minutes
        $user_role = $_SESSION['role'] ?? 'resident';
        if ($user_role === 'admin') {
            $session_lifetime = (int)env('ADMIN_SESSION_LIFETIME', 30 * 60); // 30 minutes for admin
        } else {
            $session_lifetime = (int)env('SESSION_LIFETIME', 5 * 60); // 5 minutes for regular users
        }
        
        if ((time() - $_SESSION['last_activity']) < $session_lifetime) {
            $_SESSION['last_activity'] = time();
            return true;
        } else {
            logout();
            return false;
        }
    }
    return false;
}

/**
 * Log out user
 * 
 * @return void
 */
function logout() {
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