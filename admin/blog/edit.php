<?php
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

    // Clean slug
    $data['slug'] = trim(strtolower(preg_replace('/[^a-z0-9-]+/', '-', $data['slug'])), '-');

    // Validate
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

            // Set published_at if publishing and not set
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

            // Remove existing tags
            $pdo->prepare("DELETE FROM blog_post_tags WHERE post_id = ?")->execute([$postId]);

            // Add new tags
            foreach ($selectedTags as $tagId) {
                $pdo->prepare("INSERT INTO blog_post_tags (post_id, tag_id) VALUES (?, ?)")->execute([$postId, $tagId]);
            }

            // Handle new tags
            $newTags = array_filter(array_map('trim', explode(',', $_POST['new_tags'] ?? '')));
            foreach ($newTags as $tagName) {
                $tagSlug = strtolower(preg_replace('/[^a-z0-9-]+/', '-', $tagName));
                // Check if exists
                $existingTag = $pdo->prepare("SELECT id FROM blog_tags WHERE slug = ?");
                $existingTag->execute([$tagSlug]);
                $tagRow = $existingTag->fetch();
                if ($tagRow) {
                    $tagId = $tagRow['id'];
                } else {
                    $pdo->prepare("INSERT INTO blog_tags (name, slug) VALUES (?, ?)")->execute([$tagName, $tagSlug]);
                    $tagId = $pdo->lastInsertId();
                }
                // Link to post
                $pdo->prepare("INSERT IGNORE INTO blog_post_tags (post_id, tag_id) VALUES (?, ?)")->execute([$postId, $tagId]);
            }

            // Update post data for form
            $post = array_merge($post, $data);
            $post['id'] = $postId;
            $isNew = false;

            // Refresh post tags
            $tagStmt = $pdo->prepare("SELECT tag_id FROM blog_post_tags WHERE post_id = ?");
            $tagStmt->execute([$postId]);
            $postTags = $tagStmt->fetchAll(PDO::FETCH_COLUMN);

            // Refresh all tags
            $allTags = $pdo->query("SELECT * FROM blog_tags ORDER BY name")->fetchAll();

        } catch (Exception $e) {
            $error_message = 'Database error: ' . $e->getMessage();
        }
    }
}
?>

<div style="margin-bottom: 1.5rem;">
    <a href="/admin/blog" style="color: #667eea; text-decoration: none;">&larr; Back to Blog Posts</a>
</div>

<?php if (isset($success_message)): ?>
    <div class="alert alert-success"><?= htmlspecialchars($success_message); ?></div>
<?php endif; ?>

<?php if (isset($error_message)): ?>
    <div class="alert alert-error"><?= htmlspecialchars($error_message); ?></div>
<?php endif; ?>

<form method="POST">
    <div style="display: grid; grid-template-columns: 1fr 300px; gap: 2rem;">
        <!-- Main Content -->
        <div>
            <div class="card">
                <div class="card-header">
                    <h2><?= $isNew ? 'Create New Post' : 'Edit Post'; ?></h2>
                    <?php if (!$isNew): ?>
                        <a href="/blog/<?= htmlspecialchars($post['slug']); ?>" target="_blank" class="btn btn-outline">Preview</a>
                    <?php endif; ?>
                </div>

                <div class="form-group">
                    <label for="title">Title *</label>
                    <input type="text" id="title" name="title" value="<?= htmlspecialchars($post['title']); ?>" required placeholder="Enter post title..." style="font-size: 1.25rem; font-weight: 600;">
                </div>

                <div class="form-group">
                    <label for="slug">URL Slug</label>
                    <input type="text" id="slug" name="slug" value="<?= htmlspecialchars($post['slug']); ?>" placeholder="auto-generated-from-title">
                    <div class="form-help">Leave blank to auto-generate from title.</div>
                </div>

                <div class="form-group">
                    <label for="excerpt">Excerpt</label>
                    <textarea id="excerpt" name="excerpt" rows="3" placeholder="Brief summary shown in post listings..."><?= htmlspecialchars($post['excerpt'] ?? ''); ?></textarea>
                </div>

                <div class="form-group">
                    <label for="content">Content</label>
                    <textarea id="content" name="content" rows="20" placeholder="Write your post content here... HTML is supported."><?= htmlspecialchars($post['content'] ?? ''); ?></textarea>
                    <div class="form-help">HTML is supported. Use &lt;p&gt;, &lt;h2&gt;, &lt;ul&gt;, &lt;blockquote&gt; etc.</div>
                </div>
            </div>
        </div>

        <!-- Sidebar -->
        <div>
            <!-- Publish Box -->
            <div class="card">
                <div class="card-header">
                    <h3 style="margin: 0;">Publish</h3>
                </div>

                <div class="form-group">
                    <label for="status">Status</label>
                    <select id="status" name="status">
                        <option value="draft" <?= ($post['status'] ?? '') === 'draft' ? 'selected' : ''; ?>>Draft</option>
                        <option value="published" <?= ($post['status'] ?? '') === 'published' ? 'selected' : ''; ?>>Published</option>
                    </select>
                </div>

                <div class="form-group">
                    <label for="published_at">Publish Date</label>
                    <input type="datetime-local" id="published_at" name="published_at"
                           value="<?= $post['published_at'] ? date('Y-m-d\TH:i', strtotime($post['published_at'])) : ''; ?>">
                    <div class="form-help">Leave blank to publish immediately.</div>
                </div>

                <div style="display: flex; gap: 0.5rem;">
                    <button type="submit" class="btn btn-primary" style="flex: 1;"><?= $isNew ? 'Create Post' : 'Update Post'; ?></button>
                </div>
            </div>

            <!-- Category -->
            <div class="card">
                <div class="card-header">
                    <h3 style="margin: 0;">Category</h3>
                </div>
                <div class="form-group" style="margin-bottom: 0;">
                    <select id="category_id" name="category_id">
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
            <div class="card">
                <div class="card-header">
                    <h3 style="margin: 0;">Tags</h3>
                </div>
                <?php if (!empty($allTags)): ?>
                    <div style="display: flex; flex-wrap: wrap; gap: 0.5rem; margin-bottom: 1rem;">
                        <?php foreach ($allTags as $tag): ?>
                            <label style="display: flex; align-items: center; gap: 0.25rem; padding: 0.25rem 0.5rem; background: #f1f5f9; border-radius: 0.25rem; cursor: pointer; font-size: 0.875rem;">
                                <input type="checkbox" name="tags[]" value="<?= $tag['id']; ?>" <?= in_array($tag['id'], $postTags) ? 'checked' : ''; ?>>
                                <?= htmlspecialchars($tag['name']); ?>
                            </label>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
                <div class="form-group" style="margin-bottom: 0;">
                    <input type="text" name="new_tags" placeholder="Add new tags (comma separated)">
                    <div class="form-help">e.g., faith, prayer, community</div>
                </div>
            </div>

            <!-- Featured Image -->
            <div class="card">
                <div class="card-header">
                    <h3 style="margin: 0;">Featured Image</h3>
                </div>
                <div class="form-group" style="margin-bottom: 0;">
                    <input type="text" id="featured_image" name="featured_image" value="<?= htmlspecialchars($post['featured_image'] ?? ''); ?>" placeholder="/assets/imgs/blog/...">
                    <?php if ($post['featured_image']): ?>
                        <img src="<?= htmlspecialchars($post['featured_image']); ?>" alt="Preview" style="width: 100%; margin-top: 0.5rem; border-radius: 0.5rem;">
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</form>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
