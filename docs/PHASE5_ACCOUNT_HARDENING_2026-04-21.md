# Phase 5 Account Hardening - 2026-04-21

## Scope Lock
- Focus: account lifecycle hardening and regression shielding.
- Public resident registration: remains open.
- Privileged user provisioning: one privileged account per resident identity.

## Implemented Changes
- Admin account page now submits CSRF token.
- Admin account handler now enforces:
  - CSRF token validation.
  - Current-password verification for email or password changes.
  - Shared password policy validation via `PasswordSecurity::validatePassword`.
  - New password must differ from current password.
- User-management primary CTA text updated to "Create Privileged User".
- Add-user flow now sends `resident_id` and resolves resident by ID.
- Add-user handler now blocks additional privileged account creation for a resident.
- Registration handler now logs non-production OTP fallback via system activity log.

## Migration Artifact
- File: `migrations/drop_legacy_chat_messages_phase5.sql`
- Behavior:
  - If `chat_messages` exists and backup table does not, creates backup table `chat_messages_legacy_backup_20260421` and copies data.
  - Drops `chat_messages` when present.
  - No-op when chat table is already absent.

## Baseline Automation Artifact
- File: `scripts/phase5_account_guard_check.php`
- Purpose:
  - Static guard-marker validation for hardened account/provisioning paths.
  - Fast regression signal for critical security conditions.

## Suggested Validation Matrix
1. Admin account profile update (name only): should pass without current password.
2. Admin account email change: should require current password.
3. Admin account password change: should require current password and pass policy checks.
4. Add-user with unverified resident selection: should be blocked.
5. Add-user for resident with existing privileged role: should be blocked.
6. Add-user for new resident privileged account: should succeed.
7. Public registration OTP flow:
   - Production: failed email send should hard-fail registration.
   - Non-production: failed email send should continue with fallback notice and audit log.

## Execution Commands
- Run migrations:
  - `d:\xampp\php\php.exe scripts/migrate.php`
- Run Phase 5 static guard check:
  - `d:\xampp\php\php.exe scripts/phase5_account_guard_check.php`

## Remaining Work (Planned)
- Extend re-auth requirement to other critical identity and role-escalation operations.
- Execute runtime smoke checks across registration, OTP, privileged provisioning, and account changes on test data.
- Expand from static guard checks to endpoint-level automated role/contract tests.
