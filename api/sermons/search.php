<?php
/**
 * Sermon Search API
 * Searches sermons by title, description, transcript, speaker
 */

require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../includes/db-config.php';
require_once __DIR__ . '/../../includes/SermonManager.php';

header('Content-Type: application/json');

$pdo = getDbConnection();
$sermonManager = new SermonManager($pdo);

$query = $_GET['q'] ?? '';
$speaker = $_GET['speaker'] ?? null;
$seriesId = $_GET['series_id'] ?? null;
$limit = min(50, max(1, intval($_GET['limit'] ?? 20)));
$offset = max(0, intval($_GET['offset'] ?? 0));

if (empty($query) && empty($speaker) && empty($seriesId)) {
    echo json_encode([
        'success' => true,
        'results' => [],
        'total' => 0,
        'message' => 'Please provide a search query, speaker, or series'
    ]);
    exit;
}

$filters = [
    'limit' => $limit,
    'offset' => $offset
];

if ($speaker) {
    $filters['speaker'] = $speaker;
}

if ($seriesId) {
    $filters['series_id'] = intval($seriesId);
}

try {
    $results = $sermonManager->searchSermons($query ?: '', $filters);

    // Format results for API
    $formattedResults = array_map(function($sermon) {
        return [
            'id' => $sermon['id'],
            'title' => $sermon['title'],
            'slug' => $sermon['slug'],
            'url' => '/sermon/' . $sermon['slug'],
            'speaker' => $sermon['speaker'],
            'date' => $sermon['sermon_date'],
            'date_formatted' => $sermon['sermon_date'] ? date('M j, Y', strtotime($sermon['sermon_date'])) : null,
            'duration' => $sermon['length'],
            'series_title' => $sermon['series_title'] ?? null,
            'series_slug' => $sermon['series_slug'] ?? null,
            'thumbnail_url' => $sermon['thumbnail_url'] ?: ($sermon['youtube_video_id'] ? "https://img.youtube.com/vi/{$sermon['youtube_video_id']}/mqdefault.jpg" : null),
            'youtube_video_id' => $sermon['youtube_video_id'] ?? null,
            'description' => $sermon['description'] ? substr($sermon['description'], 0, 200) : null
        ];
    }, $results);

    echo json_encode([
        'success' => true,
        'query' => $query,
        'results' => $formattedResults,
        'total' => count($results),
        'limit' => $limit,
        'offset' => $offset
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
