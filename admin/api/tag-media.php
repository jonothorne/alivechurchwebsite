<?php
/**
 * Quick Tag Media API
 * POST - Add/remove tags from media items
 */

header('Content-Type: application/json');

function json_response($data, $code = 200) {
    http_response_code($code);
    echo json_encode($data);
    exit;
}

require_once __DIR__ . '/../../includes/db-config.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    json_response(['error' => 'Authentication required'], 401);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(['error' => 'Method not allowed'], 405);
}

$pdo = getDbConnection();

$input = json_decode(file_get_contents('php://input'), true);
$mediaIds = $input['media_ids'] ?? [];
$tagId = $input['tag_id'] ?? null;
$action = $input['action'] ?? 'add'; // 'add' or 'remove'

if (empty($mediaIds) || !$tagId) {
    json_response(['error' => 'Missing media_ids or tag_id'], 400);
}

// Ensure mediaIds is an array
if (!is_array($mediaIds)) {
    $mediaIds = [$mediaIds];
}

try {
    if ($action === 'add') {
        $stmt = $pdo->prepare("INSERT IGNORE INTO media_tag_assignments (media_id, tag_id) VALUES (?, ?)");
        foreach ($mediaIds as $mediaId) {
            $stmt->execute([(int)$mediaId, (int)$tagId]);
        }
    } else {
        $stmt = $pdo->prepare("DELETE FROM media_tag_assignments WHERE media_id = ? AND tag_id = ?");
        foreach ($mediaIds as $mediaId) {
            $stmt->execute([(int)$mediaId, (int)$tagId]);
        }
    }

    json_response(['success' => true, 'updated' => count($mediaIds)]);
} catch (Exception $e) {
    json_response(['error' => 'Database error: ' . $e->getMessage()], 500);
}
