# Phase 3/4 Implementation Status - 2026-05-08

## Scope
- Phase 3: Admin handler refactor to permission-specific guards.
- Phase 4: API alignment to the permission matrix and consistent denial contracts.

## Phase 3 Results
- Replaced the remaining raw admin-role authorization in `admin/partials/add-user-handler.php` with `require_login()` and `require_permission_or_redirect('user_management', ...)`.
- Confirmed no admin partial uses `is_admin_or_official()` as an authorization shortcut.
- Confirmed key mutating admin handlers have request-method, CSRF, and permission guards.

## Phase 4 Results
- Confirmed secured API endpoints avoid `require_role()` and expose normalized JSON denial payloads with `required_permission`.
- Scheduler endpoints remain token-authorized and documented as intentionally non-session API surfaces.
- Public health/readiness/CSP endpoints remain intentionally public.

## Regression Shield
- Added `scripts/phase3_4_guard_check.php` to statically verify admin handler and secured API guard invariants.

## Validation
- PHP lint: run across all PHP files.
- RBAC baseline: `scripts/rbac_baseline_check.php`.
- App URL PBT: `tests/test_pbt_app_url.php`.
- Phase 3/4 guard check: `scripts/phase3_4_guard_check.php`.
