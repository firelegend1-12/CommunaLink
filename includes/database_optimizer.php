<?php
/**
 * Database Optimization Helper
 * 
 * Provides database performance optimization features including:
 * - Index management for frequently queried columns
 * - Query optimization utilities
 * - Connection pooling and monitoring
 * - Performance analysis tools
 */

class DatabaseOptimizer {
    
    /**
     * Database connection
     */
    private static $pdo = null;
    
    /**
     * Performance metrics
     */
    private static $metrics = [];
    
    /**
     * Initialize database optimizer
     */
    public static function init($pdo) {
        self::$pdo = $pdo;
    }
    
    /**
     * Create optimized indexes for frequently queried columns
     */
    public static function createOptimizedIndexes() {
        if (!self::$pdo) {
            throw new Exception("Database connection not initialized");
        }
        
        $indexes = [
            // Users table indexes
            'users_username_idx' => 'CREATE INDEX IF NOT EXISTS users_username_idx ON users(username)',
            'users_email_idx' => 'CREATE INDEX IF NOT EXISTS users_email_idx ON users(email)',
            'users_role_idx' => 'CREATE INDEX IF NOT EXISTS users_role_idx ON users(role)',
            'users_status_idx' => 'CREATE INDEX IF NOT EXISTS users_status_idx ON users(status)',
            'users_created_at_idx' => 'CREATE INDEX IF NOT EXISTS users_created_at_idx ON users(created_at)',
            
            // Residents table indexes
            'residents_user_id_idx' => 'CREATE INDEX IF NOT EXISTS residents_user_id_idx ON residents(user_id)',
            'residents_created_at_idx' => 'CREATE INDEX IF NOT EXISTS residents_created_at_idx ON residents(created_at)',
            'residents_status_idx' => 'CREATE INDEX IF NOT EXISTS residents_status_idx ON residents(status)',
            'residents_last_name_idx' => 'CREATE INDEX IF NOT EXISTS residents_last_name_idx ON residents(last_name)',
            
            // Businesses table indexes
            'businesses_status_idx' => 'CREATE INDEX IF NOT EXISTS businesses_status_idx ON businesses(status)',
            'businesses_permit_expiration_idx' => 'CREATE INDEX IF NOT EXISTS businesses_permit_expiration_idx ON businesses(permit_expiration_date)',
            'businesses_created_at_idx' => 'CREATE INDEX IF NOT EXISTS businesses_created_at_idx ON businesses(created_at)',
            'businesses_owner_name_idx' => 'CREATE INDEX IF NOT EXISTS businesses_owner_name_idx ON businesses(owner_name)',
            
            // Incidents table indexes
            'incidents_status_idx' => 'CREATE INDEX IF NOT EXISTS incidents_status_idx ON incidents(status)',
            'incidents_created_at_idx' => 'CREATE INDEX IF NOT EXISTS incidents_created_at_idx ON incidents(created_at)',
            'incidents_location_idx' => 'CREATE INDEX IF NOT EXISTS incidents_location_idx ON incidents(location)',
            'incidents_type_idx' => 'CREATE INDEX IF NOT EXISTS incidents_type_idx ON incidents(type)',
            'incidents_user_id_idx' => 'CREATE INDEX IF NOT EXISTS incidents_user_id_idx ON incidents(user_id)',
            
            // Documents table indexes
            'documents_type_idx' => 'CREATE INDEX IF NOT EXISTS documents_type_idx ON documents(type)',
            'documents_status_idx' => 'CREATE INDEX IF NOT EXISTS documents_status_idx ON documents(status)',
            'documents_created_at_idx' => 'CREATE INDEX IF NOT EXISTS documents_created_at_idx ON documents(created_at)',
            'documents_user_id_idx' => 'CREATE INDEX IF NOT EXISTS documents_user_id_idx ON documents(user_id)',
            
            // Activity logs table indexes
            'activity_logs_action_idx' => 'CREATE INDEX IF NOT EXISTS activity_logs_action_idx ON activity_logs(action)',
            'activity_logs_created_at_idx' => 'CREATE INDEX IF NOT EXISTS activity_logs_created_at_idx ON activity_logs(created_at)',
            'activity_logs_user_id_idx' => 'CREATE INDEX IF NOT EXISTS activity_logs_user_id_idx ON activity_logs(user_id)',
            'activity_logs_table_name_idx' => 'CREATE INDEX IF NOT EXISTS activity_logs_table_name_idx ON activity_logs(table_name)'
        ];
        
        $results = [];
        
        foreach ($indexes as $index_name => $sql) {
            try {
                $stmt = self::$pdo->prepare($sql);
                $stmt->execute();
                $results[$index_name] = 'SUCCESS';
            } catch (PDOException $e) {
                $results[$index_name] = 'ERROR: ' . $e->getMessage();
            }
        }
        
        return $results;
    }
    
