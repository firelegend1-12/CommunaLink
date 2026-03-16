<?php
/**
 * Gmail SMTP Sender - FIXED VERSION
 * Properly handles Gmail's authentication requirements
 */

class GmailSMTPSenderFixed {
    private $host = 'smtp.gmail.com';
    private $port = 465; // Default to 465 (SSL), will use config if available
    private $username;
    private $password;
    private $from_email;
    private $from_name;
    private $secure = 'ssl'; // ssl or tls
    
    public function __construct() {
        // Load configuration
        if (file_exists(__DIR__ . '/../config/email_config.php')) {
            require_once __DIR__ . '/../config/email_config.php';
        }
        
        $this->host = defined('EMAIL_SMTP_HOST') ? EMAIL_SMTP_HOST : 'smtp.gmail.com';
        $this->port = defined('EMAIL_SMTP_PORT') ? (int)EMAIL_SMTP_PORT : 465;
        $this->username = defined('EMAIL_SMTP_USERNAME') ? EMAIL_SMTP_USERNAME : 'shaunrosario023@gmail.com';
        $this->password = defined('EMAIL_SMTP_PASSWORD') ? EMAIL_SMTP_PASSWORD : '';
        $this->from_email = defined('EMAIL_FROM_EMAIL') ? EMAIL_FROM_EMAIL : 'shaunrosario023@gmail.com';
        $this->from_name = defined('EMAIL_FROM_NAME') ? EMAIL_FROM_NAME : 'CommuniLink Barangay System';
        $this->secure = defined('EMAIL_SMTP_SECURE') ? EMAIL_SMTP_SECURE : 'ssl';
    }
    
