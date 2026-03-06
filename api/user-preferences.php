<?php
/**
 * User Preferences API
 * Handles saving user preferences like theme, notifications, etc.
 */
require __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/db-config.php';
require_once __DIR__ . '/../includes/Auth.php';

header('Content-Type: application/json');

$pdo = getDbConnection();
$auth = new Auth($pdo);

// Require authentication
if (!$auth->check()) {
    http_response_code(401);
    echo json_encode(['error' => 'Authentication required']);
    exit;
}

$action = $_POST['action'] ?? $_GET['action'] ?? '';

switch ($action) {
    case 'update_preference':
        $key = $_POST['key'] ?? '';
        $value = $_POST['value'] ?? '';

        // Validate key
        $allowedKeys = ['theme', 'email_notifications', 'reading_reminders', 'font_size'];
        if (!in_array($key, $allowedKeys)) {
            echo json_encode(['error' => 'Invalid preference key']);
            exit;
        }

        // Get current preferences
        $stmt = $pdo->prepare("SELECT preferences FROM users WHERE id = ?");
        $stmt->execute([$auth->id()]);
        $row = $stmt->fetch();

        $preferences = $row['preferences'] ? json_decode($row['preferences'], true) : [];
        $preferences[$key] = $value;

        // Save updated preferences
        $stmt = $pdo->prepare("UPDATE users SET preferences = ? WHERE id = ?");
        $stmt->execute([json_encode($preferences), $auth->id()]);

        echo json_encode(['success' => true, 'preferences' => $preferences]);
        break;

    case 'get_preferences':
        $stmt = $pdo->prepare("SELECT preferences FROM users WHERE id = ?");
        $stmt->execute([$auth->id()]);
        $row = $stmt->fetch();

        $preferences = $row['preferences'] ? json_decode($row['preferences'], true) : [];
        echo json_encode(['preferences' => $preferences]);
        break;

    default:
        http_response_code(400);
        echo json_encode(['error' => 'Unknown action']);
}
