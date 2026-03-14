<?php
/**
 * ImageProcessor - Automatic image optimization, resizing, and WebP conversion
 *
 * Features:
 * - Converts PNG/JPEG/GIF to WebP for smaller file sizes
 * - Generates multiple size variants (thumbnail, medium, large)
 * - Preserves original files
 * - Supports background processing via queue
 * - Maintains aspect ratio during resize
 *
 * Usage:
 *   $processor = new ImageProcessor();
 *   $result = $processor->process('/path/to/uploaded/image.png');
 *   // Returns array with paths to all generated variants
 */

class ImageProcessor
{
    // Size presets for responsive images
    private const SIZES = [
        'thumbnail' => ['width' => 150, 'height' => 150, 'quality' => 80],
        'small'     => ['width' => 320, 'height' => null, 'quality' => 82],
        'medium'    => ['width' => 800, 'height' => null, 'quality' => 85],
        'large'     => ['width' => 1200, 'height' => null, 'quality' => 85],
        'xlarge'    => ['width' => 1920, 'height' => null, 'quality' => 88],
    ];

    // Avatar-specific sizes
    private const AVATAR_SIZES = [
        'small'  => ['width' => 48, 'height' => 48, 'quality' => 85],
        'medium' => ['width' => 96, 'height' => 96, 'quality' => 85],
        'large'  => ['width' => 256, 'height' => 256, 'quality' => 88],
    ];

    // Hero image sizes (wider aspect ratio)
    private const HERO_SIZES = [
        'mobile'  => ['width' => 640, 'height' => null, 'quality' => 82],
        'tablet'  => ['width' => 1024, 'height' => null, 'quality' => 85],
        'desktop' => ['width' => 1920, 'height' => null, 'quality' => 88],
    ];

    private string $basePath;
    private bool $generateWebp = true;
    private bool $keepOriginal = true;
    private int $maxOriginalWidth = 2560;
    private int $maxOriginalHeight = 2560;
    private int $jpegQuality = 85;
    private int $webpQuality = 82;
    private int $pngCompression = 8;

    public function __construct(string $basePath = null)
    {
        $this->basePath = $basePath ?? dirname(__DIR__) . '/storage/uploads';
    }

    /**
     * Process an uploaded image - creates optimized variants
     *
     * @param string $sourcePath Full path to the uploaded image
     * @param string $type Type of image: 'general', 'avatar', 'hero'
     * @param bool $async If true, queue for background processing instead
     * @return array Result with paths to all generated variants
     */
    public function process(string $sourcePath, string $type = 'general', bool $async = false): array
    {
        if (!file_exists($sourcePath)) {
            return ['success' => false, 'error' => 'Source file not found'];
        }

        // Get image info
        $imageInfo = @getimagesize($sourcePath);
        if (!$imageInfo) {
            return ['success' => false, 'error' => 'Invalid image file'];
        }

        $mimeType = $imageInfo['mime'];
        $originalWidth = $imageInfo[0];
        $originalHeight = $imageInfo[1];

        // Skip processing for SVGs and very small images
        if ($mimeType === 'image/svg+xml') {
            return [
                'success' => true,
                'skipped' => true,
                'reason' => 'SVG files do not need processing',
                'original' => $sourcePath
            ];
        }

        if ($originalWidth <= 150 && $originalHeight <= 150) {
            return [
                'success' => true,
                'skipped' => true,
                'reason' => 'Image too small to optimize',
                'original' => $sourcePath
            ];
        }

        // Queue for async processing if requested
        if ($async) {
            return $this->queueForProcessing($sourcePath, $type);
        }

        // Process synchronously
        return $this->processImage($sourcePath, $type, $mimeType, $originalWidth, $originalHeight);
    }

