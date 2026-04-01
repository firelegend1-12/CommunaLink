<?php
/**
 * Cache Manager
 * 
 * Provides comprehensive caching capabilities including:
 * - Multiple cache backends (APCu, file, session)
 * - Session storage optimization
 * - Dashboard statistics caching
 * - Page caching for static content
 * - Cache invalidation and management
 */

class CacheManager {
    
    /**
     * Cache configuration
     */
    private static $config = [
        'default_ttl' => 3600, // 1 hour
        'session_ttl' => 1800, // 30 minutes
        'stats_ttl' => 300,    // 5 minutes
        'page_ttl' => 7200,    // 2 hours
        'cache_dir' => 'cache/',
        'compression' => true,
        'serialization' => true
    ];
    
    /**
     * Cache backends
     */
    private static $backends = [];
    
    /**
     * Initialize cache manager
     */
    public static function init($config = []) {
        self::$config = array_merge(self::$config, $config);
        
        // Create cache directory if it doesn't exist
        if (!is_dir(self::$config['cache_dir'])) {
            mkdir(self::$config['cache_dir'], 0755, true);
        }
        
        // Initialize backends
        self::initBackends();
    }
    
    /**
     * Initialize cache backends
     */
    private static function initBackends() {
        // APCu backend (if available)
        if (extension_loaded('apcu') && ini_get('apc.enabled')) {
            self::$backends['apcu'] = new APCuBackend();
        }
        
        // File backend (always available)
        self::$backends['file'] = new FileBackend(self::$config['cache_dir']);
        
        // Session backend (for user-specific data)
        self::$backends['session'] = new SessionBackend();
    }
    
    /**
     * Get cache value
     */
    public static function get($key, $backend = 'auto') {
        $backend = self::selectBackend($backend);
        
        if (isset(self::$backends[$backend])) {
            return self::$backends[$backend]->get($key);
        }
        
        return null;
    }
    
    /**
     * Set cache value
     */
    public static function set($key, $value, $ttl = null, $backend = 'auto') {
        $ttl = $ttl ?? self::$config['default_ttl'];
        $backend = self::selectBackend($backend);
        
        if (isset(self::$backends[$backend])) {
            return self::$backends[$backend]->set($key, $value, $ttl);
        }
        
        return false;
    }
    
    /**
     * Delete cache value
     */
    public static function delete($key, $backend = 'auto') {
        $backend = self::selectBackend($backend);
        
        if (isset(self::$backends[$backend])) {
            return self::$backends[$backend]->delete($key);
        }
        
        return false;
    }
    
    /**
     * Check if key exists
     */
    public static function exists($key, $backend = 'auto') {
        $backend = self::selectBackend($backend);
        
        if (isset(self::$backends[$backend])) {
            return self::$backends[$backend]->exists($key);
        }
        
        return false;
    }
    
    /**
     * Clear all cache
     */
    public static function clear($backend = 'all') {
        if ($backend === 'all') {
            foreach (self::$backends as $backend_name => $backend_instance) {
                $backend_instance->clear();
            }
            return true;
        }
        
        if (isset(self::$backends[$backend])) {
            return self::$backends[$backend]->clear();
        }
        
        return false;
    }
    
    /**
     * Get cache statistics
     */
    public static function getStats() {
        $stats = [];
        
        foreach (self::$backends as $backend_name => $backend_instance) {
            $stats[$backend_name] = $backend_instance->getStats();
        }
        
        return $stats;
    }
    
    /**
     * Select appropriate backend
     */
    private static function selectBackend($backend) {
        if ($backend === 'auto') {
            // Prefer APCu when available; otherwise fall back to file cache.
            if (isset(self::$backends['apcu'])) {
                return 'apcu';
            }
            return 'file';
        }

        if (!isset(self::$backends[$backend])) {
            return isset(self::$backends['file']) ? 'file' : 'session';
        }
        
        return $backend;
    }
    
