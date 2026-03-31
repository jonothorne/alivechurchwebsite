<?php
/**
 * Public Groups Finder
 */

require __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/db-config.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/services/GroupsService.php';

$page_title = 'Find a Group | ' . $site['name'];
$page_description = 'Find a small group at Alive Church Norwich. Connect with others in your area through life groups, Bible studies, and more.';

$pdo = getDbConnection();
$groupsService = new GroupsService($pdo);

$filters = [
    'search' => trim($_GET['q'] ?? ''),
    'type_id' => $_GET['type'] ?? null,
    'day' => $_GET['day'] ?? null,
    'page' => max(1, (int)($_GET['page'] ?? 1)),
    'per_page' => 12,
];

$result = $groupsService->getPublicGroups($filters);
$types = $groupsService->getGroupTypes();

$days = ['sunday'=>'Sunday','monday'=>'Monday','tuesday'=>'Tuesday','wednesday'=>'Wednesday','thursday'=>'Thursday','friday'=>'Friday','saturday'=>'Saturday'];

include __DIR__ . '/../includes/header.php';
?>

<section class="page-hero">
    <div class="container narrow">
        <p class="eyebrow">Connect</p>
        <h1>Find a Group</h1>
        <p>Life change happens best in the context of community. Find a group near you.</p>
    </div>
</section>

<section class="groups-finder">
    <div class="container">
        <!-- Filters -->
        <form method="get" class="groups-filters">
            <div class="filter-bar">
                <input type="text" name="q" value="<?= htmlspecialchars($filters['search']); ?>" placeholder="Search groups..." class="search-input">
                <select name="type">
                    <option value="">All Types</option>
                    <?php foreach ($types as $t): ?>
                        <option value="<?= $t['id']; ?>" <?= $filters['type_id'] == $t['id'] ? 'selected' : ''; ?>><?= htmlspecialchars($t['name']); ?></option>
                    <?php endforeach; ?>
                </select>
                <select name="day">
                    <option value="">Any Day</option>
                    <?php foreach ($days as $k => $v): ?>
                        <option value="<?= $k; ?>" <?= $filters['day'] === $k ? 'selected' : ''; ?>><?= $v; ?></option>
                    <?php endforeach; ?>
                </select>
                <button type="submit" class="btn btn-primary">Search</button>
            </div>
        </form>

        <!-- Results -->
        <?php if (empty($result['items'])): ?>
            <div class="no-results">
                <h3>No groups found</h3>
                <p>Try adjusting your search or filters.</p>
            </div>
        <?php else: ?>
            <div class="groups-grid">
                <?php foreach ($result['items'] as $g): ?>
                    <article class="group-card">
                        <?php if ($g['image_url']): ?>
                            <div class="group-card-image" style="background-image: url('<?= htmlspecialchars($g['image_url']); ?>')"></div>
                        <?php else: ?>
                            <div class="group-card-image group-card-placeholder" style="background: <?= htmlspecialchars($g['type_color'] ?? '#6B7280'); ?>">
                                <span><?= strtoupper(substr($g['name'], 0, 2)); ?></span>
                            </div>
                        <?php endif; ?>
                        <div class="group-card-content">
                            <span class="group-type" style="color: <?= htmlspecialchars($g['type_color'] ?? '#6B7280'); ?>"><?= htmlspecialchars($g['type_name']); ?></span>
                            <h3><a href="/groups/<?= htmlspecialchars($g['slug']); ?>"><?= htmlspecialchars($g['name']); ?></a></h3>

                            <?php if ($g['description']): ?>
                                <p class="group-excerpt"><?= htmlspecialchars(substr($g['description'], 0, 120)); ?><?= strlen($g['description']) > 120 ? '...' : ''; ?></p>
                            <?php endif; ?>

                            <div class="group-meta">
                                <?php if ($g['meeting_day']): ?>
                                    <span class="meta-item">
                                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
                                        <?= ucfirst($g['meeting_day']); ?>s<?= $g['meeting_time'] ? ' at ' . date('g:i A', strtotime($g['meeting_time'])) : ''; ?>
                                    </span>
                                <?php endif; ?>
                                <?php if ($g['location_city']): ?>
                                    <span class="meta-item">
                                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/><circle cx="12" cy="10" r="3"/></svg>
                                        <?= htmlspecialchars($g['location_city']); ?>
                                    </span>
                                <?php elseif ($g['location_type'] === 'online'): ?>
                                    <span class="meta-item">
                                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="2" y="3" width="20" height="14" rx="2" ry="2"/><line x1="8" y1="21" x2="16" y2="21"/><line x1="12" y1="17" x2="12" y2="21"/></svg>
                                        Online
                                    </span>
                                <?php endif; ?>
                            </div>

                            <div class="group-features">
                                <?php if ($g['childcare_available']): ?>
                                    <span class="feature-badge">Childcare</span>
                                <?php endif; ?>
                                <?php if ($g['max_members'] && $g['member_count'] >= $g['max_members']): ?>
                                    <span class="feature-badge badge-warning">Full</span>
                                <?php endif; ?>
                            </div>

                            <a href="/groups/<?= htmlspecialchars($g['slug']); ?>" class="btn btn-outline btn-sm">Learn More</a>
                        </div>
                    </article>
                <?php endforeach; ?>
            </div>

            <?php if ($result['total_pages'] > 1): ?>
                <div class="pagination">
                    <?php for ($i = 1; $i <= $result['total_pages']; $i++): ?>
                        <?php
                        $url = '?' . http_build_query(array_merge(array_filter([
                            'q' => $filters['search'],
                            'type' => $filters['type_id'],
                            'day' => $filters['day'],
                        ]), ['page' => $i]));
                        ?>
                        <a href="<?= $url; ?>" class="pagination-item <?= $i === $result['page'] ? 'active' : ''; ?>"><?= $i; ?></a>
                    <?php endfor; ?>
                </div>
            <?php endif; ?>
        <?php endif; ?>

        <!-- CTA -->
        <div class="groups-cta">
            <h3>Can't find what you're looking for?</h3>
            <p>We'd love to help you find the right group or answer any questions you have.</p>
            <a href="/connect" class="btn btn-primary">Contact Us</a>
        </div>
    </div>
