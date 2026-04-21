<?php
/**
 * Login Page
 * Main entry point for the Barangay Reports Admin System
 */

require_once 'includes/functions.php';

// App Engine standard may route all requests through index.php.
// Dispatch known PHP paths to their actual script files when present.
$request_path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
$base_path = app_base_path();
if ($base_path !== '' && ($request_path === $base_path || $request_path === $base_path . '/')) {
    $request_path = '/';
} elseif ($base_path !== '' && stripos($request_path, $base_path . '/') === 0) {
    $request_path = substr($request_path, strlen($base_path));
}
$relative_path = ltrim((string) $request_path, '/');

// Normalize duplicated front-controller paths like /index.php/index.php.
if (stripos($relative_path, 'index.php/') === 0) {
    $normalized_tail = ltrim(substr($relative_path, strlen('index.php/')), '/');
    $canonical_path = ($normalized_tail === '' || $normalized_tail === 'index.php')
        ? app_url('/index.php')
        : app_url('/' . $normalized_tail);

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
$show_linked_account_prompt = false;
$linked_prompt_primary_role_label = 'Official';
$linked_prompt_prefill_choice = '';

/**
 * Convert role slug to friendly label for UI.
 */
function linked_role_label(string $role): string {
    $map = [
        'admin' => 'Admin',
        'barangay-officials' => 'Barangay Official',
        'barangay-kagawad' => 'Barangay Kagawad',
        'barangay-tanod' => 'Barangay Tanod',
    ];
    return $map[$role] ?? 'Official';
}

/**
 * Find tied privileged accounts for a resident login using linked identity prefix.
 */
function find_linked_privileged_accounts_for_resident(PDO $pdo, array $residentUser): array {
    $resident_user_id = (int)($residentUser['id'] ?? 0);
    if ($resident_user_id <= 0 || ($residentUser['role'] ?? '') !== 'resident') {
        return [];
    }

    $resident_stmt = $pdo->prepare("SELECT id FROM residents WHERE user_id = ? LIMIT 1");
    $resident_stmt->execute([$resident_user_id]);
    $resident_id = (int)($resident_stmt->fetchColumn() ?: 0);
    if ($resident_id <= 0) {
        return [];
    }

    $linked_prefix = 'linked.r' . $resident_id . '.';
    $linked_like = $linked_prefix . '%';

    $stmt = $pdo->prepare(
        "SELECT *
         FROM users
         WHERE role IN ('admin', 'barangay-officials', 'barangay-kagawad', 'barangay-tanod')
           AND (username LIKE ? OR email LIKE ?)
         ORDER BY FIELD(role, 'admin', 'barangay-officials', 'barangay-kagawad', 'barangay-tanod'), id ASC"
    );
    $stmt->execute([$linked_like, $linked_like]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $accounts = [];
    foreach ($rows as $row) {
        $status = strtolower(trim((string)($row['status'] ?? 'active')));
        $password_change_required = isset($row['password_change_required']) ? (int)$row['password_change_required'] : 0;

        if ($status !== 'active') {
            continue;
        }
        if ($password_change_required === 1) {
            continue;
        }

        $accounts[] = $row;
    }

    return $accounts;
}

/**
 * Build prompt state from pending linked login session context.
 */
function hydrate_linked_prompt_state(PDO $pdo): array {
    $pending = $_SESSION['pending_linked_login'] ?? null;
    if (!is_array($pending)) {
        return [false, 'Official'];
    }

    $expires_at = (int)($pending['expires_at'] ?? 0);
    if ($expires_at <= 0 || $expires_at < time()) {
        unset($_SESSION['pending_linked_login']);
        return [false, 'Official'];
    }

    $linked_ids = array_values(array_filter(array_map('intval', (array)($pending['linked_account_ids'] ?? [])), static fn($id) => $id > 0));
    if (empty($linked_ids)) {
        unset($_SESSION['pending_linked_login']);
        return [false, 'Official'];
    }

    $placeholders = implode(',', array_fill(0, count($linked_ids), '?'));
    $stmt = $pdo->prepare(
        "SELECT role
         FROM users
         WHERE id IN ($placeholders)
           AND role IN ('admin', 'barangay-officials', 'barangay-kagawad', 'barangay-tanod')
         ORDER BY FIELD(role, 'admin', 'barangay-officials', 'barangay-kagawad', 'barangay-tanod'), id ASC"
    );
    $stmt->execute($linked_ids);
    $roles = $stmt->fetchAll(PDO::FETCH_COLUMN);

    if (empty($roles)) {
        unset($_SESSION['pending_linked_login']);
        return [false, 'Official'];
    }

    return [true, linked_role_label((string)$roles[0])];
}

// Check if user is already logged in
if (is_logged_in()) {
    if (isset($_SESSION['role']) && $_SESSION['role'] === 'resident') {
        redirect_to('resident/dashboard.php');
    } else {
        redirect_to('admin/index.php');
    }
}

[$prompt_visible, $prompt_role_label] = hydrate_linked_prompt_state($pdo);
$show_linked_account_prompt = $prompt_visible;
$linked_prompt_primary_role_label = $prompt_role_label;
if ($show_linked_account_prompt) {
    $pending_username = $_SESSION['pending_linked_login']['username'] ?? '';
    if (is_string($pending_username) && $pending_username !== '') {
        $username = $pending_username;
    }
}

if ($show_linked_account_prompt && $_SERVER["REQUEST_METHOD"] === "POST") {
    $submitted_stage = strtolower(trim((string)($_POST['login_stage'] ?? '')));
    $submitted_choice = strtolower(trim((string)($_POST['account_choice'] ?? '')));
    if ($submitted_stage === 'choose_account' && in_array($submitted_choice, ['resident', 'official'], true)) {
        $linked_prompt_prefill_choice = $submitted_choice;
    }
}

// Process form data when form is submitted
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    
    // Validate CSRF token
    if (!CSRFProtection::validateFromPost()) {
        $login_err = "Invalid security token. Please refresh the page and try again.";
    } else {
        $login_stage = trim((string)($_POST['login_stage'] ?? 'credentials'));

        if ($login_stage === 'cancel_linked_prompt') {
            unset($_SESSION['pending_linked_login']);
            $show_linked_account_prompt = false;
            $linked_prompt_prefill_choice = '';
            $username = '';
            $password = '';
            $_SESSION['success_message'] = 'You can now sign in with another account.';
        } elseif ($login_stage === 'choose_account') {
            $pending = $_SESSION['pending_linked_login'] ?? null;
            if (!is_array($pending) || ((int)($pending['expires_at'] ?? 0) < time())) {
                unset($_SESSION['pending_linked_login']);
                $show_linked_account_prompt = false;
                $login_err = 'Account selection session expired. Please sign in again.';
            } else {
                $username = (string)($pending['username'] ?? '');
                $resident_user_id = (int)($pending['resident_user_id'] ?? 0);
                $linked_account_ids = array_values(array_filter(array_map('intval', (array)($pending['linked_account_ids'] ?? [])), static fn($id) => $id > 0));
                $rate_limit_identifier = (string)($pending['rate_limit_identifier'] ?? get_login_rate_limit_identifier($username));

                $resident_stmt = $pdo->prepare("SELECT * FROM users WHERE id = ? AND role = 'resident' LIMIT 1");
                $resident_stmt->execute([$resident_user_id]);
                $resident_user = $resident_stmt->fetch(PDO::FETCH_ASSOC);

                if (!$resident_user || empty($linked_account_ids)) {
                    unset($_SESSION['pending_linked_login']);
                    $show_linked_account_prompt = false;
                    $login_err = 'No linked privileged account is available for this resident.';
                } else {
                    $account_choice = strtolower(trim((string)($_POST['account_choice'] ?? '')));

                    if ($account_choice === 'resident') {
                        $session_result = create_session($resident_user, $pdo);
                        if (!$session_result['success']) {
                            $login_err = $session_result['message'] ?? 'Unable to sign in due to active session limits.';
                            [$show_linked_account_prompt, $linked_prompt_primary_role_label] = hydrate_linked_prompt_state($pdo);
                        } else {
                            reset_login_rate_limit($rate_limit_identifier);
                            unset($_SESSION['pending_linked_login']);
                            redirect_to('resident/dashboard.php');
                        }
                    } elseif ($account_choice === 'official') {
                        $official_password = trim((string)($_POST['official_password'] ?? ''));
                        if ($official_password === '') {
                            $login_err = 'Please enter official password to continue.';
                            [$show_linked_account_prompt, $linked_prompt_primary_role_label] = hydrate_linked_prompt_state($pdo);
                        } else {
                            $placeholders = implode(',', array_fill(0, count($linked_account_ids), '?'));
                            $linked_stmt = $pdo->prepare(
                                "SELECT *
                                 FROM users
                                 WHERE id IN ($placeholders)
                                   AND role IN ('admin', 'barangay-officials', 'barangay-kagawad', 'barangay-tanod')
                                 ORDER BY FIELD(role, 'admin', 'barangay-officials', 'barangay-kagawad', 'barangay-tanod'), id ASC"
                            );
                            $linked_stmt->execute($linked_account_ids);
                            $linked_accounts = $linked_stmt->fetchAll(PDO::FETCH_ASSOC);

                            $matched_privileged_user = null;
                            foreach ($linked_accounts as $candidate) {
                                $status = strtolower(trim((string)($candidate['status'] ?? 'active')));
                                $password_change_required = isset($candidate['password_change_required']) ? (int)$candidate['password_change_required'] : 0;
                                if ($status !== 'active' || $password_change_required === 1) {
                                    continue;
                                }

                                if (password_verify($official_password, (string)$candidate['password'])) {
                                    $matched_privileged_user = $candidate;
                                    break;
                                }
                            }

                            if (!$matched_privileged_user) {
                                $login_err = 'Official password is incorrect.';
                                [$show_linked_account_prompt, $linked_prompt_primary_role_label] = hydrate_linked_prompt_state($pdo);
                            } else {
                                $session_result = create_session($matched_privileged_user, $pdo);
                                if (!$session_result['success']) {
                                    $login_err = $session_result['message'] ?? 'Unable to sign in due to active session limits.';
                                    [$show_linked_account_prompt, $linked_prompt_primary_role_label] = hydrate_linked_prompt_state($pdo);
                                } else {
                                    reset_login_rate_limit($rate_limit_identifier);
                                    unset($_SESSION['pending_linked_login']);
                                    redirect_to('admin/index.php');
                                }
                            }
                        }
                    } else {
                        $login_err = 'Please choose how you want to enter the account.';
                        [$show_linked_account_prompt, $linked_prompt_primary_role_label] = hydrate_linked_prompt_state($pdo);
                    }
                }
            }
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
                        if (($user['role'] ?? '') === 'resident') {
                            $linked_accounts = find_linked_privileged_accounts_for_resident($pdo, $user);

                            if (!empty($linked_accounts)) {
                                $_SESSION['pending_linked_login'] = [
                                    'resident_user_id' => (int)$user['id'],
                                    'linked_account_ids' => array_values(array_map(static fn($row) => (int)$row['id'], $linked_accounts)),
                                    'expires_at' => time() + 300,
                                    'rate_limit_identifier' => $rate_limit_identifier,
                                    'username' => $username,
                                ];

                                reset_login_rate_limit($rate_limit_identifier);
                                [$show_linked_account_prompt, $linked_prompt_primary_role_label] = hydrate_linked_prompt_state($pdo);
                            } else {
                                $session_result = create_session($user, $pdo);
                                if (!$session_result['success']) {
                                    $login_err = $session_result['message'] ?? 'Unable to sign in due to active session limits.';
                                } else {
                                    reset_login_rate_limit($rate_limit_identifier);
                                    redirect_to('resident/dashboard.php');
                                }
                            }
                        } else {
                            $session_result = create_session($user, $pdo);
                            if (!$session_result['success']) {
                                $login_err = $session_result['message'] ?? 'Unable to sign in due to active session limits.';
                            } else {
                                reset_login_rate_limit($rate_limit_identifier);
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
                }
            }
        }
    } // Close CSRF validation else block
}

// Page title
$page_title = "Sign In - Barangay Pakiad";
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
    <link rel="stylesheet" href="assets/css/auth.css">
    <link rel="icon" href="assets/images/barangay-logo.png" type="image/png">
    <script src="assets/js/login.js" defer></script>
    <script src="assets/js/system-worker.js" defer></script>
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

                    <?php if ($show_linked_account_prompt): ?>
                    <div class="alert alert-warning" style="border-left-color:#3b82f6;background-color:rgba(59,130,246,0.12);color:#bfdbfe;">
                        <p style="margin:0;line-height:1.5;">
                            Linked accounts detected. Choose how you want to enter.
                            <br><small style="opacity:.9;">Resident sign-in uses your resident password. Official/Admin sign-in requires privileged password.</small>
                        </p>
                    </div>

                    <form id="linked-choice-form" action="<?= htmlspecialchars(app_url('/index.php')) ?>" method="POST" style="margin-top:1rem;">
                        <?php echo CSRFProtection::getTokenField(); ?>
                        <input type="hidden" name="login_stage" value="choose_account">
                        <input type="hidden" name="account_choice" id="account_choice" value="<?= htmlspecialchars($linked_prompt_prefill_choice) ?>">

                        <div style="display:flex;gap:.75rem;flex-wrap:wrap;">
                            <button type="submit" name="account_choice" value="resident" id="choose-resident-choice" class="submit-btn" style="flex:1;min-width:170px;">
                                Enter as Resident
                            </button>
                            <button type="submit" name="account_choice" value="official" id="show-official-choice" class="submit-btn" style="flex:1;min-width:170px;background:#334155;">
                                Enter as Official
                            </button>
                        </div>

                        <div id="official-choice-panel" style="display:<?= $linked_prompt_prefill_choice === 'official' ? 'block' : 'none' ?>;margin-top:1rem;">
                            <div class="form-group" style="margin-bottom:.75rem;">
                                <label for="official_password">Official Password (<?= htmlspecialchars($linked_prompt_primary_role_label) ?>)</label>
                                <div class="input-wrapper">
                                    <i class="fas fa-user-shield input-icon"></i>
                                    <input id="official_password" name="official_password" type="password" autocomplete="current-password" placeholder="Enter official password">
                                </div>
                            </div>
                            <button type="submit" name="account_choice" value="official" id="submit-official-choice" class="submit-btn">
                                Continue as Official
                            </button>
                        </div>
                    </form>

                    <form action="<?= htmlspecialchars(app_url('/index.php')) ?>" method="POST" style="margin-top:.75rem;">
                        <?php echo CSRFProtection::getTokenField(); ?>
                        <input type="hidden" name="login_stage" value="cancel_linked_prompt">
                        <button type="submit" class="submit-btn" style="margin-top:0;background:#475569;">
                            Back to Login
                        </button>
                    </form>

                    <?php else: ?>

                    <form action="<?= htmlspecialchars(app_url('/index.php')) ?>" method="POST">
                        <?php echo CSRFProtection::getTokenField(); ?>
                        <div class="form-group">
                            <label for="username">Email Address</label>
                            <div class="input-wrapper">
                                <i class="fas fa-envelope input-icon"></i>
                                <input id="username" name="username" type="text" autocomplete="username" required placeholder="example@gmail.com" value="<?= htmlspecialchars($username) ?>">
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
                        <a href="<?= htmlspecialchars(app_url('/forgot-password.php')) ?>" class="forgot-password-link">Forgot Password?</a>
                    </div>

                    <p style="text-align: center; margin-top: 2rem; color: var(--text-secondary);">
                        Don't have an account? <a href="<?= htmlspecialchars(app_url('/register.php')) ?>" class="link">Create Account</a>
                    </p>
                    <?php endif; ?>
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
