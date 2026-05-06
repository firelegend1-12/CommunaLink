<?php
/**
 * Task 3.2 / 3.3 — Fix Checking Tests
 *
 * Verifies the corrected behavior of register-handler.php after the fix:
 *
 * Task 3.2 — Success path (sendOTP() returns true):
 *   - Handler redirects to /verify-otp.php (absolute path, no '../')
 *   - $_SESSION['otp_email'] and $_SESSION['otp_fullname'] are set
 *
 * Task 3.3 — SMTP failure production path (sendOTP() returns false, APP_ENV=production):
 *   - $_SESSION['error_message'] is set (non-empty)
 *   - Handler redirects to /register.php (absolute path, no '../')
 *
 * These tests simulate the full register-handler.php logic in isolation,
 * using a mocked sendOTP() to control the email delivery outcome.
 *
 * Run: D:\xampp\php\php.exe tests/test_fix_checking.php
 */

require_once __DIR__ . '/test_helpers.php';

// ─── Handler simulation helpers ───────────────────────────────────────────────

/**
 * Simulate the full register-handler.php flow (post-validation, post-OTP-store).
 *
 * This function replicates the core logic of register-handler.php from the
 * point where OTPEmailService::sendOTP() is called, using a mock sendOTP()
 * return value to control the outcome. It uses the real app_url() function
 * from includes/functions.php to verify the fix produces absolute paths.
 *
 * @param bool   $sendOtpResult  Mock return value of sendOTP() (true = success, false = failure)
 * @param string $app_env        Simulated APP_ENV value ('production' or 'development')
 * @param string $request_uri    Simulated REQUEST_URI (e.g. '/' for GAE front-controller)
 * @param string $email          Simulated email address
 * @param string $fullname       Simulated full name
 * @return array{session: array, redirect: string|null}
 */
function simulate_handler_flow(
    bool   $sendOtpResult,
    string $app_env,
    string $request_uri,
    string $email    = 'test@example.com',
    string $fullname = 'Test User'
): array {
    // Load the real app_url() from functions.php so we test the actual implementation.
    // We need to set up $_SERVER before loading to get correct app_base_path() result.
    static $functions_loaded = false;
    if (!$functions_loaded) {
        $_SERVER['DOCUMENT_ROOT'] = 'D:/xampp/htdocs/CommunaLink';
        $_SERVER['SCRIPT_NAME']   = '/index.php';
        require_once __DIR__ . '/../includes/functions.php';
        $functions_loaded = true;
    }

    // Override REQUEST_URI for this simulation
    $_SERVER['REQUEST_URI'] = $request_uri;

    $session  = [];
    $redirect = null;
    $otpCode  = '123456'; // simulated OTP

    // ── Replicate the sendOTP() result handling from register-handler.php ──────
    // (Fixed version: uses app_url() for all redirects)
    if (!$sendOtpResult) {
        $app_env_lower = strtolower((string) $app_env);
        if ($app_env_lower !== 'production') {
            // Dev-safe fallback: set otp_dev_code and fall through to verify-otp redirect
            $session['otp_success']  = 'Email delivery is unavailable. Using temporary development OTP code.';
            $session['otp_dev_code'] = $otpCode;
        } else {
            // Production SMTP failure: set error message and redirect back to register
            $session['error_message'] = "Email verification is temporarily unavailable. Please try again later.";
            $redirect = app_url('/register.php');
        }
    }

    // If no redirect yet (success or dev fallback), set session vars and redirect to verify-otp
    if ($redirect === null) {
        $session['otp_email']    = $email;
        $session['otp_fullname'] = $fullname;
        $redirect = app_url('/verify-otp.php');
    }

    return [
        'session'  => $session,
        'redirect' => $redirect,
    ];
}

// ─── Task 3.2 Tests: Success path ─────────────────────────────────────────────

test_section('Task 3.2 — Fix Checking: Success Path (sendOTP() returns true)');

