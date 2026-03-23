<?php
$page_title = 'Welcome Journeys';
require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/../includes/db-config.php';
require_once __DIR__ . '/../includes/WelcomeJourney.php';

$pdo = getDbConnection();
$welcomeJourney = new WelcomeJourney($pdo);
$success = '';
$error = '';

// Get admin user ID for logging
$admin_user_id = $_SESSION['admin_user']['id'] ?? null;

// Handle Actions
if (isset($_GET['action'])) {
    $id = (int)($_GET['id'] ?? 0);

    switch ($_GET['action']) {
        case 'cancel':
            if ($id && $welcomeJourney->cancelJourney($id)) {
                if ($admin_user_id) {
                    log_activity($admin_user_id, 'update', 'welcome_journey', $id, 'Cancelled welcome journey');
                }
                $success = 'Journey cancelled successfully';
            } else {
                $error = 'Failed to cancel journey';
            }
            break;

        case 'mark_visited':
            $stmt = $pdo->prepare("UPDATE welcome_journeys SET status = 'visited', actual_visit_date = CURDATE() WHERE id = ?");
            if ($stmt->execute([$id])) {
                if ($admin_user_id) {
                    log_activity($admin_user_id, 'update', 'welcome_journey', $id, 'Marked visitor as visited');
                }
                $success = 'Visitor marked as visited';
            }
            break;

        case 'resend':
            $emailId = (int)($_GET['email_id'] ?? 0);
            if ($emailId) {
                // Reset email to pending and clear previous error
                $stmt = $pdo->prepare("UPDATE welcome_journey_emails SET status = 'pending', error_message = NULL, scheduled_at = NOW() WHERE id = ?");
                if ($stmt->execute([$emailId])) {
                    if ($admin_user_id) {
                        log_activity($admin_user_id, 'update', 'welcome_journey_email', $emailId, 'Resent welcome email');
                    }
                    $success = 'Email queued for resend';
                }
            }
            break;

        case 'delete':
            $stmt = $pdo->prepare("DELETE FROM welcome_journeys WHERE id = ?");
            if ($stmt->execute([$id])) {
                if ($admin_user_id) {
                    log_activity($admin_user_id, 'delete', 'welcome_journey', $id, 'Deleted welcome journey');
                }
                $success = 'Journey deleted';
            }
            break;
    }
}

// Get filter
$filter = $_GET['filter'] ?? 'active';

// Fetch journeys based on filter
$whereClause = '';
switch ($filter) {
    case 'active':
        $whereClause = "WHERE status IN ('active', 'visited')";
        break;
    case 'completed':
        $whereClause = "WHERE status = 'completed'";
        break;
    case 'cancelled':
        $whereClause = "WHERE status IN ('cancelled', 'unsubscribed')";
        break;
    default:
        $whereClause = '';
}

$journeys = $pdo->query("SELECT * FROM welcome_journeys {$whereClause} ORDER BY created_at DESC")->fetchAll();

// Get journey for detail view
$detail_journey = null;
$detail_emails = [];
if (isset($_GET['view']) && is_numeric($_GET['view'])) {
    $stmt = $pdo->prepare("SELECT * FROM welcome_journeys WHERE id = ?");
    $stmt->execute([$_GET['view']]);
    $detail_journey = $stmt->fetch();

    if ($detail_journey) {
        $stmt = $pdo->prepare("SELECT * FROM welcome_journey_emails WHERE journey_id = ? ORDER BY scheduled_at");
        $stmt->execute([$detail_journey['id']]);
        $detail_emails = $stmt->fetchAll();
    }
}

// Count stats
$stats = [
    'active' => $pdo->query("SELECT COUNT(*) FROM welcome_journeys WHERE status IN ('active', 'visited')")->fetchColumn(),
    'completed' => $pdo->query("SELECT COUNT(*) FROM welcome_journeys WHERE status = 'completed'")->fetchColumn(),
    'cancelled' => $pdo->query("SELECT COUNT(*) FROM welcome_journeys WHERE status IN ('cancelled', 'unsubscribed')")->fetchColumn(),
    'emails_sent' => $pdo->query("SELECT COUNT(*) FROM welcome_journey_emails WHERE status = 'sent'")->fetchColumn(),
    'emails_pending' => $pdo->query("SELECT COUNT(*) FROM welcome_journey_emails WHERE status = 'pending'")->fetchColumn(),
    'emails_failed' => $pdo->query("SELECT COUNT(*) FROM welcome_journey_emails WHERE status = 'failed'")->fetchColumn(),
];

