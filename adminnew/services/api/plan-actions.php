<?php
/**
 * Service Plan Actions API
 * Handles confirm, delete item, save notes, etc.
 */

require_once __DIR__ . '/../../../includes/Auth.php';
require_once __DIR__ . '/../../../includes/db-config.php';
require_once __DIR__ . '/../../../includes/services/SetlistAI.php';

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
$input = json_decode(file_get_contents('php://input'), true) ?? $_POST;
$action = $input['action'] ?? $_GET['action'] ?? '';

try {
    switch ($action) {
        case 'confirm':
            confirmService($pdo, $input);
            break;

        case 'delete-item':
            deleteItem($pdo, $input);
            break;

        case 'save-notes':
            saveNotes($pdo, $input);
            break;

        case 'update-item':
            updateItem($pdo, $input);
            break;

        case 'reorder-items':
            reorderItems($pdo, $input);
            break;

        case 'assign-teams':
            assignTeams($pdo, $input);
            break;

        case 'update-key':
            updateKey($pdo, $input);
            break;

        default:
            throw new Exception('Invalid action');
    }
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}

/**
 * Confirm a service (change status to confirmed)
 */
function confirmService(PDO $pdo, array $input): void
{
    $serviceId = (int)($input['service_id'] ?? 0);

    if (!$serviceId) {
        throw new Exception('Service ID is required');
    }

    // Check service exists
    $stmt = $pdo->prepare("SELECT id, status FROM services WHERE id = ?");
    $stmt->execute([$serviceId]);
    $service = $stmt->fetch();

    if (!$service) {
        throw new Exception('Service not found');
    }

    // Update status to confirmed
    $stmt = $pdo->prepare("UPDATE services SET status = 'confirmed', updated_at = NOW() WHERE id = ?");
    $stmt->execute([$serviceId]);

    // Learn from this service for AI setlist suggestions
    try {
        $userId = $_SESSION['admin_user_id'] ?? null;
        $setlistAI = new SetlistAI($pdo, $userId);
        $setlistAI->learnFromService($serviceId);
    } catch (Exception $e) {
        // Don't fail the confirmation if learning fails
        error_log("SetlistAI learning failed: " . $e->getMessage());
    }

    echo json_encode([
        'success' => true,
        'message' => 'Service confirmed successfully',
        'status' => 'confirmed'
    ]);
}

/**
 * Delete a service item
 */
function deleteItem(PDO $pdo, array $input): void
{
    $itemId = (int)($input['item_id'] ?? 0);

    if (!$itemId) {
        throw new Exception('Item ID is required');
    }

    // Get item to verify it exists and get service_id
    $stmt = $pdo->prepare("SELECT id, service_id FROM service_items WHERE id = ?");
    $stmt->execute([$itemId]);
    $item = $stmt->fetch();

    if (!$item) {
        throw new Exception('Item not found');
    }

    // Delete the item
    $stmt = $pdo->prepare("DELETE FROM service_items WHERE id = ?");
    $stmt->execute([$itemId]);

    // Reorder remaining items sequentially
    $stmt = $pdo->prepare("SELECT id FROM service_items WHERE service_id = ? ORDER BY position");
    $stmt->execute([$item['service_id']]);
    $items = $stmt->fetchAll(PDO::FETCH_COLUMN);

    $pos = 1;
    foreach ($items as $id) {
        $pdo->prepare("UPDATE service_items SET position = ? WHERE id = ?")->execute([$pos++, $id]);
    }

    echo json_encode([
        'success' => true,
        'message' => 'Item removed from service'
    ]);
}

/**
 * Save service notes
 */
function saveNotes(PDO $pdo, array $input): void
{
    $serviceId = (int)($input['service_id'] ?? 0);
    $notes = trim($input['notes'] ?? '');

    if (!$serviceId) {
        throw new Exception('Service ID is required');
    }

    // Update notes - store in description field or add notes column
    $stmt = $pdo->prepare("UPDATE services SET description = ?, updated_at = NOW() WHERE id = ?");
    $stmt->execute([$notes ?: null, $serviceId]);

    echo json_encode([
        'success' => true,
        'message' => 'Notes saved'
    ]);
}

/**
 * Update a service item
 */