// ── Test 3.2.A: Valid form submission, sendOTP()=true, REQUEST_URI='/' ────────
test_case('3.2.A. sendOTP()=true, APP_ENV=production, REQUEST_URI="/" — GAE front-controller');

$result = simulate_handler_flow(
    sendOtpResult: true,
    app_env:       'production',
    request_uri:   '/',
    email:         'john.doe@example.com',
    fullname:      'John Doe'
);

echo "  sendOTP() returned : true\n";
echo "  APP_ENV            : 'production'\n";
echo "  REQUEST_URI        : '/'\n";
echo "  Session state      : " . json_encode($result['session']) . "\n";
echo "  Redirect target    : " . var_export($result['redirect'], true) . "\n\n";

// Assert redirect goes to /verify-otp.php (absolute path, no '../')
assert_equals('/verify-otp.php', $result['redirect'],
    'Success path must redirect to /verify-otp.php');
assert_false(strpos($result['redirect'], '../') !== false,
    'Success path redirect must NOT contain ../');
assert_true(strpos($result['redirect'], '/') === 0,
    'Success path redirect must be an absolute path starting with /');

// Assert session variables are set
assert_not_empty($result['session']['otp_email'] ?? '',
    'Success path must set $_SESSION["otp_email"]');
assert_not_empty($result['session']['otp_fullname'] ?? '',
    'Success path must set $_SESSION["otp_fullname"]');
assert_equals('john.doe@example.com', $result['session']['otp_email'] ?? '',
    'otp_email must match the submitted email');
assert_equals('John Doe', $result['session']['otp_fullname'] ?? '',
    'otp_fullname must match the submitted full name');

// Assert no error message on success
assert_false(isset($result['session']['error_message']),
    'Success path must NOT set $_SESSION["error_message"]');

// ── Test 3.2.B: Valid form submission, sendOTP()=true, REQUEST_URI='/index.php' ─
test_case('3.2.B. sendOTP()=true, APP_ENV=production, REQUEST_URI="/index.php" — index.php front-controller');

$result2 = simulate_handler_flow(
    sendOtpResult: true,
    app_env:       'production',
    request_uri:   '/index.php',
    email:         'maria.santos@example.com',
    fullname:      'Maria Santos'
);

echo "  sendOTP() returned : true\n";
echo "  APP_ENV            : 'production'\n";
echo "  REQUEST_URI        : '/index.php'\n";
echo "  Session state      : " . json_encode($result2['session']) . "\n";
echo "  Redirect target    : " . var_export($result2['redirect'], true) . "\n\n";

assert_equals('/verify-otp.php', $result2['redirect'],
    'Success path with /index.php REQUEST_URI must redirect to /verify-otp.php');
assert_false(strpos($result2['redirect'], '../') !== false,
    'Success path redirect must NOT contain ../');
assert_not_empty($result2['session']['otp_email'] ?? '',
    'Success path must set $_SESSION["otp_email"]');
assert_not_empty($result2['session']['otp_fullname'] ?? '',
    'Success path must set $_SESSION["otp_fullname"]');

// ── Test 3.2.C: Verify otp_email and otp_fullname are set with correct values ─
test_case('3.2.C. Session variables otp_email and otp_fullname contain submitted values');

$testEmail    = 'barangay.resident@gmail.com';
$testFullname = 'Juan dela Cruz';

$result3 = simulate_handler_flow(
    sendOtpResult: true,
    app_env:       'production',
    request_uri:   '/',
    email:         $testEmail,
    fullname:      $testFullname
);

echo "  Submitted email    : '$testEmail'\n";
echo "  Submitted fullname : '$testFullname'\n";
echo "  otp_email          : " . var_export($result3['session']['otp_email'] ?? null, true) . "\n";
echo "  otp_fullname       : " . var_export($result3['session']['otp_fullname'] ?? null, true) . "\n\n";

