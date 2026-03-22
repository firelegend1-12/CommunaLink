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
    if (!isset($_SESSION['role']) || !in_array($_SESSION['role'], ['admin', 'barangay-captain', 'kagawad', 'barangay-secretary', 'barangay-treasurer', 'barangay-tanod'])) {
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
 * @return bool True if successful, false otherwise
 */
function create_notification($pdo, $user_id, $title, $message, $type = 'general') {
    try {
        $stmt = $pdo->prepare("INSERT INTO notifications (user_id, title, message, type) VALUES (?, ?, ?, ?)");
        return $stmt->execute([$user_id, $title, $message, $type]);
    } catch (PDOException $e) {
        error_log("Error creating notification: " . $e->getMessage());
        return false;
    }
}