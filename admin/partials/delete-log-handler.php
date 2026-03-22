<?php
require_once '../../config/init.php';
require_once '../../includes/auth.php';

if (!is_admin_or_official()) {
    $_SESSION['error_message'] = 'Unauthorized.';
    header('Location: ../pages/logs.php');
    exit;
}

$_SESSION['error_message'] = 'Log deletion is disabled. Logs are immutable.';
header('Location: ../pages/logs.php');
exit; 