<?php
require_once '../config/init.php';

function is_valid_qr_token_format(string $token): bool
{
    return preg_match('/\A(?:[a-f0-9]{48}|[a-f0-9]{64})\z/i', $token) === 1;
}

$clientIp = RateLimiter::getClientIP();
$rateLimitStatus = RateLimiter::checkRateLimit('qr_verify', $clientIp);
$isRateLimited = !$rateLimitStatus['allowed'];

$token = strtolower(trim((string)($_GET['t'] ?? '')));
$resident = null;
$failureReason = null;

if ($isRateLimited) {
    $failureReason = 'rate_limited';
} elseif (!is_valid_qr_token_format($token)) {
    $failureReason = 'invalid_token_format';
} else {
    $stmt = $pdo->prepare("SELECT id, first_name, middle_initial, last_name, profile_image_path, id_number, created_at, qr_token_expires_at FROM residents WHERE qr_token = ? LIMIT 1");
    $stmt->execute([$token]);
    $resident = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;

    if (!$resident) {
        $failureReason = 'token_not_found';
    } else {
        $expiresAt = (string)($resident['qr_token_expires_at'] ?? '');
        $isExpired = ($expiresAt !== '' && strtotime($expiresAt) !== false && strtotime($expiresAt) <= time());
        if ($isExpired) {
            $resident = null;
            $failureReason = 'token_expired';
        }
    }
}

RateLimiter::recordAttempt('qr_verify', $clientIp, $resident !== null);

if ($token !== '' || $isRateLimited) {
    try {
        $scanStmt = $pdo->prepare("INSERT INTO resident_qr_scans (resident_id, token_fingerprint, is_valid, failure_reason, ip_address, user_agent) VALUES (?, ?, ?, ?, ?, ?)");
        $scanStmt->execute([
            $resident['id'] ?? null,
            $token !== '' ? hash('sha256', $token) : null,
            $resident ? 1 : 0,
            $failureReason,
            $clientIp,
            substr((string)($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 255)
        ]);
    } catch (Throwable $e) {
        error_log('verify-qr scan log failed: ' . $e->getMessage());
    }
}

$errorTitle = 'Invalid or Expired QR Code';
$errorMessage = 'This resident ID could not be verified. The QR code may be expired, revoked, or does not exist in our records.';
if ($failureReason === 'rate_limited') {
    $minutes = max(1, (int)ceil(($rateLimitStatus['lockout_remaining'] ?? 60) / 60));
    $errorTitle = 'Too Many Verification Attempts';
    $errorMessage = 'Please wait ' . $minutes . ' minute(s) before trying again.';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Resident ID Verification</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; background: #f0f2f5; min-height: 100vh; display: flex; align-items: center; justify-content: center; padding: 20px; }
        .card { background: #fff; border-radius: 16px; box-shadow: 0 4px 24px rgba(0,0,0,0.08); max-width: 420px; width: 100%; overflow: hidden; }
        .header { background: #1a56db; color: #fff; padding: 24px 20px; text-align: center; }
        .header h1 { font-size: 1.25rem; font-weight: 600; }
        .header p { font-size: 0.875rem; opacity: 0.9; margin-top: 4px; }
        .body { padding: 24px 20px; text-align: center; }
        .photo { width: 100px; height: 100px; border-radius: 50%; object-fit: cover; margin: 0 auto 16px; border: 3px solid #e5e7eb; background: #f3f4f6; }
        .name { font-size: 1.125rem; font-weight: 700; color: #111827; margin-bottom: 4px; }
        .id-num { font-size: 0.875rem; color: #6b7280; margin-bottom: 16px; }
        .badge { display: inline-flex; align-items: center; gap: 6px; padding: 8px 16px; border-radius: 9999px; font-size: 0.875rem; font-weight: 600; }
        .badge.valid { background: #d1fae5; color: #065f46; }
        .badge.invalid { background: #fee2e2; color: #991b1b; }
        .footer { padding: 16px 20px; background: #f9fafb; border-top: 1px solid #e5e7eb; text-align: center; font-size: 0.75rem; color: #9ca3af; }
        .error { padding: 40px 20px; text-align: center; }
        .error-icon { font-size: 3rem; margin-bottom: 12px; }
        .error h2 { font-size: 1.125rem; color: #991b1b; margin-bottom: 8px; }
        .error p { color: #6b7280; font-size: 0.875rem; }
    </style>
</head>
<body>
<?php if ($resident): ?>
    <div class="card">
        <div class="header">
            <h1>Resident ID Verified</h1>
            <p>Barangay Resident Verification</p>
        </div>
        <div class="body">
            <?php if (!empty($resident['profile_image_path']) && file_exists('../' . $resident['profile_image_path'])): ?>
                <img src="../<?php echo htmlspecialchars($resident['profile_image_path']); ?>" alt="Photo" class="photo">
            <?php else: ?>
                <img src="../assets/images/default-avatar.png" alt="Photo" class="photo">
            <?php endif; ?>
            <div class="name">
                <?php echo htmlspecialchars($resident['first_name'] . ' ' . ($resident['middle_initial'] ? $resident['middle_initial'] . '. ' : '') . $resident['last_name']); ?>
            </div>
            <div class="id-num">ID: <?php echo htmlspecialchars($resident['id_number'] ?: 'N/A'); ?></div>
            <div class="badge valid">
                <svg width="16" height="16" fill="currentColor" viewBox="0 0 16 16"><path d="M16 8A8 8 0 1 1 0 8a8 8 0 0 1 16 0zm-3.97-3.03a.75.75 0 0 0-1.08.022L7.477 9.417 5.384 7.323a.75.75 0 0 0-1.06 1.06L6.97 11.03a.75.75 0 0 0 1.079-.02l3.992-4.99a.75.75 0 0 0-.01-1.05z"/></svg>
                Valid Resident
            </div>
        </div>
        <div class="footer">
            Verified via QR scan &middot; <?php echo date('F j, Y'); ?>
        </div>
    </div>
<?php else: ?>
    <div class="card">
        <div class="error">
            <div class="error-icon">&#9888;</div>
            <h2><?php echo htmlspecialchars($errorTitle); ?></h2>
            <p><?php echo htmlspecialchars($errorMessage); ?></p>
        </div>
    </div>
<?php endif; ?>
</body>
</html>
