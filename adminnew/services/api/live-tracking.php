<?php
/**
 * Live Tracking API
 * Handles real-time service tracking (start/end times)
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
$input = json_decode(file_get_contents('php://input'), true) ?? $_POST;
$action = $input['action'] ?? $_GET['action'] ?? '';

try {
    switch ($action) {
        case 'start-service':
            startService($pdo, $input);
            break;

        case 'end-service':
            endService($pdo, $input);
            break;

        case 'start-item':
            startItem($pdo, $input);
            break;

        case 'end-item':
            endItem($pdo, $input);
            break;

        case 'update-item-notes':
            updateItemNotes($pdo, $input);
            break;

        default:
            throw new Exception('Invalid action');
    }
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}

/**
 * Start a service in live mode
 */
function startService(PDO $pdo, array $input): void
{
    $serviceId = (int)($input['service_id'] ?? 0);

    if (!$serviceId) {
        throw new Exception('Service ID is required');
    }

    $stmt = $pdo->prepare("
        UPDATE services
        SET live_mode_active = TRUE,
            live_started_at = NOW(),
            actual_start_time = NOW(),
            updated_at = NOW()
        WHERE id = ?
    ");
    $stmt->execute([$serviceId]);

    echo json_encode([
        'success' => true,
        'message' => 'Service started in live mode',
        'started_at' => date('Y-m-d H:i:s')
    ]);
}

/**
 * End a service live mode
 */
function endService(PDO $pdo, array $input): void
{
    $serviceId = (int)($input['service_id'] ?? 0);

    if (!$serviceId) {
        throw new Exception('Service ID is required');
    }

    $stmt = $pdo->prepare("
        UPDATE services
        SET live_mode_active = FALSE,
            actual_end_time = NOW(),
            updated_at = NOW()
        WHERE id = ?
    ");
    $stmt->execute([$serviceId]);

    echo json_encode([
        'success' => true,
        'message' => 'Service ended',
        'ended_at' => date('Y-m-d H:i:s')
    ]);
}

/**
 * Start a service item
 */
function startItem(PDO $pdo, array $input): void
{
    $itemId = (int)($input['item_id'] ?? 0);

    if (!$itemId) {
        throw new Exception('Item ID is required');
    }

    // Check if item exists
    $stmt = $pdo->prepare("SELECT id, service_id FROM service_items WHERE id = ?");
    $stmt->execute([$itemId]);
    $item = $stmt->fetch();

    if (!$item) {
        throw new Exception('Item not found');
    }

    // Update item with actual start time
    $stmt = $pdo->prepare("
        UPDATE service_items
        SET actual_start_time = NOW(),
            updated_at = NOW()
        WHERE id = ?
    ");
    $stmt->execute([$itemId]);

    // If this is the first item, start the service
    $checkStmt = $pdo->prepare("SELECT live_mode_active FROM services WHERE id = ?");
    $checkStmt->execute([$item['service_id']]);
    $service = $checkStmt->fetch();

    if (!$service['live_mode_active']) {
        $pdo->prepare("
            UPDATE services
            SET live_mode_active = TRUE,
                live_started_at = NOW(),
                actual_start_time = NOW(),
                updated_at = NOW()
            WHERE id = ?
        ")->execute([$item['service_id']]);
    }

    echo json_encode([
        'success' => true,
        'message' => 'Item started',
        'started_at' => date('Y-m-d H:i:s')
    ]);
}

/**
 * End a service item
 */
function endItem(PDO $pdo, array $input): void
{
    $itemId = (int)($input['item_id'] ?? 0);

    if (!$itemId) {
        throw new Exception('Item ID is required');
    }

    // Update item with actual end time
    $stmt = $pdo->prepare("
        UPDATE service_items
        SET actual_end_time = NOW(),
            updated_at = NOW()
        WHERE id = ?
    ");
    $stmt->execute([$itemId]);

    echo json_encode([
        'success' => true,
        'message' => 'Item ended',
        'ended_at' => date('Y-m-d H:i:s')
    ]);
}

/**
 * Update item notes (worship, tech, transition)
 */
function updateItemNotes(PDO $pdo, array $input): void
{
    $itemId = (int)($input['item_id'] ?? 0);
    $noteType = $input['note_type'] ?? 'notes'; // notes, worship_notes, tech_notes, transition_notes
    $noteText = trim($input['note_text'] ?? '');

    if (!$itemId) {
        throw new Exception('Item ID is required');
    }

    // Validate note type
    $validTypes = ['notes', 'worship_notes', 'tech_notes', 'transition_notes'];
    if (!in_array($noteType, $validTypes)) {
        throw new Exception('Invalid note type');
    }

    // Update the specific note field
    $stmt = $pdo->prepare("
        UPDATE service_items
        SET {$noteType} = ?,
            updated_at = NOW()
        WHERE id = ?
    ");
    $stmt->execute([$noteText ?: null, $itemId]);

    echo json_encode([
        'success' => true,
        'message' => 'Notes updated'
    ]);
}
