<?php
require_once '../../config/init.php';
require_once '../../includes/auth.php';
require_once '../../includes/functions.php';
require_once '../../includes/csrf.php';
require_once '../../includes/permission_checker.php';

require_login();
require_permission_or_redirect('system_logs', '../pages/logs.php');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../pages/logs.php');
    exit;
}

csrf_require();

$keep_days = isset($_POST['keep_days']) ? (int) $_POST['keep_days'] : 90;
$keep_days = max(30, min(365, $keep_days));

$result = archive_old_activity_logs($pdo, $keep_days, 1000);

if (($result['archived_count'] ?? 0) > 0) {
    $_SESSION['success_message'] = sprintf(
        'Archive completed: %d logs moved to archive (batch: %s).',
        (int) $result['archived_count'],
        (string) ($result['batch_id'] ?? 'n/a')
    );

    log_activity_db(
        $pdo,
        'archive',
        'activity_logs',
        null,
        sprintf('Archived %d logs older than %d days (batch: %s)', (int) $result['archived_count'], $keep_days, (string) ($result['batch_id'] ?? 'n/a')),
        null,
        null,
        'warning'
    );
} else {
    $_SESSION['warning_message'] = $result['message'] ?? 'No logs archived.';
}

header('Location: ../pages/logs.php');
exit;
