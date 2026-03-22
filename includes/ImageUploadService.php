<?php
/**
 * ImageUploadService - Unified image upload handling
 *
 * Consolidates duplicate upload logic from:
 * - admin/api/media.php
 * - api/cms/upload.php
 *
 * Handles validation, filename generation, storage, and processing.
 */

require_once __DIR__ . '/ImageProcessor.php';

class ImageUploadService {
    private string $uploadDir;
    private string $webPath;
    private int $maxFileSize;
    private array $allowedTypes;
    private ?PDO $pdo;
    private ?ImageProcessor $processor;

    public function __construct(?PDO $pdo = null) {
        $this->pdo = $pdo;
        $this->uploadDir = dirname(__DIR__) . '/storage/uploads/';
        $this->webPath = '/storage/uploads/';
        $this->maxFileSize = 10 * 1024 * 1024; // 10MB

        $this->allowedTypes = [
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/gif' => 'gif',
            'image/webp' => 'webp',
            'image/svg+xml' => 'svg',
            'application/pdf' => 'pdf'
        ];

        $this->processor = new ImageProcessor($this->uploadDir);
    }

    /**
     * Set custom upload directory
     */
    public function setUploadDir(string $dir, string $webPath = null): self {
        $this->uploadDir = rtrim($dir, '/') . '/';
        if ($webPath) {
            $this->webPath = rtrim($webPath, '/') . '/';
        }
        return $this;
    }

    /**
     * Set maximum file size
     */
    public function setMaxSize(int $bytes): self {
        $this->maxFileSize = $bytes;
        return $this;
    }

    /**
     * Set allowed MIME types
     */
    public function setAllowedTypes(array $types): self {
        $this->allowedTypes = $types;
        return $this;
    }

    /**
     * Validate uploaded file
     *
     * @param array $file $_FILES array element
     * @return array ['valid' => bool, 'error' => string|null]
     */
    public function validate(array $file): array {
        // Check for upload errors
        if ($file['error'] !== UPLOAD_ERR_OK) {
            return ['valid' => false, 'error' => $this->getUploadErrorMessage($file['error'])];
        }

        // Check file size
        if ($file['size'] > $this->maxFileSize) {
            $maxMB = round($this->maxFileSize / 1024 / 1024, 1);
            return ['valid' => false, 'error' => "File exceeds maximum size ({$maxMB}MB)"];
        }

        // Check MIME type using finfo (more secure than relying on extension)
        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $detectedType = $finfo->file($file['tmp_name']);

        if (!isset($this->allowedTypes[$detectedType])) {
            return ['valid' => false, 'error' => 'File type not allowed: ' . $detectedType];
        }

        return ['valid' => true, 'error' => null, 'mime_type' => $detectedType];
    }

    /**
     * Generate unique filename
     *
     * @param string $originalName Original filename
     * @param string $mimeType Detected MIME type
     * @return string Unique filename
     */
    public function generateFilename(string $originalName, string $mimeType): string {
        // Get extension from MIME type (more reliable)
        $ext = $this->allowedTypes[$mimeType] ?? pathinfo($originalName, PATHINFO_EXTENSION);

        // Create slug from original filename
        $baseName = pathinfo($originalName, PATHINFO_FILENAME);
        $slug = $this->slugify($baseName);

        // Add date prefix for organization
        $datePrefix = date('Ymd');

        // Add unique suffix to prevent collisions
        $unique = substr(md5(uniqid(mt_rand(), true)), 0, 6);

        return "{$datePrefix}-{$slug}-{$unique}.{$ext}";
    }

    /**
     * Upload and process a single file
     *
     * @param array $file $_FILES array element
     * @param array $options Upload options
     * @return array Result with success status and file info
     */
    public function upload(array $file, array $options = []): array {
        // Validate
        $validation = $this->validate($file);
        if (!$validation['valid']) {
            return ['success' => false, 'error' => $validation['error']];
        }

        // Ensure upload directory exists
        $subDir = $options['subdir'] ?? '';
        $targetDir = $this->uploadDir . ltrim($subDir, '/');
        if (!is_dir($targetDir)) {
            if (!mkdir($targetDir, 0755, true)) {
                return ['success' => false, 'error' => 'Failed to create upload directory'];
            }
        }

        // Generate filename
        $filename = $options['filename'] ?? $this->generateFilename($file['name'], $validation['mime_type']);
        $filepath = $targetDir . '/' . $filename;

        // Move uploaded file
        if (!move_uploaded_file($file['tmp_name'], $filepath)) {
            return ['success' => false, 'error' => 'Failed to move uploaded file'];
        }

        // Set proper permissions
        chmod($filepath, 0644);

        // Process image (resize, optimize, create variants)
        $processResult = null;
        $isImage = strpos($validation['mime_type'], 'image/') === 0 && $validation['mime_type'] !== 'image/svg+xml';

        if ($isImage && ($options['process'] ?? true)) {
            $imageType = $options['image_type'] ?? 'general';
            $processResult = $this->processor->process($filepath, $imageType);
        }

        // Calculate relative web path
        $webFilePath = $this->webPath . ($subDir ? ltrim($subDir, '/') . '/' : '') . $filename;

        $result = [
            'success' => true,
            'filename' => $filename,
            'filepath' => $filepath,
            'web_path' => $webFilePath,
            'mime_type' => $validation['mime_type'],
            'size' => $file['size'],
            'original_name' => $file['name']
        ];

        if ($processResult) {
            $result['variants'] = $processResult;
        }

        // Save to database if PDO available and requested
        if ($this->pdo && ($options['save_to_db'] ?? false)) {
            $result['media_id'] = $this->saveToDatabase($result, $options);
        }

        return $result;
    }

