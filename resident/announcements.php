<?php
require_once '../config/database.php';
$page_title = "Announcements";
require_once 'partials/header.php';

try {
    $stmt = $pdo->query("SELECT a.*, u.fullname as author_name FROM announcements a JOIN users u ON a.user_id = u.id ORDER BY a.created_at DESC");
    $announcements = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $announcements = [];
    // You could set an error message to display to the user
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

    <?php if (empty($announcements)): ?>
        <div class="no-announcements">
            <h2>No announcements at this time.</h2>
            <p>Please check back later for updates.</p>
        </div>
    <?php else: ?>
        <?php foreach ($announcements as $ann): ?>
            <article class="announcement-card">
                <?php if ($ann['image_path']): ?>
                    <img src="../admin/<?= htmlspecialchars($ann['image_path']) ?>" alt="<?= htmlspecialchars($ann['title']) ?>" class="announcement-image">
                <?php endif; ?>
                <div class="announcement-content">
                    <h2 class="announcement-title"><?= htmlspecialchars($ann['title']) ?></h2>
                    <div class="announcement-meta">
                        <span><i class="fas fa-user"></i> Posted by <?= htmlspecialchars($ann['author_name']) ?></span> | 
                        <span><i class="fas fa-calendar-alt"></i> <?= date('F j, Y, g:i a', strtotime($ann['created_at'])) ?></span>
                    </div>
                    <div class="announcement-body">
                        <?= nl2br(htmlspecialchars($ann['content'])) ?>
                    </div>
                </div>
            </article>
        <?php endforeach; ?>
    <?php endif; ?>
</div>

<?php require_once 'partials/footer.php'; ?> 