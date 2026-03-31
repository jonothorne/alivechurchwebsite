<?php
/**
 * Services Calendar View
 * Visual calendar display of all services
 */
$page_title = 'Service Calendar';
$current_app = 'services';
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../../includes/db-config.php';

$pdo = getDbConnection();

// Get month/year from query params or use current
$year = (int)($_GET['year'] ?? date('Y'));
$month = (int)($_GET['month'] ?? date('n'));

// Validate month/year
if ($month < 1) {
    $month = 12;
    $year--;
} elseif ($month > 12) {
    $month = 1;
    $year++;
}

// Calculate date range for this month
$firstDayOfMonth = new DateTime("$year-$month-01");
$lastDayOfMonth = (clone $firstDayOfMonth)->modify('last day of this month');
$monthStart = $firstDayOfMonth->format('Y-m-d');
$monthEnd = $lastDayOfMonth->format('Y-m-d');

// For calendar display, we need to include days from prev/next month to fill the week grid
$startDayOfWeek = (int)$firstDayOfMonth->format('N'); // 1 (Mon) to 7 (Sun)
$calendarStart = (clone $firstDayOfMonth)->modify('-' . ($startDayOfWeek - 1) . ' days');
$endDayOfWeek = (int)$lastDayOfMonth->format('N');
$calendarEnd = (clone $lastDayOfMonth)->modify('+' . (7 - $endDayOfWeek) . ' days');

// Get service types for filter
$serviceTypes = $pdo->query("SELECT * FROM service_types WHERE is_active = 1 ORDER BY sort_order")->fetchAll();

// Get filter
$typeFilter = (int)($_GET['type'] ?? 0);

// Fetch services for the calendar date range
$servicesQuery = "
    SELECT s.*, st.name as type_name, st.color as type_color,
           (SELECT COUNT(*) FROM service_items si WHERE si.service_id = s.id) as item_count,
           (SELECT COUNT(*) FROM service_rota sr WHERE sr.service_id = s.id AND sr.status = 'confirmed') as confirmed_count,
           (SELECT COUNT(*) FROM service_rota sr WHERE sr.service_id = s.id AND sr.member_id IS NULL) as unassigned_count
    FROM services s
    JOIN service_types st ON s.service_type_id = st.id
    WHERE s.service_date BETWEEN ? AND ?
";
$params = [$calendarStart->format('Y-m-d'), $calendarEnd->format('Y-m-d')];

if ($typeFilter) {
    $servicesQuery .= " AND s.service_type_id = ?";
    $params[] = $typeFilter;
}

$servicesQuery .= " ORDER BY s.service_date, s.start_time";

$stmt = $pdo->prepare($servicesQuery);
$stmt->execute($params);
$services = $stmt->fetchAll();

// Group services by date
$servicesByDate = [];
foreach ($services as $service) {
    $date = $service['service_date'];
    if (!isset($servicesByDate[$date])) {
        $servicesByDate[$date] = [];
    }
    $servicesByDate[$date][] = $service;
}

// Navigation dates
$prevMonth = $month - 1;
$prevYear = $year;
if ($prevMonth < 1) {
    $prevMonth = 12;
    $prevYear--;
}
$nextMonth = $month + 1;
$nextYear = $year;
if ($nextMonth > 12) {
    $nextMonth = 1;
    $nextYear++;
}

$today = date('Y-m-d');

function getStatusClass($status) {
    switch ($status) {
        case 'confirmed': return 'status-confirmed';
        case 'planned': return 'status-planned';
        case 'completed': return 'status-completed';
        case 'cancelled': return 'status-cancelled';
        default: return '';
    }
}
?>

<!-- Page Header -->
<div class="admin-page-header">
    <div>
        <h1 class="admin-page-title">Service Calendar</h1>
        <p class="admin-page-subtitle">Visual overview of all services</p>
    </div>
    <div class="admin-page-actions">
        <a href="/adminnew/services" class="admin-btn admin-btn-secondary">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <line x1="19" y1="12" x2="5" y2="12"></line>
                <polyline points="12 19 5 12 12 5"></polyline>
            </svg>
            Back to List
        </a>
        <a href="/adminnew/services/schedule" class="admin-btn admin-btn-primary">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <line x1="12" y1="5" x2="12" y2="19"></line>
                <line x1="5" y1="12" x2="19" y2="12"></line>
            </svg>
            Schedule Service
        </a>
    </div>
</div>