// Status badge helper
function getStatusBadge($status) {
    $badges = [
        'active' => ['class' => 'admin-badge-info', 'label' => 'Active'],
        'visited' => ['class' => 'admin-badge-primary', 'label' => 'Visited'],
        'completed' => ['class' => 'admin-badge-success', 'label' => 'Completed'],
        'cancelled' => ['class' => 'admin-badge-danger', 'label' => 'Cancelled'],
        'unsubscribed' => ['class' => 'admin-badge-warning', 'label' => 'Unsubscribed'],
    ];
    $badge = $badges[$status] ?? ['class' => '', 'label' => ucfirst($status)];
    return '<span class="admin-badge ' . $badge['class'] . '">' . $badge['label'] . '</span>';
}

function getEmailStatusBadge($status) {
    $badges = [
        'pending' => ['class' => 'admin-badge-info', 'label' => 'Pending'],
        'sent' => ['class' => 'admin-badge-success', 'label' => 'Sent'],
        'failed' => ['class' => 'admin-badge-danger', 'label' => 'Failed'],
        'cancelled' => ['class' => 'admin-badge-warning', 'label' => 'Cancelled'],
        'skipped' => ['class' => '', 'label' => 'Skipped'],
    ];
    $badge = $badges[$status] ?? ['class' => '', 'label' => ucfirst($status)];
    return '<span class="admin-badge ' . $badge['class'] . '">' . $badge['label'] . '</span>';
}
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
        <span class="admin-greeting-text">Welcome Journey Dashboard</span>
        <p class="admin-greeting-subtext">Automated email sequences for new visitors</p>
    </div>
    <div>
        <a href="/admin/welcome-journey-preview" class="btn btn-outline">Preview Emails</a>
    </div>
</div>

<!-- Stats Cards -->
<div class="admin-stats-grid" style="margin-bottom: 1.5rem;">
    <div class="admin-stat-card">
        <div class="admin-stat-icon" style="background: linear-gradient(135deg, #3b82f6 0%, #1d4ed8 100%);">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/>
                <circle cx="9" cy="7" r="4"/>
                <path d="M23 21v-2a4 4 0 0 0-3-3.87"/>
                <path d="M16 3.13a4 4 0 0 1 0 7.75"/>
            </svg>
        </div>
        <div class="admin-stat-content">
            <span class="admin-stat-value"><?= $stats['active']; ?></span>
            <span class="admin-stat-label">Active Journeys</span>
        </div>
    </div>
    <div class="admin-stat-card">
        <div class="admin-stat-icon" style="background: linear-gradient(135deg, #10b981 0%, #059669 100%);">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/>
                <polyline points="22 4 12 14.01 9 11.01"/>
            </svg>
        </div>
        <div class="admin-stat-content">
            <span class="admin-stat-value"><?= $stats['completed']; ?></span>
            <span class="admin-stat-label">Completed</span>
        </div>
    </div>
    <div class="admin-stat-card">
        <div class="admin-stat-icon" style="background: linear-gradient(135deg, #eb008b 0%, #d4007d 100%);">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/>
                <polyline points="22,6 12,13 2,6"/>
            </svg>
        </div>
        <div class="admin-stat-content">
            <span class="admin-stat-value"><?= $stats['emails_sent']; ?></span>
            <span class="admin-stat-label">Emails Sent</span>
        </div>
    </div>
    <div class="admin-stat-card">
        <div class="admin-stat-icon" style="background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%);">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <circle cx="12" cy="12" r="10"/>
                <polyline points="12 6 12 12 16 14"/>
            </svg>
        </div>
        <div class="admin-stat-content">
            <span class="admin-stat-value"><?= $stats['emails_pending']; ?></span>
            <span class="admin-stat-label">Emails Queued</span>
        </div>
    </div>
</div>

<?php if ($stats['emails_failed'] > 0): ?>
<div class="admin-alert admin-alert-warning" style="margin-bottom: 1rem;">
    <strong><?= $stats['emails_failed']; ?> emails failed to send.</strong> Check the journey details to resend them.
</div>
<?php endif; ?>

