<?php
/**
 * Helper Functions
 * Contains utility functions for the application
 */

/**
 * Sanitize user input to prevent XSS attacks
 *
 * @param string|null $data Input to sanitize
 * @return string Sanitized input
 */
function sanitize_input($data) {
    if ($data === null) {
        return '';
    }
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data, ENT_QUOTES | ENT_HTML5, 'UTF-8');
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
    $url = trim((string) $url);
    if ($url === '') {
        $url = '/';
    }

    // Allow explicit absolute URLs/schemes to pass through unchanged.
    if (preg_match('/^[a-z][a-z0-9+\-.]*:/i', $url) || strpos($url, '//') === 0) {
        header("Location: $url");
        exit;
    }

    $parts = parse_url($url);
    if ($parts === false) {
        $parts = ['path' => $url];
    }

    $path = $parts['path'] ?? '';
    $query = isset($parts['query']) && $parts['query'] !== '' ? '?' . $parts['query'] : '';
    $fragment = isset($parts['fragment']) && $parts['fragment'] !== '' ? '#' . $parts['fragment'] : '';

    if ($path === '') {
        $path = '/';
    }

    if (strpos($path, '/') !== 0) {
        $request_path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
        $base_dir = preg_replace('#/[^/]*$#', '/', $request_path);
        $path = $base_dir . $path;
    }

    // Normalize repeated slashes and dot segments.
    $path = preg_replace('#/+#', '/', str_replace('\\', '/', $path));
    $segments = explode('/', $path);
    $normalized = [];

    foreach ($segments as $segment) {
        if ($segment === '' || $segment === '.') {
            continue;
        }
        if ($segment === '..') {
            if (!empty($normalized)) {
                array_pop($normalized);
            }
            continue;
        }
        $normalized[] = $segment;
    }

    $resolved_path = '/' . implode('/', $normalized);
    header("Location: " . $resolved_path . $query . $fragment);
    exit;
}

/**
 * Get the application's base path for the current deployment.
 * Returns an empty string when the app is hosted at the web root.
 *
 * @return string
 */
function app_base_path() {
    static $base_path = null;

    if ($base_path !== null) {
        return $base_path;
    }

    $project_root = realpath(__DIR__ . '/..');
    $document_root = realpath((string) ($_SERVER['DOCUMENT_ROOT'] ?? ''));

    if ($project_root && $document_root) {
        $project_root_norm = str_replace('\\', '/', $project_root);
        $document_root_norm = rtrim(str_replace('\\', '/', $document_root), '/');

        if (stripos($project_root_norm, $document_root_norm) === 0) {
            $derived = substr($project_root_norm, strlen($document_root_norm));
            $derived = $derived === false ? '' : $derived;
            $derived = '/' . ltrim((string) $derived, '/');
            $base_path = rtrim($derived, '/');

            if ($base_path === '/' || $base_path === '.') {
                $base_path = '';
            }

            return $base_path;
        }
    }

    // Fallback for unusual server setups.
    $script_name = str_replace('\\', '/', (string) ($_SERVER['SCRIPT_NAME'] ?? $_SERVER['PHP_SELF'] ?? ''));
    $base_path = rtrim(str_replace('\\', '/', dirname($script_name)), '/');
    if ($base_path === '/' || $base_path === '.') {
        $base_path = '';
    }

    return $base_path;
}

/**
 * Build an application-relative URL that works at the web root or in a subfolder.
 *
 * @param string $path Path within the application
 * @return string
 */
function app_url($path = '') {
    $path = trim((string) $path);

    if ($path === '') {
        return app_base_path() !== '' ? app_base_path() : '/';
    }

    if (preg_match('/^[a-z][a-z0-9+\-.]*:/i', $path) || strpos($path, '//') === 0) {
        return $path;
    }

    $parts = parse_url($path);
    if ($parts === false) {
        $parts = ['path' => $path];
    }

    $normalized_path = ltrim((string) ($parts['path'] ?? ''), '/');
    $query = isset($parts['query']) && $parts['query'] !== '' ? '?' . $parts['query'] : '';
    $fragment = isset($parts['fragment']) && $parts['fragment'] !== '' ? '#' . $parts['fragment'] : '';

    $base_path = app_base_path();
    $full_path = ($base_path !== '' ? $base_path : '') . '/' . $normalized_path;
    $full_path = preg_replace('#/+#', '/', $full_path);

    return $full_path . $query . $fragment;
}

/**
 * Default resident avatar (SVG data URI) when no profile_image_path is set.
 *
 * @return string
 */
function resident_default_profile_avatar_data_uri(): string
{
    return "data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 200 200'%3E%3Crect width='200' height='200' fill='%235c67e2'/%3E%3Ccircle cx='100' cy='75' r='40' fill='%23fff' opacity='0.9'/%3E%3Cellipse cx='100' cy='170' rx='60' ry='50' fill='%23fff' opacity='0.9'/%3E%3C/svg%3E";
}

/**
 * Resolve a stored residents.profile_image_path value to a browser-usable URL.
 *
 * @param string $storedPath Value from database (relative, admin/..., gs://, or absolute URL)
 * @return string Public URL or empty string if invalid
 */
function resident_profile_image_url(string $storedPath): string
{
    $path = trim($storedPath);
    if ($path === '') {
        return '';
    }

    if (strpos($path, 'gs://') === 0 || preg_match('#^https?://#i', $path) === 1) {
        require_once __DIR__ . '/storage_manager.php';
        return StorageManager::resolvePublicUrl($path);
    }

    $normalized = ltrim(str_replace('\\', '/', $path), '/');
    if ($normalized === '') {
        return '';
    }

    if (stripos($normalized, 'admin/') === 0) {
        return app_url('/' . $normalized);
    }

    return app_url('/admin/' . $normalized);
}

/**
 * Strip spaces and common separators from raw phone input (does not validate).
 *
 * @param string $raw User input
 * @return string Compact string for further parsing
 */
function normalize_ph_mobile_input(string $raw): string
{
    $s = trim($raw);
    // Remove spaces, hyphens, and non-breaking spaces (U+00A0)
    // Using \x{00A0} instead of \u{00A0} for PCRE2 compatibility
    $result = preg_replace('/[\s\-\x{00A0}]+/u', '', $s);
    // Ensure we always return a string (preg_replace can return null on error)
    return $result !== null ? $result : '';
}

