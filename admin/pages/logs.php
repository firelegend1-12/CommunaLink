<?php
require_once '../../config/init.php';
require_once '../../includes/auth.php';
require_once '../../includes/functions.php';
require_once '../../includes/csrf.php';

if (!is_logged_in() || $_SESSION['role'] !== 'admin') {
    redirect_to('../../index.php');
}

function build_logs_url($params, $overrides = []) {
    $merged = array_merge($params, $overrides);
    foreach ($merged as $key => $value) {
        if ($value === '' || $value === null) {
            unset($merged[$key]);
        }
    }

    return 'logs.php' . (empty($merged) ? '' : '?' . http_build_query($merged));
}

$per_page = 20;
$page = isset($_GET['page']) ? max(1, (int) $_GET['page']) : 1;
$offset = ($page - 1) * $per_page;

$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$user = isset($_GET['user']) ? trim($_GET['user']) : '';
$action = isset($_GET['action']) ? trim($_GET['action']) : '';
$severity_filter = isset($_GET['severity']) ? trim($_GET['severity']) : '';
$date_from = isset($_GET['date_from']) ? trim($_GET['date_from']) : '';
$date_to = isset($_GET['date_to']) ? trim($_GET['date_to']) : '';
$quick_filter = isset($_GET['quick_filter']) ? trim($_GET['quick_filter']) : '';
$export_preset = isset($_GET['export_preset']) ? trim($_GET['export_preset']) : '';

if ($export_preset === 'daily_ops') {
    $date_from = date('Y-m-d');
    $date_to = date('Y-m-d');
} elseif ($export_preset === 'security_review') {
    if ($quick_filter === '') {
        $quick_filter = 'auth_events';
    }
} elseif ($export_preset === 'payment_audit') {
    if ($quick_filter === '') {
        $quick_filter = 'payment_events';
    }
}

$where = [];
$params = [];

if ($search) {
    $where[] = "(username LIKE ? OR details LIKE ? OR target_type LIKE ? OR ip_address LIKE ? OR user_agent LIKE ? OR session_id LIKE ? OR request_id LIKE ? OR old_value LIKE ? OR new_value LIKE ?)";
    for ($i = 0; $i < 9; $i++) {
        $params[] = "%$search%";
    }
}
if ($user) {
    $where[] = 'username = ?';
    $params[] = $user;
}
if ($action) {
    $where[] = 'action = ?';
    $params[] = $action;
}
if ($severity_filter) {
    $where[] = 'severity = ?';
    $params[] = $severity_filter;
}
if ($date_from) {
    $where[] = 'created_at >= ?';
    $params[] = $date_from . ' 00:00:00';
}
if ($date_to) {
    $where[] = 'created_at <= ?';
    $params[] = $date_to . ' 23:59:59';
}

switch ($quick_filter) {
    case 'today':
        $where[] = 'DATE(created_at) = CURDATE()';
        break;
    case 'errors_only':
        $where[] = "(severity IN ('error', 'critical') OR action IN ('error', 'failed', 'deny'))";
        break;
    case 'payment_events':
        $where[] = "(target_type IN ('payment', 'document_request', 'business_transaction') OR action LIKE '%payment%' OR details LIKE '%payment%')";
        break;
    case 'status_changes':
        $where[] = "(action IN ('status_change', 'update_status') OR details LIKE '%status%')";
        break;
    case 'auth_events':
        $where[] = "(target_type = 'auth' OR action IN ('login', 'logout', 'failed_login', 'password_reset') OR details LIKE '%login%' OR details LIKE '%logout%')";
        break;
}

$where_sql = $where ? ('WHERE ' . implode(' AND ', $where)) : '';

if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment;filename="activity_logs.csv"');
    $out = fopen('php://output', 'w');
    fputcsv($out, ['Date/Time', 'Severity', 'User', 'Action', 'Target', 'Request ID', 'Session ID', 'IP Address', 'User Agent', 'Details', 'Old Value', 'New Value']);

    $stmt = $pdo->prepare("SELECT * FROM activity_logs $where_sql ORDER BY created_at DESC");
    $stmt->execute($params);

    while ($log = $stmt->fetch()) {
        fputcsv($out, [
            $log['created_at'],
            strtoupper($log['severity'] ?? 'info'),
            $log['username'],
            ucfirst($log['action']),
            $log['target_type'] . ($log['target_id'] ? ' #' . $log['target_id'] : ''),
            $log['request_id'] ?? '',
            $log['session_id'] ?? '',
            $log['ip_address'] ?? '',
            $log['user_agent'] ?? '',
            $log['details'],
            $log['old_value'],
            $log['new_value'],
        ]);
    }

    fclose($out);
    exit;
}

