<?php
/**
 * YouTube Fetch API
 * Fetches video metadata and transcript from YouTube
 */

require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../includes/db-config.php';
require_once __DIR__ . '/../../includes/Auth.php';
require_once __DIR__ . '/../../includes/SermonManager.php';

header('Content-Type: application/json');

// Check authentication
session_start();
if (empty($_SESSION['admin_user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Authentication required']);
    exit;
}

// Verify CSRF token
$csrfToken = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? $_POST['csrf_token'] ?? '';
if (!verify_csrf_token($csrfToken)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Invalid security token']);
    exit;
}

// Get video ID from request
$videoId = $_POST['video_id'] ?? $_GET['video_id'] ?? '';

if (empty($videoId)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Video ID is required']);
    exit;
}

// Extract video ID if full URL was provided
if (preg_match('/(?:youtube\.com\/(?:watch\?v=|embed\/|live\/|v\/)|youtu\.be\/)([a-zA-Z0-9_-]{11})/', $videoId, $matches)) {
    $videoId = $matches[1];
} elseif (preg_match('/[?&]v=([a-zA-Z0-9_-]{11})/', $videoId, $matches)) {
    $videoId = $matches[1];
}

// Validate video ID format
if (!preg_match('/^[a-zA-Z0-9_-]{11}$/', $videoId)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Invalid YouTube video ID format']);
    exit;
}

$pdo = getDbConnection();
$sermonManager = new SermonManager($pdo);

$fetchTranscript = isset($_POST['fetch_transcript']) ? (bool)$_POST['fetch_transcript'] : true;

try {
    // Fetch video data
    $videoData = $sermonManager->fetchYouTubeData($videoId);

    $response = [
        'success' => true,
        'data' => [
            'video_id' => $videoId,
            'title' => $videoData['title'],
            'description' => $videoData['description'],
            'thumbnail_url' => $videoData['thumbnail_url'],
            'duration_seconds' => $videoData['duration_seconds'],
            'duration_formatted' => $videoData['duration_formatted'],
            'published_at' => $videoData['published_at'],
            'channel' => $videoData['channel_title']
        ]
    ];

    // Optionally fetch transcript
    if ($fetchTranscript) {
        $transcript = $sermonManager->fetchTranscript($videoId);
        $response['data']['transcript'] = $transcript;
        $response['data']['transcript_available'] = !empty($transcript);
    }

    echo json_encode($response);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