    /**
     * Process image synchronously - generates all variants
     */
    private function processImage(
        string $sourcePath,
        string $type,
        string $mimeType,
        int $originalWidth,
        int $originalHeight
    ): array {
        $results = [
            'success' => true,
            'original' => $sourcePath,
            'original_size' => filesize($sourcePath),
            'variants' => [],
            'webp_variants' => [],
            'total_saved' => 0
        ];

        // Load source image
        $sourceImage = $this->loadImage($sourcePath, $mimeType);
        if (!$sourceImage) {
            return ['success' => false, 'error' => 'Failed to load image'];
        }

        // Get path info for generating variant filenames
        $pathInfo = pathinfo($sourcePath);
        $baseDir = $pathInfo['dirname'];
        $baseName = $pathInfo['filename'];
        $extension = strtolower($pathInfo['extension']);

        // Select size presets based on type
        $sizes = match($type) {
            'avatar' => self::AVATAR_SIZES,
            'hero' => self::HERO_SIZES,
            default => self::SIZES
        };

        // First, optimize the original if it's too large
        if ($originalWidth > $this->maxOriginalWidth || $originalHeight > $this->maxOriginalHeight) {
            $optimizedOriginal = $this->resizeImage(
                $sourceImage,
                $originalWidth,
                $originalHeight,
                $this->maxOriginalWidth,
                $this->maxOriginalHeight,
                $type === 'avatar'
            );

            // Save optimized original
            $optimizedPath = "{$baseDir}/{$baseName}-optimized.{$extension}";
            $this->saveImage($optimizedOriginal, $optimizedPath, $mimeType);
            $results['optimized_original'] = $optimizedPath;
            $results['optimized_original_size'] = filesize($optimizedPath);

            imagedestroy($optimizedOriginal);
        }

        // Generate size variants
        foreach ($sizes as $sizeName => $sizeConfig) {
            // Skip if original is smaller than this size
            if ($originalWidth <= $sizeConfig['width']) {
                continue;
            }

            $targetWidth = $sizeConfig['width'];
            $targetHeight = $sizeConfig['height'];
            $quality = $sizeConfig['quality'];

            // Calculate dimensions
            if ($type === 'avatar' && $targetHeight !== null) {
                // Square crop for avatars
                $resized = $this->cropSquare($sourceImage, $originalWidth, $originalHeight, $targetWidth);
            } else {
                // Proportional resize
                $resized = $this->resizeImage(
                    $sourceImage,
                    $originalWidth,
                    $originalHeight,
                    $targetWidth,
                    $targetHeight
                );
            }

            if ($resized) {
                // Save in original format
                $variantPath = "{$baseDir}/{$baseName}-{$sizeName}.{$extension}";
                $this->saveImage($resized, $variantPath, $mimeType, $quality);
                $results['variants'][$sizeName] = [
                    'path' => $variantPath,
                    'url' => $this->pathToUrl($variantPath),
                    'size' => filesize($variantPath),
                    'width' => imagesx($resized),
                    'height' => imagesy($resized)
                ];

                // Generate WebP variant
                if ($this->generateWebp && $mimeType !== 'image/gif') {
                    $webpPath = "{$baseDir}/{$baseName}-{$sizeName}.webp";
                    $this->saveWebp($resized, $webpPath, $this->webpQuality);
                    $results['webp_variants'][$sizeName] = [
                        'path' => $webpPath,
                        'url' => $this->pathToUrl($webpPath),
                        'size' => filesize($webpPath)
                    ];

                    // Calculate savings
                    $originalVariantSize = $results['variants'][$sizeName]['size'];
                    $webpSize = $results['webp_variants'][$sizeName]['size'];
                    $results['total_saved'] += ($originalVariantSize - $webpSize);
                }

                imagedestroy($resized);
            }
        }

        // Generate WebP of original size (if not already small)
        if ($this->generateWebp && $mimeType !== 'image/gif' && $originalWidth > 320) {
            $webpOriginalPath = "{$baseDir}/{$baseName}.webp";
            $this->saveWebp($sourceImage, $webpOriginalPath, $this->webpQuality);
            $results['webp_original'] = [
                'path' => $webpOriginalPath,
                'url' => $this->pathToUrl($webpOriginalPath),
                'size' => filesize($webpOriginalPath)
            ];
            $results['total_saved'] += ($results['original_size'] - $results['webp_original']['size']);
        }

        imagedestroy($sourceImage);

        // Format savings
        $results['savings_formatted'] = $this->formatBytes($results['total_saved']);
        $results['savings_percent'] = $results['original_size'] > 0
            ? round(($results['total_saved'] / $results['original_size']) * 100, 1)
            : 0;

        return $results;
    }

    /**
     * Load image from file based on MIME type
     */
    private function loadImage(string $path, string $mimeType): \GdImage|false
    {
        return match($mimeType) {
            'image/jpeg' => @imagecreatefromjpeg($path),
            'image/png' => @imagecreatefrompng($path),
            'image/gif' => @imagecreatefromgif($path),
            'image/webp' => @imagecreatefromwebp($path),
            default => false
        };
    }

