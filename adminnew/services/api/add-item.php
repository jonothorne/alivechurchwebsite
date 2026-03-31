<?php
/**
 * Add Service Item API
 * Adds a song, reading, or other item to a service plan
 */

require_once __DIR__ . '/../../../includes/Auth.php';
require_once __DIR__ . '/../../../includes/db-config.php';

// Check authentication
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: /adminnew/login');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /adminnew/services');
    exit;
}

$pdo = getDbConnection();

try {
    $serviceId = (int)($_POST['service_id'] ?? 0);
    $itemType = $_POST['item_type'] ?? 'song';
    $songId = (int)($_POST['song_id'] ?? 0) ?: null;
    $songKey = trim($_POST['song_key'] ?? '');
    $title = trim($_POST['title'] ?? '');
    $durationMinutes = (int)($_POST['duration_minutes'] ?? 0) ?: null;
    $notes = trim($_POST['notes'] ?? '');

    if (!$serviceId) {
        throw new Exception('Service ID is required');
    }

    // Get the next position
    $posStmt = $pdo->prepare("SELECT COALESCE(MAX(position), 0) + 1 as next_pos FROM service_items WHERE service_id = ?");
    $posStmt->execute([$serviceId]);
    $nextPosition = $posStmt->fetchColumn();

    // If it's a song item and no title provided, get it from the song
    if ($itemType === 'song' && $songId && !$title) {
        $songStmt = $pdo->prepare("SELECT title FROM songs WHERE id = ?");
        $songStmt->execute([$songId]);
        $title = $songStmt->fetchColumn() ?: '';
    }

    // Insert the service item
    $stmt = $pdo->prepare("
        INSERT INTO service_items (service_id, item_type, song_id, song_key, title, duration_minutes, notes, position, created_at, updated_at)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())
    ");
    $stmt->execute([
        $serviceId,
        $itemType,
        $songId,
        $songKey ?: null,
        $title ?: null,
        $durationMinutes,
        $notes ?: null,
        $nextPosition
    ]);

    // Redirect back to the plan page
    header("Location: /adminnew/services/plan/{$serviceId}?success=Item added to service");
    exit;

} catch (Exception $e) {
    // Redirect back with error
    $serviceId = (int)($_POST['service_id'] ?? 0);
    header("Location: /adminnew/services/plan/{$serviceId}?error=" . urlencode($e->getMessage()));
    exit;
}
