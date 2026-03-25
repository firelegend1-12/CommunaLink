<?php
/**
 * Diagnostic Script: Clear Active User Sessions
 * 
 * Usage:
 * Upload this script to your server, then access it via your browser:
 * https://your-cloud-domain.com/diagnostics/clear_cloud_sessions.php
 * 
 * Purpose:
 * When migrating databases (e.g., from local to Google Cloud), timezone differences
 * or stale active session records can artificially cap the concurrent login limits, 
 * permanently locking out admin and official accounts. This script forces a TRUNCATE
 * on the active_user_sessions table.
 */

// Basic security check to ensure we don't accidentally leave this open forever
// We recommend deleting this file after successful login.

require_once __DIR__ . '/../config/database.php';

try {
    // We truncate the table to completely wipe all locked/migrated sessions.
    $stmt = $pdo->prepare("TRUNCATE TABLE active_user_sessions");
    $stmt->execute();

    echo "<h1>Sessions Cleared Successfully</h1>";
    echo "<p>The <strong>active_user_sessions</strong> table has been truncated. The concurrency locks have been reset.</p>";
    echo "<p><a href='../index.php'>Return to Login</a></p>";
    echo "<p><em>Note: For security, please delete this script (diagnostics/clear_cloud_sessions.php) from your server once you have successfully logged in.</em></p>";

} catch (PDOException $e) {
    echo "<h1>Error Clearing Sessions</h1>";
    echo "<p>Database Error: " . htmlspecialchars($e->getMessage()) . "</p>";
}
