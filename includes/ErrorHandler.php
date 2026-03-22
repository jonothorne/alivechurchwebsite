<?php
/**
 * ErrorHandler - Centralized error and exception handling
 *
 * Provides consistent error handling across the application.
 */

require_once __DIR__ . '/exceptions/AppException.php';

class ErrorHandler {
    private static bool $registered = false;
    private static bool $isDebug = false;

    /**
     * Register error and exception handlers
     */
    public static function register(bool $debug = false): void {
        if (self::$registered) {
            return;
        }

        self::$isDebug = $debug;

        // Set error handler
        set_error_handler([self::class, 'handleError']);

        // Set exception handler
        set_exception_handler([self::class, 'handleException']);

        // Register shutdown function for fatal errors
        register_shutdown_function([self::class, 'handleShutdown']);

        self::$registered = true;
    }

    /**
     * Handle PHP errors
     */
    public static function handleError(int $level, string $message, string $file, int $line): bool {
        // Check if error should be reported
        if (!(error_reporting() & $level)) {
            return false;
        }

        // Convert error to exception
        throw new ErrorException($message, 0, $level, $file, $line);
    }

    /**
     * Handle uncaught exceptions
     */
    public static function handleException(Throwable $exception): void {
        // Log the exception
        self::logException($exception);

        // Determine if this is an API request
        $isApi = self::isApiRequest();

        if ($isApi) {
            self::renderJsonError($exception);
        } else {
            self::renderHtmlError($exception);
        }
    }

    /**
     * Handle fatal errors on shutdown
     */
    public static function handleShutdown(): void {
        $error = error_get_last();

        if ($error !== null && in_array($error['type'], [E_ERROR, E_CORE_ERROR, E_COMPILE_ERROR, E_PARSE])) {
            $exception = new ErrorException(
                $error['message'],
                0,
                $error['type'],
                $error['file'],
                $error['line']
            );
            self::handleException($exception);
        }
    }

    /**
     * Log exception
     */
    private static function logException(Throwable $exception): void {
        $message = sprintf(
            "[%s] %s: %s in %s:%d\nStack trace:\n%s",
            date('Y-m-d H:i:s'),
            get_class($exception),
            $exception->getMessage(),
            $exception->getFile(),
            $exception->getLine(),
            $exception->getTraceAsString()
        );

        error_log($message);
    }

    /**
     * Render JSON error response
     */
    private static function renderJsonError(Throwable $exception): void {
        // Clean output buffer
        while (ob_get_level() > 0) {
            ob_end_clean();
        }

        // Get status code
        $statusCode = 500;
        if ($exception instanceof AppException) {
            $statusCode = $exception->getStatusCode();
        }

        http_response_code($statusCode);
        header('Content-Type: application/json');

        $response = [
            'success' => false,
            'error' => $exception->getMessage()
        ];

        // Add validation errors if applicable
        if ($exception instanceof ValidationException) {
            $response['errors'] = $exception->getErrors();
        }

        // Add debug info in development
        if (self::$isDebug) {
            $response['debug'] = [
                'exception' => get_class($exception),
                'file' => $exception->getFile(),
                'line' => $exception->getLine(),
                'trace' => explode("\n", $exception->getTraceAsString())
            ];
        }

        echo json_encode($response);
        exit;
    }

    /**
     * Render HTML error page
     */
    private static function renderHtmlError(Throwable $exception): void {
        // Clean output buffer
        while (ob_get_level() > 0) {
            ob_end_clean();
        }

        // Get status code
        $statusCode = 500;
        if ($exception instanceof AppException) {
            $statusCode = $exception->getStatusCode();
        }

        http_response_code($statusCode);

        // Try to use custom error page
        $errorPage = dirname(__DIR__) . "/errors/{$statusCode}.php";
        if (file_exists($errorPage)) {
            $error_message = $exception->getMessage();
            $error_code = $statusCode;
            include $errorPage;
            exit;
        }

        // Fallback error page
        $title = self::getStatusTitle($statusCode);
        $message = self::$isDebug ? $exception->getMessage() : self::getGenericMessage($statusCode);

        echo "<!DOCTYPE html>
<html>
<head>
    <meta charset='UTF-8'>
    <meta name='viewport' content='width=device-width, initial-scale=1.0'>
    <title>{$statusCode} - {$title}</title>
    <style>
        body { font-family: system-ui, sans-serif; max-width: 600px; margin: 100px auto; padding: 20px; text-align: center; }
        h1 { color: #333; margin-bottom: 10px; }
        p { color: #666; line-height: 1.6; }
        a { color: #4B2679; }
        .debug { background: #f5f5f5; padding: 20px; text-align: left; margin-top: 30px; border-radius: 8px; font-size: 12px; overflow: auto; }
        .debug pre { margin: 0; white-space: pre-wrap; }
    </style>
</head>
<body>
    <h1>{$statusCode}</h1>
    <p>{$message}</p>
    <p><a href='/'>Return to Homepage</a></p>";

        if (self::$isDebug) {
            echo "<div class='debug'>
                <strong>Exception:</strong> " . get_class($exception) . "<br>
                <strong>File:</strong> {$exception->getFile()}:{$exception->getLine()}<br><br>
                <strong>Stack Trace:</strong>
                <pre>" . htmlspecialchars($exception->getTraceAsString()) . "</pre>
            </div>";
        }

        echo "</body></html>";
        exit;
    }

    /**
     * Check if this is an API request
     */
    private static function isApiRequest(): bool {
        // Check Accept header
        $accept = $_SERVER['HTTP_ACCEPT'] ?? '';
        if (strpos($accept, 'application/json') !== false) {
            return true;
        }

        // Check URL
        $uri = $_SERVER['REQUEST_URI'] ?? '';
        if (strpos($uri, '/api/') === 0) {
            return true;
        }

        // Check X-Requested-With
        if (($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'XMLHttpRequest') {
            return true;
        }

        return false;
    }

    /**
     * Get status title
     */
    private static function getStatusTitle(int $code): string {
        $titles = [
            400 => 'Bad Request',
            401 => 'Unauthorized',
            403 => 'Forbidden',
            404 => 'Not Found',
            405 => 'Method Not Allowed',
            422 => 'Validation Error',
            429 => 'Too Many Requests',
            500 => 'Internal Server Error',
            503 => 'Service Unavailable'
        ];

        return $titles[$code] ?? 'Error';
    }

    /**
     * Get generic error message
     */
    private static function getGenericMessage(int $code): string {
        $messages = [
            400 => 'The request could not be understood.',
            401 => 'Please log in to continue.',
            403 => 'You don\'t have permission to access this page.',
            404 => 'The page you\'re looking for doesn\'t exist.',
            405 => 'This action is not allowed.',
            422 => 'The submitted data is invalid.',
            429 => 'Too many requests. Please try again later.',
            500 => 'Something went wrong. We\'re working on it.',
            503 => 'The site is temporarily unavailable.'
        ];

        return $messages[$code] ?? 'An error occurred.';
    }

    /**
     * Helper to throw validation exception
     */
    public static function validationFailed(array $errors): void {
        throw new ValidationException($errors);
    }

    /**
     * Helper to throw not found exception
     */
    public static function notFound(string $message = 'Resource not found'): void {
        throw new NotFoundException($message);
    }

    /**
     * Helper to throw forbidden exception
     */
    public static function forbidden(string $message = 'Access denied'): void {
        throw new ForbiddenException($message);
    }

    /**
     * Helper to throw unauthorized exception
     */
    public static function unauthorized(string $message = 'Authentication required'): void {
        throw new UnauthorizedException($message);
    }
}
