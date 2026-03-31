<?php
/**
 * People Management - View Person Profile (New Admin)
 *
 * Displays comprehensive profile view with all related data.
 */

require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../../includes/services/PeopleService.php';

$peopleService = new PeopleService($pdo);

$personId = (int)($_GET['id'] ?? 0);
if (!$personId) {
    header('Location: /adminnew?module=people');
    exit;
}

$person = $peopleService->getPerson($personId);
if (!$person) {
    echo '<div class="admin-card"><div class="admin-card-body"><p>Person not found. <a href="/adminnew/people">Back to list</a></p></div></div>';
    require_once __DIR__ . '/../includes/footer.php';
    exit;
}

$page_title = $person['display_name'];

// Handle quick actions
$success = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid security token';
    } else {
        $action = $_POST['action'] ?? '';

        switch ($action) {
            case 'add_note':
                $result = $peopleService->addNote(
                    $personId,
                    $_POST['note'],
                    $_POST['note_type'],
                    $_SESSION['admin_user_id'],
                    isset($_POST['is_pinned'])
                );
                if ($result['success']) {
                    $success = 'Note added successfully';
                    $person = $peopleService->getPerson($personId);
                } else {
                    $error = $result['error'];
                }
                break;

            case 'delete_note':
                $result = $peopleService->deleteNote((int)$_POST['note_id']);
                if ($result['success']) {
                    $success = 'Note deleted';
                    $person = $peopleService->getPerson($personId);
                } else {
                    $error = $result['error'];
                }
                break;

            case 'toggle_pin':
                $result = $peopleService->toggleNotePin((int)$_POST['note_id']);
                if ($result['success']) {
                    $person = $peopleService->getPerson($personId);
                }
                break;

            case 'add_tag':
                $result = $peopleService->addTag($personId, (int)$_POST['tag_id'], $_SESSION['admin_user_id']);
                if ($result['success']) {
                    $person = $peopleService->getPerson($personId);
                }
                break;

            case 'remove_tag':
                $result = $peopleService->removeTag($personId, (int)$_POST['tag_id']);
                if ($result['success']) {
                    $person = $peopleService->getPerson($personId);
                }
                break;

            case 'set_status':
                $result = $peopleService->setMembershipStatus($personId, (int)$_POST['status_id']);
                if ($result['success']) {
                    $success = 'Membership status updated';
                    $person = $peopleService->getPerson($personId);
                } else {
                    $error = $result['error'];
                }
                break;
        }
    }
}

// Get available tags for adding
$allTags = $peopleService->getTags();
$personTagIds = !empty($person['tags']) ? array_column($person['tags'], 'id') : [];
$statuses = $peopleService->getMembershipStatuses();

// Format dates helper
function formatDate($date, $format = 'M j, Y') {
    if (!$date) return null;
    return date($format, strtotime($date));
}

function calculateAge($birthdate) {
    if (!$birthdate) return null;
    $birth = new DateTime($birthdate);
    $today = new DateTime();
    return $birth->diff($today)->y;
}
?>

<!-- Alerts -->
<?php if ($success): ?>
<div class="admin-card" style="background: rgba(16, 185, 129, 0.1); border-color: var(--admin-success); margin-bottom: 1rem;">
    <div class="admin-card-body" style="padding: 0.75rem 1rem; color: var(--admin-success);">
        <?= htmlspecialchars($success); ?>
    </div>
</div>
<?php endif; ?>
<?php if ($error): ?>
<div class="admin-card" style="background: rgba(239, 68, 68, 0.1); border-color: var(--admin-danger); margin-bottom: 1rem;">
    <div class="admin-card-body" style="padding: 0.75rem 1rem; color: var(--admin-danger);">
        <?= htmlspecialchars($error); ?>
    </div>
</div>
<?php endif; ?>

