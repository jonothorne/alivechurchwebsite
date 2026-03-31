<?php
/**
 * People Management - List View
 *
 * Main directory of all people/members with filtering and search.
 */

$page_title = 'People';
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../../includes/db-config.php';
require_once __DIR__ . '/../../includes/services/PeopleService.php';
require_once __DIR__ . '/../../includes/Pagination.php';

$pdo = getDbConnection();
$peopleService = new PeopleService($pdo);

// Get filter parameters
$filters = [
    'search' => trim($_GET['search'] ?? ''),
    'status_id' => $_GET['status'] ?? null,
    'is_member' => isset($_GET['members']) ? (bool)$_GET['members'] : null,
    'tag_id' => $_GET['tag'] ?? null,
    'order_by' => $_GET['sort'] ?? 'last_name',
    'order_dir' => $_GET['dir'] ?? 'ASC',
];

$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 25;

// Get people list
$result = $peopleService->getPeople($filters, $page, $perPage);
$people = $result['items'];

// Get filter options
$statuses = $peopleService->getMembershipStatuses();
$tags = $peopleService->getTagsWithCounts();
$stats = $peopleService->getStats();

// Build current URL for pagination/sorting
function buildUrl($params = []) {
    $current = $_GET;
    $merged = array_merge($current, $params);
    // Remove empty values
    $merged = array_filter($merged, fn($v) => $v !== '' && $v !== null);
    return '?' . http_build_query($merged);
}

function sortLink($column, $label) {
    global $filters;
    $isActive = $filters['order_by'] === $column;
    $newDir = ($isActive && $filters['order_dir'] === 'ASC') ? 'DESC' : 'ASC';
    $url = buildUrl(['sort' => $column, 'dir' => $newDir, 'page' => 1]);
    $arrow = '';
    if ($isActive) {
        $arrow = $filters['order_dir'] === 'ASC' ? ' ↑' : ' ↓';
    }
    return '<a href="' . htmlspecialchars($url) . '" class="' . ($isActive ? 'active' : '') . '">' . $label . $arrow . '</a>';
}
?>

<!-- Stats Bar -->
<div class="people-stats-bar">
    <div class="stat-item">
        <span class="stat-value"><?= number_format($stats['total_people']); ?></span>
        <span class="stat-label">Total People</span>
    </div>
    <div class="stat-item">
        <span class="stat-value"><?= number_format($stats['total_members']); ?></span>
        <span class="stat-label">Members</span>
    </div>
    <div class="stat-item">
        <span class="stat-value"><?= number_format($stats['total_households']); ?></span>
        <span class="stat-label">Households</span>
    </div>
    <div class="stat-item">
        <span class="stat-value"><?= number_format($stats['new_this_month']); ?></span>
        <span class="stat-label">New This Month</span>
    </div>
</div>

