<?php
/**
 * Groups Management - List View
 */

$page_title = 'Groups';
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../../includes/db-config.php';
require_once __DIR__ . '/../../includes/services/GroupsService.php';
require_once __DIR__ . '/../../includes/Pagination.php';

$pdo = getDbConnection();
$groupsService = new GroupsService($pdo);

$filters = [
    'search' => trim($_GET['search'] ?? ''),
    'type_id' => $_GET['type'] ?? null,
    'status' => $_GET['status'] ?? null,
    'day' => $_GET['day'] ?? null,
];

$page = max(1, (int)($_GET['page'] ?? 1));
$result = $groupsService->getGroups($filters, $page, 20);
$types = $groupsService->getGroupTypes();
$stats = $groupsService->getStats();

$days = ['sunday'=>'Sunday','monday'=>'Monday','tuesday'=>'Tuesday','wednesday'=>'Wednesday','thursday'=>'Thursday','friday'=>'Friday','saturday'=>'Saturday'];
?>

<!-- Stats Bar -->
<div class="stats-bar">
    <div class="stat-item">
        <span class="stat-value"><?= number_format($stats['totalGroups']); ?></span>
        <span class="stat-label">Active Groups</span>
    </div>
    <div class="stat-item">
        <span class="stat-value"><?= number_format($stats['totalMembers']); ?></span>
        <span class="stat-label">Total Members</span>
    </div>
    <?php if ($stats['pendingRequests'] > 0): ?>
    <div class="stat-item stat-alert">
        <span class="stat-value"><?= $stats['pendingRequests']; ?></span>
        <span class="stat-label">Pending Requests</span>
    </div>
    <?php endif; ?>
</div>

<!-- Filters -->
<div class="admin-card">
    <form method="get" class="filters-form">
        <div class="filter-row">
            <input type="text" name="search" value="<?= htmlspecialchars($filters['search']); ?>" placeholder="Search groups..." class="search-input">
            <select name="type" onchange="this.form.submit()">
                <option value="">All Types</option>
                <?php foreach ($types as $t): ?>
                    <option value="<?= $t['id']; ?>" <?= $filters['type_id'] == $t['id'] ? 'selected' : ''; ?>><?= htmlspecialchars($t['name']); ?></option>
                <?php endforeach; ?>
            </select>
            <select name="day" onchange="this.form.submit()">
                <option value="">Any Day</option>
                <?php foreach ($days as $k => $v): ?>
                    <option value="<?= $k; ?>" <?= $filters['day'] === $k ? 'selected' : ''; ?>><?= $v; ?></option>
                <?php endforeach; ?>
            </select>
            <select name="status" onchange="this.form.submit()">
                <option value="">All Status</option>
                <option value="active" <?= $filters['status'] === 'active' ? 'selected' : ''; ?>>Active</option>
                <option value="inactive" <?= $filters['status'] === 'inactive' ? 'selected' : ''; ?>>Inactive</option>
            </select>
            <button type="submit" class="btn btn-primary">Search</button>
            <?php if (array_filter($filters)): ?>
                <a href="/admin/groups" class="btn btn-outline">Clear</a>
            <?php endif; ?>
        </div>
    </form>
</div>

<!-- Actions -->
<div class="admin-actions-bar">
    <span class="results-count"><?= number_format($result['total']); ?> groups</span>
    <div class="actions-right">
        <a href="/admin/groups/edit.php" class="btn btn-primary">+ Create Group</a>
        <a href="/admin/groups/types.php" class="btn btn-outline">Manage Types</a>
    </div>
</div>

<!-- Groups Grid -->
<?php if (empty($result['items'])): ?>
    <div class="admin-card">
        <div class="empty-state">
            <h3>No groups found</h3>
            <p>Create your first group to get started.</p>
            <a href="/admin/groups/edit.php" class="btn btn-primary">Create Group</a>
        </div>
    </div>