<!-- Detail View -->
<?php if ($detail_journey): ?>
<div class="admin-card">
    <div class="admin-card-header">
        <h3>Journey Details: <?= htmlspecialchars($detail_journey['visitor_name']); ?></h3>
        <a href="/admin/welcome-journeys.php?filter=<?= $filter; ?>" class="btn btn-xs btn-outline">Back</a>
    </div>

    <div class="admin-detail-grid">
        <div class="admin-detail-item">
            <span class="admin-detail-label">Name</span>
            <span class="admin-detail-value"><?= htmlspecialchars($detail_journey['visitor_name']); ?></span>
        </div>
        <div class="admin-detail-item">
            <span class="admin-detail-label">Email</span>
            <span class="admin-detail-value">
                <a href="mailto:<?= htmlspecialchars($detail_journey['visitor_email']); ?>"><?= htmlspecialchars($detail_journey['visitor_email']); ?></a>
            </span>
        </div>
        <div class="admin-detail-item">
            <span class="admin-detail-label">Status</span>
            <span class="admin-detail-value"><?= getStatusBadge($detail_journey['status']); ?></span>
        </div>
        <div class="admin-detail-item">
            <span class="admin-detail-label">Registered</span>
            <span class="admin-detail-value"><?= date('M j, Y g:i A', strtotime($detail_journey['registered_at'])); ?></span>
        </div>
        <div class="admin-detail-item">
            <span class="admin-detail-label">Expected Visit</span>
            <span class="admin-detail-value"><?= $detail_journey['expected_visit_date'] ? date('M j, Y', strtotime($detail_journey['expected_visit_date'])) : 'Not set'; ?></span>
        </div>
        <div class="admin-detail-item">
            <span class="admin-detail-label">Actual Visit</span>
            <span class="admin-detail-value"><?= $detail_journey['actual_visit_date'] ? date('M j, Y', strtotime($detail_journey['actual_visit_date'])) : 'Not recorded'; ?></span>
        </div>
    </div>

    <?php if ($detail_journey['notes']): ?>
    <div style="margin-top: 1rem; padding: 1rem; background: var(--admin-bg-muted); border-radius: 8px;">
        <strong>Notes:</strong>
        <p style="margin: 0.5rem 0 0;"><?= nl2br(htmlspecialchars($detail_journey['notes'])); ?></p>
    </div>
    <?php endif; ?>

    <!-- Email Timeline -->
    <h4 style="margin: 1.5rem 0 1rem;">Email Sequence</h4>
    <div class="admin-timeline">
        <?php foreach ($detail_emails as $email): ?>
        <div class="admin-timeline-item">
            <div class="admin-timeline-marker <?= $email['status'] === 'sent' ? 'completed' : ($email['status'] === 'failed' ? 'failed' : ''); ?>"></div>
            <div class="admin-timeline-content">
                <div class="admin-timeline-header">
                    <strong><?= htmlspecialchars($email['subject']); ?></strong>
                    <?= getEmailStatusBadge($email['status']); ?>
                </div>
                <div class="admin-timeline-meta">
                    <span>Type: <?= htmlspecialchars($email['email_type']); ?></span>
                    <span>Scheduled: <?= date('M j, Y g:i A', strtotime($email['scheduled_at'])); ?></span>
                    <?php if ($email['sent_at']): ?>
                    <span>Sent: <?= date('M j, Y g:i A', strtotime($email['sent_at'])); ?></span>
                    <?php endif; ?>
                </div>
                <?php if ($email['error_message']): ?>
                <div class="admin-timeline-error">
                    Error: <?= htmlspecialchars($email['error_message']); ?>
                </div>
                <?php endif; ?>
                <?php if ($email['status'] === 'failed'): ?>
                <a href="?action=resend&email_id=<?= $email['id']; ?>&view=<?= $detail_journey['id']; ?>&filter=<?= $filter; ?>" class="btn btn-xs btn-primary" style="margin-top: 0.5rem;">Resend</a>
                <?php endif; ?>
            </div>
        </div>
        <?php endforeach; ?>
    </div>

    <!-- Actions -->
    <div class="admin-detail-actions" style="margin-top: 1.5rem;">
        <?php if ($detail_journey['status'] === 'active'): ?>
        <a href="?action=mark_visited&id=<?= $detail_journey['id']; ?>&filter=<?= $filter; ?>" class="btn btn-xs btn-primary">Mark as Visited</a>
        <a href="?action=cancel&id=<?= $detail_journey['id']; ?>&filter=<?= $filter; ?>" class="btn btn-xs btn-outline">Cancel Journey</a>
        <?php endif; ?>
        <a href="?action=delete&id=<?= $detail_journey['id']; ?>&filter=<?= $filter; ?>" class="btn btn-xs btn-danger" data-confirm-delete>Delete</a>
    </div>
</div>
<?php endif; ?>

