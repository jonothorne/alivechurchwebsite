<?php
/**
 * People Management - Add/Edit Person
 *
 * Form for creating new people or editing existing profiles.
 */

$page_title = 'Edit Person';
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../../includes/db-config.php';
require_once __DIR__ . '/../../includes/services/PeopleService.php';

$pdo = getDbConnection();
$peopleService = new PeopleService($pdo);

$personId = (int)($_GET['id'] ?? 0);
$person = null;

if ($personId) {
    $person = $peopleService->getPerson($personId);
    if (!$person) {
        echo '<div class="alert alert-error">Person not found.</div>';
        require_once __DIR__ . '/../includes/footer.php';
        exit;
    }
    $page_title = 'Edit ' . $person['display_name'];
} else {
    $page_title = 'Add Person';
}

$success = '';
$error = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid security token';
    } else {
        $data = [
            'email' => trim($_POST['email']),
            'first_name' => trim($_POST['first_name']),
            'last_name' => trim($_POST['last_name']),
            'middle_name' => trim($_POST['middle_name']) ?: null,
            'nickname' => trim($_POST['nickname']) ?: null,
            'prefix' => trim($_POST['prefix']) ?: null,
            'suffix' => trim($_POST['suffix']) ?: null,
            'gender' => $_POST['gender'] ?: null,
            'birthdate' => $_POST['birthdate'] ?: null,
            'marital_status' => $_POST['marital_status'] ?: null,
            'anniversary' => $_POST['anniversary'] ?: null,
            'salvation_date' => $_POST['salvation_date'] ?: null,
            'baptism_date' => $_POST['baptism_date'] ?: null,
            'is_member' => isset($_POST['is_member']) ? 1 : 0,
            'membership_status_id' => $_POST['membership_status_id'] ?: null,
            'member_since' => $_POST['member_since'] ?: null,
            'household_id' => $_POST['household_id'] ?: null,
            'household_role' => $_POST['household_role'] ?: null,
            'directory_visible' => isset($_POST['directory_visible']) ? 1 : 0,
            'email_opt_out' => isset($_POST['email_opt_out']) ? 1 : 0,
            'sms_opt_out' => isset($_POST['sms_opt_out']) ? 1 : 0,
            'active' => isset($_POST['active']) ? 1 : 0,
        ];

        if ($personId) {
            // Update existing
            $result = $peopleService->updatePerson($personId, $data);
        } else {
            // Create new
            if (!empty($_POST['username'])) {
                $data['username'] = trim($_POST['username']);
            }
            if (!empty($_POST['password'])) {
                $data['password'] = $_POST['password'];
            }
            $result = $peopleService->createPerson($data);
        }

        if ($result['success']) {
            $success = $result['message'] ?? ($personId ? 'Person updated' : 'Person created');

            if (!$personId && isset($result['user_id'])) {
                // Redirect to view the new person
                header('Location: /admin/people?page=view&id=' . $result['user_id'] . '&created=1');
                exit;
            }

            // Refresh data
            if ($personId) {
                $person = $peopleService->getPerson($personId);
            }

            log_activity($_SESSION['admin_user_id'], $personId ? 'update' : 'create', 'person', $personId ?: $result['user_id'], ($personId ? 'Updated' : 'Created') . ' person: ' . $data['email']);
        } else {
            $error = $result['error'] ?? 'An error occurred';
        }
    }
}

// Get options for dropdowns
$statuses = $peopleService->getMembershipStatuses();
$households = $peopleService->getHouseholds();
$allTags = $peopleService->getTags();
?>

<?php if ($success): ?>
    <div class="admin-alert admin-alert-success"><?= htmlspecialchars($success); ?></div>
<?php endif; ?>
<?php if ($error): ?>
    <div class="admin-alert admin-alert-error"><?= htmlspecialchars($error); ?></div>
<?php endif; ?>

