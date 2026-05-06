# Bugfix Requirements Document

## Introduction

When users attempt to create a new account from the login page of the Barangay Management System (CommunaLink), they receive a "This page isn't working right now" error instead of being guided through the registration flow. This prevents any new resident from self-registering on the cloud-deployed system.

The registration flow submits to `includes/register-handler.php`, which is dispatched through the `index.php` front controller on Google App Engine. After validating the form and storing the OTP, the handler calls `OTPEmailService::sendOTP()`. In the production environment (`APP_ENV=production`), if SMTP credentials are not properly configured (e.g., placeholder values like `YOUR_EMAIL_USERNAME` remain in `app.yaml`), `sendOTP()` returns `false`. The production branch then calls `redirect_to('../register.php')` — a relative path that resolves incorrectly when the script is executed via the front-controller dispatch (the `REQUEST_URI` is `/` or `/index.php`, not `/includes/register-handler.php`), producing a broken redirect that results in an HTTP 500 / "This page isn't working right now" response.

## Bug Analysis

### Current Behavior (Defect)

1.1 WHEN a user submits the registration form on the cloud-deployed system AND the SMTP credentials in `app.yaml` are placeholder values (`YOUR_EMAIL_USERNAME` / `SET_VIA_SECRET_MANAGER`) THEN the system crashes with a broken redirect ("This page isn't working right now") instead of showing an error message

1.2 WHEN `register-handler.php` is dispatched through the `index.php` front controller AND `redirect_to('../register.php')` is called THEN the system resolves the relative path against the front-controller's `REQUEST_URI` (e.g., `/`) rather than the handler's own path, producing an invalid redirect target

1.3 WHEN `OTPEmailService::sendOTP()` returns `false` in production AND `APP_ENV=production` THEN the system calls `redirect_to('../register.php')` with a relative path that breaks under front-controller routing, causing a 500 error

1.4 WHEN the SMTP credentials are unconfigured in the production deployment THEN the system provides no fallback path for completing registration, blocking all new account creation

### Expected Behavior (Correct)

2.1 WHEN a user submits the registration form AND SMTP email delivery fails in production THEN the system SHALL display a user-friendly error message on the registration page without crashing

2.2 WHEN `register-handler.php` performs any redirect THEN the system SHALL use absolute application-relative URLs via `app_url()` instead of relative paths like `'../register.php'`, so that redirects resolve correctly regardless of how the script is dispatched

2.3 WHEN `OTPEmailService::sendOTP()` returns `false` in production THEN the system SHALL set `$_SESSION['error_message']` and redirect to `app_url('/register.php')` using an absolute path, returning the user to the registration form with a clear error

2.4 WHEN SMTP credentials in `app.yaml` are placeholder values THEN the system SHALL log the misconfiguration and redirect the user back to the registration page with an actionable error message rather than producing a 500 error

### Unchanged Behavior (Regression Prevention)

3.1 WHEN a user submits the registration form AND SMTP email delivery succeeds THEN the system SHALL CONTINUE TO store the OTP, send the verification email, and redirect the user to `verify-otp.php`

3.2 WHEN a user submits the registration form with invalid or missing fields THEN the system SHALL CONTINUE TO validate inputs and redirect back to `register.php` with the appropriate error message

3.3 WHEN `APP_ENV` is not `production` (e.g., `development`) AND email delivery fails THEN the system SHALL CONTINUE TO use the development OTP fallback (showing the OTP code on the verify page)

3.4 WHEN a user successfully verifies their OTP THEN the system SHALL CONTINUE TO create the user and resident records and redirect to the login page with a success message

3.5 WHEN a user attempts to register with an email that already exists THEN the system SHALL CONTINUE TO reject the registration with an appropriate duplicate-email error message
