<?php
/**
 * Task 4.5 — Property-Based Test: app_url() Always Returns Absolute Paths
 *
 * Validates: Requirements 2.2
 *
 * Property: For ANY $_SERVER configuration (varied REQUEST_URI, SCRIPT_NAME,
 * DOCUMENT_ROOT values), app_url('/register.php') and app_url('/verify-otp.php')
 * ALWAYS:
 *   1. Return a path starting with '/' (absolute)
 *   2. Never contain '../' (no relative traversal)
 *   3. End with the expected filename (/register.php or /verify-otp.php)
 *   4. Never return an empty string
 *
 * Runs at least 30 varied server configurations.
 *
 * Run: D:\xampp\php\php.exe tests/test_pbt_app_url.php
 */

require_once __DIR__ . '/test_helpers.php';

// ─── app_url() test via subprocess ───────────────────────────────────────────
// app_url() uses app_base_path() which caches its result in a static variable.
// To test varied $_SERVER configurations, we must run each in a fresh PHP process.

/**
 * Run app_url() in a subprocess with the given $_SERVER configuration.
 * Returns ['register' => string, 'verify_otp' => string].
 *
 * @param array $server_vars  $_SERVER values to set before calling app_url()
 * @return array{register: string, verify_otp: string, raw_output: string}
 */
function run_app_url_subprocess(array $server_vars): array {
    $server_assignments = '';
    foreach ($server_vars as $key => $value) {
        $server_assignments .= '$_SERVER[' . var_export($key, true) . '] = ' . var_export($value, true) . ";\n";
    }

    $functions_path = var_export(realpath(__DIR__ . '/../includes/functions.php'), true);

    $script = <<<PHP
<?php
{$server_assignments}
require_once {$functions_path};
\$register   = app_url('/register.php');
\$verify_otp = app_url('/verify-otp.php');
echo json_encode(['register' => \$register, 'verify_otp' => \$verify_otp]);
PHP;

    $tmp = sys_get_temp_dir() . '/pbt_appurl_' . uniqid() . '.php';
    file_put_contents($tmp, $script);
    $output = trim((string) shell_exec('D:\\xampp\\php\\php.exe ' . escapeshellarg($tmp) . ' 2>&1'));
    @unlink($tmp);

    $decoded = json_decode($output, true);
    if (!is_array($decoded)) {
        return ['register' => '', 'verify_otp' => '', 'raw_output' => $output];
    }

    return [
        'register'   => (string)($decoded['register']   ?? ''),
        'verify_otp' => (string)($decoded['verify_otp'] ?? ''),
        'raw_output' => $output,
    ];
}

// ─── Server configuration generators ─────────────────────────────────────────

/**
 * Generate a varied $_SERVER configuration for testing.
 *
 * @param int $seed  Random seed
 * @return array{server: array, description: string}
 */
function generate_server_config(int $seed): array {
    mt_srand($seed);

    $doc_roots = [
        'D:/xampp/htdocs/CommunaLink',
        'D:/xampp/htdocs',
        'C:/inetpub/wwwroot/CommunaLink',
        '/var/www/html/CommunaLink',
        '/var/www/html',
        '/home/user/public_html',
        'D:/xampp/htdocs/myapp',
    ];

    $script_names = [
        '/index.php',
        '/CommunaLink/index.php',
        '/app/index.php',
        '/public/index.php',
        '/index.php',
    ];

    $request_uris = [
        '/',
        '/index.php',
        '/register.php',
        '/verify-otp.php',
        '/login.php',
        '/about',
        '/CommunaLink/',
        '/CommunaLink/index.php',
        '/app/',
    ];

    $doc_root   = $doc_roots[mt_rand(0, count($doc_roots) - 1)];
    $script     = $script_names[mt_rand(0, count($script_names) - 1)];
    $req_uri    = $request_uris[mt_rand(0, count($request_uris) - 1)];

    $server = [
        'DOCUMENT_ROOT' => $doc_root,
        'SCRIPT_NAME'   => $script,
        'REQUEST_URI'   => $req_uri,
        'PHP_SELF'      => $script,
    ];

    $desc = "DOCUMENT_ROOT={$doc_root}, SCRIPT_NAME={$script}, REQUEST_URI={$req_uri}";

    return ['server' => $server, 'description' => $desc];
}

