<?php
/**
 * Assignment Confirmations API
 * Handle accept/decline of service assignments via email links
 */

require_once __DIR__ . '/../../../includes/db-config.php';

$pdo = getDbConnection();

// Get action from request
$method = $_SERVER['REQUEST_METHOD'];
$token = $_GET['token'] ?? '';
$action = $_GET['action'] ?? $_POST['action'] ?? '';

header('Content-Type: application/json');

try {
    switch ($action) {
        case 'verify':
            verifyToken($pdo, $token);
            break;

        case 'confirm':
            confirmAssignment($pdo, $token);
            break;

        case 'decline':
            declineAssignment($pdo, $token, $_POST);
            break;

        case 'get-details':
            getAssignmentDetails($pdo, $token);
            break;

        default:
            throw new Exception('Invalid action');
    }
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}

/**
 * Verify that a confirmation token is valid
 */
function verifyToken(PDO $pdo, string $token): void
{
    if (!$token) {
        throw new Exception('Token is required');
    }

    $stmt = $pdo->prepare("
        SELECT sr.*,
               s.service_date, s.start_time, s.title,
               st.name as service_type_name,
               r.name as role_name,
               t.name as team_name,
               CONCAT(m.first_name, ' ', m.last_name) as member_name
        FROM service_rota sr
        JOIN services s ON sr.service_id = s.id
        JOIN service_types st ON s.service_type_id = st.id
        JOIN service_roles r ON sr.role_id = r.id
        JOIN service_teams t ON r.team_id = t.id
        LEFT JOIN members m ON sr.member_id = m.id
        WHERE sr.confirmation_token = ?
    ");
    $stmt->execute([$token]);
    $assignment = $stmt->fetch();

    if (!$assignment) {
        throw new Exception('Invalid or expired confirmation link');
    }

    // Check if service date has passed
    $serviceDate = new DateTime($assignment['service_date']);
    $now = new DateTime();
    if ($serviceDate < $now) {
        throw new Exception('This service has already occurred');
    }

    echo json_encode([
        'success' => true,
        'valid' => true,
        'status' => $assignment['status']
    ]);
}

/**
 * Get full details of assignment for confirmation page
 */
function getAssignmentDetails(PDO $pdo, string $token): void
{
    if (!$token) {
        throw new Exception('Token is required');
    }

    $stmt = $pdo->prepare("
        SELECT sr.*,
               s.service_date, s.start_time, s.end_time, s.title, s.location, s.notes,
               st.name as service_type_name, st.color as service_type_color,
               r.name as role_name,
               t.name as team_name, t.color as team_color,
               CONCAT(m.first_name, ' ', m.last_name) as member_name,
               m.email as member_email
        FROM service_rota sr
        JOIN services s ON sr.service_id = s.id
        JOIN service_types st ON s.service_type_id = st.id
        JOIN service_roles r ON sr.role_id = r.id
        JOIN service_teams t ON r.team_id = t.id
        LEFT JOIN members m ON sr.member_id = m.id
        WHERE sr.confirmation_token = ?
    ");
    $stmt->execute([$token]);
    $assignment = $stmt->fetch();

    if (!$assignment) {
        throw new Exception('Invalid or expired confirmation link');
    }

    // Format service date
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
    $assignment['other_team_members'] = $teamStmt->fetchAll();

    echo json_encode([
        'success' => true,
        'assignment' => $assignment
    ]);
}

/**
 * Confirm an assignment
 */
function confirmAssignment(PDO $pdo, string $token): void
{
    if (!$token) {
        throw new Exception('Token is required');
    }

    // Verify token and get rota ID
    $stmt = $pdo->prepare("SELECT id, status FROM service_rota WHERE confirmation_token = ?");
    $stmt->execute([$token]);
    $rota = $stmt->fetch();

    if (!$rota) {
        throw new Exception('Invalid confirmation link');
    }

    if ($rota['status'] === 'confirmed') {
        throw new Exception('You have already confirmed this assignment');
    }

    // Update status to confirmed
    $updateStmt = $pdo->prepare("
        UPDATE service_rota
        SET status = 'confirmed',
            responded_at = NOW(),
            decline_reason = NULL,
            updated_at = NOW()
        WHERE id = ?
    ");
    $updateStmt->execute([$rota['id']]);

    // Log notification response
    $logStmt = $pdo->prepare("
        UPDATE service_assignment_notifications
        SET responded_at = NOW()
        WHERE rota_id = ?
        ORDER BY sent_at DESC
        LIMIT 1
    ");
    $logStmt->execute([$rota['id']]);

    echo json_encode([
        'success' => true,
        'message' => 'Thank you for confirming! We look forward to seeing you serve.'
    ]);
}

/**
 * Decline an assignment
 */
function declineAssignment(PDO $pdo, string $token, array $input): void
{
    if (!$token) {
        throw new Exception('Token is required');
    }

    $reason = trim($input['reason'] ?? '');

    // Verify token and get rota ID
    $stmt = $pdo->prepare("SELECT id, status, service_id, member_id FROM service_rota WHERE confirmation_token = ?");
    $stmt->execute([$token]);
    $rota = $stmt->fetch();

    if (!$rota) {
        throw new Exception('Invalid confirmation link');
    }

    if ($rota['status'] === 'declined') {
        throw new Exception('You have already declined this assignment');
    }

    // Update status to declined
    $updateStmt = $pdo->prepare("
        UPDATE service_rota
        SET status = 'declined',
            responded_at = NOW(),
            decline_reason = ?,
            updated_at = NOW()
        WHERE id = ?
    ");
    $updateStmt->execute([$reason ?: null, $rota['id']]);

    // Log notification response
    $logStmt = $pdo->prepare("
        UPDATE service_assignment_notifications
        SET responded_at = NOW()
        WHERE rota_id = ?
        ORDER BY sent_at DESC
        LIMIT 1
    ");
    $logStmt->execute([$rota['id']]);

    // Log as a scheduling conflict that needs resolution
    $conflictStmt = $pdo->prepare("
        INSERT INTO service_scheduling_conflicts (service_id, member_id, conflict_type, conflict_details)
        VALUES (?, ?, 'unavailable', ?)
    ");
    $conflictStmt->execute([
        $rota['service_id'],
        $rota['member_id'],
        'Member declined assignment' . ($reason ? ': ' . $reason : '')
    ]);

    echo json_encode([
        'success' => true,
        'message' => 'Your response has been recorded. We will find someone else to fill this role.'
    ]);
}

/**
 * Generate a unique confirmation token
 */
function generateConfirmationToken(): string
{
    return bin2hex(random_bytes(32));
}
