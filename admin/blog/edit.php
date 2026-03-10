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
                    <input type="hidden" id="featured_image" name="featured_image" value="<?= htmlspecialchars($post['featured_image'] ?? ''); ?>">

                    <div id="featured-image-preview" style="<?= $post['featured_image'] ? '' : 'display: none;'; ?> margin-bottom: 0.75rem;">
                        <img src="<?= htmlspecialchars($post['featured_image'] ?? ''); ?>" alt="Preview" style="width: 100%; border-radius: 0.5rem;">
                        <button type="button" onclick="removeFeaturedImage()" class="btn btn-xs btn-danger" style="margin-top: 0.5rem;">Remove Image</button>
                    </div>

                    <div id="featured-image-buttons" style="<?= $post['featured_image'] ? 'display: none;' : ''; ?> display: flex; gap: 0.5rem;">
                        <button type="button" onclick="openMediaPicker('featured')" class="btn btn-sm btn-outline" style="flex: 1;">Choose from Library</button>
                        <label class="btn btn-sm btn-primary" style="flex: 1; text-align: center; cursor: pointer;">
                            Upload
                            <input type="file" id="featured-image-upload" accept="image/*" style="display: none;" onchange="uploadImage(this, 'featured')">
                        </label>
                    </div>
                </div>
            </div>

            <!-- Thumbnail (for card listings) -->
            <div class="card">
                <div class="card-header">
                    <h3 style="margin: 0;">Thumbnail</h3>
                    <span style="font-size: 0.75rem; color: var(--color-text-muted);">For blog listings. Falls back to featured image if not set.</span>
                </div>
                <div class="form-group" style="margin-bottom: 0;">
                    <input type="hidden" id="thumbnail" name="thumbnail" value="<?= htmlspecialchars($post['thumbnail'] ?? ''); ?>">

                    <div id="thumbnail-preview" style="<?= ($post['thumbnail'] ?? '') ? '' : 'display: none;'; ?> margin-bottom: 0.75rem;">
                        <img src="<?= htmlspecialchars($post['thumbnail'] ?? ''); ?>" alt="Thumbnail" style="width: 100%; max-width: 200px; border-radius: 0.5rem;">
                        <button type="button" onclick="removeThumbnail()" class="btn btn-xs btn-danger" style="margin-top: 0.5rem;">Remove</button>
                    </div>

                    <div id="thumbnail-buttons" style="<?= ($post['thumbnail'] ?? '') ? 'display: none;' : ''; ?> display: flex; gap: 0.5rem;">
                        <button type="button" onclick="openMediaPicker('thumbnail')" class="btn btn-sm btn-outline" style="flex: 1;">Choose from Library</button>
                        <label class="btn btn-sm btn-primary" style="flex: 1; text-align: center; cursor: pointer;">
                            Upload
                            <input type="file" id="thumbnail-upload" accept="image/*" style="display: none;" onchange="uploadImage(this, 'thumbnail')">
                        </label>
                    </div>
                </div>
            </div>
        </div>
    </div>
</form>

<!-- Media Picker Modal -->
<div id="media-picker-modal" class="media-picker-overlay" style="display: none;">
    <div class="media-picker-modal">
        <div class="media-picker-header">
            <h3>Select Image</h3>
            <button type="button" onclick="closeMediaPicker()" class="media-picker-close">&times;</button>
        </div>
        <div class="media-picker-body">
            <div id="media-picker-loading" style="text-align: center; padding: 2rem;">Loading...</div>
            <div id="media-picker-grid" class="media-picker-grid"></div>
            <div id="media-picker-empty" style="display: none; text-align: center; padding: 2rem; color: var(--color-text-muted);">
                No images in library. Upload one using the button above.
            </div>
        </div>
    </div>
</div>

<style>
.media-picker-overlay {
    position: fixed;
    inset: 0;
    background: rgba(0, 0, 0, 0.6);
    z-index: 1000;
    display: flex;
    align-items: center;
    justify-content: center;
}
.media-picker-modal {
    background: var(--color-bg-elevated);
    border-radius: var(--radius-xl);
    width: 90%;
    max-width: 700px;
    max-height: 80vh;
    display: flex;
    flex-direction: column;
}
.media-picker-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 1rem 1.25rem;
    border-bottom: 1px solid var(--color-border);
}
.media-picker-header h3 {
    margin: 0;
    font-size: 1rem;
}
.media-picker-close {
    background: none;
    border: none;
    font-size: 1.5rem;
    cursor: pointer;
    color: var(--color-text-muted);
    line-height: 1;
}
.media-picker-close:hover {
    color: var(--color-text);
}
.media-picker-body {
    padding: 1rem;
    overflow-y: auto;
    flex: 1;
}
.media-picker-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(100px, 1fr));
    gap: 0.75rem;
}
.media-picker-item {
    aspect-ratio: 1;
    border-radius: var(--radius-md);
    overflow: hidden;
    cursor: pointer;
    border: 2px solid transparent;
    transition: border-color 0.15s, transform 0.15s;
}
.media-picker-item:hover {
    border-color: var(--color-purple);
    transform: scale(1.02);
}
.media-picker-item img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}
</style>

<script>
const csrfToken = '<?= $_SESSION['csrf_token'] ?? ''; ?>';
const postId = <?= $post['id'] ?? 'null'; ?>;

