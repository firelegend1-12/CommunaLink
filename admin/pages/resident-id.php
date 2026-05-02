<?php
require_once '../partials/admin_auth.php';
/**
 * Barangay Resident ID Card
 */

if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['admin'])) {
    header('Location: ../../index.php');
    exit;
}

require_once '../../config/init.php';
require_once '../../includes/functions.php';
require_once '../../includes/storage_manager.php';
require_once '../../includes/qr_generator.php';

function admin_resident_profile_image_url(string $storedPath): string
{
    $path = trim($storedPath);
    if ($path === '') {
        return '';
    }

    if (strpos($path, 'gs://') === 0 || preg_match('#^https?://#i', $path) === 1) {
        return StorageManager::resolvePublicUrl($path);
    }

    $normalized = ltrim(str_replace('\\', '/', $path), '/');
    if ($normalized === '') {
        return '';
    }

    if (stripos($normalized, 'admin/') === 0) {
        return app_url('/' . $normalized);
    }

    return app_url('/admin/' . $normalized);
}

$resident_id = $_GET['id'] ?? null;
if (!$resident_id) {
    die('Resident ID is required.');
}

try {
    $stmt = $pdo->prepare("SELECT * FROM residents WHERE id = ?");
    $stmt->execute([$resident_id]);
    $resident = $stmt->fetch();

    if (!$resident) {
        die('Resident not found.');
    }
} catch (PDOException $e) {
    die('Database error: ' . $e->getMessage());
}

$full_name = strtoupper($resident['last_name'] . ', ' . $resident['first_name'] . ' ' . $resident['middle_initial'] . '.');
$address = strtoupper('123 AGUSTIN STREET, BGRY PAKIAD, ILOILO CITY'); // Example address

// Ensure resident has a secure QR token
if (empty($resident['qr_token'])) {
    $newToken = bin2hex(random_bytes(24));
    $upd = $pdo->prepare("UPDATE residents SET qr_token = ? WHERE id = ?");
    $upd->execute([$newToken, $resident['id']]);
    $resident['qr_token'] = $newToken;
}

$verify_url = app_url('/resident/verify-qr.php?t=' . urlencode($resident['qr_token']));
$qr_code_datauri = generate_qr_datauri($verify_url, 150);
$resident_photo_url = admin_resident_profile_image_url((string)($resident['profile_image_path'] ?? ''));
?>
<div class="id-card rounded-xl shadow-lg p-2 flex flex-col relative overflow-hidden">
    <!-- Background Map -->
    <div class="absolute inset-0 philippine-map"></div>

    <!-- Header -->
    <div class="flex items-center justify-between z-10">
        <div class="text-left">
            <p class="font-bold text-xs">REPUBLIC OF THE PHILIPPINES</p>
            <p class="font-semibold">CITY OF ILOILO - BARANGAY PAKIAD</p>
            <p>Tel No.: (63) 917 986 9611 | (63) 917 986 9611</p>
        </div>
    </div>

    <!-- Title -->
    <div class="text-center my-1 z-10">
        <p class="font-bold text-base leading-tight tracking-wider" style="letter-spacing: 1px;">BARANGAY RESIDENT'S CARD</p>
    </div>

    <!-- Body -->
    <div class="flex-grow flex z-10">
        <!-- Left Side: Photo and Address -->
        <div class="w-1/4 flex flex-col items-center mr-2">
            <img src="<?php
echo htmlspecialchars($resident_photo_url !== '' ? $resident_photo_url : 'https://via.placeholder.com/150'); ?>" alt="Resident Photo" class="w-20 h-20 object-cover border-2 border-white shadow-md">
            <p class="text-center mt-1 font-bold text-xxs leading-tight"><?php
echo htmlspecialchars($address); ?></p>
        </div>

        <!-- Right Side: Details -->
        <div class="w-3/4 flex">
            <!-- Main Details -->
            <div class="w-3/4 pr-2 space-y-1">
                <div>
                    <p class="text-gray-500 uppercase tracking-wider text-xxs">LAST NAME, FIRST NAME, MI.</p>
                    <p class="font-bold text-xs"><?php
echo htmlspecialchars($full_name); ?></p>
                </div>
                <div class="grid grid-cols-3 gap-x-2">
                    <div>
                        <p class="text-gray-500 uppercase text-xxs">DATE OF BIRTH</p>
                        <p class="font-bold"><?php
echo htmlspecialchars(date('m/d/Y', strtotime($resident['date_of_birth']))); ?></p>
                    </div>
                    <div>
                        <p class="text-gray-500 uppercase text-xxs">CIVIL STATUS</p>
                        <p class="font-bold"><?php
echo htmlspecialchars(strtoupper($resident['civil_status'])); ?></p>
                    </div>
                    <div>
                        <p class="text-gray-500 uppercase text-xxs">GENDER</p>
                        <p class="font-bold"><?php
echo htmlspecialchars(strtoupper(substr($resident['gender'], 0, 1))); ?></p>
                    </div>
                </div>
                <div class="grid grid-cols-3 gap-x-2">
                     <div>
                        <p class="text-gray-500 uppercase text-xxs">BARANGAY</p>
                        <p class="font-bold">PAKIAD</p>
                    </div>
                    <div>
                        <p class="text-gray-500 uppercase text-xxs">MUNICIPALITY/CITY</p>
                        <p class="font-bold">OTON</p>
                    </div>
                    <div>
                        <p class="text-gray-500 uppercase text-xxs">PROVINCE</p>
                        <p class="font-bold">ILOILO</p>
                    </div>
                </div>
                 <div class="grid grid-cols-3 gap-x-2">
                    <div>
                        <p class="text-gray-500 uppercase text-xxs">CONTACT NO</p>
                        <p class="font-bold"><?php
echo htmlspecialchars($resident['contact_no']); ?></p>
                    </div>
                    <div>
                        <p class="text-gray-500 uppercase text-xxs">ISSUED</p>
                        <p class="font-bold"><?php
echo date('m/d/y'); ?></p>
                    </div>
                    <div>
                        <p class="text-gray-500 uppercase text-xxs">VALID UNTIL</p>
                        <p class="font-bold"><?php
echo date('m/d/y', strtotime('+1 year')); ?></p>
                    </div>
                </div>
            </div>

            <!-- QR Code -->
            <div class="w-1/4 flex items-end justify-center">
                <img src="<?php
echo htmlspecialchars($qr_code_datauri); ?>" alt="QR Code" class="w-16 h-16">
            </div>
        </div>
    </div>
</div> 

