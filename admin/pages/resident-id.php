<?php
require_once '../partials/admin_auth.php';
/**
 * Barangay Resident ID Card
 */

require_once '../../config/init.php';
require_once '../../includes/functions.php';
require_once '../../includes/storage_manager.php';
require_once '../../includes/qr_generator.php';

function is_valid_qr_token_format(string $token): bool
{
    return preg_match('/\A(?:[a-f0-9]{48}|[a-f0-9]{64})\z/i', $token) === 1;
}

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
$address = strtoupper(trim((string)($resident['address'] ?? '')));
if ($address === '') {
    $address = 'N/A';
}
$display_id_number = trim((string)($resident['id_number'] ?? ''));
if ($display_id_number === '') {
    $createdAtYear = isset($resident['created_at']) ? (int)date('Y', strtotime((string)$resident['created_at'])) : (int)date('Y');
    $display_id_number = sprintf('BR-%d-%04d', $createdAtYear, (int)$resident['id']);
}

// Ensure resident has a secure QR token
$existingToken = (string)($resident['qr_token'] ?? '');
$expiresAt = (string)($resident['qr_token_expires_at'] ?? '');
$isExpired = ($expiresAt !== '' && strtotime($expiresAt) !== false && strtotime($expiresAt) <= time());
$needsRotation = ($existingToken === '' || !is_valid_qr_token_format($existingToken) || $isExpired);

if ($needsRotation) {
    $newToken = bin2hex(random_bytes(32));
    $newExpiry = date('Y-m-d H:i:s', strtotime('+1 year'));
    $upd = $pdo->prepare("UPDATE residents SET qr_token = ?, qr_token_expires_at = ? WHERE id = ?");
    $upd->execute([$newToken, $newExpiry, $resident['id']]);
    $resident['qr_token'] = $newToken;
    $resident['qr_token_expires_at'] = $newExpiry;
} elseif ($expiresAt === '') {
    $newExpiry = date('Y-m-d H:i:s', strtotime('+1 year'));
    $upd = $pdo->prepare("UPDATE residents SET qr_token_expires_at = ? WHERE id = ?");
    $upd->execute([$newExpiry, $resident['id']]);
    $resident['qr_token_expires_at'] = $newExpiry;
}

$verify_url = app_url('/resident/verify-qr.php?t=' . urlencode($resident['qr_token']));
$qr_code_datauri = generate_qr_datauri($verify_url, 150);
$resident_photo_url = admin_resident_profile_image_url((string)($resident['profile_image_path'] ?? ''));
$background_logo_url = app_url('/assets/images/barangay-logo.png');
?>
<div class="id-card rounded-xl shadow-lg px-3 py-2 flex flex-col relative overflow-hidden">
    <img src="<?php
echo htmlspecialchars($background_logo_url, ENT_QUOTES, 'UTF-8'); ?>" alt="" aria-hidden="true" class="absolute right-6 top-1/2 -translate-y-1/2 w-[108px] max-w-none opacity-[0.08] pointer-events-none select-none" style="-webkit-print-color-adjust: exact; print-color-adjust: exact; filter: grayscale(10%);">

    <!-- Header -->
    <div class="flex items-center justify-between z-10">
        <div class="text-left">
            <p class="font-bold text-[6.75px] leading-tight">REPUBLIC OF THE PHILIPPINES</p>
            <p class="font-semibold text-[6px] leading-tight">CITY OF ILOILO - BARANGAY PAKIAD</p>
            <p class="text-[5.35px] leading-tight">Tel No.: (63) 917 986 9611 | (63) 917 986 9611</p>
        </div>
    </div>

    <!-- Title -->
    <div class="text-center mt-1 mb-1.5 z-10">
        <p class="font-bold text-[13px] leading-none tracking-wider" style="letter-spacing: 1px;">BARANGAY RESIDENT'S CARD</p>
    </div>

    <!-- Body -->
    <div class="flex-grow flex z-10 gap-2.5 items-start">
        <!-- Left Side: Photo and Address -->
        <div class="w-[24%] flex flex-col items-center justify-start pt-0.5">
            <img src="<?php
