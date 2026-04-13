<?php
/**
 * Rate Limiting Helper
 * 
 * Provides comprehensive rate limiting for login attempts,
 * API calls, and other sensitive operations to prevent abuse.
 */

class RateLimiter {
    
    /**
     * Default rate limiting settings
     */
    const DEFAULT_MAX_ATTEMPTS = 5;        // Maximum attempts allowed
    const DEFAULT_WINDOW_MINUTES = 15;     // Time window in minutes
    const DEFAULT_LOCKOUT_MINUTES = 30;    // Lockout duration in minutes
    
    /**
     * Rate limiting configuration
     */
    private static $config = [
        'login' => [
            'max_attempts' => 5,
            'window_minutes' => 15,
            'lockout_minutes' => 5
        ],
        'password_reset' => [
            'max_attempts' => 3,
            'window_minutes' => 60,
            'lockout_minutes' => 120
        ],
        'api_calls' => [
            'max_attempts' => 100,
            'window_minutes' => 60,
            'lockout_minutes' => 60
        ],
        'chat_api' => [
            'max_attempts' => 1200,
            'window_minutes' => 60,
            'lockout_minutes' => 10
        ],
        'post_reactions_api' => [
            'max_attempts' => 240,
            'window_minutes' => 60,
            'lockout_minutes' => 15
        ],
        'notifications_api' => [
            'max_attempts' => 3600,
            'window_minutes' => 60,
            'lockout_minutes' => 15
        ]
    ];
    
    /**
     * Check if an action is rate limited
     * 
     * @param string $action Action type (login, password_reset, api_calls)
     * @param string $identifier Unique identifier (IP, user ID, etc.)
     * @return array Rate limit status
     */
    public static function checkRateLimit($action, $identifier) {
        if (!isset(self::$config[$action])) {
            return ['allowed' => true, 'remaining' => 999, 'reset_time' => null];
        }
        
        $config = self::$config[$action];
        $key = self::getRateLimitKey($action, $identifier);
        
        // Get current attempts
        $attempts = self::getAttempts($key);
        $current_time = time();
        
        // Check if still in lockout period
        if (isset($attempts['lockout_until']) && $current_time < $attempts['lockout_until']) {
            $remaining_lockout = $attempts['lockout_until'] - $current_time;
            return [
                'allowed' => false,
                'reason' => 'rate_limited',
                'lockout_remaining' => $remaining_lockout,
                'lockout_until' => $attempts['lockout_until'],
                'message' => "Too many attempts. Please try again in " . ceil($remaining_lockout / 60) . " minutes."
            ];
        }
        
        // Check if within rate limit window
        $window_start = $current_time - ($config['window_minutes'] * 60);
        $recent_attempts = array_filter($attempts['timestamps'] ?? [], function($timestamp) use ($window_start) {
            return $timestamp >= $window_start;
        });
        
        $attempt_count = count($recent_attempts);
        $remaining_attempts = max(0, $config['max_attempts'] - $attempt_count);
        
        // Calculate reset time
        $reset_time = null;
        if (!empty($recent_attempts)) {
            $oldest_attempt = min($recent_attempts);
            $reset_time = $oldest_attempt + ($config['window_minutes'] * 60);
        }
        
        return [
            'allowed' => $attempt_count < $config['max_attempts'],
            'remaining' => $remaining_attempts,
            'attempts' => $attempt_count,
            'reset_time' => $reset_time,
            'window_minutes' => $config['window_minutes']
        ];
    }
    
