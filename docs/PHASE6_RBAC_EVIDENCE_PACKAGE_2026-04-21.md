# Phase 6 RBAC Evidence Package - 2026-04-21

## Purpose
Provide manuscript-grade evidence for RBAC maturity claims by consolidating:
- measurable before/after authorization coverage,
- explicit deny-by-default and threat model assumptions,
- reproducible test outputs from verification scripts.

## Evidence Inputs
- Baseline roadmap findings: `docs/RBAC_AUDIT_ROADMAP_2026-04-19.md`
- Handler/runtime smoke validation: `docs/PHASE3_SMOKE_CHECK_2026-04-20.md`
- API alignment validation: `docs/PHASE4_API_ALIGNMENT_2026-04-20.md`
- RBAC baseline verifier: `scripts/rbac_baseline_check.php`
- Phase 5 guard verifier: `scripts/phase5_account_guard_check.php`
- Current enforcement primitives: `includes/permission_checker.php`
- Current page entry gate map: `admin/partials/admin_auth.php`

## Before/After Coverage Matrix

| Dimension | Baseline (2026-04-19) | Current (2026-04-21) | Evidence Source |
|---|---:|---:|---|
| Admin pages with admin gate include | 4 / 28 | 27 / 27 | Baseline roadmap + current page scan |
| Legacy broad role shortcuts in admin partials (`is_admin_or_official`) | 38 refs | 0 refs | Baseline roadmap + current search |
| Permission guard references in admin partial handlers | 2 refs (`require_permission(...)` baseline note) | 19 refs (`require_permission_or_redirect` / `require_any_permission_or_redirect`) | Baseline roadmap + current search |
| CSRF guard references in admin partials | baseline risk noted (missing in multiple handlers) | 34 refs (`csrf_require` / `csrf_validate`) | Baseline risk summary + current search |
| API permission-wrapper references (secured APIs) | endpoint-local role arrays and local helper patterns present | 9 refs across aligned endpoints (`api/incidents.php`, `api/notifications.php`, `api/post-reactions.php`) | Phase 4 report + current search |
| RBAC baseline verifier | 15 / 15 passed (earlier run) | 29 / 29 passed | Script output |

## Current Verification Run (2026-04-21)

### Script Execution
Command:

`d:\xampp\php\php.exe scripts/rbac_baseline_check.php`

Result:
- Total checks: 29
- Failures: 0
- Final line: `RBAC baseline checks passed.`

Command:

`d:\xampp\php\php.exe scripts/phase5_account_guard_check.php`

Result:
- Final line: `Phase 5 guard check: PASSED`

### Deny-by-Default Proof Points
From `includes/permission_checker.php`:
- `resolve_permission_role(...)` returns `null` for legacy `official`, preventing permissive fallback.
- `require_permission(...)` returns `false` when role or permission key is unknown and emits `RBAC_WARNING` telemetry.
- `require_permission_or_json(...)` and `require_any_permission_or_json(...)` emit structured deny responses with `required_permission`.

Observed baseline warnings during script run (expected contract behavior):
- `RBAC_WARNING [invalid_permission_context]` for blocked legacy role context.
- `RBAC_WARNING [unknown_role_or_permission]` for unknown role/permission keys.

These warnings confirm unknown/invalid contexts are denied, not elevated.

## Threat Model Assumptions
This evidence package assumes:
1. Session integrity and CSRF tokens are not compromised at the browser boundary.
2. Role claims in session are produced only by trusted authentication flow.
3. Permission matrix in `config/permissions.php` is the single policy source and is code-reviewed for changes.
4. Scheduler bearer tokens are secret, rotated, and scope-validated where implemented.
5. Production transport security (HTTPS/TLS) protects auth/session/token traffic in transit.
6. Database and activity-log storage are trusted infrastructure components.

## Manuscript Claim Support Statement
Given the current evidence, the project supports a strict-RBAC claim for the evaluated admin/API surfaces, with deny-by-default behavior, centralized permission primitives, and repeatable script-based verification.

## Residual Risks and Remaining Gaps
- Runtime positive-path resident API smoke is still a recommended follow-up from Phase 4.
- End-to-end mutation tests on disposable datasets should continue to expand beyond guard-contract checks.
- CI gating should include this evidence verification run (baseline + guard checks) on each release branch.

## Repro Steps
1. Run baseline verifier:
   - `d:\xampp\php\php.exe scripts/rbac_baseline_check.php`
2. Run guard verifier:
   - `d:\xampp\php\php.exe scripts/phase5_account_guard_check.php`
3. Recompute coverage counts:
   - Admin pages with `admin_auth.php` include
   - Handler refs for permission guards and CSRF guards
   - API refs for JSON permission wrappers

