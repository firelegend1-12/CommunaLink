<?php
/**
 * Rate Limiting Management Page
 * 
 * Allows administrators to view and configure rate limiting settings
 * for various system actions.
 */

require_once '../../config/init.php';
require_once '../../includes/functions.php';
require_once '../../includes/auth.php';

// Apply security headers for admin pages
apply_page_security_headers('admin');

// Check if user is logged in
require_login();

// Check if user is an admin
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    redirect_to('../index.php');
}

// Handle configuration updates
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'update_config') {
        $action = sanitize_input($_POST['rate_limit_action']);
        $max_attempts = filter_input(INPUT_POST, 'max_attempts', FILTER_VALIDATE_INT);
        $window_minutes = filter_input(INPUT_POST, 'window_minutes', FILTER_VALIDATE_INT);
        $lockout_minutes = filter_input(INPUT_POST, 'lockout_minutes', FILTER_VALIDATE_INT);
        
        if ($max_attempts && $window_minutes && $lockout_minutes) {
            $config = [
                'max_attempts' => $max_attempts,
                'window_minutes' => $window_minutes,
                'lockout_minutes' => $lockout_minutes
            ];
            
            if (RateLimiter::updateConfig($action, $config)) {
                $_SESSION['success_message'] = "Rate limiting configuration updated successfully.";
            } else {
                $_SESSION['error_message'] = "Failed to update rate limiting configuration.";
            }
        } else {
            $_SESSION['error_message'] = "Invalid configuration values.";
        }
        
        redirect_to('rate-limiting.php');
    }
}

// Get current configuration
$current_config = RateLimiter::getConfig();

