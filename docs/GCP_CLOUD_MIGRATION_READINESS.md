# CommunaLink Google Cloud Migration Readiness

## 1. Recommended Target Architecture

- Runtime: Google Cloud Run (preferred) or App Engine Standard (existing config style).
- Database: Cloud SQL for MySQL (private IP preferred where possible).
- Secrets: Secret Manager (DB password, SMTP/API credentials).
- Object storage: Cloud Storage bucket for user uploads and media.
- Jobs: Cloud Scheduler -> authenticated HTTP endpoint (or Cloud Run Jobs) for recurring tasks.
- Logging/monitoring: Cloud Logging + Error Reporting + uptime checks.

## 2. Current Codebase Findings

### Ready/Good
- Environment-driven config exists via `config/env_loader.php`.
- Session security uses secure cookie settings and proxy-aware HTTPS detection in `config/init.php` and `includes/auth.php`.
- Most DB access uses PDO prepared statements.

### Risks/Blockers
- Runtime schema DDL executes in `config/init.php` on normal requests (table creates/alters/indexes).
- Local filesystem writes are used for cache/logs/uploads:
  - `includes/cache_manager.php`
  - `includes/functions.php`
  - `api/incidents.php` and several admin/resident upload handlers.
- Cron scripts are local-process style and need cloud scheduling:
  - `cron_check_expiring_permits.php`
  - `cron_cleanup_active_sessions.php`
- Deployment secret handling must be tightened (no raw credentials in deployment YAML).

## 3. Changes Applied in This Pass

### 3.1 Cloud SQL-compatible DB connection
Updated `config/database.php` to:
- Support `DB_SOCKET` (Unix socket) for App Engine/Cloud Run + Cloud SQL.
- Support `DB_PORT` for TCP mode.
- Build DSN dynamically for socket or host/port.
- Gate DB auto-create with `AUTO_CREATE_DATABASE` (default true for backward compatibility).

### 3.2 Safer cache backend auto-selection
Updated `includes/cache_manager.php` to:
- Prefer APCu only when available.
- Fall back to file/session backends when APCu is unavailable.

### 3.3 Production-safe schema sync toggle
Updated `config/init.php` to:
- Honor `AUTO_DB_SCHEMA_SYNC`.
- Skip runtime DDL/migrations when `AUTO_DB_SCHEMA_SYNC=false`.

### 3.4 Cloud env templates
Updated:
- `.env.example`
- `app.yaml.example`

Added variables:
- `DB_PORT`
- `DB_SOCKET`
- `AUTO_CREATE_DATABASE`
- `AUTO_DB_SCHEMA_SYNC`
- `SESSION_SAVE_PATH`
- `GOOGLE_CLOUD_PROJECT`
- `STORAGE_BUCKET`
- `MAPS_API_KEY`

## 4. Migration Plan (Phased)

### Phase A: Foundation
1. Create GCP project and billing.
2. Create Cloud SQL MySQL instance.
3. Import schema/data from production export.
4. Create Cloud Storage bucket for uploads/media.
5. Create Secret Manager secrets for DB/API/email credentials.
6. Create service account for runtime with least-privilege IAM.

### Phase B: Application Preparation
1. Set `AUTO_DB_SCHEMA_SYNC=false` in production.
2. Set `AUTO_CREATE_DATABASE=false` in production.
3. Set `SESSION_SAVE_PATH=/tmp` for serverless runtime.
4. Ensure runtime loads secrets via env vars.
5. Keep local-dev defaults unchanged in `.env` for developer convenience.

### Phase C: Data and Files
1. Migrate DB data to Cloud SQL.
2. Migrate media from local directories to Cloud Storage.
3. Update upload flows to write to Cloud Storage (next code increment).
4. Keep backward-compatible URL/path mapping for existing DB media_path values.

### Phase D: Job Migration
1. Expose protected HTTP endpoints for scheduled logic (or use Cloud Run Jobs).
2. Configure Cloud Scheduler:
   - Permit expiry checks (daily).
   - Session cleanup (5 to 10 minutes).
3. Add idempotency checks where duplicate scheduler delivery is possible.

### Phase E: Cutover
1. Deploy to staging Cloud Run service.
2. Run smoke tests (login, register, upload, notifications, incidents).
3. Configure custom domain + managed SSL.
4. Shift traffic gradually and monitor logs/errors.
5. Keep rollback deployment revision available.

## 5. Cloud Environment Baseline

Use values similar to:

- `APP_ENV=production`
- `APP_DEBUG=false`
- `DB_SOCKET=/cloudsql/PROJECT:REGION:INSTANCE`
- `DB_NAME=barangay_reports`
- `DB_USER=<db_user>`
- `DB_PASS=<from_secret_manager>`
- `DB_CHARSET=utf8mb4`
- `AUTO_CREATE_DATABASE=false`
- `AUTO_DB_SCHEMA_SYNC=false`
- `SESSION_SAVE_PATH=/tmp`
- `STORAGE_BUCKET=<bucket_name>`

## 6. Priority Next Code Tasks

1. Replace local upload writes with Cloud Storage API integration.
2. Move file-based activity logging to Cloud Logging or DB-only strategy.
3. Split schema migration logic from request bootstrap into explicit migration scripts.
4. Add readiness/liveness endpoint for Cloud Run health checks.
5. Add a protected scheduler token/auth check for cron-equivalent endpoints.

## 7. Operational Checklist Before Go-Live

- [ ] Secrets rotated and stored in Secret Manager.
- [ ] Cloud SQL connection tested from runtime service account.
- [ ] `AUTO_DB_SCHEMA_SYNC=false` confirmed in production.
- [ ] Upload and report media paths validated after storage migration.
- [ ] Scheduler jobs running and idempotent.
- [ ] Error Reporting and alerts configured.
- [ ] Backup + PITR enabled for Cloud SQL.
- [ ] Rollback procedure tested.
