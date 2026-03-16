<?php
/**
 * Performance Management Page
 * 
 * Allows administrators to monitor and optimize system performance
 * including database optimization, caching, and query performance.
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

// Initialize performance systems
init_database_optimizer($pdo);
init_cache_manager(['cache_dir' => '../../cache/']);
init_query_optimizer($pdo, CacheManager::class);

// Handle actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    switch ($_POST['action']) {
        case 'create_indexes':
            $index_results = create_database_indexes();
            $_SESSION['success_message'] = "Database indexes created successfully.";
            break;
            
        case 'analyze_performance':
            $performance_analysis = analyze_database_performance();
            $_SESSION['performance_analysis'] = $performance_analysis;
            break;
            
        case 'clear_cache':
            cache_clear('all');
            $_SESSION['success_message'] = "All caches cleared successfully.";
            break;
            
        case 'maintenance':
            $maintenance_results = perform_database_maintenance();
            $_SESSION['maintenance_results'] = $maintenance_results;
            break;
            
        case 'clear_metrics':
            DatabaseOptimizer::clearPerformanceMetrics();
            QueryOptimizer::clearQueryMetrics();
            $_SESSION['success_message'] = "Performance metrics cleared successfully.";
            break;
    }
    
    redirect_to('performance.php');
}

// Get current performance data
$cache_stats = cache_stats();
$query_performance = get_query_performance_summary();
$database_analysis = null;

if (isset($_SESSION['performance_analysis'])) {
    $database_analysis = $_SESSION['performance_analysis'];
    unset($_SESSION['performance_analysis']);
}

$maintenance_results = null;
if (isset($_SESSION['maintenance_results'])) {
    $maintenance_results = $_SESSION['maintenance_results'];
    unset($_SESSION['maintenance_results']);
}

// Page title
$page_title = "Performance Management - CommuniLink";
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
                            <h1 class="text-2xl font-semibold text-gray-800">Performance Management</h1>
                        </div>
                        
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
                
                <!-- Performance Overview -->
                <div class="bg-white rounded-lg shadow p-6 mb-6">
                    <h2 class="text-lg font-medium text-gray-900 mb-4">Performance Overview</h2>
                    <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                        <div class="bg-blue-50 p-4 rounded-lg">
                            <h3 class="text-sm font-medium text-blue-800">Cache Hit Rate</h3>
                            <p class="text-2xl font-bold text-blue-600"><?php echo round($query_performance['cache_hit_rate'], 1); ?>%</p>
                            <p class="text-xs text-blue-600">Cache efficiency</p>
                        </div>
                        <div class="bg-green-50 p-4 rounded-lg">
                            <h3 class="text-sm font-medium text-green-800">Total Queries</h3>
                            <p class="text-2xl font-bold text-green-600"><?php echo $query_performance['total_queries']; ?></p>
                            <p class="text-xs text-green-600">Database queries</p>
                        </div>
                        <div class="bg-yellow-50 p-4 rounded-lg">
                            <h3 class="text-sm font-medium text-yellow-800">Avg Response</h3>
                            <p class="text-2xl font-bold text-yellow-600"><?php echo round($query_performance['avg_execution_time'] * 1000, 2); ?>ms</p>
                            <p class="text-xs text-yellow-600">Query response time</p>
                        </div>
                        <div class="bg-red-50 p-4 rounded-lg">
                            <h3 class="text-sm font-medium text-red-800">Slow Queries</h3>
                            <p class="text-2xl font-bold text-red-600"><?php echo $query_performance['slow_queries']; ?></p>
                            <p class="text-xs text-red-600">>100ms execution</p>
                        </div>
                    </div>
                </div>
                
                <!-- Action Buttons -->
                <div class="bg-white rounded-lg shadow p-6 mb-6">
                    <h2 class="text-lg font-medium text-gray-900 mb-4">Performance Actions</h2>
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
                        <form method="POST" class="space-y-2">
                            <input type="hidden" name="action" value="create_indexes">
                            <button type="submit" class="w-full bg-blue-600 text-white px-4 py-2 rounded-md hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500">
                                <i class="fas fa-database mr-2"></i>
                                Create Indexes
                            </button>
                        </form>
                        
                        <form method="POST" class="space-y-2">
                            <input type="hidden" name="action" value="analyze_performance">
                            <button type="submit" class="w-full bg-green-600 text-white px-4 py-2 rounded-md hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-green-500">
                                <i class="fas fa-chart-line mr-2"></i>
                                Analyze Performance
                            </button>
                        </form>
                        
                        <form method="POST" class="space-y-2">
                            <input type="hidden" name="action" value="clear_cache">
                            <button type="submit" class="w-full bg-yellow-600 text-white px-4 py-2 rounded-md hover:bg-yellow-700 focus:outline-none focus:ring-2 focus:ring-yellow-500">
                                <i class="fas fa-broom mr-2"></i>
                                Clear Cache
                            </button>
                        </form>
                        
                        <form method="POST" class="space-y-2">
                            <input type="hidden" name="action" value="maintenance">
                            <button type="submit" class="w-full bg-purple-600 text-white px-4 py-2 rounded-md hover:bg-purple-700 focus:outline-none focus:ring-2 focus:ring-purple-500">
                                <i class="fas fa-tools mr-2"></i>
                                Database Maintenance
                            </button>
                        </form>
                    </div>
                </div>
                
                <!-- Cache Statistics -->
                <div class="bg-white rounded-lg shadow p-6 mb-6">
                    <h2 class="text-lg font-medium text-gray-900 mb-4">Cache Statistics</h2>
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                        <?php foreach ($cache_stats as $backend => $stats): ?>
                            <div class="bg-gray-50 p-4 rounded-lg">
                                <h3 class="text-sm font-medium text-gray-800 capitalize"><?php echo $backend; ?> Backend</h3>
                                <?php if (isset($stats['entries'])): ?>
                                    <p class="text-2xl font-bold text-gray-600"><?php echo $stats['entries']; ?></p>
                                    <p class="text-xs text-gray-600">Cached entries</p>
                                <?php endif; ?>
                                <?php if (isset($stats['hits'])): ?>
                                    <p class="text-lg font-semibold text-green-600"><?php echo $stats['hits']; ?></p>
                                    <p class="text-xs text-gray-600">Cache hits</p>
                                <?php endif; ?>
                                <?php if (isset($stats['size'])): ?>
                                    <p class="text-sm text-blue-600"><?php echo formatBytes($stats['size']); ?></p>
                                    <p class="text-xs text-gray-600">Memory usage</p>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                
                <!-- Database Performance Analysis -->
                <?php if ($database_analysis): ?>
                    <div class="bg-white rounded-lg shadow p-6 mb-6">
                        <h2 class="text-lg font-medium text-gray-900 mb-4">Database Performance Analysis</h2>
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200">
                                <thead class="bg-gray-50">
                                    <tr>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Table</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Rows</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Columns</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Indexes</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Size (MB)</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Recommendations</th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white divide-y divide-gray-200">
                                    <?php foreach ($database_analysis as $table => $analysis): ?>
                                        <?php if (!isset($analysis['error'])): ?>
                                            <tr>
                                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900"><?php echo ucfirst($table); ?></td>
                                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><?php echo number_format($analysis['row_count']); ?></td>
                                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><?php echo $analysis['columns']; ?></td>
                                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><?php echo $analysis['indexes']; ?></td>
                                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><?php echo $analysis['size_mb']; ?></td>
                                                <td class="px-6 py-4 text-sm text-gray-500">
                                                    <?php if (!empty($analysis['recommendations'])): ?>
                                                        <ul class="list-disc list-inside space-y-1">
                                                            <?php foreach ($analysis['recommendations'] as $rec): ?>
                                                                <li class="text-xs text-yellow-600"><?php echo htmlspecialchars($rec); ?></li>
                                                            <?php endforeach; ?>
                                                        </ul>
                                                    <?php else: ?>
                                                        <span class="text-green-600 text-xs">✓ Optimized</span>
                                                    <?php endif; ?>
                                                </td>
                                            </tr>
                                        <?php endif; ?>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                <?php endif; ?>
                
                <!-- Maintenance Results -->
                <?php if ($maintenance_results): ?>
                    <div class="bg-white rounded-lg shadow p-6 mb-6">
                        <h2 class="text-lg font-medium text-gray-900 mb-4">Database Maintenance Results</h2>
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                            <?php foreach ($maintenance_results as $task => $result): ?>
                                <div class="bg-gray-50 p-4 rounded-lg">
                                    <h3 class="text-sm font-medium text-gray-800"><?php echo $task; ?></h3>
                                    <?php if ($result['status'] === 'SUCCESS'): ?>
                                        <p class="text-green-600 text-sm">✓ Completed</p>
                                        <?php if (isset($result['execution_time'])): ?>
                                            <p class="text-xs text-gray-600">Time: <?php echo round($result['execution_time'], 3); ?>s</p>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        <p class="text-red-600 text-sm">✗ Failed</p>
                                        <p class="text-xs text-gray-600"><?php echo htmlspecialchars($result['message']); ?></p>
                                    <?php endif; ?>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endif; ?>
                
                <!-- Performance Tips -->
                <div class="bg-white rounded-lg shadow p-6">
                    <h2 class="text-lg font-medium text-gray-900 mb-4">Performance Optimization Tips</h2>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div>
                            <h3 class="text-sm font-medium text-gray-900 mb-2">Database Optimization</h3>
                            <ul class="text-sm text-gray-600 space-y-1">
                                <li>• Create indexes on frequently queried columns</li>
                                <li>• Use pagination for large result sets</li>
                                <li>• Optimize queries with EXPLAIN</li>
                                <li>• Regular database maintenance (ANALYZE, VACUUM)</li>
                            </ul>
                        </div>
                        <div>
                            <h3 class="text-sm font-medium text-gray-900 mb-2">Caching Strategy</h3>
                            <ul class="text-sm text-gray-600 space-y-1">
                                <li>• Cache frequently accessed data</li>
                                <li>• Use appropriate TTL values</li>
                                <li>• Implement cache invalidation</li>
                                <li>• Monitor cache hit rates</li>
                            </ul>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>
    
    <script>
        // Auto-refresh performance data every 30 seconds
        setInterval(function() {
            location.reload();
        }, 30000);
    </script>
</body>
</html>

<?php
/**
 * Helper function to format bytes
 */
function formatBytes($bytes, $precision = 2) {
    $units = ['B', 'KB', 'MB', 'GB', 'TB'];
    
    for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
        $bytes /= 1024;
    }
    
    return round($bytes, $precision) . ' ' . $units[$i];
}
?>







