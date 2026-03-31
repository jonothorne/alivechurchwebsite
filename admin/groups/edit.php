<?php
/**
 * Groups Management - Create/Edit Group
 */

$page_title = 'Create Group';
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../../includes/db-config.php';
require_once __DIR__ . '/../../includes/services/GroupsService.php';

$pdo = getDbConnection();
$groupsService = new GroupsService($pdo);

$groupId = (int)($_GET['id'] ?? 0);
$group = null;
$success = '';
$error = '';

if ($groupId) {
    $group = $groupsService->getGroup($groupId);
    if (!$group) {
        header('Location: /admin/groups');
        exit;
    }
    $page_title = 'Edit ' . $group['name'];
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && verify_csrf_token($_POST['csrf_token'] ?? '')) {
    $action = $_POST['action'] ?? 'save';

    if ($action === 'delete' && $groupId) {
        $stmt = $pdo->prepare("UPDATE `groups` SET status = 'archived' WHERE id = ?");
        $stmt->execute([$groupId]);
        header('Location: /admin/groups?deleted=1');
        exit;
    }

    $data = [
        'group_type_id' => (int)$_POST['group_type_id'],
        'name' => trim($_POST['name']),
        'description' => trim($_POST['description']) ?: null,
        'meeting_day' => $_POST['meeting_day'] ?: null,
        'meeting_time' => $_POST['meeting_time'] ?: null,
        'meeting_frequency' => $_POST['meeting_frequency'] ?: 'weekly',
        'location_type' => $_POST['location_type'] ?: 'physical',
        'location_name' => trim($_POST['location_name']) ?: null,
        'location_address' => trim($_POST['location_address']) ?: null,
        'location_city' => trim($_POST['location_city']) ?: null,
        'location_postcode' => trim($_POST['location_postcode']) ?: null,
        'online_url' => trim($_POST['online_url']) ?: null,
        'visibility' => $_POST['visibility'] ?: 'public',
        'allow_signups' => isset($_POST['allow_signups']) ? 1 : 0,
        'requires_approval' => isset($_POST['requires_approval']) ? 1 : 0,
        'max_members' => $_POST['max_members'] ? (int)$_POST['max_members'] : null,
        'contact_email' => trim($_POST['contact_email']) ?: null,
        'contact_phone' => trim($_POST['contact_phone']) ?: null,
        'childcare_available' => isset($_POST['childcare_available']) ? 1 : 0,
        'image_url' => trim($_POST['image_url']) ?: null,
        'status' => $_POST['status'] ?: 'active',
        'created_by' => $_SESSION['admin_user_id'],
    ];

    if ($groupId) {
        $result = $groupsService->updateGroup($groupId, $data);
    } else {
        $result = $groupsService->createGroup($data);
        if ($result['success']) {
            header('Location: /admin/groups/view.php?id=' . $result['group_id'] . '&created=1');
            exit;
        }
    }

    if ($result['success']) {
        $success = 'Group saved successfully';
        $group = $groupsService->getGroup($groupId);
    } else {
        $error = $result['error'];
    }
}

$types = $groupsService->getGroupTypes();
$days = ['sunday'=>'Sunday','monday'=>'Monday','tuesday'=>'Tuesday','wednesday'=>'Wednesday','thursday'=>'Thursday','friday'=>'Friday','saturday'=>'Saturday'];
?>

<?php if ($success): ?><div class="admin-alert admin-alert-success"><?= htmlspecialchars($success); ?></div><?php endif; ?>
<?php if ($error): ?><div class="admin-alert admin-alert-error"><?= htmlspecialchars($error); ?></div><?php endif; ?>

