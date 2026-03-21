<?php
/**
 * AI-Powered Image Name Generator
 *
 * Uses Claude's vision API to analyze images and generate SEO-friendly filenames.
 */

class ImageNameGenerator {
    private $apiKey;
    private $model = 'claude-sonnet-4-20250514';
    private $brandSuffix = 'alive-church';

    public function __construct($apiKey = null) {
        $this->apiKey = $apiKey ?: getenv('ANTHROPIC_API_KEY') ?: (defined('ANTHROPIC_API_KEY') ? ANTHROPIC_API_KEY : null);
    }

    /**
     * Check if API is configured
     */
    public function isConfigured(): bool {
        return !empty($this->apiKey);
    }

    /**
     * Track the last error for debugging
     */
    public $lastError = null;

    /**
     * Generate an SEO-friendly filename for an image
     *
     * @param string $imagePath Path to the image file
     * @param string $originalFilename Original filename for fallback
     * @return string|null SEO-friendly filename (without extension), or null if can't generate
     */
    public function generateName(string $imagePath, string $originalFilename = ''): ?string {
        $this->lastError = null;

        if (!$this->isConfigured()) {
            $this->lastError = 'API not configured';
            $fallback = $this->sanitizeFilename($originalFilename);
            return $fallback ?: null;
        }

        if (!file_exists($imagePath)) {
            $this->lastError = 'File not found';
            return null;
        }

        // Always try AI first
        try {
            $description = $this->analyzeImage($imagePath);
            if ($description) {
                return $this->createSeoFilename($description);
            }
            $this->lastError = 'AI returned empty description';
        } catch (Exception $e) {
            $this->lastError = 'AI error: ' . $e->getMessage();
            error_log('ImageNameGenerator error: ' . $e->getMessage());
        }

        // Fallback to sanitized filename only if it's meaningful
        $fallback = $this->sanitizeFilename($originalFilename);
        if ($fallback && strlen($fallback) >= 5) {
            return $fallback . '-' . $this->brandSuffix;
        }

        // Can't generate a good name
        $this->lastError = $this->lastError ?: 'No meaningful name available';
        return null;
    }

    /**
     * Analyze image using Claude Vision API
     */
    private function analyzeImage(string $imagePath): ?string {
        $imageData = file_get_contents($imagePath);
        if (!$imageData) {
            return null;
        }

        $base64Image = base64_encode($imageData);
        $mimeType = mime_content_type($imagePath);

        // Ensure valid image mime type
        $validTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
        if (!in_array($mimeType, $validTypes)) {
            return null;
        }

        $prompt = "Analyze this image and provide a brief, descriptive filename for SEO purposes. " .
                  "The filename should be 3-6 words that accurately describe the main subject/action in the image. " .
                  "Context: This is for a church website (Alive Church). " .
                  "Examples of good filenames: 'worship-team-singing-sunday-service', 'youth-group-outdoor-activity', 'pastor-preaching-sermon', 'church-building-exterior-view'. " .
                  "Respond with ONLY the filename, no explanation. Use lowercase words separated by hyphens. No file extension.";

        $payload = [
            'model' => $this->model,
            'max_tokens' => 50,
            'messages' => [
                [
                    'role' => 'user',
                    'content' => [
                        [
                            'type' => 'image',
                            'source' => [
                                'type' => 'base64',
                                'media_type' => $mimeType,
                                'data' => $base64Image
                            ]
                        ],
                        [
                            'type' => 'text',
                            'text' => $prompt
                        ]
                    ]
                ]
            ]
        ];

        $ch = curl_init('https://api.anthropic.com/v1/messages');
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'x-api-key: ' . $this->apiKey,
                'anthropic-version: 2023-06-01'
            ],
            CURLOPT_POSTFIELDS => json_encode($payload),
            CURLOPT_TIMEOUT => 30
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode !== 200) {
            error_log('Claude API error: HTTP ' . $httpCode . ' - ' . $response);
            return null;
        }

        $data = json_decode($response, true);
        if (isset($data['content'][0]['text'])) {
            return trim($data['content'][0]['text']);
        }

        return null;
    }

    /**
     * Create SEO-friendly filename from description
     */
    private function createSeoFilename(string $description): string {
        // Clean up the AI response
        $filename = strtolower($description);

        // Remove any file extension if AI included one
        $filename = preg_replace('/\.(jpg|jpeg|png|gif|webp)$/i', '', $filename);

        // Replace spaces and underscores with hyphens
        $filename = preg_replace('/[\s_]+/', '-', $filename);

        // Remove any non-alphanumeric characters except hyphens
        $filename = preg_replace('/[^a-z0-9-]/', '', $filename);

        // Remove multiple consecutive hyphens
        $filename = preg_replace('/-+/', '-', $filename);

        // Trim hyphens from start and end
        $filename = trim($filename, '-');

        // Limit length
        if (strlen($filename) > 60) {
            $filename = substr($filename, 0, 60);
            $filename = preg_replace('/-[^-]*$/', '', $filename); // Remove partial word
        }

        // Add brand suffix if not too long
        if (strlen($filename) < 50) {
            $filename .= '-' . $this->brandSuffix;
        }

        return $filename ?: 'image-' . $this->brandSuffix;
    }

    /**
     * Sanitize original filename as fallback
     */
    public function sanitizeFilename(string $filename): string {
        // Remove extension
        $filename = pathinfo($filename, PATHINFO_FILENAME);

        // Convert to lowercase
        $filename = strtolower($filename);

        // Replace spaces and underscores with hyphens
        $filename = preg_replace('/[\s_]+/', '-', $filename);

        // Remove non-alphanumeric except hyphens
        $filename = preg_replace('/[^a-z0-9-]/', '', $filename);

        // Remove multiple hyphens
        $filename = preg_replace('/-+/', '-', $filename);

        // Trim hyphens
        $filename = trim($filename, '-');

        // Remove common non-descriptive prefixes/patterns
        $patterns = [
            '/^(img|image|photo|pic|dsc|dcim|screenshot|screen-shot)-?/',  // Camera prefixes
            '/^[0-9]{8}-wa[0-9]+$/',  // WhatsApp: 20250727-wa0015
            '/^wa[0-9]+/',  // WhatsApp short
            '/^[0-9]{10,}/',  // Long number strings (timestamps)
            '/^temp-?[0-9]*/',  // Temp files
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $filename)) {
                // This is a non-descriptive filename, return null to force AI or skip
                return '';
            }
        }

        // If filename is just numbers or too short, return empty to force AI
        if (preg_match('/^[0-9-]+$/', $filename) || strlen($filename) < 3) {
            return '';
        }

        return $filename;
    }

    /**
     * Generate unique filename to avoid conflicts
     */
    public function ensureUnique(string $filename, string $extension, string $directory): string {
        $fullPath = $directory . '/' . $filename . '.' . $extension;

        if (!file_exists($fullPath)) {
            return $filename;
        }

        $counter = 1;
        while (file_exists($directory . '/' . $filename . '-' . $counter . '.' . $extension)) {
            $counter++;
        }

        return $filename . '-' . $counter;
    }
}
