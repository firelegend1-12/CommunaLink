# Phase 4 API Alignment - 2026-04-20

## Goal
Align secured API endpoints to the RBAC permission matrix, remove endpoint-local role arrays, normalize 401/403 contracts, and reduce policy drift.

## Final Decisions Applied
- Scope: user-facing secured APIs only.
- Intentionally public endpoints remain public:
  - `/api/health.php`
  - `/api/ready.php`
  - `/api/csp-report.php`
- Scheduler endpoints keep token authorization, but now support scoped and rotating token patterns.
- Manual permit-check trigger permission: `financial_management`.
- Deny JSON contract: `success=false,error,required_permission` (when applicable).
- Incident mapping:
  - `report_incident`, `get_my_reports` -> `report_incidents`
  - `get_all_reports`, `update_report_status` -> `manage_incidents`
- Chat operator role policy: all roles with `access_chat`.
- Chat model: per-operator isolated inbox.
- Notification count APIs: permission-based per action.
- Post reactions: resident-facing permission only.
- `api/announcements.php`: removed.
- Compatibility mode: none.

## Files Changed
- `includes/permission_checker.php`
- `api/incidents.php`
- `api/notifications.php`
- `api/chat.php`
- `api/post-reactions.php`
- `api/check-expiring-permits.php`
- `api/cleanup-active-sessions.php`
- `admin/index.php`
- Removed: `api/announcements.php`

## Implementation Summary

### 1) Shared Guard Primitive
- Added `require_any_permission_or_json(...)` to `includes/permission_checker.php`.
- Purpose: support JSON API endpoints that accept any permission from a set.

### 2) Incidents API
- Replaced local role helper checks with permission guards:
  - `report_incidents` for resident report/read-own actions.
  - `manage_incidents` for all-reports and status updates.
- Enforced strict auth contract:
  - `401` for unauthenticated.
  - `403` for forbidden with `required_permission`.
- Standardized JSON error shape and method handling.

### 3) Notifications API
- Removed endpoint-local role arrays for secured read/count actions.
- Applied permission mapping:
  - `get_business_counts` -> `manage_businesses`
  - `get_incident_counts` -> `manage_incidents`
  - `get_events_counts` -> `manage_events`
  - `get_admin_sidebar_counts` -> any of `manage_incidents|manage_events`
- `mark_read` and `mark_all_read` remain authenticated self-service actions with CSRF.
- Added permission-aware cache key for sidebar counts to avoid cross-role cache leakage.

### 4) Chat API
- Replaced admin-only shared inbox assumptions with per-operator isolated thread model.
- Operator actions now require `access_chat`.
- Resident chat path remains available for resident users, while operator actions enforce permission.
- Normalized deny payloads and strict status codes.

### 5) Post Reactions API
- Enforced resident-facing permission (`view_announcements`) and strict deny payloads.
- Standardized status/error responses for method, auth, CSRF, and validation failures.

### 6) Scheduler Endpoint Hardening
- `api/check-expiring-permits.php`:
  - Manual UI trigger now requires `financial_management` + CSRF.
  - Scheduler authorization supports token rotation keys:
    - `PERMIT_CHECK_SCHEDULER_TOKEN_CURRENT`
    - `PERMIT_CHECK_SCHEDULER_TOKEN_NEXT`
    - fallback `PERMIT_CHECK_SCHEDULER_TOKEN`
  - Optional scope header: `X-Scheduler-Scope=permit_check`.
- `api/cleanup-active-sessions.php`:
  - Scheduler authorization supports rotation keys:
    - `SESSION_CLEANUP_SCHEDULER_TOKEN_CURRENT`
    - `SESSION_CLEANUP_SCHEDULER_TOKEN_NEXT`
    - fallback `SESSION_CLEANUP_SCHEDULER_TOKEN`
    - plus permit-token fallbacks for transition.
  - Optional scope header: `X-Scheduler-Scope=session_cleanup`.
- Both endpoints now emit normalized unauthorized payloads with `required_permission` semantics for scheduler auth failures.

### 7) Dashboard UX Guarding
- Permit-check button in `admin/index.php` is now disabled when `financial_management` is missing.
- Auto trigger for permit-check polling is skipped when permission is absent.

### 8) Endpoint Removal
- Removed `api/announcements.php`.
- Runtime validation confirms endpoint now returns `404`.

## Validation Results

### Static Validation
- IDE diagnostics: no errors in modified files.
- PHP lint: no syntax errors across all touched files.
- Post-refactor static audit:
  - no remaining `require_role(...)` in `api/*.php`.
  - no remaining local role-array auth pattern matches in `api/*.php`.
  - `api/announcements.php` removed.

### Runtime Validation (Unauthenticated)
- `GET /api/incidents.php?action=get_all_reports` -> `401` with JSON auth error.
- `GET /api/notifications.php?action=get_admin_sidebar_counts` -> `401` with JSON auth error.
- `GET /api/chat.php?action=get_conversations` -> `401` with JSON auth error.
- `POST /api/check-expiring-permits.php` -> `403` with scheduler permission contract.
- `POST /api/cleanup-active-sessions.php` -> `403` with scheduler permission contract.
- `POST /api/check-expiring-permits.php` with `X-Scheduler-Scope=wrong_scope` -> `403`.
- `POST /api/cleanup-active-sessions.php` with `X-Scheduler-Scope=wrong_scope` -> `403`.
- `GET /api/announcements.php` -> `404`.

### Runtime Validation (Authenticated Admin)
- Login: successful (`302`, no invalid-credential message).
- `GET /api/chat.php?action=get_conversations` -> `200` success JSON.
- `GET /api/chat.php?action=get_unread_count` -> `200` success JSON.
- `GET /api/notifications.php?action=get_admin_sidebar_counts` -> `200` success JSON.
- `GET /api/notifications.php?action=get_business_counts` -> `200` success JSON.
- `GET /api/notifications.php?action=get_incident_counts` -> `200` success JSON.
- `GET /api/notifications.php?action=get_events_counts` -> `200` success JSON.
- `GET /api/incidents.php?action=get_all_reports` -> `200` success JSON.
- `GET /api/incidents.php?action=get_my_reports` (admin) -> `403` with `required_permission=report_incidents`.
- `POST /api/incidents.php` invalid update payload -> `400` validation error.
- `POST /api/post-reactions.php` as admin -> `403` with `required_permission=view_announcements`.
- `POST /api/check-expiring-permits.php` with CSRF-admin session -> `202` success payload.

## Residual Validation Gaps
- Resident positive-path runtime checks were not fully exercised due unavailable resident test credentials in this run.
- Non-admin operator (`barangay-officials`, `barangay-kagawad`, `barangay-tanod`) runtime chat verification was not executed due unavailable test credentials for those roles.

## Recommended Follow-up
1. Run resident login smoke for:
   - `report_incident`
   - `get_my_reports`
   - `mark_read` and `mark_all_read`
   - post reactions toggle.
2. Run role-specific operator chat smoke for:
   - `barangay-officials`
   - `barangay-kagawad`
   - `barangay-tanod`
3. Rotate scheduler tokens to current/next env keys and document rotation SOP.
