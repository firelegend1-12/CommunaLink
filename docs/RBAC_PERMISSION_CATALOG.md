# RBAC Permission Catalog

This document freezes the RBAC vocabulary for Phase 0 and Phase 1.

## Canonical Source

The canonical RBAC matrix source is:
- `config/permissions.php`

Application-level permission checks should use:
- `includes/permission_checker.php`

## Canonical Roles

Allowed role keys in the RBAC matrix:
- `admin`
- `barangay-officials`
- `barangay-kagawad`
- `barangay-tanod`
- `resident`

Legacy value note:
- `official`, `kagawad`, `barangay-captain`, `barangay-secretary`, and `barangay-treasurer` are treated as legacy role labels and are not part of the canonical RBAC matrix.

## Canonical Permissions

| Permission | Granted Roles |
| --- | --- |
| `user_management` | `admin` |
| `system_logs` | `admin` |
| `all_pages` | `admin` |
| `delete_users` | `admin` |
| `view_logs` | `admin` |
| `manage_announcements` | `admin`, `barangay-officials` |
| `manage_events` | `admin`, `barangay-officials` |
| `manage_incidents` | `admin`, `barangay-officials`, `barangay-kagawad`, `barangay-tanod` |
| `manage_documents` | `admin`, `barangay-officials`, `barangay-kagawad` |
| `manage_businesses` | `admin`, `barangay-officials`, `barangay-kagawad` |
| `manage_residents` | `admin` |
| `add_residents` | `admin`, `barangay-officials`, `barangay-kagawad` |
| `view_residents` | `admin`, `barangay-officials`, `barangay-kagawad` |
| `edit_resident_profile` | `admin` |
| `view_monitoring_requests` | `admin`, `barangay-officials`, `barangay-kagawad` |
| `financial_management` | `admin`, `barangay-officials` |
| `approve_applications` | `admin` |
| `override_decisions` | `admin` |
| `preside_meetings` | `admin` |
| `emergency_powers` | `admin` |
| `committee_management` | _none_ |
| `record_keeping` | `admin`, `barangay-officials` |
| `meeting_minutes` | `admin`, `barangay-officials` |
| `budget_management` | `admin`, `barangay-officials` |
| `financial_reports` | `admin`, `barangay-officials` |
| `tax_collection` | `admin`, `barangay-officials` |
| `expense_tracking` | `admin`, `barangay-officials` |
| `patrol_management` | `barangay-tanod` |
| `incident_reporting` | `barangay-tanod` |
| `peace_and_order` | `barangay-tanod` |
| `emergency_response` | `barangay-tanod` |
| `view_announcements` | `resident` |
| `view_events` | `resident` |
| `submit_applications` | `resident` |
| `view_documents` | `resident` |
| `report_incidents` | `resident` |
| `access_services` | `resident` |

## Enforcement Rules

1. Unknown role keys are denied by default.
2. Unknown permission keys are denied by default.
3. If a permission key is not explicitly set to `true` for a role, access is denied.
4. Session-derived role checks must use normalized role resolution in `includes/permission_checker.php`.

## Decision Log (2026-04-20)

1. Legacy roles `official`, `kagawad`, `barangay-captain`, `barangay-secretary`, and `barangay-treasurer` are blocked in permission guards and must be migrated to canonical role keys.
2. Denied web/page guard default behavior is redirect to index/dashboard paths.
3. Denied JSON responses include `required_permission` for frontend debugging.
4. Denied authorization attempts are logged at warning level (file log, plus activity log mirror when available).
5. Permission vocabulary was updated based on the requested hierarchy/access policy (resident=1, tanod=2, barangay-kagawad=3, barangay-officials=4, admin=5).
6. Resident management split: `add_residents` is allowed for admin + barangay-kagawad + barangay-officials, while `edit_resident_profile` remains admin-only.
7. Role hierarchy is for reporting/analysis only and is not used as an authorization fallback.
8. CSRF policy direction: enforce on browser/session-based mutating endpoints; token-authenticated service calls may be exempted by explicit design.

## Baseline Validation

Run this command from project root:

```powershell
D:\xampp\php\php.exe scripts/rbac_baseline_check.php
```

The script exits with code `0` when baseline checks pass, otherwise `1`.