// Auto-save status indicator
function showSaveStatus(message, isError = false) {
    let indicator = document.getElementById('auto-save-indicator');
    if (!indicator) {
        indicator = document.createElement('div');
        indicator.id = 'auto-save-indicator';
        indicator.style.cssText = 'position: fixed; bottom: 1rem; right: 1rem; padding: 0.5rem 1rem; border-radius: 0.5rem; font-size: 0.875rem; z-index: 1000; transition: opacity 0.3s;';
        document.body.appendChild(indicator);
    }
    indicator.textContent = message;
    indicator.style.background = isError ? '#fee2e2' : '#dcfce7';
    indicator.style.color = isError ? '#991b1b' : '#166534';
    indicator.style.opacity = '1';

    setTimeout(() => {
        indicator.style.opacity = '0';
    }, 2000);
}

// Auto-save function
async function autoSave(data) {
    if (!postId) return; // Can't auto-save new posts

    try {
        const response = await fetch('/admin/api/blog.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-Token': csrfToken
            },
            body: JSON.stringify({
                csrf_token: csrfToken,
                post_id: postId,
                ...data
            })
        });

        const result = await response.json();
        if (result.success) {
            showSaveStatus('Saved');
        } else {
            showSaveStatus(result.error || 'Save failed', true);
        }
    } catch (error) {
        showSaveStatus('Save failed', true);
    }
}

// Auto-save category on change
document.getElementById('category_id')?.addEventListener('change', function() {
    autoSave({ category_id: this.value });
});

// Auto-save tags on change
document.querySelectorAll('input[name="tags[]"]').forEach(checkbox => {
    checkbox.addEventListener('change', function() {
        const checkedTags = Array.from(document.querySelectorAll('input[name="tags[]"]:checked'))
            .map(cb => cb.value);
        autoSave({ tags: checkedTags });
    });
});

// Track which image type we're selecting for
let currentImageType = 'featured';

function openMediaPicker(type = 'featured') {
    currentImageType = type;
    document.getElementById('media-picker-modal').style.display = 'flex';
    loadMediaLibrary();
}

function closeMediaPicker() {
    document.getElementById('media-picker-modal').style.display = 'none';
}

async function loadMediaLibrary() {
    const grid = document.getElementById('media-picker-grid');
    const loading = document.getElementById('media-picker-loading');
    const empty = document.getElementById('media-picker-empty');

    loading.style.display = 'block';
    grid.innerHTML = '';
    empty.style.display = 'none';

    try {
        const response = await fetch('/admin/api/media.php?type=image&limit=50');
        const result = await response.json();

        loading.style.display = 'none';

        if (result.success && result.data.length > 0) {
            result.data.forEach(item => {
                const div = document.createElement('div');
                div.className = 'media-picker-item';
                div.innerHTML = `<img src="${item.url}" alt="${item.name || ''}" loading="lazy">`;
                div.onclick = () => selectMedia(item.url, currentImageType);
                grid.appendChild(div);
            });
        } else {
            empty.style.display = 'block';
        }
    } catch (error) {
        loading.style.display = 'none';
        empty.textContent = 'Failed to load media library.';
        empty.style.display = 'block';
    }
}

function selectMedia(url, type = 'featured') {
    if (type === 'thumbnail') {
        document.getElementById('thumbnail').value = url;
        document.querySelector('#thumbnail-preview img').src = url;
        document.getElementById('thumbnail-preview').style.display = 'block';
        document.getElementById('thumbnail-buttons').style.display = 'none';
        autoSave({ thumbnail: url });
    } else {
        document.getElementById('featured_image').value = url;
        document.querySelector('#featured-image-preview img').src = url;
        document.getElementById('featured-image-preview').style.display = 'block';
        document.getElementById('featured-image-buttons').style.display = 'none';
        autoSave({ featured_image: url });
    }
    closeMediaPicker();
}

function removeFeaturedImage() {
    autoSave({ featured_image: '' });
    document.getElementById('featured_image').value = '';
    document.getElementById('featured-image-preview').style.display = 'none';
    document.getElementById('featured-image-buttons').style.display = 'flex';
}

function removeThumbnail() {
    autoSave({ thumbnail: '' });
    document.getElementById('thumbnail').value = '';
    document.getElementById('thumbnail-preview').style.display = 'none';
    document.getElementById('thumbnail-buttons').style.display = 'flex';
}

async function uploadImage(input, type = 'featured') {
    if (!input.files || !input.files[0]) return;

    const file = input.files[0];
    const formData = new FormData();
    formData.append('file', file);
    formData.append('csrf_token', csrfToken);

    // Send blog title for SEO-friendly filename
    const blogTitle = document.getElementById('title')?.value || '';
    if (blogTitle) {
        const suffix = type === 'thumbnail' ? '-thumb' : '';
        formData.append('seo_name', blogTitle + suffix);
    }

    // Show loading state
    const buttonsId = type === 'thumbnail' ? 'thumbnail-buttons' : 'featured-image-buttons';
    const buttons = document.getElementById(buttonsId);
    const originalContent = buttons.innerHTML;
    buttons.innerHTML = '<span style="color: var(--color-text-muted);">Uploading...</span>';

    try {
        const response = await fetch('/admin/api/media.php', {
            method: 'POST',
            body: formData
        });

        const result = await response.json();

        if (result.success && result.data) {
            selectMedia(result.data.url, type);
        } else {
            alert(result.error || 'Upload failed');
            buttons.innerHTML = originalContent;
        }
    } catch (error) {
        alert('Upload failed: ' + error.message);
        buttons.innerHTML = originalContent;
    }

    input.value = '';
}

// Close modal on overlay click
document.getElementById('media-picker-modal').onclick = function(e) {
    if (e.target === this) closeMediaPicker();
};

// Close modal on escape key
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') closeMediaPicker();
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
