<?php
/**
 * AuthMiddleware - Unified authentication middleware
 *
 * Provides consistent authentication checking across all endpoints.
 * Replaces scattered auth checks with a single, testable pattern.
 *
 * Usage:
 *   AuthMiddleware::requireAuth();           // Require any authenticated user
 *   AuthMiddleware::requireAdmin();          // Require admin role
 *   AuthMiddleware::requireEditor();         // Require admin or editor role
 *   AuthMiddleware::requireRole('admin');    // Require specific role
 */

class AuthMiddleware {
    private static ?Auth $auth = null;

    /**
     * Initialize the Auth instance (lazy loaded)
     */
    private static function getAuth(): Auth {
        if (self::$auth === null) {
            require_once __DIR__ . '/../db-config.php';
            require_once __DIR__ . '/../Auth.php';
            self::$auth = new Auth(getDbConnection());
        }
        return self::$auth;
    }

    /**
     * Set custom Auth instance (useful for testing)
     */
    public static function setAuth(Auth $auth): void {
        self::$auth = $auth;
    }

    /**
     * Require authenticated user
     *
     * @param string $redirectUrl URL to redirect to if not authenticated (for web pages)
     * @param bool $isApi If true, return JSON error instead of redirect
     */
    public static function requireAuth(?string $redirectUrl = null, bool $isApi = null): void {
        // Auto-detect if this is an API request
        if ($isApi === null) {
            $isApi = self::isApiRequest();
        }

        if (!self::check()) {
            if ($isApi) {
                self::jsonError('Not authenticated', 401);
            } else {
                $redirect = $redirectUrl ?? '/login?redirect=' . urlencode($_SERVER['REQUEST_URI'] ?? '/');
                header('Location: ' . $redirect);
                exit;
            }
        }
    }

    /**
     * Require admin role
     */
    public static function requireAdmin(bool $isApi = null): void {
        self::requireAuth(null, $isApi);

        if (!self::isAdmin()) {
            if ($isApi ?? self::isApiRequest()) {
                self::jsonError('Admin access required', 403);
            } else {
                header('Location: /admin?error=access_denied');
                exit;
            }
        }
    }

    /**
     * Require editor role (admin or editor)
     */
    public static function requireEditor(bool $isApi = null): void {
        self::requireAuth(null, $isApi);

        if (!self::isEditor() && !self::isAdmin()) {
            if ($isApi ?? self::isApiRequest()) {
                self::jsonError('Editor access required', 403);
            } else {
                header('Location: /admin?error=access_denied');
                exit;
            }
        }
    }

    /**
     * Require specific role
     *
     * @param string|array $roles Role or array of allowed roles
     */
    public static function requireRole($roles, bool $isApi = null): void {
        self::requireAuth(null, $isApi);

        $roles = is_array($roles) ? $roles : [$roles];
        $user = self::user();

        if (!$user || !in_array($user['role'], $roles)) {
            if ($isApi ?? self::isApiRequest()) {
                self::jsonError('Access denied', 403);
            } else {
                header('Location: /admin?error=access_denied');
                exit;
            }
        }
    }

    /**
     * Require CSRF token validation
     */
    public static function requireCsrf(): void {
        $token = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? $_POST['csrf_token'] ?? '';

        if (!verify_csrf($token)) {
            if (self::isApiRequest()) {
                self::jsonError('Invalid CSRF token', 403);
            } else {
                header('Location: ' . ($_SERVER['HTTP_REFERER'] ?? '/') . '?error=csrf');
                exit;
            }
        }
    }

    /**
     * Check if current user is authenticated
     */
    public static function check(): bool {
        return self::getAuth()->check();
    }

    /**
     * Check if current user is admin
     */
    public static function isAdmin(): bool {
        return self::getAuth()->isAdmin();
    }

    /**
     * Check if current user is editor
     */
    public static function isEditor(): bool {
        return self::getAuth()->isEditor();
    }

    /**
     * Get current user
     */
    public static function user(): ?array {
        return self::getAuth()->user();
    }

    /**
     * Get current user ID
     */
    public static function userId(): ?int {
        $user = self::user();
        return $user ? (int)$user['id'] : null;
    }

    /**
     * Check if user has any of the given roles
     */
    public static function hasRole($roles): bool {
        $user = self::user();
        if (!$user) {
            return false;
        }
        $roles = is_array($roles) ? $roles : [$roles];
        return in_array($user['role'], $roles);
    }

    /**
     * Detect if this is an API request
     */
    private static function isApiRequest(): bool {
        // Check Accept header
        $accept = $_SERVER['HTTP_ACCEPT'] ?? '';
        if (strpos($accept, 'application/json') !== false) {
            return true;
        }

        // Check if URL starts with /api/
        $uri = $_SERVER['REQUEST_URI'] ?? '';
        if (strpos($uri, '/api/') === 0) {
            return true;
        }

        // Check Content-Type
        $contentType = $_SERVER['CONTENT_TYPE'] ?? '';
        if (strpos($contentType, 'application/json') !== false) {
            return true;
        }

        // Check X-Requested-With (for AJAX)
        if (($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'XMLHttpRequest') {
            return true;
        }

        return false;
    }

    /**
     * Send JSON error response and exit
     */
    private static function jsonError(string $message, int $code): void {
        http_response_code($code);
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'error' => $message]);
        exit;
    }
}