    /**
     * Record an attempt for rate limiting
     * 
     * @param string $action Action type
     * @param string $identifier Unique identifier
     * @param bool $success Whether the attempt was successful
     * @return array Updated rate limit status
     */
    public static function recordAttempt($action, $identifier, $success = false) {
        if (!isset(self::$config[$action])) {
            return ['allowed' => true];
        }
        
        $config = self::$config[$action];
        $key = self::getRateLimitKey($action, $identifier);
        
        // Get current attempts
        $attempts = self::getAttempts($key);
        $current_time = time();
        
        // Add current attempt
        if (!isset($attempts['timestamps'])) {
            $attempts['timestamps'] = [];
        }
        $attempts['timestamps'][] = $current_time;
        
        // Keep only attempts within the window
        $window_start = $current_time - ($config['window_minutes'] * 60);
        $attempts['timestamps'] = array_filter($attempts['timestamps'], function($timestamp) use ($window_start) {
            return $timestamp >= $window_start;
        });
        
        // Check if rate limit exceeded
        if (count($attempts['timestamps']) >= $config['max_attempts']) {
            $attempts['lockout_until'] = $current_time + ($config['lockout_minutes'] * 60);
            
            // Log the rate limit event
            self::logRateLimitEvent($action, $identifier, 'rate_limit_exceeded', [
                'attempts' => count($attempts['timestamps']),
                'lockout_until' => $attempts['lockout_until']
            ]);
        }
        
        // Store updated attempts
        self::storeAttempts($key, $attempts);
        
        // Return current status
        return self::checkRateLimit($action, $identifier);
    }
    
    /**
     * Reset rate limiting for an identifier
     * 
     * @param string $action Action type
     * @param string $identifier Unique identifier
     * @return bool Success status
     */
    public static function resetRateLimit($action, $identifier) {
        $key = self::getRateLimitKey($action, $identifier);
        return self::clearAttempts($key);
    }
    
    /**
     * Get rate limit information for an identifier
     * 
     * @param string $action Action type
     * @param string $identifier Unique identifier
     * @return array Rate limit information
     */
    public static function getRateLimitInfo($action, $identifier) {
        if (!isset(self::$config[$action])) {
            return ['enabled' => false];
        }
        
        $config = self::$config[$action];
        $status = self::checkRateLimit($action, $identifier);
        
        return [
            'enabled' => true,
            'max_attempts' => $config['max_attempts'],
            'window_minutes' => $config['window_minutes'],
            'lockout_minutes' => $config['lockout_minutes'],
            'current_attempts' => $status['attempts'] ?? 0,
            'remaining_attempts' => $status['remaining'] ?? 0,
            'reset_time' => $status['reset_time'] ?? null,
            'is_locked' => isset($status['lockout_remaining']),
            'lockout_remaining' => $status['lockout_remaining'] ?? null
        ];
    }
    
    /**
     * Update rate limiting configuration
     * 
     * @param string $action Action type
     * @param array $config New configuration
     * @return bool Success status
     */
    public static function updateConfig($action, $config) {
        if (!isset(self::$config[$action])) {
            return false;
        }
        
        self::$config[$action] = array_merge(self::$config[$action], $config);
        return true;
    }
    
    /**
     * Get all rate limiting configuration
     * 
     * @return array Current configuration
     */
    public static function getConfig() {
        return self::$config;
    }
    
    /**
     * Generate rate limit key
     * 
     * @param string $action Action type
     * @param string $identifier Unique identifier
     * @return string Rate limit key
     */
    private static function getRateLimitKey($action, $identifier) {
        return "rate_limit:{$action}:" . md5($identifier);
    }

    /**
     * Determine whether APCu is usable in the current runtime.
     *
     * @return bool
     */
    private static function canUseApcu() {
        static $usable = null;

        if ($usable !== null) {
            return $usable;
        }

        $usable = function_exists('apcu_fetch') && function_exists('apcu_store') && function_exists('apcu_delete');
        if (!$usable) {
            return false;
        }

        if (function_exists('apcu_enabled')) {
            $usable = (bool) apcu_enabled();
            return $usable;
        }

        // Probe write/read support because function existence alone is not enough.
        $probeKey = 'rate_limit_probe_' . md5(uniqid((string) mt_rand(), true));
        $stored = @apcu_store($probeKey, 1, 5);
        if ($stored) {
            @apcu_delete($probeKey);
            $usable = true;
        } else {
            $usable = false;
        }

        return $usable;
    }

    /**
     * Ensure session storage is available.
     *
     * @return bool
     */
    private static function ensureSessionAvailable() {
        if (session_status() === PHP_SESSION_ACTIVE) {
            return true;
        }

        if (headers_sent()) {
            return false;
        }

        @session_start();
        return session_status() === PHP_SESSION_ACTIVE;
    }

