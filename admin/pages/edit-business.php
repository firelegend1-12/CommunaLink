<?php
/**
 * Edit Business Page
 */

require_once '../../config/init.php';
require_once '../../includes/functions.php';
require_once '../../includes/auth.php';

require_login();

$page_title = "Edit Business - CommuniLink";

// Get business ID from URL
$business_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($business_id <= 0) {
    $_SESSION['error_message'] = "Invalid business ID.";
    redirect_to('business-records.php');
}

try {
    // Fetch business details
    $stmt = $pdo->prepare("
        SELECT b.*, r.first_name, r.last_name, r.middle_initial, r.id_number 
        FROM businesses b
        JOIN residents r ON b.resident_id = r.id
        WHERE b.id = ?
    ");
    $stmt->execute([$business_id]);
    $business = $stmt->fetch();

    if (!$business) {
        $_SESSION['error_message'] = "Business not found.";
        redirect_to('business-records.php');
    }

} catch (PDOException $e) {
    $_SESSION['error_message'] = "Database error occurred.";
    redirect_to('business-records.php');
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Get old values for comparison
        $old_business_name = $business['business_name'];
        $old_business_type = $business['business_type'];
        $old_address = $business['address'];
        $old_status = $business['status'];

        // Get new values
        $new_business_name = sanitize_input($_POST['business_name']);
        $new_business_type = sanitize_input($_POST['business_type']);
        $new_address = sanitize_input($_POST['address']);
        $new_status = sanitize_input($_POST['status']);

        // Validate required fields
        if (empty($new_business_name) || empty($new_business_type) || empty($new_address)) {
            throw new Exception("All fields are required.");
        }

        // Validate status
        $allowed_statuses = ['Active', 'Inactive', 'Pending'];
        if (!in_array($new_status, $allowed_statuses)) {
            throw new Exception("Invalid status value.");
        }

        // Prepare changes array for logging
        $changes = [];
        
        if ($old_business_name !== $new_business_name) {
            $changes[] = "business_name: {$old_business_name} → {$new_business_name}";
        }
        if ($old_business_type !== $new_business_type) {
            $changes[] = "business_type: {$old_business_type} → {$new_business_type}";
        }
        if ($old_address !== $new_address) {
            $changes[] = "address: {$old_address} → {$new_address}";
        }
        if ($old_status !== $new_status) {
            $changes[] = "status: {$old_status} → {$new_status}";
        }

        // Update business record
        $update_stmt = $pdo->prepare("
            UPDATE businesses 
            SET business_name = ?, business_type = ?, address = ?, status = ?, updated_at = CURRENT_TIMESTAMP 
            WHERE id = ?
        ");
        $result = $update_stmt->execute([
            $new_business_name, $new_business_type, $new_address, $new_status, $business_id
        ]);

        if (!$result) {
            throw new Exception("Failed to update business record.");
        }

        // Log changes if any were made
        if (!empty($changes)) {
            $changes_text = implode(", ", $changes);
            log_activity_db(
                $pdo,
                'edit',
                'business',
                $business_id,
                "Business: {$new_business_name} - {$changes_text}",
                implode(", ", [
                    "business_name: {$old_business_name}",
                    "business_type: {$old_business_type}",
                    "address: {$old_address}",
                    "status: {$old_status}"
                ]),
                implode(", ", [
                    "business_name: {$new_business_name}",
                    "business_type: {$new_business_type}",
                    "address: {$new_address}",
                    "status: {$new_status}"
                ])
            );
        }

        $_SESSION['success_message'] = "Business updated successfully.";
        redirect_to('business-records.php');

    } catch (Exception $e) {
        $_SESSION['error_message'] = $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?></title>
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
                        <h1 class="text-2xl font-semibold text-gray-800">Edit Business</h1>
                        <a href="business-records.php" class="text-blue-600 hover:text-blue-800">
                            <i class="fas fa-arrow-left mr-2"></i>Back to Business Records
                        </a>
                    </div>
                </div>
            </header>
            
            <main class="flex-1 overflow-y-auto bg-gray-50 p-4 sm:p-6 lg:p-8">
                <div class="max-w-2xl mx-auto">
                    <div class="bg-white rounded-lg shadow p-6">
                        <h2 class="text-lg font-medium text-gray-900 mb-6">Edit Business Information</h2>
                        
                        <?php display_flash_messages(); ?>
                        
                        <form method="POST" class="space-y-6">
                            <!-- Business Name -->
                            <div>
                                <label for="business_name" class="block text-sm font-medium text-gray-700">Business Name</label>
                                <input type="text" name="business_name" id="business_name" 
                                       value="<?php echo htmlspecialchars($business['business_name']); ?>"
                                       class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 sm:text-sm"
                                       required>
                            </div>

                            <!-- Business Type -->
                            <div>
                                <label for="business_type" class="block text-sm font-medium text-gray-700">Business Type</label>
                                <input type="text" name="business_type" id="business_type" 
                                       value="<?php echo htmlspecialchars($business['business_type']); ?>"
                                       class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 sm:text-sm"
                                       required>
                            </div>

                            <!-- Address -->
                            <div>
                                <label for="address" class="block text-sm font-medium text-gray-700">Address</label>
                                <textarea name="address" id="address" rows="3"
                                          class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 sm:text-sm"
                                          required><?php echo htmlspecialchars($business['address']); ?></textarea>
                            </div>

                            <!-- Status -->
                            <div>
                                <label for="status" class="block text-sm font-medium text-gray-700">Status</label>
                                <select name="status" id="status" 
                                        class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 sm:text-sm">
                                    <option value="Active" <?php echo $business['status'] === 'Active' ? 'selected' : ''; ?>>Active</option>
                                    <option value="Inactive" <?php echo $business['status'] === 'Inactive' ? 'selected' : ''; ?>>Inactive</option>
                                    <option value="Pending" <?php echo $business['status'] === 'Pending' ? 'selected' : ''; ?>>Pending</option>
                                </select>
                            </div>

                            <!-- Owner Information (Read-only) -->
                            <div class="bg-gray-50 p-4 rounded-md">
                                <h3 class="text-sm font-medium text-gray-700 mb-2">Owner Information</h3>
                                <div class="grid grid-cols-2 gap-4 text-sm">
                                    <div>
                                        <span class="text-gray-500">Name:</span>
                                        <span class="ml-2 text-gray-900">
                                            <?php echo htmlspecialchars($business['last_name'] . ', ' . $business['first_name'] . ' ' . $business['middle_initial']); ?>
                                        </span>
                                    </div>
                                    <div>
                                        <span class="text-gray-500">ID Number:</span>
                                        <span class="ml-2 text-gray-900"><?php echo htmlspecialchars($business['id_number']); ?></span>
                                    </div>
                                </div>
                            </div>

                            <!-- Action Buttons -->
                            <div class="flex justify-end space-x-3 pt-6 border-t border-gray-200">
                                <a href="business-records.php" 
                                   class="bg-gray-300 hover:bg-gray-400 text-gray-800 px-4 py-2 rounded-md text-sm font-medium transition duration-300">
                                    Cancel
                                </a>
                                <button type="submit" 
                                        class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-md text-sm font-medium transition duration-300">
                                    Update Business
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </main>
        </div>
    </div>
</body>
</html> 