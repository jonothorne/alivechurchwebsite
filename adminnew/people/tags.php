<?php
/**
 * People Tags Management - New Admin
 */
$page_title = 'Tags';
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../../includes/db-config.php';

$pdo = getDbConnection();
$success = '';
$error = '';
$tags = [];
$edit_tag = null;
$tableExists = false;

// Check if table exists
try {
    $tableCheck = $pdo->query("SHOW TABLES LIKE 'people_tags'");
    $tableExists = $tableCheck->rowCount() > 0;
} catch (PDOException $e) {
    $tableExists = false;
}

if ($tableExists) {
    // Handle Delete Tag
    if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
        $id = (int)$_GET['delete'];
        try {
            // First delete tag assignments
            $stmt = $pdo->prepare("DELETE FROM people_tag_assignments WHERE tag_id = ?");
            $stmt->execute([$id]);

            // Then delete the tag
            $stmt = $pdo->prepare("DELETE FROM people_tags WHERE id = ?");
            if ($stmt->execute([$id])) {
                $success = 'Tag deleted successfully';
            }
        } catch (PDOException $e) {
            $error = 'Failed to delete tag: ' . $e->getMessage();
        }
    }

    // Handle Add/Edit Tag
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
            $error = 'Invalid security token';
        } else {
            $id = $_POST['id'] ?? null;
            $name = trim($_POST['name']);
            $color = $_POST['color'] ?? '#6b7280';
            $description = trim($_POST['description'] ?? '');

            if (empty($name)) {
                $error = 'Tag name is required';
            } else {
                try {
                    if ($id) {
                        $stmt = $pdo->prepare("UPDATE people_tags SET name = ?, color = ?, description = ? WHERE id = ?");
                        $stmt->execute([$name, $color, $description, $id]);
                        $success = 'Tag updated successfully';
                    } else {
                        $stmt = $pdo->prepare("INSERT INTO people_tags (name, color, description) VALUES (?, ?, ?)");
                        $stmt->execute([$name, $color, $description]);
                        $success = 'Tag created successfully';
                    }
                } catch (PDOException $e) {
                    $error = 'Database error: ' . $e->getMessage();
                }
            }
        }
    }

    // Fetch all tags with usage count
    try {
        $tags = $pdo->query("
            SELECT t.*, COUNT(pta.person_id) as usage_count
            FROM people_tags t
            LEFT JOIN people_tag_assignments pta ON t.id = pta.tag_id
            GROUP BY t.id
            ORDER BY t.name
        ")->fetchAll();
    } catch (PDOException $e) {
        $tags = [];
    }

    // Get tag for editing
    if (isset($_GET['edit']) && is_numeric($_GET['edit'])) {
        $stmt = $pdo->prepare("SELECT * FROM people_tags WHERE id = ?");
        $stmt->execute([$_GET['edit']]);
        $edit_tag = $stmt->fetch();
    }
}

// Predefined colors
$colors = [
    '#ef4444' => 'Red',
    '#f97316' => 'Orange',
    '#f59e0b' => 'Amber',
    '#eab308' => 'Yellow',
    '#84cc16' => 'Lime',
    '#22c55e' => 'Green',
    '#14b8a6' => 'Teal',
    '#06b6d4' => 'Cyan',
    '#0ea5e9' => 'Sky',
    '#3b82f6' => 'Blue',
    '#6366f1' => 'Indigo',
    '#8b5cf6' => 'Violet',
    '#a855f7' => 'Purple',
    '#d946ef' => 'Fuchsia',
    '#ec4899' => 'Pink',
    '#6b7280' => 'Gray',
];
?>

<?php if ($success): ?>
    <div class="admin-alert admin-alert-success"><?= htmlspecialchars($success); ?></div>
<?php endif; ?>

<?php if ($error): ?>
    <div class="admin-alert admin-alert-error"><?= htmlspecialchars($error); ?></div>
<?php endif; ?>

<div class="admin-page-header">
    <div>
        <h1 class="admin-page-title">Tags</h1>
        <p class="admin-page-subtitle"><?= count($tags); ?> tags</p>
    </div>
</div>

