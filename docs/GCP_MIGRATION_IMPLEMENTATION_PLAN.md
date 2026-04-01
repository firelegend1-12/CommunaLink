# CommunaLink Cloud Migration Implementation Plan

## Implementation Progress Update (Batch 1 - Completed)

The following migration actions are now implemented in code:

1. Secret-safe deployment templates
- `app.yaml` and `app.yml` were converted to placeholder-safe values and no longer contain plaintext active credentials.
- `.gitignore` now ignores both `app.yaml` and `app.yml`.

2. Production safety guardrails
- `config/database.php` now force-disables `AUTO_CREATE_DATABASE` in production-like environments (`production`, `prod`, `staging`).
- `config/init.php` now force-disables runtime schema sync in production-like environments even if misconfigured.
- Production-like bootstrap now disables display errors and logs instead.

3. Containerization baseline
- Added `Dockerfile` with PHP 8.2 Apache runtime, required extensions, healthcheck support, and production-safe environment defaults.
- Added `.dockerignore` to prevent sensitive or unnecessary files from entering images.

4. CI baseline
- Added `.github/workflows/cloud-deploy.yml` with build and smoke-test stages plus deploy placeholders for staging/production.

5. Initial storage abstraction
- Added `includes/storage_manager.php` with local storage default and optional cloud-storage write path when enabled.
- Refactored `api/incidents.php` upload flow to use the storage manager interface instead of direct filesystem writes.

Immediate next implementation targets:
- Expand storage manager adoption to all remaining upload handlers.
- Add cloud scheduler auth/idempotency hardening for permit check execution endpoint.
- Wire CI deploy placeholders to actual cloud deployment commands and managed secret injection.

## Implementation Progress Update (Batch 2 - Completed)

The following migration actions are now implemented in code:

1. Storage abstraction expanded across remaining upload handlers
- Refactored announcement uploads to use shared storage manager in `admin/partials/announcement-handler.php`.
- Refactored post uploads to use shared storage manager in `admin/partials/post-handler.php`.
- Refactored resident onboarding image/signature uploads in `admin/partials/add-resident-handler.php`.
- Refactored resident account profile image upload in `resident/partials/account-handler.php`.

2. Backward-compatible media path handling
- Upload writes now target `admin/images/...` storage paths while preserving existing database-facing path conventions by stripping `admin/` where needed.

3. Storage deletion abstraction
- Added centralized delete support in `includes/storage_manager.php` for both local paths and `gs://` object paths.
- Delete operations in announcement/post/resident profile handlers now use storage manager delete API.

4. Scheduler execution lock for idempotency safety
- Added distributed execution lock in `api/check-expiring-permits.php` using MySQL `GET_LOCK`/`RELEASE_LOCK` for scheduler-triggered runs.
- Duplicate overlapping scheduler invocations now return a safe skip response instead of executing a second run.

Immediate next implementation targets:
- Integrate cloud storage SDK dependency (`google/cloud-storage`) into composer and deployment runtime.
- Wire CI deploy placeholders to real staging/production commands with secret injection.
- Add deployment-time migration runner command and schema version tracking table.

## Implementation Progress Update (Batch 3 - Completed)

The following migration actions are now implemented in code:

1. Cloud storage dependency integration
- Added `google/cloud-storage` to `composer.json` so cloud-backed media upload mode is installable in deployment environments.

2. Deploy-time migration runner with schema tracking
- Added `scripts/migrate.php`.
- Runner creates and uses `schema_migrations` table (filename, checksum, applied_at, execution_ms).
- Supports `--dry-run` and validates checksum consistency for already-applied migrations.

3. CI/CD deployment workflow wiring
- Replaced deploy placeholders with concrete Cloud Run deployment commands in `.github/workflows/cloud-deploy.yml`.
- Pipeline now builds and pushes image to Artifact Registry, deploys to staging, then deploys to production.
- Added optional migration execution step controlled by `RUN_DB_MIGRATIONS=true`.

