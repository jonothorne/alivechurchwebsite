<?php
/**
 * People Management - List View (New Admin)
 *
 * Main directory of all people/members with filtering and search.
 */

$page_title = 'People';
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../../includes/services/PeopleService.php';
require_once __DIR__ . '/../../includes/Pagination.php';

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

$page_num = max(1, (int)($_GET['p'] ?? 1));
$perPage = 25;

// Get people list
$result = $peopleService->getPeople($filters, $page_num, $perPage);
$people = $result['items'];

// Get filter options
$statuses = $peopleService->getMembershipStatuses();
$tags = $peopleService->getTagsWithCounts();
$stats = $peopleService->getStats();

// Build URL helper
function buildPeopleUrl($params = []) {
    $current = $_GET;
    $merged = array_merge($current, $params);
    $merged = array_filter($merged, fn($v) => $v !== '' && $v !== null);
    // Ensure module stays set
    $merged['module'] = 'people';
    return '/adminnew?' . http_build_query($merged);
}

function sortLink($column, $label) {
    global $filters;
    $isActive = $filters['order_by'] === $column;
    $newDir = ($isActive && $filters['order_dir'] === 'ASC') ? 'DESC' : 'ASC';
    $url = buildPeopleUrl(['sort' => $column, 'dir' => $newDir, 'p' => 1]);
    $arrow = '';
    if ($isActive) {
        $arrow = $filters['order_dir'] === 'ASC'
            ? ' <svg width="12" height="12" viewBox="0 0 24 24" fill="currentColor"><path d="M7 14l5-5 5 5z"/></svg>'
            : ' <svg width="12" height="12" viewBox="0 0 24 24" fill="currentColor"><path d="M7 10l5 5 5-5z"/></svg>';
    }
    return '<a href="' . htmlspecialchars($url) . '" style="display: inline-flex; align-items: center; gap: 0.25rem;">' . $label . $arrow . '</a>';
}
?>

<!-- Page Header -->
<div class="admin-page-header">
    <div>
        <h1 class="admin-page-title">People</h1>
        <p class="admin-page-subtitle">Manage church members and contacts</p>
    </div>
    <div class="admin-page-actions">
        <a href="<?= buildPeopleUrl(['page' => 'tags']); ?>" class="admin-btn admin-btn-secondary">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M20.59 13.41l-7.17 7.17a2 2 0 0 1-2.83 0L2 12V2h10l8.59 8.59a2 2 0 0 1 0 2.82z"/>
                <line x1="7" y1="7" x2="7.01" y2="7"/>
            </svg>
            Tags
        </a>
        <a href="<?= buildPeopleUrl(['page' => 'households']); ?>" class="admin-btn admin-btn-secondary">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/>
                <polyline points="9 22 9 12 15 12 15 22"/>
            </svg>
            Households
        </a>
        <a href="<?= buildPeopleUrl(['page' => 'edit']); ?>" class="admin-btn admin-btn-primary">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/>
            </svg>
            Add Person
        </a>
    </div>
</div>

<!-- Stats Grid -->
<div class="admin-stats-grid">
    <div class="admin-stat-card">
        <div class="admin-stat-icon primary">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/>
                <circle cx="9" cy="7" r="4"/>
            </svg>
        </div>
        <div class="admin-stat-content">
            <div class="admin-stat-value"><?= number_format($stats['total_people']); ?></div>
            <div class="admin-stat-label">Total People</div>
        </div>
    </div>

    <div class="admin-stat-card">
        <div class="admin-stat-icon success">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/>
                <polyline points="22 4 12 14.01 9 11.01"/>
            </svg>
        </div>
        <div class="admin-stat-content">
            <div class="admin-stat-value"><?= number_format($stats['total_members']); ?></div>
            <div class="admin-stat-label">Members</div>
        </div>
    </div>

    <div class="admin-stat-card">
        <div class="admin-stat-icon info">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/>
                <polyline points="9 22 9 12 15 12 15 22"/>
            </svg>
        </div>
        <div class="admin-stat-content">
            <div class="admin-stat-value"><?= number_format($stats['total_households']); ?></div>
            <div class="admin-stat-label">Households</div>
        </div>
    </div>

    <div class="admin-stat-card">
        <div class="admin-stat-icon warning">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M12 2L15.09 8.26L22 9.27L17 14.14L18.18 21.02L12 17.77L5.82 21.02L7 14.14L2 9.27L8.91 8.26L12 2Z"/>
            </svg>
        </div>
        <div class="admin-stat-content">
            <div class="admin-stat-value"><?= number_format($stats['new_this_month']); ?></div>
            <div class="admin-stat-label">New This Month</div>
        </div>
    </div>
