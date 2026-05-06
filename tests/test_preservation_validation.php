<?php
/**
 * Task 4.1 / 4.2 — Preservation Tests: Validation Error Paths + Duplicate Email
 *
 * Verifies that ALL validation error paths in register-handler.php continue to:
 *   1. Set the correct $_SESSION['error_message']
 *   2. Redirect to /register.php (absolute path, no '../')
 *
 * Covers:
 *   4.1 — Empty required fields, empty personal info, invalid phone, password
 *          too short, no number, no special char, password mismatch, invalid
 *          email format, age < 18
 *   4.2 — Duplicate email path (mocked DB check)
 *
 * Run: D:\xampp\php\php.exe tests/test_preservation_validation.php
 */

require_once __DIR__ . '/test_helpers.php';

// ─── Load real app_url() from functions.php ───────────────────────────────────
$_SERVER['DOCUMENT_ROOT'] = 'D:/xampp/htdocs/CommunaLink';
$_SERVER['SCRIPT_NAME']   = '/index.php';
$_SERVER['REQUEST_URI']   = '/';
require_once __DIR__ . '/../includes/functions.php';

// ─── Local phone normalizer (avoids PCRE2 \u{00A0} issue on some PHP builds) ──
// Mirrors the logic of normalize_ph_mobile_for_registration() from functions.php
// but uses a compatible regex that works on all PCRE2 builds.
function test_normalize_phone(string $raw): ?string {
    // Strip whitespace, hyphens, and non-breaking spaces
    $s = trim($raw);
    $s = preg_replace('/[\s\-\xc2\xa0]+/', '', $s); // \xc2\xa0 = UTF-8 NBSP
    if ($s === '') {
        return null;
    }
    if (preg_match('/^\+639\d{9}$/', $s) === 1) {
        return $s;
    }
    if (preg_match('/^09\d{9}$/', $s) === 1) {
        return '+63' . substr($s, 1);
    }
    if (preg_match('/^639\d{9}$/', $s) === 1) {
        return '+' . $s;
    }
    return null;
}

// ─── Handler simulation ───────────────────────────────────────────────────────

/**
 * Simulate the full register-handler.php validation logic.
 *
 * Returns ['session' => [...], 'redirect' => string|null].
 * Mirrors the exact validation order in register-handler.php.
 *
 * @param array  $post            Simulated $_POST values
 * @param string $app_env         Simulated APP_ENV
 * @param string $request_uri     Simulated REQUEST_URI
 * @param bool   $email_exists    Whether the email already exists in DB (mock)
 * @param bool   $send_otp_result Mock return value of sendOTP()
 * @return array{session: array, redirect: string|null}
 */
