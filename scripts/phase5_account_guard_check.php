<?php

declare(strict_types=1);

/**
 * Phase 5 static guard checks for account and privileged-user provisioning flows.
 *
 * Usage:
 *   php scripts/phase5_account_guard_check.php
 */

$root = dirname(__DIR__);

$checks = [
    [
        'path' => 'admin/pages/account.php',
        'must_contain' => [
            'csrf_field()',
            'name="current_password"'
        ],
    ],
    [
        'path' => 'admin/partials/account-handler.php',
        'must_contain' => [
            'csrf_validate()',
            'PasswordSecurity::validatePassword',
            'Current password is required to change email or password.'
        ],
    ],
    [
        'path' => 'admin/pages/add-user.php',
        'must_contain' => [
            'name="resident_id"',
            '!!this.residentId',
            '@input="handleFullnameInput()"'
        ],
    ],
    [
        'path' => 'admin/partials/add-user-handler.php',
        'must_contain' => [
            '$resident_id = isset($_POST[\'resident_id\']) ? (int)$_POST[\'resident_id\'] : 0;',
            'WHERE id = ?',
            'already has a privileged account'
        ],
    ],
    [
        'path' => 'includes/register-handler.php',
        'must_contain' => [
            'log_activity_db_system(',
            'development OTP fallback was used'
        ],
    ],
];

$failures = [];

foreach ($checks as $check) {
    $relativePath = $check['path'];
    $absolutePath = $root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relativePath);

    if (!is_file($absolutePath)) {
        $failures[] = "[MISSING FILE] {$relativePath}";
        continue;
    }

    $content = file_get_contents($absolutePath);
    if ($content === false) {
        $failures[] = "[UNREADABLE FILE] {$relativePath}";
        continue;
    }

    foreach ($check['must_contain'] as $needle) {
        if (strpos($content, $needle) === false) {
            $failures[] = "[MISSING MARKER] {$relativePath} -> {$needle}";
        }
    }
}

if (!empty($failures)) {
    echo "Phase 5 guard check: FAILED\n";
    foreach ($failures as $failure) {
        echo " - {$failure}\n";
    }
    exit(1);
}

echo "Phase 5 guard check: PASSED\n";
exit(0);
