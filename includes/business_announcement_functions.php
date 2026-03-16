<?php
/**
 * Business Announcement Functions
 * Handles automatic generation of business-related announcements
 */

require_once __DIR__ . '/../config/init.php';
require_once __DIR__ . '/functions.php';

/**
 * Create an announcement with template processing
 */
function createBusinessAnnouncement($template_name, $data = [], $user_id = null) {
    global $pdo;
    
    try {
        // Get template
        $stmt = $pdo->prepare("SELECT * FROM announcement_templates WHERE name = ? AND is_active = 1");
        $stmt->execute([$template_name]);
        $template = $stmt->fetch();
        
        if (!$template) {
            throw new Exception("Template '$template_name' not found");
        }
        
        // Process template with data
        $title = processTemplate($template['title_template'], $data);
        $content = processTemplate($template['content_template'], $data);
        
        // Create announcement
        $stmt = $pdo->prepare("
            INSERT INTO announcements (
                title, content, category, priority, target_audience, 
                user_id, is_auto_generated, related_business_id, 
                related_permit_number, status, created_at
            ) VALUES (?, ?, ?, ?, ?, ?, 1, ?, ?, 'active', NOW())
        ");
        
        $stmt->execute([
            $title,
            $content,
            $template['category'],
            $template['priority'],
            $template['target_audience'],
            $user_id ?? $_SESSION['user_id'] ?? 1,
            $data['business_id'] ?? null,
            $data['permit_number'] ?? null
        ]);
        
        $announcement_id = $pdo->lastInsertId();
        
        // Log the auto-generated announcement
        log_activity_db(
            $pdo,
            'create_announcement',
            'announcement',
            $announcement_id,
            "Auto-generated business announcement: $template_name",
            null,
            null
        );
        
        return $announcement_id;
        
    } catch (Exception $e) {
        error_log("Error creating business announcement: " . $e->getMessage());
        return false;
    }
}

/**
 * Process template with data placeholders
 */
function processTemplate($template, $data) {
    $processed = $template;
    
    foreach ($data as $key => $value) {
        $placeholder = '{' . $key . '}';
        $processed = str_replace($placeholder, $value, $processed);
    }
    
    return $processed;
}

/**
 * Check for expiring permits and create reminders
 */
function checkExpiringPermits() {
    global $pdo;
    
    try {
        // Check permits expiring in 30 days
        $stmt = $pdo->query("
            SELECT COUNT(*) as count, 
                   MIN(permit_expiration_date) as earliest_expiry
            FROM businesses 
            WHERE permit_expiration_date = DATE_ADD(CURDATE(), INTERVAL 30 DAY)
            AND status = 'Active'
        ");
        $expiring_30_days = $stmt->fetch();
        
        if ($expiring_30_days['count'] > 0) {
            createBusinessAnnouncement('Business Permit Renewal Reminder', [
                'business_count' => $expiring_30_days['count'],
                'expiry_date' => date('M d, Y', strtotime($expiring_30_days['earliest_expiry']))
            ]);
        }
        
        // Check permits expiring in 7 days (urgent)
        $stmt = $pdo->query("
            SELECT COUNT(*) as count
            FROM businesses 
            WHERE permit_expiration_date = DATE_ADD(CURDATE(), INTERVAL 7 DAY)
            AND status = 'Active'
        ");
        $expiring_7_days = $stmt->fetch();
        
        if ($expiring_7_days['count'] > 0) {
            createBusinessAnnouncement('Urgent Permit Expiry', [
                'business_count' => $expiring_7_days['count'],
                'days_remaining' => '7'
            ]);
        }
        
        // Check permits expiring in 1 day (final warning)
        $stmt = $pdo->query("
            SELECT COUNT(*) as count
            FROM businesses 
            WHERE permit_expiration_date = DATE_ADD(CURDATE(), INTERVAL 1 DAY)
            AND status = 'Active'
        ");
        $expiring_1_day = $stmt->fetch();
        
        if ($expiring_1_day['count'] > 0) {
            createBusinessAnnouncement('Urgent Permit Expiry', [
                'business_count' => $expiring_1_day['count'],
                'days_remaining' => '1'
            ]);
        }
        
        return true;
        
    } catch (Exception $e) {
        error_log("Error checking expiring permits: " . $e->getMessage());
        return false;
    }
}

/**
 * Create welcome announcement for new business
 */
function createNewBusinessAnnouncement($business_id, $permit_number, $business_name, $expiry_date) {
    return createBusinessAnnouncement('New Business Welcome', [
        'business_id' => $business_id,
        'business_name' => $business_name,
        'permit_number' => $permit_number,
        'expiry_date' => date('M d, Y', strtotime($expiry_date))
    ]);
}

/**
 * Generate monthly compliance report
 */
function generateMonthlyComplianceReport() {
    global $pdo;
    
    try {
        $stmt = $pdo->query("
            SELECT 
                COUNT(*) as total_businesses,
                COUNT(CASE WHEN permit_expiration_date > CURDATE() THEN 1 END) as valid_permits,
                COUNT(CASE WHEN permit_expiration_date <= CURDATE() THEN 1 END) as expired_permits,
                COUNT(CASE WHEN permit_expiration_date <= DATE_ADD(CURDATE(), INTERVAL 30 DAY) THEN 1 END) as expiring_soon
            FROM businesses 
            WHERE status = 'Active'
        ");
        $stats = $stmt->fetch();
        
        $compliance_rate = $stats['total_businesses'] > 0 
            ? round(($stats['valid_permits'] / $stats['total_businesses']) * 100, 1)
            : 0;
        
        return createBusinessAnnouncement('Monthly Compliance Report', [
            'month_year' => date('F Y'),
            'total_businesses' => $stats['total_businesses'],
            'valid_permits' => $stats['valid_permits'],
            'expired_permits' => $stats['expired_permits'],
            'expiring_soon' => $stats['expiring_soon'],
            'compliance_rate' => $compliance_rate
        ]);
        
    } catch (Exception $e) {
        error_log("Error generating compliance report: " . $e->getMessage());
        return false;
    }
}

/**
 * Get business owners for targeted announcements
 */
function getBusinessOwners($target_audience = 'business_owners') {
    global $pdo;
    
    try {
        switch ($target_audience) {
            case 'business_owners':
                $sql = "
                    SELECT DISTINCT r.id, r.first_name, r.last_name, r.email, r.contact_no
                    FROM residents r
                    JOIN businesses b ON r.id = b.resident_id
                    WHERE b.status = 'Active'
                ";
                break;
                
            case 'expiring_permits':
                $sql = "
                    SELECT DISTINCT r.id, r.first_name, r.last_name, r.email, r.contact_no
                    FROM residents r
                    JOIN businesses b ON r.id = b.resident_id
                    WHERE b.status = 'Active'
                    AND b.permit_expiration_date <= DATE_ADD(CURDATE(), INTERVAL 30 DAY)
                ";
                break;
                
            case 'new_businesses':
                $sql = "
                    SELECT DISTINCT r.id, r.first_name, r.last_name, r.email, r.contact_no
                    FROM residents r
                    JOIN businesses b ON r.id = b.resident_id
                    WHERE b.status = 'Active'
                    AND b.approval_date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
                ";
                break;
                
            default:
                return [];
        }
        
        $stmt = $pdo->query($sql);
        return $stmt->fetchAll();
        
    } catch (Exception $e) {
        error_log("Error getting business owners: " . $e->getMessage());
        return [];
    }
}

/**
 * Mark announcement as read by resident
 */
function markAnnouncementAsRead($announcement_id, $resident_id) {
    global $pdo;
    
    try {
        // Check if already read
        $stmt = $pdo->prepare("SELECT id FROM announcement_reads WHERE announcement_id = ? AND resident_id = ?");
        $stmt->execute([$announcement_id, $resident_id]);
        
        if ($stmt->rowCount() === 0) {
            // Mark as read
            $stmt = $pdo->prepare("INSERT INTO announcement_reads (announcement_id, resident_id) VALUES (?, ?)");
            $stmt->execute([$announcement_id, $resident_id]);
            
            // Update read count
            $stmt = $pdo->prepare("UPDATE announcements SET read_count = read_count + 1 WHERE id = ?");
            $stmt->execute([$announcement_id]);
        }
        
        return true;
        
    } catch (Exception $e) {
        error_log("Error marking announcement as read: " . $e->getMessage());
        return false;
    }
}

/**
 * Get unread announcements for resident
 */
function getUnreadAnnouncements($resident_id) {
    global $pdo;
    
    try {
        $stmt = $pdo->prepare("
            SELECT a.*, u.fullname as author_name
            FROM announcements a
            JOIN users u ON a.user_id = u.id
            WHERE a.status = 'active'
            AND (a.publish_date IS NULL OR a.publish_date <= NOW())
            AND (a.expiry_date IS NULL OR a.expiry_date >= NOW())
            AND a.id NOT IN (
                SELECT announcement_id 
                FROM announcement_reads 
                WHERE resident_id = ?
            )
            ORDER BY a.priority DESC, a.created_at DESC
        ");
        $stmt->execute([$resident_id]);
        return $stmt->fetchAll();
        
    } catch (Exception $e) {
        error_log("Error getting unread announcements: " . $e->getMessage());
        return [];
    }
}

/**
 * Auto-generate business announcements based on triggers
 */
function autoGenerateBusinessAnnouncements() {
    // Check expiring permits daily
    checkExpiringPermits();
    
    // Generate monthly compliance report on the 1st of each month
    if (date('j') === '1') {
        generateMonthlyComplianceReport();
    }
}

/**
 * Get announcement statistics
 */
function getAnnouncementStats() {
    global $pdo;
    
    try {
        $stmt = $pdo->query("
            SELECT 
                COUNT(*) as total_announcements,
                COUNT(CASE WHEN status = 'active' THEN 1 END) as active_announcements,
                COUNT(CASE WHEN status = 'scheduled' THEN 1 END) as scheduled_announcements,
                COUNT(CASE WHEN is_auto_generated = 1 THEN 1 END) as auto_generated,
                COUNT(CASE WHEN category = 'business' THEN 1 END) as business_announcements,
                COUNT(CASE WHEN priority = 'urgent' THEN 1 END) as urgent_announcements
            FROM announcements
        ");
        return $stmt->fetch();
        
    } catch (Exception $e) {
        error_log("Error getting announcement stats: " . $e->getMessage());
        return [];
    }
}
?> 