4. Environment template updates for storage mode
- Updated `.env.example` with `USE_CLOUD_STORAGE` and canonical `GOOGLE_MAPS_API_KEY` variable.

Required CI environment variables for `cloud-deploy.yml`:
- `GCP_REGION`
- `GCP_PROJECT_ID`
- `ARTIFACT_REPOSITORY`
- `CLOUD_RUN_SERVICE_STAGING`
- `CLOUD_RUN_SERVICE_PRODUCTION`
- `STAGING_STORAGE_BUCKET`
- `PRODUCTION_STORAGE_BUCKET`
- `GCP_SA_KEY_JSON` (service account key JSON content)
- Optional DB migration vars when `RUN_DB_MIGRATIONS=true`:
  - `DB_HOST_STAGING`, `DB_PORT_STAGING`, `DB_NAME_STAGING`, `DB_USER_STAGING`, `DB_PASS_STAGING`, `DB_SOCKET_STAGING`

Immediate next implementation targets:
- Add structured liveness endpoint metadata (version/commit/runtime mode).
- Migrate scheduler jobs to dedicated Cloud Scheduler deployment scripts.
- Add secret-scanning gate in CI before build stage.

## Implementation Progress Update (Batch 4 - Completed)

The following migration actions are now implemented in code:

1. CI secret scanning gate
- Added `secret-scan` job to `.github/workflows/cloud-deploy.yml` using gitleaks.
- Build now depends on successful secret scan before image build/push.

2. Liveness metadata enrichment
- Updated `api/health.php` to expose structured metadata for deployment diagnostics:
  - `environment`
  - `version`
  - `release_commit`
  - `storage_mode`
  - `php_version`

3. Session cleanup scheduler endpoint
- Added `api/cleanup-active-sessions.php`.
- Endpoint is POST-only and protected by scheduler token header (`X-Cloud-Scheduler-Token`).
- Runs `clear_expired_active_sessions_with_audit()` and returns execution summary.

4. Scheduler deployment automation
- Added `scripts/deploy_scheduler_jobs.ps1` to provision Cloud Scheduler jobs for:
  - permit expiry checks
  - active-session cleanup

5. Environment template updates
- Added `SESSION_CLEANUP_SCHEDULER_TOKEN` to `.env.example` and `app.yaml.example`.

Immediate next implementation targets:
- Add CI policy checks for required deployment env vars before staging/production jobs.
- Add rollback helper script for previous Cloud Run revision traffic restore.
- Add optional post-deploy smoke test against deployed service URL.

## Implementation Progress Update (Batch 5 - Completed)

The following migration actions are now implemented in code:

1. CI deploy policy checks
- Added explicit staging and production policy validation steps in `.github/workflows/cloud-deploy.yml` before deployment.
- Staging validation now enforces required deployment inputs and conditionally validates migration inputs when `RUN_DB_MIGRATIONS=true`.

2. Deterministic image propagation across jobs
- Added `build` job output for `image_uri` and wired downstream jobs (`smoke-test`, `deploy-staging`, `deploy-production`) to use the same built image reference.

3. Post-deploy smoke checks against deployed Cloud Run URLs
- Added steps to resolve deployed service URLs from Cloud Run and run post-deploy checks for:
  - `/api/health.php`
  - `/api/ready.php`

4. Rollback helper script for Cloud Run revisions
- Added `scripts/rollback_cloud_run_revision.ps1`.
- Script supports explicit target revision rollback and auto-selection of previous revision when no target is provided.

Immediate next implementation targets:
- Add production startup config contract checks (fail-fast validation for required cloud env vars).
- Add deployment runbook notes for rollback and smoke-check failure handling.
- Add environment-specific scheduler token and secret-mapping validation in CI policy checks.

## Implementation Progress Update (Batch 6 - Completed)

