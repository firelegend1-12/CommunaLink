<?php
header('Content-Type: application/json');

require_once __DIR__ . '/../config/env_loader.php';

$now = gmdate('c');
$appEnv = (string) env('APP_ENV', 'unknown');
$appVersion = (string) env('APP_VERSION', 'unknown');
$releaseCommit = (string) env('RELEASE_COMMIT', 'unknown');
$storageMode = strtolower((string) env('USE_CLOUD_STORAGE', 'false')) === 'true' ? 'cloud' : 'local';

echo json_encode([
    'status' => 'healthy',
    'service' => 'CommunaLink',
    'timestamp' => $now,
    'metadata' => [
        'environment' => $appEnv,
        'version' => $appVersion,
        'release_commit' => $releaseCommit,
        'storage_mode' => $storageMode,
        'php_version' => PHP_VERSION,
    ],
]);
