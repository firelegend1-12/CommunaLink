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
         * Enqueue public post broadcast for asynchronous processing.
         *
         * @param PDO $pdo
         * @param array $post_data
         * @return array{success:bool,error:?string,queue_id:int}
         */
        public static function enqueue_public_post($pdo, array $post_data) {
            $title = trim((string)($post_data['title'] ?? ''));
            if ($title === '') {
                return [
                    'success' => false,
                    'error' => 'Missing post title.',
                    'queue_id' => 0,
                ];
            }

            $payload = [
                'title' => $title,
                'content' => trim((string)($post_data['content'] ?? '')),
                'target_audience' => trim((string)($post_data['target_audience'] ?? 'all')),
                'is_event' => !empty($post_data['is_event']) ? 1 : 0,
                'event_date' => trim((string)($post_data['event_date'] ?? '')),
                'event_time' => trim((string)($post_data['event_time'] ?? '')),
                'event_location' => trim((string)($post_data['event_location'] ?? '')),
            ];

            $encoded_payload = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            if ($encoded_payload === false) {
                return [
                    'success' => false,
                    'error' => 'Failed to encode broadcast payload.',
                    'queue_id' => 0,
                ];
            }

            try {
                $stmt = $pdo->prepare(
                    "INSERT INTO public_post_dispatch_queue (payload_json, status, attempts, max_attempts, available_at, created_at, updated_at)
                     VALUES (?, 'pending', 0, 5, NOW(), NOW(), NOW())"
                );
                $stmt->execute([$encoded_payload]);

                return [
                    'success' => true,
                    'error' => null,
                    'queue_id' => (int)$pdo->lastInsertId(),
                ];
            } catch (PDOException $e) {
                error_log('NotificationSystem enqueue_public_post failed: ' . $e->getMessage());
                return [
                    'success' => false,
                    'error' => 'Unable to queue post broadcast.',
                    'queue_id' => 0,
                ];
            }
        }

        /**
         * Process queued public post broadcast jobs.
         *
         * @param PDO $pdo
         * @param int $limit
         * @return array{success:bool,error:?string,processed:int,completed:int,failed:int,requeued:int,remaining:int}
         */
        public static function process_public_post_queue($pdo, $limit = 10) {
            $limit = max(1, min(100, (int)$limit));
            $processed = 0;
            $completed = 0;
            $failed = 0;
            $requeued = 0;

            for ($i = 0; $i < $limit; $i++) {
                $job = null;

                try {
                    $select = $pdo->prepare(
                        "SELECT id, payload_json, attempts, max_attempts
                         FROM public_post_dispatch_queue
                         WHERE status = 'pending'
                           AND available_at <= NOW()
                         ORDER BY id ASC
                         LIMIT 1"
                    );
                    $select->execute();
                    $job = $select->fetch(PDO::FETCH_ASSOC) ?: null;
                } catch (PDOException $e) {
                    error_log('NotificationSystem process_public_post_queue select failed: ' . $e->getMessage());
                    break;
                }

                if (!$job) {
                    break;
                }

                $job_id = (int)($job['id'] ?? 0);
                if ($job_id <= 0) {
                    break;
                }

                try {
                    $claim = $pdo->prepare(
                        "UPDATE public_post_dispatch_queue
                         SET status = 'processing',
                             attempts = attempts + 1,
                             locked_at = NOW(),
                             updated_at = NOW(),
                             last_error = NULL
                         WHERE id = ?
                           AND status = 'pending'"
                    );
                    $claim->execute([$job_id]);
                    if ($claim->rowCount() !== 1) {
                        continue;
                    }
                } catch (PDOException $e) {
                    error_log('NotificationSystem process_public_post_queue claim failed: ' . $e->getMessage());
                    continue;
                }

                $processed++;
                $attempt_no = ((int)($job['attempts'] ?? 0)) + 1;
                $max_attempts = max(1, (int)($job['max_attempts'] ?? 5));

                $payload = json_decode((string)($job['payload_json'] ?? ''), true);
                if (!is_array($payload)) {
                    self::mark_public_post_job_failed($pdo, $job_id, 'invalid_payload_json');
                    $failed++;
                    continue;
                }

                $result = self::notify_public_post($pdo, $payload);
                if (!empty($result['success'])) {
                    self::mark_public_post_job_completed($pdo, $job_id);
                    $completed++;
                    continue;
                }

                $error_message = trim((string)($result['error'] ?? 'broadcast_failed'));
                if ($attempt_no >= $max_attempts) {
                    self::mark_public_post_job_failed($pdo, $job_id, $error_message);
                    $failed++;
                } else {
                    $delay_minutes = min(30, max(1, $attempt_no * 2));
                    self::requeue_public_post_job($pdo, $job_id, $error_message, $delay_minutes);
                    $requeued++;
                }
            }

            $remaining = 0;
            try {
                $remaining_stmt = $pdo->query(
                    "SELECT COUNT(*)
                     FROM public_post_dispatch_queue
                     WHERE status = 'pending'
                       AND available_at <= NOW()"
                );
                $remaining = (int)$remaining_stmt->fetchColumn();
            } catch (PDOException $e) {
                error_log('NotificationSystem process_public_post_queue count failed: ' . $e->getMessage());
            }

            return [
                'success' => true,
                'error' => null,
                'processed' => $processed,
                'completed' => $completed,
                'failed' => $failed,
                'requeued' => $requeued,
                'remaining' => $remaining,
            ];
        }

        private static function mark_public_post_job_completed($pdo, $job_id) {
            try {
                $stmt = $pdo->prepare(
                    "UPDATE public_post_dispatch_queue
                     SET status = 'completed',
                         completed_at = NOW(),
                         locked_at = NULL,
                         updated_at = NOW(),
                         last_error = NULL
                     WHERE id = ?"
                );
                $stmt->execute([(int)$job_id]);
            } catch (PDOException $e) {
                error_log('NotificationSystem mark_public_post_job_completed failed: ' . $e->getMessage());
            }
        }

        private static function mark_public_post_job_failed($pdo, $job_id, $error_message) {
            try {
                $stmt = $pdo->prepare(
                    "UPDATE public_post_dispatch_queue
                     SET status = 'failed',
                         failed_at = NOW(),
                         locked_at = NULL,
                         updated_at = NOW(),
                         last_error = ?
                     WHERE id = ?"
                );
                $stmt->execute([substr((string)$error_message, 0, 1000), (int)$job_id]);
            } catch (PDOException $e) {
                error_log('NotificationSystem mark_public_post_job_failed failed: ' . $e->getMessage());
            }
        }

        private static function requeue_public_post_job($pdo, $job_id, $error_message, $delay_minutes) {
            $delay_minutes = max(1, min(60, (int)$delay_minutes));

            try {
                $stmt = $pdo->prepare(
                    "UPDATE public_post_dispatch_queue
                     SET status = 'pending',
                         locked_at = NULL,
                         updated_at = NOW(),
                         available_at = DATE_ADD(NOW(), INTERVAL {$delay_minutes} MINUTE),
                         last_error = ?
                     WHERE id = ?"
                );
                $stmt->execute([substr((string)$error_message, 0, 1000), (int)$job_id]);
            } catch (PDOException $e) {
                error_log('NotificationSystem requeue_public_post_job failed: ' . $e->getMessage());
            }
        }

        /**
         * Resolve recipients for announcement/event broadcasts.
         *
         * @param PDO $pdo
         * @param string $target_audience
         * @return array<int,array<string,mixed>>
         */
        private static function fetch_resident_recipients($pdo, $target_audience = 'all') {
            $target_audience = trim((string)$target_audience);
            $params = [];

            $join_clauses = ' LEFT JOIN residents r ON r.user_id = u.id ';
            $where_clauses = [
                "u.email IS NOT NULL",
                "TRIM(u.email) <> ''"
            ];

            if (self::table_column_exists($pdo, 'users', 'status')) {
                $where_clauses[] = "(u.status IS NULL OR LOWER(u.status) = 'active')";
            }

            if ($target_audience === 'business') {
                $where_clauses[] = 'r.id IS NOT NULL';
                $join_clauses .= ' INNER JOIN businesses b ON b.resident_id = r.id ';
                if (self::table_column_exists($pdo, 'businesses', 'status')) {
                    $where_clauses[] = "(b.status IS NULL OR LOWER(b.status) <> 'inactive')";
                }
            } elseif ($target_audience === 'residents') {
                $where_clauses[] = 'r.id IS NOT NULL';
            } elseif ($target_audience !== '' && $target_audience !== 'all') {
                $where_clauses[] = 'r.id IS NOT NULL';
                $where_clauses[] = 'LOWER(TRIM(r.address)) = LOWER(TRIM(?))';
                $params[] = $target_audience;
            }

            $sql = "SELECT DISTINCT u.id AS user_id, u.fullname, u.email
                    FROM users u
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
         * Send email using Mailgun -> SendGrid -> SMTP -> native mail fallback.
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

            if (self::send_smtp_email($to, $name, $subject, $html_message)) {
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

        private static function has_valid_smtp_credentials($username, $password) {
            $username = trim((string) $username);
            $password = trim((string) $password);

            if ($username === '' || $password === '') {
                return false;
            }

            $invalid_usernames = ['your-email@gmail.com', 'example@gmail.com', 'your-email@example.com'];
            $invalid_passwords = ['your-app-password-here', 'your-16-character-app-password', 'changeme'];

            return !in_array(strtolower($username), $invalid_usernames, true)
                && !in_array(strtolower($password), $invalid_passwords, true);
        }

        private static function smtp_encryption_mode() {
            $secure = strtolower(trim((string) (defined('EMAIL_SMTP_SECURE') ? EMAIL_SMTP_SECURE : 'ssl')));

            if ($secure === 'tls' || $secure === 'starttls') {
                return \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
            }

            if ($secure === '' || $secure === 'none' || $secure === 'off' || $secure === 'false' || $secure === '0') {
                return false;
            }

            return \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_SMTPS;
        }

        private static function send_smtp_email($to, $name, $subject, $html_message) {
            $to = trim((string) $to);
            if ($to === '' || !filter_var($to, FILTER_VALIDATE_EMAIL)) {
                return false;
            }

            if (!file_exists(__DIR__ . '/../vendor/autoload.php')) {
                return false;
            }

            require_once __DIR__ . '/../vendor/autoload.php';

            if (!class_exists('PHPMailer\\PHPMailer\\PHPMailer')) {
                error_log('NotificationSystem SMTP transport unavailable: PHPMailer is not installed.');
                return false;
            }

            $smtp_host = defined('EMAIL_SMTP_HOST') ? trim((string) EMAIL_SMTP_HOST) : 'smtp.gmail.com';
            $smtp_port = defined('EMAIL_SMTP_PORT') ? (int) EMAIL_SMTP_PORT : 465;
            $smtp_username = defined('EMAIL_SMTP_USERNAME') ? trim((string) EMAIL_SMTP_USERNAME) : '';
            $smtp_password = defined('EMAIL_SMTP_PASSWORD') ? trim((string) EMAIL_SMTP_PASSWORD) : '';

            if (!self::has_valid_smtp_credentials($smtp_username, $smtp_password)) {
                return false;
            }

            $from_email = defined('EMAIL_FROM_EMAIL') && trim((string) EMAIL_FROM_EMAIL) !== ''
                ? trim((string) EMAIL_FROM_EMAIL)
                : $smtp_username;
            $from_name = defined('EMAIL_FROM_NAME') && trim((string) EMAIL_FROM_NAME) !== ''
                ? trim((string) EMAIL_FROM_NAME)
                : 'CommunaLink';

            $mail = new \PHPMailer\PHPMailer\PHPMailer(true);

            try {
                $mail->isSMTP();
                $mail->Host = $smtp_host;
                $mail->SMTPAuth = true;
                $mail->Username = $smtp_username;
                $mail->Password = $smtp_password;
                $mail->SMTPSecure = self::smtp_encryption_mode();
                $mail->Port = $smtp_port;
                $mail->Timeout = 20;
                $mail->CharSet = 'UTF-8';

                $mail->setFrom($from_email, $from_name);
                $mail->addAddress($to, trim((string) $name));

                $mail->isHTML(true);
                $mail->Subject = (string) $subject;
                $mail->Body = (string) $html_message;
                $mail->AltBody = trim(strip_tags((string) $html_message));

                return $mail->send();
            } catch (\Throwable $e) {
                $error_detail = method_exists($mail, 'getError') ? (string) $mail->getError() : '';
                if ($error_detail === '' && property_exists($mail, 'ErrorInfo')) {
                    $error_detail = (string) $mail->ErrorInfo;
                }
                error_log('NotificationSystem SMTP transport error: ' . ($error_detail !== '' ? $error_detail : $e->getMessage()));
                return false;
            }
        }

        private static function send_native_email($to, $subject, $html_message) {
            $from_email = defined('EMAIL_FROM_EMAIL') && trim((string) EMAIL_FROM_EMAIL) !== ''
                ? trim((string) EMAIL_FROM_EMAIL)
                : 'noreply@communalink.local';
            $from_name = defined('EMAIL_FROM_NAME') && trim((string) EMAIL_FROM_NAME) !== ''
                ? trim((string) EMAIL_FROM_NAME)
                : 'CommunaLink';

            $headers = "MIME-Version: 1.0\r\n";
            $headers .= "Content-type:text/html;charset=UTF-8\r\n";
            $headers .= "From: " . $from_name . " <" . $from_email . ">\r\n";

            return (bool) @mail($to, $subject, $html_message, $headers);
        }
    }
}
