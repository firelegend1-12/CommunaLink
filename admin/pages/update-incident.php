<?php
require_once '../../config/init.php';
require_once '../../includes/auth.php';
require_once '../../includes/functions.php';
require_once '../../includes/permission_checker.php';

require_login();

// Check manage_incidents permission (admin, barangay-captain, kagawad, barangay-tanod)
if (!require_permission('manage_incidents')) {
    if (isset($_SESSION['role']) && $_SESSION['role'] === 'resident') {
        redirect_to('../../resident/dashboard.php');
    } else {
        redirect_to('../index.php');
    }
}

$page_title = "Update Incident Status";
$report_id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
$report = null;
$error_message = '';
$success_message = '';

if (!$report_id) {
    $_SESSION['error_message'] = "Invalid report ID.";
    redirect_to('incident-reports.php');
}

// Fetch report details
try {
    $stmt = $pdo->prepare("SELECT i.*, u.fullname FROM incidents i JOIN users u ON i.resident_user_id = u.id WHERE i.id = ?");
    $stmt->execute([$report_id]);
    $report = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$report) {
        $_SESSION['error_message'] = "Report not found.";
        redirect_to('incident-reports.php');
    }
} catch (PDOException $e) {
    $_SESSION['error_message'] = "Database error fetching report.";
    redirect_to('incident-reports.php');
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $new_status = sanitize_input($_POST['status']);
    $allowed_statuses = ['Pending', 'In Progress', 'Resolved', 'Rejected'];

    if (in_array($new_status, $allowed_statuses)) {
        try {
            // Fetch old status for logging
            $stmt = $pdo->prepare("SELECT status FROM incidents WHERE id = ?");
            $stmt->execute([$report_id]);
            $old_data = $stmt->fetch(PDO::FETCH_ASSOC);
            $old_status = $old_data ? $old_data['status'] : null;

            $stmt = $pdo->prepare("UPDATE incidents SET status = ? WHERE id = ?");
            $stmt->execute([$new_status, $report_id]);

            // Log only if status changed
            if ($old_status !== $new_status) {
                $changed_old = [ 'status' => $old_status ];
                $changed_new = [ 'status' => $new_status ];
                $old_str = '';
                $new_str = '';
                foreach ($changed_old as $k => $v) $old_str .= "$k: $v\n";
                foreach ($changed_new as $k => $v) $new_str .= "$k: $v\n";
                log_activity_db(
                    $pdo,
                    'edit',
                    'incident',
                    $report_id,
                    "Incident status updated for Incident #{$report_id}.",
                    trim($old_str),
                    trim($new_str)
                );
            }

            $_SESSION['success_message'] = "Report status updated successfully!";
            redirect_to('incident-reports.php');
        } catch (PDOException $e) {
            $error_message = "Failed to update status due to a database error.";
        }
    } else {
        $error_message = "Invalid status selected.";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($page_title) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
</head>
<body class="bg-gray-100 min-h-screen">
    <div class="flex h-screen overflow-hidden">
        <?php include '../partials/sidebar.php'; ?>
        <div class="flex flex-col flex-1 overflow-hidden">
            <header class="bg-white shadow-sm z-10">
                <div class="px-4 sm:px-6 lg:px-8">
                    <div class="flex items-center justify-between h-16">
                        <h1 class="text-2xl font-semibold text-gray-800"><?= htmlspecialchars($page_title) ?></h1>
                        
                        <!-- User Dropdown -->
                        <div x-data="{ open: false }" class="relative">
                            <button @click="open = !open" class="flex items-center space-x-2 text-sm text-gray-600 hover:text-gray-900 focus:outline-none">
                                <span><?php echo htmlspecialchars($_SESSION['fullname']); ?></span>
                                <div class="h-8 w-8 rounded-full bg-blue-600 flex items-center justify-center text-white text-sm font-bold ring-2 ring-white">
                                    <?php echo substr($_SESSION['fullname'], 0, 1); ?>
                                </div>
                            </button>
                            <div x-show="open" @click.away="open = false" x-cloak
                                 x-transition:enter="transition ease-out duration-100"
                                 x-transition:enter-start="transform opacity-0 scale-95"
                                 x-transition:enter-end="transform opacity-100 scale-100"
                                 x-transition:leave="transition ease-in duration-75"
                                 x-transition:leave-start="transform opacity-100 scale-100"
                                 x-transition:leave-end="transform opacity-0 scale-95"
                                 class="origin-top-right absolute right-0 mt-2 w-48 rounded-md shadow-lg bg-white ring-1 ring-black ring-opacity-5 focus:outline-none z-20 divide-y divide-gray-200">
                                
                                <div class="py-1">
                                    <a href="account.php" class="flex items-center px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">
                                        <i class="fas fa-user-circle mr-2 text-gray-500"></i>
                                        My Account
                                    </a>
                                </div>
                                <div class="py-1">
                                    <a href="../../includes/logout.php" class="flex items-center px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">
                                        <i class="fas fa-sign-out-alt mr-2 text-gray-500"></i>
                                        Sign Out
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </header>
            <main class="flex-1 overflow-y-auto bg-gray-50 p-4 sm:p-6 lg:p-8">
                <?php if ($error_message): ?>
                    <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-4" role="alert">
                        <span class="block sm:inline"><?= htmlspecialchars($error_message) ?></span>
                    </div>
                <?php endif; ?>
                
                <div class="bg-white rounded-lg shadow">
                    <div class="px-6 py-4 border-b border-gray-200">
                        <h2 class="text-lg font-medium text-gray-900">Incident Report Details</h2>
                    </div>
                    
                    <div class="p-6">
                        <form method="POST" action="update-incident.php?id=<?= $report_id ?>">
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
                                <div>
                                    <p class="text-sm font-medium text-gray-700 mb-1">Report ID:</p>
                                    <p class="text-sm text-gray-900 font-semibold">#<?= htmlspecialchars($report['id']) ?></p>
                                </div>
                                <div>
                                    <p class="text-sm font-medium text-gray-700 mb-1">Reported By:</p>
                                    <p class="text-sm text-gray-900"><?= htmlspecialchars($report['fullname']) ?></p>
                                </div>
                                <div>
                                    <p class="text-sm font-medium text-gray-700 mb-1">Type:</p>
                                    <p class="text-sm text-gray-900"><?= htmlspecialchars($report['type']) ?></p>
                                </div>
                                <div>
                                    <p class="text-sm font-medium text-gray-700 mb-1">Location:</p>
                                    <p class="text-sm text-gray-900"><?= htmlspecialchars($report['location']) ?></p>
                                </div>
                            </div>
                            
                            <div class="mb-6">
                                <p class="text-sm font-medium text-gray-700 mb-1">Description:</p>
                                <div class="bg-gray-50 p-4 rounded-md border border-gray-200">
                                    <p class="text-sm text-gray-900"><?= htmlspecialchars($report['description']) ?></p>
                                </div>
                            </div>
                            
                            <div class="mb-6">
                                <label for="status" class="block text-sm font-medium text-gray-700 mb-2">Update Status</label>
                                <select id="status" name="status" class="mt-1 block w-full pl-3 pr-10 py-2 text-base border border-gray-300 focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm rounded-md">
                                    <option value="Pending" <?= $report['status'] === 'Pending' ? 'selected' : '' ?>>Pending</option>
                                    <option value="In Progress" <?= $report['status'] === 'In Progress' ? 'selected' : '' ?>>In Progress</option>
                                    <option value="Resolved" <?= $report['status'] === 'Resolved' ? 'selected' : '' ?>>Resolved</option>
                                    <option value="Rejected" <?= $report['status'] === 'Rejected' ? 'selected' : '' ?>>Rejected</option>
                                </select>
                            </div>
                            
                            <div class="flex justify-end space-x-4 pt-4 border-t">
                                <a href="incident-reports.php" class="bg-gray-200 hover:bg-gray-300 text-gray-800 px-4 py-2 rounded-md">Cancel</a>
                                <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-md">Save Changes</button>
                            </div>
                        </form>
                    </div>
                </div>
            </main>
        </div>
    </div>
</body>
</html> 