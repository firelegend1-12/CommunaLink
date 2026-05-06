# Account Registration Fix - Bugfix Design

## Overview

Account registration on the cloud-deployed CommunaLink Barangay Management System fails with "This page isn't working right now" due to two compounding root causes:

1. **Relative redirect paths** — Every `redirect_to('../register.php')` and `redirect_to('../verify-otp.php')` call in `includes/register-handler.php` uses a relative path. Under Google App Engine's front-controller routing, all requests are dispatched through `index.php` at the web root, so `$_SERVER['REQUEST_URI']` is `/` (not `/includes/register-handler.php`). The `redirect_to()` helper resolves relative paths against `REQUEST_URI`, producing `/register.php` → `/register.php` (correct by accident for some paths) but `../register.php` → an invalid path that triggers an HTTP 500.

2. **Placeholder SMTP credentials** — `app.yaml` ships with `EMAIL_SMTP_USERNAME: "YOUR_EMAIL_USERNAME"`, which `OTPEmailService::hasValidSmtpCredentials()` correctly detects as invalid and returns `false` from `sendOTP()`. The production branch in `register-handler.php` then calls `redirect_to('../register.php')` — the broken relative redirect — instead of setting a session error and redirecting safely.

The fix replaces all relative `redirect_to('../...')` calls in `register-handler.php` with `redirect_to(app_url('/register.php'))` and `redirect_to(app_url('/verify-otp.php'))`, and ensures the SMTP-failure production branch sets `$_SESSION['error_message']` before redirecting. No changes are needed to `otp_email_service.php` — its credential detection already works correctly.

## Glossary

- **Bug_Condition (C)**: The condition that triggers the crash — a registration form submission that reaches a `redirect_to('../...')` call in `register-handler.php` when dispatched through the front controller
- **Property (P)**: The desired behavior — every redirect in `register-handler.php` resolves to a valid absolute path, and SMTP failures produce a user-friendly error page rather than a 500
- **Preservation**: All existing registration flows (successful OTP send, validation errors, dev fallback, OTP verification, duplicate email) that must remain unchanged by the fix
- **`redirect_to($url)`**: Helper in `includes/functions.php` that resolves relative paths against `$_SERVER['REQUEST_URI']`; safe only when given an absolute path or an `app_url()`-generated path
- **`app_url($path)`**: Helper in `includes/functions.php` that prepends `app_base_path()` to produce a correct absolute-from-root URL regardless of deployment context
- **`OTPEmailService::sendOTP()`**: Static method in `includes/otp_email_service.php` that returns `false` when SMTP credentials are placeholders or PHPMailer is unavailable in production
- **`hasValidSmtpCredentials()`**: Private static method in `OTPEmailService` that detects placeholder values like `YOUR_EMAIL_USERNAME` and returns `false`
- **Front-controller routing**: GAE dispatches all requests through `index.php`; `REQUEST_URI` is always the public path (e.g., `/`), never the included handler's filesystem path
- **`$app_env`**: Value of `APP_ENV` env variable; `'production'` on GAE, `'development'` locally

## Bug Details

### Bug Condition

The bug manifests when a user submits the registration form on the cloud-deployed system. `register-handler.php` is included via the front controller, so `REQUEST_URI` is `/`. Any call to `redirect_to('../register.php')` resolves the relative path against `/`, producing `/../register.php` → `/register.php` in some PHP versions, but the `..` traversal above root causes PHP's `header()` to emit a malformed Location header, resulting in an HTTP 500 "This page isn't working right now".

The SMTP placeholder credentials guarantee that `sendOTP()` returns `false` in production, which is the most common trigger path — but the relative-path bug affects every validation redirect too (e.g., empty fields, password mismatch, duplicate email).

**Formal Specification:**
```
FUNCTION isBugCondition(input)
  INPUT: input — an HTTP POST request to the registration form handler
  OUTPUT: boolean

  isDispatchedViaFrontController := (REQUEST_URI does not contain 'register-handler.php')
  callsRelativeRedirect := register-handler.php calls redirect_to() with a '../' prefixed path

  RETURN isDispatchedViaFrontController AND callsRelativeRedirect
END FUNCTION
```

### Examples

- **SMTP placeholder + production**: User submits valid form → `sendOTP()` returns `false` → production branch calls `redirect_to('../register.php')` → broken redirect → HTTP 500 ✗ (should show error message on `/register.php`)
- **Validation failure**: User submits form with empty required fields → `redirect_to('../register.php')` → broken redirect → HTTP 500 ✗ (should return to `/register.php` with error)
- **Password mismatch**: User enters mismatched passwords → `redirect_to('../register.php')` → broken redirect → HTTP 500 ✗ (should return to `/register.php` with error)
- **Successful OTP send**: User submits valid form with working SMTP → `redirect_to('../verify-otp.php')` → broken redirect → HTTP 500 ✗ (should redirect to `/verify-otp.php`)
- **Local development (non-production)**: `REQUEST_URI` is `/includes/register-handler.php` when accessed directly → relative paths resolve correctly → no crash ✓ (this is why the bug only appears on GAE)

