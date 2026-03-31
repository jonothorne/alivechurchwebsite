<?php
/**
 * Blockout Dates Management
 * Admin view to manage team member availability/blockout dates
 */
$page_title = 'Blockout Dates';
$current_app = 'services';
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../../includes/db-config.php';

$pdo = getDbConnection();

// Get filter parameters
$teamFilter = (int)($_GET['team'] ?? 0);
$memberFilter = (int)($_GET['member'] ?? 0);
$monthOffset = (int)($_GET['month'] ?? 0);

// Calculate date range (show current month + offset)
$currentDate = new DateTime();
$currentDate->modify("first day of {$monthOffset} month");
$monthStart = $currentDate->format('Y-m-d');
$currentDate->modify('last day of this month');
$monthEnd = $currentDate->format('Y-m-d');
$displayMonth = $currentDate->format('F Y');

// Get all teams for filter
$teams = $pdo->query("
    SELECT id, name, color
    FROM service_teams
    WHERE is_active = 1
    ORDER BY sort_order
")->fetchAll();

// Get team members for filter (optionally filtered by team)
$memberQuery = "
    SELECT u.id, CONCAT(u.first_name, ' ', u.last_name) as name, u.first_name, u.last_name
    FROM users u
    INNER JOIN service_team_members stm ON u.id = stm.member_id
    INNER JOIN service_teams t ON stm.team_id = t.id
    WHERE stm.is_active = 1 AND t.is_active = 1 AND u.active = 1
";
if ($teamFilter) {
    $memberQuery .= " AND stm.team_id = " . (int)$teamFilter;
}
$memberQuery .= " GROUP BY u.id, u.first_name, u.last_name ORDER BY u.first_name, u.last_name";
$members = $pdo->query($memberQuery)->fetchAll();

// Build blockout query with filters
$blockoutQuery = "
    SELECT ma.*,
           CONCAT(u.first_name, ' ', u.last_name) as member_name,
           u.email as member_email,
           GROUP_CONCAT(DISTINCT t.name ORDER BY t.sort_order SEPARATOR ', ') as team_names,
           SUBSTRING_INDEX(GROUP_CONCAT(DISTINCT t.color ORDER BY t.sort_order SEPARATOR ','), ',', 1) as team_color
    FROM member_availability ma
    JOIN users u ON ma.member_id = u.id
    LEFT JOIN service_team_members stm ON u.id = stm.member_id AND stm.is_active = 1
    LEFT JOIN service_teams t ON stm.team_id = t.id AND t.is_active = 1
    WHERE ma.unavailable_date BETWEEN ? AND ?
";
$params = [$monthStart, $monthEnd];

if ($teamFilter) {
    $blockoutQuery .= " AND stm.team_id = ?";
    $params[] = $teamFilter;
}

if ($memberFilter) {
    $blockoutQuery .= " AND ma.member_id = ?";
    $params[] = $memberFilter;
}

$blockoutQuery .= "
    GROUP BY ma.id
    ORDER BY ma.unavailable_date, member_name
";

$stmt = $pdo->prepare($blockoutQuery);
$stmt->execute($params);
$blockouts = $stmt->fetchAll();

// Group blockouts by date for calendar view
$blockoutsByDate = [];
foreach ($blockouts as $blockout) {
    $date = $blockout['unavailable_date'];
    if (!isset($blockoutsByDate[$date])) {
        $blockoutsByDate[$date] = [];
    }
    $blockoutsByDate[$date][] = $blockout;
}

// Get upcoming services with potential conflicts
$upcomingConflicts = $pdo->prepare("
    SELECT s.id, s.service_date, s.start_time, st.name as type_name, st.color,
           COUNT(DISTINCT ma.member_id) as unavailable_members
    FROM services s
    JOIN service_types st ON s.service_type_id = st.id
    JOIN member_availability ma ON ma.unavailable_date = s.service_date
    JOIN service_team_members stm ON ma.member_id = stm.member_id AND stm.is_active = 1
    WHERE s.service_date BETWEEN ? AND ?
    GROUP BY s.id
    HAVING unavailable_members > 0
    ORDER BY s.service_date
    LIMIT 10
");
$upcomingConflicts->execute([$monthStart, $monthEnd]);
$conflicts = $upcomingConflicts->fetchAll();

// Generate calendar data
$firstDay = new DateTime($monthStart);
$lastDay = new DateTime($monthEnd);
$startDayOfWeek = (int)$firstDay->format('N'); // 1 (Mon) to 7 (Sun)
$daysInMonth = (int)$lastDay->format('j');
?>

<!-- Page Header -->
<div class="admin-page-header">
    <div>
        <h1 class="admin-page-title">Blockout Dates</h1>
        <p class="admin-page-subtitle">View and manage team member availability</p>
    </div>
    <div class="admin-page-actions">
        <a href="/adminnew/services" class="admin-btn admin-btn-secondary">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <line x1="19" y1="12" x2="5" y2="12"></line>
                <polyline points="12 19 5 12 12 5"></polyline>
            </svg>
            Back
        </a>
        <button type="button" class="admin-btn admin-btn-primary" onclick="showAddBlockoutModal()">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <line x1="12" y1="5" x2="12" y2="19"></line>
                <line x1="5" y1="12" x2="19" y2="12"></line>
            </svg>
            Add Blockout
        </button>
    </div>
</div>

<!-- Filters & Navigation -->
<div class="blockouts-filters">
    <form method="GET" class="blockouts-filter-form">
        <div class="blockouts-month-nav">
            <a href="?month=<?= $monthOffset - 1 ?><?= $teamFilter ? "&team=$teamFilter" : '' ?><?= $memberFilter ? "&member=$memberFilter" : '' ?>" class="admin-btn admin-btn-sm admin-btn-secondary">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <polyline points="15 18 9 12 15 6"></polyline>
                </svg>
            </a>
            <span class="blockouts-month-label"><?= $displayMonth ?></span>
            <a href="?month=<?= $monthOffset + 1 ?><?= $teamFilter ? "&team=$teamFilter" : '' ?><?= $memberFilter ? "&member=$memberFilter" : '' ?>" class="admin-btn admin-btn-sm admin-btn-secondary">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <polyline points="9 18 15 12 9 6"></polyline>
                </svg>
            </a>
            <?php if ($monthOffset !== 0): ?>
            <a href="?month=0<?= $teamFilter ? "&team=$teamFilter" : '' ?><?= $memberFilter ? "&member=$memberFilter" : '' ?>" class="admin-btn admin-btn-sm admin-btn-secondary" style="margin-left: 0.5rem;">Today</a>
            <?php endif; ?>
        </div>

        <input type="hidden" name="month" value="<?= $monthOffset ?>">

        <div class="blockouts-filter-group">
            <select name="team" class="admin-input admin-input-sm" onchange="this.form.submit()">
                <option value="">All Teams</option>
                <?php foreach ($teams as $team): ?>
                <option value="<?= $team['id'] ?>" <?= $teamFilter == $team['id'] ? 'selected' : '' ?>>
                    <?= htmlspecialchars($team['name']) ?>
                </option>
                <?php endforeach; ?>
            </select>

            <select name="member" class="admin-input admin-input-sm" onchange="this.form.submit()">
                <option value="">All Members</option>
                <?php foreach ($members as $member): ?>
                <option value="<?= $member['id'] ?>" <?= $memberFilter == $member['id'] ? 'selected' : '' ?>>
                    <?= htmlspecialchars($member['name']) ?>
                </option>
                <?php endforeach; ?>
            </select>
        </div>
    </form>
</div>

<div class="blockouts-grid">
    <!-- Calendar View -->
    <div class="blockouts-calendar-section">
        <div class="admin-card">
            <div class="admin-card-header">
                <h2 class="admin-card-title">Calendar View</h2>
            </div>
            <div class="admin-card-body" style="padding: 0;">
                <div class="blockouts-calendar">
                    <div class="blockouts-calendar-header">
                        <div class="blockouts-calendar-day-header">Mon</div>
                        <div class="blockouts-calendar-day-header">Tue</div>
                        <div class="blockouts-calendar-day-header">Wed</div>
                        <div class="blockouts-calendar-day-header">Thu</div>
                        <div class="blockouts-calendar-day-header">Fri</div>
                        <div class="blockouts-calendar-day-header">Sat</div>
                        <div class="blockouts-calendar-day-header">Sun</div>
                    </div>
                    <div class="blockouts-calendar-grid">
                        <?php
                        // Empty cells before first day
                        for ($i = 1; $i < $startDayOfWeek; $i++):
                        ?>
                        <div class="blockouts-calendar-cell empty"></div>
                        <?php endfor; ?>

                        <?php
                        // Days of the month
                        $today = date('Y-m-d');
                        for ($day = 1; $day <= $daysInMonth; $day++):
                            $dateStr = $firstDay->format('Y-m') . '-' . str_pad($day, 2, '0', STR_PAD_LEFT);
                            $hasBlockouts = isset($blockoutsByDate[$dateStr]);
                            $blockoutCount = $hasBlockouts ? count($blockoutsByDate[$dateStr]) : 0;
                            $isToday = $dateStr === $today;
                            $isPast = $dateStr < $today;
                        ?>
                        <div class="blockouts-calendar-cell <?= $hasBlockouts ? 'has-blockouts' : '' ?> <?= $isToday ? 'is-today' : '' ?> <?= $isPast ? 'is-past' : '' ?>"
                             <?php if ($hasBlockouts): ?>onclick="showDateDetails('<?= $dateStr ?>')"<?php endif; ?>>
                            <span class="blockouts-calendar-date"><?= $day ?></span>
                            <?php if ($hasBlockouts): ?>
                            <div class="blockouts-calendar-count"><?= $blockoutCount ?></div>
                            <div class="blockouts-calendar-names">
                                <?php
                                $names = array_slice($blockoutsByDate[$dateStr], 0, 3);
                                foreach ($names as $b):
                                ?>
                                <span class="blockouts-calendar-name"><?= htmlspecialchars(explode(' ', $b['member_name'])[0]) ?></span>
                                <?php endforeach; ?>
                                <?php if ($blockoutCount > 3): ?>
                                <span class="blockouts-calendar-more">+<?= $blockoutCount - 3 ?> more</span>
                                <?php endif; ?>
                            </div>
                            <?php endif; ?>
                        </div>
                        <?php endfor; ?>

                        <?php
                        // Empty cells after last day
                        $endDayOfWeek = (int)$lastDay->format('N');
                        for ($i = $endDayOfWeek; $i < 7; $i++):
                        ?>
                        <div class="blockouts-calendar-cell empty"></div>
                        <?php endfor; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Sidebar: Conflicts & List -->
    <div class="blockouts-sidebar">
        <!-- Potential Conflicts -->
        <?php if (!empty($conflicts)): ?>
        <div class="admin-card">
            <div class="admin-card-header">
                <h3 class="admin-card-title">Service Conflicts</h3>
            </div>
            <div class="admin-card-body" style="padding: 0;">
                <div class="blockouts-conflicts-list">
                    <?php foreach ($conflicts as $conflict): ?>
                    <a href="/adminnew/services/plan/<?= $conflict['id'] ?>" class="blockouts-conflict-item">
                        <div class="blockouts-conflict-date">
                            <span class="blockouts-conflict-day"><?= date('D', strtotime($conflict['service_date'])) ?></span>
                            <span class="blockouts-conflict-num"><?= date('j', strtotime($conflict['service_date'])) ?></span>
                        </div>
                        <div class="blockouts-conflict-info">
                            <span class="blockouts-conflict-type" style="color: <?= $conflict['color'] ?>;">
                                <?= htmlspecialchars($conflict['type_name']) ?>
                            </span>
                            <span class="blockouts-conflict-count">
                                <?= $conflict['unavailable_members'] ?> member<?= $conflict['unavailable_members'] > 1 ? 's' : '' ?> unavailable
                            </span>
                        </div>
                    </a>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- All Blockouts List -->
        <div class="admin-card">
            <div class="admin-card-header">
                <h3 class="admin-card-title">All Blockouts</h3>
                <span class="admin-badge admin-badge-secondary"><?= count($blockouts) ?></span>
            </div>
            <div class="admin-card-body" style="padding: 0;">
                <?php if (empty($blockouts)): ?>
                <div class="admin-empty-state" style="padding: 2rem;">
                    <p class="admin-text-muted">No blockout dates for this month</p>
                </div>
                <?php else: ?>
                <div class="blockouts-list">
                    <?php foreach ($blockouts as $blockout): ?>
                    <div class="blockouts-list-item">
                        <div class="blockouts-list-date">
                            <?= date('M j', strtotime($blockout['unavailable_date'])) ?>
                        </div>
                        <div class="blockouts-list-info">
                            <span class="blockouts-list-name"><?= htmlspecialchars($blockout['member_name']) ?></span>
                            <?php if ($blockout['reason']): ?>
                            <span class="blockouts-list-reason"><?= htmlspecialchars($blockout['reason']) ?></span>
                            <?php endif; ?>
                            <?php if ($blockout['is_recurring']): ?>
                            <span class="admin-badge admin-badge-info" style="font-size: 0.65rem;">Recurring</span>
                            <?php endif; ?>
                        </div>
                        <button type="button" class="admin-btn admin-btn-sm admin-btn-ghost"
                                onclick="deleteBlockout(<?= $blockout['id'] ?>)" title="Remove">
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <polyline points="3 6 5 6 21 6"></polyline>
                                <path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path>
                            </svg>
                        </button>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Add Blockout Modal -->
<div class="admin-modal" id="addBlockoutModal">
    <div class="admin-modal-content">
        <div class="admin-modal-header">
            <h3>Add Blockout Date</h3>
            <button type="button" class="admin-modal-close" onclick="hideAddBlockoutModal()">&times;</button>
        </div>
        <form id="addBlockoutForm" onsubmit="submitBlockout(event)">
            <div class="admin-modal-body">
                <div class="admin-form-group">
                    <label class="admin-label">Team Member</label>
                    <select name="member_id" id="blockoutMemberId" class="admin-input" required>
                        <option value="">Select a member...</option>
                        <?php foreach ($members as $member): ?>
                        <option value="<?= $member['id'] ?>"><?= htmlspecialchars($member['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="admin-form-group">
                    <label class="admin-label">Date Type</label>
                    <div class="admin-radio-group">
                        <label class="admin-radio">
                            <input type="radio" name="date_type" value="single" checked onchange="toggleDateType()">
                            <span>Single Date</span>
                        </label>
                        <label class="admin-radio">
                            <input type="radio" name="date_type" value="range" onchange="toggleDateType()">
                            <span>Date Range</span>
                        </label>
                    </div>
                </div>

                <div id="singleDateField" class="admin-form-group">
                    <label class="admin-label">Date</label>
                    <input type="date" name="single_date" class="admin-input">
                </div>

                <div id="rangeDateFields" class="admin-form-group" style="display: none;">
                    <div class="admin-form-row">
                        <div>
                            <label class="admin-label">Start Date</label>
                            <input type="date" name="start_date" class="admin-input">
                        </div>
                        <div>
                            <label class="admin-label">End Date</label>
                            <input type="date" name="end_date" class="admin-input">
                        </div>
                    </div>
                </div>

                <div class="admin-form-group">
                    <label class="admin-label">Reason (optional)</label>
                    <input type="text" name="reason" class="admin-input" placeholder="e.g., Vacation, Medical, etc.">
                </div>

                <div class="admin-form-group">
                    <label class="admin-checkbox">
                        <input type="checkbox" name="is_recurring">
                        <span>Recurring annually</span>
                    </label>
                </div>
            </div>
            <div class="admin-modal-footer">
                <button type="button" class="admin-btn admin-btn-secondary" onclick="hideAddBlockoutModal()">Cancel</button>
                <button type="submit" class="admin-btn admin-btn-primary">Add Blockout</button>
            </div>
        </form>
    </div>
</div>

<!-- Date Details Modal -->
<div class="admin-modal" id="dateDetailsModal">
    <div class="admin-modal-content">
        <div class="admin-modal-header">
            <h3 id="dateDetailsTitle">Blockouts</h3>
            <button type="button" class="admin-modal-close" onclick="hideDateDetailsModal()">&times;</button>
        </div>
        <div class="admin-modal-body" id="dateDetailsBody">
            <!-- Populated by JavaScript -->
        </div>
    </div>
</div>

<style <?= csp_nonce(); ?>>
/* Blockouts Page Styles */
.blockouts-filters {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 1rem;
    margin-bottom: 1.5rem;
    flex-wrap: wrap;
}

.blockouts-filter-form {
    display: flex;
    align-items: center;
    gap: 1rem;
    flex-wrap: wrap;
    width: 100%;
    justify-content: space-between;
}

.blockouts-month-nav {
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.blockouts-month-label {
    font-weight: 600;
    font-size: 1.125rem;
    min-width: 140px;
    text-align: center;
}

.blockouts-filter-group {
    display: flex;
    gap: 0.5rem;
}

.blockouts-filter-group .admin-input-sm {
    min-width: 150px;
}

/* Grid Layout */
.blockouts-grid {
    display: grid;
    grid-template-columns: 1fr 350px;
    gap: 1.5rem;
}

@media (max-width: 1024px) {
    .blockouts-grid {
        grid-template-columns: 1fr;
    }
}

/* Calendar */
.blockouts-calendar {
    padding: 1rem;
}

.blockouts-calendar-header {
    display: grid;
    grid-template-columns: repeat(7, 1fr);
    gap: 2px;
    margin-bottom: 0.5rem;
}

.blockouts-calendar-day-header {
    text-align: center;
    font-size: 0.75rem;
    font-weight: 600;
    color: var(--admin-text-muted);
    padding: 0.5rem 0;
}

.blockouts-calendar-grid {
    display: grid;
    grid-template-columns: repeat(7, 1fr);
    gap: 2px;
}

.blockouts-calendar-cell {
    aspect-ratio: 1;
    min-height: 80px;
    background: var(--admin-bg);
    border: 1px solid var(--admin-border);
    border-radius: var(--admin-radius-sm);
    padding: 0.375rem;
    display: flex;
    flex-direction: column;
    position: relative;
    cursor: default;
}

.blockouts-calendar-cell.empty {
    background: transparent;
    border: none;
}

.blockouts-calendar-cell.is-past {
    opacity: 0.6;
}

.blockouts-calendar-cell.is-today {
    border-color: var(--current-app-color);
    border-width: 2px;
}

.blockouts-calendar-cell.has-blockouts {
    background: color-mix(in srgb, var(--admin-danger) 10%, var(--admin-bg));
    cursor: pointer;
}

.blockouts-calendar-cell.has-blockouts:hover {
    background: color-mix(in srgb, var(--admin-danger) 15%, var(--admin-bg));
}

.blockouts-calendar-date {
    font-size: 0.75rem;
    font-weight: 500;
    color: var(--admin-text-muted);
}

.blockouts-calendar-cell.is-today .blockouts-calendar-date {
    color: var(--current-app-color);
    font-weight: 700;
}

.blockouts-calendar-count {
    position: absolute;
    top: 0.25rem;
    right: 0.25rem;
    background: var(--admin-danger);
    color: white;
    font-size: 0.65rem;
    font-weight: 700;
    width: 18px;
    height: 18px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
}

.blockouts-calendar-names {
    margin-top: auto;
    display: flex;
    flex-direction: column;
    gap: 1px;
    overflow: hidden;
}

.blockouts-calendar-name {
    font-size: 0.625rem;
    color: var(--admin-text);
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.blockouts-calendar-more {
    font-size: 0.6rem;
    color: var(--admin-text-muted);
}

/* Conflicts List */
.blockouts-conflicts-list {
    display: flex;
    flex-direction: column;
}

.blockouts-conflict-item {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    padding: 0.75rem 1rem;
    text-decoration: none;
    color: inherit;
    border-bottom: 1px solid var(--admin-border);
    transition: background 0.15s;
}

.blockouts-conflict-item:last-child {
    border-bottom: none;
}

.blockouts-conflict-item:hover {
    background: var(--admin-bg);
}

.blockouts-conflict-date {
    display: flex;
    flex-direction: column;
    align-items: center;
    min-width: 36px;
}

.blockouts-conflict-day {
    font-size: 0.625rem;
    text-transform: uppercase;
    color: var(--admin-text-muted);
}

.blockouts-conflict-num {
    font-size: 1rem;
    font-weight: 700;
}

.blockouts-conflict-info {
    display: flex;
    flex-direction: column;
}

.blockouts-conflict-type {
    font-weight: 500;
    font-size: 0.875rem;
}

.blockouts-conflict-count {
    font-size: 0.75rem;
    color: var(--admin-text-muted);
}

/* Blockouts List */
.blockouts-list {
    max-height: 400px;
    overflow-y: auto;
}

.blockouts-list-item {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    padding: 0.625rem 1rem;
    border-bottom: 1px solid var(--admin-border);
}

.blockouts-list-item:last-child {
    border-bottom: none;
}

.blockouts-list-date {
    font-size: 0.75rem;
    font-weight: 500;
    color: var(--admin-text-muted);
    min-width: 45px;
}

.blockouts-list-info {
    flex: 1;
    display: flex;
    flex-direction: column;
    gap: 2px;
    min-width: 0;
}

.blockouts-list-name {
    font-weight: 500;
    font-size: 0.875rem;
}

.blockouts-list-reason {
    font-size: 0.75rem;
    color: var(--admin-text-muted);
}

/* Modal Enhancements */
.admin-form-row {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 1rem;
}

.admin-radio-group {
    display: flex;
    gap: 1.5rem;
}

.admin-radio {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    cursor: pointer;
}

.admin-radio input {
    margin: 0;
}

/* Date Details Modal */
#dateDetailsBody .blockouts-detail-item {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 0.75rem;
    border-bottom: 1px solid var(--admin-border);
}

#dateDetailsBody .blockouts-detail-item:last-child {
    border-bottom: none;
}

#dateDetailsBody .blockouts-detail-info {
    display: flex;
    flex-direction: column;
}

#dateDetailsBody .blockouts-detail-name {
    font-weight: 500;
}

#dateDetailsBody .blockouts-detail-reason {
    font-size: 0.8rem;
    color: var(--admin-text-muted);
}
</style>

<script <?= csp_nonce(); ?>>
// Blockout data for date details
const blockoutsByDate = <?= json_encode($blockoutsByDate) ?>;

function showAddBlockoutModal() {
    document.getElementById('addBlockoutModal').classList.add('active');
    document.getElementById('addBlockoutForm').reset();
    toggleDateType();
}

function hideAddBlockoutModal() {
    document.getElementById('addBlockoutModal').classList.remove('active');
}

function toggleDateType() {
    const dateType = document.querySelector('input[name="date_type"]:checked').value;
    document.getElementById('singleDateField').style.display = dateType === 'single' ? 'block' : 'none';
    document.getElementById('rangeDateFields').style.display = dateType === 'range' ? 'block' : 'none';
}

async function submitBlockout(e) {
    e.preventDefault();

    const form = e.target;
    const formData = new FormData(form);
    const dateType = formData.get('date_type');

    const data = {
        member_id: formData.get('member_id'),
        reason: formData.get('reason'),
        is_recurring: formData.get('is_recurring') === 'on'
    };

    if (dateType === 'single') {
        data.action = 'add';
        data.unavailable_date = formData.get('single_date');
    } else {
        data.action = 'add-range';
        data.start_date = formData.get('start_date');
        data.end_date = formData.get('end_date');
    }

    try {
        const response = await fetch('/adminnew/services/api/availability.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify(data)
        });

        const result = await response.json();

        if (result.success) {
            hideAddBlockoutModal();
            location.reload();
        } else {
            alert(result.error || 'Failed to add blockout date');
        }
    } catch (error) {
        console.error('Error:', error);
        alert('Failed to add blockout date');
    }
}

