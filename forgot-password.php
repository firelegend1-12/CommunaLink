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
$info_message = "";
$show_form = true; // Control whether the form is shown or not

// One-time UI flash message (PRG pattern: POST -> redirect -> GET)
$flash = $_SESSION['forgot_password_flash'] ?? null;
if (is_array($flash) && isset($flash['type'], $flash['message'])) {
    $type = (string) $flash['type'];
    $message = (string) $flash['message'];
    if ($type === 'success') {
        $success_message = $message;
        $show_form = false;
    } elseif ($type === 'info') {
        $info_message = $message;
        $show_form = true;
    } elseif ($type === 'error') {
        $email_err = $message;
        $show_form = true;
    }
    unset($_SESSION['forgot_password_flash']);
}

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
                $stmt = $pdo->prepare("SELECT id, username, fullname FROM users WHERE email = ?");
                $stmt->execute([$email]);
                $user = $stmt->fetch();
                
                if ($user) {
                    $reset_token = bin2hex(random_bytes(32));
                    $reset_token_hash = hash('sha256', $reset_token);
                    $reset_expires = date('Y-m-d H:i:s', strtotime('+1 hour'));

                    $stmt = $pdo->prepare("UPDATE users SET reset_token = ?, reset_token_expires = ? WHERE id = ?");
                    $stmt->execute([$reset_token_hash, $reset_expires, $user['id']]);
                    
                    // Create reset link
                    $reset_path = '/reset-password.php?token=' . urlencode($reset_token);
                    $configured_app_url = rtrim((string) env('APP_URL', ''), '/');
                    $reset_link = $configured_app_url !== '' ? $configured_app_url . $reset_path : app_url($reset_path);

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
                        $_SESSION['forgot_password_flash'] = [
                            'type' => 'error',
                            'message' => "We found your account, but the reset email could not be sent right now. Please try again in a minute."
                        ];
                    } else {
                        $_SESSION['forgot_password_flash'] = [
                            'type' => 'success',
                            'message' => "Reset link sent. Check your inbox and spam folder."
                        ];
                    }

                    // Log the password reset request
                    error_log("Password reset requested for user ID: " . $user['id'] . " (Email: " . $email . ") - Email sent: " . ($email_sent ? 'Yes' : 'No'));

                } else {
                    $_SESSION['forgot_password_flash'] = [
                        'type' => 'info',
                        'message' => "No account was found for that email address. Please check the spelling and try again."
                    ];
                }

                // POST/Redirect/GET: prevents resend on refresh and makes the UI message reliable.
                redirect_to(app_url('/forgot-password.php'));
                
            } catch (PDOException $e) {
                error_log("Password reset error: " . $e->getMessage());
                $email_err = "An error occurred. Please try again later.";
            }
        }
    }
}

// Page title
$page_title = "Forgot Password - CommunaLink";
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Barangay Pakiad</title>
    <!-- Font Awesome Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@fortawesome/fontawesome-free@6.4.0/css/all.min.css">
    <!-- Custom CSS -->
    <link rel="stylesheet" href="<?= htmlspecialchars(app_url('/assets/css/auth.css')) ?>?v=<?= filemtime(__DIR__ . '/assets/css/auth.css') ?>">
    <link rel="icon" href="assets/images/barangay-logo.png" type="image/png">
</head>
<body>
    <div class="auth-container">
        <!-- Header -->
        <header class="auth-header">
            <div class="auth-header-left">
                <img src="assets/images/barangay-logo.png" alt="Barangay Logo" style="height: 72px; width: auto;">
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
                        echo '<div class="alert alert-success"><p>' . htmlspecialchars($success_message) . '</p></div>';
                    }
                    if (!empty($info_message)) {
                        echo '<div class="alert alert-info"><p>' . htmlspecialchars($info_message) . '</p></div>';
                    }
                    if (!empty($email_err)) {
                        echo '<div class="alert alert-error"><p>' . htmlspecialchars($email_err) . '</p></div>';
                    }
                    ?>

                    <?php if ($show_form): ?>
                    <form action="<?php echo htmlspecialchars(app_url('/forgot-password.php')); ?>" method="POST">
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




