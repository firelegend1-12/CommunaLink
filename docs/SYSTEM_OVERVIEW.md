# CommunaLink Technical System Overview

## 1. System Overview

CommunaLink is a PHP + MySQL barangay management platform with role-based portals for residents and barangay staff. It centralizes resident records, business permits, document requests, incident reporting, announcements/events, notifications, and internal messaging.

### Architecture at a glance

- Frontend interface layer:
  - Public/auth entry: [index.php](../index.php), [register.php](../register.php), [forgot-password.php](../forgot-password.php), [reset-password.php](../reset-password.php)
  - Resident portal pages: [resident/](../resident/)
  - Admin/official portal pages: [admin/](../admin/)
- Backend logic layer:
  - Shared services and utilities: [includes/](../includes/)
  - Role and permission model: [config/permissions.php](../config/permissions.php), [includes/permission_checker.php](../includes/permission_checker.php)
  - Authentication/session management: [includes/auth.php](../includes/auth.php)
- API layer (AJAX/JSON):
  - [api/incidents.php](../api/incidents.php)
  - [api/notifications.php](../api/notifications.php)
  - [api/chat.php](../api/chat.php)
  - [api/announcements.php](../api/announcements.php)
  - [api/post-reactions.php](../api/post-reactions.php)
- Database layer:
  - Connection/bootstrap: [config/database.php](../config/database.php), [config/init.php](../config/init.php)
  - Schema docs/migrations: [docs/database_schema.md](database_schema.md), [migrations/](../migrations/)

### Runtime bootstrap pattern

Most pages and endpoints pull core initialization from [config/init.php](../config/init.php) (or lightweight DB bootstrap in selected APIs), then enforce auth/role checks, execute business logic in includes/API handlers, persist to MySQL through PDO prepared statements, and return rendered HTML or JSON.

## 2. Database Architecture

Source basis: [config/init.php](../config/init.php), [docs/database_schema.md](database_schema.md), migrations in [migrations/](../migrations/), and query usage across [api/](../api/) + [includes/](../includes/) + pages.

### Complete schema dictionary (tables, fields, keys)

Canonical source is [config/init.php](../config/init.php), then additive/normalizing migrations in [migrations/add_is_auto_generated_to_announcements.sql](../migrations/add_is_auto_generated_to_announcements.sql), [migrations/add_cancelled_status_to_document_requests.sql](../migrations/add_cancelled_status_to_document_requests.sql), and [migrations/normalize_walkin_document_requests.sql](../migrations/normalize_walkin_document_requests.sql).

### users
- Fields:
  - id INT(11) NOT NULL AUTO_INCREMENT
  - username VARCHAR(50) NOT NULL
  - password VARCHAR(255) NOT NULL
  - fullname VARCHAR(100) NOT NULL
  - email VARCHAR(100) NOT NULL
  - role ENUM('admin','resident','barangay-officials','barangay-kagawad','barangay-tanod') NOT NULL DEFAULT 'resident'
  - created_at DATETIME DEFAULT CURRENT_TIMESTAMP
  - last_login DATETIME DEFAULT NULL
- Keys:
  - PRIMARY KEY (id)
  - UNIQUE (username)
  - UNIQUE (email)

### residents
- Fields:
  - id INT(11) NOT NULL AUTO_INCREMENT
  - first_name VARCHAR(100) NOT NULL
  - middle_initial VARCHAR(5) DEFAULT NULL
  - last_name VARCHAR(100) NOT NULL
  - gender ENUM('Male','Female','Other') NOT NULL
  - date_of_birth DATE NOT NULL
  - place_of_birth VARCHAR(255) NOT NULL
  - age INT(3) NOT NULL
  - religion VARCHAR(100) DEFAULT NULL
  - citizenship VARCHAR(100) NOT NULL
  - email VARCHAR(100) DEFAULT NULL
  - contact_no VARCHAR(20) DEFAULT NULL
  - address TEXT NOT NULL
  - civil_status ENUM('Single','Married','Widowed','Separated') NOT NULL
  - occupation VARCHAR(100) DEFAULT NULL
  - signature_path VARCHAR(255) DEFAULT NULL
  - profile_image_path VARCHAR(255) DEFAULT NULL
  - id_number VARCHAR(50) DEFAULT NULL
  - voter_status ENUM('Yes','No') NOT NULL DEFAULT 'No'
  - user_id INT(11) DEFAULT NULL
  - created_at DATETIME DEFAULT CURRENT_TIMESTAMP
  - updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
- Keys:
  - PRIMARY KEY (id)
  - UNIQUE (email)
  - UNIQUE (id_number)
  - INDEX idx_residents_name (last_name, first_name)

### businesses
- Fields:
  - id INT(11) NOT NULL AUTO_INCREMENT
  - resident_id INT(11) NOT NULL
  - business_name VARCHAR(255) NOT NULL
  - business_type VARCHAR(100) NOT NULL
  - address TEXT NOT NULL
  - status ENUM('Active','Inactive','Pending') NOT NULL DEFAULT 'Pending'
  - permit_number VARCHAR(50) DEFAULT NULL
  - permit_expiration_date DATE DEFAULT NULL
  - approval_date DATETIME DEFAULT NULL
  - approved_by INT(11) DEFAULT NULL
  - date_registered DATETIME DEFAULT CURRENT_TIMESTAMP
  - updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
  - requested_by_user_id INT(11) NULL
- Keys:
  - PRIMARY KEY (id)

### business_transactions
- Fields:
  - id INT(11) NOT NULL AUTO_INCREMENT
  - resident_id INT(11) NOT NULL
  - business_name VARCHAR(255) NOT NULL
  - business_type VARCHAR(100) NOT NULL
  - owner_name VARCHAR(255) NOT NULL
  - address TEXT NOT NULL
  - transaction_type ENUM('New Permit','Renewal') NOT NULL
  - status ENUM('Pending','Processing','Ready for Pickup','Approved','Rejected','Cancelled') NOT NULL DEFAULT 'Pending'
  - application_date DATETIME DEFAULT CURRENT_TIMESTAMP
  - processed_date DATETIME DEFAULT NULL
  - remarks TEXT DEFAULT NULL
  - or_number VARCHAR(100) DEFAULT NULL
  - payment_status ENUM('Unpaid','Paid') DEFAULT 'Unpaid'
  - payment_date DATETIME DEFAULT NULL
  - cash_received DECIMAL(10,2) DEFAULT NULL
  - change_amount DECIMAL(10,2) DEFAULT NULL
- Keys:
  - PRIMARY KEY (id)
  - INDEX idx_biztrans_status (status)
  - INDEX idx_biztrans_payment_status (payment_status)
  - INDEX idx_biztrans_resident_id (resident_id)
  - INDEX idx_biztrans_status_payment_date (status, payment_status, application_date)

### business_permits
- Fields:
  - id INT(11) NOT NULL AUTO_INCREMENT
  - date_of_application DATE DEFAULT NULL
  - business_account_no VARCHAR(255) DEFAULT NULL
  - official_receipt_no VARCHAR(255) DEFAULT NULL
  - or_date DATE DEFAULT NULL
  - amount_paid DECIMAL(10,2) DEFAULT NULL
  - taxpayer_name VARCHAR(255) DEFAULT NULL
  - taxpayer_tel_no VARCHAR(50) DEFAULT NULL
  - taxpayer_fax_no VARCHAR(50) DEFAULT NULL
  - taxpayer_address TEXT DEFAULT NULL
  - capital DECIMAL(15,2) DEFAULT NULL
  - taxpayer_barangay_no VARCHAR(50) DEFAULT NULL
  - business_trade_name VARCHAR(255) DEFAULT NULL
  - business_tel_no VARCHAR(50) DEFAULT NULL
  - comm_address_building VARCHAR(255) DEFAULT NULL
  - comm_address_no VARCHAR(50) DEFAULT NULL
  - comm_address_street VARCHAR(255) DEFAULT NULL
  - comm_address_barangay_no VARCHAR(50) DEFAULT NULL
  - dti_reg_no VARCHAR(255) DEFAULT NULL
  - sec_reg_no VARCHAR(255) DEFAULT NULL
  - num_employees INT(11) DEFAULT NULL
  - main_line_business VARCHAR(255) DEFAULT NULL
  - other_line_business TEXT DEFAULT NULL
  - main_products_services TEXT DEFAULT NULL
  - other_products_services VARCHAR(255) DEFAULT NULL
  - ownership_type ENUM('single','partnership','corporation') DEFAULT NULL
  - proof_of_ownership ENUM('owned','leased') DEFAULT NULL
  - proof_owned_reg_name VARCHAR(255) DEFAULT NULL
  - proof_leased_lessor_name VARCHAR(255) DEFAULT NULL
  - rent_per_month DECIMAL(10,2) DEFAULT NULL
  - area_sq_meter DECIMAL(10,2) DEFAULT NULL
  - real_property_tax_receipt_no VARCHAR(255) DEFAULT NULL
  - has_barangay_clearance TINYINT(1) DEFAULT 0
  - has_public_liability_insurance TINYINT(1) DEFAULT 0
  - insurance_company VARCHAR(255) DEFAULT NULL
  - insurance_date DATE DEFAULT NULL
  - applicant_name VARCHAR(255) DEFAULT NULL
  - applicant_position VARCHAR(255) DEFAULT NULL
  - status ENUM('Pending','Approved','Rejected') NOT NULL DEFAULT 'Pending'
  - created_at DATETIME DEFAULT CURRENT_TIMESTAMP
  - updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
- Keys:
  - PRIMARY KEY (id)

### document_requests
- Fields:
  - id INT(11) NOT NULL AUTO_INCREMENT
  - resident_id INT(11) NOT NULL
  - document_type VARCHAR(255) NOT NULL
  - purpose TEXT NOT NULL
  - details JSON
  - date_requested DATETIME DEFAULT CURRENT_TIMESTAMP
  - status ENUM('Pending','Processing','Ready for Pickup','Completed','Rejected','Cancelled') NOT NULL DEFAULT 'Pending'
  - price DECIMAL(10,2) DEFAULT NULL
  - remarks TEXT DEFAULT NULL
  - requested_by_user_id INT(11) NULL
  - or_number VARCHAR(100) DEFAULT NULL
  - payment_status ENUM('Unpaid','Paid') DEFAULT 'Unpaid'
  - payment_date DATETIME DEFAULT NULL
  - cash_received DECIMAL(10,2) DEFAULT NULL
  - change_amount DECIMAL(10,2) DEFAULT NULL
