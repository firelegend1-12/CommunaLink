<?php
session_start();
require_once '../includes/auth.php';
require_once '../includes/functions.php';

require_role('resident');

$redirect_params = [];
foreach (['success', 'cancelled', 'cancel_error'] as $param_key) {
    if (isset($_GET[$param_key])) {
        $redirect_params[$param_key] = $_GET[$param_key];
    }
}

$redirect_target = 'my-document-requests.php';
if (!empty($redirect_params)) {
    $redirect_target .= '?' . http_build_query($redirect_params);
}

redirect_to($redirect_target);
exit;