<form method="post" class="person-edit-form">
    <?= csrf_field(); ?>

    <!-- Header with actions -->
    <div class="form-header">
        <div class="form-header-left">
            <a href="<?= $personId ? '/admin/people?page=view&id=' . $personId : '/admin/people'; ?>" class="btn btn-outline">
                &larr; <?= $personId ? 'Back to Profile' : 'Back to List'; ?>
            </a>
        </div>
        <div class="form-header-right">
            <button type="submit" class="btn btn-primary">Save Person</button>
        </div>
    </div>

    <div class="form-grid">
        <!-- Main Column -->
        <div class="form-column form-column-main">
            <!-- Basic Information -->
            <div class="admin-card">
                <div class="admin-card-header">
                    <h3>Basic Information</h3>
                </div>

                <div class="form-row-3">
                    <div class="form-group">
                        <label>First Name *</label>
                        <input type="text" name="first_name" value="<?= htmlspecialchars($person['first_name'] ?? ''); ?>" required>
                    </div>
                    <div class="form-group">
                        <label>Middle Name</label>
                        <input type="text" name="middle_name" value="<?= htmlspecialchars($person['middle_name'] ?? ''); ?>">
                    </div>
                    <div class="form-group">
                        <label>Last Name *</label>
                        <input type="text" name="last_name" value="<?= htmlspecialchars($person['last_name'] ?? ''); ?>" required>
                    </div>
                </div>

                <div class="form-row-3">
                    <div class="form-group">
                        <label>Nickname</label>
                        <input type="text" name="nickname" value="<?= htmlspecialchars($person['nickname'] ?? ''); ?>" placeholder="Goes by...">
                    </div>
                    <div class="form-group">
                        <label>Prefix</label>
                        <select name="prefix">
                            <option value="">None</option>
                            <?php foreach (['Mr.', 'Mrs.', 'Ms.', 'Miss', 'Dr.', 'Rev.', 'Pastor'] as $p): ?>
                                <option value="<?= $p; ?>" <?= ($person['prefix'] ?? '') === $p ? 'selected' : ''; ?>><?= $p; ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Suffix</label>
                        <input type="text" name="suffix" value="<?= htmlspecialchars($person['suffix'] ?? ''); ?>" placeholder="Jr., III, etc.">
                    </div>
                </div>

                <div class="form-row-2">
                    <div class="form-group">
                        <label>Email *</label>
                        <input type="email" name="email" value="<?= htmlspecialchars($person['email'] ?? ''); ?>" required>
                    </div>
                    <?php if (!$personId): ?>
                        <div class="form-group">
                            <label>Username</label>
                            <input type="text" name="username" value="" placeholder="Auto-generated if blank">
                            <div class="form-help">Leave blank to auto-generate from name</div>
                        </div>
                    <?php else: ?>
                        <div class="form-group">
                            <label>Username</label>
                            <input type="text" value="<?= htmlspecialchars($person['username'] ?? ''); ?>" disabled>
                            <div class="form-help">Username cannot be changed</div>
                        </div>
                    <?php endif; ?>
                </div>

                <?php if (!$personId): ?>
                    <div class="form-group">
                        <label>Password</label>
                        <input type="password" name="password" placeholder="Leave blank to auto-generate">
                        <div class="form-help">A random password will be generated if left blank</div>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Personal Details -->
            <div class="admin-card">
                <div class="admin-card-header">
                    <h3>Personal Details</h3>
                </div>

                <div class="form-row-3">
                    <div class="form-group">
                        <label>Gender</label>
                        <select name="gender">
                            <option value="">Not specified</option>
                            <option value="male" <?= ($person['gender'] ?? '') === 'male' ? 'selected' : ''; ?>>Male</option>
                            <option value="female" <?= ($person['gender'] ?? '') === 'female' ? 'selected' : ''; ?>>Female</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Birthdate</label>
                        <input type="date" name="birthdate" value="<?= htmlspecialchars($person['birthdate'] ?? ''); ?>">
                    </div>
                    <div class="form-group">
                        <label>Marital Status</label>
                        <select name="marital_status">
                            <option value="">Not specified</option>
                            <?php foreach (['single', 'married', 'divorced', 'widowed', 'separated'] as $s): ?>
                                <option value="<?= $s; ?>" <?= ($person['marital_status'] ?? '') === $s ? 'selected' : ''; ?>><?= ucfirst($s); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <div class="form-group">
                    <label>Anniversary</label>
                    <input type="date" name="anniversary" value="<?= htmlspecialchars($person['anniversary'] ?? ''); ?>">
                </div>
            </div>

            <!-- Spiritual Journey -->
            <div class="admin-card">
                <div class="admin-card-header">
                    <h3>Spiritual Journey</h3>
                </div>

                <div class="form-row-2">
                    <div class="form-group">
                        <label>Salvation Date</label>
                        <input type="date" name="salvation_date" value="<?= htmlspecialchars($person['salvation_date'] ?? ''); ?>">
                    </div>
                    <div class="form-group">
                        <label>Baptism Date</label>
                        <input type="date" name="baptism_date" value="<?= htmlspecialchars($person['baptism_date'] ?? ''); ?>">
                    </div>
                </div>
            </div>

            <!-- Contact Information (for existing person) -->
            <?php if ($personId): ?>
                <div class="admin-card" id="contact-info-card">
                    <div class="admin-card-header">
                        <h3>Contact Information</h3>
                        <div class="admin-card-actions">
                            <button type="button" class="btn btn-sm btn-outline" data-open-modal="address-modal">Add Address</button>
                            <button type="button" class="btn btn-sm btn-outline" data-open-modal="phone-modal">Add Phone</button>
                        </div>
                    </div>

                    <div id="addresses-list">
                        <?php if (!empty($person['addresses'])): ?>
                            <div class="contact-list">
                                <h4>Addresses</h4>
                                <?php foreach ($person['addresses'] as $addr): ?>
                                    <div class="contact-item-row" data-address-id="<?= $addr['id']; ?>">
                                        <span class="contact-type-badge"><?= htmlspecialchars(ucfirst($addr['address_type'])); ?></span>
                                        <span class="contact-detail">
                                            <?= htmlspecialchars($addr['street_line_1']); ?>
                                            <?= $addr['street_line_2'] ? ', ' . htmlspecialchars($addr['street_line_2']) : ''; ?>,
                                            <?= htmlspecialchars($addr['city']); ?>,
                                            <?= htmlspecialchars($addr['postcode']); ?>
                                        </span>
                                        <?php if ($addr['is_primary']): ?>
                                            <span class="badge badge-small badge-success">Primary</span>
                                        <?php endif; ?>
                                        <div class="contact-actions">
                                            <button type="button" class="btn btn-xs btn-ghost" onclick="editAddress(<?= $addr['id']; ?>)">Edit</button>
                                            <button type="button" class="btn btn-xs btn-ghost btn-danger" onclick="deleteAddress(<?= $addr['id']; ?>)">Delete</button>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>

                    <div id="phones-list">
                        <?php if (!empty($person['phone_numbers'])): ?>
                            <div class="contact-list">
                                <h4>Phone Numbers</h4>
                                <?php foreach ($person['phone_numbers'] as $phone): ?>
                                    <div class="contact-item-row" data-phone-id="<?= $phone['id']; ?>">
                                        <span class="contact-type-badge"><?= htmlspecialchars(ucfirst($phone['location_type'])); ?></span>
                                        <span class="contact-detail">
                                            <?= htmlspecialchars($phone['number']); ?>
                                            <?php if ($phone['can_receive_sms']): ?>
                                                <span class="badge badge-small badge-info">SMS</span>
                                            <?php endif; ?>
                                        </span>
                                        <?php if ($phone['is_primary']): ?>
                                            <span class="badge badge-small badge-success">Primary</span>
                                        <?php endif; ?>
                                        <div class="contact-actions">
                                            <button type="button" class="btn btn-xs btn-ghost" onclick="editPhone(<?= $phone['id']; ?>)">Edit</button>
                                            <button type="button" class="btn btn-xs btn-ghost btn-danger" onclick="deletePhone(<?= $phone['id']; ?>)">Delete</button>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>

                    <?php if (empty($person['addresses']) && empty($person['phone_numbers'])): ?>
                        <p class="text-muted" id="no-contact-info">No contact information on file. Use the buttons above to add addresses and phone numbers.</p>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>

        <!-- Sidebar Column -->
        <div class="form-column form-column-sidebar">
            <!-- Membership -->
            <div class="admin-card">
                <div class="admin-card-header">
                    <h3>Membership</h3>
                </div>

                <div class="form-group">
                    <label class="checkbox-label">
                        <input type="checkbox" name="is_member" value="1" <?= ($person['is_member'] ?? 0) ? 'checked' : ''; ?>>
                        <span>Is Church Member</span>
                    </label>
                </div>

                <div class="form-group">
                    <label>Membership Status</label>
                    <select name="membership_status_id">
                        <option value="">No Status</option>
                        <?php foreach ($statuses as $status): ?>
                            <option value="<?= $status['id']; ?>"
                                    <?= ($person['membership_status_id'] ?? '') == $status['id'] ? 'selected' : ''; ?>>
                                <?= htmlspecialchars($status['name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label>Member Since</label>
                    <input type="date" name="member_since" value="<?= htmlspecialchars($person['member_since'] ?? ''); ?>">
                </div>
            </div>

            <!-- Household -->
            <div class="admin-card">
                <div class="admin-card-header">
                    <h3>Household</h3>
                </div>

                <div class="form-group">
                    <label>Household</label>
                    <select name="household_id">
                        <option value="">None</option>
                        <?php foreach ($households as $hh): ?>
                            <option value="<?= $hh['id']; ?>"
                                    <?= ($person['household_id'] ?? '') == $hh['id'] ? 'selected' : ''; ?>>
                                <?= htmlspecialchars($hh['name']); ?> (<?= $hh['member_count']; ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label>Role in Household</label>
                    <select name="household_role">
                        <option value="">Not specified</option>
                        <option value="head" <?= ($person['household_role'] ?? '') === 'head' ? 'selected' : ''; ?>>Head</option>
                        <option value="spouse" <?= ($person['household_role'] ?? '') === 'spouse' ? 'selected' : ''; ?>>Spouse</option>
                        <option value="child" <?= ($person['household_role'] ?? '') === 'child' ? 'selected' : ''; ?>>Child</option>
                        <option value="other" <?= ($person['household_role'] ?? '') === 'other' ? 'selected' : ''; ?>>Other</option>
                    </select>
                </div>

                <a href="/admin/people?page=households" class="btn btn-sm btn-outline btn-block">Manage Households</a>
            </div>

            <!-- Preferences -->
            <div class="admin-card">
                <div class="admin-card-header">
                    <h3>Preferences</h3>
                </div>

                <div class="form-group">
                    <label class="checkbox-label">
                        <input type="checkbox" name="directory_visible" value="1" <?= ($person['directory_visible'] ?? 1) ? 'checked' : ''; ?>>
                        <span>Show in church directory</span>
                    </label>
                </div>

                <div class="form-group">
                    <label class="checkbox-label">
                        <input type="checkbox" name="email_opt_out" value="1" <?= ($person['email_opt_out'] ?? 0) ? 'checked' : ''; ?>>
                        <span>Opt out of email communications</span>
                    </label>
                </div>

                <div class="form-group">
                    <label class="checkbox-label">
                        <input type="checkbox" name="sms_opt_out" value="1" <?= ($person['sms_opt_out'] ?? 0) ? 'checked' : ''; ?>>
                        <span>Opt out of SMS communications</span>
                    </label>
                </div>
            </div>

            <!-- Account Status -->
            <div class="admin-card">
                <div class="admin-card-header">
                    <h3>Account Status</h3>
                </div>

                <div class="form-group">
                    <label class="checkbox-label">
                        <input type="checkbox" name="active" value="1" <?= ($person['active'] ?? 1) ? 'checked' : ''; ?>>
                        <span>Account Active</span>
                    </label>
                    <div class="form-help">Inactive accounts cannot log in</div>
                </div>
            </div>

            <?php if ($personId): ?>
                <!-- Danger Zone -->
                <div class="admin-card card-danger">
                    <div class="admin-card-header">
                        <h3>Danger Zone</h3>
                    </div>
                    <p class="text-muted" style="font-size: 0.875rem; margin-bottom: 1rem;">
                        These actions cannot be undone.
                    </p>
                    <a href="/admin/people?action=delete&id=<?= $personId; ?>" class="btn btn-danger btn-block" data-confirm-delete>
                        Delete Person
                    </a>
                </div>
            <?php endif; ?>
        </div>
    </div>
</form>

<style>
.form-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 1.5rem;
}

.form-grid {
    display: grid;
    grid-template-columns: 1fr 350px;
    gap: 1.5rem;
}

.form-row-2 {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 1rem;
}

.form-row-3 {
    display: grid;
    grid-template-columns: 1fr 1fr 1fr;
    gap: 1rem;
}

.checkbox-label {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    cursor: pointer;
}

.checkbox-label input {
    width: auto;
}

.contact-list {
    margin-bottom: 1rem;
}

.contact-list h4 {
    font-size: 0.75rem;
    font-weight: 600;
    text-transform: uppercase;
    color: var(--color-text-muted);
    margin: 0 0 0.5rem;
}

.contact-item-row {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    padding: 0.5rem 0;
    border-bottom: 1px solid var(--color-border);
}

.contact-item-row:last-child {
    border-bottom: none;
}

.contact-type-badge {
    font-size: 0.75rem;
    font-weight: 500;
    padding: 0.125rem 0.375rem;
    background: var(--color-surface-hover);
    border-radius: var(--radius);
    min-width: 60px;
    text-align: center;
}

.contact-detail {
    flex: 1;
    font-size: 0.875rem;
}

.btn-block {
    width: 100%;
}

.card-danger {
    border-color: var(--color-danger);
}

.card-danger .admin-card-header h3 {
    color: var(--color-danger);
}

.text-muted {
    color: var(--color-text-muted);
}

.badge-small {
    font-size: 0.625rem;
    padding: 0.125rem 0.375rem;
}

.contact-actions {
    display: flex;
    gap: 0.25rem;
    margin-left: auto;
}

/* Modal Styles */
.modal-overlay {
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

.modal-overlay.active {
    display: flex;
}

.modal {
    background: var(--color-surface);
    border-radius: var(--radius-lg);
    box-shadow: var(--shadow-xl);
    width: 100%;
    max-width: 500px;
    max-height: 90vh;
    overflow-y: auto;
    margin: 1rem;
}

.modal-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 1rem 1.25rem;
    border-bottom: 1px solid var(--color-border);
}

.modal-header h3 {
    margin: 0;
    font-size: 1.125rem;
}

.modal-close {
    background: none;
    border: none;
    font-size: 1.5rem;
    cursor: pointer;
    color: var(--color-text-muted);
    line-height: 1;
    padding: 0;
}

.modal-close:hover {
    color: var(--color-text);
}

.modal-body {
    padding: 1.25rem;
}

.modal-footer {
    display: flex;
    justify-content: flex-end;
    gap: 0.5rem;
    padding: 1rem 1.25rem;
    border-top: 1px solid var(--color-border);
}

.badge-info {
    background: var(--color-info-bg, #dbeafe);
    color: var(--color-info, #1d4ed8);
}

@media (max-width: 1024px) {
    .form-grid {
        grid-template-columns: 1fr;
    }
}

@media (max-width: 640px) {
    .form-row-2,
    .form-row-3 {
        grid-template-columns: 1fr;
    }

    .form-header {
        flex-direction: column;
        gap: 1rem;
        align-items: stretch;
    }
}
</style>

<?php if ($personId): ?>
<!-- Address Modal -->
<div class="modal-overlay" id="address-modal">
    <div class="modal">
        <div class="modal-header">
            <h3 id="address-modal-title">Add Address</h3>
            <button type="button" class="modal-close" data-close-modal>&times;</button>
        </div>
        <form id="address-form">
            <div class="modal-body">
                <input type="hidden" name="address_id" id="address-id">
                <input type="hidden" name="user_id" value="<?= $personId; ?>">

                <div class="form-group">
                    <label>Address Type</label>
                    <select name="address_type" id="address-type">
                        <option value="home">Home</option>
                        <option value="work">Work</option>
                        <option value="mailing">Mailing</option>
                        <option value="other">Other</option>
                    </select>
                </div>

                <div class="form-group">
                    <label>Street Address *</label>
                    <input type="text" name="street_line_1" id="address-street1" required>
                </div>

                <div class="form-group">
                    <label>Street Address Line 2</label>
                    <input type="text" name="street_line_2" id="address-street2" placeholder="Apt, Suite, Unit, etc.">
                </div>

                <div class="form-row-2">
                    <div class="form-group">
                        <label>City *</label>
                        <input type="text" name="city" id="address-city" required>
                    </div>
                    <div class="form-group">
                        <label>County</label>
                        <input type="text" name="county" id="address-county">
                    </div>
                </div>

                <div class="form-row-2">
                    <div class="form-group">
                        <label>Postcode *</label>
                        <input type="text" name="postcode" id="address-postcode" required>
                    </div>
                    <div class="form-group">
                        <label>Country</label>
                        <input type="text" name="country" id="address-country" value="United Kingdom">
                    </div>
                </div>

                <div class="form-group">
                    <label class="checkbox-label">
                        <input type="checkbox" name="is_primary" id="address-primary" value="1">
                        <span>Set as primary address</span>
                    </label>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline" data-close-modal>Cancel</button>
                <button type="submit" class="btn btn-primary">Save Address</button>
            </div>
        </form>
    </div>
</div>

<!-- Phone Modal -->
<div class="modal-overlay" id="phone-modal">
    <div class="modal">
        <div class="modal-header">
            <h3 id="phone-modal-title">Add Phone Number</h3>
            <button type="button" class="modal-close" data-close-modal>&times;</button>
        </div>
        <form id="phone-form">
            <div class="modal-body">
                <input type="hidden" name="phone_id" id="phone-id">
                <input type="hidden" name="user_id" value="<?= $personId; ?>">

                <div class="form-group">
                    <label>Phone Type</label>
                    <select name="location_type" id="phone-type">
                        <option value="mobile">Mobile</option>
                        <option value="home">Home</option>
                        <option value="work">Work</option>
                        <option value="other">Other</option>
                    </select>
                </div>

                <div class="form-group">
                    <label>Phone Number *</label>
                    <input type="tel" name="number" id="phone-number" required placeholder="07xxx xxxxxx">
                </div>

                <div class="form-group">
                    <label class="checkbox-label">
                        <input type="checkbox" name="can_receive_sms" id="phone-sms" value="1" checked>
                        <span>Can receive SMS messages</span>
                    </label>
                </div>

                <div class="form-group">
                    <label class="checkbox-label">
                        <input type="checkbox" name="is_primary" id="phone-primary" value="1">
                        <span>Set as primary phone number</span>
                    </label>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline" data-close-modal>Cancel</button>
                <button type="submit" class="btn btn-primary">Save Phone</button>
            </div>
        </form>
    </div>
</div>

<script <?= csp_nonce(); ?>>
(function() {
    const csrfToken = '<?= generate_csrf_token(); ?>';
    const userId = <?= $personId; ?>;
    const apiUrl = '/admin/api/people.php';

    // Modal handling
    document.querySelectorAll('[data-open-modal]').forEach(btn => {
        btn.addEventListener('click', function() {
            const modalId = this.dataset.openModal;
            const modal = document.getElementById(modalId);
            if (modal) {
                // Reset form when opening for new entry
                const form = modal.querySelector('form');
                if (form) form.reset();

                // Reset hidden ID field
                if (modalId === 'address-modal') {
                    document.getElementById('address-id').value = '';
                    document.getElementById('address-modal-title').textContent = 'Add Address';
                    document.getElementById('address-country').value = 'United Kingdom';
                } else if (modalId === 'phone-modal') {
                    document.getElementById('phone-id').value = '';
                    document.getElementById('phone-modal-title').textContent = 'Add Phone Number';
                    document.getElementById('phone-sms').checked = true;
                }

                modal.classList.add('active');
            }
        });
    });

    document.querySelectorAll('[data-close-modal]').forEach(btn => {
        btn.addEventListener('click', function() {
            this.closest('.modal-overlay').classList.remove('active');
        });
    });

    // Close modal on overlay click
    document.querySelectorAll('.modal-overlay').forEach(overlay => {
        overlay.addEventListener('click', function(e) {
            if (e.target === this) {
                this.classList.remove('active');
            }
        });
    });

    // Close modal on Escape key
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            document.querySelectorAll('.modal-overlay.active').forEach(m => m.classList.remove('active'));
        }
    });

    // API helper
    async function apiCall(action, data = {}) {
        const response = await fetch(apiUrl + '?action=' + action, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrfToken
            },
            body: JSON.stringify({ ...data, csrf_token: csrfToken })
        });
        return response.json();
    }

    // Address form submission
    document.getElementById('address-form').addEventListener('submit', async function(e) {
        e.preventDefault();

        const formData = new FormData(this);
        const data = Object.fromEntries(formData.entries());
        data.is_primary = formData.has('is_primary') ? 1 : 0;

        const action = data.address_id ? 'update_address' : 'add_address';
        if (data.address_id) {
            data.id = data.address_id;
        }

        try {
            const result = await apiCall(action, data);
            if (result.success) {
                renderAddresses(result.addresses);
                document.getElementById('address-modal').classList.remove('active');
                hideNoContactMessage();
            } else {
                alert(result.error || 'Failed to save address');
            }
        } catch (err) {
            alert('An error occurred. Please try again.');
        }
    });

    // Phone form submission
    document.getElementById('phone-form').addEventListener('submit', async function(e) {
        e.preventDefault();

        const formData = new FormData(this);
        const data = Object.fromEntries(formData.entries());
        data.is_primary = formData.has('is_primary') ? 1 : 0;
        data.can_receive_sms = formData.has('can_receive_sms') ? 1 : 0;

        const action = data.phone_id ? 'update_phone' : 'add_phone';
        if (data.phone_id) {
            data.id = data.phone_id;
        }

        try {
            const result = await apiCall(action, data);
            if (result.success) {
                renderPhones(result.phones);
                document.getElementById('phone-modal').classList.remove('active');
                hideNoContactMessage();
            } else {
                alert(result.error || 'Failed to save phone number');
            }
        } catch (err) {
            alert('An error occurred. Please try again.');
        }
    });

    // Edit address
    window.editAddress = async function(id) {
        try {
            const response = await fetch(apiUrl + '?action=get_address&id=' + id);
            const result = await response.json();

            if (result.success) {
                const addr = result.data;
                document.getElementById('address-id').value = addr.id;
                document.getElementById('address-type').value = addr.address_type || 'home';
                document.getElementById('address-street1').value = addr.street_line_1 || '';
                document.getElementById('address-street2').value = addr.street_line_2 || '';
                document.getElementById('address-city').value = addr.city || '';
                document.getElementById('address-county').value = addr.county || '';
                document.getElementById('address-postcode').value = addr.postcode || '';
                document.getElementById('address-country').value = addr.country || 'United Kingdom';
                document.getElementById('address-primary').checked = !!addr.is_primary;
                document.getElementById('address-modal-title').textContent = 'Edit Address';
                document.getElementById('address-modal').classList.add('active');
            } else {
                alert(result.error || 'Failed to load address');
            }
        } catch (err) {
            alert('An error occurred. Please try again.');
        }
    };

    // Delete address
    window.deleteAddress = async function(id) {
        if (!confirm('Are you sure you want to delete this address?')) return;

        try {
            const result = await apiCall('delete_address', { id });
            if (result.success) {
                renderAddresses(result.addresses);
                checkNoContactMessage();
            } else {
                alert(result.error || 'Failed to delete address');
            }
        } catch (err) {
            alert('An error occurred. Please try again.');
        }
    };

    // Edit phone
    window.editPhone = async function(id) {
        try {
            const response = await fetch(apiUrl + '?action=get_phone&id=' + id);
            const result = await response.json();

            if (result.success) {
                const phone = result.data;
                document.getElementById('phone-id').value = phone.id;
                document.getElementById('phone-type').value = phone.location_type || 'mobile';
                document.getElementById('phone-number').value = phone.number || '';
                document.getElementById('phone-sms').checked = !!phone.can_receive_sms;
                document.getElementById('phone-primary').checked = !!phone.is_primary;
                document.getElementById('phone-modal-title').textContent = 'Edit Phone Number';
                document.getElementById('phone-modal').classList.add('active');
            } else {
                alert(result.error || 'Failed to load phone number');
            }
        } catch (err) {
            alert('An error occurred. Please try again.');
        }
    };

    // Delete phone
    window.deletePhone = async function(id) {
        if (!confirm('Are you sure you want to delete this phone number?')) return;

        try {
            const result = await apiCall('delete_phone', { id });
            if (result.success) {
                renderPhones(result.phones);
                checkNoContactMessage();
            } else {
                alert(result.error || 'Failed to delete phone number');
            }
        } catch (err) {
            alert('An error occurred. Please try again.');
        }
    };

    // Render addresses list
    function renderAddresses(addresses) {
        const container = document.getElementById('addresses-list');
        if (!addresses || addresses.length === 0) {
            container.innerHTML = '';
            return;
        }

        let html = '<div class="contact-list"><h4>Addresses</h4>';
        addresses.forEach(addr => {
            const street2 = addr.street_line_2 ? ', ' + escapeHtml(addr.street_line_2) : '';
            html += `
                <div class="contact-item-row" data-address-id="${addr.id}">
                    <span class="contact-type-badge">${escapeHtml(ucfirst(addr.address_type))}</span>
                    <span class="contact-detail">
                        ${escapeHtml(addr.street_line_1)}${street2},
                        ${escapeHtml(addr.city)},
                        ${escapeHtml(addr.postcode)}
                    </span>
                    ${addr.is_primary ? '<span class="badge badge-small badge-success">Primary</span>' : ''}
                    <div class="contact-actions">
                        <button type="button" class="btn btn-xs btn-ghost" onclick="editAddress(${addr.id})">Edit</button>
                        <button type="button" class="btn btn-xs btn-ghost btn-danger" onclick="deleteAddress(${addr.id})">Delete</button>
                    </div>
                </div>
            `;
        });
        html += '</div>';
        container.innerHTML = html;
    }

    // Render phones list
    function renderPhones(phones) {
        const container = document.getElementById('phones-list');
        if (!phones || phones.length === 0) {
            container.innerHTML = '';
            return;
        }

        let html = '<div class="contact-list"><h4>Phone Numbers</h4>';
        phones.forEach(phone => {
            const smsBadge = phone.can_receive_sms ? '<span class="badge badge-small badge-info">SMS</span>' : '';
            html += `
                <div class="contact-item-row" data-phone-id="${phone.id}">
                    <span class="contact-type-badge">${escapeHtml(ucfirst(phone.location_type))}</span>
                    <span class="contact-detail">
                        ${escapeHtml(phone.number)}
                        ${smsBadge}
                    </span>
                    ${phone.is_primary ? '<span class="badge badge-small badge-success">Primary</span>' : ''}
                    <div class="contact-actions">
                        <button type="button" class="btn btn-xs btn-ghost" onclick="editPhone(${phone.id})">Edit</button>
                        <button type="button" class="btn btn-xs btn-ghost btn-danger" onclick="deletePhone(${phone.id})">Delete</button>
                    </div>
                </div>
            `;
        });
        html += '</div>';
        container.innerHTML = html;
    }

    // Helper functions
    function escapeHtml(text) {
        if (!text) return '';
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    function ucfirst(str) {
        if (!str) return '';
        return str.charAt(0).toUpperCase() + str.slice(1);
    }

    function hideNoContactMessage() {
        const msg = document.getElementById('no-contact-info');
        if (msg) msg.style.display = 'none';
    }

    function checkNoContactMessage() {
        const addresses = document.querySelectorAll('#addresses-list .contact-item-row');
        const phones = document.querySelectorAll('#phones-list .contact-item-row');
        const msg = document.getElementById('no-contact-info');

        if (msg) {
            msg.style.display = (addresses.length === 0 && phones.length === 0) ? 'block' : 'none';
        }
    }
})();
</script>
<?php endif; ?>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