    /**
     * Upload multiple files
     *
     * @param array $files $_FILES array with multiple files
     * @param array $options Upload options
     * @return array Results for each file
     */
    public function uploadMultiple(array $files, array $options = []): array {
        $results = [];
        $successful = 0;
        $failed = 0;

        // Handle both indexed array and $_FILES multi-file format
        if (isset($files['name']) && is_array($files['name'])) {
            // $_FILES multi-file format
            $count = count($files['name']);
            for ($i = 0; $i < $count; $i++) {
                $file = [
                    'name' => $files['name'][$i],
                    'type' => $files['type'][$i],
                    'tmp_name' => $files['tmp_name'][$i],
                    'error' => $files['error'][$i],
                    'size' => $files['size'][$i]
                ];
                $result = $this->upload($file, $options);
                $results[] = $result;
                $result['success'] ? $successful++ : $failed++;
            }
        } else {
            // Indexed array of file arrays
            foreach ($files as $file) {
                $result = $this->upload($file, $options);
                $results[] = $result;
                $result['success'] ? $successful++ : $failed++;
            }
        }

        return [
            'success' => $failed === 0,
            'uploaded' => $successful,
            'failed' => $failed,
            'files' => $results
        ];
    }

    /**
     * Save upload info to media library database
     */
    private function saveToDatabase(array $fileInfo, array $options): ?int {
        if (!$this->pdo) {
            return null;
        }

        try {
            $stmt = $this->pdo->prepare(
                "INSERT INTO media_library (filename, original_name, file_path, mime_type, file_size, uploaded_by, created_at)
                 VALUES (?, ?, ?, ?, ?, ?, NOW())"
            );
            $stmt->execute([
                $fileInfo['filename'],
                $fileInfo['original_name'],
                $fileInfo['web_path'],
                $fileInfo['mime_type'],
                $fileInfo['size'],
                $options['user_id'] ?? null
            ]);
            return (int)$this->pdo->lastInsertId();
        } catch (Exception $e) {
            error_log('ImageUploadService: Failed to save to database: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Delete an uploaded file and its variants
     */
    public function delete(string $filepath): bool {
        $fullPath = strpos($filepath, $this->uploadDir) === 0 ? $filepath : $this->uploadDir . ltrim($filepath, '/');

        if (!file_exists($fullPath)) {
            return false;
        }

        // Delete main file
        unlink($fullPath);

        // Delete variants (thumbnail, medium, etc.)
        $pathInfo = pathinfo($fullPath);
        $pattern = $pathInfo['dirname'] . '/' . $pathInfo['filename'] . '-*.' . $pathInfo['extension'];
        foreach (glob($pattern) as $variant) {
            unlink($variant);
        }

        // Delete WebP variants
        $webpPattern = $pathInfo['dirname'] . '/' . $pathInfo['filename'] . '*.webp';
        foreach (glob($webpPattern) as $webp) {
            unlink($webp);
        }

        return true;
    }

    /**
     * Create a URL-safe slug from filename
     */
    private function slugify(string $text): string {
        // Convert to lowercase
        $text = strtolower($text);

        // Replace non-alphanumeric characters with dashes
        $text = preg_replace('/[^a-z0-9]+/', '-', $text);

        // Remove leading/trailing dashes
        $text = trim($text, '-');

        // Limit length
        $text = substr($text, 0, 50);

        return $text ?: 'file';
    }

    /**
     * Get human-readable upload error message
     */
    private function getUploadErrorMessage(int $errorCode): string {
        $messages = [
            UPLOAD_ERR_INI_SIZE => 'File exceeds server size limit',
            UPLOAD_ERR_FORM_SIZE => 'File exceeds form size limit',
            UPLOAD_ERR_PARTIAL => 'File was only partially uploaded',
            UPLOAD_ERR_NO_FILE => 'No file was uploaded',
            UPLOAD_ERR_NO_TMP_DIR => 'Server missing temporary folder',
            UPLOAD_ERR_CANT_WRITE => 'Failed to write file to disk',
            UPLOAD_ERR_EXTENSION => 'Upload blocked by server extension'
        ];

        return $messages[$errorCode] ?? 'Unknown upload error';
    }

    /**
     * Get allowed MIME types
     */
    public function getAllowedTypes(): array {
        return $this->allowedTypes;
    }

    /**
     * Get max file size in bytes
     */
    public function getMaxSize(): int {
        return $this->maxFileSize;
    }

    /**
     * Get max file size formatted for display
     */
    public function getMaxSizeFormatted(): string {
        $bytes = $this->maxFileSize;
        if ($bytes >= 1048576) {
            return round($bytes / 1048576, 1) . 'MB';
        }
        return round($bytes / 1024, 1) . 'KB';
    }
}