<!-- Profile Header Card -->
<div class="admin-card admin-mb-lg">
    <div class="admin-card-body" style="padding: 1.5rem;">
        <div style="display: flex; gap: 1.5rem; align-items: flex-start;">
            <!-- Avatar -->
            <div style="flex-shrink: 0;">
                <?php if (!empty($person['profile_photo'])): ?>
                <img src="<?= htmlspecialchars($person['profile_photo']); ?>" alt=""
                     style="width: 96px; height: 96px; border-radius: 50%; object-fit: cover;">
                <?php else: ?>
                <div style="width: 96px; height: 96px; border-radius: 50%; background: var(--admin-primary); color: white; display: flex; align-items: center; justify-content: center; font-size: 2rem; font-weight: 700;">
                    <?= strtoupper(substr($person['first_name'] ?? $person['email'], 0, 1)); ?>
                </div>
                <?php endif; ?>
            </div>

            <!-- Info -->
            <div style="flex: 1;">
                <div style="display: flex; align-items: center; gap: 0.75rem; margin-bottom: 0.5rem;">
                    <h1 style="margin: 0; font-size: 1.5rem; font-weight: 700;">
                        <?= htmlspecialchars($person['display_name']); ?>
                    </h1>
                    <?php if ($person['nickname'] && $person['nickname'] !== $person['display_name']): ?>
                    <span class="admin-text-muted">"<?= htmlspecialchars($person['nickname']); ?>"</span>
                    <?php endif; ?>
                    <?php if ($person['is_member']): ?>
                    <span class="admin-badge admin-badge-success">Member</span>
                    <?php endif; ?>
                </div>

                <!-- Contact Info -->
                <div style="display: flex; flex-wrap: wrap; gap: 1rem; margin-bottom: 1rem;">
                    <?php if ($person['email']): ?>
                    <a href="mailto:<?= htmlspecialchars($person['email']); ?>" class="admin-text-muted" style="text-decoration: none; display: flex; align-items: center; gap: 0.375rem;">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/>
                            <polyline points="22,6 12,13 2,6"/>
                        </svg>
                        <?= htmlspecialchars($person['email']); ?>
                    </a>
                    <?php endif; ?>
                    <?php if (!empty($person['phone_numbers']) && isset($person['phone_numbers'][0])): ?>
                    <?php $primaryPhone = $person['phone_numbers'][0]; ?>
                    <span class="admin-text-muted" style="display: flex; align-items: center; gap: 0.375rem;">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z"/>
                        </svg>
                        <?= htmlspecialchars($primaryPhone['number']); ?>
                    </span>
                    <?php endif; ?>
                    <?php if (!empty($person['membership_status'])): ?>
                    <span class="admin-badge" style="background: <?= htmlspecialchars($person['membership_status']['color'] ?? '#6b7280'); ?>20; color: <?= htmlspecialchars($person['membership_status']['color'] ?? '#6b7280'); ?>;">
                        <?= htmlspecialchars($person['membership_status']['name']); ?>
                    </span>
                    <?php endif; ?>
                </div>

                <!-- Tags -->
                <div style="display: flex; flex-wrap: wrap; gap: 0.375rem; align-items: center;">
                    <?php foreach ($person['tags'] ?? [] as $tag): ?>
                    <form method="post" style="display: inline;">
                        <?= csrf_field(); ?>
                        <input type="hidden" name="action" value="remove_tag">
                        <input type="hidden" name="tag_id" value="<?= $tag['id']; ?>">
                        <span class="admin-badge" style="background: <?= htmlspecialchars($tag['color']); ?>20; color: <?= htmlspecialchars($tag['color']); ?>; display: inline-flex; align-items: center; gap: 0.25rem;">
                            <?= htmlspecialchars($tag['name']); ?>
                            <button type="submit" style="background: none; border: none; color: inherit; cursor: pointer; padding: 0; line-height: 1; opacity: 0.6;" title="Remove tag">&times;</button>
                        </span>
                    </form>
                    <?php endforeach; ?>
                    <div class="tag-dropdown" style="position: relative;">
                        <button type="button" class="admin-btn admin-btn-secondary admin-btn-sm" id="addTagBtn" style="padding: 0.125rem 0.5rem; font-size: 0.75rem;">
                            + Add Tag
                        </button>
                        <div id="tagMenu" style="display: none; position: absolute; top: 100%; left: 0; z-index: 100; min-width: 200px; max-height: 300px; overflow-y: auto; background: var(--admin-card-bg); border: 1px solid var(--admin-border); border-radius: var(--admin-radius); box-shadow: var(--admin-shadow-lg); margin-top: 0.25rem;">
                            <?php foreach ($allTags as $group => $groupTags): ?>
                            <div style="padding: 0.5rem 0.75rem; font-size: 0.625rem; font-weight: 700; text-transform: uppercase; color: var(--admin-text-muted); background: var(--admin-bg);">
                                <?= htmlspecialchars($group); ?>
                            </div>
                            <?php foreach ($groupTags as $tag): ?>
                            <?php if (!in_array($tag['id'], $personTagIds)): ?>
                            <form method="post">
                                <?= csrf_field(); ?>
                                <input type="hidden" name="action" value="add_tag">
                                <input type="hidden" name="tag_id" value="<?= $tag['id']; ?>">
                                <button type="submit" style="display: flex; align-items: center; gap: 0.5rem; width: 100%; padding: 0.5rem 0.75rem; background: none; border: none; text-align: left; cursor: pointer; font-size: 0.875rem;">
                                    <span style="width: 8px; height: 8px; border-radius: 50%; background: <?= htmlspecialchars($tag['color']); ?>;"></span>
                                    <?= htmlspecialchars($tag['name']); ?>
                                </button>
                            </form>
                            <?php endif; ?>
                            <?php endforeach; ?>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Actions -->
            <div style="display: flex; flex-direction: column; gap: 0.5rem; flex-shrink: 0;">
                <a href="/adminnew/people/edit&id=<?= $person['id']; ?>" class="admin-btn admin-btn-primary">Edit Profile</a>
                <a href="/adminnew/people" class="admin-btn admin-btn-secondary">Back to List</a>
            </div>
        </div>
    </div>
