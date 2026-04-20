# Phase 3 Smoke Check - 2026-04-20

## Scope
- Incident workflow: status/rejection/remarks, incident data endpoints.
- Monitoring workflow: payment updates, cancel/delete flows, bulk actions.
- Admin form submissions: events, business applications, clearance/certificate/business permit requests.
- RBAC migration validation for admin partial handlers.

## Environment
- Runtime HTTP smoke target: http://localhost/CommunaLink
- Result: PARTIAL PASS
  - Reachability: PASS (XAMPP runtime is reachable).
  - Unauthenticated route guards: PASS (protected pages/handlers redirect to admin entry point).
  - Authenticated session smoke: PASS (admin login succeeded and protected routes are reachable).
  - Full data-mutating workflow run: PARTIAL (safe invalid-input probes used to avoid unintended data changes).

## Executed Checks

### 1. RBAC Handler Migration Check
- Query: search for legacy broad handler guard usage in admin partials.
- Result: PASS
- Evidence: no remaining is_admin_or_official() matches in admin/partials.

### 2. Mutating Endpoint Security Contract Audit
- Verified each key POST mutation handler has:
  - request method guard
  - CSRF validation
  - permission guard
- Result: PASS
- Handlers validated:
  - admin/partials/event-handler.php
  - admin/partials/business-application-handler.php
  - admin/partials/new-barangay-clearance-handler.php
  - admin/partials/new-certificate-of-indigency-handler.php
  - admin/partials/new-certificate-of-residency-handler.php
  - admin/partials/new-business-permit-handler.php
  - admin/partials/update-incident-status-ajax.php
  - admin/partials/update-incident-remarks.php
  - admin/partials/send-business-reminder.php
  - admin/partials/make-cash-payment.php
  - admin/partials/update-payment-info.php
  - admin/partials/delete-document-request.php
  - admin/partials/delete-business-transaction.php
  - admin/partials/cancel-request.php
  - admin/partials/bulk-action-requests.php
  - admin/partials/walk-in-request-handler.php

### 3. Read/Export Endpoint Permission Guard Audit
- Verified permission checks in incident/resident/business read and export handlers.
- Result: PASS
- Handlers validated:
  - admin/partials/dashboard-stats.php
  - admin/partials/fetch-live-incidents.php
  - admin/partials/get-incident-details.php
  - admin/partials/fetch-incident-logs.php
  - admin/partials/search-incidents.php
  - admin/partials/export-incidents-csv.php
  - admin/partials/export-requests.php
  - admin/partials/export-residents-csv.php
  - admin/partials/get-resident-details.php
  - admin/partials/search-residents.php
  - admin/partials/get-permit-details.php

### 4. Page-to-Handler CSRF Wiring Audit
- Verified CSRF fields/tokens are present where required for modified handlers.
- Result: PASS
- Pages validated:
  - admin/pages/events.php
  - admin/pages/business-application-form.php
  - admin/pages/new-barangay-clearance.php
  - admin/pages/new-certificate-of-indigency.php
  - admin/pages/new-certificate-of-residency.php
  - admin/pages/new-barangay-business-clearance.php
  - admin/pages/incident-reports.php
  - admin/pages/monitoring-of-request.php

### 5. IDE Diagnostics
- Checked modified pages/partials for parse/lint issues using workspace diagnostics.
- Result: PASS (no errors found in audited files).

### 6. Runtime HTTP Baseline (Unauthenticated)
- Verified live status/redirect behavior for key entry points and protected routes.
- Result: PASS
- Evidence:
  - GET /index.php => 200 OK
  - GET /admin/pages/incident-reports.php => 302 -> /admin/index.php
  - GET /admin/pages/monitoring-of-request.php => 302 -> /admin/index.php
  - GET /admin/pages/events.php => 302 -> /admin/index.php
  - GET /admin/partials/fetch-live-incidents.php => 302 -> /admin/index.php
  - GET /admin/partials/dashboard-stats.php => 302 -> /admin/index.php

