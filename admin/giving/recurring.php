<?php
/**
 * Giving - Recurring Donations
 */

$page_title = 'Recurring Donations';
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../../includes/db-config.php';
require_once __DIR__ . '/../../includes/services/GivingService.php';

$pdo = getDbConnection();
$givingService = new GivingService($pdo);

$success = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && verify_csrf_token($_POST['csrf_token'] ?? '')) {
    if ($_POST['action'] === 'cancel') {
        $givingService->cancelRecurring((int)$_POST['id'], $_POST['reason'] ?? '');
        $success = 'Recurring donation cancelled';
    }
}

$status = $_GET['status'] ?? 'active';
$recurring = $givingService->getRecurringDonations(['status' => $status]);
?>

<?php if ($success): ?><div class="admin-alert admin-alert-success"><?= htmlspecialchars($success); ?></div><?php endif; ?>

<div class="admin-actions-bar">
    <a href="/admin/giving" class="btn btn-outline">&larr; Back to Giving</a>
    <div class="status-tabs">
        <a href="?status=active" class="tab <?= $status === 'active' ? 'active' : ''; ?>">Active</a>
        <a href="?status=paused" class="tab <?= $status === 'paused' ? 'active' : ''; ?>">Paused</a>
        <a href="?status=cancelled" class="tab <?= $status === 'cancelled' ? 'active' : ''; ?>">Cancelled</a>
    </div>
</div>

<div class="admin-card">
    <?php if (empty($recurring)): ?>
        <div class="empty-state">
            <p>No <?= $status; ?> recurring donations.</p>
        </div>
    <?php else: ?>
        <table class="data-table">
            <thead>
                <tr><th>Donor</th><th>Amount</th><th>Frequency</th><th>Fund</th><th>Stats</th><th>Next Payment</th><th></th></tr>
            </thead>
            <tbody>
                <?php foreach ($recurring as $r): ?>
                    <tr>
                        <td>
                            <?= htmlspecialchars($r['donor_name'] ?: $r['donor_email']); ?>
                            <br><small class="text-muted"><?= htmlspecialchars($r['donor_email']); ?></small>
                        </td>
                        <td>
                            <strong>£<?= number_format($r['amount'], 2); ?></strong>
                            <?php if ($r['gift_aid']): ?><span class="badge badge-small badge-success">Gift Aid</span><?php endif; ?>
                        </td>
                        <td><?= ucfirst($r['frequency']); ?></td>
                        <td><?= htmlspecialchars($r['fund_name'] ?? 'General'); ?></td>
                        <td>
                            <?= $r['payment_count']; ?> payments<br>
                            <small class="text-muted">£<?= number_format($r['total_given'], 2); ?> total</small>
                        </td>
                        <td>
                            <?php if ($r['status'] === 'active' && $r['next_payment_date']): ?>
                                <?= date('M j, Y', strtotime($r['next_payment_date'])); ?>
                            <?php elseif ($r['status'] === 'cancelled'): ?>
                                <span class="text-muted">Cancelled <?= $r['cancelled_at'] ? date('M j, Y', strtotime($r['cancelled_at'])) : ''; ?></span>
                            <?php else: ?>
                                <span class="text-muted">-</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($r['status'] === 'active'): ?>
                                <form method="post" class="inline-form">
                                    <?= csrf_field(); ?>
                                    <input type="hidden" name="action" value="cancel">
                                    <input type="hidden" name="id" value="<?= $r['id']; ?>">
                                    <button type="submit" class="btn btn-xs btn-danger" data-confirm="Cancel this recurring donation?">Cancel</button>
                                </form>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>

<style>
.status-tabs { display: flex; gap: 0.5rem; }
.tab { padding: 0.5rem 1rem; text-decoration: none; color: var(--color-text-muted); border-radius: var(--radius); }
.tab:hover { background: var(--color-surface-hover); }
.tab.active { background: var(--color-primary); color: white; }
.data-table { width: 100%; }
.data-table th, .data-table td { padding: 0.75rem 1rem; text-align: left; border-bottom: 1px solid var(--color-border); }
.inline-form { display: inline; }
.empty-state { padding: 3rem; text-align: center; color: var(--color-text-muted); }
</style>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
