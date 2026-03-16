<?php
require_once '../../config/init.php';
require_once '../../includes/auth.php';
require_once '../../includes/functions.php';

// Check if user is logged in and is admin
if (!is_logged_in() || $_SESSION['role'] !== 'admin') {
    redirect_to('../../index.php');
}

// --- Filtering, Searching, Pagination ---
$per_page = 20;
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$offset = ($page - 1) * $per_page;

$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$user = isset($_GET['user']) ? trim($_GET['user']) : '';
$action = isset($_GET['action']) ? trim($_GET['action']) : '';
$date_from = isset($_GET['date_from']) ? trim($_GET['date_from']) : '';
$date_to = isset($_GET['date_to']) ? trim($_GET['date_to']) : '';

$where = [];
$params = [];
if ($search) {
    $where[] = "(username LIKE ? OR details LIKE ? OR target_type LIKE ? OR ip_address LIKE ? OR old_value LIKE ? OR new_value LIKE ?)";
    for ($i = 0; $i < 6; $i++) $params[] = "%$search%";
}
if ($user) {
    $where[] = "username = ?";
    $params[] = $user;
}
if ($action) {
    $where[] = "action = ?";
    $params[] = $action;
}
if ($date_from) {
    $where[] = "created_at >= ?";
    $params[] = $date_from . ' 00:00:00';
}
if ($date_to) {
    $where[] = "created_at <= ?";
    $params[] = $date_to . ' 23:59:59';
}
$where_sql = $where ? ('WHERE ' . implode(' AND ', $where)) : '';

// --- CSV Export ---
if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment;filename="activity_logs.csv"');
    $out = fopen('php://output', 'w');
    fputcsv($out, ['Date/Time', 'User', 'Action', 'Target', 'Details', 'Old Value', 'New Value']);
    $stmt = $pdo->prepare("SELECT * FROM activity_logs $where_sql ORDER BY created_at DESC");
    $stmt->execute($params);
    while ($log = $stmt->fetch()) {
        fputcsv($out, [
            $log['created_at'],
            $log['username'],
            ucfirst($log['action']),
            $log['target_type'] . ($log['target_id'] ? ' #' . $log['target_id'] : ''),
            $log['details'],
            $log['old_value'],
            $log['new_value'],
        ]);
    }
    fclose($out);
    exit;
}

// --- Fetch logs for display ---
$count_stmt = $pdo->prepare("SELECT COUNT(*) FROM activity_logs $where_sql");
$count_stmt->execute($params);
$total_logs = $count_stmt->fetchColumn();

$stmt = $pdo->prepare("SELECT * FROM activity_logs $where_sql ORDER BY created_at DESC LIMIT $per_page OFFSET $offset");
$stmt->execute($params);
$logs = $stmt->fetchAll();

// --- For filter dropdowns ---
$users = $pdo->query("SELECT DISTINCT username FROM activity_logs ORDER BY username")->fetchAll(PDO::FETCH_COLUMN);
$actions = $pdo->query("SELECT DISTINCT action FROM activity_logs ORDER BY action")->fetchAll(PDO::FETCH_COLUMN);

