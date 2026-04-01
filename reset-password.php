<?php
/**
 * Reset Password Page
 * Allows users to set a new password using their reset token
 */

// Start session first
session_start();

// Include required files
require_once 'includes/functions.php';
require_once 'config/database.php';
require_once 'config/init.php';
require_once 'includes/auth.php';
require_once 'includes/csrf.php';

// Apply security headers for login page
apply_page_security_headers('login');

// Initialize variables
$password = $confirm_password = "";
$password_err = $confirm_password_err = "";
$token_valid = false;
$token_error = "";
$success_message = "";

// Get token from URL and normalize it for email-client-safe validation.
$raw_token = isset($_GET['token']) ? trim((string) $_GET['token']) : '';
$token = strtolower((string) preg_replace('/[^a-f0-9]/i', '', $raw_token));

if (empty($token) || strlen($token) !== 64) {
    $token_error = "Invalid reset link. Please request a new password reset, then use the latest reset email link.";
} else {
    try {
        // Check if token exists and is valid. Support legacy plaintext tokens during transition.
        $token_hash = hash('sha256', $token);
        $stmt = $pdo->prepare("SELECT id, username, email, reset_token FROM users WHERE (reset_token = ? OR reset_token = ?) AND reset_token_expires > NOW()");
        $stmt->execute([$token_hash, $token]);
        $user = $stmt->fetch();
        
        if ($user) {
            $stored_token = (string) ($user['reset_token'] ?? '');
            $is_hashed_match = hash_equals($stored_token, $token_hash);
            $is_legacy_match = hash_equals($stored_token, $token);

            if ($is_hashed_match || $is_legacy_match) {
                $token_valid = true;
            } else {
                $token_error = "Reset link has expired or is invalid. Please request a new password reset, then use the latest reset email link.";
            }
        } else {
            $token_error = "Reset link has expired or is invalid. Please request a new password reset, then use the latest reset email link.";
        }
    } catch (PDOException $e) {
        error_log("Token validation error: " . $e->getMessage());
        $token_error = "An error occurred. Please try again later.";
    }
}

// Process form data when form is submitted
if ($_SERVER["REQUEST_METHOD"] == "POST" && $token_valid) {
    
    // Validate CSRF token
    if (!CSRFProtection::validateFromPost()) {
        $password_err = "Invalid security token. Please refresh the page and try again.";
    } else {
        
        // Validate password (must match Create Account requirements)
        if (empty(trim($_POST["password"]))) {
            $password_err = "Please enter a password.";
        } elseif (strlen(trim($_POST["password"])) < 8) {
            $password_err = "Password must be at least 8 characters long.";
        } elseif (!preg_match('/[0-9]/', trim($_POST["password"]))) {
            $password_err = "Password must contain at least one number (0-9).";
        } elseif (!preg_match('/[!@#$%^&*()_+\-=\[\]{};:\'",.<>?\/\\|`~]/', trim($_POST["password"]))) {
            $password_err = "Password must contain at least one special character (!@#$%^&*).";
        } else {
            $password = trim($_POST["password"]);
        }
        
        // Validate confirm password
        if (empty(trim($_POST["confirm_password"]))) {
            $confirm_password_err = "Please confirm password.";
        } else {
            $confirm_password = trim($_POST["confirm_password"]);
            if (empty($password_err) && ($password != $confirm_password)) {
                $confirm_password_err = "Password did not match.";
            }
        }
        
        // If no errors, update the password
        if (empty($password_err) && empty($confirm_password_err)) {
            try {
                // Hash the new password
                $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                
                $set_clauses = [
                    "password = ?",
                    "reset_token = NULL",
                    "reset_token_expires = NULL"
                ];
                $params = [$hashed_password];

                $status_col = $pdo->query("SHOW COLUMNS FROM `users` LIKE 'status'");
                if ($status_col && $status_col->fetch()) {
                    $set_clauses[] = "status = 'active'";
                }

                $verified_col = $pdo->query("SHOW COLUMNS FROM `users` LIKE 'email_verified'");
                if ($verified_col && $verified_col->fetch()) {
                    $set_clauses[] = "email_verified = 1";
                }

                $pw_required_col = $pdo->query("SHOW COLUMNS FROM `users` LIKE 'password_change_required'");
                if ($pw_required_col && $pw_required_col->fetch()) {
                    $set_clauses[] = "password_change_required = 0";
                }

                $invitation_sent_col = $pdo->query("SHOW COLUMNS FROM `users` LIKE 'invitation_sent_at'");
                if ($invitation_sent_col && $invitation_sent_col->fetch()) {
                    $set_clauses[] = "invitation_sent_at = NULL";
                }

                $invitation_expires_col = $pdo->query("SHOW COLUMNS FROM `users` LIKE 'invitation_expires_at'");
                if ($invitation_expires_col && $invitation_expires_col->fetch()) {
                    $set_clauses[] = "invitation_expires_at = NULL";
                }

                $params[] = (int) $user['id'];
                $stmt = $pdo->prepare("UPDATE users SET " . implode(', ', $set_clauses) . " WHERE id = ? AND reset_token_expires > NOW()");
                $stmt->execute($params);
                
                if ($stmt->rowCount() > 0) {
                    $success_message = "Password updated successfully! You can now sign in with your new password.";
                    
                    // Log the password reset with IP address
                    $ip_address = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
                    error_log("Password reset completed for user ID: {$user['id']} (Email: {$user['email']}) from IP: {$ip_address}");
                    
                    // Clear any rate limiting for this IP (successful reset)
                    if (isset($_SESSION['reset_requests'][$ip_address])) {
                        unset($_SESSION['reset_requests'][$ip_address]);
                    }
                } else {
                    $password_err = "Failed to update password. Please try again.";
                }
                
            } catch (PDOException $e) {
                error_log("Password update error: " . $e->getMessage());
                $password_err = "An error occurred. Please try again later.";
            }
        }
    }
}