    /**
     * Resolve file-based storage directory for rate limit state.
     *
     * @return string
     */
    private static function getFileStorageDir() {
        $configured = '';
        if (function_exists('env')) {
            $configured = trim((string) env('RATE_LIMIT_STORAGE_DIR', ''));
        }

        if ($configured === '') {
            $configured = rtrim((string) sys_get_temp_dir(), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'communalink_rate_limits';
        }

        if (!is_dir($configured)) {
            @mkdir($configured, 0755, true);
        }

        return $configured;
    }

    /**
     * Build file path for a rate limit key.
     *
     * @param string $key
     * @return string
     */
    private static function getFilePathForKey($key) {
        return self::getFileStorageDir() . DIRECTORY_SEPARATOR . md5($key) . '.json';
    }

    /**
     * Read attempts from file storage.
     *
     * @param string $key
     * @return array|null
     */
    private static function getAttemptsFromFile($key) {
        $filePath = self::getFilePathForKey($key);
        if (!is_file($filePath)) {
            return null;
        }

        $raw = @file_get_contents($filePath);
        if ($raw === false || trim($raw) === '') {
            return null;
        }

        $decoded = json_decode($raw, true);
        if (!is_array($decoded)) {
            return null;
        }

        return $decoded;
    }

    /**
     * Persist attempts to file storage.
     *
     * @param string $key
     * @param array $attempts
     * @return bool
     */
    private static function storeAttemptsToFile($key, $attempts) {
        $filePath = self::getFilePathForKey($key);
        $payload = json_encode($attempts);
        if ($payload === false) {
            return false;
        }

        return @file_put_contents($filePath, $payload, LOCK_EX) !== false;
    }

    /**
     * Remove attempts from file storage.
     *
     * @param string $key
     * @return bool
     */
    private static function clearAttemptsFromFile($key) {
        $filePath = self::getFilePathForKey($key);
        if (!is_file($filePath)) {
            return false;
        }

        return @unlink($filePath);
    }
    
    /**
     * Get attempts from storage
     * 
     * @param string $key Rate limit key
     * @return array Attempts data
     */
    private static function getAttempts($key) {
        if (self::canUseApcu()) {
            // Use APCu if available and operational (faster).
            $success = false;
            $data = apcu_fetch($key, $success);
            if ($success && is_array($data)) {
                return $data;
            }
        }

        // Durable fallback across requests without relying solely on session state.
        $fileData = self::getAttemptsFromFile($key);
        if (is_array($fileData)) {
            return $fileData;
        }

        if (self::ensureSessionAvailable()) {
            return $_SESSION[$key] ?? [];
        }

        return [];
    }
    
    /**
     * Store attempts to storage
     * 
     * @param string $key Rate limit key
     * @param array $attempts Attempts data
     * @return bool Success status
     */
    private static function storeAttempts($key, $attempts) {
        $stored = false;

        if (self::canUseApcu()) {
            // Use APCu if available.
            $stored = @apcu_store($key, $attempts, 3600) || $stored;
        }

        $stored = self::storeAttemptsToFile($key, $attempts) || $stored;

        if (self::ensureSessionAvailable()) {
            $_SESSION[$key] = $attempts;
            $stored = true;
        }

        return $stored;
    }

    /**
     * Clear attempts from storage
     * 
     * @param string $key Rate limit key
     * @return bool Success status
     */
    private static function clearAttempts($key) {
        $cleared = false;

        if (self::canUseApcu()) {
            $cleared = @apcu_delete($key) || $cleared;
        }

        $cleared = self::clearAttemptsFromFile($key) || $cleared;

        if (self::ensureSessionAvailable()) {
            if (isset($_SESSION[$key])) {
                unset($_SESSION[$key]);
            }
            $cleared = true;
        }

        return $cleared;
    }
    
    /**
     * Log rate limit events
     * 
     * @param string $action Action type
     * @param string $identifier Identifier
     * @param string $event Event type
     * @param array $data Additional data
     */
    private static function logRateLimitEvent($action, $identifier, $event, $data = []) {
        $log_data = [
            'timestamp' => date('Y-m-d H:i:s'),
            'action' => $action,
            'identifier' => $identifier,
            'event' => $event,
            'ip_address' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown',
            'data' => $data
        ];
        
        // Log to error log for monitoring
        error_log('RATE_LIMIT: ' . json_encode($log_data));
        
        // Could also log to database or external logging service
        if (function_exists('log_activity_db') && isset($GLOBALS['pdo'])) {
            try {
                log_activity_db(
                    $GLOBALS['pdo'],
                    'security',
                    'rate_limit',
                    null,
                    "Rate limit event: {$action} - {$event}",
                    null,
                    json_encode($log_data)
                );
            } catch (Exception $e) {
                // Silently fail if logging fails
            }
        }
    }
    
    /**
     * Get client IP address
     * 
     * @return string Client IP address
     */
    public static function getClientIP() {
        $ip_keys = ['HTTP_X_FORWARDED_FOR', 'HTTP_X_REAL_IP', 'HTTP_CLIENT_IP', 'REMOTE_ADDR'];
        
        foreach ($ip_keys as $key) {
            if (array_key_exists($key, $_SERVER) === true) {
                foreach (explode(',', $_SERVER[$key]) as $ip) {
                    $ip = trim($ip);
                    if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) !== false) {
                        return $ip;
                    }
                }
            }
        }
        
