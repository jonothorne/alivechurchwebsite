<?php
$page_title = 'Geographic Analytics';
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../../includes/db-config.php';
require_once __DIR__ . '/../../includes/Analytics.php';
require_once __DIR__ . '/../../includes/GeoIP.php';

$pdo = getDbConnection();
$analytics = new Analytics($pdo);

// Get selected time period
$period = $_GET['period'] ?? 'month';
$validPeriods = ['today', 'week', 'month', 'year', 'all'];
if (!in_array($period, $validPeriods)) {
    $period = 'month';
}

// Fetch geographic data
$countriesData = $analytics->getVisitorsByCountry($period, 20);
$citiesData = $analytics->getVisitorsByCity($period, 20);
$mapLocations = $analytics->getVisitorLocationsForMap($period, 100);

// Calculate totals
$totalVisitors = array_sum(array_column($countriesData, 'unique_visitors'));
?>

<?php require_once __DIR__ . '/../includes/analytics-subnav.php'; ?>

<!-- Period Filter -->
<div class="analytics-header" style="margin-bottom: 1.5rem;">
    <h2 style="margin: 0;">Geographic</h2>
    <div class="admin-filter-tabs" style="margin: 0;">
        <a href="?period=today" class="admin-filter-tab <?= $period === 'today' ? 'active' : ''; ?>">Today</a>
        <a href="?period=week" class="admin-filter-tab <?= $period === 'week' ? 'active' : ''; ?>">7d</a>
        <a href="?period=month" class="admin-filter-tab <?= $period === 'month' ? 'active' : ''; ?>">30d</a>
        <a href="?period=year" class="admin-filter-tab <?= $period === 'year' ? 'active' : ''; ?>">Year</a>
        <a href="?period=all" class="admin-filter-tab <?= $period === 'all' ? 'active' : ''; ?>">All</a>
    </div>
</div>

<!-- Quick Stats -->
<div class="analytics-metrics" style="margin-bottom: 1.5rem;">
    <div class="analytics-metric">
        <div class="analytics-metric-value"><?= count($countriesData); ?></div>
        <div class="analytics-metric-label">Countries</div>
    </div>
    <div class="analytics-metric">
        <div class="analytics-metric-value"><?= count($citiesData); ?></div>
        <div class="analytics-metric-label">Cities</div>
    </div>
    <div class="analytics-metric">
        <div class="analytics-metric-value"><?= number_format($totalVisitors); ?></div>
        <div class="analytics-metric-label">Total Visitors</div>
    </div>
</div>

<div class="analytics-grid">
    <!-- Countries -->
    <div class="analytics-col">
        <div class="admin-card">
            <div class="admin-card-header">
                <h3>Visitors by Country</h3>
            </div>
            <?php if (empty($countriesData)): ?>
                <div class="admin-empty-state">
                    <p>No geographic data yet. Data will appear as visitors browse your site.</p>
                </div>
            <?php else: ?>
                <div class="analytics-geo-list">
                    <?php foreach ($countriesData as $country): ?>
                        <div class="analytics-geo-item">
                            <div class="analytics-geo-info">
                                <span class="analytics-geo-flag"><?= GeoIP::getCountryFlag($country['country_code']); ?></span>
                                <span class="analytics-geo-name"><?= htmlspecialchars($country['country_name'] ?: $country['country_code']); ?></span>
                            </div>
                            <div class="analytics-geo-stats">
                                <span class="analytics-geo-visitors"><?= number_format($country['unique_visitors']); ?></span>
                                <span class="analytics-geo-percent"><?= $totalVisitors > 0 ? round(($country['unique_visitors'] / $totalVisitors) * 100, 1) : 0; ?>%</span>
                            </div>
                            <div class="analytics-geo-bar">
                                <div class="analytics-geo-bar-fill" style="width: <?= $totalVisitors > 0 ? ($country['unique_visitors'] / $totalVisitors) * 100 : 0; ?>%;"></div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Cities -->
    <div class="analytics-col">
        <div class="admin-card">
            <div class="admin-card-header">
                <h3>Visitors by City</h3>
            </div>
            <?php if (empty($citiesData)): ?>
                <div class="admin-empty-state">
                    <p>No city data yet.</p>
                </div>
            <?php else: ?>
                <div class="analytics-geo-list">
                    <?php
                    $maxCityVisitors = $citiesData[0]['unique_visitors'] ?? 1;
                    foreach ($citiesData as $city):
                    ?>
                        <div class="analytics-geo-item">
                            <div class="analytics-geo-info">
                                <span class="analytics-geo-flag"><?= GeoIP::getCountryFlag($city['country_code']); ?></span>
                                <span class="analytics-geo-name">
                                    <?= htmlspecialchars($city['city']); ?>
                                    <small class="admin-muted"><?= htmlspecialchars($city['region'] ? $city['region'] . ', ' : ''); ?><?= htmlspecialchars($city['country_name']); ?></small>
                                </span>
                            </div>
                            <div class="analytics-geo-stats">
                                <span class="analytics-geo-visitors"><?= number_format($city['unique_visitors']); ?></span>
                            </div>
                            <div class="analytics-geo-bar">
                                <div class="analytics-geo-bar-fill" style="width: <?= ($city['unique_visitors'] / $maxCityVisitors) * 100; ?>%;"></div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Map Visualization -->
