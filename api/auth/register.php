<?php
/**
 * Registration API Endpoint
 * Handles AJAX registration requests
 */
header('Content-Type: application/json');

require_once __DIR__ . '/../../includes/bootstrap.php';

// Only accept POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit;
}

// Check if already logged in
if ($auth->check()) {
    echo json_encode([
        'success' => true,
        'redirect' => '/my-studies',
        'message' => 'Already logged in'
    ]);
    exit;
}

// Get input (support both form data and JSON)
$input = $_POST;
if (empty($input)) {
    $json = file_get_contents('php://input');
    $input = json_decode($json, true) ?? [];
}

// Attempt registration
$result = $auth->register([
    'username' => trim($input['username'] ?? ''),
    'email' => trim($input['email'] ?? ''),
    'password' => $input['password'] ?? '',
    'password_confirm' => $input['password_confirm'] ?? '',
    'full_name' => trim($input['full_name'] ?? '')
]);

if ($result['success']) {
    echo json_encode([
        'success' => true,
        'redirect' => '/my-studies?welcome=1',
        'message' => 'Account created successfully'
    ]);
} else {
    http_response_code(400);

    // Format errors for the frontend
    $firstError = '';
    $firstField = '';
    $fieldErrors = [];

    foreach ($result['errors'] as $field => $error) {
        if ($field === 'general') {
            $firstError = $error;
        } else {
            $fieldErrors[$field] = $error;
            if (empty($firstError)) {
                $firstError = $error;
                $firstField = $field;
            }
        }
    }

    echo json_encode([
        'success' => false,
        'error' => $firstError,
        'field' => $firstField,
        'errors' => $fieldErrors
    ]);
}
