<?php
/**
 * Query Optimizer
 * 
 * Provides query optimization features including:
 * - Query performance analysis
 * - Automatic pagination
 * - Lazy loading for images and data
 * - Query result caching
 * - Performance monitoring
 */

class QueryOptimizer {
    
    /**
     * Database connection
     */
    private static $pdo = null;
    
    /**
     * Cache manager instance
     */
    private static $cache = null;
    
    /**
     * Performance metrics
     */
    private static $query_metrics = [];
    
    /**
     * Initialize query optimizer
     */
    public static function init($pdo, $cache = null) {
        self::$pdo = $pdo;
        self::$cache = $cache;
    }
    
    /**
     * Execute optimized query with caching
     */
    public static function executeQuery($query, $params = [], $options = []) {
        $default_options = [
            'cache' => true,
            'cache_ttl' => 300,
            'cache_key' => null,
            'pagination' => false,
            'page' => 1,
            'per_page' => 20,
            'lazy_load' => false,
            'optimize' => true
        ];
        
        $options = array_merge($default_options, $options);
        
        // Generate cache key if not provided
        if ($options['cache'] && !$options['cache_key']) {
            $options['cache_key'] = 'query_' . md5($query . serialize($params));
        }
        
        // Try to get from cache first
        if ($options['cache'] && self::$cache) {
            $cached_result = self::$cache->get($options['cache_key']);
            if ($cached_result !== null) {
                self::recordQueryMetric($query, 0, 'CACHE_HIT');
                return $cached_result;
            }
        }
        
        // Execute query
        $start_time = microtime(true);
        $result = self::executeOptimizedQuery($query, $params, $options);
        $execution_time = microtime(true) - $start_time;
        
        // Record metrics
        self::recordQueryMetric($query, $execution_time, 'EXECUTION');
        
        // Cache result if enabled
        if ($options['cache'] && self::$cache && $result['success']) {
            self::$cache->set($options['cache_key'], $result, $options['cache_ttl']);
        }
        
        return $result;
    }
    
