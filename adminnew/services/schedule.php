<?php
/**
 * Schedule New Service
 */
require_once __DIR__ . '/../../includes/db-config.php';

$pdo = getDbConnection();

// Handle form submission BEFORE including header (to allow redirects)
$success = null;
$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $serviceTypeId = (int)$_POST['service_type_id'];
        $serviceDate = $_POST['service_date'];
        $startTime = $_POST['start_time'];
        $endTime = $_POST['end_time'] ?: null;
        $title = trim($_POST['title']);
        $description = trim($_POST['description']);
        $location = trim($_POST['location']);
        $selectedTeams = $_POST['teams'] ?? [];
        $applyTemplateId = (int)($_POST['apply_template'] ?? 0);

        // Validate
        if (!$serviceTypeId || !$serviceDate || !$startTime) {
            throw new Exception('Please fill in all required fields.');
        }

        // Insert service
        $userId = $_SESSION['user_id'] ?? null;
        $stmt = $pdo->prepare("
            INSERT INTO services (service_type_id, service_date, start_time, end_time, title, description, location, status, created_by, created_at, updated_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, 'planned', ?, NOW(), NOW())
        ");
        $stmt->execute([$serviceTypeId, $serviceDate, $startTime, $endTime, $title ?: null, $description ?: null, $location ?: null, $userId]);
        $serviceId = $pdo->lastInsertId();

        // Apply template if selected
        if ($applyTemplateId) {
            // Copy template items
            $itemsStmt = $pdo->prepare("
                SELECT item_type, song_id, title, duration_minutes, notes, position
                FROM service_template_items
                WHERE template_id = ?
                ORDER BY position
            ");
            $itemsStmt->execute([$applyTemplateId]);
            $items = $itemsStmt->fetchAll();

            $insertItemStmt = $pdo->prepare("
                INSERT INTO service_items (service_id, item_type, song_id, title, duration_minutes, notes, position, created_at, updated_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, NOW(), NOW())
            ");

            foreach ($items as $item) {
                $insertItemStmt->execute([
                    $serviceId,
                    $item['item_type'],
                    $item['song_id'],
                    $item['title'],
                    $item['duration_minutes'],
                    $item['notes'],
                    $item['position']
                ]);
            }

            // Copy template roles
            $rolesStmt = $pdo->prepare("
                SELECT role_id, quantity
                FROM service_template_roles
                WHERE template_id = ?
                ORDER BY position
            ");
            $rolesStmt->execute([$applyTemplateId]);
            $roles = $rolesStmt->fetchAll();

            $insertRotaStmt = $pdo->prepare("
                INSERT INTO service_rota (service_id, role_id, status, sort_order, created_at, updated_at)
                VALUES (?, ?, 'unassigned', ?, NOW(), NOW())
            ");

            $sortOrder = 0;
            foreach ($roles as $role) {
                for ($i = 0; $i < $role['quantity']; $i++) {
                    $insertRotaStmt->execute([$serviceId, $role['role_id'], $sortOrder++]);
                }
            }
        }

        // Add team assignments if teams selected
        if (!empty($selectedTeams)) {
            foreach ($selectedTeams as $teamId) {
                // Get all active members of this team
                $members = $pdo->prepare("
                    SELECT stm.member_id
                    FROM service_team_members stm
                    WHERE stm.team_id = ? AND stm.is_active = 1
                ");
                $members->execute([$teamId]);

                foreach ($members->fetchAll() as $member) {
                    $stmt = $pdo->prepare("
                        INSERT INTO service_assignments (service_id, team_id, member_id, status, created_at, updated_at)
                        VALUES (?, ?, ?, 'pending', NOW(), NOW())
                    ");
                    $stmt->execute([$serviceId, $teamId, $member['member_id']]);
                }
            }
        }

        $success = 'Service scheduled successfully!';

        // Redirect to planning page
        header("Location: /adminnew/services/plan/{$serviceId}");
        exit;

    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}

// Now include header (after any potential redirects)
$page_title = 'Schedule Service';
$current_app = 'services';
require_once __DIR__ . '/../includes/header.php';

// Get service types
$serviceTypes = $pdo->query("SELECT * FROM service_types WHERE is_active = 1 ORDER BY sort_order")->fetchAll();

// Get teams
$teams = $pdo->query("
    SELECT t.*,
           (SELECT COUNT(*) FROM service_team_members stm WHERE stm.team_id = t.id AND stm.is_active = 1) as member_count
    FROM service_teams t
    WHERE t.is_active = 1
    ORDER BY t.sort_order
")->fetchAll();

// Get default values from first service type
$defaultType = $serviceTypes[0] ?? null;
$defaultDate = date('Y-m-d', strtotime('next ' . ($defaultType['default_day'] ?? 'sunday')));
$defaultTime = $defaultType['default_time'] ?? '09:00:00';

// Get templates for quick creation
$templates = $pdo->query("
    SELECT st.*,
           stype.name as type_name, stype.color as type_color,
           (SELECT COUNT(*) FROM service_template_items sti WHERE sti.template_id = st.id) as item_count
    FROM service_templates st
    JOIN service_types stype ON st.service_type_id = stype.id
    WHERE st.is_active = 1
    ORDER BY st.name
")->fetchAll();

// Check if creating from template
$templateId = (int)($_GET['template_id'] ?? 0);
$selectedTemplate = null;
if ($templateId) {
    $templateStmt = $pdo->prepare("
        SELECT st.*, stype.name as type_name
        FROM service_templates st
        JOIN service_types stype ON st.service_type_id = stype.id
        WHERE st.id = ? AND st.is_active = 1
    ");
    $templateStmt->execute([$templateId]);
    $selectedTemplate = $templateStmt->fetch();
}
?>

<!-- Page Header -->
<div class="admin-page-header">
    <div>
        <h1 class="admin-page-title">Schedule Service</h1>
        <p class="admin-page-subtitle">Create a new service to plan</p>
    </div>
    <div class="admin-page-actions">
        <a href="/adminnew/services" class="admin-btn admin-btn-secondary">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <line x1="19" y1="12" x2="5" y2="12"></line>
                <polyline points="12 19 5 12 12 5"></polyline>
            </svg>
            Back to Services
        </a>
    </div>
</div>

<?php if ($error): ?>
    <div class="admin-alert admin-alert-danger"><?= htmlspecialchars($error); ?></div>
<?php endif; ?>

<form method="POST" class="schedule-form">
    <div class="schedule-grid">
        <!-- Main Form -->
        <div class="schedule-main">
            <div class="admin-card">
                <div class="admin-card-header">
                    <h2 class="admin-card-title">Service Details</h2>
                </div>
                <div class="admin-card-body">
                    <!-- Service Type -->
                    <div class="admin-form-group">
                        <label class="admin-form-label">Service Type *</label>
                        <div class="service-type-selector">
                            <?php foreach ($serviceTypes as $type): ?>
                                <label class="service-type-option">
                                    <input type="radio" name="service_type_id" value="<?= $type['id']; ?>"
                                           data-day="<?= $type['default_day']; ?>"
                                           data-time="<?= $type['default_time']; ?>"
                                           <?= $type['id'] === ($defaultType['id'] ?? 0) ? 'checked' : ''; ?>>
                                    <span class="service-type-card">
                                        <span class="service-type-indicator" style="background: <?= $type['color']; ?>;"></span>
                                        <span class="service-type-name"><?= htmlspecialchars($type['name']); ?></span>
                                        <span class="service-type-schedule">
                                            <?= ucfirst($type['default_day'] ?? 'sunday'); ?>s<?= $type['default_time'] ? ' at ' . date('g:i A', strtotime($type['default_time'])) : ''; ?>
                                        </span>
                                    </span>
                                </label>
                            <?php endforeach; ?>
                        </div>
                        <?php if (empty($serviceTypes)): ?>
                            <p class="admin-form-help text-warning">
                                No service types configured. <a href="/adminnew/services/types">Add service types</a> first.
                            </p>
                        <?php endif; ?>
                    </div>

                    <!-- Date and Time -->
                    <div class="admin-form-row">
                        <div class="admin-form-group">
                            <label class="admin-form-label" for="service_date">Date *</label>
                            <input type="date" id="service_date" name="service_date" class="admin-form-input"
                                   value="<?= $defaultDate; ?>" required>
                        </div>
                        <div class="admin-form-group">
                            <label class="admin-form-label" for="start_time">Start Time *</label>
                            <input type="time" id="start_time" name="start_time" class="admin-form-input"
                                   value="<?= date('H:i', strtotime($defaultTime)); ?>" required>
                        </div>
                        <div class="admin-form-group">
                            <label class="admin-form-label" for="end_time">End Time</label>
                            <input type="time" id="end_time" name="end_time" class="admin-form-input">
                        </div>
                    </div>

                    <!-- Title (optional) -->
                    <div class="admin-form-group">
                        <label class="admin-form-label" for="title">Service Title (optional)</label>
                        <input type="text" id="title" name="title" class="admin-form-input"
                               placeholder="e.g., Easter Sunday, Good Friday">
                        <p class="admin-form-help">Leave blank to use the date as the title</p>
                    </div>

                    <!-- Description -->
                    <div class="admin-form-group">
                        <label class="admin-form-label" for="description">Description (optional)</label>
                        <textarea id="description" name="description" class="admin-form-input" rows="3"
                                  placeholder="Notes about this service..."></textarea>
                    </div>

                    <!-- Location -->
                    <div class="admin-form-group">
                        <label class="admin-form-label" for="location">Location</label>
                        <input type="text" id="location" name="location" class="admin-form-input"
                               placeholder="Main Auditorium">
                    </div>
                </div>
            </div>
        </div>

        <!-- Teams Sidebar -->
        <div class="schedule-sidebar">
            <div class="admin-card">
                <div class="admin-card-header">
                    <h3 class="admin-card-title">Assign Teams</h3>
                </div>
                <div class="admin-card-body">
                    <p class="admin-form-help" style="margin-bottom: 1rem;">
                        Select teams to automatically request all active members.
                    </p>
                    <div class="teams-checkbox-list">
                        <?php foreach ($teams as $team): ?>
                            <label class="team-checkbox">
                                <input type="checkbox" name="teams[]" value="<?= $team['id']; ?>">
                                <span class="team-checkbox-content">
                                    <span class="team-checkbox-color" style="background: <?= $team['color']; ?>;"></span>
                                    <span class="team-checkbox-info">
                                        <span class="team-checkbox-name"><?= htmlspecialchars($team['name']); ?></span>
                                        <span class="team-checkbox-count"><?= $team['member_count']; ?> members</span>
                                    </span>
                                </span>
                            </label>
                        <?php endforeach; ?>
                        <?php if (empty($teams)): ?>
                            <p class="text-muted">No teams configured yet.</p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Templates -->
            <?php if (!empty($templates)): ?>
            <div class="admin-card">
                <div class="admin-card-header">
                    <h3 class="admin-card-title">Use Template</h3>
                </div>
                <div class="admin-card-body">
                    <p class="admin-form-help" style="margin-bottom: 1rem;">
                        Start with a pre-configured service template
                    </p>
                    <input type="hidden" name="apply_template" id="apply-template-id" value="<?= $templateId; ?>">
                    <div class="template-select-list">
                        <?php foreach ($templates as $tpl): ?>
                            <label class="template-select-option">
                                <input type="radio" name="template_select" value="<?= $tpl['id']; ?>"
                                       <?= $tpl['id'] === $templateId ? 'checked' : ''; ?>>
                                <span class="template-select-card">
                                    <span class="template-select-name"><?= htmlspecialchars($tpl['name']); ?></span>
                                    <span class="template-select-meta">
                                        <?= htmlspecialchars($tpl['type_name']); ?> • <?= $tpl['item_count']; ?> items
                                    </span>
                                </span>
                            </label>
                        <?php endforeach; ?>
                    </div>
                    <a href="/adminnew/services/templates" class="admin-btn admin-btn-sm admin-btn-secondary" style="margin-top: 0.75rem; width: 100%;">
                        Manage Templates
                    </a>
                </div>
            </div>
            <?php endif; ?>

            <!-- Quick Actions -->
            <div class="admin-card">
                <div class="admin-card-header">
                    <h3 class="admin-card-title">Quick Actions</h3>
                </div>
                <div class="admin-card-body">
                    <div class="quick-schedule-buttons">
                        <button type="button" class="quick-schedule-btn" data-offset="0">This Week</button>
                        <button type="button" class="quick-schedule-btn" data-offset="7">Next Week</button>
                        <button type="button" class="quick-schedule-btn" data-offset="14">In 2 Weeks</button>
                        <hr style="margin: 0.75rem 0; border: none; border-top: 1px solid var(--admin-border);">
                        <button type="button" class="quick-duplicate-btn" onclick="duplicateLastWeek()">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <rect x="9" y="9" width="13" height="13" rx="2" ry="2"></rect>
                                <path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"></path>
                            </svg>
                            Duplicate Last Week's Service
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Form Actions -->
    <div class="schedule-actions">
        <a href="/adminnew/services" class="admin-btn admin-btn-secondary">Cancel</a>
        <button type="submit" class="admin-btn admin-btn-primary">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect>
                <line x1="16" y1="2" x2="16" y2="6"></line>
                <line x1="8" y1="2" x2="8" y2="6"></line>
                <line x1="3" y1="10" x2="21" y2="10"></line>
            </svg>
            Schedule Service
        </button>
    </div>
</form>

<style <?= csp_nonce(); ?>>
/* Schedule Form Layout */
.schedule-grid {
    display: grid;
    grid-template-columns: 1fr 320px;
    gap: 1.5rem;
    margin-bottom: 1.5rem;
}

@media (max-width: 1024px) {
    .schedule-grid {
        grid-template-columns: 1fr;
    }
}

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

/* Service Type Selector */
.service-type-selector {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(180px, 1fr));
    gap: 0.75rem;
}

.service-type-option {
    cursor: pointer;
}

.service-type-option input {
    position: absolute;
    opacity: 0;
    pointer-events: none;
}

.service-type-card {
    display: flex;
    flex-direction: column;
    padding: 1rem;
    background: var(--admin-bg);
    border: 2px solid var(--admin-border);
    border-radius: var(--admin-radius);
    transition: all 0.15s;
}

.service-type-option input:checked + .service-type-card {
    border-color: var(--current-app-color);
    background: color-mix(in srgb, var(--current-app-color) 5%, var(--admin-bg));
}

.service-type-indicator {
    width: 8px;
    height: 8px;
    border-radius: 50%;
    margin-bottom: 0.5rem;
}

.service-type-name {
    font-weight: 600;
    color: var(--admin-text);
    margin-bottom: 0.25rem;
}

.service-type-schedule {
    font-size: 0.8rem;
    color: var(--admin-text-muted);
}

/* Teams Checkbox List */
.teams-checkbox-list {
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
}

.team-checkbox {
    cursor: pointer;
}

.team-checkbox input {
    position: absolute;
    opacity: 0;
    pointer-events: none;
}

.team-checkbox-content {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    padding: 0.75rem;
    background: var(--admin-bg);
    border: 1px solid var(--admin-border);
    border-radius: var(--admin-radius);
    transition: all 0.15s;
}

.team-checkbox input:checked + .team-checkbox-content {
    border-color: var(--current-app-color);
    background: color-mix(in srgb, var(--current-app-color) 5%, var(--admin-bg));
}

.team-checkbox-color {
    width: 12px;
    height: 12px;
    border-radius: 50%;
    flex-shrink: 0;
}

.team-checkbox-info {
    display: flex;
    flex-direction: column;
}

.team-checkbox-name {
    font-weight: 500;
    color: var(--admin-text);
}

.team-checkbox-count {
    font-size: 0.8rem;
    color: var(--admin-text-muted);
}

/* Template Select List */
.template-select-list {
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
}

.template-select-option {
    cursor: pointer;
}

.template-select-option input {
    position: absolute;
    opacity: 0;
    pointer-events: none;
}

.template-select-card {
    display: flex;
    flex-direction: column;
    padding: 0.75rem;
    background: var(--admin-bg);
    border: 2px solid var(--admin-border);
    border-radius: var(--admin-radius);
    transition: all 0.15s;
}

.template-select-option input:checked + .template-select-card {
    border-color: var(--current-app-color);
    background: color-mix(in srgb, var(--current-app-color) 5%, var(--admin-bg));
}

.template-select-card:hover {
    border-color: var(--current-app-color);
}

.template-select-name {
    font-weight: 500;
    color: var(--admin-text);
    margin-bottom: 0.25rem;
}

.template-select-meta {
    font-size: 0.75rem;
    color: var(--admin-text-muted);
}

/* Quick Schedule Buttons */
.quick-schedule-buttons {
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
}

.quick-schedule-btn,
.quick-duplicate-btn {
    padding: 0.625rem 1rem;
    background: var(--admin-bg);
    border: 1px solid var(--admin-border);
    border-radius: var(--admin-radius);
    color: var(--admin-text);
    font-size: 0.875rem;
    cursor: pointer;
    transition: all 0.15s;
    text-align: left;
}

.quick-schedule-btn:hover,
.quick-duplicate-btn:hover {
    border-color: var(--current-app-color);
    background: color-mix(in srgb, var(--current-app-color) 5%, var(--admin-bg));
}

.quick-duplicate-btn {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    font-weight: 500;
}

/* Form Actions */
.schedule-actions {
    display: flex;
    justify-content: flex-end;
    gap: 0.75rem;
    padding-top: 1rem;
    border-top: 1px solid var(--admin-border);
}
</style>

<script <?= csp_nonce(); ?>>
// Service type selection updates date/time
document.querySelectorAll('input[name="service_type_id"]').forEach(radio => {
    radio.addEventListener('change', function() {
        const day = this.dataset.day;
        const time = this.dataset.time;

        // Update time
        if (time) {
            document.getElementById('start_time').value = time.substring(0, 5);
        }

        // Update date to next occurrence of this day
        if (day) {
            const days = ['sunday', 'monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday'];
            const targetDay = days.indexOf(day.toLowerCase());
            const today = new Date();
            const currentDay = today.getDay();
            let daysUntil = targetDay - currentDay;
            if (daysUntil <= 0) daysUntil += 7;

            const nextDate = new Date(today);
            nextDate.setDate(today.getDate() + daysUntil);

            document.getElementById('service_date').value = nextDate.toISOString().split('T')[0];
        }
    });
});

// Quick schedule buttons
document.querySelectorAll('.quick-schedule-btn').forEach(btn => {
    btn.addEventListener('click', function() {
        const offset = parseInt(this.dataset.offset);
        const selectedType = document.querySelector('input[name="service_type_id"]:checked');
        const day = selectedType ? selectedType.dataset.day : 'sunday';

        const days = ['sunday', 'monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday'];
        const targetDay = days.indexOf(day.toLowerCase());
        const today = new Date();
        const currentDay = today.getDay();
        let daysUntil = targetDay - currentDay;
        if (daysUntil <= 0) daysUntil += 7;

        const nextDate = new Date(today);
        nextDate.setDate(today.getDate() + daysUntil + offset);

        document.getElementById('service_date').value = nextDate.toISOString().split('T')[0];
    });
});

// Template selection
document.querySelectorAll('input[name="template_select"]').forEach(radio => {
    radio.addEventListener('change', function() {
        document.getElementById('apply-template-id').value = this.value;
    });
});

// Duplicate last week's service
function duplicateLastWeek() {
    const serviceTypeId = document.querySelector('input[name="service_type_id"]:checked')?.value;
    const serviceDate = document.getElementById('service_date').value;
    const startTime = document.getElementById('start_time').value;

    if (!serviceTypeId) {
        alert('Please select a service type first');
        return;
    }

    if (!serviceDate || !startTime) {
        alert('Please select a date and time first');
        return;
    }

    if (!confirm('This will create a new service based on the most recent service of this type. Continue?')) {
        return;
    }

    // Show loading state
    const btn = event.target.closest('button');
    const originalText = btn.innerHTML;
    btn.disabled = true;
    btn.innerHTML = '<span style="opacity: 0.6;">Creating...</span>';

    fetch('/adminnew/services/api/templates', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
            action: 'duplicate-last',
            service_type_id: serviceTypeId,
            service_date: serviceDate,
            start_time: startTime
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            // Redirect to the new service
            window.location.href = data.redirect_url;
        } else {
            alert(data.error || 'Failed to duplicate service');
            btn.disabled = false;
            btn.innerHTML = originalText;
        }
    })
    .catch(() => {
        alert('Error duplicating service');
        btn.disabled = false;
        btn.innerHTML = originalText;
    });
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
