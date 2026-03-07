<?php
require __DIR__ . '/config.php';
require_once __DIR__ . '/includes/db-config.php';

$pdo = getDbConnection();

// Get filter parameters
$categorySlug = $_GET['category'] ?? '';
$tagSlug = $_GET['tag'] ?? '';
$search = $_GET['search'] ?? '';
$page = max(1, intval($_GET['page'] ?? 1));
$perPage = 9;
$offset = ($page - 1) * $perPage;

// Build query
$where = ["p.status = 'published'", "p.published_at <= NOW()"];
$params = [];

if ($categorySlug) {
    $where[] = "c.slug = ?";
    $params[] = $categorySlug;
}

if ($tagSlug) {
    $where[] = "p.id IN (SELECT post_id FROM blog_post_tags pt JOIN blog_tags t ON pt.tag_id = t.id WHERE t.slug = ?)";
    $params[] = $tagSlug;
}

if ($search) {
    $where[] = "(p.title LIKE ? OR p.excerpt LIKE ? OR p.content LIKE ?)";
    $searchTerm = "%$search%";
    $params[] = $searchTerm;
    $params[] = $searchTerm;
    $params[] = $searchTerm;
}

$whereClause = implode(' AND ', $where);

// Get total count
$countSql = "SELECT COUNT(*) FROM blog_posts p LEFT JOIN blog_categories c ON p.category_id = c.id WHERE $whereClause";
$countStmt = $pdo->prepare($countSql);
$countStmt->execute($params);
$totalPosts = $countStmt->fetchColumn();
$totalPages = ceil($totalPosts / $perPage);

// Get posts
$sql = "SELECT p.*, c.name as category_name, c.slug as category_slug, u.full_name as author_name
        FROM blog_posts p
        LEFT JOIN blog_categories c ON p.category_id = c.id
        LEFT JOIN users u ON p.author_id = u.id
        WHERE $whereClause
        ORDER BY p.published_at DESC
        LIMIT $perPage OFFSET $offset";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$posts = $stmt->fetchAll();

