<?php
/**
 * Media Library API
 * GET - List media from database
 * POST - Upload new media
 */

// Start output buffering to catch any stray PHP output
ob_start();

// Suppress HTML errors, return JSON instead
ini_set('display_errors', 0);
ini_set('html_errors', 0);
error_reporting(E_ALL);

// Set JSON header early
header('Content-Type: application/json');

// Clean output function - ensures only JSON is returned
function json_response($data, $code = 200) {
    ob_end_clean(); // Discard any buffered output
    http_response_code($code);
    echo json_encode($data);
    exit;
}

// Include required files
try {
    require_once __DIR__ . '/../../includes/auth.php';
    require_once __DIR__ . '/../../includes/db-config.php';
} catch (Exception $e) {
    json_response(['error' => 'Server configuration error'], 500);
}

// Check authentication (JSON-friendly version instead of require_auth())
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    json_response(['error' => 'Authentication required'], 401);
}

// Get database connection
try {
    $pdo = getDbConnection();
} catch (Exception $e) {
    json_response(['error' => 'Database connection failed'], 500);
}

// GET - List media (images only by default)
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    try {
        $type = $_GET['type'] ?? 'image';
        $limit = min((int)($_GET['limit'] ?? 50), 100);

        if ($type === 'all') {
            $stmt = $pdo->prepare("SELECT * FROM media ORDER BY created_at DESC LIMIT ?");
            $stmt->execute([$limit]);
        } else {
            $stmt = $pdo->prepare("SELECT * FROM media WHERE file_type = ? ORDER BY created_at DESC LIMIT ?");
            $stmt->execute([$type, $limit]);
        }

        $media = $stmt->fetchAll();

        // Format for picker
        $items = array_map(function($item) {
            return [
                'id' => $item['id'],
                'url' => '/' . $item['file_path'],
                'name' => $item['original_filename'],
                'alt' => $item['alt_text'] ?? '',
                'type' => $item['file_type'],
                'size' => $item['file_size']
            ];
        }, $media);

        json_response(['success' => true, 'data' => $items]);
    } catch (Exception $e) {
        json_response(['error' => 'Failed to fetch media: ' . $e->getMessage()], 500);
    }
}

// POST - Upload media
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Verify CSRF
        $csrf = $_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
        if (!function_exists('verify_csrf_token') || !verify_csrf_token($csrf)) {
            json_response(['error' => 'Invalid CSRF token'], 403);
        }

        if (empty($_FILES['file'])) {
            json_response(['error' => 'No file uploaded'], 400);
        }

        $file = $_FILES['file'];
        $uploadDir = __DIR__ . '/../../uploads/';

        // Create upload directory if needed
        if (!is_dir($uploadDir)) {
            if (!mkdir($uploadDir, 0755, true)) {
                json_response(['error' => 'Failed to create upload directory'], 500);
            }
        }

        // Check directory is writable
        if (!is_writable($uploadDir)) {
            json_response(['error' => 'Upload directory is not writable'], 500);
        }

        // Validate file type
        $allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $actualType = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);

        if (!in_array($actualType, $allowedTypes)) {
            json_response(['error' => 'Invalid file type. Allowed: JPG, PNG, GIF, WebP'], 400);
        }

        // Validate file size
        if ($file['size'] > 10 * 1024 * 1024) {
            json_response(['error' => 'File too large. Max 10MB'], 400);
        }

        // Check for upload errors
        if ($file['error'] !== UPLOAD_ERR_OK) {
            $errorMessages = [
                UPLOAD_ERR_INI_SIZE => 'File exceeds server upload limit',
                UPLOAD_ERR_FORM_SIZE => 'File exceeds form upload limit',
                UPLOAD_ERR_PARTIAL => 'File was only partially uploaded',
                UPLOAD_ERR_NO_FILE => 'No file was uploaded',
                UPLOAD_ERR_NO_TMP_DIR => 'Missing temporary folder',
                UPLOAD_ERR_CANT_WRITE => 'Failed to write file to disk',
                UPLOAD_ERR_EXTENSION => 'Upload blocked by extension',
            ];
            $msg = $errorMessages[$file['error']] ?? 'Unknown upload error';
            json_response(['error' => $msg], 400);
        }

        // Generate unique filename
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        $allowedExts = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
        if (!in_array($ext, $allowedExts)) {
            $ext = 'jpg'; // Default to jpg if extension is weird
        }

        // Generate SEO-friendly filename if title provided
        $seoName = $_POST['seo_name'] ?? '';
        if (!empty($seoName)) {
            // Create slug from title
            $slug = strtolower($seoName);
            $slug = preg_replace('/[^a-z0-9\s-]/', '', $slug); // Remove special chars
            $slug = preg_replace('/[\s_]+/', '-', $slug);       // Replace spaces with hyphens
            $slug = preg_replace('/-+/', '-', $slug);           // Remove duplicate hyphens
            $slug = trim($slug, '-');                            // Trim hyphens from ends
            $slug = substr($slug, 0, 50);                        // Limit length

            // Format: DDMMYYYY-slug.ext (e.g., 07032026-honey-im-home.jpg)
            $datePrefix = date('dmY');
            $uniqueName = $datePrefix . '-' . $slug . '.' . $ext;

            // If file exists, add a counter
            $counter = 1;
            $baseName = $datePrefix . '-' . $slug;
            while (file_exists($uploadDir . $uniqueName)) {
                $uniqueName = $baseName . '-' . $counter . '.' . $ext;
                $counter++;
            }
        } else {
            // Fallback to timestamp-based name
            $uniqueName = date('dmY') . '-' . uniqid() . '.' . $ext;
        }

        $destination = $uploadDir . $uniqueName;

        // Move uploaded file
        if (!move_uploaded_file($file['tmp_name'], $destination)) {
            json_response(['error' => 'Failed to save file to disk'], 500);
        }

        // Insert into database
        $userId = $_SESSION['admin_user_id'] ?? null;
        $filePath = 'uploads/' . $uniqueName;
        $fileUrl = '/uploads/' . $uniqueName;

        $stmt = $pdo->prepare("INSERT INTO media (filename, original_filename, file_type, file_size, file_path, file_url, uploaded_by) VALUES (?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([
            $uniqueName,
            $file['name'],
            'image',
            $file['size'],
            $filePath,
            $fileUrl,
            $userId
        ]);

        $mediaId = $pdo->lastInsertId();

        // Log activity
        if ($userId && function_exists('log_activity')) {
            try {
                log_activity($userId, 'upload', 'media', $mediaId, 'Uploaded: ' . $file['name']);
            } catch (Exception $e) {
                // Don't fail upload if logging fails
            }
        }

        json_response([
            'success' => true,
            'data' => [
                'id' => $mediaId,
                'url' => '/uploads/' . $uniqueName,
                'name' => $file['name']
            ]
        ]);
    } catch (Exception $e) {
        json_response(['error' => 'Server error: ' . $e->getMessage()], 500);
    }
}

json_response(['error' => 'Method not allowed'], 405);
