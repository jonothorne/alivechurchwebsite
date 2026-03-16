<?php
/**
 * YouTube Livestream Helper
 * Automatically detects current livestream from a YouTube channel
 */

class YouTubeLivestream {
    private string $channelId;
    private string $apiKey;
    private string $cacheFile;
    private int $cacheDuration = 120; // Cache for 2 minutes

    public function __construct(string $channelId, string $apiKey) {
        $this->channelId = $channelId;
        $this->apiKey = $apiKey;
        $this->cacheFile = sys_get_temp_dir() . '/yt_livestream_' . md5($channelId) . '.json';
    }

    /**
     * Get the current livestream embed URL
     * Returns the live video embed URL if streaming, or channel-based fallback
     */
    public function getEmbedUrl(): string {
        $videoId = $this->getLiveVideoId();

        if ($videoId) {
            return "https://www.youtube.com/embed/{$videoId}?autoplay=1";
        }

        // Fallback to channel-based live embed
        return "https://www.youtube.com/embed/live_stream?channel={$this->channelId}";
    }

    /**
     * Check if the channel is currently live
     */
    public function isLive(): bool {
        return $this->getLiveVideoId() !== null;
    }

    /**
     * Get the current live video ID (cached)
     */
    public function getLiveVideoId(): ?string {
        // Check cache first
        $cached = $this->getFromCache();
        if ($cached !== null) {
            return $cached['video_id'];
        }

        // Fetch from API
        $videoId = $this->fetchLiveVideoId();

        // Cache the result (even if null)
        $this->saveToCache($videoId);

        return $videoId;
    }

    /**
     * Fetch live video ID from YouTube Data API
     */
    private function fetchLiveVideoId(): ?string {
        if (empty($this->apiKey)) {
            error_log('YouTubeLivestream: No API key configured');
            return null;
        }

        $url = 'https://www.googleapis.com/youtube/v3/search?' . http_build_query([
            'part' => 'id',
            'channelId' => $this->channelId,
            'eventType' => 'live',
            'type' => 'video',
            'key' => $this->apiKey
        ]);

        $context = stream_context_create([
            'http' => [
                'timeout' => 5,
                'ignore_errors' => true
            ]
        ]);

        $response = @file_get_contents($url, false, $context);

        if ($response === false) {
            error_log('YouTubeLivestream: API request failed');
            return null;
        }

        $data = json_decode($response, true);

        if (isset($data['error'])) {
            error_log('YouTubeLivestream: API error - ' . ($data['error']['message'] ?? 'Unknown error'));
            return null;
        }

        if (!empty($data['items'][0]['id']['videoId'])) {
            return $data['items'][0]['id']['videoId'];
        }

        return null;
    }

    /**
     * Get cached data if valid
     */
    private function getFromCache(): ?array {
        if (!file_exists($this->cacheFile)) {
            return null;
        }

        $content = file_get_contents($this->cacheFile);
        $data = json_decode($content, true);

        if (!$data || !isset($data['timestamp'])) {
            return null;
        }

        // Check if cache is still valid
        if (time() - $data['timestamp'] > $this->cacheDuration) {
            return null;
        }

        return $data;
    }

    /**
     * Save result to cache
     */
    private function saveToCache(?string $videoId): void {
        $data = [
            'video_id' => $videoId,
            'timestamp' => time()
        ];

        @file_put_contents($this->cacheFile, json_encode($data));
    }

    /**
     * Clear the cache (useful after going live)
     */
    public function clearCache(): void {
        if (file_exists($this->cacheFile)) {
            @unlink($this->cacheFile);
        }
    }
}
