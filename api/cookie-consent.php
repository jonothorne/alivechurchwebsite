<?php
/**
 * Cookie Consent API
 * Handles saving cookie consent preferences
 * - For logged-in users: saves to database (user preferences)
 * - For guests: saves to session
 */
require_once __DIR__ . '/../includes/bootstrap.php';

header('Content-Type: application/json');

$action = $_POST['action'] ?? $_GET['action'] ?? '';

switch ($action) {
    case 'accept':
        $consent = [
            'accepted' => true,
            'timestamp' => date('Y-m-d H:i:s'),
            'necessary' => true,
            'analytics' => isset($_POST['analytics']) ? (bool)$_POST['analytics'] : true,
            'marketing' => isset($_POST['marketing']) ? (bool)$_POST['marketing'] : false
        ];

        if ($current_user) {
            // Logged-in user: save to database
            $stmt = $pdo->prepare("SELECT preferences FROM users WHERE id = ?");
            $stmt->execute([$current_user['id']]);
            $row = $stmt->fetch();

            $preferences = $row['preferences'] ? json_decode($row['preferences'], true) : [];
            $preferences['cookie_consent'] = $consent;

            $stmt = $pdo->prepare("UPDATE users SET preferences = ? WHERE id = ?");
            $stmt->execute([json_encode($preferences), $current_user['id']]);
        }

        // Always save to session (covers both logged-in and guest users)
        $_SESSION['cookie_consent'] = $consent;

        echo json_encode(['success' => true, 'consent' => $consent]);
        break;

    case 'decline':
        $consent = [
            'accepted' => false,
            'timestamp' => date('Y-m-d H:i:s'),
            'necessary' => true,
            'analytics' => false,
            'marketing' => false
        ];

        if ($current_user) {
            $stmt = $pdo->prepare("SELECT preferences FROM users WHERE id = ?");
            $stmt->execute([$current_user['id']]);
            $row = $stmt->fetch();

            $preferences = $row['preferences'] ? json_decode($row['preferences'], true) : [];
            $preferences['cookie_consent'] = $consent;

            $stmt = $pdo->prepare("UPDATE users SET preferences = ? WHERE id = ?");
            $stmt->execute([json_encode($preferences), $current_user['id']]);
        }

        $_SESSION['cookie_consent'] = $consent;

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

        // Fall back to session
        if (!$consent && isset($_SESSION['cookie_consent'])) {
            $consent = $_SESSION['cookie_consent'];
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