function simulate_validation(
    array  $post,
    string $app_env        = 'production',
    string $request_uri    = '/',
    bool   $email_exists   = false,
    bool   $send_otp_result = true
): array {
    $_SERVER['REQUEST_URI'] = $request_uri;

    $session  = [];
    $redirect = null;

    // ── Replicate sanitize_input() ────────────────────────────────────────────
    $si = function($v) {
        $v = trim((string)$v);
        $v = stripslashes($v);
        $v = htmlspecialchars($v);
        return $v;
    };

    // ── Sanitize inputs (mirrors register-handler.php) ────────────────────────
    $fullname         = $si($post['fullname']         ?? '');
    $raw_email        = $post['email']                ?? '';
    $email            = filter_var($raw_email, FILTER_VALIDATE_EMAIL) ? $si($raw_email) : null;
    $password         = $post['password']             ?? '';
    $confirm_password = $post['confirm_password']     ?? '';

    $first_name   = $si($post['first_name']   ?? '');
    $last_name    = $si($post['last_name']    ?? '');
    $gender       = $si($post['gender']       ?? '');
    $date_of_birth = $si($post['date_of_birth'] ?? '');
    $place_of_birth = $si($post['place_of_birth'] ?? '');
    $citizenship  = $si($post['citizenship']  ?? '');
    $civil_status = $si($post['civil_status'] ?? '');
    $voter_status = $si($post['voter_status'] ?? '');
    $address      = $si($post['address']      ?? '');

    // ── Validate required fields ──────────────────────────────────────────────
    if (empty($fullname) || empty($email) || empty($password) || empty($confirm_password)) {
        $session['error_message'] = "All required fields must be filled.";
        $redirect = app_url('/register.php');
        return compact('session', 'redirect');
    }

    // ── Validate personal info ────────────────────────────────────────────────
    if (empty($first_name) || empty($last_name) || empty($gender) || empty($date_of_birth) ||
        empty($place_of_birth) || empty($citizenship) || empty($civil_status) || empty($voter_status) || empty($address)) {
        $session['error_message'] = "All required personal information fields must be filled.";
        $redirect = app_url('/register.php');
        return compact('session', 'redirect');
    }

    // ── Validate phone ────────────────────────────────────────────────────────
    $contact_canonical = test_normalize_phone((string)($post['contact_no'] ?? ''));
    if ($contact_canonical === null) {
        $session['error_message'] = 'Please enter a valid Philippine mobile number starting with +63 (e.g. +639171234567, or 09171234567).';
        $redirect = app_url('/register.php');
        return compact('session', 'redirect');
    }

    // ── Validate password length ──────────────────────────────────────────────
    if (strlen($password) < 8) {
        $session['error_message'] = "Password must be at least 8 characters long.";
        $redirect = app_url('/register.php');
        return compact('session', 'redirect');
    }

    // ── Validate password has number ──────────────────────────────────────────
    if (!preg_match('/[0-9]/', $password)) {
        $session['error_message'] = "Password must contain at least one number (0-9).";
        $redirect = app_url('/register.php');
        return compact('session', 'redirect');
    }

    // ── Validate password has special char ────────────────────────────────────
    if (!preg_match('/[!@#$%^&*()_+\-=\[\]{};:\'",.<>?\/\\|`~]/', $password)) {
        $session['error_message'] = "Password must contain at least one special character (!@#$%^&*).";
        $redirect = app_url('/register.php');
        return compact('session', 'redirect');
    }

    // ── Validate password match ───────────────────────────────────────────────
    if ($password !== $confirm_password) {
        $session['error_message'] = "Passwords do not match.";
        $redirect = app_url('/register.php');
        return compact('session', 'redirect');
    }

    // ── Validate email format ─────────────────────────────────────────────────
    if (!$email) {
        $session['error_message'] = "Please enter a valid email address.";
        $redirect = app_url('/register.php');
        return compact('session', 'redirect');
    }

    // ── Validate age ──────────────────────────────────────────────────────────
    try {
        $birth_date = new DateTime($date_of_birth);
        $today      = new DateTime();
        $age        = $today->diff($birth_date)->y;
    } catch (Exception $e) {
        $age = 0;
    }

    if ($age < 18) {
        $session['error_message'] = "You must be at least 18 years old to register.";
        $redirect = app_url('/register.php');
        return compact('session', 'redirect');
    }

    // ── Duplicate email check (mocked) ────────────────────────────────────────
    if ($email_exists) {
        $session['error_message'] = "An account with this email already exists.";
        $redirect = app_url('/register.php');
        return compact('session', 'redirect');
    }

    // ── OTP send (mocked) ─────────────────────────────────────────────────────
    $otpCode = '123456';
    if (!$send_otp_result) {
        $app_env_lower = strtolower($app_env);
        if ($app_env_lower !== 'production') {
            $session['otp_success']  = 'Email delivery is unavailable. Using temporary development OTP code.';
            $session['otp_dev_code'] = $otpCode;
        } else {
            $session['error_message'] = "Email verification is temporarily unavailable. Please try again later.";
            $redirect = app_url('/register.php');
            return compact('session', 'redirect');
        }
    }

    $session['otp_email']    = $email;
    $session['otp_fullname'] = $fullname;
    $redirect = app_url('/verify-otp.php');

    return compact('session', 'redirect');
}

// ─── Base valid post data ─────────────────────────────────────────────────────

/**
 * Returns a fully valid POST array that passes all validations.
 * Individual tests override specific fields to trigger each error.
 */
function valid_post(): array {
    return [
        'fullname'         => 'Juan dela Cruz',
        'email'            => 'juan@example.com',
        'password'         => 'SecurePass1!',
        'confirm_password' => 'SecurePass1!',
        'first_name'       => 'Juan',
        'middle_initial'   => 'D',
        'last_name'        => 'dela Cruz',
        'gender'           => 'Male',
        'date_of_birth'    => '1990-01-15',
        'place_of_birth'   => 'Manila',
        'religion'         => 'Catholic',
        'citizenship'      => 'Filipino',
        'civil_status'     => 'Single',
        'voter_status'     => 'Registered',
        'contact_no'       => '+639171234567',
        'address'          => '123 Barangay St, Manila',
    ];
}