    /**
     * Resize image maintaining aspect ratio
     */
    private function resizeImage(
        \GdImage $source,
        int $sourceWidth,
        int $sourceHeight,
        int $maxWidth,
        ?int $maxHeight = null,
        bool $crop = false
    ): \GdImage|false {
        // Calculate new dimensions
        if ($maxHeight === null) {
            // Width-based resize (maintain aspect ratio)
            $ratio = $maxWidth / $sourceWidth;
            $newWidth = $maxWidth;
            $newHeight = (int) round($sourceHeight * $ratio);
        } else {
            // Fit within bounds
            $widthRatio = $maxWidth / $sourceWidth;
            $heightRatio = $maxHeight / $sourceHeight;
            $ratio = min($widthRatio, $heightRatio);
            $newWidth = (int) round($sourceWidth * $ratio);
            $newHeight = (int) round($sourceHeight * $ratio);
        }

        // Create new image
        $resized = imagecreatetruecolor($newWidth, $newHeight);
        if (!$resized) {
            return false;
        }

        // Preserve transparency for PNG
        imagealphablending($resized, false);
        imagesavealpha($resized, true);
        $transparent = imagecolorallocatealpha($resized, 255, 255, 255, 127);
        imagefilledrectangle($resized, 0, 0, $newWidth, $newHeight, $transparent);

        // High-quality resampling
        imagecopyresampled(
            $resized, $source,
            0, 0, 0, 0,
            $newWidth, $newHeight,
            $sourceWidth, $sourceHeight
        );

        return $resized;
    }

    /**
     * Crop to square (for avatars)
     */
    private function cropSquare(\GdImage $source, int $sourceWidth, int $sourceHeight, int $size): \GdImage|false
    {
        // Determine crop area (center crop)
        $cropSize = min($sourceWidth, $sourceHeight);
        $cropX = (int) (($sourceWidth - $cropSize) / 2);
        $cropY = (int) (($sourceHeight - $cropSize) / 2);

        // Create square image
        $square = imagecreatetruecolor($size, $size);
        if (!$square) {
            return false;
        }

        // Preserve transparency
        imagealphablending($square, false);
        imagesavealpha($square, true);

        // Crop and resize
        imagecopyresampled(
            $square, $source,
            0, 0, $cropX, $cropY,
            $size, $size,
            $cropSize, $cropSize
        );

        return $square;
    }

    /**
     * Save image in original format
     */
    private function saveImage(\GdImage $image, string $path, string $mimeType, int $quality = null): bool
    {
        // Ensure directory exists
        $dir = dirname($path);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $quality = $quality ?? $this->jpegQuality;

        return match($mimeType) {
            'image/jpeg' => imagejpeg($image, $path, $quality),
            'image/png' => imagepng($image, $path, $this->pngCompression),
            'image/gif' => imagegif($image, $path),
            'image/webp' => imagewebp($image, $path, $quality),
            default => false
        };
    }

    /**
     * Save as WebP format
     */
    private function saveWebp(\GdImage $image, string $path, int $quality = 82): bool
    {
        $dir = dirname($path);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        return imagewebp($image, $path, $quality);
    }

    /**
     * Convert filesystem path to web URL
     */
    private function pathToUrl(string $path): string
    {
        // Remove base path and convert to URL
        $relativePath = str_replace(dirname(__DIR__), '', $path);
        return $relativePath;
    }

