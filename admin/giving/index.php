<?php
/**
 * Giving Management - Dashboard & Donations List
 */

$page_title = 'Giving';
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../../includes/db-config.php';
require_once __DIR__ . '/../../includes/services/GivingService.php';
require_once __DIR__ . '/../../includes/Pagination.php';

$pdo = getDbConnection();
$givingService = new GivingService($pdo);

$filters = [
    'search' => trim($_GET['search'] ?? ''),
    'fund_id' => $_GET['fund'] ?? null,
    'status' => $_GET['status'] ?? 'completed',
    'date_from' => $_GET['from'] ?? null,
    'date_to' => $_GET['to'] ?? null,
];

$page = max(1, (int)($_GET['page'] ?? 1));
$result = $givingService->getDonations($filters, $page, 25);
$funds = $givingService->getFunds(false);
$stats = $givingService->getStats();
$monthStats = $givingService->getStats('month');
?>

<!-- Stats -->
<div class="stats-grid">
    <div class="stat-card">
        <span class="stat-label">This Month</span>
        <span class="stat-value">£<?= number_format($monthStats['total_amount'], 2); ?></span>
        <span class="stat-sub"><?= $monthStats['total_donations']; ?> donations</span>
    </div>
    <div class="stat-card">
        <span class="stat-label">All Time</span>
        <span class="stat-value">£<?= number_format($stats['total_amount'], 2); ?></span>
        <span class="stat-sub"><?= $stats['unique_donors']; ?> donors</span>
    </div>
    <div class="stat-card">
        <span class="stat-label">Average Gift</span>
        <span class="stat-value">£<?= number_format($stats['avg_amount'], 2); ?></span>
    </div>
    <div class="stat-card">
        <span class="stat-label">Active Recurring</span>
        <span class="stat-value"><?= $stats['active_recurring']; ?></span>
        <span class="stat-sub">£<?= number_format($stats['recurring_monthly'], 2); ?>/mo</span>
    </div>
</div>

<!-- Filters -->
<div class="admin-card">
    <form method="get" class="filters-form">
        <div class="filter-row">
            <input type="text" name="search" value="<?= htmlspecialchars($filters['search']); ?>" placeholder="Search by email or name...">
            <select name="fund">
                <option value="">All Funds</option>
                <?php foreach ($funds as $f): ?>
                    <option value="<?= $f['id']; ?>" <?= $filters['fund_id'] == $f['id'] ? 'selected' : ''; ?>><?= htmlspecialchars($f['name']); ?></option>
                <?php endforeach; ?>
            </select>
            <select name="status">
                <option value="">All Status</option>
                <option value="completed" <?= $filters['status'] === 'completed' ? 'selected' : ''; ?>>Completed</option>
                <option value="pending" <?= $filters['status'] === 'pending' ? 'selected' : ''; ?>>Pending</option>
                <option value="refunded" <?= $filters['status'] === 'refunded' ? 'selected' : ''; ?>>Refunded</option>
            </select>
            <input type="date" name="from" value="<?= htmlspecialchars($filters['date_from'] ?? ''); ?>" placeholder="From">
            <input type="date" name="to" value="<?= htmlspecialchars($filters['date_to'] ?? ''); ?>" placeholder="To">
            <button type="submit" class="btn btn-primary">Filter</button>
            <?php if (array_filter($filters)): ?>
                <a href="/admin/giving" class="btn btn-outline">Clear</a>
            <?php endif; ?>
        </div>
    </form>
</div>

<!-- Actions Bar -->
<div class="admin-actions-bar">
    <span class="results-count"><?= number_format($result['total']); ?> donations</span>
    <div class="actions-right">
        <a href="/admin/giving/add.php" class="btn btn-primary">+ Record Donation</a>
        <a href="/admin/giving/recurring.php" class="btn btn-outline">Recurring</a>
        <a href="/admin/giving/funds.php" class="btn btn-outline">Manage Funds</a>
    </div>