$count_stmt = $pdo->prepare("SELECT COUNT(*) FROM activity_logs $where_sql");
$count_stmt->execute($params);
$total_logs = (int) $count_stmt->fetchColumn();

$archive_count_stmt = $pdo->query('SELECT COUNT(*) FROM activity_logs_archive');
$archive_logs_count = (int) $archive_count_stmt->fetchColumn();

$latest_archive_stmt = $pdo->query('SELECT batch_id, entry_count, created_at FROM activity_log_archive_batches ORDER BY id DESC LIMIT 1');
$latest_archive_batch = $latest_archive_stmt ? $latest_archive_stmt->fetch(PDO::FETCH_ASSOC) : null;

$stmt = $pdo->prepare("SELECT * FROM activity_logs $where_sql ORDER BY created_at DESC LIMIT $per_page OFFSET $offset");
$stmt->execute($params);
$logs = $stmt->fetchAll();

$users = $pdo->query('SELECT DISTINCT username FROM activity_logs ORDER BY username')->fetchAll(PDO::FETCH_COLUMN);
$actions = $pdo->query('SELECT DISTINCT action FROM activity_logs ORDER BY action')->fetchAll(PDO::FETCH_COLUMN);
$base_query = $_GET;

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
                    <a href="logs.php" class="inline-flex items-center bg-gray-200 hover:bg-gray-300 text-gray-700 px-3 py-1.5 rounded-lg text-xs font-semibold transition">
                        <i class="fas fa-list mr-2"></i>Show All Logs
                    </a>
                </div>

                <div class="mb-4 flex flex-wrap gap-2 items-center">
                    <span class="text-xs text-gray-500 font-semibold uppercase tracking-wider">Quick Filters</span>
                    <a href="<?php echo htmlspecialchars(build_logs_url($base_query, ['quick_filter' => 'today', 'page' => 1])); ?>" class="px-3 py-1 rounded-full text-xs font-semibold <?php echo $quick_filter === 'today' ? 'bg-blue-600 text-white' : 'bg-blue-50 text-blue-700 hover:bg-blue-100'; ?>">Today</a>
                    <a href="<?php echo htmlspecialchars(build_logs_url($base_query, ['quick_filter' => 'errors_only', 'page' => 1])); ?>" class="px-3 py-1 rounded-full text-xs font-semibold <?php echo $quick_filter === 'errors_only' ? 'bg-red-600 text-white' : 'bg-red-50 text-red-700 hover:bg-red-100'; ?>">Errors Only</a>
                    <a href="<?php echo htmlspecialchars(build_logs_url($base_query, ['quick_filter' => 'payment_events', 'page' => 1])); ?>" class="px-3 py-1 rounded-full text-xs font-semibold <?php echo $quick_filter === 'payment_events' ? 'bg-emerald-600 text-white' : 'bg-emerald-50 text-emerald-700 hover:bg-emerald-100'; ?>">Payment Events</a>
                    <a href="<?php echo htmlspecialchars(build_logs_url($base_query, ['quick_filter' => 'status_changes', 'page' => 1])); ?>" class="px-3 py-1 rounded-full text-xs font-semibold <?php echo $quick_filter === 'status_changes' ? 'bg-amber-600 text-white' : 'bg-amber-50 text-amber-700 hover:bg-amber-100'; ?>">Status Changes</a>
                    <a href="<?php echo htmlspecialchars(build_logs_url($base_query, ['quick_filter' => 'auth_events', 'page' => 1])); ?>" class="px-3 py-1 rounded-full text-xs font-semibold <?php echo $quick_filter === 'auth_events' ? 'bg-indigo-600 text-white' : 'bg-indigo-50 text-indigo-700 hover:bg-indigo-100'; ?>">Auth Events</a>
                    <a href="<?php echo htmlspecialchars(build_logs_url($base_query, ['quick_filter' => '', 'page' => 1])); ?>" class="px-3 py-1 rounded-full text-xs font-semibold bg-gray-100 text-gray-700 hover:bg-gray-200">Clear Quick Filter</a>
                </div>

                <form method="get" class="mb-4 flex flex-wrap gap-2 items-end">
                    <input type="hidden" name="quick_filter" value="<?php echo htmlspecialchars($quick_filter); ?>">
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

                    <select name="severity" class="bg-gray-100 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500 text-sm">
                        <option value="">All Severities</option>
                        <option value="info" <?php if ($severity_filter === 'info') echo 'selected'; ?>>Info</option>
                        <option value="warning" <?php if ($severity_filter === 'warning') echo 'selected'; ?>>Warning</option>
                        <option value="error" <?php if ($severity_filter === 'error') echo 'selected'; ?>>Error</option>
                        <option value="critical" <?php if ($severity_filter === 'critical') echo 'selected'; ?>>Critical</option>
                    </select>

                    <input type="date" name="date_from" value="<?php echo htmlspecialchars($date_from); ?>" class="bg-gray-100 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500 text-sm" />
                    <input type="date" name="date_to" value="<?php echo htmlspecialchars($date_to); ?>" class="bg-gray-100 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500 text-sm" />
                    <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg text-sm font-semibold">Filter</button>
                    <a href="logs.php" class="inline-flex items-center bg-gray-200 hover:bg-gray-300 text-gray-700 px-4 py-2 rounded-lg text-sm font-semibold ml-2 transition">
                        <i class="fas fa-undo mr-2"></i>Reset
                    </a>

                    <button type="submit" name="export" value="csv" class="ml-auto bg-green-600 hover:bg-green-700 text-white px-4 py-2 rounded-lg text-sm font-semibold flex items-center">
                        <i class="fas fa-file-csv mr-2"></i>Export CSV
                    </button>
                </form>

                <form method="get" class="mb-6 flex flex-wrap items-center gap-2 bg-gray-50 border border-gray-200 rounded-lg p-3">
                    <input type="hidden" name="export" value="csv">
                    <input type="hidden" name="quick_filter" value="<?php echo htmlspecialchars($quick_filter); ?>">
                    <input type="hidden" name="search" value="<?php echo htmlspecialchars($search); ?>">
                    <input type="hidden" name="user" value="<?php echo htmlspecialchars($user); ?>">
                    <input type="hidden" name="action" value="<?php echo htmlspecialchars($action); ?>">
                    <input type="hidden" name="severity" value="<?php echo htmlspecialchars($severity_filter); ?>">
                    <input type="hidden" name="date_from" value="<?php echo htmlspecialchars($date_from); ?>">
                    <input type="hidden" name="date_to" value="<?php echo htmlspecialchars($date_to); ?>">

                    <span class="text-xs font-semibold text-gray-600 uppercase tracking-wider">Export Presets</span>
                    <select name="export_preset" class="bg-white rounded-lg px-3 py-2 border border-gray-200 focus:outline-none focus:ring-2 focus:ring-green-500 text-sm">
                        <option value="">Choose report...</option>
                        <option value="daily_ops">Daily Ops</option>
                        <option value="security_review">Security Review</option>
                        <option value="payment_audit">Payment Audit</option>
                    </select>
                    <button type="submit" class="bg-green-600 hover:bg-green-700 text-white px-4 py-2 rounded-lg text-sm font-semibold inline-flex items-center">
                        <i class="fas fa-download mr-2"></i>Export Preset
                    </button>
                </form>

                <div class="mb-6 grid grid-cols-1 md:grid-cols-3 gap-3">
                    <div class="bg-slate-50 border border-slate-200 rounded-lg p-4">
                        <p class="text-xs uppercase tracking-wider text-slate-500 font-semibold">Hot Logs</p>
                        <p class="text-2xl font-black text-slate-800"><?php echo number_format($total_logs); ?></p>
                        <p class="text-xs text-slate-500 mt-1">Active logs in primary table</p>
                    </div>
                    <div class="bg-blue-50 border border-blue-200 rounded-lg p-4">
                        <p class="text-xs uppercase tracking-wider text-blue-600 font-semibold">Archived Logs</p>
                        <p class="text-2xl font-black text-blue-800"><?php echo number_format($archive_logs_count); ?></p>
                        <p class="text-xs text-blue-600 mt-1">Historical logs in archive table</p>
                    </div>
                    <div class="bg-amber-50 border border-amber-200 rounded-lg p-4">
                        <p class="text-xs uppercase tracking-wider text-amber-600 font-semibold">Last Archive Batch</p>
                        <p class="text-sm font-black text-amber-800"><?php echo htmlspecialchars($latest_archive_batch['batch_id'] ?? 'None'); ?></p>
                        <p class="text-xs text-amber-700 mt-1">
                            <?php if ($latest_archive_batch): ?>
                                <?php echo number_format((int) $latest_archive_batch['entry_count']); ?> rows on <?php echo htmlspecialchars($latest_archive_batch['created_at']); ?>
                            <?php else: ?>
                                No archive runs yet.
                            <?php endif; ?>
                        </p>
                    </div>
                </div>

                <form method="post" action="../partials/archive-logs.php" class="mb-6 flex flex-wrap items-end gap-3 bg-rose-50 border border-rose-200 rounded-lg p-4">
                    <?php echo csrf_field(); ?>
                    <div>
                        <label class="block text-xs font-semibold text-rose-700 uppercase tracking-wider mb-1">Retention Days</label>
                        <input type="number" name="keep_days" min="30" max="365" value="90" class="bg-white border border-rose-200 rounded-lg px-3 py-2 w-32 text-sm focus:outline-none focus:ring-2 focus:ring-rose-500">
                    </div>
                    <div class="text-xs text-rose-700 max-w-xl leading-relaxed">
                        Moves logs older than the retention window to archive and creates a tamper-evident archive batch hash.
                    </div>
                    <button type="submit" class="ml-auto bg-rose-600 hover:bg-rose-700 text-white px-4 py-2 rounded-lg text-sm font-semibold inline-flex items-center">
                        <i class="fas fa-box-archive mr-2"></i>Run Archive Now
                    </button>
                </form>

                <?php if (count($logs)): ?>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-100">
                            <tr>
                                <th class="px-4 py-2 text-left text-xs font-semibold text-gray-700 uppercase tracking-wider">Date/Time</th>
                                <th class="px-4 py-2 text-left text-xs font-semibold text-gray-700 uppercase tracking-wider">Severity</th>
                                <th class="px-4 py-2 text-left text-xs font-semibold text-gray-700 uppercase tracking-wider">User</th>
                                <th class="px-4 py-2 text-left text-xs font-semibold text-gray-700 uppercase tracking-wider">Action</th>
                                <th class="px-4 py-2 text-left text-xs font-semibold text-gray-700 uppercase tracking-wider">Target</th>
                                <th class="px-4 py-2 text-left text-xs font-semibold text-gray-700 uppercase tracking-wider">Trace</th>
                                <th class="px-4 py-2 text-left text-xs font-semibold text-gray-700 uppercase tracking-wider">Details</th>
                                <th class="px-4 py-2 text-left text-xs font-semibold text-gray-700 uppercase tracking-wider">Old Value</th>
                                <th class="px-4 py-2 text-left text-xs font-semibold text-gray-700 uppercase tracking-wider">New Value</th>
                                <th class="px-4 py-2 text-left text-xs font-semibold text-gray-700 uppercase tracking-wider">Inspect</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-100">
                            <?php foreach ($logs as $log): ?>
                                <?php
                                $severity = strtolower((string) ($log['severity'] ?? 'info'));
                                $severity_class = 'bg-slate-100 text-slate-700';
                                if ($severity === 'warning') $severity_class = 'bg-amber-100 text-amber-800';
                                if ($severity === 'error') $severity_class = 'bg-rose-100 text-rose-800';
                                if ($severity === 'critical') $severity_class = 'bg-red-200 text-red-900';
                                ?>
                                <tr class="hover:bg-gray-50">
                                    <td class="px-4 py-2 whitespace-nowrap text-sm text-gray-600"><?php echo htmlspecialchars($log['created_at']); ?></td>
                                    <td class="px-4 py-2 whitespace-nowrap text-xs font-semibold">
                                        <span class="px-2 py-1 rounded-full uppercase <?php echo $severity_class; ?>"><?php echo htmlspecialchars($severity); ?></span>
                                    </td>
                                    <td class="px-4 py-2 whitespace-nowrap text-sm text-gray-800 font-medium"><?php echo htmlspecialchars($log['username']); ?></td>
                                    <td class="px-4 py-2 whitespace-nowrap text-sm text-blue-700 font-semibold"><?php echo htmlspecialchars(ucfirst($log['action'])); ?></td>
                                    <td class="px-4 py-2 whitespace-nowrap text-sm text-gray-700"><?php echo htmlspecialchars($log['target_type']); ?><?php if ($log['target_id']) echo ' #' . htmlspecialchars($log['target_id']); ?></td>
                                    <td class="px-4 py-2 text-xs text-gray-500">
                                        <div>Req: <?php echo htmlspecialchars($log['request_id'] ?: '-'); ?></div>
                                        <div>Session: <?php echo htmlspecialchars($log['session_id'] ?: '-'); ?></div>
                                        <div>IP: <?php echo htmlspecialchars($log['ip_address'] ?: '-'); ?></div>
                                    </td>
                                    <td class="px-4 py-2 text-sm text-gray-600 max-w-sm">
                                        <div><?php echo htmlspecialchars($log['details']); ?></div>
                                        <div class="mt-1 text-xs text-gray-400 truncate" title="<?php echo htmlspecialchars($log['user_agent'] ?: ''); ?>"><?php echo htmlspecialchars($log['user_agent'] ?: ''); ?></div>
                                    </td>
                                    <td class="px-4 py-2 text-sm text-gray-500">
                                        <?php
                                        $old_val = (string) ($log['old_value'] ?? '');
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
                                        $new_val = (string) ($log['new_value'] ?? '');
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
                                    <td class="px-4 py-2 whitespace-nowrap text-sm">
                                        <?php
                                        $drawerPayload = [
                                            'id' => (int) ($log['id'] ?? 0),
                                            'created_at' => (string) ($log['created_at'] ?? ''),
                                            'severity' => (string) ($log['severity'] ?? 'info'),
                                            'username' => (string) ($log['username'] ?? ''),
                                            'action' => (string) ($log['action'] ?? ''),
                                            'target_type' => (string) ($log['target_type'] ?? ''),
                                            'target_id' => (string) ($log['target_id'] ?? ''),
                                            'request_id' => (string) ($log['request_id'] ?? ''),
                                            'session_id' => (string) ($log['session_id'] ?? ''),
                                            'ip_address' => (string) ($log['ip_address'] ?? ''),
                                            'user_agent' => (string) ($log['user_agent'] ?? ''),
                                            'details' => (string) ($log['details'] ?? ''),
                                            'old_value' => (string) ($log['old_value'] ?? ''),
                                            'new_value' => (string) ($log['new_value'] ?? ''),
                                            'prev_hash' => (string) ($log['prev_hash'] ?? ''),
                                            'log_hash' => (string) ($log['log_hash'] ?? ''),
                                        ];
                                        ?>
                                        <button
                                            type="button"
                                            class="open-details-drawer inline-flex items-center px-3 py-1.5 rounded-lg bg-indigo-50 text-indigo-700 hover:bg-indigo-100 text-xs font-semibold"
                                            data-log="<?php echo htmlspecialchars(json_encode($drawerPayload, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT), ENT_QUOTES, 'UTF-8'); ?>"
                                        >
                                            <i class="fas fa-up-right-and-down-left-from-center mr-1"></i>Details
                                        </button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <?php
                $total_pages = (int) ceil($total_logs / $per_page);
                if ($total_pages > 1): ?>
                <div class="mb-4 mt-4 flex justify-center space-x-1 items-center">
                    <a href="?<?php $q = $_GET; $q['page'] = 1; echo http_build_query($q); ?>"
                       class="px-3 py-1 rounded <?php echo $page == 1 ? 'bg-blue-600 text-white' : 'bg-gray-200 text-gray-700 hover:bg-blue-100'; ?> text-sm font-semibold mr-2">First</a>
                    <?php
                    $start = max(1, $page - 2);
                    $end = min($total_pages, $page + 2);
                    if ($start > 1) echo '<span class="px-2">...</span>';
                    for ($i = $start; $i <= $end; $i++): ?>
                        <a href="?<?php $q = $_GET; $q['page'] = $i; echo http_build_query($q); ?>"
                           class="px-3 py-1 rounded <?php echo $i == $page ? 'bg-blue-600 text-white' : 'bg-gray-200 text-gray-700 hover:bg-blue-100'; ?> text-sm font-semibold"><?php echo $i; ?></a>
                    <?php endfor;
                    if ($end < $total_pages) echo '<span class="px-2">...</span>';
                    ?>
                    <a href="?<?php $q = $_GET; $q['page'] = $total_pages; echo http_build_query($q); ?>"
                       class="px-3 py-1 rounded <?php echo $page == $total_pages ? 'bg-blue-600 text-white' : 'bg-gray-200 text-gray-700 hover:bg-blue-100'; ?> text-sm font-semibold ml-2">Last</a>
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

