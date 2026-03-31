<?php
/**
 * Groups Management - View Group
 */

$page_title = 'View Group';
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../../includes/db-config.php';
require_once __DIR__ . '/../../includes/services/GroupsService.php';

$pdo = getDbConnection();
$groupsService = new GroupsService($pdo);

$groupId = (int)($_GET['id'] ?? 0);
if (!$groupId) {
    header('Location: /admin/groups');
    exit;
}

$group = $groupsService->getGroup($groupId);
if (!$group) {
    echo '<div class="admin-alert admin-alert-error">Group not found.</div>';
    require_once __DIR__ . '/../includes/footer.php';
    exit;
}

$page_title = $group['name'];
$pendingRequests = $groupsService->getPendingRequests($groupId);
$success = '';
$error = '';

// Handle actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && verify_csrf_token($_POST['csrf_token'] ?? '')) {
    $action = $_POST['action'] ?? '';

    switch ($action) {
        case 'add_member':
            $result = $groupsService->addMember($groupId, (int)$_POST['user_id'], $_POST['role'] ?? 'member');
            $success = $result['success'] ? 'Member added' : $result['error'];
            $group = $groupsService->getGroup($groupId);
            break;

        case 'remove_member':
            $groupsService->removeMember($groupId, (int)$_POST['user_id']);
            $success = 'Member removed';
            $group = $groupsService->getGroup($groupId);
            break;

        case 'update_role':
            $groupsService->updateMemberRole($groupId, (int)$_POST['user_id'], $_POST['role']);
            $success = 'Role updated';
            $group = $groupsService->getGroup($groupId);
            break;

        case 'approve_request':
        case 'deny_request':
            $groupsService->handleSignupRequest((int)$_POST['request_id'], $action === 'approve_request' ? 'approve' : 'deny', $_SESSION['admin_user_id']);
            $success = $action === 'approve_request' ? 'Request approved' : 'Request denied';
            $pendingRequests = $groupsService->getPendingRequests($groupId);
            $group = $groupsService->getGroup($groupId);
            break;
    }
}

$days = ['sunday'=>'Sunday','monday'=>'Monday','tuesday'=>'Tuesday','wednesday'=>'Wednesday','thursday'=>'Thursday','friday'=>'Friday','saturday'=>'Saturday'];
?>

<?php if ($success): ?><div class="admin-alert admin-alert-success"><?= htmlspecialchars($success); ?></div><?php endif; ?>
<?php if ($error): ?><div class="admin-alert admin-alert-error"><?= htmlspecialchars($error); ?></div><?php endif; ?>

<!-- Header -->
<div class="group-header">
    <div class="group-header-left">
        <a href="/admin/groups" class="btn btn-outline">&larr; All Groups</a>
        <div class="group-title">
            <span class="group-type-label" style="color: <?= htmlspecialchars($group['type_color'] ?? '#6B7280'); ?>"><?= htmlspecialchars($group['type_name']); ?></span>
            <h1><?= htmlspecialchars($group['name']); ?></h1>
            <?php if ($group['status'] !== 'active'): ?>
                <span class="badge badge-warning"><?= ucfirst($group['status']); ?></span>
            <?php endif; ?>
        </div>
    </div>
    <div class="group-header-right">
        <a href="/admin/groups/edit.php?id=<?= $groupId; ?>" class="btn btn-primary">Edit Group</a>
        <a href="/groups/<?= htmlspecialchars($group['slug']); ?>" class="btn btn-outline" target="_blank">View Public Page</a>
    </div>
</div>