<!-- Add/Edit Tag Form -->
<div class="admin-card">
    <div class="admin-card-header">
        <h3 class="admin-card-title"><?= $edit_tag ? 'Edit' : 'Add'; ?> Tag</h3>
        <?php if ($edit_tag): ?>
            <a href="/adminnew/people/tags" class="admin-btn admin-btn-sm admin-btn-secondary">Cancel</a>
        <?php endif; ?>
    </div>
    <div class="admin-card-body">
        <form method="post">
            <?= csrf_field(); ?>
            <?php if ($edit_tag): ?>
                <input type="hidden" name="id" value="<?= $edit_tag['id']; ?>">
            <?php endif; ?>

            <div class="tag-form-grid">
                <div class="admin-form-group">
                    <label class="admin-form-label">Tag Name *</label>
                    <input type="text" name="name" class="admin-form-input" value="<?= htmlspecialchars($edit_tag['name'] ?? ''); ?>" required placeholder="e.g., Volunteer, New Member">
                </div>
                <div class="admin-form-group">
                    <label class="admin-form-label">Color</label>
                    <div class="color-picker">
                        <?php foreach ($colors as $hex => $colorName): ?>
                            <label class="color-option" style="--color: <?= $hex; ?>;" title="<?= $colorName; ?>">
                                <input type="radio" name="color" value="<?= $hex; ?>" <?= ($edit_tag['color'] ?? '#6b7280') === $hex ? 'checked' : ''; ?>>
                                <span class="color-swatch"></span>
                            </label>
                        <?php endforeach; ?>
                    </div>
                </div>
                <div class="admin-form-group">
                    <label class="admin-form-label">Description (optional)</label>
                    <input type="text" name="description" class="admin-form-input" value="<?= htmlspecialchars($edit_tag['description'] ?? ''); ?>" placeholder="Brief description of this tag">
                </div>
            </div>

            <button type="submit" class="admin-btn admin-btn-primary"><?= $edit_tag ? 'Update Tag' : 'Create Tag'; ?></button>
        </form>
    </div>
</div>

<!-- Tags List -->
<div class="admin-card">
    <div class="admin-card-header">
        <h3 class="admin-card-title">All Tags</h3>
    </div>

    <?php if (empty($tags)): ?>
        <div class="admin-empty-state">
            <div class="admin-empty-icon">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20.59 13.41l-7.17 7.17a2 2 0 0 1-2.83 0L2 12V2h10l8.59 8.59a2 2 0 0 1 0 2.82z"/><line x1="7" y1="7" x2="7.01" y2="7"/></svg>
            </div>
            <h3 class="admin-empty-title">No tags yet</h3>
            <p class="admin-empty-text">Tags help you organize and filter people. Create your first tag above.</p>
        </div>
    <?php else: ?>
        <div class="tags-grid">
            <?php foreach ($tags as $tag): ?>
                <div class="tag-card">
                    <div class="tag-card-header">
                        <span class="tag-badge" style="--tag-color: <?= htmlspecialchars($tag['color']); ?>;">
                            <?= htmlspecialchars($tag['name']); ?>
                        </span>
                        <span class="tag-count"><?= $tag['usage_count']; ?> people</span>
                    </div>
                    <?php if ($tag['description']): ?>
                        <p class="tag-description"><?= htmlspecialchars($tag['description']); ?></p>
                    <?php endif; ?>
                    <div class="tag-actions">
                        <a href="/adminnew?module=people&filter=tag&tag_id=<?= $tag['id']; ?>" class="admin-btn admin-btn-sm admin-btn-secondary">View People</a>
                        <a href="/adminnew/people/tags&edit=<?= $tag['id']; ?>" class="admin-btn admin-btn-sm admin-btn-secondary">Edit</a>
                        <a href="/adminnew/people/tags&delete=<?= $tag['id']; ?>" class="admin-btn admin-btn-sm admin-btn-danger" onclick="return confirm('Delete this tag? People will be untagged but not deleted.')">×</a>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<style>
.tag-form-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 1rem;
    margin-bottom: 1rem;
}
.tag-form-grid .admin-form-group:last-child {
    grid-column: 1 / -1;
}
@media (max-width: 768px) {
    .tag-form-grid {
        grid-template-columns: 1fr;
    }
}

.color-picker {
    display: flex;
    flex-wrap: wrap;
    gap: 0.5rem;
}
.color-option {
    cursor: pointer;
}
.color-option input {
    display: none;
}
.color-swatch {
    display: block;
    width: 24px;
    height: 24px;
    border-radius: 50%;
    background: var(--color);
    border: 2px solid transparent;
    transition: all var(--admin-transition);
}
.color-option:hover .color-swatch {
    transform: scale(1.1);
}
.color-option input:checked + .color-swatch {
    border-color: var(--admin-text);
    box-shadow: 0 0 0 2px var(--admin-card-bg), 0 0 0 4px var(--color);
}

.tags-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
    gap: 1rem;
    padding: var(--admin-spacing-lg);
}

.tag-card {
    background: var(--admin-bg);
    border: 1px solid var(--admin-border);
    border-radius: var(--admin-radius-lg);
    padding: 1rem;
}
.tag-card-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 0.5rem;
}
.tag-badge {
    display: inline-flex;
    align-items: center;
    padding: 0.25rem 0.75rem;
    font-size: 0.875rem;
    font-weight: 500;
    border-radius: 9999px;
    background: var(--tag-color);
    color: white;
}
.tag-count {
    font-size: 0.75rem;
    color: var(--admin-text-muted);
}
.tag-description {
    font-size: 0.8125rem;
    color: var(--admin-text-muted);
    margin: 0.5rem 0;
}
.tag-actions {
    display: flex;
    gap: 0.5rem;
    margin-top: 0.75rem;
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

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
