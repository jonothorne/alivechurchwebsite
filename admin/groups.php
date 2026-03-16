<?php
$page_title = 'Groups';
require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/../includes/db-config.php';

$pdo = getDbConnection();
$success = '';
$error = '';

// Handle Delete
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $id = (int)$_GET['delete'];
    $stmt = $pdo->prepare("DELETE FROM groups_list WHERE id = ?");
    if ($stmt->execute([$id])) {
        log_activity($_SESSION['admin_user_id'], 'delete', 'group', $id, 'Deleted group');
        $success = 'Group deleted successfully';
    }
}

// Handle Add/Edit
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid security token';
    } else {
        $id = $_POST['id'] ?? null;
        $title = $_POST['title'];
        $description = $_POST['description'];
        $schedule = $_POST['schedule'];
        $location = $_POST['location'];
        $image_url = $_POST['image_url'];
        $signup_url = $_POST['signup_url'];
        $category = $_POST['category'];
        $display_order = (int)$_POST['display_order'];
        $visible = isset($_POST['visible']) ? 1 : 0;

        if ($id) {
            $stmt = $pdo->prepare("UPDATE groups_list SET title = ?, description = ?, schedule = ?, location = ?, image_url = ?, signup_url = ?, category = ?, display_order = ?, visible = ? WHERE id = ?");
            $stmt->execute([$title, $description, $schedule, $location, $image_url, $signup_url, $category, $display_order, $visible, $id]);
            log_activity($_SESSION['admin_user_id'], 'update', 'group', $id, 'Updated group: ' . $title);
            $success = 'Group updated successfully';
        } else {
            $stmt = $pdo->prepare("INSERT INTO groups_list (title, description, schedule, location, image_url, signup_url, category, display_order, visible) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([$title, $description, $schedule, $location, $image_url, $signup_url, $category, $display_order, $visible]);
            $new_id = $pdo->lastInsertId();
            log_activity($_SESSION['admin_user_id'], 'create', 'group', $new_id, 'Created group: ' . $title);
            $success = 'Group created successfully';
        }
    }
}

// Fetch all groups
$groups = $pdo->query("SELECT * FROM groups_list ORDER BY display_order ASC, title ASC")->fetchAll();

// Get group for editing
$edit_group = null;
if (isset($_GET['edit']) && is_numeric($_GET['edit'])) {
    $stmt = $pdo->prepare("SELECT * FROM groups_list WHERE id = ?");
    $stmt->execute([$_GET['edit']]);
    $edit_group = $stmt->fetch();
}

// Count stats
$total = count($groups);
$visible_count = count(array_filter($groups, fn($g) => $g['visible']));
?>

<?php if ($success): ?>
    <div class="admin-alert admin-alert-success"><?= htmlspecialchars($success); ?></div>
<?php endif; ?>

<?php if ($error): ?>
    <div class="admin-alert admin-alert-error"><?= htmlspecialchars($error); ?></div>
<?php endif; ?>

<!-- Header with Stats -->
<div class="admin-dashboard-header" style="margin-bottom: 1rem;">
    <div class="admin-dashboard-greeting">
        <span class="admin-greeting-text">Groups</span>
    </div>
    <div class="admin-inline-stats">
        <span class="admin-inline-stat"><strong><?= $total; ?></strong> Total</span>
        <span class="admin-inline-stat"><strong><?= $visible_count; ?></strong> Visible</span>
    </div>
</div>

