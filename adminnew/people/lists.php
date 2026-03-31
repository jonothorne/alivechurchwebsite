<?php
/**
 * People Lists/Segments Management
 */

$page_title = 'People Lists';
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../../includes/db-config.php';
require_once __DIR__ . '/../../includes/repositories/PeopleListRepository.php';
require_once __DIR__ . '/../../includes/services/PeopleService.php';
require_once __DIR__ . '/../../includes/Pagination.php';

$pdo = getDbConnection();
$listRepo = new PeopleListRepository($pdo);
$peopleService = new PeopleService($pdo);

$action = $_GET['action'] ?? 'index';
$listId = (int)($_GET['id'] ?? 0);
$success = '';
$error = '';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && verify_csrf_token($_POST['csrf_token'] ?? '')) {
    $postAction = $_POST['action'] ?? '';

    switch ($postAction) {
        case 'create':
            $slug = preg_replace('/[^a-z0-9-]/', '', strtolower(str_replace(' ', '-', $_POST['name'])));
            if ($listRepo->findBySlug($slug)) {
                $error = 'A list with this name already exists';
            } else {
                $data = [
                    'name' => trim($_POST['name']),
                    'slug' => $slug,
                    'description' => trim($_POST['description']) ?: null,
                    'list_type' => $_POST['list_type'],
                    'color' => $_POST['color'] ?? '#6B7280',
                    'visibility' => $_POST['visibility'] ?? 'shared',
                    'created_by' => $_SESSION['admin_user_id'],
                ];

                if ($_POST['list_type'] === 'dynamic') {
                    $data['criteria'] = buildCriteriaFromPost($_POST);
                }

                $newId = $listRepo->createList($data);
                header('Location: /adminnew/people/lists?action=view&id=' . $newId . '&created=1');
                exit;
            }
            break;

        case 'update':
            $listRepo->update($listId, [
                'name' => trim($_POST['name']),
                'description' => trim($_POST['description']) ?: null,
                'color' => $_POST['color'] ?? '#6B7280',
                'visibility' => $_POST['visibility'] ?? 'shared',
            ]);
            $success = 'List updated successfully';
            break;

        case 'add_members':
            $userIds = array_map('intval', explode(',', $_POST['user_ids']));
            $added = $listRepo->addMembers($listId, $userIds, $_SESSION['admin_user_id']);
            $success = "Added $added people to list";
            break;

        case 'remove_member':
            $listRepo->removeMember($listId, (int)$_POST['user_id']);
            $success = 'Removed from list';
            break;

        case 'delete':
            if ($listRepo->deleteList($listId)) {
                header('Location: /adminnew/people/lists?deleted=1');
                exit;
            } else {
                $error = 'Cannot delete system lists';
            }
            break;
    }
}

function buildCriteriaFromPost($post): array {
    $criteria = [];
    if (isset($post['criteria_is_member'])) $criteria['is_member'] = $post['criteria_is_member'] === '1';
    if (!empty($post['criteria_status_id'])) $criteria['membership_status_id'] = (int)$post['criteria_status_id'];
    if (!empty($post['criteria_created_within'])) $criteria['created_within'] = $post['criteria_created_within'];
    if (!empty($post['criteria_last_login_within'])) $criteria['last_login_within'] = $post['criteria_last_login_within'];
    if (!empty($post['criteria_tag_ids'])) $criteria['tag_ids'] = array_map('intval', $post['criteria_tag_ids']);
    if (!empty($post['criteria_gender'])) $criteria['gender'] = $post['criteria_gender'];
    if (!empty($post['criteria_age_min'])) $criteria['age_min'] = (int)$post['criteria_age_min'];
    if (!empty($post['criteria_age_max'])) $criteria['age_max'] = (int)$post['criteria_age_max'];
    return $criteria;
}

// Get data based on action
$list = null;
$members = null;
$lists = [];

if ($action === 'view' && $listId) {
    $list = $listRepo->find($listId);
    if (!$list) {
        $error = 'List not found';
        $action = 'index';
    } else {
        $page = max(1, (int)($_GET['p'] ?? 1));
        if ($list['list_type'] === 'dynamic') {
            $criteria = json_decode($list['criteria'], true) ?? [];
            $members = $listRepo->getDynamicListMembers($criteria, $page, 25);
        } else {
            $members = $listRepo->getStaticListMembers($listId, $page, 25);
        }
    }
}

