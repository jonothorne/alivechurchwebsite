<?php
/**
 * People Management - View Person Profile
 *
 * Displays comprehensive profile view with all related data.
 */

require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../../includes/db-config.php';
require_once __DIR__ . '/../../includes/services/PeopleService.php';

$pdo = getDbConnection();
$peopleService = new PeopleService($pdo);

$personId = (int)($_GET['id'] ?? 0);
if (!$personId) {
    header('Location: /admin/people');
    exit;
}

$person = $peopleService->getPerson($personId);
if (!$person) {
    echo '<div class="alert alert-error">Person not found.</div>';
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
                    $person = $peopleService->getPerson($personId); // Refresh
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
$personTagIds = array_column($person['tags'], 'id');
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

<?php if ($success): ?>
    <div class="admin-alert admin-alert-success"><?= htmlspecialchars($success); ?></div>
<?php endif; ?>
<?php if ($error): ?>
    <div class="admin-alert admin-alert-error"><?= htmlspecialchars($error); ?></div>
<?php endif; ?>

<!-- Profile Header -->
<div class="person-profile-header">
    <div class="profile-avatar-section">
        <?php if (!empty($person['profile_photo'])): ?>
            <img src="<?= htmlspecialchars($person['profile_photo']); ?>" alt="" class="profile-avatar-large">
        <?php else: ?>
            <div class="profile-avatar-large profile-avatar-initials">
                <?= strtoupper(substr($person['first_name'] ?? $person['email'], 0, 1)); ?>
            </div>
        <?php endif; ?>
    </div>

    <div class="profile-info-section">
        <div class="profile-name-row">
            <h1 class="profile-name">
                <?= htmlspecialchars($person['display_name']); ?>
                <?php if ($person['nickname'] && $person['nickname'] !== $person['display_name']): ?>
                    <span class="profile-nickname">"<?= htmlspecialchars($person['nickname']); ?>"</span>
                <?php endif; ?>
            </h1>
            <?php if ($person['is_member']): ?>
                <span class="badge badge-member-large">Member</span>
            <?php endif; ?>
        </div>

        <div class="profile-meta">
            <?php if ($person['email']): ?>
                <a href="mailto:<?= htmlspecialchars($person['email']); ?>" class="meta-item">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/>
                        <polyline points="22,6 12,13 2,6"/>
                    </svg>
                    <?= htmlspecialchars($person['email']); ?>
                </a>
            <?php endif; ?>

            <?php if (!empty($person['phone_numbers'])): ?>
                <?php $primaryPhone = $person['phone_numbers'][0]; ?>
                <span class="meta-item">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z"/>
                    </svg>
                    <?= htmlspecialchars($primaryPhone['number']); ?>
                </span>
            <?php endif; ?>

            <?php if ($person['membership_status']): ?>
                <span class="meta-item status-meta" style="--status-color: <?= htmlspecialchars($person['membership_status']['color'] ?? '#6B7280'); ?>">
                    <?= htmlspecialchars($person['membership_status']['name']); ?>
                </span>
            <?php endif; ?>
        </div>

        <!-- Tags -->
        <div class="profile-tags">
            <?php foreach ($person['tags'] as $tag): ?>
                <form method="post" class="tag-form">
                    <?= csrf_field(); ?>
                    <input type="hidden" name="action" value="remove_tag">
                    <input type="hidden" name="tag_id" value="<?= $tag['id']; ?>">
                    <span class="tag-badge" style="--tag-color: <?= htmlspecialchars($tag['color']); ?>">
                        <?= htmlspecialchars($tag['name']); ?>
                        <button type="submit" class="tag-remove" title="Remove tag">&times;</button>
                    </span>
                </form>
            <?php endforeach; ?>

            <div class="add-tag-dropdown">
                <button type="button" class="add-tag-btn" data-toggle-dropdown>+ Add Tag</button>
                <div class="dropdown-menu add-tag-menu">
                    <?php foreach ($allTags as $group => $groupTags): ?>
                        <div class="tag-group-header"><?= htmlspecialchars($group); ?></div>
                        <?php foreach ($groupTags as $tag): ?>
                            <?php if (!in_array($tag['id'], $personTagIds)): ?>
                                <form method="post">
                                    <?= csrf_field(); ?>
                                    <input type="hidden" name="action" value="add_tag">
                                    <input type="hidden" name="tag_id" value="<?= $tag['id']; ?>">
                                    <button type="submit" class="dropdown-item tag-option" style="--tag-color: <?= htmlspecialchars($tag['color']); ?>">
                                        <span class="tag-dot"></span>
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

    <div class="profile-actions">
        <a href="/admin/people?page=edit&id=<?= $person['id']; ?>" class="btn btn-primary">Edit Profile</a>
        <a href="/admin/people" class="btn btn-outline">Back to List</a>
    </div>
</div>

<!-- Main Content Grid -->
<div class="person-profile-grid">
    <!-- Left Column: Details -->
    <div class="profile-column profile-column-main">
        <!-- Personal Information -->
        <div class="admin-card">
            <div class="admin-card-header">
                <h3>Personal Information</h3>
            </div>
            <div class="detail-grid">
                <?php if ($person['first_name'] || $person['last_name']): ?>
                    <div class="detail-item">
                        <span class="detail-label">Full Name</span>
                        <span class="detail-value">
                            <?= htmlspecialchars(trim(($person['prefix'] ?? '') . ' ' . ($person['first_name'] ?? '') . ' ' . ($person['middle_name'] ?? '') . ' ' . ($person['last_name'] ?? '') . ' ' . ($person['suffix'] ?? ''))); ?>
                        </span>
                    </div>
                <?php endif; ?>

                <?php if ($person['gender']): ?>
                    <div class="detail-item">
                        <span class="detail-label">Gender</span>
                        <span class="detail-value"><?= htmlspecialchars(ucfirst($person['gender'])); ?></span>
                    </div>
                <?php endif; ?>

                <?php if ($person['birthdate']): ?>
                    <div class="detail-item">
                        <span class="detail-label">Birthday</span>
                        <span class="detail-value">
                            <?= formatDate($person['birthdate'], 'F j, Y'); ?>
                            <span class="detail-secondary">(<?= calculateAge($person['birthdate']); ?> years old)</span>
                        </span>
                    </div>
                <?php endif; ?>

                <?php if ($person['marital_status']): ?>
                    <div class="detail-item">
                        <span class="detail-label">Marital Status</span>
                        <span class="detail-value"><?= htmlspecialchars(ucfirst($person['marital_status'])); ?></span>
                    </div>
                <?php endif; ?>

                <?php if ($person['anniversary']): ?>
                    <div class="detail-item">
                        <span class="detail-label">Anniversary</span>
                        <span class="detail-value"><?= formatDate($person['anniversary'], 'F j, Y'); ?></span>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Spiritual Journey -->
        <?php if ($person['salvation_date'] || $person['baptism_date'] || $person['member_since']): ?>
            <div class="admin-card">
                <div class="admin-card-header">
                    <h3>Spiritual Journey</h3>
                </div>
                <div class="detail-grid">
                    <?php if ($person['salvation_date']): ?>
                        <div class="detail-item">
                            <span class="detail-label">Salvation Date</span>
                            <span class="detail-value"><?= formatDate($person['salvation_date'], 'F j, Y'); ?></span>
                        </div>
                    <?php endif; ?>

                    <?php if ($person['baptism_date']): ?>
                        <div class="detail-item">
                            <span class="detail-label">Baptism Date</span>
                            <span class="detail-value"><?= formatDate($person['baptism_date'], 'F j, Y'); ?></span>
                        </div>
                    <?php endif; ?>

                    <?php if ($person['member_since']): ?>
                        <div class="detail-item">
                            <span class="detail-label">Member Since</span>
                            <span class="detail-value"><?= formatDate($person['member_since'], 'F j, Y'); ?></span>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>

        <!-- Contact Information -->
        <div class="admin-card">
            <div class="admin-card-header">
                <h3>Contact Information</h3>
            </div>

            <!-- Addresses -->
            <?php if (!empty($person['addresses'])): ?>
                <div class="contact-section">
                    <h4 class="section-subheader">Addresses</h4>
                    <?php foreach ($person['addresses'] as $address): ?>
                        <div class="contact-item">
                            <span class="contact-type"><?= htmlspecialchars(ucfirst($address['address_type'])); ?></span>
                            <span class="contact-value">
                                <?= htmlspecialchars($address['street_line_1']); ?><br>
                                <?php if ($address['street_line_2']): ?>
                                    <?= htmlspecialchars($address['street_line_2']); ?><br>
                                <?php endif; ?>
                                <?= htmlspecialchars($address['city']); ?><?= $address['county'] ? ', ' . htmlspecialchars($address['county']) : ''; ?><br>
                                <?= htmlspecialchars($address['postcode']); ?>
                            </span>
                            <?php if ($address['is_primary']): ?>
                                <span class="badge badge-small">Primary</span>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <!-- Phone Numbers -->
            <?php if (!empty($person['phone_numbers'])): ?>
                <div class="contact-section">
                    <h4 class="section-subheader">Phone Numbers</h4>
                    <?php foreach ($person['phone_numbers'] as $phone): ?>
                        <div class="contact-item">
                            <span class="contact-type"><?= htmlspecialchars(ucfirst($phone['location_type'])); ?></span>
                            <span class="contact-value"><?= htmlspecialchars($phone['number']); ?></span>
                            <?php if ($phone['is_primary']): ?>
                                <span class="badge badge-small">Primary</span>
                            <?php endif; ?>
                            <?php if ($phone['can_receive_sms']): ?>
                                <span class="badge badge-small badge-info">SMS</span>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <?php if (empty($person['addresses']) && empty($person['phone_numbers'])): ?>
                <p class="text-muted">No contact information on file.</p>
            <?php endif; ?>
        </div>

        <!-- Household -->
        <?php if ($person['household']): ?>
            <div class="admin-card">
                <div class="admin-card-header">
                    <h3>Household</h3>
                    <a href="/admin/people?page=households&id=<?= $person['household']['id']; ?>" class="btn btn-xs btn-outline">View Household</a>
                </div>
                <div class="household-name-display"><?= htmlspecialchars($person['household']['name']); ?></div>
                <?php if ($person['household_role']): ?>
                    <div class="household-role-display">Role: <?= htmlspecialchars(ucfirst($person['household_role'])); ?></div>
                <?php endif; ?>

                <?php if (!empty($person['household']['members'])): ?>
                    <div class="household-members">
                        <h4 class="section-subheader">Family Members</h4>
                        <?php foreach ($person['household']['members'] as $member): ?>
                            <?php if ($member['id'] != $person['id']): ?>
                                <a href="/admin/people?page=view&id=<?= $member['id']; ?>" class="household-member-link">
                                    <span class="member-name"><?= htmlspecialchars(trim($member['first_name'] . ' ' . $member['last_name'])); ?></span>
                                    <?php if ($member['household_role']): ?>
                                        <span class="member-role">(<?= htmlspecialchars($member['household_role']); ?>)</span>
                                    <?php endif; ?>
                                </a>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>

    <!-- Right Column: Notes & Activity -->
    <div class="profile-column profile-column-sidebar">
        <!-- Membership Status Quick Change -->
        <div class="admin-card">
            <div class="admin-card-header">
                <h3>Membership Status</h3>
            </div>
            <form method="post">
                <?= csrf_field(); ?>
                <input type="hidden" name="action" value="set_status">
                <select name="status_id" onchange="this.form.submit()" class="status-select">
                    <option value="">No Status</option>
                    <?php foreach ($statuses as $status): ?>
                        <option value="<?= $status['id']; ?>"
                                <?= ($person['membership_status_id'] ?? '') == $status['id'] ? 'selected' : ''; ?>
                                data-color="<?= htmlspecialchars($status['color']); ?>">
                            <?= htmlspecialchars($status['name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </form>
        </div>

        <!-- Notes -->
        <div class="admin-card">
            <div class="admin-card-header">
                <h3>Notes</h3>
                <span class="note-count"><?= count($person['notes']); ?></span>
            </div>

            <!-- Add Note Form -->
            <form method="post" class="add-note-form">
                <?= csrf_field(); ?>
                <input type="hidden" name="action" value="add_note">
                <textarea name="note" placeholder="Add a note..." rows="2" required></textarea>
                <div class="note-form-row">
                    <select name="note_type">
                        <option value="general">General</option>
                        <option value="prayer">Prayer Request</option>
                        <option value="pastoral">Pastoral</option>
                        <option value="follow_up">Follow Up</option>
                        <option value="private">Private</option>
                    </select>
                    <label class="pin-label">
                        <input type="checkbox" name="is_pinned" value="1">
                        Pin
                    </label>
                    <button type="submit" class="btn btn-sm btn-primary">Add Note</button>
                </div>
            </form>

            <!-- Notes List -->
            <?php if (!empty($person['notes'])): ?>
                <div class="notes-list">
                    <?php foreach ($person['notes'] as $note): ?>
                        <div class="note-item <?= $note['is_pinned'] ? 'note-pinned' : ''; ?>">
                            <div class="note-header">
                                <span class="note-type note-type-<?= $note['note_type']; ?>">
                                    <?= htmlspecialchars(ucfirst(str_replace('_', ' ', $note['note_type']))); ?>
                                </span>
                                <?php if ($note['is_pinned']): ?>
                                    <span class="note-pin-icon" title="Pinned">📌</span>
                                <?php endif; ?>
                                <span class="note-date"><?= formatDate($note['created_at'], 'M j, Y'); ?></span>
                            </div>
                            <div class="note-content"><?= nl2br(htmlspecialchars($note['note'])); ?></div>
                            <div class="note-footer">
                                <span class="note-author">by <?= htmlspecialchars($note['created_by_name'] ?? 'Unknown'); ?></span>
                                <div class="note-actions">
                                    <form method="post" style="display: inline;">
                                        <?= csrf_field(); ?>
                                        <input type="hidden" name="action" value="toggle_pin">
                                        <input type="hidden" name="note_id" value="<?= $note['id']; ?>">
                                        <button type="submit" class="btn btn-xs btn-ghost" title="<?= $note['is_pinned'] ? 'Unpin' : 'Pin'; ?>">
                                            <?= $note['is_pinned'] ? 'Unpin' : 'Pin'; ?>
                                        </button>
                                    </form>
                                    <form method="post" style="display: inline;">
                                        <?= csrf_field(); ?>
                                        <input type="hidden" name="action" value="delete_note">
                                        <input type="hidden" name="note_id" value="<?= $note['id']; ?>">
                                        <button type="submit" class="btn btn-xs btn-ghost btn-danger" data-confirm-delete>Delete</button>
                                    </form>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <p class="text-muted" style="padding: 1rem;">No notes yet.</p>
            <?php endif; ?>
        </div>

        <!-- Account Info -->
        <div class="admin-card">
            <div class="admin-card-header">
                <h3>Account</h3>
            </div>
            <div class="detail-grid compact">
                <div class="detail-item">
                    <span class="detail-label">Username</span>
                    <span class="detail-value"><?= htmlspecialchars($person['username']); ?></span>
                </div>
                <div class="detail-item">
                    <span class="detail-label">Role</span>
                    <span class="detail-value"><?= htmlspecialchars(ucfirst($person['role'] ?? 'user')); ?></span>
                </div>
                <div class="detail-item">
                    <span class="detail-label">Created</span>
                    <span class="detail-value"><?= formatDate($person['created_at']); ?></span>
                </div>
                <?php if ($person['last_login']): ?>
                    <div class="detail-item">
                        <span class="detail-label">Last Login</span>
                        <span class="detail-value"><?= formatDate($person['last_login'], 'M j, Y g:i A'); ?></span>
                    </div>
                <?php endif; ?>
                <div class="detail-item">
                    <span class="detail-label">Status</span>
                    <span class="detail-value">
                        <?php if ($person['active']): ?>
                            <span class="badge badge-success">Active</span>
                        <?php else: ?>
                            <span class="badge badge-danger">Inactive</span>
                        <?php endif; ?>
                    </span>
                </div>
            </div>
        </div>

        <!-- Communication Preferences -->
        <div class="admin-card">
            <div class="admin-card-header">
                <h3>Preferences</h3>
            </div>
            <div class="preferences-list">
                <div class="pref-item">
                    <span>Directory Visible</span>
                    <?php if ($person['directory_visible'] ?? true): ?>
                        <span class="badge badge-success">Yes</span>
                    <?php else: ?>
                        <span class="badge badge-muted">No</span>
                    <?php endif; ?>
                </div>
                <div class="pref-item">
                    <span>Email Communications</span>
                    <?php if (!($person['email_opt_out'] ?? false)): ?>
                        <span class="badge badge-success">Opted In</span>
                    <?php else: ?>
                        <span class="badge badge-danger">Opted Out</span>
                    <?php endif; ?>
                </div>
                <div class="pref-item">
                    <span>SMS Communications</span>
                    <?php if (!($person['sms_opt_out'] ?? false)): ?>
                        <span class="badge badge-success">Opted In</span>
                    <?php else: ?>
                        <span class="badge badge-danger">Opted Out</span>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
/* Profile Header */
.person-profile-header {
    display: flex;
    gap: 1.5rem;
    align-items: flex-start;
    padding: 1.5rem;
    background: var(--color-surface);
    border-radius: var(--radius-lg);
    border: 1px solid var(--color-border);
    margin-bottom: 1.5rem;
}

.profile-avatar-large {
    width: 96px;
    height: 96px;
    border-radius: 50%;
    object-fit: cover;
    flex-shrink: 0;
}

.profile-avatar-initials {
    display: flex;
    align-items: center;
    justify-content: center;
    background: var(--color-primary);
    color: white;
    font-size: 2rem;
    font-weight: 700;
}

.profile-info-section {
    flex: 1;
}

.profile-name-row {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    margin-bottom: 0.5rem;
}

.profile-name {
    font-size: 1.5rem;
    font-weight: 700;
    margin: 0;
}

.profile-nickname {
    color: var(--color-text-muted);
    font-weight: 400;
}

.badge-member-large {
    padding: 0.25rem 0.75rem;
    font-size: 0.75rem;
    font-weight: 600;
    text-transform: uppercase;
    background: var(--color-success-bg);
    color: var(--color-success);
    border-radius: var(--radius-full);
}

.profile-meta {
    display: flex;
    flex-wrap: wrap;
    gap: 1rem;
    margin-bottom: 1rem;
}

.meta-item {
    display: flex;
    align-items: center;
    gap: 0.375rem;
    color: var(--color-text-muted);
    font-size: 0.875rem;
    text-decoration: none;
}

.meta-item:hover {
    color: var(--color-primary);
}

.meta-item svg {
    opacity: 0.7;
}

.status-meta {
    padding: 0.25rem 0.5rem;
    background: color-mix(in srgb, var(--status-color) 15%, transparent);
    color: var(--status-color);
    border-radius: var(--radius);
    font-weight: 500;
}

/* Tags */
.profile-tags {
    display: flex;
    flex-wrap: wrap;
    gap: 0.375rem;
    align-items: center;
}

.tag-form {
    display: inline;
}

.tag-badge {
    display: inline-flex;
    align-items: center;
    gap: 0.25rem;
    padding: 0.25rem 0.5rem;
    background: color-mix(in srgb, var(--tag-color) 15%, transparent);
    color: var(--tag-color);
    border-radius: var(--radius);
    font-size: 0.75rem;
    font-weight: 500;
}

.tag-remove {
    background: none;
    border: none;
    color: inherit;
    cursor: pointer;
    padding: 0;
    line-height: 1;
    opacity: 0.6;
}

.tag-remove:hover {
    opacity: 1;
}

.add-tag-dropdown {
    position: relative;
}

.add-tag-btn {
    background: none;
    border: 1px dashed var(--color-border);
    padding: 0.25rem 0.5rem;
    border-radius: var(--radius);
    font-size: 0.75rem;
    color: var(--color-text-muted);
    cursor: pointer;
}

.add-tag-btn:hover {
    border-color: var(--color-primary);
    color: var(--color-primary);
}

.add-tag-menu {
    position: absolute;
    top: 100%;
    left: 0;
    z-index: 100;
    min-width: 200px;
    max-height: 300px;
    overflow-y: auto;
    background: var(--color-surface);
    border: 1px solid var(--color-border);
    border-radius: var(--radius);
    box-shadow: var(--shadow-lg);
    display: none;
}

.add-tag-dropdown.open .add-tag-menu {
    display: block;
}

.tag-group-header {
    padding: 0.5rem 0.75rem;
    font-size: 0.625rem;
    font-weight: 700;
    text-transform: uppercase;
    color: var(--color-text-muted);
    background: var(--color-surface-hover);
}

.tag-option {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    width: 100%;
    padding: 0.5rem 0.75rem;
    background: none;
    border: none;
    text-align: left;
    cursor: pointer;
    font-size: 0.875rem;
}

.tag-option:hover {
    background: var(--color-surface-hover);
}

.tag-dot {
    width: 8px;
    height: 8px;
    border-radius: 50%;
    background: var(--tag-color);
}

.profile-actions {
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
    flex-shrink: 0;
}

/* Profile Grid */
.person-profile-grid {
    display: grid;
    grid-template-columns: 1fr 400px;
    gap: 1.5rem;
}

/* Detail Grid */
.detail-grid {
    display: grid;
    gap: 1rem;
}

.detail-grid.compact {
    gap: 0.5rem;
}

.detail-item {
    display: flex;
    flex-direction: column;
    gap: 0.125rem;
}

.detail-label {
    font-size: 0.75rem;
    font-weight: 500;
    text-transform: uppercase;
    letter-spacing: 0.05em;
    color: var(--color-text-muted);
}

.detail-value {
    font-size: 0.9375rem;
}

.detail-secondary {
    color: var(--color-text-muted);
    font-size: 0.875rem;
}

/* Contact Sections */
.contact-section {
    padding-top: 1rem;
    border-top: 1px solid var(--color-border);
}

.contact-section:first-child {
    padding-top: 0;
    border-top: none;
}

.section-subheader {
    font-size: 0.75rem;
    font-weight: 600;
    text-transform: uppercase;
    color: var(--color-text-muted);
    margin: 0 0 0.75rem;
}

.contact-item {
    display: flex;
    align-items: flex-start;
    gap: 0.75rem;
    margin-bottom: 0.75rem;
}

.contact-type {
    font-size: 0.75rem;
    font-weight: 500;
    color: var(--color-text-muted);
    min-width: 60px;
}

.contact-value {
    flex: 1;
    font-size: 0.875rem;
    line-height: 1.5;
}

.badge-small {
    font-size: 0.625rem;
    padding: 0.125rem 0.375rem;
}

/* Household */
.household-name-display {
    font-size: 1.125rem;
    font-weight: 600;
    margin-bottom: 0.25rem;
}

.household-role-display {
    color: var(--color-text-muted);
    font-size: 0.875rem;
    margin-bottom: 1rem;
}

.household-members {
    padding-top: 1rem;
    border-top: 1px solid var(--color-border);
}

.household-member-link {
    display: block;
    padding: 0.5rem 0;
    color: var(--color-text);
    text-decoration: none;
}

.household-member-link:hover {
    color: var(--color-primary);
}

.member-role {
    color: var(--color-text-muted);
    font-size: 0.875rem;
}

/* Status Select */
.status-select {
    width: 100%;
}

/* Notes */
.note-count {
    background: var(--color-surface-hover);
    padding: 0.125rem 0.5rem;
    border-radius: var(--radius-full);
    font-size: 0.75rem;
    font-weight: 600;
}

.add-note-form {
    padding: 1rem;
    border-bottom: 1px solid var(--color-border);
}

.add-note-form textarea {
    width: 100%;
    margin-bottom: 0.5rem;
    resize: vertical;
}

.note-form-row {
    display: flex;
    gap: 0.5rem;
    align-items: center;
}

.note-form-row select {
    flex: 1;
}

.pin-label {
    display: flex;
    align-items: center;
    gap: 0.25rem;
    font-size: 0.75rem;
    cursor: pointer;
}

.pin-label input {
    width: auto;
}

.notes-list {
    max-height: 500px;
    overflow-y: auto;
}

.note-item {
    padding: 1rem;
    border-bottom: 1px solid var(--color-border);
}

.note-item:last-child {
    border-bottom: none;
}

.note-pinned {
    background: color-mix(in srgb, var(--color-warning) 5%, transparent);
}

.note-header {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    margin-bottom: 0.5rem;
}

.note-type {
    font-size: 0.625rem;
    font-weight: 600;
    text-transform: uppercase;
    padding: 0.125rem 0.375rem;
    border-radius: var(--radius);
}

.note-type-general { background: var(--color-surface-hover); }
.note-type-prayer { background: #e0e7ff; color: #4338ca; }
.note-type-pastoral { background: #fce7f3; color: #be185d; }
.note-type-follow_up { background: #fef3c7; color: #b45309; }
.note-type-private { background: #fee2e2; color: #b91c1c; }

.note-pin-icon {
    font-size: 0.75rem;
}

.note-date {
    font-size: 0.75rem;
    color: var(--color-text-muted);
    margin-left: auto;
}

.note-content {
    font-size: 0.875rem;
    line-height: 1.5;
    margin-bottom: 0.5rem;
}

.note-footer {
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.note-author {
    font-size: 0.75rem;
    color: var(--color-text-muted);
}

.note-actions {
    display: flex;
    gap: 0.25rem;
}

/* Preferences */
.preferences-list {
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
}

.pref-item {
    display: flex;
    justify-content: space-between;
    align-items: center;
    font-size: 0.875rem;
}

/* Responsive */
@media (max-width: 1024px) {
    .person-profile-grid {
        grid-template-columns: 1fr;
    }
}

@media (max-width: 768px) {
    .person-profile-header {
        flex-direction: column;
        align-items: center;
        text-align: center;
    }

    .profile-name-row {
        flex-direction: column;
    }

    .profile-meta {
        justify-content: center;
    }

    .profile-tags {
        justify-content: center;
    }

    .profile-actions {
        flex-direction: row;
        width: 100%;
    }

    .profile-actions .btn {
        flex: 1;
    }
}
</style>

<script <?= csp_nonce(); ?>>
// Tag dropdown toggle
document.querySelectorAll('[data-toggle-dropdown]').forEach(btn => {
    btn.addEventListener('click', function(e) {
        e.stopPropagation();
        this.closest('.add-tag-dropdown').classList.toggle('open');
    });
});

document.addEventListener('click', function() {
    document.querySelectorAll('.add-tag-dropdown').forEach(d => d.classList.remove('open'));
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