The following migration actions are now implemented in code:

1. Production startup config contract checks
- Added fail-fast production-like startup contract validation in `config/init.php`.
- Runtime now blocks startup with HTTP 500 (or CLI non-zero exit) when required cloud config is missing or invalid.
- Enforced checks include DB connectivity contract (`DB_NAME`, `DB_USER`, and `DB_SOCKET` or `DB_HOST`+`DB_PORT`), cloud storage contract (`STORAGE_BUCKET` when `USE_CLOUD_STORAGE=true`), and scheduler token contract (`PERMIT_CHECK_SCHEDULER_TOKEN`, `SESSION_CLEANUP_SCHEDULER_TOKEN`).

2. CI secret-mapping policy hardening
- Added explicit environment-specific secret mapping variables in `.github/workflows/cloud-deploy.yml`.
- Added pre-deploy policy checks for staging and production to ensure required secret mapping keys are present before deployment.
- Updated Cloud Run deploy commands to inject both scheduler tokens (`PERMIT_CHECK_SCHEDULER_TOKEN` and `SESSION_CLEANUP_SCHEDULER_TOKEN`) through mapped secret names.

3. Deployment runbook notes for smoke failure and rollback
- Added the runbook below for operational response when post-deploy smoke checks fail.

### Batch 6 Runbook Addendum: Smoke Failure and Rollback Handling

1. Trigger condition
- Post-deploy smoke step fails on `/api/health.php` or `/api/ready.php` in staging or production.

2. Immediate containment
- Stop forward promotion (do not continue to production if staging fails).
- Capture failed pipeline URL, deployment SHA, and Cloud Run revision name from logs.

3. Rollback procedure
- Use `scripts/rollback_cloud_run_revision.ps1` to restore traffic to previous known-good revision.
- Example:
  - `pwsh scripts/rollback_cloud_run_revision.ps1 -ProjectId <project> -Region <region> -ServiceName <service>`
  - Or explicitly: `pwsh scripts/rollback_cloud_run_revision.ps1 -ProjectId <project> -Region <region> -ServiceName <service> -TargetRevision <revision-name> -TrafficPercent 100`

4. Verification after rollback
- Re-run smoke checks against service URL:
  - `/api/health.php`
  - `/api/ready.php`
- Validate scheduler endpoints still authenticate and execute:
  - `/api/check-expiring-permits.php` (POST + scheduler token)
  - `/api/cleanup-active-sessions.php` (POST + scheduler token)

5. Release decision
- Keep rollback revision at 100% traffic until root cause is fixed and a new deployment passes all smoke checks.

Immediate next implementation targets:
- Add optional pre-deploy dry-run gate for SQL migrations in staging CI.
- Add Cloud Run revision metadata tagging (version/commit labels) during deploy for faster incident triage.
- Add alert hooks for repeated smoke failures and rollback events.

## Objectives

- Migrate CommunaLink to Google Cloud with no data loss and controlled downtime.
- Remove critical security exposures before any production deployment.
- Sequence implementation so every patch set is compatible with previous sets.
- Keep rollback points after each phase.

## Delivery Strategy

- Use patch bundles (P0, P1, P2...) that are small, testable, and dependency-aware.
- Promote changes through environments in order: local -> staging -> production.
- Freeze schema-changing and secret-related work during cutover windows.

## Risk-Ordered Phases

## Phase 0 (Critical Risk): Security Containment and Secret Hygiene

### Goals
- Eliminate plaintext secrets from tracked files.
- Prevent future accidental secret commits.

### Patch Sets

1. P0.1 Secret Eradication
- Replace sensitive values in tracked deployment files with placeholders.
- Affected files:
  - app.yaml
  - app.yml
  - MIGRATION_GUIDE.md (remove real examples)
- Output connection:
  - Required before any repository push or CI/CD activation.

