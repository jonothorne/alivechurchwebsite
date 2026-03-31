<?php
/**
 * People API - AJAX endpoints for People module
 *
 * Handles addresses, phone numbers, and other person-related CRUD operations.
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
    require_once __DIR__ . '/../../includes/Auth.php';
    require_once __DIR__ . '/../../includes/db-config.php';
    require_once __DIR__ . '/../../includes/services/PeopleService.php';
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

// Get database connection
try {
    $pdo = getDbConnection();
    $peopleService = new PeopleService($pdo);
} catch (Exception $e) {
    json_response(['error' => 'Database connection failed'], 500);
}

// Parse JSON body for POST/PUT/DELETE
$input = [];
if (in_array($_SERVER['REQUEST_METHOD'], ['POST', 'PUT', 'DELETE'])) {
    $rawInput = file_get_contents('php://input');
    if (!empty($rawInput)) {
        $input = json_decode($rawInput, true) ?? [];
    }
    // Also merge with POST data for form submissions
    $input = array_merge($_POST, $input);
}

// Verify CSRF for modifying requests
if (in_array($_SERVER['REQUEST_METHOD'], ['POST', 'PUT', 'DELETE'])) {
    $csrf = $input['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    if (!function_exists('verify_csrf_token') || !verify_csrf_token($csrf)) {
        json_response(['error' => 'Invalid CSRF token'], 403);
    }
}

// Route based on action
$action = $_GET['action'] ?? $input['action'] ?? '';

switch ($action) {
    // =========================================================================
    // ADDRESS OPERATIONS
    // =========================================================================

    case 'get_address':
        $id = (int)($_GET['id'] ?? 0);
        if (!$id) {
            json_response(['error' => 'Address ID required'], 400);
        }

        $addressRepo = new AddressRepository($pdo);
        $address = $addressRepo->find($id);

        if (!$address) {
            json_response(['error' => 'Address not found'], 404);
        }

        json_response(['success' => true, 'data' => $address]);
        break;

    case 'add_address':
        $userId = (int)($input['user_id'] ?? 0);
        if (!$userId) {
            json_response(['error' => 'User ID required'], 400);
        }

        $data = [
            'user_id' => $userId,
            'street_line_1' => trim($input['street_line_1'] ?? ''),
            'street_line_2' => trim($input['street_line_2'] ?? '') ?: null,
            'city' => trim($input['city'] ?? ''),
            'county' => trim($input['county'] ?? '') ?: null,
            'postcode' => trim($input['postcode'] ?? ''),
            'country' => trim($input['country'] ?? 'United Kingdom'),
            'address_type' => $input['address_type'] ?? 'home',
            'is_primary' => !empty($input['is_primary']) ? 1 : 0,
        ];

        // Validate required fields
        if (empty($data['street_line_1']) || empty($data['city']) || empty($data['postcode'])) {
            json_response(['error' => 'Street, city, and postcode are required'], 400);
        }

        $result = $peopleService->addAddress($data);

        if ($result['success']) {
            // Return updated addresses list
            $addressRepo = new AddressRepository($pdo);
            $addresses = $addressRepo->getForUser($userId);
            json_response(['success' => true, 'address_id' => $result['address_id'], 'addresses' => $addresses]);
        } else {
            json_response(['error' => $result['error']], 400);
        }
        break;

    case 'update_address':
        $id = (int)($input['id'] ?? 0);
        if (!$id) {
            json_response(['error' => 'Address ID required'], 400);
        }

        $data = [
            'street_line_1' => trim($input['street_line_1'] ?? ''),
            'street_line_2' => trim($input['street_line_2'] ?? '') ?: null,
            'city' => trim($input['city'] ?? ''),
            'county' => trim($input['county'] ?? '') ?: null,
            'postcode' => trim($input['postcode'] ?? ''),
            'country' => trim($input['country'] ?? 'United Kingdom'),
            'address_type' => $input['address_type'] ?? 'home',
            'is_primary' => !empty($input['is_primary']) ? 1 : 0,
        ];

        $result = $peopleService->updateAddress($id, $data);

        if ($result['success']) {
            // Get the address to find the user_id
            $addressRepo = new AddressRepository($pdo);
            $address = $addressRepo->find($id);
            $addresses = $address ? $addressRepo->getForUser($address['user_id']) : [];
            json_response(['success' => true, 'addresses' => $addresses]);
        } else {
            json_response(['error' => $result['error']], 400);
        }
        break;

    case 'delete_address':
        $id = (int)($input['id'] ?? 0);
        if (!$id) {
            json_response(['error' => 'Address ID required'], 400);
        }

        // Get user_id before deleting
        $addressRepo = new AddressRepository($pdo);
        $address = $addressRepo->find($id);
        $userId = $address['user_id'] ?? null;

        $result = $peopleService->deleteAddress($id);

        if ($result['success']) {
            $addresses = $userId ? $addressRepo->getForUser($userId) : [];
            json_response(['success' => true, 'addresses' => $addresses]);
        } else {
            json_response(['error' => $result['error']], 400);
        }
        break;

    case 'set_primary_address':
        $id = (int)($input['id'] ?? 0);
        if (!$id) {
            json_response(['error' => 'Address ID required'], 400);
        }

        $addressRepo = new AddressRepository($pdo);
        $address = $addressRepo->find($id);

        if (!$address) {
            json_response(['error' => 'Address not found'], 404);
        }

        $addressRepo->setAsPrimary($id);
        $addresses = $addressRepo->getForUser($address['user_id']);

        json_response(['success' => true, 'addresses' => $addresses]);
        break;

    // =========================================================================
    // PHONE NUMBER OPERATIONS
    // =========================================================================

    case 'get_phone':
        $id = (int)($_GET['id'] ?? 0);
        if (!$id) {
            json_response(['error' => 'Phone ID required'], 400);
        }

        $phoneRepo = new PhoneNumberRepository($pdo);
        $phone = $phoneRepo->find($id);

        if (!$phone) {
            json_response(['error' => 'Phone number not found'], 404);
        }

        json_response(['success' => true, 'data' => $phone]);
        break;

    case 'add_phone':
        $userId = (int)($input['user_id'] ?? 0);
        if (!$userId) {
            json_response(['error' => 'User ID required'], 400);
        }

        $data = [
            'number' => trim($input['number'] ?? ''),
            'location_type' => $input['location_type'] ?? 'mobile',
            'is_primary' => !empty($input['is_primary']) ? 1 : 0,
            'can_receive_sms' => !empty($input['can_receive_sms']) ? 1 : 0,
        ];

        if (empty($data['number'])) {
            json_response(['error' => 'Phone number is required'], 400);
        }

        $result = $peopleService->addPhoneNumber($userId, $data);

        if ($result['success']) {
            $phoneRepo = new PhoneNumberRepository($pdo);
            $phones = $phoneRepo->getForUser($userId);
            json_response(['success' => true, 'phone_id' => $result['phone_id'], 'phones' => $phones]);
        } else {
            json_response(['error' => $result['error']], 400);
        }
        break;

    case 'update_phone':
        $id = (int)($input['id'] ?? 0);
        if (!$id) {
            json_response(['error' => 'Phone ID required'], 400);
        }

        $data = [
            'number' => trim($input['number'] ?? ''),
            'location_type' => $input['location_type'] ?? 'mobile',
            'is_primary' => !empty($input['is_primary']) ? 1 : 0,
            'can_receive_sms' => !empty($input['can_receive_sms']) ? 1 : 0,
        ];

        $result = $peopleService->updatePhoneNumber($id, $data);

        if ($result['success']) {
            $phoneRepo = new PhoneNumberRepository($pdo);
            $phone = $phoneRepo->find($id);
            $phones = $phone ? $phoneRepo->getForUser($phone['user_id']) : [];
            json_response(['success' => true, 'phones' => $phones]);
        } else {
            json_response(['error' => $result['error']], 400);
        }
        break;

    case 'delete_phone':
        $id = (int)($input['id'] ?? 0);
        if (!$id) {
            json_response(['error' => 'Phone ID required'], 400);
        }

        $phoneRepo = new PhoneNumberRepository($pdo);
        $phone = $phoneRepo->find($id);
        $userId = $phone['user_id'] ?? null;

        $result = $peopleService->deletePhoneNumber($id);

        if ($result['success']) {
            $phones = $userId ? $phoneRepo->getForUser($userId) : [];
            json_response(['success' => true, 'phones' => $phones]);
        } else {
            json_response(['error' => $result['error']], 400);
        }
        break;

    case 'set_primary_phone':
        $id = (int)($input['id'] ?? 0);
        if (!$id) {
            json_response(['error' => 'Phone ID required'], 400);
        }

        $phoneRepo = new PhoneNumberRepository($pdo);
        $phone = $phoneRepo->find($id);

        if (!$phone) {
            json_response(['error' => 'Phone number not found'], 404);
        }

        $phoneRepo->setAsPrimary($id);
        $phones = $phoneRepo->getForUser($phone['user_id']);

        json_response(['success' => true, 'phones' => $phones]);
        break;

    // =========================================================================
    // SEARCH / AUTOCOMPLETE
    // =========================================================================

    case 'search':
        $query = trim($_GET['q'] ?? '');
        $limit = min((int)($_GET['limit'] ?? 10), 50);

        if (strlen($query) < 2) {
            json_response(['success' => true, 'data' => []]);
        }

        $results = $peopleService->searchPeople($query, $limit);
        json_response(['success' => true, 'data' => $results]);
        break;

    default:
        json_response(['error' => 'Invalid action'], 400);
}