## Expected Behavior

### Preservation Requirements

**Unchanged Behaviors:**
- Successful OTP send must continue to store the OTP, send the verification email, and redirect the user to `verify-otp.php`
- Validation errors (empty fields, invalid email, password rules, age check, duplicate email) must continue to redirect back to `register.php` with the appropriate `$_SESSION['error_message']`
- Development OTP fallback (`APP_ENV !== 'production'`) must continue to set `$_SESSION['otp_dev_code']` and proceed to `verify-otp.php`
- OTP verification flow (`verify-otp.php` → account creation → login redirect) must remain completely unaffected
- Duplicate email detection must continue to reject registration with the existing error message
- `OTPEmailService::hasValidSmtpCredentials()` behavior must remain unchanged — it already correctly returns `false` for placeholder credentials

**Scope:**
All inputs that do NOT involve the front-controller relative-path resolution issue are unaffected. This includes:
- Any direct file access in local development (relative paths work when `REQUEST_URI` matches the handler path)
- OTP verification (`verify-otp-handler.php`) — separate handler, not part of this fix
- Admin registration flows — separate handlers
- Password reset flow — separate handler

## Hypothesized Root Cause

Based on the bug description and code review:

1. **Relative paths in `redirect_to()` calls**: `register-handler.php` was written assuming direct file access (where `REQUEST_URI` = `/includes/register-handler.php`), so `../register.php` would resolve to `/register.php`. Under GAE front-controller routing, `REQUEST_URI` = `/`, so `redirect_to()` resolves `../register.php` against `/`, producing a path that traverses above the root — an invalid Location header.

2. **SMTP placeholder credentials in `app.yaml`**: The deployment template ships with `EMAIL_SMTP_USERNAME: "YOUR_EMAIL_USERNAME"`, which is never replaced before deployment. `OTPEmailService::hasValidSmtpCredentials()` correctly detects this and returns `false`, but the handler's production error branch then hits the broken relative redirect.

3. **No actionable error logging for placeholder credentials**: While `otp_email_service.php` logs `"OTP Email: Gmail SMTP credentials not configured."`, there is no log entry that explicitly names the placeholder value detected, making it harder to diagnose in production logs.

4. **`app_url()` helper exists but is unused in `register-handler.php`**: The `app_url()` function in `includes/functions.php` is the correct tool for building deployment-safe absolute URLs, but `register-handler.php` never uses it.

## Correctness Properties

Property 1: Bug Condition - All Redirects in register-handler.php Use Absolute Paths

_For any_ HTTP POST request to the registration handler dispatched through the front controller (where `REQUEST_URI` does not contain `register-handler.php`), the fixed `register-handler.php` SHALL call `redirect_to(app_url('/register.php'))` or `redirect_to(app_url('/verify-otp.php'))` — never a `'../'`-prefixed relative path — so that every redirect resolves to a valid absolute URL and no HTTP 500 is produced.

**Validates: Requirements 2.2**

Property 2: Bug Condition - SMTP Failure Produces User-Friendly Error

_For any_ registration form submission where `OTPEmailService::sendOTP()` returns `false` in a production environment (`APP_ENV=production`), the fixed handler SHALL set `$_SESSION['error_message']` with a user-friendly message and redirect to `app_url('/register.php')`, returning the user to the registration form without a 500 error.

**Validates: Requirements 2.1, 2.3, 2.4**

Property 3: Preservation - Existing Redirect Targets Are Unchanged

_For any_ input where the bug condition does NOT hold (i.e., the redirect path is already correct or the code path is unaffected), the fixed `register-handler.php` SHALL produce the same observable behavior as the original — same session variables set, same destination page reached — preserving all validation, success, and dev-fallback flows.

**Validates: Requirements 3.1, 3.2, 3.3, 3.4, 3.5**

## Fix Implementation

### Changes Required

Assuming our root cause analysis is correct:

**File**: `includes/register-handler.php`

**Specific Changes**:

1. **Replace all `redirect_to('../register.php')` calls with `redirect_to(app_url('/register.php'))`**:
   - There are approximately 10 occurrences throughout the file (validation errors, duplicate email check, OTP store failure, SMTP failure production branch, PDOException catch)
   - Each must be updated to use `app_url('/register.php')` to produce a deployment-safe absolute path

2. **Replace `redirect_to('../verify-otp.php')` with `redirect_to(app_url('/verify-otp.php'))`**:
   - The final success redirect at the bottom of the handler
   - Must use `app_url('/verify-otp.php')` for the same reason

3. **Ensure the SMTP failure production branch sets a session error message before redirecting**:
   - The current production branch already sets `$_SESSION['error_message']` — verify this is present and the message is user-friendly
   - Confirm the redirect uses `app_url('/register.php')` after the fix in point 1

4. **No changes to `otp_email_service.php`**:
   - `hasValidSmtpCredentials()` already correctly detects placeholder values and returns `false`
   - The existing `error_log("OTP Email: Gmail SMTP credentials not configured.")` is sufficient
   - No credential detection logic changes are needed

