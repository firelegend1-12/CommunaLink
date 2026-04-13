<?php
require_once '../config/database.php';
$page_title = "Events";
require_once 'partials/header.php';

try {
        // Fetch Upcoming Events from both legacy events and unified announcements/events posts.
        $stmt_upcoming = $pdo->prepare(
                "SELECT combined.title, combined.description, combined.event_date, combined.event_time, combined.location
                 FROM (
                         SELECT e.title, e.description, e.event_date, e.event_time, e.location, e.created_at
                         FROM events e
                         WHERE e.type = 'Upcoming Event'
                             AND e.event_date >= CURDATE()

                         UNION ALL

                         SELECT a.title, a.content AS description, a.event_date, a.event_time, a.event_location AS location, a.created_at
                         FROM announcements a
                         WHERE a.is_event = 1
                             AND a.status = 'active'
                             AND (a.publish_date IS NULL OR a.publish_date <= NOW())
                             AND (a.expiry_date IS NULL OR a.expiry_date >= NOW())
                             AND COALESCE(NULLIF(a.event_type, ''), 'Upcoming Event') <> 'Regular Activity'
                             AND a.event_date >= CURDATE()
                 ) combined
                 ORDER BY combined.event_date ASC,
                                    COALESCE(combined.event_time, '00:00:00') ASC,
                                    combined.created_at DESC"
        );
    $stmt_upcoming->execute();
    $upcoming_events = $stmt_upcoming->fetchAll(PDO::FETCH_ASSOC);

        // Fetch Regular Activities from both sources.
        $stmt_regular = $pdo->prepare(
                "SELECT combined.title, combined.description, combined.event_date, combined.event_time, combined.location
                 FROM (
                         SELECT e.title, e.description, e.event_date, e.event_time, e.location, e.created_at
                         FROM events e
                         WHERE e.type = 'Regular Activity'

                         UNION ALL

                         SELECT a.title, a.content AS description, a.event_date, a.event_time, a.event_location AS location, a.created_at
                         FROM announcements a
                         WHERE a.is_event = 1
                             AND a.status = 'active'
                             AND (a.publish_date IS NULL OR a.publish_date <= NOW())
                             AND (a.expiry_date IS NULL OR a.expiry_date >= NOW())
                             AND COALESCE(NULLIF(a.event_type, ''), 'Upcoming Event') = 'Regular Activity'
                 ) combined
                 ORDER BY COALESCE(combined.event_date, '1900-01-01') DESC,
                                    COALESCE(combined.event_time, '00:00:00') DESC,
                                    combined.created_at DESC"
        );
    $stmt_regular->execute();
    $regular_activities = $stmt_regular->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    $upcoming_events = [];
    $regular_activities = [];
    // You could set an error message to display to the user
}
?>

<style>
.events-container {
    max-width: 1200px;
    margin: 0 auto;
    padding: 20px;
}

.event-section {
    background-color: #fff;
    border-radius: 12px;
    box-shadow: 0 4px 12px rgba(0,0,0,0.08);
    margin-bottom: 40px;
}

.section-header {
    padding: 20px 25px;
    border-bottom: 1px solid #eef2f7;
    display: flex;
    align-items: center;
}

.section-header i {
    font-size: 1.5rem;
    color: #4a5568;
    margin-right: 15px;
}

.section-header h2 {
    font-size: 1.5rem;
    font-weight: 600;
    color: #2d3748;
    margin: 0;
}

.section-content {
    padding: 25px;
}

.event-card {
    border: 1px solid #eef2f7;
    border-radius: 10px;
    padding: 20px;
    margin-bottom: 20px;
    background-color: #f9fafb;
}

.event-card:last-child {
    margin-bottom: 0;
}

.event-title {
    font-size: 1.25rem;
    font-weight: 600;
    margin-bottom: 8px;
}

.event-meta {
    display: flex;
    flex-wrap: wrap;
    gap: 20px;
    font-size: 0.9rem;
    color: #718096;
    margin-bottom: 15px;
}

.event-meta span {
    display: flex;
    align-items: center;
}

.event-meta i {
    margin-right: 8px;
    width: 16px;
    text-align: center;
}

.event-description {
    font-size: 1rem;
    line-height: 1.6;
    color: #4a5568;
}

.no-events {
    text-align: center;
    padding: 40px;
    color: #718096;
}
.no-events i {
    font-size: 3rem;
    margin-bottom: 15px;
    color: #cbd5e0;
}
.no-events h3 {
    font-size: 1.2rem;
    font-weight: 500;
    margin: 0;
}
.no-events p {
    margin-top: 5px;
}