    /**
     * Analyze table performance and suggest optimizations
     */
    public static function analyzeTablePerformance() {
        if (!self::$pdo) {
            throw new Exception("Database connection not initialized");
        }
        
        $tables = ['users', 'residents', 'businesses', 'incidents', 'documents', 'activity_logs'];
        $analysis = [];
        
        foreach ($tables as $table) {
            try {
                // Get table size
                $stmt = self::$pdo->prepare("SELECT COUNT(*) as row_count FROM $table");
                $stmt->execute();
                $row_count = $stmt->fetchColumn();
                
                // Get table structure
                $stmt = self::$pdo->prepare("PRAGMA table_info($table)");
                $stmt->execute();
                $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                // Analyze indexes
                $stmt = self::$pdo->prepare("PRAGMA index_list($table)");
                $stmt->execute();
                $indexes = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                $analysis[$table] = [
                    'row_count' => $row_count,
                    'columns' => count($columns),
                    'indexes' => count($indexes),
                    'size_mb' => self::getTableSize($table),
                    'recommendations' => self::getTableRecommendations($table, $row_count, $columns, $indexes)
                ];
                
            } catch (PDOException $e) {
                $analysis[$table] = ['error' => $e->getMessage()];
            }
        }
        
        return $analysis;
    }
    
    /**
     * Get table size in MB
     */
    private static function getTableSize($table) {
        try {
            $stmt = self::$pdo->prepare("PRAGMA page_count");
            $stmt->execute();
            $page_count = $stmt->fetchColumn();
            
            $stmt = self::$pdo->prepare("PRAGMA page_size");
            $stmt->execute();
            $page_size = $stmt->fetchColumn();
            
            return round(($page_count * $page_size) / (1024 * 1024), 2);
        } catch (PDOException $e) {
            return 0;
        }
    }
    
    /**
     * Get optimization recommendations for a table
     */
    private static function getTableRecommendations($table, $row_count, $columns, $indexes) {
        $recommendations = [];
        
        // Large table recommendations
        if ($row_count > 10000) {
            $recommendations[] = "Consider partitioning for tables with >10k rows";
            $recommendations[] = "Implement pagination for all queries";
        }
        
        // Index recommendations
        if ($indexes < 3) {
            $recommendations[] = "Add more indexes for frequently queried columns";
        }
        
        // Column recommendations
        if ($columns > 15) {
            $recommendations[] = "Consider normalizing table structure";
        }
        
        // Table-specific recommendations
        switch ($table) {
            case 'users':
                if ($row_count > 1000) {
                    $recommendations[] = "Add composite index on (role, status)";
                }
                break;
            case 'incidents':
                if ($row_count > 5000) {
                    $recommendations[] = "Add composite index on (status, created_at)";
                    $recommendations[] = "Add composite index on (location, type)";
                }
                break;
            case 'businesses':
                if ($row_count > 1000) {
                    $recommendations[] = "Add composite index on (status, permit_expiration_date)";
                }
                break;
        }
        
        return $recommendations;
    }
    
    /**
     * Optimize specific queries
     */
    public static function optimizeQuery($query, $params = []) {
        if (!self::$pdo) {
            throw new Exception("Database connection not initialized");
        }
        
        $start_time = microtime(true);
        
        try {
            $stmt = self::$pdo->prepare($query);
            $stmt->execute($params);
            $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            $execution_time = microtime(true) - $start_time;
            
            // Store performance metrics
            self::$metrics[] = [
                'query' => $query,
                'execution_time' => $execution_time,
                'rows_returned' => count($result),
                'timestamp' => date('Y-m-d H:i:s')
            ];
            
            return [
                'success' => true,
                'data' => $result,
                'execution_time' => $execution_time,
                'rows_returned' => count($result)
            ];
            
        } catch (PDOException $e) {
            $execution_time = microtime(true) - $start_time;
            
            return [
                'success' => false,
                'error' => $e->getMessage(),
                'execution_time' => $execution_time
            ];
        }
    }
    
    /**
     * Get query performance metrics
     */
    public static function getPerformanceMetrics() {
        return self::$metrics;
    }
    
    /**
     * Clear performance metrics
     */
    public static function clearPerformanceMetrics() {
        self::$metrics = [];
    }
    
    /**
     * Get slow queries (execution time > 0.1 seconds)
     */
    public static function getSlowQueries($threshold = 0.1) {
        return array_filter(self::$metrics, function($metric) use ($threshold) {
            return $metric['execution_time'] > $threshold;
        });
    }
    
