<?php
require_once '../config/database.php';
$page_title = "Community Board";
require_once 'partials/header.php';
$post_reaction_csrf_token = csrf_token();

// Ensure user is logged in (should be handled by header/middleware)
$user_id = $_SESSION['user_id'] ?? null;
$user_role = $_SESSION['role'] ?? 'resident';
// Assuming purok is stored in session or user record. 
// For this implementation, we'll try to fetch it if not in session.
$user_purok = $_SESSION['purok'] ?? null;

try {
    // 1. Fetch current user context for targeting
    $stmt_context = $pdo->prepare("
        SELECT u.role, r.address 
        FROM users u 
        LEFT JOIN residents r ON u.id = r.user_id 
        WHERE u.id = ?
    ");
    $stmt_context->execute([$user_id]);
    $user_context = $stmt_context->fetch(PDO::FETCH_ASSOC);
    
    $user_role = $user_context['role'] ?? 'resident';
    $user_address = $user_context['address'] ?? null;

    // 2. Fetch Unified Posts (Announcements & Events)
    // Respect Status, Scheduling, Expiry, and Target Audience
    $target_queries = ["'all'"];
    if ($user_role === 'resident') $target_queries[] = "'residents'";
    if ($user_role === 'business_owner' || $user_role === 'admin') $target_queries[] = "'business'";
    
    if ($user_address) {
        $target_queries[] = $pdo->quote($user_address);
    }
    
    $target_sql = implode(',', $target_queries);

    $sql = "SELECT a.*, u.fullname as author_name,
            (SELECT COUNT(*) FROM post_reactions WHERE post_id = a.id AND reaction_type = 'like') as like_count,
            (SELECT COUNT(*) FROM post_reactions WHERE post_id = a.id AND reaction_type = 'acknowledge') as ack_count,
            (SELECT reaction_type FROM post_reactions WHERE post_id = a.id AND resident_id = ?) as my_reaction
            FROM announcements a
            JOIN users u ON a.user_id = u.id
            WHERE a.status = 'active'
            AND (a.publish_date IS NULL OR a.publish_date <= NOW())
            AND (a.expiry_date IS NULL OR a.expiry_date >= NOW())
            AND (a.target_audience IN ($target_sql))
            ORDER BY a.publish_date DESC, a.created_at DESC";
            
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$user_id]);
    $feed = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    $feed = [];
    $error = $e->getMessage();
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

<style>
/* Previous styles... (omitted for brevity in replace_file_content target) */
.reaction-btn {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    padding: 8px 16px;
    border-radius: 9999px;
    font-size: 0.875rem;
    font-weight: 600;
    transition: all 0.2s;
    border: 1px solid var(--border-color);
}
.reaction-btn:hover {
    background-color: var(--bg-secondary);
}
.reaction-btn.active {
    background-color: #EEF2FF;
    color: #4F46E5;
    border-color: #C7D2FE;
}
.reaction-btn.active.ack {
    background-color: #ECFDF5;
    color: #059669;
    border-color: #A7F3D0;
}
.reaction-count {
    font-size: 0.75rem;
    opacity: 0.8;
}
</style>

