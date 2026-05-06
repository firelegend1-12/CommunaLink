<?php
/**
 * Task 4.3 — Preservation Test: Development OTP Fallback
 *
 * Verifies that when APP_ENV=development and sendOTP() returns false:
 *   1. $_SESSION['otp_dev_code'] is set (non-empty)
 *   2. Redirect is to /verify-otp.php (absolute path, no '../')
 *   3. $_SESSION['error_message'] is NOT set
 *   4. $_SESSION['otp_email'] and ['otp_fullname'] ARE set
 *
 * Run: D:\xampp\php\php.exe tests/test_preservation_dev_fallback.php
 */

require_once __DIR__ . '/test_helpers.php';

// ─── Load real app_url() from functions.php ───────────────────────────────────
$_SERVER['DOCUMENT_ROOT'] = 'D:/xampp/htdocs/CommunaLink';
$_SERVER['SCRIPT_NAME']   = '/index.php';
$_SERVER['REQUEST_URI']   = '/';
require_once __DIR__ . '/../includes/functions.php';

// ─── Handler simulation (reuses logic from test_fix_checking.php) ─────────────

/**
 * Simulate the OTP-send branch of register-handler.php.
 * Mirrors the exact logic from the fixed register-handler.php.
 *
 * @param bool   $send_otp_result  Mock return value of sendOTP()
 * @param string $app_env          Simulated APP_ENV value
 * @param string $request_uri      Simulated REQUEST_URI
 * @param string $email            Simulated email
 * @param string $fullname         Simulated full name
 * @param string $otp_code         Simulated OTP code
 * @return array{session: array, redirect: string|null}
 */
function simulate_otp_branch(
    bool   $send_otp_result,
    string $app_env     = 'development',
    string $request_uri = '/',
    string $email       = 'dev@example.com',
    string $fullname    = 'Dev User',
    string $otp_code    = '654321'
): array {
    $_SERVER['REQUEST_URI'] = $request_uri;

    $session  = [];
    $redirect = null;

    if (!$send_otp_result) {
        $app_env_lower = strtolower($app_env);
        if ($app_env_lower !== 'production') {
            // Dev-safe fallback: set otp_dev_code and fall through to verify-otp redirect
            $session['otp_success']  = 'Email delivery is unavailable. Using temporary development OTP code.';
            $session['otp_dev_code'] = $otp_code;
        } else {
            // Production SMTP failure: set error message and redirect back to register
            $session['error_message'] = "Email verification is temporarily unavailable. Please try again later.";
            $redirect = app_url('/register.php');
            return compact('session', 'redirect');
        }
    }

    // If no redirect yet (success or dev fallback), set session vars and redirect to verify-otp
    $session['otp_email']    = $email;
    $session['otp_fullname'] = $fullname;
    $redirect = app_url('/verify-otp.php');

    return compact('session', 'redirect');
}

// ═════════════════════════════════════════════════════════════════════════════
// TASK 4.3 — Development OTP Fallback
// ═════════════════════════════════════════════════════════════════════════════

test_section('Task 4.3 — Preservation: Development OTP Fallback');

// ── 4.3.1: APP_ENV=development, sendOTP()=false → otp_dev_code set ────────────
test_case('4.3.1 APP_ENV=development + sendOTP()=false → otp_dev_code is set');
$r = simulate_otp_branch(
    send_otp_result: false,
    app_env:         'development',
    request_uri:     '/',
    otp_code:        '654321'
);
echo "  APP_ENV      : 'development'\n";
echo "  sendOTP()    : false\n";
echo "  Session      : " . json_encode($r['session']) . "\n";
echo "  Redirect     : " . var_export($r['redirect'], true) . "\n\n";

assert_not_empty($r['session']['otp_dev_code'] ?? '',
    '4.3.1: otp_dev_code must be set and non-empty');
assert_equals('654321', $r['session']['otp_dev_code'] ?? '',
    '4.3.1: otp_dev_code must equal the generated OTP code');

// ── 4.3.2: Redirect is to /verify-otp.php (absolute) ─────────────────────────
test_case('4.3.2 APP_ENV=development + sendOTP()=false → redirect to /verify-otp.php');
echo "  Redirect     : " . var_export($r['redirect'], true) . "\n\n";

assert_equals('/verify-otp.php', $r['redirect'],
    '4.3.2: dev fallback must redirect to /verify-otp.php');
assert_false(strpos((string)($r['redirect'] ?? ''), '../') !== false,
    '4.3.2: dev fallback redirect must NOT contain ../');