</div>

<!-- Main Content Grid -->
<div style="display: grid; grid-template-columns: 1fr 400px; gap: 1.5rem;">
    <!-- Left Column -->
    <div style="display: flex; flex-direction: column; gap: 1.5rem;">
        <!-- Personal Information -->
        <div class="admin-card">
            <div class="admin-card-header">
                <h3 class="admin-card-title">Personal Information</h3>
            </div>
            <div class="admin-card-body">
                <div style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 1rem;">
                    <?php if ($person['first_name'] || $person['last_name']): ?>
                    <div>
                        <div class="admin-text-muted" style="font-size: 0.75rem; text-transform: uppercase; letter-spacing: 0.05em; margin-bottom: 0.25rem;">Full Name</div>
                        <div><?= htmlspecialchars(trim(($person['prefix'] ?? '') . ' ' . ($person['first_name'] ?? '') . ' ' . ($person['middle_name'] ?? '') . ' ' . ($person['last_name'] ?? '') . ' ' . ($person['suffix'] ?? ''))); ?></div>
                    </div>
                    <?php endif; ?>
                    <?php if ($person['gender']): ?>
                    <div>
                        <div class="admin-text-muted" style="font-size: 0.75rem; text-transform: uppercase; letter-spacing: 0.05em; margin-bottom: 0.25rem;">Gender</div>
                        <div><?= htmlspecialchars(ucfirst($person['gender'])); ?></div>
                    </div>
                    <?php endif; ?>
                    <?php if ($person['birthdate']): ?>
                    <div>
                        <div class="admin-text-muted" style="font-size: 0.75rem; text-transform: uppercase; letter-spacing: 0.05em; margin-bottom: 0.25rem;">Birthday</div>
                        <div><?= formatDate($person['birthdate'], 'F j, Y'); ?> <span class="admin-text-muted">(<?= calculateAge($person['birthdate']); ?> years old)</span></div>
                    </div>
                    <?php endif; ?>
                    <?php if ($person['marital_status']): ?>
                    <div>
                        <div class="admin-text-muted" style="font-size: 0.75rem; text-transform: uppercase; letter-spacing: 0.05em; margin-bottom: 0.25rem;">Marital Status</div>
                        <div><?= htmlspecialchars(ucfirst($person['marital_status'])); ?></div>
                    </div>
                    <?php endif; ?>
                    <?php if ($person['anniversary']): ?>
                    <div>
                        <div class="admin-text-muted" style="font-size: 0.75rem; text-transform: uppercase; letter-spacing: 0.05em; margin-bottom: 0.25rem;">Anniversary</div>
                        <div><?= formatDate($person['anniversary'], 'F j, Y'); ?></div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Spiritual Journey -->
        <?php if ($person['salvation_date'] || $person['baptism_date'] || $person['member_since']): ?>
        <div class="admin-card">
            <div class="admin-card-header">
                <h3 class="admin-card-title">Spiritual Journey</h3>
            </div>
            <div class="admin-card-body">
                <div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 1rem;">
                    <?php if ($person['salvation_date']): ?>
                    <div>
                        <div class="admin-text-muted" style="font-size: 0.75rem; text-transform: uppercase; letter-spacing: 0.05em; margin-bottom: 0.25rem;">Salvation Date</div>
                        <div><?= formatDate($person['salvation_date'], 'F j, Y'); ?></div>
                    </div>
                    <?php endif; ?>
                    <?php if ($person['baptism_date']): ?>
                    <div>
                        <div class="admin-text-muted" style="font-size: 0.75rem; text-transform: uppercase; letter-spacing: 0.05em; margin-bottom: 0.25rem;">Baptism Date</div>
                        <div><?= formatDate($person['baptism_date'], 'F j, Y'); ?></div>
                    </div>
                    <?php endif; ?>
                    <?php if ($person['member_since']): ?>
                    <div>
                        <div class="admin-text-muted" style="font-size: 0.75rem; text-transform: uppercase; letter-spacing: 0.05em; margin-bottom: 0.25rem;">Member Since</div>
                        <div><?= formatDate($person['member_since'], 'F j, Y'); ?></div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- Contact Information -->
        <div class="admin-card">
            <div class="admin-card-header">
                <h3 class="admin-card-title">Contact Information</h3>
            </div>
            <div class="admin-card-body">
                <?php if (!empty($person['addresses'])): ?>
                <div style="margin-bottom: 1.5rem;">
                    <div class="admin-text-muted" style="font-size: 0.75rem; text-transform: uppercase; letter-spacing: 0.05em; margin-bottom: 0.75rem;">Addresses</div>
                    <?php foreach ($person['addresses'] as $address): ?>
                    <div style="display: flex; gap: 0.75rem; margin-bottom: 0.75rem;">
                        <span class="admin-text-muted" style="font-size: 0.75rem; min-width: 60px;"><?= htmlspecialchars(ucfirst($address['address_type'])); ?></span>
                        <div style="font-size: 0.875rem; line-height: 1.5;">
                            <?= htmlspecialchars($address['street_line_1']); ?><br>
                            <?php if ($address['street_line_2']): ?><?= htmlspecialchars($address['street_line_2']); ?><br><?php endif; ?>
                            <?= htmlspecialchars($address['city']); ?><?= $address['county'] ? ', ' . htmlspecialchars($address['county']) : ''; ?><br>
                            <?= htmlspecialchars($address['postcode']); ?>
                        </div>
                        <?php if ($address['is_primary']): ?>
                        <span class="admin-badge admin-badge-primary" style="height: fit-content;">Primary</span>
                        <?php endif; ?>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>

                <?php if (!empty($person['phone_numbers'])): ?>
                <div>
                    <div class="admin-text-muted" style="font-size: 0.75rem; text-transform: uppercase; letter-spacing: 0.05em; margin-bottom: 0.75rem;">Phone Numbers</div>
                    <?php foreach ($person['phone_numbers'] as $phone): ?>
                    <div style="display: flex; gap: 0.75rem; align-items: center; margin-bottom: 0.5rem;">
                        <span class="admin-text-muted" style="font-size: 0.75rem; min-width: 60px;"><?= htmlspecialchars(ucfirst($phone['location_type'])); ?></span>
                        <span style="font-size: 0.875rem;"><?= htmlspecialchars($phone['number']); ?></span>
                        <?php if ($phone['is_primary']): ?>
                        <span class="admin-badge admin-badge-primary">Primary</span>
                        <?php endif; ?>
                        <?php if ($phone['can_receive_sms']): ?>
                        <span class="admin-badge admin-badge-info">SMS</span>
                        <?php endif; ?>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>

                <?php if (empty($person['addresses']) && empty($person['phone_numbers'])): ?>
                <p class="admin-text-muted">No contact information on file.</p>
                <?php endif; ?>
            </div>
        </div>

        <!-- Household -->
        <?php if (!empty($person['household'])): ?>
        <div class="admin-card">
            <div class="admin-card-header">
                <h3 class="admin-card-title">Household</h3>
                <a href="/adminnew/people/households&id=<?= $person['household']['id']; ?>" class="admin-btn admin-btn-secondary admin-btn-sm">View Household</a>
            </div>
            <div class="admin-card-body">
                <div style="font-size: 1.125rem; font-weight: 600; margin-bottom: 0.25rem;"><?= htmlspecialchars($person['household']['name']); ?></div>
                <?php if ($person['household_role']): ?>
                <div class="admin-text-muted" style="margin-bottom: 1rem;">Role: <?= htmlspecialchars(ucfirst($person['household_role'])); ?></div>
                <?php endif; ?>
                <?php if (!empty($person['household']['members'])): ?>
                <div style="border-top: 1px solid var(--admin-border); padding-top: 1rem;">
                    <div class="admin-text-muted" style="font-size: 0.75rem; text-transform: uppercase; letter-spacing: 0.05em; margin-bottom: 0.75rem;">Family Members</div>
                    <?php foreach ($person['household']['members'] as $member): ?>
                    <?php if ($member['id'] != $person['id']): ?>
                    <a href="/adminnew?module=people&page=view&id=<?= $member['id']; ?>" style="display: block; padding: 0.5rem 0; color: var(--admin-text); text-decoration: none;">
                        <?= htmlspecialchars(trim($member['first_name'] . ' ' . $member['last_name'])); ?>
                        <?php if ($member['household_role']): ?>
                        <span class="admin-text-muted" style="font-size: 0.875rem;">(<?= htmlspecialchars($member['household_role']); ?>)</span>
                        <?php endif; ?>
                    </a>
                    <?php endif; ?>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>
    </div>

    <!-- Right Column -->
    <div style="display: flex; flex-direction: column; gap: 1.5rem;">
        <!-- Membership Status Quick Change -->
        <div class="admin-card">
            <div class="admin-card-header">
                <h3 class="admin-card-title">Membership Status</h3>
            </div>
            <div class="admin-card-body">
                <form method="post">
                    <?= csrf_field(); ?>
                    <input type="hidden" name="action" value="set_status">
                    <select name="status_id" onchange="this.form.submit()" class="admin-form-select">
                        <option value="">No Status</option>
                        <?php foreach ($statuses as $status): ?>
                        <option value="<?= $status['id']; ?>" <?= ($person['membership_status_id'] ?? '') == $status['id'] ? 'selected' : ''; ?>>
                            <?= htmlspecialchars($status['name']); ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </form>
            </div>
        </div>

        <!-- Notes -->
        <div class="admin-card">
            <div class="admin-card-header">
                <h3 class="admin-card-title">Notes</h3>
                <span class="admin-badge admin-badge-primary"><?= count($person['notes']); ?></span>
            </div>

            <!-- Add Note Form -->
            <div class="admin-card-body" style="border-bottom: 1px solid var(--admin-border);">
                <form method="post">
                    <?= csrf_field(); ?>
                    <input type="hidden" name="action" value="add_note">
                    <textarea name="note" placeholder="Add a note..." rows="2" class="admin-form-textarea" style="margin-bottom: 0.5rem;" required></textarea>
                    <div style="display: flex; gap: 0.5rem; align-items: center;">
                        <select name="note_type" class="admin-form-select" style="flex: 1;">
                            <option value="general">General</option>
                            <option value="prayer">Prayer Request</option>
                            <option value="pastoral">Pastoral</option>
                            <option value="follow_up">Follow Up</option>
                            <option value="private">Private</option>
                        </select>
                        <label style="display: flex; align-items: center; gap: 0.25rem; font-size: 0.75rem; cursor: pointer;">
                            <input type="checkbox" name="is_pinned" value="1"> Pin
                        </label>
                        <button type="submit" class="admin-btn admin-btn-primary admin-btn-sm">Add</button>
                    </div>
                </form>
            </div>

            <!-- Notes List -->
            <div style="max-height: 400px; overflow-y: auto;">
                <?php if (!empty($person['notes'])): ?>
                <?php foreach ($person['notes'] as $note): ?>
                <div style="padding: 1rem; border-bottom: 1px solid var(--admin-border);<?= $note['is_pinned'] ? ' background: rgba(245, 158, 11, 0.05);' : ''; ?>">
                    <div style="display: flex; align-items: center; gap: 0.5rem; margin-bottom: 0.5rem;">
                        <span class="admin-badge admin-badge-<?= $note['note_type'] === 'prayer' ? 'info' : ($note['note_type'] === 'pastoral' ? 'warning' : ($note['note_type'] === 'private' ? 'danger' : 'primary')); ?>" style="font-size: 0.625rem; text-transform: uppercase;">
                            <?= htmlspecialchars(ucfirst(str_replace('_', ' ', $note['note_type']))); ?>
                        </span>
                        <?php if ($note['is_pinned']): ?>
                        <span style="font-size: 0.75rem;">📌</span>
                        <?php endif; ?>
                        <span class="admin-text-muted" style="font-size: 0.75rem; margin-left: auto;"><?= formatDate($note['created_at'], 'M j, Y'); ?></span>
                    </div>
                    <div style="font-size: 0.875rem; line-height: 1.5; margin-bottom: 0.5rem;"><?= nl2br(htmlspecialchars($note['note'])); ?></div>
                    <div style="display: flex; justify-content: space-between; align-items: center;">
                        <span class="admin-text-muted" style="font-size: 0.75rem;">by <?= htmlspecialchars($note['created_by_name'] ?? 'Unknown'); ?></span>
                        <div style="display: flex; gap: 0.25rem;">
                            <form method="post" style="display: inline;">
                                <?= csrf_field(); ?>
                                <input type="hidden" name="action" value="toggle_pin">
                                <input type="hidden" name="note_id" value="<?= $note['id']; ?>">
                                <button type="submit" class="admin-btn admin-btn-secondary admin-btn-sm" style="padding: 0.125rem 0.375rem; font-size: 0.75rem;">
                                    <?= $note['is_pinned'] ? 'Unpin' : 'Pin'; ?>
                                </button>
                            </form>
                            <form method="post" style="display: inline;" onsubmit="return confirm('Delete this note?');">
                                <?= csrf_field(); ?>
                                <input type="hidden" name="action" value="delete_note">
                                <input type="hidden" name="note_id" value="<?= $note['id']; ?>">
                                <button type="submit" class="admin-btn admin-btn-secondary admin-btn-sm" style="padding: 0.125rem 0.375rem; font-size: 0.75rem; color: var(--admin-danger);">
                                    Delete
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
                <?php else: ?>
                <div class="admin-card-body">
                    <p class="admin-text-muted">No notes yet.</p>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Account Info -->
        <div class="admin-card">
            <div class="admin-card-header">
                <h3 class="admin-card-title">Account</h3>
            </div>
            <div class="admin-card-body">
                <div style="display: grid; gap: 0.5rem;">
                    <div style="display: flex; justify-content: space-between;">
                        <span class="admin-text-muted" style="font-size: 0.875rem;">Username</span>
                        <span style="font-size: 0.875rem;"><?= htmlspecialchars($person['username']); ?></span>
                    </div>
                    <div style="display: flex; justify-content: space-between;">
                        <span class="admin-text-muted" style="font-size: 0.875rem;">Role</span>
                        <span style="font-size: 0.875rem;"><?= htmlspecialchars(ucfirst($person['role'] ?? 'user')); ?></span>
                    </div>
                    <div style="display: flex; justify-content: space-between;">
                        <span class="admin-text-muted" style="font-size: 0.875rem;">Created</span>
                        <span style="font-size: 0.875rem;"><?= formatDate($person['created_at']); ?></span>
                    </div>
                    <?php if ($person['last_login']): ?>
                    <div style="display: flex; justify-content: space-between;">
                        <span class="admin-text-muted" style="font-size: 0.875rem;">Last Login</span>
                        <span style="font-size: 0.875rem;"><?= formatDate($person['last_login'], 'M j, Y g:i A'); ?></span>
                    </div>
                    <?php endif; ?>
                    <div style="display: flex; justify-content: space-between;">
                        <span class="admin-text-muted" style="font-size: 0.875rem;">Status</span>
                        <?php if ($person['active']): ?>
                        <span class="admin-badge admin-badge-success">Active</span>
                        <?php else: ?>
                        <span class="admin-badge admin-badge-danger">Inactive</span>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Communication Preferences -->
        <div class="admin-card">
            <div class="admin-card-header">
                <h3 class="admin-card-title">Preferences</h3>
            </div>
            <div class="admin-card-body">
                <div style="display: grid; gap: 0.5rem;">
                    <div style="display: flex; justify-content: space-between; align-items: center;">
                        <span style="font-size: 0.875rem;">Directory Visible</span>
                        <?php if ($person['directory_visible'] ?? true): ?>
                        <span class="admin-badge admin-badge-success">Yes</span>
                        <?php else: ?>
                        <span class="admin-badge admin-badge-warning">No</span>
                        <?php endif; ?>
                    </div>
                    <div style="display: flex; justify-content: space-between; align-items: center;">
                        <span style="font-size: 0.875rem;">Email Communications</span>
                        <?php if (!($person['email_opt_out'] ?? false)): ?>
                        <span class="admin-badge admin-badge-success">Opted In</span>
                        <?php else: ?>
                        <span class="admin-badge admin-badge-danger">Opted Out</span>
                        <?php endif; ?>
                    </div>
                    <div style="display: flex; justify-content: space-between; align-items: center;">
                        <span style="font-size: 0.875rem;">SMS Communications</span>
                        <?php if (!($person['sms_opt_out'] ?? false)): ?>
                        <span class="admin-badge admin-badge-success">Opted In</span>
                        <?php else: ?>
                        <span class="admin-badge admin-badge-danger">Opted Out</span>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// Tag dropdown toggle
document.getElementById('addTagBtn').addEventListener('click', function(e) {
    e.stopPropagation();
    var menu = document.getElementById('tagMenu');
    menu.style.display = menu.style.display === 'none' ? 'block' : 'none';
});

document.addEventListener('click', function() {
    document.getElementById('tagMenu').style.display = 'none';
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
