<?php
/**
 * Active Sessions Data Endpoint
 */

require_once '../../config/init.php';
require_once '../../includes/auth.php';
require_once '../../includes/functions.php';

header('Content-Type: application/json');

if (!is_logged_in() || !isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    http_response_code(403);
    echo json_encode([
        'success' => false,
        'error' => 'Unauthorized'
    ]);
    exit;
}

try {
    $admin_session_cap = function_exists('get_admin_max_concurrent') ? get_admin_max_concurrent() : 2;
    $official_session_cap = function_exists('get_official_max_concurrent') ? get_official_max_concurrent() : 5;
    $auto_kick_duplicate_enabled = function_exists('is_auto_kick_duplicate_sessions_enabled') ? is_auto_kick_duplicate_sessions_enabled() : false;

    $active_admin_stmt = $pdo->query("SELECT COUNT(*) FROM active_user_sessions
                                      WHERE is_active = 1
                                        AND role = 'admin'
                                        AND expires_at > NOW()");
    $active_admin_sessions = (int) $active_admin_stmt->fetchColumn();

    $active_official_stmt = $pdo->query("SELECT COUNT(*) FROM active_user_sessions
                                         WHERE is_active = 1
                                           AND role IN ('barangay-captain', 'kagawad', 'barangay-secretary', 'barangay-treasurer', 'barangay-tanod')
                                           AND expires_at > NOW()");
    $active_official_sessions = (int) $active_official_stmt->fetchColumn();

    $active_total_stmt = $pdo->query("SELECT COUNT(*) FROM active_user_sessions
                                      WHERE is_active = 1
                                        AND expires_at > NOW()");
    $active_total_sessions = (int) $active_total_stmt->fetchColumn();

    $active_sessions_stmt = $pdo->query("SELECT aus.id,
                                                aus.session_id,
                                                aus.user_id,
                                                aus.role,
                                                aus.ip_address,
                                                aus.last_seen_at,
                                                aus.expires_at,
                                                u.username,
                                                u.fullname
                                         FROM active_user_sessions aus
                                         LEFT JOIN users u ON u.id = aus.user_id
                                         WHERE aus.is_active = 1
                                           AND aus.expires_at > NOW()
                                         ORDER BY aus.last_seen_at DESC
                                         LIMIT 30");
    $active_sessions_raw = $active_sessions_stmt->fetchAll(PDO::FETCH_ASSOC);

    $active_sessions = [];
    foreach ($active_sessions_raw as $row) {
        $active_sessions[] = [
            'id' => (int) ($row['id'] ?? 0),
            'session_id' => (string) ($row['session_id'] ?? ''),
            'username' => (string) ($row['username'] ?? 'unknown'),
            'fullname' => (string) ($row['fullname'] ?? 'Unknown User'),
            'role' => (string) ($row['role'] ?? ''),
            'role_display' => (string) get_role_display_name($row['role'] ?? ''),
            'ip_address' => (string) ($row['ip_address'] ?? '-'),
            'last_seen_at' => (string) ($row['last_seen_at'] ?? ''),
            'expires_at' => (string) ($row['expires_at'] ?? ''),
        ];
    }

    echo json_encode([
        'success' => true,
        'counts' => [
            'admin' => $active_admin_sessions,
            'official' => $active_official_sessions,
            'total' => $active_total_sessions,
        ],
        'caps' => [
            'admin' => (int) $admin_session_cap,
            'official' => (int) $official_session_cap,
        ],
        'policy' => [
            'auto_kick_duplicate_enabled' => (bool) $auto_kick_duplicate_enabled,
        ],
        'sessions' => $active_sessions,
        'refreshed_at' => date('Y-m-d H:i:s'),
    ]);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Unable to load active sessions.'
    ]);
}
