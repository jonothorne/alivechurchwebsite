<?php
/**
 * CMS API - Media Library
 *
 * Lists media files from the database.
 */

header('Content-Type: application/json');

// Start session and check auth
session_start();
require_once __DIR__ . '/../../includes/Auth.php';
require_once __DIR__ . '/../../includes/db-config.php';

// Check if user is logged in
if (!is_logged_in()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Not authenticated']);
    exit;
}

try {
    $pdo = getDbConnection();

    // Get pagination parameters
    $page = max(1, intval($_GET['page'] ?? 1));
    $limit = min(100, max(10, intval($_GET['limit'] ?? 50)));
    $offset = ($page - 1) * $limit;

    // Get media files
    $stmt = $pdo->prepare("
        SELECT id, filename, original_filename, file_url, file_path, file_type, file_size,
               width, height, alt_text, caption, created_at
        FROM media
        ORDER BY created_at DESC
        LIMIT ? OFFSET ?
    ");
    $stmt->execute([$limit, $offset]);
    $media = $stmt->fetchAll();

    // Get variants for each media item
    $mediaIds = array_column($media, 'id');
    $variants = [];

    if (!empty($mediaIds)) {
        $placeholders = str_repeat('?,', count($mediaIds) - 1) . '?';
        $variantStmt = $pdo->prepare("
            SELECT media_id, variant_name, variant_path, format, width, height, file_size
            FROM image_variants
            WHERE media_id IN ($placeholders)
            ORDER BY width ASC
        ");
        $variantStmt->execute($mediaIds);
        $variantRows = $variantStmt->fetchAll();

        // Group variants by media_id
        foreach ($variantRows as $v) {
            $variants[$v['media_id']][] = $v;
        }
    }

    // Attach variants to media items
    foreach ($media as &$item) {
        $item['variants'] = $variants[$item['id']] ?? [];
    }

    // Get total count
    $countStmt = $pdo->query("SELECT COUNT(*) FROM media");
    $total = $countStmt->fetchColumn();

    echo json_encode([
        'success' => true,
        'media' => $media,
        'pagination' => [
            'page' => $page,
            'limit' => $limit,
            'total' => intval($total),
            'pages' => ceil($total / $limit)
        ]
    ]);
} catch (Exception $e) {
    error_log('CMS media list error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Server error']);
}
