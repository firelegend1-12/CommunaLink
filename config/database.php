<?php
/**
 * Database Connection File
 * Handles the connection to the MySQL database using PDO.
 */

// Load environment variables
require_once __DIR__ . '/env_loader.php';

// Database credentials from environment variables with fallback defaults
$servername = env('DB_HOST', 'localhost');
$username = env('DB_USER', 'root');
$password = env('DB_PASS', '');
$dbname = env('DB_NAME', 'barangay_reports');
$charset = env('DB_CHARSET', 'utf8mb4');

try {
    // First, connect to MySQL server without specifying a database
    $temp_pdo = new PDO("mysql:host=$servername", $username, $password);
    $temp_pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Check if the database exists and create it if it doesn't
    $temp_pdo->exec("CREATE DATABASE IF NOT EXISTS `$dbname` CHARACTER SET $charset COLLATE utf8mb4_unicode_ci;");

    // Now, establish the persistent connection to the specific database
    $dsn = "mysql:host=$servername;dbname=$dbname;charset=$charset";
    $options = [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ];
    
    $pdo = new PDO($dsn, $username, $password, $options);

} catch (PDOException $e) {
    // Log error securely without exposing details
    error_log("Database Connection Error: " . $e->getMessage());
    
    // Show generic error message to user
    if (env('APP_DEBUG', false) === true) {
        die("Database Connection Error: " . $e->getMessage());
    } else {
        die("Database connection failed. Please contact the administrator.");
    }
} 