echo htmlspecialchars($resident_photo_url !== '' ? $resident_photo_url : 'https://via.placeholder.com/150'); ?>" alt="Resident Photo" class="w-[54px] h-[66px] object-cover border border-white shadow-md bg-white">
            <p class="text-center mt-1 font-bold text-[5.9px] leading-tight max-w-[58px]"><?php
echo htmlspecialchars($address); ?></p>
        </div>

        <!-- Right Side: Details -->
        <div class="flex-1 flex min-w-0 justify-between gap-2.5 items-start">
            <div class="flex-1 min-w-0 flex flex-col gap-1">
                <div>
                    <p class="text-gray-500 uppercase tracking-wider text-[5.7px] mb-0.5">LAST NAME, FIRST NAME, MI.</p>
                    <p class="font-bold text-[7.35px] leading-[1.08] uppercase break-words max-w-[118px]" style="display:-webkit-box;-webkit-line-clamp:3;-webkit-box-orient:vertical;overflow:hidden;"><?php
echo htmlspecialchars($full_name); ?></p>
                    <p class="text-gray-500 uppercase tracking-wider text-[5.7px] mt-1 mb-0.5">ID NUMBER</p>
                    <p class="font-bold text-[7.5px] leading-tight"><?php
echo htmlspecialchars($display_id_number); ?></p>
                </div>

                <div class="grid grid-cols-3 gap-x-2 gap-y-1.5 mt-0.5">
                    <div class="min-w-0">
                        <p class="text-gray-500 uppercase text-[5.6px]">DATE OF BIRTH</p>
                        <p class="font-bold text-[6.15px] leading-tight"><?php
echo htmlspecialchars(date('m/d/Y', strtotime($resident['date_of_birth']))); ?></p>
                    </div>
                    <div class="min-w-0">
                        <p class="text-gray-500 uppercase text-[5.6px]">CIVIL STATUS</p>
                        <p class="font-bold text-[6.15px] leading-tight"><?php
echo htmlspecialchars(strtoupper($resident['civil_status'])); ?></p>
                    </div>
                    <div class="min-w-0">
                        <p class="text-gray-500 uppercase text-[5.6px]">GENDER</p>
                        <p class="font-bold text-[6.15px] leading-tight"><?php
echo htmlspecialchars(strtoupper(substr($resident['gender'], 0, 1))); ?></p>
                    </div>

                    <div class="min-w-0">
                        <p class="text-gray-500 uppercase text-[5.6px]">BARANGAY</p>
                        <p class="font-bold text-[6.15px] leading-tight">PAKIAD</p>
                    </div>
                    <div class="min-w-0">
                        <p class="text-gray-500 uppercase text-[5.6px]">MUNICIPALITY/CITY</p>
                        <p class="font-bold text-[6.15px] leading-tight">OTON</p>
                    </div>
                    <div class="min-w-0">
                        <p class="text-gray-500 uppercase text-[5.6px]">PROVINCE</p>
                        <p class="font-bold text-[6.15px] leading-tight">ILOILO</p>
                    </div>

                    <div class="min-w-0">
                        <p class="text-gray-500 uppercase text-[5.6px]">CONTACT NO</p>
                        <p class="font-bold text-[6.15px] leading-tight"><?php
echo htmlspecialchars($resident['contact_no']); ?></p>
                    </div>
                    <div class="min-w-0">
                        <p class="text-gray-500 uppercase text-[5.6px]">ISSUED</p>
                        <p class="font-bold text-[6.15px] leading-tight"><?php
echo date('m/d/y'); ?></p>
                    </div>
                    <div class="min-w-0">
                        <p class="text-gray-500 uppercase text-[5.6px]">VALID UNTIL</p>
                        <p class="font-bold text-[6.15px] leading-tight"><?php
echo date('m/d/y', strtotime('+1 year')); ?></p>
                    </div>
                </div>
            </div>

            <!-- QR Code -->
            <div class="w-[66px] flex items-end justify-center pt-2">
                <img src="<?php
echo htmlspecialchars($qr_code_datauri); ?>" alt="QR Code" class="w-[60px] h-[60px]">
            </div>
        </div>
    </div>
</div> 

