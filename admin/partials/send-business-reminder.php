<?php
require_once '../../config/init.php';
require_once '../../includes/functions.php';
require_once '../../includes/auth.php';

header('Content-Type: application/json');

if (!is_admin_or_official()) {
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'error' => 'Invalid request method']);
    exit;
}

$resident_id = isset($_POST['resident_id']) ? (int) $_POST['resident_id'] : 0;
$business_id = isset($_POST['business_id']) ? (int) $_POST['business_id'] : 0;
$business_name = sanitize_input(trim($_POST['business_name'] ?? 'your business'));
$expiry_date = trim($_POST['expiry_date'] ?? '');

if ($resident_id <= 0 || $business_id <= 0) {
    echo json_encode(['success' => false, 'error' => 'Invalid parameters']);
    exit;
}

$rate_identifier = ($_SESSION['user_id'] ?? 'unknown') . ':' . $resident_id;
$limit = RateLimiter::checkRateLimit('api_calls', $rate_identifier);
if (!$limit['allowed']) {
    echo json_encode([
        'success' => false,
        'error' => $limit['message'] ?? 'Too many requests. Please try again later.'
    ]);
    exit;
}

RateLimiter::recordAttempt('api_calls', $rate_identifier);

function send_mailgun_email($to, $name, $subject, $html_message) {
    if (!defined('MAILGUN_API_KEY') || !defined('MAILGUN_DOMAIN') || MAILGUN_API_KEY === '' || MAILGUN_DOMAIN === '') {
        return false;
    }

    $from_email = defined('MAILGUN_FROM_EMAIL') ? MAILGUN_FROM_EMAIL : 'noreply@communalink.local';
    $from_name = defined('MAILGUN_FROM_NAME') ? MAILGUN_FROM_NAME : 'CommuniLink';

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, 'https://api.mailgun.net/v3/' . MAILGUN_DOMAIN . '/messages');
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, [
        'from' => $from_name . ' <' . $from_email . '>',
        'to' => $name ? ($name . ' <' . $to . '>') : $to,
        'subject' => $subject,
        'html' => $html_message
    ]);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Authorization: Basic ' . base64_encode('api:' . MAILGUN_API_KEY)
    ]);

    curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    return $http_code >= 200 && $http_code < 300;
}

function send_sendgrid_email($to, $name, $subject, $html_message) {
    if (!defined('SENDGRID_API_KEY') || SENDGRID_API_KEY === '') {
        return false;
    }

    $from_email = defined('SENDGRID_FROM_EMAIL') ? SENDGRID_FROM_EMAIL : 'noreply@communalink.local';
    $from_name = defined('SENDGRID_FROM_NAME') ? SENDGRID_FROM_NAME : 'CommuniLink';

    $payload = [
        'personalizations' => [[
            'to' => [[
                'email' => $to,
                'name' => $name
            ]],
            'subject' => $subject
        ]],
        'from' => [
            'email' => $from_email,
            'name' => $from_name
        ],
        'content' => [[
            'type' => 'text/html',
            'value' => $html_message
        ]]
    ];

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, 'https://api.sendgrid.com/v3/mail/send');
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Authorization: Bearer ' . SENDGRID_API_KEY,
        'Content-Type: application/json'
    ]);

    curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    return $http_code === 202;
}

try {
    $stmt = $pdo->prepare("SELECT r.id, r.first_name, r.last_name, r.email as resident_email, u.email as user_email
                           FROM residents r
                           LEFT JOIN users u ON r.user_id = u.id
                           WHERE r.id = ?");
    $stmt->execute([$resident_id]);
    $resident = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$resident) {
        echo json_encode(['success' => false, 'error' => 'Resident not found']);
        exit;
    }

    $resident_name = trim(($resident['first_name'] ?? '') . ' ' . ($resident['last_name'] ?? 'Resident'));
    $resident_email = trim((string) ($resident['resident_email'] ?: $resident['user_email'] ?: ''));

    $message = "Reminder: Your business permit for {$business_name} is nearing expiry. Please process renewal as soon as possible.";
    if (!empty($expiry_date)) {
        $message .= " Expiry date: {$expiry_date}.";
    }

    $link = 'my-requests.php';

    $notif_stmt = $pdo->prepare("INSERT INTO notifications (resident_id, message, link, is_read) VALUES (?, ?, ?, 0)");
    $notif_stmt->execute([$resident_id, $message, $link]);

    $email_sent = false;
    if ($resident_email !== '') {
        if (file_exists('../../config/email_config.php')) {
            require_once '../../config/email_config.php';
        }

        $subject = 'Business Permit Renewal Reminder';
        $html_message = '<p>Dear ' . htmlspecialchars($resident_name, ENT_QUOTES, 'UTF-8') . ',</p>'
            . '<p>' . htmlspecialchars($message, ENT_QUOTES, 'UTF-8') . '</p>'
            . '<p>Please visit the barangay office for renewal requirements.</p>'
            . '<p>Thank you.<br>CommuniLink Barangay Office</p>';

        $email_sent = send_mailgun_email($resident_email, $resident_name, $subject, $html_message);
        if (!$email_sent) {
            $email_sent = send_sendgrid_email($resident_email, $resident_name, $subject, $html_message);
        }
        if (!$email_sent) {
            $headers = "MIME-Version: 1.0\r\n";
            $headers .= "Content-type:text/html;charset=UTF-8\r\n";
            $headers .= "From: CommuniLink <noreply@communalink.local>\r\n";
            $email_sent = @mail($resident_email, $subject, $html_message, $headers);
        }
    }

    log_activity_db(
        $pdo,
        'send_reminder',
        'business',
        $business_id,
        "Renewal reminder sent for business '{$business_name}' to resident ID {$resident_id}",
        null,
        $email_sent ? 'in_app+email' : 'in_app_only'
    );

    echo json_encode([
        'success' => true,
        'message' => $email_sent ? 'Reminder sent via in-app notification and email.' : 'Reminder sent in-app. Email was not delivered.',
        'email_sent' => $email_sent
    ]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => 'Failed to send reminder']);
}
