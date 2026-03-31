<?php
/**
 * Service Types Management
 * Configure different types of services (Sunday AM, Wednesday, etc.)
 */
$page_title = 'Service Types';
$current_app = 'services';
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../../includes/db-config.php';

$pdo = getDbConnection();

// Handle form submissions
$success = null;
$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    try {
        if ($action === 'create_type') {
            $name = trim($_POST['name']);
            $description = trim($_POST['description']);
            $color = $_POST['color'] ?? '#6366f1';
            $defaultDay = $_POST['default_day'];
            $defaultTime = $_POST['default_time'];

            if (empty($name)) {
                throw new Exception('Service type name is required.');
            }

            $maxSort = $pdo->query("SELECT MAX(sort_order) FROM service_types")->fetchColumn() ?? 0;

            $stmt = $pdo->prepare("
                INSERT INTO service_types (name, description, color, default_day, default_time, sort_order, is_active, created_at, updated_at)
                VALUES (?, ?, ?, ?, ?, ?, 1, NOW(), NOW())
            ");
            $stmt->execute([$name, $description ?: null, $color, $defaultDay, $defaultTime, $maxSort + 1]);
            $success = 'Service type created!';

        } elseif ($action === 'update_type') {
            $typeId = (int)$_POST['type_id'];
            $name = trim($_POST['name']);
            $description = trim($_POST['description']);
            $color = $_POST['color'] ?? '#6366f1';
            $defaultDay = $_POST['default_day'];
            $defaultTime = $_POST['default_time'];

            $stmt = $pdo->prepare("
                UPDATE service_types SET name = ?, description = ?, color = ?, default_day = ?, default_time = ?, updated_at = NOW()
                WHERE id = ?
            ");
            $stmt->execute([$name, $description ?: null, $color, $defaultDay, $defaultTime, $typeId]);
            $success = 'Service type updated!';

        } elseif ($action === 'delete_type') {
            $typeId = (int)$_POST['type_id'];

            // Check if any services use this type
            $usageCount = $pdo->prepare("SELECT COUNT(*) FROM services WHERE service_type_id = ?");
            $usageCount->execute([$typeId]);
            if ($usageCount->fetchColumn() > 0) {
                throw new Exception('Cannot delete this service type because it has services scheduled. Deactivate it instead.');
            }

            $stmt = $pdo->prepare("DELETE FROM service_types WHERE id = ?");
            $stmt->execute([$typeId]);
            $success = 'Service type deleted.';

        } elseif ($action === 'toggle_active') {
            $typeId = (int)$_POST['type_id'];
            $stmt = $pdo->prepare("UPDATE service_types SET is_active = NOT is_active, updated_at = NOW() WHERE id = ?");
            $stmt->execute([$typeId]);
            $success = 'Service type status updated!';
        }
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}

// Fetch all service types with usage counts
$types = $pdo->query("
    SELECT st.*,
           (SELECT COUNT(*) FROM services s WHERE s.service_type_id = st.id) as service_count
    FROM service_types st
    ORDER BY st.sort_order
")->fetchAll();

// Days of week
$daysOfWeek = ['sunday', 'monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday'];

// Predefined colors
$typeColors = [
    '#6366f1', '#8b5cf6', '#ec4899', '#f43f5e',
    '#f97316', '#eab308', '#22c55e', '#14b8a6',
    '#06b6d4', '#3b82f6', '#64748b', '#71717a'
];
?>

<!-- Page Header -->
<div class="admin-page-header">
    <div>
        <h1 class="admin-page-title">Service Types</h1>
        <p class="admin-page-subtitle">Configure different types of services</p>
    </div>
    <div class="admin-page-actions">
        <a href="/adminnew/services" class="admin-btn admin-btn-secondary">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <line x1="19" y1="12" x2="5" y2="12"></line>
                <polyline points="12 19 5 12 12 5"></polyline>
            </svg>
            Back to Services
        </a>
        <button type="button" class="admin-btn admin-btn-primary" onclick="showCreateTypeModal()">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <line x1="12" y1="5" x2="12" y2="19"></line>
                <line x1="5" y1="12" x2="19" y2="12"></line>
            </svg>
            New Service Type
        </button>
    </div>
</div>

<?php if ($success): ?>
    <div class="admin-alert admin-alert-success"><?= htmlspecialchars($success); ?></div>
<?php endif; ?>

<?php if ($error): ?>
    <div class="admin-alert admin-alert-danger"><?= htmlspecialchars($error); ?></div>
<?php endif; ?>

<!-- Service Types List -->
<div class="admin-card">
    <div class="admin-card-body" style="padding: 0;">
        <?php if (empty($types)): ?>
            <div class="admin-empty-state">
                <div class="admin-empty-icon">
                    <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                        <circle cx="12" cy="12" r="3"></circle>
                        <path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1 0 2.83 2 2 0 0 1-2.83 0l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-2 2 2 2 0 0 1-2-2v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83 0 2 2 0 0 1 0-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1-2-2 2 2 0 0 1 2-2h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 0-2.83 2 2 0 0 1 2.83 0l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 2-2 2 2 0 0 1 2 2v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 0 2 2 0 0 1 0 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 2 2 2 2 0 0 1-2 2h-.09a1.65 1.65 0 0 0-1.51 1z"></path>
                    </svg>
                </div>
                <h3 class="admin-empty-title">No service types configured</h3>
                <p class="admin-empty-text">Create your first service type to start scheduling services.</p>
                <button type="button" class="admin-btn admin-btn-primary" onclick="showCreateTypeModal()">Create First Type</button>
            </div>
        <?php else: ?>
            <div class="service-types-grid">
                <?php foreach ($types as $type): ?>
                    <div class="service-type-card <?= !$type['is_active'] ? 'inactive' : ''; ?>">
                        <div class="service-type-header">
                            <span class="service-type-color" style="background: <?= $type['color']; ?>;"></span>
                            <div class="service-type-info">
                                <h3 class="service-type-name"><?= htmlspecialchars($type['name']); ?></h3>
                                <?php if ($type['description']): ?>
                                    <p class="service-type-desc"><?= htmlspecialchars($type['description']); ?></p>
                                <?php endif; ?>
                            </div>
                            <?php if (!$type['is_active']): ?>
                                <span class="admin-badge admin-badge-secondary">Inactive</span>
                            <?php endif; ?>
                        </div>
                        <div class="service-type-details">
                            <div class="service-type-detail">
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect>
                                    <line x1="16" y1="2" x2="16" y2="6"></line>
                                    <line x1="8" y1="2" x2="8" y2="6"></line>
                                    <line x1="3" y1="10" x2="21" y2="10"></line>
                                </svg>
                                <span><?= ucfirst($type['default_day']); ?>s</span>
                            </div>
                            <div class="service-type-detail">
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <circle cx="12" cy="12" r="10"></circle>
                                    <polyline points="12 6 12 12 16 14"></polyline>
                                </svg>
                                <span><?= date('g:i A', strtotime($type['default_time'])); ?></span>
                            </div>
                            <div class="service-type-detail">
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <line x1="8" y1="6" x2="21" y2="6"></line>
                                    <line x1="8" y1="12" x2="21" y2="12"></line>
                                    <line x1="8" y1="18" x2="21" y2="18"></line>
                                    <line x1="3" y1="6" x2="3.01" y2="6"></line>
                                    <line x1="3" y1="12" x2="3.01" y2="12"></line>
                                    <line x1="3" y1="18" x2="3.01" y2="18"></line>
                                </svg>
                                <span><?= $type['service_count']; ?> services</span>
                            </div>
                        </div>
                        <div class="service-type-actions">
                            <button type="button" class="admin-btn admin-btn-sm admin-btn-secondary"
                                    onclick="showEditTypeModal(<?= htmlspecialchars(json_encode($type)); ?>)">
                                Edit
                            </button>
                            <form method="POST" style="display: inline;">
                                <input type="hidden" name="action" value="toggle_active">
                                <input type="hidden" name="type_id" value="<?= $type['id']; ?>">
                                <button type="submit" class="admin-btn admin-btn-sm admin-btn-secondary">
                                    <?= $type['is_active'] ? 'Deactivate' : 'Activate'; ?>
                                </button>
                            </form>
                            <?php if ($type['service_count'] == 0): ?>
                                <form method="POST" style="display: inline;"
                                      onsubmit="return confirm('Delete this service type?');">
                                    <input type="hidden" name="action" value="delete_type">
                                    <input type="hidden" name="type_id" value="<?= $type['id']; ?>">
                                    <button type="submit" class="admin-btn admin-btn-sm admin-btn-danger">
                                        Delete
                                    </button>
                                </form>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Create/Edit Type Modal -->
<div class="admin-modal" id="type-modal">
    <div class="admin-modal-backdrop" onclick="hideTypeModal()"></div>
    <div class="admin-modal-content">
        <div class="admin-modal-header">
            <h3 class="admin-modal-title" id="type-modal-title">Create Service Type</h3>
            <button type="button" class="admin-modal-close" onclick="hideTypeModal()">&times;</button>
        </div>
        <form method="POST" id="type-form">
            <input type="hidden" name="action" id="type-action" value="create_type">
            <input type="hidden" name="type_id" id="type-id" value="">
            <div class="admin-modal-body">
                <div class="admin-form-group">
                    <label class="admin-form-label">Name *</label>
                    <input type="text" name="name" id="type-name" class="admin-form-input" required
                           placeholder="e.g., Sunday Morning, Wednesday Night">
                </div>
                <div class="admin-form-group">
                    <label class="admin-form-label">Description</label>
                    <textarea name="description" id="type-description" class="admin-form-input" rows="2"
                              placeholder="Brief description..."></textarea>
                </div>
                <div class="admin-form-row">
                    <div class="admin-form-group">
                        <label class="admin-form-label">Default Day</label>
                        <select name="default_day" id="type-day" class="admin-form-input">
                            <?php foreach ($daysOfWeek as $day): ?>
                                <option value="<?= $day; ?>"><?= ucfirst($day); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="admin-form-group">
                        <label class="admin-form-label">Default Time</label>
                        <input type="time" name="default_time" id="type-time" class="admin-form-input" value="09:00">
                    </div>
                </div>
                <div class="admin-form-group">
                    <label class="admin-form-label">Color</label>
                    <div class="color-picker">
                        <?php foreach ($typeColors as $color): ?>
                            <label class="color-option">
                                <input type="radio" name="color" value="<?= $color; ?>" <?= $color === '#6366f1' ? 'checked' : ''; ?>>
                                <span class="color-swatch" style="background: <?= $color; ?>;"></span>
                            </label>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
            <div class="admin-modal-footer">
                <button type="button" class="admin-btn admin-btn-secondary" onclick="hideTypeModal()">Cancel</button>
                <button type="submit" class="admin-btn admin-btn-primary" id="type-submit">Create Type</button>
            </div>
        </form>
    </div>
</div>

<style <?= csp_nonce(); ?>>
/* Service Types Grid */
.service-types-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
    gap: 1rem;
    padding: 1rem;
}

.service-type-card {
    background: var(--admin-bg);
    border: 1px solid var(--admin-border);
    border-radius: var(--admin-radius-lg);
    padding: 1.25rem;
}

.service-type-card.inactive {
    opacity: 0.6;
}

.service-type-header {
    display: flex;
    gap: 0.75rem;
    margin-bottom: 1rem;
}

.service-type-color {
    width: 8px;
    border-radius: 4px;
    flex-shrink: 0;
}

.service-type-info {
    flex: 1;
}

.service-type-name {
    font-size: 1rem;
    font-weight: 600;
    color: var(--admin-text);
    margin: 0 0 0.25rem 0;
}

.service-type-desc {
    font-size: 0.8rem;
    color: var(--admin-text-muted);
    margin: 0;
}

.service-type-details {
    display: flex;
    flex-wrap: wrap;
    gap: 1rem;
    margin-bottom: 1rem;
    padding: 0.75rem;
    background: var(--admin-card-bg);
    border-radius: var(--admin-radius);
}

.service-type-detail {
    display: flex;
    align-items: center;
    gap: 0.375rem;
    font-size: 0.8rem;
    color: var(--admin-text-muted);
}

.service-type-detail svg {
    color: var(--admin-text-muted);
    opacity: 0.7;
}

.service-type-actions {
    display: flex;
    gap: 0.5rem;
    flex-wrap: wrap;
}

/* Form Row */
.admin-form-row {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 1rem;
}

/* Color Picker */
.color-picker {
    display: flex;
    flex-wrap: wrap;
    gap: 0.5rem;
}

.color-option {
    cursor: pointer;
}

.color-option input {
    position: absolute;
    opacity: 0;
    pointer-events: none;
}

.color-swatch {
    display: block;
    width: 32px;
    height: 32px;
    border-radius: var(--admin-radius);
    border: 2px solid transparent;
    transition: all 0.15s;
}

.color-option input:checked + .color-swatch {
    border-color: white;
    box-shadow: 0 0 0 2px var(--current-app-color);
}

/* Modal Styles */
.admin-modal {
    position: fixed;
    inset: 0;
    z-index: 1000;
    display: none;
    align-items: center;
    justify-content: center;
}

.admin-modal.active {
    display: flex;
}

.admin-modal-backdrop {
    position: absolute;
    inset: 0;
    background: rgba(0, 0, 0, 0.5);
}

.admin-modal-content {
    position: relative;
    width: 100%;
    max-width: 500px;
    background: var(--admin-card-bg);
    border-radius: var(--admin-radius-lg);
    box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25);
}

.admin-modal-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 1rem 1.5rem;
    border-bottom: 1px solid var(--admin-border);
}