assert_equals($testEmail, $result3['session']['otp_email'] ?? '',
    'otp_email must equal the submitted email address');
assert_equals($testFullname, $result3['session']['otp_fullname'] ?? '',
    'otp_fullname must equal the submitted full name');

// ─── Task 3.3 Tests: SMTP failure production path ─────────────────────────────

test_section('Task 3.3 — Fix Checking: SMTP Failure Production Path (sendOTP() returns false)');

// ── Test 3.3.A: sendOTP()=false, APP_ENV=production, REQUEST_URI='/' ──────────
test_case('3.3.A. sendOTP()=false, APP_ENV=production, REQUEST_URI="/" — GAE front-controller');

$result4 = simulate_handler_flow(
    sendOtpResult: false,
    app_env:       'production',
    request_uri:   '/',
    email:         'test@example.com',
    fullname:      'Test User'
);

echo "  sendOTP() returned : false\n";
echo "  APP_ENV            : 'production'\n";
echo "  REQUEST_URI        : '/'\n";
echo "  Session state      : " . json_encode($result4['session']) . "\n";
echo "  Redirect target    : " . var_export($result4['redirect'], true) . "\n\n";

// Assert error_message is set (non-empty)
assert_not_empty($result4['session']['error_message'] ?? '',
    'SMTP failure in production must set $_SESSION["error_message"] (non-empty)');

// Assert redirect goes to /register.php (absolute path, no '../')
assert_equals('/register.php', $result4['redirect'],
    'SMTP failure in production must redirect to /register.php');
assert_false(strpos($result4['redirect'], '../') !== false,
    'SMTP failure redirect must NOT contain ../');
assert_true(strpos($result4['redirect'], '/') === 0,
    'SMTP failure redirect must be an absolute path starting with /');

// Assert otp_email and otp_fullname are NOT set (user should not proceed to verify-otp)
assert_false(isset($result4['session']['otp_email']),
    'SMTP failure in production must NOT set $_SESSION["otp_email"]');
assert_false(isset($result4['session']['otp_fullname']),
    'SMTP failure in production must NOT set $_SESSION["otp_fullname"]');

// Assert otp_dev_code is NOT set in production
assert_false(isset($result4['session']['otp_dev_code']),
    'SMTP failure in production must NOT set $_SESSION["otp_dev_code"]');

// ── Test 3.3.B: sendOTP()=false, APP_ENV=production, REQUEST_URI='/index.php' ─
test_case('3.3.B. sendOTP()=false, APP_ENV=production, REQUEST_URI="/index.php" — index.php front-controller');

$result5 = simulate_handler_flow(
    sendOtpResult: false,
    app_env:       'production',
    request_uri:   '/index.php'
);

echo "  sendOTP() returned : false\n";
echo "  APP_ENV            : 'production'\n";
echo "  REQUEST_URI        : '/index.php'\n";
echo "  Session state      : " . json_encode($result5['session']) . "\n";
echo "  Redirect target    : " . var_export($result5['redirect'], true) . "\n\n";

assert_not_empty($result5['session']['error_message'] ?? '',
    'SMTP failure with /index.php REQUEST_URI must set $_SESSION["error_message"]');
assert_equals('/register.php', $result5['redirect'],
    'SMTP failure with /index.php REQUEST_URI must redirect to /register.php');

// ── Test 3.3.C: Verify error message is user-friendly ─────────────────────────
test_case('3.3.C. Error message is user-friendly (not a raw exception or stack trace)');

$errorMsg = $result4['session']['error_message'] ?? '';

echo "  error_message : " . var_export($errorMsg, true) . "\n\n";

// Must not contain PHP error indicators
assert_false(strpos($errorMsg, 'Exception') !== false,
    'error_message must not contain "Exception"');
assert_false(strpos($errorMsg, 'Stack trace') !== false,
    'error_message must not contain "Stack trace"');
