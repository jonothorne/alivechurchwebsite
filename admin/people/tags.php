<?php
/**
 * People Management - Tags
 *
 * Manage member tags for categorization and segmentation.
 */

$page_title = 'Member Tags';
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../../includes/db-config.php';
require_once __DIR__ . '/../../includes/services/PeopleService.php';

$pdo = getDbConnection();
$peopleService = new PeopleService($pdo);

$success = '';
$error = '';

// Handle actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid security token';
    } else {
        $action = $_POST['action'] ?? '';

        switch ($action) {
            case 'create':
                $result = $peopleService->createTag([
                    'name' => trim($_POST['name']),
                    'tag_group' => trim($_POST['tag_group']) ?: null,
                    'color' => $_POST['color'] ?: '#6B7280',
                    'description' => trim($_POST['description']) ?: null,
                ]);
                if ($result['success']) {
                    $success = 'Tag created successfully';
                    log_activity($_SESSION['admin_user_id'], 'create', 'member_tag', $result['tag_id'], 'Created tag: ' . $_POST['name']);
                } else {
                    $error = $result['error'];
                }
                break;

            case 'delete':
                $tagId = (int)$_POST['tag_id'];
                // Remove tag from all users first
                $pdo->prepare("DELETE FROM user_tags WHERE tag_id = ?")->execute([$tagId]);
                $pdo->prepare("DELETE FROM member_tags WHERE id = ?")->execute([$tagId]);
                $success = 'Tag deleted';
                log_activity($_SESSION['admin_user_id'], 'delete', 'member_tag', $tagId, 'Deleted tag');
                break;
        }
    }
}

// Get tags with usage counts
$tags = $peopleService->getTagsWithCounts();

// Group tags
$groupedTags = [];
foreach ($tags as $tag) {
    $group = $tag['tag_group'] ?? 'Other';
    if (!isset($groupedTags[$group])) {
        $groupedTags[$group] = [];
    }
    $groupedTags[$group][] = $tag;
}
?>

<?php if ($success): ?>
    <div class="admin-alert admin-alert-success"><?= htmlspecialchars($success); ?></div>
<?php endif; ?>
<?php if ($error): ?>
    <div class="admin-alert admin-alert-error"><?= htmlspecialchars($error); ?></div>
<?php endif; ?>

<div class="admin-actions-bar">
    <div class="actions-left">
        <a href="/admin/people" class="btn btn-outline">&larr; Back to People</a>
    </div>
    <div class="actions-right">
        <button type="button" class="btn btn-primary" data-open-modal="create-tag">Create Tag</button>
    </div>
</div>

<!-- Tags Overview -->
<div class="tags-overview">
    <?php foreach ($groupedTags as $group => $groupTags): ?>
        <div class="admin-card tag-group-card">
            <div class="admin-card-header">
                <h3><?= htmlspecialchars($group); ?></h3>
                <span class="badge"><?= count($groupTags); ?> tags</span>
            </div>

            <div class="tags-grid">
                <?php foreach ($groupTags as $tag): ?>
                    <div class="tag-card" style="--tag-color: <?= htmlspecialchars($tag['color']); ?>">
                        <div class="tag-header">
                            <span class="tag-dot"></span>
                            <span class="tag-name"><?= htmlspecialchars($tag['name']); ?></span>
                        </div>
                        <div class="tag-meta">
                            <span class="tag-count"><?= $tag['user_count']; ?> people</span>
                        </div>
                        <?php if ($tag['user_count'] > 0): ?>
                            <a href="/admin/people?tag=<?= $tag['id']; ?>" class="tag-view-link">View People</a>
                        <?php endif; ?>
                        <div class="tag-actions">
                            <form method="post" style="display: inline;">
                                <?= csrf_field(); ?>
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="tag_id" value="<?= $tag['id']; ?>">
                                <button type="submit" class="btn btn-xs btn-ghost btn-danger"
                                        data-confirm-delete
                                        <?= $tag['user_count'] > 0 ? 'title="This will remove the tag from ' . $tag['user_count'] . ' people"' : ''; ?>>
                                    Delete
                                </button>
                            </form>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    <?php endforeach; ?>
</div>

<?php if (empty($tags)): ?>
    <div class="admin-card">
        <div class="empty-state">
            <p>No tags created yet.</p>
            <button type="button" class="btn btn-primary" data-open-modal="create-tag">Create First Tag</button>
        </div>
    </div>
<?php endif; ?>

