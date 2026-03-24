<?php
/**
 * Mailgun Email Sender
 * 5,000 emails/month FREE forever - much more reliable than Gmail SMTP
 */

class MailgunSender {
    private $api_key;
    private $domain;
    private $from_email;
    private $from_name;
    
    public function __construct() {
        // Load configuration
        if (file_exists(__DIR__ . '/../config/email_config.php')) {
            require_once __DIR__ . '/../config/email_config.php';
        }
        
        $this->api_key = defined('MAILGUN_API_KEY') ? MAILGUN_API_KEY : '';
        $this->domain = defined('MAILGUN_DOMAIN') ? MAILGUN_DOMAIN : '';
        $this->from_email = defined('MAILGUN_FROM_EMAIL') ? MAILGUN_FROM_EMAIL : 'noreply@yourbarangay.com';
        $this->from_name = defined('MAILGUN_FROM_NAME') ? MAILGUN_FROM_NAME : 'CommuniLink Barangay System';
    }
    
    /**
     * Send password reset email via Mailgun
     */
    public function sendPasswordResetEmail($user_email, $user_name, $reset_link) {
        try {
            // Check if Mailgun is configured
            if (empty($this->api_key) || empty($this->domain)) {
                error_log("Mailgun not configured - missing API key or domain");
                return false;
            }
            
            // Create email content
            $subject = 'Password Reset - CommuniLink';
            $message = $this->getPasswordResetEmailTemplate($user_name, $reset_link);
            
            // Send via Mailgun API
            $result = $this->sendEmail($user_email, $subject, $message);
            
            if ($result) {
                error_log("Mailgun email sent successfully to: {$user_email}");
                return true;
            } else {
                error_log("Mailgun email failed for: {$user_email}");
                return false;
            }
            
        } catch (Exception $e) {
            error_log("Mailgun error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Send email via Mailgun API
     */
    private function sendEmail($to_email, $subject, $message) {
        try {
            // Prepare the data
            $data = array(
                'from' => "{$this->from_name} <{$this->from_email}>",
                'to' => $to_email,
                'subject' => $subject,
                'html' => $message
            );
            
            // Create cURL request
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, "https://api.mailgun.net/v3/{$this->domain}/messages");
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_HTTPHEADER, array(
                'Authorization: Basic ' . base64_encode('api:' . $this->api_key)
            ));
            
            // Execute the request
            $response = curl_exec($ch);
            $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            
            // Check response
            if ($http_code === 200) {
                $result = json_decode($response, true);
                if (isset($result['id'])) {
                    return true; // Success
                }
            }
            
            error_log("Mailgun API error: HTTP {$http_code}, Response: {$response}");
            return false;
            
        } catch (Exception $e) {
            error_log("Mailgun cURL error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Get HTML email template for password reset
     */
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
                .warning ul { margin: 10px 0; padding-left: 20px; }
                .warning li { margin: 5px 0; }
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
                                <li>This link will expire in <strong>15 minutes</strong></li>
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

/**
 * Simple function to send password reset email via Mailgun
 */
function sendPasswordResetEmailViaMailgun($user_email, $user_name, $reset_link) {
    $sender = new MailgunSender();
    return $sender->sendPasswordResetEmail($user_email, $user_name, $reset_link);
}
?>