if ($action === 'index' || $action === 'create') {
    $lists = $listRepo->getAllWithCounts();
    $statuses = $peopleService->getMembershipStatuses();
    $tags = $peopleService->getTagsWithCounts();
}

if (isset($_GET['created'])) $success = 'List created successfully';
if (isset($_GET['deleted'])) $success = 'List deleted successfully';
?>

<?php if ($success): ?>
    <div class="admin-alert admin-alert-success"><?= htmlspecialchars($success); ?></div>
<?php endif; ?>
<?php if ($error): ?>
    <div class="admin-alert admin-alert-error"><?= htmlspecialchars($error); ?></div>
<?php endif; ?>

<?php if ($action === 'index'): ?>
<!-- Lists Index -->
<div class="admin-actions-bar">
    <a href="/adminnew/people" class="btn btn-outline">&larr; Back to People</a>
    <button type="button" class="btn btn-primary" data-open-modal="create-list-modal">Create List</button>
</div>

<div class="lists-grid">
    <?php
    $systemLists = array_filter($lists, fn($l) => $l['is_system']);
    $customLists = array_filter($lists, fn($l) => !$l['is_system']);
    ?>

    <?php if ($systemLists): ?>
        <div class="lists-section">
            <h3>Smart Lists</h3>
            <div class="lists-row">
                <?php foreach ($systemLists as $l): ?>
                    <a href="/adminnew/people/lists?action=view&id=<?= $l['id']; ?>" class="list-card" style="--list-color: <?= htmlspecialchars($l['color']); ?>">
                        <div class="list-color-bar"></div>
                        <div class="list-info">
                            <span class="list-name"><?= htmlspecialchars($l['name']); ?></span>
                            <span class="list-count"><?= number_format($l['member_count']); ?> people</span>
                        </div>
                        <span class="list-type-badge">Dynamic</span>
                    </a>
                <?php endforeach; ?>
            </div>
        </div>
    <?php endif; ?>

    <div class="lists-section">
        <h3>Custom Lists</h3>
        <?php if ($customLists): ?>
            <div class="lists-row">
                <?php foreach ($customLists as $l): ?>
                    <a href="/adminnew/people/lists?action=view&id=<?= $l['id']; ?>" class="list-card" style="--list-color: <?= htmlspecialchars($l['color']); ?>">
                        <div class="list-color-bar"></div>
                        <div class="list-info">
                            <span class="list-name"><?= htmlspecialchars($l['name']); ?></span>
                            <span class="list-count"><?= number_format($l['member_count']); ?> people</span>
                        </div>
                        <span class="list-type-badge"><?= ucfirst($l['list_type']); ?></span>
                    </a>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <p class="text-muted">No custom lists yet. Create one to get started.</p>
        <?php endif; ?>
    </div>
</div>

