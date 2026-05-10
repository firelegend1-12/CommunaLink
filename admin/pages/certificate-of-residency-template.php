<?php
require_once '../partials/admin_auth.php';

require_once '../../config/init.php';
require_once '../../includes/auth.php';
require_once '../../includes/functions.php';

require_login();

$_SESSION['error_message'] = 'Document preview, print, and viewing-only pages have been removed.';
redirect_to('monitoring-of-request.php');
