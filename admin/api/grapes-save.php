<?php
/**
 * GrapesJS Save/Load API Endpoint
 * Handles saving and loading of GrapesJS page data
 */

require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/db-config.php';

// Require authentication
require_auth();

// Get database connection
$pdo = getDbConnection();

// Set JSON header
header('Content-Type: application/json');

// Get page ID
$page_id = $_GET['page_id'] ?? $_POST['page_id'] ?? null;

if (!$page_id || !is_numeric($page_id)) {
    http_response_code(400);
    die(json_encode(['error' => 'Invalid page_id']));
}

// Handle GET request - Load page data
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    try {
        $stmt = $pdo->prepare("
            SELECT grapes_html, grapes_css, grapes_components, grapes_styles
            FROM pages
            WHERE id = ?
        ");
        $stmt->execute([$page_id]);
        $page = $stmt->fetch();

        if (!$page) {
            http_response_code(404);
            die(json_encode(['error' => 'Page not found']));
        }

        // Return GrapesJS format - parse JSON fields properly
        $components = $page['grapes_components'];
        $styles = $page['grapes_styles'];

        // If components/styles are JSON strings, decode them, otherwise use empty objects
        if (!empty($components) && is_string($components)) {
            $components = json_decode($components);
        }
        if (!empty($styles) && is_string($styles)) {
            $styles = json_decode($styles);
        }

        // Return in GrapesJS expected format
        echo json_encode([
            'success' => true,
            'data' => [
                'html' => $page['grapes_html'] ?? '',
                'css' => $page['grapes_css'] ?? '',
                'components' => $components ?: null,
                'styles' => $styles ?: null
            ]
        ]);

    } catch (PDOException $e) {
        http_response_code(500);
        error_log('GrapesJS load error: ' . $e->getMessage());
        die(json_encode(['error' => 'Database error']));
    }
    exit;
}

// Handle POST request - Save page data
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Get JSON payload
    $rawInput = file_get_contents('php://input');
    $data = json_decode($rawInput, true);

    // If not JSON, try form data
    if (!$data) {
        $data = $_POST;
    }

    // Verify CSRF token
    $csrf_token = $data['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    if (!verify_csrf_token($csrf_token)) {
        http_response_code(403);
        die(json_encode(['error' => 'Invalid CSRF token']));
    }

    // Extract data
    $html = $data['html'] ?? '';
    $css = $data['css'] ?? '';
    $components = $data['components'] ?? '';
    $styles = $data['styles'] ?? '';

    // If components/styles are arrays, encode them
    if (is_array($components)) {
        $components = json_encode($components);
    }
    if (is_array($styles)) {
        $styles = json_encode($styles);
    }

    // Sanitize HTML (basic - for production, use HTMLPurifier)
    // Note: For now, we'll trust admin users, but add sanitization later
    $html = trim($html);
    $css = trim($css);

    try {
        // Update page
        $stmt = $pdo->prepare("
            UPDATE pages
            SET
                grapes_html = ?,
                grapes_css = ?,
                grapes_components = ?,
                grapes_styles = ?,
                builder_mode = 'grapes',
                updated_at = NOW()
            WHERE id = ?
        ");

        $stmt->execute([
            $html,
            $css,
            $components,
            $styles,
            $page_id
        ]);

        // Log activity
        log_activity(
            $_SESSION['admin_user_id'],
            'update',
            'page',
            $page_id,
            'Updated page with GrapesJS editor'
        );

        // Return success
        echo json_encode([
            'success' => true,
            'message' => 'Page saved successfully',
            'data' => [
                'page_id' => $page_id,
                'updated_at' => date('Y-m-d H:i:s')
            ]
        ]);

    } catch (PDOException $e) {
        http_response_code(500);
        error_log('GrapesJS save error: ' . $e->getMessage());
        die(json_encode(['error' => 'Failed to save page']));
    }

    exit;
}

// Unsupported method
http_response_code(405);
die(json_encode(['error' => 'Method not allowed']));
