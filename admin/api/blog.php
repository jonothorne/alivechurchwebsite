<?php
/**
 * Blog API
 * PATCH - Auto-save blog post fields
 */

ob_start();
ini_set('display_errors', 0);
ini_set('html_errors', 0);
error_reporting(E_ALL);

header('Content-Type: application/json');

function json_response($data, $code = 200) {
    ob_end_clean();
    http_response_code($code);
    echo json_encode($data);
    exit;
}

try {
    require_once __DIR__ . '/../../includes/Auth.php';
    require_once __DIR__ . '/../../includes/db-config.php';
} catch (Exception $e) {
    json_response(['error' => 'Server configuration error'], 500);
}

// Check authentication
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    json_response(['error' => 'Authentication required'], 401);
}

try {
    $pdo = getDbConnection();
} catch (Exception $e) {
    json_response(['error' => 'Database connection failed'], 500);
}

// PATCH - Auto-save specific fields
if ($_SERVER['REQUEST_METHOD'] === 'PATCH' || $_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Get JSON body
        $input = json_decode(file_get_contents('php://input'), true);
        if (!$input) {
            // Try POST data instead
            $input = $_POST;
        }

        // Verify CSRF
        $csrf = $input['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
        if (!function_exists('verify_csrf_token') || !verify_csrf_token($csrf)) {
            json_response(['error' => 'Invalid CSRF token'], 403);
        }

        $postId = (int)($input['post_id'] ?? 0);
        if (!$postId) {
            json_response(['error' => 'Post ID required'], 400);
        }

        // Verify post exists and user can edit it
        $stmt = $pdo->prepare("SELECT id, author_id FROM blog_posts WHERE id = ?");
        $stmt->execute([$postId]);
        $post = $stmt->fetch();

        if (!$post) {
            json_response(['error' => 'Post not found'], 404);
        }

        // Track what was updated
        $updated = [];

        // Update featured_image
        if (isset($input['featured_image'])) {
            $stmt = $pdo->prepare("UPDATE blog_posts SET featured_image = ? WHERE id = ?");
            $stmt->execute([$input['featured_image'] ?: null, $postId]);
            $updated[] = 'featured_image';
        }

        // Update thumbnail
        if (isset($input['thumbnail'])) {
            $stmt = $pdo->prepare("UPDATE blog_posts SET thumbnail = ? WHERE id = ?");
            $stmt->execute([$input['thumbnail'] ?: null, $postId]);
            $updated[] = 'thumbnail';
        }

        // Update category_id
        if (isset($input['category_id'])) {
            $categoryId = $input['category_id'] === '' ? null : (int)$input['category_id'];
            $stmt = $pdo->prepare("UPDATE blog_posts SET category_id = ? WHERE id = ?");
            $stmt->execute([$categoryId, $postId]);
            $updated[] = 'category_id';
        }

        // Update tags
        if (isset($input['tags'])) {
            $tags = is_array($input['tags']) ? $input['tags'] : [];

            // Delete existing tags
            $stmt = $pdo->prepare("DELETE FROM blog_post_tags WHERE post_id = ?");
            $stmt->execute([$postId]);

            // Insert new tags
            if (!empty($tags)) {
                $stmt = $pdo->prepare("INSERT INTO blog_post_tags (post_id, tag_id) VALUES (?, ?)");
                foreach ($tags as $tagId) {
                    $stmt->execute([$postId, (int)$tagId]);
                }
            }
            $updated[] = 'tags';
        }

        json_response([
            'success' => true,
            'updated' => $updated,
            'message' => 'Saved'
        ]);

    } catch (Exception $e) {
        json_response(['error' => 'Server error: ' . $e->getMessage()], 500);
    }
}

json_response(['error' => 'Method not allowed'], 405);
