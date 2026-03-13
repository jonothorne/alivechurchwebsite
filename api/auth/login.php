<?php
/**
 * Login API Endpoint
 * Handles AJAX login requests
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

$identifier = trim($input['identifier'] ?? $input['email'] ?? '');
$password = $input['password'] ?? '';
$remember = isset($input['remember']) && $input['remember'];
$redirect = $input['redirect'] ?? '/my-studies';

// Validate input
if (empty($identifier)) {
    echo json_encode(['success' => false, 'error' => 'Email or username is required', 'field' => 'identifier']);
    exit;
}

if (empty($password)) {
    echo json_encode(['success' => false, 'error' => 'Password is required', 'field' => 'password']);
    exit;
}

// Attempt login
$result = $auth->login($identifier, $password, $remember);

if ($result['success']) {
    // Sanitize redirect URL
    if (strpos($redirect, '/') !== 0) {
        $redirect = '/my-studies';
    }

    // If admin/editor trying to access admin area, allow it
    if (strpos($redirect, '/admin') === 0 && !in_array($result['user']['role'], ['admin', 'editor'])) {
        $redirect = '/my-studies';
    }

    echo json_encode([
        'success' => true,
        'redirect' => $redirect,
        'message' => 'Login successful',
        'user' => [
            'id' => $result['user']['id'],
            'username' => $result['user']['username'],
            'full_name' => $result['user']['full_name']
        ]
    ]);
} else {
    // Check if rate limited
    $statusCode = isset($result['rate_limited']) ? 429 : 401;
    http_response_code($statusCode);

    echo json_encode([
        'success' => false,
        'error' => $result['error'],
        'rate_limited' => $result['rate_limited'] ?? false,
        'retry_after' => $result['retry_after'] ?? null
    ]);
}
