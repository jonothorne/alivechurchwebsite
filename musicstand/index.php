<?php
/**
 * Music Stand - Chord Chart Viewer for Worship Teams
 *
 * A PWA app for displaying chord charts during services
 * Accessible at /musicstand
 */

require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/Auth.php';
require_once __DIR__ . '/../includes/db-config.php';

// Get database connection
$pdo = getDbConnection();

// Check authentication
$auth = new Auth($pdo);
$user = $auth->user();

// Redirect to login if not authenticated
if (!$auth->check()) {
    $returnUrl = urlencode('/musicstand' . ($_SERVER['REQUEST_URI'] !== '/musicstand' && $_SERVER['REQUEST_URI'] !== '/musicstand/' ? $_SERVER['REQUEST_URI'] : ''));
    header('Location: /login?redirect=' . $returnUrl);
    exit;
}

// Check if user is part of worship team
$stmt = $pdo->prepare("
    SELECT stm.*, st.name as team_name
    FROM service_team_members stm
    JOIN service_teams st ON stm.team_id = st.id
    WHERE stm.user_id = ?
    AND stm.is_active = 1
    AND st.name IN ('worship-team', 'Worship Team', 'worship_team')
    LIMIT 1
");
$stmt->execute([$user['id']]);
$worshipTeamMember = $stmt->fetch(PDO::FETCH_ASSOC);

// Also check if user has admin/editor role (they get access regardless)
$hasAccess = $worshipTeamMember || in_array($user['role'], ['admin', 'editor']);

if (!$hasAccess) {
    // Check if user has ANY service assignments as a fallback
    $stmt = $pdo->prepare("
        SELECT COUNT(*) FROM service_assignments
        WHERE user_id = ?
    ");
    $stmt->execute([$user['id']]);
    $hasAssignments = $stmt->fetchColumn() > 0;

    if (!$hasAssignments) {
        http_response_code(403);
        include __DIR__ . '/access-denied.php';
        exit;
    }
}

// Parse request
$requestUri = $_SERVER['REQUEST_URI'];
$basePath = '/musicstand';
$path = substr($requestUri, strlen($basePath));
$path = strtok($path, '?'); // Remove query string
$path = $path ?: '/';

// Route to appropriate page
switch (true) {
    case $path === '/' || $path === '':
        include __DIR__ . '/pages/home.php';
        break;

    case preg_match('#^/service/(\d+)$#', $path, $matches):
        $_GET['service_id'] = $matches[1];
        include __DIR__ . '/pages/service.php';
        break;

    case $path === '/library':
        include __DIR__ . '/pages/library.php';
        break;

    case preg_match('#^/song/(\d+)$#', $path, $matches):
        $_GET['song_id'] = $matches[1];
        include __DIR__ . '/pages/song.php';
        break;

    case $path === '/settings':
        include __DIR__ . '/pages/settings.php';
        break;

    default:
        http_response_code(404);
        echo '<h1>Page Not Found</h1>';
        break;
}