$page_title = 'System Logs';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?> - Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body class="bg-gray-100 min-h-screen">
<div class="flex h-screen overflow-hidden">
    <?php include '../partials/sidebar.php'; ?>
    <div class="flex flex-col flex-1 overflow-hidden">
        <header class="bg-white shadow-sm z-10">
            <div class="px-4 sm:px-6 lg:px-8">
                <div class="flex items-center justify-between h-16">
                    <h1 class="text-2xl font-semibold text-gray-800">System Logs</h1>
                    <div class="flex items-center space-x-4">
                        <span class="text-sm text-gray-600">Total Logs: <?php echo number_format($total_logs); ?></span>
                    </div>
                </div>
            </div>
        </header>
        <main class="flex-1 overflow-y-auto bg-gray-50 p-4 sm:p-6 lg:p-8">
            <div class="bg-white rounded-lg shadow p-6">
                <div class="mb-4 flex flex-wrap gap-2 items-center">
                    <a href="logs.php" class="ml-2 text-gray-500 hover:text-blue-700 text-sm">Show All Logs</a>
                </div>
                <form method="get" class="mb-4 flex flex-wrap gap-2 items-end">
                    <div class="relative">
                        <span class="absolute inset-y-0 left-0 flex items-center pl-3 text-gray-500">
                            <i class="fas fa-search"></i>
                        </span>
                        <input type="text" name="search" value="<?php echo htmlspecialchars($search); ?>" placeholder="Search logs..." class="w-full pl-10 pr-4 py-2 bg-gray-100 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500" />
                    </div>
                    <select name="user" class="bg-gray-100 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500 text-sm">
                        <option value="">All Users</option>
                        <?php foreach ($users as $u): ?>
                            <option value="<?php echo htmlspecialchars($u); ?>" <?php if ($user === $u) echo 'selected'; ?>><?php echo htmlspecialchars($u); ?></option>
                        <?php endforeach; ?>
                    </select>
                    <select name="action" class="bg-gray-100 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500 text-sm">
                        <option value="">All Actions</option>
                        <?php foreach ($actions as $a): ?>
                            <option value="<?php echo htmlspecialchars($a); ?>" <?php if ($action === $a) echo 'selected'; ?>><?php echo htmlspecialchars(ucfirst($a)); ?></option>
                        <?php endforeach; ?>
                    </select>
                    <input type="date" name="date_from" value="<?php echo htmlspecialchars($date_from); ?>" placeholder="From Date" class="bg-gray-100 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500 text-sm" />
                    <input type="date" name="date_to" value="<?php echo htmlspecialchars($date_to); ?>" placeholder="To Date" class="bg-gray-100 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500 text-sm" />
                    <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg text-sm font-semibold">Filter</button>
                    <a href="logs.php" class="text-gray-500 hover:text-blue-700 text-sm ml-2">Reset</a>
                    <button type="submit" name="export" value="csv" class="ml-auto bg-green-600 hover:bg-green-700 text-white px-4 py-2 rounded-lg text-sm font-semibold flex items-center"><i class="fas fa-file-csv mr-2"></i>Export CSV</button>
                </form>
                
                <?php if (count($logs)): ?>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-100">
                            <tr>
                                <th class="px-4 py-2 text-left text-xs font-semibold text-gray-700 uppercase tracking-wider">Date/Time</th>
                                <th class="px-4 py-2 text-left text-xs font-semibold text-gray-700 uppercase tracking-wider">User</th>
                                <th class="px-4 py-2 text-left text-xs font-semibold text-gray-700 uppercase tracking-wider">Action</th>
                                <th class="px-4 py-2 text-left text-xs font-semibold text-gray-700 uppercase tracking-wider">Target</th>
                                <th class="px-4 py-2 text-left text-xs font-semibold text-gray-700 uppercase tracking-wider">Details</th>
                                <th class="px-4 py-2 text-left text-xs font-semibold text-gray-700 uppercase tracking-wider">Old Value</th>
                                <th class="px-4 py-2 text-left text-xs font-semibold text-gray-700 uppercase tracking-wider">New Value</th>
                                <th class="px-4 py-2 text-left text-xs font-semibold text-gray-700 uppercase tracking-wider">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-100">
                            <?php foreach ($logs as $log): ?>
                                <tr class="hover:bg-gray-50">
                                    <td class="px-4 py-2 whitespace-nowrap text-sm text-gray-600"><?php echo htmlspecialchars($log['created_at']); ?></td>
                                    <td class="px-4 py-2 whitespace-nowrap text-sm text-gray-800 font-medium"><?php echo htmlspecialchars($log['username']); ?></td>
                                    <td class="px-4 py-2 whitespace-nowrap text-sm text-blue-700 font-semibold"><?php echo htmlspecialchars(ucfirst($log['action'])); ?></td>
                                    <td class="px-4 py-2 whitespace-nowrap text-sm text-gray-700"><?php echo htmlspecialchars($log['target_type']); ?><?php if ($log['target_id']) echo ' #'.htmlspecialchars($log['target_id']); ?></td>
                                    <td class="px-4 py-2 text-sm text-gray-600"><?php echo htmlspecialchars($log['details']); ?></td>
                                    <td class="px-4 py-2 text-sm text-gray-500">
                                        <?php
                                        $old_val = $log['old_value'];
                                        if (strlen($old_val) > 60) {
                                            $short = htmlspecialchars(mb_substr($old_val, 0, 60)) . '...';
                                            $full = htmlspecialchars($old_val);
                                            $id = 'oldval-' . $log['id'];
                                            echo "<span>{$short}</span> <a href=\"#\" class=\"text-blue-600 underline view-all-link\" data-modal='modal-{$id}'>View All</a>";
                                            echo "<div id='modal-{$id}' class='hidden fixed inset-0 z-50 flex items-center justify-center bg-black bg-opacity-50'>
                                                    <div class='bg-white rounded-lg shadow-lg max-w-lg w-full p-6 relative'>
                                                        <button class='absolute top-2 right-2 text-gray-500 close-modal' data-modal='modal-{$id}'>&times;</button>
                                                        <h3 class='text-lg font-semibold mb-2'>Full Old Value</h3>
                                                        <pre class='whitespace-pre-wrap text-xs bg-gray-50 rounded p-2 max-h-96 overflow-y-auto'>{$full}</pre>
                                                    </div>
                                                </div>";
                                        } else {
                                            echo nl2br(htmlspecialchars($old_val));
                                        }
                                        ?>
                                    </td>
                                    <td class="px-4 py-2 text-sm text-gray-500">
                                        <?php
                                        $new_val = $log['new_value'];
                                        if (strlen($new_val) > 60) {
                                            $short = htmlspecialchars(mb_substr($new_val, 0, 60)) . '...';
                                            $full = htmlspecialchars($new_val);
                                            $id = 'newval-' . $log['id'];
                                            echo "<span>{$short}</span> <a href=\"#\" class=\"text-blue-600 underline view-all-link\" data-modal='modal-{$id}'>View All</a>";
                                            echo "<div id='modal-{$id}' class='hidden fixed inset-0 z-50 flex items-center justify-center bg-black bg-opacity-50'>
                                                    <div class='bg-white rounded-lg shadow-lg max-w-lg w-full p-6 relative'>
                                                        <button class='absolute top-2 right-2 text-gray-500 close-modal' data-modal='modal-{$id}'>&times;</button>
                                                        <h3 class='text-lg font-semibold mb-2'>Full New Value</h3>
                                                        <pre class='whitespace-pre-wrap text-xs bg-green-50 rounded p-2 max-h-96 overflow-y-auto'>{$full}</pre>
                                                    </div>
                                                </div>";
                                        } else {
                                            echo nl2br(htmlspecialchars($new_val));
                                        }
                                        ?>
                                    </td>
                                    <td class="px-4 py-2 text-sm text-center">
                                        <form method="POST" action="../partials/delete-log-handler.php" onsubmit="return confirm('Are you sure you want to delete this log entry?');" style="display:inline;">
                                            <input type="hidden" name="log_id" value="<?php echo (int)$log['id']; ?>">
                                            <button type="submit" class="bg-red-600 hover:bg-red-700 text-white px-3 py-1 rounded text-xs font-semibold" title="Delete">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                
                <!-- Pagination -->
                <?php
                $total_pages = ceil($total_logs / $per_page);
                if ($total_pages > 1): ?>
                <div class="mb-4 flex justify-center space-x-1 items-center">
                    <a href="?<?php
                        $q = $_GET;
                        $q['page'] = 1;
                        echo http_build_query($q);
                    ?>"
                    class="px-3 py-1 rounded <?php echo $page == 1 ? 'bg-blue-600 text-white' : 'bg-gray-200 text-gray-700 hover:bg-blue-100'; ?> text-sm font-semibold mr-2">
                        First
                    </a>
                    <?php
                    // Show up to 2 pages before and after the current page
                    $start = max(1, $page - 2);
                    $end = min($total_pages, $page + 2);
                    if ($start > 1) echo '<span class="px-2">...</span>';
                    for ($i = $start; $i <= $end; $i++): ?>
                        <a href="?<?php
                            $q = $_GET;
                            $q['page'] = $i;
                            echo http_build_query($q);
                        ?>"
                        class="px-3 py-1 rounded <?php echo $i == $page ? 'bg-blue-600 text-white' : 'bg-gray-200 text-gray-700 hover:bg-blue-100'; ?> text-sm font-semibold">
                            <?php echo $i; ?>
                        </a>
                    <?php endfor;
                    if ($end < $total_pages) echo '<span class="px-2">...</span>';
                    ?>
                    <a href="?<?php
                        $q = $_GET;
                        $q['page'] = $total_pages;
                        echo http_build_query($q);
                    ?>"
                    class="px-3 py-1 rounded <?php echo $page == $total_pages ? 'bg-blue-600 text-white' : 'bg-gray-200 text-gray-700 hover:bg-blue-100'; ?> text-sm font-semibold ml-2">
                        Last
                    </a>
                </div>
                <?php endif; ?>
                
                <?php else: ?>
                <div class="text-center py-8">
                    <i class="fas fa-clipboard-list text-4xl text-gray-300 mb-4"></i>
                    <p class="text-gray-500 text-lg">No activity logs found.</p>
                    <p class="text-gray-400 text-sm mt-2">Activity logs will appear here when users perform actions in the system.</p>
                </div>
                <?php endif; ?>
            </div>
        </main>
    </div>
</div>
<script>
document.addEventListener('DOMContentLoaded', function() {
    document.querySelectorAll('.view-all-link').forEach(function(link) {
        link.addEventListener('click', function(e) {
            e.preventDefault();
            var modalId = this.getAttribute('data-modal');
            document.getElementById(modalId).classList.remove('hidden');
        });
    });
    document.querySelectorAll('.close-modal').forEach(function(btn) {
        btn.addEventListener('click', function() {
            var modalId = this.getAttribute('data-modal');
            document.getElementById(modalId).classList.add('hidden');
        });
    });
    // Close modal on background click
    document.querySelectorAll('[id^="modal-"]').forEach(function(modal) {
        modal.addEventListener('click', function(e) {
            if (e.target === modal) {
                modal.classList.add('hidden');
            }
        });
    });
});
</script>
</body>
</html> 