.admin-modal-title {
    font-size: 1.125rem;
    font-weight: 600;
    color: var(--admin-text);
}

.admin-modal-close {
    width: 32px;
    height: 32px;
    display: flex;
    align-items: center;
    justify-content: center;
    background: transparent;
    border: none;
    border-radius: var(--admin-radius);
    font-size: 1.5rem;
    color: var(--admin-text-muted);
    cursor: pointer;
}

.admin-modal-close:hover {
    background: var(--admin-bg);
}

.admin-modal-body {
    padding: 1.5rem;
}

.admin-modal-footer {
    display: flex;
    justify-content: flex-end;
    gap: 0.75rem;
    padding: 1rem 1.5rem;
    border-top: 1px solid var(--admin-border);
}

.admin-btn-danger {
    background: #ef4444;
    color: white;
}

.admin-btn-danger:hover {
    background: #dc2626;
}
</style>

<script <?= csp_nonce(); ?>>
function showCreateTypeModal() {
    document.getElementById('type-modal-title').textContent = 'Create Service Type';
    document.getElementById('type-action').value = 'create_type';
    document.getElementById('type-id').value = '';
    document.getElementById('type-form').reset();
    document.getElementById('type-submit').textContent = 'Create Type';
    document.querySelector('input[name="color"][value="#6366f1"]').checked = true;
    document.getElementById('type-modal').classList.add('active');
}

function showEditTypeModal(type) {
    document.getElementById('type-modal-title').textContent = 'Edit Service Type';
    document.getElementById('type-action').value = 'update_type';
    document.getElementById('type-id').value = type.id;
    document.getElementById('type-name').value = type.name || '';
    document.getElementById('type-description').value = type.description || '';
    document.getElementById('type-day').value = type.default_day || 'sunday';
    document.getElementById('type-time').value = type.default_time ? type.default_time.substring(0, 5) : '09:00';
    document.getElementById('type-submit').textContent = 'Save Changes';

    // Set color
    const colorInput = document.querySelector('input[name="color"][value="' + type.color + '"]');
    if (colorInput) colorInput.checked = true;

    document.getElementById('type-modal').classList.add('active');
}

function hideTypeModal() {
    document.getElementById('type-modal').classList.remove('active');
}

// Close modal on escape key
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        hideTypeModal();
    }
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
