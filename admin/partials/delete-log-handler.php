<?php
require_once '../../config/init.php';
require_once '../../includes/auth.php';
require_once '../../includes/csrf.php';
require_once '../../includes/permission_checker.php';

require_login();
require_permission_or_redirect('system_logs', '../pages/logs.php');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    $_SESSION['error_message'] = 'Invalid request method.';
    header('Location: ../pages/logs.php');
    exit;
}

if (!csrf_validate()) {
    $_SESSION['error_message'] = 'Invalid security token. Please refresh and try again.';
    header('Location: ../pages/logs.php');
    exit;
}

$_SESSION['error_message'] = 'Log deletion is disabled. Logs are immutable.';
header('Location: ../pages/logs.php');
exit; 