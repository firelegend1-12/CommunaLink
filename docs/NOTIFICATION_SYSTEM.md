# 🔔 Notification System Documentation

## Overview

The CommuniLink Notification System provides comprehensive notification capabilities including email, in-app, SMS, and push notifications. It's designed to be scalable, configurable, and easy to integrate with existing system events.

## Features

### ✅ **Notification Types**
- **Email Notifications** - HTML-formatted emails with templates
- **In-App Notifications** - Real-time notifications within the application
- **SMS Notifications** - Text message notifications (Twilio integration ready)
- **Push Notifications** - Mobile push notifications (Firebase integration ready)

### ✅ **Notification Categories**
- **Document Updates** - Status changes, approvals, rejections
- **Incident Reports** - New reports, status updates, resolutions
- **System Messages** - Maintenance, updates, welcome messages
- **Urgent Alerts** - Critical notifications requiring immediate attention
- **Reminders** - Scheduled reminders and notifications

### ✅ **Priority Levels**
- **Low** - Informational notifications
- **Normal** - Standard notifications
- **High** - Important notifications
- **Urgent** - Critical notifications

### ✅ **User Preferences**
- Granular control over notification types and categories
- Per-user customization
- Default preferences for new users

## System Architecture

```
┌─────────────────┐    ┌──────────────────┐    ┌─────────────────┐
│   Event Source │───▶│ Notification     │───▶│   Delivery      │
│   (System)     │    │   Triggers       │    │   Methods       │
└─────────────────┘    └──────────────────┘    └─────────────────┘
                                │                        │
                                ▼                        ▼
                       ┌──────────────────┐    ┌─────────────────┐
                       │ Notification     │    │   User          │
                       │   System        │    │   Preferences   │
                       └──────────────────┘    └─────────────────┘
```

## Database Schema

### Tables Created

1. **`notifications`** - Main notifications table
2. **`notification_templates`** - Email and message templates
3. **`notification_preferences`** - User notification preferences

### Key Fields

- `user_id` - Target user
- `type` - Notification type (email, in_app, sms, push)
- `category` - Notification category
- `priority` - Priority level
- `title` - Notification title
- `message` - Notification content
- `data` - JSON metadata
- `read_at` - When notification was read
- `delivery_status` - Delivery status tracking

## Quick Start

### 1. Initialize the System

```php
require_once 'includes/notification_system.php';

// Initialize with default settings
init_notification_system($pdo);

// Or with custom configuration
init_notification_system($pdo, [
    'email_enabled' => true,
    'smtp_host' => 'your-smtp-server.com',
    'smtp_username' => 'your-username',
    'smtp_password' => 'your-password'
]);
```

### 2. Send a Simple Notification

```php
// Basic notification
send_notification(
    $user_id,           // Target user ID
    'in_app',           // Notification type
    'document',          // Category
    'Document Updated',  // Title
    'Your document has been approved', // Message
    [],                 // Additional data
    'normal'            // Priority
);
```

### 3. Use Pre-built Triggers

```php
require_once 'includes/notification_triggers.php';

// Document status change
notify_document_status_change($user_id, 'Barangay Clearance', 'approved');

// Incident report
notify_incident_reported($user_id, 'Traffic', 'Main Street');

// Welcome message
notify_welcome_user($user_id, 'John Doe', 'resident');
```

## Integration Examples

### Document Management System

```php
// When document status changes
function updateDocumentStatus($document_id, $new_status) {
    // ... update document logic ...
    
    // Send notification
    $user_id = getDocumentOwner($document_id);
    $document_type = getDocumentType($document_id);
    
    notify_document_status_change($user_id, $document_type, $new_status, $document_id);
}
```

### Incident Reporting System

```php
// When incident is submitted
function submitIncident($incident_data) {
    // ... save incident logic ...
    
    // Send confirmation
    notify_incident_reported(
        $incident_data['user_id'],
        $incident_data['type'],
        $incident_data['location'],
        $incident_id
    );
}

// When incident status changes
function updateIncidentStatus($incident_id, $new_status) {
    // ... update incident logic ...
    
    // Send status update
    $user_id = getIncidentReporter($incident_id);
    $incident_type = getIncidentType($incident_id);
    
    notify_incident_status_change($user_id, $incident_type, $new_status, $incident_id);
}
```

### User Management System

```php
// When new user is created
function createUser($user_data) {
    // ... create user logic ...
    
    // Send welcome notification
    notify_welcome_user($user_id, $user_data['fullname'], $user_data['role']);
}

// When account is updated
function updateUser($user_id, $changes) {
    // ... update user logic ...
    
    // Send account update notification
    notify_account_change($user_id, 'Profile Updated', 'Your profile information has been updated.');
}
```