.info-box {
    background-color: #e6f7ff;
    border-left: 5px solid #1890ff;
    padding: 20px;
    border-radius: 8px;
}
.info-box h4 {
    margin-top: 0;
    font-size: 1.1rem;
    font-weight: 600;
    color: #0050b3;
}
.info-box ul {
    padding-left: 20px;
    margin-bottom: 10px;
}
.info-box li {
    margin-bottom: 5px;
}
.info-box p {
    margin-bottom: 0;
}

@media (max-width: 767px) {
    .events-container {
        padding: 8px;
    }
    .section-header h2 {
        font-size: 1.2rem;
    }
    .section-content {
        padding: 16px;
    }
    .event-card {
        padding: 14px;
    }
    .event-title {
        font-size: 1.05rem;
    }
    .event-meta {
        flex-direction: column;
        gap: 6px;
        font-size: 0.85rem;
    }
    .event-description {
        font-size: 0.9rem;
    }
    .info-box {
        padding: 14px;
    }
}

</style>

<div class="events-container">

    <!-- Upcoming Events -->
    <div class="event-section">
        <div class="section-header">
            <i class="fas fa-calendar-plus" style="color: #38a169;"></i>
            <h2>Upcoming Events</h2>
        </div>
        <div class="section-content">
            <?php if (empty($upcoming_events)): ?>
                <div class="no-events">
                    <i class="far fa-calendar-times"></i>
                    <h3>No Upcoming Events</h3>
                    <p>There are no upcoming events scheduled at the moment.</p>
                </div>
            <?php else: ?>
                <?php foreach ($upcoming_events as $event): ?>
                    <div class="event-card">
                        <h3 class="event-title"><?= htmlspecialchars($event['title']) ?></h3>
                        <div class="event-meta">
                            <span><i class="fas fa-calendar-alt"></i> <?= date('F j, Y', strtotime($event['event_date'])) ?></span>
                            <?php if (!empty($event['event_time'])): ?>
                                <span><i class="fas fa-clock"></i> <?= date('g:i A', strtotime($event['event_time'])) ?></span>
                            <?php endif; ?>
                            <?php if (!empty($event['location'])): ?>
                                <span><i class="fas fa-map-marker-alt"></i> <?= htmlspecialchars($event['location']) ?></span>
                            <?php endif; ?>
                        </div>
                        <p class="event-description"><?= nl2br(htmlspecialchars((string) ($event['description'] ?? ''))) ?></p>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>

    <!-- Regular Activities -->
    <div class="event-section">
        <div class="section-header">
            <i class="fas fa-calendar-check" style="color: #4299e1;"></i>
            <h2>Regular Activities</h2>
        </div>
        <div class="section-content">
            <?php if (empty($regular_activities)): ?>
                <div class="no-events">
                    <i class="far fa-calendar-times"></i>
                    <h3>No Regular Activities</h3>
                    <p>No regular activities are currently scheduled.</p>
                </div>
            <?php else: ?>
                 <?php foreach ($regular_activities as $event): ?>
                    <div class="event-card">
                        <h3 class="event-title"><?= htmlspecialchars($event['title']) ?></h3>
                        <div class="event-meta">
                           <?php if ($event['event_date']): ?>
                                <span><i class="fas fa-calendar-alt"></i> <?= date('F j, Y', strtotime($event['event_date'])) ?></span>
                           <?php endif; ?>
                           <?php if ($event['event_time']): ?>
                                <span><i class="fas fa-clock"></i> <?= date('g:i A', strtotime($event['event_time'])) ?></span>
                           <?php endif; ?>
                           <?php if ($event['location']): ?>
                                <span><i class="fas fa-map-marker-alt"></i> <?= htmlspecialchars($event['location']) ?></span>
                           <?php endif; ?>
                        </div>
                        <p class="event-description"><?= nl2br(htmlspecialchars((string) ($event['description'] ?? ''))) ?></p>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>

    <!-- Event Registration -->
    <div class="event-section">
        <div class="section-header">
            <i class="fas fa-clipboard-list"></i>
            <h2>Event Registration</h2>
        </div>
        <div class="section-content">
            <div class="info-box">
                <h4><i class="fas fa-info-circle"></i> How to Register for Events</h4>
                <p>For events that require registration, please contact the barangay office during business hours:</p>
                <ul>
                    <li>Visit the barangay office in person</li>
                    <li>Call: +63 2 8123 4567</li>
                    <li>Email: events@barangaymasigasig.gov.ph</li>
                </ul>
                <p><strong>Note:</strong> Some events have limited slots and are available on a first-come, first-served basis.</p>
            </div>
        </div>
    </div>

</div>

<?php require_once 'partials/footer.php'; ?> 