    /**
     * Cache dashboard statistics
     */
    public static function cacheDashboardStats($stats) {
        return self::set('dashboard_stats', $stats, self::$config['stats_ttl'], 'apcu');
    }
    
    /**
     * Get cached dashboard statistics
     */
    public static function getCachedDashboardStats() {
        return self::get('dashboard_stats', 'apcu');
    }
    
    /**
     * Cache user session data
     */
    public static function cacheUserSession($user_id, $data) {
        $key = "user_session_{$user_id}";
        return self::set($key, $data, self::$config['session_ttl'], 'session');
    }
    
    /**
     * Get cached user session data
     */
    public static function getCachedUserSession($user_id) {
        $key = "user_session_{$user_id}";
        return self::get($key, 'session');
    }
    
    /**
     * Cache page content
     */
    public static function cachePage($url, $content) {
        $key = "page_" . md5($url);
        return self::set($key, $content, self::$config['page_ttl'], 'file');
    }
    
    /**
     * Get cached page content
     */
    public static function getCachedPage($url) {
        $key = "page_" . md5($url);
        return self::get($key, 'file');
    }
    
    /**
     * Cache database query results
     */
    public static function cacheQuery($query, $params, $result, $ttl = null) {
        $key = "query_" . md5($query . serialize($params));
        return self::set($key, $result, $ttl ?? self::$config['default_ttl'], 'apcu');
    }
    
    /**
     * Get cached query results
     */
    public static function getCachedQuery($query, $params) {
        $key = "query_" . md5($query . serialize($params));
        return self::get($key, 'apcu');
    }
    
    /**
     * Invalidate cache by pattern
     */
    public static function invalidatePattern($pattern, $backend = 'all') {
        if ($backend === 'all') {
            foreach (self::$backends as $backend_name => $backend_instance) {
                $backend_instance->invalidatePattern($pattern);
            }
            return true;
        }
        
        if (isset(self::$backends[$backend])) {
            return self::$backends[$backend]->invalidatePattern($pattern);
        }
        
        return false;
    }
}

/**
 * APCu Backend Implementation
 */
class APCuBackend {
    
    public function get($key) {
        $value = apcu_fetch($key, $success);
        return $success ? $value : null;
    }
    
    public function set($key, $value, $ttl) {
        return apcu_store($key, $value, $ttl);
    }
    
    public function delete($key) {
        return apcu_delete($key);
    }
    
    public function exists($key) {
        return apcu_exists($key);
    }
    
    public function clear() {
        return apcu_clear_cache();
    }
    
    public function getStats() {
        $info = apcu_cache_info();
        return [
            'hits' => $info['num_hits'] ?? 0,
            'misses' => $info['num_misses'] ?? 0,
            'entries' => $info['num_entries'] ?? 0,
            'memory' => $info['mem_size'] ?? 0
        ];
    }
    
    public function invalidatePattern($pattern) {
        if (class_exists('APCuIterator')) {
            $class = 'APCuIterator';
            $iterator = new $class($pattern);
            foreach ($iterator as $item) {
                apcu_delete($item['key']);
            }
        }
        return true;
    }
}

/**
 * File Backend Implementation
 */
class FileBackend {
    
    private $cache_dir;
    
    public function __construct($cache_dir) {
        $this->cache_dir = rtrim($cache_dir, '/') . '/';
    }
    
    public function get($key) {
        $filename = $this->getFilename($key);
        
        if (!file_exists($filename)) {
            return null;
        }
        
        $data = file_get_contents($filename);
        $cached = json_decode($data, true);
        
        if (!$cached || $cached['expires'] < time()) {
            unlink($filename);
            return null;
        }
        
        return $cached['value'];
    }
    
    public function set($key, $value, $ttl) {
        $filename = $this->getFilename($key);
        
        $data = [
            'value' => $value,
            'expires' => time() + $ttl,
            'created' => time()
        ];
        
        return file_put_contents($filename, json_encode($data)) !== false;
    }
    
