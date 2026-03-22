<?php
/**
 * Environment Variable Loader
 *
 * Loads configuration from .env file into PHP environment.
 * Falls back to existing defines for backward compatibility.
 */

class Env {
    private static bool $loaded = false;
    private static array $vars = [];

    /**
     * Load environment variables from .env file
     */
    public static function load(string $path = null): void {
        if (self::$loaded) {
            return;
        }

        $path = $path ?? dirname(__DIR__) . '/.env';

        if (file_exists($path)) {
            $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

            foreach ($lines as $line) {
                // Skip comments
                if (strpos(trim($line), '#') === 0) {
                    continue;
                }

                // Parse KEY=value
                if (strpos($line, '=') !== false) {
                    list($key, $value) = explode('=', $line, 2);
                    $key = trim($key);
                    $value = trim($value);

                    // Remove quotes if present
                    if (preg_match('/^(["\'])(.*)\\1$/', $value, $matches)) {
                        $value = $matches[2];
                    }

                    // Store in our array and set in environment
                    self::$vars[$key] = $value;
                    putenv("{$key}={$value}");
                    $_ENV[$key] = $value;
                }
            }
        }

        self::$loaded = true;
    }

    /**
     * Get an environment variable
     *
     * @param string $key The variable name
     * @param mixed $default Default value if not found
     * @return mixed
     */
    public static function get(string $key, $default = null) {
        // Ensure env is loaded
        if (!self::$loaded) {
            self::load();
        }

        // Check our loaded vars first
        if (isset(self::$vars[$key])) {
            return self::parseValue(self::$vars[$key]);
        }

        // Check environment
        $value = getenv($key);
        if ($value !== false) {
            return self::parseValue($value);
        }

        // Check $_ENV
        if (isset($_ENV[$key])) {
            return self::parseValue($_ENV[$key]);
        }

        return $default;
    }

    /**
     * Parse special values (booleans, null, etc.)
     */
    private static function parseValue(string $value) {
        $lower = strtolower($value);

        switch ($lower) {
            case 'true':
            case '(true)':
                return true;
            case 'false':
            case '(false)':
                return false;
            case 'null':
            case '(null)':
                return null;
            case 'empty':
            case '(empty)':
                return '';
        }

        return $value;
    }

    /**
     * Check if running in production
     */
    public static function isProduction(): bool {
        return self::get('APP_ENV', 'production') === 'production';
    }

    /**
     * Check if debug mode is enabled
     */
    public static function isDebug(): bool {
        return self::get('APP_DEBUG', false) === true;
    }

    /**
     * Get a required environment variable (throws if missing)
     */
    public static function require(string $key): string {
        $value = self::get($key);

        if ($value === null || $value === '') {
            throw new RuntimeException("Required environment variable '{$key}' is not set");
        }

        return $value;
    }
}

/**
 * Helper function for quick access
 */
function env(string $key, $default = null) {
    return Env::get($key, $default);
}
