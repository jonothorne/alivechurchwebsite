<?php
/**
 * Edit Service Details
 * Edit basic service information (date, time, type, status)
 */
$page_title = 'Edit Service';
$current_app = 'services';
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../../includes/db-config.php';

$pdo = getDbConnection();

// Get service ID
$serviceId = (int)($_GET['id'] ?? 0);

if (!$serviceId) {
    header('Location: /adminnew/services');
    exit;
}

// Fetch service
$stmt = $pdo->prepare("
    SELECT s.*, st.name as type_name, st.color as type_color
    FROM services s
    JOIN service_types st ON s.service_type_id = st.id
    WHERE s.id = ?
");
$stmt->execute([$serviceId]);
$service = $stmt->fetch();

if (!$service) {
    header('Location: /adminnew/services');
    exit;
}

// Handle form submission
$errors = [];
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $serviceTypeId = (int)($_POST['service_type_id'] ?? 0);
    $serviceDate = $_POST['service_date'] ?? '';
    $startTime = $_POST['start_time'] ?? '';
    $endTime = $_POST['end_time'] ?? null;
    $title = trim($_POST['title'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $location = trim($_POST['location'] ?? '');
    $status = $_POST['status'] ?? 'planned';

    // Validate
    if (!$serviceTypeId) {
        $errors[] = 'Service type is required';
    }
    if (!$serviceDate) {
        $errors[] = 'Service date is required';
    }
    if (!$startTime) {
        $errors[] = 'Start time is required';
    }

    // Validate status
    $validStatuses = ['planned', 'confirmed', 'completed', 'cancelled'];
    if (!in_array($status, $validStatuses)) {
        $errors[] = 'Invalid status';
    }

    if (empty($errors)) {
        try {
            $updateStmt = $pdo->prepare("
                UPDATE services SET
                    service_type_id = ?,
                    service_date = ?,
                    start_time = ?,
                    end_time = ?,
                    title = ?,
                    description = ?,
                    location = ?,
                    status = ?,
                    updated_at = NOW()
                WHERE id = ?
            ");
            $updateStmt->execute([
                $serviceTypeId,
                $serviceDate,
                $startTime,
                $endTime ?: null,
                $title ?: null,
                $description ?: null,
                $location ?: null,
                $status,
                $serviceId
            ]);

            $success = true;

            // Refresh service data
            $stmt->execute([$serviceId]);
            $service = $stmt->fetch();
        } catch (PDOException $e) {
            $errors[] = 'Failed to update service: ' . $e->getMessage();
        }
    }
}

// Get service types
$serviceTypes = $pdo->query("SELECT * FROM service_types WHERE is_active = 1 ORDER BY sort_order")->fetchAll();

$serviceDate = new DateTime($service['service_date']);
$formattedDate = $serviceDate->format('l, F j, Y');
?>

<!-- Page Header -->
<div class="admin-page-header">
    <div>
        <div class="edit-header-meta">
            <span class="service-type-badge" style="background: <?= $service['type_color']; ?>;">
                <?= htmlspecialchars($service['type_name']); ?>
            </span>
        </div>
        <h1 class="admin-page-title">Edit Service</h1>
        <p class="admin-page-subtitle"><?= $formattedDate ?></p>
    </div>
    <div class="admin-page-actions">
        <a href="/adminnew/services/plan/<?= $serviceId ?>" class="admin-btn admin-btn-secondary">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <line x1="19" y1="12" x2="5" y2="12"></line>
                <polyline points="12 19 5 12 12 5"></polyline>
            </svg>
            Back to Plan
        </a>
        <button type="button" class="admin-btn admin-btn-danger" onclick="confirmDelete()">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <polyline points="3 6 5 6 21 6"></polyline>
                <path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path>
            </svg>
            Delete Service
        </button>
    </div>
</div>

<?php if ($success): ?>
<div class="admin-alert admin-alert-success">
    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
        <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path>
        <polyline points="22 4 12 14.01 9 11.01"></polyline>
    </svg>
    Service updated successfully!
</div>
<?php endif; ?>

<?php if (!empty($errors)): ?>
<div class="admin-alert admin-alert-danger">
    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
        <circle cx="12" cy="12" r="10"></circle>
        <line x1="12" y1="8" x2="12" y2="12"></line>
        <line x1="12" y1="16" x2="12.01" y2="16"></line>
    </svg>
    <ul style="margin: 0; padding-left: 1.5rem;">
        <?php foreach ($errors as $error): ?>
        <li><?= htmlspecialchars($error) ?></li>
        <?php endforeach; ?>
    </ul>
</div>
<?php endif; ?>

<div class="edit-grid">
    <div class="edit-main">
        <form method="POST" class="admin-card">
            <div class="admin-card-header">
                <h2 class="admin-card-title">Service Details</h2>
            </div>
            <div class="admin-card-body">
                <div class="admin-form-group">
                    <label class="admin-label">Service Type</label>
                    <select name="service_type_id" class="admin-input" required>
                        <?php foreach ($serviceTypes as $type): ?>
                        <option value="<?= $type['id'] ?>" <?= $service['service_type_id'] == $type['id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($type['name']) ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="admin-form-row">
                    <div class="admin-form-group">
                        <label class="admin-label">Date</label>
                        <input type="date" name="service_date" class="admin-input"
                               value="<?= $service['service_date'] ?>" required>
                    </div>
                    <div class="admin-form-group">
                        <label class="admin-label">Start Time</label>
                        <input type="time" name="start_time" class="admin-input"
                               value="<?= date('H:i', strtotime($service['start_time'])) ?>" required>
                    </div>
                    <div class="admin-form-group">
                        <label class="admin-label">End Time (optional)</label>
                        <input type="time" name="end_time" class="admin-input"
                               value="<?= $service['end_time'] ? date('H:i', strtotime($service['end_time'])) : '' ?>">
                    </div>
                </div>

                <div class="admin-form-group">
                    <label class="admin-label">Title (optional)</label>
                    <input type="text" name="title" class="admin-input"
                           value="<?= htmlspecialchars($service['title'] ?? '') ?>"
                           placeholder="e.g., Easter Sunday, Christmas Eve">
                    <p class="admin-help-text">Leave blank to use the date as the title</p>
                </div>

                <div class="admin-form-group">
                    <label class="admin-label">Description (optional)</label>
                    <textarea name="description" class="admin-input" rows="3"
                              placeholder="Any notes or description for this service"><?= htmlspecialchars($service['description'] ?? '') ?></textarea>
                </div>

                <div class="admin-form-group">
                    <label class="admin-label">Location (optional)</label>
                    <input type="text" name="location" class="admin-input"
                           value="<?= htmlspecialchars($service['location'] ?? '') ?>"
                           placeholder="e.g., Main Auditorium">
                </div>

                <div class="admin-form-group">
                    <label class="admin-label">Status</label>
                    <div class="status-options">
                        <label class="status-option">
                            <input type="radio" name="status" value="planned" <?= $service['status'] === 'planned' ? 'checked' : '' ?>>
                            <span class="status-option-content">
                                <span class="admin-badge admin-badge-info">Planned</span>
                                <span class="status-option-desc">Service is being planned</span>
                            </span>
                        </label>
                        <label class="status-option">
                            <input type="radio" name="status" value="confirmed" <?= $service['status'] === 'confirmed' ? 'checked' : '' ?>>
                            <span class="status-option-content">
                                <span class="admin-badge admin-badge-success">Confirmed</span>
                                <span class="status-option-desc">Service is confirmed and ready</span>
                            </span>
                        </label>
                        <label class="status-option">
                            <input type="radio" name="status" value="completed" <?= $service['status'] === 'completed' ? 'checked' : '' ?>>
                            <span class="status-option-content">
                                <span class="admin-badge admin-badge-secondary">Completed</span>
                                <span class="status-option-desc">Service has been completed</span>
                            </span>
                        </label>
                        <label class="status-option">
                            <input type="radio" name="status" value="cancelled" <?= $service['status'] === 'cancelled' ? 'checked' : '' ?>>
                            <span class="status-option-content">
                                <span class="admin-badge admin-badge-danger">Cancelled</span>
                                <span class="status-option-desc">Service has been cancelled</span>
                            </span>
                        </label>
                    </div>
                </div>
            </div>
            <div class="admin-card-footer">
                <a href="/adminnew/services/plan/<?= $serviceId ?>" class="admin-btn admin-btn-secondary">Cancel</a>
                <button type="submit" class="admin-btn admin-btn-primary">Save Changes</button>
            </div>
        </form>
    </div>

    <!-- Sidebar Info -->
    <div class="edit-sidebar">
        <div class="admin-card">
            <div class="admin-card-header">
                <h3 class="admin-card-title">Service Info</h3>
            </div>
            <div class="admin-card-body">
                <div class="info-list">
                    <div class="info-item">
                        <span class="info-label">Created</span>
                        <span class="info-value"><?= date('M j, Y', strtotime($service['created_at'])) ?></span>
                    </div>
                    <div class="info-item">
                        <span class="info-label">Last Updated</span>
                        <span class="info-value"><?= date('M j, Y g:i A', strtotime($service['updated_at'])) ?></span>
                    </div>
                    <?php
                    // Get item and team counts
                    $itemCount = $pdo->prepare("SELECT COUNT(*) FROM service_items WHERE service_id = ?");
                    $itemCount->execute([$serviceId]);
                    $items = $itemCount->fetchColumn();

                    $rotaCount = $pdo->prepare("SELECT COUNT(*) FROM service_rota WHERE service_id = ?");
                    $rotaCount->execute([$serviceId]);
                    $rota = $rotaCount->fetchColumn();
                    ?>
                    <div class="info-item">
                        <span class="info-label">Service Items</span>
                        <span class="info-value"><?= $items ?></span>
                    </div>
                    <div class="info-item">
                        <span class="info-label">Team Roles</span>
                        <span class="info-value"><?= $rota ?></span>
                    </div>
                </div>
            </div>
        </div>

        <div class="admin-card">
            <div class="admin-card-header">
                <h3 class="admin-card-title">Quick Actions</h3>
            </div>
            <div class="admin-card-body" style="padding: 0;">
                <div class="quick-actions-list">
                    <a href="/adminnew/services/plan/<?= $serviceId ?>" class="quick-action-item">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path>
                            <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"></path>
                        </svg>
                        Edit Service Plan
                    </a>
                    <a href="/adminnew/services/runsheet/<?= $serviceId ?>" class="quick-action-item">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path>
                            <polyline points="14 2 14 8 20 8"></polyline>
                            <line x1="16" y1="13" x2="8" y2="13"></line>
                            <line x1="16" y1="17" x2="8" y2="17"></line>
                            <polyline points="10 9 9 9 8 9"></polyline>
                        </svg>
                        View Run Sheet
                    </a>
                    <a href="/adminnew/services/live/<?= $serviceId ?>" class="quick-action-item">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <circle cx="12" cy="12" r="10"></circle>
                            <polyline points="12 6 12 12 16 14"></polyline>
                        </svg>
                        Live Mode
                    </a>
                    <button type="button" class="quick-action-item" onclick="duplicateService()">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <rect x="9" y="9" width="13" height="13" rx="2" ry="2"></rect>
                            <path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"></path>
                        </svg>
                        Duplicate Service
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Delete Confirmation Modal -->
<div class="admin-modal" id="deleteModal">
    <div class="admin-modal-content">
        <div class="admin-modal-header">
            <h3>Delete Service</h3>
            <button type="button" class="admin-modal-close" onclick="hideDeleteModal()">&times;</button>
        </div>
        <div class="admin-modal-body">
            <p>Are you sure you want to delete this service?</p>
            <p><strong><?= htmlspecialchars($service['type_name']) ?> - <?= $formattedDate ?></strong></p>
            <p class="admin-text-danger">This will also delete all service items and team assignments. This action cannot be undone.</p>
        </div>
        <div class="admin-modal-footer">
            <button type="button" class="admin-btn admin-btn-secondary" onclick="hideDeleteModal()">Cancel</button>
            <form method="POST" action="/adminnew/services/api/plan-actions.php" style="display: inline;">
                <input type="hidden" name="action" value="delete-service">
                <input type="hidden" name="service_id" value="<?= $serviceId ?>">
                <button type="submit" class="admin-btn admin-btn-danger">Delete Service</button>
            </form>
        </div>
    </div>
</div>

<style <?= csp_nonce(); ?>>
/* Edit Page Layout */
.edit-grid {
    display: grid;
    grid-template-columns: 1fr 320px;
    gap: 1.5rem;
}

@media (max-width: 1024px) {
    .edit-grid {
        grid-template-columns: 1fr;
    }
}

.edit-header-meta {
    margin-bottom: 0.5rem;
}

/* Form Styling */
.admin-form-row {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 1rem;
}

@media (max-width: 768px) {
    .admin-form-row {
        grid-template-columns: 1fr;
    }
}

.admin-help-text {
    font-size: 0.75rem;
    color: var(--admin-text-muted);
    margin-top: 0.25rem;
}

/* Status Options */
.status-options {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 0.75rem;
}

@media (max-width: 600px) {
    .status-options {
        grid-template-columns: 1fr;
    }
}

.status-option {
    display: flex;
    cursor: pointer;
}

.status-option input {
    position: absolute;
    opacity: 0;
}

.status-option-content {
    display: flex;
    flex-direction: column;
    gap: 0.25rem;
    padding: 0.75rem 1rem;
    border: 2px solid var(--admin-border);
    border-radius: var(--admin-radius);
    width: 100%;
    transition: all 0.15s;
}

.status-option input:checked + .status-option-content {
    border-color: var(--current-app-color);
    background: color-mix(in srgb, var(--current-app-color) 5%, transparent);
}

.status-option-desc {
    font-size: 0.75rem;
    color: var(--admin-text-muted);
}

/* Info List */
.info-list {
    display: flex;
    flex-direction: column;
    gap: 0.75rem;
}

.info-item {
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.info-label {
    font-size: 0.8125rem;
    color: var(--admin-text-muted);
}

.info-value {
    font-size: 0.875rem;
    font-weight: 500;
}

/* Quick Actions */
.quick-actions-list {
    display: flex;
    flex-direction: column;
}

.quick-action-item {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    padding: 0.875rem 1rem;
    text-decoration: none;
    color: var(--admin-text);
    border: none;
    background: none;
    border-bottom: 1px solid var(--admin-border);
    font-size: 0.875rem;
    cursor: pointer;
    text-align: left;
    width: 100%;
    transition: background 0.15s;
}

.quick-action-item:last-child {
    border-bottom: none;
}

.quick-action-item:hover {
    background: var(--admin-bg);
}

.quick-action-item svg {
    color: var(--admin-text-muted);
}

/* Card Footer */
.admin-card-footer {
    display: flex;
    justify-content: flex-end;
    gap: 0.75rem;
    padding: 1rem 1.25rem;
    border-top: 1px solid var(--admin-border);
    background: var(--admin-bg);
}

/* Alert */
.admin-alert {
    display: flex;
    align-items: flex-start;
    gap: 0.75rem;
    padding: 1rem;
    border-radius: var(--admin-radius);
    margin-bottom: 1rem;
}

.admin-alert svg {
    flex-shrink: 0;
    margin-top: 0.125rem;
}

.admin-alert-success {
    background: color-mix(in srgb, var(--admin-success) 15%, var(--admin-card-bg));
    color: var(--admin-success);
}

.admin-alert-danger {
    background: color-mix(in srgb, var(--admin-danger) 15%, var(--admin-card-bg));
    color: var(--admin-danger);
}

.admin-text-danger {
    color: var(--admin-danger);
}
</style>

<script <?= csp_nonce(); ?>>
function confirmDelete() {
    document.getElementById('deleteModal').classList.add('active');
}

function hideDeleteModal() {
    document.getElementById('deleteModal').classList.remove('active');
}

async function duplicateService() {
    if (!confirm('Create a copy of this service?')) {
        return;
    }

    try {
        const response = await fetch('/adminnew/services/api/templates.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({
                action: 'duplicate',
                service_id: <?= $serviceId ?>
            })
        });

        const result = await response.json();

        if (result.success) {
            window.location.href = '/adminnew/services/edit/' + result.service_id;
        } else {
            alert(result.error || 'Failed to duplicate service');
        }
    } catch (error) {
        console.error('Error:', error);
        alert('Failed to duplicate service');
    }
}

// Close modal on escape or outside click
document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape') {
        hideDeleteModal();
    }
});

document.getElementById('deleteModal').addEventListener('click', (e) => {
    if (e.target.id === 'deleteModal') {
        hideDeleteModal();
    }
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