<form method="post" class="group-edit-form">
    <?= csrf_field(); ?>
    <input type="hidden" name="action" value="save">

    <div class="form-header">
        <a href="<?= $groupId ? '/admin/groups/view.php?id=' . $groupId : '/admin/groups'; ?>" class="btn btn-outline">&larr; <?= $groupId ? 'Back to Group' : 'All Groups'; ?></a>
        <button type="submit" class="btn btn-primary">Save Group</button>
    </div>

    <div class="form-grid">
        <div class="form-column form-column-main">
            <!-- Basic Info -->
            <div class="admin-card">
                <div class="admin-card-header"><h3>Basic Information</h3></div>
                <div class="form-row-2">
                    <div class="form-group">
                        <label>Group Name *</label>
                        <input type="text" name="name" value="<?= htmlspecialchars($group['name'] ?? ''); ?>" required>
                    </div>
                    <div class="form-group">
                        <label>Group Type *</label>
                        <select name="group_type_id" required>
                            <option value="">Select type...</option>
                            <?php foreach ($types as $t): ?>
                                <option value="<?= $t['id']; ?>" <?= ($group['group_type_id'] ?? '') == $t['id'] ? 'selected' : ''; ?>><?= htmlspecialchars($t['name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="form-group">
                    <label>Description</label>
                    <textarea name="description" rows="4"><?= htmlspecialchars($group['description'] ?? ''); ?></textarea>
                </div>
            </div>

            <!-- Schedule -->
            <div class="admin-card">
                <div class="admin-card-header"><h3>Schedule</h3></div>
                <div class="form-row-3">
                    <div class="form-group">
                        <label>Meeting Day</label>
                        <select name="meeting_day">
                            <option value="">Not specified</option>
                            <?php foreach ($days as $k => $v): ?>
                                <option value="<?= $k; ?>" <?= ($group['meeting_day'] ?? '') === $k ? 'selected' : ''; ?>><?= $v; ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Meeting Time</label>
                        <input type="time" name="meeting_time" value="<?= htmlspecialchars($group['meeting_time'] ?? ''); ?>">
                    </div>
                    <div class="form-group">
                        <label>Frequency</label>
                        <select name="meeting_frequency">
                            <option value="weekly" <?= ($group['meeting_frequency'] ?? '') === 'weekly' ? 'selected' : ''; ?>>Weekly</option>
                            <option value="bi-weekly" <?= ($group['meeting_frequency'] ?? '') === 'bi-weekly' ? 'selected' : ''; ?>>Bi-Weekly</option>
                            <option value="monthly" <?= ($group['meeting_frequency'] ?? '') === 'monthly' ? 'selected' : ''; ?>>Monthly</option>
                        </select>
                    </div>
                </div>
            </div>

            <!-- Location -->
            <div class="admin-card">
                <div class="admin-card-header"><h3>Location</h3></div>
                <div class="form-group">
                    <label>Location Type</label>
                    <select name="location_type" id="location-type">
                        <option value="physical" <?= ($group['location_type'] ?? '') === 'physical' ? 'selected' : ''; ?>>Physical Location</option>
                        <option value="online" <?= ($group['location_type'] ?? '') === 'online' ? 'selected' : ''; ?>>Online Only</option>
                        <option value="hybrid" <?= ($group['location_type'] ?? '') === 'hybrid' ? 'selected' : ''; ?>>Hybrid</option>
                    </select>
                </div>

                <div id="physical-location">
                    <div class="form-row-2">
                        <div class="form-group">
                            <label>Location Name</label>
                            <input type="text" name="location_name" value="<?= htmlspecialchars($group['location_name'] ?? ''); ?>" placeholder="e.g. Smith's Home">
                        </div>
                        <div class="form-group">
                            <label>City</label>
                            <input type="text" name="location_city" value="<?= htmlspecialchars($group['location_city'] ?? ''); ?>">
                        </div>
                    </div>
                    <div class="form-group">
                        <label>Full Address</label>
                        <textarea name="location_address" rows="2"><?= htmlspecialchars($group['location_address'] ?? ''); ?></textarea>
                    </div>
                    <div class="form-group">
                        <label>Postcode</label>
                        <input type="text" name="location_postcode" value="<?= htmlspecialchars($group['location_postcode'] ?? ''); ?>" style="width: 150px;">
                    </div>
                </div>

                <div id="online-location" style="display: none;">
                    <div class="form-group">
                        <label>Online Meeting URL</label>
                        <input type="url" name="online_url" value="<?= htmlspecialchars($group['online_url'] ?? ''); ?>" placeholder="https://zoom.us/...">
                    </div>
                </div>
            </div>

            <!-- Contact -->
            <div class="admin-card">
                <div class="admin-card-header"><h3>Contact</h3></div>
                <div class="form-row-2">
                    <div class="form-group">
                        <label>Contact Email</label>
                        <input type="email" name="contact_email" value="<?= htmlspecialchars($group['contact_email'] ?? ''); ?>">
                    </div>
                    <div class="form-group">
                        <label>Contact Phone</label>
                        <input type="tel" name="contact_phone" value="<?= htmlspecialchars($group['contact_phone'] ?? ''); ?>">
                    </div>
                </div>
            </div>
        </div>

        <div class="form-column form-column-sidebar">
            <!-- Status -->
            <div class="admin-card">
                <div class="admin-card-header"><h3>Status</h3></div>
                <div class="form-group">
                    <select name="status">
                        <option value="active" <?= ($group['status'] ?? 'active') === 'active' ? 'selected' : ''; ?>>Active</option>
                        <option value="inactive" <?= ($group['status'] ?? '') === 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                    </select>
                </div>
            </div>

            <!-- Visibility & Signups -->
            <div class="admin-card">
                <div class="admin-card-header"><h3>Visibility & Signups</h3></div>
                <div class="form-group">
                    <label>Visibility</label>
                    <select name="visibility">
                        <option value="public" <?= ($group['visibility'] ?? 'public') === 'public' ? 'selected' : ''; ?>>Public - Listed in finder</option>
                        <option value="unlisted" <?= ($group['visibility'] ?? '') === 'unlisted' ? 'selected' : ''; ?>>Unlisted - Direct link only</option>
                        <option value="private" <?= ($group['visibility'] ?? '') === 'private' ? 'selected' : ''; ?>>Private - Not visible</option>
                    </select>
                </div>
                <div class="form-group">
                    <label class="checkbox-label">
                        <input type="checkbox" name="allow_signups" value="1" <?= ($group['allow_signups'] ?? 1) ? 'checked' : ''; ?>>
                        <span>Allow online signups</span>
                    </label>
                </div>
                <div class="form-group">
                    <label class="checkbox-label">
                        <input type="checkbox" name="requires_approval" value="1" <?= ($group['requires_approval'] ?? 0) ? 'checked' : ''; ?>>
                        <span>Require leader approval</span>
                    </label>
                </div>
                <div class="form-group">
                    <label>Max Members</label>
                    <input type="number" name="max_members" value="<?= htmlspecialchars($group['max_members'] ?? ''); ?>" min="1" placeholder="Unlimited">
                </div>
            </div>

            <!-- Features -->
            <div class="admin-card">
                <div class="admin-card-header"><h3>Features</h3></div>
                <div class="form-group">
                    <label class="checkbox-label">
                        <input type="checkbox" name="childcare_available" value="1" <?= ($group['childcare_available'] ?? 0) ? 'checked' : ''; ?>>
                        <span>Childcare available</span>
                    </label>
                </div>
            </div>

            <!-- Image -->
            <div class="admin-card">
                <div class="admin-card-header"><h3>Image</h3></div>
                <div class="form-group">
                    <label>Image URL</label>
                    <input type="url" name="image_url" value="<?= htmlspecialchars($group['image_url'] ?? ''); ?>" placeholder="/uploads/...">
                </div>
            </div>

            <?php if ($groupId): ?>
            <div class="admin-card card-danger">
                <div class="admin-card-header"><h3>Danger Zone</h3></div>
                <p class="text-muted" style="font-size: 0.875rem; margin-bottom: 1rem;">Archive this group. Members will be preserved.</p>
                <button type="submit" name="action" value="delete" class="btn btn-danger btn-block" data-confirm="Archive this group?">Archive Group</button>
            </div>
            <?php endif; ?>
        </div>
    </div>
</form>

<style>
.form-header { display: flex; justify-content: space-between; margin-bottom: 1.5rem; }
.form-grid { display: grid; grid-template-columns: 1fr 350px; gap: 1.5rem; }
.form-row-2 { display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; }
.form-row-3 { display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 1rem; }
.checkbox-label { display: flex; align-items: center; gap: 0.5rem; cursor: pointer; }
.checkbox-label input { width: auto; }
.card-danger { border-color: var(--color-danger); }
.card-danger .admin-card-header h3 { color: var(--color-danger); }
.btn-block { width: 100%; }
@media (max-width: 1024px) { .form-grid { grid-template-columns: 1fr; } }
@media (max-width: 640px) { .form-row-2, .form-row-3 { grid-template-columns: 1fr; } }
</style>

<script <?= csp_nonce(); ?>>
const locationType = document.getElementById('location-type');
const physicalDiv = document.getElementById('physical-location');
const onlineDiv = document.getElementById('online-location');

function updateLocationFields() {
    const val = locationType.value;
    physicalDiv.style.display = (val === 'physical' || val === 'hybrid') ? 'block' : 'none';
    onlineDiv.style.display = (val === 'online' || val === 'hybrid') ? 'block' : 'none';
}

locationType?.addEventListener('change', updateLocationFields);
updateLocationFields();
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