    /**
     * Execute query with optimization
     */
    private static function executeOptimizedQuery($query, $params, $options) {
        try {
            // Apply query optimizations
            if ($options['optimize']) {
                $query = self::optimizeQueryString($query);
            }
            
            // Handle pagination
            if ($options['pagination']) {
                $query = self::addPaginationToQuery($query, $options['page'], $options['per_page']);
            }
            
            // Prepare and execute
            $stmt = self::$pdo->prepare($query);
            $stmt->execute($params);
            
            if ($options['pagination']) {
                $result = self::getPaginatedResult($stmt, $options['page'], $options['per_page']);
            } else {
                $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
            }
            
            // Apply lazy loading if enabled
            if ($options['lazy_load']) {
                $result = self::applyLazyLoading($result);
            }
            
            return [
                'success' => true,
                'data' => $result,
                'execution_time' => microtime(true) - microtime(true),
                'rows_returned' => is_array($result) ? count($result) : 0,
                'query' => $query
            ];
            
        } catch (PDOException $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
                'query' => $query
            ];
        }
    }
    
    /**
     * Optimize query string
     */
    private static function optimizeQueryString($query) {
        // Remove unnecessary whitespace
        $query = preg_replace('/\s+/', ' ', trim($query));
        
        // Convert to uppercase for consistency
        $query = strtoupper($query);
        
        // Add hints for optimization
        if (strpos($query, 'SELECT') === 0) {
            // Add LIMIT if not present for large result sets
            if (!strpos($query, 'LIMIT') && !strpos($query, 'COUNT')) {
                $query .= ' LIMIT 1000';
            }
        }
        
        return $query;
    }
    
    /**
     * Add pagination to query
     */
    private static function addPaginationToQuery($query, $page, $per_page) {
        $offset = ($page - 1) * $per_page;
        
        // Check if query already has LIMIT
        if (strpos(strtoupper($query), 'LIMIT') === false) {
            $query .= " LIMIT $per_page OFFSET $offset";
        }
        
        return $query;
    }
    
    /**
     * Get paginated result
     */
    private static function getPaginatedResult($stmt, $page, $per_page) {
        $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Get total count (this would need to be optimized in production)
        $count_query = "SELECT COUNT(*) FROM (" . $stmt->queryString . ") as count_table";
        $count_stmt = self::$pdo->prepare($count_query);
        $count_stmt->execute();
        $total_count = $count_stmt->fetchColumn();
        
        $total_pages = ceil($total_count / $per_page);
        
        return [
            'data' => $data,
            'pagination' => [
                'current_page' => $page,
                'per_page' => $per_page,
                'total_count' => $total_count,
                'total_pages' => $total_pages,
                'has_next' => $page < $total_pages,
                'has_prev' => $page > 1
            ]
        ];
    }
    
    /**
     * Apply lazy loading to result set
     */
    private static function applyLazyLoading($data) {
        if (!is_array($data)) {
            return $data;
        }
        
        foreach ($data as &$row) {
            // Lazy load images
            if (isset($row['image_path']) && !empty($row['image_path'])) {
                $row['image_loaded'] = false;
                $row['image_url'] = $row['image_path'];
            }
            
            // Lazy load related data
            if (isset($row['user_id'])) {
                $row['user_data_loaded'] = false;
            }
            
            // Lazy load documents
            if (isset($row['document_id'])) {
                $row['document_loaded'] = false;
            }
        }
        
        return $data;
    }
    
    /**
     * Lazy load image data
     */
    public static function lazyLoadImage($image_path, $options = []) {
        $default_options = [
            'width' => 300,
            'height' => 200,
            'quality' => 80,
            'format' => 'webp'
        ];
        
        $options = array_merge($default_options, $options);
        
        // Check if optimized image exists
        $optimized_path = self::getOptimizedImagePath($image_path, $options);
        
        if (file_exists($optimized_path)) {
            return [
                'success' => true,
                'path' => $optimized_path,
                'size' => filesize($optimized_path),
                'cached' => true
            ];
        }
        
        // Create optimized image
        $result = self::createOptimizedImage($image_path, $optimized_path, $options);
        
        if ($result['success']) {
            // Cache the result
            if (self::$cache) {
                $cache_key = 'image_' . md5($image_path . serialize($options));
                self::$cache->set($cache_key, $result, 86400); // Cache for 24 hours
            }
        }
        
        return $result;
    }
    
    /**
     * Get optimized image path
     */
    private static function getOptimizedImagePath($original_path, $options) {
        $path_info = pathinfo($original_path);
        $optimized_dir = 'cache/images/';
        
        if (!is_dir($optimized_dir)) {
            mkdir($optimized_dir, 0755, true);
        }
        
        $filename = $path_info['filename'] . "_{$options['width']}x{$options['height']}.{$options['format']}";
        return $optimized_dir . $filename;
    }
    
    /**
     * Create optimized image
     */
    private static function createOptimizedImage($source_path, $target_path, $options) {
        try {
            // Check if GD extension is available
            if (!extension_loaded('gd')) {
                return [
                    'success' => false,
                    'error' => 'GD extension not available'
                ];
            }
            
            // Get image info
            $image_info = getimagesize($source_path);
            if (!$image_info) {
                return [
                    'success' => false,
                    'error' => 'Invalid image file'
                ];
            }
            
            $source_width = $image_info[0];
            $source_height = $image_info[1];
            $source_type = $image_info[2];
            
            // Calculate new dimensions
            $ratio = min($options['width'] / $source_width, $options['height'] / $source_height);
            $new_width = round($source_width * $ratio);
            $new_height = round($source_height * $ratio);
            
            // Create source image
            switch ($source_type) {
                case IMAGETYPE_JPEG:
                    $source_image = imagecreatefromjpeg($source_path);
                    break;
                case IMAGETYPE_PNG:
                    $source_image = imagecreatefrompng($source_path);
                    break;
                case IMAGETYPE_GIF:
                    $source_image = imagecreatefromgif($source_path);
                    break;
                default:
                    return [
                        'success' => false,
                        'error' => 'Unsupported image type'
                    ];
            }
            
            // Create target image
            $target_image = imagecreatetruecolor($new_width, $new_height);
            
            // Preserve transparency for PNG
            if ($source_type === IMAGETYPE_PNG) {
                imagealphablending($target_image, false);
                imagesavealpha($target_image, true);
            }
            
            // Resize image
            imagecopyresampled($target_image, $source_image, 0, 0, 0, 0, $new_width, $new_height, $source_width, $source_height);
            
            // Save optimized image
            $success = false;
            switch ($options['format']) {
                case 'jpeg':
                case 'jpg':
                    $success = imagejpeg($target_image, $target_path, $options['quality']);
                    break;
                case 'png':
                    $success = imagepng($target_image, $target_path, round($options['quality'] / 10));
                    break;
                case 'webp':
                    if (function_exists('imagewebp')) {
                        $success = imagewebp($target_image, $target_path, $options['quality']);
                    } else {
                        $success = imagejpeg($target_image, $target_path, $options['quality']);
                    }
                    break;
            }
            
            // Clean up
            imagedestroy($source_image);
            imagedestroy($target_image);
            
            if ($success) {
                return [
                    'success' => true,
                    'path' => $target_path,
                    'size' => filesize($target_path),
                    'dimensions' => [$new_width, $new_height],
                    'cached' => false
                ];
            } else {
                return [
                    'success' => false,
                    'error' => 'Failed to save optimized image'
                ];
            }
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Lazy load user data
     */
    public static function lazyLoadUserData($user_id) {
        if (self::$cache) {
            $cache_key = "user_data_{$user_id}";
            $cached_data = self::$cache->get($cache_key);
            if ($cached_data !== null) {
                return $cached_data;
            }
        }
        
        try {
            $query = "SELECT id, username, fullname, email, role, status, created_at FROM users WHERE id = ?";
            $stmt = self::$pdo->prepare($query);
            $stmt->execute([$user_id]);
            $user_data = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (self::$cache && $user_data) {
                self::$cache->set($cache_key, $user_data, 1800); // Cache for 30 minutes
            }
            
            return $user_data;
            
        } catch (PDOException $e) {
            return null;
        }
    }
    
    /**
     * Lazy load document data
     */
    public static function lazyLoadDocumentData($document_id) {
        if (self::$cache) {
            $cache_key = "document_data_{$document_id}";
            $cached_data = self::$cache->get($cache_key);
            if ($cached_data !== null) {
                return $cached_data;
            }
        }
        
        try {
            $query = "SELECT id, title, type, status, file_path, created_at FROM documents WHERE id = ?";
            $stmt = self::$pdo->prepare($query);
            $stmt->execute([$document_id]);
            $document_data = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (self::$cache && $document_data) {
                self::$cache->set($cache_key, $document_data, 3600); // Cache for 1 hour
            }
            
            return $document_data;
            
        } catch (PDOException $e) {
            return null;
        }
    }
    
    /**
     * Record query performance metrics
     */
    private static function recordQueryMetric($query, $execution_time, $type) {
        self::$query_metrics[] = [
            'query' => $query,
            'execution_time' => $execution_time,
            'type' => $type,
            'timestamp' => date('Y-m-d H:i:s'),
            'memory_usage' => memory_get_usage(true)
        ];
        
        // Keep only last 1000 metrics
        if (count(self::$query_metrics) > 1000) {
            array_shift(self::$query_metrics);
        }
    }
    
    /**
     * Get query performance metrics
     */
    public static function getQueryMetrics() {
        return self::$query_metrics;
    }
    
    /**
     * Get slow queries
     */
    public static function getSlowQueries($threshold = 0.1) {
        return array_filter(self::$query_metrics, function($metric) use ($threshold) {
            return $metric['type'] === 'EXECUTION' && $metric['execution_time'] > $threshold;
        });
    }
    
    /**
     * Clear query metrics
     */
    public static function clearQueryMetrics() {
        self::$query_metrics = [];
    }
    
    /**
     * Get performance summary
     */
    public static function getPerformanceSummary() {
        $execution_metrics = array_filter(self::$query_metrics, function($metric) {
            return $metric['type'] === 'EXECUTION';
        });
        
        $cache_metrics = array_filter(self::$query_metrics, function($metric) {
            return $metric['type'] === 'CACHE_HIT';
        });
        
        $total_queries = count($execution_metrics);
        $cache_hits = count($cache_metrics);
        $total_requests = $total_queries + $cache_hits;
        
        $avg_execution_time = 0;
        if ($total_queries > 0) {
            $total_time = array_sum(array_column($execution_metrics, 'execution_time'));
            $avg_execution_time = $total_time / $total_queries;
        }
        
        return [
            'total_queries' => $total_queries,
            'cache_hits' => $cache_hits,
            'cache_hit_rate' => $total_requests > 0 ? ($cache_hits / $total_requests) * 100 : 0,
            'avg_execution_time' => $avg_execution_time,
            'total_execution_time' => array_sum(array_column($execution_metrics, 'execution_time')),
            'slow_queries' => count(self::getSlowQueries())
        ];
    }
}

/**
 * Helper functions for easier usage
 */

/**
 * Initialize query optimizer (shorthand)
 */
function init_query_optimizer($pdo, $cache = null) {
    QueryOptimizer::init($pdo, $cache);
}

/**
 * Execute optimized query (shorthand)
 */
function execute_optimized_query($query, $params = [], $options = []) {
    return QueryOptimizer::executeQuery($query, $params, $options);
}

/**
 * Lazy load image (shorthand)
 */
function lazy_load_image($image_path, $options = []) {
    return QueryOptimizer::lazyLoadImage($image_path, $options);
}

/**
 * Lazy load user data (shorthand)
 */
function lazy_load_user_data($user_id) {
    return QueryOptimizer::lazyLoadUserData($user_id);
}

/**
 * Lazy load document data (shorthand)
 */
function lazy_load_document_data($document_id) {
    return QueryOptimizer::lazyLoadDocumentData($document_id);
}

/**
 * Get query performance summary (shorthand)
 */
function get_query_performance_summary() {
    return QueryOptimizer::getPerformanceSummary();
}







