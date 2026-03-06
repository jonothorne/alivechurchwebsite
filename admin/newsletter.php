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

    // CSV Header
    fputcsv($output, ['Email Address', 'First Name', 'Last Name', 'Subscribed Date', 'Status']);

    foreach ($subscribers as $subscriber) {
        if (($subscriber['status'] ?? 'confirmed') !== 'unsubscribed') {
            fputcsv($output, [
                $subscriber['email'],
                '', // First Name - not collected
                '', // Last Name - not collected
                $subscriber['subscribed_at'] ?? '',
                'subscribed' // Mailchimp expects 'subscribed' status
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
    <div class="alert alert-success">✅ <?= htmlspecialchars($success); ?></div>
<?php endif; ?>

<?php if ($error): ?>
    <div class="alert alert-error">⚠️ <?= htmlspecialchars($error); ?></div>
<?php endif; ?>

<!-- Stats Cards -->
<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1.5rem; margin-bottom: 2rem;">
    <div class="card" style="padding: 1.5rem;">
        <div style="color: #64748b; font-size: 0.875rem; margin-bottom: 0.5rem;">Total Subscribers</div>
        <div style="font-size: 2rem; font-weight: 700; color: #1e293b;"><?= $totalCount; ?></div>
    </div>

    <div class="card" style="padding: 1.5rem;">
        <div style="color: #64748b; font-size: 0.875rem; margin-bottom: 0.5rem;">Active Subscribers</div>
        <div style="font-size: 2rem; font-weight: 700; color: #10b981;"><?= $confirmedCount; ?></div>
    </div>

    <div class="card" style="padding: 1.5rem;">
        <div style="color: #64748b; font-size: 0.875rem; margin-bottom: 0.5rem;">Unsubscribed</div>
        <div style="font-size: 2rem; font-weight: 700; color: #94a3b8;"><?= $unsubscribedCount; ?></div>
    </div>
</div>

<!-- Actions Bar -->
<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem;">
    <!-- Filter Tabs -->
    <div style="display: flex; gap: 1rem;">
        <a href="?filter=all" class="<?= $filter === 'all' ? 'btn btn-primary' : 'btn btn-outline'; ?>">
            All (<?= $totalCount; ?>)
        </a>
        <a href="?filter=confirmed" class="<?= $filter === 'confirmed' ? 'btn btn-primary' : 'btn btn-outline'; ?>">
            Active (<?= $confirmedCount; ?>)
        </a>
        <a href="?filter=unsubscribed" class="<?= $filter === 'unsubscribed' ? 'btn btn-primary' : 'btn btn-outline'; ?>">
            Unsubscribed (<?= $unsubscribedCount; ?>)
        </a>
    </div>

    <!-- Export Button -->
    <a href="?export=csv" class="btn btn-primary">📥 Export to CSV (Mailchimp)</a>
</div>

<!-- Subscribers Table -->
<div class="card">
    <div class="card-header">
        <h2>Newsletter Subscribers</h2>
    </div>

    <?php if (empty($filteredSubscribers)): ?>
        <div class="empty-state">
            <div class="empty-state-icon">📧</div>
            <h3>No subscribers yet</h3>
            <p>Newsletter subscribers will appear here when someone signs up from your website footer.</p>
        </div>
    <?php else: ?>
        <div class="table-container">
            <table>
                <thead>
                    <tr>
                        <th>Email Address</th>
                        <th>Subscribed Date</th>
                        <th>Status</th>
                        <th>IP Address</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $index = 0;
                    foreach ($subscribers as $originalIndex => $subscriber):
                        if ($filter !== 'all' && ($subscriber['status'] ?? 'pending') !== $filter) {
                            continue;
                        }
                    ?>
                        <tr>
                            <td>
                                <strong><?= htmlspecialchars($subscriber['email']); ?></strong>
                            </td>
                            <td><?= date('M j, Y g:i A', strtotime($subscriber['subscribed_at'] ?? 'now')); ?></td>
                            <td>
                                <?php
                                $status = $subscriber['status'] ?? 'confirmed';

                                if ($status === 'unsubscribed') {
                                    $badgeClass = 'badge-danger';
                                    $displayStatus = 'Unsubscribed';
                                } else {
                                    // Default to active for confirmed or any other status
                                    $badgeClass = 'badge-success';
                                    $displayStatus = 'Active';
                                }
                                ?>
                                <span class="badge <?= $badgeClass; ?>"><?= $displayStatus; ?></span>
                            </td>
                            <td style="font-family: monospace; font-size: 0.875rem;">
                                <?= htmlspecialchars($subscriber['ip_address'] ?? 'N/A'); ?>
                            </td>
                            <td class="table-actions">
                                <?php if ($status !== 'unsubscribed'): ?>
                                    <a href="?status=unsubscribed&id=<?= $originalIndex; ?>&filter=<?= $filter; ?>"
                                       class="btn btn-sm btn-outline">Unsubscribe</a>
                                <?php else: ?>
                                    <a href="?status=confirmed&id=<?= $originalIndex; ?>&filter=<?= $filter; ?>"
                                       class="btn btn-sm btn-outline">Reactivate</a>
                                <?php endif; ?>

                                <a href="?delete=<?= $originalIndex; ?>&filter=<?= $filter; ?>"
                                   class="btn btn-sm btn-danger"
                                   data-confirm-delete>Delete</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<div style="margin-top: 2rem; padding: 1.5rem; background: #f8fafc; border-radius: 0.5rem; border-left: 4px solid #3b82f6;">
    <h3 style="margin-bottom: 0.75rem;">💡 Importing to Mailchimp</h3>
    <ol style="margin-left: 1.5rem; line-height: 1.8;">
        <li>Click the "Export to CSV" button above</li>
        <li>Log in to your Mailchimp account</li>
        <li>Go to <strong>Audience → Manage Audience → Import Contacts</strong></li>
        <li>Choose "Upload a file" and select the CSV file you just downloaded</li>
        <li>Map the columns: Email Address → Email, Status → Status</li>
        <li>Complete the import process</li>
    </ol>
    <p style="margin-top: 1rem; color: #64748b; font-size: 0.875rem;">
        Note: The CSV export only includes active subscribers (status is automatically set to "subscribed" for Mailchimp). Unsubscribed contacts are excluded from the export.
    </p>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
