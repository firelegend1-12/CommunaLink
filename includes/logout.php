<?php
/**
 * Logout Script
 * Handles user logout functionality
 */

// Include authentication system
require_once 'auth.php';

// Log out the user
logout();

// Redirect to login page
redirect_to('/index.php?logout=success'); 