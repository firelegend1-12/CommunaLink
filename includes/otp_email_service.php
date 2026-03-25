<?php
/**
 * OTP Email Service using PHPMailer with Gmail SMTP
 * Sends OTP verification codes during registration
 */

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../config/email_config.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

class OTPEmailService {
    
    /**
     * Generate a 6-digit OTP code
     */
    public static function generateOTP(): string {
        return str_pad((string)random_int(100000, 999999), 6, '0', STR_PAD_LEFT);
    }

    /**
     * Send OTP verification email via Gmail SMTP using PHPMailer
     */
    public static function sendOTP(string $toEmail, string $toName, string $otpCode): bool {
        $mail = new PHPMailer(true);

        try {
            // SMTP configuration
            $mail->isSMTP();
            $mail->Host       = defined('EMAIL_SMTP_HOST') ? EMAIL_SMTP_HOST : 'smtp.gmail.com';
            $mail->SMTPAuth   = true;
            $mail->Username   = defined('EMAIL_SMTP_USERNAME') ? EMAIL_SMTP_USERNAME : '';
            $mail->Password   = defined('EMAIL_SMTP_PASSWORD') ? EMAIL_SMTP_PASSWORD : '';
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
            $mail->Port       = defined('EMAIL_SMTP_PORT') ? EMAIL_SMTP_PORT : 465;

            if (empty($mail->Username) || empty($mail->Password)) {
                error_log("OTP Email: Gmail SMTP credentials not configured.");
                return false;
            }

            // Sender & recipient
            $fromEmail = defined('EMAIL_FROM_EMAIL') && EMAIL_FROM_EMAIL !== '' 
                ? EMAIL_FROM_EMAIL 
                : $mail->Username;
            $fromName = defined('EMAIL_FROM_NAME') ? EMAIL_FROM_NAME : 'CommunaLink Barangay System';

            $mail->setFrom($fromEmail, $fromName);
            $mail->addAddress($toEmail, $toName);

            // Email content
            $mail->isHTML(true);
            $mail->Subject = 'Your Verification Code - CommunaLink';
            $mail->Body    = self::getOTPEmailTemplate($toName, $otpCode);
            $mail->AltBody = "Hi $toName,\n\nYour CommunaLink verification code is: $otpCode\n\nThis code expires in 10 minutes.\n\nIf you did not request this, please ignore this email.";

            $mail->send();
            error_log("OTP email sent successfully to: $toEmail");
            return true;

        } catch (Exception $e) {
            error_log("OTP email failed for $toEmail: " . $mail->ErrorInfo);
            return false;
        }
    }

