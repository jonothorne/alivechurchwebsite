<?php
/**
 * CMS API - Save Content Block
 *
 * Saves editable content blocks from inline editor.
 */

header('Content-Type: application/json');

// Start session and check auth
session_start();
require_once __DIR__ . '/../../includes/Auth.php';
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

// Debug logging
error_log("CMS Save Request - Key: {$key}, Page: {$page}, Type: {$type}, IsGlobal: " . ($isGlobal ? 'true' : 'false') . ", Content length: " . strlen($content));

if (!$key) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Missing block key']);
    exit;
}

// Get user ID - support both session formats
$userId = $_SESSION['admin_user_id'] ?? $_SESSION['admin_user']['id'] ?? null;

try {
    // Check if this is a blog post edit
    if ($page && preg_match('/^blog-post-(\d+)$/', $page, $matches)) {
        $postId = $matches[1];

        // Save directly to blog_posts table
        require_once __DIR__ . '/../../includes/db-config.php';
        $pdo = getDbConnection();

        $stmt = $pdo->prepare("UPDATE blog_posts SET content = ?, updated_at = NOW() WHERE id = ?");
        $success = $stmt->execute([$content, $postId]);

        if ($success) {
            log_activity($userId, 'edit_blog_post', 'blog_post', $postId,
                "Updated blog post content (ID: {$postId})");

            echo json_encode([
                'success' => true,
                'message' => 'Blog post saved successfully'
            ]);
        } else {
            throw new Exception('Failed to save blog post');
        }
    } elseif ($isGlobal) {
        // Save global content
        $cms = new ContentManager();
        $success = $cms->saveGlobal($key, $content, $userId);

        if ($success) {
            log_activity($userId, 'edit_content', 'content_block', null,
                "Updated global content block '{$key}'");

            echo json_encode([
                'success' => true,
                'message' => 'Content saved successfully'
            ]);
        } else {
            throw new Exception('Failed to save content');
        }
    } else {
        // Save page-specific content
        if (!$page) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Missing page slug']);
            exit;
        }

        $cms = new ContentManager($page);
        $success = $cms->saveBlock($key, $content, $type, $userId);

        if ($success) {
            log_activity($userId, 'edit_content', 'content_block', null,
                "Updated content block '{$key}' on page '{$page}'");

            echo json_encode([
                'success' => true,
                'message' => 'Content saved successfully',
                'debug' => [
                    'page' => $page,
                    'key' => $key,
                    'content_length' => strlen($content)
                ]
            ]);
        } else {
            throw new Exception('Failed to save content');
        }
    }
} catch (Exception $e) {
    error_log('CMS save error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Server error']);
}
