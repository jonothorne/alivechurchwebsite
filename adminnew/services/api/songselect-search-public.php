<?php
/**
 * SongSelect Public Search API
 *
 * Proxies search requests to CCLI SongSelect's public search API.
 * No authentication required - search results are publicly accessible.
 */

ob_start();
ini_set('display_errors', 0);
ini_set('html_errors', 0);
error_reporting(E_ALL);

header('Content-Type: application/json');

function json_response($data, $code = 200) {
    ob_end_clean();
    http_response_code($code);
    echo json_encode($data);
    exit;
}

try {
    require_once __DIR__ . '/../../../includes/Auth.php';
} catch (Exception $e) {
    json_response(['error' => 'Server configuration error'], 500);
}

// Check authentication
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    json_response(['error' => 'Authentication required'], 401);
}

$query = trim($_GET['q'] ?? '');

if (empty($query)) {
    json_response(['error' => 'Search query required'], 400);
}

// Call SongSelect's public search API
$ch = curl_init();

curl_setopt_array($ch, [
    CURLOPT_URL => 'https://songselect.ccli.com/api/GetSongSearchResults',
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => http_build_query([
        'search' => $query,
        'numPerPage' => 15,
        'page' => 1,
    ]),
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT => 15,
    CURLOPT_FOLLOWLOCATION => true,
    CURLOPT_ENCODING => 'gzip, deflate',
    CURLOPT_HTTPHEADER => [
        'User-Agent: Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
        'Accept: application/json, text/plain, */*',
        'Accept-Language: en-US,en;q=0.9',
        'Referer: https://songselect.ccli.com/search/results?search=' . urlencode($query),
        'Origin: https://songselect.ccli.com',
        'client-locale: en-US',
    ],
]);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$error = curl_error($ch);
curl_close($ch);

if ($error) {
    json_response(['error' => 'Failed to connect to SongSelect: ' . $error], 502);
}

if ($httpCode !== 200) {
    json_response(['error' => 'SongSelect returned an error (HTTP ' . $httpCode . ')'], 502);
}

$data = json_decode($response, true);

if (!$data || !isset($data['payload'])) {
    json_response(['error' => 'Invalid response from SongSelect'], 502);
}

$payload = $data['payload'];
$items = $payload['items'] ?? [];

// Transform results into our format
$results = [];
foreach ($items as $item) {
    $results[] = [
        'title' => $item['title'] ?? '',
        'artist' => implode(', ', array_slice($item['authors'] ?? [], 0, 3)),
        'authors' => implode(', ', $item['authors'] ?? []),
        'ccli_number' => $item['songNumber'] ?? '',
        'slug' => $item['slug'] ?? '',
        'has_chords' => ($item['productExists']['chords'] ?? false) ? true : false,
        'has_lyrics' => ($item['productExists']['lyrics'] ?? false) ? true : false,
    ];
}

json_response([
    'success' => true,
    'results' => $results,
    'total' => $payload['numSongs'] ?? count($results),
    'query' => $query,
]);