- Keys:
  - PRIMARY KEY (id)
  - INDEX idx_docreq_date_requested (date_requested)
  - INDEX idx_docreq_status (status)
  - INDEX idx_docreq_payment_status (payment_status)
  - INDEX idx_docreq_resident_id (resident_id)
  - INDEX idx_docreq_status_payment_date (status, payment_status, date_requested)

### incidents
- Fields:
  - id INT(11) NOT NULL AUTO_INCREMENT
  - resident_user_id INT(11) NOT NULL
  - type VARCHAR(100) NOT NULL
  - location VARCHAR(255) NOT NULL
  - latitude DECIMAL(10,8) DEFAULT NULL
  - longitude DECIMAL(11,8) DEFAULT NULL
  - description TEXT NOT NULL
  - media_path VARCHAR(255) DEFAULT NULL
  - status ENUM('Pending','In Progress','Resolved','Rejected') NOT NULL DEFAULT 'Pending'
  - reported_at DATETIME DEFAULT CURRENT_TIMESTAMP
  - admin_remarks TEXT DEFAULT NULL
- Keys:
  - PRIMARY KEY (id)
  - INDEX idx_incidents_reported_at (reported_at)
  - INDEX idx_incidents_status (status)

### chat_messages
- Fields:
  - id INT(11) NOT NULL AUTO_INCREMENT
  - sender_id INT(11) NOT NULL
  - receiver_id INT(11) NOT NULL
  - message TEXT NOT NULL
  - sent_at DATETIME DEFAULT CURRENT_TIMESTAMP
  - is_read TINYINT(1) NOT NULL DEFAULT 0
- Keys:
  - PRIMARY KEY (id)

### announcements
- Fields:
  - id INT(11) NOT NULL AUTO_INCREMENT
  - user_id INT(11) NOT NULL
  - is_auto_generated TINYINT(1) NOT NULL DEFAULT 0
  - related_business_id INT(11) DEFAULT NULL
  - related_permit_number VARCHAR(100) DEFAULT NULL
  - title VARCHAR(255) NOT NULL
  - content TEXT NOT NULL
  - status VARCHAR(20) NOT NULL DEFAULT 'active'
  - priority VARCHAR(20) NOT NULL DEFAULT 'normal'
  - target_audience VARCHAR(50) NOT NULL DEFAULT 'all'
  - publish_date DATETIME DEFAULT NULL
  - expiry_date DATETIME DEFAULT NULL
  - read_count INT(11) NOT NULL DEFAULT 0
  - is_event TINYINT(1) NOT NULL DEFAULT 0
  - event_date DATE DEFAULT NULL
  - event_time TIME DEFAULT NULL
  - event_location VARCHAR(255) DEFAULT NULL
  - event_type VARCHAR(50) DEFAULT NULL
  - image_path VARCHAR(255) DEFAULT NULL
  - created_at DATETIME DEFAULT CURRENT_TIMESTAMP
- Keys:
  - PRIMARY KEY (id)
  - INDEX idx_announcements_created_at (created_at)
  - INDEX idx_announcements_status_dates (status, publish_date, expiry_date, created_at)

### announcement_reads
- Fields:
  - id INT(11) NOT NULL AUTO_INCREMENT
  - announcement_id INT(11) NOT NULL
  - resident_id INT(11) NOT NULL
  - read_at DATETIME DEFAULT CURRENT_TIMESTAMP
- Keys:
  - PRIMARY KEY (id)
  - UNIQUE KEY uniq_announcement_resident (announcement_id, resident_id)
  - INDEX idx_announcement_reads_resident (resident_id)
  - INDEX idx_announcement_reads_announcement (announcement_id)

### events
- Fields:
  - id INT(11) NOT NULL AUTO_INCREMENT
  - title VARCHAR(255) NOT NULL
  - description TEXT DEFAULT NULL
  - location VARCHAR(255) DEFAULT NULL
  - event_date DATE DEFAULT NULL
  - event_time TIME DEFAULT NULL
  - type ENUM('Upcoming Event','Regular Activity') NOT NULL
  - created_by INT(11) NOT NULL
  - created_at DATETIME DEFAULT CURRENT_TIMESTAMP
- Keys:
  - PRIMARY KEY (id)
- Migration behavior:
  - Existing rows may be migrated into announcements and table may be renamed to events_migrated by runtime migration logic.

### activity_logs
- Fields:
  - id INT AUTO_INCREMENT
  - user_id INT
  - username VARCHAR(100)
  - action VARCHAR(50)
  - target_type VARCHAR(50)
  - target_id INT
  - details TEXT
  - ip_address VARCHAR(45) DEFAULT NULL
  - user_agent VARCHAR(255) DEFAULT NULL
  - session_id VARCHAR(128) DEFAULT NULL
  - request_id VARCHAR(100) DEFAULT NULL
  - severity ENUM('info','warning','error','critical') NOT NULL DEFAULT 'info'
  - old_value TEXT DEFAULT NULL
  - new_value TEXT DEFAULT NULL
  - prev_hash CHAR(64) DEFAULT NULL
  - log_hash CHAR(64) DEFAULT NULL
  - created_at DATETIME DEFAULT CURRENT_TIMESTAMP
- Keys:
  - PRIMARY KEY (id)
  - INDEX idx_activity_logs_created_at (created_at)
  - INDEX idx_activity_logs_action (action)
  - INDEX idx_activity_logs_username (username)
  - INDEX idx_activity_logs_target_type (target_type)
  - INDEX idx_activity_logs_severity (severity)
  - INDEX idx_activity_logs_request_id (request_id)
  - INDEX idx_activity_logs_log_hash (log_hash)

### activity_logs_archive
- Fields:
  - id INT AUTO_INCREMENT
  - source_log_id INT DEFAULT NULL
  - user_id INT
  - username VARCHAR(100)
  - action VARCHAR(50)
  - target_type VARCHAR(50)
  - target_id INT
  - details TEXT
  - ip_address VARCHAR(45) DEFAULT NULL
  - user_agent VARCHAR(255) DEFAULT NULL
  - session_id VARCHAR(128) DEFAULT NULL
  - request_id VARCHAR(100) DEFAULT NULL
  - severity ENUM('info','warning','error','critical') NOT NULL DEFAULT 'info'
  - old_value TEXT DEFAULT NULL
  - new_value TEXT DEFAULT NULL
  - prev_hash CHAR(64) DEFAULT NULL
  - log_hash CHAR(64) DEFAULT NULL
  - created_at DATETIME DEFAULT NULL
  - archived_at DATETIME DEFAULT CURRENT_TIMESTAMP
  - archive_batch_id VARCHAR(64) DEFAULT NULL
- Keys:
  - PRIMARY KEY (id)
  - INDEX idx_activity_logs_archive_created_at (created_at)
  - INDEX idx_activity_logs_archive_batch_id (archive_batch_id)

### activity_log_archive_batches
- Fields:
  - id INT AUTO_INCREMENT
  - batch_id VARCHAR(64) NOT NULL
  - previous_batch_hash CHAR(64) DEFAULT NULL
  - batch_hash CHAR(64) NOT NULL
  - start_log_id INT DEFAULT NULL
  - end_log_id INT DEFAULT NULL
  - entry_count INT NOT NULL DEFAULT 0
  - created_at DATETIME DEFAULT CURRENT_TIMESTAMP
- Keys:
  - PRIMARY KEY (id)
  - UNIQUE KEY uniq_archive_batch_id (batch_id)

### notifications
- Fields:
  - id INT(11) NOT NULL AUTO_INCREMENT
  - user_id INT(11) NOT NULL
  - title VARCHAR(255) NOT NULL
  - message TEXT NOT NULL
  - type VARCHAR(50) DEFAULT 'general'
  - link VARCHAR(255) DEFAULT NULL
  - is_read TINYINT(1) DEFAULT 0
  - created_at DATETIME DEFAULT CURRENT_TIMESTAMP
- Keys:
  - PRIMARY KEY (id)
- Schema-evolution note:
  - Runtime migration also contains compatibility logic for legacy resident_id-based notifications data.

### active_user_sessions
- Fields:
  - id INT(11) NOT NULL AUTO_INCREMENT
  - session_id VARCHAR(128) NOT NULL
  - user_id INT(11) DEFAULT NULL
  - role VARCHAR(50) NOT NULL
  - ip_address VARCHAR(45) DEFAULT NULL
  - user_agent VARCHAR(255) DEFAULT NULL
  - started_at DATETIME DEFAULT CURRENT_TIMESTAMP
  - last_seen_at DATETIME DEFAULT CURRENT_TIMESTAMP
  - expires_at DATETIME DEFAULT NULL
  - is_active TINYINT(1) NOT NULL DEFAULT 1
  - ended_at DATETIME DEFAULT NULL
  - ended_reason VARCHAR(50) DEFAULT NULL
- Keys:
  - PRIMARY KEY (id)
  - UNIQUE KEY uniq_active_session_id (session_id)
  - INDEX idx_active_sessions_role_active_expires (role, is_active, expires_at)
  - INDEX idx_active_sessions_user_active (user_id, is_active)

### post_reactions
- Fields:
  - id INT(11) NOT NULL AUTO_INCREMENT
  - post_id INT(11) NOT NULL
  - resident_id INT(11) NOT NULL
  - reaction_type ENUM('like','acknowledge') NOT NULL
  - created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
- Keys:
  - PRIMARY KEY (id)
  - UNIQUE KEY unique_reaction (post_id, resident_id, reaction_type)

### Relationship map (explicit FK constraints)

