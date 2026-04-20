# RBAC Audit and Patch Roadmap (2026-04-19)

## Implementation Status Update (2026-04-20)

- Phase 0: Completed
   - Permission catalog published at `docs/RBAC_PERMISSION_CATALOG.md`.
   - Baseline validation script added at `scripts/rbac_baseline_check.php`.
   - Baseline run result: 15/15 checks passed.
- Phase 1: Completed (core primitives)
   - Added centralized role normalization and deny-by-default permission vocabulary helpers.
   - Added hard guard helpers in `includes/permission_checker.php`:
      - `require_permission_or_redirect(...)`
      - `require_any_permission_or_redirect(...)`
      - `require_permission_or_json(...)`
   - Added warning-level authorization deny logging (error log + activity log mirror when available).
- Phase 4: Completed (core API alignment)
   - Aligned `api/incidents.php`, `api/notifications.php`, `api/chat.php`, and `api/post-reactions.php` to permission-based authorization.
   - Removed deprecated endpoint `api/announcements.php`.
   - Hardened scheduler endpoint authorization in `api/check-expiring-permits.php` and `api/cleanup-active-sessions.php` for scoped/rotating tokens.
   - Validation artifact published at `docs/PHASE4_API_ALIGNMENT_2026-04-20.md`.
- Decision locks applied:
   - Legacy `official` is blocked immediately in permission guards.
   - Web deny default is redirect.
   - JSON deny includes `required_permission`.

## Executive Verdict

Current implementation is **not strict RBAC**. The project has a strong permission model definition, but enforcement is inconsistent and frequently bypasses permission-level checks.

## Evidence Summary

### 1) Permission model exists, but is under-enforced
- Permission matrix is defined in `config/permissions.php`.
- Permission checker exists in `includes/permission_checker.php`.
- Usage in admin tree:
  - `require_permission(...)` references: 2
  - `is_admin_or_official(...)` references in admin partials: 38

### 2) Admin page entry protection is inconsistent
- Admin pages in `admin/pages`: 28 files.
- Pages including `admin_auth.php`: 4 files.
- `admin/index.php` includes `admin_auth.php`, but most `admin/pages/*.php` do not.

Pages with `require_login()` but no role/permission gate markers:
- `admin/pages/about-us.php`
- `admin/pages/account.php`
- `admin/pages/add-resident.php`
- `admin/pages/barangay-clearance-template.php`
- `admin/pages/business-application-form.php`
- `admin/pages/business-clearance-template.php`
- `admin/pages/business-clearance.php`
- `admin/pages/certificate-of-indigency-template.php`
- `admin/pages/certificate-of-residency-template.php`
- `admin/pages/edit-resident.php`
- `admin/pages/generate-business-permit.php`
- `admin/pages/monitoring-of-request.php`
- `admin/pages/new-barangay-business-clearance.php`
- `admin/pages/new-barangay-clearance.php`
- `admin/pages/new-certificate-of-indigency.php`
- `admin/pages/new-certificate-of-residency.php`

### 3) Broad role gates conflict with defined least-privilege matrix
Examples:
- Permission matrix says `barangay-treasurer` has `manage_announcements = false`, `manage_incidents = false`, `manage_documents = false`.
- Yet handlers like `admin/partials/announcement-handler.php`, `admin/partials/new-barangay-clearance-handler.php`, and `admin/partials/update-document-request-status.php` authorize via broad `is_admin_or_official()`.

### 4) Role set mismatch (legacy role behavior divergence)
- `includes/auth.php` `is_admin_or_official()` includes role `official`.
- `admin/partials/admin_auth.php` authorized role list excludes `official`.
- Result: behavior differs by endpoint type (page vs direct handler/API path).

### 5) API role policy is hand-coded per endpoint (not centralized)
- `api/incidents.php` uses custom local role checks and a local `require_role()` helper.
- `api/notifications.php` uses hard-coded allowed role arrays.
- This drifts from `config/permissions.php` and can create policy mismatch over time.

## Risk Classification

### Critical
1. Admin pages reachable with login-only checks in many files under `admin/pages` (potential data exposure/action surface expansion).
2. High-impact mutation handlers rely on broad `is_admin_or_official()` rather than permission-specific checks.

### High
3. Permission matrix and runtime checks are not consistently connected, so policy intent can be violated without code errors.
4. Role-set divergence (`official` included in some guards, excluded in others).

