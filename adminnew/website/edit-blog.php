<?php
/**
 * Blog Post Editor - New Admin
 */
$page_title = 'Edit Blog Post';
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../../includes/db-config.php';

$pdo = getDbConnection();

$postId = $_GET['id'] ?? null;
$isNew = !$postId;

$post = [
    'id' => null,
    'title' => '',
    'slug' => '',
    'excerpt' => '',
    'content' => '',
    'featured_image' => '',
    'category_id' => null,
    'status' => 'draft',
    'published_at' => null,
];

// Load existing post
if ($postId) {
    $stmt = $pdo->prepare("SELECT * FROM blog_posts WHERE id = ?");
    $stmt->execute([$postId]);
    $existing = $stmt->fetch();
    if ($existing) {
        $post = $existing;
        $isNew = false;
    }
}

// Get categories
$categories = $pdo->query("SELECT * FROM blog_categories ORDER BY name")->fetchAll();

// Get all tags
$allTags = $pdo->query("SELECT * FROM blog_tags ORDER BY name")->fetchAll();

// Get post tags
$postTags = [];
if (!$isNew) {
    $tagStmt = $pdo->prepare("SELECT tag_id FROM blog_post_tags WHERE post_id = ?");
    $tagStmt->execute([$post['id']]);
    $postTags = $tagStmt->fetchAll(PDO::FETCH_COLUMN);
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = [
        'title' => trim($_POST['title']),
        'slug' => trim($_POST['slug']) ?: strtolower(preg_replace('/[^a-z0-9]+/i', '-', trim($_POST['title']))),
        'excerpt' => trim($_POST['excerpt']),
        'content' => $_POST['content'],
        'featured_image' => trim($_POST['featured_image']),
        'category_id' => $_POST['category_id'] ?: null,
        'status' => $_POST['status'],
        'published_at' => $_POST['published_at'] ?: null,
    ];

    $data['slug'] = trim(strtolower(preg_replace('/[^a-z0-9-]+/', '-', $data['slug'])), '-');

    if (empty($data['title'])) {
        $error_message = 'Title is required.';
    } else {
        try {
            // Check slug uniqueness
            $checkStmt = $pdo->prepare("SELECT id FROM blog_posts WHERE slug = ? AND id != ?");
            $checkStmt->execute([$data['slug'], $postId ?? 0]);
            if ($checkStmt->fetch()) {
                $data['slug'] .= '-' . time();
            }

            if ($data['status'] === 'published' && !$data['published_at']) {
                $data['published_at'] = date('Y-m-d H:i:s');
            }

            if ($isNew) {
                $stmt = $pdo->prepare("INSERT INTO blog_posts (title, slug, excerpt, content, featured_image, category_id, author_id, status, published_at)
                                       VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
                $stmt->execute([
                    $data['title'], $data['slug'], $data['excerpt'], $data['content'],
                    $data['featured_image'], $data['category_id'], $current_user['id'],
                    $data['status'], $data['published_at']
                ]);
                $postId = $pdo->lastInsertId();
                $success_message = 'Post created successfully.';
            } else {
                $stmt = $pdo->prepare("UPDATE blog_posts SET title = ?, slug = ?, excerpt = ?, content = ?,
                                       featured_image = ?, category_id = ?, status = ?, published_at = ?
                                       WHERE id = ?");
                $stmt->execute([
                    $data['title'], $data['slug'], $data['excerpt'], $data['content'],
                    $data['featured_image'], $data['category_id'],
                    $data['status'], $data['published_at'], $postId
                ]);
                $success_message = 'Post updated successfully.';
            }

            // Handle tags
            $selectedTags = $_POST['tags'] ?? [];
            $pdo->prepare("DELETE FROM blog_post_tags WHERE post_id = ?")->execute([$postId]);
            foreach ($selectedTags as $tagId) {
                $pdo->prepare("INSERT INTO blog_post_tags (post_id, tag_id) VALUES (?, ?)")->execute([$postId, $tagId]);
            }

            // Handle new tags
            $newTags = array_filter(array_map('trim', explode(',', $_POST['new_tags'] ?? '')));
            foreach ($newTags as $tagName) {
                $tagSlug = strtolower(preg_replace('/[^a-z0-9-]+/', '-', $tagName));
                $existingTag = $pdo->prepare("SELECT id FROM blog_tags WHERE slug = ?");
                $existingTag->execute([$tagSlug]);
                $tagRow = $existingTag->fetch();
                if ($tagRow) {
                    $tagId = $tagRow['id'];
                } else {
                    $pdo->prepare("INSERT INTO blog_tags (name, slug) VALUES (?, ?)")->execute([$tagName, $tagSlug]);
                    $tagId = $pdo->lastInsertId();
                }
                $pdo->prepare("INSERT IGNORE INTO blog_post_tags (post_id, tag_id) VALUES (?, ?)")->execute([$postId, $tagId]);
            }

            $post = array_merge($post, $data);
            $post['id'] = $postId;
            $isNew = false;

            $tagStmt = $pdo->prepare("SELECT tag_id FROM blog_post_tags WHERE post_id = ?");
            $tagStmt->execute([$postId]);
            $postTags = $tagStmt->fetchAll(PDO::FETCH_COLUMN);

            $allTags = $pdo->query("SELECT * FROM blog_tags ORDER BY name")->fetchAll();

        } catch (Exception $e) {
            $error_message = 'Database error: ' . $e->getMessage();
        }
    }
}
?>

<div class="admin-page-header">
    <div>
        <a href="/adminnew/blog" class="admin-text-muted" style="text-decoration: none; font-size: 0.875rem;">&larr; Back to Blog Posts</a>
        <h1 class="admin-page-title"><?= $isNew ? 'New Post' : 'Edit Post'; ?></h1>
    </div>
    <?php if (!$isNew): ?>
    <div class="admin-page-actions">
        <a href="/blog/<?= htmlspecialchars($post['slug']); ?>" target="_blank" class="admin-btn admin-btn-secondary">Preview</a>
    </div>
    <?php endif; ?>
</div>

<?php if (isset($success_message)): ?>
    <div class="admin-alert admin-alert-success"><?= htmlspecialchars($success_message); ?></div>
<?php endif; ?>

<?php if (isset($error_message)): ?>
    <div class="admin-alert admin-alert-error"><?= htmlspecialchars($error_message); ?></div>
<?php endif; ?>

<form method="POST">
    <div class="editor-grid">
        <!-- Main Content -->
        <div class="editor-main">
            <div class="admin-card">
                <div class="admin-card-body">
                    <div class="admin-form-group">
                        <label class="admin-form-label">Title *</label>
                        <input type="text" id="title" name="title" class="admin-form-input" value="<?= htmlspecialchars($post['title']); ?>" required placeholder="Enter post title..." style="font-size: 1.25rem; font-weight: 600;">
                    </div>

                    <div class="admin-form-group">
                        <label class="admin-form-label">URL Slug</label>
                        <input type="text" id="slug" name="slug" class="admin-form-input" value="<?= htmlspecialchars($post['slug']); ?>" placeholder="auto-generated-from-title">
                        <small class="admin-text-muted">Leave blank to auto-generate from title.</small>
                    </div>

                    <div class="admin-form-group">
                        <label class="admin-form-label">Excerpt</label>
                        <textarea id="excerpt" name="excerpt" class="admin-form-textarea" rows="3" placeholder="Brief summary shown in post listings..."><?= htmlspecialchars($post['excerpt'] ?? ''); ?></textarea>
                    </div>

                    <div class="admin-form-group">
                        <label class="admin-form-label">Content</label>
                        <textarea id="content" name="content" class="admin-form-textarea" rows="20" placeholder="Write your post content here... HTML is supported."><?= htmlspecialchars($post['content'] ?? ''); ?></textarea>
                        <small class="admin-text-muted">HTML is supported. Use &lt;p&gt;, &lt;h2&gt;, &lt;ul&gt;, &lt;blockquote&gt; etc.</small>
                    </div>
                </div>
            </div>
        </div>

        <!-- Sidebar -->
        <div class="editor-sidebar">
            <!-- Publish Box -->
            <div class="admin-card">
                <div class="admin-card-header">
                    <h3 class="admin-card-title">Publish</h3>
                </div>
                <div class="admin-card-body">
                    <div class="admin-form-group">
                        <label class="admin-form-label">Status</label>
                        <select id="status" name="status" class="admin-form-select">
                            <option value="draft" <?= ($post['status'] ?? '') === 'draft' ? 'selected' : ''; ?>>Draft</option>
                            <option value="published" <?= ($post['status'] ?? '') === 'published' ? 'selected' : ''; ?>>Published</option>
                        </select>
                    </div>

                    <div class="admin-form-group">
                        <label class="admin-form-label">Publish Date</label>
                        <input type="datetime-local" id="published_at" name="published_at" class="admin-form-input"
                               value="<?= $post['published_at'] ? date('Y-m-d\TH:i', strtotime($post['published_at'])) : ''; ?>">
                        <small class="admin-text-muted">Leave blank to publish immediately.</small>
                    </div>

                    <button type="submit" class="admin-btn admin-btn-primary" style="width: 100%;"><?= $isNew ? 'Create Post' : 'Update Post'; ?></button>
                </div>
            </div>

            <!-- Category -->
            <div class="admin-card">
                <div class="admin-card-header">
                    <h3 class="admin-card-title">Category</h3>
                </div>
                <div class="admin-card-body">
                    <select id="category_id" name="category_id" class="admin-form-select">
                        <option value="">— No Category —</option>
                        <?php foreach ($categories as $cat): ?>
                            <option value="<?= $cat['id']; ?>" <?= ($post['category_id'] ?? '') == $cat['id'] ? 'selected' : ''; ?>>
                                <?= htmlspecialchars($cat['name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <!-- Tags -->
            <div class="admin-card">
                <div class="admin-card-header">
                    <h3 class="admin-card-title">Tags</h3>
                </div>
                <div class="admin-card-body">
                    <?php if (!empty($allTags)): ?>
                        <div class="tag-checkboxes">
                            <?php foreach ($allTags as $tag): ?>
                                <label class="tag-checkbox-label">
                                    <input type="checkbox" name="tags[]" value="<?= $tag['id']; ?>" <?= in_array($tag['id'], $postTags) ? 'checked' : ''; ?>>
                                    <?= htmlspecialchars($tag['name']); ?>
                                </label>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                    <div class="admin-form-group" style="margin-top: 1rem;">
                        <input type="text" name="new_tags" class="admin-form-input" placeholder="Add new tags (comma separated)">
                        <small class="admin-text-muted">e.g., faith, prayer, community</small>
                    </div>
                </div>
            </div>

            <!-- Featured Image -->
            <div class="admin-card">
                <div class="admin-card-header">
                    <h3 class="admin-card-title">Featured Image</h3>
                </div>
                <div class="admin-card-body">
                    <input type="hidden" id="featured_image" name="featured_image" value="<?= htmlspecialchars($post['featured_image'] ?? ''); ?>">

                    <div id="featured-image-preview" class="image-preview-box" style="<?= $post['featured_image'] ? '' : 'display: none;'; ?>">
                        <img src="<?= htmlspecialchars($post['featured_image'] ?? ''); ?>" alt="Preview">
                        <button type="button" class="admin-btn admin-btn-sm admin-btn-danger" onclick="removeFeaturedImage()">Remove</button>
                    </div>

                    <div id="featured-image-buttons" style="<?= $post['featured_image'] ? 'display: none;' : ''; ?>">
                        <input type="text" id="featured_image_url" class="admin-form-input" placeholder="Image URL or paste from media library" onchange="setFeaturedImage(this.value)" style="margin-bottom: 0.5rem;">
                        <small class="admin-text-muted">Paste an image URL or upload to the <a href="/adminnew/media" target="_blank">media library</a> first.</small>
                    </div>
                </div>
            </div>
        </div>
    </div>
</form>

<style <?= csp_nonce(); ?>>
.editor-grid {
    display: grid;
    grid-template-columns: 1fr 320px;
    gap: 1.5rem;
    align-items: start;
}
@media (max-width: 1024px) {
    .editor-grid {
        grid-template-columns: 1fr;
    }
}
.editor-main {
    min-width: 0;
}
.editor-sidebar {
    display: flex;
    flex-direction: column;
    gap: 1rem;
}
.editor-sidebar .admin-card {
    margin-bottom: 0;
}

.tag-checkboxes {
    display: flex;
    flex-wrap: wrap;
    gap: 0.5rem;
}
.tag-checkbox-label {
    display: flex;
    align-items: center;
    gap: 0.25rem;
    padding: 0.25rem 0.5rem;
    background: var(--admin-bg);
    border-radius: var(--admin-radius-sm);
    cursor: pointer;
    font-size: 0.875rem;
}
.tag-checkbox-label:has(input:checked) {
    background: color-mix(in srgb, var(--current-app-color) 15%, transparent);
    color: var(--current-app-color);
}
.tag-checkbox-label input {
    display: none;
}

.image-preview-box {
    margin-bottom: 0.75rem;
}
.image-preview-box img {
    width: 100%;
    border-radius: var(--admin-radius);
    margin-bottom: 0.5rem;
}

.admin-alert {
    padding: 1rem;
    border-radius: var(--admin-radius);
    margin-bottom: 1rem;
}
.admin-alert-success {
    background: rgba(16, 185, 129, 0.1);
    color: var(--admin-success);
    border: 1px solid var(--admin-success);
}
.admin-alert-error {
    background: rgba(239, 68, 68, 0.1);
    color: var(--admin-danger);
    border: 1px solid var(--admin-danger);
}
</style>

<script>
function setFeaturedImage(url) {
    if (url) {
        document.getElementById('featured_image').value = url;
        document.querySelector('#featured-image-preview img').src = url;
        document.getElementById('featured-image-preview').style.display = 'block';
        document.getElementById('featured-image-buttons').style.display = 'none';
    }
}

function removeFeaturedImage() {
    document.getElementById('featured_image').value = '';
    document.getElementById('featured_image_url').value = '';
    document.getElementById('featured-image-preview').style.display = 'none';
    document.getElementById('featured-image-buttons').style.display = 'block';
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