2. P0.2 Repo Guardrails
- Update .gitignore to include app.yml and any secret-bearing variants.
- Add pre-commit secret scanning (for example gitleaks/trufflehog in CI).
- Output connection:
  - Depends on P0.1 cleanup to avoid scanning immediate false failures.

3. P0.3 Credential Rotation
- Rotate DB password, SMTP app password, and Google Maps API key.
- Store new credentials in Secret Manager.
- Output connection:
  - Must happen after P0.1, because exposed credentials are assumed compromised.

### Exit Criteria
- No active credentials in repo history tip.
- Secret scan passes on default branch.
- All runtime secrets available from Secret Manager.

## Phase 1 (High Risk): Runtime Safety and Deterministic Behavior

### Goals
- Remove cloud-hostile behaviors that can break production stability.
- Ensure scheduler actions are safe and idempotent.

### Patch Sets

1. P1.1 Scheduler Endpoint Hardening
- Refactor permit-expiry execution into a scheduler-only endpoint:
  - Use POST only (no state changes on GET).
  - Require scheduler auth token or IAM-based service auth.
  - Add idempotency lock per day/time window to prevent duplicate notifications.
- Affected files:
  - api/check-expiring-permits.php
  - admin/index.php (remove direct trigger or switch to read-only status endpoint)
- Output connection:
  - Depends on Phase 0 secret model for scheduler token storage.

2. P1.2 Production DDL Lockdown
- Enforce AUTO_DB_SCHEMA_SYNC=false and AUTO_CREATE_DATABASE=false for production.
- Add startup guard log if production runs with unsafe defaults.
- Affected files:
  - config/init.php
  - config/database.php
  - app.yaml.example
  - .env.example
- Output connection:
  - Depends on current DB compatibility; should be done before traffic cutover.