- residents.user_id -> users.id (ON DELETE SET NULL)
- businesses.resident_id -> residents.id (ON DELETE CASCADE)
- businesses.requested_by_user_id -> users.id (ON DELETE SET NULL)
- businesses.approved_by -> users.id (ON DELETE SET NULL)
- business_transactions.resident_id -> residents.id (ON DELETE CASCADE)
- document_requests.resident_id -> residents.id (ON DELETE CASCADE)
- document_requests.requested_by_user_id -> users.id (ON DELETE SET NULL)
- incidents.resident_user_id -> users.id (ON DELETE CASCADE)
- chat_messages.sender_id -> users.id (ON DELETE CASCADE)
- chat_messages.receiver_id -> users.id (ON DELETE CASCADE)
- announcements.user_id -> users.id (ON DELETE CASCADE)
- announcement_reads.announcement_id -> announcements.id (ON DELETE CASCADE)
- announcement_reads.resident_id -> residents.id (ON DELETE CASCADE)
- events.created_by -> users.id (ON DELETE CASCADE)
- notifications.user_id -> users.id (ON DELETE CASCADE)
- active_user_sessions.user_id -> users.id (ON DELETE SET NULL)
- post_reactions.post_id -> announcements.id (ON DELETE CASCADE)
- post_reactions.resident_id -> users.id (ON DELETE CASCADE)

### Schema evolution and deployment notes

- announcements is extended after base create through migration/runtime add-column logic (status, priority, audience, publish/expiry, read_count, event metadata, auto-generated metadata).
- document_requests and business_transactions receive payment fields (or_number, payment_status, payment_date, cash_received, change_amount) via additive ALTERs.
- document_requests status includes Cancelled in both runtime and dedicated migration.
- normalize_walkin_document_requests.sql performs data normalization only (no structural change).
- Because init.php executes create-plus-migrate logic, production schema can differ from bare create blocks if migrations have run partially; this section reflects the intended post-migration state.

## 3. Core Features and Functional Modules

### A. Authentication and Account Lifecycle

- Login/session establishment and role routing:
  - [index.php](../index.php)
  - [includes/auth.php](../includes/auth.php)
- Registration/password flows:
  - [register.php](../register.php)
  - [includes/register-handler.php](../includes/register-handler.php)
  - [forgot-password.php](../forgot-password.php)
  - [reset-password.php](../reset-password.php)

How it works:
- User credentials are verified against users table, session is regenerated, role/session context is stored, and user is redirected to resident or admin surface.

### B. Resident Services

- Resident dashboard and account:
  - [resident/dashboard.php](../resident/dashboard.php)
  - [resident/account.php](../resident/account.php)
- Document request submission and tracking:
  - [resident/new-barangay-clearance.php](../resident/new-barangay-clearance.php)
  - [resident/new-certificate-of-residency.php](../resident/new-certificate-of-residency.php)
  - [resident/new-certificate-of-indigency.php](../resident/new-certificate-of-indigency.php)
  - [resident/my-requests.php](../resident/my-requests.php)
  - [resident/request-details.php](../resident/request-details.php)
- Incident reporting:
  - [resident/report-incident.php](../resident/report-incident.php)
  - [api/incidents.php](../api/incidents.php)
- Business permit request flow:
  - [resident/new-barangay-business-clearance.php](../resident/new-barangay-business-clearance.php)
  - [resident/business-details.php](../resident/business-details.php)

How it works:
- Resident submits forms, handlers validate and persist records, statuses are updated by staff workflows, and residents retrieve status/notifications from portal or API calls.

### C. Admin and Barangay Operations

- Operational dashboard and analytics:
  - [admin/index.php](../admin/index.php)
  - [includes/cache_manager.php](../includes/cache_manager.php)
- Resident/user administration:
  - [admin/pages/residents.php](../admin/pages/residents.php)
  - [admin/pages/user-management.php](../admin/pages/user-management.php)
- Document processing and business permit handling:
  - [admin/pages/monitoring-of-request.php](../admin/pages/monitoring-of-request.php)
  - [admin/pages/business-clearance.php](../admin/pages/business-clearance.php)
- Incident management:
  - [admin/pages/incident-reports.php](../admin/pages/incident-reports.php)
- Announcements/events:
  - [admin/pages/announcements.php](../admin/pages/announcements.php)
  - [admin/pages/events.php](../admin/pages/events.php)
- Audit/log monitoring:
  - [admin/pages/logs.php](../admin/pages/logs.php)

How it works:
- Staff and admins process queued submissions, update statuses, create official content, and monitor operational activity with role-bound permissions.

### D. Notifications and Communication

- In-app + email notification service:
  - [includes/notification_system.php](../includes/notification_system.php)
  - [api/notifications.php](../api/notifications.php)
  - [resident/partials/mark-notifications-read.php](../resident/partials/mark-notifications-read.php)
- Chat subsystem:
  - [api/chat.php](../api/chat.php)
  - [resident/chat.php](../resident/chat.php)

How it works:
- System events create notification rows and optionally send emails using fallback providers; chat endpoints support message send/fetch/read-state.

### E. Scheduled and Support Operations

- Permit expiry checks and auto announcements:
  - [cron_check_expiring_permits.php](../cron_check_expiring_permits.php)
  - [includes/business_announcement_functions.php](../includes/business_announcement_functions.php)
- Session cleanup:
  - [cron_cleanup_active_sessions.php](../cron_cleanup_active_sessions.php)
- Environment and health checks:
  - [test_env.php](../test_env.php)
  - [check_db.php](../check_db.php)
  - [check_db_precision.php](../check_db_precision.php)

## 4. User Roles, Permissions, and Restrictions

### Roles identified

Defined in [config/permissions.php](../config/permissions.php):
- admin
- barangay-officials
- barangay-kagawad
- barangay-tanod
- resident

### Access control logic

- Permission model and role hierarchy:
  - [config/permissions.php](../config/permissions.php)
- Permission check helpers:
  - [includes/permission_checker.php](../includes/permission_checker.php)
- Session/auth gatekeeping:
  - [includes/auth.php](../includes/auth.php)
- Endpoint/page-level role checks:
  - [api/incidents.php](../api/incidents.php)
  - [api/notifications.php](../api/notifications.php)
  - [admin/pages/](../admin/pages/)
  - [resident/](../resident/)

### Restrictions enforced

- Non-authenticated access is blocked by login checks.
- Endpoint actions are role-gated (resident-only reporting, admin/official operational endpoints).
- Role hierarchy restricts override/approval to higher-authority roles.
- Concurrency/session policy limits active sessions for admin/official roles through active_user_sessions logic.

### How unauthorized actions are prevented

- Session-bound role verification before protected operations.
- Permission checks against role capability matrix.
- Request rejection with HTTP 401/403 for unauthorized API calls.
- Database ownership constraints in update statements (for example user-scoped notification updates).

## 5. Security Posture Assessment

### Authentication mechanisms

- Password-based auth with session cookies and login state in [includes/auth.php](../includes/auth.php).
- Session hardening in [config/init.php](../config/init.php): secure/httponly/samesite handling and strict mode.
- Session ID regeneration on authentication and active session tracking in DB.

### Password handling and hashing

- Password policy/strength checks in [includes/password_security.php](../includes/password_security.php).
- Hash verification/creation via PHP password APIs in auth and registration handlers.

### Session/token handling

- PHP session used as primary auth state.
- CSRF token generation/validation in [includes/csrf.php](../includes/csrf.php), required in mutating API operations.
- Active session tracking and cleanup in [includes/auth.php](../includes/auth.php) + [cron_cleanup_active_sessions.php](../cron_cleanup_active_sessions.php).

### Input validation and sanitization

- Central validation framework in [includes/input_validator.php](../includes/input_validator.php).
- File upload validation (MIME/size/extension controls) applied in incident upload path.
- Additional request sanitization helpers in shared functions and endpoint handlers.

### Protection against common vulnerabilities

- SQL injection:
  - PDO prepared statements are used in core APIs and business handlers.
- CSRF:
  - CSRF token checks are present in POST operations in APIs/forms.
- XSS:
  - Output and input sanitization patterns are used; security headers include CSP and framing/content-type protections in [includes/security_headers.php](../includes/security_headers.php).
- Abuse/rate control:
  - Action-specific throttling in [includes/rate_limiter.php](../includes/rate_limiter.php).

### Observed security gaps/risks

- CSP currently allows unsafe-inline for scripts/styles (reduced XSS hardness).
- Trust of forwarded IP headers in rate-limit identification can be proxy-config sensitive.
- Access checks are not uniformly centralized middleware; some page-level checks are inline and easy to drift.
- Password reset flow should be treated as sensitive path needing strict token lifecycle and replay controls.
- Mixed bootstrap patterns (full init vs db-only paths) can create inconsistent policy/application behavior across endpoints.

## 6. System Capabilities and Limitations

### What the system can do

- Manage users/residents and role-specific barangay workflows.
- Accept and process document requests and business permit transactions.
- Receive and track incident reports with optional media and geolocation.
- Publish announcements/events and notify users in-app and by email.
- Support resident-admin chat and operational dashboards with logs.
- Run scheduled maintenance/automation tasks via cron scripts.

### What it explicitly prevents

- Unauthorized access to protected pages/APIs without valid session.
- Role-incompatible actions through capability matrix and role checks.
- Excessive request bursts through endpoint-specific rate limiting.
- Invalid or dangerous file uploads through validator restrictions.

### Hard-coded limits and constraints

- Role taxonomy and hierarchy are code-defined (custom role expansion requires code changes).
- Session concurrency caps for admin/official roles are env-driven but hard bounded in logic.
- Workflow states rely on fixed enum/status values in DB and handlers.
- Some subsystems are polling-based (chat/notifications), not real-time socket architecture.

### Missing validation or design constraints (not exhaustive)

- Security behavior consistency depends on endpoint-level discipline.
- Schema evolution shows legacy compatibility edges that require migration hygiene.
- Caching and file storage are local-path based and may need redesign for horizontal scale.

## 7. Execution Flow

### Flow A: Login to dashboard

1. UI submits credentials from [index.php](../index.php).
2. [includes/auth.php](../includes/auth.php) validates credentials and role.
3. Session is created/regenerated; active session policy is applied.
4. User is redirected to resident/admin dashboard page.
5. Dashboard page queries DB and renders role-specific data.

### Flow B: Resident incident report

1. Resident submits report form in [resident/report-incident.php](../resident/report-incident.php).
2. POST hits [api/incidents.php](../api/incidents.php) with CSRF token.
3. Endpoint enforces resident role, validates fields/upload, rate limits call.
4. Incident row is inserted into incidents table.
5. JSON response returns success/error; admin later processes report in [admin/pages/incident-reports.php](../admin/pages/incident-reports.php).