/**
 * Whether the value is a canonical Philippines mobile: +639 plus 9 digits.
 *
 * @param string $normalized Already compact (e.g. from normalize_ph_mobile_input)
 * @return bool
 */
function is_valid_ph_mobile_contact(string $normalized): bool
{
    return $normalized !== '' && preg_match('/^\+639\d{9}$/', $normalized) === 1;
}

/**
 * Normalize registration contact to +639XXXXXXXXX, or null if not a valid PH mobile pattern.
 * Accepts +639XXXXXXXXX, 09XXXXXXXXX, or 639XXXXXXXXX (no plus).
 *
 * @param string $raw Posted contact_no
 * @return string|null Canonical form or null
 */
function normalize_ph_mobile_for_registration(string $raw): ?string
{
    $s = normalize_ph_mobile_input($raw);
    if ($s === '') {
        return null;
    }
    if (preg_match('/^\+639\d{9}$/', $s) === 1) {
        return $s;
    }
    if (preg_match('/^09\d{9}$/', $s) === 1) {
        return '+63' . substr($s, 1);
    }
    if (preg_match('/^639\d{9}$/', $s) === 1) {
        return '+' . $s;
    }

    return null;
}

/**
 * Get the configured maximum number of admin users.
 * Defaults to 5 and is bounded to a sensible range.
 *
 * @return int
 */
function get_admin_user_cap() {
    $default_cap = 5;

    if (function_exists('env')) {
        $configured = (int) env('ADMIN_MAX_USERS', $default_cap);
    } else {
        $configured = $default_cap;
    }

    if ($configured < 1) {
        $configured = 1;
    }
    if ($configured > 50) {
        $configured = 50;
    }

    return $configured;
}

/**
 * Count existing admin users, optionally with row locks for transaction-safe checks.
 *
 * @param PDO $pdo Database connection
 * @param bool $lock_for_update Whether to lock matched rows
 * @return int
 */
