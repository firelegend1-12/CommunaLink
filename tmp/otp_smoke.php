<?php
require __DIR__ . '/../config/init.php';
require __DIR__ . '/../includes/otp_email_service.php';
$email = 'otp-smoke-' . time() . '@example.com';
$ok = OTPEmailService::storeOTP($pdo, $email, '123456', [
    'fullname' => 'Smoke',
    'email' => $email,
    'password' => 'x'
]);
echo $ok ? 'STORE_OK' : 'STORE_FAIL';
if ($ok) {
    $stmt = $pdo->prepare('DELETE FROM email_verification_otps WHERE email = ?');
    $stmt->execute([$email]);
}