### Flow C: Document request lifecycle

1. Resident submits request via resident request page and handler in [resident/partials/](../resident/partials/).
2. Request is stored in document_requests with initial status.
3. Admin/official reviews and updates request in [admin/pages/monitoring-of-request.php](../admin/pages/monitoring-of-request.php).
4. Notification service creates in-app updates and optional email.
5. Resident sees status in [resident/my-requests.php](../resident/my-requests.php) and [resident/notifications.php](../resident/notifications.php).

### Flow D: Mark notification as read

1. Resident action triggers call from [resident/partials/mark-notifications-read.php](../resident/partials/mark-notifications-read.php).
2. [api/notifications.php](../api/notifications.php) checks auth, role, rate limit, and CSRF.
3. UPDATE executes on notifications scoped to current user_id.
4. JSON response returns updated status/count.

### Flow E: Announcement/event publishing

1. Admin/authorized official creates content in [admin/pages/announcements.php](../admin/pages/announcements.php) or [admin/pages/events.php](../admin/pages/events.php).
2. Insert persists to announcements/events tables.
3. Notification pathways distribute in-app items and email (as configured).
4. Residents retrieve content from resident pages and announcement APIs.

---

## Notes on Evidence Confidence

- High confidence: core architecture, role model, major module boundaries, and primary table structure from bootstrap/schema docs.
- Medium confidence: fields introduced by later migrations/runtime usage where schema docs and bootstrap differ.
- Action for maintainers: treat [migrations/](../migrations/) as source of truth for deployment-state schema and keep [docs/database_schema.md](database_schema.md) synchronized.

## 8. Full Feature-Process Inventory

Legend:
- Actor: public, resident, admin, official, system.
- Trigger: GET, POST, AJAX, cron, CLI/manual.
- Tables touched are inferred from SQL and called handlers in current code.

### A. Root entry, auth, cron, and diagnostics

| File | Process | Actor | Trigger | Inputs | Outputs | Tables touched |
|---|---|---|---|---|---|---|
| [index.php](../index.php) | Login/auth routing | public -> role user | POST | username, password, CSRF | session + redirect / error | users, active_user_sessions |
| [register.php](../register.php) | Registration page | public | GET | N/A | HTML form | N/A |
| [forgot-password.php](../forgot-password.php) | Password reset request | public | GET/POST | email, CSRF | reset link flow / errors | users (reset token fields) |
| [reset-password.php](../reset-password.php) | Password reset completion | public | GET/POST | token, new password, CSRF | password update + redirect | users |
| [setup_env.php](../setup_env.php) | Environment bootstrap utility | system | CLI/manual | env vars/templates | setup output | N/A |
| [cron_check_expiring_permits.php](../cron_check_expiring_permits.php) | Expiring permit automation | system | cron | date/time window | generated reminders/announcements | businesses, announcements, notifications |
| [cron_cleanup_active_sessions.php](../cron_cleanup_active_sessions.php) | Expired session cleanup | system | cron | N/A | deactivation summary | active_user_sessions |
| [check_db.php](../check_db.php) | DB structure/health check | admin/system | GET/CLI | env mode | textual status | schema/tables introspection |
| [check_db_precision.php](../check_db_precision.php) | DB precision check | admin/system | GET/CLI | env mode | textual status | schema/tables introspection |
| [test_env.php](../test_env.php) | Environment smoke test | admin/system | GET/CLI | N/A | pass/fail details | N/A |
| [test_email.php](../test_email.php) | Email sender smoke test | admin/system | GET/CLI | recipient/config | send result | users (optional lookup), notifications (optional) |
| [test_index.php](../test_index.php) | Login/index test harness | admin/system | GET | N/A | diagnostics | users (optional) |
| [test_notification_service_smoke.php](../test_notification_service_smoke.php) | Notification smoke test | admin/system | GET/CLI | sample user/message | send/create result | notifications, users |

### B. API endpoints

| File | Process | Actor | Trigger | Inputs | Outputs | Tables touched |
|---|---|---|---|---|---|---|
| [api/announcements.php](../api/announcements.php) | Announcement feed API | resident | GET | action/filter params | JSON payload | announcements, post_reactions, users |
| [api/chat.php](../api/chat.php) | Chat send/fetch/read API | resident/admin | AJAX GET/POST | message, peer ids, action, CSRF | JSON payload | chat_messages, users |
| [api/csp-report.php](../api/csp-report.php) | CSP violation ingestion | system/browser | POST | CSP report body | JSON ack/status | activity_logs (or server logs) |
| [api/incidents.php](../api/incidents.php) | Incident create/update API | resident/admin | AJAX GET/POST | incident fields, media, action, CSRF | JSON payload | incidents, notifications, users |
| [api/notifications.php](../api/notifications.php) | Notification read/list API | resident/admin | AJAX GET/POST | action, id(s), CSRF | JSON payload | notifications |
| [api/post-reactions.php](../api/post-reactions.php) | Announcement reaction toggle/count | resident | AJAX POST | post_id, reaction_type, CSRF | JSON payload | post_reactions, announcements |

### C. Resident pages

| File | Process | Actor | Trigger | Inputs | Outputs | Tables touched |
|---|---|---|---|---|---|---|
| [resident/account.php](../resident/account.php) | Resident account view | resident | GET | session user | HTML page | users, residents |
| [resident/announcements.php](../resident/announcements.php) | Resident announcements feed | resident | GET | paging/filter | HTML feed | announcements, post_reactions, users |
| [resident/barangay-services.php](../resident/barangay-services.php) | Services catalog entry point | resident | GET | N/A | HTML page | N/A/derived counts |
| [resident/business-details.php](../resident/business-details.php) | Business application details | resident | GET | application id | HTML page | business_transactions, business_permits, residents |
| [resident/chat.php](../resident/chat.php) | Resident chat UI | resident | GET + AJAX | peer/action/message | HTML + JSON via API | chat_messages, users |
| [resident/dashboard.php](../resident/dashboard.php) | Resident dashboard summary | resident | GET | session user | HTML page | document_requests, incidents, announcements, notifications |
| [resident/emergency-contacts.php](../resident/emergency-contacts.php) | Emergency contacts view | resident | GET | N/A | HTML page | N/A or config-driven |
| [resident/events.php](../resident/events.php) | Community events listing | resident | GET | date/filter | HTML page | events/announcements (event rows) |
| [resident/my-reports.php](../resident/my-reports.php) | Resident incident history | resident | GET | session user | HTML page | incidents |
| [resident/my-requests.php](../resident/my-requests.php) | Resident request history | resident | GET | session user | HTML page | document_requests, business_transactions |
| [resident/new-barangay-business-clearance.php](../resident/new-barangay-business-clearance.php) | Business clearance form | resident | GET | prefill/session | HTML form | residents |
| [resident/new-barangay-clearance.php](../resident/new-barangay-clearance.php) | Barangay clearance form | resident | GET | prefill/session | HTML form | residents |
| [resident/new-certificate-of-indigency.php](../resident/new-certificate-of-indigency.php) | Indigency certificate form | resident | GET | prefill/session | HTML form | residents |
| [resident/new-certificate-of-residency.php](../resident/new-certificate-of-residency.php) | Residency certificate form | resident | GET | prefill/session | HTML form | residents |
| [resident/notifications.php](../resident/notifications.php) | Resident notifications center | resident | GET | session user | HTML page | notifications |
| [resident/report-details.php](../resident/report-details.php) | Incident detail view | resident | GET | incident id | HTML page | incidents |
| [resident/report-incident.php](../resident/report-incident.php) | Incident report form | resident | GET/POST->API | incident fields/media | HTML form + JSON via API | incidents |
| [resident/request-details.php](../resident/request-details.php) | Request detail view | resident | GET | request id | HTML page | document_requests, business_transactions |

### D. Resident partial handlers and UI partials

| File | Process | Actor | Trigger | Inputs | Outputs | Tables touched |
|---|---|---|---|---|---|---|
| [resident/partials/account-handler.php](../resident/partials/account-handler.php) | Account update handler | resident | POST | profile fields, uploads, CSRF | redirect/JSON | users, residents |
| [resident/partials/cancel-business-application.php](../resident/partials/cancel-business-application.php) | Cancel business request | resident | AJAX POST | transaction id, CSRF | JSON | business_transactions, notifications |
| [resident/partials/cancel-document-request.php](../resident/partials/cancel-document-request.php) | Cancel document request | resident | AJAX POST | request id, CSRF | JSON | document_requests, notifications |
| [resident/partials/fetch-live-updates.php](../resident/partials/fetch-live-updates.php) | Polling/live updates | resident | AJAX GET | since/id cursors | JSON | notifications, document_requests, business_transactions |
| [resident/partials/mark-notifications-read.php](../resident/partials/mark-notifications-read.php) | Mark notifications read (compat shim) | resident | AJAX POST | id/all + CSRF | JSON | notifications |
| [resident/partials/submit-business-permit.php](../resident/partials/submit-business-permit.php) | Submit business permit request | resident | POST | business permit form payload | JSON/redirect | business_permits, business_transactions, residents |
| [resident/partials/submit-clearance.php](../resident/partials/submit-clearance.php) | Submit barangay clearance request | resident | POST | form fields/details json | JSON/redirect | document_requests, residents |
| [resident/partials/submit-indigency.php](../resident/partials/submit-indigency.php) | Submit indigency request | resident | POST | form fields/details json | JSON/redirect | document_requests, residents |
| [resident/partials/submit-residency.php](../resident/partials/submit-residency.php) | Submit residency request | resident | POST | form fields/details json | JSON/redirect | document_requests, residents |
| [resident/partials/header.php](../resident/partials/header.php) | Shared resident header | resident | include/render | session/page vars | HTML fragment | notifications (badge counts, optional) |
| [resident/partials/footer.php](../resident/partials/footer.php) | Shared resident footer | resident | include/render | N/A | HTML fragment | N/A |
| [resident/partials/sidebar.php](../resident/partials/sidebar.php) | Shared resident sidebar | resident | include/render | session role/path | HTML fragment | N/A |
| [resident/partials/bottom-nav.php](../resident/partials/bottom-nav.php) | Shared mobile nav | resident | include/render | route state | HTML fragment | N/A |

