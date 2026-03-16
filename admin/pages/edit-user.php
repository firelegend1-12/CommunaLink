<?php
require_once '../../config/init.php';
require_once '../../includes/functions.php';
require_once '../../includes/auth.php';

// Check if user is logged in and is admin
require_login();
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    redirect_to('../index.php');
}

$user_id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
$user = null;
$error_message = '';
$success_message = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_validate()) {
        $error_message = "Invalid security token. Please refresh the page and try again.";
    } else {
        $user_id = filter_input(INPUT_POST, 'user_id', FILTER_VALIDATE_INT);
        $username = sanitize_input($_POST['username']);
        $fullname = sanitize_input($_POST['fullname']);
        $email = sanitize_input($_POST['email']);
        $role = sanitize_input($_POST['role']);
        $official_position = sanitize_input($_POST['official_position'] ?? '');
        $new_password = $_POST['new_password'];

        // Process role selection
        if ($role === 'official') {
            if (empty($official_position)) {
                $error_message = "Please select an official position.";
            } else {
                $final_role = $official_position;
            }
        } else {
            $final_role = $role;
        }

        if (!$user_id) {
            $error_message = "Invalid user ID.";
        } else {
            try {
                // Get current user data
                $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
                $stmt->execute([$user_id]);
                $current_user = $stmt->fetch();
                
                if (!$current_user) {
                    $error_message = "User not found.";
                } else {
                    // Check if username is already taken by another user
                    $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ? AND id != ?");
                    $stmt->execute([$username, $user_id]);
                    if ($stmt->rowCount() > 0) {
                        $error_message = "Username already taken by another user.";
                    } else {
                        // Check if email is already taken by another user
                        $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
                        $stmt->execute([$email, $user_id]);
                        if ($stmt->rowCount() > 0) {
                            $error_message = "Email already taken by another user.";
                        } else {
                            // Prepare changes array for logging
                            $changes = [];
                            if ($current_user['username'] !== $username) $changes[] = "username";
                            if ($current_user['fullname'] !== $fullname) $changes[] = "fullname";
                            if ($current_user['email'] !== $email) $changes[] = "email";
                            if ($current_user['role'] !== $final_role) $changes[] = "role";
                            if (!empty($new_password)) $changes[] = "password";

                            // Check if there are actual changes to make
                            if (empty($changes) && empty($new_password)) {
                                // No changes needed, consider this a success
                                $update_success = true;
                            } else {
                                // Changes detected, perform the update
                                try {
                                    if (!empty($new_password)) {
                                        $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
                                        $stmt = $pdo->prepare("UPDATE users SET username = ?, fullname = ?, email = ?, password = ?, role = ? WHERE id = ?");
                                        $stmt->execute([$username, $fullname, $email, $hashed_password, $final_role, $user_id]);
                                    } else {
                                        $stmt = $pdo->prepare("UPDATE users SET username = ?, fullname = ?, email = ?, role = ? WHERE id = ?");
                                        $stmt->execute([$username, $fullname, $email, $final_role, $user_id]);
                                    }
                                    $update_success = true;
                                } catch (PDOException $e) {
                                    $error_message = "Database error: " . $e->getMessage();
                                    $update_success = false;
                                }
                            }
                            
                            if ($update_success) {
                                if (!empty($changes)) {
                                    $changes_text = implode(", ", $changes);
                                    log_activity_db($pdo, 'edit', 'user', $user_id, "User updated: {$changes_text}");
                                }
                                $_SESSION['success_message'] = !empty($changes) ? "User updated successfully." : "No changes detected.";
                                header('Location: user-management.php');
                                exit;
                            } else {
                                $error_message = "Failed to update user. Please try again.";
                            }
                        }
                    }
                }
            } catch (PDOException $e) {
                $error_message = "Database error: " . $e->getMessage();
            }
        }
    }
}

// Fetch user data
if (!$user && $user_id) {
    try {
        $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
        $stmt->execute([$user_id]);
        $user = $stmt->fetch();
        
        if (!$user) {
            $error_message = "User not found.";
        }
    } catch (PDOException $e) {
        $error_message = "Database error occurred while fetching user.";
    }
}