<!-- Form Card -->
<div class="admin-card">
    <details <?= $edit_group ? 'open' : ''; ?>>
        <summary class="admin-card-header" style="cursor: pointer;">
            <h3><?= $edit_group ? 'Edit Group' : '+ Add Group'; ?></h3>
            <?php if ($edit_group): ?>
                <a href="/admin/groups.php" class="btn btn-xs btn-outline">Cancel</a>
            <?php endif; ?>
        </summary>
        <form method="post" class="admin-compact-form">
            <?= csrf_field(); ?>
            <?php if ($edit_group): ?>
                <input type="hidden" name="id" value="<?= $edit_group['id']; ?>">
            <?php endif; ?>

            <div class="admin-form-row">
                <div class="admin-form-group">
                    <label>Group Name</label>
                    <input type="text" name="title" value="<?= htmlspecialchars($edit_group['title'] ?? ''); ?>" required placeholder="e.g., Young Adults">
                </div>
                <div class="admin-form-group">
                    <label>Category</label>
                    <select name="category">
                        <option value="">No category</option>
                        <option value="men" <?= ($edit_group['category'] ?? '') === 'men' ? 'selected' : ''; ?>>Men</option>
                        <option value="women" <?= ($edit_group['category'] ?? '') === 'women' ? 'selected' : ''; ?>>Women</option>
                        <option value="youth" <?= ($edit_group['category'] ?? '') === 'youth' ? 'selected' : ''; ?>>Youth</option>
                        <option value="young-adults" <?= ($edit_group['category'] ?? '') === 'young-adults' ? 'selected' : ''; ?>>Young Adults</option>
                        <option value="seniors" <?= ($edit_group['category'] ?? '') === 'seniors' ? 'selected' : ''; ?>>Seniors</option>
                        <option value="families" <?= ($edit_group['category'] ?? '') === 'families' ? 'selected' : ''; ?>>Families</option>
                    </select>
                </div>
            </div>

            <div class="admin-form-row">
                <div class="admin-form-group">
                    <label>Schedule</label>
                    <input type="text" name="schedule" value="<?= htmlspecialchars($edit_group['schedule'] ?? ''); ?>" placeholder="Tuesdays at 7:00 PM">
                </div>
                <div class="admin-form-group">
                    <label>Location</label>
                    <input type="text" name="location" value="<?= htmlspecialchars($edit_group['location'] ?? ''); ?>" placeholder="Room 201">
                </div>
            </div>

            <div class="admin-form-group">
                <label>Description</label>
                <textarea name="description" rows="2"><?= htmlspecialchars($edit_group['description'] ?? ''); ?></textarea>
            </div>

            <div class="admin-form-row">
                <div class="admin-form-group">
                    <label>Image</label>
                    <div class="image-picker-field">
                        <input type="hidden" name="image_url" id="image_url" value="<?= htmlspecialchars($edit_group['image_url'] ?? ''); ?>">
                        <div class="image-preview-container">
                            <?php if (!empty($edit_group['image_url'])): ?>
                                <img src="<?= htmlspecialchars($edit_group['image_url']); ?>" id="image_preview" class="image-preview">
                            <?php else: ?>
                                <div id="image_placeholder" class="image-placeholder">No image selected</div>
                                <img src="" id="image_preview" class="image-preview" style="display: none;">
                            <?php endif; ?>
                        </div>
                        <div class="image-picker-actions">
                            <button type="button" class="btn btn-sm btn-outline" onclick="openMediaPicker()">Select Image</button>
                            <button type="button" class="btn btn-sm btn-outline" onclick="clearImage()" style="<?= empty($edit_group['image_url']) ? 'display:none;' : ''; ?>" id="clear_image_btn">Clear</button>
                        </div>
                    </div>
                </div>
                <div class="admin-form-group">
                    <label>Signup URL</label>
                    <input type="text" name="signup_url" value="<?= htmlspecialchars($edit_group['signup_url'] ?? ''); ?>" placeholder="https://...">
                </div>
            </div>

            <div class="admin-form-row">
                <div class="admin-form-group" style="flex: 0 0 100px;">
                    <label>Order</label>
                    <input type="number" name="display_order" value="<?= $edit_group['display_order'] ?? 0; ?>" min="0">
                </div>
                <div class="admin-form-group" style="flex: 0 0 auto; align-self: flex-end;">
                    <label class="admin-checkbox">
                        <input type="checkbox" name="visible" value="1" <?= ($edit_group['visible'] ?? 1) ? 'checked' : ''; ?>>
                        <span>Visible</span>
                    </label>
                </div>
                <div class="admin-form-group" style="flex: 1; align-self: flex-end; text-align: right;">
                    <button type="submit" class="btn btn-sm btn-primary">Save Group</button>
                </div>
            </div>
        </form>
    </details>
</div>