<div class="group-layout">
    <!-- Main Content -->
    <div class="group-main">
        <!-- Description -->
        <?php if ($group['description']): ?>
            <div class="admin-card">
                <div class="admin-card-header"><h3>About</h3></div>
                <div class="group-description"><?= nl2br(htmlspecialchars($group['description'])); ?></div>
            </div>
        <?php endif; ?>

        <!-- Members -->
        <div class="admin-card">
            <div class="admin-card-header">
                <h3>Members (<?= count($group['members']); ?>)</h3>
                <button type="button" class="btn btn-sm btn-primary" data-open-modal="add-member-modal">Add Member</button>
            </div>

            <?php if (empty($group['members'])): ?>
                <p class="text-muted" style="padding: 1rem;">No members yet.</p>
            <?php else: ?>
                <table class="members-table">
                    <thead>
                        <tr><th>Name</th><th>Role</th><th>Joined</th><th></th></tr>
                    </thead>
                    <tbody>
                        <?php foreach ($group['members'] as $m): ?>
                            <tr>
                                <td>
                                    <a href="/admin/people?page=view&id=<?= $m['user_id']; ?>">
                                        <?= htmlspecialchars(trim($m['first_name'] . ' ' . $m['last_name']) ?: $m['email']); ?>
                                    </a>
                                </td>
                                <td>
                                    <form method="post" class="inline-form">
                                        <?= csrf_field(); ?>
                                        <input type="hidden" name="action" value="update_role">
                                        <input type="hidden" name="user_id" value="<?= $m['user_id']; ?>">
                                        <select name="role" onchange="this.form.submit()" class="role-select">
                                            <option value="member" <?= $m['role'] === 'member' ? 'selected' : ''; ?>>Member</option>
                                            <option value="co-leader" <?= $m['role'] === 'co-leader' ? 'selected' : ''; ?>>Co-Leader</option>
                                            <option value="leader" <?= $m['role'] === 'leader' ? 'selected' : ''; ?>>Leader</option>
                                        </select>
                                    </form>
                                </td>
                                <td class="text-muted"><?= date('M j, Y', strtotime($m['joined_at'])); ?></td>
                                <td>
                                    <form method="post" class="inline-form">
                                        <?= csrf_field(); ?>
                                        <input type="hidden" name="action" value="remove_member">
                                        <input type="hidden" name="user_id" value="<?= $m['user_id']; ?>">
                                        <button type="submit" class="btn btn-xs btn-ghost btn-danger" data-confirm="Remove this member?">Remove</button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>

        <!-- Pending Requests -->
        <?php if ($pendingRequests): ?>
            <div class="admin-card card-warning">
                <div class="admin-card-header"><h3>Pending Requests (<?= count($pendingRequests); ?>)</h3></div>
                <div class="requests-list">
                    <?php foreach ($pendingRequests as $req): ?>
                        <div class="request-item">
                            <div class="request-info">
                                <strong><?= htmlspecialchars(trim($req['first_name'] . ' ' . $req['last_name'])); ?></strong>
                                <span class="text-muted"><?= htmlspecialchars($req['email']); ?></span>
                                <?php if ($req['message']): ?>
                                    <p class="request-message"><?= htmlspecialchars($req['message']); ?></p>
                                <?php endif; ?>
                                <span class="request-date"><?= date('M j, Y', strtotime($req['created_at'])); ?></span>
                            </div>
                            <div class="request-actions">
                                <form method="post" class="inline-form">
                                    <?= csrf_field(); ?>
                                    <input type="hidden" name="request_id" value="<?= $req['id']; ?>">
                                    <button type="submit" name="action" value="approve_request" class="btn btn-sm btn-success">Approve</button>
                                    <button type="submit" name="action" value="deny_request" class="btn btn-sm btn-danger">Deny</button>
                                </form>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <!-- Sidebar -->
    <div class="group-sidebar">
        <div class="admin-card">
            <div class="admin-card-header"><h3>Details</h3></div>
            <div class="detail-list">
                <?php if ($group['meeting_day']): ?>
                    <div class="detail-item">
                        <span class="detail-label">Meets</span>
                        <span class="detail-value"><?= $days[$group['meeting_day']] ?? ucfirst($group['meeting_day']); ?>s<?= $group['meeting_time'] ? ' at ' . date('g:i A', strtotime($group['meeting_time'])) : ''; ?></span>
                    </div>
                <?php endif; ?>

                <?php if ($group['meeting_frequency'] && $group['meeting_frequency'] !== 'weekly'): ?>
                    <div class="detail-item">
                        <span class="detail-label">Frequency</span>
                        <span class="detail-value"><?= ucfirst(str_replace('-', ' ', $group['meeting_frequency'])); ?></span>
                    </div>
                <?php endif; ?>

                <?php if ($group['location_type'] === 'online'): ?>
                    <div class="detail-item">
                        <span class="detail-label">Location</span>
                        <span class="detail-value">Online<?= $group['online_url'] ? ' - <a href="' . htmlspecialchars($group['online_url']) . '" target="_blank">Join Link</a>' : ''; ?></span>
                    </div>
                <?php elseif ($group['location_name'] || $group['location_city']): ?>
                    <div class="detail-item">
                        <span class="detail-label">Location</span>
                        <span class="detail-value">
                            <?= htmlspecialchars($group['location_name'] ?? ''); ?>
                            <?php if ($group['location_city']): ?><br><?= htmlspecialchars($group['location_city']); ?><?php endif; ?>
                        </span>
                    </div>
                <?php endif; ?>

                <?php if ($group['max_members']): ?>
                    <div class="detail-item">
                        <span class="detail-label">Capacity</span>
                        <span class="detail-value"><?= $group['member_count']; ?> / <?= $group['max_members']; ?></span>
                    </div>
                <?php endif; ?>

                <?php if ($group['childcare_available']): ?>
                    <div class="detail-item">
                        <span class="detail-label">Childcare</span>
                        <span class="detail-value">Available</span>
                    </div>
                <?php endif; ?>

                <div class="detail-item">
                    <span class="detail-label">Visibility</span>
                    <span class="detail-value"><?= ucfirst($group['visibility']); ?></span>
                </div>

                <div class="detail-item">
                    <span class="detail-label">Signups</span>
                    <span class="detail-value"><?= $group['allow_signups'] ? ($group['requires_approval'] ? 'Requires approval' : 'Open') : 'Closed'; ?></span>
                </div>
            </div>
        </div>

        <?php if ($group['leaders']): ?>
            <div class="admin-card">
                <div class="admin-card-header"><h3>Leaders</h3></div>
                <div class="leaders-list">
                    <?php foreach ($group['leaders'] as $l): ?>
                        <div class="leader-item">
                            <span class="leader-name"><?= htmlspecialchars(trim($l['first_name'] . ' ' . $l['last_name'])); ?></span>
                            <span class="leader-role"><?= ucfirst(str_replace('-', ' ', $l['role'])); ?></span>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endif; ?>

        <?php if ($group['contact_email'] || $group['contact_phone']): ?>
            <div class="admin-card">
                <div class="admin-card-header"><h3>Contact</h3></div>
                <div class="detail-list">
                    <?php if ($group['contact_email']): ?>
                        <div class="detail-item">
                            <span class="detail-label">Email</span>
                            <span class="detail-value"><a href="mailto:<?= htmlspecialchars($group['contact_email']); ?>"><?= htmlspecialchars($group['contact_email']); ?></a></span>
                        </div>
                    <?php endif; ?>
                    <?php if ($group['contact_phone']): ?>
                        <div class="detail-item">
                            <span class="detail-label">Phone</span>
                            <span class="detail-value"><?= htmlspecialchars($group['contact_phone']); ?></span>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Add Member Modal -->