    /**
     * Queue image for background processing
     */
    private function queueForProcessing(string $sourcePath, string $type): array
    {
        try {
            require_once __DIR__ . '/db-config.php';
            $pdo = getDbConnection();

            $stmt = $pdo->prepare("
                INSERT INTO image_processing_queue (source_path, image_type, status, created_at)
                VALUES (?, ?, 'pending', NOW())
            ");
            $stmt->execute([$sourcePath, $type]);
            $queueId = $pdo->lastInsertId();

            return [
                'success' => true,
                'queued' => true,
                'queue_id' => $queueId,
                'message' => 'Image queued for background processing'
            ];
        } catch (Exception $e) {
            error_log("ImageProcessor queue error: " . $e->getMessage());
            // Fall back to sync processing if queue fails
            return $this->process($sourcePath, $type, false);
        }
    }

    /**
     * Process queued images (called by cron job)
     */
    public function processQueue(int $limit = 10): array
    {
        require_once __DIR__ . '/db-config.php';
        $pdo = getDbConnection();

        // Get pending items
        $stmt = $pdo->prepare("
            SELECT id, source_path, image_type
            FROM image_processing_queue
            WHERE status = 'pending'
            ORDER BY created_at ASC
            LIMIT ?
        ");
        $stmt->execute([$limit]);
        $items = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $results = ['processed' => 0, 'failed' => 0, 'details' => []];

        foreach ($items as $item) {
            // Mark as processing
            $pdo->prepare("UPDATE image_processing_queue SET status = 'processing', started_at = NOW() WHERE id = ?")
                ->execute([$item['id']]);

            try {
                $result = $this->process($item['source_path'], $item['image_type'], false);

                if ($result['success']) {
                    // Mark as completed
                    $pdo->prepare("
                        UPDATE image_processing_queue
                        SET status = 'completed', completed_at = NOW(), result = ?
                        WHERE id = ?
                    ")->execute([json_encode($result), $item['id']]);

                    $results['processed']++;
                    $results['details'][] = [
                        'id' => $item['id'],
                        'success' => true,
                        'saved' => $result['savings_formatted'] ?? '0 B'
                    ];
                } else {
                    throw new Exception($result['error'] ?? 'Unknown error');
                }
            } catch (Exception $e) {
                // Mark as failed
                $pdo->prepare("
                    UPDATE image_processing_queue
                    SET status = 'failed', error = ?, completed_at = NOW()
                    WHERE id = ?
                ")->execute([$e->getMessage(), $item['id']]);

                $results['failed']++;
                $results['details'][] = [
                    'id' => $item['id'],
                    'success' => false,
                    'error' => $e->getMessage()
                ];
            }
        }

        return $results;
    }

    /**
     * Get the best available image URL (WebP if supported, otherwise original)
     *
     * @param string $originalUrl Original image URL
     * @param string $size Size variant: 'thumbnail', 'small', 'medium', 'large', 'xlarge'
     * @param bool $webpSupported Whether client supports WebP
     * @return string Best available URL
     */
    public static function getResponsiveUrl(string $originalUrl, string $size = 'medium', bool $webpSupported = true): string
    {
        $pathInfo = pathinfo($originalUrl);
        $dir = $pathInfo['dirname'];
        $baseName = $pathInfo['filename'];
        $extension = $pathInfo['extension'] ?? 'jpg';

        // Try WebP first if supported
        if ($webpSupported) {
            $webpUrl = "{$dir}/{$baseName}-{$size}.webp";
            // We can't check file existence from static method, so return WebP URL
            // The server should have fallback handling
            return $webpUrl;
        }

        // Return sized variant
        return "{$dir}/{$baseName}-{$size}.{$extension}";
    }

    /**
     * Generate srcset attribute for responsive images
     */
    public static function generateSrcset(string $originalUrl, array $sizes = ['small', 'medium', 'large']): string
    {
        $pathInfo = pathinfo($originalUrl);
        $dir = $pathInfo['dirname'];
        $baseName = $pathInfo['filename'];

        $widths = [
            'thumbnail' => 150,
            'small' => 320,
            'medium' => 800,
            'large' => 1200,
            'xlarge' => 1920
        ];

        $srcset = [];
        foreach ($sizes as $size) {
            if (isset($widths[$size])) {
                $srcset[] = "{$dir}/{$baseName}-{$size}.webp {$widths[$size]}w";
            }
        }

        return implode(', ', $srcset);
    }

    /**
     * Format bytes to human-readable string
     */
    private function formatBytes(int $bytes): string
    {
        if ($bytes < 1024) return $bytes . ' B';
        if ($bytes < 1048576) return round($bytes / 1024, 1) . ' KB';
        return round($bytes / 1048576, 2) . ' MB';
    }

    /**
     * Cleanup old processed images (optional maintenance)
     */
    public function cleanupOldVariants(string $originalPath): bool
    {
        $pathInfo = pathinfo($originalPath);
        $dir = $pathInfo['dirname'];
        $baseName = $pathInfo['filename'];

        $pattern = "{$dir}/{$baseName}-*.{jpg,jpeg,png,gif,webp}";
        $files = glob($pattern, GLOB_BRACE);

        foreach ($files as $file) {
            if ($file !== $originalPath) {
                @unlink($file);
            }
        }

        return true;
    }
}