### E. Admin pages

| File | Process | Actor | Trigger | Inputs | Outputs | Tables touched |
|---|---|---|---|---|---|---|
| [admin/index.php](../admin/index.php) | Admin dashboard | admin/official | GET | filters (optional) | HTML dashboard | residents, users, incidents, document_requests, business_transactions, announcements, events, notifications |
| [admin/pages/about-us.php](../admin/pages/about-us.php) | About page | admin/official | GET | N/A | HTML page | N/A |
| [admin/pages/account.php](../admin/pages/account.php) | Admin account page | admin/official | GET | session user | HTML page | users |
| [admin/pages/add-resident.php](../admin/pages/add-resident.php) | Add resident form | admin/official | GET | N/A | HTML form | N/A |
| [admin/pages/add-user.php](../admin/pages/add-user.php) | Add user form | admin | GET | N/A | HTML form | N/A |
| [admin/pages/announcements.php](../admin/pages/announcements.php) | Announcements management | admin/official | GET | search/filter/status | HTML page | announcements, users |
| [admin/pages/barangay-clearance-template.php](../admin/pages/barangay-clearance-template.php) | Clearance template render | admin/official | GET | request/resident ids | HTML/template | document_requests, residents |
| [admin/pages/business-application-form.php](../admin/pages/business-application-form.php) | Business app form template | admin/official | GET | request/resident ids | HTML/template | business_transactions, residents |
| [admin/pages/business-clearance-template.php](../admin/pages/business-clearance-template.php) | Business clearance template render | admin/official | GET | request/resident ids | HTML/template | business_transactions, residents |
| [admin/pages/business-clearance.php](../admin/pages/business-clearance.php) | Business processing board | admin/official | GET | status/search filters | HTML page | business_transactions, business_permits, residents |
| [admin/pages/certificate-of-indigency-template.php](../admin/pages/certificate-of-indigency-template.php) | Indigency template render | admin/official | GET | request/resident ids | HTML/template | document_requests, residents |
| [admin/pages/certificate-of-residency-template.php](../admin/pages/certificate-of-residency-template.php) | Residency template render | admin/official | GET | request/resident ids | HTML/template | document_requests, residents |
| [admin/pages/chat.php](../admin/pages/chat.php) | Admin chat UI | admin/official | GET + AJAX | peer/action/message | HTML + JSON via API | chat_messages, users |
| [admin/pages/edit-resident.php](../admin/pages/edit-resident.php) | Edit resident form | admin/official | GET | resident id | HTML form | residents |
| [admin/pages/edit-user.php](../admin/pages/edit-user.php) | Edit user form | admin | GET | user id | HTML form | users |
| [admin/pages/events.php](../admin/pages/events.php) | Events management | admin/official | GET | search/date filters | HTML page | events/announcements |
| [admin/pages/generate-business-permit.php](../admin/pages/generate-business-permit.php) | Permit generation page | admin/official | GET | application id | HTML/PDF workflow | business_transactions, residents |
| [admin/pages/incident-reports.php](../admin/pages/incident-reports.php) | Incident operations board | admin/official | GET | status/search filters | HTML page | incidents, users, residents |
| [admin/pages/logs.php](../admin/pages/logs.php) | Audit logs viewer | admin | GET | search/date/action filters | HTML page | activity_logs |
| [admin/pages/maps.php](../admin/pages/maps.php) | Incident/location maps view | admin/official | GET | map/date filters | HTML map view | incidents |
| [admin/pages/monitoring-of-request.php](../admin/pages/monitoring-of-request.php) | Unified request monitoring | admin/official | GET | search/status/type/payment filters | HTML page | document_requests, business_transactions, residents, users |
| [admin/pages/new-barangay-business-clearance.php](../admin/pages/new-barangay-business-clearance.php) | New business clearance form | admin/official | GET | resident id (optional) | HTML form | residents, businesses |
| [admin/pages/new-barangay-clearance.php](../admin/pages/new-barangay-clearance.php) | New barangay clearance form | admin/official | GET | resident id (optional) | HTML form | residents |
| [admin/pages/new-certificate-of-indigency.php](../admin/pages/new-certificate-of-indigency.php) | New indigency form | admin/official | GET | resident id (optional) | HTML form | residents |
| [admin/pages/new-certificate-of-residency.php](../admin/pages/new-certificate-of-residency.php) | New residency form | admin/official | GET | resident id (optional) | HTML form | residents |
| [admin/pages/resident-id.php](../admin/pages/resident-id.php) | Resident ID generation view | admin/official | GET | resident id | HTML/print view | residents |
| [admin/pages/residents.php](../admin/pages/residents.php) | Residents management list | admin/official | GET | search/filter | HTML page | residents, users |
| [admin/pages/update-incident.php](../admin/pages/update-incident.php) | Incident update form flow | admin/official | GET/POST | incident id, status, remarks | redirect/JSON | incidents, notifications |
| [admin/pages/user-management.php](../admin/pages/user-management.php) | User management list | admin | GET | search/filter/role | HTML page | users, active_user_sessions |

### F. Admin partial handlers (operations and AJAX)

| File | Process | Actor | Trigger | Inputs | Outputs | Tables touched |
|---|---|---|---|---|---|---|
| [admin/partials/account-handler.php](../admin/partials/account-handler.php) | Admin account update | admin | POST | account fields/password | redirect/JSON | users, activity_logs |
| [admin/partials/active-sessions-data.php](../admin/partials/active-sessions-data.php) | Active sessions data API | admin | AJAX GET | filters (optional) | JSON | active_user_sessions, users |
| [admin/partials/add-resident-handler.php](../admin/partials/add-resident-handler.php) | Create resident (and optional user) | admin/official | POST | resident profile fields | redirect/JSON | residents, users, activity_logs |
| [admin/partials/add-user-handler.php](../admin/partials/add-user-handler.php) | Create system user | admin | POST | username/email/password/role | redirect/JSON | users, activity_logs |
| [admin/partials/admin_auth.php](../admin/partials/admin_auth.php) | Admin auth gate middleware | admin/official | include/runtime | session role/state | redirect/deny | active_user_sessions, users |
| [admin/partials/ajax-update-request-status.php](../admin/partials/ajax-update-request-status.php) | AJAX request status update | admin/official | AJAX POST | id, status, remarks, CSRF | JSON | document_requests, notifications, activity_logs |
| [admin/partials/announcement-handler.php](../admin/partials/announcement-handler.php) | Announcement CRUD | admin/official | POST | title/content/media/status/priority | redirect/JSON | announcements, notifications, activity_logs |
| [admin/partials/archive-logs.php](../admin/partials/archive-logs.php) | Archive old logs | admin | POST | cutoff/flags | redirect/JSON | activity_logs, activity_logs_archive, activity_log_archive_batches |
| [admin/partials/bulk-action-requests.php](../admin/partials/bulk-action-requests.php) | Bulk actions on requests | admin/official | AJAX POST | ids, action, status | JSON | document_requests, business_transactions, notifications, activity_logs |
| [admin/partials/business-application-handler.php](../admin/partials/business-application-handler.php) | Process business applications | admin/official | POST | application id, action, remarks | redirect/JSON | business_transactions, businesses, notifications, activity_logs |
| [admin/partials/cancel-request.php](../admin/partials/cancel-request.php) | Admin cancel request | admin/official | POST/AJAX | request id, CSRF | JSON | document_requests, notifications, activity_logs |
| [admin/partials/dashboard-stats.php](../admin/partials/dashboard-stats.php) | Dashboard stats aggregator | admin/official | AJAX GET/include | date/filter params | JSON/array | residents, users, incidents, document_requests, business_transactions |
| [admin/partials/delete-business-transaction.php](../admin/partials/delete-business-transaction.php) | Delete business transaction | admin | POST | transaction id, CSRF | redirect/JSON | business_transactions, activity_logs |
| [admin/partials/delete-document-request.php](../admin/partials/delete-document-request.php) | Delete document request | admin | POST | request id, CSRF | redirect/JSON | document_requests, activity_logs |
| [admin/partials/delete-log-handler.php](../admin/partials/delete-log-handler.php) | Delete log row(s) | admin | POST | log id/filter | redirect/JSON | activity_logs |
| [admin/partials/delete-resident-handler.php](../admin/partials/delete-resident-handler.php) | Delete resident | admin | POST | resident id, CSRF | redirect/JSON | residents, users, activity_logs |
| [admin/partials/delete-user-handler.php](../admin/partials/delete-user-handler.php) | Delete user | admin | POST | user id, CSRF | redirect/JSON | users, active_user_sessions, activity_logs |
| [admin/partials/event-handler.php](../admin/partials/event-handler.php) | Event CRUD | admin/official | POST | event fields | redirect/JSON | events/announcements, activity_logs |
| [admin/partials/export-incidents-csv.php](../admin/partials/export-incidents-csv.php) | Export incidents CSV | admin/official | GET | filters/date range | CSV download | incidents, residents, users |
| [admin/partials/export-requests.php](../admin/partials/export-requests.php) | Export requests CSV | admin/official | GET | type/status/date filters | CSV download | document_requests, business_transactions, residents |
| [admin/partials/export-residents-csv.php](../admin/partials/export-residents-csv.php) | Export residents CSV | admin/official | GET | filters | CSV download | residents, users |
| [admin/partials/fetch-incident-logs.php](../admin/partials/fetch-incident-logs.php) | Incident activity feed | admin/official | AJAX GET | incident id/paging | JSON | incidents, activity_logs |
| [admin/partials/fetch-live-incidents.php](../admin/partials/fetch-live-incidents.php) | Live incidents polling | admin/official | AJAX GET | since cursor/filter | JSON | incidents, users, residents |
| [admin/partials/generate-business-permit-pdf.php](../admin/partials/generate-business-permit-pdf.php) | Permit PDF generation | admin/official | GET/POST | application/resident ids | PDF stream/file | business_transactions, business_permits, residents |
| [admin/partials/get-incident-details.php](../admin/partials/get-incident-details.php) | Incident details AJAX | admin/official | AJAX GET | incident id | JSON | incidents, residents, users |
| [admin/partials/get-permit-details.php](../admin/partials/get-permit-details.php) | Permit details AJAX | admin/official | AJAX GET | permit/transaction id | JSON | business_permits, business_transactions, residents |
| [admin/partials/get-resident-details.php](../admin/partials/get-resident-details.php) | Resident details AJAX | admin/official | AJAX GET | resident id | JSON | residents, users |
| [admin/partials/make-cash-payment.php](../admin/partials/make-cash-payment.php) | Cash payment posting | admin/official | POST/AJAX | request id/type, cash received, OR fields | JSON | document_requests, business_transactions, notifications, activity_logs |
| [admin/partials/migrate_add_business_code.php](../admin/partials/migrate_add_business_code.php) | One-off schema/data migration | admin/system | CLI/manual | N/A | migration output | businesses |
| [admin/partials/new-barangay-clearance-handler.php](../admin/partials/new-barangay-clearance-handler.php) | Create clearance request (admin-assisted) | admin/official | POST | resident + request fields | redirect/JSON | document_requests, residents, activity_logs |
| [admin/partials/new-business-permit-handler.php](../admin/partials/new-business-permit-handler.php) | Create business permit request (admin-assisted) | admin/official | POST | full application fields | redirect/JSON | business_permits, business_transactions, residents, activity_logs |
| [admin/partials/new-certificate-of-indigency-handler.php](../admin/partials/new-certificate-of-indigency-handler.php) | Create indigency request (admin-assisted) | admin/official | POST | resident + request fields | redirect/JSON | document_requests, residents, activity_logs |
| [admin/partials/new-certificate-of-residency-handler.php](../admin/partials/new-certificate-of-residency-handler.php) | Create residency request (admin-assisted) | admin/official | POST | resident + request fields | redirect/JSON | document_requests, residents, activity_logs |
| [admin/partials/post-handler.php](../admin/partials/post-handler.php) | Generic post/announcement action handler | admin/official | POST | action payload | redirect/JSON | announcements, activity_logs |
| [admin/partials/revoke-session-handler.php](../admin/partials/revoke-session-handler.php) | Session revocation | admin | POST/AJAX | user id/session id | JSON | active_user_sessions, activity_logs |
| [admin/partials/search-incidents.php](../admin/partials/search-incidents.php) | Incident search AJAX | admin/official | AJAX GET | q/filter | JSON | incidents, residents |
| [admin/partials/search-residents.php](../admin/partials/search-residents.php) | Resident search AJAX | admin/official | AJAX GET | q/filter | JSON | residents, users |
| [admin/partials/search-users.php](../admin/partials/search-users.php) | User search AJAX | admin | AJAX GET | q/filter | JSON | users |
| [admin/partials/send-business-reminder.php](../admin/partials/send-business-reminder.php) | Manual business reminder blast | admin/official | POST | target filters/template | redirect/JSON/email result | businesses, users, notifications |
| [admin/partials/update-business-status.php](../admin/partials/update-business-status.php) | Business status transitions | admin/official | POST/AJAX | business id, status, remarks | JSON | businesses, business_transactions, notifications, activity_logs |
| [admin/partials/update-document-request-status.php](../admin/partials/update-document-request-status.php) | Document status transitions | admin/official | POST/AJAX | request id, status, remarks | JSON | document_requests, notifications, activity_logs |
| [admin/partials/update-incident-remarks.php](../admin/partials/update-incident-remarks.php) | Incident remarks update | admin/official | POST/AJAX | incident id, remarks | JSON | incidents, activity_logs |
| [admin/partials/update-incident-status-ajax.php](../admin/partials/update-incident-status-ajax.php) | Incident status transitions | admin/official | AJAX POST | incident id, status | JSON | incidents, notifications, activity_logs |
| [admin/partials/update-payment-info.php](../admin/partials/update-payment-info.php) | OR/payment metadata update | admin/official | POST/AJAX | request id/type, OR no, payment date, status | JSON | document_requests, business_transactions, notifications, activity_logs |
| [admin/partials/walk-in-request-handler.php](../admin/partials/walk-in-request-handler.php) | Walk-in request encoding | admin/official | POST | resident + request fields | redirect/JSON | document_requests, business_transactions, residents, activity_logs |
| [admin/partials/sidebar.php](../admin/partials/sidebar.php) | Shared admin sidebar | admin/official | include/render | route state | HTML fragment | N/A |
| [admin/partials/user-dropdown.php](../admin/partials/user-dropdown.php) | Shared user dropdown | admin/official | include/render | session/profile vars | HTML fragment | users (display only) |

