<?php
/**
 * Cookie Consent API
 * Handles saving cookie consent preferences
 * - For logged-in users: saves to database (user preferences)
 * - For all users: saves to cookie (persists across sessions)
 */

// Set cookie BEFORE any output
$action = $_POST['action'] ?? $_GET['action'] ?? '';

// Cookie settings - 1 year expiry
$cookieExpiry = time() + (365 * 24 * 60 * 60);

// Set cookie immediately if accepting/declining (before any output)
if ($action === 'accept' || $action === 'decline') {
    $consent = [
        'accepted' => ($action === 'accept'),
        'timestamp' => date('Y-m-d H:i:s'),
        'necessary' => true,
        'analytics' => ($action === 'accept'),
        'marketing' => false
    ];
    setcookie('cookie_consent', json_encode($consent), [
        'expires' => $cookieExpiry,
        'path' => '/',
        'httponly' => false,
        'samesite' => 'Lax'
    ]);
}

require_once __DIR__ . '/../includes/bootstrap.php';

header('Content-Type: application/json');

switch ($action) {
    case 'accept':
    case 'decline':
        // Cookie already set at top of file
        // Also save to database for logged-in users
        if (isset($current_user) && $current_user) {
            $stmt = $pdo->prepare("SELECT preferences FROM users WHERE id = ?");
            $stmt->execute([$current_user['id']]);
            $row = $stmt->fetch();

            $preferences = $row['preferences'] ? json_decode($row['preferences'], true) : [];
            $preferences['cookie_consent'] = $consent;

            $stmt = $pdo->prepare("UPDATE users SET preferences = ? WHERE id = ?");
            $stmt->execute([json_encode($preferences), $current_user['id']]);
        }

        echo json_encode(['success' => true, 'consent' => $consent]);
        break;

    case 'check':
        // Check if user has already given consent
        $consent = null;

        if ($current_user) {
            // Check database for logged-in users
            $stmt = $pdo->prepare("SELECT preferences FROM users WHERE id = ?");
            $stmt->execute([$current_user['id']]);
            $row = $stmt->fetch();

            if ($row['preferences']) {
                $preferences = json_decode($row['preferences'], true);
                $consent = $preferences['cookie_consent'] ?? null;
            }
        }

        // Fall back to cookie
        if (!$consent && isset($_COOKIE['cookie_consent'])) {
            $consent = json_decode($_COOKIE['cookie_consent'], true);
        }

        echo json_encode([
            'has_consent' => $consent !== null,
            'consent' => $consent
        ]);
        break;

    default:
        http_response_code(400);
        echo json_encode(['error' => 'Unknown action']);
}
