<?php
/**
 * Groups - Manage Group Types
 */

$page_title = 'Group Types';
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../../includes/db-config.php';
require_once __DIR__ . '/../../includes/services/GroupsService.php';

$pdo = getDbConnection();
$groupsService = new GroupsService($pdo);

$success = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && verify_csrf_token($_POST['csrf_token'] ?? '')) {
    $action = $_POST['action'] ?? '';

    if ($action === 'create') {
        $name = trim($_POST['name']);
        $slug = preg_replace('/[^a-z0-9-]/', '', strtolower(str_replace(' ', '-', $name)));
        $stmt = $pdo->prepare("INSERT INTO group_types (name, slug, description, color, sort_order) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([$name, $slug, trim($_POST['description']) ?: null, $_POST['color'] ?? '#6B7280', (int)$_POST['sort_order']]);
        $success = 'Group type created';
    } elseif ($action === 'update') {
        $stmt = $pdo->prepare("UPDATE group_types SET name = ?, description = ?, color = ?, sort_order = ? WHERE id = ?");
        $stmt->execute([trim($_POST['name']), trim($_POST['description']) ?: null, $_POST['color'], (int)$_POST['sort_order'], (int)$_POST['id']]);
        $success = 'Group type updated';
    } elseif ($action === 'delete') {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM `groups` WHERE group_type_id = ?");
        $stmt->execute([(int)$_POST['id']]);
        if ((int)$stmt->fetchColumn() > 0) {
            $error = 'Cannot delete type with existing groups';
        } else {
            $stmt = $pdo->prepare("DELETE FROM group_types WHERE id = ?");
            $stmt->execute([(int)$_POST['id']]);
            $success = 'Group type deleted';
        }
    }
}

$types = $groupsService->getGroupTypes();
?>

<?php if ($success): ?><div class="admin-alert admin-alert-success"><?= htmlspecialchars($success); ?></div><?php endif; ?>
<?php if ($error): ?><div class="admin-alert admin-alert-error"><?= htmlspecialchars($error); ?></div><?php endif; ?>

<div class="admin-actions-bar">
    <a href="/admin/groups" class="btn btn-outline">&larr; Back to Groups</a>
    <button type="button" class="btn btn-primary" data-open-modal="type-modal" data-mode="create">Add Group Type</button>
</div>

<div class="admin-card">
    <table class="data-table">
        <thead>
            <tr><th>Order</th><th>Color</th><th>Name</th><th>Description</th><th></th></tr>
        </thead>
        <tbody>
            <?php foreach ($types as $t): ?>
                <tr>
                    <td><?= $t['sort_order']; ?></td>
                    <td><span class="color-dot" style="background: <?= htmlspecialchars($t['color']); ?>"></span></td>
                    <td><strong><?= htmlspecialchars($t['name']); ?></strong></td>
                    <td class="text-muted"><?= htmlspecialchars($t['description'] ?? ''); ?></td>
                    <td>
                        <button type="button" class="btn btn-xs btn-outline" data-open-modal="type-modal" data-mode="edit"
                            data-id="<?= $t['id']; ?>" data-name="<?= htmlspecialchars($t['name']); ?>"
                            data-description="<?= htmlspecialchars($t['description'] ?? ''); ?>"
                            data-color="<?= htmlspecialchars($t['color']); ?>" data-sort="<?= $t['sort_order']; ?>">Edit</button>
                        <form method="post" class="inline-form">
                            <?= csrf_field(); ?>
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="id" value="<?= $t['id']; ?>">
                            <button type="submit" class="btn btn-xs btn-ghost btn-danger" data-confirm="Delete this type?">Delete</button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

<!-- Type Modal -->
<div class="modal-overlay" id="type-modal">
    <div class="modal">
        <div class="modal-header">
            <h3 id="modal-title">Add Group Type</h3>
            <button type="button" class="modal-close" data-close-modal>&times;</button>
        </div>
        <form method="post">
            <?= csrf_field(); ?>
            <input type="hidden" name="action" id="modal-action" value="create">
            <input type="hidden" name="id" id="modal-id">
            <div class="modal-body">
                <div class="form-group">
                    <label>Name *</label>
                    <input type="text" name="name" id="modal-name" required>
                </div>
                <div class="form-group">
                    <label>Description</label>
                    <textarea name="description" id="modal-description" rows="2"></textarea>
                </div>
                <div class="form-row-2">
                    <div class="form-group">
                        <label>Color</label>
                        <input type="color" name="color" id="modal-color" value="#6B7280">
                    </div>
                    <div class="form-group">
                        <label>Sort Order</label>
                        <input type="number" name="sort_order" id="modal-sort" value="0" min="0">
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline" data-close-modal>Cancel</button>
                <button type="submit" class="btn btn-primary">Save</button>
            </div>
        </form>
    </div>
</div>

<style>
.data-table { width: 100%; }
.data-table th, .data-table td { padding: 0.75rem 1rem; text-align: left; border-bottom: 1px solid var(--color-border); }
.color-dot { display: inline-block; width: 16px; height: 16px; border-radius: 50%; }
.inline-form { display: inline; }
.modal-overlay { display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.5); z-index: 1000; align-items: center; justify-content: center; }
.modal-overlay.active { display: flex; }
.modal { background: var(--color-surface); border-radius: var(--radius-lg); width: 100%; max-width: 450px; margin: 1rem; }
.modal-header { display: flex; justify-content: space-between; align-items: center; padding: 1rem; border-bottom: 1px solid var(--color-border); }
.modal-header h3 { margin: 0; }
.modal-close { background: none; border: none; font-size: 1.5rem; cursor: pointer; }
.modal-body { padding: 1rem; }
.modal-footer { display: flex; justify-content: flex-end; gap: 0.5rem; padding: 1rem; border-top: 1px solid var(--color-border); }
.form-row-2 { display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; }
</style>

<script <?= csp_nonce(); ?>>
document.querySelectorAll('[data-open-modal]').forEach(b => {
    b.addEventListener('click', () => {
        const modal = document.getElementById(b.dataset.openModal);
        if (b.dataset.mode === 'edit') {
            document.getElementById('modal-title').textContent = 'Edit Group Type';
            document.getElementById('modal-action').value = 'update';
            document.getElementById('modal-id').value = b.dataset.id;
            document.getElementById('modal-name').value = b.dataset.name;
            document.getElementById('modal-description').value = b.dataset.description;
            document.getElementById('modal-color').value = b.dataset.color;
            document.getElementById('modal-sort').value = b.dataset.sort;
        } else {
            document.getElementById('modal-title').textContent = 'Add Group Type';
            document.getElementById('modal-action').value = 'create';
            document.getElementById('modal-id').value = '';
            document.getElementById('modal-name').value = '';
            document.getElementById('modal-description').value = '';
            document.getElementById('modal-color').value = '#6B7280';
            document.getElementById('modal-sort').value = '0';
        }
        modal?.classList.add('active');
    });
});
document.querySelectorAll('[data-close-modal]').forEach(b => b.addEventListener('click', () => b.closest('.modal-overlay')?.classList.remove('active')));
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