<!-- Filters -->
<div class="admin-card">
    <form method="get" class="people-filters">
        <div class="filter-row">
            <div class="search-box">
                <input type="text" name="search" value="<?= htmlspecialchars($filters['search']); ?>"
                       placeholder="Search by name or email..." class="search-input">
                <button type="submit" class="btn btn-sm btn-primary">Search</button>
            </div>

            <div class="filter-group">
                <select name="status" onchange="this.form.submit()">
                    <option value="">All Statuses</option>
                    <?php foreach ($statuses as $status): ?>
                        <option value="<?= $status['id']; ?>" <?= $filters['status_id'] == $status['id'] ? 'selected' : ''; ?>>
                            <?= htmlspecialchars($status['name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>

                <select name="members" onchange="this.form.submit()">
                    <option value="">All People</option>
                    <option value="1" <?= $filters['is_member'] === true ? 'selected' : ''; ?>>Members Only</option>
                    <option value="0" <?= $filters['is_member'] === false ? 'selected' : ''; ?>>Non-Members</option>
                </select>

                <select name="tag" onchange="this.form.submit()">
                    <option value="">All Tags</option>
                    <?php foreach ($tags as $tag): ?>
                        <option value="<?= $tag['id']; ?>" <?= $filters['tag_id'] == $tag['id'] ? 'selected' : ''; ?>>
                            <?= htmlspecialchars($tag['name']); ?> (<?= $tag['user_count']; ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <?php if ($filters['search'] || $filters['status_id'] || $filters['is_member'] !== null || $filters['tag_id']): ?>
                <a href="/admin/people" class="btn btn-sm btn-outline">Clear Filters</a>
            <?php endif; ?>
        </div>
    </form>
</div>

<!-- Actions Bar -->
<div class="admin-actions-bar">
    <div class="actions-left">
        <span class="results-count">
            <?= number_format($result['total']); ?> people
            <?php if ($filters['search'] || $filters['status_id'] || $filters['is_member'] !== null || $filters['tag_id']): ?>
                (filtered)
            <?php endif; ?>
        </span>
    </div>
    <div class="actions-right">
        <a href="/admin/people?page=edit" class="btn btn-primary">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/>
            </svg>
            Add Person
        </a>
        <a href="/admin/people?page=lists" class="btn btn-outline">Lists</a>
        <a href="/admin/people?page=households" class="btn btn-outline">Households</a>
        <a href="/admin/people?page=tags" class="btn btn-outline">Manage Tags</a>
    </div>
</div>

<!-- People Table -->
<div class="admin-card">
    <?php if (empty($people)): ?>
        <div class="empty-state">
            <div class="empty-state-icon">
                <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                    <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/>
                    <circle cx="9" cy="7" r="4"/>
                    <path d="M23 21v-2a4 4 0 0 0-3-3.87"/>
                    <path d="M16 3.13a4 4 0 0 1 0 7.75"/>
                </svg>
            </div>
            <h3>No people found</h3>
            <?php if ($filters['search'] || $filters['status_id'] || $filters['is_member'] !== null || $filters['tag_id']): ?>
                <p>Try adjusting your filters or <a href="/admin/people">clear all filters</a>.</p>
            <?php else: ?>
                <p>Add your first person to get started.</p>
                <a href="/admin/people?page=edit" class="btn btn-primary">Add Person</a>
            <?php endif; ?>
        </div>
    <?php else: ?>
        <div class="table-container">
            <table class="people-table">
                <thead>
                    <tr>
                        <th class="col-photo"></th>
                        <th class="col-name"><?= sortLink('last_name', 'Name'); ?></th>
                        <th class="col-email"><?= sortLink('email', 'Email'); ?></th>
                        <th class="col-status">Status</th>
                        <th class="col-household">Household</th>
                        <th class="col-actions"></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($people as $person): ?>
                        <tr>
                            <td class="col-photo">
                                <?php if (!empty($person['profile_photo'])): ?>
                                    <img src="<?= htmlspecialchars($person['profile_photo']); ?>"
                                         alt="" class="person-avatar">
                                <?php else: ?>
                                    <div class="person-avatar person-avatar-initials">
                                        <?= strtoupper(substr($person['first_name'] ?? $person['email'], 0, 1)); ?>
                                    </div>
                                <?php endif; ?>
                            </td>
                            <td class="col-name">
                                <a href="/admin/people?page=view&id=<?= $person['id']; ?>" class="person-name-link">
                                    <strong><?= htmlspecialchars(trim(($person['first_name'] ?? '') . ' ' . ($person['last_name'] ?? '')) ?: $person['email']); ?></strong>
                                    <?php if ($person['nickname']): ?>
                                        <span class="nickname">(<?= htmlspecialchars($person['nickname']); ?>)</span>
                                    <?php endif; ?>
                                </a>
                                <?php if ($person['is_member']): ?>
                                    <span class="badge badge-member">Member</span>
                                <?php endif; ?>
                            </td>
                            <td class="col-email">
                                <a href="mailto:<?= htmlspecialchars($person['email']); ?>" class="email-link">
                                    <?= htmlspecialchars($person['email']); ?>
                                </a>
                            </td>
                            <td class="col-status">
                                <?php if ($person['status_name']): ?>
                                    <span class="status-badge" style="--status-color: <?= htmlspecialchars($person['status_color'] ?? '#6B7280'); ?>">
                                        <?= htmlspecialchars($person['status_name']); ?>
                                    </span>
                                <?php else: ?>
                                    <span class="text-muted">-</span>
                                <?php endif; ?>
                            </td>
                            <td class="col-household">
                                <?php if ($person['household_name']): ?>
                                    <span class="household-name"><?= htmlspecialchars($person['household_name']); ?></span>
                                    <?php if ($person['household_role']): ?>
                                        <span class="household-role">(<?= htmlspecialchars($person['household_role']); ?>)</span>
                                    <?php endif; ?>
                                <?php else: ?>
                                    <span class="text-muted">-</span>
                                <?php endif; ?>
                            </td>
                            <td class="col-actions">
                                <a href="/admin/people?page=view&id=<?= $person['id']; ?>" class="btn btn-xs btn-outline">View</a>
                                <a href="/admin/people?page=edit&id=<?= $person['id']; ?>" class="btn btn-xs btn-outline">Edit</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <?php if ($result['total_pages'] > 1): ?>
            <div class="pagination-wrapper">
                <?php
                $pagination = new Pagination($result['total'], $perPage, $page);
                echo $pagination->render(buildUrl(['page' => '{page}']));
                ?>
            </div>
        <?php endif; ?>
    <?php endif; ?>
</div>

<style>
/* People Stats Bar */
.people-stats-bar {
    display: flex;
    gap: 1.5rem;
    margin-bottom: 1rem;
    padding: 1rem 1.25rem;
    background: var(--color-surface);
    border-radius: var(--radius-lg);
    border: 1px solid var(--color-border);
}

.stat-item {
    display: flex;
    flex-direction: column;
    gap: 0.125rem;
}

.stat-value {
    font-size: 1.5rem;
    font-weight: 700;
    color: var(--color-text);
    line-height: 1;
}

.stat-label {
    font-size: 0.75rem;
    color: var(--color-text-muted);
    text-transform: uppercase;
    letter-spacing: 0.05em;
}

/* People Filters */
.people-filters .filter-row {
    display: flex;
    align-items: center;
    gap: 1rem;
    flex-wrap: wrap;
}

.search-box {
    display: flex;
    gap: 0.5rem;
    flex: 1;
    max-width: 400px;
}

.search-input {
    flex: 1;
}

.filter-group {
    display: flex;
    gap: 0.5rem;
}

.filter-group select {
    min-width: 140px;
}

/* Actions Bar */
.admin-actions-bar {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 1rem;
}

.results-count {
    font-size: 0.875rem;
    color: var(--color-text-muted);
}

.actions-right {
    display: flex;
    gap: 0.5rem;
}

/* People Table */
.people-table {
    width: 100%;
}

.people-table th {
    font-weight: 600;
    text-align: left;
    padding: 0.75rem 1rem;
    border-bottom: 2px solid var(--color-border);
    font-size: 0.75rem;
    text-transform: uppercase;
    letter-spacing: 0.05em;
    color: var(--color-text-muted);
}

.people-table th a {
    color: inherit;
    text-decoration: none;
}

.people-table th a:hover,
.people-table th a.active {
    color: var(--color-primary);
}

.people-table td {
    padding: 0.75rem 1rem;
    border-bottom: 1px solid var(--color-border);
    vertical-align: middle;
}

.people-table tbody tr:hover {
    background: var(--color-surface-hover);
}

.col-photo {
    width: 48px;
    padding-right: 0 !important;
}

.person-avatar {
    width: 36px;
    height: 36px;
    border-radius: 50%;
    object-fit: cover;
}

.person-avatar-initials {
    display: flex;
    align-items: center;
    justify-content: center;
    background: var(--color-primary);
    color: white;
    font-weight: 600;
    font-size: 0.875rem;
}

.person-name-link {
    color: var(--color-text);
    text-decoration: none;
}

.person-name-link:hover {
    color: var(--color-primary);
}

.nickname {
    color: var(--color-text-muted);
    font-weight: 400;
}

.badge-member {
    display: inline-block;
    padding: 0.125rem 0.5rem;
    font-size: 0.625rem;
    font-weight: 600;
    text-transform: uppercase;
    background: var(--color-success-bg);
    color: var(--color-success);
    border-radius: var(--radius-full);
    margin-left: 0.5rem;
    vertical-align: middle;
}

.email-link {
    color: var(--color-text-muted);
    text-decoration: none;
    font-size: 0.875rem;
}

.email-link:hover {
    color: var(--color-primary);
}

.status-badge {
    display: inline-block;
    padding: 0.25rem 0.5rem;
    font-size: 0.75rem;
    font-weight: 500;
    background: color-mix(in srgb, var(--status-color) 15%, transparent);
    color: var(--status-color);
    border-radius: var(--radius);
}

.household-name {
    font-size: 0.875rem;
}

.household-role {
    color: var(--color-text-muted);
    font-size: 0.75rem;
}

.text-muted {
    color: var(--color-text-muted);
}

.col-actions {
    text-align: right;
    white-space: nowrap;
}

/* Empty State */
.empty-state {
    text-align: center;
    padding: 3rem 1rem;
}

.empty-state-icon {
    color: var(--color-text-muted);
    margin-bottom: 1rem;
}

.empty-state h3 {
    margin: 0 0 0.5rem;
    font-size: 1.125rem;
}

.empty-state p {
    color: var(--color-text-muted);
    margin: 0 0 1rem;
}

/* Pagination */
.pagination-wrapper {
    padding: 1rem;
    border-top: 1px solid var(--color-border);
}

@media (max-width: 768px) {
    .people-stats-bar {
        flex-wrap: wrap;
    }

    .people-filters .filter-row {
        flex-direction: column;
        align-items: stretch;
    }

    .search-box {
        max-width: none;
    }

    .filter-group {
        flex-wrap: wrap;
    }

    .admin-actions-bar {
        flex-direction: column;
        gap: 1rem;
        align-items: stretch;
    }

    .actions-right {
        flex-wrap: wrap;
    }

    .col-household,
    .col-status {
        display: none;
    }
}
</style>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