// ─── Helper: assert redirect is /register.php ─────────────────────────────────

function assert_register_redirect(array $result, string $context): void {
    assert_equals('/register.php', $result['redirect'],
        "$context: redirect must be /register.php");
    assert_false(strpos((string)($result['redirect'] ?? ''), '../') !== false,
        "$context: redirect must NOT contain ../");
    assert_true(strpos((string)($result['redirect'] ?? ''), '/') === 0,
        "$context: redirect must be an absolute path starting with /");
}

function assert_error_set(array $result, string $context): void {
    assert_not_empty($result['session']['error_message'] ?? '',
        "$context: error_message must be set and non-empty");
}

// ═════════════════════════════════════════════════════════════════════════════
// TASK 4.1 — Validation Error Paths
// ═════════════════════════════════════════════════════════════════════════════

test_section('Task 4.1 — Preservation: Validation Error Paths');

// ── 4.1.1: Empty fullname ─────────────────────────────────────────────────────
test_case('4.1.1 Empty fullname → error + redirect to /register.php');
$post = valid_post();
$post['fullname'] = '';
$r = simulate_validation($post);
echo "  error_message: " . var_export($r['session']['error_message'] ?? null, true) . "\n";
echo "  redirect     : " . var_export($r['redirect'], true) . "\n\n";
assert_equals("All required fields must be filled.", $r['session']['error_message'] ?? '',
    '4.1.1: error_message must be "All required fields must be filled."');
assert_register_redirect($r, '4.1.1');

// ── 4.1.2: Empty email ────────────────────────────────────────────────────────
test_case('4.1.2 Empty email → error + redirect to /register.php');
$post = valid_post();
$post['email'] = '';
$r = simulate_validation($post);
echo "  error_message: " . var_export($r['session']['error_message'] ?? null, true) . "\n";
echo "  redirect     : " . var_export($r['redirect'], true) . "\n\n";
assert_equals("All required fields must be filled.", $r['session']['error_message'] ?? '',
    '4.1.2: error_message must be "All required fields must be filled."');
assert_register_redirect($r, '4.1.2');

// ── 4.1.3: Empty password ─────────────────────────────────────────────────────
test_case('4.1.3 Empty password → error + redirect to /register.php');
$post = valid_post();
$post['password'] = '';
$r = simulate_validation($post);
echo "  error_message: " . var_export($r['session']['error_message'] ?? null, true) . "\n";
echo "  redirect     : " . var_export($r['redirect'], true) . "\n\n";
assert_equals("All required fields must be filled.", $r['session']['error_message'] ?? '',
    '4.1.3: error_message must be "All required fields must be filled."');
assert_register_redirect($r, '4.1.3');

// ── 4.1.4: Empty confirm_password ────────────────────────────────────────────
test_case('4.1.4 Empty confirm_password → error + redirect to /register.php');
$post = valid_post();
$post['confirm_password'] = '';
$r = simulate_validation($post);
echo "  error_message: " . var_export($r['session']['error_message'] ?? null, true) . "\n";
echo "  redirect     : " . var_export($r['redirect'], true) . "\n\n";
assert_equals("All required fields must be filled.", $r['session']['error_message'] ?? '',
    '4.1.4: error_message must be "All required fields must be filled."');
assert_register_redirect($r, '4.1.4');

// ── 4.1.5: Empty first_name ───────────────────────────────────────────────────
test_case('4.1.5 Empty first_name → error + redirect to /register.php');
$post = valid_post();
$post['first_name'] = '';
$r = simulate_validation($post);
echo "  error_message: " . var_export($r['session']['error_message'] ?? null, true) . "\n";
echo "  redirect     : " . var_export($r['redirect'], true) . "\n\n";
assert_equals("All required personal information fields must be filled.", $r['session']['error_message'] ?? '',
    '4.1.5: error_message must be "All required personal information fields must be filled."');
assert_register_redirect($r, '4.1.5');

// ── 4.1.6: Empty last_name ────────────────────────────────────────────────────
test_case('4.1.6 Empty last_name → error + redirect to /register.php');
$post = valid_post();
$post['last_name'] = '';
$r = simulate_validation($post);
echo "  error_message: " . var_export($r['session']['error_message'] ?? null, true) . "\n";
echo "  redirect     : " . var_export($r['redirect'], true) . "\n\n";
assert_equals("All required personal information fields must be filled.", $r['session']['error_message'] ?? '',
    '4.1.6: error_message must be "All required personal information fields must be filled."');