// ─── Property-Based Test ──────────────────────────────────────────────────────

test_section('Task 4.5 — PBT: app_url() Always Returns Absolute Paths');

echo "  Property: For ANY \$_SERVER configuration, app_url('/register.php') and\n";
echo "  app_url('/verify-otp.php') MUST:\n";
echo "    1. Start with '/' (absolute path)\n";
echo "    2. Never contain '../' (no relative traversal)\n";
echo "    3. End with the expected filename\n";
echo "    4. Never be empty\n\n";

$NUM_TRIALS = 35; // Run at least 30 as required
$failures   = [];

echo "  Running $NUM_TRIALS varied server configurations...\n\n";

for ($i = 0; $i < $NUM_TRIALS; $i++) {
    $seed   = 2000 + $i;
    $config = generate_server_config($seed);
    $server = $config['server'];
    $desc   = $config['description'];

    $result = run_app_url_subprocess($server);

    $register   = $result['register'];
    $verify_otp = $result['verify_otp'];

    // Check properties for /register.php
    $reg_starts_slash  = strpos($register, '/') === 0;
    $reg_no_dot_dot    = strpos($register, '../') === false;
    $reg_ends_register = (substr($register, -strlen('/register.php')) === '/register.php');
    $reg_not_empty     = $register !== '';

    // Check properties for /verify-otp.php
    $otp_starts_slash  = strpos($verify_otp, '/') === 0;
    $otp_no_dot_dot    = strpos($verify_otp, '../') === false;
    $otp_ends_verify   = (substr($verify_otp, -strlen('/verify-otp.php')) === '/verify-otp.php');
    $otp_not_empty     = $verify_otp !== '';

    $trial_ok = $reg_starts_slash && $reg_no_dot_dot && $reg_ends_register && $reg_not_empty
             && $otp_starts_slash && $otp_no_dot_dot && $otp_ends_verify   && $otp_not_empty;

    if (!$trial_ok) {
        $failures[] = [
            'trial'       => $i + 1,
            'seed'        => $seed,
            'description' => $desc,
            'register'    => $register,
            'verify_otp'  => $verify_otp,
            'raw_output'  => $result['raw_output'],
            'reg_checks'  => compact('reg_starts_slash','reg_no_dot_dot','reg_ends_register','reg_not_empty'),
            'otp_checks'  => compact('otp_starts_slash','otp_no_dot_dot','otp_ends_verify','otp_not_empty'),
        ];
    }
}

if (empty($failures)) {
    $GLOBALS['_test_pass_count']++;
    echo "  ✓ PASS: All $NUM_TRIALS server configurations produced valid absolute paths\n";
} else {
    $GLOBALS['_test_fail_count']++;
    $GLOBALS['_test_failures'][] = "PBT app_url: " . count($failures) . " server configurations produced invalid paths";
    echo "  ✗ FAIL: " . count($failures) . " trial(s) did not satisfy the property:\n\n";
    foreach ($failures as $f) {
        echo "    Trial #{$f['trial']} (seed={$f['seed']}):\n";
        echo "      Config      : {$f['description']}\n";
        echo "      register    : " . var_export($f['register'], true) . "\n";
        echo "      verify_otp  : " . var_export($f['verify_otp'], true) . "\n";
        echo "      raw_output  : " . var_export($f['raw_output'], true) . "\n";
        foreach ($f['reg_checks'] as $k => $v) {
            echo "      $k: " . ($v ? 'OK' : 'FAIL ✗') . "\n";
        }
        foreach ($f['otp_checks'] as $k => $v) {
            echo "      $k: " . ($v ? 'OK' : 'FAIL ✗') . "\n";
        }
        echo "\n";
    }
}

// ── Coverage check ────────────────────────────────────────────────────────────
test_case('PBT coverage: at least 30 server configurations tested');
assert_true($NUM_TRIALS >= 30,
    "PBT must test at least 30 server configurations (tested $NUM_TRIALS)");

