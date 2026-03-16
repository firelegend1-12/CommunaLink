<?php
/**
 * Simple SMTP Sender using PHP's mail() function
 * Fallback when SMTP connections fail
 */

class SimpleSMTP {
    
    public function sendPasswordResetEmail($user_email, $user_name, $reset_link) {
        // Load email config
        if (file_exists(__DIR__ . '/../config/email_config.php')) {
            require_once __DIR__ . '/../config/email_config.php';
        }
        
        $from_email = defined('EMAIL_FROM_EMAIL') ? EMAIL_FROM_EMAIL : 'noreply@localhost';
        $from_name = defined('EMAIL_FROM_NAME') ? EMAIL_FROM_NAME : 'CommuniLink Barangay System';
        
        $subject = 'Password Reset - CommuniLink';
        $message = $this->getPasswordResetEmailTemplate($user_name, $reset_link);
        
        // Email headers
        $headers = "From: {$from_name} <{$from_email}>\r\n";
        $headers .= "Reply-To: {$from_email}\r\n";
        $headers .= "MIME-Version: 1.0\r\n";
        $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
        $headers .= "X-Mailer: PHP/" . phpversion();
        
        // Try to send using PHP's mail() function
        $result = @mail($user_email, $subject, $message, $headers);
        
        if ($result) {
            error_log("Simple mail() email sent successfully to: {$user_email}");
            return true;
        } else {
            error_log("Simple mail() email failed for: {$user_email}");
            return false;
        }
    }
    
    private function getPasswordResetEmailTemplate($user_name, $reset_link) {
        return "
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset='UTF-8'>
            <meta name='viewport' content='width=device-width, initial-scale=1.0'>
            <title>Password Reset - CommuniLink</title>
            <style>
                body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; margin: 0; padding: 0; }
                .container { max-width: 600px; margin: 0 auto; background: #ffffff; }
                .header { background: #4F46E5; color: white; padding: 30px; text-align: center; }
                .header h1 { margin: 0; font-size: 28px; }
                .content { padding: 40px 30px; background: #f9f9f9; }
                .email-body { background: white; padding: 30px; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
                .button { display: inline-block; background: #4F46E5; color: white; padding: 15px 30px; text-decoration: none; border-radius: 6px; font-weight: bold; font-size: 16px; }
                .warning { background: #fff3cd; border: 1px solid #ffeaa7; padding: 20px; border-radius: 6px; margin: 25px 0; }
                .footer { text-align: center; padding: 20px; color: #666; font-size: 14px; background: #f1f1f1; }
                .link-box { background: #f8f9fa; border: 1px solid #dee2e6; padding: 15px; border-radius: 4px; margin: 20px 0; word-break: break-all; font-family: monospace; font-size: 12px; }
            </style>
        </head>
        <body>
            <div class='container'>
                <div class='header'>
                    <h1>🔐 Password Reset Request</h1>
                </div>
                
                <div class='content'>
                    <div class='email-body'>
                        <h2 style='color: #4F46E5; margin-top: 0;'>Hello {$user_name},</h2>
                        
                        <p>You have requested to reset your password for your <strong>CommuniLink</strong> account.</p>
                        
                        <p>Click the button below to reset your password:</p>
                        
                        <div style='text-align: center; margin: 30px 0;'>
                            <a href='{$reset_link}' class='button'>Reset My Password</a>
                        </div>
                        
                        <div class='warning'>
                            <strong>⚠️ Security Notice:</strong>
                            <ul>
                                <li>This link will expire in <strong>1 hour</strong></li>
                                <li>This link can only be used <strong>once</strong></li>
                                <li>If you didn't request this, please ignore this email</li>
                            </ul>
                        </div>
                        
                        <p>If the button above doesn't work, copy and paste this link into your browser:</p>
                        <div class='link-box'>{$reset_link}</div>
                        
                        <p style='margin-top: 30px;'>Best regards,<br><strong>CommuniLink Team</strong></p>
                    </div>
                </div>
                
                <div class='footer'>
                    <p>This is an automated message. Please do not reply to this email.</p>
                    <p>CommuniLink Barangay System - Your Partner in Progress</p>
                </div>
            </div>
        </body>
        </html>
        ";
    }
}