<!-- Create List Modal -->
<div class="modal-overlay" id="create-list-modal">
    <div class="modal" style="max-width: 600px;">
        <div class="modal-header">
            <h3>Create List</h3>
            <button type="button" class="modal-close" data-close-modal>&times;</button>
        </div>
        <form method="post">
            <?= csrf_field(); ?>
            <input type="hidden" name="action" value="create">
            <div class="modal-body">
                <div class="form-row-2">
                    <div class="form-group">
                        <label>List Name *</label>
                        <input type="text" name="name" required>
                    </div>
                    <div class="form-group">
                        <label>Type</label>
                        <select name="list_type" id="list-type-select">
                            <option value="static">Static (Manual)</option>
                            <option value="dynamic">Dynamic (Auto-updating)</option>
                        </select>
                    </div>
                </div>

                <div class="form-group">
                    <label>Description</label>
                    <textarea name="description" rows="2"></textarea>
                </div>

                <div class="form-row-2">
                    <div class="form-group">
                        <label>Color</label>
                        <input type="color" name="color" value="#6B7280">
                    </div>
                    <div class="form-group">
                        <label>Visibility</label>
                        <select name="visibility">
                            <option value="shared">Shared (All admins)</option>
                            <option value="private">Private (Only me)</option>
                        </select>
                    </div>
                </div>

                <!-- Dynamic criteria (shown when dynamic selected) -->
                <div id="dynamic-criteria" style="display: none;">
                    <h4 style="margin: 1rem 0 0.5rem; font-size: 0.875rem;">Filter Criteria</h4>

                    <div class="form-row-2">
                        <div class="form-group">
                            <label>Membership</label>
                            <select name="criteria_is_member">
                                <option value="">Any</option>
                                <option value="1">Members only</option>
                                <option value="0">Non-members only</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Status</label>
                            <select name="criteria_status_id">
                                <option value="">Any</option>
                                <?php foreach ($statuses as $s): ?>
                                    <option value="<?= $s['id']; ?>"><?= htmlspecialchars($s['name']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <div class="form-row-2">
                        <div class="form-group">
                            <label>Created Within</label>
                            <select name="criteria_created_within">
                                <option value="">Any time</option>
                                <option value="week">This week</option>
                                <option value="month">This month</option>
                                <option value="90_days">Last 90 days</option>
                                <option value="year">This year</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Last Login</label>
                            <select name="criteria_last_login_within">
                                <option value="">Any</option>
                                <option value="7_days">Last 7 days</option>
                                <option value="30_days">Last 30 days</option>
                                <option value="90_days">Last 90 days</option>
                            </select>
                        </div>
                    </div>

                    <div class="form-row-2">
                        <div class="form-group">
                            <label>Gender</label>
                            <select name="criteria_gender">
                                <option value="">Any</option>
                                <option value="male">Male</option>
                                <option value="female">Female</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Age Range</label>
                            <div style="display: flex; gap: 0.5rem; align-items: center;">
                                <input type="number" name="criteria_age_min" placeholder="Min" style="width: 80px;">
                                <span>to</span>
                                <input type="number" name="criteria_age_max" placeholder="Max" style="width: 80px;">
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline" data-close-modal>Cancel</button>
                <button type="submit" class="btn btn-primary">Create List</button>
            </div>
        </form>
    </div>
</div>

<?php elseif ($action === 'view' && $list): ?>
<!-- View List -->
<div class="admin-actions-bar">
    <div class="actions-left">
        <a href="/adminnew/people/lists" class="btn btn-outline">&larr; All Lists</a>
        <h2 style="margin: 0; display: flex; align-items: center; gap: 0.5rem;">
            <span class="list-color-dot" style="background: <?= htmlspecialchars($list['color']); ?>"></span>
            <?= htmlspecialchars($list['name']); ?>
            <span class="badge"><?= ucfirst($list['list_type']); ?></span>
        </h2>
    </div>
    <div class="actions-right">
        <?php if ($list['list_type'] === 'static'): ?>
            <button type="button" class="btn btn-primary" data-open-modal="add-members-modal">Add People</button>
        <?php endif; ?>
        <?php if (!$list['is_system']): ?>
            <form method="post" style="display: inline;">
                <?= csrf_field(); ?>
                <input type="hidden" name="action" value="delete">
                <button type="submit" class="btn btn-danger" data-confirm-delete>Delete List</button>
            </form>
        <?php endif; ?>
    </div>
</div>

<?php if ($list['description']): ?>
    <p class="text-muted" style="margin-bottom: 1rem;"><?= htmlspecialchars($list['description']); ?></p>
<?php endif; ?>

<div class="admin-card">
    <div class="admin-card-header">
        <span><?= number_format($members['total']); ?> people</span>
    </div>

    <?php if (empty($members['items'])): ?>
        <div class="empty-state">
            <p>No people in this list<?= $list['list_type'] === 'dynamic' ? ' match the criteria' : ' yet'; ?>.</p>
        </div>
    <?php else: ?>
        <table class="people-table">
            <thead>
                <tr>
                    <th>Name</th>
                    <th>Email</th>
                    <th>Status</th>
                    <?php if ($list['list_type'] === 'static'): ?><th></th><?php endif; ?>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($members['items'] as $p): ?>
                    <tr>
                        <td>
                            <a href="/adminnew/people/view?id=<?= $p['id']; ?>">
                                <?= htmlspecialchars(trim(($p['first_name'] ?? '') . ' ' . ($p['last_name'] ?? '')) ?: $p['email']); ?>
                            </a>
                            <?php if ($p['is_member']): ?><span class="badge badge-member">Member</span><?php endif; ?>
                        </td>
                        <td><?= htmlspecialchars($p['email']); ?></td>
                        <td>
                            <?php if ($p['status_name']): ?>
                                <span class="status-badge" style="--status-color: <?= $p['status_color'] ?? '#6B7280'; ?>"><?= htmlspecialchars($p['status_name']); ?></span>
                            <?php endif; ?>
                        </td>
                        <?php if ($list['list_type'] === 'static'): ?>
                            <td>
                                <form method="post" style="display: inline;">
                                    <?= csrf_field(); ?>
                                    <input type="hidden" name="action" value="remove_member">
                                    <input type="hidden" name="user_id" value="<?= $p['id']; ?>">
                                    <button type="submit" class="btn btn-xs btn-ghost btn-danger">Remove</button>
                                </form>
                            </td>
                        <?php endif; ?>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <?php if ($members['total_pages'] > 1): ?>
            <div class="pagination-wrapper">
                <?php
                $pagination = new Pagination($members['total'], $members['per_page'], $members['page']);
                echo $pagination->render("/adminnew/people/lists?action=view&id={$listId}&p={page}");
                ?>
            </div>
        <?php endif; ?>
    <?php endif; ?>
</div>

<?php if ($list['list_type'] === 'static'): ?>
<!-- Add Members Modal -->
<div class="modal-overlay" id="add-members-modal">
    <div class="modal">
        <div class="modal-header">
            <h3>Add People to List</h3>
            <button type="button" class="modal-close" data-close-modal>&times;</button>
        </div>
        <form method="post">
            <?= csrf_field(); ?>
            <input type="hidden" name="action" value="add_members">
            <div class="modal-body">
                <div class="form-group">
                    <label>Search People</label>
                    <input type="text" id="people-search" placeholder="Type to search..." autocomplete="off">
                    <div id="search-results" class="search-results"></div>
                </div>
                <div class="form-group">
                    <label>Selected People</label>
                    <div id="selected-people" class="selected-people"></div>
                    <input type="hidden" name="user_ids" id="selected-ids">
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline" data-close-modal>Cancel</button>
                <button type="submit" class="btn btn-primary">Add to List</button>
            </div>
        </form>
    </div>
</div>

<script <?= csp_nonce(); ?>>
(function() {
    const searchInput = document.getElementById('people-search');
    const resultsDiv = document.getElementById('search-results');
    const selectedDiv = document.getElementById('selected-people');
    const selectedIdsInput = document.getElementById('selected-ids');
    const selected = new Map();
    let searchTimeout;

    searchInput?.addEventListener('input', function() {
        clearTimeout(searchTimeout);
        const q = this.value.trim();
        if (q.length < 2) { resultsDiv.innerHTML = ''; return; }

        searchTimeout = setTimeout(async () => {
            const res = await fetch('/admin/api/people.php?action=search&q=' + encodeURIComponent(q));
            const data = await res.json();
            if (data.success) {
                resultsDiv.innerHTML = data.data.map(p => `
                    <div class="search-result-item" data-id="${p.id}" data-name="${p.first_name} ${p.last_name}">
                        ${p.first_name} ${p.last_name} <span class="text-muted">${p.email}</span>
                    </div>
                `).join('') || '<div class="text-muted" style="padding:0.5rem">No results</div>';
            }
        }, 300);
    });

    resultsDiv?.addEventListener('click', function(e) {
        const item = e.target.closest('.search-result-item');
        if (item && !selected.has(item.dataset.id)) {
            selected.set(item.dataset.id, item.dataset.name);
            updateSelected();
            searchInput.value = '';
            resultsDiv.innerHTML = '';
        }
    });

    selectedDiv?.addEventListener('click', function(e) {
        if (e.target.classList.contains('remove-selected')) {
            selected.delete(e.target.dataset.id);
            updateSelected();
        }
    });

    function updateSelected() {
        selectedDiv.innerHTML = Array.from(selected).map(([id, name]) =>
            `<span class="selected-tag">${name} <button type="button" class="remove-selected" data-id="${id}">&times;</button></span>`
        ).join('');
        selectedIdsInput.value = Array.from(selected.keys()).join(',');
    }
})();
</script>
<?php endif; ?>
<?php endif; ?>

<style <?= csp_nonce(); ?>>
.lists-grid { display: flex; flex-direction: column; gap: 2rem; }
.lists-section h3 { margin: 0 0 1rem; font-size: 1rem; color: var(--color-text-muted); }
.lists-row { display: grid; grid-template-columns: repeat(auto-fill, minmax(250px, 1fr)); gap: 1rem; }
.list-card { display: flex; align-items: center; gap: 0.75rem; padding: 1rem; background: var(--color-surface); border: 1px solid var(--color-border); border-radius: var(--radius-lg); text-decoration: none; color: var(--color-text); transition: border-color 0.2s; }
.list-card:hover { border-color: var(--list-color); }
.list-color-bar { width: 4px; height: 40px; background: var(--list-color); border-radius: 2px; }
.list-info { flex: 1; display: flex; flex-direction: column; }
.list-name { font-weight: 600; }
.list-count { font-size: 0.875rem; color: var(--color-text-muted); }
.list-type-badge { font-size: 0.625rem; padding: 0.125rem 0.375rem; background: var(--color-surface-hover); border-radius: var(--radius); text-transform: uppercase; }
.list-color-dot { width: 12px; height: 12px; border-radius: 50%; }
.search-results { position: absolute; z-index: 10; background: var(--color-surface); border: 1px solid var(--color-border); border-radius: var(--radius); max-height: 200px; overflow-y: auto; width: 100%; }
.search-result-item { padding: 0.5rem 0.75rem; cursor: pointer; }
.search-result-item:hover { background: var(--color-surface-hover); }
.selected-people { display: flex; flex-wrap: wrap; gap: 0.5rem; min-height: 40px; padding: 0.5rem; border: 1px solid var(--color-border); border-radius: var(--radius); }
.selected-tag { display: flex; align-items: center; gap: 0.25rem; padding: 0.25rem 0.5rem; background: var(--color-primary); color: white; border-radius: var(--radius); font-size: 0.875rem; }
.remove-selected { background: none; border: none; color: inherit; cursor: pointer; opacity: 0.7; }
.remove-selected:hover { opacity: 1; }
.form-group { position: relative; }
.modal-overlay { display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.5); z-index: 1000; align-items: center; justify-content: center; }
.modal-overlay.active { display: flex; }
.modal { background: var(--color-surface); border-radius: var(--radius-lg); box-shadow: var(--shadow-xl); width: 100%; max-width: 500px; max-height: 90vh; overflow-y: auto; margin: 1rem; }
.modal-header { display: flex; justify-content: space-between; align-items: center; padding: 1rem 1.25rem; border-bottom: 1px solid var(--color-border); }
.modal-header h3 { margin: 0; }
.modal-close { background: none; border: none; font-size: 1.5rem; cursor: pointer; color: var(--color-text-muted); }
.modal-body { padding: 1.25rem; }
.modal-footer { display: flex; justify-content: flex-end; gap: 0.5rem; padding: 1rem 1.25rem; border-top: 1px solid var(--color-border); }
.form-row-2 { display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; }
.empty-state { padding: 2rem; text-align: center; color: var(--color-text-muted); }
</style>

<script <?= csp_nonce(); ?>>
document.querySelectorAll('[data-open-modal]').forEach(b => b.addEventListener('click', () => document.getElementById(b.dataset.openModal)?.classList.add('active')));
document.querySelectorAll('[data-close-modal]').forEach(b => b.addEventListener('click', () => b.closest('.modal-overlay')?.classList.remove('active')));
document.querySelectorAll('.modal-overlay').forEach(o => o.addEventListener('click', e => { if (e.target === o) o.classList.remove('active'); }));
document.getElementById('list-type-select')?.addEventListener('change', function() {
    document.getElementById('dynamic-criteria').style.display = this.value === 'dynamic' ? 'block' : 'none';
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
