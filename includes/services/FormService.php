<?php
/**
 * FormService - Business logic for form handling
 *
 * Consolidates form processing from api/forms/submit.php and form-handler.php
 */

class FormService {
    private PDO $pdo;
    private array $validTypes = ['contact', 'prayer', 'visit', 'group', 'serve', 'event', 'baptism'];

    public function __construct(PDO $pdo) {
        $this->pdo = $pdo;
    }

    /**
     * Process a form submission
     *
     * @param string $type Form type
     * @param array $data Form data
     * @return array Result with success status
     */
    public function submit(string $type, array $data): array {
        // Validate type
        if (!in_array($type, $this->validTypes)) {
            return ['success' => false, 'error' => 'Invalid form type'];
        }

        // Sanitize data
        $sanitized = $this->sanitize($data);

        // Validate based on type
        $errors = $this->validate($type, $sanitized);
        if (!empty($errors)) {
            return [
                'success' => false,
                'error' => reset($errors),
                'errors' => $errors
            ];
        }

        // Store to database
        $stored = $this->storeToDatabase($type, $sanitized);

        // Store to JSON backup
        $backedUp = $this->storeToJson($type, $sanitized);

        // Send notification email
        $notified = $this->sendNotification($type, $sanitized);

        if (!$stored && !$backedUp) {
            return ['success' => false, 'error' => 'Failed to save submission'];
        }

        return [
            'success' => true,
            'message' => $this->getSuccessMessage($type)
        ];
    }

    /**
     * Sanitize form data
     */
    public function sanitize(array $data): array {
        $sanitized = [];
        foreach ($data as $key => $value) {
            if (is_string($value)) {
                $sanitized[$key] = trim(htmlspecialchars($value, ENT_QUOTES, 'UTF-8'));
            } else {
                $sanitized[$key] = $value;
            }
        }
        return $sanitized;
    }

    /**
     * Validate form data based on type
     */
    public function validate(string $type, array $data): array {
        $errors = [];

        switch ($type) {
            case 'contact':
                if (empty($data['name'])) {
                    $errors['name'] = 'Name is required';
                }
                if (empty($data['email']) || !filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
                    $errors['email'] = 'Valid email is required';
                }
                if (empty($data['message'])) {
                    $errors['message'] = 'Message is required';
                }
                break;

            case 'prayer':
                if (empty($data['prayer_request']) && empty($data['message'])) {
                    $errors['prayer_request'] = 'Prayer request is required';
                }
                break;

            case 'visit':
                if (empty($data['name'])) {
                    $errors['name'] = 'Name is required';
                }
                if (empty($data['email']) || !filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
                    $errors['email'] = 'Valid email is required';
                }
                break;

            case 'group':
            case 'serve':
                if (empty($data['name'])) {
                    $errors['name'] = 'Name is required';
                }
                if (empty($data['email']) || !filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
                    $errors['email'] = 'Valid email is required';
                }
                break;

            case 'event':
                if (empty($data['name'])) {
                    $errors['name'] = 'Name is required';
                }
                if (empty($data['email']) || !filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
                    $errors['email'] = 'Valid email is required';
                }
                if (empty($data['event_id'])) {
                    $errors['event_id'] = 'Event selection is required';
                }
                break;

            case 'baptism':
                if (empty($data['name'])) {
                    $errors['name'] = 'Name is required';
                }
                if (empty($data['email']) || !filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
                    $errors['email'] = 'Valid email is required';
                }
                break;
        }

        return $errors;
    }

    /**
     * Store submission to database
     */
    private function storeToDatabase(string $type, array $data): bool {
        try {
            $stmt = $this->pdo->prepare(
                "INSERT INTO form_submissions (form_type, form_data, ip_address, submitted_at, processed)
                 VALUES (?, ?, ?, NOW(), FALSE)"
            );
            return $stmt->execute([
                $type,
                json_encode($data, JSON_UNESCAPED_UNICODE),
                $_SERVER['REMOTE_ADDR'] ?? 'unknown'
            ]);
        } catch (Exception $e) {
            error_log('FormService storeToDatabase error: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Store submission to JSON file (backup)
     */
    private function storeToJson(string $type, array $data): bool {
        $dir = dirname(__DIR__, 2) . '/storage';
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $file = $dir . '/form-submissions.json';
        $existing = [];

        if (file_exists($file)) {
            $json = file_get_contents($file);
            $decoded = json_decode($json, true);
            if (is_array($decoded)) {
                $existing = $decoded;
            }
        }

        $existing[] = [
            'type' => $type,
            'timestamp' => gmdate('c'),
            'data' => $data,
            'ip_address' => $_SERVER['REMOTE_ADDR'] ?? 'unknown'
        ];

        return (bool)file_put_contents($file, json_encode($existing, JSON_PRETTY_PRINT));
    }

    /**
     * Send notification email
     */
    private function sendNotification(string $type, array $data): bool {
        // Use form-handler functions if available
        if (function_exists('send_form_notification')) {
            return send_form_notification($type, $data);
        }

        // Otherwise, log that email would be sent
        error_log("Form notification would be sent for {$type} form");
        return true;
    }

    /**
     * Get success message for form type
     */
    private function getSuccessMessage(string $type): string {
        $messages = [
            'contact' => 'Thank you for your message! We\'ll get back to you soon.',
            'prayer' => 'Thank you for sharing your prayer request. We will be praying for you.',
            'visit' => 'Thank you for registering! We look forward to seeing you.',
            'group' => 'Thank you for your interest in joining a group! We\'ll be in touch soon.',
            'serve' => 'Thank you for wanting to serve! Someone will contact you about opportunities.',
            'event' => 'You\'ve been registered for the event. See you there!',
            'baptism' => 'Thank you for your interest in baptism! We\'ll contact you with next steps.'
        ];

        return $messages[$type] ?? 'Thank you for your submission!';
    }

    /**
     * Get recent submissions (for admin)
     */
    public function getRecent(int $limit = 50, ?string $type = null): array {
        $sql = "SELECT * FROM form_submissions";
        $params = [];

        if ($type) {
            $sql .= " WHERE form_type = ?";
            $params[] = $type;
        }

        $sql .= " ORDER BY submitted_at DESC LIMIT ?";
        $params[] = $limit;

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    /**
     * Mark submission as processed
     */
    public function markProcessed(int $id): bool {
        $stmt = $this->pdo->prepare(
            "UPDATE form_submissions SET processed = TRUE, processed_at = NOW() WHERE id = ?"
        );
        return $stmt->execute([$id]);
    }

    /**
     * Get valid form types
     */
    public function getValidTypes(): array {
        return $this->validTypes;
    }
}