    /**
     * Store OTP in database
     */
    public static function storeOTP(PDO $pdo, string $email, string $otpCode, array $registrationData): bool {
        try {
            // Delete any existing OTPs for this email
            $stmt = $pdo->prepare("DELETE FROM email_verification_otps WHERE email = ?");
            $stmt->execute([$email]);

            // Insert new OTP (expires in 10 minutes)
            $stmt = $pdo->prepare(
                "INSERT INTO email_verification_otps (email, otp_code, registration_data, expires_at) 
                 VALUES (?, ?, ?, DATE_ADD(NOW(), INTERVAL 10 MINUTE))"
            );
            $stmt->execute([
                $email,
                password_hash($otpCode, PASSWORD_DEFAULT),
                json_encode($registrationData)
            ]);

            return true;
        } catch (\PDOException $e) {
            error_log("OTP store failed: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Verify OTP code
     * Returns the registration data on success, false on failure
     */
    public static function verifyOTP(PDO $pdo, string $email, string $otpCode) {
        try {
            $stmt = $pdo->prepare(
                "SELECT * FROM email_verification_otps 
                 WHERE email = ? AND expires_at > NOW() 
                 ORDER BY created_at DESC LIMIT 1"
            );
            $stmt->execute([$email]);
            $record = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$record) {
                return false;
            }

            // Check max attempts (5)
            if ($record['attempts'] >= 5) {
                return 'max_attempts';
            }

            // Increment attempts
            $updateStmt = $pdo->prepare("UPDATE email_verification_otps SET attempts = attempts + 1 WHERE id = ?");
            $updateStmt->execute([$record['id']]);

            // Verify the OTP hash
            if (!password_hash_verify_otp($otpCode, $record['otp_code'])) {
                return false;
            }

            // OTP is valid — delete it and return registration data
            $deleteStmt = $pdo->prepare("DELETE FROM email_verification_otps WHERE id = ?");
            $deleteStmt->execute([$record['id']]);

            return json_decode($record['registration_data'], true);

        } catch (\PDOException $e) {
            error_log("OTP verify failed: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Send password reset email via Gmail SMTP using PHPMailer
     */
    public static function sendPasswordResetEmail(string $toEmail, string $toName, string $resetLink): bool {
        $mail = new PHPMailer(true);

        try {
            $mail->isSMTP();
            $mail->Host       = defined('EMAIL_SMTP_HOST') ? EMAIL_SMTP_HOST : 'smtp.gmail.com';
            $mail->SMTPAuth   = true;
            $mail->Username   = defined('EMAIL_SMTP_USERNAME') ? EMAIL_SMTP_USERNAME : '';
            $mail->Password   = defined('EMAIL_SMTP_PASSWORD') ? EMAIL_SMTP_PASSWORD : '';
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
            $mail->Port       = defined('EMAIL_SMTP_PORT') ? EMAIL_SMTP_PORT : 465;

            if (empty($mail->Username) || empty($mail->Password)) {
                error_log("Password Reset Email: Gmail SMTP credentials not configured.");
                return false;
            }

            $fromEmail = defined('EMAIL_FROM_EMAIL') && EMAIL_FROM_EMAIL !== '' 
                ? EMAIL_FROM_EMAIL 
                : $mail->Username;
            $fromName = defined('EMAIL_FROM_NAME') ? EMAIL_FROM_NAME : 'CommunaLink Barangay System';

            $mail->setFrom($fromEmail, $fromName);
            $mail->addAddress($toEmail, $toName);

            $mail->isHTML(true);
            $mail->Subject = 'Password Reset - CommunaLink';
            $mail->Body    = self::getPasswordResetEmailTemplate($toName, $resetLink);
            $mail->AltBody = "Hi $toName,\n\nWe received a request to reset your CommunaLink password.\n\nClick the link below to reset your password:\n$resetLink\n\nThis link expires in 1 hour.\n\nIf you did not request this, please ignore this email.";

            $mail->send();
            error_log("Password reset email sent successfully to: $toEmail");
            return true;

        } catch (Exception $e) {
            error_log("Password reset email failed for $toEmail: " . $mail->ErrorInfo);
            return false;
        }
    }

    /**
     * HTML email template for password reset
     */
    private static function getPasswordResetEmailTemplate(string $name, string $resetLink): string {
        $escapedName = htmlspecialchars($name);
        $escapedLink = htmlspecialchars($resetLink);
        return '<!DOCTYPE html>
<html>
<head><meta charset="UTF-8"></head>
<body style="margin:0;padding:0;background-color:#f1f5f9;font-family:\'Segoe UI\',Tahoma,Geneva,Verdana,sans-serif;">
<table width="100%" cellpadding="0" cellspacing="0" style="background-color:#f1f5f9;padding:40px 0;">
<tr><td align="center">
<table width="480" cellpadding="0" cellspacing="0" style="background-color:#ffffff;border-radius:16px;overflow:hidden;box-shadow:0 4px 24px rgba(0,0,0,0.08);">
    <tr>
        <td style="background:linear-gradient(135deg,#4f46e5,#7c3aed);padding:32px 40px;text-align:center;">
            <h1 style="color:#ffffff;margin:0;font-size:22px;font-weight:700;letter-spacing:0.5px;">CommunaLink</h1>
            <p style="color:#c7d2fe;margin:6px 0 0;font-size:13px;">Barangay Management System</p>
        </td>
    </tr>
    <tr>
        <td style="padding:40px;">
            <h2 style="color:#1e293b;margin:0 0 8px;font-size:20px;">Password Reset</h2>
            <p style="color:#64748b;font-size:14px;line-height:1.6;margin:0 0 28px;">
                Hi <strong>' . $escapedName . '</strong>, we received a request to reset your password. Click the button below to create a new password.
            </p>
            <div style="text-align:center;margin:0 0 28px;">
                <a href="' . $escapedLink . '" style="display:inline-block;background:linear-gradient(135deg,#4f46e5,#7c3aed);color:#ffffff;text-decoration:none;padding:14px 40px;border-radius:8px;font-size:16px;font-weight:600;letter-spacing:0.3px;">Reset Password</a>
            </div>
            <p style="color:#94a3b8;font-size:13px;line-height:1.5;margin:0 0 12px;">
                Or copy and paste this link into your browser:
            </p>
            <p style="color:#4f46e5;font-size:12px;word-break:break-all;background:#f8fafc;padding:12px;border-radius:8px;border:1px solid #e2e8f0;margin:0 0 28px;">
                ' . $escapedLink . '
            </p>
            <p style="color:#94a3b8;font-size:13px;line-height:1.5;margin:0 0 8px;">
                <strong>This link expires in 1 hour.</strong>
            </p>
            <p style="color:#94a3b8;font-size:13px;line-height:1.5;margin:0;">
                If you did not request a password reset, you can safely ignore this email. Your password will remain unchanged.
            </p>
        </td>
    </tr>
    <tr>
        <td style="background-color:#f8fafc;padding:20px 40px;text-align:center;border-top:1px solid #e2e8f0;">
            <p style="color:#94a3b8;font-size:11px;margin:0;">&copy; ' . date('Y') . ' CommunaLink Barangay System. All rights reserved.</p>
        </td>
    </tr>
</table>
</td></tr>
</table>
</body>
</html>';
    }

    /**
     * Cleanup expired OTPs
     */
    public static function cleanupExpired(PDO $pdo): void {
        try {
            $pdo->exec("DELETE FROM email_verification_otps WHERE expires_at < NOW()");
        } catch (\PDOException $e) {
            error_log("OTP cleanup failed: " . $e->getMessage());
        }
    }

    /**
     * HTML email template for OTP
     */
    private static function getOTPEmailTemplate(string $name, string $otpCode): string {
        return '<!DOCTYPE html>
<html>
<head><meta charset="UTF-8"></head>
<body style="margin:0;padding:0;background-color:#f1f5f9;font-family:\'Segoe UI\',Tahoma,Geneva,Verdana,sans-serif;">
<table width="100%" cellpadding="0" cellspacing="0" style="background-color:#f1f5f9;padding:40px 0;">
<tr><td align="center">
<table width="480" cellpadding="0" cellspacing="0" style="background-color:#ffffff;border-radius:16px;overflow:hidden;box-shadow:0 4px 24px rgba(0,0,0,0.08);">
    <tr>
        <td style="background:linear-gradient(135deg,#4f46e5,#7c3aed);padding:32px 40px;text-align:center;">
            <h1 style="color:#ffffff;margin:0;font-size:22px;font-weight:700;letter-spacing:0.5px;">CommunaLink</h1>
            <p style="color:#c7d2fe;margin:6px 0 0;font-size:13px;">Barangay Management System</p>
        </td>
    </tr>
    <tr>
        <td style="padding:40px;">
            <h2 style="color:#1e293b;margin:0 0 8px;font-size:20px;">Email Verification</h2>
            <p style="color:#64748b;font-size:14px;line-height:1.6;margin:0 0 28px;">
                Hi <strong>' . htmlspecialchars($name) . '</strong>, use the code below to verify your email address and complete your registration.
            </p>
            <div style="background-color:#f8fafc;border:2px dashed #e2e8f0;border-radius:12px;padding:24px;text-align:center;margin:0 0 28px;">
                <span style="font-size:36px;font-weight:800;letter-spacing:8px;color:#4f46e5;">' . htmlspecialchars($otpCode) . '</span>
            </div>
            <p style="color:#94a3b8;font-size:13px;line-height:1.5;margin:0 0 8px;">
                <strong>This code expires in 10 minutes.</strong>
            </p>
            <p style="color:#94a3b8;font-size:13px;line-height:1.5;margin:0;">
                If you did not create an account on CommunaLink, you can safely ignore this email.
            </p>
        </td>
    </tr>
    <tr>
        <td style="background-color:#f8fafc;padding:20px 40px;text-align:center;border-top:1px solid #e2e8f0;">
            <p style="color:#94a3b8;font-size:11px;margin:0;">&copy; ' . date('Y') . ' CommunaLink Barangay System. All rights reserved.</p>
        </td>
    </tr>
</table>
</td></tr>
</table>
</body>
</html>';
    }
}

/**
 * Verify OTP using password_verify (since we hash with password_hash)
 */
function password_hash_verify_otp(string $otpCode, string $hash): bool {
    return password_verify($otpCode, $hash);
}