<?php if (!empty($mapLocations)): ?>
<div class="admin-card" style="margin-top: 1.5rem;">
    <div class="admin-card-header">
        <h3>Visitor Map</h3>
    </div>
    <div class="analytics-map-container" id="visitorMap">
        <div class="analytics-map-placeholder">
            <p>Map visualization requires internet connection</p>
        </div>
    </div>
</div>

<!-- Leaflet Map -->
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script <?= csp_nonce(); ?>>
document.addEventListener('DOMContentLoaded', function() {
    const mapContainer = document.getElementById('visitorMap');
    mapContainer.innerHTML = '';
    mapContainer.style.height = '400px';

    const map = L.map('visitorMap').setView([52.6309, 1.2974], 6); // Norwich, UK as center

    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        attribution: '&copy; OpenStreetMap contributors'
    }).addTo(map);

    const locations = <?= json_encode($mapLocations); ?>;
    const maxVisitors = Math.max(...locations.map(l => l.visitors));

    locations.forEach(function(loc) {
        if (loc.latitude && loc.longitude) {
            const radius = Math.max(5, Math.min(30, (loc.visitors / maxVisitors) * 30));
            L.circleMarker([loc.latitude, loc.longitude], {
                radius: radius,
                fillColor: '#4b2679',
                color: '#fff',
                weight: 2,
                opacity: 1,
                fillOpacity: 0.7
            })
            .bindPopup('<strong>' + loc.city + '</strong><br>' + loc.visitors + ' visitors')
            .addTo(map);
        }
    });

    // Fit bounds to markers if we have data
    if (locations.length > 0) {
        const validLocations = locations.filter(l => l.latitude && l.longitude);
        if (validLocations.length > 0) {
            const bounds = L.latLngBounds(validLocations.map(l => [l.latitude, l.longitude]));
            map.fitBounds(bounds, { padding: [20, 20] });
        }
    }
});
</script>
<?php endif; ?>

<style <?= csp_nonce(); ?>>
.analytics-geo-list {
    display: flex;
    flex-direction: column;
    gap: 0.75rem;
}
.analytics-geo-item {
    display: grid;
    grid-template-columns: 1fr auto auto;
    align-items: center;
    gap: 1rem;
    padding: 0.75rem 0;
    border-bottom: 1px solid var(--color-border);
}
.analytics-geo-item:last-child {
    border-bottom: none;
}
.analytics-geo-info {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    min-width: 0;
}
.analytics-geo-flag {
    font-size: 1.25rem;
    flex-shrink: 0;
}
.analytics-geo-name {
    font-weight: 500;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}
.analytics-geo-name small {
    display: block;
    font-weight: 400;
    font-size: 0.75rem;
}
.analytics-geo-stats {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    text-align: right;
}
.analytics-geo-visitors {
    font-weight: 600;
    font-size: 0.9rem;
}
.analytics-geo-percent {
    color: var(--color-text-muted);
    font-size: 0.8rem;
    min-width: 3rem;
}
.analytics-geo-bar {
    width: 80px;
    height: 6px;
    background: var(--color-border);
    border-radius: 3px;
    overflow: hidden;
}
.analytics-geo-bar-fill {
    height: 100%;
    background: linear-gradient(90deg, var(--color-purple), var(--color-magenta));
    border-radius: 3px;
    transition: width 0.3s ease;
}
.analytics-map-container {
    border-radius: var(--radius-lg);
    overflow: hidden;
    background: var(--color-bg);
}
.analytics-map-placeholder {
    display: flex;
    align-items: center;
    justify-content: center;
    height: 300px;
    color: var(--color-text-muted);
}
@media (max-width: 768px) {
    .analytics-geo-item {
        grid-template-columns: 1fr auto;
    }
    .analytics-geo-bar {
        display: none;
    }
}
</style>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