<!-- Journeys List -->
<div class="admin-card">
    <div class="admin-card-header">
        <div class="admin-filter-tabs" style="margin: 0;">
            <a href="?filter=active" class="admin-filter-tab <?= $filter === 'active' ? 'active' : ''; ?>">Active<?= $stats['active'] > 0 ? ' (' . $stats['active'] . ')' : ''; ?></a>
            <a href="?filter=completed" class="admin-filter-tab <?= $filter === 'completed' ? 'active' : ''; ?>">Completed</a>
            <a href="?filter=cancelled" class="admin-filter-tab <?= $filter === 'cancelled' ? 'active' : ''; ?>">Cancelled/Unsubscribed</a>
            <a href="?filter=all" class="admin-filter-tab <?= $filter === 'all' ? 'active' : ''; ?>">All</a>
        </div>
    </div>

    <?php if (empty($journeys)): ?>
        <div class="admin-empty-state">
            <span class="admin-empty-icon">📧</span>
            <p>No welcome journeys yet.</p>
            <p class="admin-muted-text">When visitors register through the "Plan Your Visit" form, their welcome journey will appear here.</p>
        </div>
    <?php else: ?>
        <div class="admin-compact-list">
            <?php foreach ($journeys as $journey): ?>
                <?php
                // Get email progress
                $stmt = $pdo->prepare("SELECT COUNT(*) as total, SUM(CASE WHEN status = 'sent' THEN 1 ELSE 0 END) as sent FROM welcome_journey_emails WHERE journey_id = ?");
                $stmt->execute([$journey['id']]);
                $emailProgress = $stmt->fetch();
                ?>
                <div class="admin-post-row">
                    <div class="admin-post-info">
                        <div class="admin-post-title">
                            <?= htmlspecialchars($journey['visitor_name']); ?>
                            <?= getStatusBadge($journey['status']); ?>
                        </div>
                        <div class="admin-post-meta">
                            <?= htmlspecialchars($journey['visitor_email']); ?> ·
                            Registered <?= date('M j, Y', strtotime($journey['registered_at'])); ?> ·
                            <?= $emailProgress['sent']; ?>/<?= $emailProgress['total']; ?> emails sent
                        </div>
                    </div>
                    <div class="admin-post-actions">
                        <a href="?view=<?= $journey['id']; ?>&filter=<?= $filter; ?>" class="btn btn-xs btn-primary">View</a>
                        <?php if ($journey['status'] === 'active'): ?>
                        <a href="?action=cancel&id=<?= $journey['id']; ?>&filter=<?= $filter; ?>" class="btn btn-xs btn-outline" title="Cancel Journey">Cancel</a>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<style>
/* Timeline styles */
.admin-timeline {
    position: relative;
    padding-left: 30px;
}

.admin-timeline::before {
    content: '';
    position: absolute;
    left: 8px;
    top: 0;
    bottom: 0;
    width: 2px;
    background: var(--admin-border);
}

.admin-timeline-item {
    position: relative;
    padding-bottom: 1.5rem;
}

.admin-timeline-item:last-child {
    padding-bottom: 0;
}

.admin-timeline-marker {
    position: absolute;
    left: -24px;
    top: 4px;
    width: 12px;
    height: 12px;
    border-radius: 50%;
    background: var(--admin-border);
    border: 2px solid var(--admin-bg);
}

.admin-timeline-marker.completed {
    background: #10b981;
}

.admin-timeline-marker.failed {
    background: #ef4444;
}

.admin-timeline-content {
    background: var(--admin-bg-muted);
    padding: 1rem;
    border-radius: 8px;
}

.admin-timeline-header {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    flex-wrap: wrap;
}

.admin-timeline-meta {
    display: flex;
    gap: 1rem;
    flex-wrap: wrap;
    font-size: 0.8rem;
    color: var(--admin-text-muted);
    margin-top: 0.5rem;
}

.admin-timeline-error {
    margin-top: 0.5rem;
    padding: 0.5rem;
    background: #fef2f2;
    border-radius: 4px;
    font-size: 0.85rem;
    color: #dc2626;
}

/* Stats grid */
.admin-stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
    gap: 1rem;
}

.admin-stat-card {
    display: flex;
    align-items: center;
    gap: 1rem;
    padding: 1.25rem;
    background: var(--admin-card-bg);
    border-radius: 12px;
    border: 1px solid var(--admin-border);
}

.admin-stat-icon {
    width: 48px;
    height: 48px;
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    flex-shrink: 0;
}

.admin-stat-content {
    display: flex;
    flex-direction: column;
}

.admin-stat-value {
    font-size: 1.5rem;
    font-weight: 700;
    color: var(--admin-text);
    line-height: 1.2;
}

.admin-stat-label {
    font-size: 0.85rem;
    color: var(--admin-text-muted);
}

/* Badge colors */
.admin-badge-info {
    background: #dbeafe;
    color: #1d4ed8;
}

.admin-badge-primary {
    background: linear-gradient(135deg, #eb008b 0%, #d4007d 100%);
    color: white;
}

.admin-badge-warning {
    background: #fef3c7;
    color: #92400e;
}
</style>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
