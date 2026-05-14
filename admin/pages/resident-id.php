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

$verify_url        = app_url('/resident/verify-qr.php?t=' . urlencode($resident['qr_token']));
$qr_code_datauri   = generate_qr_datauri($verify_url, 150);
$resident_photo_url = admin_resident_profile_image_url((string)($resident['profile_image_path'] ?? ''));
$background_logo_url = app_url('/assets/images/barangay-logo.png');

// Safer display values
$dob = '';
if (!empty($resident['date_of_birth']) && strtotime((string)$resident['date_of_birth']) !== false) {
    $dob = date('m/d/Y', strtotime((string)$resident['date_of_birth']));
}
$civil_status = strtoupper((string)($resident['civil_status'] ?? ''));
$gender = strtoupper(substr((string)($resident['gender'] ?? ''), 0, 1));
$contact_no = (string)($resident['contact_no'] ?? '');

// Issued/valid (you can swap to DB fields later if you have them)
$issued_display = date('m/d/y');
$valid_until_display = date('m/d/y', strtotime('+1 year'));
?>

<!-- ID CARD WRAPPER (so buttons can sit under the ID) -->
<div class="inline-block">
  <!-- ID CARD -->
  <div class="id-card rounded-xl shadow-lg px-2 py-1 flex flex-col relative overflow-hidden">
    <!-- Background logo: CENTERED + larger -->
    <img
      src="<?php echo htmlspecialchars($background_logo_url, ENT_QUOTES, 'UTF-8'); ?>"
      alt=""
      aria-hidden="true"
      class="absolute left-1/2 top-1/2 -translate-x-1/2 -translate-y-1/2 w-[185px] max-w-none opacity-[0.08] pointer-events-none select-none"
      style="-webkit-print-color-adjust: exact; print-color-adjust: exact; filter: grayscale(10%);"
    >

    <!-- QR: leveled with VALID UNTIL area + add right margin -->
    <!-- Adjust bottom-[10px] +/- a few px if your card height changes -->
    <img
      src="<?php echo htmlspecialchars($qr_code_datauri, ENT_QUOTES, 'UTF-8'); ?>"
      alt="QR Code"
      class="absolute right-2 bottom-[10px] w-[62px] h-[62px] z-20"
    >

    <!-- Header (CENTERED) -->
    <div class="flex items-center justify-center z-10">
      <div class="text-center">
        <p class="font-bold text-[7.9px] leading-tight">REPUBLIC OF THE PHILIPPINES</p>
        <p class="font-semibold text-[7.1px] leading-tight">CITY OF ILOILO - BARANGAY PAKIAD</p>
        <p class="text-[6.4px] leading-tight mb-1">
          Tel No.: (63) 917 986 9611 | (63) 917 986 9611
        </p>
      </div>
    </div>

    <!-- Title -->
    <div class="text-center mt-0.5 mb-1.5 z-10">
      <p class="font-bold text-[14px] leading-none tracking-wider" style="letter-spacing: 1px;">
        BARANGAY RESIDENT'S CARD
      </p>
    </div>

    <!-- Body -->
    <div class="flex-grow flex z-10 gap-2.5 items-start">
      <!-- Left: Photo + Address (68 x 82) -->
      <div class="w-[24%] flex flex-col items-center justify-start pt-2">
        <img
          src="<?php echo htmlspecialchars($resident_photo_url !== '' ? $resident_photo_url : 'https://via.placeholder.com/150', ENT_QUOTES, 'UTF-8'); ?>"
          alt="Resident Photo"
          class="w-[68px] h-[82px] object-cover border border-white shadow-md bg-white"
        >
        <p class="text-center mt-1 font-bold text-[6.4px] leading-tight max-w-[70px]">
          <?php echo htmlspecialchars($address, ENT_QUOTES, 'UTF-8'); ?>
        </p>
      </div>

      <!-- Right: Details (shift right a bit + reserve QR space so no overlap) -->
      <div class="flex-1 min-w-0 pr-[78px] pt-1 pl-1.5">
        <div>
          <p class="text-gray-500 uppercase tracking-wider text-[6.2px] mb-0.5">LAST NAME, FIRST NAME, MI.</p>
          <p
            class="font-bold text-[7.9px] leading-[1.08] uppercase break-words max-w-[132px]"
            style="display:-webkit-box;-webkit-line-clamp:3;-webkit-box-orient:vertical;overflow:hidden;"
          >
            <?php echo htmlspecialchars($full_name, ENT_QUOTES, 'UTF-8'); ?>
          </p>

          <p class="text-gray-500 uppercase tracking-wider text-[6.2px] mt-1 mb-0.5">ID NUMBER</p>
          <p class="font-bold text-[8.1px] leading-tight">
            <?php echo htmlspecialchars($display_id_number, ENT_QUOTES, 'UTF-8'); ?>
          </p>
        </div>

        <!-- Grid -->
        <div class="grid grid-cols-3 gap-x-2 gap-y-1.5 mt-2">
          <div class="min-w-0">
            <p class="text-gray-500 uppercase text-[6.1px]">DATE OF BIRTH</p>
            <p class="font-bold text-[6.8px] leading-tight"><?php echo htmlspecialchars($dob, ENT_QUOTES, 'UTF-8'); ?></p>
          </div>
          <div class="min-w-0">
            <p class="text-gray-500 uppercase text-[6.1px]">CIVIL STATUS</p>
            <p class="font-bold text-[6.8px] leading-tight"><?php echo htmlspecialchars($civil_status, ENT_QUOTES, 'UTF-8'); ?></p>
          </div>
          <div class="min-w-0">
            <p class="text-gray-500 uppercase text-[6.1px]">GENDER</p>
            <p class="font-bold text-[6.8px] leading-tight"><?php echo htmlspecialchars($gender, ENT_QUOTES, 'UTF-8'); ?></p>
          </div>

          <div class="min-w-0">
            <p class="text-gray-500 uppercase text-[6.1px]">BARANGAY</p>
            <p class="font-bold text-[6.8px] leading-tight">PAKIAD</p>
          </div>

          <!-- overlap fix: smaller label + wrap -->
          <div class="min-w-0">
            <p class="text-gray-500 uppercase text-[5.6px] leading-[1.05] break-words">MUNICIPALITY/CITY</p>
            <p class="font-bold text-[6.8px] leading-tight">OTON</p>
          </div>

          <div class="min-w-0">
            <p class="text-gray-500 uppercase text-[6.1px]">PROVINCE</p>
            <p class="font-bold text-[6.8px] leading-tight">ILOILO</p>
          </div>

          <div class="min-w-0">
            <p class="text-gray-500 uppercase text-[6.1px]">CONTACT NO</p>
            <p class="font-bold text-[6.8px] leading-tight"><?php echo htmlspecialchars($contact_no, ENT_QUOTES, 'UTF-8'); ?></p>
          </div>
          <div class="min-w-0">
            <p class="text-gray-500 uppercase text-[6.1px]">ISSUED</p>
            <p class="font-bold text-[6.8px] leading-tight"><?php echo htmlspecialchars($issued_display, ENT_QUOTES, 'UTF-8'); ?></p>
          </div>
          <div class="min-w-0">
            <p class="text-gray-500 uppercase text-[6.1px]">VALID UNTIL</p>
            <p class="font-bold text-[6.8px] leading-tight"><?php echo htmlspecialchars($valid_until_display, ENT_QUOTES, 'UTF-8'); ?></p>
          </div>
        </div>
      </div>
    </div>
  </div>

  <!-- ACTIONS UNDER THE ID -->
  <div class="mt-2 flex justify-end gap-2 print:hidden">
    <button
      type="button"
      onclick="window.print()"
      class="px-3 py-1.5 rounded-lg text-sm font-semibold bg-blue-600 text-white hover:bg-blue-700"
    >
      Print
    </button>

    <!-- If you're using <dialog id="idModal"> this closes it -->
    <button
      type="button"
      onclick="document.getElementById('idModal')?.close?.()"
      class="px-3 py-1.5 rounded-lg text-sm font-semibold bg-gray-200 text-gray-800 hover:bg-gray-300"
    >
      Cancel
    </button>
  </div>
</div>