<?php
/**
 * Member Availability Management
 * Members can mark dates they're unavailable to serve
 */
$page_title = 'My Availability';
$current_app = 'services';
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../../includes/db-config.php';

$pdo = getDbConnection();

// Get logged-in user's member ID
// For now, we'll use a GET parameter, but in production this would come from session
$memberId = (int)($_GET['member_id'] ?? $_SESSION['member_id'] ?? 0);

if (!$memberId) {
    echo '<div class="admin-alert admin-alert-danger">Member ID not found. Please log in.</div>';
    require_once __DIR__ . '/../includes/footer.php';
    exit;
}

// Get member details (from users table)
$memberStmt = $pdo->prepare("
    SELECT id, first_name, last_name, email
    FROM users
    WHERE id = ? AND active = 1
");
$memberStmt->execute([$memberId]);
$member = $memberStmt->fetch();

if (!$member) {
    echo '<div class="admin-alert admin-alert-danger">Member not found.</div>';
    require_once __DIR__ . '/../includes/footer.php';
    exit;
}

// Get member's teams and roles
$teamsStmt = $pdo->prepare("
    SELECT DISTINCT t.name as team_name, t.color
    FROM service_team_members stm
    JOIN service_teams t ON stm.team_id = t.id
    WHERE stm.member_id = ? AND stm.is_active = 1
");
$teamsStmt->execute([$memberId]);
$teams = $teamsStmt->fetchAll();

$rolesStmt = $pdo->prepare("
    SELECT r.name as role_name, t.name as team_name
    FROM member_role_capabilities mrc
    JOIN service_roles r ON mrc.role_id = r.id
    JOIN service_teams t ON r.team_id = t.id
    WHERE mrc.member_id = ? AND mrc.is_active = 1
    ORDER BY t.sort_order, r.sort_order
");
$rolesStmt->execute([$memberId]);
$roles = $rolesStmt->fetchAll();

// Get upcoming assignments
$assignmentsStmt = $pdo->prepare("
    SELECT sr.*,
           s.service_date, s.start_time, s.title,
           st.name as service_type_name, st.color as service_type_color,
           r.name as role_name
    FROM service_rota sr
    JOIN services s ON sr.service_id = s.id
    JOIN service_types st ON s.service_type_id = st.id
    JOIN service_roles r ON sr.role_id = r.id
    WHERE sr.member_id = ?
    AND s.service_date >= CURDATE()
    ORDER BY s.service_date, s.start_time
    LIMIT 10
");
$assignmentsStmt->execute([$memberId]);
$assignments = $assignmentsStmt->fetchAll();

// Get blackout dates (next 6 months)
$startDate = date('Y-m-d');
$endDate = date('Y-m-d', strtotime('+6 months'));

$blackoutsStmt = $pdo->prepare("
    SELECT *
    FROM member_availability
    WHERE member_id = ?
    AND unavailable_date BETWEEN ? AND ?
    ORDER BY unavailable_date
");
$blackoutsStmt->execute([$memberId, $startDate, $endDate]);
$blackouts = $blackoutsStmt->fetchAll();

// Convert blackouts to calendar format (YYYY-MM-DD array)
$blackoutDates = array_column($blackouts, 'unavailable_date');
?>

<!-- Page Header -->
<div class="admin-page-header">
    <div>
        <h1 class="admin-page-title">My Availability</h1>
        <p class="admin-page-subtitle">
            <?= htmlspecialchars($member['first_name'] . ' ' . $member['last_name']); ?>
        </p>
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

<div class="availability-layout">
    <!-- Main Calendar Area -->
    <div class="availability-main">
        <div class="admin-card">
            <div class="admin-card-header">
                <h2 class="admin-card-title">Mark Unavailable Dates</h2>
                <button type="button" class="admin-btn admin-btn-sm admin-btn-primary" onclick="showAddDateModal()">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <line x1="12" y1="5" x2="12" y2="19"></line>
                        <line x1="5" y1="12" x2="19" y2="12"></line>
                    </svg>
                    Add Date(s)
                </button>
            </div>
            <div class="admin-card-body">
                <p class="availability-help">
                    Click "Add Date(s)" to mark dates when you cannot serve. You can add single dates or a range.
                </p>
                <div id="availability-calendar" class="availability-calendar">
                    <!-- Calendar will be rendered by JavaScript -->
                </div>
            </div>
        </div>

        <!-- Unavailable Dates List -->
        <div class="admin-card" style="margin-top: 1.5rem;">
            <div class="admin-card-header">
                <h3 class="admin-card-title">My Unavailable Dates</h3>
            </div>
            <div class="admin-card-body" style="padding: 0;">
                <div id="blackouts-list">
                    <?php if (empty($blackouts)): ?>
                        <div class="admin-empty-state" style="padding: 2rem;">
                            <p class="text-muted">No blackout dates marked.</p>
                        </div>
                    <?php else: ?>
                        <table class="admin-table">
                            <thead>
                                <tr>
                                    <th>Date</th>
                                    <th>Reason</th>
                                    <th></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($blackouts as $blackout): ?>
                                    <tr data-id="<?= $blackout['id']; ?>">
                                        <td>
                                            <?php
                                            $date = new DateTime($blackout['unavailable_date']);
                                            echo $date->format('l, F j, Y');
                                            ?>
                                            <?php if ($blackout['is_recurring']): ?>
                                                <span class="admin-badge admin-badge-info">Recurring</span>
                                            <?php endif; ?>
                                        </td>
                                        <td><?= htmlspecialchars($blackout['reason'] ?: '—'); ?></td>
                                        <td class="text-right">
                                            <button type="button" class="admin-btn-icon text-danger"
                                                    onclick="removeBlackout(<?= $blackout['id']; ?>)" title="Remove">
                                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                                    <line x1="18" y1="6" x2="6" y2="18"></line>
                                                    <line x1="6" y1="6" x2="18" y2="18"></line>
                                                </svg>
                                            </button>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Sidebar -->
    <div class="availability-sidebar">
        <!-- My Teams -->
        <div class="admin-card">
            <div class="admin-card-header">
                <h3 class="admin-card-title">My Teams</h3>
            </div>
            <div class="admin-card-body">
                <?php if (empty($teams)): ?>
                    <p class="text-muted">Not assigned to any teams yet.</p>
                <?php else: ?>
                    <div class="teams-list">
                        <?php foreach ($teams as $team): ?>
                            <div class="team-badge">
                                <span class="team-badge-color" style="background: <?= $team['color']; ?>;"></span>
                                <span class="team-badge-name"><?= htmlspecialchars($team['team_name']); ?></span>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- My Roles -->
        <div class="admin-card">
            <div class="admin-card-header">
                <h3 class="admin-card-title">My Roles</h3>
            </div>
            <div class="admin-card-body">
                <?php if (empty($roles)): ?>
                    <p class="text-muted">No roles assigned yet.</p>
                <?php else: ?>
                    <div class="roles-list">
                        <?php foreach ($roles as $role): ?>
                            <div class="role-item">
                                <span class="role-item-name"><?= htmlspecialchars($role['role_name']); ?></span>
                                <span class="role-item-team"><?= htmlspecialchars($role['team_name']); ?></span>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Upcoming Assignments -->
        <div class="admin-card">
            <div class="admin-card-header">
                <h3 class="admin-card-title">Upcoming Assignments</h3>
            </div>
            <div class="admin-card-body" style="padding: 0;">
                <?php if (empty($assignments)): ?>
                    <div class="admin-empty-state" style="padding: 1.5rem;">
                        <p class="text-muted">No upcoming assignments.</p>
                    </div>
                <?php else: ?>
                    <div class="assignments-list">
                        <?php foreach ($assignments as $assignment): ?>
                            <?php
                            $serviceDate = new DateTime($assignment['service_date']);
                            $statusClass = [
                                'pending' => 'warning',
                                'confirmed' => 'success',
                                'declined' => 'danger'
                            ][$assignment['status']] ?? 'secondary';
                            ?>
                            <div class="assignment-item">
                                <div class="assignment-date">
                                    <div class="assignment-day"><?= $serviceDate->format('d'); ?></div>
                                    <div class="assignment-month"><?= $serviceDate->format('M'); ?></div>
                                </div>
                                <div class="assignment-details">
                                    <div class="assignment-service">
                                        <span class="service-type-dot" style="background: <?= $assignment['service_type_color']; ?>;"></span>
                                        <?= htmlspecialchars($assignment['title'] ?: $assignment['service_type_name']); ?>
                                    </div>
                                    <div class="assignment-role"><?= htmlspecialchars($assignment['role_name']); ?></div>
                                </div>
                                <span class="admin-badge admin-badge-<?= $statusClass; ?>">
                                    <?= ucfirst($assignment['status']); ?>
                                </span>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Add Date Modal -->
<div class="admin-modal" id="add-date-modal">
    <div class="admin-modal-backdrop" onclick="hideAddDateModal()"></div>
    <div class="admin-modal-content">
        <div class="admin-modal-header">
            <h3 class="admin-modal-title">Add Unavailable Date(s)</h3>
            <button type="button" class="admin-modal-close" onclick="hideAddDateModal()">&times;</button>
        </div>
        <div class="admin-modal-body">
            <div class="admin-form-group">
                <label class="admin-form-label">Date Type</label>
                <div class="date-type-selector">
                    <label class="date-type-option">
                        <input type="radio" name="date_type" value="single" checked onchange="toggleDateType()">
                        <span>Single Date</span>
                    </label>
                    <label class="date-type-option">
                        <input type="radio" name="date_type" value="range" onchange="toggleDateType()">
                        <span>Date Range</span>
                    </label>
                </div>
            </div>

            <div id="single-date-fields">
                <div class="admin-form-group">
                    <label class="admin-form-label" for="single_date">Date</label>
                    <input type="date" id="single_date" class="admin-form-input"
                           min="<?= date('Y-m-d'); ?>">
                </div>
            </div>

            <div id="range-date-fields" style="display: none;">
                <div class="admin-form-group">
                    <label class="admin-form-label" for="start_date">Start Date</label>
                    <input type="date" id="start_date" class="admin-form-input"
                           min="<?= date('Y-m-d'); ?>">
                </div>
                <div class="admin-form-group">
                    <label class="admin-form-label" for="end_date">End Date</label>
                    <input type="date" id="end_date" class="admin-form-input"
                           min="<?= date('Y-m-d'); ?>">
                </div>
            </div>

            <div class="admin-form-group">
                <label class="admin-form-label" for="reason">Reason (optional)</label>
                <input type="text" id="reason" class="admin-form-input"
                       placeholder="e.g., Vacation, Out of town">
            </div>
        </div>
        <div class="admin-modal-footer">
            <button type="button" class="admin-btn admin-btn-secondary" onclick="hideAddDateModal()">Cancel</button>
            <button type="button" class="admin-btn admin-btn-primary" onclick="saveBlackout()">Save</button>
        </div>
    </div>
</div>

<style <?= csp_nonce(); ?>>
.availability-layout {
    display: grid;
    grid-template-columns: 1fr 360px;
    gap: 1.5rem;
}

@media (max-width: 1200px) {
    .availability-layout {
        grid-template-columns: 1fr;
    }
}

.availability-help {
    background: var(--admin-bg);
    padding: 1rem;
    border-radius: var(--admin-radius);
    margin-bottom: 1.5rem;
    font-size: 0.875rem;
    color: var(--admin-text-muted);
}

.availability-calendar {
    /* Calendar styles will go here */
    min-height: 400px;
}

.teams-list {
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
}

.team-badge {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    padding: 0.625rem;
    background: var(--admin-bg);
    border-radius: var(--admin-radius);
}

.team-badge-color {
    width: 12px;
    height: 12px;
    border-radius: 50%;
    flex-shrink: 0;
}

.team-badge-name {
    font-weight: 500;
    color: var(--admin-text);
}

.roles-list {
    display: flex;
    flex-direction: column;
    gap: 0.375rem;
}

.role-item {
    display: flex;
    flex-direction: column;
    padding: 0.5rem;
    background: var(--admin-bg);
    border-radius: var(--admin-radius);
}

.role-item-name {
    font-weight: 500;
    font-size: 0.875rem;
    color: var(--admin-text);
}

.role-item-team {
    font-size: 0.75rem;
    color: var(--admin-text-muted);
}

.assignments-list {
    display: flex;
    flex-direction: column;
}

.assignment-item {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    padding: 0.875rem 1rem;
    border-bottom: 1px solid var(--admin-border);
}

.assignment-item:last-child {
    border-bottom: none;
}

.assignment-date {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    width: 48px;
    height: 48px;
    background: var(--admin-bg);
    border-radius: var(--admin-radius);
    flex-shrink: 0;
}

.assignment-day {
    font-size: 1.125rem;
    font-weight: 700;
    color: var(--admin-text);
    line-height: 1;
}

.assignment-month {
    font-size: 0.625rem;
    font-weight: 600;
    text-transform: uppercase;
    color: var(--admin-text-muted);
    letter-spacing: 0.05em;
}

.assignment-details {
    flex: 1;
    min-width: 0;
}

.assignment-service {
    display: flex;
    align-items: center;
    gap: 0.375rem;
    font-weight: 500;
    font-size: 0.875rem;
    color: var(--admin-text);
    margin-bottom: 0.125rem;
}

.service-type-dot {
    width: 6px;
    height: 6px;
    border-radius: 50%;
    flex-shrink: 0;
}

.assignment-role {
    font-size: 0.75rem;
    color: var(--admin-text-muted);
}

.date-type-selector {
    display: flex;
    gap: 0.5rem;
}

.date-type-option {
    flex: 1;
    cursor: pointer;
}

.date-type-option input {
    position: absolute;
    opacity: 0;
    pointer-events: none;
}

.date-type-option span {
    display: block;
    padding: 0.625rem 1rem;
    text-align: center;
    background: var(--admin-bg);
    border: 2px solid var(--admin-border);
    border-radius: var(--admin-radius);
    font-weight: 500;
    color: var(--admin-text);
    transition: all 0.15s;
}

.date-type-option input:checked + span {
    border-color: var(--current-app-color);
    background: color-mix(in srgb, var(--current-app-color) 10%, var(--admin-bg));
    color: var(--current-app-color);
}

.text-right {
    text-align: right;
}

.admin-btn-icon {
    width: 32px;
    height: 32px;
    display: flex;
    align-items: center;
    justify-content: center;
    background: transparent;
    border: none;
    border-radius: var(--admin-radius);
    cursor: pointer;
    color: var(--admin-text-muted);
    transition: all 0.15s;
}

.admin-btn-icon:hover {
    background: var(--admin-bg);
    color: var(--admin-text);
}

.admin-btn-icon.text-danger:hover {
    background: rgba(239, 68, 68, 0.1);
    color: #ef4444;
}

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
</style>

<script <?= csp_nonce(); ?>>
const memberId = <?= $memberId; ?>;
const blackoutDates = <?= json_encode($blackoutDates); ?>;

function showAddDateModal() {
    document.getElementById('add-date-modal').classList.add('active');
}

function hideAddDateModal() {
    document.getElementById('add-date-modal').classList.remove('active');
    document.getElementById('single_date').value = '';
    document.getElementById('start_date').value = '';
    document.getElementById('end_date').value = '';
    document.getElementById('reason').value = '';
}

function toggleDateType() {
    const dateType = document.querySelector('input[name="date_type"]:checked').value;
    document.getElementById('single-date-fields').style.display = dateType === 'single' ? 'block' : 'none';
    document.getElementById('range-date-fields').style.display = dateType === 'range' ? 'block' : 'none';
}

function saveBlackout() {
    const dateType = document.querySelector('input[name="date_type"]:checked').value;
    const reason = document.getElementById('reason').value;

    let url = '/adminnew/services/api/availability.php';
    let data = {
        member_id: memberId,
        reason: reason
    };

    if (dateType === 'single') {
        const date = document.getElementById('single_date').value;
        if (!date) {
            alert('Please select a date');
            return;
        }
        data.action = 'add';
        data.unavailable_date = date;
    } else {
        const startDate = document.getElementById('start_date').value;
        const endDate = document.getElementById('end_date').value;
        if (!startDate || !endDate) {
            alert('Please select both start and end dates');
            return;
        }
        data.action = 'add-range';
        data.start_date = startDate;
        data.end_date = endDate;
    }

    fetch(url, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(data)
    })
    .then(response => response.json())
    .then(result => {
        if (result.success) {
            hideAddDateModal();
            location.reload();
        } else {
            alert(result.error || 'Failed to save');
        }
    })
    .catch(err => alert('Failed to save: ' + err.message));
}

function removeBlackout(id) {
    if (!confirm('Remove this unavailable date?')) return;

    fetch('/adminnew/services/api/availability.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
            action: 'remove',
            id: id
        })
    })
    .then(response => response.json())
    .then(result => {
        if (result.success) {
            location.reload();
        } else {
            alert(result.error || 'Failed to remove');
        }
    })
    .catch(err => alert('Failed to remove: ' + err.message));
}

// Close modal on escape
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        hideAddDateModal();
    }
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
