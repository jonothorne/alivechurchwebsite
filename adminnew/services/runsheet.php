<?php
/**
 * Service Run Sheet / Flow View
 * Detailed run sheet with timing, notes, and cues
 */
$page_title = 'Run Sheet';
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

// Fetch service with type
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

// Fetch service items with running totals
$itemsStmt = $pdo->prepare("
    SELECT
        si.*,
        s.title as song_title,
        s.artist as song_artist,
        s.default_key,
        COALESCE(si.planned_duration, si.duration_minutes, 5) as effective_duration,
        (SELECT SUM(COALESCE(si2.planned_duration, si2.duration_minutes, 5))
         FROM service_items si2
         WHERE si2.service_id = si.service_id
         AND si2.position < si.position) as cumulative_before
    FROM service_items si
    LEFT JOIN songs s ON si.song_id = s.id
    WHERE si.service_id = ?
    ORDER BY si.position
");
$itemsStmt->execute([$serviceId]);
$items = $itemsStmt->fetchAll();

// Calculate total service time
$totalMinutes = array_sum(array_column($items, 'effective_duration'));

// Calculate estimated end time
$serviceStartTime = new DateTime($service['service_date'] . ' ' . $service['start_time']);
$estimatedEndTime = clone $serviceStartTime;
$estimatedEndTime->modify("+{$totalMinutes} minutes");

$serviceDate = new DateTime($service['service_date']);
$formattedDate = $serviceDate->format('l, F j, Y');
$startTime = date('g:i A', strtotime($service['start_time']));

// Item type icons
$itemTypeIcons = [
    'song' => '<path d="M9 18V5l12-2v13"></path><circle cx="6" cy="18" r="3"></circle><circle cx="18" cy="16" r="3"></circle>',
    'prayer' => '<path d="M12 2L2 7l10 5 10-5-10-5z"></path><path d="M2 17l10 5 10-5M2 12l10 5 10-5"></path>',
    'announcement' => '<path d="M21.3 12.23h-3.48l-4.51-4.51C11.6 5.96 9 6.88 9 8.98v7.02c0 2.1 2.6 3.02 4.31 1.26l4.51-4.51h3.48c.38 0 .7-.32.7-.76v-1.2c0-.44-.32-.76-.7-.76z"></path><path d="M9 9v6"></path>',
    'offering' => '<circle cx="12" cy="12" r="10"></circle><path d="M12 6v6l4 2"></path>',
    'sermon' => '<path d="M2 3h6a4 4 0 0 1 4 4v14a3 3 0 0 0-3-3H2z"></path><path d="M22 3h-6a4 4 0 0 0-4 4v14a3 3 0 0 1 3-3h7z"></path>',
    'video' => '<polygon points="23 7 16 12 23 17 23 7"></polygon><rect x="1" y="5" width="15" height="14" rx="2" ry="2"></rect>',
    'scripture' => '<path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"></path><path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z"></path>',
    'communion' => '<path d="M18 8h1a4 4 0 0 1 0 8h-1M2 8h16v9a4 4 0 0 1-4 4H6a4 4 0 0 1-4-4V8z"></path>',
    'other' => '<circle cx="12" cy="12" r="10"></circle><line x1="12" y1="8" x2="12" y2="12"></line><line x1="12" y1="16" x2="12.01" y2="16"></line>'
];

$itemTypeLabels = [
    'song' => 'Song',
    'scripture' => 'Scripture',
    'prayer' => 'Prayer',
    'announcement' => 'Announcements',
    'sermon' => 'Sermon',
    'offering' => 'Offering',
    'communion' => 'Communion',
    'video' => 'Video',
    'other' => 'Other'
];
?>

<div class="runsheet-container no-print">
    <!-- Header -->
    <div class="admin-page-header">
        <div>
            <div class="plan-header-meta">
                <span class="service-type-badge" style="background: <?= $service['type_color']; ?>;">
                    <?= htmlspecialchars($service['type_name']); ?>
                </span>
            </div>
            <h1 class="admin-page-title">
                <?= $service['title'] ? htmlspecialchars($service['title']) : $formattedDate; ?>
            </h1>
            <p class="admin-page-subtitle">
                <?= $formattedDate; ?> at <?= $startTime; ?>
                <span class="text-muted">• Est. <?= $totalMinutes; ?> min (ends <?= $estimatedEndTime->format('g:i A'); ?>)</span>
            </p>
        </div>
        <div class="admin-page-actions">
            <a href="/adminnew/services/plan/<?= $serviceId; ?>" class="admin-btn admin-btn-secondary">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <line x1="19" y1="12" x2="5" y2="12"></line>
                    <polyline points="12 19 5 12 12 5"></polyline>
                </svg>
                Back to Plan
            </a>
            <a href="/adminnew/services/live/<?= $serviceId; ?>" class="admin-btn admin-btn-primary">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <circle cx="12" cy="12" r="10"></circle>
                    <polygon points="10 8 16 12 10 16 10 8"></polygon>
                </svg>
                Live Mode
            </a>
            <button onclick="window.print()" class="admin-btn admin-btn-secondary">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <polyline points="6 9 6 2 18 2 18 9"></polyline>
                    <path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"></path>
                    <rect x="6" y="14" width="12" height="8"></rect>
                </svg>
                Print
            </button>
        </div>
    </div>

    <!-- View Options -->
    <div class="runsheet-options">
        <div class="runsheet-view-toggle">
            <button class="view-toggle-btn active" data-view="full" onclick="switchView('full')">Full View</button>
            <button class="view-toggle-btn" data-view="compact" onclick="switchView('compact')">Compact</button>
            <button class="view-toggle-btn" data-view="tech" onclick="switchView('tech')">Tech Only</button>
        </div>
        <div class="runsheet-filters">
            <label class="runsheet-checkbox">
                <input type="checkbox" id="show-times" checked onchange="toggleTimes()">
                <span>Show Times</span>
            </label>
            <label class="runsheet-checkbox">
                <input type="checkbox" id="show-notes" checked onchange="toggleNotes()">
                <span>Show Notes</span>
            </label>
        </div>
    </div>
</div>

<!-- Run Sheet -->
<div class="runsheet-sheet">
    <!-- Print Header -->
    <div class="print-only runsheet-print-header">
        <h1><?= $service['title'] ? htmlspecialchars($service['title']) : $formattedDate; ?></h1>
        <div class="print-header-meta">
            <span><?= htmlspecialchars($service['type_name']); ?></span>
            <span><?= $formattedDate; ?></span>
            <span><?= $startTime; ?></span>
            <span>Est. Duration: <?= $totalMinutes; ?> minutes</span>
        </div>
    </div>

    <?php if (empty($items)): ?>
        <div class="admin-empty-state">
            <p>No items in this service yet.</p>
            <a href="/adminnew/services/plan/<?= $serviceId; ?>" class="admin-btn admin-btn-primary">Add Items</a>
        </div>
    <?php else: ?>
        <div class="runsheet-timeline">
            <?php
            $currentTime = clone $serviceStartTime;
            foreach ($items as $index => $item):
                $itemStartTime = clone $currentTime;
                $duration = (int)$item['effective_duration'];
                $currentTime->modify("+{$duration} minutes");
                $itemEndTime = clone $currentTime;
            ?>
                <div class="runsheet-item" data-item-id="<?= $item['id']; ?>">
                    <!-- Time Column -->
                    <div class="runsheet-time-col">
                        <div class="runsheet-time-start"><?= $itemStartTime->format('g:i A'); ?></div>
                        <div class="runsheet-duration"><?= $duration; ?> min</div>
                        <div class="runsheet-time-end text-muted"><?= $itemEndTime->format('g:i'); ?></div>
                    </div>

                    <!-- Item Content -->
                    <div class="runsheet-item-content">
                        <!-- Item Header -->
                        <div class="runsheet-item-header">
                            <div class="runsheet-item-number"><?= $index + 1; ?></div>
                            <div class="runsheet-item-icon">
                                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <?= $itemTypeIcons[$item['item_type']] ?? $itemTypeIcons['other']; ?>
                                </svg>
                            </div>
                            <div class="runsheet-item-title-section">
                                <div class="runsheet-item-type"><?= $itemTypeLabels[$item['item_type']] ?? ucfirst($item['item_type']); ?></div>
                                <div class="runsheet-item-title">
                                    <?php if ($item['song_id'] && $item['song_title']): ?>
                                        <strong><?= htmlspecialchars($item['song_title']); ?></strong>
                                        <?php if ($item['song_artist']): ?>
                                            <span class="text-muted">by <?= htmlspecialchars($item['song_artist']); ?></span>
                                        <?php endif; ?>
                                    <?php elseif ($item['title']): ?>
                                        <strong><?= htmlspecialchars($item['title']); ?></strong>
                                    <?php else: ?>
                                        <span class="text-muted">Untitled</span>
                                    <?php endif; ?>
                                </div>
                                <?php if ($item['presenter']): ?>
                                    <div class="runsheet-presenter">
                                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                            <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path>
                                            <circle cx="12" cy="7" r="4"></circle>
                                        </svg>
                                        <?= htmlspecialchars($item['presenter']); ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                            <?php if ($item['item_type'] === 'song' && $item['song_key']): ?>
                                <div class="runsheet-key-badge">Key: <?= htmlspecialchars($item['song_key']); ?></div>
                            <?php endif; ?>
                        </div>

                        <!-- Notes Section -->
                        <div class="runsheet-notes-section">
                            <?php if ($item['notes']): ?>
                                <div class="runsheet-note runsheet-note-general">
                                    <div class="runsheet-note-label">
                                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                            <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path>
                                            <polyline points="14 2 14 8 20 8"></polyline>
                                        </svg>
                                        Notes
                                    </div>
                                    <div class="runsheet-note-text"><?= nl2br(htmlspecialchars($item['notes'])); ?></div>
                                </div>
                            <?php endif; ?>

                            <?php if ($item['worship_notes']): ?>
                                <div class="runsheet-note runsheet-note-worship">
                                    <div class="runsheet-note-label">
                                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                            <path d="M9 18V5l12-2v13"></path>
                                            <circle cx="6" cy="18" r="3"></circle>
                                            <circle cx="18" cy="16" r="3"></circle>
                                        </svg>
                                        Worship Team
                                    </div>
                                    <div class="runsheet-note-text"><?= nl2br(htmlspecialchars($item['worship_notes'])); ?></div>
                                </div>
                            <?php endif; ?>

                            <?php if ($item['tech_notes']): ?>
                                <div class="runsheet-note runsheet-note-tech">
                                    <div class="runsheet-note-label">
                                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                            <rect x="2" y="7" width="20" height="14" rx="2" ry="2"></rect>
                                            <path d="M16 21V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v16"></path>
                                        </svg>
                                        Tech / AV
                                    </div>
                                    <div class="runsheet-note-text"><?= nl2br(htmlspecialchars($item['tech_notes'])); ?></div>
                                </div>
                            <?php endif; ?>

                            <?php if ($item['transition_notes']): ?>
                                <div class="runsheet-note runsheet-note-transition">
                                    <div class="runsheet-note-label">
                                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                            <polyline points="13 17 18 12 13 7"></polyline>
                                            <polyline points="6 17 11 12 6 7"></polyline>
                                        </svg>
                                        Transition
                                    </div>
                                    <div class="runsheet-note-text"><?= nl2br(htmlspecialchars($item['transition_notes'])); ?></div>
                                </div>
                            <?php endif; ?>
                        </div>

                        <?php if ($item['video_url'] || $item['slides_url']): ?>
                            <div class="runsheet-links">
                                <?php if ($item['video_url']): ?>
                                    <a href="<?= htmlspecialchars($item['video_url']); ?>" target="_blank" class="runsheet-link">
                                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                            <polygon points="23 7 16 12 23 17 23 7"></polygon>
                                            <rect x="1" y="5" width="15" height="14" rx="2" ry="2"></rect>
                                        </svg>
                                        Video Link
                                    </a>
                                <?php endif; ?>
                                <?php if ($item['slides_url']): ?>
                                    <a href="<?= htmlspecialchars($item['slides_url']); ?>" target="_blank" class="runsheet-link">
                                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                            <path d="M13 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V9z"></path>
                                            <polyline points="13 2 13 9 20 9"></polyline>
                                        </svg>
                                        Slides
                                    </a>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <!-- Summary Footer -->
        <div class="runsheet-summary no-print">
            <div class="runsheet-summary-stat">
                <span class="runsheet-summary-label">Total Items:</span>
                <span class="runsheet-summary-value"><?= count($items); ?></span>
            </div>
            <div class="runsheet-summary-stat">
                <span class="runsheet-summary-label">Total Duration:</span>
                <span class="runsheet-summary-value"><?= $totalMinutes; ?> minutes</span>
            </div>
            <div class="runsheet-summary-stat">
                <span class="runsheet-summary-label">Estimated End:</span>
                <span class="runsheet-summary-value"><?= $estimatedEndTime->format('g:i A'); ?></span>
            </div>
        </div>
    <?php endif; ?>
</div>

<style <?= csp_nonce(); ?>>
/* Run Sheet Container */
.runsheet-container {
    margin-bottom: 2rem;
}

/* View Options */
.runsheet-options {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 1rem;
    background: var(--admin-card-bg);
    border-radius: var(--admin-radius-lg);
    border: 1px solid var(--admin-border);
    margin-bottom: 1.5rem;
}

.runsheet-view-toggle {
    display: flex;
    gap: 0.5rem;
    background: var(--admin-bg);
    padding: 0.25rem;
    border-radius: var(--admin-radius);
}

.view-toggle-btn {
    padding: 0.5rem 1rem;
    background: transparent;
    border: none;
    border-radius: var(--admin-radius);
    font-size: 0.875rem;
    font-weight: 500;
    color: var(--admin-text-muted);
    cursor: pointer;
    transition: all 0.15s;
}

.view-toggle-btn:hover {
    color: var(--admin-text);
}

.view-toggle-btn.active {
    background: var(--admin-card-bg);
    color: var(--current-app-color);
    box-shadow: 0 1px 3px rgba(0,0,0,0.1);
}

.runsheet-filters {
    display: flex;
    gap: 1rem;
}

.runsheet-checkbox {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    cursor: pointer;
    font-size: 0.875rem;
    color: var(--admin-text);
}

.runsheet-checkbox input[type="checkbox"] {
    width: 16px;
    height: 16px;
    cursor: pointer;
}

/* Run Sheet Timeline */
.runsheet-sheet {
    background: var(--admin-card-bg);
    border-radius: var(--admin-radius-lg);
    border: 1px solid var(--admin-border);
    padding: 2rem;
}

.runsheet-timeline {
    display: flex;
    flex-direction: column;
    gap: 1.5rem;
}

/* Run Sheet Item */
.runsheet-item {
    display: grid;
    grid-template-columns: 100px 1fr;
    gap: 2rem;
    padding: 1.5rem;
    background: var(--admin-bg);
    border-radius: var(--admin-radius);
    border: 2px solid var(--admin-border);
    transition: all 0.2s;
}

.runsheet-item:hover {
    border-color: var(--current-app-color);
    box-shadow: 0 2px 8px rgba(0,0,0,0.05);
}

/* Time Column */
.runsheet-time-col {
    display: flex;
    flex-direction: column;
    align-items: center;
    padding: 0.5rem;
    background: var(--admin-card-bg);
    border-radius: var(--admin-radius);
    border: 1px solid var(--admin-border);
}

.runsheet-time-start {
    font-size: 1.125rem;
    font-weight: 700;
    color: var(--admin-text);
    margin-bottom: 0.25rem;
}

.runsheet-duration {
    font-size: 0.75rem;
    font-weight: 600;
    color: var(--current-app-color);
    background: color-mix(in srgb, var(--current-app-color) 10%, transparent);
    padding: 0.25rem 0.5rem;
    border-radius: 4px;
    margin-bottom: 0.25rem;
}

.runsheet-time-end {
    font-size: 0.75rem;
}

/* Item Content */
.runsheet-item-content {
    display: flex;
    flex-direction: column;
    gap: 1rem;
}

.runsheet-item-header {
    display: flex;
    align-items: flex-start;
    gap: 0.75rem;
}

.runsheet-item-number {
    width: 32px;
    height: 32px;
    display: flex;
    align-items: center;
    justify-content: center;
    background: var(--admin-card-bg);
    border: 2px solid var(--admin-border);
    border-radius: 50%;
    font-weight: 700;
    color: var(--admin-text-muted);
    flex-shrink: 0;
}

.runsheet-item-icon {
    width: 40px;
    height: 40px;
    display: flex;
    align-items: center;
    justify-content: center;
    background: color-mix(in srgb, var(--current-app-color) 10%, transparent);
    border-radius: var(--admin-radius);
    color: var(--current-app-color);
    flex-shrink: 0;
}

.runsheet-item-title-section {
    flex: 1;
    min-width: 0;
}

.runsheet-item-type {
    font-size: 0.7rem;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.05em;
    color: var(--current-app-color);
    margin-bottom: 0.25rem;
}

.runsheet-item-title {
    font-size: 1.125rem;
    line-height: 1.4;
    color: var(--admin-text);
    margin-bottom: 0.25rem;
}

.runsheet-presenter {
    display: flex;
    align-items: center;
    gap: 0.375rem;
    font-size: 0.875rem;
    color: var(--admin-text-muted);
    margin-top: 0.25rem;
}

.runsheet-key-badge {
    padding: 0.375rem 0.75rem;
    background: var(--admin-card-bg);
    border: 2px solid var(--admin-border);
    border-radius: var(--admin-radius);
    font-weight: 700;
    font-size: 0.875rem;
    color: var(--current-app-color);
    flex-shrink: 0;
}

/* Notes Section */
.runsheet-notes-section {
    display: flex;
    flex-direction: column;
    gap: 0.75rem;
}

.runsheet-note {
    padding: 0.75rem 1rem;
    border-radius: var(--admin-radius);
    border-left: 4px solid;
}

.runsheet-note-general {
    background: color-mix(in srgb, #6b7280 5%, transparent);
    border-left-color: #6b7280;
}

.runsheet-note-worship {
    background: color-mix(in srgb, #ec4899 5%, transparent);
    border-left-color: #ec4899;
}

.runsheet-note-tech {
    background: color-mix(in srgb, #3b82f6 5%, transparent);
    border-left-color: #3b82f6;
}

.runsheet-note-transition {
    background: color-mix(in srgb, #f59e0b 5%, transparent);
    border-left-color: #f59e0b;
}

.runsheet-note-label {
    display: flex;
    align-items: center;
    gap: 0.375rem;
    font-size: 0.7rem;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.05em;
    color: var(--admin-text-muted);
    margin-bottom: 0.375rem;
}

.runsheet-note-text {
    font-size: 0.875rem;
    line-height: 1.5;
    color: var(--admin-text);
}

/* Links */
.runsheet-links {
    display: flex;
    gap: 0.75rem;
    flex-wrap: wrap;
}

.runsheet-link {
    display: inline-flex;
    align-items: center;
    gap: 0.375rem;
    padding: 0.375rem 0.75rem;
    background: var(--admin-card-bg);
    border: 1px solid var(--admin-border);
    border-radius: var(--admin-radius);
    font-size: 0.875rem;
    color: var(--admin-text);
    text-decoration: none;
    transition: all 0.15s;
}

.runsheet-link:hover {
    border-color: var(--current-app-color);
    color: var(--current-app-color);
}

/* Summary */
.runsheet-summary {
    display: flex;
    justify-content: center;
    gap: 3rem;
    padding: 1.5rem;
    margin-top: 2rem;
    border-top: 2px solid var(--admin-border);
}

.runsheet-summary-stat {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 0.25rem;
}

.runsheet-summary-label {
    font-size: 0.75rem;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.05em;
    color: var(--admin-text-muted);
}

.runsheet-summary-value {
    font-size: 1.25rem;
    font-weight: 700;
    color: var(--admin-text);
}

/* Compact View */
.view-compact .runsheet-item {
    grid-template-columns: 80px 1fr;
    gap: 1rem;
    padding: 1rem;
}

.view-compact .runsheet-notes-section {
    display: none;
}

.view-compact .runsheet-item-title {
    font-size: 1rem;
}

/* Tech Only View */
.view-tech .runsheet-note-general,
.view-tech .runsheet-note-worship,
.view-tech .runsheet-note-transition {
    display: none;
}

/* Hidden Times/Notes */
.hide-times .runsheet-time-col {
    display: none;
}

.hide-times .runsheet-item {
    grid-template-columns: 1fr;
}

.hide-notes .runsheet-notes-section {
    display: none;
}

/* Print Styles */
.print-only {
    display: none;
}

@media print {
    .no-print {
        display: none !important;
    }

    .print-only {
        display: block;
    }

    .runsheet-print-header {
        margin-bottom: 2rem;
        padding-bottom: 1rem;
        border-bottom: 3px solid #000;
    }

    .runsheet-print-header h1 {
        font-size: 24pt;
        margin-bottom: 0.5rem;
    }

    .print-header-meta {
        display: flex;
        gap: 1rem;
        font-size: 10pt;
        color: #666;
    }

    .print-header-meta span {
        padding-right: 1rem;
        border-right: 1px solid #ccc;
    }

    .print-header-meta span:last-child {
        border-right: none;
    }

    .runsheet-sheet {
        border: none;
        padding: 0;
        background: white;
    }

    .runsheet-item {
        page-break-inside: avoid;
        border: 2px solid #000;
        background: white;
        margin-bottom: 1rem;
    }

    .runsheet-time-col {
        background: #f5f5f5;
        border: 1px solid #000;
    }

    .runsheet-note {
        border-left-width: 3px;
        background: #f9f9f9 !important;
    }

    @page {
        margin: 0.75in;
    }
}

/* Responsive */
@media (max-width: 768px) {
    .runsheet-item {
        grid-template-columns: 1fr;
        gap: 1rem;
    }

    .runsheet-time-col {
        flex-direction: row;
        justify-content: space-around;
    }

    .runsheet-options {
        flex-direction: column;
        gap: 1rem;
        align-items: stretch;
    }
}
</style>

<script <?= csp_nonce(); ?>>
function switchView(view) {
    // Update active button
    document.querySelectorAll('.view-toggle-btn').forEach(btn => {
        btn.classList.remove('active');
    });
    document.querySelector(`[data-view="${view}"]`).classList.add('active');

    // Update view class
    const sheet = document.querySelector('.runsheet-sheet');
    sheet.className = 'runsheet-sheet';
    if (view !== 'full') {
        sheet.classList.add('view-' + view);
    }
}

function toggleTimes() {
    const checked = document.getElementById('show-times').checked;
    const sheet = document.querySelector('.runsheet-sheet');
    if (checked) {
        sheet.classList.remove('hide-times');
    } else {
        sheet.classList.add('hide-times');
    }
}

function toggleNotes() {
    const checked = document.getElementById('show-notes').checked;
    const sheet = document.querySelector('.runsheet-sheet');
    if (checked) {
        sheet.classList.remove('hide-notes');
    } else {
        sheet.classList.add('hide-notes');
    }
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