<!-- Groups List -->
<div class="admin-card">
    <div class="admin-card-header">
        <h3>All Groups</h3>
        <span class="admin-muted-text"><?= $total; ?> groups</span>
    </div>

    <?php if (empty($groups)): ?>
        <div class="admin-empty-state">
            <span class="admin-empty-icon">👥</span>
            <p>No groups yet. Add one above.</p>
        </div>
    <?php else: ?>
        <div class="admin-compact-list">
            <?php foreach ($groups as $group): ?>
                <div class="admin-post-row">
                    <div class="admin-post-info">
                        <div class="admin-post-title">
                            <?= htmlspecialchars($group['title']); ?>
                            <?php if ($group['category']): ?>
                                <span class="admin-badge admin-badge-info"><?= htmlspecialchars(ucwords(str_replace('-', ' ', $group['category']))); ?></span>
                            <?php endif; ?>
                            <?php if ($group['visible']): ?>
                                <span class="admin-badge admin-badge-success">Visible</span>
                            <?php else: ?>
                                <span class="admin-badge admin-badge-secondary">Hidden</span>
                            <?php endif; ?>
                        </div>
                        <div class="admin-post-meta">
                            <?php if ($group['schedule']): ?>
                                <?= htmlspecialchars($group['schedule']); ?>
                            <?php endif; ?>
                            <?php if ($group['location']): ?>
                                · <?= htmlspecialchars($group['location']); ?>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="admin-post-actions">
                        <a href="?edit=<?= $group['id']; ?>" class="btn btn-xs btn-outline">Edit</a>
                        <a href="?delete=<?= $group['id']; ?>" class="btn btn-xs btn-danger" data-confirm-delete>×</a>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

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
                No images in library. <a href="/admin/media">Upload one</a>.
            </div>
        </div>
    </div>
</div>

<style>
.image-picker-field {
    display: flex;
    gap: 1rem;
    align-items: flex-start;
}
.image-preview-container {
    width: 120px;
    height: 80px;
    border-radius: var(--radius-md);
    overflow: hidden;
    background: var(--color-bg-subtle);
    border: 1px solid var(--color-border);
}
.image-preview {
    width: 100%;
    height: 100%;
    object-fit: cover;
}
.image-placeholder {
    width: 100%;
    height: 100%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 0.75rem;
    color: var(--color-text-muted);
}
.image-picker-actions {
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
}
.media-picker-overlay {
    position: fixed;
    inset: 0;
    background: rgba(0, 0, 0, 0.6);
    z-index: 9999;
    display: flex;
    align-items: center;
    justify-content: center;
}
.media-picker-modal {
    background: var(--color-bg-elevated);
    border-radius: var(--radius-xl);
    width: 90%;
    max-width: 600px;
    max-height: 80vh;
    display: flex;
    flex-direction: column;
}
.media-picker-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 1rem;
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
function openMediaPicker() {
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
        const response = await fetch('/admin/api/media?type=image');
        const result = await response.json();

        loading.style.display = 'none';

        if (result.data && result.data.length > 0) {
            result.data.forEach(item => {
                const div = document.createElement('div');
                div.className = 'media-picker-item';
                div.innerHTML = `<img src="${item.thumbnail || item.url}" alt="${item.name || ''}" loading="lazy">`;
                div.onclick = () => selectMedia(item.url);
                grid.appendChild(div);
            });
        } else {
            empty.style.display = 'block';
        }
    } catch (error) {
        loading.style.display = 'none';
        empty.textContent = 'Error loading media library';
        empty.style.display = 'block';
    }
}

function selectMedia(url) {
    // Try to use medium size variant if available
    let finalUrl = url;
    const mediumUrl = url.replace(/(\.[^.]+)$/, '-medium$1');
    const smallUrl = url.replace(/(\.[^.]+)$/, '-small$1');

    // Check if medium variant exists by trying to load it
    const img = new Image();
    img.onload = function() {
        finalUrl = mediumUrl;
        setImageUrl(finalUrl);
    };
    img.onerror = function() {
        // Try small variant
        const imgSmall = new Image();
        imgSmall.onload = function() {
            finalUrl = smallUrl;
            setImageUrl(finalUrl);
        };
        imgSmall.onerror = function() {
            // Use original
            setImageUrl(url);
        };
        imgSmall.src = smallUrl;
    };
    img.src = mediumUrl;

    closeMediaPicker();
}

function setImageUrl(url) {
    document.getElementById('image_url').value = url;
    const preview = document.getElementById('image_preview');
    const placeholder = document.getElementById('image_placeholder');

    preview.src = url;
    preview.style.display = 'block';
    if (placeholder) placeholder.style.display = 'none';
    document.getElementById('clear_image_btn').style.display = 'inline-flex';
}

function clearImage() {
    document.getElementById('image_url').value = '';
    const preview = document.getElementById('image_preview');
    const placeholder = document.getElementById('image_placeholder');

    preview.style.display = 'none';
    preview.src = '';
    if (placeholder) placeholder.style.display = 'flex';
    document.getElementById('clear_image_btn').style.display = 'none';
}

// Close modal on overlay click
document.getElementById('media-picker-modal').onclick = function(e) {
    if (e.target === this) closeMediaPicker();
};
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