function updateItem(PDO $pdo, array $input): void
{
    $itemId = (int)($input['item_id'] ?? 0);
    $title = trim($input['title'] ?? '');
    $notes = trim($input['notes'] ?? '');
    $duration = (int)($input['duration_minutes'] ?? 0) ?: null;
    $plannedDuration = (int)($input['planned_duration'] ?? 0) ?: null;
    $presenter = trim($input['presenter'] ?? '');
    $worshipNotes = trim($input['worship_notes'] ?? '');
    $techNotes = trim($input['tech_notes'] ?? '');
    $transitionNotes = trim($input['transition_notes'] ?? '');
    $videoUrl = trim($input['video_url'] ?? '');
    $slidesUrl = trim($input['slides_url'] ?? '');

    if (!$itemId) {
        throw new Exception('Item ID is required');
    }

    $stmt = $pdo->prepare("
        UPDATE service_items
        SET title = ?,
            notes = ?,
            duration_minutes = ?,
            planned_duration = ?,
            presenter = ?,
            worship_notes = ?,
            tech_notes = ?,
            transition_notes = ?,
            video_url = ?,
            slides_url = ?,
            updated_at = NOW()
        WHERE id = ?
    ");
    $stmt->execute([
        $title ?: null,
        $notes ?: null,
        $duration,
        $plannedDuration,
        $presenter ?: null,
        $worshipNotes ?: null,
        $techNotes ?: null,
        $transitionNotes ?: null,
        $videoUrl ?: null,
        $slidesUrl ?: null,
        $itemId
    ]);

    echo json_encode([
        'success' => true,
        'message' => 'Item updated'
    ]);
}

/**
 * Reorder service items
 */
function reorderItems(PDO $pdo, array $input): void
{
    $serviceId = (int)($input['service_id'] ?? 0);
    $order = $input['order'] ?? [];

    if (!$serviceId || empty($order)) {
        throw new Exception('Service ID and order are required');
    }

    $pos = 1;
    foreach ($order as $itemId) {
        $stmt = $pdo->prepare("UPDATE service_items SET position = ? WHERE id = ? AND service_id = ?");
        $stmt->execute([$pos++, (int)$itemId, $serviceId]);
    }

    echo json_encode([
        'success' => true,
        'message' => 'Order updated'
    ]);
}

/**
 * Assign teams to a service
 */
function assignTeams(PDO $pdo, array $input): void
{
    $serviceId = (int)($input['service_id'] ?? 0);
    $teamIds = $input['team_ids'] ?? [];

    if (!$serviceId) {
        throw new Exception('Service ID is required');
    }

    if (empty($teamIds)) {
        throw new Exception('At least one team is required');
    }

    $assignedCount = 0;

    foreach ($teamIds as $teamId) {
        $teamId = (int)$teamId;

        // Get all active members of this team
        $members = $pdo->prepare("
            SELECT stm.member_id
            FROM service_team_members stm
            WHERE stm.team_id = ? AND stm.is_active = 1
        ");
        $members->execute([$teamId]);

        foreach ($members->fetchAll(PDO::FETCH_COLUMN) as $memberId) {
            // Check if already assigned
            $exists = $pdo->prepare("
                SELECT id FROM service_assignments
                WHERE service_id = ? AND team_id = ? AND member_id = ?
            ");
            $exists->execute([$serviceId, $teamId, $memberId]);

            if (!$exists->fetch()) {
                // Insert new assignment
                $stmt = $pdo->prepare("
                    INSERT INTO service_assignments (service_id, team_id, member_id, status, created_at, updated_at)
                    VALUES (?, ?, ?, 'pending', NOW(), NOW())
                ");
                $stmt->execute([$serviceId, $teamId, $memberId]);
                $assignedCount++;
            }
        }
    }

    echo json_encode([
        'success' => true,
        'message' => $assignedCount > 0
            ? "Assigned {$assignedCount} team member(s) to service"
            : "Teams assigned (members already assigned)"
    ]);
}

/**
 * Update song key for a service item
 */
function updateKey(PDO $pdo, array $input): void
{
    $itemId = (int)($input['item_id'] ?? 0);
    $key = trim($input['key'] ?? '');

    if (!$itemId) {
        throw new Exception('Item ID is required');
    }

    $stmt = $pdo->prepare("UPDATE service_items SET song_key = ?, updated_at = NOW() WHERE id = ?");
    $stmt->execute([$key ?: null, $itemId]);

    echo json_encode([
        'success' => true,
        'message' => $key ? "Key set to {$key}" : 'Key cleared'
    ]);
}
