<?php
/**
 * Email Configuration Test
 * Test if your email settings are working correctly
 * 
 * Usage: Development only. Run via CLI or as authenticated admin/official in non-production.
 */

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

// Load configuration
require_once __DIR__ . '/config/email_config.php';

echo "<h1>📧 Email Configuration Test</h1>";
echo "<style>
    body { font-family: Arial, sans-serif; max-width: 800px; margin: 50px auto; padding: 20px; }
    .success { background: #d4edda; color: #155724; padding: 15px; border-radius: 5px; margin: 10px 0; }
    .error { background: #f8d7da; color: #721c24; padding: 15px; border-radius: 5px; margin: 10px 0; }
    .info { background: #d1ecf1; color: #0c5460; padding: 15px; border-radius: 5px; margin: 10px 0; }
    .warning { background: #fff3cd; color: #856404; padding: 15px; border-radius: 5px; margin: 10px 0; }
    table { width: 100%; border-collapse: collapse; margin: 20px 0; }
    th, td { padding: 12px; text-align: left; border-bottom: 1px solid #ddd; }
    th { background-color: #4F46E5; color: white; }
    .masked { color: #666; font-family: monospace; }
</style>";

// Check Gmail SMTP Configuration
echo "<h2>Gmail SMTP Configuration</h2>";
echo "<table>";
echo "<tr><th>Setting</th><th>Value</th><th>Status</th></tr>";

$smtp_host = defined('EMAIL_SMTP_HOST') ? EMAIL_SMTP_HOST : 'not set';
$smtp_port = defined('EMAIL_SMTP_PORT') ? EMAIL_SMTP_PORT : 'not set';
$smtp_username = defined('EMAIL_SMTP_USERNAME') ? EMAIL_SMTP_USERNAME : 'not set';
$smtp_password = defined('EMAIL_SMTP_PASSWORD') ? EMAIL_SMTP_PASSWORD : 'not set';
$smtp_secure = defined('EMAIL_SMTP_SECURE') ? EMAIL_SMTP_SECURE : 'not set';
$from_email = defined('EMAIL_FROM_EMAIL') ? EMAIL_FROM_EMAIL : 'not set';

$has_username = !empty($smtp_username) && $smtp_username !== 'not set';
$has_password = !empty($smtp_password) && $smtp_password !== 'not set';

echo "<tr><td>EMAIL_SMTP_HOST</td><td>" . htmlspecialchars($smtp_host) . "</td><td>" . ($smtp_host !== 'not set' ? '✅' : '⚠️') . "</td></tr>";
echo "<tr><td>EMAIL_SMTP_PORT</td><td>" . htmlspecialchars($smtp_port) . "</td><td>" . ($smtp_port !== 'not set' ? '✅' : '⚠️') . "</td></tr>";
echo "<tr><td>EMAIL_SMTP_USERNAME</td><td>" . htmlspecialchars($smtp_username) . "</td><td>" . ($has_username ? '✅' : '⚠️ Not configured') . "</td></tr>";
echo "<tr><td>EMAIL_SMTP_PASSWORD</td><td>" . ($has_password ? '<span class="masked">••••••••</span>' : '<em>not set</em>') . "</td><td>" . ($has_password ? '✅' : '⚠️ Not configured') . "</td></tr>";
echo "<tr><td>EMAIL_SMTP_SECURE</td><td>" . htmlspecialchars($smtp_secure) . "</td><td>" . ($smtp_secure !== 'not set' ? '✅' : '⚠️') . "</td></tr>";
echo "<tr><td>EMAIL_FROM_EMAIL</td><td>" . htmlspecialchars($from_email) . "</td><td>" . ($from_email !== 'not set' ? '✅' : '⚠️') . "</td></tr>";
echo "</table>";

// Test email sending
if ($has_username && $has_password) {
    echo "<h2>🧪 Test Email Sending</h2>";
    
    if (isset($_GET['test_email']) && !empty($_GET['test_email'])) {
        $test_email = filter_var($_GET['test_email'], FILTER_VALIDATE_EMAIL);
        
        if ($test_email) {
            echo "<div class='info'>Attempting to send test email to: " . htmlspecialchars($test_email) . "</div>";
            
            try {
                // Try the fixed version first (uses stream_socket_client which works better on Windows)
                if (file_exists(__DIR__ . '/includes/gmail_smtp_sender_fixed.php')) {
                    require_once __DIR__ . '/includes/gmail_smtp_sender_fixed.php';
                    $gmail = new GmailSMTPSenderFixed();
                } else {
                    require_once __DIR__ . '/includes/gmail_smtp_sender.php';
                    $gmail = new GmailSMTPSender();
                }
                
                $test_link = "http://localhost/barangay/reset-password.php?token=test123";
                $result = $gmail->sendPasswordResetEmail($test_email, 'Test User', $test_link);
                
                if ($result) {
                    echo "<div class='success'>✅ Test email sent successfully! Check your inbox at: " . htmlspecialchars($test_email) . "</div>";
                } else {
                    echo "<div class='error'>❌ Failed to send test email.</div>";
                    
                    // Check PHP error log
                    $error_log_path = ini_get('error_log');
                    if (empty($error_log_path)) {
                        $error_log_path = 'C:\xampp\php\logs\php_error_log'; // Default XAMPP location
                    }
                    
                    // Also check Apache error log
                    $apache_error_log = 'C:\xampp\apache\logs\error.log';
                    
                    $error_found = false;
                    if ($error_log_path && file_exists($error_log_path)) {
                        $recent_errors = tail($error_log_path, 15);
                        if ($recent_errors && stripos($recent_errors, 'SMTP') !== false) {
                            echo "<div class='info'><strong>Recent PHP error log entries (SMTP related):</strong><pre style='background: #f5f5f5; padding: 10px; border-radius: 5px; overflow-x: auto; max-height: 300px; overflow-y: auto;'>" . htmlspecialchars($recent_errors) . "</pre></div>";
                            $error_found = true;
                        }
                    }
                    
                    if (!$error_found && file_exists($apache_error_log)) {
                        $recent_errors = tail($apache_error_log, 15);
                        if ($recent_errors && stripos($recent_errors, 'SMTP') !== false) {
                            echo "<div class='info'><strong>Recent Apache error log entries (SMTP related):</strong><pre style='background: #f5f5f5; padding: 10px; border-radius: 5px; overflow-x: auto; max-height: 300px; overflow-y: auto;'>" . htmlspecialchars($recent_errors) . "</pre></div>";
                        }
                    }
                    
                    echo "<div class='warning'><strong>Common issues and solutions:</strong><ul>";
                    echo "<li><strong>Gmail app password incorrect:</strong> Generate a new app password from Google Account > Security > App Passwords</li>";
                    echo "<li><strong>2-Factor Authentication not enabled:</strong> Must be enabled to use app passwords</li>";
                    echo "<li><strong>Firewall blocking SMTP:</strong> Allow outbound connections on port 465 (SSL)</li>";
                    echo "<li><strong>Gmail blocking connection:</strong> Try using port 587 with TLS instead</li>";
                    echo "<li><strong>Wrong email in .env:</strong> Make sure EMAIL_SMTP_USERNAME matches the Gmail account</li>";
                    echo "</ul></div>";
                    
                    echo "<div class='info'><strong>Debug Info:</strong><ul>";
                    echo "<li>SMTP Host: " . htmlspecialchars($smtp_host) . "</li>";
                    echo "<li>SMTP Port: " . htmlspecialchars($smtp_port) . "</li>";
                    echo "<li>SMTP Username: " . htmlspecialchars($smtp_username) . "</li>";
                    echo "<li>SMTP Secure: " . htmlspecialchars($smtp_secure) . "</li>";
                    echo "<li>From Email: " . htmlspecialchars($from_email) . "</li>";
                    echo "</ul></div>";
                }
            } catch (Exception $e) {
                echo "<div class='error'>❌ Error: " . htmlspecialchars($e->getMessage()) . "</div>";
            }
        } else {
            echo "<div class='error'>Invalid email address provided.</div>";
        }
    } else {
        echo "<div class='info'>";
        echo "<p>Enter an email address to test sending:</p>";
        echo "<form method='GET'>";
        echo "<input type='email' name='test_email' placeholder='your-email@example.com' required style='padding: 10px; width: 300px; margin-right: 10px;'>";
        echo "<button type='submit' style='padding: 10px 20px; background: #4F46E5; color: white; border: none; border-radius: 5px; cursor: pointer;'>Send Test Email</button>";
        echo "</form>";
        echo "</div>";
    }
} else {
    echo "<div class='warning'>⚠️ Gmail SMTP credentials are not fully configured. Please check your .env file.</div>";
}

echo "<hr>";
echo "<p><strong>Next steps:</strong></p>";
echo "<ul>";
echo "<li>✅ If test email works, password reset emails should work too</li>";
echo "<li>✅ If test fails, check your Gmail app password in .env file</li>";
echo "<li>✅ Make sure 2-Factor Authentication is enabled on your Gmail account</li>";
echo "<li>✅ Verify the app password was generated correctly</li>";
echo "</ul>";

echo "<p><em>Note: This test file can be deleted after verification.</em></p>";

// Helper function to read last N lines of a file
function tail($filepath, $lines = 10) {
    if (!file_exists($filepath)) {
        return "Error log file not found: $filepath";
    }
    $file = file($filepath);
    return implode("", array_slice($file, -$lines));
}

// Get PHP error log location
$php_error_log = ini_get('error_log');
if (empty($php_error_log)) {
    $php_error_log = 'C:\xampp\php\logs\php_error_log'; // Default XAMPP location
}

