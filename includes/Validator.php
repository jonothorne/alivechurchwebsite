<?php
/**
 * Validator - Standardized input validation
 *
 * Consolidates duplicate validation logic across the codebase.
 */

class Validator {
    private array $data;
    private array $errors = [];

    public function __construct(array $data) {
        $this->data = $data;
    }

    /**
     * Create validator from data array
     */
    public static function make(array $data): self {
        return new self($data);
    }

    /**
     * Validate required field
     */
    public function required(string $field, ?string $message = null): self {
        if (empty($this->data[$field]) && $this->data[$field] !== '0') {
            $this->errors[$field] = $message ?? ucfirst(str_replace('_', ' ', $field)) . ' is required';
        }
        return $this;
    }

    /**
     * Validate email format
     */
    public function email(string $field, ?string $message = null): self {
        if (!empty($this->data[$field]) && !filter_var($this->data[$field], FILTER_VALIDATE_EMAIL)) {
            $this->errors[$field] = $message ?? 'Please enter a valid email address';
        }
        return $this;
    }

    /**
     * Validate minimum length
     */
    public function minLength(string $field, int $min, ?string $message = null): self {
        if (!empty($this->data[$field]) && strlen($this->data[$field]) < $min) {
            $this->errors[$field] = $message ?? ucfirst(str_replace('_', ' ', $field)) . " must be at least {$min} characters";
        }
        return $this;
    }

    /**
     * Validate maximum length
     */
    public function maxLength(string $field, int $max, ?string $message = null): self {
        if (!empty($this->data[$field]) && strlen($this->data[$field]) > $max) {
            $this->errors[$field] = $message ?? ucfirst(str_replace('_', ' ', $field)) . " must be no more than {$max} characters";
        }
        return $this;
    }

    /**
     * Validate numeric value
     */
    public function numeric(string $field, ?string $message = null): self {
        if (!empty($this->data[$field]) && !is_numeric($this->data[$field])) {
            $this->errors[$field] = $message ?? ucfirst(str_replace('_', ' ', $field)) . ' must be a number';
        }
        return $this;
    }

    /**
     * Validate integer value
     */
    public function integer(string $field, ?string $message = null): self {
        if (!empty($this->data[$field]) && filter_var($this->data[$field], FILTER_VALIDATE_INT) === false) {
            $this->errors[$field] = $message ?? ucfirst(str_replace('_', ' ', $field)) . ' must be a whole number';
        }
        return $this;
    }

    /**
     * Validate positive number
     */
    public function positive(string $field, ?string $message = null): self {
        if (!empty($this->data[$field]) && floatval($this->data[$field]) <= 0) {
            $this->errors[$field] = $message ?? ucfirst(str_replace('_', ' ', $field)) . ' must be greater than zero';
        }
        return $this;
    }

    /**
     * Validate value is in allowed list
     */
    public function in(string $field, array $allowed, ?string $message = null): self {
        if (!empty($this->data[$field]) && !in_array($this->data[$field], $allowed)) {
            $this->errors[$field] = $message ?? 'Invalid value for ' . str_replace('_', ' ', $field);
        }
        return $this;
    }

    /**
     * Validate URL format
     */
    public function url(string $field, ?string $message = null): self {
        if (!empty($this->data[$field]) && !filter_var($this->data[$field], FILTER_VALIDATE_URL)) {
            $this->errors[$field] = $message ?? 'Please enter a valid URL';
        }
        return $this;
    }

    /**
     * Validate date format
     */
    public function date(string $field, string $format = 'Y-m-d', ?string $message = null): self {
        if (!empty($this->data[$field])) {
            $d = DateTime::createFromFormat($format, $this->data[$field]);
            if (!$d || $d->format($format) !== $this->data[$field]) {
                $this->errors[$field] = $message ?? 'Please enter a valid date';
            }
        }
        return $this;
    }

    /**
     * Validate using regex pattern
     */
    public function pattern(string $field, string $regex, ?string $message = null): self {
        if (!empty($this->data[$field]) && !preg_match($regex, $this->data[$field])) {
            $this->errors[$field] = $message ?? 'Invalid format for ' . str_replace('_', ' ', $field);
        }
        return $this;
    }