<!-- Calendar Controls -->
<div class="calendar-controls">
    <div class="calendar-nav">
        <a href="?year=<?= $prevYear ?>&month=<?= $prevMonth ?><?= $typeFilter ? "&type=$typeFilter" : '' ?>" class="admin-btn admin-btn-secondary">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <polyline points="15 18 9 12 15 6"></polyline>
            </svg>
        </a>
        <h2 class="calendar-month-title"><?= $firstDayOfMonth->format('F Y') ?></h2>
        <a href="?year=<?= $nextYear ?>&month=<?= $nextMonth ?><?= $typeFilter ? "&type=$typeFilter" : '' ?>" class="admin-btn admin-btn-secondary">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <polyline points="9 18 15 12 9 6"></polyline>
            </svg>
        </a>
        <?php if ($year != date('Y') || $month != date('n')): ?>
        <a href="?<?= $typeFilter ? "type=$typeFilter" : '' ?>" class="admin-btn admin-btn-secondary" style="margin-left: 0.5rem;">Today</a>
        <?php endif; ?>
    </div>

    <div class="calendar-filters">
        <form method="GET" id="filterForm">
            <input type="hidden" name="year" value="<?= $year ?>">
            <input type="hidden" name="month" value="<?= $month ?>">
            <select name="type" class="admin-input admin-input-sm" onchange="document.getElementById('filterForm').submit()">
                <option value="">All Service Types</option>
                <?php foreach ($serviceTypes as $type): ?>
                <option value="<?= $type['id'] ?>" <?= $typeFilter == $type['id'] ? 'selected' : '' ?>>
                    <?= htmlspecialchars($type['name']) ?>
                </option>
                <?php endforeach; ?>
            </select>
        </form>
    </div>
</div>

<!-- Legend -->
<div class="calendar-legend">
    <?php foreach ($serviceTypes as $type): ?>
    <div class="legend-item">
        <span class="legend-color" style="background: <?= $type['color'] ?>;"></span>
        <span class="legend-label"><?= htmlspecialchars($type['name']) ?></span>
    </div>
    <?php endforeach; ?>
</div>

<!-- Calendar Grid -->
<div class="admin-card">
    <div class="calendar-container">
        <!-- Day Headers -->
        <div class="calendar-header">
            <div class="calendar-day-header">Monday</div>
            <div class="calendar-day-header">Tuesday</div>
            <div class="calendar-day-header">Wednesday</div>
            <div class="calendar-day-header">Thursday</div>
            <div class="calendar-day-header">Friday</div>
            <div class="calendar-day-header">Saturday</div>
            <div class="calendar-day-header">Sunday</div>
        </div>

        <!-- Calendar Grid -->
        <div class="calendar-grid">
            <?php
            $currentDay = clone $calendarStart;
            while ($currentDay <= $calendarEnd):
                $dateStr = $currentDay->format('Y-m-d');
                $isToday = $dateStr === $today;
                $isCurrentMonth = $currentDay->format('n') == $month;
                $dayServices = $servicesByDate[$dateStr] ?? [];
            ?>
            <div class="calendar-cell <?= $isToday ? 'is-today' : '' ?> <?= !$isCurrentMonth ? 'other-month' : '' ?>">
                <div class="calendar-date">
                    <span class="calendar-date-number <?= $isToday ? 'today' : '' ?>">
                        <?= $currentDay->format('j') ?>
                    </span>
                </div>
                <div class="calendar-events">
                    <?php foreach ($dayServices as $service): ?>
                    <a href="/adminnew/services/plan/<?= $service['id'] ?>"
                       class="calendar-event <?= getStatusClass($service['status']) ?>"
                       style="border-left-color: <?= $service['type_color'] ?>;">
                        <span class="calendar-event-time"><?= date('g:ia', strtotime($service['start_time'])) ?></span>
                        <span class="calendar-event-title"><?= htmlspecialchars($service['type_name']) ?></span>
                        <?php if ($service['unassigned_count'] > 0): ?>
                        <span class="calendar-event-badge warning"><?= $service['unassigned_count'] ?></span>
                        <?php endif; ?>
                    </a>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php
                $currentDay->modify('+1 day');
            endwhile;
            ?>
        </div>
    </div>
</div>

