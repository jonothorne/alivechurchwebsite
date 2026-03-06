<?php
/**
 * Author Page - Shows profile, blog posts, and Bible studies by an author
 */
require __DIR__ . '/config.php';
require_once __DIR__ . '/includes/db-config.php';

$pdo = getDbConnection();

// Get username from URL
$username = $_GET['username'] ?? '';
if (empty($username)) {
    header('Location: /');
    exit;
}

// Get author info (only admins/editors with active accounts)
$stmt = $pdo->prepare("
    SELECT id, username, full_name, bio, avatar, social_links, created_at
    FROM users
    WHERE username = ? AND role IN ('admin', 'editor') AND active = 1
");
$stmt->execute([$username]);
$author = $stmt->fetch();

if (!$author) {
    header('HTTP/1.0 404 Not Found');
    $page_title = 'Author Not Found | ' . $site['name'];
    include __DIR__ . '/includes/header.php';
    ?>
    <section class="page-hero">
        <div class="container narrow">
            <h1>Author Not Found</h1>
            <p>Sorry, we couldn't find that author.</p>
            <a href="/" class="btn btn-primary">Go Home</a>
        </div>
    </section>
    <?php
    include __DIR__ . '/includes/footer.php';
    exit;
}

// Parse social links
$socialLinks = [];
if ($author['social_links']) {
    $socialLinks = json_decode($author['social_links'], true) ?: [];
}

// Get published blog posts by this author
$blogStmt = $pdo->prepare("
    SELECT p.*, c.name as category_name, c.slug as category_slug
    FROM blog_posts p
    LEFT JOIN blog_categories c ON p.category_id = c.id
    WHERE p.author_id = ? AND p.status = 'published' AND p.published_at <= NOW()
    ORDER BY p.published_at DESC
");
$blogStmt->execute([$author['id']]);
$blogPosts = $blogStmt->fetchAll();

// Get published Bible studies by this author
$studyStmt = $pdo->prepare("
    SELECT s.*, b.name as book_name, b.slug as book_slug
    FROM bible_studies s
    JOIN bible_books b ON s.book_id = b.id
    WHERE s.author_id = ? AND s.status = 'published'
    ORDER BY b.book_order, s.chapter
");
$studyStmt->execute([$author['id']]);
$bibleStudies = $studyStmt->fetchAll();

// If author has no content, redirect (they shouldn't have an author page)
if (empty($blogPosts) && empty($bibleStudies)) {
    header('HTTP/1.0 404 Not Found');
    $page_title = 'Author Not Found | ' . $site['name'];
    include __DIR__ . '/includes/header.php';
    ?>
    <section class="page-hero">
        <div class="container narrow">
            <h1>No Content Yet</h1>
            <p>This author hasn't published any content yet.</p>
            <a href="/" class="btn btn-primary">Go Home</a>
        </div>
    </section>
    <?php
    include __DIR__ . '/includes/footer.php';
    exit;
}

$authorDisplayName = $author['full_name'] ?: $author['username'];
$page_title = $authorDisplayName . ' | ' . $site['name'];

// Calculate total contributions
$totalContributions = count($blogPosts) + count($bibleStudies);

// Get unique books for Bible studies
$uniqueBooks = [];
foreach ($bibleStudies as $study) {
    $uniqueBooks[$study['book_name']] = true;
}
$bookCount = count($uniqueBooks);

include __DIR__ . '/includes/header.php';
?>

<!-- Hero Section - Centered, Impactful -->
<section class="author-hero">
    <div class="container">
        <div class="author-hero-content">
            <!-- Large Centered Avatar -->
            <div class="author-avatar-wrapper">
                <?php if ($author['avatar']): ?>
                    <img src="<?= htmlspecialchars($author['avatar']); ?>" alt="<?= htmlspecialchars($authorDisplayName); ?>" class="author-avatar">
                <?php else: ?>
                    <div class="author-avatar author-avatar-placeholder"><?= strtoupper(substr($authorDisplayName, 0, 1)); ?></div>
                <?php endif; ?>
            </div>

            <!-- Name & Role -->
            <h1 class="author-name"><?= htmlspecialchars($authorDisplayName); ?></h1>

            <?php if (!empty($bibleStudies) && empty($blogPosts)): ?>
                <p class="author-role">Bible Study Writer</p>
            <?php elseif (empty($bibleStudies) && !empty($blogPosts)): ?>
                <p class="author-role">Blog Contributor</p>
            <?php else: ?>
                <p class="author-role">Writer & Bible Teacher</p>
            <?php endif; ?>

            <!-- Stats Pills -->
            <div class="author-stats-pills">
                <?php if (!empty($bibleStudies)): ?>
                    <span class="stat-pill">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"/><path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z"/></svg>
                        <?= count($bibleStudies); ?> Bible <?= count($bibleStudies) === 1 ? 'Study' : 'Studies'; ?>
                    </span>
                <?php endif; ?>
                <?php if (!empty($blogPosts)): ?>
                    <span class="stat-pill">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 19l7-7 3 3-7 7-3-3z"/><path d="M18 13l-1.5-7.5L2 2l3.5 14.5L13 18l5-5z"/></svg>
                        <?= count($blogPosts); ?> <?= count($blogPosts) === 1 ? 'Article' : 'Articles'; ?>
                    </span>
                <?php endif; ?>
            </div>

            <!-- Social Links -->
            <?php if (!empty($socialLinks)): ?>
                <div class="author-social">
                    <?php if (!empty($socialLinks['website'])): ?>
                        <a href="<?= htmlspecialchars($socialLinks['website']); ?>" target="_blank" rel="noopener" class="social-btn" title="Website">
                            <svg viewBox="0 0 24 24" width="18" height="18" fill="currentColor"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-1 17.93c-3.95-.49-7-3.85-7-7.93 0-.62.08-1.21.21-1.79L9 15v1c0 1.1.9 2 2 2v1.93zm6.9-2.54c-.26-.81-1-1.39-1.9-1.39h-1v-3c0-.55-.45-1-1-1H8v-2h2c.55 0 1-.45 1-1V7h2c1.1 0 2-.9 2-2v-.41c2.93 1.19 5 4.06 5 7.41 0 2.08-.8 3.97-2.1 5.39z"/></svg>
                        </a>
                    <?php endif; ?>
                    <?php if (!empty($socialLinks['twitter'])): ?>
                        <a href="https://twitter.com/<?= htmlspecialchars($socialLinks['twitter']); ?>" target="_blank" rel="noopener" class="social-btn" title="Twitter/X">
                            <svg viewBox="0 0 24 24" width="18" height="18" fill="currentColor"><path d="M18.244 2.25h3.308l-7.227 8.26 8.502 11.24H16.17l-5.214-6.817L4.99 21.75H1.68l7.73-8.835L1.254 2.25H8.08l4.713 6.231zm-1.161 17.52h1.833L7.084 4.126H5.117z"/></svg>
                        </a>
                    <?php endif; ?>
                    <?php if (!empty($socialLinks['facebook'])): ?>
                        <a href="https://facebook.com/<?= htmlspecialchars($socialLinks['facebook']); ?>" target="_blank" rel="noopener" class="social-btn" title="Facebook">
                            <svg viewBox="0 0 24 24" width="18" height="18" fill="currentColor"><path d="M24 12.073c0-6.627-5.373-12-12-12s-12 5.373-12 12c0 5.99 4.388 10.954 10.125 11.854v-8.385H7.078v-3.47h3.047V9.43c0-3.007 1.792-4.669 4.533-4.669 1.312 0 2.686.235 2.686.235v2.953H15.83c-1.491 0-1.956.925-1.956 1.874v2.25h3.328l-.532 3.47h-2.796v8.385C19.612 23.027 24 18.062 24 12.073z"/></svg>
                        </a>
                    <?php endif; ?>
                    <?php if (!empty($socialLinks['instagram'])): ?>
                        <a href="https://instagram.com/<?= htmlspecialchars($socialLinks['instagram']); ?>" target="_blank" rel="noopener" class="social-btn" title="Instagram">
                            <svg viewBox="0 0 24 24" width="18" height="18" fill="currentColor"><path d="M12 2.163c3.204 0 3.584.012 4.85.07 3.252.148 4.771 1.691 4.919 4.919.058 1.265.069 1.645.069 4.849 0 3.205-.012 3.584-.069 4.849-.149 3.225-1.664 4.771-4.919 4.919-1.266.058-1.644.07-4.85.07-3.204 0-3.584-.012-4.849-.07-3.26-.149-4.771-1.699-4.919-4.92-.058-1.265-.07-1.644-.07-4.849 0-3.204.013-3.583.07-4.849.149-3.227 1.664-4.771 4.919-4.919 1.266-.057 1.645-.069 4.849-.069zM12 0C8.741 0 8.333.014 7.053.072 2.695.272.273 2.69.073 7.052.014 8.333 0 8.741 0 12c0 3.259.014 3.668.072 4.948.2 4.358 2.618 6.78 6.98 6.98C8.333 23.986 8.741 24 12 24c3.259 0 3.668-.014 4.948-.072 4.354-.2 6.782-2.618 6.979-6.98.059-1.28.073-1.689.073-4.948 0-3.259-.014-3.667-.072-4.947-.196-4.354-2.617-6.78-6.979-6.98C15.668.014 15.259 0 12 0zm0 5.838a6.162 6.162 0 100 12.324 6.162 6.162 0 000-12.324zM12 16a4 4 0 110-8 4 4 0 010 8zm6.406-11.845a1.44 1.44 0 100 2.881 1.44 1.44 0 000-2.881z"/></svg>
                        </a>
                    <?php endif; ?>
                    <?php if (!empty($socialLinks['linkedin'])): ?>
                        <a href="https://linkedin.com/in/<?= htmlspecialchars($socialLinks['linkedin']); ?>" target="_blank" rel="noopener" class="social-btn" title="LinkedIn">
                            <svg viewBox="0 0 24 24" width="18" height="18" fill="currentColor"><path d="M20.447 20.452h-3.554v-5.569c0-1.328-.027-3.037-1.852-3.037-1.853 0-2.136 1.445-2.136 2.939v5.667H9.351V9h3.414v1.561h.046c.477-.9 1.637-1.85 3.37-1.85 3.601 0 4.267 2.37 4.267 5.455v6.286zM5.337 7.433a2.062 2.062 0 01-2.063-2.065 2.064 2.064 0 112.063 2.065zm1.782 13.019H3.555V9h3.564v11.452zM22.225 0H1.771C.792 0 0 .774 0 1.729v20.542C0 23.227.792 24 1.771 24h20.451C23.2 24 24 23.227 24 22.271V1.729C24 .774 23.2 0 22.222 0h.003z"/></svg>
                        </a>
                    <?php endif; ?>
                    <?php if (!empty($socialLinks['youtube'])): ?>
                        <a href="https://youtube.com/<?= htmlspecialchars($socialLinks['youtube']); ?>" target="_blank" rel="noopener" class="social-btn" title="YouTube">
                            <svg viewBox="0 0 24 24" width="18" height="18" fill="currentColor"><path d="M23.498 6.186a3.016 3.016 0 00-2.122-2.136C19.505 3.545 12 3.545 12 3.545s-7.505 0-9.377.505A3.017 3.017 0 00.502 6.186C0 8.07 0 12 0 12s0 3.93.502 5.814a3.016 3.016 0 002.122 2.136c1.871.505 9.376.505 9.376.505s7.505 0 9.377-.505a3.015 3.015 0 002.122-2.136C24 15.93 24 12 24 12s0-3.93-.502-5.814zM9.545 15.568V8.432L15.818 12l-6.273 3.568z"/></svg>
                        </a>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</section>

<!-- Bio Section -->
<?php if ($author['bio']): ?>
<section class="author-bio-section">
    <div class="container narrow">
        <div class="bio-card">
            <h2>About <?= htmlspecialchars($author['full_name'] ? explode(' ', $author['full_name'])[0] : $author['username']); ?></h2>
            <div class="bio-text">
                <?= nl2br(htmlspecialchars($author['bio'])); ?>
            </div>
        </div>
    </div>
</section>
<?php endif; ?>

<?php if (!empty($bibleStudies)): ?>
<!-- Bible Studies Section -->
<section class="author-works">
    <div class="container">
        <div class="section-header">
            <h2>Bible Studies</h2>
            <p class="section-subtitle"><?= count($bibleStudies); ?> in-depth studies across <?= $bookCount; ?> <?= $bookCount === 1 ? 'book' : 'books'; ?> of the Bible</p>
        </div>

        <?php
        // Group studies by book
        $studiesByBook = [];
        foreach ($bibleStudies as $study) {
            $studiesByBook[$study['book_name']][] = $study;
        }
        ?>

        <?php foreach ($studiesByBook as $bookName => $studies): ?>
            <div class="author-book-group">
                <h3 class="book-group-title"><?= htmlspecialchars($bookName); ?></h3>
                <div class="chapters-grid">
                    <?php foreach ($studies as $study): ?>
                        <a href="/bible-study/<?= htmlspecialchars($study['book_slug']); ?>/<?= $study['chapter']; ?>" class="chapter-card available">
                            <span class="chapter-number"><?= $study['chapter']; ?></span>
                            <?php if ($study['title']): ?>
                                <span class="chapter-title"><?= htmlspecialchars($study['title']); ?></span>
                            <?php endif; ?>
                        </a>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
</section>
<?php endif; ?>

<?php if (!empty($blogPosts)): ?>
<!-- Blog Posts Section -->
<section class="author-works author-posts">
    <div class="container">
        <div class="section-header">
            <h2>Articles</h2>
            <p class="section-subtitle">Thoughts, insights, and reflections</p>
        </div>

        <div class="blog-grid">
            <?php foreach ($blogPosts as $post): ?>
                <article class="blog-card">
                    <a href="/blog/<?= htmlspecialchars($post['slug']); ?>">
                        <?php if ($post['featured_image']): ?>
                            <div class="blog-card-image">
                                <img src="<?= htmlspecialchars($post['featured_image']); ?>" alt="<?= htmlspecialchars($post['title']); ?>">
                            </div>
                        <?php endif; ?>
                        <div class="blog-card-content">
                            <?php if ($post['category_name']): ?>
                                <span class="post-category"><?= htmlspecialchars($post['category_name']); ?></span>
                            <?php endif; ?>
                            <h3><?= htmlspecialchars($post['title']); ?></h3>
                            <?php if ($post['excerpt']): ?>
                                <p><?= htmlspecialchars($post['excerpt']); ?></p>
                            <?php endif; ?>
                            <div class="post-meta">
                                <span class="post-date"><?= date('M j, Y', strtotime($post['published_at'])); ?></span>
                            </div>
                        </div>
                    </a>
                </article>
            <?php endforeach; ?>
        </div>
    </div>
</section>
<?php endif; ?>

<!-- Footer Links -->
<?php if (!empty($bibleStudies) || !empty($blogPosts)): ?>
<section class="author-footer">
    <div class="container" style="text-align: center;">
        <?php if (!empty($bibleStudies)): ?>
            <a href="/bible-study" class="btn btn-outline">Explore All Bible Studies</a>
        <?php endif; ?>
        <?php if (!empty($blogPosts)): ?>
            <a href="/blog" class="btn btn-outline">Explore All Articles</a>
        <?php endif; ?>
    </div>
</section>
<?php endif; ?>

<style>
/* ==================== Author Page Styles ==================== */

/* Hero Section */
.author-hero {
    padding: 5rem 0 4rem;
    background: linear-gradient(145deg, var(--color-purple) 0%, #2d1650 50%, #1a0d30 100%);
    color: white;
    text-align: center;
    position: relative;
    overflow: hidden;
}

.author-hero::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: url("data:image/svg+xml,%3Csvg width='60' height='60' viewBox='0 0 60 60' xmlns='http://www.w3.org/2000/svg'%3E%3Cg fill='none' fill-rule='evenodd'%3E%3Cg fill='%23ffffff' fill-opacity='0.03'%3E%3Cpath d='M36 34v-4h-2v4h-4v2h4v4h2v-4h4v-2h-4zm0-30V0h-2v4h-4v2h4v4h2V6h4V4h-4zM6 34v-4H4v4H0v2h4v4h2v-4h4v-2H6zM6 4V0H4v4H0v2h4v4h2V6h4V4H6z'/%3E%3C/g%3E%3C/g%3E%3C/svg%3E");
    opacity: 0.5;
}

.author-hero-content {
    position: relative;
    z-index: 1;
    display: flex;
    flex-direction: column;
    align-items: center;
}

.author-avatar-wrapper {
    margin-bottom: 1.5rem;
    display: flex;
    justify-content: center;
}

.author-avatar {
    width: 140px;
    height: 140px;
    border-radius: 50%;
    object-fit: cover;
    border: 4px solid rgba(255, 255, 255, 0.25);
    box-shadow: 0 8px 32px rgba(0, 0, 0, 0.3);
}

.author-avatar-placeholder {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    background: linear-gradient(135deg, rgba(255,255,255,0.2), rgba(255,255,255,0.05));
    font-size: 3.5rem;
    font-weight: 600;
    color: white;
}

.author-name {
    font-size: 2.75rem;
    font-weight: 700;
    margin: 0 0 0.5rem;
    letter-spacing: -0.02em;
}

.author-role {
    font-size: 1.1rem;
    opacity: 0.85;
    margin: 0 0 1.5rem;
    font-weight: 400;
}

/* Stats Pills */
.author-stats-pills {
    display: flex;
    justify-content: center;
    gap: 0.75rem;
    flex-wrap: wrap;
    margin-bottom: 1.5rem;
}

.stat-pill {
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    padding: 0.5rem 1rem;
    background: rgba(255, 255, 255, 0.1);
    border-radius: 2rem;
    font-size: 0.9rem;
    font-weight: 500;
    backdrop-filter: blur(10px);
}

.stat-pill svg {
    opacity: 0.8;
}

/* Social Buttons */
.author-social {
    display: flex;
    justify-content: center;
    gap: 0.5rem;
}

.social-btn {
    display: flex;
    align-items: center;
    justify-content: center;
    width: 42px;
    height: 42px;
    border-radius: 50%;
    background: rgba(255, 255, 255, 0.1);
    color: white;
    transition: all 0.2s ease;
    border: 1px solid rgba(255, 255, 255, 0.1);
}

.social-btn:hover {
    background: rgba(255, 255, 255, 0.2);
    transform: translateY(-2px);
    border-color: rgba(255, 255, 255, 0.3);
}

/* Bio Section */
.author-bio-section {
    padding: 3rem 0;
    background: var(--color-bg);
}

.bio-card {
    background: var(--color-card-bg);
    border-radius: 1rem;
    padding: 2rem;
    box-shadow: 0 4px 20px var(--color-shadow);
    border: 1px solid var(--color-border);
}

.bio-card h2 {
    font-size: 1.25rem;
    margin: 0 0 1rem;
    color: var(--color-text);
}

.bio-text {
    font-size: 1.05rem;
    line-height: 1.8;
    color: var(--color-text-muted);
}

/* Works Section */
.author-works {
    padding: 4rem 0;
    background: var(--color-bg-subtle);
}

.author-posts {
    background: var(--color-bg);
}

.section-header {
    text-align: center;
    margin-bottom: 2.5rem;
}

.section-header h2 {
    font-size: 2rem;
    margin: 0 0 0.5rem;
    color: var(--color-text);
}

.section-subtitle {
    color: var(--color-text-muted);
    margin: 0;
    font-size: 1rem;
}

/* Book Groups for Bible Studies */
.author-book-group {
    margin-bottom: 2.5rem;
}

.author-book-group:last-child {
    margin-bottom: 0;
}

.book-group-title {
    font-size: 1.25rem;
    font-weight: 600;
    margin: 0 0 1rem;
    color: var(--color-text);
    padding-bottom: 0.5rem;
    border-bottom: 2px solid var(--color-purple);
    display: inline-block;
}

/* Footer */
.author-footer {
    padding: 3rem 0 4rem;
    background: var(--color-bg);
}

.author-footer .btn {
    margin: 0 0.5rem;
}

/* Responsive */
@media (max-width: 768px) {
    .author-hero {
        padding: 3rem 0;
    }

    .author-avatar {
        width: 120px;
        height: 120px;
    }

    .author-name {
        font-size: 2rem;
    }

    .stat-pill {
        font-size: 0.8rem;
        padding: 0.4rem 0.8rem;
    }
}
</style>
<?php include __DIR__ . '/includes/newsletter.php'; ?>
<?php include __DIR__ . '/includes/footer.php'; ?>