    /**
     * Validate password strength
     */
    public function password(string $field, ?string $message = null): self {
        if (!empty($this->data[$field])) {
            $password = $this->data[$field];
            if (strlen($password) < 8) {
                $this->errors[$field] = $message ?? 'Password must be at least 8 characters';
            } elseif (!preg_match('/[A-Z]/', $password)) {
                $this->errors[$field] = $message ?? 'Password must contain at least one uppercase letter';
            } elseif (!preg_match('/[a-z]/', $password)) {
                $this->errors[$field] = $message ?? 'Password must contain at least one lowercase letter';
            } elseif (!preg_match('/[0-9]/', $password)) {
                $this->errors[$field] = $message ?? 'Password must contain at least one number';
            }
        }
        return $this;
    }

    /**
     * Validate field matches another field
     */
    public function matches(string $field, string $otherField, ?string $message = null): self {
        if (($this->data[$field] ?? '') !== ($this->data[$otherField] ?? '')) {
            $this->errors[$field] = $message ?? ucfirst(str_replace('_', ' ', $field)) . ' does not match';
        }
        return $this;
    }

    /**
     * Validate phone number (basic)
     */
    public function phone(string $field, ?string $message = null): self {
        if (!empty($this->data[$field])) {
            // Remove common formatting characters
            $phone = preg_replace('/[\s\-\(\)\.]/', '', $this->data[$field]);
            // Check for reasonable phone number format
            if (!preg_match('/^\+?[0-9]{7,15}$/', $phone)) {
                $this->errors[$field] = $message ?? 'Please enter a valid phone number';
            }
        }
        return $this;
    }

    /**
     * Custom validation with callback
     */
    public function custom(string $field, callable $callback, ?string $message = null): self {
        if (!empty($this->data[$field]) && !$callback($this->data[$field], $this->data)) {
            $this->errors[$field] = $message ?? 'Invalid value for ' . str_replace('_', ' ', $field);
        }
        return $this;
    }

    /**
     * Check if validation passed
     */
    public function passes(): bool {
        return empty($this->errors);
    }

    /**
     * Check if validation failed
     */
    public function fails(): bool {
        return !empty($this->errors);
    }

    /**
     * Get all validation errors
     */
    public function errors(): array {
        return $this->errors;
    }

    /**
     * Get first error message
     */
    public function firstError(): ?string {
        return reset($this->errors) ?: null;
    }

    /**
     * Get error for specific field
     */
    public function error(string $field): ?string {
        return $this->errors[$field] ?? null;
    }

    /**
     * Get validated and sanitized data
     */
    public function validated(): array {
        $validated = [];
        foreach ($this->data as $key => $value) {
            if (!isset($this->errors[$key])) {
                $validated[$key] = self::sanitize($value);
            }
        }
        return $validated;
    }

    /**
     * Sanitize a value
     */
    public static function sanitize($value): string {
        if (is_array($value)) {
            return array_map([self::class, 'sanitize'], $value);
        }
        return trim(htmlspecialchars($value, ENT_QUOTES, 'UTF-8'));
    }

    /**
     * Sanitize for plain text (no HTML)
     */
    public static function sanitizeText(string $value): string {
        return trim(strip_tags($value));
    }

    /**
     * Sanitize integer
     */
    public static function sanitizeInt($value): int {
        return intval($value);
    }

    /**
     * Sanitize float
     */
    public static function sanitizeFloat($value): float {
        return floatval($value);
    }

    /**
     * Sanitize boolean
     */
    public static function sanitizeBool($value): bool {
        return filter_var($value, FILTER_VALIDATE_BOOLEAN);
    }

    /**
     * Validate and respond with errors if failed (for API endpoints)
     *
     * @return array Validated data if successful (exits with error response if failed)
     */
    public function validateOrFail(): array {
        if ($this->fails()) {
            if (class_exists('ApiResponse')) {
                ApiResponse::validationError($this->errors());
            } else {
                http_response_code(422);
                header('Content-Type: application/json');
                echo json_encode(['success' => false, 'error' => $this->firstError(), 'errors' => $this->errors()]);
                exit;
            }
        }
        return $this->validated();
    }
}
