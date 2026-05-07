# Phase 5 RBAC Verification - 2026-05-08

## Scope
- Add repeatable authorization matrix tests for roles x protected surfaces.
- Add CI-style static guard checks for admin pages, mutation handlers, APIs, and denial telemetry.
- Run the full local verification suite for the implemented Phase 1-5 changes.

## Added Regression Checks
- `scripts/phase5_rbac_authorization_matrix.php`
  - Table-driven authenticated status expectations for admin pages, admin workflows, and secured APIs.
  - Verifies least-privilege expectations for `admin`, official roles, `resident`, legacy `official`, and an unknown role.
- `scripts/phase5_static_rbac_gate.php`
  - Verifies all admin pages include the admin bootstrap.
  - Verifies high-risk mutation handlers include login, CSRF, and permission guard markers.
  - Verifies secured API endpoints expose normalized `required_permission` denial contracts.
  - Verifies RBAC denial helpers still call `log_rbac_warning(...)`.

## Denial Audit Logging
- Existing centralized RBAC denial helpers in `includes/permission_checker.php` already emit `log_rbac_warning(...)`.
- The Phase 5 static gate now fails if redirect or JSON denial helpers lose those telemetry calls.

## Validation Commands
- `D:\xampp\php\php.exe -l <all php files>`
- `D:\xampp\php\php.exe scripts/rbac_baseline_check.php`
- `D:\xampp\php\php.exe scripts/phase3_4_guard_check.php`
- `D:\xampp\php\php.exe scripts/phase5_account_guard_check.php`
- `D:\xampp\php\php.exe scripts/phase5_rbac_authorization_matrix.php`
- `D:\xampp\php\php.exe scripts/phase5_static_rbac_gate.php`
- `D:\xampp\php\php.exe tests/test_pbt_app_url.php`
