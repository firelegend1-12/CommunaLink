<?php
/**
 * Notification System Service
 * Centralized in-app + email notification delivery helpers.
 */

require_once __DIR__ . '/functions.php';

if (!class_exists('NotificationSystem')) {
    class NotificationSystem {
        /**
         * Notify resident about upcoming business permit expiry.
         *
         * @param PDO $pdo
         * @param int $recipient_user_id
         * @param string $recipient_name
         * @param string $recipient_email
         * @param int $business_id
         * @param string $business_name
         * @param string $expiry_date
         * @param string $link
         * @return array{success:bool,error:?string,notification_created:bool,email_sent:bool,title:string,message:string}
         */
        public static function notify_business_expiry($pdo, $recipient_user_id, $recipient_name, $recipient_email, $business_id, $business_name, $expiry_date = '', $link = 'my-requests.php') {
            $recipient_user_id = (int) $recipient_user_id;
            $business_id = (int) $business_id;
            $business_name = trim((string) $business_name);
            $expiry_date = trim((string) $expiry_date);
            $recipient_name = trim((string) $recipient_name);
            $recipient_email = trim((string) $recipient_email);

            if ($recipient_user_id <= 0 || $business_id <= 0) {
                return [
                    'success' => false,
                    'error' => 'Invalid notification parameters.',
                    'notification_created' => false,
                    'email_sent' => false,
                    'title' => '',
                    'message' => '',
                ];
            }

            $title = 'Business Permit Renewal Reminder';
            $message = "Reminder: Your business permit for {$business_name} is nearing expiry. Please process renewal as soon as possible.";
            if ($expiry_date !== '') {
                $message .= " Expiry date: {$expiry_date}.";
            }

            $notification_created = create_notification(
                $pdo,
                $recipient_user_id,
                $title,
                $message,
                'business_reminder',
                $link
            );

            if (!$notification_created) {
                return [
                    'success' => false,
                    'error' => 'Failed to create in-app notification.',
                    'notification_created' => false,
                    'email_sent' => false,
                    'title' => $title,
                    'message' => $message,
                ];
            }

            $email_sent = false;
            if ($recipient_email !== '') {
                $email_body = '<p>Dear ' . htmlspecialchars($recipient_name !== '' ? $recipient_name : 'Resident', ENT_QUOTES, 'UTF-8') . ',</p>'
                    . '<p>' . htmlspecialchars($message, ENT_QUOTES, 'UTF-8') . '</p>'
                    . '<p>Please visit the barangay office for renewal requirements.</p>'
                    . '<p>Thank you.<br>CommunaLink Barangay Office</p>';

                $email_sent = self::send_email_with_fallback($recipient_email, $recipient_name, $title, $email_body);
            }

            return [
                'success' => true,
                'error' => null,
                'notification_created' => true,
                'email_sent' => $email_sent,
                'title' => $title,
                'message' => $message,
            ];
        }

        /**
         * Notify user that a document request status was updated.
         *
         * @param PDO $pdo
         * @param int $recipient_user_id
         * @param string $document_type
         * @param string $status
         * @param string $link
         * @return bool
         */
        public static function notify_document_status($pdo, $recipient_user_id, $document_type, $status, $link = 'my-requests.php') {
            $recipient_user_id = (int) $recipient_user_id;
            if ($recipient_user_id <= 0) {
                return false;
            }

            $document_type = trim((string) $document_type);
            $status = trim((string) $status);

            $title = 'Request Update: ' . $document_type;
            $message = 'Your request for ' . $document_type . ' has been updated to: ' . $status . '. ';
            if ($status === 'Approved') {
                $message .= 'Please proceed to the Barangay Hall for receiving and checkout.';
            } elseif ($status === 'Rejected') {
                $message .= 'Please contact the office for more details.';
            }

            // Create in-app notification
            $notification_created = create_notification($pdo, $recipient_user_id, $title, $message, 'request_status', $link);

            // Send email for Approved or Rejected status
            if ($status === 'Approved' || $status === 'Rejected') {
                try {
                    $stmt = $pdo->prepare("SELECT email, fullname FROM users WHERE id = ? LIMIT 1");
                    $stmt->execute([$recipient_user_id]);
                    $user = $stmt->fetch(PDO::FETCH_ASSOC);
                    if ($user && !empty($user['email'])) {
                        $email_body = '<p>Dear ' . htmlspecialchars($user['fullname'] ?: 'Resident') . ',</p>'
                            . '<p>Your request for <strong>' . htmlspecialchars($document_type) . '</strong> has been <strong>' . htmlspecialchars($status) . '</strong>.</p>';
                        if ($status === 'Approved') {
                            $email_body .= '<p>Please proceed to the Barangay Hall for receiving and checkout.</p>';
                        } elseif ($status === 'Rejected') {
                            $email_body .= '<p>Please contact the office for more details.</p>';
                        }
                        $email_body .= '<p>Thank you.<br>CommunaLink Barangay Office</p>';

                        self::send_email_with_fallback(
                            $user['email'],
                            $user['fullname'] ?: 'Resident',
                            $title,
                            $email_body
                        );
                    }
                } catch (Exception $e) {
                    error_log('Failed to send email notification for document status: ' . $e->getMessage());
                }
            }

            return $notification_created;
        }

        /**
         * Notify resident that an incident report status changed.
         *
         * @param PDO $pdo
         * @param int $recipient_user_id
         * @param string $incident_type
         * @param string $status
         * @param string $reason
         * @param string $link
         * @return bool
         */
        public static function notify_incident_status($pdo, $recipient_user_id, $incident_type, $status, $reason = '', $link = '/resident/my-reports.php') {
            $recipient_user_id = (int) $recipient_user_id;
            if ($recipient_user_id <= 0) {
                return false;
            }

            $incident_type = trim((string) $incident_type);
            $status = trim((string) $status);
            $reason = trim((string) $reason);
            $link = trim((string) $link);
            if ($link === '') {
                $link = '/resident/my-reports.php';
            }
            if (function_exists('app_url')) {
                $base_path = function_exists('app_base_path') ? app_base_path() : '';
                $starts_with_base = $base_path !== '' && strpos($link, $base_path . '/') === 0;
                if (!$starts_with_base) {
                    $link = app_url($link);
                }
            }

            $title = 'Incident Update: ' . $incident_type;
            $message = 'Your incident report for ' . $incident_type . ' has been updated to: ' . $status . '. ';

            if ($status === 'Rejected') {
                if ($reason !== '') {
                    $message .= 'Reason: ' . $reason . '. ';
                }
                $message .= 'Please review the details or contact the barangay office for clarification.';
            } elseif ($status === 'Resolved') {
                $message .= 'Your report has been marked as resolved. Thank you for your patience.';
            }

            return create_notification($pdo, $recipient_user_id, $title, $message, 'incident_status', $link);
        }

        /**
         * Notify user about payment status updates.
         *
         * @param PDO $pdo
         * @param int $recipient_user_id
         * @param string $item_name
         * @param string $payment_status
         * @param string $or_number
         * @param string $link
         * @return bool
         */
        public static function notify_payment_update($pdo, $recipient_user_id, $item_name, $payment_status, $or_number = '', $link = 'my-requests.php') {
            $recipient_user_id = (int) $recipient_user_id;
            if ($recipient_user_id <= 0) {
                return false;
            }

            $item_name = trim((string) $item_name);
            $payment_status = trim((string) $payment_status);
            $or_number = trim((string) $or_number);

            $title = 'Payment Updated: ' . $item_name;
            $message = 'Payment information for ' . $item_name . ' has been updated. ';

            if (strcasecmp($payment_status, 'Paid') === 0) {
                $message .= 'Status: Paid.';
                if ($or_number !== '') {
                    $message .= ' Official Receipt #: ' . $or_number . '.';
                }
                $message .= ' Date: ' . date('M d, Y') . '.';
            } else {
                $message .= 'Status: Unpaid. Please settle your balance at the Barangay Hall.';
            }

            return create_notification($pdo, $recipient_user_id, $title, $message, 'payment_update', $link);
        }

        /**
         * Broadcast a newly published public post (announcement/event) to resident recipients.
         *
         * @param PDO $pdo
         * @param array $post_data
         * @return array{success:bool,error:?string,recipient_count:int,notification_created:int,email_sent:int}
         */
        public static function notify_public_post($pdo, array $post_data) {
            $title = trim((string)($post_data['title'] ?? ''));
            $content = trim((string)($post_data['content'] ?? ''));
            $target_audience = trim((string)($post_data['target_audience'] ?? 'all'));
            $is_event = !empty($post_data['is_event']);
            $event_date = trim((string)($post_data['event_date'] ?? ''));
            $event_time = trim((string)($post_data['event_time'] ?? ''));
            $event_location = trim((string)($post_data['event_location'] ?? ''));

            if ($title === '') {
                return [
                    'success' => false,
                    'error' => 'Missing post title.',
                    'recipient_count' => 0,
                    'notification_created' => 0,
                    'email_sent' => 0,
                ];
            }

            $recipients = self::fetch_resident_recipients($pdo, $target_audience);
            if (empty($recipients)) {
                return [
                    'success' => true,
                    'error' => null,
                    'recipient_count' => 0,
                    'notification_created' => 0,
                    'email_sent' => 0,
                ];
            }

            $link = '/resident/notifications.php';
            $absolute_link = function_exists('app_url') ? app_url($link) : $link;

            $subject_prefix = $is_event ? 'New Barangay Event' : 'New Barangay Announcement';
            $notification_type = $is_event ? 'event_announcement' : 'announcement';
            $notification_title = $subject_prefix . ': ' . $title;

            $summary = $content;
            if (strlen($summary) > 220) {
                $summary = substr($summary, 0, 217) . '...';
            }

            $notification_message = $summary !== ''
                ? $summary
                : ($is_event ? 'A new barangay event was posted.' : 'A new barangay announcement was posted.');

            if ($is_event) {
                $event_details = [];
                if ($event_date !== '') {
                    $event_details[] = 'Date: ' . date('M d, Y', strtotime($event_date));
                }
                if ($event_time !== '') {
                    $event_details[] = 'Time: ' . date('h:i A', strtotime($event_time));
                }
                if ($event_location !== '') {
                    $event_details[] = 'Venue: ' . $event_location;
                }

                if (!empty($event_details)) {
                    $notification_message .= ' ' . implode(' | ', $event_details);
                }
            }

            $in_app_count = 0;
            $email_count = 0;

            foreach ($recipients as $recipient) {
                $recipient_user_id = (int)($recipient['user_id'] ?? 0);
                if ($recipient_user_id <= 0) {
                    continue;
                }

                $notification_created = create_notification(
                    $pdo,
                    $recipient_user_id,
                    $notification_title,
                    $notification_message,
                    $notification_type,
                    $link
                );

                if ($notification_created) {
                    $in_app_count++;
                }

                $recipient_email = trim((string)($recipient['email'] ?? ''));
                if ($recipient_email === '' || !filter_var($recipient_email, FILTER_VALIDATE_EMAIL)) {
                    continue;
                }

                $recipient_name = trim((string)($recipient['fullname'] ?? 'Resident'));
                $email_body = self::build_post_email_body([
                    'title' => $title,
                    'content' => $content,
                    'is_event' => $is_event,
                    'event_date' => $event_date,
                    'event_time' => $event_time,
                    'event_location' => $event_location,
                ], $recipient_name, $absolute_link);

                if (self::send_email_with_fallback($recipient_email, $recipient_name, $notification_title, $email_body)) {
                    $email_count++;
                }
            }

            return [
                'success' => true,
                'error' => null,
                'recipient_count' => count($recipients),
                'notification_created' => $in_app_count,
                'email_sent' => $email_count,
            ];
        }

        /**
         * Broadcast a barangay event from the dedicated events module.
         *
         * @param PDO $pdo
         * @param array $event_data
         * @return array{success:bool,error:?string,recipient_count:int,notification_created:int,email_sent:int}
         */
        public static function notify_barangay_event($pdo, array $event_data) {
            $event_data['is_event'] = true;
            if (!isset($event_data['target_audience']) || trim((string)$event_data['target_audience']) === '') {
                $event_data['target_audience'] = 'all';
            }

            return self::notify_public_post($pdo, $event_data);
        }

        /**
         * Resolve resident recipients for announcement/event broadcasts.
         *
         * @param PDO $pdo
         * @param string $target_audience
         * @return array<int,array<string,mixed>>
         */
        private static function fetch_resident_recipients($pdo, $target_audience = 'all') {
            $target_audience = trim((string)$target_audience);
            $params = [];

            $join_clauses = '';
            $where_clauses = [
                "u.role = 'resident'",
                "r.user_id IS NOT NULL"
            ];

            if (self::table_column_exists($pdo, 'users', 'status')) {
                $where_clauses[] = "(u.status IS NULL OR LOWER(u.status) = 'active')";
            }

            if ($target_audience === 'business') {
                $join_clauses .= ' INNER JOIN businesses b ON b.resident_id = r.id ';
                if (self::table_column_exists($pdo, 'businesses', 'status')) {
                    $where_clauses[] = "(b.status IS NULL OR LOWER(b.status) <> 'inactive')";
                }
            } elseif ($target_audience !== '' && !in_array($target_audience, ['all', 'residents'], true)) {
                $where_clauses[] = 'LOWER(TRIM(r.address)) = LOWER(TRIM(?))';
                $params[] = $target_audience;
            }

            $sql = "SELECT DISTINCT u.id AS user_id, u.fullname, u.email
                    FROM users u
                    INNER JOIN residents r ON r.user_id = u.id
                    {$join_clauses}
                    WHERE " . implode(' AND ', $where_clauses) . "
                    ORDER BY u.id ASC";

            try {
                $stmt = $pdo->prepare($sql);
                $stmt->execute($params);
                return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
            } catch (PDOException $e) {
                error_log('NotificationSystem recipient resolution failed: ' . $e->getMessage());
                return [];
            }
        }

        /**
         * Schema-safe column existence check with in-request caching.
         */
        private static function table_column_exists($pdo, $table_name, $column_name) {
            static $cache = [];
            $cache_key = strtolower((string)$table_name . ':' . (string)$column_name);
            if (array_key_exists($cache_key, $cache)) {
                return $cache[$cache_key];
            }

            $stmt = $pdo->prepare(
                "SELECT COUNT(*)
                 FROM information_schema.COLUMNS
                 WHERE TABLE_SCHEMA = DATABASE()
                   AND TABLE_NAME = ?
                   AND COLUMN_NAME = ?"
            );
            $stmt->execute([(string)$table_name, (string)$column_name]);
            $exists = ((int)$stmt->fetchColumn()) > 0;
            $cache[$cache_key] = $exists;

            return $exists;
        }

        /**
         * Build an HTML email body for announcement/event broadcasts.
         */
        private static function build_post_email_body(array $post_data, $recipient_name, $link) {
            $title = trim((string)($post_data['title'] ?? 'Community Update'));
            $content = trim((string)($post_data['content'] ?? ''));
            $is_event = !empty($post_data['is_event']);
            $event_date = trim((string)($post_data['event_date'] ?? ''));
            $event_time = trim((string)($post_data['event_time'] ?? ''));
            $event_location = trim((string)($post_data['event_location'] ?? ''));

            $safe_name = htmlspecialchars(trim((string)$recipient_name) !== '' ? (string)$recipient_name : 'Resident', ENT_QUOTES, 'UTF-8');
            $safe_title = htmlspecialchars($title, ENT_QUOTES, 'UTF-8');
            $safe_content = nl2br(htmlspecialchars($content !== '' ? $content : 'Please view this update in your resident portal.', ENT_QUOTES, 'UTF-8'));
            $safe_link = htmlspecialchars((string)$link, ENT_QUOTES, 'UTF-8');

            $event_html = '';
            if ($is_event) {
                $event_parts = [];
                if ($event_date !== '') {
                    $event_parts[] = '<li><strong>Date:</strong> ' . htmlspecialchars(date('M d, Y', strtotime($event_date)), ENT_QUOTES, 'UTF-8') . '</li>';
                }
                if ($event_time !== '') {
                    $event_parts[] = '<li><strong>Time:</strong> ' . htmlspecialchars(date('h:i A', strtotime($event_time)), ENT_QUOTES, 'UTF-8') . '</li>';
                }
                if ($event_location !== '') {
                    $event_parts[] = '<li><strong>Venue:</strong> ' . htmlspecialchars($event_location, ENT_QUOTES, 'UTF-8') . '</li>';
                }

                if (!empty($event_parts)) {
                    $event_html = '<p><strong>Event Details</strong></p><ul>' . implode('', $event_parts) . '</ul>';
                }
            }

            return '<p>Dear ' . $safe_name . ',</p>'
                . '<p>A new ' . ($is_event ? 'barangay event' : 'barangay announcement') . ' has been posted.</p>'
                . '<h3 style="margin-bottom:8px;">' . $safe_title . '</h3>'
                . '<p style="line-height:1.6;">' . $safe_content . '</p>'
                . $event_html
                . '<p style="margin-top:18px;"><a href="' . $safe_link . '" style="display:inline-block;padding:10px 14px;background:#2563eb;color:#ffffff;text-decoration:none;border-radius:6px;">Open Resident Notifications</a></p>'
                . '<p>Thank you.<br>CommunaLink Barangay Office</p>';
        }

        /**
         * Send email using Mailgun -> SendGrid -> native mail fallback.
         *
         * @param string $to
         * @param string $name
         * @param string $subject
         * @param string $html_message
         * @return bool
         */
        public static function send_email_with_fallback($to, $name, $subject, $html_message) {
            if (file_exists(__DIR__ . '/../config/email_config.php')) {
                require_once __DIR__ . '/../config/email_config.php';
            }

            if (self::send_mailgun_email($to, $name, $subject, $html_message)) {
                return true;
            }

            if (self::send_sendgrid_email($to, $name, $subject, $html_message)) {
                return true;
            }

            return self::send_native_email($to, $subject, $html_message);
        }

        private static function send_mailgun_email($to, $name, $subject, $html_message) {
            if (!defined('MAILGUN_API_KEY') || !defined('MAILGUN_DOMAIN') || MAILGUN_API_KEY === '' || MAILGUN_DOMAIN === '') {
                return false;
            }

            $from_email = defined('MAILGUN_FROM_EMAIL') ? MAILGUN_FROM_EMAIL : 'noreply@communalink.local';
            $from_name = defined('MAILGUN_FROM_NAME') ? MAILGUN_FROM_NAME : 'CommunaLink';

            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, 'https://api.mailgun.net/v3/' . MAILGUN_DOMAIN . '/messages');
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, [
                'from' => $from_name . ' <' . $from_email . '>',
                'to' => $name ? ($name . ' <' . $to . '>') : $to,
                'subject' => $subject,
                'html' => $html_message,
            ]);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Authorization: Basic ' . base64_encode('api:' . MAILGUN_API_KEY),
            ]);

            $response = curl_exec($ch);
            $curl_errno = curl_errno($ch);
            $curl_error = curl_error($ch);
            $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            if ($response === false || $curl_errno !== 0) {
                error_log('NotificationSystem Mailgun transport error: ' . $curl_error . ' (' . $curl_errno . ')');
                return false;
            }

            if ($http_code < 200 || $http_code >= 300) {
                error_log('NotificationSystem Mailgun API error. HTTP ' . $http_code . ' Response: ' . (string) $response);
                return false;
            }

            return true;
        }

        private static function send_sendgrid_email($to, $name, $subject, $html_message) {
            if (!defined('SENDGRID_API_KEY') || SENDGRID_API_KEY === '') {
                return false;
            }

            $from_email = defined('SENDGRID_FROM_EMAIL') ? SENDGRID_FROM_EMAIL : 'noreply@communalink.local';
            $from_name = defined('SENDGRID_FROM_NAME') ? SENDGRID_FROM_NAME : 'CommunaLink';

            $payload = [
                'personalizations' => [[
                    'to' => [[
                        'email' => $to,
                        'name' => $name,
                    ]],
                    'subject' => $subject,
                ]],
                'from' => [
                    'email' => $from_email,
                    'name' => $from_name,
                ],
                'content' => [[
                    'type' => 'text/html',
                    'value' => $html_message,
                ]],
            ];

            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, 'https://api.sendgrid.com/v3/mail/send');
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Authorization: Bearer ' . constant('SENDGRID_API_KEY'),
                'Content-Type: application/json',
            ]);

            $response = curl_exec($ch);
            $curl_errno = curl_errno($ch);
            $curl_error = curl_error($ch);
            $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            if ($response === false || $curl_errno !== 0) {
                error_log('NotificationSystem SendGrid transport error: ' . $curl_error . ' (' . $curl_errno . ')');
                return false;
            }

            if ($http_code !== 202) {
                error_log('NotificationSystem SendGrid API error. HTTP ' . $http_code . ' Response: ' . (string) $response);
                return false;
            }

            return true;
        }

        private static function send_native_email($to, $subject, $html_message) {
            $headers = "MIME-Version: 1.0\r\n";
            $headers .= "Content-type:text/html;charset=UTF-8\r\n";
            $headers .= "From: CommunaLink <noreply@communalink.local>\r\n";

            return (bool) @mail($to, $subject, $html_message, $headers);
        }
    }
}
