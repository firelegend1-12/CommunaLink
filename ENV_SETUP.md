# Environment Variables Setup Guide

## Quick Start

1. **Copy the example file:**
   ```bash
   cp .env.example .env
   ```

2. **Edit the `.env` file** with your actual credentials:
   ```bash
   # On Windows, use Notepad or any text editor
   notepad .env
   ```

3. **Fill in your credentials** (see sections below)

4. **Never commit `.env` to version control** - it's already in `.gitignore`

## Database Configuration

```env
DB_HOST=localhost
DB_NAME=barangay_reports
DB_USER=root
DB_PASS=your-database-password
DB_CHARSET=utf8mb4
```

## Email Configuration - Mailgun (Recommended)

Mailgun offers 5,000 emails/month FREE forever.

1. Sign up at https://mailgun.com
2. Get your API key from Settings > API Keys
3. Get your domain from Domains section
4. Add to `.env`:

```env
MAILGUN_API_KEY=your-api-key-here
MAILGUN_DOMAIN=mg.yourdomain.com
MAILGUN_FROM_EMAIL=your-email@example.com
MAILGUN_FROM_NAME=CommuniLink Barangay System
```

## Email Configuration - Gmail SMTP (Alternative)

1. Enable 2-Factor Authentication on your Gmail account
2. Generate an App Password:
   - Go to Google Account > Security > App Passwords
   - Generate a new app password
3. Add to `.env`:

```env
EMAIL_SMTP_HOST=smtp.gmail.com
EMAIL_SMTP_PORT=465
EMAIL_SMTP_USERNAME=your-email@gmail.com
EMAIL_SMTP_PASSWORD=your-16-character-app-password
EMAIL_SMTP_SECURE=ssl
EMAIL_FROM_EMAIL=your-email@gmail.com
EMAIL_FROM_NAME=CommuniLink Barangay System
```

## Application Configuration

```env
APP_ENV=development          # development or production
APP_DEBUG=false              # true to show errors, false for production
APP_URL=http://localhost/barangay

# Session timeouts (seconds)
SESSION_LIFETIME=300         # resident/default timeout (5 minutes)
ADMIN_SESSION_LIFETIME=1800  # admin/official timeout (30 minutes)

# Concurrency controls
ENABLE_CONCURRENCY_CAPS=true
ADMIN_MAX_CONCURRENT=2       # max simultaneous admin sessions
OFFICIAL_MAX_CONCURRENT=5    # max simultaneous official sessions (non-admin officials)
AUTO_KICK_DUPLICATE_SESSIONS=false  # true = new login ends older active sessions of same account
BULK_IDLE_MINUTES_DEFAULT=15        # default idle threshold shown in bulk terminate action

# Account provisioning cap (separate from online session cap)
ADMIN_MAX_USERS=5
```

## Security Notes

- ✅ The `.env` file is automatically excluded from version control
- ✅ Never share your `.env` file
- ✅ Use different credentials for development and production
- ✅ Rotate passwords regularly
- ✅ In production, set `APP_DEBUG=false`

## Troubleshooting

### Environment variables not loading?

1. Make sure `.env` file exists in the project root
2. Check file permissions (should be readable by web server)
3. Verify the file format (no spaces around `=`)
4. Check for syntax errors in `.env` file

### Still using old credentials?

The system falls back to defaults if `.env` is missing, but you should create `.env` for security.