    public function delete($key) {
        $filename = $this->getFilename($key);
        return file_exists($filename) ? unlink($filename) : true;
    }
    
    public function exists($key) {
        $filename = $this->getFilename($key);
        return file_exists($filename) && $this->get($key) !== null;
    }
    
    public function clear() {
        $files = glob($this->cache_dir . '*');
        foreach ($files as $file) {
            if (is_file($file)) {
                unlink($file);
            }
        }
        return true;
    }
    
    public function getStats() {
        $files = glob($this->cache_dir . '*');
        $total_size = 0;
        $expired = 0;
        
        foreach ($files as $file) {
            if (is_file($file)) {
                $total_size += filesize($file);
                $data = json_decode(file_get_contents($file), true);
                if ($data && $data['expires'] < time()) {
                    $expired++;
                }
            }
        }
        
        return [
            'files' => count($files),
            'size' => $total_size,
            'expired' => $expired
        ];
    }
    
    public function invalidatePattern($pattern) {
        $files = glob($this->cache_dir . $pattern);
        foreach ($files as $file) {
            if (is_file($file)) {
                unlink($file);
            }
        }
        return true;
    }
    
    private function getFilename($key) {
        return $this->cache_dir . md5($key) . '.cache';
    }
}

/**
 * Session Backend Implementation
 */
class SessionBackend {
    
    public function get($key) {
        return $_SESSION['cache'][$key] ?? null;
    }
    
    public function set($key, $value, $ttl) {
        if (!isset($_SESSION['cache'])) {
            $_SESSION['cache'] = [];
        }
        
        $_SESSION['cache'][$key] = [
            'value' => $value,
            'expires' => time() + $ttl
        ];
        
        return true;
    }
    
    public function delete($key) {
        if (isset($_SESSION['cache'][$key])) {
            unset($_SESSION['cache'][$key]);
            return true;
        }
        return false;
    }
    
    public function exists($key) {
        if (!isset($_SESSION['cache'][$key])) {
            return false;
        }
        
        $cached = $_SESSION['cache'][$key];
        if ($cached['expires'] < time()) {
            unset($_SESSION['cache'][$key]);
            return false;
        }
        
        return true;
    }
    
    public function clear() {
        $_SESSION['cache'] = [];
        return true;
    }
    
    public function getStats() {
        if (!isset($_SESSION['cache'])) {
            return ['entries' => 0, 'size' => 0];
        }
        
        $entries = count($_SESSION['cache']);
        $size = strlen(serialize($_SESSION['cache']));
        
        return [
            'entries' => $entries,
            'size' => $size
        ];
    }
    
    public function invalidatePattern($pattern) {
        if (!isset($_SESSION['cache'])) {
            return true;
        }
        
        foreach ($_SESSION['cache'] as $key => $value) {
            if (fnmatch($pattern, $key)) {
                unset($_SESSION['cache'][$key]);
            }
        }
        
        return true;
    }
}

/**
 * Helper functions for easier usage
 */

/**
 * Initialize cache manager (shorthand)
 */
function init_cache_manager($config = []) {
    CacheManager::init($config);
}

/**
 * Get cache value (shorthand)
 */
function cache_get($key, $backend = 'auto') {
    return CacheManager::get($key, $backend);
}

/**
 * Set cache value (shorthand)
 */
function cache_set($key, $value, $ttl = null, $backend = 'auto') {
    return CacheManager::set($key, $value, $ttl, $backend);
}

/**
 * Delete cache value (shorthand)
 */
function cache_delete($key, $backend = 'auto') {
    return CacheManager::delete($key, $backend);
}

/**
 * Check if cache exists (shorthand)
 */
function cache_exists($key, $backend = 'auto') {
    return CacheManager::exists($key, $backend);
}

/**
 * Clear all cache (shorthand)
 */
function cache_clear($backend = 'all') {
    return CacheManager::clear($backend);
}

/**
 * Get cache statistics (shorthand)
 */
function cache_stats() {
    return CacheManager::getStats();
}







