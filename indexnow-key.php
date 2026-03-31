<?php
/**
 * IndexNow Key Verification
 * Serves the API key as a text file for IndexNow protocol verification.
 */

$requestedKey = $_GET['key'] ?? '';
if (!preg_match('/^[a-f0-9]{32}$/', $requestedKey)) {
    http_response_code(404);
    exit;
}

require_once __DIR__ . '/includes/db-config.php';

try {
    $pdo = getDbConnection();
    $stmt = $pdo->prepare("SELECT config_value FROM seo_indexing_config WHERE config_key = 'indexnow_api_key'");
    $stmt->execute();
    $storedKey = $stmt->fetchColumn();

    if ($storedKey && $storedKey === $requestedKey) {
        header('Content-Type: text/plain');
        echo $storedKey;
        exit;
    }
} catch (Exception $e) {}

http_response_code(404);