### G. Core business-service includes

| File | Process | Actor | Trigger | Inputs | Outputs | Tables touched |
|---|---|---|---|---|---|---|
| [includes/auth.php](../includes/auth.php) | Auth/session policy service | system | include/runtime | session/login data | auth allow/deny, session sync | users, active_user_sessions |
| [includes/register-handler.php](../includes/register-handler.php) | Registration business logic | public | POST | registration payload | user creation / redirect | users, residents |
| [includes/notification_system.php](../includes/notification_system.php) | Notification orchestration (in-app + email) | system | function calls | user id, title, message, link, channel | notification rows + email provider call | notifications, users |
| [includes/business_announcement_functions.php](../includes/business_announcement_functions.php) | Auto business announcement generation | system | cron/function | business/permit metadata | announcements + optional notification | announcements, businesses, notifications |
| [includes/permission_checker.php](../includes/permission_checker.php) | Permission middleware | system | include/runtime | role + permission key | allow/deny booleans | N/A |
| [includes/cache_manager.php](../includes/cache_manager.php) | Cache layer | system | include/runtime | cache keys/ttl | cache read/write | N/A |
| [includes/csrf.php](../includes/csrf.php) | CSRF token service | system | include/runtime | token/session | token verify result | N/A |
| [includes/input_validator.php](../includes/input_validator.php) | Input validation service | system | include/runtime | input values/files | validated/normalized values | N/A |
| [includes/rate_limiter.php](../includes/rate_limiter.php) | Abuse throttling service | system | include/runtime | action key + client id | allow/deny with counters | N/A (or cache/store) |
| [includes/security_headers.php](../includes/security_headers.php) | HTTP security headers service | system | include/runtime | context/page type | response headers | N/A |
| [includes/password_security.php](../includes/password_security.php) | Password policy service | system | include/runtime | password text | policy pass/fail | N/A |

### H. Coverage notes for this inventory

- This section intentionally includes operational UI/support partials so every API/page/script path in the project has a process mapping.
- Some template/static pages primarily render HTML and defer writes to handler files; in those cases, tables touched are listed as read-only or N/A.
- Test and temporary scripts are listed for completeness because they are executable project scripts, even if non-production.

<!-- SNIPPETS_START -->

## 9. Referenced File Snippets

The snippets below are extracted from the referenced files in this report. They are short context samples, not full files.

### [admin/](../admin/)

```php
require_once 'partials/admin_auth.php';
require_once '../includes/cache_manager.php';

init_cache_manager(['cache_dir' => '../cache/']);
$cached_data = cache_get('admin_dashboard_stats', 'file');

if (!$cached_data) {
    $stmt = $pdo->query("SELECT COUNT(*) as total_population FROM residents");
  $population_stats = $stmt->fetch();

  $stmt = $pdo->query("SELECT id, transaction_type, owner_name, application_date, status
            FROM business_transactions
            WHERE status IS NOT NULL AND status != '' AND status != 'DELETED'
            ORDER BY application_date DESC LIMIT 4");
  $latest_transactions = $stmt->fetchAll();

  $stats_to_cache = compact('population_stats', 'latest_transactions');
  cache_set('admin_dashboard_stats', $stats_to_cache, 900, 'file');
}
```

### [admin/pages/](../admin/pages/)

```php
require_once '../../includes/permission_checker.php';
require_login();

if (!require_permission('manage_incidents')) {
    header('Location: ../../index.php');
    exit;
}

$stmt = $pdo->query("SELECT COUNT(*) FROM incidents");
$total_incidents = $stmt->fetchColumn();

$stmt = $pdo->query("SELECT COUNT(*) FROM incidents WHERE status IN ('Pending', 'In Progress')");
$active_cases = $stmt->fetchColumn();

$stmt = $pdo->query("SELECT COUNT(*) FROM incidents WHERE reported_at >= NOW() - INTERVAL 1 DAY");
$trending_today = $stmt->fetchColumn();

$stmt = $pdo->query("SELECT type, COUNT(*) as count
           FROM incidents
           WHERE MONTH(reported_at) = MONTH(CURRENT_DATE())
           GROUP BY type
           ORDER BY count DESC
           LIMIT 1");
$most_frequent = $stmt->fetch();

```

### [api/](../api/)

```php
if (!is_logged_in()) {
    http_response_code(401);
    echo json_encode(['error' => 'Authentication required.']);
    exit;
}

$limit = RateLimiter::checkRateLimit('notifications_api', RateLimiter::getClientIP());
if (!$limit['allowed'] || !csrf_validate()) {
  http_response_code(403);
  echo json_encode(['error' => 'Unauthorized or rate-limited']);
  exit;
}

$action = $_GET['action'] ?? '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'mark_all_read') {
  $user_id = (int) ($_SESSION['user_id'] ?? 0);
  $stmt = $pdo->prepare("UPDATE notifications SET is_read = 1 WHERE user_id = ? AND is_read = 0");
  $stmt->execute([$user_id]);
  echo json_encode(['success' => true, 'updated_count' => (int) $stmt->rowCount()]);
  exit;
}

$chat_rate_limit = RateLimiter::checkRateLimit('chat_api', RateLimiter::getClientIP());
if (!$chat_rate_limit['allowed']) {
  http_response_code(429);
  echo json_encode(['error' => 'Too Many Requests']);
  exit;
}

$stmt = $pdo->prepare("INSERT INTO chat_messages (sender_id, receiver_id, message) VALUES (?, ?, ?)");
$stmt->execute([$user_id, $receiver_id, $message_text]);
echo json_encode(['success' => true]);

```

### [includes/](../includes/)

