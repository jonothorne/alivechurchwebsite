<?php
/**
 * SongSelect Cookie Sync API
 *
 * Receives cookies from the Chrome extension and saves them encrypted.
 * Authenticated via API key stored in songselect_config.
 */

ob_start();
ini_set('display_errors', 0);
ini_set('html_errors', 0);
error_reporting(E_ALL);

header('Content-Type: application/json');

// Allow CORS from Chrome extension
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, X-API-Key');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

function json_response($data, $code = 200) {
    ob_end_clean();
    http_response_code($code);
    echo json_encode($data);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(['error' => 'POST required'], 405);
}

try {
    require_once __DIR__ . '/../../../includes/db-config.php';
    require_once __DIR__ . '/../../../includes/services/SongSelectAPI.php';
} catch (Exception $e) {
    json_response(['error' => 'Server configuration error'], 500);
}

// Read JSON body
$input = json_decode(file_get_contents('php://input'), true);
if (!$input) {
    json_response(['error' => 'Invalid JSON body'], 400);
}

$apiKey = $input['api_key'] ?? ($_SERVER['HTTP_X_API_KEY'] ?? '');
$cookies = $input['cookies'] ?? '';

if (empty($apiKey)) {
    json_response(['error' => 'API key required'], 401);
}

if (empty($cookies)) {
    json_response(['error' => 'No cookies provided'], 400);
}

// Validate API key
$pdo = getDbConnection();
$stmt = $pdo->prepare("SELECT api_key FROM songselect_config WHERE api_key = ? AND api_key IS NOT NULL LIMIT 1");
$stmt->execute([$apiKey]);
$config = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$config) {
    json_response(['error' => 'Invalid API key'], 401);
}

// Ensure cookies contain the auth token
if (strpos($cookies, 'CCLI_JWT_AUTH') === false) {
    json_response(['error' => 'Cookies missing CCLI_JWT_AUTH token'], 400);
}

// Save cookies
SongSelectAPI::saveCookies($pdo, $cookies);

// Verify they work
$api = new SongSelectAPI($pdo);
if ($api->isAuthenticated()) {
    $profile = $api->getProfile();
    json_response([
        'success' => true,
        'message' => 'Cookies synced successfully',
        'user' => $profile['name'] ?? 'Unknown',
        'organization' => $profile['organizationName'] ?? '',
    ]);
} else {
    json_response([
        'success' => false,
        'message' => 'Cookies saved but authentication failed — they may have expired',
    ]);
}
