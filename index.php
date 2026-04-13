<?php
/**
 * Login Page
 * Main entry point for the Barangay Reports Admin System
 */

// App Engine standard may route all requests through index.php.
// Dispatch known PHP paths to their actual script files when present.
$request_path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
$relative_path = ltrim((string) $request_path, '/');

// Normalize duplicated front-controller paths like /index.php/index.php.
if (stripos($relative_path, 'index.php/') === 0) {
    $normalized_tail = ltrim(substr($relative_path, strlen('index.php/')), '/');
    $canonical_path = ($normalized_tail === '' || $normalized_tail === 'index.php')
        ? '/index.php'
        : '/' . $normalized_tail;

    header('Location: ' . $canonical_path, true, 302);
    exit;
}
if ($relative_path === 'index.php') {
    $relative_path = '';
}

if ($relative_path !== '' && $relative_path !== 'index.php') {
    $normalized_relative = str_replace('/', DIRECTORY_SEPARATOR, $relative_path);
    $normalized_relative_lower = strtolower($normalized_relative);
    $project_root = realpath(__DIR__);
    $target_path = realpath(__DIR__ . DIRECTORY_SEPARATOR . $normalized_relative);

    $allowed_internal_scripts = [
        strtolower('includes' . DIRECTORY_SEPARATOR . 'logout.php'),
        strtolower('includes' . DIRECTORY_SEPARATOR . 'register-handler.php'),
        strtolower('includes' . DIRECTORY_SEPARATOR . 'verify-otp-handler.php'),
        strtolower('includes' . DIRECTORY_SEPARATOR . 'resend-otp-handler.php'),
    ];
    $is_allowed_internal_script = in_array($normalized_relative_lower, $allowed_internal_scripts, true);

    $blocked_prefixes = [
        'includes' . DIRECTORY_SEPARATOR,
        'config' . DIRECTORY_SEPARATOR,
        'vendor' . DIRECTORY_SEPARATOR,
        'scripts' . DIRECTORY_SEPARATOR,
        'migrations' . DIRECTORY_SEPARATOR,
        'logs' . DIRECTORY_SEPARATOR,
        'tmp' . DIRECTORY_SEPARATOR,
        'cache' . DIRECTORY_SEPARATOR,
    ];

    $is_blocked = false;
    foreach ($blocked_prefixes as $prefix) {
        if (!$is_allowed_internal_script && stripos($normalized_relative, $prefix) === 0) {
            $is_blocked = true;
            break;
        }
    }

    if (!$is_blocked && $project_root && $target_path && is_file($target_path)) {
        $is_inside_root = (strpos($target_path, $project_root . DIRECTORY_SEPARATOR) === 0);
        $is_php_file = (strtolower((string) pathinfo($target_path, PATHINFO_EXTENSION)) === 'php');

        if ($is_inside_root && $is_php_file) {
            $target_dir = dirname($target_path);
            if (is_dir($target_dir)) {
                @chdir($target_dir);
            }
            require $target_path;
            exit;
        }
    }

    if (strtolower((string) pathinfo($relative_path, PATHINFO_EXTENSION)) === 'php') {
        http_response_code(404);
        echo '404 Not Found';
        exit;
    }
}

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
    if (isset($_SESSION['role']) && $_SESSION['role'] === 'resident') {
        redirect_to('resident/dashboard.php');
    } else {
        redirect_to('admin/index.php');
    }
}

// Process form data when form is submitted
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    
    // Validate CSRF token
    if (!CSRFProtection::validateFromPost()) {
        $login_err = "Invalid security token. Please refresh the page and try again.";
    } else {
        $rate_limit_identifier = get_login_rate_limit_identifier($_POST['username'] ?? null);
        
        // Check rate limiting before processing login
        $rate_limit_status = check_login_rate_limit($rate_limit_identifier);
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
            $session_result = create_session($user, $pdo);
            if (!$session_result['success']) {
                $login_err = $session_result['message'] ?? 'Unable to sign in due to active session limits.';
                $user = false;
            }
            
            // Redirect user based on role
            if ($user) {
                if ($user['role'] === 'resident') {
                    redirect_to('resident/dashboard.php');
                } else {
                    redirect_to('admin/index.php');
                }
            }
        } else {
            // Password is not valid, display generic error
            if (empty($login_err)) {
                $auth_specific_error = $_SESSION['login_error_message'] ?? '';
                if (!empty($auth_specific_error)) {
                    $login_err = $auth_specific_error;
                    unset($_SESSION['login_error_message']);
                } else {
                    $login_err = "Invalid username or password.";
                }
            }
            
            // Record failed login attempt
            record_login_attempt($rate_limit_identifier);
        }
        
        // Record successful login attempt (resets rate limiting)
        if (empty($username_err) && empty($password_err) && $user) {
            reset_login_rate_limit($rate_limit_identifier);
        }
    }
    } // Close rate limiting check else block
    } // Close CSRF validation else block
}

// Page title
$page_title = "Sign In - Communalink";
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?></title>
    <!-- Font Awesome Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@fortawesome/fontawesome-free@6.4.0/css/all.min.css">
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
                <img src="assets/svg/logos.jpg" alt="CommunaLink Logo" style="height: 50px; width: auto;">
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
                    $display_rate_limit_identifier = get_login_rate_limit_identifier($_POST['username'] ?? null);
                    $rate_limit_info = get_login_rate_limit_info($display_rate_limit_identifier);
                    if ($rate_limit_info['enabled']) {
                        $is_locked = !empty($rate_limit_info['is_locked']);
                        $remaining = (int)$rate_limit_info['remaining_attempts'];
                        
                        // We only show the countdown if NOT locked and attempts > 0
                        if (!$is_locked && $rate_limit_info['current_attempts'] > 0 && $remaining > 0) {
                            $severityClass = $remaining <= 2 ? 'style="border-left-color:#ef4444;background-color:rgba(239,68,68,0.12);color:#fca5a5;"' : '';
                            echo '<div class="alert alert-warning" ' . $severityClass . '>
                                    <p style="display:flex;align-items:center;gap:.5rem;margin:0;">
                                        <span aria-hidden="true" style="display:inline-flex;align-items:center;justify-content:center;width:22px;height:22px;border-radius:50%;background:#f59e0b33;color:#fbbf24;">⚠️</span>
                                        <span>Login attempts remaining: <strong style="color:#fbbf24">' . $remaining . '</strong></span>
                                    </p>
                                  </div>';
                        } elseif ($is_locked && empty($login_err)) {
                            // Only show this banner if the generic $login_err isn't already warning us
                            $minutes = ceil($rate_limit_info['lockout_remaining'] / 60);
                            echo '<div class="alert alert-error"><p>🚫 Account temporarily locked. Please try again in ' . $minutes . ' minutes.</p></div>';
                        }
                    }
                    ?>

                    <form action="/index.php" method="POST">
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
                        <a href="/forgot-password.php" class="forgot-password-link">Forgot Password?</a>
                    </div>

                    <p style="text-align: center; margin-top: 2rem; color: var(--text-secondary);">
                        Don't have an account? <a href="/register.php" class="link">Create Account</a>
                    </p>
                </div>
                
                <!-- Right Column - Image/Info -->
                <div class="auth-image-container">
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