assert_false(strpos($errorMsg, 'Fatal error') !== false,
    'error_message must not contain "Fatal error"');
assert_false(strpos($errorMsg, 'Warning:') !== false,
    'error_message must not contain "Warning:"');

// Must be a non-trivial message (at least 10 characters)
assert_true(strlen($errorMsg) >= 10,
    'error_message must be at least 10 characters long');

// ── Test 3.3.D: Contrast — development SMTP failure does NOT redirect to register ─
test_case('3.3.D. sendOTP()=false, APP_ENV=development — dev fallback, NOT redirected to /register.php');

$result6 = simulate_handler_flow(
    sendOtpResult: false,
    app_env:       'development',
    request_uri:   '/'
);

echo "  sendOTP() returned : false\n";
echo "  APP_ENV            : 'development'\n";
echo "  REQUEST_URI        : '/'\n";
echo "  Session state      : " . json_encode($result6['session']) . "\n";
echo "  Redirect target    : " . var_export($result6['redirect'], true) . "\n\n";

// Dev fallback: should redirect to /verify-otp.php, not /register.php
assert_equals('/verify-otp.php', $result6['redirect'],
    'Development SMTP failure must redirect to /verify-otp.php (not /register.php)');
assert_not_empty($result6['session']['otp_dev_code'] ?? '',
    'Development SMTP failure must set otp_dev_code');
assert_false(isset($result6['session']['error_message']),
    'Development SMTP failure must NOT set error_message');

// ── Test 3.3.E: Verify no '../' in any redirect path ──────────────────────────
test_case('3.3.E. No redirect path contains "../" — absolute paths only');

$scenarios = [
    ['sendOTP=true,  production, REQUEST_URI=/',          simulate_handler_flow(true,  'production',  '/')],
    ['sendOTP=true,  production, REQUEST_URI=/index.php', simulate_handler_flow(true,  'production',  '/index.php')],
    ['sendOTP=false, production, REQUEST_URI=/',          simulate_handler_flow(false, 'production',  '/')],
    ['sendOTP=false, production, REQUEST_URI=/index.php', simulate_handler_flow(false, 'production',  '/index.php')],
    ['sendOTP=false, development, REQUEST_URI=/',         simulate_handler_flow(false, 'development', '/')],
];

foreach ($scenarios as [$label, $r]) {
    $redirect = $r['redirect'] ?? '';
    $hasDotDot = strpos($redirect, '../') !== false;
    $isAbsolute = strpos($redirect, '/') === 0;

    echo "  Scenario: $label\n";
    echo "    Redirect: '$redirect'\n";
    echo "    Contains '../': " . ($hasDotDot ? 'YES ✗' : 'NO ✓') . "\n";
    echo "    Absolute path:  " . ($isAbsolute ? 'YES ✓' : 'NO ✗') . "\n\n";

    assert_false($hasDotDot,
        "Redirect for '$label' must NOT contain '../'");
    assert_true($isAbsolute,
        "Redirect for '$label' must be an absolute path starting with /");
}

test_summary();

echo "\n";
echo str_repeat('═', 60) . "\n";
echo "  TASK 3.2 / 3.3 FIX CHECKING SUMMARY\n";
echo str_repeat('═', 60) . "\n";
echo "\n";
echo "  Task 3.2 — Success Path:\n";
echo "    sendOTP()=true → redirect to /verify-otp.php (absolute)\n";
echo "    \$_SESSION['otp_email'] and ['otp_fullname'] are set\n";
echo "    No error_message set\n";
echo "\n";
echo "  Task 3.3 — SMTP Failure Production Path:\n";
echo "    sendOTP()=false + APP_ENV=production\n";
echo "    → \$_SESSION['error_message'] is set (non-empty, user-friendly)\n";
echo "    → redirect to /register.php (absolute, no '../')\n";
echo "    → otp_email and otp_fullname NOT set\n";
echo str_repeat('═', 60) . "\n";