</div>

<!-- Filters Card -->
<div class="admin-card admin-mb-lg">
    <div class="admin-card-body">
        <form method="get" action="/adminnew" style="display: flex; align-items: center; gap: 1rem; flex-wrap: wrap;">
            <input type="hidden" name="module" value="people">

            <!-- Search -->
            <div style="flex: 1; min-width: 200px; max-width: 350px; position: relative;">
                <svg style="position: absolute; left: 0.75rem; top: 50%; transform: translateY(-50%); width: 18px; height: 18px; color: var(--admin-text-muted); pointer-events: none;" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/>
                </svg>
                <input type="text" name="search" value="<?= htmlspecialchars($filters['search']); ?>"
                       placeholder="Search by name or email..."
                       class="admin-form-input" style="padding-left: 2.5rem;">
            </div>

            <!-- Status Filter -->
            <select name="status" class="admin-form-select" style="width: auto; min-width: 150px;" onchange="this.form.submit()">
                <option value="">All Statuses</option>
                <?php foreach ($statuses as $status): ?>
                <option value="<?= $status['id']; ?>" <?= $filters['status_id'] == $status['id'] ? 'selected' : ''; ?>>
                    <?= htmlspecialchars($status['name']); ?>
                </option>
                <?php endforeach; ?>
            </select>

            <!-- Member Filter -->
            <select name="members" class="admin-form-select" style="width: auto; min-width: 140px;" onchange="this.form.submit()">
                <option value="">All People</option>
                <option value="1" <?= $filters['is_member'] === true ? 'selected' : ''; ?>>Members Only</option>
                <option value="0" <?= $filters['is_member'] === false ? 'selected' : ''; ?>>Non-Members</option>
            </select>

            <!-- Tag Filter -->
            <?php if (!empty($tags)): ?>
            <select name="tag" class="admin-form-select" style="width: auto; min-width: 150px;" onchange="this.form.submit()">
                <option value="">All Tags</option>
                <?php foreach ($tags as $tag): ?>
                <option value="<?= $tag['id']; ?>" <?= $filters['tag_id'] == $tag['id'] ? 'selected' : ''; ?>>
                    <?= htmlspecialchars($tag['name']); ?> (<?= $tag['user_count']; ?>)
                </option>
                <?php endforeach; ?>
            </select>
            <?php endif; ?>

            <button type="submit" class="admin-btn admin-btn-primary">Search</button>

            <?php if ($filters['search'] || $filters['status_id'] || $filters['is_member'] !== null || $filters['tag_id']): ?>
            <a href="/adminnew/people" class="admin-btn admin-btn-secondary">Clear</a>
            <?php endif; ?>
        </form>
    </div>
</div>

<!-- Results Info -->
<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem;">
    <span class="admin-text-muted">
        <?= number_format($result['total']); ?> people found
        <?php if ($filters['search'] || $filters['status_id'] || $filters['is_member'] !== null || $filters['tag_id']): ?>
        (filtered)
        <?php endif; ?>
    </span>
</div>

