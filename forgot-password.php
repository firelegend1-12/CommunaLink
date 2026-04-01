<?php
/**
 * Forgot Password Page
 * Allows users to request a password reset
 */

// Include required files
require_once 'includes/functions.php';
require_once 'config/database.php';
require_once 'config/init.php';
require_once 'includes/auth.php';
require_once 'includes/otp_email_service.php';

// Apply security headers for login page
apply_page_security_headers('login');

// Initialize variables
$email = "";
$email_err = "";
$success_message = "";
$show_form = true; // Control whether the form is shown or not
$generic_reset_response = "If an account with that email exists, we've sent a password reset link to your email address.";

// Process form data when form is submitted
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    
    // Validate CSRF token
    if (!CSRFProtection::validateFromPost()) {
        $email_err = "Invalid security token. Please refresh the page and try again.";
        error_log("CSRF validation failed. POST token: " . ($_POST['csrf_token'] ?? 'NOT SET') . ", Session token: " . ($_SESSION['csrf_token'] ?? 'NOT SET'));
    } else {
        
        // Check if email is empty
        if (empty(trim($_POST["email"]))) {
            $email_err = "Please enter your email address.";
        } else {
            $email = trim($_POST["email"]);
            
            // Validate email format
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $email_err = "Please enter a valid email address.";
            }
        }
        
        // If no errors, process the request
        if (empty($email_err)) {
            try {
                // Check if email exists in users table
                $stmt = $pdo->prepare("SELECT id, username, fullname, reset_token, reset_token_expires FROM users WHERE email = ?");
                $stmt->execute([$email]);
                $user = $stmt->fetch();
                
                if ($user) {
                    $existing_token = trim((string) ($user['reset_token'] ?? ''));
                    $existing_expires = trim((string) ($user['reset_token_expires'] ?? ''));

                    // Reuse an existing unexpired token to avoid invalidating links when users request twice.
                    $can_reuse = false;
                    if ($existing_token !== '' && $existing_expires !== '') {
                        $expiry_ts = strtotime($existing_expires);
                        $can_reuse = ($expiry_ts !== false && $expiry_ts > time());
                    }

                    if ($can_reuse) {
                        $reset_token = $existing_token;
                    } else {
                        // Generate reset token
                        $reset_token = bin2hex(random_bytes(32));
                        $reset_expires = date('Y-m-d H:i:s', strtotime('+1 hour'));

                        // Store reset token as-is to match the exact emailed link token.
                        // reset-password.php keeps backward compatibility with legacy hashed tokens.
                        $stmt = $pdo->prepare("UPDATE users SET reset_token = ?, reset_token_expires = ? WHERE id = ?");
                        $stmt->execute([$reset_token, $reset_expires, $user['id']]);
                    }
                    
                    // Create reset link
                    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https://' : 'http://';
                    $basePath = rtrim(dirname($_SERVER['REQUEST_URI']), '/\\');
                    $reset_link = $scheme . $_SERVER['HTTP_HOST'] . $basePath . "/reset-password.php?token=" . $reset_token;

                    // Try to send email using PHPMailer Gmail SMTP
                    $email_sent = false;
                    $user_name = $user['fullname'] ?? $user['username'];
                    
                    try {
                        $email_sent = OTPEmailService::sendPasswordResetEmail($email, $user_name, $reset_link);
                    } catch (Exception $e) {
                        error_log('Password reset email exception for user ID: ' . (int) $user['id']);
                    }

                    if (!$email_sent) {
                        error_log('Password reset email delivery failed for user ID: ' . (int) $user['id']);
                    }

                    // Always return a generic response to avoid account and delivery-state disclosure.
                    $success_message = $generic_reset_response;
                    $show_form = false;
                    
                    // Log the password reset request
                    error_log("Password reset requested for user ID: " . $user['id'] . " (Email: " . $email . ") - Email sent: " . ($email_sent ? 'Yes' : 'No'));
                    
                } else {
                    // Don't reveal if email exists or not for security
                    $success_message = $generic_reset_response;
                    $show_form = false;
                }
                
            } catch (PDOException $e) {
                error_log("Password reset error: " . $e->getMessage());
                $email_err = "An error occurred. Please try again later.";
            }
        }
    }
}

// Page title
$page_title = "Forgot Password - CommuniLink";
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
                    <h1>Forgot Password</h1>
                    <p class="subtitle">
                        Enter your email address and we'll send you a link to reset your password
                    </p>

                    <?php 
                    if (!empty($success_message)) {
                        echo '<div class="alert alert-success"><p>' . $success_message . '</p></div>';
                    }
                    if (!empty($email_err)) {
                        echo '<div class="alert alert-error"><p>' . $email_err . '</p></div>';
                    }
                    ?>

                    <?php if ($show_form): ?>
                    <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="POST">
                        <?php echo CSRFProtection::getTokenField(); ?>
                        <div class="form-group">
                            <label for="email">Email Address</label>
                            <div class="input-wrapper">
                                <i class="fas fa-envelope input-icon"></i>
                                <input id="email" name="email" type="email" autocomplete="email" required placeholder="Enter your email address" value="<?php echo htmlspecialchars($email); ?>">
                            </div>
                        </div>
                        
                        <button type="submit" class="submit-btn">
                            Send Reset Link
                        </button>
                    </form>
                    <?php endif; ?>

                    <div class="form-links">
                        <a href="index.php" class="back-to-login-link">
                            <i class="fas fa-arrow-left"></i> Back to Sign In
                        </a>
                    </div>

                    <p style="text-align: center; margin-top: 2rem; color: var(--text-secondary);">
                        Don't have an account? <a href="register.php" class="link">Create Account</a>
                    </p>
                </div>
                
                <!-- Right Column - Image/Info -->
                <div class="auth-image-container">
                    <p>
                        Secure password recovery ensures your account remains protected while providing easy access when you need it most.
                    </p>
                </div>
            </div>
        </main>
    </div>
</body>
</html>



