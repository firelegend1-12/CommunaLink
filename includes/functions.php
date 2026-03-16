<?php
/**
 * Helper Functions
 * Contains utility functions for the application
 */

/**
 * Sanitize user input to prevent XSS attacks
 *
 * @param string $data Input to sanitize
 * @return string Sanitized input
 */
function sanitize_input($data) {
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data);
    return $data;
}

/**
 * Validate email address format
 *
 * @param string $email Email to validate
 * @return bool True if valid, false otherwise
 */
function is_valid_email($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

/**
 * Check if string contains only alphanumeric characters
 *
 * @param string $string String to check
 * @return bool True if valid, false otherwise
 */
function is_alphanumeric($string) {
    return ctype_alnum($string);
}

/**
 * Redirect to a URL
 *
 * @param string $url URL to redirect to
 * @return void
 */
function redirect_to($url) {
    header("Location: $url");
    exit;
}

/**
 * Display error message
 *
 * @param string $message Error message
 * @return string HTML for error message
 */
function display_error($message) {
    return '<div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-4" role="alert">
                <p>' . $message . '</p>
            </div>';
}

/**
 * Display success message
 *
 * @param string $message Success message
 * @return string HTML for success message
 */
function display_success($message) {
    return '<div class="bg-green-100 border-l-4 border-green-500 text-green-700 p-4 mb-4" role="alert">
                <p>' . $message . '</p>
            </div>';
}

/**
 * Display flash messages from session and then clear them
 *
 * @return void
 */
function display_flash_messages() {
    if (isset($_SESSION['success_message'])) {
        echo display_success($_SESSION['success_message']);
        unset($_SESSION['success_message']);
    }

    if (isset($_SESSION['error_message'])) {
        echo display_error($_SESSION['error_message']);
        unset($_SESSION['error_message']);
    }

    if (isset($_SESSION['warning_message'])) {
        // You might want to create a display_warning function similar to display_error
        echo '<div class="bg-yellow-100 border-l-4 border-yellow-500 text-yellow-700 p-4 mb-4" role="alert">
                <p>' . $_SESSION['warning_message'] . '</p>
            </div>';
        unset($_SESSION['warning_message']);
    }
}

/**
 * Log activity to file
 *
 * @param string $action Action performed
 * @param string $description Description of action
 * @param int $user_id User ID performing the action
 * @return void
 */
function log_activity($action, $description, $user_id = null) {
    $log_dir = __DIR__ . '/../logs';
    
    // Create logs directory if it doesn't exist
    if (!file_exists($log_dir)) {
        mkdir($log_dir, 0755, true);
    }
    
    $log_file = $log_dir . '/activity_' . date('Y-m-d') . '.log';
    $timestamp = date('Y-m-d H:i:s');
    $log_message = "[{$timestamp}] User ID: {$user_id} | {$action} | {$description}" . PHP_EOL;
    
    file_put_contents($log_file, $log_message, FILE_APPEND);
}

/**
 * Log activity to the activity_logs database table
 *
 * @param PDO $pdo PDO database connection
 * @param string $action Action performed (add, edit, delete, etc.)
 * @param string $target_type What was affected (resident, business, etc.)
 * @param int|null $target_id ID of the affected record (optional)
 * @param string|null $details Description/details (optional)
 * @param string|null $old_value Old value (optional)
 * @param string|null $new_value New value (optional)
 * @return void
 */
function log_activity_db($pdo, $action, $target_type, $target_id = null, $details = null, $old_value = null, $new_value = null) {
    // Only log for admin
    if (!isset($_SESSION['role']) || !in_array($_SESSION['role'], ['admin'])) {
        return;
    }
    
    $user_id = $_SESSION['user_id'] ?? null;
    $username = $_SESSION['username'] ?? 'Unknown';
    
    try {
        $stmt = $pdo->prepare("INSERT INTO activity_logs (user_id, username, action, target_type, target_id, details, old_value, new_value) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([
            $user_id,
            $username,
            $action,
            $target_type,
            $target_id,
            $details,
            $old_value,
            $new_value
        ]);
    } catch (PDOException $e) {
        // Log failed, but don't break the application
        // In production, you might want to log this to a file instead
    }
}

if (!function_exists('require_role')) {
    function require_role($role) {
        if (!is_logged_in() || $_SESSION['role'] !== $role) {
            redirect_to('../index.php');
        }
    }
} 