    /**
     * Send password reset email via Gmail SMTP
     */
    public function sendPasswordResetEmail($user_email, $user_name, $reset_link) {
        try {
            // Create email content
            $subject = 'Password Reset - CommuniLink';
            $message = $this->getPasswordResetEmailTemplate($user_name, $reset_link);
            
            // Send via Gmail SMTP
            $result = $this->sendEmail($user_email, $subject, $message);
            
            if ($result) {
                error_log("Gmail SMTP email sent successfully to: {$user_email}");
                return true;
            } else {
                error_log("Gmail SMTP email failed for: {$user_email}");
                return false;
            }
            
        } catch (Exception $e) {
            error_log("Gmail SMTP error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Send email via Gmail SMTP with proper STARTTLS
     */
    private function sendEmail($to_email, $subject, $message) {
        $socket = null;
        try {
            // Use SSL for port 465, or plain connection for port 587 with STARTTLS
            if ($this->port == 465 && $this->secure == 'ssl') {
                // Port 465: Use SSL connection directly
                $socket = @fsockopen('ssl://' . $this->host, $this->port, $errno, $errstr, 30);
            } else {
                // Port 587: Plain connection, then STARTTLS
                $socket = @fsockopen($this->host, $this->port, $errno, $errstr, 30);
            }
            
            if (!$socket) {
                error_log("Gmail SMTP connection failed to {$this->host}:{$this->port} - $errstr ($errno)");
                return false;
            }
            
            // Read server greeting
            $response = $this->readResponse($socket);
            if (substr($response, 0, 3) != '220') {
                error_log("Gmail SMTP greeting failed: $response");
                fclose($socket);
                return false;
            }
            
            // Send EHLO
            fwrite($socket, "EHLO localhost\r\n");
            $response = $this->readResponse($socket);
            if (substr($response, 0, 3) != '250') {
                error_log("Gmail SMTP EHLO failed: $response");
                fclose($socket);
                return false;
            }
            
            // For port 587, start TLS
            if ($this->port == 587 && $this->secure == 'tls') {
                fwrite($socket, "STARTTLS\r\n");
                $response = $this->readResponse($socket);
                if (substr($response, 0, 3) != '220') {
                    error_log("Gmail SMTP STARTTLS failed: $response");
                    fclose($socket);
                    return false;
                }
                
                // Enable crypto
                if (!stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
                    error_log("Gmail SMTP TLS encryption failed");
                    fclose($socket);
                    return false;
                }
                
                // Send EHLO again after TLS
                fwrite($socket, "EHLO localhost\r\n");
                $response = $this->readResponse($socket);
                if (substr($response, 0, 3) != '250') {
                    error_log("Gmail SMTP EHLO after TLS failed: $response");
                    fclose($socket);
                    return false;
                }
            }
            fwrite($socket, "EHLO localhost\r\n");
            $response = $this->readResponse($socket);
            if (substr($response, 0, 3) != '250') {
                error_log("Gmail SMTP EHLO after TLS failed: $response");
                fclose($socket);
                return false;
            }
            
            // Start authentication
            fwrite($socket, "AUTH LOGIN\r\n");
            $response = $this->readResponse($socket);
            if (substr($response, 0, 3) != '334') {
                error_log("Gmail SMTP AUTH LOGIN failed: $response");
                fclose($socket);
                return false;
            }
            
            // Send username
            fwrite($socket, base64_encode($this->username) . "\r\n");
            $response = $this->readResponse($socket);
            if (substr($response, 0, 3) != '334') {
                error_log("Gmail SMTP username failed: $response");
                fclose($socket);
                return false;
            }
            
            // Send password
            fwrite($socket, base64_encode($this->password) . "\r\n");
            $response = $this->readResponse($socket);
            if (substr($response, 0, 3) != '235') {
                error_log("Gmail SMTP authentication failed: $response");
                fclose($socket);
                return false;
            }
            
            // Set sender
            fwrite($socket, "MAIL FROM: <{$this->from_email}>\r\n");
            $response = $this->readResponse($socket);
            if (substr($response, 0, 3) != '250') {
                error_log("Gmail SMTP MAIL FROM failed: $response");
                fclose($socket);
                return false;
            }
            
            // Set recipient
            fwrite($socket, "RCPT TO: <{$to_email}>\r\n");
            $response = $this->readResponse($socket);
            if (substr($response, 0, 3) != '250') {
                error_log("Gmail SMTP RCPT TO failed: $response");
                fclose($socket);
                return false;
            }
            
            // Send data
            fwrite($socket, "DATA\r\n");
            $response = $this->readResponse($socket);
            if (substr($response, 0, 3) != '354') {
                error_log("Gmail SMTP DATA failed: $response");
                fclose($socket);
                return false;
            }
            
            // Send email headers and content
            $headers = "From: {$this->from_name} <{$this->from_email}>\r\n";
            $headers .= "To: {$to_email}\r\n";
            $headers .= "Subject: {$subject}\r\n";
            $headers .= "MIME-Version: 1.0\r\n";
            $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
            $headers .= "Content-Transfer-Encoding: 8bit\r\n";
            $headers .= "\r\n";
            
            fwrite($socket, $headers . $message . "\r\n.\r\n");
            $response = $this->readResponse($socket);
            if (substr($response, 0, 3) != '250') {
                error_log("Gmail SMTP message send failed: $response");
                fclose($socket);
                return false;
            }
            
            // Quit
            fwrite($socket, "QUIT\r\n");
            fclose($socket);
            
            return true;
            
        } catch (Exception $e) {
            error_log("Gmail SMTP exception: " . $e->getMessage());
            if (isset($socket)) {
                fclose($socket);
            }
            return false;
        }
    }
    
    /**
     * Read SMTP response
     */
    private function readResponse($socket) {
        $response = '';
        while ($line = fgets($socket, 515)) {
            $response .= $line;
            if (substr($line, 3, 1) == ' ') {
                break;
            }
        }
        return trim($response);
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
 * Simple function to send password reset email via Gmail SMTP (FIXED)
 */
function sendPasswordResetEmailViaGmailSMTPFixed($user_email, $user_name, $reset_link) {
    $sender = new GmailSMTPSenderFixed();
    return $sender->sendPasswordResetEmail($user_email, $user_name, $reset_link);
}
?>



