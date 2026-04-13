<?php
/**
 * Edit Resident Page
 */

// Include authentication system
require_once '../../config/init.php';
require_once '../../includes/functions.php';
require_once '../../includes/auth.php';

// Check if user is logged in
require_login();

$resident_id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
$resident = null;
$error_message = '';
$success_message = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $resident_id = filter_input(INPUT_POST, 'resident_id', FILTER_VALIDATE_INT);
    $first_name = sanitize_input($_POST['first_name']);
    $middle_initial = sanitize_input($_POST['middle_initial']);
    $last_name = sanitize_input($_POST['last_name']);
    $gender = sanitize_input($_POST['gender']);
    $date_of_birth = sanitize_input($_POST['date_of_birth']);
    $place_of_birth = sanitize_input($_POST['place_of_birth']);
    $religion = sanitize_input($_POST['religion']);
    $citizenship = sanitize_input($_POST['citizenship']);
    $email = sanitize_input($_POST['email']);
    $contact_no = sanitize_input($_POST['contact_no']);
    $address = sanitize_input($_POST['address']);
    $civil_status = sanitize_input($_POST['civil_status']);
    $occupation = sanitize_input($_POST['occupation']);
    $id_number = sanitize_input($_POST['id_number']);
    $voter_status = sanitize_input($_POST['voter_status']);
    
    if (!$resident_id) {
        $error_message = "Invalid resident ID.";
    } else {
        try {
            // Check if ID number is already taken by another resident
            $stmt = $pdo->prepare("SELECT id FROM residents WHERE id_number = ? AND id != ?");
            $stmt->execute([$id_number, $resident_id]);
            if ($stmt->rowCount() > 0) {
                $error_message = "ID number is already taken by another resident.";
            } else {
                // Get current resident data for logging
                $stmt = $pdo->prepare("SELECT * FROM residents WHERE id = ?");
                $stmt->execute([$resident_id]);
                $old_resident_data = $stmt->fetch();
                
                // Calculate age from date of birth
                $birth_date = new DateTime($date_of_birth);
                $today = new DateTime();
                $age = $today->diff($birth_date)->y;
                
                // Update resident information
                $stmt = $pdo->prepare("UPDATE residents SET 
                    first_name = ?, middle_initial = ?, last_name = ?, gender = ?, 
                    date_of_birth = ?, place_of_birth = ?, age = ?, religion = ?, 
                    citizenship = ?, email = ?, contact_no = ?, address = ?, 
                    civil_status = ?, occupation = ?, id_number = ?, voter_status = ?,
                    updated_at = NOW()
                    WHERE id = ?");
                
                $stmt->execute([
                    $first_name, $middle_initial, $last_name, $gender,
                    $date_of_birth, $place_of_birth, $age, $religion,
                    $citizenship, $email, $contact_no, $address,
                    $civil_status, $occupation, $id_number, $voter_status,
                    $resident_id
                ]);
                
                if ($stmt->rowCount() > 0) {
                    // Log the edit activity
                    $current_date = date('Y-m-d H:i:s');
                    $resident_name = $first_name . ' ' . $last_name;
                    
                    // Prepare old and new values for logging
                    $old_values = [
                        'first_name' => $old_resident_data['first_name'],
                        'middle_initial' => $old_resident_data['middle_initial'],
                        'last_name' => $old_resident_data['last_name'],
                        'gender' => $old_resident_data['gender'],
                        'date_of_birth' => $old_resident_data['date_of_birth'],
                        'place_of_birth' => $old_resident_data['place_of_birth'],
                        'age' => $old_resident_data['age'],
                        'religion' => $old_resident_data['religion'],
                        'citizenship' => $old_resident_data['citizenship'],
                        'email' => $old_resident_data['email'],
                        'contact_no' => $old_resident_data['contact_no'],
                        'address' => $old_resident_data['address'],
                        'civil_status' => $old_resident_data['civil_status'],
                        'occupation' => $old_resident_data['occupation'],
                        'id_number' => $old_resident_data['id_number'],
                        'voter_status' => $old_resident_data['voter_status']
                    ];
                    
                    $new_values = [
                        'first_name' => $first_name,
                        'middle_initial' => $middle_initial,
                        'last_name' => $last_name,
                        'gender' => $gender,
                        'date_of_birth' => $date_of_birth,
                        'place_of_birth' => $place_of_birth,
                        'age' => $age,
                        'religion' => $religion,
                        'citizenship' => $citizenship,
                        'email' => $email,
                        'contact_no' => $contact_no,
                        'address' => $address,
                        'civil_status' => $civil_status,
                        'occupation' => $occupation,
                        'id_number' => $id_number,
                        'voter_status' => $voter_status
                    ];
                    // Only log changed fields
                    $changed_old = [];
                    $changed_new = [];
                    foreach ($old_values as $key => $old_val) {
                        $new_val = $new_values[$key];
                        if ($old_val != $new_val) {
                            $changed_old[$key] = $old_val;
                            $changed_new[$key] = $new_val;
                        }
                    }
                    $old_str = '';
                    $new_str = '';
                    foreach ($changed_old as $k => $v) $old_str .= "$k: $v\n";
                    foreach ($changed_new as $k => $v) $new_str .= "$k: $v\n";

                    log_activity_db(
                        $pdo,
                        'edit',
                        'resident',
                        $resident_id,
                        "Resident {$resident_name} (ID: {$id_number}) updated on {$current_date}.",
                        trim($old_str),
                        trim($new_str)
                    );
                    
                    $success_message = "Resident information updated successfully.";
                    // Refresh resident data
                    $stmt = $pdo->prepare("SELECT * FROM residents WHERE id = ?");
                    $stmt->execute([$resident_id]);
                    $resident = $stmt->fetch();
                } else {
                    $error_message = "No changes were made.";
                }
            }
        } catch (PDOException $e) {
            $error_message = "Database error occurred while updating resident.";
            error_log($e->getMessage());
        }
    }
}

