<?php
/**
 * Site Cache Manager
 * Simple file-based caching for frequently accessed data
 */

class SiteCache {
    private static $cacheDir;
    private static $memoryCache = [];

    /**
     * Initialize cache directory
     */
    private static function init() {
        if (self::$cacheDir === null) {
            self::$cacheDir = __DIR__ . '/../data/cache';
            if (!is_dir(self::$cacheDir)) {
                mkdir(self::$cacheDir, 0755, true);
            }
        }
    }

    /**
     * Get cached value
     */
    public static function get($key, $default = null) {
        // Check memory cache first (fastest)
        if (isset(self::$memoryCache[$key])) {
            return self::$memoryCache[$key];
        }

        self::init();
        $file = self::$cacheDir . '/' . md5($key) . '.cache';

        if (!file_exists($file)) {
            return $default;
        }

        $data = unserialize(file_get_contents($file));

        // Check expiration
        if ($data['expires'] !== 0 && $data['expires'] < time()) {
            unlink($file);
            return $default;
        }

        // Store in memory for subsequent access
        self::$memoryCache[$key] = $data['value'];

        return $data['value'];
    }

    /**
     * Set cached value
     * @param string $key Cache key
     * @param mixed $value Value to cache
     * @param int $ttl Time to live in seconds (0 = forever)
     */
    public static function set($key, $value, $ttl = 3600) {
        self::init();

        // Store in memory cache
        self::$memoryCache[$key] = $value;

        $file = self::$cacheDir . '/' . md5($key) . '.cache';
        $data = [
            'expires' => $ttl > 0 ? time() + $ttl : 0,
            'value' => $value
        ];

        file_put_contents($file, serialize($data), LOCK_EX);
    }

    /**
     * Delete cached value
     */
    public static function delete($key) {
        unset(self::$memoryCache[$key]);

        self::init();
        $file = self::$cacheDir . '/' . md5($key) . '.cache';

        if (file_exists($file)) {
            unlink($file);
        }
    }

    /**
     * Clear all cache
     */
    public static function flush() {
        self::$memoryCache = [];
        self::init();

        $files = glob(self::$cacheDir . '/*.cache');
        foreach ($files as $file) {
            unlink($file);
        }
    }

    /**
     * Get or compute cached value
     * If value doesn't exist, calls callback and caches result
     */
    public static function remember($key, $ttl, $callback) {
        $value = self::get($key);

        if ($value !== null) {
            return $value;
        }

        $value = $callback();
        self::set($key, $value, $ttl);

        return $value;
    }

    /**
     * Get site settings with caching
     */
    public static function getSiteSettings($pdo) {
        return self::remember('site_settings', 300, function() use ($pdo) {
            try {
                $stmt = $pdo->query("SELECT setting_key, setting_value FROM site_settings");
                return $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
            } catch (Exception $e) {
                return [];
            }
        });
    }

    /**
     * Clear site settings cache (call after admin updates)
     */
    public static function clearSiteSettings() {
        self::delete('site_settings');
    }
}
