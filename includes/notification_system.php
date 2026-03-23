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
                    . '<p>Thank you.<br>CommuniLink Barangay Office</p>';

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
            if ($status === 'Ready for Pickup') {
                $message .= 'Please visit the Barangay Hall to claim your document.';
            } elseif ($status === 'Rejected') {
                $message .= 'Please contact the office for more details.';
            }

            return create_notification($pdo, $recipient_user_id, $title, $message, 'request_status', $link);
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
            $from_name = defined('MAILGUN_FROM_NAME') ? MAILGUN_FROM_NAME : 'CommuniLink';

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
            $from_name = defined('SENDGRID_FROM_NAME') ? SENDGRID_FROM_NAME : 'CommuniLink';

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
                'Authorization: Bearer ' . SENDGRID_API_KEY,
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
            $headers .= "From: CommuniLink <noreply@communalink.local>\r\n";

            return (bool) @mail($to, $subject, $html_message, $headers);
        }
    }
}
