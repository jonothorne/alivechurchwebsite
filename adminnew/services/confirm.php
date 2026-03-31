<?php
/**
 * Service Assignment Confirmation Page
 * Public page for members to confirm/decline assignments via email link
 */

// No authentication required - using token-based access
require_once __DIR__ . '/../../includes/db-config.php';

$pdo = getDbConnection();

$token = $_GET['token'] ?? '';
$action = $_GET['action'] ?? '';
$error = null;
$success = null;
$assignment = null;

// Validate token and get assignment details
if ($token) {
    $stmt = $pdo->prepare("
        SELECT sr.*,
               s.service_date, s.start_time, s.end_time, s.title, s.location, s.notes,
               st.name as service_type_name, st.color as service_type_color,
               r.name as role_name,
               t.name as team_name, t.color as team_color,
               CONCAT(m.first_name, ' ', m.last_name) as member_name,
               m.first_name,
               m.email as member_email
        FROM service_rota sr
        JOIN services s ON sr.service_id = s.id
        JOIN service_types st ON s.service_type_id = st.id
        JOIN service_roles r ON sr.role_id = r.id
        JOIN service_teams t ON r.team_id = t.id
        JOIN members m ON sr.member_id = m.id
        WHERE sr.confirmation_token = ?
    ");
    $stmt->execute([$token]);
    $assignment = $stmt->fetch();

    if ($assignment) {
        // Check if service date has passed
        $serviceDate = new DateTime($assignment['service_date']);
        $now = new DateTime();
        if ($serviceDate < $now) {
            $error = 'This service has already occurred.';
        }

        // Handle quick confirm/decline actions from email links
        if ($action === 'confirm' && !$error) {
            $updateStmt = $pdo->prepare("
                UPDATE service_rota
                SET status = 'confirmed',
                    responded_at = NOW(),
                    updated_at = NOW()
                WHERE id = ?
            ");
            $updateStmt->execute([$assignment['id']]);
            $success = 'Thank you for confirming! We look forward to seeing you serve.';
            $assignment['status'] = 'confirmed';

        } elseif ($action === 'decline' && !$error) {
            // For decline, we'll show a form to get the reason
            // This is handled below
        }
    } else {
        $error = 'Invalid or expired confirmation link.';
    }
} else {
    $error = 'No confirmation token provided.';
}

// Handle decline form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $assignment && !$error) {
    $reason = trim($_POST['reason'] ?? '');

    $updateStmt = $pdo->prepare("
        UPDATE service_rota
        SET status = 'declined',
            responded_at = NOW(),
            decline_reason = ?,
            updated_at = NOW()
        WHERE id = ?
    ");
    $updateStmt->execute([$reason ?: null, $assignment['id']]);

    // Log as conflict
    $conflictStmt = $pdo->prepare("
        INSERT INTO service_scheduling_conflicts (service_id, member_id, conflict_type, conflict_details)
        VALUES (?, ?, 'unavailable', ?)
    ");
    $conflictStmt->execute([
        $assignment['service_id'],
        $assignment['member_id'],
        'Member declined assignment' . ($reason ? ': ' . $reason : '')
    ]);

    $success = 'Your response has been recorded. We will find someone else to fill this role.';
    $assignment['status'] = 'declined';
}

// Format display data
if ($assignment) {
    $serviceDate = new DateTime($assignment['service_date']);
    $assignment['formatted_date'] = $serviceDate->format('l, F j, Y');
    $assignment['formatted_start_time'] = date('g:i A', strtotime($assignment['start_time']));
    if ($assignment['end_time']) {
        $assignment['formatted_end_time'] = date('g:i A', strtotime($assignment['end_time']));
    }

    // Get other team members for this service
    $teamStmt = $pdo->prepare("
        SELECT CONCAT(m.first_name, ' ', m.last_name) as name,
               r.name as role_name
        FROM service_rota sr
        JOIN service_roles r ON sr.role_id = r.id
        LEFT JOIN members m ON sr.member_id = m.id
        WHERE sr.service_id = ?
        AND sr.id != ?
        AND sr.member_id IS NOT NULL
        ORDER BY r.sort_order
    ");
    $teamStmt->execute([$assignment['service_id'], $assignment['id']]);
    $otherMembers = $teamStmt->fetchAll();
}

// Simple standalone page (no admin header/footer)
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Service Assignment Confirmation</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
            line-height: 1.6;
            color: #333;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        .container {
            max-width: 600px;
            width: 100%;
            background: white;
            border-radius: 12px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
            overflow: hidden;
        }
        .header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 30px 20px;
            text-align: center;
        }
        .header h1 { font-size: 24px; margin-bottom: 5px; }
        .header p { opacity: 0.9; font-size: 14px; }
        .content { padding: 30px; }
        .alert {
            padding: 15px 20px;
            border-radius: 8px;
            margin-bottom: 20px;
            font-size: 15px;
        }
        .alert-error {
            background: #fee2e2;
            color: #991b1b;
            border: 1px solid #fecaca;
        }
        .alert-success {
            background: #d1fae5;
            color: #065f46;
            border: 1px solid #a7f3d0;
        }
        .service-card {
            background: #f8f9fa;
            border-left: 4px solid #667eea;
            padding: 20px;
            margin: 20px 0;
            border-radius: 8px;
        }
        .service-title {
            font-size: 22px;
            font-weight: 600;
            margin-bottom: 15px;
            color: #1f2937;
        }
        .detail-row {
            display: flex;
            margin: 12px 0;
            align-items: flex-start;
        }
        .detail-label {
            font-weight: 600;
            min-width: 100px;
            color: #6b7280;
            font-size: 14px;
        }
        .detail-value {
            color: #1f2937;
            flex: 1;
        }
        .role-badge {
            display: inline-block;
            background: #667eea;
            color: white;
            padding: 6px 16px;
            border-radius: 6px;
            font-weight: 600;
            font-size: 15px;
        }
        .status-badge {
            display: inline-block;
            padding: 6px 12px;
            border-radius: 6px;
            font-size: 13px;
            font-weight: 600;
        }
        .status-pending { background: #fef3c7; color: #92400e; }
        .status-confirmed { background: #d1fae5; color: #065f46; }
        .status-declined { background: #fee2e2; color: #991b1b; }
        .team-list {
            background: white;
            border: 1px solid #e5e7eb;
            border-radius: 8px;
            padding: 15px;
            margin-top: 20px;
        }
        .team-list h3 {
            font-size: 16px;
            margin-bottom: 12px;
            color: #1f2937;
        }
        .team-member {
            padding: 8px 0;
            border-bottom: 1px solid #f3f4f6;
            font-size: 14px;
        }
        .team-member:last-child { border-bottom: none; }
        .team-member-name { font-weight: 500; color: #1f2937; }
        .team-member-role { color: #6b7280; font-size: 13px; margin-left: 8px; }
        .buttons {
            display: flex;
            gap: 12px;
            margin-top: 30px;
        }
        .btn {
            flex: 1;
            padding: 14px 24px;
            border: none;
            border-radius: 8px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            text-decoration: none;
            text-align: center;
            transition: all 0.2s;
        }
        .btn-confirm {
            background: #10b981;
            color: white;
        }
        .btn-confirm:hover {
            background: #059669;
        }
        .btn-decline {
            background: #ef4444;
            color: white;
        }
        .btn-decline:hover {
            background: #dc2626;
        }
        .btn:disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }
        .form-group {
            margin: 20px 0;
        }
        .form-label {
            display: block;
            font-weight: 600;
            margin-bottom: 8px;
            color: #374151;
            font-size: 14px;
        }
        .form-input {
            width: 100%;
            padding: 10px 12px;
            border: 1px solid #d1d5db;
            border-radius: 6px;
            font-size: 15px;
            font-family: inherit;
        }
        .form-input:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }
        .footer {
            text-align: center;
            padding: 20px;
            background: #f9fafb;
            border-top: 1px solid #e5e7eb;
            font-size: 13px;
            color: #6b7280;
        }
        @media (max-width: 600px) {
            .buttons { flex-direction: column; }
            .detail-row { flex-direction: column; }
            .detail-label { margin-bottom: 4px; }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>Service Assignment</h1>
            <p>Confirm Your Availability</p>
        </div>

        <div class="content">
            <?php if ($error): ?>
                <div class="alert alert-error">
                    <?= htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>

            <?php if ($success): ?>
                <div class="alert alert-success">
                    <?= htmlspecialchars($success); ?>
                </div>
            <?php endif; ?>

            <?php if ($assignment && !$error): ?>
                <p style="margin-bottom: 20px;">
                    Hi <strong><?= htmlspecialchars($assignment['first_name']); ?></strong>,
                </p>

                <div class="service-card">
                    <div class="service-title">
                        <?= htmlspecialchars($assignment['title'] ?: $assignment['service_type_name']); ?>
                    </div>

                    <div class="detail-row">
                        <span class="detail-label">Date</span>
                        <span class="detail-value"><?= htmlspecialchars($assignment['formatted_date']); ?></span>
                    </div>

                    <div class="detail-row">
                        <span class="detail-label">Time</span>
                        <span class="detail-value">
                            <?= htmlspecialchars($assignment['formatted_start_time']); ?>
                            <?php if ($assignment['formatted_end_time']): ?>
                                - <?= htmlspecialchars($assignment['formatted_end_time']); ?>
                            <?php endif; ?>
                        </span>
                    </div>

                    <?php if ($assignment['location']): ?>
                        <div class="detail-row">
                            <span class="detail-label">Location</span>
                            <span class="detail-value"><?= htmlspecialchars($assignment['location']); ?></span>
                        </div>
                    <?php endif; ?>

                    <div class="detail-row">
                        <span class="detail-label">Your Role</span>
                        <span class="detail-value">
                            <span class="role-badge"><?= htmlspecialchars($assignment['role_name']); ?></span>
                        </span>
                    </div>

                    <div class="detail-row">
                        <span class="detail-label">Team</span>
                        <span class="detail-value"><?= htmlspecialchars($assignment['team_name']); ?></span>
                    </div>

                    <div class="detail-row">
                        <span class="detail-label">Status</span>
                        <span class="detail-value">
                            <span class="status-badge status-<?= $assignment['status']; ?>">
                                <?= ucfirst($assignment['status']); ?>
                            </span>
                        </span>
                    </div>
                </div>

                <?php if (!empty($otherMembers)): ?>
                    <div class="team-list">
                        <h3>Serving With You</h3>
                        <?php foreach ($otherMembers as $member): ?>
                            <div class="team-member">
                                <span class="team-member-name"><?= htmlspecialchars($member['name']); ?></span>
                                <span class="team-member-role"><?= htmlspecialchars($member['role_name']); ?></span>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>

                <?php if ($assignment['status'] === 'pending' && !$success): ?>
                    <?php if ($action === 'decline'): ?>
                        <!-- Decline form -->
                        <form method="POST" style="margin-top: 30px;">
                            <div class="form-group">
                                <label class="form-label" for="reason">
                                    Why can't you make it? (optional)
                                </label>
                                <input type="text" id="reason" name="reason" class="form-input"
                                       placeholder="e.g., Out of town, Schedule conflict">
                            </div>
                            <div class="buttons">
                                <a href="?token=<?= urlencode($token); ?>" class="btn" style="background: #6b7280; color: white;">
                                    Go Back
                                </a>
                                <button type="submit" class="btn btn-decline">
                                    Confirm I Can't Make It
                                </button>
                            </div>
                        </form>
                    <?php else: ?>
                        <!-- Confirm/Decline buttons -->
                        <div class="buttons">
                            <a href="?token=<?= urlencode($token); ?>&action=confirm" class="btn btn-confirm">
                                I Can Serve
                            </a>
                            <a href="?token=<?= urlencode($token); ?>&action=decline" class="btn btn-decline">
                                I Can't Make It
                            </a>
                        </div>
                    <?php endif; ?>
                <?php elseif ($assignment['status'] === 'confirmed'): ?>
                    <p style="margin-top: 20px; text-align: center; color: #059669; font-weight: 500;">
                        You have confirmed this assignment. Thank you!
                    </p>
                <?php elseif ($assignment['status'] === 'declined'): ?>
                    <p style="margin-top: 20px; text-align: center; color: #dc2626; font-weight: 500;">
                        You have declined this assignment.
                    </p>
                    <?php if ($assignment['decline_reason']): ?>
                        <p style="text-align: center; color: #6b7280; font-size: 14px;">
                            Reason: <?= htmlspecialchars($assignment['decline_reason']); ?>
                        </p>
                    <?php endif; ?>
                <?php endif; ?>
            <?php endif; ?>
        </div>

        <div class="footer">
            Alive Church - Service Scheduling
        </div>
    </div>
</body>
</html>
