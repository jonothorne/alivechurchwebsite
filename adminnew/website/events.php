<?php
/**
 * Events Management - New Admin
 */
$page_title = 'Events';
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../../includes/db-config.php';
require_once __DIR__ . '/../../config.php';

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

<div class="admin-page-header">
    <div>
        <h1 class="admin-page-title">Events</h1>
        <p class="admin-page-subtitle"><?= $customCount; ?> custom details, <?= $pcCount; ?> Planning Center events</p>
    </div>
    <div class="admin-page-actions">
        <a href="/adminnew/events/edit" class="admin-btn admin-btn-primary">+ New Event</a>
    </div>
</div>

<!-- Custom Event Details -->
<div class="admin-card">
    <div class="admin-card-header">
        <h3 class="admin-card-title">Custom Event Details</h3>
        <span class="admin-text-muted"><?= $customCount; ?> entries</span>
    </div>

    <?php if (empty($eventDetails)): ?>
        <div class="admin-card-body">
            <p class="admin-text-muted">No custom event details yet. Add details to Planning Center events below.</p>
        </div>
    <?php else: ?>
        <div class="admin-table-wrapper">
            <table class="admin-table">
                <thead>
                    <tr>
                        <th>Title</th>
                        <th>Slug</th>
                        <th>Location</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($eventDetails as $detail): ?>
                        <tr>
                            <td>
                                <strong><?= htmlspecialchars($detail['title']); ?></strong>
                                <?php if ($detail['subtitle']): ?>
                                    <br><span class="admin-text-muted"><?= htmlspecialchars($detail['subtitle']); ?></span>
                                <?php endif; ?>
                            </td>
                            <td><code style="font-size: 0.75rem;">/<?= htmlspecialchars($detail['slug']); ?></code></td>
                            <td>
                                <?php if ($detail['custom_location']): ?>
                                    <?= htmlspecialchars($detail['custom_location']); ?>
                                <?php else: ?>
                                    <span class="admin-text-muted">—</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <div class="admin-table-actions">
                                    <a href="/events/<?= htmlspecialchars($detail['slug']); ?>" target="_blank" class="admin-btn admin-btn-sm admin-btn-secondary">View</a>
                                    <a href="/adminnew/events/edit?slug=<?= urlencode($detail['slug']); ?>" class="admin-btn admin-btn-sm admin-btn-secondary">Edit</a>
                                    <form method="POST" style="display: inline;" onsubmit="return confirm('Delete this event\'s details?')">
                                        <input type="hidden" name="slug" value="<?= htmlspecialchars($detail['slug']); ?>">
                                        <button type="submit" name="delete" class="admin-btn admin-btn-sm admin-btn-danger">×</button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<!-- Planning Center Events -->
<div class="admin-card">
    <div class="admin-card-header">
        <h3 class="admin-card-title">Planning Center Events</h3>
        <span class="admin-text-muted"><?= $pcCount; ?> events</span>
    </div>

    <?php if (empty($planningCenterEvents)): ?>
        <div class="admin-card-body">
            <p class="admin-text-muted">No events from Planning Center.</p>
        </div>
    <?php else: ?>
        <div class="admin-table-wrapper">
            <table class="admin-table">
                <thead>
                    <tr>
                        <th>Title</th>
                        <th>Category</th>
                        <th>Date</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($planningCenterEvents as $event): ?>
                        <?php $hasDetails = isset($detailsMap[$event['slug']]); ?>
                        <tr>
                            <td><strong><?= htmlspecialchars($event['title']); ?></strong></td>
                            <td><span class="admin-badge admin-badge-info"><?= htmlspecialchars(ucfirst($event['category'] ?? 'general')); ?></span></td>
                            <td>
                                <?php if (!empty($event['is_recurring'])): ?>
                                    <?= htmlspecialchars($event['frequency'] ?? 'Recurring'); ?>
                                <?php else: ?>
                                    <?= htmlspecialchars($event['date'] ?? 'No date'); ?>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($hasDetails): ?>
                                    <span class="admin-badge admin-badge-success">Has Details</span>
                                <?php else: ?>
                                    <span class="admin-badge admin-badge-secondary">PC Only</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <div class="admin-table-actions">
                                    <a href="/events/<?= htmlspecialchars($event['slug']); ?>" target="_blank" class="admin-btn admin-btn-sm admin-btn-secondary">View</a>
                                    <?php if ($hasDetails): ?>
                                        <a href="/adminnew/events/edit?slug=<?= urlencode($event['slug']); ?>" class="admin-btn admin-btn-sm admin-btn-secondary">Edit</a>
                                    <?php else: ?>
                                        <a href="/adminnew/events/edit?slug=<?= urlencode($event['slug']); ?>&title=<?= urlencode($event['title']); ?>" class="admin-btn admin-btn-sm admin-btn-primary">+ Details</a>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<style <?= csp_nonce(); ?>>
.admin-badge-secondary {
    background: var(--admin-bg);
    color: var(--admin-text-muted);
}
.admin-badge-info {
    background: rgba(59, 130, 246, 0.1);
    color: var(--admin-info);
}
</style>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