<!-- Upcoming Services Sidebar -->
<div class="calendar-sidebar">
    <div class="admin-card">
        <div class="admin-card-header">
            <h3 class="admin-card-title">Upcoming Services</h3>
        </div>
        <div class="admin-card-body" style="padding: 0;">
            <?php
            $upcomingStmt = $pdo->prepare("
                SELECT s.*, st.name as type_name, st.color as type_color
                FROM services s
                JOIN service_types st ON s.service_type_id = st.id
                WHERE s.service_date >= CURDATE()
                ORDER BY s.service_date, s.start_time
                LIMIT 10
            ");
            $upcomingStmt->execute();
            $upcoming = $upcomingStmt->fetchAll();
            ?>
            <?php if (empty($upcoming)): ?>
            <div class="admin-empty-state" style="padding: 1.5rem;">
                <p class="admin-text-muted">No upcoming services</p>
            </div>
            <?php else: ?>
            <div class="upcoming-services-list">
                <?php foreach ($upcoming as $service): ?>
                <a href="/adminnew/services/plan/<?= $service['id'] ?>" class="upcoming-service-item">
                    <div class="upcoming-service-date">
                        <span class="upcoming-service-day"><?= date('D', strtotime($service['service_date'])) ?></span>
                        <span class="upcoming-service-num"><?= date('j', strtotime($service['service_date'])) ?></span>
                        <span class="upcoming-service-month"><?= date('M', strtotime($service['service_date'])) ?></span>
                    </div>
                    <div class="upcoming-service-info">
                        <span class="upcoming-service-type" style="color: <?= $service['type_color'] ?>;">
                            <?= htmlspecialchars($service['type_name']) ?>
                        </span>
                        <span class="upcoming-service-time">
                            <?= date('g:i A', strtotime($service['start_time'])) ?>
                        </span>
                    </div>
                    <span class="admin-badge admin-badge-<?= $service['status'] === 'confirmed' ? 'success' : ($service['status'] === 'planned' ? 'info' : 'secondary') ?>">
                        <?= ucfirst($service['status']) ?>
                    </span>
                </a>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Quick Stats -->
    <div class="admin-card">
        <div class="admin-card-header">
            <h3 class="admin-card-title">This Month</h3>
        </div>
        <div class="admin-card-body">
            <?php
            $monthStats = $pdo->prepare("
                SELECT
                    COUNT(*) as total,
                    SUM(CASE WHEN status = 'confirmed' THEN 1 ELSE 0 END) as confirmed,
                    SUM(CASE WHEN status = 'planned' THEN 1 ELSE 0 END) as planned
                FROM services
                WHERE service_date BETWEEN ? AND ?
            ");
            $monthStats->execute([$monthStart, $monthEnd]);
            $stats = $monthStats->fetch();
            ?>
            <div class="month-stats">
                <div class="month-stat">
                    <span class="month-stat-value"><?= $stats['total'] ?></span>
                    <span class="month-stat-label">Total Services</span>
                </div>
                <div class="month-stat">
                    <span class="month-stat-value"><?= $stats['confirmed'] ?></span>
                    <span class="month-stat-label">Confirmed</span>
                </div>
                <div class="month-stat">
                    <span class="month-stat-value"><?= $stats['planned'] ?></span>
                    <span class="month-stat-label">Planned</span>
                </div>
            </div>
        </div>
    </div>
</div>

<style <?= csp_nonce(); ?>>
/* Calendar Layout */
.calendar-controls {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 1rem;
    flex-wrap: wrap;
    gap: 1rem;
}

.calendar-nav {
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.calendar-month-title {
    font-size: 1.25rem;
    font-weight: 600;
    margin: 0;
    min-width: 180px;
    text-align: center;
}

/* Legend */
.calendar-legend {
    display: flex;
    gap: 1.5rem;
    margin-bottom: 1rem;
    flex-wrap: wrap;
}

.legend-item {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    font-size: 0.8125rem;
}

.legend-color {
    width: 12px;
    height: 12px;
    border-radius: 3px;
}

/* Calendar Container with Sidebar */
.calendar-sidebar {
    display: none;
}

@media (min-width: 1200px) {
    .admin-content {
        display: grid;
        grid-template-columns: 1fr 320px;
        gap: 1.5rem;
    }

    .admin-content > .admin-card:first-of-type {
        grid-column: 1;
    }

    .calendar-sidebar {
        display: flex;
        flex-direction: column;
        gap: 1rem;
        position: sticky;
        top: 1rem;
        height: fit-content;
    }
}

/* Calendar Grid */
.calendar-container {
    background: var(--admin-card-bg);
    border-radius: var(--admin-radius-lg);
    overflow: hidden;
}

.calendar-header {
    display: grid;
    grid-template-columns: repeat(7, 1fr);
    background: var(--admin-bg);
    border-bottom: 1px solid var(--admin-border);
}

.calendar-day-header {
    padding: 0.75rem 0.5rem;
    text-align: center;
    font-size: 0.75rem;
    font-weight: 600;
    text-transform: uppercase;
    color: var(--admin-text-muted);
}

.calendar-grid {
    display: grid;
    grid-template-columns: repeat(7, 1fr);
}

.calendar-cell {
    min-height: 120px;
    border-right: 1px solid var(--admin-border);
    border-bottom: 1px solid var(--admin-border);
    padding: 0.5rem;
    display: flex;
    flex-direction: column;
}

.calendar-cell:nth-child(7n) {
    border-right: none;
}

.calendar-cell.other-month {
    background: var(--admin-bg);
}

.calendar-cell.other-month .calendar-date-number {
    color: var(--admin-text-muted);
}

.calendar-cell.is-today {
    background: color-mix(in srgb, var(--current-app-color) 5%, var(--admin-card-bg));
}

.calendar-date {
    margin-bottom: 0.25rem;
}

.calendar-date-number {
    font-size: 0.875rem;
    font-weight: 500;
    color: var(--admin-text);
}

.calendar-date-number.today {
    background: var(--current-app-color);
    color: white;
    width: 24px;
    height: 24px;
    border-radius: 50%;
    display: inline-flex;
    align-items: center;
    justify-content: center;
}

.calendar-events {
    display: flex;
    flex-direction: column;
    gap: 0.25rem;
    overflow-y: auto;
    flex: 1;
}

.calendar-event {
    display: flex;
    align-items: center;
    gap: 0.375rem;
    padding: 0.25rem 0.5rem;
    background: var(--admin-bg);
    border-radius: var(--admin-radius-sm);
    border-left: 3px solid;
    text-decoration: none;
    font-size: 0.75rem;
    transition: all 0.15s;
}

.calendar-event:hover {
    background: var(--admin-border);
}

.calendar-event.status-confirmed {
    background: color-mix(in srgb, var(--admin-success) 10%, var(--admin-bg));
}

.calendar-event.status-cancelled {
    opacity: 0.5;
    text-decoration: line-through;
}

.calendar-event-time {
    color: var(--admin-text-muted);
    font-size: 0.6875rem;
    white-space: nowrap;
}

.calendar-event-title {
    color: var(--admin-text);
    flex: 1;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.calendar-event-badge {
    font-size: 0.625rem;
    padding: 0.125rem 0.375rem;
    border-radius: 10px;
    font-weight: 600;
}

.calendar-event-badge.warning {
    background: var(--admin-warning);
    color: #000;
}

/* Upcoming Services */
.upcoming-services-list {
    display: flex;
    flex-direction: column;
}

.upcoming-service-item {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    padding: 0.75rem 1rem;
    border-bottom: 1px solid var(--admin-border);
    text-decoration: none;
    color: inherit;
    transition: background 0.15s;
}

.upcoming-service-item:last-child {
    border-bottom: none;
}

.upcoming-service-item:hover {
    background: var(--admin-bg);
}

.upcoming-service-date {
    display: flex;
    flex-direction: column;
    align-items: center;
    min-width: 40px;
}

.upcoming-service-day {
    font-size: 0.625rem;
    text-transform: uppercase;
    color: var(--admin-text-muted);
}

.upcoming-service-num {
    font-size: 1.125rem;
    font-weight: 700;
    line-height: 1.2;
}

.upcoming-service-month {
    font-size: 0.625rem;
    text-transform: uppercase;
    color: var(--admin-text-muted);
}

.upcoming-service-info {
    flex: 1;
    display: flex;
    flex-direction: column;
    min-width: 0;
}

.upcoming-service-type {
    font-weight: 500;
    font-size: 0.875rem;
}

.upcoming-service-time {
    font-size: 0.75rem;
    color: var(--admin-text-muted);
}

/* Month Stats */
.month-stats {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 1rem;
    text-align: center;
}

.month-stat {
    display: flex;
    flex-direction: column;
}

.month-stat-value {
    font-size: 1.5rem;
    font-weight: 700;
    color: var(--admin-text);
    line-height: 1.2;
}

.month-stat-label {
    font-size: 0.75rem;
    color: var(--admin-text-muted);
}

/* Responsive */
@media (max-width: 768px) {
    .calendar-day-header {
        font-size: 0.65rem;
    }

    .calendar-cell {
        min-height: 80px;
        padding: 0.25rem;
    }

    .calendar-event {
        padding: 0.125rem 0.25rem;
        font-size: 0.65rem;
    }

    .calendar-event-time {
        display: none;
    }
}
</style>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
