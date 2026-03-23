<?php
/**
 * Bootstrap File
 * Centralizes all includes, session management, and common initialization
 * Include this file at the start of every page for consistent setup
 */

// Prevent multiple inclusions
if (defined('APP_BOOTSTRAPPED')) {
    return;
}
define('APP_BOOTSTRAPPED', true);

// Generate CSP nonce for this request (used for inline scripts/styles)
if (!defined('CSP_NONCE')) {
    define('CSP_NONCE', base64_encode(random_bytes(16)));
}

/**
 * Get CSP nonce attribute for inline scripts/styles
 * Usage: <script <?= csp_nonce(); ?>>...</script>
 */
if (!function_exists('csp_nonce')) {
    function csp_nonce() {
        return 'nonce="' . CSP_NONCE . '"';
    }
}

/**
 * Set Content Security Policy header with nonce
 * Called from header.php before any output
 */
if (!function_exists('set_csp_header')) {
    function set_csp_header() {
        if (headers_sent()) {
            return;
        }
        $nonce = CSP_NONCE;
        $csp = "default-src 'self'; " .
               "script-src 'self' 'nonce-{$nonce}' https://js.stripe.com https://www.youtube.com https://www.google.com https://www.gstatic.com https://img1.wsimg.com https://cdn.jsdelivr.net https://unpkg.com; " .
               "style-src 'self' 'unsafe-inline' 'nonce-{$nonce}' https://fonts.googleapis.com https://unpkg.com;" .
               "font-src 'self' https://fonts.gstatic.com; " .
               "img-src 'self' data: https: blob:; " .
               "frame-src 'self' https://www.youtube.com https://www.youtube-nocookie.com https://js.stripe.com https://www.google.com; " .
               "connect-src 'self' https://api.stripe.com https://*.tile.openstreetmap.org; " .
               "object-src 'none'; " .
               "base-uri 'self'; " .
               "form-action 'self';";
        header("Content-Security-Policy: {$csp}");
    }
}

// Start session once for the entire application
if (session_status() === PHP_SESSION_NONE) {
    // Optimize session settings
    ini_set('session.cookie_httponly', 1);
    ini_set('session.use_strict_mode', 1);
    session_start();
}

// Include core files
require_once __DIR__ . '/Config.php';
Config::load(); // Initialize configuration and path constants
require_once __DIR__ . '/db-config.php';
require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/Auth.php';

// Get database connection (singleton)
$pdo = getDbConnection();

// Initialize Auth
$auth = new Auth($pdo);
$current_user = $auth->user();

// Load site configuration with caching
require_once __DIR__ . '/../config.php';

/**
 * Quick escape helper - shorthand for htmlspecialchars
 */
if (!function_exists('e')) {
    function e($string) {
        return htmlspecialchars($string ?? '', ENT_QUOTES, 'UTF-8');
    }
}

/**
 * Quick JSON response helper
 */
if (!function_exists('json_response')) {
    function json_response($data, $status = 200) {
        http_response_code($status);
        header('Content-Type: application/json');
        echo json_encode($data);
        exit;
    }
}

/**
 * Require authentication or redirect
 */
if (!function_exists('require_login')) {
    function require_login($redirect = null) {
        global $auth;
        if (!$auth->check()) {
            $redirect = $redirect ?? $_SERVER['REQUEST_URI'] ?? '/';
            header('Location: /login?redirect=' . urlencode($redirect));
            exit;
        }
    }
}

/**
 * Check if current request is AJAX
 */
if (!function_exists('is_ajax')) {
    function is_ajax() {
        return !empty($_SERVER['HTTP_X_REQUESTED_WITH']) &&
               strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
    }
}

/**
 * Get input with default value
 */
if (!function_exists('input')) {
    function input($key, $default = null, $method = null) {
        if ($method === 'GET' || ($method === null && $_SERVER['REQUEST_METHOD'] === 'GET')) {
            return $_GET[$key] ?? $default;
        }
        if ($method === 'POST' || ($method === null && $_SERVER['REQUEST_METHOD'] === 'POST')) {
            return $_POST[$key] ?? $default;
        }
        return $_REQUEST[$key] ?? $default;
    }
}