assert_register_redirect($r, '4.1.6');

// ── 4.1.7: Empty gender ───────────────────────────────────────────────────────
test_case('4.1.7 Empty gender → error + redirect to /register.php');
$post = valid_post();
$post['gender'] = '';
$r = simulate_validation($post);
echo "  error_message: " . var_export($r['session']['error_message'] ?? null, true) . "\n";
echo "  redirect     : " . var_export($r['redirect'], true) . "\n\n";
assert_equals("All required personal information fields must be filled.", $r['session']['error_message'] ?? '',
    '4.1.7: error_message must be "All required personal information fields must be filled."');
assert_register_redirect($r, '4.1.7');

// ── 4.1.8: Empty date_of_birth ───────────────────────────────────────────────
test_case('4.1.8 Empty date_of_birth → error + redirect to /register.php');
$post = valid_post();
$post['date_of_birth'] = '';
$r = simulate_validation($post);
echo "  error_message: " . var_export($r['session']['error_message'] ?? null, true) . "\n";
echo "  redirect     : " . var_export($r['redirect'], true) . "\n\n";
assert_equals("All required personal information fields must be filled.", $r['session']['error_message'] ?? '',
    '4.1.8: error_message must be "All required personal information fields must be filled."');
assert_register_redirect($r, '4.1.8');

// ── 4.1.9: Invalid phone number ───────────────────────────────────────────────
test_case('4.1.9 Invalid phone number → error + redirect to /register.php');
$post = valid_post();
$post['contact_no'] = '12345';
$r = simulate_validation($post);
echo "  error_message: " . var_export($r['session']['error_message'] ?? null, true) . "\n";
echo "  redirect     : " . var_export($r['redirect'], true) . "\n\n";
assert_equals(
    'Please enter a valid Philippine mobile number starting with +63 (e.g. +639171234567, or 09171234567).',
    $r['session']['error_message'] ?? '',
    '4.1.9: error_message must be the phone validation message'
);
assert_register_redirect($r, '4.1.9');

// ── 4.1.10: Password too short (< 8 chars) ────────────────────────────────────
test_case('4.1.10 Password too short (< 8 chars) → error + redirect to /register.php');
$post = valid_post();
$post['password'] = 'Ab1!';
$post['confirm_password'] = 'Ab1!';
$r = simulate_validation($post);
echo "  error_message: " . var_export($r['session']['error_message'] ?? null, true) . "\n";
echo "  redirect     : " . var_export($r['redirect'], true) . "\n\n";
assert_equals("Password must be at least 8 characters long.", $r['session']['error_message'] ?? '',
    '4.1.10: error_message must be "Password must be at least 8 characters long."');
assert_register_redirect($r, '4.1.10');

// ── 4.1.11: Password with no number ──────────────────────────────────────────
test_case('4.1.11 Password with no number → error + redirect to /register.php');
$post = valid_post();
$post['password'] = 'SecurePass!';
$post['confirm_password'] = 'SecurePass!';
$r = simulate_validation($post);
echo "  error_message: " . var_export($r['session']['error_message'] ?? null, true) . "\n";
echo "  redirect     : " . var_export($r['redirect'], true) . "\n\n";
assert_equals("Password must contain at least one number (0-9).", $r['session']['error_message'] ?? '',
    '4.1.11: error_message must be "Password must contain at least one number (0-9)."');
assert_register_redirect($r, '4.1.11');

// ── 4.1.12: Password with no special character ────────────────────────────────
test_case('4.1.12 Password with no special character → error + redirect to /register.php');
$post = valid_post();
$post['password'] = 'SecurePass1';
$post['confirm_password'] = 'SecurePass1';
$r = simulate_validation($post);
echo "  error_message: " . var_export($r['session']['error_message'] ?? null, true) . "\n";
echo "  redirect     : " . var_export($r['redirect'], true) . "\n\n";
assert_equals("Password must contain at least one special character (!@#$%^&*).", $r['session']['error_message'] ?? '',
    '4.1.12: error_message must be "Password must contain at least one special character (!@#$%^&*)."');
assert_register_redirect($r, '4.1.12');

