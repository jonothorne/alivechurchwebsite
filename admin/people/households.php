<?php
/**
 * People Management - Households
 *
 * Manage family groupings/households.
 */

$page_title = 'Households';
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../../includes/db-config.php';
require_once __DIR__ . '/../../includes/services/PeopleService.php';

$pdo = getDbConnection();
$peopleService = new PeopleService($pdo);

$success = '';
$error = '';

// Handle actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid security token';
    } else {
        $action = $_POST['action'] ?? '';

        switch ($action) {
            case 'create':
                $result = $peopleService->createHousehold(
                    trim($_POST['name']),
                    $_POST['primary_contact_id'] ?: null
                );
                if ($result['success']) {
                    $success = 'Household created successfully';
                    log_activity($_SESSION['admin_user_id'], 'create', 'household', $result['household_id'], 'Created household: ' . $_POST['name']);
                } else {
                    $error = $result['error'];
                }
                break;

            case 'delete':
                $householdId = (int)$_POST['household_id'];
                // Remove all members from household first
                $pdo->prepare("UPDATE users SET household_id = NULL, household_role = NULL WHERE household_id = ?")->execute([$householdId]);
                $pdo->prepare("DELETE FROM addresses WHERE household_id = ?")->execute([$householdId]);
                $pdo->prepare("DELETE FROM households WHERE id = ?")->execute([$householdId]);
                $success = 'Household deleted';
                log_activity($_SESSION['admin_user_id'], 'delete', 'household', $householdId, 'Deleted household');
                break;
        }
    }
}

// Get household to view/edit if specified
$viewHouseholdId = (int)($_GET['id'] ?? 0);
$viewHousehold = $viewHouseholdId ? $peopleService->getHousehold($viewHouseholdId) : null;

// Get all households
$households = $peopleService->getHouseholds();

// Get people for primary contact dropdown
$peopleResult = $peopleService->getPeople([], 1, 200);
$allPeople = $peopleResult['items'];
?>

<?php if ($success): ?>
    <div class="admin-alert admin-alert-success"><?= htmlspecialchars($success); ?></div>
<?php endif; ?>
<?php if ($error): ?>
    <div class="admin-alert admin-alert-error"><?= htmlspecialchars($error); ?></div>
<?php endif; ?>

<div class="admin-actions-bar">
    <div class="actions-left">
        <a href="/admin/people" class="btn btn-outline">&larr; Back to People</a>
    </div>
    <div class="actions-right">
        <button type="button" class="btn btn-primary" data-open-modal="create-household">Create Household</button>
    </div>
</div>

<?php if ($viewHousehold): ?>
    <!-- Single Household View -->
    <div class="admin-card">
        <div class="admin-card-header">
            <h3><?= htmlspecialchars($viewHousehold['name']); ?></h3>
            <div class="admin-card-actions">
                <a href="/admin/people?page=households" class="btn btn-sm btn-outline">View All</a>
            </div>
        </div>

        <?php if (!empty($viewHousehold['members'])): ?>
            <div class="household-members-grid">
                <?php foreach ($viewHousehold['members'] as $member): ?>
                    <a href="/admin/people?page=view&id=<?= $member['id']; ?>" class="household-member-card">
                        <?php if (!empty($member['profile_photo'])): ?>
                            <img src="<?= htmlspecialchars($member['profile_photo']); ?>" alt="" class="member-photo">
                        <?php else: ?>
                            <div class="member-photo member-photo-initials">
                                <?= strtoupper(substr($member['first_name'], 0, 1)); ?>
                            </div>
                        <?php endif; ?>
                        <div class="member-info">
                            <span class="member-name"><?= htmlspecialchars($member['first_name'] . ' ' . $member['last_name']); ?></span>
                            <?php if ($member['household_role']): ?>
                                <span class="member-role"><?= htmlspecialchars(ucfirst($member['household_role'])); ?></span>
                            <?php endif; ?>
                        </div>
                    </a>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <p class="text-muted">No members in this household.</p>
        <?php endif; ?>

        <?php if (!empty($viewHousehold['addresses'])): ?>
            <div class="household-addresses">
                <h4>Addresses</h4>
                <?php foreach ($viewHousehold['addresses'] as $addr): ?>
                    <div class="address-item">
                        <span class="address-type"><?= htmlspecialchars(ucfirst($addr['address_type'])); ?></span>
                        <span class="address-text">
                            <?= htmlspecialchars($addr['street_line_1']); ?>,
                            <?= htmlspecialchars($addr['city']); ?>,
                            <?= htmlspecialchars($addr['postcode']); ?>
                        </span>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
<?php endif; ?>

