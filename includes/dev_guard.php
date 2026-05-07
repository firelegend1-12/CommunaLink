<?php
/**
 * Guard public diagnostic/setup scripts.
 *
 * Production returns 404 so the scripts are not discoverable. Non-production
 * allows CLI usage or authenticated admin/official web sessions.
 */

require_once __DIR__ . '/../config/env_loader.php';

function communalink_require_diagnostic_access(): void
{
    $app_env = strtolower((string) env('APP_ENV', 'production'));
    if (in_array($app_env, ['production', 'prod'], true)) {
        http_response_code(404);
        exit('Not Found');
    }

    if (PHP_SAPI === 'cli') {
        return;
    }

    require_once __DIR__ . '/auth.php';

    if (!is_logged_in() || !is_admin_or_official()) {
        http_response_code(403);
        exit('Forbidden');
    }
}

communalink_require_diagnostic_access();
