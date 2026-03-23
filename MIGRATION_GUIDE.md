# Migration Guide: Moving from Hardcoded Credentials to Environment Variables

## ✅ What Was Changed

1. **Created environment variable system:**
   - `config/env_loader.php` - Loads variables from `.env` file
   - `.env.example` - Template file with all configuration options
   - `.gitignore` - Excludes `.env` from version control

2. **Updated configuration files:**
   - `config/database.php` - Now reads from environment variables
   - `config/email_config.php` - Now reads from environment variables

3. **Improved error handling:**
   - Database errors no longer expose sensitive information in production

## 🔄 Migration Steps

### Step 1: Create Your .env File

**Option A: Using the setup script (Recommended)**
```bash
php setup_env.php
```

**Option B: Manual copy**
```bash
# Windows PowerShell
Copy-Item .env.example .env

# Linux/Mac
cp .env.example .env
```

### Step 2: Add Your Current Credentials

Edit the `.env` file and add your actual credentials:

**Database Configuration:**
```env
DB_HOST=localhost
DB_NAME=barangay_reports
DB_USER=root
DB_PASS=                    # Your database password (if any)
DB_CHARSET=utf8mb4
```

**Gmail SMTP Configuration (Current Setup):**
```env
EMAIL_SMTP_HOST=smtp.gmail.com
EMAIL_SMTP_PORT=465
EMAIL_SMTP_USERNAME=shaunrosario023@gmail.com
EMAIL_SMTP_PASSWORD=ptvg nwbe worp iidc
EMAIL_SMTP_SECURE=ssl
EMAIL_FROM_EMAIL=shaunrosario023@gmail.com
EMAIL_FROM_NAME=CommuniLink Barangay System
```

**Or use Mailgun (Recommended for production):**
```env
MAILGUN_API_KEY=your-api-key
MAILGUN_DOMAIN=mg.yourdomain.com
MAILGUN_FROM_EMAIL=your-email@example.com
MAILGUN_FROM_NAME=CommuniLink Barangay System
```

### Step 3: Test the Application

1. Make sure your `.env` file is in the project root
2. Access your application - it should work exactly as before
3. Check that database connections work
4. Test email functionality (if configured)

### Step 4: Verify .env is Ignored

The `.env` file should NOT appear in your version control:
```bash
git status
```

If `.env` appears, make sure `.gitignore` includes it.

## 🔒 Security Benefits

✅ **Credentials no longer in code** - Safe to commit to version control  
✅ **Environment-specific configs** - Different settings for dev/staging/production  
✅ **Easy credential rotation** - Update `.env` without touching code  
✅ **Team collaboration** - Each developer has their own `.env`  

## ⚠️ Important Notes

1. **Never commit `.env` to version control** - It's already in `.gitignore`
2. **Keep `.env.example` updated** - This is the template for new setups
3. **Use different credentials for production** - Never use dev credentials in production
4. **Backup your `.env` file securely** - But don't store it in version control

## 🆘 Troubleshooting

### Application not working after migration?

1. **Check if `.env` file exists:**
   ```bash
   ls -la .env  # Linux/Mac
   dir .env     # Windows
   ```

2. **Verify file format:**
   - No spaces around `=` sign
   - One variable per line
   - No quotes needed (unless value contains spaces)

3. **Check file permissions:**
   - File should be readable by web server
   - On Linux: `chmod 644 .env`

4. **Fallback behavior:**
   - If `.env` doesn't exist, system uses default values
   - This ensures backward compatibility

### Still seeing old credentials?

- Clear any PHP opcode cache (if using OPcache)
- Restart your web server
- Make sure you're editing the correct `.env` file

## 📝 Next Steps

After migration, consider:
1. Rotating your passwords/API keys
2. Setting up different `.env` files for different environments
3. Using a secrets management service for production (AWS Secrets Manager, etc.)

## Legacy Endpoint Retirement (Notifications)

Use this sequence for staggered/cloud rollouts of the deprecated endpoint at resident/partials/mark-notifications-read.php:

1. Keep `LEGACY_MARK_NOTIFICATIONS_READ_ENABLED=true` for one deployment cycle.
2. Monitor traffic/logs for legacy endpoint hits.
3. Set `LEGACY_MARK_NOTIFICATIONS_READ_ENABLED=false` to retire safely with 410 Gone.