<div class="announcements-container">
    <div class="page-header">
        <h1><i class="fas fa-clipboard-list text-indigo-500"></i> Community Board</h1>
        <p>Stay updated with the latest news, events, and announcements from your barangay.</p>
    </div>

    <?php if (empty($feed)): ?>
        <div class="no-announcements">
            <div class="text-slate-300 mb-4"><i class="fas fa-mailbox text-6xl"></i></div>
            <h2 class="text-xl font-bold text-slate-700">No updates at this time.</h2>
            <p class="text-slate-500">Please check back later for news and upcoming events.</p>
        </div>
    <?php else: ?>
        <div class="space-y-8">
            <?php foreach ($feed as $item): ?>
                <article class="announcement-card <?= $item['priority'] === 'urgent' ? 'border-l-4 border-amber-500' : '' ?> bg-white shadow-sm border border-slate-200 rounded-3xl overflow-hidden">
                    <?php if ($item['image_path']): ?>
                        <div class="relative h-64 overflow-hidden">
                            <img src="../admin/<?= htmlspecialchars($item['image_path']) ?>" alt="<?= htmlspecialchars($item['title']) ?>" class="w-full h-full object-cover">
                        </div>
                    <?php endif; ?>
                    
                    <div class="p-8">
                        <div class="flex flex-wrap items-center gap-3 mb-4">
                            <?php if ($item['is_event']): ?>
                                <span class="bg-indigo-50 text-indigo-600 text-[10px] font-black px-2.5 py-1 rounded-lg uppercase tracking-widest border border-indigo-100"><i class="fas fa-calendar-star mr-1"></i> Event</span>
                            <?php else: ?>
                                <span class="bg-slate-100 text-slate-600 text-[10px] font-black px-2.5 py-1 rounded-lg uppercase tracking-widest border border-slate-200"><i class="fas fa-bullhorn mr-1"></i> Announcement</span>
                            <?php endif; ?>
                            
                            <?php if ($item['priority'] === 'urgent'): ?>
                                <span class="bg-amber-100 text-amber-700 text-[10px] font-black px-2.5 py-1 rounded-lg uppercase tracking-widest border border-amber-200"><i class="fas fa-bolt mr-1"></i> Urgent</span>
                            <?php endif; ?>

                            <span class="text-[10px] font-bold text-slate-400 uppercase tracking-widest ml-auto">
                                <i class="fas fa-clock mr-1"></i> <?= date('M j, Y • h:i A', strtotime($item['publish_date'] ?: $item['created_at'])) ?>
                            </span>
                        </div>

                        <h2 class="text-2xl font-black text-slate-900 mb-2"><?= htmlspecialchars($item['title']) ?></h2>
                        
                        <?php if ($item['is_event']): ?>
                            <div class="mb-6 grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4 bg-slate-50 p-5 rounded-2xl border border-slate-100 text-sm">
                                <div class="flex items-center text-slate-600">
                                    <i class="fas fa-calendar-alt text-indigo-500 mr-3"></i>
                                    <span><strong>Date:</strong> <?= date('F j, Y', strtotime($item['event_date'])) ?></span>
                                </div>
                                <div class="flex items-center text-slate-600">
                                    <i class="fas fa-clock text-indigo-500 mr-3"></i>
                                    <span><strong>Time:</strong> <?= date('g:i A', strtotime($item['event_time'])) ?></span>
                                </div>
                                <div class="flex items-center text-slate-600">
                                    <i class="fas fa-map-marker-alt text-indigo-500 mr-3"></i>
                                    <span><strong>Location:</strong> <?= htmlspecialchars($item['event_location']) ?></span>
                                </div>
                            </div>
                        <?php endif; ?>

                        <div class="text-slate-600 leading-relaxed mb-8 text-lg">
                            <?= nl2br(htmlspecialchars($item['content'])) ?>
                        </div>

                        <div class="flex items-center justify-between pt-6 border-t border-slate-100">
                            <div class="flex items-center gap-4">
                                <button onclick="toggleReaction(<?= $item['id'] ?>, 'like')" id="like-btn-<?= $item['id'] ?>" 
                                    class="reaction-btn <?= $item['my_reaction'] === 'like' ? 'active' : '' ?> text-slate-600 hover:text-indigo-600">
                                    <i class="<?= $item['my_reaction'] === 'like' ? 'fas' : 'far' ?> fa-thumbs-up"></i>
                                    <span>Like</span>
                                    <span class="reaction-count" id="like-count-<?= $item['id'] ?>"><?= $item['like_count'] ?></span>
                                </button>
                                
                                <button onclick="toggleReaction(<?= $item['id'] ?>, 'acknowledge')" id="ack-btn-<?= $item['id'] ?>" 
                                    class="reaction-btn ack <?= $item['my_reaction'] === 'acknowledge' ? 'active' : '' ?> text-slate-600 hover:text-emerald-600">
                                    <i class="<?= $item['my_reaction'] === 'acknowledge' ? 'fas' : 'far' ?> fa-check-circle"></i>
                                    <span>Acknowledge</span>
                                    <span class="reaction-count" id="ack-count-<?= $item['id'] ?>"><?= $item['ack_count'] ?></span>
                                </button>
                            </div>
                            
                            <div class="text-[10px] font-black text-slate-400 uppercase tracking-widest text-right">
                                <div class="text-slate-900 mb-0.5">Posted by <?= htmlspecialchars($item['author_name']) ?></div>
                                <div>Public Official</div>
                            </div>
                        </div>
                    </div>
                </article>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<script>
async function toggleReaction(postId, type) {
    const POST_REACTION_CSRF_TOKEN = <?php echo json_encode($post_reaction_csrf_token); ?>;
    try {
        const response = await fetch('../api/post-reactions.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: `post_id=${postId}&reaction_type=${type}&csrf_token=${encodeURIComponent(POST_REACTION_CSRF_TOKEN)}`
        });
        
        const data = await response.json();
        
        if (data.success) {
            // Update UI
            const likeBtn = document.getElementById(`like-btn-${postId}`);
            const ackBtn = document.getElementById(`ack-btn-${postId}`);
            const likeCount = document.getElementById(`like-count-${postId}`);
            const ackCount = document.getElementById(`ack-count-${postId}`);
            
            // Toggle classes and counts based on response
            if (type === 'like') {
                toggleBtnState(likeBtn, data.is_active);
                likeCount.innerText = data.new_count;
                // If the other was active, it might have been cleared if we only allow one reaction
                // But our DB schema allows both. Let's assume they can do both.
            } else {
                toggleBtnState(ackBtn, data.is_active);
                ackCount.innerText = data.new_count;
            }
        }
    } catch (error) {
        console.error('Reaction failed:', error);
    }
}

function toggleBtnState(btn, active) {
    if (active) {
        btn.classList.add('active');
        const icon = btn.querySelector('i');
        icon.classList.remove('far');
        icon.classList.add('fas');
    } else {
        btn.classList.remove('active');
        const icon = btn.querySelector('i');
        icon.classList.remove('fas');
        icon.classList.add('far');
    }
}
</script>

<?php require_once 'partials/footer.php'; ?> 