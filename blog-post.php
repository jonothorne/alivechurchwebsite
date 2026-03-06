<?php
require __DIR__ . '/config.php';
require_once __DIR__ . '/includes/db-config.php';

$pdo = getDbConnection();

// Get post slug from URL
$slug = $_GET['slug'] ?? '';
if (empty($slug)) {
    header('Location: /blog');
    exit;
}

// Get post
$stmt = $pdo->prepare("SELECT p.*, c.name as category_name, c.slug as category_slug, u.full_name as author_name, u.id as author_id, u.username as author_username
                       FROM blog_posts p
                       LEFT JOIN blog_categories c ON p.category_id = c.id
                       LEFT JOIN users u ON p.author_id = u.id
                       WHERE p.slug = ? AND (p.status = 'published' OR ? = 1)");

// Allow preview for logged-in admins
session_start();
$isAdmin = isset($_SESSION['admin_user_id']) ? 1 : 0;
$stmt->execute([$slug, $isAdmin]);
$post = $stmt->fetch();

if (!$post) {
    header('HTTP/1.0 404 Not Found');
    $page_title = 'Post Not Found | ' . $site['name'];
    include __DIR__ . '/includes/header.php';
    ?>
    <section class="page-hero">
        <div class="container narrow">
            <h1>Post Not Found</h1>
            <p>Sorry, we couldn't find that post.</p>
            <a href="/blog" class="btn btn-primary">Back to Blog</a>
        </div>
    </section>
    <?php
    include __DIR__ . '/includes/footer.php';
    exit;
}

// Get post tags
$tagStmt = $pdo->prepare("SELECT t.* FROM blog_tags t
                          JOIN blog_post_tags pt ON t.id = pt.tag_id
                          WHERE pt.post_id = ?");
$tagStmt->execute([$post['id']]);
$postTags = $tagStmt->fetchAll();

// Get approved comments
$commentStmt = $pdo->prepare("SELECT * FROM blog_comments
                              WHERE post_id = ? AND status = 'approved' AND parent_id IS NULL
                              ORDER BY created_at ASC");
$commentStmt->execute([$post['id']]);
$comments = $commentStmt->fetchAll();

// Get comment replies
$replyStmt = $pdo->prepare("SELECT * FROM blog_comments
                            WHERE post_id = ? AND status = 'approved' AND parent_id IS NOT NULL
                            ORDER BY created_at ASC");
$replyStmt->execute([$post['id']]);
$replies = $replyStmt->fetchAll();

// Group replies by parent
$repliesByParent = [];
foreach ($replies as $reply) {
    $repliesByParent[$reply['parent_id']][] = $reply;
}

// Handle comment submission
$commentSuccess = false;
$commentError = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_comment'])) {
    $authorName = trim($_POST['author_name'] ?? '');
    $authorEmail = trim($_POST['author_email'] ?? '');
    $commentContent = trim($_POST['content'] ?? '');
    $parentId = !empty($_POST['parent_id']) ? intval($_POST['parent_id']) : null;

    if (empty($authorName) || empty($authorEmail) || empty($commentContent)) {
        $commentError = 'Please fill in all required fields.';
    } elseif (!filter_var($authorEmail, FILTER_VALIDATE_EMAIL)) {
        $commentError = 'Please enter a valid email address.';
    } else {
        $insertStmt = $pdo->prepare("INSERT INTO blog_comments (post_id, parent_id, author_name, author_email, content) VALUES (?, ?, ?, ?, ?)");
        $insertStmt->execute([$post['id'], $parentId, $authorName, $authorEmail, $commentContent]);
        $commentSuccess = true;
    }
}

// Get related posts
$relatedStmt = $pdo->prepare("SELECT p.*, c.name as category_name
                              FROM blog_posts p
                              LEFT JOIN blog_categories c ON p.category_id = c.id
                              WHERE p.id != ? AND p.status = 'published' AND p.published_at <= NOW()
                              AND (p.category_id = ? OR p.id IN (
                                  SELECT DISTINCT pt2.post_id FROM blog_post_tags pt1
                                  JOIN blog_post_tags pt2 ON pt1.tag_id = pt2.tag_id
                                  WHERE pt1.post_id = ?
                              ))
                              ORDER BY p.published_at DESC
                              LIMIT 3");
$relatedStmt->execute([$post['id'], $post['category_id'], $post['id']]);
$relatedPosts = $relatedStmt->fetchAll();

$page_title = $post['title'] . ' | Blog | ' . $site['name'];
include __DIR__ . '/includes/header.php';

// Initialize CMS for inline editing
if (!isset($cms)) {
    require_once __DIR__ . '/includes/cms/ContentManager.php';
    $cms = new ContentManager('blog-post-' . $post['id']);
}
?>

<article class="blog-post">
    <!-- Post Header -->
    <header class="blog-post-header" <?php if ($post['featured_image']): ?>style="background-image: linear-gradient(rgba(30, 26, 43, 0.7), rgba(30, 26, 43, 0.9)), url('<?= htmlspecialchars($post['featured_image']); ?>');"<?php endif; ?>>
        <div class="container narrow">
            <?php if ($post['category_name']): ?>
                <a href="/blog?category=<?= htmlspecialchars($post['category_slug']); ?>" class="post-category"><?= htmlspecialchars($post['category_name']); ?></a>
            <?php endif; ?>
            <h1><?= htmlspecialchars($post['title']); ?></h1>
            <div class="post-meta">
                <?php if ($post['author_name']): ?>
                    <span class="post-author">By <a href="/author/<?= htmlspecialchars($post['author_username'] ?? ''); ?>" class="author-link"><?= htmlspecialchars($post['author_name']); ?></a></span>
                <?php endif; ?>
                <span class="post-date"><?= date('F j, Y', strtotime($post['published_at'])); ?></span>
            </div>
            <?php if ($post['status'] === 'draft'): ?>
                <div class="draft-notice">This post is a draft and not publicly visible.</div>
            <?php endif; ?>
        </div>
    </header>

    <!-- Post Content -->
    <div class="blog-post-content">
        <div class="container narrow">
            <div class="post-body" data-cms-editable="post_content_<?= $post['id']; ?>" data-cms-page="blog-post-<?= $post['id']; ?>" data-cms-type="html">
                <?= $post['content']; ?>
            </div>

            <?php if (!empty($postTags)): ?>
                <div class="post-tags">
                    <span>Topics:</span>
                    <?php foreach ($postTags as $tag): ?>
                        <a href="/blog?tag=<?= htmlspecialchars($tag['slug']); ?>" class="tag"><?= htmlspecialchars($tag['name']); ?></a>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <!-- Share Links -->
            <div class="post-share">
                <span>Share:</span>
                <a href="https://www.facebook.com/sharer/sharer.php?u=<?= urlencode('https://' . $_SERVER['HTTP_HOST'] . '/blog/' . $post['slug']); ?>" target="_blank" rel="noopener" class="share-link">Facebook</a>
                <a href="https://twitter.com/intent/tweet?url=<?= urlencode('https://' . $_SERVER['HTTP_HOST'] . '/blog/' . $post['slug']); ?>&text=<?= urlencode($post['title']); ?>" target="_blank" rel="noopener" class="share-link">Twitter</a>
                <a href="mailto:?subject=<?= urlencode($post['title']); ?>&body=<?= urlencode('Check out this post: https://' . $_SERVER['HTTP_HOST'] . '/blog/' . $post['slug']); ?>" class="share-link">Email</a>
            </div>
        </div>
    </div>

    <!-- Comments Section -->
    <section class="blog-comments">
        <div class="container narrow">
            <h2>Comments (<?= count($comments); ?>)</h2>

            <?php if ($commentSuccess): ?>
                <div class="alert alert-success">Thank you for your comment! It will appear after moderation.</div>
            <?php endif; ?>

            <?php if ($commentError): ?>
                <div class="alert alert-error"><?= htmlspecialchars($commentError); ?></div>
            <?php endif; ?>

            <?php
            $maxLength = 300; // Character limit before truncation
            ?>
            <?php if (!empty($comments)): ?>
                <div class="comments-list">
                    <?php foreach ($comments as $comment): ?>
                        <?php
                        $commentText = htmlspecialchars($comment['content']);
                        $isLong = strlen($comment['content']) > $maxLength;
                        ?>
                        <div class="comment" id="comment-<?= $comment['id']; ?>">
                            <div class="comment-header">
                                <strong class="comment-author"><?= htmlspecialchars($comment['author_name']); ?></strong>
                                <span class="comment-date"><?= date('M j, Y \a\t g:ia', strtotime($comment['created_at'])); ?></span>
                            </div>
                            <div class="comment-content <?= $isLong ? 'truncated' : ''; ?>" id="comment-content-<?= $comment['id']; ?>">
                                <?= nl2br($commentText); ?>
                            </div>
                            <?php if ($isLong): ?>
                                <button class="read-more-btn" onclick="toggleComment(<?= $comment['id']; ?>)">Read more</button>
                            <?php endif; ?>
                            <button class="reply-btn" onclick="showReplyForm(<?= $comment['id']; ?>)">Reply</button>

                            <!-- Reply Form (hidden by default) -->
                            <div class="reply-form-container" id="reply-form-<?= $comment['id']; ?>" style="display: none;">
                                <form method="POST" class="comment-form">
                                    <input type="hidden" name="parent_id" value="<?= $comment['id']; ?>">
                                    <div class="form-row">
                                        <input type="text" name="author_name" placeholder="Your Name *" required>
                                        <input type="email" name="author_email" placeholder="Your Email *" required>
                                    </div>
                                    <textarea name="content" placeholder="Your reply..." required></textarea>
                                    <button type="submit" name="submit_comment" class="btn btn-primary">Post Reply</button>
                                    <button type="button" class="btn btn-outline" onclick="hideReplyForm(<?= $comment['id']; ?>)">Cancel</button>
                                </form>
                            </div>

                            <!-- Replies -->
                            <?php if (isset($repliesByParent[$comment['id']])): ?>
                                <div class="comment-replies">
                                    <?php foreach ($repliesByParent[$comment['id']] as $reply): ?>
                                        <?php
                                        $replyText = htmlspecialchars($reply['content']);
                                        $isReplyLong = strlen($reply['content']) > $maxLength;
                                        ?>
                                        <div class="comment reply">
                                            <div class="comment-header">
                                                <strong class="comment-author"><?= htmlspecialchars($reply['author_name']); ?></strong>
                                                <span class="comment-date"><?= date('M j, Y \a\t g:ia', strtotime($reply['created_at'])); ?></span>
                                            </div>
                                            <div class="comment-content <?= $isReplyLong ? 'truncated' : ''; ?>" id="comment-content-<?= $reply['id']; ?>">
                                                <?= nl2br($replyText); ?>
                                            </div>
                                            <?php if ($isReplyLong): ?>
                                                <button class="read-more-btn" onclick="toggleComment(<?= $reply['id']; ?>)">Read more</button>
                                            <?php endif; ?>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <!-- Comment Form -->
            <div class="comment-form-section">
                <h3>Leave a Comment</h3>
                <form method="POST" class="comment-form">
                    <div class="form-row">
                        <input type="text" name="author_name" placeholder="Your Name *" required>
                        <input type="email" name="author_email" placeholder="Your Email *" required>
                    </div>
                    <textarea name="content" placeholder="Share your thoughts..." rows="5" required></textarea>
                    <button type="submit" name="submit_comment" class="btn btn-primary">Post Comment</button>
                </form>
            </div>
        </div>
    </section>

    <?php if (!empty($relatedPosts)): ?>
    <!-- Related Posts -->
    <section class="related-posts">
        <div class="container">
            <h2>Related Posts</h2>
            <div class="blog-grid">
                <?php foreach ($relatedPosts as $related): ?>
                    <article class="blog-card">
                        <a href="/blog/<?= htmlspecialchars($related['slug']); ?>">
                            <?php if ($related['featured_image']): ?>
                                <div class="blog-card-image">
                                    <img src="<?= htmlspecialchars($related['featured_image']); ?>" alt="<?= htmlspecialchars($related['title']); ?>">
                                </div>
                            <?php endif; ?>
                            <div class="blog-card-content">
                                <?php if ($related['category_name']): ?>
                                    <span class="post-category"><?= htmlspecialchars($related['category_name']); ?></span>
                                <?php endif; ?>
                                <h3><?= htmlspecialchars($related['title']); ?></h3>
                                <p><?= htmlspecialchars($related['excerpt'] ?? ''); ?></p>
                            </div>
                        </a>
                    </article>
                <?php endforeach; ?>
            </div>
        </div>
    </section>
    <?php endif; ?>
</article>

<!-- Back to Blog -->
<section class="content-section">
    <div class="container" style="text-align: center;">
        <a href="/blog" class="btn btn-outline">&larr; Back to Blog</a>
    </div>
</section>

<script>
function showReplyForm(commentId) {
    document.getElementById('reply-form-' + commentId).style.display = 'block';
}
function hideReplyForm(commentId) {
    document.getElementById('reply-form-' + commentId).style.display = 'none';
}
function toggleComment(commentId) {
    var content = document.getElementById('comment-content-' + commentId);
    var btn = content.nextElementSibling;
    if (content.classList.contains('truncated')) {
        content.classList.remove('truncated');
        content.classList.add('expanded');
        btn.textContent = 'Show less';
    } else {
        content.classList.remove('expanded');
        content.classList.add('truncated');
        btn.textContent = 'Read more';
    }
}
</script>
<?php include __DIR__ . '/includes/newsletter.php'; ?>
<?php include __DIR__ . '/includes/footer.php'; ?>
