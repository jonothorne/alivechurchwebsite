<?php
/**
 * GrapesJS Media/Asset Manager API Endpoint
 * Handles media uploads and listing for GrapesJS
 */

require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/db-config.php';

// Require authentication
require_auth();

// Set JSON header
header('Content-Type: application/json');

// Media upload directory
$uploadDir = __DIR__ . '/../../assets/uploads/';
$uploadUrl = '/assets/uploads/';

// Create upload directory if it doesn't exist
if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0755, true);
}

// Handle GET request - List existing media
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $assets = [];
    $files = glob($uploadDir . '*');

    foreach ($files as $file) {
        if (is_file($file)) {
            $filename = basename($file);
            $assets[] = [
                'src' => $uploadUrl . $filename,
                'name' => $filename,
                'type' => mime_content_type($file),
                'size' => filesize($file)
            ];
        }
    }

    echo json_encode([
        'success' => true,
        'data' => $assets
    ]);
    exit;
}

// Handle POST request - Upload media
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Verify CSRF token
    $csrf_token = $_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    if (!verify_csrf_token($csrf_token)) {
        http_response_code(403);
        die(json_encode(['error' => 'Invalid CSRF token']));
    }

    // Check if files were uploaded
    if (empty($_FILES['files']) && empty($_FILES['file'])) {
        http_response_code(400);
        die(json_encode(['error' => 'No files uploaded']));
    }

    // Get files (support both 'files[]' and 'file')
    $files = $_FILES['files'] ?? [$_FILES['file']];

    // Handle multiple files
    $uploadedAssets = [];
    $errors = [];

    // If single file, normalize structure
    if (isset($files['name']) && !is_array($files['name'])) {
        $files = [
            'name' => [$files['name']],
            'type' => [$files['type']],
            'tmp_name' => [$files['tmp_name']],
            'error' => [$files['error']],
            'size' => [$files['size']]
        ];
    }

    // Process each file
    $fileCount = count($files['name']);
    for ($i = 0; $i < $fileCount; $i++) {
        $fileName = $files['name'][$i];
        $fileTmpName = $files['tmp_name'][$i];
        $fileError = $files['error'][$i];
        $fileSize = $files['size'][$i];
        $fileType = $files['type'][$i];

        // Check for upload errors
        if ($fileError !== UPLOAD_ERR_OK) {
            $errors[] = "Upload error for {$fileName}: code {$fileError}";
            continue;
        }

        // Validate file type
        $allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp', 'image/svg+xml'];
        if (!in_array($fileType, $allowedTypes)) {
            $errors[] = "Invalid file type for {$fileName}: {$fileType}";
            continue;
        }

        // Validate file size (5MB max)
        $maxSize = 5 * 1024 * 1024;
        if ($fileSize > $maxSize) {
            $errors[] = "File too large: {$fileName}";
            continue;
        }

        // Generate unique filename
        $extension = pathinfo($fileName, PATHINFO_EXTENSION);
        $baseName = pathinfo($fileName, PATHINFO_FILENAME);
        $baseName = preg_replace('/[^a-z0-9-_]/i', '-', $baseName);
        $uniqueName = $baseName . '-' . time() . '-' . uniqid() . '.' . $extension;

        // Move uploaded file
        $destination = $uploadDir . $uniqueName;
        if (move_uploaded_file($fileTmpName, $destination)) {
            // Track in database if page_id provided
            $page_id = $_POST['page_id'] ?? null;
            if ($page_id && is_numeric($page_id)) {
                try {
                    $pdo = getDbConnection();
                    $stmt = $pdo->prepare("
                        INSERT INTO grapes_assets (page_id, asset_type, asset_url, asset_name)
                        VALUES (?, ?, ?, ?)
                    ");

                    $assetType = 'image';
                    if (strpos($fileType, 'video') !== false) {
                        $assetType = 'video';
                    }

                    $stmt->execute([
                        $page_id,
                        $assetType,
                        $uploadUrl . $uniqueName,
                        $fileName
                    ]);
                } catch (PDOException $e) {
                    error_log('Failed to track asset: ' . $e->getMessage());
                }
            }

            // Add to uploaded assets
            $uploadedAssets[] = [
                'src' => $uploadUrl . $uniqueName,
                'name' => $uniqueName,
                'type' => $fileType,
                'size' => $fileSize
            ];

            // Log activity
            log_activity(
                $_SESSION['admin_user_id'],
                'upload',
                'media',
                null,
                "Uploaded media file: {$fileName}"
            );
        } else {
            $errors[] = "Failed to move uploaded file: {$fileName}";
        }
    }

    // Return response
    if (empty($uploadedAssets) && !empty($errors)) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'errors' => $errors
        ]);
    } else {
        echo json_encode([
            'success' => true,
            'data' => $uploadedAssets,
            'errors' => $errors
        ]);
    }

    exit;
}

// Unsupported method
http_response_code(405);
die(json_encode(['error' => 'Method not allowed']));