        return $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
    }
    
    /**
     * Clean up expired rate limit data
     * 
     * @return int Number of cleaned entries
     */
    public static function cleanup() {
        $cleaned = 0;
        
        if (self::canUseApcu() && function_exists('apcu_cache_info')) {
            // Clean up APCu cache entries
            $info = apcu_cache_info();
            foreach ($info['cache_list'] as $entry) {
                if (strpos($entry['info'], 'rate_limit:') === 0) {
                    // Check if entry is expired (older than 24 hours)
                    if (time() - $entry['mtime'] > 86400) {
                        apcu_delete($entry['info']);
                        $cleaned++;
                    }
                }
            }
        }

        // Clean up file-based fallback entries older than 24 hours.
        $storageDir = self::getFileStorageDir();
        if (is_dir($storageDir)) {
            $files = @glob($storageDir . DIRECTORY_SEPARATOR . '*.json');
            if (is_array($files)) {
                foreach ($files as $filePath) {
                    $mtime = @filemtime($filePath);
                    if ($mtime !== false && (time() - $mtime > 86400)) {
                        if (@unlink($filePath)) {
                            $cleaned++;
                        }
                    }
                }
            }
        }
        
        return $cleaned;
    }
}

/**
 * Helper functions for easier usage
 */

/**
 * Build a stable login rate-limit identifier.
 *
 * Uses a hashed username when available to stay consistent even if IP headers
 * vary across requests. Falls back to client IP for anonymous attempts.
 *
 * @param string|null $username Username/email submitted during login
 * @return string
 */
function get_login_rate_limit_identifier($username = null) {
    $normalized = strtolower(trim((string) $username));
    if ($normalized !== '') {
        return 'user:' . hash('sha256', $normalized);
    }

    return 'ip:' . RateLimiter::getClientIP();
}

/**
 * Check login rate limit (shorthand)
 */
function check_login_rate_limit($identifier = null) {
    if ($identifier === null) {
        $identifier = get_login_rate_limit_identifier();
    }
    return RateLimiter::checkRateLimit('login', $identifier);
}

/**
 * Record login attempt (shorthand)
 */
function record_login_attempt($identifier = null, $success = false) {
    if ($identifier === null) {
        $identifier = get_login_rate_limit_identifier();
    }
    return RateLimiter::recordAttempt('login', $identifier, $success);
}

/**
 * Reset login rate limit (shorthand)
 */
function reset_login_rate_limit($identifier = null) {
    if ($identifier === null) {
        $identifier = get_login_rate_limit_identifier();
    }
    return RateLimiter::resetRateLimit('login', $identifier);
}

/**
 * Check if login is allowed (shorthand)
 */
function is_login_allowed($identifier = null) {
    $status = check_login_rate_limit($identifier);
    return $status['allowed'];
}

/**
 * Get login rate limit info (shorthand)
 */
function get_login_rate_limit_info($identifier = null) {
    if ($identifier === null) {
        $identifier = get_login_rate_limit_identifier();
    }
    return RateLimiter::getRateLimitInfo('login', $identifier);
}







