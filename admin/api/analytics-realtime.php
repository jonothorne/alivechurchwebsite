<?php
/**
 * Real-time Analytics API
 * Returns current visitor stats for auto-refresh
 */

require_once __DIR__ . '/../../includes/bootstrap.php';

// Check admin/editor auth
if (!$auth->check() || !in_array($current_user['role'], ['admin', 'editor'])) {
    http_response_code(403);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

require_once __DIR__ . '/../../includes/Analytics.php';

$analytics = new Analytics($pdo);
$stats = $analytics->getRealTimeStats();

header('Content-Type: application/json');
echo json_encode($stats);
