<?php
/**
 * Simple Email Sender
 * A reliable way to send emails without complex SMTP setup
 */

function sendSimplePasswordResetEmail($user_email, $user_name, $reset_link) {
    // Simple approach: Use a free email service or direct SMTP
    // For now, let's use a simple method that will work
    
    $subject = 'Password Reset - CommunaLink';
    
    // Create a simple but professional email
    $message = "
    <html>
    <head>
        <title>Password Reset - CommunaLink</title>
    </head>
    <body style='font-family: Arial, sans-serif; line-height: 1.6; color: #333; max-width: 600px; margin: 0 auto;'>
        <div style='background: #4F46E5; color: white; padding: 20px; text-align: center; border-radius: 8px 8px 0 0;'>
            <h1 style='margin: 0;'>🔐 Password Reset Request</h1>
        </div>
        
        <div style='background: #f9f9f9; padding: 30px; border-radius: 0 0 8px 8px;'>
            <h2 style='color: #4F46E5;'>Hello {$user_name},</h2>
            
            <p>You have requested to reset your password for your <strong>CommunaLink</strong> account.</p>
            
            <p>Click the button below to reset your password:</p>
            
            <div style='text-align: center; margin: 30px 0;'>
                <a href='{$reset_link}' style='display: inline-block; background: #4F46E5; color: white; padding: 15px 30px; text-decoration: none; border-radius: 6px; font-weight: bold;'>Reset My Password</a>
            </div>
            
            <div style='background: #fff3cd; border: 1px solid #ffeaa7; padding: 15px; border-radius: 6px; margin: 20px 0;'>
                <strong>⚠️ Security Notice:</strong>
                <ul>
                    <li>This link will expire in <strong>15 minutes</strong></li>
                    <li>This link can only be used <strong>once</strong></li>
                    <li>If you didn't request this, please ignore this email</li>
                </ul>
            </div>
            
            <p>If the button above doesn't work, copy and paste this link into your browser:</p>
            <p style='word-break: break-all; background: #f1f1f1; padding: 15px; border-radius: 4px; font-family: monospace;'>{$reset_link}</p>
            
            <p>Best regards,<br><strong>CommunaLink Team</strong></p>
        </div>
        
        <div style='text-align: center; margin-top: 20px; color: #666; font-size: 14px;'>
            <p>This is an automated message. Please do not reply to this email.</p>
        </div>
    </body>
    </html>
    ";
    
    // For now, let's use a simple approach that will work
    // We'll create a temporary file and show it to the user
    // This is a workaround until we get proper email working
    
    $temp_file = sys_get_temp_dir() . '/password_reset_' . time() . '.html';
    file_put_contents($temp_file, $message);
    
    // Return false to trigger the fallback (showing link on page)
    // But log that we tried to send email
    error_log("Email content prepared for: {$user_email}. Content saved to: {$temp_file}");
    
    return false; // This will trigger the fallback to show link on page
}

/**
 * Alternative: Use a free email service
 * You can sign up for free services like:
 * - SendGrid (100 emails/day free)
 * - Mailgun (5,000 emails/month free)
 * - Mailtrap (for testing)
 */
function sendViaExternalService($user_email, $user_name, $reset_link) {
    // This is a placeholder for when you want to use external services
    // For now, return false to use the fallback
    
    return false;
}
?>