```php
function require_permission($permission, $user_role = null) {
  return can_access($user_role ?? ($_SESSION['role'] ?? ''), $permission);
}

$notification_created = create_notification(
  $pdo,
  $recipient_user_id,
  $title,
  $message,
  'business_reminder',
  $link
);

$email_sent = self::send_email_with_fallback($recipient_email, $recipient_name, $title, $email_body);

if (!csrf_validate() || !RateLimiter::checkRateLimit('api_calls', RateLimiter::getClientIP())['allowed']) {
  http_response_code(403);
  echo json_encode(['error' => 'Invalid security token or rate limit exceeded.']);
  exit;
}

class PasswordSecurity {
  const MIN_LENGTH = 8;
  const MAX_LENGTH = 128;

  public static function validatePassword($password) {
    $len = strlen($password);
    return $len >= self::MIN_LENGTH && $len <= self::MAX_LENGTH;
  }
}
```

### [migrations/](../migrations/)

```sql
ALTER TABLE document_requests
MODIFY status ENUM('Pending', 'Processing', 'Ready for Pickup', 'Completed', 'Rejected', 'Cancelled')
NOT NULL DEFAULT 'Pending';

ALTER TABLE `announcements`
  ADD COLUMN IF NOT EXISTS `is_auto_generated` TINYINT(1) NOT NULL DEFAULT 0 AFTER `user_id`;

ALTER TABLE `announcements`
  ADD COLUMN IF NOT EXISTS `status` VARCHAR(20) NOT NULL DEFAULT 'active' AFTER `content`,
  ADD COLUMN IF NOT EXISTS `priority` VARCHAR(20) NOT NULL DEFAULT 'normal' AFTER `status`,
  ADD COLUMN IF NOT EXISTS `target_audience` VARCHAR(50) NOT NULL DEFAULT 'all' AFTER `priority`;

UPDATE document_requests
SET status = 'Pending'
WHERE requested_by_user_id IS NULL
  AND status = 'Processing'
  AND (remarks IS NULL OR remarks = '');
```

### [resident/](../resident/)

```php
function require_role($role) {
    if (!is_logged_in() || $_SESSION['role'] !== $role) {
        redirect_to('../index.php');
    }
}

require_role('resident');
$stmtReq = $pdo->prepare("SELECT id, document_type, status FROM document_requests WHERE requested_by_user_id = ? ORDER BY date_requested DESC LIMIT 3");
$stmtReq->execute([$_SESSION['user_id']]);
$recentRequests = $stmtReq->fetchAll(PDO::FETCH_ASSOC);

$stmtInc = $pdo->prepare("SELECT id, type, description, location, reported_at, status
              FROM incidents
              WHERE resident_user_id = ?
              ORDER BY reported_at DESC LIMIT 3");
$stmtInc->execute([$_SESSION['user_id']]);
$recentIncidents = $stmtInc->fetchAll(PDO::FETCH_ASSOC);
```

### [resident/partials/](../resident/partials/)

```php
if (!is_logged_in() || $_SESSION['role'] !== 'resident') {
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

$sql = "INSERT INTO document_requests (resident_id, document_type, purpose, details, requested_by_user_id, status)
        VALUES (?, 'Barangay Clearance', ?, ?, ?, 'Pending')";
$stmt = $pdo->prepare($sql);
$stmt->execute([$resident_id, $purpose, json_encode($details), $_SESSION['user_id']]);

$details = [
  'application_type' => sanitize_input($_POST['application_type'] ?? 'New'),
  'precinct_no' => sanitize_input($_POST['precinct_no'] ?? ''),
  'resident_since' => sanitize_input($_POST['resident_since'] ?? ''),
  'references' => [
    ['name' => sanitize_input($_POST['reference_1'] ?? '')],
    ['name' => sanitize_input($_POST['reference_2'] ?? '')],
  ],
];

log_activity('Document Request', 'New Barangay Clearance requested natively by resident.', $_SESSION['user_id']);
echo json_encode(['success' => true]);
```

### [index.php](../index.php)

```php
<?php
/**
 * Login Page
 * Main entry point for the Barangay Reports Admin System
 */

// Include required files
require_once 'includes/functions.php';
require_once 'config/database.php';
require_once 'config/init.php';
require_once 'includes/auth.php';

apply_page_security_headers('login');

if (is_logged_in()) {
  redirect_to('admin/index.php');
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
  if (!CSRFProtection::validateFromPost()) {
    $login_err = "Invalid security token. Please refresh the page and try again.";
  } else {
    $rate_limit_status = check_login_rate_limit();
    if (!$rate_limit_status['allowed']) {
      $login_err = $rate_limit_status['message'];
    }
  }
}
```

### [register.php](../register.php)

```php
<?php
/**
 * Registration Page
 */

require_once 'config/init.php';

// Apply security headers for public page rollout
apply_page_security_headers('public');

if (isset($_SESSION["loggedin"]) && $_SESSION["loggedin"] === true) {
  header("location: admin/index.php");
  exit;
}

// Registration submits to centralized handler with CSRF protection.
echo '<form action="includes/register-handler.php" method="POST" id="registrationForm">';
echo csrf_field();
```

### [forgot-password.php](../forgot-password.php)

```php
<?php
/**
 * Forgot Password Page
 * Allows users to request a password reset
 */

// Include required files
require_once 'includes/functions.php';
require_once 'config/database.php';
require_once 'config/init.php';
require_once 'config/email_config.php';
require_once 'includes/auth.php';

apply_page_security_headers('login');

if ($_SERVER["REQUEST_METHOD"] == "POST") {
  if (!CSRFProtection::validateFromPost()) {
    $email_err = "Invalid security token. Please refresh the page and try again.";
  } else {
    $stmt = $pdo->prepare("SELECT id, username, fullname FROM users WHERE email = ? AND status = 'active'");
    $stmt->execute([$email]);
    $user = $stmt->fetch();
  }
}
```

### [reset-password.php](../reset-password.php)

```php
<?php
/**
 * Reset Password Page
 * Allows users to set a new password using their reset token
 */

// Start session first
session_start();

require_once 'includes/functions.php';
require_once 'config/database.php';
require_once 'config/init.php';
require_once 'includes/auth.php';
require_once 'includes/csrf.php';

$token = isset($_GET['token']) ? trim($_GET['token']) : '';
$token_hash = hash('sha256', $token);
$stmt = $pdo->prepare("SELECT id, username, email, reset_token
                       FROM users
                       WHERE (reset_token = ? OR reset_token = ?)
                         AND reset_token_expires > NOW()
                         AND status = 'active'");
$stmt->execute([$token_hash, $token]);
```

### [config/permissions.php](../config/permissions.php)

```php
<?php
/**
 * Role-Based Permissions Configuration
 * Defines specific permissions for each role based on REAL barangay governance structure
 */

// Role hierarchy (higher number = higher authority) - Based on actual Philippine barangay system
function get_role_hierarchy($role) {
  $hierarchy = [
    'resident' => 1,
    'barangay-tanod' => 2,
    'barangay-kagawad' => 3,
    'barangay-officials' => 4,
    'admin' => 5
  ];
  return $hierarchy[$role] ?? 0;
}

function check_role_override($user_role, $target_role) {
  return get_role_hierarchy($user_role) > get_role_hierarchy($target_role);
}

$role_permissions = [
  'admin' => ['access' => ['manage_incidents' => true, 'manage_documents' => true, 'override_decisions' => true]],
  'resident' => ['access' => ['report_incidents' => true, 'submit_applications' => true, 'view_announcements' => true]]
];
```

### [includes/permission_checker.php](../includes/permission_checker.php)

```php
<?php
/**
 * Permission Checker Helper Functions
 * Provides easy-to-use functions for checking user permissions
 */

require_once __DIR__ . '/../config/permissions.php';

function require_permission($permission, $user_role = null) {
  if ($user_role === null) {
    if (!isset($_SESSION['role'])) {
      return false;
    }
    $user_role = $_SESSION['role'];
  }

  return can_access($user_role, $permission);
}

function can_override_role($target_role) {
  if (!isset($_SESSION['role'])) {
    return false;
  }

  return check_role_override($_SESSION['role'], $target_role);
}

```

### [includes/auth.php](../includes/auth.php)

```php
<?php
/**
 * Authentication System
 * Handles user authentication, sessions, and access control
 */

// Include necessary files
require_once __DIR__ . '/../config/init.php'; // Includes DB and functions
require_once __DIR__ . '/functions.php';

if (session_status() === PHP_SESSION_NONE) {
  if (function_exists('configure_session_cookie_security')) {
    configure_session_cookie_security();
  }
  session_start();
}

function is_official_role_only($role) {
  return in_array($role, ['barangay-officials', 'barangay-kagawad', 'barangay-tanod'], true);
}

function is_session_policy_tracked_role($role) {
  return $role === 'admin' || is_official_role_only($role);
}

function clear_expired_active_sessions($pdo) {
  $stmt = $pdo->prepare("UPDATE active_user_sessions
              SET is_active = 0, ended_at = NOW(), ended_reason = 'expired'
              WHERE is_active = 1 AND expires_at IS NOT NULL AND expires_at < NOW()");
  $stmt->execute();
  return (int) $stmt->rowCount();
}
```

### [api/incidents.php](../api/incidents.php)

```php
<?php
require_once '../config/init.php';
require_once '../includes/auth.php';
require_once '../includes/csrf.php';

// Apply security headers for API endpoints
apply_page_security_headers('api');

```

### [api/notifications.php](../api/notifications.php)

```php
<?php
header('Content-Type: application/json');
require_once '../config/database.php';
require_once '../includes/auth.php';
require_once '../includes/csrf.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
```

### [api/chat.php](../api/chat.php)

```php
<?php
header('Content-Type: application/json');
// Use lightweight database-only bootstrap - not init.php, which runs all schema migrations on every poll
require_once '../config/database.php';
require_once '../includes/auth.php';
require_once '../includes/csrf.php';

if (session_status() === PHP_SESSION_NONE) {
```

### [api/announcements.php](../api/announcements.php)

```php
<?php
header('Content-Type: application/json');
require_once '../config/database.php';
require_once '../includes/auth.php';

// Allow access only to logged-in residents
if (!is_logged_in() || $_SESSION['role'] !== 'resident') {
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
```

### [api/post-reactions.php](../api/post-reactions.php)

```php
<?php
require_once '../config/database.php';
require_once '../includes/auth.php'; // Ensure user is logged in
require_once '../includes/csrf.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
```

### [config/database.php](../config/database.php)

```php
<?php
/**
 * Database Connection File
 * Handles the connection to the MySQL database using PDO.
 */

// Load environment variables
require_once __DIR__ . '/env_loader.php';
```

