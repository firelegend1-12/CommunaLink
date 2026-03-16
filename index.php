<?php
/**
 * Login Page
 * Main entry point for the Barangay Reports Admin System
 */

// Include required files
require_once 'includes/functions.php';
require_once 'config/database.php';
require_once 'config/init.php';
require_once 'includes/auth.php';

// Apply security headers for login page
apply_page_security_headers('login');

// Initialize variables
$username = $password = "";
$username_err = $password_err = $login_err = "";

// Check if user is already logged in
if (is_logged_in()) {
    redirect_to('admin/index.php');
}

// Process form data when form is submitted
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    
    // Validate CSRF token
    if (!CSRFProtection::validateFromPost()) {
        $login_err = "Invalid security token. Please refresh the page and try again.";
    } else {
        
        // Check rate limiting before processing login
        $rate_limit_status = check_login_rate_limit();
        if (!$rate_limit_status['allowed']) {
            $login_err = $rate_limit_status['message'];
        } else {
            
            // Check if username is empty
    if (empty(trim($_POST["username"]))) {
        $username_err = "Please enter username.";
    } else {
        $username = trim($_POST["username"]);
    }
    
    // Check if password is empty
    if (empty(trim($_POST["password"]))) {
        $password_err = "Please enter your password.";
    } else {
        $password = trim($_POST["password"]);
    }
    
    // Validate credentials
    if (empty($username_err) && empty($password_err)) {
        $user = authenticate_user($username, $password, $pdo);
        
        if ($user) {
            // Password is correct, start a new session
            create_session($user, $pdo);
            
            // Redirect user based on role
            if ($user['role'] === 'resident') {
                redirect_to('resident/dashboard.php');
            } else {
                redirect_to('admin/index.php');
            }
        } else {
            // Password is not valid, display generic error
            $login_err = "Invalid username or password.";
            
            // Record failed login attempt
            record_login_attempt();
        }
        
        // Record successful login attempt (resets rate limiting)
        if (empty($username_err) && empty($password_err) && $user) {
            reset_login_rate_limit();
        }
    }
    } // Close rate limiting check else block
    } // Close CSRF validation else block
}

// Page title
$page_title = "Sign In - CommuniLink";
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
    <script src="assets/js/login.js" defer></script>
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
                    <h1>Welcome Back</h1>
                    <p class="subtitle">
                        Sign in to your account to continue your barangay management journey
                    </p>

                    <?php 
                    if(isset($_SESSION['error_message'])) {
                        echo '<div class="alert alert-error"><p>' . $_SESSION['error_message'] . '</p></div>';
                        unset($_SESSION['error_message']);
                    }
                    if (isset($_SESSION['success_message'])) {
                        echo '<div class="alert alert-success"><p>' . $_SESSION['success_message'] . '</p></div>';
                        unset($_SESSION['success_message']);
                    }
                    if (!empty($login_err)) {
                        echo '<div class="alert alert-error"><p>' . $login_err . '</p></div>';
                    }
                    if (!empty($username_err)) {
                        echo '<div class="alert alert-error"><p>' . $username_err . '</p></div>';
                    }
                    if (!empty($password_err)) {
                        echo '<div class="alert alert-error"><p>' . $password_err . '</p></div>';
                    }
                    
                    // Display rate limit information
                    $rate_limit_info = get_login_rate_limit_info();
                    if ($rate_limit_info['enabled'] && $rate_limit_info['current_attempts'] > 0) {
                        $remaining = (int)$rate_limit_info['remaining_attempts'];
                        $is_locked = !empty($rate_limit_info['is_locked']);
                        if ($remaining > 0 && !$is_locked) {
                            $severityClass = $remaining <= 2 ? 'style="border-left-color:#ef4444;background-color:rgba(239,68,68,0.12);color:#fca5a5;"' : '';
                            echo '<div class="alert alert-warning" ' . $severityClass . '>
                                    <p style="display:flex;align-items:center;gap:.5rem;margin:0;">
                                        <span aria-hidden="true" style="display:inline-flex;align-items:center;justify-content:center;width:22px;height:22px;border-radius:50%;background:#f59e0b33;color:#fbbf24;">⚠️</span>
                                        <span>Login attempts remaining: <strong style="color:#fbbf24">' . $remaining . '</strong></span>
                                    </p>
                                  </div>';
                        } elseif ($is_locked) {
                            $minutes = ceil($rate_limit_info['lockout_remaining'] / 60);
                            echo '<div class="alert alert-error"><p>🚫 Account temporarily locked. Please try again in ' . $minutes . ' minutes.</p></div>';
                        }
                    }
                    ?>

                    <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="POST">
                        <?php echo CSRFProtection::getTokenField(); ?>
                        <div class="form-group">
                            <label for="username">Email Address</label>
                            <div class="input-wrapper">
                                <i class="fas fa-envelope input-icon"></i>
                                <input id="username" name="username" type="text" autocomplete="username" required placeholder="example@gmail.com">
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label for="password">Password</label>
                            <div class="input-wrapper">
                                <i class="fas fa-lock input-icon"></i>
                                <input id="password" name="password" type="password" autocomplete="current-password" required placeholder="Enter your password" style="padding-right: 2.75rem; position: relative; z-index: 1;">
                                <button type="button" id="toggle-password" aria-label="Show password" style="background: transparent; border: none; position: absolute; right: 12px; top: 50%; transform: translateY(-50%); color: #cbd5e1; cursor: pointer; z-index: 3; width: 24px; height: 24px; display: flex; align-items: center; justify-content: center;">
                                    <svg id="icon-eye" xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                        <path d="M1 12s4-7 11-7 11 7 11 7-4 7-11 7-11-7-11-7z"></path>
                                        <circle cx="12" cy="12" r="3"></circle>
                                    </svg>
                                    <svg id="icon-eye-off" xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="display:none;">
                                        <path d="M17.94 17.94A10.94 10.94 0 0 1 12 20c-7 0-11-8-11-8a21.77 21.77 0 0 1 5.06-5.94"></path>
                                        <path d="M1 1l22 22"></path>
                                    </svg>
                                </button>
                            </div>
                        </div>
                        
                        <button type="submit" class="submit-btn">
                            Sign In
                        </button>
                    </form>

                    <div class="form-links">
                        <a href="forgot-password.php" class="forgot-password-link">Forgot Password?</a>
                    </div>

                    <p style="text-align: center; margin-top: 2rem; color: var(--text-secondary);">
                        Don't have an account? <a href="register.php" class="link">Create Account</a>
                    </p>
                </div>
                
                <!-- Right Column - Image/Info -->
                <div class="auth-image-container">
                    <img src="assets/sk.svg" alt="SK Logo">
                    <p>
                        In today's rapidly evolving communities, efficient and secure identification systems are essential for streamlined governance and effective service delivery.
                    </p>
                </div>
            </div>
        </main>
    </div>
</body>
</body>
</html> 