<?php
header('Content-Type: application/json');

require_once __DIR__ . '/../config/env_loader.php';

function ready_db_check(): array
{
    $servername = (string) env('DB_HOST', 'localhost');
    $dbPort = (int) env('DB_PORT', 3306);
    $dbSocket = trim((string) env('DB_SOCKET', ''));
    $username = (string) env('DB_USER', 'root');
    $password = (string) env('DB_PASS', '');
    $dbname = (string) env('DB_NAME', 'barangay_reports');
    $charset = (string) env('DB_CHARSET', 'utf8mb4');

    try {
        $dsnParts = ["dbname={$dbname}", "charset={$charset}"];
        if ($dbSocket !== '') {
            $dsnParts[] = "unix_socket={$dbSocket}";
        } else {
            $dsnParts[] = "host={$servername}";
            if ($dbPort > 0) {
                $dsnParts[] = "port={$dbPort}";
            }
        }

        $pdo = new PDO('mysql:' . implode(';', $dsnParts), $username, $password, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_TIMEOUT => 2,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
        $pdo->query('SELECT 1');

        return ['ok' => true, 'message' => 'ok'];
    } catch (Throwable $e) {
        error_log('ready.php db check failed: ' . $e->getMessage());
        return ['ok' => false, 'message' => 'unreachable'];
    }
}

function ready_cache_check(): array
{
    if (function_exists('apcu_enabled') && apcu_enabled()) {
        return ['ok' => true, 'message' => 'apcu'];
    }

    $cacheDir = realpath(__DIR__ . '/../cache') ?: (__DIR__ . '/../cache');
    if (!is_dir($cacheDir)) {
        return ['ok' => false, 'message' => 'cache_dir_missing'];
    }

    if (!is_writable($cacheDir)) {
        return ['ok' => false, 'message' => 'cache_dir_not_writable'];
    }

    return ['ok' => true, 'message' => 'file'];
}

$db = ready_db_check();
$cache = ready_cache_check();
$isReady = $db['ok'] && $cache['ok'];

http_response_code($isReady ? 200 : 503);

echo json_encode([
    'status' => $isReady ? 'ready' : 'not_ready',
    'service' => 'CommunaLink',
    'timestamp' => gmdate('c'),
    'checks' => [
        'db' => $db,
        'cache' => $cache,
    ]
]);
