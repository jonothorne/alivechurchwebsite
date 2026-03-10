<?php
$page_title = 'Events';
require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/../includes/db-config.php';
require_once __DIR__ . '/../config.php';

$pdo = getDbConnection();

// Handle delete action
if (isset($_POST['delete']) && isset($_POST['slug'])) {
    $stmt = $pdo->prepare("DELETE FROM event_details WHERE slug = ?");
    $stmt->execute([$_POST['slug']]);
    $success_message = 'Event details deleted.';
}

// Get all event details from database
$eventDetails = $pdo->query("SELECT * FROM event_details ORDER BY title")->fetchAll();

// Create a lookup map for easy access
$detailsMap = [];
foreach ($eventDetails as $detail) {
    $detailsMap[$detail['slug']] = $detail;
}

// Get Planning Center events for reference
$planningCenterEvents = $all_events ?? [];

// Count stats
$customCount = count($eventDetails);
$pcCount = count($planningCenterEvents);
$withDetails = 0;
foreach ($planningCenterEvents as $e) {
    if (isset($detailsMap[$e['slug']])) $withDetails++;
}
?>

<?php if (isset($success_message)): ?>
    <div class="admin-alert admin-alert-success"><?= htmlspecialchars($success_message); ?></div>
<?php endif; ?>

<!-- Header with Stats -->
<div class="admin-dashboard-header" style="margin-bottom: 1rem;">
    <div class="admin-dashboard-greeting">
        <span class="admin-greeting-text">Events</span>
        <a href="/admin/events/edit" class="btn btn-sm btn-primary">+ New</a>
    </div>
    <div class="admin-inline-stats">
        <span class="admin-inline-stat"><strong><?= $customCount; ?></strong> Custom Details</span>
        <span class="admin-inline-stat"><strong><?= $pcCount; ?></strong> PC Events</span>
        <span class="admin-inline-stat"><strong><?= $withDetails; ?></strong> With Details</span>
    </div>
</div>

<!-- Custom Event Details -->
<div class="admin-card">
    <div class="admin-card-header">
        <h3>Custom Event Details</h3>
        <span class="admin-muted-text"><?= $customCount; ?> entries</span>
    </div>

    <?php if (empty($eventDetails)): ?>
        <p class="admin-muted-text">No custom event details yet. Add details to Planning Center events below.</p>
    <?php else: ?>
        <div class="admin-compact-list">
            <?php foreach ($eventDetails as $detail): ?>
                <div class="admin-post-row">
                    <div class="admin-post-info">
                        <div class="admin-post-title">
                            <?= htmlspecialchars($detail['title']); ?>
                            <code style="font-size: 0.65rem; color: var(--color-text-muted); margin-left: 0.5rem;">/<?= htmlspecialchars($detail['slug']); ?></code>
                        </div>
                        <div class="admin-post-meta">
                            <?php if ($detail['subtitle']): ?>
                                <?= htmlspecialchars($detail['subtitle']); ?> ·
                            <?php endif; ?>
                            <?php if ($detail['custom_location']): ?>
                                <?= htmlspecialchars($detail['custom_location']); ?>
                            <?php else: ?>
                                <span class="admin-muted">No custom location</span>
                            <?php endif; ?>
                            <?php if ($detail['registration_url']): ?>
                                · <a href="<?= htmlspecialchars($detail['registration_url']); ?>" target="_blank">Registration</a>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="admin-post-actions">
                        <a href="/events/<?= htmlspecialchars($detail['slug']); ?>" target="_blank" class="btn btn-xs btn-outline">View</a>
                        <a href="/admin/events/edit?slug=<?= urlencode($detail['slug']); ?>" class="btn btn-xs btn-outline">Edit</a>
                        <form method="POST" style="display: inline;" onsubmit="return confirm('Delete this event\'s details?');">
                            <input type="hidden" name="slug" value="<?= htmlspecialchars($detail['slug']); ?>">
                            <button type="submit" name="delete" class="btn btn-xs btn-danger">×</button>
                        </form>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<!-- Planning Center Events -->
<div class="admin-card">
    <div class="admin-card-header">
        <h3>Planning Center Events</h3>
        <span class="admin-muted-text"><?= $pcCount; ?> events</span>
    </div>

    <?php if (empty($planningCenterEvents)): ?>
        <p class="admin-muted-text">No events from Planning Center.</p>
    <?php else: ?>
        <div class="admin-compact-list">
            <?php foreach ($planningCenterEvents as $event): ?>
                <?php $hasDetails = isset($detailsMap[$event['slug']]); ?>
                <div class="admin-post-row">
                    <div class="admin-post-info">
                        <div class="admin-post-title">
                            <?= htmlspecialchars($event['title']); ?>
                            <?php if ($hasDetails): ?>
                                <span class="admin-badge admin-badge-success">Has Details</span>
                            <?php endif; ?>
                        </div>
                        <div class="admin-post-meta">
                            <span class="admin-badge admin-badge-secondary"><?= htmlspecialchars(ucfirst($event['category'] ?? 'general')); ?></span>
                            ·
                            <?php if (!empty($event['is_recurring'])): ?>
                                <?= htmlspecialchars($event['frequency'] ?? 'Recurring'); ?>
                            <?php else: ?>
                                <?= htmlspecialchars($event['date'] ?? 'No date'); ?>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="admin-post-actions">
                        <a href="/events/<?= htmlspecialchars($event['slug']); ?>" target="_blank" class="btn btn-xs btn-outline">View</a>
                        <?php if ($hasDetails): ?>
                            <a href="/admin/events/edit?slug=<?= urlencode($event['slug']); ?>" class="btn btn-xs btn-outline">Edit</a>
                        <?php else: ?>
                            <a href="/admin/events/edit?slug=<?= urlencode($event['slug']); ?>&title=<?= urlencode($event['title']); ?>" class="btn btn-xs btn-primary">+ Details</a>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
