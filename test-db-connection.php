<?php
require_once __DIR__ . '/includes/dev_guard.php';
/**
 * Database Connection Test Script
 * Tests if the database connection works on App Engine
 */

// Load environment variables
if (!function_exists('env')) {
    function env($key, $default = null) {
        $value = getenv($key);
        if ($value === false) {
            $value = $_ENV[$key] ?? $_SERVER[$key] ?? $default;
        }
        return $value;
    }
}

echo "=== Database Connection Test ===\n\n";

// Get environment variables
$dbHost = env('DB_HOST', 'localhost');
$dbPort = env('DB_PORT', '3306');
$dbSocket = trim((string) env('DB_SOCKET', ''));
$dbUser = env('DB_USER', 'root');
$dbPass = env('DB_PASS', '');
$dbName = env('DB_NAME', 'barangay_reports');
$dbCharset = env('DB_CHARSET', 'utf8mb4');

echo "DB_HOST: $dbHost\n";
echo "DB_PORT: $dbPort\n";
echo "DB_SOCKET: $dbSocket\n";
echo "DB_USER: $dbUser\n";
echo "DB_NAME: $dbName\n";
echo "DB_CHARSET: $dbCharset\n\n";

try {
    // Build DSN
    $dsn_parts = ["dbname=$dbName", "charset=$dbCharset"];
    if ($dbSocket !== '') {
        $dsn_parts[] = "unix_socket=$dbSocket";
        echo "Using Unix socket connection\n";
    } else {
        $dsn_parts[] = "host=$dbHost";
        if ($dbPort > 0) {
            $dsn_parts[] = "port=$dbPort";
        }
        echo "Using TCP connection\n";
    }

    $dsn = 'mysql:' . implode(';', $dsn_parts);
    echo "DSN: $dsn\n\n";

    $options = [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ];
    
    echo "Attempting connection...\n";
    $pdo = new PDO($dsn, $dbUser, $dbPass, $options);
    echo "✓ Connection successful!\n\n";

    // Test query
    $stmt = $pdo->query("SELECT VERSION() as version, DATABASE() as db");
    $result = $stmt->fetch();
    echo "MySQL Version: " . $result['version'] . "\n";
    echo "Current Database: " . $result['db'] . "\n\n";

    // Test users table
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM users");
    $result = $stmt->fetch();
    echo "Users table count: " . $result['count'] . "\n";

    echo "\n✓ All tests passed!\n";

} catch (PDOException $e) {
    echo "✗ Connection failed!\n";
    echo "Error: " . $e->getMessage() . "\n";
    echo "Code: " . $e->getCode() . "\n";
    exit(1);
}