// Page title
$page_title = "Reset Password - CommuniLink";
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?></title>
    <!-- Font Awesome Icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- Custom CSS -->
    <link rel="stylesheet" href="assets/css/auth.css">
    <link rel="icon" href="assets/svg/logos.jpg" type="image/jpeg">
</head>
<body>
    <div class="auth-container">
        <!-- Header -->
        <header class="auth-header">
            <div class="auth-header-left">
                <img src="assets/svg/logos.jpg" alt="CommuniLink Logo" style="height: 50px; width: auto;">
            </div>
        </header>

        <!-- Main Content -->
        <main class="auth-main">
            <img src="assets/svg/wave-bg.svg" alt="" class="wave-bg">
            <div class="auth-content">
                <!-- Left Column - Form -->
                <div class="auth-form-container">
                    <h1>Reset Password</h1>
                    
                    <?php if (!empty($token_error)): ?>
                        <div class="alert alert-error">
                            <p><?php echo $token_error; ?></p>
                            <div class="mt-4">
                                <a href="forgot-password.php" class="btn btn-primary">Request New Reset Link</a>
                            </div>
                        </div>
                    <?php elseif (!empty($success_message)): ?>
                        <div class="alert alert-success">
                            <p><?php echo $success_message; ?></p>
                            <div class="mt-4">
                                <a href="index.php" class="btn btn-primary">Go to Sign In</a>
                            </div>
                        </div>
                    <?php else: ?>
                        <p class="subtitle">
                            Enter your new password below
                        </p>

                        <?php 
                        if (!empty($password_err)) {
                            echo '<div class="alert alert-error"><p>' . $password_err . '</p></div>';
                        }
                        if (!empty($confirm_password_err)) {
                            echo '<div class="alert alert-error"><p>' . $confirm_password_err . '</p></div>';
                        }
                        ?>

                        <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]) . '?token=' . urlencode($token); ?>" method="POST" id="reset-form">
                            <?php echo CSRFProtection::getTokenField(); ?>
                            <div class="form-group">
                                <label for="password">New Password</label>
                                <div class="input-wrapper">
                                    <i class="fas fa-lock input-icon"></i>
                                    <input id="password" name="password" type="password" autocomplete="new-password" required placeholder="Enter new password" minlength="8">
                                </div>
                                <small class="form-help" style="display: block; margin-top: 0.75rem;">
                                    <div id="req-length" style="color: #ef4444;">✓ Password must be at least 8 characters</div>
                                    <div id="req-number" style="color: #ef4444;">✓ Include at least one number (0-9)</div>
                                    <div id="req-special" style="color: #ef4444;">✓ Include at least one special character (!@#$%^&*)</div>
                                </small>
                            </div>
                            
                            <div class="form-group">
                                <label for="confirm_password">Confirm New Password</label>
                                <div class="input-wrapper">
                                    <i class="fas fa-lock input-icon"></i>
                                    <input id="confirm_password" name="confirm_password" type="password" autocomplete="new-password" required placeholder="Confirm new password" minlength="8">
                                </div>
                                <small id="req-match" class="form-help" style="color: #ef4444; display: block; margin-top: 0.75rem;">✓ Both Passwords Matched!</small>
                            </div>
                            
                            <button type="submit" class="submit-btn" id="submit-btn">
                                <span class="btn-text">Reset Password</span>
                                <span class="btn-loading" style="display: none;">
                                    <i class="fas fa-spinner fa-spin"></i> Processing...
                                </span>
                            </button>
                        </form>
                        
                    <?php endif; ?>

                    <div class="form-links">
                        <a href="index.php" class="back-to-login-link">
                            <i class="fas fa-arrow-left"></i> Back to Sign In
                        </a>
                    </div>
                </div>
                
                <!-- Right Column - Image/Info -->
                <div class="auth-image-container">
                    <p>
                        Choose a strong password to keep your account secure and protect your personal information.
                    </p>
                </div>
            </div>
        </main>
    </div>
    <script src="assets/js/reset-password.js?v=<?= filemtime('assets/js/reset-password.js') ?>" defer></script>
</body>
</html>



