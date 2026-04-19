<?php
/**
 * About Us Page
 */

require_once '../../config/init.php';
require_once '../../includes/auth.php';
require_login();

$page_title = "About Us - CommunaLink";

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Barangay Pakiad</title>
    <script src="https://cdn.tailwindcss.com"></script>
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
                        <h1 class="text-2xl font-semibold text-gray-800">About Us</h1>
                        
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
                                 class="origin-top-right absolute right-0 mt-2 w-48 rounded-md shadow-lg py-1 bg-white ring-1 ring-black ring-opacity-5 focus:outline-none z-20">
                                
                                <a href="account.php" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">My Account</a>
                                <a href="../../includes/logout.php" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">Sign Out</a>
                            </div>
                        </div>
                    </div>
                </div>
            </header>
            
            <!-- Page Content -->
            <main class="flex-1 overflow-y-auto bg-gray-50 p-4 sm:p-6 lg:p-8">
                <div class="bg-white rounded-lg shadow p-8 max-w-4xl mx-auto">
                    <div class="flex flex-col items-center text-center">
                        
                        <!-- Logo -->
                        <div class="mb-6">
                            <h1 class="text-5xl font-bold text-blue-800">Veru<span class="text-blue-500">Soft</span></h1>
                            <p class="text-gray-500">Innovative Barangay Solutions</p>
                        </div>

                        <!-- Mission Statement -->
                        <div class="mt-4 text-gray-700">
                            <h2 class="text-2xl font-semibold mb-4">Our Mission</h2>
                            <p class="text-lg leading-relaxed">
                                Our mission is to empower local government units with innovative, user-friendly, and efficient digital solutions. We believe in leveraging technology to streamline administrative processes, enhance community engagement, and foster transparent governance. By providing robust tools like CommunaLink, we aim to help barangay officials better serve their constituents, manage resources effectively, and build smarter, more connected communities for a brighter future.
                            </p>
                        </div>
                        
                    </div>
                </div>
            </main>
        </div>
    </div>
</body>
</html> 