<div id="log-details-drawer" class="hidden fixed inset-0 z-50">
    <div id="log-details-overlay" class="absolute inset-0 bg-black bg-opacity-40"></div>
    <aside class="absolute right-0 top-0 h-full w-full sm:w-[620px] bg-white shadow-2xl border-l border-slate-200 flex flex-col">
        <div class="px-5 py-4 border-b border-slate-200 flex items-center justify-between">
            <h3 class="text-lg font-bold text-slate-800">Log Details</h3>
            <button type="button" id="close-log-drawer" class="text-slate-500 hover:text-slate-800">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <div class="p-5 overflow-y-auto space-y-4 text-sm">
            <div class="grid grid-cols-2 gap-2">
                <div><span class="text-slate-500">ID:</span> <span id="drawer-id" class="font-semibold"></span></div>
                <div><span class="text-slate-500">Time:</span> <span id="drawer-created-at" class="font-semibold"></span></div>
                <div><span class="text-slate-500">Severity:</span> <span id="drawer-severity" class="font-semibold"></span></div>
                <div><span class="text-slate-500">User:</span> <span id="drawer-username" class="font-semibold"></span></div>
                <div><span class="text-slate-500">Action:</span> <span id="drawer-action" class="font-semibold"></span></div>
                <div><span class="text-slate-500">Target:</span> <span id="drawer-target" class="font-semibold"></span></div>
            </div>

            <div class="bg-slate-50 border border-slate-200 rounded-lg p-3">
                <p class="text-xs uppercase tracking-wider text-slate-500 font-semibold mb-2">Traceability</p>
                <p><span class="text-slate-500">Request ID:</span> <span id="drawer-request-id" class="font-mono"></span></p>
                <p><span class="text-slate-500">Session ID:</span> <span id="drawer-session-id" class="font-mono"></span></p>
                <p><span class="text-slate-500">IP Address:</span> <span id="drawer-ip-address" class="font-mono"></span></p>
                <p><span class="text-slate-500">User Agent:</span> <span id="drawer-user-agent" class="break-all"></span></p>
            </div>

            <div class="bg-amber-50 border border-amber-200 rounded-lg p-3">
                <p class="text-xs uppercase tracking-wider text-amber-700 font-semibold mb-2">Tamper-Evidence Chain</p>
                <p><span class="text-amber-700">Previous Hash:</span> <span id="drawer-prev-hash" class="font-mono break-all"></span></p>
                <p><span class="text-amber-700">Current Hash:</span> <span id="drawer-log-hash" class="font-mono break-all"></span></p>
            </div>

            <div class="bg-white border border-slate-200 rounded-lg p-3">
                <p class="text-xs uppercase tracking-wider text-slate-500 font-semibold mb-2">Details</p>
                <pre id="drawer-details" class="whitespace-pre-wrap text-xs bg-slate-50 rounded p-2"></pre>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                <div class="border border-rose-200 rounded-lg p-3 bg-rose-50">
                    <p class="text-xs uppercase tracking-wider text-rose-700 font-semibold mb-2">Old Value</p>
                    <pre id="drawer-old" class="whitespace-pre-wrap text-xs bg-white rounded p-2 max-h-56 overflow-y-auto"></pre>
                </div>
                <div class="border border-emerald-200 rounded-lg p-3 bg-emerald-50">
                    <p class="text-xs uppercase tracking-wider text-emerald-700 font-semibold mb-2">New Value</p>
                    <pre id="drawer-new" class="whitespace-pre-wrap text-xs bg-white rounded p-2 max-h-56 overflow-y-auto"></pre>
                </div>
            </div>

            <div class="bg-indigo-50 border border-indigo-200 rounded-lg p-3">
                <p class="text-xs uppercase tracking-wider text-indigo-700 font-semibold mb-2">Simple Diff</p>
                <pre id="drawer-diff" class="whitespace-pre-wrap text-xs bg-white rounded p-2 max-h-80 overflow-y-auto"></pre>
            </div>
        </div>
    </aside>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    document.querySelectorAll('.view-all-link').forEach(function(link) {
        link.addEventListener('click', function(e) {
            e.preventDefault();
            var modalId = this.getAttribute('data-modal');
            var modal = document.getElementById(modalId);
            if (modal) {
                modal.classList.remove('hidden');
            }
        });
    });

    document.querySelectorAll('.close-modal').forEach(function(btn) {
        btn.addEventListener('click', function() {
            var modalId = this.getAttribute('data-modal');
            var modal = document.getElementById(modalId);
            if (modal) {
                modal.classList.add('hidden');
            }
        });
    });

    document.querySelectorAll('[id^="modal-"]').forEach(function(modal) {
        modal.addEventListener('click', function(e) {
            if (e.target === modal) {
                modal.classList.add('hidden');
            }
        });
    });

    function tryPrettyJson(value) {
        if (!value) return '';
        try {
            return JSON.stringify(JSON.parse(value), null, 2);
        } catch (e) {
            return value;
        }
    }

    function buildSimpleDiff(oldValue, newValue) {
        var oldLines = (oldValue || '').split(/\r?\n/);
        var newLines = (newValue || '').split(/\r?\n/);
        var max = Math.max(oldLines.length, newLines.length);
        var out = [];

        for (var i = 0; i < max; i++) {
            var o = oldLines[i] !== undefined ? oldLines[i] : '';
            var n = newLines[i] !== undefined ? newLines[i] : '';

            if (o === n) {
                out.push('  ' + o);
            } else {
                if (o !== '') out.push('- ' + o);
                if (n !== '') out.push('+ ' + n);
            }
        }

        return out.join('\n');
    }

    var drawer = document.getElementById('log-details-drawer');
    var overlay = document.getElementById('log-details-overlay');
    var closeDrawerBtn = document.getElementById('close-log-drawer');

    function closeDrawer() {
        if (drawer) drawer.classList.add('hidden');
    }

    function fillText(id, value) {
        var el = document.getElementById(id);
        if (!el) return;
        el.textContent = value || '-';
    }

    document.querySelectorAll('.open-details-drawer').forEach(function(btn) {
        btn.addEventListener('click', function() {
            var raw = this.getAttribute('data-log') || '{}';
            var data = {};
            try {
                data = JSON.parse(raw);
            } catch (e) {
                data = {};
            }

            fillText('drawer-id', data.id);
            fillText('drawer-created-at', data.created_at);
            fillText('drawer-severity', data.severity);
            fillText('drawer-username', data.username);
            fillText('drawer-action', data.action);
            fillText('drawer-target', (data.target_type || '-') + (data.target_id ? ' #' + data.target_id : ''));
            fillText('drawer-request-id', data.request_id);
            fillText('drawer-session-id', data.session_id);
            fillText('drawer-ip-address', data.ip_address);
            fillText('drawer-user-agent', data.user_agent);
            fillText('drawer-prev-hash', data.prev_hash);
            fillText('drawer-log-hash', data.log_hash);

            var detailsEl = document.getElementById('drawer-details');
            var oldEl = document.getElementById('drawer-old');
            var newEl = document.getElementById('drawer-new');
            var diffEl = document.getElementById('drawer-diff');

            var detailsPretty = tryPrettyJson(data.details || '');
            var oldPretty = tryPrettyJson(data.old_value || '');
            var newPretty = tryPrettyJson(data.new_value || '');

            if (detailsEl) detailsEl.textContent = detailsPretty || '-';
            if (oldEl) oldEl.textContent = oldPretty || '-';
            if (newEl) newEl.textContent = newPretty || '-';
            if (diffEl) diffEl.textContent = buildSimpleDiff(oldPretty, newPretty) || 'No differences';

            if (drawer) drawer.classList.remove('hidden');
        });
    });

    if (overlay) overlay.addEventListener('click', closeDrawer);
    if (closeDrawerBtn) closeDrawerBtn.addEventListener('click', closeDrawer);
});
</script>
</body>
</html>
