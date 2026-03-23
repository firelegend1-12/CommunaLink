<?php
/**
 * Forgot Password Page
 * Allows users to request a password reset
 */

// Include required files
require_once 'includes/functions.php';
require_once 'config/database.php';
require_once 'config/init.php';
require_once 'config/email_config.php'; // Load email configuration
require_once 'includes/auth.php';

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
                $stmt = $pdo->prepare("SELECT id, username, fullname FROM users WHERE email = ? AND status = 'active'");
                $stmt->execute([$email]);
                $user = $stmt->fetch();
                
                if ($user) {
                    // Generate reset token
                    $reset_token = bin2hex(random_bytes(32));
                    $reset_token_hash = hash('sha256', $reset_token);
                    $reset_expires = date('Y-m-d H:i:s', strtotime('+1 hour'));
                    
                    // Store reset token hash in database
                    $stmt = $pdo->prepare("UPDATE users SET reset_token = ?, reset_token_expires = ? WHERE id = ?");
                    $stmt->execute([$reset_token_hash, $reset_expires, $user['id']]);
                    
                    // Create reset link
                    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https://' : 'http://';
                    $basePath = rtrim(dirname($_SERVER['REQUEST_URI']), '/\\');
                    $reset_link = $scheme . $_SERVER['HTTP_HOST'] . $basePath . "/reset-password.php?token=" . $reset_token;

                    // Try to send email
                    $email_sent = false;
                    $user_name = $user['fullname'] ?? $user['username'];
                    $email_error = '';
                    
                    // Try Mailgun first (if configured)
                    if (defined('MAILGUN_API_KEY') && !empty(MAILGUN_API_KEY) && defined('MAILGUN_DOMAIN') && !empty(MAILGUN_DOMAIN)) {
                        try {
                            require_once 'includes/mailgun_sender.php';
                            $mailgun = new MailgunSender();
                            $email_sent = $mailgun->sendPasswordResetEmail($email, $user_name, $reset_link);
                            if (!$email_sent) {
                                $email_error = 'mailgun_send_failed';
                            }
                        } catch (Exception $e) {
                            $email_error = 'mailgun_exception';
                            error_log('Mailgun email error occurred during password reset flow.');
                        }
                    }
                    
                    // If Mailgun failed or not configured, try Gmail SMTP
                    // Try the fixed version first (works better on Windows)
                    if (!$email_sent) {
                        try {
                            if (file_exists('includes/gmail_smtp_sender_fixed.php')) {
                                require_once 'includes/gmail_smtp_sender_fixed.php';
                                $gmail = new GmailSMTPSenderFixed();
                            } else {
                                require_once 'includes/gmail_smtp_sender.php';
                                $gmail = new GmailSMTPSender();
                            }
                            $email_sent = $gmail->sendPasswordResetEmail($email, $user_name, $reset_link);
                            if (!$email_sent) {
                                // Check if credentials are configured
                                $has_username = defined('EMAIL_SMTP_USERNAME') && !empty(EMAIL_SMTP_USERNAME);
                                $has_password = defined('EMAIL_SMTP_PASSWORD') && !empty(EMAIL_SMTP_PASSWORD);
                                $has_from_email = defined('EMAIL_FROM_EMAIL') && !empty(EMAIL_FROM_EMAIL);
                                if (!$has_username || !$has_password || !$has_from_email) {
                                    $email_error = trim($email_error . ' smtp_not_configured');
                                } else {
                                    $email_error = trim($email_error . ' smtp_send_failed');
                                }
                            }
                        } catch (Exception $e) {
                            $email_error = trim($email_error . ' smtp_exception');
                            error_log('Gmail SMTP email error occurred during password reset flow.');
                        }
                    }
                    
                    // Final fallback: Try PHP's mail() function (works if sendmail is configured)
                    if (!$email_sent) {
                        try {
                            require_once 'includes/simple_smtp_sender.php';
                            $simple = new SimpleSMTP();
                            $email_sent = $simple->sendPasswordResetEmail($email, $user_name, $reset_link);
                        } catch (Exception $e) {
                            $email_error = trim($email_error . ' simple_mail_exception');
                            error_log('Simple mail fallback error occurred during password reset flow.');
                        }
                    }

                    if (!$email_sent) {
                        error_log('Password reset email delivery failed for user ID: ' . (int) $user['id'] . '. Failure codes: ' . trim($email_error));
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
                    <img src="assets/sk.svg" alt="SK Logo">
                    <p>
                        Secure password recovery ensures your account remains protected while providing easy access when you need it most.
                    </p>
                </div>
            </div>
        </main>
    </div>
</body>
</html>