function count_admin_users($pdo, $lock_for_update = false) {
    $sql = "SELECT id FROM users WHERE role = 'admin'";
    if ($lock_for_update) {
        $sql .= ' FOR UPDATE';
    }

    $stmt = $pdo->prepare($sql);
    $stmt->execute();

    return count($stmt->fetchAll(PDO::FETCH_COLUMN));
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
 * Build deterministic hash for a log entry.
 *
 * @param array $entryData Core log payload data
 * @param string $previousHash Previous chain hash
 * @return string SHA-256 hash
 */
function build_log_chain_hash(array $entryData, $previousHash = '') {
    ksort($entryData);
    $payload = json_encode([
        'prev_hash' => (string) $previousHash,
        'entry' => $entryData,
    ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

    return hash('sha256', (string) $payload);
}

/**
 * Archive old activity logs and create a tamper-evident batch record.
 *
 * @param PDO $pdo PDO database connection
 * @param int $days_to_keep Number of days to retain in hot table
 * @param int $batch_size Max rows to archive per run
 * @return array Result summary
 */
function archive_old_activity_logs($pdo, $days_to_keep = 90, $batch_size = 1000) {
    $days_to_keep = max(1, (int) $days_to_keep);
    $batch_size = max(1, min(5000, (int) $batch_size));

    $cutoff = date('Y-m-d H:i:s', strtotime("-{$days_to_keep} days"));

    try {
        $selectStmt = $pdo->prepare("SELECT * FROM activity_logs WHERE created_at < ? ORDER BY id ASC LIMIT {$batch_size}");
        $selectStmt->execute([$cutoff]);
        $rows = $selectStmt->fetchAll(PDO::FETCH_ASSOC);

        if (!$rows) {
            return [
                'archived_count' => 0,
                'batch_id' => null,
                'cutoff' => $cutoff,
                'message' => 'No logs older than retention window were found.',
            ];
        }

        $pdo->beginTransaction();

        $batch_id = uniqid('arch_', true);
        $archived_at = date('Y-m-d H:i:s');
        $rowHashes = [];
        $ids = [];

        $insertArchive = $pdo->prepare(
            "INSERT INTO activity_logs_archive (
                source_log_id,
                user_id,
                username,
                action,
                target_type,
                target_id,
                details,
                ip_address,
                user_agent,
                session_id,
                request_id,
                severity,
                old_value,
                new_value,
                prev_hash,
                log_hash,
                created_at,
                archived_at,
                archive_batch_id
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
        );

        foreach ($rows as $row) {
            $ids[] = (int) $row['id'];
            $rowHashes[] = (string) ($row['log_hash'] ?? '');

            $insertArchive->execute([
                $row['id'],
                $row['user_id'] ?? null,
                $row['username'] ?? null,
                $row['action'] ?? null,
                $row['target_type'] ?? null,
                $row['target_id'] ?? null,
                $row['details'] ?? null,
                $row['ip_address'] ?? null,
                $row['user_agent'] ?? null,
                $row['session_id'] ?? null,
                $row['request_id'] ?? null,
                $row['severity'] ?? 'info',
                $row['old_value'] ?? null,
                $row['new_value'] ?? null,
                $row['prev_hash'] ?? null,
                $row['log_hash'] ?? null,
                $row['created_at'] ?? null,
                $archived_at,
                $batch_id,
            ]);
        }

        $prevBatchHash = '';
        $prevBatchStmt = $pdo->query("SELECT batch_hash FROM activity_log_archive_batches ORDER BY id DESC LIMIT 1");
        if ($prevBatchStmt) {
            $prev = $prevBatchStmt->fetch(PDO::FETCH_ASSOC);
            if ($prev && !empty($prev['batch_hash'])) {
                $prevBatchHash = (string) $prev['batch_hash'];
            }
        }

        $batchPayload = implode('|', $rowHashes);
        $batchHash = hash('sha256', $prevBatchHash . '|' . $batch_id . '|' . $batchPayload . '|' . count($rows));

        $insertBatch = $pdo->prepare(
            "INSERT INTO activity_log_archive_batches (
                batch_id,
                previous_batch_hash,
                batch_hash,
                start_log_id,
                end_log_id,
                entry_count,
                created_at
            ) VALUES (?, ?, ?, ?, ?, ?, ?)"
        );
        $insertBatch->execute([
            $batch_id,
            $prevBatchHash,
            $batchHash,
            min($ids),
            max($ids),
            count($rows),
            $archived_at,
        ]);

        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $deleteStmt = $pdo->prepare("DELETE FROM activity_logs WHERE id IN ({$placeholders})");
        $deleteStmt->execute($ids);

        $pdo->commit();

        return [
            'archived_count' => count($rows),
            'batch_id' => $batch_id,
            'batch_hash' => $batchHash,
            'cutoff' => $cutoff,
            'message' => 'Archive completed successfully.',
        ];
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }

        return [
            'archived_count' => 0,
            'batch_id' => null,
            'cutoff' => $cutoff,
            'message' => 'Archive failed: ' . $e->getMessage(),
        ];
    }
}

/**
 * Insert an activity log row with hash-chain data.
 *
 * @param PDO $pdo PDO database connection
 * @param int|null $user_id User ID (optional)
 * @param string $username Username label
 * @param string $action Action performed (add, edit, delete, etc.)
 * @param string $target_type What was affected
 * @param int|null $target_id ID of the affected record (optional)
 * @param string|null $details Description/details (optional)
 * @param string|null $old_value Old value (optional)
 * @param string|null $new_value New value (optional)
 * @param string|null $severity Severity level (optional: info, warning, error, critical)
 * @param string|null $request_id Request identifier (optional)
 * @param string|null $session_id Session identifier (optional)
 * @param string|null $user_agent User agent (optional)
 * @param string|null $ip_address IP address (optional)
 * @return void
 */
function insert_activity_log_entry($pdo, $user_id, $username, $action, $target_type, $target_id = null, $details = null, $old_value = null, $new_value = null, $severity = null, $request_id = null, $session_id = null, $user_agent = null, $ip_address = null) {
    if ($request_id === null) {
        $request_id = $_SERVER['HTTP_X_REQUEST_ID'] ?? null;
    }
    if ($request_id === null) {
        $request_id = uniqid('req_', true);
    }

    if ($severity === null || $severity === '') {
        $severity = 'info';
        $action_lc = strtolower((string) $action);
        $details_lc = strtolower((string) ($details ?? ''));

        if (in_array($action_lc, ['critical', 'security_breach'])) {
            $severity = 'critical';
        } elseif (in_array($action_lc, ['error', 'failed', 'deny']) || strpos($details_lc, 'failed') !== false || strpos($details_lc, 'error') !== false) {
            $severity = 'error';
        } elseif (in_array($action_lc, ['warning', 'warn']) || strpos($details_lc, 'warning') !== false) {
            $severity = 'warning';
        }
    }

    $previous_hash = '';
    $chain_hash = '';

    try {
        $prevStmt = $pdo->query("SELECT log_hash FROM activity_logs ORDER BY id DESC LIMIT 1");
        if ($prevStmt) {
            $prevRow = $prevStmt->fetch(PDO::FETCH_ASSOC);
            if ($prevRow && !empty($prevRow['log_hash'])) {
                $previous_hash = (string) $prevRow['log_hash'];
            }
        }

        if ($previous_hash === '') {
            $archPrevStmt = $pdo->query("SELECT log_hash FROM activity_logs_archive ORDER BY id DESC LIMIT 1");
            if ($archPrevStmt) {
                $archPrevRow = $archPrevStmt->fetch(PDO::FETCH_ASSOC);
                if ($archPrevRow && !empty($archPrevRow['log_hash'])) {
                    $previous_hash = (string) $archPrevRow['log_hash'];
                }
            }
        }
    } catch (Throwable $e) {
        // If hash chain lookup fails, start fresh with empty previous hash.
        // Next log entry will begin a new chain segment.
        $previous_hash = '';
    }

    try {
        $chain_hash = build_log_chain_hash([
            'user_id' => $user_id,
            'username' => $username,
            'action' => $action,
            'target_type' => $target_type,
            'target_id' => $target_id,
            'details' => $details,
            'ip_address' => $ip_address,
            'user_agent' => $user_agent,
            'session_id' => $session_id,
            'request_id' => $request_id,
            'severity' => $severity,
            'old_value' => $old_value,
            'new_value' => $new_value,
        ], $previous_hash);
    } catch (Throwable $e) {
        // If hash computation fails, set to empty. The log will still be written but without chain integrity.
        // This is better than losing the log entry entirely.
        $chain_hash = '';
    }

    try {
        $stmt = $pdo->prepare("INSERT INTO activity_logs (user_id, username, action, target_type, target_id, details, ip_address, user_agent, session_id, request_id, severity, old_value, new_value, prev_hash, log_hash) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([
            $user_id,
            $username,
            $action,
            $target_type,
            $target_id,
            $details,
            $ip_address,
            $user_agent,
            $session_id,
            $request_id,
            $severity,
            $old_value,
            $new_value,
            $previous_hash,
            $chain_hash
        ]);
    } catch (PDOException $e) {
        // Logging failures must not break application flow.
    }
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
 * @param string|null $severity Severity level (optional: info, warning, error, critical)
 * @param string|null $request_id Request identifier (optional)
 * @return void
 */
function log_activity_db($pdo, $action, $target_type, $target_id = null, $details = null, $old_value = null, $new_value = null, $severity = null, $request_id = null) {
    // Only log for authorized admin/official roles
    if (!isset($_SESSION['role']) || !in_array($_SESSION['role'], ['admin', 'barangay-officials', 'barangay-kagawad', 'barangay-tanod'], true)) {
        return;
    }
    
    $user_id = $_SESSION['user_id'] ?? null;
    $username = $_SESSION['username'] ?? 'Unknown';
    insert_activity_log_entry(
        $pdo,
        $user_id,
        $username,
        $action,
        $target_type,
        $target_id,
        $details,
        $old_value,
        $new_value,
        $severity,
        $request_id,
        session_id() ?: null,
        $_SERVER['HTTP_USER_AGENT'] ?? null,
        $_SERVER['REMOTE_ADDR'] ?? null
    );
}

/**
 * System-level activity logging that bypasses role-gating while preserving hash-chain integrity.
 *
 * @return void
 */
function log_activity_db_system($pdo, $action, $target_type, $target_id = null, $details = null, $old_value = null, $new_value = null, $severity = null, $request_id = null, $user_id = null, $username = 'system', $session_id = null, $user_agent = null, $ip_address = null) {
    insert_activity_log_entry(
        $pdo,
        $user_id,
        $username,
        $action,
        $target_type,
        $target_id,
        $details,
        $old_value,
        $new_value,
        $severity,
        $request_id,
        $session_id,
        $user_agent,
        $ip_address
    );
}

if (!function_exists('require_role')) {
    function require_role($role) {
        if (!is_logged_in() || $_SESSION['role'] !== $role) {
            redirect_to('../index.php');
        }
    }
}

/**
 * Retrieve the resident ID for a given user ID
 *
 * @param PDO $pdo Database connection
 * @param int $user_id User ID
 * @return int|null Resident ID, or null if not found
 */
function get_resident_id($pdo, $user_id) {
    try {
        $stmt = $pdo->prepare("SELECT id FROM residents WHERE user_id = ?");
        $stmt->execute([$user_id]);
        $resident = $stmt->fetch();
        return $resident ? $resident['id'] : null;
    } catch (PDOException $e) {
        error_log("Error fetching resident ID: " . $e->getMessage());
        return null;
    }
}

/**
 * Create a new notification for a user
 *
 * @param PDO $pdo Database connection
 * @param int $user_id Recipient user ID
 * @param string $title Notification title
 * @param string $message Notification message
 * @param string $type Notification type (general, request_status, etc.)
 * @param string|null $link Optional target URL for notification click-through
 * @return bool True if successful, false otherwise
 */
function create_notification($pdo, $user_id, $title, $message, $type = 'general', $link = null) {
    try {
        static $notifications_columns = null;
        $GLOBALS['last_notification_error'] = null;

        if ($notifications_columns === null) {
            $notifications_columns = [];
            $colStmt = $pdo->query("SHOW COLUMNS FROM `notifications`");
            if ($colStmt) {
                foreach ($colStmt->fetchAll(PDO::FETCH_ASSOC) as $col) {
                    $name = (string) ($col['Field'] ?? '');
                    if ($name === '') {
                        continue;
                    }
                    $notifications_columns[$name] = [
                        'null' => strtoupper((string) ($col['Null'] ?? '')) === 'YES',
                        'default' => array_key_exists('Default', $col) ? $col['Default'] : null,
                    ];
                }
            }
        }

        $has_user_id = array_key_exists('user_id', $notifications_columns);
        $has_resident_id = array_key_exists('resident_id', $notifications_columns);
        $has_created_at = array_key_exists('created_at', $notifications_columns);

        if (!$has_user_id && !$has_resident_id) {
            $GLOBALS['last_notification_error'] = 'notifications table missing user_id/resident_id columns';
            error_log('Error creating notification: ' . $GLOBALS['last_notification_error']);
            return false;
        }
        if (!array_key_exists('message', $notifications_columns)) {
            $GLOBALS['last_notification_error'] = 'notifications table missing required message column';
            error_log('Error creating notification: ' . $GLOBALS['last_notification_error']);
            return false;
        }

        $resident_id_value = null;
        $needs_resident_id = false;
        if ($has_resident_id) {
            $resident_meta = $notifications_columns['resident_id'];
            $needs_resident_id = ($resident_meta['null'] === false && $resident_meta['default'] === null);
            $resolved_resident_id = get_resident_id($pdo, (int) $user_id);
            if ($resolved_resident_id !== null) {
                $resident_id_value = (int) $resolved_resident_id;
            } elseif ($needs_resident_id) {
                $GLOBALS['last_notification_error'] = 'could not resolve resident_id for user_id=' . (int) $user_id;
                error_log('Error creating notification: ' . $GLOBALS['last_notification_error']);
                return false;
            }
        }

        $needs_created_at = false;
        if ($has_created_at) {
            $created_meta = $notifications_columns['created_at'];
            $needs_created_at = ($created_meta['null'] === false && $created_meta['default'] === null);
        }

        $cols = [];
        $vals = [];
        $params = [];

        if ($has_user_id) {
            $cols[] = 'user_id';
            $vals[] = '?';
            $params[] = (int) $user_id;
        }

        if ($has_resident_id) {
            $cols[] = 'resident_id';
            $vals[] = '?';
            $params[] = $resident_id_value;
        }

        if (array_key_exists('title', $notifications_columns)) {
            $cols[] = 'title';
            $vals[] = '?';
            $params[] = $title;
        }

        $cols[] = 'message';
        $vals[] = '?';
        $params[] = $message;

        if (array_key_exists('type', $notifications_columns)) {
            $cols[] = 'type';
            $vals[] = '?';
            $params[] = $type;
        }

        if (array_key_exists('link', $notifications_columns)) {
            $cols[] = 'link';
            $vals[] = '?';
            $params[] = $link;
        }

        if (array_key_exists('is_read', $notifications_columns)) {
            $cols[] = 'is_read';
            $vals[] = '0';
        }

        if ($has_created_at && $needs_created_at) {
            $cols[] = 'created_at';
            $vals[] = 'NOW()';
        }

        $sql = "INSERT INTO notifications (" . implode(', ', $cols) . ") VALUES (" . implode(', ', $vals) . ")";
        $stmt = $pdo->prepare($sql);
        $created = $stmt->execute($params);
        if (!$created) {
            $error_info = $stmt->errorInfo();
            $GLOBALS['last_notification_error'] = 'notification insert returned false: ' . implode(' | ', array_filter(array_map('strval', $error_info)));
            error_log('Error creating notification: ' . $GLOBALS['last_notification_error']);
        }
        return $created;
    } catch (PDOException $e) {
        $GLOBALS['last_notification_error'] = $e->getMessage();
        error_log("Error creating notification: " . $GLOBALS['last_notification_error']);
        return false;
    }
}

/**
 * Last in-app notification creation failure for JSON/debug surfaces.
 *
 * @return string|null
 */
function get_last_notification_error(): ?string {
    $error = $GLOBALS['last_notification_error'] ?? null;
    return is_string($error) && $error !== '' ? $error : null;
}

/**
 * Resolve the audience values used for resident-targeted public posts.
 *
 * @param PDO $pdo
 * @param int $user_id
 * @return array<int, string>
 */
function get_resident_public_post_target_values(PDO $pdo, int $user_id): array {
    if ($user_id <= 0) {
        return ['all'];
    }

    try {
        $stmt = $pdo->prepare(
            "SELECT LOWER(TRIM(u.role)) AS role, TRIM(r.address) AS address
             FROM users u
             LEFT JOIN residents r ON r.user_id = u.id
             WHERE u.id = ?
             LIMIT 1"
        );
        $stmt->execute([$user_id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
    } catch (PDOException $e) {
        error_log('Error resolving resident public-post targeting context: ' . $e->getMessage());
        $row = [];
    }

    $role = (string)($row['role'] ?? 'resident');
    $address = trim((string)($row['address'] ?? ''));
    $targets = ['all'];

    if ($role === 'resident') {
        $targets[] = 'residents';
    }

    if ($role === 'business_owner' || $role === 'admin') {
        $targets[] = 'business';
    }

    if ($address !== '') {
        $targets[] = $address;
    }

    return array_values(array_unique($targets));
}

/**
 * Build a short bell-friendly summary for a public post.
 *
 * @param array<string, mixed> $post
 * @return string
 */
function build_resident_public_post_notification_message(array $post): string {
    $content = trim((string)($post['content'] ?? ''));
    $is_event = !empty($post['is_event']);
    $summary = $content;

    if ($summary === '') {
        $summary = $is_event ? 'A new barangay event was posted.' : 'A new barangay announcement was posted.';
    }

    if (function_exists('mb_strlen') && function_exists('mb_substr')) {
        if (mb_strlen($summary, 'UTF-8') > 160) {
            $summary = mb_substr($summary, 0, 157, 'UTF-8') . '...';
        }
    } elseif (strlen($summary) > 160) {
        $summary = substr($summary, 0, 157) . '...';
    }

    if ($is_event) {
        $event_parts = [];
        $event_date = trim((string)($post['event_date'] ?? ''));
        $event_time = trim((string)($post['event_time'] ?? ''));
        $event_location = trim((string)($post['event_location'] ?? ''));

        if ($event_date !== '') {
            $timestamp = strtotime($event_date);
            $event_parts[] = 'Date: ' . ($timestamp ? date('M d, Y', $timestamp) : $event_date);
        }
        if ($event_time !== '') {
            $timestamp = strtotime($event_time);
            $event_parts[] = 'Time: ' . ($timestamp ? date('h:i A', $timestamp) : $event_time);
        }
        if ($event_location !== '') {
            $event_parts[] = 'Venue: ' . $event_location;
        }

        if (!empty($event_parts)) {
            $summary .= ' ' . implode(' | ', $event_parts);
        }
    }

    return $summary;
}

/**
 * Fetch table column names with a small in-request cache.
 *
 * @param PDO $pdo
 * @param string $table_name
 * @return array<string, bool>
 */
function communalink_table_columns(PDO $pdo, string $table_name): array {
    static $cache = [];
    $table_name = trim($table_name);

    if (!preg_match('/^[A-Za-z0-9_]+$/', $table_name)) {
        return [];
    }

    if (array_key_exists($table_name, $cache)) {
        return $cache[$table_name];
    }

    $columns = [];
    try {
        $stmt = $pdo->query("SHOW COLUMNS FROM `{$table_name}`");
        if ($stmt) {
            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $column) {
                $name = (string)($column['Field'] ?? '');
                if ($name !== '') {
                    $columns[$name] = true;
                }
            }
        }
    } catch (PDOException $e) {
        error_log('Unable to inspect table columns for ' . $table_name . ': ' . $e->getMessage());
    }

    $cache[$table_name] = $columns;
    return $columns;
}

/**
 * Check table existence with a small in-request cache.
 */
function communalink_table_exists(PDO $pdo, string $table_name): bool {
    static $cache = [];
    $table_name = trim($table_name);

    if (!preg_match('/^[A-Za-z0-9_]+$/', $table_name)) {
        return false;
    }

    if (array_key_exists($table_name, $cache)) {
        return $cache[$table_name];
    }

    try {
        $stmt = $pdo->query("SHOW TABLES LIKE " . $pdo->quote($table_name));
        $cache[$table_name] = $stmt && $stmt->rowCount() > 0;
    } catch (PDOException $e) {
        error_log('Unable to inspect table existence for ' . $table_name . ': ' . $e->getMessage());
        $cache[$table_name] = false;
    }

    return $cache[$table_name];
}

/**
 * Fetch Community Board items as bell-compatible notification rows.
 *
 * @param PDO $pdo
 * @param int $user_id
 * @param int $resident_id
 * @param int $limit
 * @return array<int, array<string, mixed>>
 */
function get_resident_board_notifications(PDO $pdo, int $user_id, int $resident_id = 0, int $limit = 10): array {
    if ($user_id <= 0) {
        return [];
    }

    $resident_id = $resident_id > 0 ? $resident_id : (int)(get_resident_id($pdo, $user_id) ?? 0);
    $targets = get_resident_public_post_target_values($pdo, $user_id);
    $limit = max(1, min(50, $limit));

    if (empty($targets)) {
        $targets = ['all'];
    }

    $announcement_columns = communalink_table_columns($pdo, 'announcements');
    if (empty($announcement_columns) || empty($announcement_columns['id']) || empty($announcement_columns['title'])) {
        return [];
    }

    $has_content = !empty($announcement_columns['content']);
    $has_status = !empty($announcement_columns['status']);
    $has_publish_date = !empty($announcement_columns['publish_date']);
    $has_expiry_date = !empty($announcement_columns['expiry_date']);
    $has_target_audience = !empty($announcement_columns['target_audience']);
    $has_is_event = !empty($announcement_columns['is_event']);
    $has_event_date = !empty($announcement_columns['event_date']);
    $has_event_time = !empty($announcement_columns['event_time']);
    $has_event_location = !empty($announcement_columns['event_location']);
    $has_created_at = !empty($announcement_columns['created_at']);
    $has_reads_table = communalink_table_exists($pdo, 'announcement_reads') && $resident_id > 0;

    $created_expr = $has_publish_date && $has_created_at
        ? 'COALESCE(a.publish_date, a.created_at)'
        : ($has_publish_date ? 'a.publish_date' : ($has_created_at ? 'a.created_at' : 'NOW()'));
    $is_event_expr = $has_is_event ? 'a.is_event' : '0';
    $event_date_expr = $has_event_date ? 'a.event_date' : 'NULL';
    $event_time_expr = $has_event_time ? 'a.event_time' : 'NULL';
    $event_location_expr = $has_event_location ? 'a.event_location' : 'NULL';
    $content_expr = $has_content ? 'a.content' : "''";
    $read_expr = $has_reads_table ? 'CASE WHEN ar.announcement_id IS NULL THEN 0 ELSE 1 END' : '0';
    $join_sql = $has_reads_table
        ? ' LEFT JOIN announcement_reads ar ON ar.announcement_id = a.id AND ar.resident_id = ? '
        : '';
    $params = [];
    if ($has_reads_table) {
        $params[] = $resident_id;
    }

    $where = [];
    if ($has_status) {
        $where[] = "a.status = 'active'";
    }
    if ($has_publish_date) {
        $where[] = "(a.publish_date IS NULL OR a.publish_date <= NOW())";
    }
    if ($has_expiry_date) {
        $where[] = "(a.expiry_date IS NULL OR a.expiry_date >= NOW())";
    }
    if ($has_target_audience) {
        $placeholders = implode(',', array_fill(0, count($targets), '?'));
        $where[] = "a.target_audience IN ({$placeholders})";
        foreach ($targets as $target) {
            $params[] = $target;
        }
    }
    $where_sql = empty($where) ? '1 = 1' : implode(' AND ', $where);
    $params[] = $limit;

    try {
        $stmt = $pdo->prepare(
            "SELECT
                CONCAT('announcement-', a.id) AS id,
                a.id AS source_id,
                CASE
                    WHEN {$is_event_expr} = 1 THEN CONCAT('Community Event: ', a.title)
                    ELSE CONCAT('Community Board: ', a.title)
                END AS title,
                {$content_expr} AS content,
                {$is_event_expr} AS is_event,
                {$event_date_expr} AS event_date,
                {$event_time_expr} AS event_time,
                {$event_location_expr} AS event_location,
                'announcements.php' AS link,
                {$read_expr} AS is_read,
                CASE WHEN {$is_event_expr} = 1 THEN 'event_announcement' ELSE 'announcement' END AS type,
                {$created_expr} AS created_at
             FROM announcements a
             {$join_sql}
             WHERE {$where_sql}
             ORDER BY {$created_expr} DESC
             LIMIT ?"
        );
        $stmt->execute($params);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    } catch (PDOException $e) {
        error_log('Error fetching resident board notifications: ' . $e->getMessage());
        $rows = [];
    }

    foreach ($rows as &$row) {
        $row['message'] = build_resident_public_post_notification_message($row);
        $row['source'] = 'community_board';
        $row['source_link'] = 'announcements.php';
        $row['is_read'] = (int)($row['is_read'] ?? 0);
    }
    unset($row);

    try {
        $events_table = $pdo->query("SHOW TABLES LIKE 'events'");
        if ($events_table && $events_table->rowCount() > 0) {
            $legacy_stmt = $pdo->prepare(
                "SELECT
                    CONCAT('legacy-event-', e.id) AS id,
                    e.id AS source_id,
                    CONCAT('Community Event: ', e.title) AS title,
                    COALESCE(e.description, '') AS content,
                    1 AS is_event,
                    e.event_date,
                    e.event_time,
                    e.location AS event_location,
                    'announcements.php' AS link,
                    1 AS is_read,
                    'event_announcement' AS type,
                    e.created_at
                 FROM events e
                 WHERE (e.event_date IS NULL OR e.event_date >= CURDATE())
                   AND NOT EXISTS (
                        SELECT 1
                        FROM announcements a
                        WHERE a.is_event = 1
                          AND a.title = e.title
                          AND (a.event_date <=> e.event_date)
                   )
                 ORDER BY e.created_at DESC
                 LIMIT ?"
            );
            $legacy_stmt->execute([$limit]);
            $legacy_rows = $legacy_stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

            foreach ($legacy_rows as &$legacy_row) {
                $legacy_row['message'] = build_resident_public_post_notification_message($legacy_row);
                $legacy_row['source'] = 'community_board';
                $legacy_row['source_link'] = 'announcements.php';
                $legacy_row['is_read'] = 1;
            }
            unset($legacy_row);

            if (!empty($legacy_rows)) {
                $rows = array_merge($rows, $legacy_rows);
            }
        }
    } catch (PDOException $e) {
        error_log('Error fetching legacy event notifications: ' . $e->getMessage());
    }

    usort($rows, static function (array $left, array $right): int {
        $left_time = strtotime((string)($left['created_at'] ?? '')) ?: 0;
        $right_time = strtotime((string)($right['created_at'] ?? '')) ?: 0;
        return $right_time <=> $left_time;
    });

    return array_slice($rows, 0, $limit);
}

/**
 * Fetch the resident bell feed by merging in-app notifications and Community Board items.
 *
 * @param PDO $pdo
 * @param int $user_id
 * @param int $resident_id
 * @param int $limit
 * @return array<int, array<string, mixed>>
 */
function get_resident_combined_notifications(PDO $pdo, int $user_id, int $resident_id = 0, int $limit = 10): array {
    if ($user_id <= 0) {
        return [];
    }

    $limit = max(1, min(50, $limit));

    try {
        $stmt = $pdo->prepare(
            "SELECT
                CONCAT('notification-', id) AS id,
                id AS source_id,
                title,
                message,
                type,
                link,
                is_read,
                created_at
             FROM notifications
             WHERE user_id = ?
             ORDER BY created_at DESC
             LIMIT ?"
        );
        $stmt->execute([$user_id, $limit]);
        $notifications = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    } catch (PDOException $e) {
        error_log('Error fetching resident in-app notifications: ' . $e->getMessage());
        $notifications = [];
    }

    foreach ($notifications as &$notification) {
        $notification['source'] = 'notification';
        $notification['is_read'] = (int)($notification['is_read'] ?? 0);

        $type = trim((string)($notification['type'] ?? ''));
        if ($type === 'announcement' || $type === 'event_announcement') {
            $title = trim((string)($notification['title'] ?? ''));
            $normalized_title = preg_replace('/^(new barangay announcement|new barangay event|community board|community event)\s*:\s*/i', '', $title);
            $prefix = $type === 'event_announcement' ? 'Community Event' : 'Community Board';
            $notification['title'] = trim($prefix . ($normalized_title !== '' ? ': ' . $normalized_title : ''));

            $link = trim((string)($notification['link'] ?? ''));
            if ($link === '' || preg_match('#(?:^|/)resident/notifications\.php$#i', $link) === 1 || strcasecmp($link, 'notifications.php') === 0) {
                $notification['link'] = 'announcements.php';
            }
        }
    }
    unset($notification);

    $board_rows = get_resident_board_notifications($pdo, $user_id, $resident_id, $limit);
    $covered_board_keys = [];

    foreach ($notifications as $notification) {
        $type = trim((string)($notification['type'] ?? ''));
        if ($type !== 'announcement' && $type !== 'event_announcement') {
            continue;
        }

        $title = trim((string)($notification['title'] ?? ''));
        $normalized_title = preg_replace('/^(new barangay announcement|new barangay event|community board|community event)\s*:\s*/i', '', $title);
        $date_key = '';
        $timestamp = strtotime((string)($notification['created_at'] ?? ''));
        if ($timestamp) {
            $date_key = date('Y-m-d', $timestamp);
        }

        $covered_board_keys[strtolower($normalized_title) . '|' . $date_key] = true;
    }

    $merged = $notifications;
    foreach ($board_rows as $board_row) {
        $title = trim((string)($board_row['title'] ?? ''));
        $normalized_title = preg_replace('/^(Community Board|Community Event)\s*:\s*/i', '', $title);
        $timestamp = strtotime((string)($board_row['created_at'] ?? ''));
        $date_key = $timestamp ? date('Y-m-d', $timestamp) : '';
        $fingerprint = strtolower($normalized_title) . '|' . $date_key;

        if (isset($covered_board_keys[$fingerprint])) {
            continue;
        }

        $merged[] = $board_row;
    }

    usort($merged, static function (array $left, array $right): int {
        $left_time = strtotime((string)($left['created_at'] ?? '')) ?: 0;
        $right_time = strtotime((string)($right['created_at'] ?? '')) ?: 0;
        return $right_time <=> $left_time;
    });

    return array_slice($merged, 0, $limit);
}

/**
 * Mark targeted Community Board items as read for a resident.
 *
 * @param PDO $pdo
 * @param int $user_id
 * @param int $resident_id
 * @return int
 */
function mark_resident_board_notifications_read(PDO $pdo, int $user_id, int $resident_id = 0): int {
    if ($user_id <= 0) {
        return 0;
    }

    $resident_id = $resident_id > 0 ? $resident_id : (int)(get_resident_id($pdo, $user_id) ?? 0);
    if ($resident_id <= 0) {
        return 0;
    }
    if (!communalink_table_exists($pdo, 'announcement_reads')) {
        return 0;
    }

    $announcement_columns = communalink_table_columns($pdo, 'announcements');
    if (empty($announcement_columns['id'])) {
        return 0;
    }

    $targets = get_resident_public_post_target_values($pdo, $user_id);
    if (empty($targets)) {
        $targets = ['all'];
    }

    $params = [$resident_id];

    $where = [];
    if (!empty($announcement_columns['status'])) {
        $where[] = "a.status = 'active'";
    }
    if (!empty($announcement_columns['publish_date'])) {
        $where[] = "(a.publish_date IS NULL OR a.publish_date <= NOW())";
    }
    if (!empty($announcement_columns['expiry_date'])) {
        $where[] = "(a.expiry_date IS NULL OR a.expiry_date >= NOW())";
    }
    if (!empty($announcement_columns['target_audience'])) {
        $placeholders = implode(',', array_fill(0, count($targets), '?'));
        $where[] = "a.target_audience IN ({$placeholders})";
        foreach ($targets as $target) {
            $params[] = $target;
        }
    }
    $where[] = 'ar.announcement_id IS NULL';
    $where_sql = implode(' AND ', $where);

    try {
        $stmt = $pdo->prepare(
            "SELECT a.id
             FROM announcements a
             LEFT JOIN announcement_reads ar
                ON ar.announcement_id = a.id
               AND ar.resident_id = ?
             WHERE {$where_sql}"
        );
        $stmt->execute($params);
        $announcement_ids = array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN) ?: []);
    } catch (PDOException $e) {
        error_log('Error resolving unread board notifications: ' . $e->getMessage());
        return 0;
    }

    if (empty($announcement_ids)) {
        return 0;
    }

    try {
        $pdo->beginTransaction();
        $insert = $pdo->prepare("INSERT IGNORE INTO announcement_reads (announcement_id, resident_id) VALUES (?, ?)");
        $update = !empty($announcement_columns['read_count'])
            ? $pdo->prepare("UPDATE announcements SET read_count = read_count + 1 WHERE id = ?")
            : null;
        $updated_count = 0;

        foreach ($announcement_ids as $announcement_id) {
            $insert->execute([$announcement_id, $resident_id]);
            if ((int)$insert->rowCount() > 0) {
                if ($update !== null) {
                    $update->execute([$announcement_id]);
                }
                $updated_count++;
            }
        }

        $pdo->commit();
        return $updated_count;
    } catch (PDOException $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log('Error marking board notifications read: ' . $e->getMessage());
        return 0;
    }
}