async function deleteBlockout(id) {
    if (!confirm('Are you sure you want to remove this blockout date?')) {
        return;
    }

    try {
        const response = await fetch('/adminnew/services/api/availability.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({
                action: 'remove',
                id: id
            })
        });

        const result = await response.json();

        if (result.success) {
            location.reload();
        } else {
            alert(result.error || 'Failed to remove blockout date');
        }
    } catch (error) {
        console.error('Error:', error);
        alert('Failed to remove blockout date');
    }
}

function showDateDetails(dateStr) {
    const blockouts = blockoutsByDate[dateStr] || [];
    const date = new Date(dateStr + 'T00:00:00');
    const formattedDate = date.toLocaleDateString('en-GB', {
        weekday: 'long',
        day: 'numeric',
        month: 'long',
        year: 'numeric'
    });

    document.getElementById('dateDetailsTitle').textContent = formattedDate;

    let html = '';
    if (blockouts.length === 0) {
        html = '<p class="admin-text-muted">No blockouts for this date</p>';
    } else {
        blockouts.forEach(b => {
            html += `
                <div class="blockouts-detail-item">
                    <div class="blockouts-detail-info">
                        <span class="blockouts-detail-name">${escapeHtml(b.member_name)}</span>
                        <span class="blockouts-detail-reason">${b.reason ? escapeHtml(b.reason) : 'No reason given'}</span>
                    </div>
                    <button type="button" class="admin-btn admin-btn-sm admin-btn-ghost"
                            onclick="deleteBlockout(${b.id})" title="Remove">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <polyline points="3 6 5 6 21 6"></polyline>
                            <path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path>
                        </svg>
                    </button>
                </div>
            `;
        });
    }

    document.getElementById('dateDetailsBody').innerHTML = html;
    document.getElementById('dateDetailsModal').classList.add('active');
}

function hideDateDetailsModal() {
    document.getElementById('dateDetailsModal').classList.remove('active');
}

function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

// Close modals on escape key or outside click
document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape') {
        hideAddBlockoutModal();
        hideDateDetailsModal();
    }
});

document.querySelectorAll('.admin-modal').forEach(modal => {
    modal.addEventListener('click', (e) => {
        if (e.target === modal) {
            modal.classList.remove('active');
        }
    });
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
