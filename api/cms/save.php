<?php
/**
 * CMS API - Save Content Block
 *
 * Saves editable content blocks from inline editor.
 */

header('Content-Type: application/json');

// Start session and check auth
session_start();
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/cms/ContentManager.php';

// Check if user is logged in
if (!is_logged_in()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Not authenticated']);
    exit;
}

// Only accept POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit;
}

// Get JSON input
$input = json_decode(file_get_contents('php://input'), true);

if (!$input) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Invalid JSON']);
    exit;
}

// Validate required fields
$key = $input['key'] ?? null;
$page = $input['page'] ?? null;
$content = $input['content'] ?? '';
$type = $input['type'] ?? 'html';
$isGlobal = $input['isGlobal'] ?? false;

if (!$key) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Missing block key']);
    exit;
}

// Get user ID - support both session formats
$userId = $_SESSION['admin_user_id'] ?? $_SESSION['admin_user']['id'] ?? null;

try {
    if ($isGlobal) {
        // Save global content
        $cms = new ContentManager();
        $success = $cms->saveGlobal($key, $content, $userId);
    } else {
        // Save page-specific content
        if (!$page) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Missing page slug']);
            exit;
        }

        $cms = new ContentManager($page);
        $success = $cms->saveBlock($key, $content, $type, $userId);
    }

    if ($success) {
        // Log activity
        log_activity($userId, 'edit_content', 'content_block', null,
            "Updated content block '{$key}' on page '{$page}'");

        echo json_encode([
            'success' => true,
            'message' => 'Content saved successfully'
        ]);
    } else {
        throw new Exception('Failed to save content');
    }
} catch (Exception $e) {
    error_log('CMS save error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Server error']);
}
