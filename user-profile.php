<?php
require __DIR__ . '/config.php';
require_once __DIR__ . '/includes/db-config.php';

$pdo = getDbConnection();

// Get username from URL
$username = $_GET['username'] ?? '';
if (empty($username)) {
    header('Location: /');
    exit;
}

// Get user
$stmt = $pdo->prepare("
    SELECT id, username, full_name, avatar, avatar_color, bio, social_links, role,
           reading_streak, longest_streak, created_at
    FROM users
    WHERE username = ? AND active = TRUE
");
$stmt->execute([$username]);
$user = $stmt->fetch();

if (!$user) {
    header('HTTP/1.0 404 Not Found');
    $page_title = 'User Not Found | ' . $site['name'];
    include __DIR__ . '/includes/header.php';
    ?>
    <section class="page-hero">
        <div class="container narrow">
            <h1>User Not Found</h1>
            <p>Sorry, we couldn't find that user.</p>
            <a href="/" class="btn btn-primary">Back to Home</a>
        </div>
    </section>
    <?php
    include __DIR__ . '/includes/footer.php';
    exit;
}

// Parse social links and build URLs
$socialLinksRaw = $user['social_links'] ? json_decode($user['social_links'], true) : [];
$socialLinks = [];

// Helper to build full URLs from usernames
if (!empty($socialLinksRaw['facebook'])) {
    $val = $socialLinksRaw['facebook'];
    $socialLinks['facebook'] = (strpos($val, 'http') === 0) ? $val : 'https://facebook.com/' . $val;
}
if (!empty($socialLinksRaw['instagram'])) {
    $val = $socialLinksRaw['instagram'];
    $socialLinks['instagram'] = (strpos($val, 'http') === 0) ? $val : 'https://instagram.com/' . $val;
}
if (!empty($socialLinksRaw['twitter'])) {
    $val = $socialLinksRaw['twitter'];
    $socialLinks['twitter'] = (strpos($val, 'http') === 0) ? $val : 'https://x.com/' . $val;
}
if (!empty($socialLinksRaw['linkedin'])) {
    $val = $socialLinksRaw['linkedin'];
    $socialLinks['linkedin'] = (strpos($val, 'http') === 0) ? $val : 'https://linkedin.com/in/' . $val;
}
if (!empty($socialLinksRaw['website'])) {
    $socialLinks['website'] = $socialLinksRaw['website'];
}

// Get user's recent approved comments
$commentsStmt = $pdo->prepare("
    SELECT c.*, p.title as post_title, p.slug as post_slug
    FROM blog_comments c
    JOIN blog_posts p ON c.post_id = p.id
    WHERE c.user_id = ? AND c.status = 'approved'
    ORDER BY c.created_at DESC
    LIMIT 5
");
$commentsStmt->execute([$user['id']]);
$recentComments = $commentsStmt->fetchAll();

// Get total comment count
$commentCountStmt = $pdo->prepare("SELECT COUNT(*) FROM blog_comments WHERE user_id = ? AND status = 'approved'");
$commentCountStmt->execute([$user['id']]);
$commentCount = $commentCountStmt->fetchColumn();

// Get reading plan stats
$plansCompletedStmt = $pdo->prepare("
    SELECT COUNT(DISTINCT plan_id) FROM user_reading_plan_completions
    WHERE user_id = ?
    AND (plan_id, day_number) IN (
        SELECT plan_id, MAX(day_number) FROM reading_plan_days GROUP BY plan_id
    )
");
$plansCompletedStmt->execute([$user['id']]);
$plansCompleted = $plansCompletedStmt->fetchColumn();

// Get total days read
$daysReadStmt = $pdo->prepare("SELECT COUNT(*) FROM user_reading_plan_completions WHERE user_id = ?");
$daysReadStmt->execute([$user['id']]);
$totalDaysRead = $daysReadStmt->fetchColumn();

// Get bible studies completed
$studiesCompletedStmt = $pdo->prepare("
    SELECT COUNT(*) FROM bible_study_progress
    WHERE user_identifier = ? AND completed = 1
");
$studiesCompletedStmt->execute([$user['id']]);
$studiesCompleted = $studiesCompletedStmt->fetchColumn();

// Get authored blog posts (if editor/admin)
$authoredPosts = [];
if (in_array($user['role'], ['admin', 'editor'])) {
    $postsStmt = $pdo->prepare("
        SELECT id, title, slug, excerpt, featured_image, published_at
        FROM blog_posts
        WHERE author_id = ? AND status = 'published'
        ORDER BY published_at DESC
        LIMIT 6
    ");
    $postsStmt->execute([$user['id']]);
    $authoredPosts = $postsStmt->fetchAll();
}


// Get total blog post count
$postCountStmt = $pdo->prepare("SELECT COUNT(*) FROM blog_posts WHERE author_id = ? AND status = 'published'");
$postCountStmt->execute([$user['id']]);
$postCount = $postCountStmt->fetchColumn();

$displayName = $user['full_name'] ?: $user['username'];
$page_title = $displayName . ' | ' . $site['name'];
include __DIR__ . '/includes/header.php';
?>

<section class="user-profile-hero">
    <div class="container narrow">
        <div class="profile-header">
            <div class="profile-avatar-large">
                <?php if ($user['avatar']): ?>
                    <img src="<?= htmlspecialchars($user['avatar']); ?>" alt="<?= htmlspecialchars($displayName); ?>">
                <?php else: ?>
                    <div class="avatar-initials" style="background-color: <?= htmlspecialchars($user['avatar_color'] ?? '#4b2679'); ?>">
                        <?= strtoupper(substr($displayName, 0, 1)); ?>
                    </div>
                <?php endif; ?>
            </div>
            <div class="profile-info">
                <h1><?= htmlspecialchars($displayName); ?></h1>
                <?php if ($user['role'] !== 'member'): ?>
                    <span class="profile-role"><?= ucfirst($user['role']); ?></span>
                <?php endif; ?>
                <p class="profile-joined">Member since <?= date('F Y', strtotime($user['created_at'])); ?></p>

                <?php if (!empty($socialLinks)): ?>
                    <div class="profile-social">
                        <?php if (!empty($socialLinks['facebook'])): ?>
                            <a href="<?= htmlspecialchars($socialLinks['facebook']); ?>" target="_blank" rel="noopener" class="social-link" title="Facebook">
                                <svg width="20" height="20" fill="currentColor" viewBox="0 0 24 24"><path d="M24 12.073c0-6.627-5.373-12-12-12s-12 5.373-12 12c0 5.99 4.388 10.954 10.125 11.854v-8.385H7.078v-3.47h3.047V9.43c0-3.007 1.792-4.669 4.533-4.669 1.312 0 2.686.235 2.686.235v2.953H15.83c-1.491 0-1.956.925-1.956 1.874v2.25h3.328l-.532 3.47h-2.796v8.385C19.612 23.027 24 18.062 24 12.073z"/></svg>
                            </a>
                        <?php endif; ?>
                        <?php if (!empty($socialLinks['instagram'])): ?>
                            <a href="<?= htmlspecialchars($socialLinks['instagram']); ?>" target="_blank" rel="noopener" class="social-link" title="Instagram">
                                <svg width="20" height="20" fill="currentColor" viewBox="0 0 24 24"><path d="M12 2.163c3.204 0 3.584.012 4.85.07 3.252.148 4.771 1.691 4.919 4.919.058 1.265.069 1.645.069 4.849 0 3.205-.012 3.584-.069 4.849-.149 3.225-1.664 4.771-4.919 4.919-1.266.058-1.644.07-4.85.07-3.204 0-3.584-.012-4.849-.07-3.26-.149-4.771-1.699-4.919-4.92-.058-1.265-.07-1.644-.07-4.849 0-3.204.013-3.583.07-4.849.149-3.227 1.664-4.771 4.919-4.919 1.266-.057 1.645-.069 4.849-.069zm0-2.163c-3.259 0-3.667.014-4.947.072-4.358.2-6.78 2.618-6.98 6.98-.059 1.281-.073 1.689-.073 4.948 0 3.259.014 3.668.072 4.948.2 4.358 2.618 6.78 6.98 6.98 1.281.058 1.689.072 4.948.072 3.259 0 3.668-.014 4.948-.072 4.354-.2 6.782-2.618 6.979-6.98.059-1.28.073-1.689.073-4.948 0-3.259-.014-3.667-.072-4.947-.196-4.354-2.617-6.78-6.979-6.98-1.281-.059-1.69-.073-4.949-.073zm0 5.838c-3.403 0-6.162 2.759-6.162 6.162s2.759 6.163 6.162 6.163 6.162-2.759 6.162-6.163c0-3.403-2.759-6.162-6.162-6.162zm0 10.162c-2.209 0-4-1.79-4-4 0-2.209 1.791-4 4-4s4 1.791 4 4c0 2.21-1.791 4-4 4zm6.406-11.845c-.796 0-1.441.645-1.441 1.44s.645 1.44 1.441 1.44c.795 0 1.439-.645 1.439-1.44s-.644-1.44-1.439-1.44z"/></svg>
                            </a>
                        <?php endif; ?>
                        <?php if (!empty($socialLinks['twitter'])): ?>
                            <a href="<?= htmlspecialchars($socialLinks['twitter']); ?>" target="_blank" rel="noopener" class="social-link" title="X/Twitter">
                                <svg width="20" height="20" fill="currentColor" viewBox="0 0 24 24"><path d="M18.244 2.25h3.308l-7.227 8.26 8.502 11.24H16.17l-5.214-6.817L4.99 21.75H1.68l7.73-8.835L1.254 2.25H8.08l4.713 6.231zm-1.161 17.52h1.833L7.084 4.126H5.117z"/></svg>
                            </a>
                        <?php endif; ?>
                        <?php if (!empty($socialLinks['linkedin'])): ?>
                            <a href="<?= htmlspecialchars($socialLinks['linkedin']); ?>" target="_blank" rel="noopener" class="social-link" title="LinkedIn">
                                <svg width="20" height="20" fill="currentColor" viewBox="0 0 24 24"><path d="M20.447 20.452h-3.554v-5.569c0-1.328-.027-3.037-1.852-3.037-1.853 0-2.136 1.445-2.136 2.939v5.667H9.351V9h3.414v1.561h.046c.477-.9 1.637-1.85 3.37-1.85 3.601 0 4.267 2.37 4.267 5.455v6.286zM5.337 7.433c-1.144 0-2.063-.926-2.063-2.065 0-1.138.92-2.063 2.063-2.063 1.14 0 2.064.925 2.064 2.063 0 1.139-.925 2.065-2.064 2.065zm1.782 13.019H3.555V9h3.564v11.452zM22.225 0H1.771C.792 0 0 .774 0 1.729v20.542C0 23.227.792 24 1.771 24h20.451C23.2 24 24 23.227 24 22.271V1.729C24 .774 23.2 0 22.222 0h.003z"/></svg>
                            </a>
                        <?php endif; ?>
                        <?php if (!empty($socialLinks['website'])): ?>
                            <a href="<?= htmlspecialchars($socialLinks['website']); ?>" target="_blank" rel="noopener" class="social-link" title="Website">
                                <svg width="20" height="20" fill="currentColor" viewBox="0 0 24 24"><path d="M12 0C5.373 0 0 5.373 0 12s5.373 12 12 12 12-5.373 12-12S18.627 0 12 0zm-1 19.93c-3.95-.49-7-3.85-7-7.93 0-.62.08-1.21.21-1.79L9 15v1c0 1.1.9 2 2 2v1.93zm6.9-2.54c-.26-.81-1-1.39-1.9-1.39h-1v-3c0-.55-.45-1-1-1H8v-2h2c.55 0 1-.45 1-1V7h2c1.1 0 2-.9 2-2v-.41c2.93 1.19 5 4.06 5 7.41 0 2.08-.8 3.97-2.1 5.39z"/></svg>
                            </a>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <?php if ($user['bio']): ?>
            <div class="profile-bio">
                <?= nl2br(htmlspecialchars($user['bio'])); ?>
            </div>
        <?php endif; ?>
    </div>
</section>

<!-- Stats Section -->
<section class="profile-stats">
    <div class="container narrow">
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-number"><?= number_format($user['longest_streak']); ?></div>
                <div class="stat-label">Day Streak Record</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?= number_format($totalDaysRead); ?></div>
                <div class="stat-label">Days of Reading</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?= number_format($plansCompleted); ?></div>
                <div class="stat-label">Plans Completed</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?= number_format($studiesCompleted); ?></div>
                <div class="stat-label">Studies Completed</div>
            </div>
        </div>
    </div>
</section>

<?php if (!empty($authoredPosts)): ?>
<!-- Authored Blog Posts -->
<section class="profile-section">
    <div class="container">
        <div class="section-header">
            <h2>Blog Posts by <?= htmlspecialchars($displayName); ?></h2>
            <?php if ($postCount > 6): ?>
                <a href="/blog?author=<?= urlencode($user['username']); ?>" class="btn btn-outline">View All (<?= $postCount; ?>)</a>
            <?php endif; ?>
        </div>
        <div class="blog-grid">
            <?php foreach ($authoredPosts as $post): ?>
                <article class="blog-card">
                    <a href="/blog/<?= htmlspecialchars($post['slug']); ?>">
                        <?php if ($post['featured_image']): ?>
                            <div class="blog-card-image">
                                <img src="<?= htmlspecialchars($post['featured_image']); ?>" alt="<?= htmlspecialchars($post['title']); ?>">
                            </div>
                        <?php endif; ?>
                        <div class="blog-card-content">
                            <h3><?= htmlspecialchars($post['title']); ?></h3>
                            <p><?= htmlspecialchars($post['excerpt'] ?? ''); ?></p>
                            <span class="post-date"><?= date('M j, Y', strtotime($post['published_at'])); ?></span>
                        </div>
                    </a>
                </article>
            <?php endforeach; ?>
        </div>
    </div>
</section>
<?php endif; ?>


<?php if (!empty($recentComments)): ?>
<!-- Recent Comments -->
<section class="profile-section">
    <div class="container narrow">
        <div class="section-header">
            <h2>Recent Comments</h2>
            <?php if ($commentCount > 5): ?>
                <span class="comment-count"><?= $commentCount; ?> total comments</span>
            <?php endif; ?>
        </div>
        <div class="profile-comments">
            <?php foreach ($recentComments as $comment): ?>
                <div class="profile-comment">
                    <div class="comment-meta">
                        <a href="/blog/<?= htmlspecialchars($comment['post_slug']); ?>#comment-<?= $comment['id']; ?>" class="comment-post-link">
                            <?= htmlspecialchars($comment['post_title']); ?>
                        </a>
                        <span class="comment-date"><?= date('M j, Y', strtotime($comment['created_at'])); ?></span>
                    </div>
                    <p class="comment-excerpt"><?= htmlspecialchars(substr($comment['content'], 0, 200)); ?><?= strlen($comment['content']) > 200 ? '...' : ''; ?></p>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>
<?php endif; ?>

<?php include __DIR__ . '/includes/footer.php'; ?>