</section>

<style>
.groups-finder { padding: 3rem 0; }
.groups-filters { margin-bottom: 2rem; }
.filter-bar { display: flex; gap: 0.75rem; flex-wrap: wrap; }
.filter-bar .search-input { flex: 1; min-width: 200px; }
.groups-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(320px, 1fr)); gap: 2rem; }
.group-card { background: var(--color-surface, #fff); border-radius: 12px; overflow: hidden; box-shadow: 0 2px 8px rgba(0,0,0,0.08); transition: transform 0.2s, box-shadow 0.2s; }
.group-card:hover { transform: translateY(-4px); box-shadow: 0 8px 24px rgba(0,0,0,0.12); }
.group-card-image { height: 160px; background-size: cover; background-position: center; }
.group-card-placeholder { display: flex; align-items: center; justify-content: center; color: white; font-size: 2rem; font-weight: 700; }
.group-card-content { padding: 1.5rem; }
.group-type { font-size: 0.75rem; font-weight: 600; text-transform: uppercase; letter-spacing: 0.05em; }
.group-card h3 { margin: 0.5rem 0; font-size: 1.25rem; }
.group-card h3 a { color: inherit; text-decoration: none; }
.group-card h3 a:hover { color: var(--color-primary); }
.group-excerpt { color: var(--color-text-muted, #6B7280); font-size: 0.9375rem; line-height: 1.5; margin: 0.75rem 0; }
.group-meta { display: flex; flex-wrap: wrap; gap: 1rem; margin: 1rem 0; font-size: 0.875rem; color: var(--color-text-muted, #6B7280); }
.meta-item { display: flex; align-items: center; gap: 0.375rem; }
.meta-item svg { opacity: 0.7; }
.group-features { display: flex; gap: 0.5rem; margin-bottom: 1rem; }
.feature-badge { font-size: 0.625rem; padding: 0.25rem 0.5rem; background: var(--color-surface-hover, #f3f4f6); border-radius: 4px; text-transform: uppercase; font-weight: 600; }
.badge-warning { background: #fef3c7; color: #b45309; }
.no-results { text-align: center; padding: 4rem 0; color: var(--color-text-muted); }
.pagination { display: flex; justify-content: center; gap: 0.5rem; margin-top: 2rem; }
.pagination-item { padding: 0.5rem 1rem; background: var(--color-surface, #fff); border: 1px solid var(--color-border, #e5e7eb); border-radius: 6px; text-decoration: none; color: inherit; }
.pagination-item.active { background: var(--color-primary); color: white; border-color: var(--color-primary); }
.groups-cta { text-align: center; margin-top: 4rem; padding: 3rem; background: var(--color-surface-hover, #f9fafb); border-radius: 12px; }
.groups-cta h3 { margin-bottom: 0.5rem; }
.groups-cta p { color: var(--color-text-muted); margin-bottom: 1.5rem; }
</style>

<?php include __DIR__ . '/../includes/footer.php'; ?>
