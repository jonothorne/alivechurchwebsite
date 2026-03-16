<?php
/**
 * Rotate Media API
 * POST - Rotate an image by 90 degrees
 */

header('Content-Type: application/json');

function json_response($data, $code = 200) {
    http_response_code($code);
    echo json_encode($data);
    exit;
}

require_once __DIR__ . '/../../includes/db-config.php';
require_once __DIR__ . '/../../includes/Auth.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check authentication - support both unified Auth and legacy admin session
$pdo = getDbConnection();
$auth = new Auth($pdo);
$isAuthorized = false;

// Method 1: Unified Auth system
if ($auth->check() && $auth->isEditor()) {
    $isAuthorized = true;
}
// Method 2: Legacy admin session
elseif (isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true) {
    if (isset($_SESSION['admin_user']['role']) && in_array($_SESSION['admin_user']['role'], ['admin', 'editor'])) {
        $isAuthorized = true;
    }
}

if (!$isAuthorized) {
    json_response(['error' => 'Authentication required'], 401);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(['error' => 'Method not allowed'], 405);
}

$input = json_decode(file_get_contents('php://input'), true);
$mediaId = $input['media_id'] ?? null;
$direction = $input['direction'] ?? 'right'; // 'left' or 'right'

if (!$mediaId) {
    json_response(['error' => 'Missing media_id'], 400);
}

// Get media info
$stmt = $pdo->prepare("SELECT * FROM media WHERE id = ?");
$stmt->execute([$mediaId]);
$media = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$media) {
    json_response(['error' => 'Media not found'], 404);
}

if ($media['file_type'] !== 'image') {
    json_response(['error' => 'Only images can be rotated'], 400);
}

// Get full path
$basePath = __DIR__ . '/../../';
$filePath = $basePath . $media['file_path'];

if (!file_exists($filePath)) {
    json_response(['error' => 'File not found on disk'], 404);
}

// Determine rotation angle
$angle = $direction === 'left' ? 90 : -90;

// Load image based on type
$extension = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));
$image = null;

switch ($extension) {
    case 'jpg':
    case 'jpeg':
        $image = @imagecreatefromjpeg($filePath);
        break;
    case 'png':
        $image = @imagecreatefrompng($filePath);
        break;
    case 'gif':
        $image = @imagecreatefromgif($filePath);
        break;
    case 'webp':
        $image = @imagecreatefromwebp($filePath);
        break;
    default:
        json_response(['error' => 'Unsupported image format'], 400);
}

if (!$image) {
    json_response(['error' => 'Failed to load image'], 500);
}

// Rotate the image
$rotated = imagerotate($image, $angle, 0);
if (!$rotated) {
    imagedestroy($image);
    json_response(['error' => 'Failed to rotate image'], 500);
}

// For PNG, preserve transparency
if ($extension === 'png') {
    imagealphablending($rotated, false);
    imagesavealpha($rotated, true);
}

// Save rotated image
$saved = false;
switch ($extension) {
    case 'jpg':
    case 'jpeg':
        $saved = imagejpeg($rotated, $filePath, 90);
        break;
    case 'png':
        $saved = imagepng($rotated, $filePath, 8);
        break;
    case 'gif':
        $saved = imagegif($rotated, $filePath);
        break;
    case 'webp':
        $saved = imagewebp($rotated, $filePath, 85);
        break;
}

// Also rotate thumbnail if it exists
$thumbPath = $basePath . $media['thumbnail_path'];
if (file_exists($thumbPath) && $thumbPath !== $filePath) {
    $thumbExt = strtolower(pathinfo($thumbPath, PATHINFO_EXTENSION));
    $thumbImage = null;

    switch ($thumbExt) {
        case 'jpg':
        case 'jpeg':
            $thumbImage = @imagecreatefromjpeg($thumbPath);
            break;
        case 'png':
            $thumbImage = @imagecreatefrompng($thumbPath);
            break;
        case 'webp':
            $thumbImage = @imagecreatefromwebp($thumbPath);
            break;
    }

    if ($thumbImage) {
        $rotatedThumb = imagerotate($thumbImage, $angle, 0);
        if ($rotatedThumb) {
            if ($thumbExt === 'png') {
                imagealphablending($rotatedThumb, false);
                imagesavealpha($rotatedThumb, true);
            }

            switch ($thumbExt) {
                case 'jpg':
                case 'jpeg':
                    imagejpeg($rotatedThumb, $thumbPath, 85);
                    break;
                case 'png':
                    imagepng($rotatedThumb, $thumbPath, 8);
                    break;
                case 'webp':
                    imagewebp($rotatedThumb, $thumbPath, 82);
                    break;
            }
            imagedestroy($rotatedThumb);
        }
        imagedestroy($thumbImage);
    }
}

// Clean up
imagedestroy($image);
imagedestroy($rotated);

if (!$saved) {
    json_response(['error' => 'Failed to save rotated image'], 500);
}

// Add cache buster to force browser refresh
$cacheBuster = time();

json_response([
    'success' => true,
    'message' => 'Image rotated successfully',
    'cache_buster' => $cacheBuster
]);
