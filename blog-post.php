<?php
require __DIR__ . '/config.php';
require_once __DIR__ . '/includes/db-config.php';
require_once __DIR__ . '/includes/Auth.php';
require_once __DIR__ . '/includes/profanity-filter.php';
require_once __DIR__ . '/includes/BibleVerseLinker.php';

$pdo = getDbConnection();
$auth = new Auth($pdo);
$currentUser = $auth->user();

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

// Allow preview for logged-in admins (session already started by Auth)
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

// Get approved comments with user data
$commentStmt = $pdo->prepare("SELECT c.*, u.full_name as user_full_name, u.username as user_username, u.avatar as user_avatar, u.avatar_color as user_avatar_color
                              FROM blog_comments c
                              LEFT JOIN users u ON c.user_id = u.id
                              WHERE c.post_id = ? AND c.status = 'approved' AND c.parent_id IS NULL
                              ORDER BY c.created_at ASC");
$commentStmt->execute([$post['id']]);
$comments = $commentStmt->fetchAll();

// Get comment replies with user data
$replyStmt = $pdo->prepare("SELECT c.*, u.full_name as user_full_name, u.username as user_username, u.avatar as user_avatar, u.avatar_color as user_avatar_color
                            FROM blog_comments c
                            LEFT JOIN users u ON c.user_id = u.id
                            WHERE c.post_id = ? AND c.status = 'approved' AND c.parent_id IS NOT NULL
                            ORDER BY c.created_at ASC");
$replyStmt->execute([$post['id']]);
$replies = $replyStmt->fetchAll();

// Group replies by parent
$repliesByParent = [];
foreach ($replies as $reply) {
    $repliesByParent[$reply['parent_id']][] = $reply;
}

// Handle comment submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_comment'])) {
    $commentContent = trim($_POST['content'] ?? '');
    $parentId = !empty($_POST['parent_id']) ? intval($_POST['parent_id']) : null;

    if (empty($commentContent)) {
        $_SESSION['comment_error'] = 'Please enter a comment.';
    } else {
        // Check profanity
        $profanityCheck = checkProfanity($commentContent);

        if ($currentUser) {
            // Logged-in user: use their data
            $userId = $currentUser['id'];
            $authorName = $currentUser['full_name'] ?? $currentUser['username'];
            $authorEmail = $currentUser['email'];

            // Auto-approve if no profanity, otherwise send for review
            $status = $profanityCheck['has_profanity'] ? 'pending' : 'approved';

            $insertStmt = $pdo->prepare("INSERT INTO blog_comments (post_id, user_id, parent_id, author_name, author_email, content, status) VALUES (?, ?, ?, ?, ?, ?, ?)");
            $insertStmt->execute([$post['id'], $userId, $parentId, $authorName, $authorEmail, $commentContent, $status]);

            $_SESSION['comment_success'] = ($status === 'approved') ? 'auto' : 'pending';
        } else {
            // Anonymous user: require name and email
            $authorName = trim($_POST['author_name'] ?? '');
            $authorEmail = trim($_POST['author_email'] ?? '');

            if (empty($authorName) || empty($authorEmail)) {
                $_SESSION['comment_error'] = 'Please fill in all required fields.';
            } elseif (!filter_var($authorEmail, FILTER_VALIDATE_EMAIL)) {
                $_SESSION['comment_error'] = 'Please enter a valid email address.';
            } else {
                $insertStmt = $pdo->prepare("INSERT INTO blog_comments (post_id, parent_id, author_name, author_email, content, status) VALUES (?, ?, ?, ?, ?, 'pending')");
                $insertStmt->execute([$post['id'], $parentId, $authorName, $authorEmail, $commentContent]);
                $_SESSION['comment_success'] = 'pending';
            }
        }
    }

    // Redirect to prevent form resubmission and scroll to comments
    header('Location: /blog/' . $slug . '#comments');
    exit;
}

// Get flash messages from session
$commentSuccess = $_SESSION['comment_success'] ?? false;
$commentError = $_SESSION['comment_error'] ?? '';
$commentAutoApproved = ($commentSuccess === 'auto');
unset($_SESSION['comment_success'], $_SESSION['comment_error']);

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
                <?= linkBibleVerses($post['content']); ?>
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
    <section id="comments" class="blog-comments">
        <div class="container narrow">
            <h2>Comments (<?= count($comments); ?>)</h2>

            <?php if ($commentSuccess): ?>
                <?php if ($commentAutoApproved): ?>
                    <div class="alert alert-success">Your comment has been posted!</div>
                <?php else: ?>
                    <div class="alert alert-success">Thank you for your comment! It will appear after moderation.</div>
                <?php endif; ?>
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
                        $displayName = $comment['user_id'] ? ($comment['user_full_name'] ?? $comment['author_name']) : $comment['author_name'];
                        ?>
                        <div class="comment" id="comment-<?= $comment['id']; ?>">
                            <div class="comment-header">
                                <?php if ($comment['user_id'] && $comment['user_avatar']): ?>
                                    <img src="<?= htmlspecialchars($comment['user_avatar']); ?>" alt="" class="comment-avatar">
                                <?php elseif ($comment['user_id']): ?>
                                    <div class="comment-avatar comment-avatar-initials" style="background-color: <?= htmlspecialchars($comment['user_avatar_color'] ?? '#4b2679'); ?>">
                                        <?= strtoupper(substr($displayName, 0, 1)); ?>
                                    </div>
                                <?php endif; ?>
                                <?php if ($comment['user_id'] && $comment['user_username']): ?>
                                    <a href="/user/<?= htmlspecialchars($comment['user_username']); ?>" class="comment-author-link"><?= htmlspecialchars($displayName); ?></a>
                                <?php else: ?>
                                    <strong class="comment-author"><?= htmlspecialchars($displayName); ?></strong>
                                <?php endif; ?>
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
                                <div class="form-message reply-form-message" style="display: none;"></div>
                                <form method="POST" class="comment-form reply-comment-form" data-comment-type="blog" data-content-id="<?= $post['id']; ?>" data-parent-id="<?= $comment['id']; ?>">
                                    <?php if ($currentUser): ?>
                                        <div class="comment-form-user">
                                            <?php if ($currentUser['avatar']): ?>
                                                <img src="<?= htmlspecialchars($currentUser['avatar']); ?>" alt="" class="comment-avatar">
                                            <?php else: ?>
                                                <div class="comment-avatar comment-avatar-initials" style="background-color: <?= htmlspecialchars($currentUser['avatar_color'] ?? '#4b2679'); ?>">
                                                    <?= strtoupper(substr($currentUser['full_name'] ?? $currentUser['username'], 0, 1)); ?>
                                                </div>
                                            <?php endif; ?>
                                            <span class="comment-form-username"><?= htmlspecialchars($currentUser['full_name'] ?? $currentUser['username']); ?></span>
                                        </div>
                                    <?php else: ?>
                                        <div class="form-row">
                                            <input type="text" name="author_name" placeholder="Your Name *" required>
                                            <input type="email" name="author_email" placeholder="Your Email *" required>
                                        </div>
                                    <?php endif; ?>
                                    <textarea name="content" placeholder="Your reply..." required></textarea>
                                    <div class="form-actions">
                                        <button type="submit" class="btn btn-primary">
                                            <span class="btn-text">Post Reply</span>
                                            <span class="btn-spinner" style="display: none;">
                                                <svg class="spinner" width="20" height="20" viewBox="0 0 24 24">
                                                    <circle cx="12" cy="12" r="10" stroke="currentColor" stroke-width="3" fill="none" opacity="0.25"/>
                                                    <path d="M12 2a10 10 0 0 1 10 10" stroke="currentColor" stroke-width="3" fill="none" stroke-linecap="round">
                                                        <animateTransform attributeName="transform" type="rotate" from="0 12 12" to="360 12 12" dur="1s" repeatCount="indefinite"/>
                                                    </path>
                                                </svg>
                                            </span>
                                        </button>
                                        <button type="button" class="btn btn-outline" onclick="hideReplyForm(<?= $comment['id']; ?>)">Cancel</button>
                                    </div>
                                </form>
                            </div>

                            <!-- Replies -->
                            <?php if (isset($repliesByParent[$comment['id']])): ?>
                                <div class="comment-replies">
                                    <?php foreach ($repliesByParent[$comment['id']] as $reply): ?>
                                        <?php
                                        $replyText = htmlspecialchars($reply['content']);
                                        $isReplyLong = strlen($reply['content']) > $maxLength;
                                        $replyDisplayName = $reply['user_id'] ? ($reply['user_full_name'] ?? $reply['author_name']) : $reply['author_name'];
                                        ?>
                                        <div class="comment reply">
                                            <div class="comment-header">
                                                <?php if ($reply['user_id'] && $reply['user_avatar']): ?>
                                                    <img src="<?= htmlspecialchars($reply['user_avatar']); ?>" alt="" class="comment-avatar">
                                                <?php elseif ($reply['user_id']): ?>
                                                    <div class="comment-avatar comment-avatar-initials" style="background-color: <?= htmlspecialchars($reply['user_avatar_color'] ?? '#4b2679'); ?>">
                                                        <?= strtoupper(substr($replyDisplayName, 0, 1)); ?>
                                                    </div>
                                                <?php endif; ?>
                                                <?php if ($reply['user_id'] && $reply['user_username']): ?>
                                                    <a href="/user/<?= htmlspecialchars($reply['user_username']); ?>" class="comment-author-link"><?= htmlspecialchars($replyDisplayName); ?></a>
                                                <?php else: ?>
                                                    <strong class="comment-author"><?= htmlspecialchars($replyDisplayName); ?></strong>
                                                <?php endif; ?>
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
                <div class="form-message comment-form-message" id="main-comment-message" style="display: none;"></div>
                <?php if ($currentUser): ?>
                    <form method="POST" class="comment-form" id="main-comment-form" data-comment-type="blog" data-content-id="<?= $post['id']; ?>">
                        <div class="comment-form-user">
                            <?php if ($currentUser['avatar']): ?>
                                <img src="<?= htmlspecialchars($currentUser['avatar']); ?>" alt="" class="comment-avatar">
                            <?php else: ?>
                                <div class="comment-avatar comment-avatar-initials" style="background-color: <?= htmlspecialchars($currentUser['avatar_color'] ?? '#4b2679'); ?>">
                                    <?= strtoupper(substr($currentUser['full_name'] ?? $currentUser['username'], 0, 1)); ?>
                                </div>
                            <?php endif; ?>
                            <span class="comment-form-username"><?= htmlspecialchars($currentUser['full_name'] ?? $currentUser['username']); ?></span>
                        </div>
                        <textarea name="content" placeholder="Share your thoughts..." rows="5" required></textarea>
                        <button type="submit" class="btn btn-primary">
                            <span class="btn-text">Post Comment</span>
                            <span class="btn-spinner" style="display: none;">
                                <svg class="spinner" width="20" height="20" viewBox="0 0 24 24">
                                    <circle cx="12" cy="12" r="10" stroke="currentColor" stroke-width="3" fill="none" opacity="0.25"/>
                                    <path d="M12 2a10 10 0 0 1 10 10" stroke="currentColor" stroke-width="3" fill="none" stroke-linecap="round">
                                        <animateTransform attributeName="transform" type="rotate" from="0 12 12" to="360 12 12" dur="1s" repeatCount="indefinite"/>
                                    </path>
                                </svg>
                            </span>
                        </button>
                    </form>
                <?php else: ?>
                    <form method="POST" class="comment-form" id="main-comment-form" data-comment-type="blog" data-content-id="<?= $post['id']; ?>">
                        <div class="form-row">
                            <input type="text" name="author_name" placeholder="Your Name *" required>
                            <input type="email" name="author_email" placeholder="Your Email *" required>
                        </div>
                        <textarea name="content" placeholder="Share your thoughts..." rows="5" required></textarea>
                        <button type="submit" class="btn btn-primary">
                            <span class="btn-text">Post Comment</span>
                            <span class="btn-spinner" style="display: none;">
                                <svg class="spinner" width="20" height="20" viewBox="0 0 24 24">
                                    <circle cx="12" cy="12" r="10" stroke="currentColor" stroke-width="3" fill="none" opacity="0.25"/>
                                    <path d="M12 2a10 10 0 0 1 10 10" stroke="currentColor" stroke-width="3" fill="none" stroke-linecap="round">
                                        <animateTransform attributeName="transform" type="rotate" from="0 12 12" to="360 12 12" dur="1s" repeatCount="indefinite"/>
                                    </path>
                                </svg>
                            </span>
                        </button>
                    </form>
                    <p class="comment-login-prompt">Have an account? <a href="/login?redirect=<?= urlencode('/blog/' . $post['slug']); ?>">Log in</a> to post instantly.</p>
                <?php endif; ?>
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

// AJAX Comment Submission
async function submitComment(form, messageEl) {
    const btn = form.querySelector('button[type="submit"]');
    const btnText = btn.querySelector('.btn-text');
    const btnSpinner = btn.querySelector('.btn-spinner');

    // Show loading state
    btn.disabled = true;
    if (btnText) btnText.style.display = 'none';
    if (btnSpinner) btnSpinner.style.display = 'inline-block';
    messageEl.style.display = 'none';

    const formData = new FormData(form);
    formData.append('comment_type', form.dataset.commentType);
    formData.append('content_id', form.dataset.contentId);
    if (form.dataset.parentId) {
        formData.append('parent_id', form.dataset.parentId);
    }

    try {
        const response = await fetch('/api/comments/submit', {
            method: 'POST',
            body: formData
        });

        const data = await response.json();

        if (data.success) {
            messageEl.className = 'form-message success';
            messageEl.textContent = data.message;
            messageEl.style.display = 'block';
            form.reset();

            // If comment was auto-approved, add it to the page
            if (data.approved && data.comment) {
                const commentsContainer = document.querySelector('.comments-list') || document.querySelector('.comment-form-section');
                if (form.dataset.parentId) {
                    // Reply - find the parent comment's replies section
                    const parentComment = document.getElementById('reply-form-' + form.dataset.parentId).closest('.comment');
                    let repliesContainer = parentComment.querySelector('.comment-replies');
                    if (!repliesContainer) {
                        repliesContainer = document.createElement('div');
                        repliesContainer.className = 'comment-replies';
                        parentComment.appendChild(repliesContainer);
                    }
                    repliesContainer.insertAdjacentHTML('beforeend', data.comment);
                    hideReplyForm(form.dataset.parentId);
                } else {
                    // Main comment - add to comments list
                    const commentsList = document.querySelector('.comments-list');
                    if (commentsList) {
                        commentsList.insertAdjacentHTML('beforeend', data.comment);
                    } else {
                        // No comments yet, create the list
                        const section = document.querySelector('.comment-form-section');
                        section.insertAdjacentHTML('beforebegin', '<div class="comments-list">' + data.comment + '</div>');
                    }
                }

                // Highlight new comment briefly
                setTimeout(() => {
                    const newComment = document.querySelector('.new-comment');
                    if (newComment) {
                        newComment.scrollIntoView({ behavior: 'smooth', block: 'center' });
                        newComment.classList.add('highlight');
                        setTimeout(() => newComment.classList.remove('new-comment', 'highlight'), 2000);
                    }
                }, 100);
            }
        } else {
            messageEl.className = 'form-message error';
            messageEl.textContent = data.error || 'Failed to submit comment.';
            messageEl.style.display = 'block';
        }
    } catch (error) {
        messageEl.className = 'form-message error';
        messageEl.textContent = 'Something went wrong. Please try again.';
        messageEl.style.display = 'block';
    }

    // Reset button
    btn.disabled = false;
    if (btnText) btnText.style.display = 'inline';
    if (btnSpinner) btnSpinner.style.display = 'none';
}

// Main comment form
const mainForm = document.getElementById('main-comment-form');
if (mainForm) {
    mainForm.addEventListener('submit', function(e) {
        e.preventDefault();
        submitComment(this, document.getElementById('main-comment-message'));
    });
}

// Reply forms
document.querySelectorAll('.reply-comment-form').forEach(function(form) {
    form.addEventListener('submit', function(e) {
        e.preventDefault();
        const messageEl = this.closest('.reply-form-container').querySelector('.reply-form-message');
        submitComment(this, messageEl);
    });
});
</script>
<?php include __DIR__ . '/includes/newsletter.php'; ?>
<?php include __DIR__ . '/includes/footer.php'; ?>
