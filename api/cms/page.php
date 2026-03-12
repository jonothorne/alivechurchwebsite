<?php
/**
 * CMS API - Page Settings
 *
 * Get and update page settings (title, meta, template).
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

$pdo = getDbConnection();

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    // Get page settings
    $slug = $_GET['slug'] ?? null;

    if (!$slug) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Missing slug']);
        exit;
    }

    try {
        $stmt = $pdo->prepare("SELECT * FROM pages WHERE slug = ?");
        $stmt->execute([$slug]);
        $page = $stmt->fetch();

        if ($page) {
            echo json_encode(['success' => true, 'page' => $page]);
        } else {
            // Return empty page data for new pages
            echo json_encode(['success' => true, 'page' => [
                'slug' => $slug,
                'title' => '',
                'meta_description' => '',
                'template' => 'default',
                'layout' => 'default',
                'published' => true
            ]]);
        }
    } catch (Exception $e) {
        error_log('CMS page get error: ' . $e->getMessage());
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'Server error']);
    }

} elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Update page settings
    $input = json_decode(file_get_contents('php://input'), true);

    if (!$input) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Invalid JSON']);
        exit;
    }

    $slug = $input['slug'] ?? null;
    $title = $input['title'] ?? '';
    $metaDescription = $input['meta_description'] ?? '';
    $template = $input['template'] ?? 'default';
    $layout = $input['layout'] ?? 'default';
    $published = $input['published'] ?? true;

    if (!$slug) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Missing slug']);
        exit;
    }

    try {
        $userId = $_SESSION['admin_user_id'];

        // Check if page exists
        $stmt = $pdo->prepare("SELECT id FROM pages WHERE slug = ?");
        $stmt->execute([$slug]);
        $existing = $stmt->fetch();

        if ($existing) {
            // Update existing page
            $stmt = $pdo->prepare("
                UPDATE pages SET
                    title = ?,
                    meta_description = ?,
                    template = ?,
                    layout = ?,
                    published = ?
                WHERE slug = ?
            ");
            $stmt->execute([$title, $metaDescription, $template, $layout, $published, $slug]);
        } else {
            // Create new page
            $stmt = $pdo->prepare("
                INSERT INTO pages (slug, title, meta_description, template, layout, published, created_by)
                VALUES (?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([$slug, $title, $metaDescription, $template, $layout, $published, $userId]);
        }

        // Log activity
        log_activity($userId, 'edit_page', 'page', $existing['id'] ?? $pdo->lastInsertId(),
            "Updated page settings for '{$slug}'");

        echo json_encode(['success' => true, 'message' => 'Page settings saved']);
    } catch (Exception $e) {
        error_log('CMS page update error: ' . $e->getMessage());
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'Server error']);
    }

} else {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
}
