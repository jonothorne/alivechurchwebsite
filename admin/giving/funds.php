<?php
/**
 * Giving - Manage Funds
 */

$page_title = 'Giving Funds';
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../../includes/db-config.php';
require_once __DIR__ . '/../../includes/services/GivingService.php';

$pdo = getDbConnection();
$givingService = new GivingService($pdo);

$success = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && verify_csrf_token($_POST['csrf_token'] ?? '')) {
    $action = $_POST['action'] ?? '';

    if ($action === 'save') {
        $data = [
            'name' => trim($_POST['name']),
            'description' => trim($_POST['description']) ?: null,
            'is_default' => isset($_POST['is_default']) ? 1 : 0,
            'is_active' => isset($_POST['is_active']) ? 1 : 0,
            'goal_amount' => $_POST['goal_amount'] ? (float)$_POST['goal_amount'] : null,
            'goal_deadline' => $_POST['goal_deadline'] ?: null,
            'display_on_form' => isset($_POST['display_on_form']) ? 1 : 0,
            'sort_order' => (int)$_POST['sort_order'],
        ];
        $id = $_POST['fund_id'] ? (int)$_POST['fund_id'] : null;
        $result = $givingService->saveFund($data, $id);
        $success = $result['success'] ? 'Fund saved' : $result['error'];
    } elseif ($action === 'delete') {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM donations WHERE fund_id = ?");
        $stmt->execute([(int)$_POST['fund_id']]);
        if ((int)$stmt->fetchColumn() > 0) {
            $error = 'Cannot delete fund with existing donations';
        } else {
            $stmt = $pdo->prepare("DELETE FROM giving_funds WHERE id = ?");
            $stmt->execute([(int)$_POST['fund_id']]);
            $success = 'Fund deleted';
        }
    }
}

$funds = $givingService->getFunds(false);
?>

<?php if ($success): ?><div class="admin-alert admin-alert-success"><?= htmlspecialchars($success); ?></div><?php endif; ?>
<?php if ($error): ?><div class="admin-alert admin-alert-error"><?= htmlspecialchars($error); ?></div><?php endif; ?>

<div class="admin-actions-bar">
    <a href="/admin/giving" class="btn btn-outline">&larr; Back to Giving</a>
    <button type="button" class="btn btn-primary" data-open-modal="fund-modal" data-mode="create">+ Add Fund</button>
</div>

<div class="admin-card">
    <table class="data-table">
        <thead>
            <tr><th>Order</th><th>Fund</th><th>Goal</th><th>Status</th><th></th></tr>
        </thead>
        <tbody>
            <?php foreach ($funds as $f): ?>
                <tr>
                    <td><?= $f['sort_order']; ?></td>
                    <td>
                        <strong><?= htmlspecialchars($f['name']); ?></strong>
                        <?php if ($f['is_default']): ?><span class="badge badge-small">Default</span><?php endif; ?>
                        <?php if ($f['description']): ?><br><small class="text-muted"><?= htmlspecialchars($f['description']); ?></small><?php endif; ?>
                    </td>
                    <td>
                        <?php if ($f['goal_amount']): ?>
                            £<?= number_format($f['goal_amount'], 0); ?>
                            <?php if ($f['goal_deadline']): ?><br><small class="text-muted">by <?= date('M j, Y', strtotime($f['goal_deadline'])); ?></small><?php endif; ?>
                        <?php else: ?>
                            <span class="text-muted">-</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php if ($f['is_active']): ?>
                            <span class="badge badge-success">Active</span>
                        <?php else: ?>
                            <span class="badge badge-muted">Inactive</span>
                        <?php endif; ?>
                        <?php if ($f['display_on_form']): ?>
                            <span class="badge badge-small">On Form</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <button type="button" class="btn btn-xs btn-outline" data-open-modal="fund-modal" data-mode="edit"
                            data-id="<?= $f['id']; ?>" data-name="<?= htmlspecialchars($f['name']); ?>"
                            data-description="<?= htmlspecialchars($f['description'] ?? ''); ?>"
                            data-default="<?= $f['is_default']; ?>" data-active="<?= $f['is_active']; ?>"
                            data-goal="<?= $f['goal_amount'] ?? ''; ?>" data-deadline="<?= $f['goal_deadline'] ?? ''; ?>"
                            data-display="<?= $f['display_on_form']; ?>" data-order="<?= $f['sort_order']; ?>">Edit</button>
                        <form method="post" class="inline-form">
                            <?= csrf_field(); ?>
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="fund_id" value="<?= $f['id']; ?>">
                            <button type="submit" class="btn btn-xs btn-ghost btn-danger" data-confirm="Delete this fund?">Delete</button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