/**
 * Resolve the resident account that should receive document request notifications.
 *
 * Prefer the account that submitted the request, then fall back to the linked
 * resident profile account for older records.
 *
 * @param PDO $pdo
 * @param int $request_id
 * @return int|null
 */
function get_document_request_recipient_user_id(PDO $pdo, int $request_id): ?int {
    if ($request_id <= 0) {
        return null;
    }

    try {
        $stmt = $pdo->prepare("SELECT dr.requested_by_user_id, r.user_id AS resident_user_id
                               FROM document_requests dr
                               LEFT JOIN residents r ON dr.resident_id = r.id
                               WHERE dr.id = ?
                               LIMIT 1");
        $stmt->execute([$request_id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            return null;
        }

        $requested_by_user_id = (int)($row['requested_by_user_id'] ?? 0);
        if ($requested_by_user_id > 0) {
            return $requested_by_user_id;
        }

        $resident_user_id = (int)($row['resident_user_id'] ?? 0);
        return $resident_user_id > 0 ? $resident_user_id : null;
    } catch (PDOException $e) {
        error_log('Error resolving document request recipient: ' . $e->getMessage());
        return null;
    }
}

/**
 * Whether debug details may be included in JSON responses.
 *
 * @return bool
 */
function app_debug_enabled(): bool {
    if (!function_exists('env')) {
        return strtolower(trim((string)(getenv('APP_DEBUG') ?: 'false'))) === 'true';
    }

    return in_array(strtolower(trim((string) env('APP_DEBUG', 'false'))), ['1', 'true', 'yes', 'on'], true);
}

/**
 * Return the standard processing fee for resident document requests.
 *
 * @param string|null $document_type
 * @return float
 */
function get_document_request_fee($document_type): float {
    $normalized = strtolower(trim((string) $document_type));
    $normalized = preg_replace('/\s+/', ' ', $normalized);

    if ($normalized === 'certificate of indigency' || $normalized === 'certificate of indigency (special)') {
        return 0.00;
    }

    return 50.00;
}

/**
 * Determine whether a document request requires payment before printing.
 *
 * @param string|null $document_type
 * @return bool
 */
function document_request_requires_payment($document_type): bool {
    return get_document_request_fee($document_type) > 0;
}

/**
 * Emit a consistent JSON error response for request handlers.
 *
 * @param string $public_message
 * @param int $status_code
 * @param Throwable|null $exception
 * @param string $context
 * @param array<string,mixed> $extra
 * @return void
 */
function send_json_error_response(string $public_message, int $status_code = 400, ?Throwable $exception = null, string $context = 'Application error', array $extra = []): void {
    $error_id = 'ERR-' . strtoupper(bin2hex(random_bytes(4))) . '-' . date('YmdHis');

    if ($exception !== null) {
        error_log($context . " [{$error_id}]: " . $exception->getMessage());
    } else {
        error_log($context . " [{$error_id}]: " . $public_message);
    }

    if (!headers_sent()) {
        http_response_code($status_code);
        header('Content-Type: application/json');
    }

    $payload = array_merge([
        'success' => false,
        'error' => $public_message,
        'error_id' => $error_id,
    ], $extra);

    if ($exception !== null && app_debug_enabled()) {
        $payload['debug_error'] = $exception->getMessage();
    }

    echo json_encode($payload);
    exit;
}

/**
 * Normalize request status values for consistent UI display across pages.
 *
 * The thesis-submitted schemas commonly allow only:
 * Pending, Approved, Completed, Rejected, Cancelled
 *
 * Some legacy/older UI flows may store or return variants like:
 * Processing / Ready for Pickup / uppercase variants.
 *
 * @param string|null $status
 * @return string Normalized status label
 */
function normalize_request_status_display($status) {
    $value = trim((string) $status);
    if ($value === '') {
        return '';
    }

    $upper = strtoupper($value);

    // Legacy variants that should be shown as Approved in the UI.
    if ($upper === 'PROCESSING' || $upper === 'READY FOR PICKUP') {
        return 'Approved';
    }

    // Canonicalize common enum/label variants.
    if ($upper === 'PENDING') {
        return 'Pending';
    }
    if ($upper === 'APPROVED') {
        return 'Approved';
    }
    if ($upper === 'COMPLETED') {
        return 'Completed';
    }
    if ($upper === 'REJECTED') {
        return 'Rejected';
    }
    if ($upper === 'CANCELLED' || $upper === 'CANCELED') {
        return 'Cancelled';
    }

    return $value;
}

/**
 * Determine whether a request is already in a terminal non-completable state.
 *
 * @param string|null $status
 * @return bool
 */
function request_has_terminal_status($status): bool {
    $normalized = normalize_request_status_display($status);
    return in_array($normalized, ['Rejected', 'Cancelled'], true);
}

/**
 * Determine whether a paid request should be treated as completed.
 *
 * @param string|null $status
 * @param string|null $payment_status
 * @param bool $requires_payment
 * @return bool
 */
function paid_request_should_display_completed($status, $payment_status, bool $requires_payment = true): bool {
    if (!$requires_payment) {
        return false;
    }

    return trim((string) $payment_status) === 'Paid' && !request_has_terminal_status($status);
}

/**
 * Normalize a request status for display, including the paid-equals-completed rule.
 *
 * @param string|null $status
 * @param string|null $payment_status
 * @param bool $requires_payment
 * @return string
 */
function get_request_display_status($status, $payment_status = null, bool $requires_payment = true): string {
    if (paid_request_should_display_completed($status, $payment_status, $requires_payment)) {
        return 'Completed';
    }

    return normalize_request_status_display($status);
}

/**
 * Canonical request statuses used by the current UI.
 *
 * @return array<int, string>
 */
function canonical_request_statuses() {
    return ['Pending', 'Approved', 'Completed', 'Rejected', 'Cancelled'];
}

/**
 * Read the allowed ENUM values for a request status column.
 *
 * @param PDO $pdo
 * @param string $table
 * @return array<int, string>
 */
function request_status_enum_values(PDO $pdo, string $table) {
    $allowed_tables = ['document_requests', 'business_transactions'];
    if (!in_array($table, $allowed_tables, true)) {
        return [];
    }

    try {
        $stmt = $pdo->query("SHOW COLUMNS FROM `{$table}` LIKE 'status'");
        $column = $stmt ? $stmt->fetch(PDO::FETCH_ASSOC) : false;
        $type = (string)($column['Type'] ?? '');
        preg_match_all("/'((?:[^'\\\\]|\\\\.)*)'/", $type, $matches);
        return array_map(static function ($value) {
            return str_replace("\\'", "'", $value);
        }, $matches[1] ?? []);
    } catch (Throwable $e) {
        error_log("Could not inspect {$table}.status enum: " . $e->getMessage());
        return [];
    }
}

/**
 * Convert a requested UI status into the safest DB value for the current schema.
 *
 * Modern schemas store Approved directly. Older schemas may still only accept
 * Processing or Ready for Pickup for the approved step.
 *
 * @param PDO $pdo
 * @param string $table
 * @param string|null $status
 * @return string
 */
function normalize_request_status_for_storage(PDO $pdo, string $table, $status) {
    $display_status = normalize_request_status_display($status);
    if ($display_status === '') {
        return '';
    }

    $enum_values = request_status_enum_values($pdo, $table);
    if (empty($enum_values) || in_array($display_status, $enum_values, true)) {
        return $display_status;
    }

    foreach ($enum_values as $enum_value) {
        if (strcasecmp($display_status, $enum_value) === 0) {
            return $enum_value;
        }
    }

    if ($display_status === 'Approved') {
        if (in_array('Processing', $enum_values, true)) {
            return 'Processing';
        }
        if (in_array('Ready for Pickup', $enum_values, true)) {
            return 'Ready for Pickup';
        }
        foreach ($enum_values as $enum_value) {
            $normalized_enum = normalize_request_status_display($enum_value);
            if ($normalized_enum === 'Approved') {
                return $enum_value;
            }
        }
    }

    return $display_status;
}
