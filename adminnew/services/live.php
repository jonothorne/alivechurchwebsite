<?php
/**
 * Live Service Mode
 * Real-time tracking of service progress
 */
$page_title = 'Live Mode';
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

// Fetch service items
$itemsStmt = $pdo->prepare("
    SELECT
        si.*,
        s.title as song_title,
        s.artist as song_artist,
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

$totalMinutes = array_sum(array_column($items, 'effective_duration'));

$serviceDate = new DateTime($service['service_date']);
$formattedDate = $serviceDate->format('l, F j, Y');

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

<div class="live-mode-container">
    <!-- Header -->
    <div class="live-mode-header">
        <div class="live-mode-title">
            <div class="live-indicator">
                <span class="live-dot"></span>
                LIVE
            </div>
            <h1><?= $service['title'] ? htmlspecialchars($service['title']) : $formattedDate; ?></h1>
        </div>
        <div class="live-mode-actions">
            <button onclick="toggleFullscreen()" class="live-btn live-btn-secondary">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M8 3H5a2 2 0 0 0-2 2v3m18 0V5a2 2 0 0 0-2-2h-3m0 18h3a2 2 0 0 0 2-2v-3M3 16v3a2 2 0 0 0 2 2h3"></path>
                </svg>
                Fullscreen
            </button>
            <a href="/adminnew/services/runsheet/<?= $serviceId; ?>" class="live-btn live-btn-secondary">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <line x1="19" y1="12" x2="5" y2="12"></line>
                    <polyline points="12 19 5 12 12 5"></polyline>
                </svg>
                Exit Live
            </a>
        </div>
    </div>

    <!-- Service Progress -->
    <div class="live-progress-section">
        <div class="live-progress-bar">
            <div class="live-progress-fill" id="progress-fill" style="width: 0%"></div>
        </div>
        <div class="live-progress-info">
            <div class="live-time-info">
                <span class="live-time-label">Elapsed:</span>
                <span class="live-time-value" id="elapsed-time">00:00</span>
            </div>
            <div class="live-time-info">
                <span class="live-time-label">Current:</span>
                <span class="live-time-value live-time-current" id="current-time"></span>
            </div>
            <div class="live-time-info">
                <span class="live-time-label">Remaining:</span>
                <span class="live-time-value" id="remaining-time"><?= $totalMinutes; ?> min</span>
            </div>
        </div>
    </div>

    <!-- Service Items Timeline -->
    <div class="live-timeline">
        <?php foreach ($items as $index => $item): ?>
            <div class="live-item"
                 data-item-id="<?= $item['id']; ?>"
                 data-duration="<?= $item['effective_duration']; ?>"
                 data-position="<?= $index; ?>">

                <div class="live-item-status">
                    <div class="live-item-number"><?= $index + 1; ?></div>
                    <div class="live-item-status-indicator" id="status-<?= $item['id']; ?>">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <circle cx="12" cy="12" r="10"></circle>
                        </svg>
                    </div>
                </div>

                <div class="live-item-content">
                    <div class="live-item-header">
                        <div class="live-item-type"><?= $itemTypeLabels[$item['item_type']] ?? ucfirst($item['item_type']); ?></div>
                        <div class="live-item-duration"><?= $item['effective_duration']; ?> min</div>
                    </div>
                    <div class="live-item-title">
                        <?php if ($item['song_id'] && $item['song_title']): ?>
                            <?= htmlspecialchars($item['song_title']); ?>
                            <?php if ($item['song_artist']): ?>
                                <span class="text-muted">- <?= htmlspecialchars($item['song_artist']); ?></span>
                            <?php endif; ?>
                        <?php elseif ($item['title']): ?>
                            <?= htmlspecialchars($item['title']); ?>
                        <?php endif; ?>
                    </div>
                    <?php if ($item['presenter']): ?>
                        <div class="live-item-presenter"><?= htmlspecialchars($item['presenter']); ?></div>
                    <?php endif; ?>
                </div>

                <div class="live-item-controls">
                    <button class="live-control-btn live-start-btn" onclick="startItem(<?= $item['id']; ?>)" id="start-<?= $item['id']; ?>">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <polygon points="5 3 19 12 5 21 5 3"></polygon>
                        </svg>
                        Start
                    </button>
                    <button class="live-control-btn live-end-btn" onclick="endItem(<?= $item['id']; ?>)" id="end-<?= $item['id']; ?>" style="display: none;">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <rect x="6" y="4" width="4" height="16"></rect>
                            <rect x="14" y="4" width="4" height="16"></rect>
                        </svg>
                        End
                    </button>
                    <div class="live-item-timer" id="timer-<?= $item['id']; ?>" style="display: none;">00:00</div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
</div>

<style <?= csp_nonce(); ?>>
/* Live Mode Container */
.live-mode-container {
    min-height: 100vh;
    padding: 1.5rem;
    background: linear-gradient(135deg, #1e293b 0%, #0f172a 100%);
}

/* Header */
.live-mode-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 2rem;
    padding: 1.5rem;
    background: rgba(255, 255, 255, 0.05);
    border-radius: var(--admin-radius-lg);
    border: 1px solid rgba(255, 255, 255, 0.1);
}

.live-mode-title {
    display: flex;
    align-items: center;
    gap: 1rem;
}

.live-indicator {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    padding: 0.5rem 1rem;
    background: rgba(239, 68, 68, 0.2);
    border: 2px solid #ef4444;
    border-radius: var(--admin-radius);
    font-weight: 700;
    font-size: 0.875rem;
    color: #ef4444;
}

.live-dot {
    width: 8px;
    height: 8px;
    background: #ef4444;
    border-radius: 50%;
    animation: pulse 2s infinite;
}

@keyframes pulse {
    0%, 100% { opacity: 1; }
    50% { opacity: 0.5; }
}

.live-mode-title h1 {
    color: white;
    font-size: 1.5rem;
    margin: 0;
}

.live-mode-actions {
    display: flex;
    gap: 0.75rem;
}

.live-btn {
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    padding: 0.75rem 1.25rem;
    border-radius: var(--admin-radius);
    font-weight: 600;
    cursor: pointer;
    transition: all 0.2s;
    text-decoration: none;
    border: none;
    font-size: 0.875rem;
}

.live-btn-secondary {
    background: rgba(255, 255, 255, 0.1);
    color: white;
    border: 1px solid rgba(255, 255, 255, 0.2);
}

.live-btn-secondary:hover {
    background: rgba(255, 255, 255, 0.15);
}

/* Progress Section */
.live-progress-section {
    margin-bottom: 2rem;
    padding: 1.5rem;
    background: rgba(255, 255, 255, 0.05);
    border-radius: var(--admin-radius-lg);
    border: 1px solid rgba(255, 255, 255, 0.1);
}

.live-progress-bar {
    width: 100%;
    height: 12px;
    background: rgba(255, 255, 255, 0.1);
    border-radius: 6px;
    overflow: hidden;
    margin-bottom: 1rem;
}

.live-progress-fill {
    height: 100%;
    background: linear-gradient(90deg, #22c55e 0%, #16a34a 100%);
    border-radius: 6px;
    transition: width 0.3s ease;
}

.live-progress-info {
    display: flex;
    justify-content: space-between;
}

.live-time-info {
    display: flex;
    flex-direction: column;
    align-items: center;
}

.live-time-label {
    font-size: 0.75rem;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.05em;
    color: rgba(255, 255, 255, 0.5);
    margin-bottom: 0.25rem;
}

.live-time-value {
    font-size: 1.5rem;
    font-weight: 700;
    color: white;
    font-variant-numeric: tabular-nums;
}

.live-time-current {
    color: #22c55e;
}

/* Timeline */
.live-timeline {
    display: flex;
    flex-direction: column;
    gap: 0.75rem;
}

/* Live Item */
.live-item {
    display: grid;
    grid-template-columns: 80px 1fr auto;
    gap: 1.5rem;
    align-items: center;
    padding: 1.25rem 1.5rem;
    background: rgba(255, 255, 255, 0.05);
    border-radius: var(--admin-radius-lg);
    border: 2px solid rgba(255, 255, 255, 0.1);
    transition: all 0.3s;
}

.live-item.upcoming {
    opacity: 0.5;
}

.live-item.current {
    background: rgba(34, 197, 94, 0.1);
    border-color: #22c55e;
    box-shadow: 0 0 20px rgba(34, 197, 94, 0.3);
    opacity: 1;
}

.live-item.completed {
    opacity: 0.4;
    border-style: dashed;
}

.live-item-status {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 0.5rem;
}

.live-item-number {
    width: 36px;
    height: 36px;
    display: flex;
    align-items: center;
    justify-content: center;
    background: rgba(255, 255, 255, 0.1);
    border: 2px solid rgba(255, 255, 255, 0.2);
    border-radius: 50%;
    font-weight: 700;
    color: white;
    font-size: 0.875rem;
}

.live-item.current .live-item-number {
    background: #22c55e;
    border-color: #22c55e;
    color: white;
}

.live-item.completed .live-item-number {
    background: rgba(34, 197, 94, 0.3);
    border-color: #22c55e;
}

.live-item-status-indicator {
    color: rgba(255, 255, 255, 0.3);
}

.live-item.current .live-item-status-indicator {
    color: #22c55e;
}

.live-item.completed .live-item-status-indicator svg {
    fill: #22c55e;
    stroke: #22c55e;
}

.live-item-content {
    flex: 1;
    min-width: 0;
}

.live-item-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 0.5rem;
}

.live-item-type {
    font-size: 0.7rem;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.05em;
    color: rgba(255, 255, 255, 0.5);
}

.live-item.current .live-item-type {
    color: #22c55e;
}

.live-item-duration {
    font-size: 0.75rem;
    font-weight: 600;
    color: rgba(255, 255, 255, 0.5);
    background: rgba(255, 255, 255, 0.1);
    padding: 0.25rem 0.5rem;
    border-radius: 4px;
}

.live-item-title {
    font-size: 1.125rem;
    font-weight: 600;
    color: white;
    margin-bottom: 0.25rem;
}

.live-item-presenter {
    font-size: 0.875rem;
    color: rgba(255, 255, 255, 0.6);
}

/* Controls */
.live-item-controls {
    display: flex;
    gap: 0.5rem;
    align-items: center;
}

.live-control-btn {
    display: inline-flex;
    align-items: center;
    gap: 0.375rem;
    padding: 0.625rem 1rem;
    border-radius: var(--admin-radius);
    font-weight: 600;
    font-size: 0.875rem;
    cursor: pointer;
    transition: all 0.2s;
    border: none;
}

.live-start-btn {
    background: #22c55e;
    color: white;
}

.live-start-btn:hover {
    background: #16a34a;
}

.live-end-btn {
    background: #ef4444;
    color: white;
}

.live-end-btn:hover {
    background: #dc2626;
}

.live-item-timer {
    font-size: 1.25rem;
    font-weight: 700;
    color: #22c55e;
    font-variant-numeric: tabular-nums;
    min-width: 60px;
    text-align: center;
}

/* Fullscreen Mode */
.fullscreen-mode {
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    z-index: 9999;
    overflow-y: auto;
}

/* Responsive */
@media (max-width: 768px) {
    .live-item {
        grid-template-columns: 1fr;
        gap: 1rem;
    }

    .live-item-controls {
        justify-content: center;
    }

    .live-progress-info {
        flex-direction: column;
        gap: 1rem;
    }
}
</style>

<script <?= csp_nonce(); ?>>
let serviceStartTime = null;
let currentItemId = null;
let currentItemStartTime = null;
let timerInterval = null;

// Initialize
document.addEventListener('DOMContentLoaded', function() {
    updateCurrentTime();
    setInterval(updateCurrentTime, 1000);

    // Mark first item as upcoming
    const firstItem = document.querySelector('.live-item');
    if (firstItem) {
        firstItem.classList.add('upcoming');
    }
});

function updateCurrentTime() {
    const now = new Date();
    const timeStr = now.toLocaleTimeString('en-US', {
        hour: 'numeric',
        minute: '2-digit',
        hour12: true
    });
    document.getElementById('current-time').textContent = timeStr;

    // Update elapsed time if service has started
    if (serviceStartTime) {
        const elapsed = Math.floor((now - serviceStartTime) / 1000);
        const minutes = Math.floor(elapsed / 60);
        const seconds = elapsed % 60;
        document.getElementById('elapsed-time').textContent =
            `${String(minutes).padStart(2, '0')}:${String(seconds).padStart(2, '0')}`;
    }
}

function startItem(itemId) {
    const now = new Date();

    // If this is the first item, start the service
    if (!serviceStartTime) {
        serviceStartTime = now;
    }

    // End previous item if any
    if (currentItemId) {
        endItem(currentItemId, true);
    }

    currentItemId = itemId;
    currentItemStartTime = now;

    // Update UI
    const item = document.querySelector(`[data-item-id="${itemId}"]`);
    document.querySelectorAll('.live-item').forEach(el => {
        el.classList.remove('current');
        el.classList.add('completed');
    });
    item.classList.remove('upcoming', 'completed');
    item.classList.add('current');

    // Mark subsequent items as upcoming
    let foundCurrent = false;
    document.querySelectorAll('.live-item').forEach(el => {
        if (el === item) {
            foundCurrent = true;
        } else if (foundCurrent && !el.classList.contains('completed')) {
            el.classList.add('upcoming');
        }
    });

    // Show/hide buttons
    document.getElementById('start-' + itemId).style.display = 'none';
    document.getElementById('end-' + itemId).style.display = 'inline-flex';
    document.getElementById('timer-' + itemId).style.display = 'block';

    // Start timer for current item
    if (timerInterval) clearInterval(timerInterval);
    timerInterval = setInterval(() => updateItemTimer(itemId), 1000);

    // Save to backend
    saveItemStart(itemId);

    // Update progress
    updateProgress();
}

function endItem(itemId, skipUpdate = false) {
    if (currentItemId !== itemId && !skipUpdate) return;

    const now = new Date();

    // Update UI
    const item = document.querySelector(`[data-item-id="${itemId}"]`);
    item.classList.remove('current');
    item.classList.add('completed');

    // Hide timer and buttons
    document.getElementById('start-' + itemId).style.display = 'none';
    document.getElementById('end-' + itemId).style.display = 'none';
    const timer = document.getElementById('timer-' + itemId);
    if (timer) {
        timer.style.display = 'none';
    }

    // Stop timer
    if (timerInterval) {
        clearInterval(timerInterval);
        timerInterval = null;
    }

    // Save to backend
    if (!skipUpdate) {
        saveItemEnd(itemId);
    }

    currentItemId = null;
    currentItemStartTime = null;

    // Update progress
    updateProgress();

    // Auto-start next item?
    const nextItem = item.nextElementSibling;
    if (nextItem && nextItem.classList.contains('live-item')) {
        nextItem.classList.add('upcoming');
    }
}

function updateItemTimer(itemId) {
    if (!currentItemStartTime) return;

    const now = new Date();
    const elapsed = Math.floor((now - currentItemStartTime) / 1000);
    const minutes = Math.floor(elapsed / 60);
    const seconds = elapsed % 60;

    const timer = document.getElementById('timer-' + itemId);
    if (timer) {
        timer.textContent = `${String(minutes).padStart(2, '0')}:${String(seconds).padStart(2, '0')}`;
    }
}

function updateProgress() {
    const items = document.querySelectorAll('.live-item');
    const completed = document.querySelectorAll('.live-item.completed').length;
    const total = items.length;

    const percentage = total > 0 ? (completed / total) * 100 : 0;
    document.getElementById('progress-fill').style.width = percentage + '%';

    // Calculate remaining time
    let remainingMinutes = 0;
    items.forEach(item => {
        if (!item.classList.contains('completed')) {
            remainingMinutes += parseInt(item.dataset.duration) || 0;
        }
    });

    document.getElementById('remaining-time').textContent = remainingMinutes + ' min';
}

async function saveItemStart(itemId) {
    try {
        await fetch('/adminnew/services/api/live-tracking', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                action: 'start-item',
                item_id: itemId
            })
        });
    } catch (err) {
        console.error('Failed to save item start:', err);
    }
}

async function saveItemEnd(itemId) {
    try {
        await fetch('/adminnew/services/api/live-tracking', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                action: 'end-item',
                item_id: itemId
            })
        });
    } catch (err) {
        console.error('Failed to save item end:', err);
    }
}

function toggleFullscreen() {
    const container = document.querySelector('.live-mode-container');
    if (!document.fullscreenElement) {
        container.requestFullscreen().catch(err => {
            console.error('Error attempting to enable fullscreen:', err);
        });
    } else {
        document.exitFullscreen();
    }
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
