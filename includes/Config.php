<?php
/**
 * Config - Centralized configuration management
 *
 * Provides type-safe access to configuration values with defaults.
 */

require_once __DIR__ . '/env-loader.php';

class Config {
    private static array $config = [];
    private static bool $loaded = false;

    /**
     * Load configuration
     */
    public static function load(): void {
        if (self::$loaded) {
            return;
        }

        // Ensure environment is loaded
        Env::load();

        // Define path constants
        self::definePaths();

        // Load configuration values
        self::$config = [
            // Application
            'app' => [
                'name' => env('APP_NAME', 'Alive Church'),
                'env' => env('APP_ENV', 'production'),
                'debug' => env('APP_DEBUG', false),
                'url' => env('APP_URL', ''),
            ],

            // Database
            'database' => [
                'host' => env('DB_HOST', 'localhost'),
                'name' => env('DB_NAME', 'alive_church_cms'),
                'user' => env('DB_USER', 'root'),
                'pass' => env('DB_PASS', ''),
                'charset' => env('DB_CHARSET', 'utf8mb4'),
            ],

            // Mail
            'mail' => [
                'admin_email' => env('CHURCH_ADMIN_EMAIL', 'admin@example.com'),
                'from_address' => env('MAIL_FROM_ADDRESS', 'noreply@example.com'),
                'from_name' => env('CHURCH_NAME', 'Church Website'),
            ],

            // Uploads
            'uploads' => [
                'max_size' => 10 * 1024 * 1024, // 10MB
                'allowed_types' => ['image/jpeg', 'image/png', 'image/gif', 'image/webp', 'image/svg+xml', 'application/pdf'],
            ],

            // Pagination
            'pagination' => [
                'default_limit' => 20,
                'max_limit' => 100,
            ],

            // Cache
            'cache' => [
                'enabled' => env('CACHE_ENABLED', true),
                'ttl' => env('CACHE_TTL', 300), // 5 minutes
            ],

            // API Keys
            'api' => [
                'anthropic' => env('ANTHROPIC_API_KEY', ''),
                'stripe' => env('STRIPE_SECRET_KEY', ''),
            ],
        ];

        self::$loaded = true;
    }

    /**
     * Define path constants
     */
    private static function definePaths(): void {
        $root = dirname(__DIR__);

        // Only define if not already defined (allows override)
        if (!defined('ROOT_PATH')) {
            define('ROOT_PATH', $root);
        }
        if (!defined('INCLUDES_PATH')) {
            define('INCLUDES_PATH', $root . '/includes');
        }
        if (!defined('STORAGE_PATH')) {
            define('STORAGE_PATH', $root . '/storage');
        }
        if (!defined('UPLOAD_PATH')) {
            define('UPLOAD_PATH', STORAGE_PATH . '/uploads');
        }
        if (!defined('CACHE_PATH')) {
            define('CACHE_PATH', $root . '/data/cache');
        }
        if (!defined('ADMIN_PATH')) {
            define('ADMIN_PATH', $root . '/admin');
        }
        if (!defined('ASSETS_PATH')) {
            define('ASSETS_PATH', $root . '/assets');
        }

        // Web paths (for URLs)
        if (!defined('UPLOAD_URL')) {
            define('UPLOAD_URL', '/storage/uploads');
        }
        if (!defined('ASSETS_URL')) {
            define('ASSETS_URL', '/assets');
        }
    }

    /**
     * Get a configuration value
     *
     * @param string $key Dot-notation key (e.g., 'app.name', 'database.host')
     * @param mixed $default Default value if not found
     * @return mixed
     */
    public static function get(string $key, $default = null) {
        if (!self::$loaded) {
            self::load();
        }

        $keys = explode('.', $key);
        $value = self::$config;

        foreach ($keys as $k) {
            if (!is_array($value) || !isset($value[$k])) {
                return $default;
            }
            $value = $value[$k];
        }

        return $value;
    }

    /**
     * Set a configuration value at runtime
     */
    public static function set(string $key, $value): void {
        if (!self::$loaded) {
            self::load();
        }

        $keys = explode('.', $key);
        $config = &self::$config;

        foreach ($keys as $i => $k) {
            if ($i === count($keys) - 1) {
                $config[$k] = $value;
            } else {
                if (!isset($config[$k]) || !is_array($config[$k])) {
                    $config[$k] = [];
                }
                $config = &$config[$k];
            }
        }
    }

    /**
     * Check if configuration key exists
     */
    public static function has(string $key): bool {
        return self::get($key) !== null;
    }

    /**
     * Get all configuration
     */
    public static function all(): array {
        if (!self::$loaded) {
            self::load();
        }
        return self::$config;
    }

    /**
     * Check if running in production
     */
    public static function isProduction(): bool {
        return self::get('app.env') === 'production';
    }

    /**
     * Check if debug mode is enabled
     */
    public static function isDebug(): bool {
        return self::get('app.debug') === true;
    }

    /**
     * Get database configuration
     */
    public static function database(): array {
        return self::get('database');
    }

    /**
     * Get mail configuration
     */
    public static function mail(): array {
        return self::get('mail');
    }
}

/**
 * Helper function for quick config access
 */
function config(string $key, $default = null) {
    return Config::get($key, $default);
}