5. **No changes to `app.yaml`**:
   - Placeholder values are intentional in the template; real credentials are set via Secret Manager
   - The fix makes the application handle missing credentials gracefully rather than crashing

## Testing Strategy

### Validation Approach

The testing strategy follows a two-phase approach: first, surface counterexamples that demonstrate the broken redirect on unfixed code, then verify the fix produces correct absolute redirects and preserves all existing behavior.

### Exploratory Bug Condition Checking

**Goal**: Surface counterexamples that demonstrate the broken redirect BEFORE implementing the fix. Confirm that relative paths in `redirect_to()` produce invalid Location headers when `REQUEST_URI` is `/`.

**Test Plan**: Write unit tests that simulate the front-controller environment (set `$_SERVER['REQUEST_URI'] = '/'`) and call `redirect_to('../register.php')`, asserting that the resulting Location header is `/register.php`. Run these tests on the UNFIXED code to observe the incorrect behavior.

**Test Cases**:
1. **Relative redirect from root REQUEST_URI**: Set `REQUEST_URI = '/'`, call `redirect_to('../register.php')`, assert Location header equals `/register.php` — will fail on unfixed code (produces `/../register.php` or similar)
2. **SMTP failure production redirect**: Simulate `sendOTP()` returning `false` with `APP_ENV=production`, assert that the handler sets `$_SESSION['error_message']` and redirects to `/register.php` — will fail on unfixed code
3. **Validation error redirect**: Simulate empty form submission, assert redirect goes to `/register.php` — will fail on unfixed code
4. **Success redirect**: Simulate successful OTP send, assert redirect goes to `/verify-otp.php` — will fail on unfixed code

**Expected Counterexamples**:
- `redirect_to('../register.php')` with `REQUEST_URI='/'` produces a Location header that is not `/register.php`
- Possible causes: path traversal above root, empty path, or malformed URL

### Fix Checking

**Goal**: Verify that for all inputs where the bug condition holds (front-controller dispatch + any redirect call), the fixed handler produces a valid absolute redirect.

**Pseudocode:**
```
FOR ALL input WHERE isBugCondition(input) DO
  result := register_handler_fixed(input)
  ASSERT result.location_header MATCHES '^/[a-z]'   // absolute path, no '..'
  ASSERT result.http_status IN [301, 302]
  ASSERT result.location_header NOT CONTAINS '../'
END FOR
```

### Preservation Checking

**Goal**: Verify that for all inputs where the bug condition does NOT hold (local dev with direct file access), the fixed handler produces the same behavior as the original.

**Pseudocode:**
```
FOR ALL input WHERE NOT isBugCondition(input) DO
  ASSERT register_handler_original(input).session_vars
       = register_handler_fixed(input).session_vars
  ASSERT register_handler_original(input).redirect_destination
       = register_handler_fixed(input).redirect_destination
END FOR
```

**Testing Approach**: Property-based testing is recommended for preservation checking because:
- It generates many combinations of form inputs automatically
- It catches edge cases in validation logic that manual tests might miss
- It provides strong guarantees that session variable behavior is unchanged across all non-buggy inputs

**Test Plan**: Observe behavior on UNFIXED code for successful flows and validation errors in local dev, then write property-based tests capturing that behavior.

**Test Cases**:
1. **Validation error preservation**: Verify that empty-field, password-mismatch, age-check, and duplicate-email errors continue to set the correct `$_SESSION['error_message']` values after the fix
2. **Dev fallback preservation**: Verify that `APP_ENV=development` + SMTP failure still sets `$_SESSION['otp_dev_code']` and proceeds to `verify-otp.php`
3. **Success flow preservation**: Verify that a valid form submission with working SMTP still stores the OTP, sends the email, sets `$_SESSION['otp_email']` and `$_SESSION['otp_fullname']`, and redirects to `verify-otp.php`

### Unit Tests

- Test `redirect_to(app_url('/register.php'))` with `REQUEST_URI='/'` produces Location: `/register.php`
- Test `redirect_to(app_url('/verify-otp.php'))` with `REQUEST_URI='/'` produces Location: `/verify-otp.php`
- Test that `app_url('/register.php')` returns `/register.php` when app is at web root
- Test SMTP failure branch sets `$_SESSION['error_message']` and redirects to `/register.php` in production
- Test each validation error path sets the correct session error and redirects to `/register.php`

### Property-Based Tests

- Generate random valid registration form inputs and verify that with working SMTP the handler always redirects to `/verify-otp.php` (never a relative path)
- Generate random invalid form inputs (empty fields, bad email, short password, age < 18) and verify the handler always redirects to `/register.php` with a non-empty `$_SESSION['error_message']`
- Generate random `REQUEST_URI` values and verify that `app_url('/register.php')` always returns an absolute path starting with `/` and never containing `../`

### Integration Tests

- End-to-end test: submit registration form on a GAE-like environment (front-controller routing active) with placeholder SMTP credentials → assert user lands on `/register.php` with an error message, not a 500
- End-to-end test: submit registration form with working SMTP → assert user lands on `/verify-otp.php`
- Regression test: submit form with duplicate email → assert user lands on `/register.php` with duplicate-email error message
