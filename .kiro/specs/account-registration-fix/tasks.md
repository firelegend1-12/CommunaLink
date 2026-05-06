# Account Registration Fix - Tasks

## Task List

- [x] 1. Exploratory Testing - Confirm Broken Redirect Behavior
  - [x] 1.1 Write a unit test that sets `$_SERVER['REQUEST_URI'] = '/'` and calls `redirect_to('../register.php')`, asserting the resulting Location header equals `/register.php` — run on unfixed code to observe the failure
  - [x] 1.2 Write a unit test that simulates `sendOTP()` returning `false` with `APP_ENV=production` and asserts the handler sets `$_SESSION['error_message']` and redirects to `/register.php` — run on unfixed code to observe the failure
  - [x] 1.3 Document the counterexamples observed (actual vs expected Location header values) to confirm the root cause

- [x] 2. Fix - Replace Relative Redirects with app_url() in register-handler.php
  - [x] 2.1 Replace all `redirect_to('../register.php')` calls in `includes/register-handler.php` with `redirect_to(app_url('/register.php'))` — covers the non-POST guard, all validation error branches, duplicate email check, OTP store failure, SMTP failure production branch, and PDOException catch
  - [x] 2.2 Replace `redirect_to('../verify-otp.php')` with `redirect_to(app_url('/verify-otp.php'))` in the success path at the bottom of `includes/register-handler.php`
  - [x] 2.3 Verify the SMTP failure production branch already sets a user-friendly `$_SESSION['error_message']` before the redirect (no new message needed if already present; update wording only if the existing message is unclear)

- [x] 3. Fix Checking - Verify Corrected Redirect Behavior
  - [x] 3.1 Re-run the exploratory tests from Task 1 on the fixed code and assert they now pass: `redirect_to(app_url('/register.php'))` with `REQUEST_URI='/'` produces `Location: /register.php`
  - [x] 3.2 Write and run a fix-checking test for the success path: simulate a valid form submission with a mocked `sendOTP()` returning `true`, assert the handler redirects to `/verify-otp.php` (absolute path, no `../`)
  - [x] 3.3 Write and run a fix-checking test for the SMTP failure production path: mock `sendOTP()` returning `false` with `APP_ENV=production`, assert `$_SESSION['error_message']` is set and redirect is to `/register.php`

- [x] 4. Preservation Checking - Verify Existing Behavior Is Unchanged
  - [x] 4.1 Write and run preservation tests for all validation error paths (empty fields, invalid email, password too short, no number in password, no special character, password mismatch, age < 18) — assert each sets the correct `$_SESSION['error_message']` and redirects to `/register.php`
  - [x] 4.2 Write and run a preservation test for the duplicate email path — assert `$_SESSION['error_message']` is set to the duplicate-email message and redirect is to `/register.php`
  - [x] 4.3 Write and run a preservation test for the development OTP fallback (`APP_ENV=development`, `sendOTP()` returns `false`) — assert `$_SESSION['otp_dev_code']` is set and redirect is to `/verify-otp.php`
  - [x] 4.4 Write a property-based test that generates random invalid form inputs and asserts the handler always redirects to `/register.php` with a non-empty `$_SESSION['error_message']` (never a relative path, never a 500)
  - [x] 4.5 Write a property-based test that asserts `app_url('/register.php')` and `app_url('/verify-otp.php')` always return absolute paths starting with `/` and never containing `../`, across varied `$_SERVER` configurations

- [x] 5. Integration Verification
  - [x] 5.1 Manually verify (or write an integration test) that submitting the registration form in a front-controller environment with placeholder SMTP credentials lands the user on `/register.php` with an error message — not a 500
  - [x] 5.2 Manually verify (or write an integration test) that submitting the registration form with valid SMTP credentials lands the user on `/verify-otp.php`
  - [x] 5.3 Confirm no other handler files use `redirect_to('../...')` relative paths that would be affected by the same front-controller routing issue (search `includes/` for `redirect_to\('\.\./` pattern)