3. P1.3 Session Policy Normalization
- Standardize session initialization path so cookie/security settings are always applied.
- Remove ad-hoc session_start() from entry points where possible.
- Affected areas:
  - resident/* entry pages
  - admin/pages/* entry pages
  - includes/auth.php and config/init.php bootstrap flow
- Output connection:
  - Depends on stable bootstrap behavior from P1.2.

### Exit Criteria
- No state-changing GET endpoints for scheduled jobs.
- Scheduler retries do not duplicate records.
- Production runtime performs zero schema DDL on request path.

## Phase 2 (High-to-Medium Risk): Storage and File System Decoupling

### Goals
- Remove dependency on local ephemeral disk in cloud runtime.

### Patch Sets

1. P2.1 Cloud Storage Adapter
- Introduce storage abstraction service with local and GCS drivers.
- Add env controls for bucket and storage mode.
- New files:
  - includes/storage_adapter.php (or similar)
- Output connection:
  - Prerequisite for all upload path migrations.

2. P2.2 Upload Path Migration
- Move incident, announcement, post, and profile uploads to GCS.
- Preserve backward compatibility for existing stored relative paths.
- Affected files:
  - api/incidents.php
  - admin/partials/announcement-handler.php
  - admin/partials/post-handler.php
  - admin/partials/add-resident-handler.php
  - resident/partials/account-handler.php
- Output connection:
  - Depends on P2.1 adapter.

3. P2.3 Cache/Log Strategy Update
- Route logs to Cloud Logging (or DB-backed audit where needed).
- Keep file cache local only for non-production; production to APCu/managed strategy.
- Affected files:
  - includes/functions.php
  - includes/cache_manager.php
- Output connection:
  - Depends on environment detection and deployment configs from Phase 1.

### Exit Criteria
- No critical user data written only to local filesystem in production.
- Uploads survive instance restarts and horizontal scaling.

## Phase 3 (Medium Risk): Service Configuration and Consistency

### Goals
- Align configuration names and runtime assumptions.

### Patch Sets

1. P3.1 Environment Key Normalization
- Standardize map key variable name to one canonical key (recommend GOOGLE_MAPS_API_KEY).
- Remove hardcoded fallback real key from code.
- Affected files:
  - admin/pages/maps.php
  - resident/report-incident.php
  - resident/report-details.php
  - .env.example
  - app.yaml.example
- Output connection:
  - Depends on Phase 0 key rotation.

2. P3.2 App Config Contracts
- Add config validation at startup for required production vars.
- Fail fast with clear logs for missing critical vars.
- Output connection:
  - Depends on normalized key names from P3.1.

### Exit Criteria
- One source of truth for each config value.
- No hardcoded cloud secrets in source files.

## Phase 4 (Medium-to-Low Risk): Cloud Platform Integration and Cutover

### Goals
- Deploy safely with progressive rollout and rollback controls.

### Patch Sets

1. P4.1 Infrastructure Provisioning
- Provision Cloud SQL, Cloud Run/App Engine service, Cloud Storage, Secret Manager entries.
- Configure service account IAM least privilege.

2. P4.2 Job Orchestration
- Create Cloud Scheduler jobs:
  - Permit check daily.
  - Session cleanup every 5-10 minutes.
- Route scheduler to protected endpoints only.

3. P4.3 Observability Baseline
- Enable Cloud Logging/Error Reporting.
- Add alerts for 5xx rate, DB connection errors, scheduler failures.

4. P4.4 Data Migration and Verification
- Snapshot source DB.
- Import to Cloud SQL.
- Verify row counts and key business workflows.

5. P4.5 Traffic Cutover
- Deploy staging first, run smoke and regression checks.
- Canary production traffic.
- Full cutover after stability window.

### Exit Criteria
- Stable production behavior under cloud traffic.
- Rollback path validated and documented.

## Phase 5 (Low Risk): Optimization and Hardening

### Goals
- Improve performance, maintainability, and long-term operability.

### Patch Sets

1. P5.1 Migration Framework Separation
- Move remaining schema evolution from runtime bootstrap into explicit migration runner.

2. P5.2 Query and Cache Tuning
- Reassess indexes and cache TTLs using production telemetry.

3. P5.3 Cost and SLO Optimization
- Right-size instance class/concurrency and scheduler frequencies.

## Patch Dependency Graph

- P0.1 -> P0.2 -> P0.3
- P0.3 -> P1.1
- P1.2 -> P1.3
- P1.1 + P1.2 -> P2.1
- P2.1 -> P2.2
- P1.x + P2.2 -> P2.3
- P0.3 -> P3.1 -> P3.2
- P1 + P2 + P3 -> P4
- P4 -> P5

## Rollout and Validation Gates

- Gate A (after Phase 0): Security signoff
  - Secret scan clean
  - Rotated credentials live

- Gate B (after Phase 1): Runtime safety signoff
  - No state-changing GET jobs
  - No request-time DDL in production

- Gate C (after Phase 2): Data durability signoff
  - All uploads validated on GCS
  - Logs visible in cloud logging

- Gate D (after Phase 4): Production readiness signoff
  - Smoke tests pass
  - Scheduler stable
  - Rollback tested

## Suggested Sprint Allocation

- Sprint 1: Phase 0 + P1.1
- Sprint 2: P1.2 + P1.3 + P2.1
- Sprint 3: P2.2 + P2.3
- Sprint 4: P3.1 + P3.2 + P4.1/P4.2
- Sprint 5: P4.3/P4.4/P4.5
- Sprint 6: Phase 5 improvements

## Notes on Patch Connectivity

- Every patch should include:
  - Prerequisites (which patch outputs it consumes)
  - Forward contract (which files/env keys next patches rely on)
  - Rollback instructions (how to safely revert without orphaning data)

- For this project, the key forward contracts are:
  - Canonical env keys in .env.example and deployment YAML examples.
  - Scheduler endpoint contract (auth + idempotency behavior).
  - Storage adapter interface used by all upload handlers.