## Configuration

### Email Settings

```php
// In config/notifications.php
'email' => [
    'enabled' => true,
    'smtp' => [
        'host' => 'smtp.gmail.com',
        'port' => 587,
        'username' => 'your-email@gmail.com',
        'password' => 'your-app-password',
        'encryption' => 'tls',
        'from_email' => 'noreply@communalink.com',
        'from_name' => 'CommuniLink System'
    ]
]
```

### SMS Settings (Twilio)

```php
'sms' => [
    'enabled' => true,
    'provider' => 'twilio',
    'api_key' => 'your-twilio-account-sid',
    'api_secret' => 'your-twilio-auth-token',
    'from_number' => '+1234567890'
]
```

### Push Notifications (Firebase)

```php
'push' => [
    'enabled' => true,
    'service' => 'firebase',
    'api_key' => 'your-firebase-server-key',
    'project_id' => 'your-firebase-project-id'
]
```

## User Interface

### Notification Center

The notification center provides a dropdown interface showing:
- Recent notifications
- Unread count badge
- Mark as read functionality
- Notification preferences

### Notification Preferences Page

Users can customize:
- Which notification types to receive
- Which categories to receive
- Default settings for new categories

## API Endpoints

### Get Notifications
```
GET /api/notifications.php?action=get_notifications&limit=10&offset=0
```

### Mark as Read
```
POST /api/notifications.php
{
    "action": "mark_read",
    "notification_id": 123
}
```

### Mark All as Read
```
POST /api/notifications.php
{
    "action": "mark_all_read"
}
```

### Update Preferences
```
POST /api/notifications.php
{
    "action": "update_preferences",
    "preferences": {
        "email": {
            "document": true,
            "incident": false
        }
    }
}
```

## Testing

### Test Script

Use `test_notifications.php` to test the system:

1. **Document Notifications** - Test document status updates
2. **Incident Notifications** - Test incident reporting
3. **System Notifications** - Test system messages
4. **Urgent Notifications** - Test critical alerts

### Manual Testing

```php
// Test email notification
send_notification(1, 'email', 'system', 'Test', 'This is a test email');

// Test SMS notification
send_notification(1, 'sms', 'urgent', 'Test SMS', 'This is a test SMS');

// Test push notification
send_notification(1, 'push', 'document', 'Test Push', 'This is a test push notification');
```

## Maintenance

### Cleanup Old Notifications

```php
// Clean up notifications older than 90 days
NotificationSystem::cleanupOldNotifications(90);
```

### Performance Monitoring

```php
// Get notification statistics
$stats = NotificationSystem::getPerformanceMetrics();

// Monitor delivery rates
$delivery_stats = NotificationSystem::getDeliveryStats();
```

## Security Considerations

### Rate Limiting

- Built-in rate limiting for notification sending
- Configurable limits per hour/day
- Cooldown periods for excessive usage

### User Preferences

- Users control their own notification preferences
- No notifications sent without user consent
- Granular control over notification types

### Data Privacy

- Notification data is stored securely
- Personal information is not exposed in notifications
- Automatic cleanup of old notifications

## Troubleshooting

### Common Issues

1. **Notifications not sending**
   - Check database connection
   - Verify user preferences
   - Check email/SMS configuration

2. **Email delivery issues**
   - Verify SMTP settings
   - Check server firewall
   - Test with simple mail() function

3. **Performance issues**
   - Monitor database query performance
   - Check notification cleanup schedule
   - Optimize notification triggers

### Debug Mode

Enable debug logging:

```php
// In notification system initialization
init_notification_system($pdo, [
    'debug' => true,
    'log_level' => 'debug'
]);
```

## Future Enhancements

### Planned Features

1. **Advanced Scheduling** - Time-based notification delivery
2. **Template Engine** - Dynamic notification templates
3. **Analytics Dashboard** - Notification performance metrics
4. **Mobile App Integration** - Native mobile notifications
5. **Webhook Support** - External system integration

### Customization

The system is designed to be easily extensible:

- Add new notification types
- Create custom notification categories
- Implement custom delivery methods
- Add notification workflows

## Support

For technical support or questions about the notification system:

1. Check the test script (`test_notifications.php`)
2. Review the database schema
3. Check error logs for specific issues
4. Verify configuration settings

---

**Last Updated:** December 2024  
**Version:** 1.0.0  
**System:** CommuniLink Barangay Management System







