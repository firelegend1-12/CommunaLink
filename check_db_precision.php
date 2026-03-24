<?php
require_once __DIR__ . '/config/env_loader.php';

$app_env = strtolower((string) env('APP_ENV', 'production'));

if ($app_env === 'production') {
    http_response_code(404);
    exit('Not Found');
}

if (PHP_SAPI !== 'cli') {
    require_once __DIR__ . '/includes/auth.php';

    if (!is_logged_in() || !is_admin_or_official()) {
        http_response_code(403);
        exit('Forbidden');
    }
}

require_once 'config/init.php';

function check_table($pdo, $table) {
    echo "\n--- Table: $table ---\n";
    try {
        $stmt = $pdo->query("SHOW COLUMNS FROM `$table` ");
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            echo "{$row['Field']} ({$row['Type']})\n";
        }
    } catch (Exception $e) {
        echo "Error: " . $e->getMessage() . "\n";
    }
}

check_table($pdo, 'notifications');
check_table($pdo, 'document_requests');
check_table($pdo, 'business_transactions');
