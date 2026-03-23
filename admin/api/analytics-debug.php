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

// Test direct database insert
if (isset($_GET['test_insert'])) {
    try {
        $testSql = "INSERT INTO page_visits (page_url, page_title, session_id, ip_address, device_type, browser, is_new_visitor, visited_at)
                    VALUES (?, ?, ?, ?, ?, ?, ?, NOW())";
        $stmt = $pdo->prepare($testSql);
        $result = $stmt->execute([
            '/debug-test-' . time(),
            'Debug Test Visit',
            'debug-session-' . bin2hex(random_bytes(8)),
            $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1',
            'desktop',
            'Debug',
            0
        ]);
        $debug['test_insert'] = $result ? 'SUCCESS' : 'FAILED';
        $debug['test_insert_id'] = $pdo->lastInsertId();
    } catch (Exception $e) {
        $debug['test_insert'] = 'ERROR';
        $debug['test_insert_error'] = $e->getMessage();
    }
}

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

// Check Analytics.php version by looking for key code
$analyticsFile = __DIR__ . '/../../includes/Analytics.php';
if (file_exists($analyticsFile)) {
    $analyticsCode = file_get_contents($analyticsFile);
    $debug['analytics_has_17_placeholders'] = strpos($analyticsCode, '?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?') !== false;
    $debug['analytics_has_fallback'] = strpos($analyticsCode, 'writeVisitDirectly') !== false;
    $debug['analytics_has_url_skip'] = strpos($analyticsCode, 'shouldSkipUrl') !== false;
    $debug['analytics_batch_threshold'] = preg_match('/batchThreshold\s*=\s*(\d+)/', $analyticsCode, $m) ? (int)$m[1] : 'unknown';
}

echo json_encode($debug, JSON_PRETTY_PRINT);