// Fetch resident data if not already fetched
if (!$resident && $resident_id) {
    try {
        $stmt = $pdo->prepare("SELECT * FROM residents WHERE id = ?");
        $stmt->execute([$resident_id]);
        $resident = $stmt->fetch();
        
        if (!$resident) {
            $error_message = "Resident not found.";
        }
    } catch (PDOException $e) {
        $error_message = "Database error occurred while fetching resident.";
        error_log($e->getMessage());
    }
}

// Page title
$page_title = "Edit Resident - CommunaLink";
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
                            <a href="residents.php" class="text-gray-500 hover:text-gray-700 mr-4">
                                <i class="fas fa-arrow-left"></i>
                            </a>
                            <h1 class="text-2xl font-semibold text-gray-800">Edit Resident</h1>
                        </div>
                        
                        <!-- User Dropdown -->
                        <div class="relative">
                            <div class="flex items-center space-x-2 text-sm text-gray-600">
                                <span><?php echo htmlspecialchars($_SESSION['fullname']); ?></span>
                                <div class="h-8 w-8 rounded-full bg-blue-600 flex items-center justify-center text-white text-sm font-bold ring-2 ring-white">
                                    <?php echo substr($_SESSION['fullname'], 0, 1); ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </header>
            
            <!-- Page Content -->
            <main class="flex-1 overflow-y-auto bg-gray-50 p-4 sm:p-6 lg:p-8">
                <nav aria-label="Breadcrumb" class="mb-4 text-sm text-gray-500 flex items-center gap-2">
                    <a href="../index.php" class="hover:text-blue-700">Dashboard</a>
                    <span>/</span>
                    <a href="residents.php" class="hover:text-blue-700">Residents</a>
                    <span>/</span>
                    <span class="text-gray-700 font-semibold">Edit Resident</span>
                </nav>
                <?php if ($error_message): ?>
                    <?php echo display_error($error_message); ?>
                <?php endif; ?>
                
                <?php if ($success_message): ?>
                    <?php if (!empty($success_message)): ?>
                    <div id="edit-resident-success-alert" class="bg-green-100 border-l-4 border-green-500 text-green-700 p-4 mb-4" role="alert">
                        <p><?php echo htmlspecialchars($success_message); ?></p>
                    </div>
                <?php endif; ?>
                <?php endif; ?>
                
                <?php if ($resident): ?>
                    <div class="max-w-4xl mx-auto">
                        <div class="bg-white rounded-lg shadow p-6">
                            <form method="POST" action="edit-resident.php">
                                <input type="hidden" name="resident_id" value="<?php echo htmlspecialchars($resident['id']); ?>">
                                
                                <!-- Personal Information -->
                                <div class="space-y-6">
                                    <div>
                                        <h3 class="text-lg font-medium text-gray-900 mb-4">Personal Information</h3>
                                        
                                        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                                            <!-- First Name -->
                                            <div>
                                                <label for="first_name" class="block text-sm font-medium text-gray-700 mb-2">First Name *</label>
                                                <input type="text" id="first_name" name="first_name" 
                                                       value="<?php echo htmlspecialchars($resident['first_name']); ?>" 
                                                       class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500" 
                                                       required>
                                            </div>
                                            
                                            <!-- Middle Initial -->
                                            <div>
                                                <label for="middle_initial" class="block text-sm font-medium text-gray-700 mb-2">Middle Initial</label>
                                                <input type="text" id="middle_initial" name="middle_initial" 
                                                       value="<?php echo htmlspecialchars($resident['middle_initial']); ?>" 
                                                       class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500" 
                                                       maxlength="5">
                                            </div>
                                            
                                            <!-- Last Name -->
                                            <div>
                                                <label for="last_name" class="block text-sm font-medium text-gray-700 mb-2">Last Name *</label>
                                                <input type="text" id="last_name" name="last_name" 
                                                       value="<?php echo htmlspecialchars($resident['last_name']); ?>" 
                                                       class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500" 
                                                       required>
                                            </div>
                                        </div>
                                        
                                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mt-4">
                                            <!-- Gender -->
                                            <div>
                                                <label for="gender" class="block text-sm font-medium text-gray-700 mb-2">Gender *</label>
                                                <select id="gender" name="gender" 
                                                        class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500" 
                                                        required>
                                                    <option value="Male" <?php echo $resident['gender'] === 'Male' ? 'selected' : ''; ?>>Male</option>
                                                    <option value="Female" <?php echo $resident['gender'] === 'Female' ? 'selected' : ''; ?>>Female</option>
                                                    <option value="Other" <?php echo $resident['gender'] === 'Other' ? 'selected' : ''; ?>>Other</option>
                                                </select>
                                            </div>
                                            
                                            <!-- Date of Birth -->
                                            <div>
                                                <label for="date_of_birth" class="block text-sm font-medium text-gray-700 mb-2">Date of Birth *</label>
                                                <input type="date" id="date_of_birth" name="date_of_birth" 
                                                       value="<?php echo htmlspecialchars($resident['date_of_birth']); ?>" 
                                                       class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500" 
                                                       required>
                                            </div>
                                        </div>
                                        
                                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mt-4">
                                            <!-- Place of Birth -->
                                            <div>
                                                <label for="place_of_birth" class="block text-sm font-medium text-gray-700 mb-2">Place of Birth *</label>
                                                <input type="text" id="place_of_birth" name="place_of_birth" 
                                                       value="<?php echo htmlspecialchars($resident['place_of_birth']); ?>" 
                                                       class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500" 
                                                       required>
                                            </div>
                                            
                                            <!-- Religion -->
                                            <div>
                                                <label for="religion" class="block text-sm font-medium text-gray-700 mb-2">Religion</label>
                                                <input type="text" id="religion" name="religion" 
                                                       value="<?php echo htmlspecialchars($resident['religion']); ?>" 
                                                       class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <!-- Contact Information -->
                                    <div>
                                        <h3 class="text-lg font-medium text-gray-900 mb-4">Contact Information</h3>
                                        
                                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                            <!-- Email -->
                                            <div>
                                                <label for="email" class="block text-sm font-medium text-gray-700 mb-2">Email</label>
                                                <input type="email" id="email" name="email" 
                                                       value="<?php echo htmlspecialchars($resident['email']); ?>" 
                                                       class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                                            </div>
                                            
                                            <!-- Contact Number -->
                                            <div>
                                                <label for="contact_no" class="block text-sm font-medium text-gray-700 mb-2">Contact Number</label>
                                                <input type="text" id="contact_no" name="contact_no" 
                                                       value="<?php echo htmlspecialchars($resident['contact_no']); ?>" 
                                                       class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                                            </div>
                                        </div>
                                        
                                        <!-- Address -->
                                        <div class="mt-4">
                                            <label for="address" class="block text-sm font-medium text-gray-700 mb-2">Address *</label>
                                            <textarea id="address" name="address" rows="3" 
                                                      class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500" 
                                                      required><?php echo htmlspecialchars($resident['address']); ?></textarea>
                                        </div>
                                    </div>
                                    
                                    <!-- Additional Information -->
                                    <div>
                                        <h3 class="text-lg font-medium text-gray-900 mb-4">Additional Information</h3>
                                        
                                        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                                            <!-- Citizenship -->
                                            <div>
                                                <label for="citizenship" class="block text-sm font-medium text-gray-700 mb-2">Citizenship *</label>
                                                <input type="text" id="citizenship" name="citizenship" 
                                                       value="<?php echo htmlspecialchars($resident['citizenship']); ?>" 
                                                       class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500" 
                                                       required>
                                            </div>
                                            
                                            <!-- Civil Status -->
                                            <div>
                                                <label for="civil_status" class="block text-sm font-medium text-gray-700 mb-2">Civil Status *</label>
                                                <select id="civil_status" name="civil_status" 
                                                        class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500" 
                                                        required>
                                                    <option value="Single" <?php echo $resident['civil_status'] === 'Single' ? 'selected' : ''; ?>>Single</option>
                                                    <option value="Married" <?php echo $resident['civil_status'] === 'Married' ? 'selected' : ''; ?>>Married</option>
                                                    <option value="Widowed" <?php echo $resident['civil_status'] === 'Widowed' ? 'selected' : ''; ?>>Widowed</option>
                                                    <option value="Separated" <?php echo $resident['civil_status'] === 'Separated' ? 'selected' : ''; ?>>Separated</option>
                                                </select>
                                            </div>
                                            
                                            <!-- Occupation -->
                                            <div>
                                                <label for="occupation" class="block text-sm font-medium text-gray-700 mb-2">Occupation</label>
                                                <input type="text" id="occupation" name="occupation" 
                                                       value="<?php echo htmlspecialchars($resident['occupation']); ?>" 
                                                       class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                                            </div>
                                        </div>
                                        
                                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mt-4">
                                            <!-- ID Number -->
                                            <div>
                                                <label for="id_number" class="block text-sm font-medium text-gray-700 mb-2">ID Number</label>
                                                <input type="text" id="id_number" name="id_number" 
                                                       value="<?php echo htmlspecialchars($resident['id_number']); ?>" 
                                                       class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                                                <p class="text-xs text-gray-500 mt-1">Format: BR-YYYY-XXXX (can be edited)</p>
                                            </div>
                                            
                                            <!-- Voter Status -->
                                            <div>
                                                <label for="voter_status" class="block text-sm font-medium text-gray-700 mb-2">Voter Status *</label>
                                                <select id="voter_status" name="voter_status" 
                                                        class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500" 
                                                        required>
                                                    <option value="Yes" <?php echo $resident['voter_status'] === 'Yes' ? 'selected' : ''; ?>>Yes</option>
                                                    <option value="No" <?php echo $resident['voter_status'] === 'No' ? 'selected' : ''; ?>>No</option>
                                                </select>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <!-- Current Information (Read-only) -->
                                    <div>
                                        <h3 class="text-lg font-medium text-gray-900 mb-4">Current Information</h3>
                                        
                                        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                                            <div>
                                                <label class="block text-sm font-medium text-gray-700 mb-2">Age</label>
                                                <input type="text" value="<?php echo htmlspecialchars($resident['age']); ?>" 
                                                       class="w-full px-3 py-2 border border-gray-300 rounded-md bg-gray-50 text-gray-500" 
                                                       readonly>
                                                <p class="text-xs text-gray-500 mt-1">Calculated from date of birth</p>
                                            </div>
                                            
                                            <div>
                                                <label class="block text-sm font-medium text-gray-700 mb-2">Created</label>
                                                <input type="text" value="<?php echo date('M d, Y h:i A', strtotime($resident['created_at'])); ?>" 
                                                       class="w-full px-3 py-2 border border-gray-300 rounded-md bg-gray-50 text-gray-500" 
                                                       readonly>
                                            </div>
                                            
                                            <div>
                                                <label class="block text-sm font-medium text-gray-700 mb-2">Last Updated</label>
                                                <input type="text" value="<?php echo $resident['updated_at'] ? date('M d, Y h:i A', strtotime($resident['updated_at'])) : 'Never'; ?>" 
                                                       class="w-full px-3 py-2 border border-gray-300 rounded-md bg-gray-50 text-gray-500" 
                                                       readonly>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                
                                <!-- Action Buttons -->
                                <div class="flex justify-end space-x-3 mt-8 pt-6 border-t border-gray-200">
                                    <a href="residents.php" 
                                       class="px-4 py-2 border border-gray-300 rounded-md text-sm font-medium text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                                        Cancel
                                    </a>
                                    <button type="submit" 
                                            class="px-4 py-2 bg-blue-600 border border-transparent rounded-md text-sm font-medium text-white hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                                        Update Resident
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                <?php else: ?>
                    <div class="max-w-2xl mx-auto">
                        <div class="bg-white rounded-lg shadow p-6 text-center">
                            <i class="fas fa-exclamation-triangle text-red-500 text-4xl mb-4"></i>
                            <h3 class="text-lg font-medium text-gray-900 mb-2">Resident Not Found</h3>
                            <p class="text-gray-500 mb-4">The resident you're looking for doesn't exist or has been deleted.</p>
                            <a href="residents.php" 
                               class="inline-flex items-center px-4 py-2 bg-blue-600 border border-transparent rounded-md text-sm font-medium text-white hover:bg-blue-700">
                                <i class="fas fa-arrow-left mr-2"></i>
                                Back to Residents
                            </a>
                        </div>
                    </div>
                <?php endif; ?>
            </main>
        </div>
    </div>

    <script>
    document.addEventListener('DOMContentLoaded', function() {
        const alert = document.getElementById('edit-resident-success-alert');
        if (alert) {
            setTimeout(() => {
                alert.style.display = 'none';
            }, 3000);
        }
    });
    </script>
</body>
</html> 