// ─── Deterministic spot-checks ────────────────────────────────────────────────

test_section('PBT Spot-Checks: Known Server Configurations');

$spot_configs = [
    [
        'desc'   => 'GAE front-controller: DOCUMENT_ROOT=project root, REQUEST_URI=/',
        'server' => [
            'DOCUMENT_ROOT' => 'D:/xampp/htdocs/CommunaLink',
            'SCRIPT_NAME'   => '/index.php',
            'REQUEST_URI'   => '/',
            'PHP_SELF'      => '/index.php',
        ],
        'expected_register'   => '/register.php',
        'expected_verify_otp' => '/verify-otp.php',
    ],
    [
        'desc'   => 'Subfolder deployment: DOCUMENT_ROOT=htdocs, SCRIPT_NAME=/CommunaLink/index.php',
        'server' => [
            'DOCUMENT_ROOT' => 'D:/xampp/htdocs',
            'SCRIPT_NAME'   => '/CommunaLink/index.php',
            'REQUEST_URI'   => '/CommunaLink/',
            'PHP_SELF'      => '/CommunaLink/index.php',
        ],
        'expected_register'   => null, // path depends on base path detection
        'expected_verify_otp' => null,
    ],
    [
        'desc'   => 'Linux deployment: DOCUMENT_ROOT=/var/www/html/CommunaLink',
        'server' => [
            'DOCUMENT_ROOT' => '/var/www/html/CommunaLink',
            'SCRIPT_NAME'   => '/index.php',
            'REQUEST_URI'   => '/',
            'PHP_SELF'      => '/index.php',
        ],
        'expected_register'   => '/register.php',
        'expected_verify_otp' => '/verify-otp.php',
    ],
    [
        'desc'   => 'REQUEST_URI=/register.php (direct access)',
        'server' => [
            'DOCUMENT_ROOT' => 'D:/xampp/htdocs/CommunaLink',
            'SCRIPT_NAME'   => '/index.php',
            'REQUEST_URI'   => '/register.php',
            'PHP_SELF'      => '/index.php',
        ],
        'expected_register'   => '/register.php',
        'expected_verify_otp' => '/verify-otp.php',
    ],
];

foreach ($spot_configs as $sc) {
    test_case("Spot-check: {$sc['desc']}");
    $result = run_app_url_subprocess($sc['server']);
    $register   = $result['register'];
    $verify_otp = $result['verify_otp'];

    echo "  app_url('/register.php')   = " . var_export($register, true) . "\n";
    echo "  app_url('/verify-otp.php') = " . var_export($verify_otp, true) . "\n\n";

    // Always check absolute path and no ../
    assert_true(strpos($register, '/') === 0,
        "{$sc['desc']}: register must start with /");
    assert_false(strpos($register, '../') !== false,
        "{$sc['desc']}: register must NOT contain ../");
    assert_not_empty($register,
        "{$sc['desc']}: register must not be empty");

    assert_true(strpos($verify_otp, '/') === 0,
        "{$sc['desc']}: verify-otp must start with /");
    assert_false(strpos($verify_otp, '../') !== false,
        "{$sc['desc']}: verify-otp must NOT contain ../");
    assert_not_empty($verify_otp,
        "{$sc['desc']}: verify-otp must not be empty");

    // Check expected values if specified
    if ($sc['expected_register'] !== null) {
        assert_equals($sc['expected_register'], $register,
            "{$sc['desc']}: register must equal {$sc['expected_register']}");
    }
    if ($sc['expected_verify_otp'] !== null) {
        assert_equals($sc['expected_verify_otp'], $verify_otp,
            "{$sc['desc']}: verify-otp must equal {$sc['expected_verify_otp']}");
    }

    // Always check filename suffix
    assert_true(substr($register, -strlen('/register.php')) === '/register.php',
        "{$sc['desc']}: register must end with /register.php");
    assert_true(substr($verify_otp, -strlen('/verify-otp.php')) === '/verify-otp.php',
        "{$sc['desc']}: verify-otp must end with /verify-otp.php");
}

test_summary();