### Medium
5. Lack of centralized authorization middleware/service for both page and API contexts increases regression risk.
6. Multiple POST handlers lack CSRF checks (adjacent security gap that increases exploitability if session is compromised).

## Patch and Optimization Roadmap

## Phase 0 - Freeze and Baseline (1 day)
1. Freeze role and permission vocabulary in one source: `config/permissions.php`.
2. Add a repo-level policy doc for each permission and expected roles.
3. Capture baseline tests for key role-action pairs before refactor.

Deliverables:
- Permission catalog (role x action table).
- Baseline verification script output.

## Phase 1 - Centralize Enforcement Primitives (1-2 days)
1. Extend `includes/permission_checker.php` with hard-enforcing guards:
   - `require_permission_or_redirect($permission, $redirectPath)`
   - `require_permission_or_json($permission)`
   - `require_any_permission_or_redirect(array $permissions, $redirectPath)`
2. Normalize role resolution from session in one helper.
3. Add deny-by-default behavior for unknown roles/permissions.

Deliverables:
- Single enforcement API for page, partial, and API handlers.
- Unit tests for helper behavior.

## Phase 2 - Lock Admin Entry Points (2-3 days)
1. Apply `admin_auth.php` (or equivalent central include) to all `admin/pages/*.php`.
2. Replace login-only page checks with permission-based checks by module:
   - Residents module -> `manage_residents`
   - Documents module -> `manage_documents`
   - Businesses module -> `manage_businesses`
   - Incidents module -> `manage_incidents`
   - Announcements/Events -> `manage_announcements` / `manage_events`
   - Logs/User management -> admin-only/system permissions
3. Keep UI hiding in sidebar as secondary UX only; never as enforcement.

Deliverables:
- 100% admin page gate coverage.
- Explicit permission gate near top of each page.

## Phase 3 - Refactor Admin Handlers (3-4 days)
1. Replace `is_admin_or_official()` checks in `admin/partials/*.php` with permission-specific guard calls.
2. Separate admin-only operations from role-capability operations.
3. Add CSRF validation to all state-changing handlers lacking it.

Suggested mapping examples:
- `announcement-handler.php`, `post-handler.php`, `event-handler.php` -> `manage_announcements` / `manage_events`
- `new-*clearance-handler.php`, `update-document-request-status.php` -> `manage_documents`
- `new-business-permit-handler.php`, `update-business-status.php` -> `manage_businesses`
- `add-resident-handler.php`, `delete-resident-handler.php` -> `manage_residents`
- `archive-logs.php`, session revocation, user create/delete -> admin-only

Deliverables:
- Handler permission map checked into docs.
- Security tests per handler for allow/deny matrix.

## Phase 4 - Align APIs to Permission Matrix (2-3 days)
1. Remove endpoint-local role arrays where possible.
2. Route all API authorization through permission checker wrappers.
3. Keep health/readiness/CSP endpoints intentionally public but documented.
4. Normalize API error contracts for 401/403 with consistent JSON shape.

Deliverables:
- API guard consistency across `api/*.php`.
- Reduced policy duplication and drift.

## Phase 5 - Verification and Regression Shielding (2 days)
1. Add automated permission tests (table-driven):
   - Roles x endpoints x expected status code.
2. Add static check (CI script):
   - Flag handlers/pages without required guard markers.
3. Add audit logs for denied authorization attempts with role + endpoint.

Deliverables:
- CI RBAC gate.
- Repeatable authorization test suite.

## Phase 6 - Manuscript-Grade Evidence Package (1 day)
1. Produce before/after matrix (coverage and failed access attempts).
2. Document threat model assumptions and deny-by-default policy.
3. Include sample test runs proving least privilege.

Deliverables:
- Evidence appendix for manuscript claim.

## Success Criteria

A strict RBAC claim is supportable only when all are true:
1. Every admin page and mutation handler is protected by explicit permission checks.
2. No broad role shortcuts bypass permission-level policy.
3. API and page auth share one enforcement source.
4. Unknown roles/permissions are denied by default.
5. Automated tests enforce role-action expectations and run in CI.

## Recommended Immediate Hotfixes (same day)
1. Add role/permission gates to login-only admin pages (especially monitoring and issuance forms).
2. Convert top-risk mutation handlers from `is_admin_or_official()` to permission-specific guards.
3. Add CSRF checks to remaining state-changing handlers.
4. Decide and normalize treatment of legacy `official` role across all guards.
