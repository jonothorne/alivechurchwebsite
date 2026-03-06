<?php
$page_title = 'Event Details';
require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/../includes/db-config.php';
require_once __DIR__ . '/../config.php';

$pdo = getDbConnection();

// Handle delete action
if (isset($_POST['delete']) && isset($_POST['slug'])) {
    $stmt = $pdo->prepare("DELETE FROM event_details WHERE slug = ?");
    $stmt->execute([$_POST['slug']]);
    $success_message = 'Event details deleted successfully.';
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
?>

<?php if (isset($success_message)): ?>
    <div class="alert alert-success"><?= htmlspecialchars($success_message); ?></div>
<?php endif; ?>

<div class="card">
    <div class="card-header">
        <h2>Manage Event Details</h2>
        <a href="/admin/events/edit" class="btn btn-primary">+ Add New Event Details</a>
    </div>

    <p style="color: #64748b; margin-bottom: 1.5rem;">
        Add custom content to events from Planning Center. This content appears on event detail pages and overrides Planning Center data where specified.
    </p>

    <?php if (empty($eventDetails)): ?>
        <div class="empty-state">
            <div style="font-size: 3rem; margin-bottom: 1rem;">📅</div>
            <h3>No Custom Event Details Yet</h3>
            <p style="color: #64748b; margin-bottom: 1.5rem;">Add custom content to your Planning Center events to create rich detail pages.</p>
            <a href="/admin/events/edit" class="btn btn-primary">Add Event Details</a>
        </div>
    <?php else: ?>
        <div class="table-container">
            <table>
                <thead>
                    <tr>
                        <th>Event</th>
                        <th>Slug</th>
                        <th>Custom Location</th>
                        <th>Registration URL</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($eventDetails as $detail): ?>
                        <tr>
                            <td>
                                <strong><?= htmlspecialchars($detail['title']); ?></strong>
                                <?php if ($detail['subtitle']): ?>
                                    <br><span style="color: #64748b; font-size: 0.875rem;"><?= htmlspecialchars($detail['subtitle']); ?></span>
                                <?php endif; ?>
                            </td>
                            <td><code style="background: #f1f5f9; padding: 0.25rem 0.5rem; border-radius: 0.25rem; font-size: 0.875rem;"><?= htmlspecialchars($detail['slug']); ?></code></td>
                            <td><?= $detail['custom_location'] ? htmlspecialchars($detail['custom_location']) : '<span style="color: #94a3b8;">—</span>'; ?></td>
                            <td>
                                <?php if ($detail['registration_url']): ?>
                                    <a href="<?= htmlspecialchars($detail['registration_url']); ?>" target="_blank" style="color: #667eea;">View Link</a>
                                <?php else: ?>
                                    <span style="color: #94a3b8;">—</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <div class="table-actions">
                                    <a href="/events/<?= htmlspecialchars($detail['slug']); ?>" target="_blank" class="btn btn-sm btn-outline">View</a>
                                    <a href="/admin/events/edit?slug=<?= urlencode($detail['slug']); ?>" class="btn btn-sm btn-secondary">Edit</a>
                                    <form method="POST" style="display: inline;" onsubmit="return confirm('Are you sure you want to delete this event\'s custom details?');">
                                        <input type="hidden" name="slug" value="<?= htmlspecialchars($detail['slug']); ?>">
                                        <button type="submit" name="delete" class="btn btn-sm btn-danger">Delete</button>
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

<!-- Planning Center Events Reference -->
<div class="card">
    <div class="card-header">
        <h2>Planning Center Events</h2>
    </div>
    <p style="color: #64748b; margin-bottom: 1.5rem;">
        These events are pulled from Planning Center. Click "Add Details" to create a custom detail page for any event.
    </p>

    <div class="table-container">
        <table>
            <thead>
                <tr>
                    <th>Event</th>
                    <th>Category</th>
                    <th>Date/Frequency</th>
                    <th>Custom Details</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($planningCenterEvents as $event): ?>
                    <?php $hasDetails = isset($detailsMap[$event['slug']]); ?>
                    <tr>
                        <td>
                            <strong><?= htmlspecialchars($event['title']); ?></strong>
                            <br><span style="color: #64748b; font-size: 0.875rem;"><?= htmlspecialchars(substr($event['description'] ?? '', 0, 60)); ?><?= strlen($event['description'] ?? '') > 60 ? '...' : ''; ?></span>
                        </td>
                        <td>
                            <span class="badge badge-<?= $event['category'] === 'special' ? 'primary' : ($event['category'] === 'youth' ? 'success' : 'secondary'); ?>">
                                <?= htmlspecialchars(ucfirst($event['category'])); ?>
                            </span>
                        </td>
                        <td>
                            <?php if (!empty($event['is_recurring'])): ?>
                                <?= htmlspecialchars($event['frequency']); ?>
                            <?php else: ?>
                                <?= htmlspecialchars($event['date']); ?>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($hasDetails): ?>
                                <span style="color: #10b981;">✓ Has custom details</span>
                            <?php else: ?>
                                <span style="color: #94a3b8;">No custom details</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <div class="table-actions">
                                <a href="/events/<?= htmlspecialchars($event['slug']); ?>" target="_blank" class="btn btn-sm btn-outline">View</a>
                                <?php if ($hasDetails): ?>
                                    <a href="/admin/events/edit?slug=<?= urlencode($event['slug']); ?>" class="btn btn-sm btn-secondary">Edit</a>
                                <?php else: ?>
                                    <a href="/admin/events/edit?slug=<?= urlencode($event['slug']); ?>&title=<?= urlencode($event['title']); ?>" class="btn btn-sm btn-primary">Add Details</a>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
