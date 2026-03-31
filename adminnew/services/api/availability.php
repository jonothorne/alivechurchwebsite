<?php
/**
 * Member Availability API
 * Handle member availability/blackout dates
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
    $action = $_GET['action'] ?? 'list';
} else {
    $input = json_decode(file_get_contents('php://input'), true) ?? $_POST;
    $action = $input['action'] ?? '';
}

try {
    switch ($action) {
        case 'list':
            listAvailability($pdo, $_GET);
            break;

        case 'add':
            addAvailability($pdo, $input);
            break;

        case 'add-range':
            addAvailabilityRange($pdo, $input);
            break;

        case 'remove':
            removeAvailability($pdo, $input);
            break;

        case 'check':
            checkAvailability($pdo, $_GET);
            break;

        default:
            throw new Exception('Invalid action');
    }
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}

/**
 * List availability/blackout dates for a member
 */
function listAvailability(PDO $pdo, array $params): void
{
    $memberId = (int)($params['member_id'] ?? 0);
    $startDate = $params['start_date'] ?? date('Y-m-d');
    $endDate = $params['end_date'] ?? date('Y-m-d', strtotime('+6 months'));

    if (!$memberId) {
        throw new Exception('Member ID is required');
    }

    $stmt = $pdo->prepare("
        SELECT *
        FROM member_availability
        WHERE member_id = ?
        AND unavailable_date BETWEEN ? AND ?
        ORDER BY unavailable_date
    ");
    $stmt->execute([$memberId, $startDate, $endDate]);
    $availability = $stmt->fetchAll();

    echo json_encode([
        'success' => true,
        'availability' => $availability
    ]);
}

/**
 * Add a single unavailable date
 */
function addAvailability(PDO $pdo, array $input): void
{
    $memberId = (int)($input['member_id'] ?? 0);
    $unavailableDate = $input['unavailable_date'] ?? '';
    $reason = trim($input['reason'] ?? '');
    $isRecurring = (bool)($input['is_recurring'] ?? false);

    if (!$memberId || !$unavailableDate) {
        throw new Exception('Member ID and date are required');
    }

    // Validate date
    $date = DateTime::createFromFormat('Y-m-d', $unavailableDate);
    if (!$date || $date->format('Y-m-d') !== $unavailableDate) {
        throw new Exception('Invalid date format');
    }

    $stmt = $pdo->prepare("
        INSERT INTO member_availability (member_id, unavailable_date, reason, is_recurring)
        VALUES (?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE reason = ?, is_recurring = ?, updated_at = NOW()
    ");
    $stmt->execute([$memberId, $unavailableDate, $reason ?: null, $isRecurring ? 1 : 0, $reason ?: null, $isRecurring ? 1 : 0]);

    echo json_encode([
        'success' => true,
        'message' => 'Availability updated',
        'id' => $pdo->lastInsertId() ?: null
    ]);
}

/**
 * Add a range of unavailable dates
 */
function addAvailabilityRange(PDO $pdo, array $input): void
{
    $memberId = (int)($input['member_id'] ?? 0);
    $startDate = $input['start_date'] ?? '';
    $endDate = $input['end_date'] ?? '';
    $reason = trim($input['reason'] ?? '');
    $isRecurring = (bool)($input['is_recurring'] ?? false);

    if (!$memberId || !$startDate || !$endDate) {
        throw new Exception('Member ID, start date, and end date are required');
    }

    // Validate dates
    $start = DateTime::createFromFormat('Y-m-d', $startDate);
    $end = DateTime::createFromFormat('Y-m-d', $endDate);

    if (!$start || $start->format('Y-m-d') !== $startDate) {
        throw new Exception('Invalid start date format');
    }
    if (!$end || $end->format('Y-m-d') !== $endDate) {
        throw new Exception('Invalid end date format');
    }
    if ($end < $start) {
        throw new Exception('End date must be after start date');
    }

    // Calculate days
    $interval = $start->diff($end);
    $days = $interval->days + 1;

    if ($days > 365) {
        throw new Exception('Date range cannot exceed 365 days');
    }

    // Insert each date
    $stmt = $pdo->prepare("
        INSERT INTO member_availability (member_id, unavailable_date, reason, is_recurring)
        VALUES (?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE reason = ?, is_recurring = ?, updated_at = NOW()
    ");

    $current = clone $start;
    $inserted = 0;

    while ($current <= $end) {
        $dateStr = $current->format('Y-m-d');
        $stmt->execute([$memberId, $dateStr, $reason ?: null, $isRecurring ? 1 : 0, $reason ?: null, $isRecurring ? 1 : 0]);
        $inserted++;
        $current->modify('+1 day');
    }

    echo json_encode([
        'success' => true,
        'message' => "$inserted date(s) marked as unavailable",
        'count' => $inserted
    ]);
}

/**
 * Remove an unavailable date
 */
function removeAvailability(PDO $pdo, array $input): void
{
    $availabilityId = (int)($input['id'] ?? 0);

    if (!$availabilityId) {
        throw new Exception('Availability ID is required');
    }

    $stmt = $pdo->prepare("DELETE FROM member_availability WHERE id = ?");
    $stmt->execute([$availabilityId]);

    echo json_encode([
        'success' => true,
        'message' => 'Availability removed'
    ]);
}

/**
 * Check if member is available on a specific date
 */
function checkAvailability(PDO $pdo, array $params): void
{
    $memberId = (int)($params['member_id'] ?? 0);
    $date = $params['date'] ?? date('Y-m-d');

    if (!$memberId) {
        throw new Exception('Member ID is required');
    }

    $stmt = $pdo->prepare("
        SELECT COUNT(*) as is_unavailable
        FROM member_availability
        WHERE member_id = ?
        AND unavailable_date = ?
    ");
    $stmt->execute([$memberId, $date]);
    $result = $stmt->fetch();

    $isAvailable = $result['is_unavailable'] == 0;

    echo json_encode([
        'success' => true,
        'is_available' => $isAvailable,
        'date' => $date
    ]);
}