<?php else: ?>
    <div class="groups-grid">
        <?php foreach ($result['items'] as $g): ?>
            <div class="group-card">
                <?php if ($g['image_url']): ?>
                    <div class="group-image" style="background-image: url('<?= htmlspecialchars($g['image_url']); ?>')"></div>
                <?php else: ?>
                    <div class="group-image group-image-placeholder" style="background: <?= htmlspecialchars($g['type_color'] ?? '#6B7280'); ?>">
                        <span><?= strtoupper(substr($g['name'], 0, 2)); ?></span>
                    </div>
                <?php endif; ?>
                <div class="group-content">
                    <div class="group-type" style="color: <?= htmlspecialchars($g['type_color'] ?? '#6B7280'); ?>"><?= htmlspecialchars($g['type_name']); ?></div>
                    <h3 class="group-name"><a href="/admin/groups/view.php?id=<?= $g['id']; ?>"><?= htmlspecialchars($g['name']); ?></a></h3>
                    <div class="group-meta">
                        <?php if ($g['meeting_day']): ?>
                            <span><?= ucfirst($g['meeting_day']); ?>s<?= $g['meeting_time'] ? ' @ ' . date('g:ia', strtotime($g['meeting_time'])) : ''; ?></span>
                        <?php endif; ?>
                        <?php if ($g['location_city']): ?>
                            <span><?= htmlspecialchars($g['location_city']); ?></span>
                        <?php endif; ?>
                    </div>
                    <div class="group-stats">
                        <span><?= $g['member_count']; ?> members</span>
                        <?php if ($g['leader_count']): ?><span><?= $g['leader_count']; ?> leaders</span><?php endif; ?>
                    </div>
                    <div class="group-actions">
                        <a href="/admin/groups/view.php?id=<?= $g['id']; ?>" class="btn btn-sm btn-outline">View</a>
                        <a href="/admin/groups/edit.php?id=<?= $g['id']; ?>" class="btn btn-sm btn-outline">Edit</a>
                    </div>
                </div>
                <?php if ($g['status'] !== 'active'): ?>
                    <span class="group-status-badge status-<?= $g['status']; ?>"><?= ucfirst($g['status']); ?></span>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>
    </div>

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

<style>
.stats-bar { display: flex; gap: 1.5rem; margin-bottom: 1rem; padding: 1rem; background: var(--color-surface); border-radius: var(--radius-lg); border: 1px solid var(--color-border); }
.stat-item { display: flex; flex-direction: column; }
.stat-value { font-size: 1.5rem; font-weight: 700; }
.stat-label { font-size: 0.75rem; color: var(--color-text-muted); text-transform: uppercase; }
.stat-alert .stat-value { color: var(--color-warning); }
.filters-form .filter-row { display: flex; gap: 0.5rem; flex-wrap: wrap; }
.search-input { flex: 1; min-width: 200px; }
.groups-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(300px, 1fr)); gap: 1.5rem; }
.group-card { background: var(--color-surface); border: 1px solid var(--color-border); border-radius: var(--radius-lg); overflow: hidden; position: relative; }
.group-image { height: 120px; background-size: cover; background-position: center; }
.group-image-placeholder { display: flex; align-items: center; justify-content: center; color: white; font-size: 2rem; font-weight: 700; }
.group-content { padding: 1rem; }
.group-type { font-size: 0.75rem; font-weight: 600; text-transform: uppercase; margin-bottom: 0.25rem; }
.group-name { margin: 0 0 0.5rem; font-size: 1.125rem; }
.group-name a { color: inherit; text-decoration: none; }
.group-name a:hover { color: var(--color-primary); }
.group-meta { font-size: 0.875rem; color: var(--color-text-muted); margin-bottom: 0.5rem; display: flex; gap: 1rem; }
.group-stats { font-size: 0.75rem; color: var(--color-text-muted); margin-bottom: 0.75rem; display: flex; gap: 1rem; }
.group-actions { display: flex; gap: 0.5rem; }
.group-status-badge { position: absolute; top: 0.5rem; right: 0.5rem; font-size: 0.625rem; padding: 0.25rem 0.5rem; border-radius: var(--radius); text-transform: uppercase; font-weight: 600; }
.status-inactive { background: var(--color-warning-bg); color: var(--color-warning); }
.status-archived { background: var(--color-surface-hover); color: var(--color-text-muted); }
.empty-state { text-align: center; padding: 3rem; }
.pagination-wrapper { margin-top: 1.5rem; }
</style>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
