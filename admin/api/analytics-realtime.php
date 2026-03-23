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
require_once __DIR__ . '/../../includes/GeoIP.php';

$analytics = new Analytics($pdo);
$stats = $analytics->getRealTimeStats();

// Get database time for accurate "time ago" calculation
$dbTime = strtotime($pdo->query("SELECT NOW()")->fetchColumn());

// Get recent page views for activity feed
$recentViews = $analytics->getRecentPageViews(30);

// Format activity feed with time ago
$activity = [];
foreach ($recentViews as $view) {
    $timeAgo = $dbTime - strtotime($view['visited_at']);
    if ($timeAgo < 60) {
        $timeAgoText = 'Just now';
    } elseif ($timeAgo < 3600) {
        $timeAgoText = floor($timeAgo / 60) . 'm ago';
    } else {
        $timeAgoText = floor($timeAgo / 3600) . 'h ago';
    }

    $activity[] = [
        'page_url' => $view['page_url'],
        'device_type' => $view['device_type'],
        'city' => $view['city'],
        'country_code' => $view['country_code'],
        'country_flag' => $view['country_code'] ? GeoIP::getCountryFlag($view['country_code']) : '',
        'session_id' => $view['session_id'],
        'time_ago' => $timeAgoText
    ];
}

$stats['activity'] = $activity;

header('Content-Type: application/json');
echo json_encode($stats);
