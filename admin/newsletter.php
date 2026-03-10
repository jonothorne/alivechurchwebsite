<?php
// Load newsletter subscribers (needed for both CSV export and page display)
$dataFile = __DIR__ . '/../data/newsletter-subscribers.json';
$subscribers = [];
if (file_exists($dataFile)) {
    $subscribers = json_decode(file_get_contents($dataFile), true) ?? [];
}

// Handle CSV Export BEFORE any output
if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="newsletter-subscribers-' . date('Y-m-d') . '.csv"');

    $output = fopen('php://output', 'w');
    fputcsv($output, ['Email Address', 'First Name', 'Last Name', 'Subscribed Date', 'Status']);

    foreach ($subscribers as $subscriber) {
        if (($subscriber['status'] ?? 'confirmed') !== 'unsubscribed') {
            fputcsv($output, [
                $subscriber['email'],
                '',
                '',
                $subscriber['subscribed_at'] ?? '',
                'subscribed'
            ]);
        }
    }

    fclose($output);
    exit;
}

// Now start normal page output
$page_title = 'Newsletter Subscribers';
require_once __DIR__ . '/includes/header.php';

$success = '';
$error = '';

// Reload subscribers after potential modifications
if (file_exists($dataFile)) {
    $subscribers = json_decode(file_get_contents($dataFile), true) ?? [];
}

// Handle Delete
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $index = (int)$_GET['delete'];
    if (isset($subscribers[$index])) {
        array_splice($subscribers, $index, 1);
        file_put_contents($dataFile, json_encode(array_values($subscribers), JSON_PRETTY_PRINT));
        $success = 'Subscriber deleted successfully';
    }
}

// Handle Status Change
if (isset($_GET['status']) && isset($_GET['id']) && is_numeric($_GET['id'])) {
    $index = (int)$_GET['id'];
    $newStatus = $_GET['status'];

    if (isset($subscribers[$index]) && in_array($newStatus, ['confirmed', 'unsubscribed'])) {
        $subscribers[$index]['status'] = $newStatus;
        file_put_contents($dataFile, json_encode($subscribers, JSON_PRETTY_PRINT));
        $success = 'Status updated successfully';
    }
}

// Get filter
$filter = $_GET['filter'] ?? 'all';

// Filter subscribers
$filteredSubscribers = $subscribers;
if ($filter === 'confirmed') {
    $filteredSubscribers = array_filter($subscribers, fn($s) => ($s['status'] ?? 'confirmed') === 'confirmed');
} elseif ($filter === 'unsubscribed') {
    $filteredSubscribers = array_filter($subscribers, fn($s) => ($s['status'] ?? 'confirmed') === 'unsubscribed');
}

// Calculate stats
$totalCount = count($subscribers);
$confirmedCount = count(array_filter($subscribers, fn($s) => ($s['status'] ?? 'confirmed') === 'confirmed'));
$unsubscribedCount = count(array_filter($subscribers, fn($s) => ($s['status'] ?? 'confirmed') === 'unsubscribed'));
?>

<?php if ($success): ?>
    <div class="admin-alert admin-alert-success"><?= htmlspecialchars($success); ?></div>
<?php endif; ?>

<?php if ($error): ?>
    <div class="admin-alert admin-alert-error"><?= htmlspecialchars($error); ?></div>
<?php endif; ?>

<!-- Header with Stats -->
<div class="admin-dashboard-header" style="margin-bottom: 1rem;">
    <div class="admin-dashboard-greeting">
        <span class="admin-greeting-text">Newsletter</span>
        <a href="?export=csv" class="btn btn-sm btn-primary">Export CSV</a>
    </div>
    <div class="admin-inline-stats">
        <span class="admin-inline-stat"><strong><?= $totalCount; ?></strong> Total</span>
        <span class="admin-inline-stat"><strong><?= $confirmedCount; ?></strong> Active</span>
        <span class="admin-inline-stat"><strong><?= $unsubscribedCount; ?></strong> Unsubscribed</span>
    </div>
</div>

<!-- Subscribers List -->
<div class="admin-card">
    <div class="admin-card-header">
        <div class="admin-filter-tabs" style="margin: 0;">
            <a href="?filter=all" class="admin-filter-tab <?= $filter === 'all' ? 'active' : ''; ?>">All</a>
            <a href="?filter=confirmed" class="admin-filter-tab <?= $filter === 'confirmed' ? 'active' : ''; ?>">Active</a>
            <a href="?filter=unsubscribed" class="admin-filter-tab <?= $filter === 'unsubscribed' ? 'active' : ''; ?>">Unsubscribed</a>
        </div>
    </div>

    <?php if (empty($filteredSubscribers)): ?>
        <div class="admin-empty-state">
            <span class="admin-empty-icon">📧</span>
            <p>No subscribers yet.</p>
        </div>
    <?php else: ?>
        <div class="admin-compact-list">
            <?php
            foreach ($subscribers as $originalIndex => $subscriber):
                if ($filter !== 'all' && ($subscriber['status'] ?? 'confirmed') !== $filter) continue;
                $status = $subscriber['status'] ?? 'confirmed';
            ?>
                <div class="admin-post-row">
                    <div class="admin-post-info">
                        <div class="admin-post-title">
                            <?= htmlspecialchars($subscriber['email']); ?>
                            <?php if ($status === 'unsubscribed'): ?>
                                <span class="admin-badge admin-badge-secondary">Unsubscribed</span>
                            <?php else: ?>
                                <span class="admin-badge admin-badge-success">Active</span>
                            <?php endif; ?>
                        </div>
                        <div class="admin-post-meta">
                            <?= date('M j, Y g:i A', strtotime($subscriber['subscribed_at'] ?? 'now')); ?>
                            <?php if ($subscriber['ip_address'] ?? false): ?>
                                · <code><?= htmlspecialchars($subscriber['ip_address']); ?></code>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="admin-post-actions">
                        <?php if ($status !== 'unsubscribed'): ?>
                            <a href="?status=unsubscribed&id=<?= $originalIndex; ?>&filter=<?= $filter; ?>" class="btn btn-xs btn-outline">Unsubscribe</a>
                        <?php else: ?>
                            <a href="?status=confirmed&id=<?= $originalIndex; ?>&filter=<?= $filter; ?>" class="btn btn-xs btn-outline">Reactivate</a>
                        <?php endif; ?>
                        <a href="?delete=<?= $originalIndex; ?>&filter=<?= $filter; ?>" class="btn btn-xs btn-danger" data-confirm-delete>×</a>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<!-- Mailchimp Instructions -->
<div class="admin-card">
    <details>
        <summary class="admin-card-header" style="cursor: pointer;">
            <h3>Importing to Mailchimp</h3>
        </summary>
        <div class="admin-info-box">
            <ol>
                <li>Click "Export CSV" above</li>
                <li>Log in to Mailchimp</li>
                <li>Go to Audience → Manage Audience → Import Contacts</li>
                <li>Upload the CSV file</li>
                <li>Map columns and complete import</li>
            </ol>
            <p class="admin-muted-text">CSV export only includes active subscribers.</p>
        </div>
    </details>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