// Get categories for filter
$categories = $pdo->query("SELECT c.*, COUNT(p.id) as post_count
                           FROM blog_categories c
                           LEFT JOIN blog_posts p ON c.id = p.category_id AND p.status = 'published'
                           GROUP BY c.id
                           ORDER BY c.name")->fetchAll();

// Get popular tags
$tags = $pdo->query("SELECT t.*, COUNT(pt.post_id) as post_count
                     FROM blog_tags t
                     JOIN blog_post_tags pt ON t.id = pt.tag_id
                     JOIN blog_posts p ON pt.post_id = p.id AND p.status = 'published'
                     GROUP BY t.id
                     ORDER BY post_count DESC
                     LIMIT 15")->fetchAll();

// Get featured/recent post for hero
$featuredPost = null;
if ($page === 1 && !$categorySlug && !$tagSlug && !$search) {
    $featuredPost = $pdo->query("SELECT p.*, c.name as category_name, c.slug as category_slug, u.full_name as author_name
                                  FROM blog_posts p
                                  LEFT JOIN blog_categories c ON p.category_id = c.id
                                  LEFT JOIN users u ON p.author_id = u.id
                                  WHERE p.status = 'published' AND p.published_at <= NOW()
                                  ORDER BY p.published_at DESC
                                  LIMIT 1")->fetch();
    // Remove featured from main list to avoid duplication
    if ($featuredPost) {
        $posts = array_filter($posts, fn($p) => $p['id'] !== $featuredPost['id']);
    }
}

$page_title = 'Blog | ' . $site['name'];
if ($categorySlug) {
    $currentCategory = array_filter($categories, fn($c) => $c['slug'] === $categorySlug);
    $currentCategory = reset($currentCategory);
    if ($currentCategory) {
        $page_title = $currentCategory['name'] . ' | Blog | ' . $site['name'];
    }
}

include __DIR__ . '/includes/header.php';

// Initialize CMS
if (!isset($cms)) {
    require_once __DIR__ . '/includes/cms/ContentManager.php';
    $cms = new ContentManager('blog');
}
?>

<?php if ($featuredPost): ?>
<section class="blog-featured-hero" style="background-image: linear-gradient(rgba(30, 26, 43, 0.6), rgba(30, 26, 43, 0.85)), url('<?= htmlspecialchars($featuredPost['featured_image'] ?: '/assets/imgs/gallery/alive-church-worship-congregation.jpg'); ?>');">
    <div class="container">
        <div class="featured-hero-content">
            <p class="eyebrow">Latest Post</p>
            <?php if ($featuredPost['category_name']): ?>
                <span class="post-category"><?= htmlspecialchars($featuredPost['category_name']); ?></span>
            <?php endif; ?>
            <h1><?= htmlspecialchars($featuredPost['title']); ?></h1>
            <p class="featured-excerpt"><?= htmlspecialchars($featuredPost['excerpt']); ?></p>
            <div class="post-meta">
                <?php if ($featuredPost['author_name']): ?>
                    <span class="post-author">By <?= htmlspecialchars($featuredPost['author_name']); ?></span>
                <?php endif; ?>
                <span class="post-date"><?= date('F j, Y', strtotime($featuredPost['published_at'])); ?></span>
            </div>
            <a href="/blog/<?= htmlspecialchars($featuredPost['slug']); ?>" class="btn btn-primary">Read More</a>
        </div>
    </div>
</section>
<?php else: ?>
<section class="page-hero <?= $hero_texture_class; ?>">
    <div class="container narrow">
        <p class="eyebrow" data-cms-editable="hero_eyebrow" data-cms-page="blog" data-cms-type="text"><?= $cms->text('hero_eyebrow', 'Blog'); ?></p>
        <h1 data-cms-editable="hero_headline" data-cms-page="blog" data-cms-type="text"><?= $cms->text('hero_headline', 'Stories & Updates'); ?></h1>
        <p data-cms-editable="hero_subtext" data-cms-page="blog" data-cms-type="text"><?= $cms->text('hero_subtext', 'News, devotionals, and stories from our church family.'); ?></p>
    </div>
</section>
<?php endif; ?>

<section class="blog-section">
    <div class="container">
        <div class="blog-layout">
            <!-- Main Content -->
            <div class="blog-main">
                <!-- Search & Filter Bar -->
                <div class="blog-controls">
                    <form class="blog-search" action="/blog" method="GET">
                        <input type="text" name="search" placeholder="Search posts..." value="<?= htmlspecialchars($search); ?>">
                        <button type="submit" class="btn btn-primary">Search</button>
                    </form>
                </div>

                <?php if ($categorySlug || $tagSlug || $search): ?>
                    <div class="blog-filter-info">
                        <p>
                            <?php if ($search): ?>
                                Showing results for "<?= htmlspecialchars($search); ?>"
                            <?php elseif ($categorySlug && !empty($currentCategory)): ?>
                                Browsing: <?= htmlspecialchars($currentCategory['name']); ?>
                            <?php elseif ($tagSlug): ?>
                                Tagged: <?= htmlspecialchars($tagSlug); ?>
                            <?php endif; ?>
                            <a href="/blog" class="clear-filter">Clear filter</a>
                        </p>
                    </div>
                <?php endif; ?>

                <?php if (empty($posts) && !$featuredPost): ?>
                    <div class="blog-empty">
                        <h3>No posts found</h3>
                        <p>Check back soon for new content!</p>
                    </div>
                <?php elseif (!empty($posts)): ?>
                    <?php if ($featuredPost && !$categorySlug && !$tagSlug && !$search): ?>
                        <h2 class="blog-section-title">More Posts</h2>
                    <?php endif; ?>
                    <div class="blog-grid">
                        <?php foreach ($posts as $post): ?>
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
                                        <p><?= htmlspecialchars($post['excerpt'] ?? ''); ?></p>
                                        <div class="post-meta">
                                            <span class="post-date"><?= date('M j, Y', strtotime($post['published_at'])); ?></span>
                                        </div>
                                    </div>
                                </a>
                            </article>
                        <?php endforeach; ?>
                    </div>

                    <?php if ($totalPages > 1): ?>
                        <nav class="blog-pagination">
                            <?php if ($page > 1): ?>
                                <a href="?page=<?= $page - 1; ?><?= $categorySlug ? '&category=' . urlencode($categorySlug) : ''; ?><?= $search ? '&search=' . urlencode($search) : ''; ?>" class="btn btn-outline">&larr; Newer</a>
                            <?php endif; ?>
                            <span class="pagination-info">Page <?= $page; ?> of <?= $totalPages; ?></span>
                            <?php if ($page < $totalPages): ?>
                                <a href="?page=<?= $page + 1; ?><?= $categorySlug ? '&category=' . urlencode($categorySlug) : ''; ?><?= $search ? '&search=' . urlencode($search) : ''; ?>" class="btn btn-outline">Older &rarr;</a>
                            <?php endif; ?>
                        </nav>
                    <?php endif; ?>
                <?php endif; ?>
            </div>

            <!-- Sidebar -->
            <aside class="blog-sidebar">
                <!-- Categories -->
                <div class="sidebar-widget">
                    <h3>Categories</h3>
                    <ul class="category-list">
                        <li><a href="/blog" class="<?= !$categorySlug ? 'active' : ''; ?>">All Posts</a></li>
                        <?php foreach ($categories as $cat): ?>
                            <li>
                                <a href="/blog?category=<?= htmlspecialchars($cat['slug']); ?>" class="<?= $categorySlug === $cat['slug'] ? 'active' : ''; ?>">
                                    <?= htmlspecialchars($cat['name']); ?>
                                    <span class="count"><?= $cat['post_count']; ?></span>
                                </a>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </div>

                <?php if (!empty($tags)): ?>
                <!-- Tags -->
                <div class="sidebar-widget">
                    <h3>Topics</h3>
                    <div class="tag-cloud">
                        <?php foreach ($tags as $tag): ?>
                            <a href="/blog?tag=<?= htmlspecialchars($tag['slug']); ?>" class="tag <?= $tagSlug === $tag['slug'] ? 'active' : ''; ?>">
                                <?= htmlspecialchars($tag['name']); ?>
                            </a>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Newsletter Signup -->
                <div class="sidebar-widget sidebar-cta">
                    <h3>Stay Connected</h3>
                    <p>Get our latest posts delivered to your inbox.</p>
                    <a href="/connect" class="btn btn-primary btn-block">Subscribe</a>
                </div>
            </aside>
        </div>
    </div>
</section>
<?php include __DIR__ . '/includes/newsletter.php'; ?>
<?php include __DIR__ . '/includes/footer.php'; ?>
