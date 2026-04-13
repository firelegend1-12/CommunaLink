<?php
/**
 * Database Connection File
 * Handles the connection to the MySQL database using PDO.
 */

// Load environment variables
require_once __DIR__ . '/env_loader.php';

// Database credentials from environment variables with fallback defaults
$servername = env('DB_HOST', 'localhost');
$dbPort = (int) env('DB_PORT', 3306);
$dbSocket = trim((string) env('DB_SOCKET', ''));
$username = env('DB_USER', 'root');
$password = env('DB_PASS', '');
$dbname = env('DB_NAME', 'barangay_reports');
$charset = env('DB_CHARSET', 'utf8mb4');
$autoCreateDatabase = strtolower((string) env('AUTO_CREATE_DATABASE', 'true')) === 'true';
$appEnv = strtolower(trim((string) env('APP_ENV', 'production')));
$appDebug = strtolower(trim((string) env('APP_DEBUG', 'false'))) === 'true';

// Safety override: never auto-create DB in production-like environments.
if (in_array($appEnv, ['production', 'prod', 'staging'], true)) {
    $autoCreateDatabase = false;
}

try {
    // App Engine/Cloud Run Cloud SQL Unix socket support.
    $dsn_parts = ["dbname=$dbname", "charset=$charset"];
    if ($dbSocket !== '') {
        $dsn_parts[] = "unix_socket=$dbSocket";
    } else {
        $dsn_parts[] = "host=$servername";
        if ($dbPort > 0) {
            $dsn_parts[] = "port=$dbPort";
        }
    }

    if ($autoCreateDatabase && $dbSocket === '') {
        // DB creation is convenient in local/dev but should usually be disabled in managed production DBs.
        $bootstrapDsn = "mysql:host=$servername";
        if ($dbPort > 0) {
            $bootstrapDsn .= ";port=$dbPort";
        }
        $temp_pdo = new PDO($bootstrapDsn, $username, $password);
        $temp_pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $temp_pdo->exec("CREATE DATABASE IF NOT EXISTS `$dbname` CHARACTER SET $charset COLLATE utf8mb4_unicode_ci;");
    }

    $dsn = 'mysql:' . implode(';', $dsn_parts);
    $options = [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ];
    
    $pdo = new PDO($dsn, $username, $password, $options);

    // Keep DB session time aligned with PH operations for NOW()/CURDATE()/CURRENT_TIMESTAMP behaviors.
    $dbTimeZone = trim((string) env('DB_TIMEZONE', '+08:00'));
    if ($dbTimeZone === '') {
        $dbTimeZone = '+08:00';
    }

    try {
        $pdo->exec('SET time_zone = ' . $pdo->quote($dbTimeZone));
    } catch (Throwable $tzError) {
        // Fallback to a known-safe PH offset when custom timezone value is unavailable.
        try {
            $pdo->exec("SET time_zone = '+08:00'");
        } catch (Throwable $fallbackTzError) {
            error_log('Unable to set DB session timezone: ' . $fallbackTzError->getMessage());
        }
    }

} catch (PDOException $e) {
    // Log error securely without exposing details
    error_log("Database Connection Error: " . $e->getMessage());
    
    // Show generic error message to user
    if ($appDebug) {
        die("Database Connection Error: " . $e->getMessage());
    } else {
        die("Database connection failed. Please contact the administrator.");
    }
} 