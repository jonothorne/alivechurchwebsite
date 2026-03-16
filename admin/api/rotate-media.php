<?php
/**
 * Rotate Media API
 * POST - Rotate an image by 90 degrees (all sizes/variants)
 */

header('Content-Type: application/json');
header('Cache-Control: no-cache, no-store, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');

// Debug logging
$logFile = __DIR__ . '/../../logs/rotate-debug.log';
function debug_log($msg) {
    global $logFile;
    $dir = dirname($logFile);
    if (!is_dir($dir)) mkdir($dir, 0755, true);
    file_put_contents($logFile, date('Y-m-d H:i:s') . " - $msg\n", FILE_APPEND);
}

debug_log("Rotate API called");

function json_response($data, $code = 200) {
    debug_log("Response: " . json_encode($data) . " (code: $code)");
    http_response_code($code);
    echo json_encode($data);
    exit;
}

/**
 * Rotate a single image file
 */
function rotateImageFile($filePath, $angle) {
    try {
        if (!file_exists($filePath) || !is_file($filePath)) {
            return false;
        }

        // Skip empty files
        $size = @filesize($filePath);
        if ($size === false || $size === 0) {
            debug_log("Skipping empty/invalid file: $filePath");
            return false;
        }

        $extension = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));
        $image = null;

        // Suppress errors and catch any issues
        set_error_handler(function($errno, $errstr) {
            throw new Exception($errstr);
        });

        try {
            switch ($extension) {
                case 'jpg':
                case 'jpeg':
                    $image = imagecreatefromjpeg($filePath);
                    break;
                case 'png':
                    $image = imagecreatefrompng($filePath);
                    break;
                case 'gif':
                    $image = imagecreatefromgif($filePath);
                    break;
                case 'webp':
                    $image = imagecreatefromwebp($filePath);
                    break;
                default:
                    restore_error_handler();
                    return false;
            }
        } catch (Exception $e) {
            restore_error_handler();
            debug_log("Error loading image $filePath: " . $e->getMessage());
            return false;
        }

        restore_error_handler();

        if (!$image) {
            debug_log("Failed to load image: $filePath");
            return false;
        }

    $rotated = imagerotate($image, $angle, 0);
    if (!$rotated) {
        imagedestroy($image);
        return false;
    }

    // Preserve transparency for PNG
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

        imagedestroy($image);
        imagedestroy($rotated);

        return $saved;
    } catch (Exception $e) {
        debug_log("Exception in rotateImageFile: " . $e->getMessage());
        return false;
    }
}

require_once __DIR__ . '/../../includes/db-config.php';
require_once __DIR__ . '/../../includes/Auth.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check authentication
$pdo = getDbConnection();
$auth = new Auth($pdo);
$isAuthorized = false;

if ($auth->check() && $auth->isEditor()) {
    $isAuthorized = true;
} elseif (isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true) {
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
$direction = $input['direction'] ?? 'right';

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

$basePath = __DIR__ . '/../../';
$filePath = $basePath . $media['file_path'];

if (!file_exists($filePath)) {
    json_response(['error' => 'File not found on disk'], 404);
}

$angle = $direction === 'left' ? 90 : -90;

// Get the base filename without extension
$pathInfo = pathinfo($filePath);
$baseDir = $pathInfo['dirname'];
$filename = $pathInfo['filename'];
$extension = $pathInfo['extension'];

debug_log("Rotating media ID $mediaId: $filename.$extension");

// Find all related files to rotate
$filesToRotate = [];

// 1. Main file (from database)
$filesToRotate[] = $filePath;

// 2. Alternative format versions (if main is PNG, also look for JPG and vice versa)
$formats = ['png', 'jpg', 'jpeg', 'webp', 'gif'];
foreach ($formats as $fmt) {
    $altPath = "$baseDir/$filename.$fmt";
    if ($altPath !== $filePath && file_exists($altPath)) {
        $filesToRotate[] = $altPath;
    }
}

// 3. WebP companion files (image.png.webp style)
$webpCompanion = "$filePath.webp";
if (file_exists($webpCompanion)) {
    $filesToRotate[] = $webpCompanion;
}

// 4. Size variants - check for common patterns like image-large.jpg, image-thumbnail.jpg
$sizeVariants = ['large', 'medium', 'small', 'thumbnail', 'thumb'];

foreach ($sizeVariants as $size) {
    foreach ($formats as $fmt) {
        // Pattern: image-size.ext
        $variantPath = "$baseDir/{$filename}-{$size}.$fmt";
        if (file_exists($variantPath)) {
            $filesToRotate[] = $variantPath;
        }
        // Pattern: image_size.ext
        $variantPath2 = "$baseDir/{$filename}_{$size}.$fmt";
        if (file_exists($variantPath2)) {
            $filesToRotate[] = $variantPath2;
        }
    }
}

// Also check thumbs subdirectory
foreach ($formats as $fmt) {
    $thumbsPath = "$baseDir/thumbs/$filename.$fmt";
    if (file_exists($thumbsPath)) {
        $filesToRotate[] = $thumbsPath;
    }
}

// 5. Original file in originals directory
$originalsDir = $basePath . 'uploads/originals';
foreach ($formats as $fmt) {
    $originalPath = "$originalsDir/$filename.$fmt";
    if (file_exists($originalPath)) {
        $filesToRotate[] = $originalPath;
    }
}

// Remove duplicates
$filesToRotate = array_unique($filesToRotate);

debug_log("Files to rotate: " . implode(', ', $filesToRotate));

// Rotate all files
$rotatedCount = 0;
$errors = [];

foreach ($filesToRotate as $file) {
    if (rotateImageFile($file, $angle)) {
        $rotatedCount++;
        debug_log("Rotated: $file");
    } else {
        $errors[] = basename($file);
        debug_log("Failed to rotate: $file");
    }
}

if ($rotatedCount === 0) {
    json_response(['error' => 'Failed to rotate any images'], 500);
}

$cacheBuster = time();

$response = [
    'success' => true,
    'message' => "Rotated $rotatedCount file(s)",
    'rotated_count' => $rotatedCount,
    'cache_buster' => $cacheBuster
];

if (!empty($errors)) {
    $response['warnings'] = 'Some files failed: ' . implode(', ', $errors);
}

json_response($response);