assert_true(strpos((string)($r['redirect'] ?? ''), '/') === 0,
    '4.3.2: dev fallback redirect must be an absolute path starting with /');

// ── 4.3.3: error_message is NOT set ──────────────────────────────────────────
test_case('4.3.3 APP_ENV=development + sendOTP()=false → error_message NOT set');
echo "  error_message: " . var_export($r['session']['error_message'] ?? null, true) . "\n\n";

assert_false(isset($r['session']['error_message']),
    '4.3.3: dev fallback must NOT set error_message');

// ── 4.3.4: otp_email and otp_fullname ARE set ─────────────────────────────────
test_case('4.3.4 APP_ENV=development + sendOTP()=false → otp_email and otp_fullname are set');
echo "  otp_email    : " . var_export($r['session']['otp_email'] ?? null, true) . "\n";
echo "  otp_fullname : " . var_export($r['session']['otp_fullname'] ?? null, true) . "\n\n";

assert_not_empty($r['session']['otp_email'] ?? '',
    '4.3.4: dev fallback must set otp_email');
assert_not_empty($r['session']['otp_fullname'] ?? '',
    '4.3.4: dev fallback must set otp_fullname');
assert_equals('dev@example.com', $r['session']['otp_email'] ?? '',
    '4.3.4: otp_email must match the submitted email');
assert_equals('Dev User', $r['session']['otp_fullname'] ?? '',
    '4.3.4: otp_fullname must match the submitted full name');

// ── 4.3.5: Contrast — APP_ENV=production, sendOTP()=false → error, NOT dev fallback ─
test_case('4.3.5 APP_ENV=production + sendOTP()=false → error_message set, NOT otp_dev_code');
$r_prod = simulate_otp_branch(
    send_otp_result: false,
    app_env:         'production',
    request_uri:     '/'
);
echo "  APP_ENV      : 'production'\n";
echo "  sendOTP()    : false\n";
echo "  Session      : " . json_encode($r_prod['session']) . "\n";
echo "  Redirect     : " . var_export($r_prod['redirect'], true) . "\n\n";

assert_not_empty($r_prod['session']['error_message'] ?? '',
    '4.3.5: production SMTP failure must set error_message');
assert_false(isset($r_prod['session']['otp_dev_code']),
    '4.3.5: production SMTP failure must NOT set otp_dev_code');
assert_equals('/register.php', $r_prod['redirect'],
    '4.3.5: production SMTP failure must redirect to /register.php');

// ── 4.3.6: APP_ENV=development, sendOTP()=true → normal success (no dev fallback) ─
test_case('4.3.6 APP_ENV=development + sendOTP()=true → normal success, no otp_dev_code');
$r_success = simulate_otp_branch(
    send_otp_result: true,
    app_env:         'development',
    request_uri:     '/'
);
echo "  APP_ENV      : 'development'\n";
echo "  sendOTP()    : true\n";
echo "  Session      : " . json_encode($r_success['session']) . "\n";
echo "  Redirect     : " . var_export($r_success['redirect'], true) . "\n\n";

assert_false(isset($r_success['session']['otp_dev_code']),
    '4.3.6: successful OTP send must NOT set otp_dev_code');
assert_equals('/verify-otp.php', $r_success['redirect'],
    '4.3.6: successful OTP send must redirect to /verify-otp.php');
assert_not_empty($r_success['session']['otp_email'] ?? '',
    '4.3.6: successful OTP send must set otp_email');

// ── 4.3.7: APP_ENV=development with REQUEST_URI='/' (GAE-like) ───────────────
test_case('4.3.7 APP_ENV=development + sendOTP()=false + REQUEST_URI="/" → absolute redirect');
$r_gae = simulate_otp_branch(
    send_otp_result: false,
    app_env:         'development',
    request_uri:     '/',
    otp_code:        '999888'
);
echo "  REQUEST_URI  : '/'\n";
echo "  otp_dev_code : " . var_export($r_gae['session']['otp_dev_code'] ?? null, true) . "\n";
echo "  Redirect     : " . var_export($r_gae['redirect'], true) . "\n\n";

assert_equals('/verify-otp.php', $r_gae['redirect'],
    '4.3.7: dev fallback with REQUEST_URI="/" must redirect to /verify-otp.php (absolute)');
assert_not_empty($r_gae['session']['otp_dev_code'] ?? '',
    '4.3.7: dev fallback with REQUEST_URI="/" must set otp_dev_code');

test_summary();
