<?php
/**
 * Logout Script
 * Handles user logout functionality
 */

// Include authentication system
require_once 'auth.php';

// Log out the user
logout();

$redirect_url = app_url('/index.php?logout=success');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Logging Out - CommunaLink</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
    <script src="../assets/js/system-worker.js" defer></script>
    <!-- Fallback redirect just in case JS fails -->
    <meta http-equiv="refresh" content="2;url=<?php echo htmlspecialchars($redirect_url); ?>">
    <style>
        body {
            font-family: 'Plus Jakarta Sans', sans-serif;
        }
        .spinner {
            border: 4px solid rgba(0, 0, 0, 0.1);
            width: 48px;
            height: 48px;
            border-radius: 50%;
            border-left-color: #3b82f6;
            animation: spin 1s linear infinite;
        }
        .loading-dots span {
            display: inline-block;
            animation: bounce 1.2s infinite ease-in-out;
        }
        .loading-dots span:nth-child(2) {
            animation-delay: 0.15s;
        }
        .loading-dots span:nth-child(3) {
            animation-delay: 0.3s;
        }
        @keyframes bounce {
            0%, 80%, 100% { transform: translateY(0); opacity: 0.45; }
            40% { transform: translateY(-4px); opacity: 1; }
        }
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
    </style>
</head>
<body class="flex items-center justify-center min-h-screen bg-gray-50">
    <div class="text-center p-8 bg-white rounded-2xl shadow-xl max-w-sm w-full mx-4 transform transition-all">
        <div class="flex justify-center mb-6">
            <div class="spinner"></div>
        </div>
        <h2 class="text-2xl font-bold text-gray-800 mb-2">Logging Out</h2>
        <p class="text-gray-500 mb-2">Please wait while we securely end your session</p>
        <p class="text-sm text-gray-400 loading-dots" aria-hidden="true"><span>.</span><span>.</span><span>.</span></p>
    </div>

    <script>
        // Redirect after a slight delay for better UX
        setTimeout(function() {
            window.location.href = "<?php echo addslashes($redirect_url); ?>";
        }, 1800);
    </script>
</body>
</html>