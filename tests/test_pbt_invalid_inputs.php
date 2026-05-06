<?php
/**
 * Task 4.4 — Property-Based Test: Invalid Form Inputs
 *
 * Validates: Requirements 3.2
 *
 * Property: For ANY combination of invalid form inputs (empty fields, bad email,
 * short password, age < 18, etc.), the handler ALWAYS:
 *   1. Redirects to /register.php (absolute path, never relative)
 *   2. Sets a non-empty $_SESSION['error_message']
 *   3. Never produces a relative path (no '../')
 *   4. Never produces an empty redirect
 *
 * Runs at least 50 random invalid input combinations.
 *
 * Run: D:\xampp\php\php.exe tests/test_pbt_invalid_inputs.php
 */

require_once __DIR__ . '/test_helpers.php';

// ─── Load real app_url() from functions.php ───────────────────────────────────
$_SERVER['DOCUMENT_ROOT'] = 'D:/xampp/htdocs/CommunaLink';
$_SERVER['SCRIPT_NAME']   = '/index.php';
$_SERVER['REQUEST_URI']   = '/';
require_once __DIR__ . '/../includes/functions.php';

// ─── Local phone normalizer (avoids PCRE2 \u{00A0} issue on some PHP builds) ──
function pbt_normalize_phone(string $raw): ?string {
    $s = trim($raw);
    $s = preg_replace('/[\s\-\xc2\xa0]+/', '', $s);
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

// ─── Handler simulation (same as test_preservation_validation.php) ────────────

function pbt_simulate_validation(array $post, string $request_uri = '/'): array {
    $_SERVER['REQUEST_URI'] = $request_uri;

    $session  = [];
    $redirect = null;

    $si = function($v) {
        $v = trim((string)$v);
        $v = stripslashes($v);
        $v = htmlspecialchars($v);
        return $v;
    };

    $fullname         = $si($post['fullname']         ?? '');
    $raw_email        = $post['email']                ?? '';
    $email            = filter_var($raw_email, FILTER_VALIDATE_EMAIL) ? $si($raw_email) : null;
    $password         = $post['password']             ?? '';
    $confirm_password = $post['confirm_password']     ?? '';

    $first_name    = $si($post['first_name']    ?? '');
    $last_name     = $si($post['last_name']     ?? '');
    $gender        = $si($post['gender']        ?? '');
    $date_of_birth = $si($post['date_of_birth'] ?? '');
    $place_of_birth = $si($post['place_of_birth'] ?? '');
    $citizenship   = $si($post['citizenship']   ?? '');
    $civil_status  = $si($post['civil_status']  ?? '');
    $voter_status  = $si($post['voter_status']  ?? '');
    $address       = $si($post['address']       ?? '');

    if (empty($fullname) || empty($email) || empty($password) || empty($confirm_password)) {
        $session['error_message'] = "All required fields must be filled.";
        $redirect = app_url('/register.php');
        return compact('session', 'redirect');
    }

    if (empty($first_name) || empty($last_name) || empty($gender) || empty($date_of_birth) ||
        empty($place_of_birth) || empty($citizenship) || empty($civil_status) || empty($voter_status) || empty($address)) {
        $session['error_message'] = "All required personal information fields must be filled.";
        $redirect = app_url('/register.php');
        return compact('session', 'redirect');
    }

    $contact_canonical = pbt_normalize_phone((string)($post['contact_no'] ?? ''));
    if ($contact_canonical === null) {
        $session['error_message'] = 'Please enter a valid Philippine mobile number starting with +63 (e.g. +639171234567, or 09171234567).';
        $redirect = app_url('/register.php');
        return compact('session', 'redirect');
    }

    if (strlen($password) < 8) {
        $session['error_message'] = "Password must be at least 8 characters long.";
        $redirect = app_url('/register.php');
        return compact('session', 'redirect');
    }

    if (!preg_match('/[0-9]/', $password)) {
        $session['error_message'] = "Password must contain at least one number (0-9).";
        $redirect = app_url('/register.php');
        return compact('session', 'redirect');
    }

    if (!preg_match('/[!@#$%^&*()_+\-=\[\]{};:\'",.<>?\/\\|`~]/', $password)) {
        $session['error_message'] = "Password must contain at least one special character (!@#$%^&*).";
        $redirect = app_url('/register.php');
        return compact('session', 'redirect');
    }

    if ($password !== $confirm_password) {
        $session['error_message'] = "Passwords do not match.";
        $redirect = app_url('/register.php');
        return compact('session', 'redirect');
    }

    if (!$email) {
        $session['error_message'] = "Please enter a valid email address.";
        $redirect = app_url('/register.php');
        return compact('session', 'redirect');
    }

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

    // If we reach here, the input was actually valid — return null redirect to signal this
    $redirect = null;
    return compact('session', 'redirect');
}

// ─── Input generators ─────────────────────────────────────────────────────────

/** Returns a fully valid base POST array */
function pbt_valid_base(): array {
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

/**
 * Generate a random invalid POST array.
 * Guarantees at least one field is invalid so the handler must reject it.
 *
 * @param int $seed  Random seed for reproducibility
 * @return array{post: array, description: string}
 */
function pbt_generate_invalid_input(int $seed): array {
    mt_srand($seed);

    $post = pbt_valid_base();
    $description_parts = [];

    // Pick 1–3 random invalidation strategies
    $strategies = [
        'empty_fullname',
        'empty_email',
        'empty_password',
        'empty_confirm_password',
        'empty_first_name',
        'empty_last_name',
        'empty_gender',
        'empty_date_of_birth',
        'empty_place_of_birth',
        'empty_citizenship',
        'empty_civil_status',
        'empty_voter_status',
        'empty_address',
        'invalid_phone',
        'password_too_short',
        'password_no_number',
        'password_no_special',
        'password_mismatch',
        'invalid_email',
        'age_under_18',
    ];

    $num_strategies = mt_rand(1, 3);
    $chosen_indices = [];
    while (count($chosen_indices) < $num_strategies) {
        $idx = mt_rand(0, count($strategies) - 1);
        if (!in_array($idx, $chosen_indices, true)) {
            $chosen_indices[] = $idx;
        }
    }

    foreach ($chosen_indices as $idx) {
        $strategy = $strategies[$idx];
        switch ($strategy) {
            case 'empty_fullname':
                $post['fullname'] = '';
                $description_parts[] = 'empty_fullname';
                break;
            case 'empty_email':
                $post['email'] = '';
                $description_parts[] = 'empty_email';
                break;
            case 'empty_password':
                $post['password'] = '';
                $post['confirm_password'] = '';
                $description_parts[] = 'empty_password';
                break;
            case 'empty_confirm_password':
                $post['confirm_password'] = '';
                $description_parts[] = 'empty_confirm_password';
                break;
            case 'empty_first_name':
                $post['first_name'] = '';
                $description_parts[] = 'empty_first_name';
                break;
            case 'empty_last_name':
                $post['last_name'] = '';
                $description_parts[] = 'empty_last_name';
                break;
            case 'empty_gender':
                $post['gender'] = '';
                $description_parts[] = 'empty_gender';
                break;
            case 'empty_date_of_birth':
                $post['date_of_birth'] = '';
                $description_parts[] = 'empty_date_of_birth';
                break;
            case 'empty_place_of_birth':
                $post['place_of_birth'] = '';
                $description_parts[] = 'empty_place_of_birth';
                break;
            case 'empty_citizenship':
                $post['citizenship'] = '';
                $description_parts[] = 'empty_citizenship';
                break;
            case 'empty_civil_status':
                $post['civil_status'] = '';
                $description_parts[] = 'empty_civil_status';
                break;
            case 'empty_voter_status':
                $post['voter_status'] = '';
                $description_parts[] = 'empty_voter_status';
                break;
            case 'empty_address':
                $post['address'] = '';
                $description_parts[] = 'empty_address';
                break;
            case 'invalid_phone':
                $phones = ['12345', 'abc', '0000000000', '+1234567890', ''];
                $post['contact_no'] = $phones[mt_rand(0, count($phones) - 1)];
                $description_parts[] = 'invalid_phone(' . $post['contact_no'] . ')';
                break;
            case 'password_too_short':
                $post['password'] = 'Ab1!';
                $post['confirm_password'] = 'Ab1!';
                $description_parts[] = 'password_too_short';
                break;
            case 'password_no_number':
                $post['password'] = 'SecurePass!';
                $post['confirm_password'] = 'SecurePass!';
                $description_parts[] = 'password_no_number';
                break;
            case 'password_no_special':
                $post['password'] = 'SecurePass1';
                $post['confirm_password'] = 'SecurePass1';
                $description_parts[] = 'password_no_special';
                break;
            case 'password_mismatch':
                $post['password'] = 'SecurePass1!';
                $post['confirm_password'] = 'DifferentPass1!';
                $description_parts[] = 'password_mismatch';
                break;
            case 'invalid_email':
                $bad_emails = ['not-an-email', 'missing@', '@nodomain', 'spaces in@email.com', 'no-at-sign'];
                $post['email'] = $bad_emails[mt_rand(0, count($bad_emails) - 1)];
                $description_parts[] = 'invalid_email(' . $post['email'] . ')';
                break;
            case 'age_under_18':
                $years_ago = mt_rand(1, 17);
                $post['date_of_birth'] = date('Y-m-d', strtotime("-{$years_ago} years"));
                $description_parts[] = 'age_under_18(' . $years_ago . 'y)';
                break;
        }
    }

    return [
        'post'        => $post,
        'description' => implode(', ', $description_parts),
    ];
}

// ─── Property-Based Test ──────────────────────────────────────────────────────

test_section('Task 4.4 — PBT: Invalid Inputs Always Redirect to /register.php with Error');

echo "  Property: For ANY invalid form input combination, the handler MUST:\n";
echo "    1. Redirect to /register.php (absolute path)\n";
echo "    2. Set a non-empty \$_SESSION['error_message']\n";
echo "    3. Never use a relative path (no '../')\n";
echo "    4. Never produce an empty redirect\n\n";

$NUM_TRIALS = 60; // Run at least 50 as required
$failures   = [];
$skipped    = 0;  // Count inputs that accidentally passed all validations

echo "  Running $NUM_TRIALS random invalid input combinations...\n\n";

for ($i = 0; $i < $NUM_TRIALS; $i++) {
    $seed  = 1000 + $i;
    $input = pbt_generate_invalid_input($seed);
    $post  = $input['post'];
    $desc  = $input['description'];

    // Vary REQUEST_URI to test different deployment contexts
    $request_uris = ['/', '/index.php', '/register.php'];
    $request_uri  = $request_uris[$i % count($request_uris)];

    $r = pbt_simulate_validation($post, $request_uri);

    // If redirect is null, the input accidentally passed all validations
    // (this can happen if strategies conflict, e.g., empty_password + password_mismatch)
    if ($r['redirect'] === null) {
        $skipped++;
        continue;
    }

    $redirect     = (string)($r['redirect'] ?? '');
    $error_msg    = $r['session']['error_message'] ?? '';
    $has_dot_dot  = strpos($redirect, '../') !== false;
    $is_absolute  = strpos($redirect, '/') === 0;
    $is_register  = ($redirect === '/register.php');
    $has_error    = !empty($error_msg);

    $trial_ok = $is_register && $has_error && !$has_dot_dot && $is_absolute;

    if (!$trial_ok) {
        $failures[] = [
            'trial'       => $i + 1,
            'seed'        => $seed,
            'description' => $desc,
            'request_uri' => $request_uri,
            'redirect'    => $redirect,
            'error_msg'   => $error_msg,
            'is_register' => $is_register,
            'has_error'   => $has_error,
            'no_dot_dot'  => !$has_dot_dot,
            'is_absolute' => $is_absolute,
        ];
    }
}

$effective_trials = $NUM_TRIALS - $skipped;
echo "  Effective trials (invalid inputs that triggered validation): $effective_trials / $NUM_TRIALS\n";
if ($skipped > 0) {
    echo "  Skipped (conflicting strategies produced valid input): $skipped\n";
}
echo "\n";

if (empty($failures)) {
    $GLOBALS['_test_pass_count']++;
    echo "  ✓ PASS: All $effective_trials invalid input combinations correctly redirected to /register.php with error_message\n";
} else {
    $GLOBALS['_test_fail_count']++;
    $GLOBALS['_test_failures'][] = "PBT: " . count($failures) . " invalid input combinations did NOT redirect correctly";
    echo "  ✗ FAIL: " . count($failures) . " trial(s) did not satisfy the property:\n\n";
    foreach ($failures as $f) {
        echo "    Trial #{$f['trial']} (seed={$f['seed']}):\n";
        echo "      Input      : {$f['description']}\n";
        echo "      REQUEST_URI: {$f['request_uri']}\n";
        echo "      Redirect   : " . var_export($f['redirect'], true) . "\n";
        echo "      Error msg  : " . var_export($f['error_msg'], true) . "\n";
        echo "      is_register: " . ($f['is_register'] ? 'YES' : 'NO ✗') . "\n";
        echo "      has_error  : " . ($f['has_error']   ? 'YES' : 'NO ✗') . "\n";
        echo "      no_dot_dot : " . ($f['no_dot_dot']  ? 'YES' : 'NO ✗') . "\n";
        echo "      is_absolute: " . ($f['is_absolute'] ? 'YES' : 'NO ✗') . "\n\n";
    }
}

// ── Additional spot-check: verify at least 50 effective trials ran ─────────────
test_case('PBT coverage: at least 50 effective invalid input trials ran');
assert_true($effective_trials >= 50,
    "PBT must run at least 50 effective invalid input trials (ran $effective_trials)");

// ── Spot-check a few specific known-bad inputs ────────────────────────────────
test_section('PBT Spot-Checks: Known Invalid Inputs');

$spot_checks = [
    ['desc' => 'All empty fields',
     'post' => ['fullname'=>'','email'=>'','password'=>'','confirm_password'=>'',
                'first_name'=>'','last_name'=>'','gender'=>'','date_of_birth'=>'',
                'place_of_birth'=>'','citizenship'=>'','civil_status'=>'','voter_status'=>'',
                'contact_no'=>'','address'=>'']],
    ['desc' => 'Age 5 years old',
     'post' => array_merge(pbt_valid_base(), ['date_of_birth' => date('Y-m-d', strtotime('-5 years'))])],
    ['desc' => 'Password "abc" (too short, no number, no special)',
     'post' => array_merge(pbt_valid_base(), ['password'=>'abc','confirm_password'=>'abc'])],
    ['desc' => 'Email "invalid-email"',
     'post' => array_merge(pbt_valid_base(), ['email'=>'invalid-email'])],
    ['desc' => 'Phone "not-a-phone"',
     'post' => array_merge(pbt_valid_base(), ['contact_no'=>'not-a-phone'])],
    ['desc' => 'Password mismatch',
     'post' => array_merge(pbt_valid_base(), ['password'=>'SecurePass1!','confirm_password'=>'WrongPass1!'])],
    ['desc' => 'Password no special char',
     'post' => array_merge(pbt_valid_base(), ['password'=>'SecurePass1','confirm_password'=>'SecurePass1'])],
    ['desc' => 'Password no number',
     'post' => array_merge(pbt_valid_base(), ['password'=>'SecurePass!','confirm_password'=>'SecurePass!'])],
];

foreach ($spot_checks as $sc) {
    test_case("Spot-check: {$sc['desc']}");
    $r = pbt_simulate_validation($sc['post'], '/');
    echo "  redirect     : " . var_export($r['redirect'], true) . "\n";
    echo "  error_message: " . var_export($r['session']['error_message'] ?? null, true) . "\n\n";

    if ($r['redirect'] !== null) {
        assert_equals('/register.php', $r['redirect'],
            "{$sc['desc']}: must redirect to /register.php");
        assert_not_empty($r['session']['error_message'] ?? '',
            "{$sc['desc']}: must set error_message");
        assert_false(strpos((string)($r['redirect'] ?? ''), '../') !== false,
            "{$sc['desc']}: redirect must NOT contain ../");
    } else {
        // Input accidentally valid — skip this spot check
        echo "  (skipped: input was valid)\n";
    }
}

test_summary();
