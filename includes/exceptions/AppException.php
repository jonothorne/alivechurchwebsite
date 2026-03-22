<?php
/**
 * AppException - Base exception class for application exceptions
 */

class AppException extends Exception {
    protected int $statusCode = 500;
    protected array $context = [];

    public function __construct(string $message = '', int $code = 0, ?Throwable $previous = null, array $context = []) {
        parent::__construct($message, $code, $previous);
        $this->context = $context;
    }

    public function getStatusCode(): int {
        return $this->statusCode;
    }

    public function getContext(): array {
        return $this->context;
    }

    public function toArray(): array {
        return [
            'error' => $this->getMessage(),
            'code' => $this->getCode(),
            'context' => $this->context
        ];
    }
}

/**
 * ValidationException - Thrown when validation fails
 */
class ValidationException extends AppException {
    protected int $statusCode = 422;
    private array $errors;

    public function __construct(array $errors, string $message = 'Validation failed') {
        parent::__construct($message);
        $this->errors = $errors;
    }

    public function getErrors(): array {
        return $this->errors;
    }

    public function toArray(): array {
        return [
            'error' => $this->getMessage(),
            'errors' => $this->errors
        ];
    }
}

/**
 * NotFoundException - Thrown when a resource is not found
 */
class NotFoundException extends AppException {
    protected int $statusCode = 404;

    public function __construct(string $message = 'Resource not found') {
        parent::__construct($message);
    }
}

/**
 * UnauthorizedException - Thrown when authentication is required
 */
class UnauthorizedException extends AppException {
    protected int $statusCode = 401;

    public function __construct(string $message = 'Authentication required') {
        parent::__construct($message);
    }
}

/**
 * ForbiddenException - Thrown when access is denied
 */
class ForbiddenException extends AppException {
    protected int $statusCode = 403;

    public function __construct(string $message = 'Access denied') {
        parent::__construct($message);
    }
}

/**
 * RateLimitException - Thrown when rate limit is exceeded
 */
class RateLimitException extends AppException {
    protected int $statusCode = 429;
    private int $retryAfter;

    public function __construct(int $retryAfter = 60, string $message = 'Too many requests') {
        parent::__construct($message);
        $this->retryAfter = $retryAfter;
    }

    public function getRetryAfter(): int {
        return $this->retryAfter;
    }
}

/**
 * ConfigurationException - Thrown when configuration is invalid
 */
class ConfigurationException extends AppException {
    protected int $statusCode = 500;

    public function __construct(string $message = 'Configuration error') {
        parent::__construct($message);
    }
}
