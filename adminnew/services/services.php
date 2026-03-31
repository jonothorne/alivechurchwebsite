<?php
/**
 * Services Dashboard - Worship Planning
 */
$page_title = 'Services';
$current_app = 'services';
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../../includes/db-config.php';

$pdo = getDbConnection();

// Get date range for upcoming services (next 4 weeks)
$today = date('Y-m-d');
$fourWeeksOut = date('Y-m-d', strtotime('+4 weeks'));

// Check which assignment table exists (service_rota is newer, service_assignments is legacy)
$useRotaTable = false;
try {
    $pdo->query("SELECT 1 FROM service_rota LIMIT 1");
    $useRotaTable = true;
} catch (PDOException $e) {
    // Table doesn't exist, use service_assignments
}

// Fetch upcoming services with type info
if ($useRotaTable) {
    $upcomingServices = $pdo->prepare("
        SELECT s.*, st.name as type_name, st.color as type_color,
               (SELECT COUNT(*) FROM service_rota sr WHERE sr.service_id = s.id AND sr.status = 'confirmed') as confirmed_count,
               (SELECT COUNT(*) FROM service_rota sr WHERE sr.service_id = s.id AND sr.status = 'pending') as pending_count,
               (SELECT COUNT(*) FROM service_items si WHERE si.service_id = s.id) as item_count
        FROM services s
        JOIN service_types st ON s.service_type_id = st.id
        WHERE s.service_date >= ?
        ORDER BY s.service_date, s.start_time
        LIMIT 20
    ");
} else {
    $upcomingServices = $pdo->prepare("
        SELECT s.*, st.name as type_name, st.color as type_color,
               (SELECT COUNT(*) FROM service_assignments sa WHERE sa.service_id = s.id AND sa.status = 'confirmed') as confirmed_count,
               (SELECT COUNT(*) FROM service_assignments sa WHERE sa.service_id = s.id AND sa.status = 'pending') as pending_count,
               (SELECT COUNT(*) FROM service_items si WHERE si.service_id = s.id) as item_count
        FROM services s
        JOIN service_types st ON s.service_type_id = st.id
        WHERE s.service_date >= ?
        ORDER BY s.service_date, s.start_time
        LIMIT 20
    ");
}
$upcomingServices->execute([$today]);
$services = $upcomingServices->fetchAll();

// Get service types for quick add
$serviceTypes = $pdo->query("SELECT * FROM service_types WHERE is_active = 1 ORDER BY sort_order")->fetchAll();

// Get teams with member counts
$teams = $pdo->query("
    SELECT t.*,
           (SELECT COUNT(*) FROM service_team_members stm WHERE stm.team_id = t.id AND stm.is_active = 1) as member_count
    FROM service_teams t
    WHERE t.is_active = 1
    ORDER BY t.sort_order
")->fetchAll();

// Stats
$pendingCount = 0;
try {
    if ($useRotaTable) {
        $pendingCount = $pdo->query("SELECT COUNT(*) FROM service_rota WHERE status = 'pending'")->fetchColumn();
    } else {
        $pendingCount = $pdo->query("SELECT COUNT(*) FROM service_assignments WHERE status = 'pending'")->fetchColumn();
    }
} catch (PDOException $e) {
    // Table may not exist yet
}

$stats = [
    'upcoming' => count($services),
    'this_week' => $pdo->query("SELECT COUNT(*) FROM services WHERE service_date >= '$today' AND service_date < DATE_ADD('$today', INTERVAL 7 DAY)")->fetchColumn(),
    'pending_confirmations' => $pendingCount,
    'total_songs' => $pdo->query("SELECT COUNT(*) FROM songs")->fetchColumn(),
];

// Group services by week
$servicesByWeek = [];
foreach ($services as $service) {
    $weekStart = date('Y-m-d', strtotime('monday this week', strtotime($service['service_date'])));
    $weekLabel = date('M j', strtotime($weekStart)) . ' - ' . date('M j', strtotime($weekStart . ' +6 days'));
    if (!isset($servicesByWeek[$weekLabel])) {
        $servicesByWeek[$weekLabel] = [];
    }
    $servicesByWeek[$weekLabel][] = $service;
}

function getStatusBadge($status) {
    $badges = [
        'planned' => ['class' => 'admin-badge-info', 'label' => 'Planned'],
        'confirmed' => ['class' => 'admin-badge-success', 'label' => 'Confirmed'],
        'completed' => ['class' => 'admin-badge-secondary', 'label' => 'Completed'],
        'cancelled' => ['class' => 'admin-badge-danger', 'label' => 'Cancelled'],
    ];
    $badge = $badges[$status] ?? ['class' => '', 'label' => ucfirst($status)];
    return '<span class="admin-badge ' . $badge['class'] . '">' . $badge['label'] . '</span>';
}
?>

<!-- Dashboard Header -->
<div class="admin-page-header">
    <div>
        <h1 class="admin-page-title">Service Planning</h1>
        <p class="admin-page-subtitle">Plan and schedule worship services</p>
    </div>
    <div class="admin-page-actions">
        <a href="/adminnew/services/schedule" class="admin-btn admin-btn-primary">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <line x1="12" y1="5" x2="12" y2="19"></line>
                <line x1="5" y1="12" x2="19" y2="12"></line>
            </svg>
            Schedule Service
        </a>
    </div>
</div>

<!-- Stats Cards -->
<div class="services-stats-grid">
    <div class="services-stat-card">
        <div class="services-stat-icon" style="background: linear-gradient(135deg, #22c55e 0%, #16a34a 100%);">
            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect>
                <line x1="16" y1="2" x2="16" y2="6"></line>
                <line x1="8" y1="2" x2="8" y2="6"></line>
                <line x1="3" y1="10" x2="21" y2="10"></line>
            </svg>
        </div>
        <div class="services-stat-content">
            <span class="services-stat-value"><?= $stats['this_week']; ?></span>
            <span class="services-stat-label">This Week</span>
        </div>
    </div>
    <div class="services-stat-card">
        <div class="services-stat-icon" style="background: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%);">
            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path>
                <circle cx="9" cy="7" r="4"></circle>
                <path d="M23 21v-2a4 4 0 0 0-3-3.87"></path>
                <path d="M16 3.13a4 4 0 0 1 0 7.75"></path>
            </svg>
        </div>
        <div class="services-stat-content">
            <span class="services-stat-value"><?= $stats['pending_confirmations']; ?></span>
            <span class="services-stat-label">Pending Confirmations</span>
        </div>
    </div>
    <div class="services-stat-card">
        <div class="services-stat-icon" style="background: linear-gradient(135deg, #ec4899 0%, #db2777 100%);">
            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M9 18V5l12-2v13"></path>
                <circle cx="6" cy="18" r="3"></circle>
                <circle cx="18" cy="16" r="3"></circle>
            </svg>
        </div>
        <div class="services-stat-content">
            <span class="services-stat-value"><?= $stats['total_songs']; ?></span>
            <span class="services-stat-label">Songs in Library</span>
        </div>
    </div>
    <div class="services-stat-card">
        <div class="services-stat-icon" style="background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%);">
            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M12 2L2 7l10 5 10-5-10-5z"></path>
                <path d="M2 17l10 5 10-5"></path>
                <path d="M2 12l10 5 10-5"></path>
            </svg>
        </div>
        <div class="services-stat-content">
            <span class="services-stat-value"><?= count($teams); ?></span>
            <span class="services-stat-label">Active Teams</span>
        </div>
    </div>
</div>

<!-- Quick Links -->
<div class="services-quick-links">
    <a href="/adminnew/services/templates" class="services-quick-link">
        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <path d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
        </svg>
        Service Templates
    </a>
    <a href="/adminnew/services/teams" class="services-quick-link">
        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path>
            <circle cx="9" cy="7" r="4"></circle>
            <path d="M23 21v-2a4 4 0 0 0-3-3.87"></path>
            <path d="M16 3.13a4 4 0 0 1 0 7.75"></path>
        </svg>
        Manage Teams
    </a>
    <a href="/adminnew/services/songs" class="services-quick-link">
        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <path d="M9 18V5l12-2v13"></path>
            <circle cx="6" cy="18" r="3"></circle>
            <circle cx="18" cy="16" r="3"></circle>
        </svg>
        Song Library
    </a>
    <a href="/adminnew/services/types" class="services-quick-link">
        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <circle cx="12" cy="12" r="3"></circle>
            <path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1 0 2.83 2 2 0 0 1-2.83 0l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-2 2 2 2 0 0 1-2-2v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83 0 2 2 0 0 1 0-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1-2-2 2 2 0 0 1 2-2h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 0-2.83 2 2 0 0 1 2.83 0l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 2-2 2 2 0 0 1 2 2v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 0 2 2 0 0 1 0 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 2 2 2 2 0 0 1-2 2h-.09a1.65 1.65 0 0 0-1.51 1z"></path>
        </svg>
        Service Types
    </a>
    <a href="/adminnew/services/blockouts" class="services-quick-link">
        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect>
            <line x1="16" y1="2" x2="16" y2="6"></line>
            <line x1="8" y1="2" x2="8" y2="6"></line>
            <line x1="3" y1="10" x2="21" y2="10"></line>
            <line x1="9" y1="16" x2="15" y2="16"></line>
        </svg>
        Blockout Dates
    </a>
</div>

<!-- Main Content Grid -->
<div class="services-content-grid">
    <!-- Upcoming Services -->
    <div class="services-main">
        <div class="admin-card">
            <div class="admin-card-header">
                <h2 class="admin-card-title">Upcoming Services</h2>
                <a href="/adminnew/services/calendar" class="admin-btn admin-btn-sm admin-btn-secondary">View Calendar</a>
            </div>
            <div class="admin-card-body">
                <?php if (empty($services)): ?>
                    <div class="admin-empty-state">
                        <div class="admin-empty-icon">
                            <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                                <rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect>
                                <line x1="16" y1="2" x2="16" y2="6"></line>
                                <line x1="8" y1="2" x2="8" y2="6"></line>
                                <line x1="3" y1="10" x2="21" y2="10"></line>
                            </svg>
                        </div>
                        <h3 class="admin-empty-title">No upcoming services</h3>
                        <p class="admin-empty-text">Schedule your first service to get started.</p>
                        <a href="/adminnew/services/schedule" class="admin-btn admin-btn-primary">Schedule Service</a>
                    </div>
                <?php else: ?>
                    <?php foreach ($servicesByWeek as $weekLabel => $weekServices): ?>
                        <div class="services-week-group">
                            <h3 class="services-week-label"><?= $weekLabel; ?></h3>
                            <div class="services-list">
                                <?php foreach ($weekServices as $service): ?>
                                    <a href="/adminnew/services/plan/<?= $service['id']; ?>" class="service-card">
                                        <div class="service-card-date">
                                            <span class="service-day"><?= date('D', strtotime($service['service_date'])); ?></span>
                                            <span class="service-date-num"><?= date('j', strtotime($service['service_date'])); ?></span>
                                        </div>
                                        <div class="service-card-content">
                                            <div class="service-card-header">
                                                <span class="service-type-badge" style="background: <?= $service['type_color']; ?>;">
                                                    <?= htmlspecialchars($service['type_name']); ?>
                                                </span>
                                                <?= getStatusBadge($service['status']); ?>
                                            </div>
                                            <div class="service-card-title">
                                                <?= $service['title'] ? htmlspecialchars($service['title']) : date('l, F j', strtotime($service['service_date'])); ?>
                                            </div>
                                            <div class="service-card-meta">
                                                <span><?= date('g:i A', strtotime($service['start_time'])); ?></span>
                                                <span><?= $service['item_count']; ?> items</span>
                                                <span><?= $service['confirmed_count']; ?> confirmed<?= $service['pending_count'] > 0 ? ', ' . $service['pending_count'] . ' pending' : ''; ?></span>
                                            </div>
                                        </div>
                                        <div class="service-card-arrow">
                                            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                                <polyline points="9 18 15 12 9 6"></polyline>
                                            </svg>
                                        </div>
                                    </a>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Teams Sidebar -->
    <div class="services-sidebar">
        <div class="admin-card">
            <div class="admin-card-header">
                <h3 class="admin-card-title">Teams</h3>
                <a href="/adminnew/services/teams" class="admin-btn admin-btn-sm admin-btn-secondary">Manage</a>
            </div>
            <div class="admin-card-body" style="padding: 0;">
                <div class="teams-list">
                    <?php foreach ($teams as $team): ?>
                        <a href="/adminnew/services/teams/<?= $team['id']; ?>" class="team-item">
                            <div class="team-color" style="background: <?= $team['color']; ?>;"></div>
                            <div class="team-info">
                                <span class="team-name"><?= htmlspecialchars($team['name']); ?></span>
                                <span class="team-count"><?= $team['member_count']; ?> members</span>
                            </div>
                        </a>
                    <?php endforeach; ?>
                    <?php if (empty($teams)): ?>
                        <div style="padding: 1rem; text-align: center; color: var(--admin-text-muted);">
                            No teams yet
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Service Types -->
        <div class="admin-card">
            <div class="admin-card-header">
                <h3 class="admin-card-title">Service Types</h3>
            </div>
            <div class="admin-card-body" style="padding: 0;">
                <div class="service-types-list">
                    <?php foreach ($serviceTypes as $type): ?>
                        <div class="service-type-item">
                            <div class="service-type-color" style="background: <?= $type['color']; ?>;"></div>
                            <div class="service-type-info">
                                <span class="service-type-name"><?= htmlspecialchars($type['name']); ?></span>
                                <span class="service-type-schedule"><?= ucfirst($type['default_day']); ?>s at <?= date('g:i A', strtotime($type['default_time'])); ?></span>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<style <?= csp_nonce(); ?>>
/* Services Stats Grid */
.services-stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 1rem;
    margin-bottom: 1.5rem;
}

.services-stat-card {
    display: flex;
    align-items: center;
    gap: 1rem;
    padding: 1.25rem;
    background: var(--admin-card-bg);
    border-radius: var(--admin-radius-lg);
    border: 1px solid var(--admin-border);
}

.services-stat-icon {
    width: 48px;
    height: 48px;
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    flex-shrink: 0;
}

.services-stat-content {
    display: flex;
    flex-direction: column;
}

.services-stat-value {
    font-size: 1.5rem;
    font-weight: 700;
    color: var(--admin-text);
    line-height: 1.2;
}

.services-stat-label {
    font-size: 0.85rem;
    color: var(--admin-text-muted);
}

/* Quick Links */
.services-quick-links {
    display: flex;
    gap: 0.75rem;
    margin-bottom: 1.5rem;
    flex-wrap: wrap;
}

.services-quick-link {
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    padding: 0.625rem 1rem;
    background: var(--admin-card-bg);
    border: 1px solid var(--admin-border);
    border-radius: var(--admin-radius);
    color: var(--admin-text);
    text-decoration: none;
    font-size: 0.875rem;
    font-weight: 500;
    transition: all 0.15s;
}

.services-quick-link:hover {
    border-color: var(--current-app-color);
    background: color-mix(in srgb, var(--current-app-color) 5%, transparent);
}

.services-quick-link svg {
    color: var(--admin-text-muted);
}

/* Content Grid */
.services-content-grid {
    display: grid;
    grid-template-columns: 1fr 320px;
    gap: 1.5rem;
}

@media (max-width: 1024px) {
    .services-content-grid {
        grid-template-columns: 1fr;
    }
}

/* Week Groups */
.services-week-group {
    margin-bottom: 1.5rem;
}

.services-week-group:last-child {
    margin-bottom: 0;
}

.services-week-label {
    font-size: 0.75rem;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.05em;
    color: var(--admin-text-muted);
    margin-bottom: 0.75rem;
}

/* Service Cards */
.services-list {
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
}

.service-card {
    display: flex;
    align-items: center;
    gap: 1rem;
    padding: 1rem;
    background: var(--admin-bg);
    border: 1px solid var(--admin-border);
    border-radius: var(--admin-radius);
    text-decoration: none;
    color: inherit;
    transition: all 0.15s;
}

.service-card:hover {
    border-color: var(--current-app-color);
    background: color-mix(in srgb, var(--current-app-color) 3%, var(--admin-bg));
}

.service-card-date {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    width: 48px;
    height: 48px;
    background: var(--admin-card-bg);
    border-radius: var(--admin-radius);
    flex-shrink: 0;
}

.service-day {
    font-size: 0.625rem;
    font-weight: 600;
    text-transform: uppercase;
    color: var(--admin-text-muted);
    line-height: 1;
}

.service-date-num {
    font-size: 1.25rem;
    font-weight: 700;
    color: var(--admin-text);
    line-height: 1.2;
}

.service-card-content {
    flex: 1;
    min-width: 0;
}

.service-card-header {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    margin-bottom: 0.25rem;
}

.service-type-badge {
    display: inline-block;
    padding: 0.125rem 0.5rem;
    border-radius: 4px;
    font-size: 0.75rem;
    font-weight: 500;
    color: white;
}

.service-card-title {
    font-weight: 600;
    color: var(--admin-text);
    margin-bottom: 0.25rem;
}

.service-card-meta {
    display: flex;
    gap: 1rem;
    font-size: 0.8rem;
    color: var(--admin-text-muted);
}

.service-card-arrow {
    color: var(--admin-text-muted);
    flex-shrink: 0;
}

/* Teams List */
.teams-list {
    display: flex;
    flex-direction: column;
}

.team-item {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    padding: 0.875rem 1rem;
    text-decoration: none;
    color: inherit;
    border-bottom: 1px solid var(--admin-border);
    transition: background 0.15s;
}

.team-item:last-child {
    border-bottom: none;
}

.team-item:hover {
    background: var(--admin-bg);
}

.team-color {
    width: 12px;
    height: 12px;
    border-radius: 50%;
    flex-shrink: 0;
}

.team-info {
    display: flex;
    flex-direction: column;
}

.team-name {
    font-weight: 500;
    color: var(--admin-text);
}

.team-count {
    font-size: 0.8rem;
    color: var(--admin-text-muted);
}

/* Service Types List */
.service-types-list {
    display: flex;
    flex-direction: column;
}

.service-type-item {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    padding: 0.875rem 1rem;
    border-bottom: 1px solid var(--admin-border);
}

.service-type-item:last-child {
    border-bottom: none;
}

.service-type-color {
    width: 12px;
    height: 12px;
    border-radius: 3px;
    flex-shrink: 0;
}

.service-type-info {
    display: flex;
    flex-direction: column;
}

.service-type-name {
    font-weight: 500;
    color: var(--admin-text);
}

.service-type-schedule {
    font-size: 0.8rem;
    color: var(--admin-text-muted);
}
</style>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