<div class="modal-overlay" id="add-member-modal">
    <div class="modal">
        <div class="modal-header">
            <h3>Add Member</h3>
            <button type="button" class="modal-close" data-close-modal>&times;</button>
        </div>
        <form method="post">
            <?= csrf_field(); ?>
            <input type="hidden" name="action" value="add_member">
            <div class="modal-body">
                <div class="form-group">
                    <label>Search Person</label>
                    <input type="text" id="member-search" placeholder="Type to search..." autocomplete="off">
                    <div id="member-search-results" class="search-results"></div>
                    <input type="hidden" name="user_id" id="selected-user-id" required>
                    <div id="selected-member" class="selected-member"></div>
                </div>
                <div class="form-group">
                    <label>Role</label>
                    <select name="role">
                        <option value="member">Member</option>
                        <option value="co-leader">Co-Leader</option>
                        <option value="leader">Leader</option>
                    </select>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline" data-close-modal>Cancel</button>
                <button type="submit" class="btn btn-primary">Add Member</button>
            </div>
        </form>
    </div>
</div>

<style>
.group-header { display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 1.5rem; gap: 1rem; flex-wrap: wrap; }
.group-header-left { display: flex; align-items: flex-start; gap: 1rem; }
.group-title h1 { margin: 0; font-size: 1.5rem; }
.group-type-label { font-size: 0.75rem; font-weight: 600; text-transform: uppercase; }
.group-header-right { display: flex; gap: 0.5rem; }
.group-layout { display: grid; grid-template-columns: 1fr 350px; gap: 1.5rem; }
.group-description { padding: 1rem; line-height: 1.6; }
.members-table { width: 100%; }
.members-table th, .members-table td { padding: 0.75rem 1rem; text-align: left; border-bottom: 1px solid var(--color-border); }
.members-table th { font-size: 0.75rem; text-transform: uppercase; color: var(--color-text-muted); }
.role-select { padding: 0.25rem 0.5rem; font-size: 0.875rem; }
.inline-form { display: inline; }
.detail-list { display: flex; flex-direction: column; gap: 0.75rem; }
.detail-item { display: flex; flex-direction: column; gap: 0.125rem; }
.detail-label { font-size: 0.75rem; color: var(--color-text-muted); text-transform: uppercase; }
.detail-value { font-size: 0.9375rem; }
.leaders-list { display: flex; flex-direction: column; gap: 0.5rem; }
.leader-item { display: flex; justify-content: space-between; align-items: center; }
.leader-role { font-size: 0.75rem; color: var(--color-text-muted); }
.card-warning { border-color: var(--color-warning); }
.requests-list { display: flex; flex-direction: column; gap: 1rem; padding: 1rem; }
.request-item { display: flex; justify-content: space-between; align-items: center; gap: 1rem; padding: 0.75rem; background: var(--color-surface-hover); border-radius: var(--radius); }
.request-info { flex: 1; }
.request-message { margin: 0.5rem 0; font-style: italic; }
.request-date { font-size: 0.75rem; color: var(--color-text-muted); }
.request-actions { display: flex; gap: 0.5rem; }
.modal-overlay { display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.5); z-index: 1000; align-items: center; justify-content: center; }
.modal-overlay.active { display: flex; }
.modal { background: var(--color-surface); border-radius: var(--radius-lg); box-shadow: var(--shadow-xl); width: 100%; max-width: 450px; margin: 1rem; }
.modal-header { display: flex; justify-content: space-between; align-items: center; padding: 1rem; border-bottom: 1px solid var(--color-border); }
.modal-header h3 { margin: 0; }
.modal-close { background: none; border: none; font-size: 1.5rem; cursor: pointer; }
.modal-body { padding: 1rem; }
.modal-footer { display: flex; justify-content: flex-end; gap: 0.5rem; padding: 1rem; border-top: 1px solid var(--color-border); }
.search-results { position: absolute; z-index: 10; background: var(--color-surface); border: 1px solid var(--color-border); border-radius: var(--radius); max-height: 200px; overflow-y: auto; width: 100%; display: none; }
.search-results.active { display: block; }
.search-result-item { padding: 0.5rem 0.75rem; cursor: pointer; }
.search-result-item:hover { background: var(--color-surface-hover); }
.selected-member { margin-top: 0.5rem; padding: 0.5rem; background: var(--color-primary); color: white; border-radius: var(--radius); display: none; }
.selected-member.active { display: flex; justify-content: space-between; align-items: center; }
.form-group { position: relative; margin-bottom: 1rem; }
@media (max-width: 1024px) { .group-layout { grid-template-columns: 1fr; } }
</style>