<!-- Households List -->
<div class="admin-card">
    <div class="admin-card-header">
        <h3>All Households</h3>
        <span class="badge"><?= count($households); ?></span>
    </div>

    <?php if (empty($households)): ?>
        <div class="empty-state">
            <p>No households created yet.</p>
            <button type="button" class="btn btn-primary" data-open-modal="create-household">Create First Household</button>
        </div>
    <?php else: ?>
        <div class="table-container">
            <table>
                <thead>
                    <tr>
                        <th>Household Name</th>
                        <th>Primary Contact</th>
                        <th>Members</th>
                        <th>Created</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($households as $hh): ?>
                        <tr>
                            <td>
                                <a href="/admin/people?page=households&id=<?= $hh['id']; ?>" class="household-name-link">
                                    <strong><?= htmlspecialchars($hh['name']); ?></strong>
                                </a>
                            </td>
                            <td>
                                <?php if ($hh['primary_contact_first']): ?>
                                    <?= htmlspecialchars($hh['primary_contact_first'] . ' ' . $hh['primary_contact_last']); ?>
                                <?php else: ?>
                                    <span class="text-muted">-</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <span class="member-count-badge"><?= $hh['member_count']; ?></span>
                            </td>
                            <td><?= date('M j, Y', strtotime($hh['created_at'])); ?></td>
                            <td class="table-actions">
                                <a href="/admin/people?page=households&id=<?= $hh['id']; ?>" class="btn btn-xs btn-outline">View</a>
                                <form method="post" style="display: inline;">
                                    <?= csrf_field(); ?>
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="household_id" value="<?= $hh['id']; ?>">
                                    <button type="submit" class="btn btn-xs btn-danger" data-confirm-delete>Delete</button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<!-- Create Household Modal -->
<div id="create-household" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3>Create Household</h3>
            <button type="button" class="modal-close" data-close-modal>&times;</button>
        </div>
        <form method="post">
            <?= csrf_field(); ?>
            <input type="hidden" name="action" value="create">

            <div class="form-group">
                <label>Household Name *</label>
                <input type="text" name="name" required placeholder="e.g., The Smith Family">
            </div>

            <div class="form-group">
                <label>Primary Contact</label>
                <select name="primary_contact_id">
                    <option value="">Select person...</option>
                    <?php foreach ($allPeople as $p): ?>
                        <option value="<?= $p['id']; ?>">
                            <?= htmlspecialchars(trim(($p['first_name'] ?? '') . ' ' . ($p['last_name'] ?? '')) ?: $p['email']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="modal-footer">
                <button type="button" class="btn btn-outline" data-close-modal>Cancel</button>
                <button type="submit" class="btn btn-primary">Create Household</button>
            </div>
        </form>
    </div>
</div>

<style>
.household-members-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
    gap: 1rem;
    padding: 1rem;
}

.household-member-card {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    padding: 0.75rem;
    background: var(--color-surface-hover);
    border-radius: var(--radius);
    text-decoration: none;
    color: var(--color-text);
}

.household-member-card:hover {
    background: var(--color-primary-light);
}

.member-photo {
    width: 48px;
    height: 48px;
    border-radius: 50%;
    object-fit: cover;
}

.member-photo-initials {
    display: flex;
    align-items: center;
    justify-content: center;
    background: var(--color-primary);
    color: white;
    font-weight: 600;
}

.member-info {
    display: flex;
    flex-direction: column;
}

.member-name {
    font-weight: 600;
}

.member-role {
    font-size: 0.75rem;
    color: var(--color-text-muted);
}

.household-addresses {
    padding: 1rem;
    border-top: 1px solid var(--color-border);
}

.household-addresses h4 {
    font-size: 0.75rem;
    font-weight: 600;
    text-transform: uppercase;
    color: var(--color-text-muted);
    margin: 0 0 0.5rem;
}

.address-item {
    display: flex;
    gap: 0.5rem;
    padding: 0.5rem 0;
}

.address-type {
    font-size: 0.75rem;
    font-weight: 500;
    color: var(--color-text-muted);
    min-width: 60px;
}

.household-name-link {
    color: var(--color-text);
    text-decoration: none;
}

.household-name-link:hover {
    color: var(--color-primary);
}

.member-count-badge {
    display: inline-block;
    min-width: 24px;
    padding: 0.125rem 0.375rem;
    background: var(--color-surface-hover);
    border-radius: var(--radius-full);
    text-align: center;
    font-size: 0.875rem;
    font-weight: 600;
}

/* Modal */
.modal {
    display: none;
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: rgba(0, 0, 0, 0.5);
    z-index: 1000;
    align-items: center;
    justify-content: center;
}

.modal.open {
    display: flex;
}

.modal-content {
    background: var(--color-surface);
    border-radius: var(--radius-lg);
    width: 100%;
    max-width: 500px;
    max-height: 90vh;
    overflow-y: auto;
}

.modal-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 1rem 1.5rem;
    border-bottom: 1px solid var(--color-border);
}

.modal-header h3 {
    margin: 0;
}

.modal-close {
    background: none;
    border: none;
    font-size: 1.5rem;
    cursor: pointer;
    color: var(--color-text-muted);
}

.modal-content form {
    padding: 1.5rem;
}

.modal-footer {
    display: flex;
    justify-content: flex-end;
    gap: 0.5rem;
    margin-top: 1.5rem;
}

.text-muted {
    color: var(--color-text-muted);
}

.empty-state {
    text-align: center;
    padding: 2rem;
}
</style>

<script <?= csp_nonce(); ?>>
// Modal functionality
document.querySelectorAll('[data-open-modal]').forEach(btn => {
    btn.addEventListener('click', function() {
        document.getElementById(this.dataset.openModal).classList.add('open');
    });
});

document.querySelectorAll('[data-close-modal]').forEach(btn => {
    btn.addEventListener('click', function() {
        this.closest('.modal').classList.remove('open');
    });
});

document.querySelectorAll('.modal').forEach(modal => {
    modal.addEventListener('click', function(e) {
        if (e.target === this) {
            this.classList.remove('open');
        }
    });
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
