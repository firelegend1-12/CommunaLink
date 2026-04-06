**Code Snippets for the Project **

Core architecture:
- UI pages: index.php, register.php, resident/*, admin/*
- API endpoints: api/* (JSON)
- Shared services: includes/*
- Config/bootstrap: config/*
- Database SQL schema: CommunaLink_Database_Complete.sql

FEATURE 1: Authentication + Session Security
	This feature authenticates users via username or email, verifies password hashes,
enforces account-state checks, and creates secure sessions with role awareness.

Main files:
- includes/auth.php
- config/init.php

Code snippet (from includes/auth.php, explanatory comments added):

function authenticate_user($username, $password, $pdo) {
    // Security: sanitize input before query usage
    $username = sanitize_input($username);

    // UX feature: allow login by either username OR email
    $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ? OR email = ? LIMIT 1");
    $stmt->execute([$username, $username]);
    $user = $stmt->fetch();

    // Access gate: block inactive/pending users and users who must reset password
    if ($user) {
        $status = strtolower(trim((string) ($user['status'] ?? 'active')));
        if ($status !== 'active') {
            $_SESSION['login_error_message'] = 'Account is pending activation or inactive.';
            return false;
        }
        if (isset($user['password_change_required']) && (int) $user['password_change_required'] === 1) {
            $_SESSION['login_error_message'] = 'Password setup is required before sign-in.';
            return false;
        }
    }
    // Security: verify hashed password (never compare plaintext)
    if ($user && password_verify($password, $user['password'])) {
        $update_stmt = $pdo->prepare("UPDATE users SET last_login = CURRENT_TIMESTAMP WHERE id = ?");
        $update_stmt->execute([$user['id']]);
        return $user;
    }
    return false;
}

FEATURE 2: Resident Registration with OTP Verification
	This feature validates registration input, enforces password rules and age checks,
stores pre-account data with OTP, and sends verification email before activation.

Main files:
- includes/register-handler.php
- verify-otp.php
- includes/otp_email_service.php

Code snippet (from includes/register-handler.php, explanatory comments added):

// Password policy checks
if (strlen($password) < 8) {
    $_SESSION['error_message'] = "Password must be at least 8 characters long.";
    redirect_to('../register.php');
}
if (!preg_match('/[0-9]/', $password)) {
    $_SESSION['error_message'] = "Password must contain at least one number (0-9).";
    redirect_to('../register.php');
}
if (!preg_match('/[!@#$%^&*()_+\-=\[\]{};:\'",.<>?\/\\|`~]/', $password)) {
    $_SESSION['error_message'] = "Password must contain at least one special character (!@#$%^&*).";
    redirect_to('../register.php');
}

// OTP flow
OTPEmailService::cleanupExpired($pdo);
$otpCode = OTPEmailService::generateOTP();

$registrationData = [
    'fullname' => $fullname,
    'email' => $email,
    // Security: store hashed password even in temporary registration payload
    'password' => password_hash($password, PASSWORD_DEFAULT),
    ...
];
OTPEmailService::storeOTP($pdo, $email, $otpCode, $registrationData);
OTPEmailService::sendOTP($email, $fullname, $otpCode);

FEATURE 3: CSRF Protection for Forms and APIs
	This feature prevents cross-site request forgery by issuing server-side tokens
and validating them on every protected POST action.

Main files:
- includes/csrf.php
- api/* and handler files using csrf_validate()

Code snippet (from includes/csrf.php):

function csrf_field() {
    // Generates hidden input field containing CSRF token
    return CSRFProtection::getTokenField();
}

function csrf_token() {
    // Generates token for AJAX usage
    return CSRFProtection::generateToken();
}

function csrf_validate() {
    // Validates incoming token from POST payload
    return CSRFProtection::validateFromPost();
}

FEATURE 4: Rate Limiting for Sensitive Endpoints
	This feature limits abusive requests (login, notifications API, chat API,
password resets, etc.) with lockout windows and retry metadata.

Main files:
- includes/rate_limiter.php

Code snippet (from includes/rate_limiter.php):

private static $config = [
    'login' => ['max_attempts' => 5, 'window_minutes' => 15, 'lockout_minutes' => 5],
    'password_reset' => ['max_attempts' => 3, 'window_minutes' => 60, 'lockout_minutes' => 120],
    'api_calls' => ['max_attempts' => 100, 'window_minutes' => 60, 'lockout_minutes' => 60],
    'chat_api' => ['max_attempts' => 1200, 'window_minutes' => 60, 'lockout_minutes' => 10],
    'notifications_api' => ['max_attempts' => 3600, 'window_minutes' => 60, 'lockout_minutes' => 15]
];
public static function checkRateLimit($action, $identifier) {
    // Checks recent attempts and active lockout period
    // Returns allowed/blocked state and retry metadata
}
public static function recordAttempt($action, $identifier, $success = false) {
    // Records each attempt and triggers lockout if threshold is exceeded
}

FEATURE 5: Privileged User Creation with Invite Activation Flow
	This feature allows admin/officer account provisioning while enforcing admin
re-authentication for admin creation, capacity checks, and invite-based activation.

Main files:
- admin/partials/add-user-handler.php
- admin/pages/add-user.php

Code snippet (from admin/partials/add-user-handler.php):

if ($final_role === 'admin') {
    // Security step-up auth: creator must re-enter own admin password
    if ($admin_confirmation_password === '') {
        $_SESSION['error_message'] = "Please confirm your current admin password.";
        redirect_to('../pages/add-user.php');
    }

    $actor_stmt = $pdo->prepare("SELECT password FROM users WHERE id = ? AND role = 'admin' LIMIT 1");
    $actor_stmt->execute([$actor_user_id]);
    $actor_hash = (string)($actor_stmt->fetchColumn() ?: '');

    if ($actor_hash === '' || !password_verify($admin_confirmation_password, $actor_hash)) {
        $_SESSION['error_message'] = "Admin re-authentication failed.";
        redirect_to('../pages/add-user.php');
    }
}
// Invite lifecycle: user remains pending until password setup link is completed
$raw_invite_token = bin2hex(random_bytes(32));
$invite_token_hash = hash('sha256', $raw_invite_token);
$invite_expires = date('Y-m-d H:i:s', strtotime('+24 hours'));

FEATURE 6: Incident Reporting with Geolocation + Media Upload
	Residents can click map coordinates, attach media, and submit incident reports.
The API validates CSRF, rate limits requests, validates file input, and stores paths.

Main files:
- resident/report-incident.php
- api/incidents.php

Code snippet (frontend from resident/report-incident.php):

// Dynamic map loader with environment API key
const apiKey = "<?php echo htmlspecialchars((string)(function_exists('maps_api_key') ? maps_api_key('') : ''), ENT_QUOTES, 'UTF-8'); ?>";
if (!apiKey) {
    mapDiv.innerHTML = 'Google Maps API key is missing.';
    return;
}

// Submit incident to API with same-origin credentials
fetch('../api/incidents.php', {
    method: 'POST',
    body: formData,
    credentials: 'same-origin'
})

Code snippet (backend from api/incidents.php):

if (!csrf_validate()) {
    http_response_code(403);
    echo json_encode(['error' => 'Invalid security token.']);
    exit;
}

$storage_result = StorageManager::saveUploadedFile($validated_file, 'admin/images/incidents', 'incident_');

$sql = "INSERT INTO incidents (resident_user_id, type, location, latitude, longitude, description, media_path)
        VALUES (?, ?, ?, ?, ?, ?, ?)";

**FEATURE 7: Resident-Admin Chat (Shared Admin Inbox)
	This feature supports resident-admin messaging, message read tracking,
admin-side shared inbox across multiple admins, and unread counters.**

Main files:
- api/chat.php
- admin/pages/chat.php
- resident/chat.php

Code snippet (from api/chat.php):

function getAdminIds($pdo) {
    // Multi-admin support: resolve all admin IDs dynamically
    $stmt = $pdo->query("SELECT id FROM users WHERE role = 'admin' ORDER BY id ASC");
    ...
}

if ($_POST['action'] === 'send_message' && !empty($_POST['message'])) {
    // Resident sends to primary admin; admin sends to selected resident
    $stmt = $pdo->prepare("INSERT INTO chat_messages (sender_id, receiver_id, message) VALUES (?, ?, ?)");
    $stmt->execute([$user_id, $receiver_id, $message_text]);
}

// Admin conversation list with unread counts and latest message per resident
$sql = "SELECT u.id AS user_id, u.fullname, m.message, m.sent_at,
               COALESCE(unread.unread_count, 0) AS unread_count
        ...";



**FEATURE 8: Notifications API with Caching + Sidebar Counts
	This feature centralizes notification operations (mark read/all read),
returns aggregated counts for admin UI badges, and caches expensive counts.**

Main files:
- api/notifications.php
- assets/js/admin-sidebar.min.js

Code snippet (from api/notifications.php):

init_cache_manager(['cache_dir' => __DIR__ . '/../cache/']);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($action === 'mark_read' || $action === 'mark_all_read')) {
    // Protected by role + CSRF
    if (!csrf_validate()) {
        echo json_encode(['error' => 'Invalid security token.']);
        exit;
    }
}

if ($action === 'get_admin_sidebar_counts') {
    $cacheKey = 'notifications:get_admin_sidebar_counts';
    $cachedResponse = notifications_cache_get($cacheKey);
    if ($cachedResponse !== null) {
        echo json_encode($cachedResponse);
        exit;
    }

    // Single-query aggregate counts
    $stmtSidebar = $pdo->query("SELECT
        (SELECT COUNT(*) FROM incidents WHERE status = 'Pending') AS pending_incidents,
        (SELECT COUNT(*) FROM events WHERE event_date >= CURDATE() AND event_date <= DATE_ADD(CURDATE(), INTERVAL 1 DAY)) AS upcoming_events");
}

**FEATURE 9: Document Request Workflow (Resident to Admin Processing)
	Residents submit structured document requests (example: barangay clearance).
Request payload includes purpose + JSON details used later by admin processors.**

Main files:
- resident/partials/submit-clearance.php
- admin handlers for document processing

Code snippet (from resident/partials/submit-clearance.php):

if (!csrf_validate()) {
    echo json_encode(['success' => false, 'error' => 'Invalid security token. Please refresh and try again.']);
    exit;
}

$details = [
    // Structured schema used by admin-side request processors
    'application_type' => sanitize_input($_POST['application_type'] ?? 'New'),
    'references' => [
        ['name' => sanitize_input($_POST['reference_1'] ?? '')],
        ['name' => sanitize_input($_POST['reference_2'] ?? '')]
    ],
    'ctc' => [
        'no' => sanitize_input($_POST['ctc_no'] ?? ''),
        'issued_at' => sanitize_input($_POST['ctc_issued_at'] ?? ''),
        'issued_on' => sanitize_input($_POST['ctc_issued_on'] ?? '')
    ]
];

$sql = "INSERT INTO document_requests (resident_id, document_type, purpose, details, requested_by_user_id, status)
        VALUES (?, 'Barangay Clearance', ?, ?, ?, 'Pending')";

**FEATURE 10: Permit Expiry Automation + Scheduler-Safe Idempotency
	This feature auto-checks permits nearing expiration, updates expired statuses,
and sends deduplicated notifications. It supports secure scheduler calls and
prevents duplicate concurrent execution with database locks.**

Main files:
- api/check-expiring-permits.php
- cron_check_expiring_permits.php

Code snippet (from api/check-expiring-permits.php):

function is_scheduler_authorized(): bool {
    $expectedToken = trim((string)env('PERMIT_CHECK_SCHEDULER_TOKEN', ''));
    $providedToken = request_header('X-Cloud-Scheduler-Token');
    return $expectedToken !== '' && $providedToken !== '' && hash_equals($expectedToken, $providedToken);
}
$lockStmt = $pdo->prepare('SELECT GET_LOCK(?, 1)');
$lockStmt->execute([$lockName]);
$lockAcquired = ((int) $lockStmt->fetchColumn() === 1);

// Deduplicated notification insert within configurable time window
function insert_admin_notification_batch(PDO $pdo, string $title, string $message, string $type, string $link, int $dedupeWindowMinutes = 15): int {
    ...
}

FEATURE 11: Admin Dashboard Analytics Endpoint
	This feature powers admin cards with aggregated counts in one DB trip and emits
performance headers for runtime observability.

Main files:
- admin/partials/dashboard-stats.php

Code snippet:
$stats_stmt = $pdo->query("SELECT
    (SELECT COUNT(*) FROM document_requests WHERE status = 'Pending') AS pending_doc_requests,
    (SELECT COUNT(*) FROM business_transactions WHERE status = 'Pending') AS pending_biz_requests,
    (SELECT COUNT(*) FROM businesses) AS business_count,
    (SELECT COUNT(*) FROM residents) AS resident_count,
    (SELECT COUNT(*) FROM incidents WHERE status IN ('Pending', 'In Progress')) AS active_incidents,
    (SELECT COUNT(*) FROM events WHERE event_date >= CURDATE()) AS upcoming_events");

header('X-Response-Time-Ms: ' . number_format($elapsed_ms, 2, '.', ''));

**FEATURE 12: Storage Abstraction (Local + Cloud)
	This feature decouples uploads/deletes from local filesystem assumptions and
supports cloud object storage mode through environment flags.**

Main files:
- includes/storage_manager.php

Code snippet:
public static function saveUploadedFile(array $validatedFile, string $relativeDir, string $prefix = 'upload_'): array {
    // Computes object path and uses cloud mode when configured
    if (self::shouldUseCloudStorage()) {
        $cloudResult = self::saveToCloudStorage($tmpName, $objectPath);
        if ($cloudResult['success']) {
            return $cloudResult;
        }
        // Reliability fallback: if cloud upload fails, try local storage
        error_log('Cloud storage write failed, falling back to local storage');
    }
    return self::saveToLocalStorage($tmpName, $relativeDir, $filename);
}
public static function deleteStoredPath(string $storedPath): bool {
    // Supports both gs:// object paths and local file paths
    if (strpos($storedPath, 'gs://') === 0) {
        return self::deleteFromCloudStorage($storedPath);
    }
    return self::deleteFromLocalStorage($storedPath);
}
