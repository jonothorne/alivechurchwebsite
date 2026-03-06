<?php
/**
 * CMS API - Upload Media
 *
 * Handles file uploads for the media library.
 */

header('Content-Type: application/json');

// Start session and check auth
session_start();
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/db-config.php';

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

// Configuration
$uploadDir = __DIR__ . '/../../storage/uploads/';
$webPath = '/storage/uploads/';
$maxFileSize = 10 * 1024 * 1024; // 10MB
$allowedTypes = [
    'image/jpeg' => 'jpg',
    'image/png' => 'png',
    'image/gif' => 'gif',
    'image/webp' => 'webp',
    'image/svg+xml' => 'svg',
    'application/pdf' => 'pdf'
];

// Ensure upload directory exists
if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0755, true);
}

// Check if files were uploaded
if (empty($_FILES['files'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'No files uploaded']);
    exit;
}

$files = $_FILES['files'];
$uploaded = [];
$errors = [];

try {
    $pdo = getDbConnection();
    $userId = $_SESSION['admin_user_id'];

    // Process each file
    $fileCount = is_array($files['name']) ? count($files['name']) : 1;

    for ($i = 0; $i < $fileCount; $i++) {
        $name = is_array($files['name']) ? $files['name'][$i] : $files['name'];
        $tmpName = is_array($files['tmp_name']) ? $files['tmp_name'][$i] : $files['tmp_name'];
        $size = is_array($files['size']) ? $files['size'][$i] : $files['size'];
        $error = is_array($files['error']) ? $files['error'][$i] : $files['error'];
        $type = is_array($files['type']) ? $files['type'][$i] : $files['type'];

        // Check for upload errors
        if ($error !== UPLOAD_ERR_OK) {
            $errors[] = "Upload error for {$name}: " . getUploadError($error);
            continue;
        }

        // Validate file size
        if ($size > $maxFileSize) {
            $errors[] = "{$name} exceeds maximum file size (10MB)";
            continue;
        }

        // Validate file type
        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $detectedType = $finfo->file($tmpName);

        if (!isset($allowedTypes[$detectedType])) {
            $errors[] = "{$name} has an unsupported file type";
            continue;
        }

        // Generate unique filename
        $ext = $allowedTypes[$detectedType];
        $filename = generateUniqueFilename($name, $ext);
        $filepath = $uploadDir . $filename;
        $webUrl = $webPath . $filename;

        // Move uploaded file
        if (!move_uploaded_file($tmpName, $filepath)) {
            $errors[] = "Failed to save {$name}";
            continue;
        }

        // Get image dimensions if applicable
        $width = null;
        $height = null;
        if (strpos($detectedType, 'image/') === 0 && $detectedType !== 'image/svg+xml') {
            $imageInfo = getimagesize($filepath);
            if ($imageInfo) {
                $width = $imageInfo[0];
                $height = $imageInfo[1];
            }
        }

        // Save to database
        $stmt = $pdo->prepare("
            INSERT INTO media (filename, original_filename, file_path, file_url, file_type, file_size, width, height, uploaded_by)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $filename,
            $name,
            $filepath,
            $webUrl,
            $detectedType,
            $size,
            $width,
            $height,
            $userId
        ]);

        $uploaded[] = [
            'id' => $pdo->lastInsertId(),
            'filename' => $filename,
            'original_filename' => $name,
            'file_url' => $webUrl,
            'file_type' => $detectedType,
            'file_size' => $size,
            'width' => $width,
            'height' => $height
        ];

        // Log activity
        log_activity($userId, 'upload_media', 'media', $pdo->lastInsertId(), "Uploaded file: {$name}");
    }

    echo json_encode([
        'success' => count($uploaded) > 0,
        'uploaded' => $uploaded,
        'errors' => $errors
    ]);

} catch (Exception $e) {
    error_log('CMS upload error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Server error']);
}

/**
 * Generate unique filename
 */
function generateUniqueFilename($originalName, $ext) {
    $baseName = pathinfo($originalName, PATHINFO_FILENAME);
    $baseName = preg_replace('/[^a-zA-Z0-9_-]/', '-', $baseName);
    $baseName = strtolower(substr($baseName, 0, 50));

    $timestamp = date('Y/m');
    $unique = substr(md5(uniqid()), 0, 8);

    return "{$timestamp}/{$baseName}-{$unique}.{$ext}";
}

/**
 * Get human-readable upload error
 */
function getUploadError($code) {
    $errors = [
        UPLOAD_ERR_INI_SIZE => 'File exceeds server limit',
        UPLOAD_ERR_FORM_SIZE => 'File exceeds form limit',
        UPLOAD_ERR_PARTIAL => 'File only partially uploaded',
        UPLOAD_ERR_NO_FILE => 'No file uploaded',
        UPLOAD_ERR_NO_TMP_DIR => 'Missing temp folder',
        UPLOAD_ERR_CANT_WRITE => 'Failed to write file',
        UPLOAD_ERR_EXTENSION => 'Upload blocked by extension'
    ];
    return $errors[$code] ?? 'Unknown error';
}
