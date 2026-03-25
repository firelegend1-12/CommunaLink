<?php
/**
 * Email Configuration
 * Configure your email settings here
 * 
 * IMPORTANT: Credentials are now loaded from .env file
 * Copy .env.example to .env and fill in your credentials
 */

// Load environment variables
require_once __DIR__ . '/env_loader.php';

// ========================================
// MAILGUN CONFIGURATION (RECOMMENDED)
// ========================================
// 5,000 emails/month FREE forever - much more reliable than Gmail SMTP
// Configure these in your .env file
define('MAILGUN_API_KEY', env('MAILGUN_API_KEY', ''));
define('MAILGUN_DOMAIN', env('MAILGUN_DOMAIN', ''));
define('MAILGUN_FROM_EMAIL', env('MAILGUN_FROM_EMAIL', 'noreply@yourbarangay.com'));
define('MAILGUN_FROM_NAME', env('MAILGUN_FROM_NAME', 'CommunaLink Barangay System'));

// ========================================
// GMAIL SMTP CONFIGURATION (ALTERNATIVE)
// ========================================
// Uses Gmail SMTP - FREE FOREVER
// Configure these in your .env file
define('EMAIL_SMTP_HOST', env('EMAIL_SMTP_HOST', 'smtp.gmail.com'));
define('EMAIL_SMTP_PORT', (int)env('EMAIL_SMTP_PORT', 465));
define('EMAIL_SMTP_USERNAME', env('EMAIL_SMTP_USERNAME', ''));
define('EMAIL_SMTP_PASSWORD', env('EMAIL_SMTP_PASSWORD', ''));
define('EMAIL_SMTP_SECURE', env('EMAIL_SMTP_SECURE', 'ssl'));
define('EMAIL_FROM_EMAIL', env('EMAIL_FROM_EMAIL', ''));
define('EMAIL_FROM_NAME', env('EMAIL_FROM_NAME', 'CommunaLink Barangay System'));

// ========================================
// SETUP INSTRUCTIONS
// ========================================

// OPTION 1: Mailgun (RECOMMENDED - FREE FOREVER)
// 1. Go to mailgun.com and sign up for free
// 2. Get your API key from Settings > API Keys
// 3. Get your domain from Domains section
// 4. Add them above: MAILGUN_API_KEY and MAILGUN_DOMAIN

// OPTION 2: Gmail SMTP (ALTERNATIVE - FREE FOREVER)
// 1. Enable 2-Factor Authentication on your Gmail account
// 2. Generate an App Password: Google Account > Security > App Passwords
// 3. Add the app password to your .env file: EMAIL_SMTP_PASSWORD=your-app-password
// 4. Make sure "Less secure app access" is OFF

// ========================================
// OTHER FREE EMAIL SERVICES
// ========================================
// - SendGrid: 100 emails/day free for 60 days, then $19.95/month
// - Mailtrap: Free testing environment forever
// - SendinBlue: 300 emails/day free forever

// ========================================
// SECURITY NOTES
// ========================================
// - Never commit real passwords to version control
// - Use environment variables in production
// - Consider using OAuth2 for Gmail instead of app passwords
?>
