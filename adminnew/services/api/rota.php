<?php
/**
 * Rota API
 * Handle rota operations: add roles, assign members, get suggestions
 */

require_once __DIR__ . '/../../../includes/Auth.php';
require_once __DIR__ . '/../../../includes/db-config.php';

// Check authentication
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

header('Content-Type: application/json');

if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

$pdo = getDbConnection();

// Get action from request
$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    $action = $_GET['action'] ?? '';
} else {
    $input = json_decode(file_get_contents('php://input'), true) ?? $_POST;
    $action = $input['action'] ?? '';
}

try {
    switch ($action) {
        case 'add-role':
            addRole($pdo, $input);
            break;

        case 'assign-member':
            assignMember($pdo, $input);
            break;

        case 'remove':
            removeRotaItem($pdo, $input);
            break;

        case 'suggestions':
            getMemberSuggestions($pdo, $_GET);
            break;

        case 'update-status':
            updateStatus($pdo, $input);
            break;

        case 'check-conflicts':
            checkConflicts($pdo, $_GET);
            break;

        case 'send-notifications':
            sendNotifications($pdo, $input);
            break;

        default:
            throw new Exception('Invalid action');
    }
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}

/**
 * Add a role to the service rota
 */
function addRole(PDO $pdo, array $input): void
{
    $serviceId = (int)($input['service_id'] ?? 0);
    $roleId = (int)($input['role_id'] ?? 0);

    if (!$serviceId || !$roleId) {
        throw new Exception('Service ID and Role ID are required');
    }

    // Get max sort order
    $maxSort = $pdo->prepare("SELECT COALESCE(MAX(sort_order), 0) + 1 FROM service_rota WHERE service_id = ?");
    $maxSort->execute([$serviceId]);
    $sortOrder = $maxSort->fetchColumn();

    // Insert rota item (unassigned)
    $stmt = $pdo->prepare("
        INSERT INTO service_rota (service_id, role_id, status, sort_order, created_at)
        VALUES (?, ?, 'unassigned', ?, NOW())
    ");
    $stmt->execute([$serviceId, $roleId, $sortOrder]);

    echo json_encode([
        'success' => true,
        'message' => 'Role added to rota',
        'rota_id' => $pdo->lastInsertId()
    ]);
}

/**
 * Assign a member to a rota slot with conflict detection
 */
function assignMember(PDO $pdo, array $input): void
{
    $rotaId = (int)($input['rota_id'] ?? 0);
    $memberId = (int)($input['member_id'] ?? 0);
    $addCapability = (bool)($input['add_capability'] ?? false);
    $sendNotification = (bool)($input['send_notification'] ?? true);

    if (!$rotaId || !$memberId) {
        throw new Exception('Rota ID and Member ID are required');
    }

    // Get the role_id and service details from the rota item
    $rotaStmt = $pdo->prepare("
        SELECT sr.role_id, sr.service_id, s.service_date, s.title,
               st.name as service_type_name,
               r.name as role_name
        FROM service_rota sr
        JOIN services s ON sr.service_id = s.id
        JOIN service_types st ON s.service_type_id = st.id
        JOIN service_roles r ON sr.role_id = r.id
        WHERE sr.id = ?
    ");
    $rotaStmt->execute([$rotaId]);
    $rotaData = $rotaStmt->fetch();

    if (!$rotaData) {
        throw new Exception('Rota item not found');
    }

    $roleId = $rotaData['role_id'];
    $serviceId = $rotaData['service_id'];
    $serviceDate = $rotaData['service_date'];

    // Check for conflicts
    $conflicts = [];

    // 1. Check if member is unavailable on this date
    $unavailableStmt = $pdo->prepare("
        SELECT reason FROM member_availability
        WHERE member_id = ? AND unavailable_date = ?
    ");
    $unavailableStmt->execute([$memberId, $serviceDate]);
    if ($unavailable = $unavailableStmt->fetch()) {
        $conflicts[] = [
            'type' => 'unavailable',
            'message' => 'Member is marked unavailable on this date' . ($unavailable['reason'] ? ': ' . $unavailable['reason'] : '')
        ];
    }

    // 2. Check how many roles they're already assigned to for this service
    $assignmentCountStmt = $pdo->prepare("
        SELECT COUNT(*) as count
        FROM service_rota
        WHERE service_id = ? AND member_id = ? AND id != ?
    ");
    $assignmentCountStmt->execute([$serviceId, $memberId, $rotaId]);
    $assignmentCount = $assignmentCountStmt->fetchColumn();

    if ($assignmentCount >= 3) {
        $conflicts[] = [
            'type' => 'over_scheduled',
            'message' => "Member is already assigned to $assignmentCount role(s) for this service"
        ];
    }

    // 3. Check if member has capability for this role (if not adding capability)
    if (!$addCapability) {
        $capabilityStmt = $pdo->prepare("
            SELECT skill_level FROM member_role_capabilities
            WHERE member_id = ? AND role_id = ? AND is_active = 1
        ");
        $capabilityStmt->execute([$memberId, $roleId]);
        if (!$capabilityStmt->fetch()) {
            $conflicts[] = [
                'type' => 'insufficient_skill',
                'message' => 'Member does not have this role capability'
            ];
        }
    }

    // If adding capability, insert into member_role_capabilities
    if ($addCapability && $roleId) {
        $capStmt = $pdo->prepare("
            INSERT INTO member_role_capabilities (member_id, role_id, skill_level, is_active, created_at, updated_at)
            VALUES (?, ?, 'competent', 1, NOW(), NOW())
            ON DUPLICATE KEY UPDATE is_active = 1, updated_at = NOW()
        ");
        $capStmt->execute([$memberId, $roleId]);
    }

    // Generate confirmation token for email link
    $confirmationToken = bin2hex(random_bytes(32));

    // Update rota item
    $stmt = $pdo->prepare("
        UPDATE service_rota
        SET member_id = ?,
            status = 'pending',
            assigned_at = NOW(),
            confirmation_token = ?,
            updated_at = NOW()
        WHERE id = ?
    ");
    $stmt->execute([$memberId, $confirmationToken, $rotaId]);

    // Log conflicts if any
    foreach ($conflicts as $conflict) {
        $conflictStmt = $pdo->prepare("
            INSERT INTO service_scheduling_conflicts (service_id, member_id, conflict_type, conflict_details)
            VALUES (?, ?, ?, ?)
        ");
        $conflictStmt->execute([
            $serviceId,
            $memberId,
            $conflict['type'],
            $conflict['message']
        ]);
    }

    // If sending notification, get member email and log it
    if ($sendNotification) {
        $memberStmt = $pdo->prepare("SELECT email FROM users WHERE id = ?");
        $memberStmt->execute([$memberId]);
        $memberEmail = $memberStmt->fetchColumn();

        if ($memberEmail) {
            $notifStmt = $pdo->prepare("
                INSERT INTO service_assignment_notifications (rota_id, notification_type, sent_to_email)
                VALUES (?, 'assignment', ?)
            ");
            $notifStmt->execute([$rotaId, $memberEmail]);

            // TODO: Actually send email here
            // For now, just log that we would send it
        }
    }

    echo json_encode([
        'success' => true,
        'message' => 'Member assigned',
        'conflicts' => $conflicts,
        'confirmation_token' => $confirmationToken
    ]);
}

/**
 * Remove a rota item
 */
function removeRotaItem(PDO $pdo, array $input): void
{
    $rotaId = (int)($input['rota_id'] ?? 0);

    if (!$rotaId) {
        throw new Exception('Rota ID is required');
    }

    $stmt = $pdo->prepare("DELETE FROM service_rota WHERE id = ?");
    $stmt->execute([$rotaId]);

    echo json_encode([
        'success' => true,
        'message' => 'Role removed from rota'
    ]);
}

/**
 * Get member suggestions for a role
 * Returns members who can perform the role, sorted by:
 * 1. Availability (not blocked out on that date)
 * 2. Longest time since last served
 * Note: Same person CAN be assigned to multiple roles
 */
function getMemberSuggestions(PDO $pdo, array $params): void
{
    $roleId = (int)($params['role_id'] ?? 0);
    $serviceId = (int)($params['service_id'] ?? 0);
    $serviceDate = $params['service_date'] ?? date('Y-m-d');

    if (!$roleId) {
        throw new Exception('Role ID is required');
    }

    // Get members who have this role capability
    // Exclude those who are unavailable on this date
    // Order by last served date (oldest first)
    // Note: We allow same person to be assigned to multiple roles
    $stmt = $pdo->prepare("
        SELECT
            m.id,
            CONCAT(m.first_name, ' ', m.last_name) as name,
            m.email,
            mrc.skill_level,
            (
                SELECT MAX(s.service_date)
                FROM service_rota sr2
                JOIN services s ON sr2.service_id = s.id
                WHERE sr2.member_id = m.id
                AND sr2.status IN ('confirmed', 'pending')
            ) as last_served_date,
            (
                SELECT COUNT(*)
                FROM service_rota sr3
                WHERE sr3.service_id = ?
                AND sr3.member_id = m.id
            ) as already_assigned_count
        FROM users m
        JOIN member_role_capabilities mrc ON m.id = mrc.member_id
        WHERE mrc.role_id = ?
        AND mrc.is_active = 1
        AND m.id NOT IN (
            SELECT ma.member_id
            FROM member_availability ma
            WHERE ma.unavailable_date = ?
        )
        ORDER BY
            already_assigned_count ASC,
            CASE WHEN last_served_date IS NULL THEN 0 ELSE 1 END,
            last_served_date ASC,
            m.first_name
        LIMIT 15
    ");

    $stmt->execute([$serviceId, $roleId, $serviceDate]);
    $members = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Format last served for display
    foreach ($members as &$member) {
        $member['has_capability'] = true;
        if ($member['last_served_date']) {
            $date = new DateTime($member['last_served_date']);
            $now = new DateTime();
            $diff = $now->diff($date);

            if ($diff->days === 0) {
                $member['last_served'] = 'Today';
            } elseif ($diff->days === 1) {
                $member['last_served'] = 'Yesterday';
            } elseif ($diff->days < 7) {
                $member['last_served'] = $diff->days . ' days ago';
            } elseif ($diff->days < 30) {
                $weeks = floor($diff->days / 7);
                $member['last_served'] = $weeks . ' week' . ($weeks > 1 ? 's' : '') . ' ago';
            } else {
                $member['last_served'] = $date->format('M j');
            }
        } else {
            $member['last_served'] = 'Never served';
        }
    }

    // If no members have this role capability, get all team members for this role's team
    $otherTeamMembers = [];
    if (empty($members)) {
        // Get the team for this role
        $teamStmt = $pdo->prepare("SELECT team_id FROM service_roles WHERE id = ?");
        $teamStmt->execute([$roleId]);
        $teamId = $teamStmt->fetchColumn();

        if ($teamId) {
            // Get all team members who don't have this capability
            $otherStmt = $pdo->prepare("
                SELECT
                    m.id,
                    CONCAT(m.first_name, ' ', m.last_name) as name,
                    m.email,
                    NULL as skill_level,
                    (
                        SELECT COUNT(*)
                        FROM service_rota sr3
                        WHERE sr3.service_id = ?
                        AND sr3.member_id = m.id
                    ) as already_assigned_count
                FROM users m
                JOIN service_team_members stm ON m.id = stm.member_id
                WHERE stm.team_id = ?
                AND stm.is_active = 1
                AND m.id NOT IN (
                    SELECT ma.member_id
                    FROM member_availability ma
                    WHERE ma.unavailable_date = ?
                )
                AND m.id NOT IN (
                    SELECT mrc.member_id
                    FROM member_role_capabilities mrc
                    WHERE mrc.role_id = ?
                    AND mrc.is_active = 1
                )
                ORDER BY m.first_name
                LIMIT 20
            ");
            $otherStmt->execute([$serviceId, $teamId, $serviceDate, $roleId]);
            $otherTeamMembers = $otherStmt->fetchAll(PDO::FETCH_ASSOC);

            foreach ($otherTeamMembers as &$member) {
                $member['has_capability'] = false;
                $member['last_served'] = 'Not assigned to this role';
            }
        }
    }

    echo json_encode([
        'success' => true,
        'members' => $members,
        'other_team_members' => $otherTeamMembers
    ]);
}

/**
 * Update rota item status (confirm/decline)
 */
function updateStatus(PDO $pdo, array $input): void
{
    $rotaId = (int)($input['rota_id'] ?? 0);
    $status = $input['status'] ?? '';
    $reason = $input['reason'] ?? '';

    if (!$rotaId || !in_array($status, ['confirmed', 'declined'])) {
        throw new Exception('Invalid rota ID or status');
    }

    $stmt = $pdo->prepare("
        UPDATE service_rota
        SET status = ?,
            responded_at = NOW(),
            decline_reason = ?,
            updated_at = NOW()
        WHERE id = ?
    ");
    $stmt->execute([$status, $status === 'declined' ? $reason : null, $rotaId]);

    echo json_encode([
        'success' => true,
        'message' => $status === 'confirmed' ? 'Confirmed!' : 'Declined'
    ]);
}

/**
 * Check for scheduling conflicts for a service
 */
function checkConflicts(PDO $pdo, array $params): void
{
    $serviceId = (int)($params['service_id'] ?? 0);

    if (!$serviceId) {
        throw new Exception('Service ID is required');
    }

    // Get service date
    $serviceStmt = $pdo->prepare("SELECT service_date FROM services WHERE id = ?");
    $serviceStmt->execute([$serviceId]);
    $serviceDate = $serviceStmt->fetchColumn();

    // Get all conflicts for this service
    $conflictsStmt = $pdo->prepare("
        SELECT sc.*,
               CONCAT(m.first_name, ' ', m.last_name) as member_name
        FROM service_scheduling_conflicts sc
        JOIN members m ON sc.member_id = m.id
        WHERE sc.service_id = ?
        AND sc.resolved = 0
        ORDER BY sc.created_at DESC
    ");
    $conflictsStmt->execute([$serviceId]);
    $conflicts = $conflictsStmt->fetchAll();

    // Check for members assigned multiple times
    $multipleAssignmentsStmt = $pdo->prepare("
        SELECT sr.member_id,
               CONCAT(m.first_name, ' ', m.last_name) as member_name,
               COUNT(*) as assignment_count,
               GROUP_CONCAT(r.name SEPARATOR ', ') as roles
        FROM service_rota sr
        JOIN members m ON sr.member_id = m.id
        JOIN service_roles r ON sr.role_id = r.id
        WHERE sr.service_id = ?
        AND sr.member_id IS NOT NULL
        GROUP BY sr.member_id
        HAVING COUNT(*) > 2
    ");
    $multipleAssignmentsStmt->execute([$serviceId]);
    $multipleAssignments = $multipleAssignmentsStmt->fetchAll();

    // Check for unavailable members
    $unavailableStmt = $pdo->prepare("
        SELECT sr.id as rota_id,
               CONCAT(m.first_name, ' ', m.last_name) as member_name,
               r.name as role_name,
               ma.reason
        FROM service_rota sr
        JOIN members m ON sr.member_id = m.id
        JOIN service_roles r ON sr.role_id = r.id
        JOIN member_availability ma ON m.id = ma.member_id
        WHERE sr.service_id = ?
        AND ma.unavailable_date = ?
        AND sr.member_id IS NOT NULL
    ");
    $unavailableStmt->execute([$serviceId, $serviceDate]);
    $unavailable = $unavailableStmt->fetchAll();

    echo json_encode([
        'success' => true,
        'conflicts' => $conflicts,
        'multiple_assignments' => $multipleAssignments,
        'unavailable_members' => $unavailable,
        'total_conflicts' => count($conflicts) + count($multipleAssignments) + count($unavailable)
    ]);
}

/**
 * Send notifications to all pending assignments for a service
 */
function sendNotifications(PDO $pdo, array $input): void
{
    $serviceId = (int)($input['service_id'] ?? 0);

    if (!$serviceId) {
        throw new Exception('Service ID is required');
    }

    // Get all pending assignments
    $stmt = $pdo->prepare("
        SELECT sr.id, sr.confirmation_token,
               CONCAT(m.first_name, ' ', m.last_name) as member_name,
               m.email,
               r.name as role_name
        FROM service_rota sr
        JOIN members m ON sr.member_id = m.id
        JOIN service_roles r ON sr.role_id = r.id
        WHERE sr.service_id = ?
        AND sr.status = 'pending'
        AND sr.member_id IS NOT NULL
        AND m.email IS NOT NULL
    ");
    $stmt->execute([$serviceId]);
    $assignments = $stmt->fetchAll();

    $sent = 0;
    foreach ($assignments as $assignment) {
        // Log the notification
        $logStmt = $pdo->prepare("
            INSERT INTO service_assignment_notifications (rota_id, notification_type, sent_to_email)
            VALUES (?, 'reminder', ?)
        ");
        $logStmt->execute([$assignment['id'], $assignment['email']]);

        // TODO: Actually send email here
        $sent++;
    }

    echo json_encode([
        'success' => true,
        'message' => "$sent notification(s) sent",
        'count' => $sent
    ]);
}