<!-- People Table -->
<div class="admin-card">
    <?php if (empty($people)): ?>
    <div class="admin-card-body">
        <div class="admin-empty-state">
            <svg class="admin-empty-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/>
                <circle cx="9" cy="7" r="4"/>
                <path d="M23 21v-2a4 4 0 0 0-3-3.87"/>
                <path d="M16 3.13a4 4 0 0 1 0 7.75"/>
            </svg>
            <h3 class="admin-empty-title">No people found</h3>
            <?php if ($filters['search'] || $filters['status_id'] || $filters['is_member'] !== null || $filters['tag_id']): ?>
            <p class="admin-empty-text">Try adjusting your filters or <a href="/adminnew/people">clear all filters</a>.</p>
            <?php else: ?>
            <p class="admin-empty-text">Add your first person to get started.</p>
            <a href="<?= buildPeopleUrl(['page' => 'edit']); ?>" class="admin-btn admin-btn-primary">Add Person</a>
            <?php endif; ?>
        </div>
    </div>
    <?php else: ?>
    <div class="admin-table-wrapper">
        <table class="admin-table">
            <thead>
                <tr>
                    <th style="width: 48px;"></th>
                    <th><?= sortLink('last_name', 'Name'); ?></th>
                    <th><?= sortLink('email', 'Email'); ?></th>
                    <th>Status</th>
                    <th>Household</th>
                    <th style="width: 120px;"></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($people as $person): ?>
                <tr>
                    <td>
                        <?php if (!empty($person['profile_photo'])): ?>
                        <img src="<?= htmlspecialchars($person['profile_photo']); ?>" alt=""
                             style="width: 36px; height: 36px; border-radius: 50%; object-fit: cover;">
                        <?php else: ?>
                        <div style="width: 36px; height: 36px; border-radius: 50%; background: var(--admin-primary); color: white; display: flex; align-items: center; justify-content: center; font-weight: 600; font-size: 0.875rem;">
                            <?= strtoupper(substr($person['first_name'] ?? $person['email'], 0, 1)); ?>
                        </div>
                        <?php endif; ?>
                    </td>
                    <td>
                        <div>
                            <a href="<?= buildPeopleUrl(['page' => 'view', 'id' => $person['id']]); ?>" style="font-weight: 500; color: var(--admin-text); text-decoration: none;">
                                <?= htmlspecialchars(trim(($person['first_name'] ?? '') . ' ' . ($person['last_name'] ?? '')) ?: $person['email']); ?>
                            </a>
                            <?php if ($person['is_member']): ?>
                            <span class="admin-badge admin-badge-success" style="margin-left: 0.5rem;">Member</span>
                            <?php endif; ?>
                        </div>
                        <?php if ($person['nickname']): ?>
                        <div class="admin-text-muted" style="font-size: 0.8125rem;">"<?= htmlspecialchars($person['nickname']); ?>"</div>
                        <?php endif; ?>
                    </td>
                    <td>
                        <a href="mailto:<?= htmlspecialchars($person['email']); ?>" class="admin-text-muted" style="text-decoration: none;">
                            <?= htmlspecialchars($person['email']); ?>
                        </a>
                    </td>
                    <td>
                        <?php if ($person['status_name']): ?>
                        <span class="admin-badge" style="background: <?= htmlspecialchars($person['status_color'] ?? '#6b7280'); ?>20; color: <?= htmlspecialchars($person['status_color'] ?? '#6b7280'); ?>;">
                            <?= htmlspecialchars($person['status_name']); ?>
                        </span>
                        <?php else: ?>
                        <span class="admin-text-muted">-</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php if ($person['household_name']): ?>
                        <div style="font-size: 0.875rem;"><?= htmlspecialchars($person['household_name']); ?></div>
                        <?php if ($person['household_role']): ?>
                        <div class="admin-text-muted" style="font-size: 0.75rem;"><?= ucfirst(htmlspecialchars($person['household_role'])); ?></div>
                        <?php endif; ?>
                        <?php else: ?>
                        <span class="admin-text-muted">-</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <div class="admin-table-actions">
                            <a href="<?= buildPeopleUrl(['page' => 'view', 'id' => $person['id']]); ?>" class="admin-btn admin-btn-secondary admin-btn-sm">View</a>
                            <a href="<?= buildPeopleUrl(['page' => 'edit', 'id' => $person['id']]); ?>" class="admin-btn admin-btn-secondary admin-btn-sm">Edit</a>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <?php if ($result['total_pages'] > 1): ?>
    <div class="admin-card-footer" style="display: flex; justify-content: center; gap: 0.5rem;">
        <?php if ($page_num > 1): ?>
        <a href="<?= buildPeopleUrl(['p' => $page_num - 1]); ?>" class="admin-btn admin-btn-secondary admin-btn-sm">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <polyline points="15 18 9 12 15 6"/>
            </svg>
            Previous
        </a>
        <?php endif; ?>

        <span class="admin-text-muted" style="padding: 0.25rem 0.5rem;">
            Page <?= $page_num; ?> of <?= $result['total_pages']; ?>
        </span>

        <?php if ($page_num < $result['total_pages']): ?>
        <a href="<?= buildPeopleUrl(['p' => $page_num + 1]); ?>" class="admin-btn admin-btn-secondary admin-btn-sm">
            Next
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <polyline points="9 18 15 12 9 6"/>
            </svg>
        </a>
        <?php endif; ?>
    </div>
    <?php endif; ?>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
