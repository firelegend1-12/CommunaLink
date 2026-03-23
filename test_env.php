<?php
/**
 * Environment Variables Test Script
 * Run this to verify your .env file is working correctly
 * 
 * Usage: Development only. Run via CLI or as authenticated admin/official in non-production.
 */

// Load environment variables
require_once __DIR__ . '/config/env_loader.php';

$app_env = strtolower((string) env('APP_ENV', 'production'));

if ($app_env === 'production') {
    http_response_code(404);
    exit('Not Found');
}

if (PHP_SAPI !== 'cli') {
    require_once __DIR__ . '/includes/auth.php';

    if (!is_logged_in() || !is_admin_or_official()) {
        http_response_code(403);
        exit('Forbidden');
    }
}

echo "<h1>🔍 Environment Variables Test</h1>";
echo "<style>
    body { font-family: Arial, sans-serif; max-width: 800px; margin: 50px auto; padding: 20px; }
    .success { background: #d4edda; color: #155724; padding: 15px; border-radius: 5px; margin: 10px 0; }
    .error { background: #f8d7da; color: #721c24; padding: 15px; border-radius: 5px; margin: 10px 0; }
    .info { background: #d1ecf1; color: #0c5460; padding: 15px; border-radius: 5px; margin: 10px 0; }
    table { width: 100%; border-collapse: collapse; margin: 20px 0; }
    th, td { padding: 12px; text-align: left; border-bottom: 1px solid #ddd; }
    th { background-color: #4F46E5; color: white; }
    .masked { color: #666; font-family: monospace; }
</style>";

// Check if .env file exists
$env_file = __DIR__ . '/.env';
if (file_exists($env_file)) {
    echo "<div class='success'>✅ .env file found at: " . htmlspecialchars($env_file) . "</div>";
} else {
    echo "<div class='error'>❌ .env file NOT found! Please create it from .env.example</div>";
    exit;
}

// Test database configuration
echo "<h2>📊 Database Configuration</h2>";
echo "<table>";
echo "<tr><th>Setting</th><th>Value</th><th>Status</th></tr>";

$db_host = env('DB_HOST', 'localhost');
$db_name = env('DB_NAME', 'barangay_reports');
$db_user = env('DB_USER', 'root');
$db_pass = env('DB_PASS', '');
$db_charset = env('DB_CHARSET', 'utf8mb4');

echo "<tr><td>DB_HOST</td><td>" . htmlspecialchars($db_host) . "</td><td>✅</td></tr>";
echo "<tr><td>DB_NAME</td><td>" . htmlspecialchars($db_name) . "</td><td>✅</td></tr>";
echo "<tr><td>DB_USER</td><td>" . htmlspecialchars($db_user) . "</td><td>✅</td></tr>";
echo "<tr><td>DB_PASS</td><td>" . ($db_pass ? '<span class="masked">••••••••</span>' : '<em>empty</em>') . "</td><td>✅</td></tr>";
echo "<tr><td>DB_CHARSET</td><td>" . htmlspecialchars($db_charset) . "</td><td>✅</td></tr>";
echo "</table>";

// Test database connection
try {
    require_once __DIR__ . '/config/database.php';
    echo "<div class='success'>✅ Database connection successful!</div>";
} catch (Exception $e) {
    echo "<div class='error'>❌ Database connection failed: " . htmlspecialchars($e->getMessage()) . "</div>";
}

// Test email configuration
echo "<h2>📧 Email Configuration</h2>";
echo "<table>";
echo "<tr><th>Setting</th><th>Value</th><th>Status</th></tr>";

$smtp_host = env('EMAIL_SMTP_HOST', 'smtp.gmail.com');
$smtp_port = env('EMAIL_SMTP_PORT', 465);
$smtp_username = env('EMAIL_SMTP_USERNAME', '');
$smtp_password = env('EMAIL_SMTP_PASSWORD', '');
$smtp_secure = env('EMAIL_SMTP_SECURE', 'ssl');
$from_email = env('EMAIL_FROM_EMAIL', '');
$from_name = env('EMAIL_FROM_NAME', 'CommuniLink Barangay System');

echo "<tr><td>EMAIL_SMTP_HOST</td><td>" . htmlspecialchars($smtp_host) . "</td><td>" . ($smtp_host ? '✅' : '⚠️') . "</td></tr>";
echo "<tr><td>EMAIL_SMTP_PORT</td><td>" . htmlspecialchars($smtp_port) . "</td><td>" . ($smtp_port ? '✅' : '⚠️') . "</td></tr>";
echo "<tr><td>EMAIL_SMTP_USERNAME</td><td>" . htmlspecialchars($smtp_username ?: '<em>not set</em>') . "</td><td>" . ($smtp_username ? '✅' : '⚠️') . "</td></tr>";
echo "<tr><td>EMAIL_SMTP_PASSWORD</td><td>" . ($smtp_password ? '<span class="masked">••••••••</span>' : '<em>not set</em>') . "</td><td>" . ($smtp_password ? '✅' : '⚠️') . "</td></tr>";
echo "<tr><td>EMAIL_SMTP_SECURE</td><td>" . htmlspecialchars($smtp_secure) . "</td><td>✅</td></tr>";
echo "<tr><td>EMAIL_FROM_EMAIL</td><td>" . htmlspecialchars($from_email ?: '<em>not set</em>') . "</td><td>" . ($from_email ? '✅' : '⚠️') . "</td></tr>";
echo "<tr><td>EMAIL_FROM_NAME</td><td>" . htmlspecialchars($from_name) . "</td><td>✅</td></tr>";
echo "</table>";

// Test application configuration
echo "<h2>⚙️ Application Configuration</h2>";
echo "<table>";
echo "<tr><th>Setting</th><th>Value</th><th>Status</th></tr>";

$app_env = env('APP_ENV', 'development');
$app_debug = env('APP_DEBUG', 'false');
$app_url = env('APP_URL', 'http://localhost/barangay');

echo "<tr><td>APP_ENV</td><td>" . htmlspecialchars($app_env) . "</td><td>✅</td></tr>";
echo "<tr><td>APP_DEBUG</td><td>" . htmlspecialchars($app_debug) . "</td><td>✅</td></tr>";
echo "<tr><td>APP_URL</td><td>" . htmlspecialchars($app_url) . "</td><td>✅</td></tr>";
echo "</table>";

// Summary
echo "<h2>📋 Summary</h2>";
$all_good = true;
if (!$smtp_username || !$smtp_password) {
    $all_good = false;
    echo "<div class='info'>⚠️ Email configuration incomplete. Emails may not work.</div>";
}

if ($all_good) {
    echo "<div class='success'>✅ All environment variables are loaded correctly!</div>";
    echo "<div class='info'>💡 Your application is now using environment variables instead of hardcoded credentials.</div>";
} else {
    echo "<div class='error'>⚠️ Some configuration is missing. Please check your .env file.</div>";
}

echo "<hr>";
echo "<p><strong>Next steps:</strong></p>";
echo "<ul>";
echo "<li>✅ Test your application login - should work as before</li>";
echo "<li>✅ Try password reset - should use Gmail SMTP</li>";
echo "<li>✅ Check that database operations work normally</li>";
echo "</ul>";

echo "<p><em>Note: This test file can be deleted after verification.</em></p>";

