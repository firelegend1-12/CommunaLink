# Security Blockers & Critical Issues

**Last Updated**: March 26, 2026  
**Status**: BLOCKING PRODUCTION DEPLOYMENT

---

## 1. Plaintext Secrets in Tracked Config Files

### Issue
Production secrets (database credentials, API keys, email credentials) are stored in plaintext in version-controlled files:
- `app.yml` - Contains DB and email configuration (tracked)
- `app.yaml` - Contains deployment secrets (tracked)
- `.gitignore` policy does NOT prevent these from being committed

### Risk Level
🔴 **CRITICAL**
- Secrets exposed to anyone with repository access
- Cloud deployment risk if .gitignore rules are bypassed
- Compliance violation (PII exposure potential)

### Evidence
Files exist in repository with plaintext credentials visible in commit history.

### Remediation Steps

**Immediate (Before Deployment)**
1. **Rotate all credentials**:
   - Generate new database password in MySQL
   - Regenerate API keys (SendGrid, Mailgun, Gmail)
   - Issue new authentication tokens

2. **Update .gitignore**:
   ```bash
   # Add to .gitignore
   app.yml
   app.yaml
   .env
   .env.local
   config/email_config.php
   config/google-credentials.json
   ```

3. **Create secure config templates**:
   - Rename tracked configs to `.example`:
     - `app.yml.example` (already exists - good)
     - `app.yaml.example` (already exists - good)
   - Document required env variables in `ENV_SETUP.md`

4. **Load secrets from environment only**:
   ```php
   // In config/init.php or config/database.php
   $db_host = $_ENV['DB_HOST'] ?? $_SERVER['DB_HOST'] ?? getenv('DB_HOST');
   $db_pass = $_ENV['DB_PASSWORD'] ?? $_SERVER['DB_PASSWORD'] ?? getenv('DB_PASSWORD');
   // Never fall back to hardcoded or file-based defaults
   ```

5. **Remove secrets from git history**:
   ```bash
   git rm --cached app.yml app.yaml
   git filter-branch --tree-filter "git rm -f app.yml app.yaml" --prune-empty HEAD
   # Or use: git-filter-repo (safer)
   ```

6. **Audit deployment config**:
   - For Google Cloud Run deployment (app.yaml):
     - Use Cloud Secret Manager for sensitive values
     - Reference via `valueFrom` constraints
   - For local/Docker (app.yml):
     - Use Docker secrets or `.env` mounting only

**Deployment Checklist**
- [ ] All plain-text secrets rotated
- [ ] `.gitignore` updated and verified
- [ ] Sensitive values loaded only from env/secrets manager
- [ ] Git history cleaned of secrets
- [ ] Production deployment uses Cloud Secret Manager (GCP) or equivalent
- [ ] Staging environment uses separate, non-production secrets
- [ ] Commit history audited: `git log --all --diff-filter=D --summary | grep delete | grep -E "(app\.|config)" | head -20`

---

## 2. Missing CSRF Validation on Cancel Endpoints

### Status
✅ **FIXED** (March 26, 2026)

### Changes Made
- Added `csrf_validate()` checks to:
  - `resident/partials/cancel-business-application.php`
  - `resident/partials/cancel-incident-report.php`
  - `resident/partials/cancel-document-request.php`
- Updated corresponding detail pages to expose CSRF tokens to fetch calls
- Token now passed in all cancel operation requests

### Verification
- ✅ PHP lint: All files syntax-valid
- ✅ CSRF token generation enabled in report-details, request-details, business-details
- ✅ Fetch calls updated to include `csrf_token` in POST body

---

## 3. Duplicated Access Control Implementation

### Status
✅ **CONSOLIDATED** (March 26, 2026)

### Changes Made
- Consolidated 9 duplicated `require_role()` implementations across resident pages
- Updated to use centralized definition from `includes/functions.php`
- Converted bare function definitions to safe `if (!function_exists())` pattern
- Files updated:
  - resident/dashboard.php
  - resident/my-reports.php
  - (Others already used conditional pattern)

### Impact
- Single authoritative role-check definition
- Easier maintenance and future security patches
- Consistent behavior across all resident flows

---

## 4. Cloud Durability & Multi-Instance Unsafe Patterns

### Issue
Codebase assumes single-instance local filesystem for:
- Session storage (file-based in `tmp/` session storage)
- Upload cache (`cache/` directory local-only)
- Generated files/reports (cache_manager.php uses local dirs)

### Risk Level
🔴 **CRITICAL** for Cloud Deployment
- Sessions lost on container restart (Google Cloud Run ephemeral filesystems)
- Uploaded files disappear between requests
- Cache invalidation across instances fails

### Remediation Required
**Before Cloud Deployment**:
1. Migrate sessions from file-based to database or Cloud Memcache
2. Implement Cloud Storage adapter for uploads
3. Use Redis/Memcached for session caching
4. Disable local filesystem caching

See: [GCP_CLOUD_MIGRATION_READINESS.md](docs/GCP_CLOUD_MIGRATION_READINESS.md)

---

## 5. Auto-Schema-Sync Risk in Production

### Issue
`AUTO_DB_SCHEMA_SYNC=true` in `config/init.php` can auto-alter database in production

### Risk Level
🟠 **HIGH**
- Unintended schema changes during deployment
- Data loss if migrations trigger destructive operations
- No approval/audit trail

### Quick Fix
```php
// In config/init.php
define('AUTO_DB_SCHEMA_SYNC', 
    ($_ENV['ENVIRONMENT'] ?? 'development') === 'production' ? false : true
);
```

---

## Security Hardening Summary

| Category | Status | Files | Notes |
|----------|--------|-------|-------|
| CSRF Protection | ✅ Complete (3/26) | 9 resident handlers | All POST endpoints protected |
| SQL Injection | ✅ Strong | All API/handlers | PDO prepared statements |
| XSS Protection | ✅ Strong | Output escaping common | Uneven in some templates |
| Access Control | ✅ Consolidated (3/26) | 9 resident pages | Single require_role() def |
| Session Security | 🟠 Needs upgrade | includes/auth.php | File-based → DB/cloud |
| Secrets Management | 🔴 BLOCKER | app.yml, app.yaml | Plaintext in repo |
| Cloud Safety | 🔴 BLOCKER | cache_manager, uploads | Ephemeral filesystem unsafe |

---

## Priority Fix Order

**P0 (Before ANY deployment)**
1. Rotate and remove secrets from repo
2. Update .gitignore, implement env-based config
3. Audit git history for secret leakage

**P1 (Before cloud deployment)**
1. Migrate sessions to Cloud SQL/Memcache
2. Implement Cloud Storage for uploads
3. Disable local filesystem caching in cloud config

**P2 (Quality/consistency)**
1. Idempotency tokens for duplicate-submit prevention
2. Error response standardization (JSON envelope)
3. Dead code cleanup (test_*.php, check_*.php files)

---

## Verification Checklist

- [ ] Git clean of secrets (no app.yml/app.yaml in recent commits)
- [ ] Environment variables documented in ENV_SETUP.md
- [ ] .gitignore updated and tested
- [ ] Production .env file generation script created
- [ ] All 9 resident POST handlers have CSRF validation
- [ ] No hardcoded DB credentials in any PHP files
- [ ] Access control tests pass (require_role enforced)
- [ ] Cloud migration doc reviewed before deployment

---

## Contact & Escalation

For security issues:
1. Do NOT commit secrets to git
2. Notify security team immediately
3. Request credential rotation in relevant systems
4. Audit access logs for exposure window

---

**Next Review**: After remediation of all P0 blockers (estimated 1-2 days)