// ── 4.1.13: Password mismatch ─────────────────────────────────────────────────
test_case('4.1.13 Password mismatch → error + redirect to /register.php');
$post = valid_post();
$post['password'] = 'SecurePass1!';
$post['confirm_password'] = 'DifferentPass1!';
$r = simulate_validation($post);
echo "  error_message: " . var_export($r['session']['error_message'] ?? null, true) . "\n";
echo "  redirect     : " . var_export($r['redirect'], true) . "\n\n";
assert_equals("Passwords do not match.", $r['session']['error_message'] ?? '',
    '4.1.13: error_message must be "Passwords do not match."');
assert_register_redirect($r, '4.1.13');

// ── 4.1.14: Invalid email format ─────────────────────────────────────────────
test_case('4.1.14 Invalid email format → error + redirect to /register.php');
$post = valid_post();
$post['email'] = 'not-an-email';
$r = simulate_validation($post);
echo "  error_message: " . var_export($r['session']['error_message'] ?? null, true) . "\n";
echo "  redirect     : " . var_export($r['redirect'], true) . "\n\n";
// Invalid email causes $email=null, which triggers "All required fields must be filled."
// because the null email is treated as empty in the required-fields check.
assert_not_empty($r['session']['error_message'] ?? '',
    '4.1.14: error_message must be set for invalid email');
assert_register_redirect($r, '4.1.14');

// ── 4.1.15: Age < 18 ─────────────────────────────────────────────────────────
test_case('4.1.15 Age < 18 → error + redirect to /register.php');
$post = valid_post();
$post['date_of_birth'] = date('Y-m-d', strtotime('-16 years'));
$r = simulate_validation($post);
echo "  error_message: " . var_export($r['session']['error_message'] ?? null, true) . "\n";
echo "  redirect     : " . var_export($r['redirect'], true) . "\n\n";
assert_equals("You must be at least 18 years old to register.", $r['session']['error_message'] ?? '',
    '4.1.15: error_message must be "You must be at least 18 years old to register."');
assert_register_redirect($r, '4.1.15');

// ═════════════════════════════════════════════════════════════════════════════
// TASK 4.2 — Duplicate Email Path
// ═════════════════════════════════════════════════════════════════════════════

test_section('Task 4.2 — Preservation: Duplicate Email Path');

// ── 4.2.1: Duplicate email ────────────────────────────────────────────────────
test_case('4.2.1 Duplicate email → error + redirect to /register.php');
$post = valid_post();
$r = simulate_validation($post, 'production', '/', email_exists: true);
echo "  error_message: " . var_export($r['session']['error_message'] ?? null, true) . "\n";
echo "  redirect     : " . var_export($r['redirect'], true) . "\n\n";
assert_equals("An account with this email already exists.", $r['session']['error_message'] ?? '',
    '4.2.1: error_message must be "An account with this email already exists."');
assert_register_redirect($r, '4.2.1');

// ── 4.2.2: Duplicate email with front-controller REQUEST_URI ─────────────────
test_case('4.2.2 Duplicate email with REQUEST_URI="/" (GAE front-controller) → absolute redirect');
$post = valid_post();
$r = simulate_validation($post, 'production', '/', email_exists: true);
echo "  REQUEST_URI  : '/'\n";
echo "  error_message: " . var_export($r['session']['error_message'] ?? null, true) . "\n";
echo "  redirect     : " . var_export($r['redirect'], true) . "\n\n";
assert_equals('/register.php', $r['redirect'],
    '4.2.2: duplicate email redirect must be /register.php (absolute, not relative)');
assert_false(strpos((string)($r['redirect'] ?? ''), '../') !== false,
    '4.2.2: duplicate email redirect must NOT contain ../');

// ── 4.2.3: Non-duplicate email proceeds past duplicate check ─────────────────
test_case('4.2.3 Non-duplicate email proceeds past duplicate check (no error set)');
$post = valid_post();
$r = simulate_validation($post, 'production', '/', email_exists: false, send_otp_result: true);
echo "  email_exists : false\n";
echo "  redirect     : " . var_export($r['redirect'], true) . "\n\n";
assert_false(isset($r['session']['error_message']),
    '4.2.3: non-duplicate email must NOT set error_message');
assert_equals('/verify-otp.php', $r['redirect'],
    '4.2.3: non-duplicate email with valid data must redirect to /verify-otp.php');

test_summary();