// Page title
$page_title = "Rate Limiting Management - CommuniLink";
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?></title>
    <!-- Tailwind CSS CDN -->
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <!-- Font Awesome Icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body class="bg-gray-100 min-h-screen">
    <div class="flex h-screen overflow-hidden">
        <!-- Sidebar Navigation -->
        <?php include '../partials/sidebar.php'; ?>
        
        <!-- Main Content -->
        <div class="flex flex-col flex-1 overflow-hidden">
            <!-- Top Header -->
            <header class="bg-white shadow-sm z-10">
                <div class="px-4 sm:px-6 lg:px-8">
                    <div class="flex items-center justify-between h-16">
                        <div class="flex items-center">
                            <h1 class="text-2xl font-semibold text-gray-800">Rate Limiting Management</h1>
                        </div>
                        
                        <!-- Notification Center -->
                        <?php 
                        if (file_exists('../../includes/notification_center.php')) {
                            require_once '../../includes/notification_center.php';
                            echo render_notification_center($_SESSION['user_id'], 'top-right');
                        }
                        ?>
                        
                        <!-- User Dropdown -->
                        <div class="relative">
                            <div class="flex items-center space-x-2 text-sm text-gray-600">
                                <span><?php echo htmlspecialchars($_SESSION['fullname']); ?></span>
                                <div class="h-8 w-8 rounded-full bg-purple-600 flex items-center justify-center text-white text-sm font-bold ring-2 ring-white">
                                    <?php echo substr($_SESSION['fullname'], 0, 1); ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </header>
            
            <!-- Page Content -->
            <main class="flex-1 overflow-y-auto bg-gray-50 p-4 sm:p-6 lg:p-8">
                <?php if (isset($_SESSION['success_message'])): ?>
                    <div class="bg-green-100 border-l-4 border-green-500 text-green-700 p-4 mb-4" role="alert">
                        <p><?php echo htmlspecialchars($_SESSION['success_message']); ?></p>
                    </div>
                    <?php unset($_SESSION['success_message']); ?>
                <?php endif; ?>
                
                <?php if (isset($_SESSION['error_message'])): ?>
                    <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-4" role="alert">
                        <p><?php echo htmlspecialchars($_SESSION['error_message']); ?></p>
                    </div>
                    <?php unset($_SESSION['error_message']); ?>
                <?php endif; ?>
                
                <!-- Rate Limiting Overview -->
                <div class="bg-white rounded-lg shadow p-6 mb-6">
                    <h2 class="text-lg font-medium text-gray-900 mb-4">Rate Limiting Overview</h2>
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                        <div class="bg-blue-50 p-4 rounded-lg">
                            <h3 class="text-sm font-medium text-blue-800">Login Protection</h3>
                            <p class="text-2xl font-bold text-blue-600"><?php echo $current_config['login']['max_attempts']; ?></p>
                            <p class="text-xs text-blue-600">Max attempts per <?php echo $current_config['login']['window_minutes']; ?> minutes</p>
                        </div>
                        <div class="bg-green-50 p-4 rounded-lg">
                            <h3 class="text-sm font-medium text-green-800">Password Reset</h3>
                            <p class="text-2xl font-bold text-green-600"><?php echo $current_config['password_reset']['max_attempts']; ?></p>
                            <p class="text-xs text-green-600">Max attempts per <?php echo $current_config['password_reset']['window_minutes']; ?> minutes</p>
                        </div>
                        <div class="bg-purple-50 p-4 rounded-lg">
                            <h3 class="text-sm font-medium text-purple-800">API Calls</h3>
                            <p class="text-2xl font-bold text-purple-600"><?php echo $current_config['api_calls']['max_attempts']; ?></p>
                            <p class="text-xs text-purple-600">Max calls per <?php echo $current_config['api_calls']['window_minutes']; ?> minutes</p>
                        </div>
                    </div>
                </div>
                
                <!-- Configuration Forms -->
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                    <!-- Login Rate Limiting -->
                    <div class="bg-white rounded-lg shadow p-6">
                        <h3 class="text-lg font-medium text-gray-900 mb-4">Login Rate Limiting</h3>
                        <form method="POST" class="space-y-4">
                            <input type="hidden" name="action" value="update_config">
                            <input type="hidden" name="rate_limit_action" value="login">
                            
                            <div>
                                <label for="login_max_attempts" class="block text-sm font-medium text-gray-700">Maximum Attempts</label>
                                <input type="number" id="login_max_attempts" name="max_attempts" 
                                       value="<?php echo $current_config['login']['max_attempts']; ?>"
                                       min="1" max="20" class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                                <p class="text-xs text-gray-500 mt-1">Number of failed login attempts before lockout</p>
                            </div>
                            
                            <div>
                                <label for="login_window_minutes" class="block text-sm font-medium text-gray-700">Time Window (minutes)</label>
                                <input type="number" id="login_window_minutes" name="window_minutes" 
                                       value="<?php echo $current_config['login']['window_minutes']; ?>"
                                       min="1" max="1440" class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                                <p class="text-xs text-gray-500 mt-1">Time period to count attempts</p>
                            </div>
                            
                            <div>
                                <label for="login_lockout_minutes" class="block text-sm font-medium text-gray-700">Lockout Duration (minutes)</label>
                                <input type="number" id="login_lockout_minutes" name="lockout_minutes" 
                                       value="<?php echo $current_config['login']['lockout_minutes']; ?>"
                                       min="1" max="1440" class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                                <p class="text-xs text-gray-500 mt-1">How long to lock out after exceeding limit</p>
                            </div>
                            
                            <button type="submit" class="w-full bg-blue-600 text-white px-4 py-2 rounded-md hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500">
                                Update Login Settings
                            </button>
                        </form>
                    </div>
                    
                    <!-- API Rate Limiting -->
                    <div class="bg-white rounded-lg shadow p-6">
                        <h3 class="text-lg font-medium text-gray-900 mb-4">API Rate Limiting</h3>
                        <form method="POST" class="space-y-4">
                            <input type="hidden" name="action" value="update_config">
                            <input type="hidden" name="rate_limit_action" value="api_calls">
                            
                            <div>
                                <label for="api_max_attempts" class="block text-sm font-medium text-gray-700">Maximum API Calls</label>
                                <input type="number" id="api_max_attempts" name="max_attempts" 
                                       value="<?php echo $current_config['api_calls']['max_attempts']; ?>"
                                       min="10" max="1000" class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                                <p class="text-xs text-gray-500 mt-1">Number of API calls allowed per time window</p>
                            </div>
                            
                            <div>
                                <label for="api_window_minutes" class="block text-sm font-medium text-gray-700">Time Window (minutes)</label>
                                <input type="number" id="api_window_minutes" name="window_minutes" 
                                       value="<?php echo $current_config['api_calls']['window_minutes']; ?>"
                                       min="1" max="1440" class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                                <p class="text-xs text-gray-500 mt-1">Time period to count API calls</p>
                            </div>
                            
                            <div>
                                <label for="api_lockout_minutes" class="block text-sm font-medium text-gray-700">Lockout Duration (minutes)</label>
                                <input type="number" id="api_lockout_minutes" name="lockout_minutes" 
                                       value="<?php echo $current_config['api_calls']['lockout_minutes']; ?>"
                                       min="1" max="1440" class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                                <p class="text-xs text-gray-500 mt-1">How long to block API access after exceeding limit</p>
                            </div>
                            
                            <button type="submit" class="w-full bg-purple-600 text-white px-4 py-2 rounded-md hover:bg-purple-700 focus:outline-none focus:ring-2 focus:ring-purple-500">
                                Update API Settings
                            </button>
                        </form>
                    </div>
                </div>
                
                <!-- Security Information -->
                <div class="bg-white rounded-lg shadow p-6 mt-6">
                    <h3 class="text-lg font-medium text-gray-900 mb-4">Security Information</h3>
                    <div class="bg-yellow-50 border-l-4 border-yellow-400 p-4">
                        <div class="flex">
                            <div class="flex-shrink-0">
                                <i class="fas fa-exclamation-triangle text-yellow-400"></i>
                            </div>
                            <div class="ml-3">
                                <p class="text-sm text-yellow-700">
                                    <strong>Rate limiting</strong> helps protect your system against brute force attacks, 
                                    automated bots, and other abuse. The system automatically tracks login attempts, 
                                    API calls, and other sensitive operations by IP address and applies temporary 
                                    lockouts when limits are exceeded.
                                </p>
                            </div>
                        </div>
                    </div>
                    
                    <div class="mt-4 grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <h4 class="text-sm font-medium text-gray-900 mb-2">How It Works:</h4>
                            <ul class="text-sm text-gray-600 space-y-1">
                                <li>• Tracks attempts by IP address</li>
                                <li>• Applies sliding time windows</li>
                                <li>• Automatically locks out after limit exceeded</li>
                                <li>• Resets after successful authentication</li>
                            </ul>
                        </div>
                        <div>
                            <h4 class="text-sm font-medium text-gray-900 mb-2">Benefits:</h4>
                            <ul class="text-sm text-gray-600 space-y-1">
                                <li>• Prevents brute force attacks</li>
                                <li>• Protects against automated bots</li>
                                <li>• Reduces server load from abuse</li>
                                <li>• Maintains system availability</li>
                            </ul>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>
</body>
</html>
