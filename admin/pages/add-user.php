<?php
/**
 * Add User Page
 */

// Include authentication system
require_once '../../config/init.php';
require_once '../../includes/functions.php';
require_once '../../includes/auth.php';

// Check if user is logged in
require_login();

// Check if user is an admin, otherwise redirect
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    redirect_to('../index.php');
}

// Page title
$page_title = "Add New User - CommuniLink";
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
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
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
                        <h1 class="text-2xl font-semibold text-gray-800">Add New User</h1>
                        <!-- User Dropdown -->
                        <div x-data="{ open: false }" class="relative">
                            <button @click="open = !open" class="flex items-center space-x-2 text-sm text-gray-600 hover:text-gray-900 focus:outline-none">
                                <span><?php echo htmlspecialchars($_SESSION['fullname']); ?></span>
                                <div class="h-8 w-8 rounded-full bg-purple-600 flex items-center justify-center text-white text-sm font-bold ring-2 ring-white">
                                    <?php echo substr($_SESSION['fullname'], 0, 1); ?>
                                </div>
                            </button>
                            <div x-show="open" @click.away="open = false" x-cloak class="origin-top-right absolute right-0 mt-2 w-48 rounded-md shadow-lg bg-white ring-1 ring-black ring-opacity-5 focus:outline-none z-20 divide-y divide-gray-200">
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

            <!-- Page Content -->
            <main class="flex-1 overflow-y-auto bg-gray-50 p-4 sm:p-6 lg:p-8">
                <div class="max-w-2xl mx-auto">
                    <?php
                    if (isset($_SESSION['error_message'])) {
                        echo display_error($_SESSION['error_message']);
                        unset($_SESSION['error_message']);
                    }
                    ?>
                    <div class="bg-white rounded-lg shadow p-6">
                        <form action="../partials/add-user-handler.php" method="POST" class="space-y-6">
                            <?php echo csrf_field(); ?>
                            <!-- Full Name -->
                            <div>
                                <label for="fullname" class="block text-sm font-medium text-gray-700">Full Name</label>
                                <input type="text" name="fullname" id="fullname" required class="mt-1 block w-full px-3 py-2 bg-white border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm">
                            </div>

                            <!-- Email -->
                            <div>
                                <label for="email" class="block text-sm font-medium text-gray-700">Email Address</label>
                                <input type="email" name="email" id="email" required class="mt-1 block w-full px-3 py-2 bg-white border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm">
                            </div>

                            <!-- Password -->
                            <div>
                                <label for="password" class="block text-sm font-medium text-gray-700">Password</label>
                                <input type="password" name="password" id="password" required 
                                       class="mt-1 block w-full px-3 py-2 bg-white border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm"
                                       onkeyup="validatePassword(this.value)" 
                                       minlength="8">
                                
                                <!-- Password Strength Indicator -->
                                <div id="password-strength" class="mt-2 hidden">
                                    <div class="flex items-center space-x-2">
                                        <span class="text-sm font-medium">Strength:</span>
                                        <span id="strength-text" class="text-sm font-semibold"></span>
                                        <span id="strength-color" class="text-sm"></span>
                                    </div>
                                    <div class="mt-1 w-full bg-gray-200 rounded-full h-2">
                                        <div id="strength-bar" class="h-2 rounded-full transition-all duration-300"></div>
                                    </div>
                                </div>
                                
                                <!-- Password Requirements -->
                                <div class="mt-2 text-xs text-gray-600">
                                    <p>Password must contain:</p>
                                    <ul class="list-disc list-inside space-y-1 mt-1">
                                        <li id="req-length" class="text-red-600">✗ At least 8 characters</li>
                                        <li id="req-uppercase" class="text-red-600">✗ One uppercase letter</li>
                                        <li id="req-lowercase" class="text-red-600">✗ One lowercase letter</li>
                                        <li id="req-number" class="text-red-600">✗ One number</li>
                                        <li id="req-special" class="text-red-600">✗ One special character (!@#$%^&*()_+-=[]{}|;:,.<>?)</li>
                                    </ul>
                                </div>
                                
                                <!-- Password Suggestions -->
                                <div id="password-suggestions" class="mt-2 hidden">
                                    <p class="text-xs text-blue-600 font-medium">Suggestions:</p>
                                    <ul id="suggestions-list" class="text-xs text-blue-600 list-disc list-inside mt-1"></ul>
                                </div>
                            </div>

                            <!-- Role -->
                            <div>
                                <label for="role" class="block text-sm font-medium text-gray-700">Role</label>
                                <select name="role" id="role" required class="mt-1 block w-full pl-3 pr-10 py-2 text-base border border-gray-300 focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm rounded-md" onchange="toggleOfficialPosition()">
                                    <option value="">Select a role</option>
                                    <option value="admin">Admin</option>
                                    <option value="official">Official</option>
                                    <option value="resident">Resident</option>
                                </select>
                            </div>

                            <!-- Official Position (hidden by default) -->
                            <div id="officialPositionDiv" class="hidden">
                                <label for="official_position" class="block text-sm font-medium text-gray-700">Official Position</label>
                                <select name="official_position" id="official_position" class="mt-1 block w-full pl-3 pr-10 py-2 text-base border border-gray-300 focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm rounded-md">
                                    <option value="">Select official position</option>
                                    <option value="barangay-captain">Barangay Captain</option>
                                    <option value="kagawad">Kagawad</option>
                                    <option value="barangay-secretary">Barangay Secretary</option>
                                    <option value="barangay-treasurer">Barangay Treasurer</option>
                                    <option value="barangay-tanod">Barangay Tanod</option>
                                </select>
                            </div>

                            <!-- Submit Button -->
                            <div class="flex justify-end">
                                <a href="user-management.php" class="bg-gray-200 text-gray-700 px-4 py-2 rounded-md mr-2">Cancel</a>
                                <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-md" onclick="return validateForm()">
                                    Create User
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
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

        function validateForm() {
            const role = document.getElementById('role').value;
            const officialPosition = document.getElementById('official_position').value;
            const password = document.getElementById('password').value;
            
            if (role === 'official' && !officialPosition) {
                alert('Please select an official position.');
                return false;
            }
            
            // Validate password strength
            const passwordValidation = validatePassword(password);
            if (!passwordValidation.valid) {
                alert('Please ensure your password meets all security requirements.');
                return false;
            }
            
            return true;
        }
        
        function validatePassword(password) {
            const strengthDiv = document.getElementById('password-strength');
            const suggestionsDiv = document.getElementById('password-suggestions');
            
            if (password.length === 0) {
                strengthDiv.classList.add('hidden');
                suggestionsDiv.classList.add('hidden');
                return { valid: false };
            }
            
            // Show strength indicator
            strengthDiv.classList.remove('hidden');
            
            // Calculate strength
            let score = 0;
            let errors = [];
            let suggestions = [];
            
            // Length check
            if (password.length >= 8) score += 1;
            if (password.length >= 12) score += 1;
            if (password.length >= 16) score += 1;
            
            // Character variety checks
            const hasUppercase = /[A-Z]/.test(password);
            const hasLowercase = /[a-z]/.test(password);
            const hasNumbers = /[0-9]/.test(password);
            const hasSpecial = /[^A-Za-z0-9]/.test(password);
            
            if (hasUppercase) score += 1;
            if (hasLowercase) score += 1;
            if (hasNumbers) score += 1;
            if (hasSpecial) score += 1;
            
            // Update requirement indicators
            updateRequirement('req-length', password.length >= 8);
            updateRequirement('req-uppercase', hasUppercase);
            updateRequirement('req-lowercase', hasLowercase);
            updateRequirement('req-number', hasNumbers);
            updateRequirement('req-special', hasSpecial);
            
            // Generate suggestions
            if (password.length < 12) suggestions.push('Make your password at least 12 characters long.');
            if (!hasUppercase) suggestions.push('Add uppercase letters to make your password stronger.');
            if (!hasNumbers) suggestions.push('Include numbers to increase complexity.');
            if (!hasSpecial) suggestions.push('Add special characters like !@#$%^&* for better security.');
            
            // Update strength display
            updateStrengthDisplay(score, suggestions);
            
            // Check if password meets minimum requirements
            const valid = password.length >= 8 && hasUppercase && hasLowercase && hasNumbers && hasSpecial;
            
            return { valid, score, errors, suggestions };
        }
        
        function updateRequirement(elementId, met) {
            const element = document.getElementById(elementId);
            if (met) {
                element.classList.remove('text-red-600');
                element.classList.add('text-green-600');
                element.innerHTML = element.innerHTML.replace('✗', '✓');
            } else {
                element.classList.remove('text-green-600');
                element.classList.add('text-red-600');
                element.innerHTML = element.innerHTML.replace('✓', '✗');
            }
        }
        
        function updateStrengthDisplay(score, suggestions) {
            const strengthText = document.getElementById('strength-text');
            const strengthColor = document.getElementById('strength-color');
            const strengthBar = document.getElementById('strength-bar');
            const suggestionsList = document.getElementById('suggestions-list');
            const suggestionsDiv = document.getElementById('password-suggestions');
            
            // Set strength text and color
            let strengthLabel, colorClass, barColor, barWidth;
            
            if (score <= 2) {
                strengthLabel = 'Very Weak';
                colorClass = 'text-red-600';
                barColor = 'bg-red-500';
                barWidth = '20%';
            } else if (score <= 3) {
                strengthLabel = 'Weak';
                colorClass = 'text-orange-600';
                barColor = 'bg-orange-500';
                barWidth = '40%';
            } else if (score <= 4) {
                strengthLabel = 'Fair';
                colorClass = 'text-yellow-600';
                barColor = 'bg-yellow-500';
                barWidth = '60%';
            } else if (score <= 5) {
                strengthLabel = 'Strong';
                colorClass = 'text-blue-600';
                barColor = 'bg-blue-500';
                barWidth = '80%';
            } else {
                strengthLabel = 'Very Strong';
                colorClass = 'text-green-600';
                barColor = 'bg-green-500';
                barWidth = '100%';
            }
            
            strengthText.textContent = strengthLabel;
            strengthColor.className = `text-sm ${colorClass}`;
            strengthColor.textContent = strengthLabel;
            
            // Update strength bar
            strengthBar.className = `h-2 rounded-full transition-all duration-300 ${barColor}`;
            strengthBar.style.width = barWidth;
            
            // Update suggestions
            if (suggestions.length > 0) {
                suggestionsList.innerHTML = suggestions.map(s => `<li>${s}</li>`).join('');
                suggestionsDiv.classList.remove('hidden');
            } else {
                suggestionsDiv.classList.add('hidden');
            }
        }

        // Initialize on page load
        document.addEventListener('DOMContentLoaded', function() {
            toggleOfficialPosition();
        });
    </script>
</body>
</html> 