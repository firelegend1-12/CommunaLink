<?php
session_start();
require_once '../includes/functions.php';

// Resident notifications have been removed. Keep the route stable.
redirect_to('announcements.php');
?>

