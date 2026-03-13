<?php
/**
 * Form Submission API Endpoint
 * Handles AJAX form submissions for contact, prayer, visit, etc.
 */
header('Content-Type: application/json');

require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../includes/form-handler.php';

// Only accept POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit;
}

// Get input (support both form data and JSON)
$input = $_POST;
if (empty($input)) {
    $json = file_get_contents('php://input');
    $input = json_decode($json, true) ?? [];
}

$formType = $input['form_type'] ?? '';

// Validate form type
$validTypes = ['contact', 'prayer', 'visit', 'group', 'serve', 'event', 'baptism'];
if (!in_array($formType, $validTypes)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Invalid form type']);
    exit;
}

// Remove form_type from data before processing
unset($input['form_type']);

// Sanitize all input fields
$data = [];
foreach ($input as $key => $value) {
    $data[$key] = sanitize_field($value);
}

// Validate based on form type
$errors = validateForm($formType, $data);

if (!empty($errors)) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => reset($errors),
        'errors' => $errors
    ]);
    exit;
}

// Process the form submission
$result = process_form_submission($formType, $data);

if ($result) {
    $messages = [
        'contact' => "Thank you for your message! We'll get back to you as soon as possible.",
        'prayer' => "Your prayer request has been received. Our team is praying for you.",
        'visit' => "We can't wait to meet you! Look out for a welcome email with more details.",
        'group' => "Thanks for your interest! A group leader will contact you soon.",
        'serve' => "Thank you for wanting to serve! Our team will be in touch.",
        'event' => "You're registered! Check your email for event details.",
        'baptism' => "Amazing! We're so excited for your baptism journey. We'll be in touch soon."
    ];

    echo json_encode([
        'success' => true,
        'message' => $messages[$formType] ?? 'Form submitted successfully!'
    ]);
} else {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'We were unable to submit your form. Please try again or contact us directly.'
    ]);
}

/**
 * Validate form data based on type
 */
function validateForm(string $type, array $data): array
{
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
            if (empty($data['name'])) {
                $errors['name'] = 'Name is required';
            }
            if (empty($data['email']) || !filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
                $errors['email'] = 'Valid email is required';
            }
            if (empty($data['request'])) {
                $errors['request'] = 'Prayer request is required';
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
        case 'event':
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