    /**
     * Optimize dashboard queries
     */
    public static function getOptimizedDashboardStats() {
        if (!self::$pdo) {
            throw new Exception("Database connection not initialized");
        }
        
        $stats = [];
        
        // Users count by role (optimized)
        $query = "SELECT role, COUNT(*) as count FROM users WHERE status = 'active' GROUP BY role";
        $result = self::optimizeQuery($query);
        if ($result['success']) {
            $stats['users_by_role'] = $result['data'];
        }
        
        // Recent incidents (with pagination)
        $query = "SELECT id, type, location, status, created_at FROM incidents 
                  WHERE status != 'resolved' 
                  ORDER BY created_at DESC 
                  LIMIT 10";
        $result = self::optimizeQuery($query);
        if ($result['success']) {
            $stats['recent_incidents'] = $result['data'];
        }
        
        // Business permits expiring soon
        $query = "SELECT id, business_name, owner_name, permit_expiration_date 
                  FROM businesses 
                  WHERE status = 'active' 
                  AND permit_expiration_date BETWEEN DATE('now') AND DATE('now', '+30 days')
                  ORDER BY permit_expiration_date ASC";
        $result = self::optimizeQuery($query);
        if ($result['success']) {
            $stats['expiring_permits'] = $result['data'];
        }
        
        // Monthly incident trends
        $query = "SELECT strftime('%Y-%m', created_at) as month, COUNT(*) as count 
                  FROM incidents 
                  WHERE created_at >= DATE('now', '-6 months')
                  GROUP BY month 
                  ORDER BY month DESC";
        $result = self::optimizeQuery($query);
        if ($result['success']) {
            $stats['incident_trends'] = $result['data'];
        }
        
        return $stats;
    }
    
    /**
     * Implement pagination for large datasets
     */
    public static function getPaginatedResults($table, $page = 1, $per_page = 20, $filters = [], $order_by = 'id', $order_direction = 'DESC') {
        if (!self::$pdo) {
            throw new Exception("Database connection not initialized");
        }
        
        $offset = ($page - 1) * $per_page;
        
        // Build WHERE clause for filters
        $where_clause = '';
        $params = [];
        
        if (!empty($filters)) {
            $conditions = [];
            foreach ($filters as $column => $value) {
                if ($value !== null && $value !== '') {
                    $conditions[] = "$column = ?";
                    $params[] = $value;
                }
            }
            if (!empty($conditions)) {
                $where_clause = 'WHERE ' . implode(' AND ', $conditions);
            }
        }
        
        // Get total count
        $count_query = "SELECT COUNT(*) FROM $table $where_clause";
        $stmt = self::$pdo->prepare($count_query);
        $stmt->execute($params);
        $total_count = $stmt->fetchColumn();
        
        // Get paginated data
        $data_query = "SELECT * FROM $table $where_clause ORDER BY $order_by $order_direction LIMIT $per_page OFFSET $offset";
        $result = self::optimizeQuery($data_query, $params);
        
        $total_pages = ceil($total_count / $per_page);
        
        return [
            'data' => $result['success'] ? $result['data'] : [],
            'pagination' => [
                'current_page' => $page,
                'per_page' => $per_page,
                'total_count' => $total_count,
                'total_pages' => $total_pages,
                'has_next' => $page < $total_pages,
                'has_prev' => $page > 1
            ],
            'performance' => [
                'execution_time' => $result['execution_time'] ?? 0,
                'rows_returned' => $result['rows_returned'] ?? 0
            ]
        ];
    }
    
    /**
     * Database maintenance tasks
     */
    public static function performMaintenance() {
        if (!self::$pdo) {
            throw new Exception("Database connection not initialized");
        }
        
        $tasks = [
            'ANALYZE' => 'ANALYZE',
            'VACUUM' => 'VACUUM',
            'REINDEX' => 'REINDEX'
        ];
        
        $results = [];
        
        foreach ($tasks as $task_name => $sql) {
            try {
                $start_time = microtime(true);
                self::$pdo->exec($sql);
                $execution_time = microtime(true) - $start_time;
                
                $results[$task_name] = [
                    'status' => 'SUCCESS',
                    'execution_time' => $execution_time
                ];
            } catch (PDOException $e) {
                $results[$task_name] = [
                    'status' => 'ERROR',
                    'message' => $e->getMessage()
                ];
            }
        }
        
        return $results;
    }
}

/**
 * Helper functions for easier usage
 */

/**
 * Initialize database optimizer (shorthand)
 */
function init_database_optimizer($pdo) {
    DatabaseOptimizer::init($pdo);
}

/**
 * Create optimized indexes (shorthand)
 */
function create_database_indexes() {
    return DatabaseOptimizer::createOptimizedIndexes();
}

/**
 * Analyze table performance (shorthand)
 */
function analyze_database_performance() {
    return DatabaseOptimizer::analyzeTablePerformance();
}

/**
 * Get optimized dashboard stats (shorthand)
 */
function get_optimized_dashboard_stats() {
    return DatabaseOptimizer::getOptimizedDashboardStats();
}

/**
 * Get paginated results (shorthand)
 */
function get_paginated_results($table, $page = 1, $per_page = 20, $filters = [], $order_by = 'id', $order_direction = 'DESC') {
    return DatabaseOptimizer::getPaginatedResults($table, $page, $per_page, $filters, $order_by, $order_direction);
}

/**
 * Perform database maintenance (shorthand)
 */
function perform_database_maintenance() {
    return DatabaseOptimizer::performMaintenance();
}