$page_title = "Edit User - CommuniLink";
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
                        <div class="flex items-center">
                            <a href="user-management.php" class="text-gray-500 hover:text-gray-700 mr-4">
                                <i class="fas fa-arrow-left"></i>
                            </a>
                            <h1 class="text-2xl font-semibold text-gray-800">Edit User</h1>
                        </div>
                        
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
            
            <main class="flex-1 overflow-y-auto bg-gray-50 p-4 sm:p-6 lg:p-8">
                <?php if ($error_message): ?>
                    <?php echo display_error($error_message); ?>
                <?php endif; ?>
                
                <?php if ($success_message): ?>
                    <div class="bg-green-100 border-l-4 border-green-500 text-green-700 p-4 mb-4">
                        <p><?php echo htmlspecialchars($success_message); ?></p>
                    </div>
                <?php endif; ?>
                
                <?php if ($user): ?>
                    <div class="max-w-2xl mx-auto">
                        <div class="bg-white rounded-lg shadow p-6">
                            <form method="POST" action="edit-user.php">
                                <?php echo csrf_field(); ?>
                                <input type="hidden" name="user_id" value="<?php echo htmlspecialchars($user['id']); ?>">
                                
                                <div class="space-y-6">
                                    <div>
                                        <h3 class="text-lg font-medium text-gray-900 mb-4">User Information</h3>
                                        
                                        <div class="mb-4">
                                            <label for="username" class="block text-sm font-medium text-gray-700 mb-2">Username</label>
                                            <input type="text" id="username" name="username" 
                                                   value="<?php echo htmlspecialchars($user['username']); ?>" 
                                                   class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500" 
                                                   required>
                                        </div>
                                        
                                        <div class="mb-4">
                                            <label for="fullname" class="block text-sm font-medium text-gray-700 mb-2">Full Name</label>
                                            <input type="text" id="fullname" name="fullname" 
                                                   value="<?php echo htmlspecialchars($user['fullname']); ?>" 
                                                   class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500" 
                                                   required>
                                        </div>
                                        
                                        <div class="mb-4">
                                            <label for="email" class="block text-sm font-medium text-gray-700 mb-2">Email</label>
                                            <input type="email" id="email" name="email" 
                                                   value="<?php echo htmlspecialchars($user['email']); ?>" 
                                                   class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500" 
                                                   required>
                                        </div>
                                        
                                        <div class="mb-4">
                                            <label for="role" class="block text-sm font-medium text-gray-700 mb-2">Role</label>
                                            <select id="role" name="role" 
                                                    class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500" 
                                                    required onchange="toggleOfficialPosition()">
                                                <option value="">Select a role</option>
                                                <option value="resident" <?php echo $user['role'] === 'resident' ? 'selected' : ''; ?>>Resident</option>
                                                <option value="admin" <?php echo $user['role'] === 'admin' ? 'selected' : ''; ?>>Admin</option>
                                                <option value="official" <?php echo in_array($user['role'], ['barangay-captain', 'kagawad', 'barangay-secretary', 'barangay-treasurer', 'barangay-tanod']) ? 'selected' : ''; ?>>Official</option>
                                            </select>
                                            
                                            <!-- Role Color Legend -->
                                            <div class="mt-2 flex flex-wrap gap-2">
                                                <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-blue-100 text-blue-800 border border-blue-200">
                                                    <span class="w-2 h-2 bg-blue-500 rounded-full mr-1"></span>
                                                    Resident
                                                </span>
                                                <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-red-100 text-red-800 border border-red-200">
                                                    <span class="w-2 h-2 bg-red-500 rounded-full mr-1"></span>
                                                    Admin
                                                </span>
                                                <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-purple-100 text-purple-800 border border-purple-200" style="background-color: #f3e8ff; color: #6b21a8; border-color: #c4b5fd;">
                                                    <span class="w-2 h-2 bg-purple-500 rounded-full mr-1" style="background-color: #8b5cf6;"></span>
                                                    Official
                                                </span>
                                            </div>
                                        </div>
                                        
                                        <div id="officialPositionDiv" class="mb-4 <?php echo in_array($user['role'], ['barangay-captain', 'kagawad', 'barangay-secretary', 'barangay-treasurer', 'barangay-tanod']) ? '' : 'hidden'; ?>">
                                            <label for="official_position" class="block text-sm font-medium text-gray-700 mb-2">Official Position</label>
                                            <select id="official_position" name="official_position" 
                                                    class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                                                <option value="">Select official position</option>
                                                <option value="barangay-captain" <?php echo $user['role'] === 'barangay-captain' ? 'selected' : ''; ?>>Barangay Captain</option>
                                                <option value="kagawad" <?php echo $user['role'] === 'kagawad' ? 'selected' : ''; ?>>Kagawad</option>
                                                <option value="barangay-secretary" <?php echo $user['role'] === 'barangay-secretary' ? 'selected' : ''; ?>>Barangay Secretary</option>
                                                <option value="barangay-treasurer" <?php echo $user['role'] === 'barangay-treasurer' ? 'selected' : ''; ?>>Barangay Treasurer</option>
                                                <option value="barangay-tanod" <?php echo $user['role'] === 'barangay-tanod' ? 'selected' : ''; ?>>Barangay Tanod</option>
                                            </select>
                                        </div>
                                        
                                        <div class="mb-4">
                                            <label for="new_password" class="block text-sm font-medium text-gray-700 mb-2">New Password (leave blank to keep current)</label>
                                            <input type="password" id="new_password" name="new_password" 
                                                   class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500" 
                                                   minlength="8">
                                            <p class="text-xs text-gray-500 mt-1">Minimum 8 characters</p>
                                        </div>
                                    </div>
                                    
                                    <div>
                                        <h3 class="text-lg font-medium text-gray-900 mb-4">Account Information</h3>
                                        
                                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                            <div>
                                                <label class="block text-sm font-medium text-gray-700 mb-2">Account Created</label>
                                                <input type="text" value="<?php echo date('M d, Y h:i A', strtotime($user['created_at'])); ?>" 
                                                       class="w-full px-3 py-2 border border-gray-300 rounded-md bg-gray-50 text-gray-500" 
                                                       readonly>
                                            </div>
                                            
                                            <div>
                                                <label class="block text-sm font-medium text-gray-700 mb-2">Last Login</label>
                                                <input type="text" value="<?php echo $user['last_login'] ? date('M d, Y h:i A', strtotime($user['last_login'])) : 'Never'; ?>" 
                                                       class="w-full px-3 py-2 border border-gray-300 rounded-md bg-gray-50 text-gray-500" 
                                                       readonly>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="flex justify-end space-x-3 mt-8 pt-6 border-t border-gray-200">
                                    <a href="user-management.php" 
                                       class="px-4 py-2 border border-gray-300 rounded-md text-sm font-medium text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                                        Cancel
                                    </a>
                                    <button type="submit" 
                                            class="px-4 py-2 bg-blue-600 border border-transparent rounded-md text-sm font-medium text-white hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                                        Update User
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                <?php else: ?>
                    <div class="max-w-2xl mx-auto">
                        <div class="bg-white rounded-lg shadow p-6 text-center">
                            <i class="fas fa-exclamation-triangle text-red-500 text-4xl mb-4"></i>
                            <h3 class="text-lg font-medium text-gray-900 mb-2">User Not Found</h3>
                            <p class="text-gray-500 mb-4">The user you're looking for doesn't exist or has been deleted.</p>
                            <a href="user-management.php" 
                               class="inline-flex items-center px-4 py-2 bg-blue-600 border border-transparent rounded-md text-sm font-medium text-white hover:bg-blue-700">
                                <i class="fas fa-arrow-left mr-2"></i>
                                Back to User Management
                            </a>
                        </div>
                    </div>
                <?php endif; ?>
            </main>
        </div>
    </div>

    <script>
        function toggleOfficialPosition() {
            const roleSelect = document.getElementById('role');
            const officialPositionDiv = document.getElementById('officialPositionDiv');
            const officialPositionSelect = document.getElementById('official_position');
            
            if (roleSelect.value === 'official') {
                officialPositionDiv.classList.remove('hidden');
                officialPositionSelect.required = true;
            } else {
                officialPositionDiv.classList.add('hidden');
                officialPositionSelect.required = false;
                officialPositionSelect.value = '';
            }
        }

        document.addEventListener('DOMContentLoaded', function() {
            toggleOfficialPosition();
        });
    </script>
</body>
</html> 