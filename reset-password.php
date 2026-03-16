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

// Get token from URL
$token = isset($_GET['token']) ? trim($_GET['token']) : '';

if (empty($token)) {
    $token_error = "Invalid reset link. Please request a new password reset.";
} else {
    try {
        // Check if token exists and is valid
        $stmt = $pdo->prepare("SELECT id, username, email, reset_token FROM users WHERE reset_token = ? AND reset_token_expires > NOW() AND status = 'active'");
        $stmt->execute([$token]);
        $user = $stmt->fetch();
        
        if ($user) {
            // Additional security: Check if token was already used
            if (isset($user['reset_token']) && $user['reset_token'] === null) {
                $token_error = "This reset link has already been used. Please request a new password reset.";
            } else {
                $token_valid = true;
            }
        } else {
            $token_error = "Reset link has expired or is invalid. Please request a new password reset.";
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
        
        // Validate password
        if (empty(trim($_POST["password"]))) {
            $password_err = "Please enter a password.";
        } elseif (strlen(trim($_POST["password"])) < 6) {
            $password_err = "Password must have at least 6 characters.";
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
                
                // Update password and clear reset token
                $stmt = $pdo->prepare("UPDATE users SET password = ?, reset_token = NULL, reset_token_expires = NULL WHERE reset_token = ?");
                $stmt->execute([$hashed_password, $token]);
                
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
                                    <input id="password" name="password" type="password" autocomplete="new-password" required placeholder="Enter new password" minlength="6">
                                </div>
                                <small class="form-help">Password must be at least 6 characters long</small>
                            </div>
                            
                            <div class="form-group">
                                <label for="confirm_password">Confirm New Password</label>
                                <div class="input-wrapper">
                                    <i class="fas fa-lock input-icon"></i>
                                    <input id="confirm_password" name="confirm_password" type="password" autocomplete="new-password" required placeholder="Confirm new password" minlength="6">
                                </div>
                                <small class="form-help">Both passwords must match</small>
                            </div>
                            
                            <button type="submit" class="submit-btn" id="submit-btn">
                                <span class="btn-text">Reset Password</span>
                                <span class="btn-loading" style="display: none;">
                                    <i class="fas fa-spinner fa-spin"></i> Processing...
                                </span>
                            </button>
                        </form>
                        
                        <script>
                        document.getElementById('reset-form').addEventListener('submit', function(e) {
                            const password = document.getElementById('password').value;
                            const confirmPassword = document.getElementById('confirm_password').value;
                            const submitBtn = document.getElementById('submit-btn');
                            const btnText = submitBtn.querySelector('.btn-text');
                            const btnLoading = submitBtn.querySelector('.btn-loading');
                            
                            // Basic client-side validation
                            if (password !== confirmPassword) {
                                e.preventDefault();
                                alert('Passwords do not match!');
                                return;
                            }
                            
                            if (password.length < 6) {
                                e.preventDefault();
                                alert('Password must be at least 6 characters long!');
                                return;
                            }
                            
                            // Show loading state
                            btnText.style.display = 'none';
                            btnLoading.style.display = 'inline-block';
                            submitBtn.disabled = true;
                        });
                        </script>
                    <?php endif; ?>

                    <div class="form-links">
                        <a href="index.php" class="back-to-login-link">
                            <i class="fas fa-arrow-left"></i> Back to Sign In
                        </a>
                    </div>
                </div>
                
                <!-- Right Column - Image/Info -->
                <div class="auth-image-container">
                    <img src="assets/sk.svg" alt="SK Logo">
                    <p>
                        Choose a strong password to keep your account secure and protect your personal information.
                    </p>
                </div>
            </div>
        </main>
    </div>
</body>
</html>