</div>

<!-- Donations Table -->
<div class="admin-card">
    <?php if (empty($result['items'])): ?>
        <div class="empty-state">
            <p>No donations found.</p>
        </div>
    <?php else: ?>
        <table class="data-table">
            <thead>
                <tr>
                    <th>Date</th>
                    <th>Donor</th>
                    <th>Amount</th>
                    <th>Fund</th>
                    <th>Type</th>
                    <th>Status</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($result['items'] as $d): ?>
                    <tr>
                        <td><?= date('M j, Y', strtotime($d['donated_at'])); ?></td>
                        <td>
                            <?php if ($d['user_id']): ?>
                                <a href="/admin/people?page=view&id=<?= $d['user_id']; ?>"><?= htmlspecialchars($d['donor_name'] ?: $d['first_name'] . ' ' . $d['last_name']); ?></a>
                            <?php else: ?>
                                <?= htmlspecialchars($d['donor_name'] ?: $d['donor_email']); ?>
                            <?php endif; ?>
                            <br><small class="text-muted"><?= htmlspecialchars($d['donor_email']); ?></small>
                        </td>
                        <td class="amount-cell">
                            <strong>£<?= number_format($d['amount'], 2); ?></strong>
                            <?php if ($d['gift_aid']): ?>
                                <span class="badge badge-success badge-small">Gift Aid</span>
                            <?php endif; ?>
                        </td>
                        <td><?= htmlspecialchars($d['fund_name'] ?? 'General'); ?></td>
                        <td>
                            <?php if ($d['frequency'] !== 'one-time'): ?>
                                <span class="badge"><?= ucfirst($d['frequency']); ?></span>
                            <?php else: ?>
                                One-time
                            <?php endif; ?>
                        </td>
                        <td>
                            <span class="status-badge status-<?= $d['status']; ?>"><?= ucfirst($d['status']); ?></span>
                        </td>
                        <td>
                            <a href="/admin/giving/view.php?id=<?= $d['id']; ?>" class="btn btn-xs btn-outline">View</a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <?php if ($result['total_pages'] > 1): ?>
            <div class="pagination-wrapper">
                <?php
                $pagination = new Pagination($result['total'], $result['per_page'], $result['page']);
                $url = '?' . http_build_query(array_filter($filters)) . '&page={page}';
                echo $pagination->render($url);
                ?>
            </div>
        <?php endif; ?>
    <?php endif; ?>
</div>

<style>
.stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1rem; margin-bottom: 1.5rem; }
.stat-card { background: var(--color-surface); border: 1px solid var(--color-border); border-radius: var(--radius-lg); padding: 1.25rem; }
.stat-label { font-size: 0.75rem; color: var(--color-text-muted); text-transform: uppercase; }
.stat-value { display: block; font-size: 1.75rem; font-weight: 700; margin: 0.25rem 0; }
.stat-sub { font-size: 0.875rem; color: var(--color-text-muted); }
.filters-form .filter-row { display: flex; gap: 0.5rem; flex-wrap: wrap; }
.filters-form input[type="text"] { flex: 1; min-width: 200px; }
.data-table { width: 100%; }
.data-table th, .data-table td { padding: 0.75rem 1rem; text-align: left; border-bottom: 1px solid var(--color-border); }
.data-table th { font-size: 0.75rem; text-transform: uppercase; color: var(--color-text-muted); }
.amount-cell { white-space: nowrap; }
.status-completed { background: var(--color-success-bg); color: var(--color-success); }
.status-pending { background: var(--color-warning-bg); color: var(--color-warning); }
.status-refunded { background: var(--color-surface-hover); color: var(--color-text-muted); }
.status-badge { font-size: 0.75rem; padding: 0.25rem 0.5rem; border-radius: var(--radius); }
.badge-small { font-size: 0.625rem; padding: 0.125rem 0.375rem; vertical-align: middle; }
</style>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