### 7. Runtime Mutating Handler Gate Check (Unauthenticated)
- Sent direct POST requests (without session/CSRF) to critical mutating handlers.
- Result: PASS (all denied by auth guard redirect before execution).
- Evidence:
  - POST /admin/partials/update-incident-status-ajax.php => 302 -> /admin/index.php
  - POST /admin/partials/update-incident-remarks.php => 302 -> /admin/index.php
  - POST /admin/partials/make-cash-payment.php => 302 -> /admin/index.php
  - POST /admin/partials/update-payment-info.php => 302 -> /admin/index.php
  - POST /admin/partials/delete-document-request.php => 302 -> /admin/index.php
  - POST /admin/partials/delete-business-transaction.php => 302 -> /admin/index.php
  - POST /admin/partials/cancel-request.php => 302 -> /admin/index.php
  - POST /admin/partials/bulk-action-requests.php => 302 -> /admin/index.php
  - POST /admin/partials/event-handler.php => 302 -> /admin/index.php
  - POST /admin/partials/business-application-handler.php => 302 -> /admin/index.php

### 8. Runtime Admin Login Check
- Executed CSRF-valid login with provided admin credentials.
- Result: PASS
- Evidence:
  - POST /index.php with CSRF token + admin credentials => 302 Found (authenticated session established)

### 9. Runtime Protected Route Access (Authenticated)
- Verified authenticated access to key pages and read endpoints.
- Result: PASS
- Evidence:
  - GET /admin/index.php => 200 OK
  - GET /admin/pages/incident-reports.php => 200 OK
  - GET /admin/pages/monitoring-of-request.php => 200 OK
  - GET /admin/pages/events.php => 200 OK
  - GET /admin/partials/dashboard-stats.php => 200 OK
  - GET /admin/partials/fetch-live-incidents.php => 200 OK
  - GET /admin/partials/search-incidents.php?query=smoke => 200 OK
  - GET /admin/partials/search-residents.php?query=smoke => 200 OK

### 10. Runtime CSRF + Validation Behavior (Authenticated)
- Verified mutating handlers reject missing CSRF and reject invalid payloads when CSRF is present.
- Result: PASS
- Evidence:
  - POST /admin/partials/update-incident-status-ajax.php without csrf_token => 403 JSON {"error":"Invalid security token."}
  - POST /admin/partials/update-incident-status-ajax.php with csrf_token and invalid id/status payload => 400 JSON {"error":"Invalid Request Data"}
  - POST /admin/partials/make-cash-payment.php without csrf_token => 403 JSON {"error":"Invalid security token."}
  - POST /admin/partials/make-cash-payment.php with csrf_token and invalid params => 400 JSON {"error":"Invalid request parameters"}
  - POST /admin/partials/event-handler.php without csrf_token => 302 -> /admin/pages/events.php

## Findings
- Runtime host is reachable and unauthenticated guard behavior is correct.
- Authenticated route and handler contract behavior is verified (login, permissioned access, CSRF, and validation guards).
- Full real-record mutation scenarios (create/update/delete on actual entities) were intentionally not executed in this run to avoid altering operational data.
- Some hardened endpoints appear unreferenced in current admin/pages and assets code scans:
  - admin/partials/send-business-reminder.php
  - admin/partials/walk-in-request-handler.php
  - admin/partials/update-business-status.php
  - admin/partials/fetch-incident-logs.php

## Recommended Next Runtime Smoke Steps
1. On a disposable test dataset, run full real-entity UI mutations end-to-end:
   - incident status change, rejection with reason, remarks save
   - monitoring payment update, cancel request, document delete, business soft-delete
   - event create/update/delete
   - new business application and new permit form submission
   - new barangay clearance / indigency / residency form submission
2. For unreferenced endpoints, either:
   - wire active UI/AJAX callers and include CSRF token payloads, or
   - deprecate/remove endpoints if no longer used.