<!-- Create Tag Modal -->
<div id="create-tag" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3>Create Tag</h3>
            <button type="button" class="modal-close" data-close-modal>&times;</button>
        </div>
        <form method="post">
            <?= csrf_field(); ?>
            <input type="hidden" name="action" value="create">

            <div class="form-group">
                <label>Tag Name *</label>
                <input type="text" name="name" required placeholder="e.g., Prayer Team">
            </div>

            <div class="form-group">
                <label>Group</label>
                <input type="text" name="tag_group" placeholder="e.g., Serving, Life Stage, Status" list="tag-groups">
                <datalist id="tag-groups">
                    <?php foreach (array_keys($groupedTags) as $group): ?>
                        <option value="<?= htmlspecialchars($group); ?>">
                    <?php endforeach; ?>
                </datalist>
                <div class="form-help">Tags in the same group appear together</div>
            </div>

            <div class="form-group">
                <label>Color</label>
                <div class="color-picker">
                    <input type="color" name="color" value="#6B7280" id="tag-color-input">
                    <input type="text" value="#6B7280" id="tag-color-text" pattern="^#[0-9A-Fa-f]{6}$">
                </div>
            </div>

            <div class="form-group">
                <label>Description</label>
                <textarea name="description" rows="2" placeholder="Optional description..."></textarea>
            </div>

            <div class="modal-footer">
                <button type="button" class="btn btn-outline" data-close-modal>Cancel</button>
                <button type="submit" class="btn btn-primary">Create Tag</button>
            </div>
        </form>
    </div>
</div>

<style>
.tags-overview {
    display: flex;
    flex-direction: column;
    gap: 1.5rem;
}

.tag-group-card {
    overflow: visible;
}

.tags-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
    gap: 1rem;
    padding: 1rem;
}

.tag-card {
    padding: 1rem;
    background: color-mix(in srgb, var(--tag-color) 5%, var(--color-surface));
    border: 1px solid color-mix(in srgb, var(--tag-color) 20%, transparent);
    border-radius: var(--radius);
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
}

.tag-header {
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.tag-dot {
    width: 12px;
    height: 12px;
    border-radius: 50%;
    background: var(--tag-color);
    flex-shrink: 0;
}

.tag-name {
    font-weight: 600;
    font-size: 0.9375rem;
}

.tag-meta {
    font-size: 0.75rem;
    color: var(--color-text-muted);
}

.tag-view-link {
    font-size: 0.75rem;
    color: var(--color-primary);
}

.tag-actions {
    margin-top: auto;
    padding-top: 0.5rem;
    border-top: 1px solid var(--color-border);
}

/* Color Picker */
.color-picker {
    display: flex;
    gap: 0.5rem;
    align-items: center;
}

.color-picker input[type="color"] {
    width: 48px;
    height: 36px;
    padding: 0;
    border: 1px solid var(--color-border);
    border-radius: var(--radius);
    cursor: pointer;
}

.color-picker input[type="text"] {
    flex: 1;
    font-family: monospace;
}

/* Modal */
.modal {
    display: none;
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: rgba(0, 0, 0, 0.5);
    z-index: 1000;
    align-items: center;
    justify-content: center;
}

.modal.open {
    display: flex;
}

.modal-content {
    background: var(--color-surface);
    border-radius: var(--radius-lg);
    width: 100%;
    max-width: 500px;
    max-height: 90vh;
    overflow-y: auto;
}

.modal-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 1rem 1.5rem;
    border-bottom: 1px solid var(--color-border);
}

.modal-header h3 {
    margin: 0;
}

.modal-close {
    background: none;
    border: none;
    font-size: 1.5rem;
    cursor: pointer;
    color: var(--color-text-muted);
}

.modal-content form {
    padding: 1.5rem;
}

.modal-footer {
    display: flex;
    justify-content: flex-end;
    gap: 0.5rem;
    margin-top: 1.5rem;
}

.empty-state {
    text-align: center;
    padding: 2rem;
}
</style>

<script <?= csp_nonce(); ?>>
// Modal functionality
document.querySelectorAll('[data-open-modal]').forEach(btn => {
    btn.addEventListener('click', function() {
        document.getElementById(this.dataset.openModal).classList.add('open');
    });
});

document.querySelectorAll('[data-close-modal]').forEach(btn => {
    btn.addEventListener('click', function() {
        this.closest('.modal').classList.remove('open');
    });
});

document.querySelectorAll('.modal').forEach(modal => {
    modal.addEventListener('click', function(e) {
        if (e.target === this) {
            this.classList.remove('open');
        }
    });
});

// Color picker sync
const colorInput = document.getElementById('tag-color-input');
const colorText = document.getElementById('tag-color-text');

if (colorInput && colorText) {
    colorInput.addEventListener('input', function() {
        colorText.value = this.value.toUpperCase();
    });

    colorText.addEventListener('input', function() {
        if (/^#[0-9A-Fa-f]{6}$/.test(this.value)) {
            colorInput.value = this.value;
        }
    });
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
