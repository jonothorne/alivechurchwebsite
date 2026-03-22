<?php
/**
 * ApiResponse - Standardized JSON API response handling
 *
 * Eliminates duplicate response patterns across 20+ API files.
 */

class ApiResponse {
    /**
     * Send a success response
     *
     * @param mixed $data Response data
     * @param int $code HTTP status code
     */
    public static function success($data = [], int $code = 200): void {
        self::send(['success' => true] + (is_array($data) ? $data : ['data' => $data]), $code);
    }

    /**
     * Send an error response
     *
     * @param string $message Error message
     * @param int $code HTTP status code
     * @param array $extra Additional fields (e.g., 'errors' => [], 'field' => 'email')
     */
    public static function error(string $message, int $code = 400, array $extra = []): void {
        self::send(['success' => false, 'error' => $message] + $extra, $code);
    }

    /**
     * Send a 405 Method Not Allowed response
     */
    public static function methodNotAllowed(): void {
        self::error('Method not allowed', 405);
    }

    /**
     * Send a 401 Unauthorized response
     */
    public static function unauthorized(string $message = 'Not authenticated'): void {
        self::error($message, 401);
    }

    /**
     * Send a 403 Forbidden response
     */
    public static function forbidden(string $message = 'Access denied'): void {
        self::error($message, 403);
    }

    /**
     * Send a 404 Not Found response
     */
    public static function notFound(string $message = 'Not found'): void {
        self::error($message, 404);
    }

    /**
     * Send a 422 Validation Error response
     *
     * @param array $errors Validation errors ['field' => 'message']
     */
    public static function validationError(array $errors): void {
        self::error(reset($errors) ?: 'Validation failed', 422, ['errors' => $errors]);
    }

    /**
     * Send a 500 Server Error response
     */
    public static function serverError(string $message = 'Internal server error'): void {
        self::error($message, 500);
    }

    /**
     * Send JSON response and exit
     *
     * @param array $data Response data
     * @param int $code HTTP status code
     */
    public static function send(array $data, int $code = 200): void {
        // Clean any output buffering
        while (ob_get_level() > 0) {
            ob_end_clean();
        }

        http_response_code($code);
        header('Content-Type: application/json');
        echo json_encode($data, JSON_UNESCAPED_UNICODE);
        exit;
    }

    /**
     * Require POST method or send error
     */
    public static function requirePost(): void {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            self::methodNotAllowed();
        }
    }

    /**
     * Require GET method or send error
     */
    public static function requireGet(): void {
        if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
            self::methodNotAllowed();
        }
    }

    /**
     * Require authentication or send error
     *
     * @param callable $authCheck Function that returns true if authenticated
     */
    public static function requireAuth(callable $authCheck): void {
        if (!$authCheck()) {
            self::unauthorized();
        }
    }

    /**
     * Verify CSRF token or send error
     *
     * @param string|null $token Token to verify (auto-fetches from header/POST if null)
     */
    public static function requireCsrf(?string $token = null): void {
        if ($token === null) {
            $token = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? $_POST['csrf_token'] ?? '';
        }

        if (!function_exists('verify_csrf') || !verify_csrf($token)) {
            self::forbidden('Invalid CSRF token');
        }
    }

    /**
     * Get JSON input from request body
     *
     * @param bool $required Throw error if no input provided
     * @return array Decoded JSON data
     */
    public static function getJsonInput(bool $required = false): array {
        $input = $_POST;

        if (empty($input)) {
            $json = file_get_contents('php://input');
            $input = json_decode($json, true) ?? [];
        }

        if ($required && empty($input)) {
            self::error('No input provided', 400);
        }

        return $input;
    }
}
