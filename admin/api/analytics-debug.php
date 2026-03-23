<?php
/**
 * Analytics Debug API
 * Helps diagnose analytics issues
 */

require_once __DIR__ . '/../../includes/bootstrap.php';

// Check admin auth
if (!$auth->check() || $current_user['role'] !== 'admin') {
    http_response_code(403);
    echo json_encode(['error' => 'Unauthorized - admin only']);
    exit;
}

header('Content-Type: application/json');

$debug = [];

// Check if page_visits table exists
try {
    $result = $pdo->query("SHOW TABLES LIKE 'page_visits'")->fetch();
    $debug['table_exists'] = $result ? true : false;
} catch (Exception $e) {
    $debug['table_exists'] = false;
    $debug['table_error'] = $e->getMessage();
}

// Check recent visits count
if ($debug['table_exists']) {
    try {
        $count = $pdo->query("SELECT COUNT(*) FROM page_visits")->fetchColumn();
        $debug['total_visits'] = (int)$count;

        $recent = $pdo->query("SELECT COUNT(*) FROM page_visits WHERE visited_at >= DATE_SUB(NOW(), INTERVAL 1 HOUR)")->fetchColumn();
        $debug['visits_last_hour'] = (int)$recent;

        $lastVisit = $pdo->query("SELECT visited_at FROM page_visits ORDER BY visited_at DESC LIMIT 1")->fetchColumn();
        $debug['last_visit'] = $lastVisit ?: 'No visits recorded';

        // Server time
        $debug['server_time'] = date('Y-m-d H:i:s');
        $debug['db_time'] = $pdo->query("SELECT NOW()")->fetchColumn();
    } catch (Exception $e) {
        $debug['query_error'] = $e->getMessage();
    }
}

// Check data directory
$dataDir = __DIR__ . '/../../data';
$debug['data_dir_exists'] = is_dir($dataDir);
$debug['data_dir_writable'] = is_dir($dataDir) && is_writable($dataDir);

// Check batch file
$batchFile = $dataDir . '/analytics-batch.json';
$debug['batch_file_exists'] = file_exists($batchFile);
if (file_exists($batchFile)) {
    $data = json_decode(file_get_contents($batchFile), true);
    $debug['batch_visits_pending'] = count($data['visits'] ?? []);
}

// Git commit info
$gitHead = __DIR__ . '/../../.git/HEAD';
if (file_exists($gitHead)) {
    $debug['git_head'] = trim(file_get_contents($gitHead));
}

echo json_encode($debug, JSON_PRETTY_PRINT);
