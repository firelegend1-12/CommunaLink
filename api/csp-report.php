<?php
require_once '../config/env_loader.php';

header('Content-Type: application/json');

function csp_env_to_bool($value, $default = false) {
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

if (!csp_env_to_bool(env('CSP_REPORT_ENDPOINT_ENABLED', 'true'))) {
    http_response_code(204);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

$raw_body = file_get_contents('php://input');
if ($raw_body === false || $raw_body === '') {
    http_response_code(204);
    exit;
}

if (strlen($raw_body) > 65535) {
    http_response_code(413);
    echo json_encode(['error' => 'Payload too large']);
    exit;
}

$decoded = json_decode($raw_body, true);
if (!is_array($decoded)) {
    http_response_code(204);
    exit;
}

$entries = [];
if (isset($decoded['csp-report']) && is_array($decoded['csp-report'])) {
    $entries[] = $decoded['csp-report'];
} elseif (isset($decoded[0]) && is_array($decoded[0])) {
    $entries = $decoded;
} else {
    $entries[] = $decoded;
}

$ip_address = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
$user_agent = $_SERVER['HTTP_USER_AGENT'] ?? 'unknown';

foreach ($entries as $entry) {
    if (!is_array($entry)) {
        continue;
    }

    $log_payload = [
        'timestamp' => date('c'),
        'ip_address' => $ip_address,
        'user_agent' => $user_agent,
        'document_uri' => (string) ($entry['document-uri'] ?? $entry['documentURL'] ?? ''),
        'violated_directive' => (string) ($entry['violated-directive'] ?? ''),
        'effective_directive' => (string) ($entry['effective-directive'] ?? ''),
        'blocked_uri' => (string) ($entry['blocked-uri'] ?? ''),
        'line_number' => (int) ($entry['line-number'] ?? 0),
        'column_number' => (int) ($entry['column-number'] ?? 0),
        'source_file' => (string) ($entry['source-file'] ?? ''),
        'disposition' => (string) ($entry['disposition'] ?? ''),
    ];

    error_log('CSP_REPORT: ' . json_encode($log_payload));
}

http_response_code(204);
