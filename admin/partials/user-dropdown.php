<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$fullname_raw = $_SESSION['fullname'] ?? 'User';
$fullname = htmlspecialchars($fullname_raw, ENT_QUOTES, 'UTF-8');
$initial = htmlspecialchars(strtoupper(substr($fullname_raw, 0, 1)), ENT_QUOTES, 'UTF-8');
?>
<div x-data="{ open: false }" class="relative">
    <button @click="open = !open" class="flex items-center space-x-3 text-sm text-gray-700 hover:text-gray-900 focus:outline-none transition group" type="button">
        <span class="font-medium group-hover:text-blue-600"><?php echo $fullname; ?></span>
        <div class="h-9 w-9 rounded-xl bg-gradient-to-br from-blue-500 to-blue-700 flex items-center justify-center text-white text-sm font-bold shadow-sm group-hover:shadow-md transition">
            <?php echo $initial; ?>
        </div>
    </button>
    <div x-show="open" @click.away="open = false" x-cloak
         x-transition:enter="transition ease-out duration-100"
         x-transition:enter-start="transform opacity-0 scale-95"
         x-transition:enter-end="transform opacity-100 scale-100"
         x-transition:leave="transition ease-in duration-75"
         x-transition:leave-start="transform opacity-100 scale-100"
         x-transition:leave-end="transform opacity-0 scale-95"
         class="origin-top-right absolute right-0 mt-2 w-48 rounded-xl shadow-xl py-1 bg-white ring-1 ring-black ring-opacity-5 focus:outline-none z-20">
        <a href="account.php" class="flex items-center px-4 py-2 text-sm text-gray-700 hover:bg-gray-50"><i class="fas fa-user-circle mr-2 text-gray-400"></i> My Account</a>
        <div class="border-t border-gray-100 mt-1"></div>
        <a href="../../includes/logout.php" class="flex items-center px-4 py-2 text-sm text-red-600 hover:bg-red-50"><i class="fas fa-sign-out-alt mr-2"></i> Sign Out</a>
    </div>
</div>
