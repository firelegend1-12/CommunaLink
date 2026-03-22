<?php
require_once '../config/database.php';
$page_title = "Announcements";
require_once 'partials/header.php';

try {
    $feed = [];

    // Fetch Announcements
        $stmt_a = $pdo->query("SELECT a.*, u.fullname as author_name
                                                     FROM announcements a
                                                     JOIN users u ON a.user_id = u.id
                                                     WHERE a.status = 'active'
                                                         AND (a.publish_date IS NULL OR a.publish_date <= NOW())
                                                         AND (a.expiry_date IS NULL OR a.expiry_date >= NOW())
                                                     ORDER BY a.created_at DESC");
    $announcements = $stmt_a->fetchAll(PDO::FETCH_ASSOC);
    foreach ($announcements as $a) {
        $feed[] = [
            'type' => 'announcement',
            'id' => $a['id'],
            'title' => $a['title'],
            'content' => $a['content'],
            'author_name' => $a['author_name'],
            'display_date' => $a['created_at'],
            'image_path' => $a['image_path'],
            'priority' => $a['priority'] ?? 'normal',
            'is_auto_generated' => $a['is_auto_generated'] ?? 0
        ];
    }

    // Fetch Events
    $stmt_e = $pdo->query("SELECT e.*, u.fullname as author_name FROM events e JOIN users u ON e.created_by = u.id");
    $events = $stmt_e->fetchAll(PDO::FETCH_ASSOC);
    foreach ($events as $e) {
        $feed[] = [
            'type' => 'event',
            'id' => $e['id'],
            'title' => $e['title'],
            'content' => $e['description'],
            'author_name' => $e['author_name'],
            'display_date' => $e['created_at'] ?? $e['event_date'],
            'event_date' => $e['event_date'],
            'event_time' => $e['event_time'],
            'location' => $e['location'],
            'event_type' => $e['type']
        ];
    }

    // Sort combined feed chronologically descending
    usort($feed, function($a, $b) {
        return strtotime($b['display_date']) - strtotime($a['display_date']);
    });

} catch (PDOException $e) {
    $feed = [];
}
?>

<style>
.announcements-container {
    max-width: 1200px;
    margin: 0 auto;
}
.page-header {
    margin-bottom: 30px;
    border-bottom: 2px solid var(--border-color);
    padding-bottom: 15px;
}
.page-header h1 {
    font-size: 2.2rem;
    font-weight: 700;
    color: var(--text-primary);
}
.page-header p {
    font-size: 1.1rem;
    color: var(--text-secondary);
    margin-top: 5px;
}
.announcement-card {
    background-color: var(--card-bg);
    border-radius: 12px;
    box-shadow: 0 5px 15px var(--shadow-color);
    margin-bottom: 30px;
    overflow: hidden;
    transition: all 0.3s ease;
}
.announcement-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 8px 25px rgba(0,0,0,0.1);
}
.announcement-image {
    width: 100%;
    height: 300px;
    object-fit: cover;
}
.announcement-content {
    padding: 30px;
}
.announcement-title {
    font-size: 1.6rem;
    font-weight: 600;
    margin-bottom: 10px;
}
.announcement-meta {
    font-size: 0.9rem;
    color: var(--text-secondary);
    margin-bottom: 20px;
}
.announcement-meta i {
    margin-right: 5px;
}
.announcement-body {
    font-size: 1rem;
    line-height: 1.6;
    color: var(--text-primary);
}
.no-announcements {
    text-align: center;
    padding: 50px;
    background-color: var(--card-bg);
    border-radius: 12px;
}
</style>

<div class="announcements-container">
    <div class="page-header">
        <h1><i class="fas fa-bullhorn"></i> Barangay Announcements</h1>
        <p>Stay updated with the latest news and announcements from your barangay officials.</p>
    </div>

    <?php if (empty($feed)): ?>
        <div class="no-announcements">
            <h2>No announcements or events at this time.</h2>
            <p>Please check back later for updates.</p>
        </div>
    <?php else: ?>
        <?php foreach ($feed as $item): ?>
            <?php if ($item['type'] === 'announcement'): ?>
                <article class="announcement-card <?= $item['priority'] === 'urgent' ? 'border-l-4 border-red-500' : '' ?>">
                    <?php if ($item['image_path']): ?>
                        <img src="../admin/<?= htmlspecialchars($item['image_path']) ?>" alt="<?= htmlspecialchars($item['title']) ?>" class="announcement-image">
                    <?php endif; ?>
                    <div class="announcement-content">
                        <h2 class="announcement-title">
                            <?= htmlspecialchars($item['title']) ?>
                            <?php if ($item['priority'] === 'urgent'): ?>
                                <span class="ml-2 text-xs font-bold bg-red-100 text-red-600 px-2 py-1 rounded-full uppercase">Urgent</span>
                            <?php endif; ?>
                        </h2>
                        <div class="announcement-meta">
                            <span><i class="fas fa-bullhorn text-blue-500"></i> Announcement</span> | 
                            <span><i class="fas fa-user"></i> Posted by <?= htmlspecialchars($item['author_name']) ?></span> | 
                            <span><i class="fas fa-clock"></i> <?= date('F j, Y, g:i a', strtotime($item['display_date'])) ?></span>
                        </div>
                        <div class="announcement-body">
                            <?= nl2br(htmlspecialchars($item['content'])) ?>
                        </div>
                    </div>
                </article>
            <?php else: ?>
                <article class="announcement-card border-l-4 border-green-500">
                    <div class="announcement-content bg-green-50">
                        <h2 class="announcement-title text-green-800">
                            <?= htmlspecialchars($item['title']) ?>
                            <span class="ml-2 text-xs font-bold bg-green-200 text-green-800 px-2 py-1 rounded-full uppercase"><?= htmlspecialchars($item['event_type']) ?></span>
                        </h2>
                        
                        <div class="announcement-meta text-green-700">
                            <span><i class="fas fa-calendar-alt"></i> Event</span> | 
                            <span><i class="fas fa-user"></i> Posted by <?= htmlspecialchars($item['author_name']) ?></span> | 
                            <span><i class="fas fa-clock"></i> Posted on <?= date('M j, Y', strtotime($item['display_date'])) ?></span>
                        </div>
                        
                        <div class="mb-4 bg-white p-4 rounded-lg shadow-sm border border-green-100 flex flex-col sm:flex-row sm:space-x-6 space-y-2 sm:space-y-0 text-sm text-gray-700">
                            <div class="flex items-center">
                                <i class="fas fa-calendar-day text-green-600 mr-2"></i>
                                <span><strong>Date:</strong> <?= date('F j, Y', strtotime($item['event_date'])) ?></span>
                            </div>
                            <div class="flex items-center">
                                <i class="fas fa-clock text-green-600 mr-2"></i>
                                <span><strong>Time:</strong> <?= date('g:i A', strtotime($item['event_time'])) ?></span>
                            </div>
                            <div class="flex items-center">
                                <i class="fas fa-map-marker-alt text-green-600 mr-2"></i>
                                <span><strong>Location:</strong> <?= htmlspecialchars($item['location']) ?></span>
                            </div>
                        </div>

                        <div class="announcement-body">
                            <?= nl2br(htmlspecialchars($item['content'])) ?>
                        </div>
                    </div>
                </article>
            <?php endif; ?>
        <?php endforeach; ?>
    <?php endif; ?>
</div>

<?php require_once 'partials/footer.php'; ?> 