### [config/init.php](../config/init.php)

```php
<?php
/**
 * Database Initialization File
 * Establishes a PDO connection and creates necessary tables if they don't exist.
 */

function configure_session_cookie_security() {
    if (session_status() !== PHP_SESSION_NONE) {
```

### [docs/database_schema.md](database_schema.md)

```markdown
# Barangay Management System - Database Schema Documentation

## Overview
The barangay management system uses a MySQL database with 10 main tables to manage residents, businesses, documents, incidents, communications, and administrative functions.

## Database Tables

### 1. users
```

### [includes/register-handler.php](../includes/register-handler.php)

```php
<?php
/**
 * Registration Form Handler
 */

require_once '../config/init.php'; // Use init to include db and functions
require_once 'functions.php';

```

### [resident/dashboard.php](../resident/dashboard.php)

```php
<?php
session_start();
require_once '../includes/auth.php';
require_once '../includes/functions.php';

// This function will be defined in auth.php or a new resident-auth.php
// For now, let's assume a function that requires a specific role
function require_role($role) {
```

### [resident/account.php](../resident/account.php)

```php
<?php
require_once '../config/database.php';
$page_title = "My Account";
require_once 'partials/header.php';

// user_id is from header session
$user_id = $_SESSION['user_id'];

```

### [resident/new-barangay-clearance.php](../resident/new-barangay-clearance.php)

```php
<?php
session_start();
require_once '../includes/auth.php';
require_once '../includes/functions.php';
require_once '../config/database.php';

if (!function_exists('require_role')) {
    function require_role($role) {
```

### [resident/new-certificate-of-residency.php](../resident/new-certificate-of-residency.php)

```php
<?php
session_start();
require_once '../includes/auth.php';
require_once '../includes/functions.php';
require_once '../config/database.php';

if (!function_exists('require_role')) {
    function require_role($role) {
```

### [resident/new-certificate-of-indigency.php](../resident/new-certificate-of-indigency.php)

```php
<?php
session_start();
require_once '../includes/auth.php';
require_once '../includes/functions.php';
require_once '../config/database.php';

if (!function_exists('require_role')) {
    function require_role($role) {
```

### [resident/my-requests.php](../resident/my-requests.php)

```php
<?php
session_start();
require_once '../includes/auth.php';
require_once '../includes/functions.php';
require_role('resident');
$page_title = 'My Document Requests';
require_once 'partials/header.php';

```

### [resident/request-details.php](../resident/request-details.php)

```php
<?php
session_start();
require_once '../includes/auth.php';
require_once '../includes/functions.php';

if (!function_exists('require_role')) {
    function require_role($role) {
        if (!is_logged_in() || $_SESSION['role'] !== $role) {
```

### [resident/report-incident.php](../resident/report-incident.php)

```php
<?php
session_start();
require_once '../includes/auth.php';
require_once '../includes/functions.php';
require_once '../config/env_loader.php';

// This is already in header.php, but for safety.
if (!function_exists('require_role')) {
```

### [resident/new-barangay-business-clearance.php](../resident/new-barangay-business-clearance.php)

```php
<?php
session_start();
require_once '../includes/auth.php';
require_once '../includes/functions.php';
require_once '../config/database.php';

if (!function_exists('require_role')) {
    function require_role($role) {
```

### [resident/business-details.php](../resident/business-details.php)

```php
<?php
session_start();
require_once '../includes/auth.php';
require_once '../includes/functions.php';

if (!function_exists('require_role')) {
    function require_role($role) {
        if (!is_logged_in() || $_SESSION['role'] !== $role) {
```

### [admin/index.php](../admin/index.php)

```php
<?php
/**
 * Admin Dashboard
 * Main landing page after successful login
 */

// Include admin authentication and session management
require_once 'partials/admin_auth.php';
```

### [includes/cache_manager.php](../includes/cache_manager.php)

```php
<?php
/**
 * Cache Manager
 * 
 * Provides comprehensive caching capabilities including:
 * - Multiple cache backends (APCu, file, session)
 * - Session storage optimization
 * - Dashboard statistics caching
```

### [admin/pages/residents.php](../admin/pages/residents.php)

```php
<?php
/**
 * Residents Management Page
 */

// Include admin authentication and session management
require_once '../partials/admin_auth.php';

```

### [admin/pages/user-management.php](../admin/pages/user-management.php)

```php
<?php
/**
 * User Management - Modernized
 */
require_once '../partials/admin_auth.php';
require_once '../../includes/functions.php';

// Note: Advanced User Management is typically restricted to the 'admin' role
```

### [admin/pages/monitoring-of-request.php](../admin/pages/monitoring-of-request.php)

```php
<?php
/**
 * Monitoring of Requests Page
 */

require_once '../../config/init.php';
require_once '../../includes/functions.php';
require_once '../../includes/auth.php';
```

### [admin/pages/business-clearance.php](../admin/pages/business-clearance.php)

```php
<?php
/**
 * Barangay Business Clearance Certificate
 */

require_once '../../includes/auth.php';
require_once '../../includes/functions.php';
require_once '../../config/database.php';
```

### [admin/pages/incident-reports.php](../admin/pages/incident-reports.php)

```php
<?php
/**
 * Incident Reports Management - Modernized
 */
require_once '../../config/init.php';
require_once '../../includes/auth.php';
require_once '../../includes/functions.php';
require_once '../../includes/permission_checker.php';
```

### [admin/pages/announcements.php](../admin/pages/announcements.php)

```php
<?php
/**
 * Announcements Management - Modernized
 */
require_once '../partials/admin_auth.php';
require_once '../../includes/functions.php';
require_once '../../includes/business_announcement_functions.php';
require_once '../../includes/csrf.php';
```

### [admin/pages/events.php](../admin/pages/events.php)

```php
<?php
/**
 * Events Management - Modernized
 */
require_once '../partials/admin_auth.php';
require_once '../../includes/functions.php';

$page_title = "Manage Events";
```

### [admin/pages/logs.php](../admin/pages/logs.php)

```php
<?php
require_once '../../config/init.php';
require_once '../../includes/auth.php';
require_once '../../includes/functions.php';
require_once '../../includes/csrf.php';

if (!is_logged_in() || $_SESSION['role'] !== 'admin') {
    redirect_to('../../index.php');
```

### [includes/notification_system.php](../includes/notification_system.php)

```php
<?php
/**
 * Notification System Service
 * Centralized in-app + email notification delivery helpers.
 */

require_once __DIR__ . '/functions.php';

```

### [resident/partials/mark-notifications-read.php](../resident/partials/mark-notifications-read.php)

```php
<?php
/**
 * @deprecated Use /api/notifications.php?action=mark_all_read instead.
 *
 * Compatibility shim kept intentionally to support staggered/cloud rollouts
 * where older clients may still call this endpoint briefly after deployment.
 */

```

### [resident/chat.php](../resident/chat.php)

```php
<?php
session_start();
require_once '../includes/auth.php';
require_once '../includes/functions.php';

function require_role($role) {
    if (!is_logged_in() || $_SESSION['role'] !== $role) {
        redirect_to('../index.php');
```

### [cron_check_expiring_permits.php](../cron_check_expiring_permits.php)

```php
<?php
/**
 * Cron Job Script for Business Permit Expiry Checks
 * 
 * This script should be run daily via cron job to automatically:
 * - Check for expiring permits
 * - Generate reminder announcements
 * - Update permit statuses
```

### [includes/business_announcement_functions.php](../includes/business_announcement_functions.php)

```php
<?php
/**
 * Business Announcement Functions
 * Handles automatic generation of business-related announcements
 */

require_once __DIR__ . '/../config/init.php';
require_once __DIR__ . '/functions.php';
```

### [cron_cleanup_active_sessions.php](../cron_cleanup_active_sessions.php)

```php
<?php
/**
 * Cron: Cleanup expired active sessions
 *
 * Usage:
 *   php cron_cleanup_active_sessions.php
 */

```

### [test_env.php](../test_env.php)

```php
<?php
/**
 * Environment Variables Test Script
 * Run this to verify your .env file is working correctly
 * 
 * Usage: Development only. Run via CLI or as authenticated admin/official in non-production.
 */

```

### [check_db.php](../check_db.php)

```php
<?php
require_once __DIR__ . '/config/env_loader.php';

$app_env = strtolower((string) env('APP_ENV', 'production'));

if ($app_env === 'production') {
    http_response_code(404);
    exit('Not Found');
```

### [check_db_precision.php](../check_db_precision.php)

```php
<?php
require_once __DIR__ . '/config/env_loader.php';

$app_env = strtolower((string) env('APP_ENV', 'production'));

if ($app_env === 'production') {
    http_response_code(404);
    exit('Not Found');
```

### [includes/password_security.php](../includes/password_security.php)

```php
<?php
/**
 * Password Security Helper
 * 
 * Provides comprehensive password validation, strength checking,
 * and security functions for the system.
 */

```

### [includes/csrf.php](../includes/csrf.php)

```php
<?php
/**
 * CSRF (Cross-Site Request Forgery) Protection
 * 
 * This class provides CSRF token generation and validation
 * to protect against CSRF attacks on forms.
 */

```

### [includes/input_validator.php](../includes/input_validator.php)

```php
<?php
/**
 * Input Validation & Sanitization Helper
 * 
 * Provides comprehensive input validation, sanitization, and security
 * functions for forms, file uploads, and data processing.
 */

```

### [includes/security_headers.php](../includes/security_headers.php)

```php
<?php
/**
 * Security Headers Helper
 * 
 * Provides comprehensive security headers implementation
 * to protect against various web attacks and vulnerabilities.
 */

```

### [includes/rate_limiter.php](../includes/rate_limiter.php)

```php
<?php
/**
 * Rate Limiting Helper
 * 
 * Provides comprehensive rate limiting for login attempts,
 * API calls, and other sensitive operations to prevent abuse.
 */

```

### [resident/notifications.php](../resident/notifications.php)

```php
<?php
session_start();
require_once '../includes/auth.php';
require_once '../includes/functions.php';
require_once '../includes/csrf.php';
require_role('resident');
$page_title = 'Alerts & Announcements';
require_once 'partials/header.php';
```

<!-- SNIPPETS_END -->

