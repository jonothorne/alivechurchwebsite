<?php
/**
 * Music Stand - Home Page
 * Shows list of upcoming and recent services
 */

// Get upcoming services (next 4 weeks)
$stmt = $pdo->prepare("
    SELECT
        s.*,
        st.name as type_name,
        st.color as type_color,
        (SELECT COUNT(*) FROM service_items si WHERE si.service_id = s.id AND si.item_type = 'song') as song_count
    FROM services s
    JOIN service_types st ON s.service_type_id = st.id
    WHERE s.service_date >= CURDATE() - INTERVAL 7 DAY
    AND s.service_date <= CURDATE() + INTERVAL 28 DAY
    AND s.status IN ('planned', 'confirmed')
    ORDER BY s.service_date ASC, s.start_time ASC
    LIMIT 20
");
$stmt->execute();
$services = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Group services by upcoming vs past
$upcoming = [];
$recent = [];
$today = date('Y-m-d');

foreach ($services as $service) {
    if ($service['service_date'] >= $today) {
        $upcoming[] = $service;
    } else {
        $recent[] = $service;
    }
}

// Get user's name
$userName = $user['first_name'] ?? $user['full_name'] ?? 'there';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover, user-scalable=no">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <meta name="theme-color" content="#1a1a2e">
    <title>Music Stand - Alive Church</title>
    <link rel="manifest" href="/musicstand/manifest.json">
    <link rel="apple-touch-icon" href="/musicstand/assets/icons/icon-192.svg">
    <link rel="stylesheet" href="/musicstand/assets/css/app.css">
</head>
<body>
    <div class="app-container">
        <header class="app-header">
            <button type="button" class="hamburger-btn" onclick="toggleSidebar()">
                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M3 12h18M3 6h18M3 18h18"/>
                </svg>
            </button>
            <h1>Music Stand</h1>
            <div style="width: 40px;"></div>
        </header>

        <main class="app-content">
            <div class="services-list">
                <?php if (!empty($upcoming)): ?>
                    <h2 class="section-title">Upcoming Services</h2>
                    <?php foreach ($upcoming as $service): ?>
                        <?php
                        $date = new DateTime($service['service_date']);
                        $isToday = $service['service_date'] === $today;
                        $isTomorrow = $service['service_date'] === date('Y-m-d', strtotime('+1 day'));
                        ?>
                        <a href="/musicstand/service/<?= $service['id']; ?>" class="service-card">
                            <div class="service-date" style="<?= $isToday ? 'background: #34d399;' : ''; ?>">
                                <span class="day"><?= $date->format('j'); ?></span>
                                <span class="month"><?= $date->format('M'); ?></span>
                            </div>
                            <div class="service-info">
                                <div class="service-title">
                                    <?php if ($isToday): ?>
                                        <span style="color: #34d399;">Today</span> &middot;
                                    <?php elseif ($isTomorrow): ?>
                                        Tomorrow &middot;
                                    <?php else: ?>
                                        <?= $date->format('l'); ?> &middot;
                                    <?php endif; ?>
                                    <?= htmlspecialchars($service['title'] ?: ucwords(str_replace('-', ' ', $service['type_name']))); ?>
                                </div>
                                <div class="service-meta">
                                    <?php if ($service['start_time']): ?>
                                        <?= date('g:ia', strtotime($service['start_time'])); ?>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <?php if ($service['song_count'] > 0): ?>
                                <div class="service-songs-count">
                                    <?= $service['song_count']; ?> song<?= $service['song_count'] !== 1 ? 's' : ''; ?>
                                </div>
                            <?php endif; ?>
                            <svg class="arrow-icon" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M9 18l6-6-6-6"/>
                            </svg>
                        </a>
                    <?php endforeach; ?>
                <?php endif; ?>

                <?php if (!empty($recent)): ?>
                    <h2 class="section-title" style="margin-top: 2rem;">Recent Services</h2>
                    <?php foreach ($recent as $service): ?>
                        <?php $date = new DateTime($service['service_date']); ?>
                        <a href="/musicstand/service/<?= $service['id']; ?>" class="service-card" style="opacity: 0.7;">
                            <div class="service-date" style="background: var(--ms-surface);">
                                <span class="day"><?= $date->format('j'); ?></span>
                                <span class="month"><?= $date->format('M'); ?></span>
                            </div>
                            <div class="service-info">
                                <div class="service-title">
                                    <?= $date->format('l'); ?> &middot;
                                    <?= htmlspecialchars($service['title'] ?: ucwords(str_replace('-', ' ', $service['type_name']))); ?>
                                </div>
                                <div class="service-meta">
                                    <?php if ($service['start_time']): ?>
                                        <?= date('g:ia', strtotime($service['start_time'])); ?>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <?php if ($service['song_count'] > 0): ?>
                                <div class="service-songs-count">
                                    <?= $service['song_count']; ?> song<?= $service['song_count'] !== 1 ? 's' : ''; ?>
                                </div>
                            <?php endif; ?>
                            <svg class="arrow-icon" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M9 18l6-6-6-6"/>
                            </svg>
                        </a>
                    <?php endforeach; ?>
                <?php endif; ?>

                <?php if (empty($upcoming) && empty($recent)): ?>
                    <div class="empty-state">
                        <div class="empty-icon">📋</div>
                        <h2>No Services Found</h2>
                        <p>There are no planned services in the next few weeks.</p>
                    </div>
                <?php endif; ?>
            </div>
        </main>
    </div>

    <!-- Sidebar Navigation -->
    <div class="sidebar-overlay" id="sidebar-overlay" onclick="toggleSidebar()"></div>
    <nav class="sidebar" id="sidebar">
        <div class="sidebar-header">
            <h2>Music Stand</h2>
            <button type="button" class="close-btn" onclick="toggleSidebar()">
                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M18 6L6 18M6 6l12 12"/>
                </svg>
            </button>
        </div>
        <div class="sidebar-content">
            <a href="/musicstand/" class="sidebar-link active">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/>
                    <polyline points="9 22 9 12 15 12 15 22"/>
                </svg>
                <span>Services</span>
            </a>
            <a href="/musicstand/library" class="sidebar-link">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"/>
                    <path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z"/>
                </svg>
                <span>Song Library</span>
            </a>
        </div>
        <div class="sidebar-footer">
            <div class="sidebar-user">
                <div class="user-avatar"><?= strtoupper(substr($user['first_name'] ?? $user['email'], 0, 1)); ?></div>
                <span><?= htmlspecialchars($user['first_name'] ?? $user['email']); ?></span>
            </div>
        </div>
    </nav>

    <script src="/musicstand/assets/js/app.js"></script>
</body>
</html>