<!-- Fund Modal -->
<div class="modal-overlay" id="fund-modal">
    <div class="modal">
        <div class="modal-header">
            <h3 id="modal-title">Add Fund</h3>
            <button type="button" class="modal-close" data-close-modal>&times;</button>
        </div>
        <form method="post">
            <?= csrf_field(); ?>
            <input type="hidden" name="action" value="save">
            <input type="hidden" name="fund_id" id="fund-id">
            <div class="modal-body">
                <div class="form-group">
                    <label>Name *</label>
                    <input type="text" name="name" id="fund-name" required>
                </div>
                <div class="form-group">
                    <label>Description</label>
                    <textarea name="description" id="fund-description" rows="2"></textarea>
                </div>
                <div class="form-row-2">
                    <div class="form-group">
                        <label>Goal Amount (£)</label>
                        <input type="number" name="goal_amount" id="fund-goal" step="0.01" min="0">
                    </div>
                    <div class="form-group">
                        <label>Goal Deadline</label>
                        <input type="date" name="goal_deadline" id="fund-deadline">
                    </div>
                </div>
                <div class="form-group">
                    <label>Sort Order</label>
                    <input type="number" name="sort_order" id="fund-order" value="0" min="0">
                </div>
                <div class="form-group">
                    <label class="checkbox-label"><input type="checkbox" name="is_active" id="fund-active" value="1" checked> Active</label>
                </div>
                <div class="form-group">
                    <label class="checkbox-label"><input type="checkbox" name="is_default" id="fund-default" value="1"> Default fund</label>
                </div>
                <div class="form-group">
                    <label class="checkbox-label"><input type="checkbox" name="display_on_form" id="fund-display" value="1" checked> Show on giving form</label>
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
.inline-form { display: inline; }
.checkbox-label { display: flex; align-items: center; gap: 0.5rem; cursor: pointer; }
.checkbox-label input { width: auto; }
.form-row-2 { display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; }
.modal-overlay { display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.5); z-index: 1000; align-items: center; justify-content: center; }
.modal-overlay.active { display: flex; }
.modal { background: var(--color-surface); border-radius: var(--radius-lg); width: 100%; max-width: 500px; margin: 1rem; }
.modal-header { display: flex; justify-content: space-between; align-items: center; padding: 1rem; border-bottom: 1px solid var(--color-border); }
.modal-header h3 { margin: 0; }
.modal-close { background: none; border: none; font-size: 1.5rem; cursor: pointer; }
.modal-body { padding: 1rem; }
.modal-footer { display: flex; justify-content: flex-end; gap: 0.5rem; padding: 1rem; border-top: 1px solid var(--color-border); }
</style>

<script <?= csp_nonce(); ?>>
document.querySelectorAll('[data-open-modal]').forEach(b => {
    b.addEventListener('click', () => {
        const modal = document.getElementById(b.dataset.openModal);
        if (b.dataset.mode === 'edit') {
            document.getElementById('modal-title').textContent = 'Edit Fund';
            document.getElementById('fund-id').value = b.dataset.id;
            document.getElementById('fund-name').value = b.dataset.name;
            document.getElementById('fund-description').value = b.dataset.description;
            document.getElementById('fund-goal').value = b.dataset.goal;
            document.getElementById('fund-deadline').value = b.dataset.deadline;
            document.getElementById('fund-order').value = b.dataset.order;
            document.getElementById('fund-active').checked = b.dataset.active === '1';
            document.getElementById('fund-default').checked = b.dataset.default === '1';
            document.getElementById('fund-display').checked = b.dataset.display === '1';
        } else {
            document.getElementById('modal-title').textContent = 'Add Fund';
            document.getElementById('fund-id').value = '';
            document.getElementById('fund-name').value = '';
            document.getElementById('fund-description').value = '';
            document.getElementById('fund-goal').value = '';
            document.getElementById('fund-deadline').value = '';
            document.getElementById('fund-order').value = '0';
            document.getElementById('fund-active').checked = true;
            document.getElementById('fund-default').checked = false;
            document.getElementById('fund-display').checked = true;
        }
        modal?.classList.add('active');
    });
});
document.querySelectorAll('[data-close-modal]').forEach(b => b.addEventListener('click', () => b.closest('.modal-overlay')?.classList.remove('active')));
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