<script <?= csp_nonce(); ?>>
document.querySelectorAll('[data-open-modal]').forEach(b => b.addEventListener('click', () => document.getElementById(b.dataset.openModal)?.classList.add('active')));
document.querySelectorAll('[data-close-modal]').forEach(b => b.addEventListener('click', () => b.closest('.modal-overlay')?.classList.remove('active')));
document.querySelectorAll('.modal-overlay').forEach(o => o.addEventListener('click', e => { if (e.target === o) o.classList.remove('active'); }));

// Member search
const searchInput = document.getElementById('member-search');
const resultsDiv = document.getElementById('member-search-results');
const selectedDiv = document.getElementById('selected-member');
const userIdInput = document.getElementById('selected-user-id');
let searchTimeout;

searchInput?.addEventListener('input', function() {
    clearTimeout(searchTimeout);
    const q = this.value.trim();
    if (q.length < 2) { resultsDiv.classList.remove('active'); return; }

    searchTimeout = setTimeout(async () => {
        const res = await fetch('/admin/api/people.php?action=search&q=' + encodeURIComponent(q));
        const data = await res.json();
        if (data.success && data.data.length) {
            resultsDiv.innerHTML = data.data.map(p => `
                <div class="search-result-item" data-id="${p.id}" data-name="${p.first_name} ${p.last_name}">
                    ${p.first_name} ${p.last_name} <span class="text-muted">${p.email}</span>
                </div>
            `).join('');
            resultsDiv.classList.add('active');
        } else {
            resultsDiv.innerHTML = '<div style="padding:0.5rem" class="text-muted">No results</div>';
            resultsDiv.classList.add('active');
        }
    }, 300);
});

resultsDiv?.addEventListener('click', function(e) {
    const item = e.target.closest('.search-result-item');
    if (item) {
        userIdInput.value = item.dataset.id;
        selectedDiv.innerHTML = item.dataset.name + ' <button type="button" onclick="clearSelection()">&times;</button>';
        selectedDiv.classList.add('active');
        searchInput.value = '';
        resultsDiv.classList.remove('active');
    }
});

function clearSelection() {
    userIdInput.value = '';
    selectedDiv.classList.remove('active');
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
