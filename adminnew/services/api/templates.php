<?php
/**
 * Service Templates API
 * Handle template operations: create, get, delete, apply
 */

require_once __DIR__ . '/../../../includes/Auth.php';
require_once __DIR__ . '/../../../includes/db-config.php';

// Check authentication
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

header('Content-Type: application/json');

if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

$pdo = getDbConnection();

// Get action from request
$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    $action = $_GET['action'] ?? '';
    $params = $_GET;
} else {
    $input = json_decode(file_get_contents('php://input'), true) ?? $_POST;
    $action = $input['action'] ?? '';
    $params = $input;
}

try {
    switch ($action) {
        case 'save':
            saveServiceAsTemplate($pdo, $params);
            break;

        case 'get':
            getTemplate($pdo, $params);
            break;

        case 'delete':
            deleteTemplate($pdo, $params);
            break;

        case 'apply':
            applyTemplateToService($pdo, $params);
            break;

        case 'duplicate-last':
            duplicateLastWeeksService($pdo, $params);
            break;

        case 'duplicate':
            duplicateService($pdo, $params);
            break;

        default:
            throw new Exception('Invalid action');
    }
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}

/**
 * Save a service as a template
 */
function saveServiceAsTemplate(PDO $pdo, array $params): void
{
    $serviceId = (int)($params['service_id'] ?? 0);
    $templateName = trim($params['template_name'] ?? '');
    $templateDescription = trim($params['template_description'] ?? '');

    if (!$serviceId) {
        throw new Exception('Service ID is required');
    }

    if (!$templateName) {
        throw new Exception('Template name is required');
    }

    // Get service details
    $serviceStmt = $pdo->prepare("SELECT service_type_id FROM services WHERE id = ?");
    $serviceStmt->execute([$serviceId]);
    $service = $serviceStmt->fetch();

    if (!$service) {
        throw new Exception('Service not found');
    }

    $userId = $_SESSION['user_id'] ?? null;

    // Create template
    $templateStmt = $pdo->prepare("
        INSERT INTO service_templates (name, description, service_type_id, created_by, created_at, updated_at)
        VALUES (?, ?, ?, ?, NOW(), NOW())
    ");
    $templateStmt->execute([
        $templateName,
        $templateDescription ?: null,
        $service['service_type_id'],
        $userId
    ]);

    $templateId = $pdo->lastInsertId();

    // Copy service items
    $itemsStmt = $pdo->prepare("
        SELECT item_type, song_id, title, duration_minutes, notes, position
        FROM service_items
        WHERE service_id = ?
        ORDER BY position
    ");
    $itemsStmt->execute([$serviceId]);
    $items = $itemsStmt->fetchAll();

    $insertItemStmt = $pdo->prepare("
        INSERT INTO service_template_items (template_id, item_type, song_id, title, duration_minutes, notes, position, created_at)
        VALUES (?, ?, ?, ?, ?, ?, ?, NOW())
    ");

    foreach ($items as $item) {
        $insertItemStmt->execute([
            $templateId,
            $item['item_type'],
            $item['song_id'],
            $item['title'],
            $item['duration_minutes'],
            $item['notes'],
            $item['position']
        ]);
    }

    // Copy rota roles (count how many of each role are needed)
    $rotaStmt = $pdo->prepare("
        SELECT role_id, COUNT(*) as quantity
        FROM service_rota
        WHERE service_id = ?
        GROUP BY role_id
        ORDER BY MIN(sort_order)
    ");
    $rotaStmt->execute([$serviceId]);
    $roles = $rotaStmt->fetchAll();

    $insertRoleStmt = $pdo->prepare("
        INSERT INTO service_template_roles (template_id, role_id, quantity, position, created_at)
        VALUES (?, ?, ?, ?, NOW())
    ");

    foreach ($roles as $index => $role) {
        $insertRoleStmt->execute([
            $templateId,
            $role['role_id'],
            $role['quantity'],
            $index
        ]);
    }

    echo json_encode([
        'success' => true,
        'message' => 'Template saved successfully!',
        'template_id' => $templateId
    ]);
}

/**
 * Get template details
 */
function getTemplate(PDO $pdo, array $params): void
{
    $templateId = (int)($params['template_id'] ?? 0);

    if (!$templateId) {
        throw new Exception('Template ID is required');
    }

    // Get template info
    $templateStmt = $pdo->prepare("
        SELECT st.*, stype.name as type_name, stype.color as type_color
        FROM service_templates st
        JOIN service_types stype ON st.service_type_id = stype.id
        WHERE st.id = ? AND st.is_active = 1
    ");
    $templateStmt->execute([$templateId]);
    $template = $templateStmt->fetch(PDO::FETCH_ASSOC);

    if (!$template) {
        throw new Exception('Template not found');
    }

    // Get template items
    $itemsStmt = $pdo->prepare("
        SELECT sti.*, s.title as song_title, s.artist as song_artist
        FROM service_template_items sti
        LEFT JOIN songs s ON sti.song_id = s.id
        WHERE sti.template_id = ?
        ORDER BY sti.position
    ");
    $itemsStmt->execute([$templateId]);
    $items = $itemsStmt->fetchAll(PDO::FETCH_ASSOC);

    // Format items for display
    foreach ($items as &$item) {
        if ($item['song_id'] && $item['song_title']) {
            $item['title'] = $item['song_title'];
            if ($item['song_artist']) {
                $item['title'] .= ' - ' . $item['song_artist'];
            }
        }
    }

    // Get template roles
    $rolesStmt = $pdo->prepare("
        SELECT str.*, r.name as role_name, t.name as team_name, t.color as team_color
        FROM service_template_roles str
        JOIN service_roles r ON str.role_id = r.id
        JOIN service_teams t ON r.team_id = t.id
        WHERE str.template_id = ?
        ORDER BY str.position
    ");
    $rolesStmt->execute([$templateId]);
    $roles = $rolesStmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'success' => true,
        'template' => $template,
        'items' => $items,
        'roles' => $roles
    ]);
}

/**
 * Delete a template
 */
function deleteTemplate(PDO $pdo, array $params): void
{
    $templateId = (int)($params['template_id'] ?? 0);

    if (!$templateId) {
        throw new Exception('Template ID is required');
    }

    // Soft delete
    $stmt = $pdo->prepare("
        UPDATE service_templates
        SET is_active = 0, updated_at = NOW()
        WHERE id = ?
    ");
    $stmt->execute([$templateId]);

    echo json_encode([
        'success' => true,
        'message' => 'Template deleted successfully'
    ]);
}

/**
 * Apply a template to a service
 */
function applyTemplateToService(PDO $pdo, array $params): void
{
    $serviceId = (int)($params['service_id'] ?? 0);
    $templateId = (int)($params['template_id'] ?? 0);

    if (!$serviceId || !$templateId) {
        throw new Exception('Service ID and Template ID are required');
    }

    // Get template items
    $itemsStmt = $pdo->prepare("
        SELECT item_type, song_id, title, duration_minutes, notes, position
        FROM service_template_items
        WHERE template_id = ?
        ORDER BY position
    ");
    $itemsStmt->execute([$templateId]);
    $items = $itemsStmt->fetchAll();

    // Insert items into service
    $insertItemStmt = $pdo->prepare("
        INSERT INTO service_items (service_id, item_type, song_id, title, duration_minutes, notes, position, created_at, updated_at)
        VALUES (?, ?, ?, ?, ?, ?, ?, NOW(), NOW())
    ");

    foreach ($items as $item) {
        $insertItemStmt->execute([
            $serviceId,
            $item['item_type'],
            $item['song_id'],
            $item['title'],
            $item['duration_minutes'],
            $item['notes'],
            $item['position']
        ]);
    }

    // Get template roles
    $rolesStmt = $pdo->prepare("
        SELECT role_id, quantity, position
        FROM service_template_roles
        WHERE template_id = ?
        ORDER BY position
    ");
    $rolesStmt->execute([$templateId]);
    $roles = $rolesStmt->fetchAll();

    // Insert roles into service rota
    $insertRotaStmt = $pdo->prepare("
        INSERT INTO service_rota (service_id, role_id, status, sort_order, created_at, updated_at)
        VALUES (?, ?, 'unassigned', ?, NOW(), NOW())
    ");

    $sortOrder = 0;
    foreach ($roles as $role) {
        for ($i = 0; $i < $role['quantity']; $i++) {
            $insertRotaStmt->execute([
                $serviceId,
                $role['role_id'],
                $sortOrder++
            ]);
        }
    }

    echo json_encode([
        'success' => true,
        'message' => 'Template applied successfully!'
    ]);
}

/**
 * Duplicate last week's service
 */
function duplicateLastWeeksService(PDO $pdo, array $params): void
{
    $serviceTypeId = (int)($params['service_type_id'] ?? 0);
    $newServiceDate = $params['service_date'] ?? '';
    $newStartTime = $params['start_time'] ?? '';

    if (!$serviceTypeId || !$newServiceDate || !$newStartTime) {
        throw new Exception('Service type, date, and time are required');
    }

    // Find the most recent service of this type
    $lastServiceStmt = $pdo->prepare("
        SELECT id, service_date, start_time, end_time, title
        FROM services
        WHERE service_type_id = ?
        AND service_date < ?
        ORDER BY service_date DESC
        LIMIT 1
    ");
    $lastServiceStmt->execute([$serviceTypeId, $newServiceDate]);
    $lastService = $lastServiceStmt->fetch();

    if (!$lastService) {
        throw new Exception('No previous service found to duplicate');
    }

    $userId = $_SESSION['user_id'] ?? null;

    // Create new service
    $createServiceStmt = $pdo->prepare("
        INSERT INTO services (service_type_id, service_date, start_time, end_time, title, status, created_by, created_at, updated_at)
        VALUES (?, ?, ?, ?, ?, 'planned', ?, NOW(), NOW())
    ");
    $createServiceStmt->execute([
        $serviceTypeId,
        $newServiceDate,
        $newStartTime,
        $lastService['end_time'],
        $lastService['title'],
        $userId
    ]);

    $newServiceId = $pdo->lastInsertId();

    // Copy service items
    $copyItemsStmt = $pdo->prepare("
        INSERT INTO service_items (service_id, item_type, song_id, song_key, title, duration_minutes, notes, position, created_at, updated_at)
        SELECT ?, item_type, song_id, song_key, title, duration_minutes, notes, position, NOW(), NOW()
        FROM service_items
        WHERE service_id = ?
    ");
    $copyItemsStmt->execute([$newServiceId, $lastService['id']]);

    // Copy rota (without member assignments)
    $copyRotaStmt = $pdo->prepare("
        INSERT INTO service_rota (service_id, role_id, status, sort_order, created_at, updated_at)
        SELECT ?, role_id, 'unassigned', sort_order, NOW(), NOW()
        FROM service_rota
        WHERE service_id = ?
    ");
    $copyRotaStmt->execute([$newServiceId, $lastService['id']]);

    echo json_encode([
        'success' => true,
        'message' => 'Service duplicated successfully!',
        'service_id' => $newServiceId,
        'redirect_url' => "/adminnew/services/plan/{$newServiceId}"
    ]);
}

/**
 * Duplicate a specific service
 */
function duplicateService(PDO $pdo, array $params): void
{
    $serviceId = (int)($params['service_id'] ?? 0);

    if (!$serviceId) {
        throw new Exception('Service ID is required');
    }

    // Get the original service
    $serviceStmt = $pdo->prepare("
        SELECT service_type_id, start_time, end_time, title, description, location
        FROM services
        WHERE id = ?
    ");
    $serviceStmt->execute([$serviceId]);
    $service = $serviceStmt->fetch();

    if (!$service) {
        throw new Exception('Service not found');
    }

    $userId = $_SESSION['user_id'] ?? null;

    // Create new service with date one week later
    $newDate = date('Y-m-d', strtotime('+7 days'));

    $createServiceStmt = $pdo->prepare("
        INSERT INTO services (service_type_id, service_date, start_time, end_time, title, description, location, status, created_by, created_at, updated_at)
        VALUES (?, ?, ?, ?, ?, ?, ?, 'planned', ?, NOW(), NOW())
    ");
    $createServiceStmt->execute([
        $service['service_type_id'],
        $newDate,
        $service['start_time'],
        $service['end_time'],
        $service['title'] ? $service['title'] . ' (Copy)' : null,
        $service['description'],
        $service['location'],
        $userId
    ]);

    $newServiceId = $pdo->lastInsertId();

    // Copy service items
    $copyItemsStmt = $pdo->prepare("
        INSERT INTO service_items (service_id, item_type, song_id, song_key, title, duration_minutes, notes, position, created_at, updated_at)
        SELECT ?, item_type, song_id, song_key, title, duration_minutes, notes, position, NOW(), NOW()
        FROM service_items
        WHERE service_id = ?
    ");
    $copyItemsStmt->execute([$newServiceId, $serviceId]);

    // Copy rota (without member assignments)
    $copyRotaStmt = $pdo->prepare("
        INSERT INTO service_rota (service_id, role_id, status, sort_order, created_at, updated_at)
        SELECT ?, role_id, 'unassigned', sort_order, NOW(), NOW()
        FROM service_rota
        WHERE service_id = ?
    ");
    $copyRotaStmt->execute([$newServiceId, $serviceId]);

    echo json_encode([
        'success' => true,
        'message' => 'Service duplicated successfully!',
        'service_id' => $newServiceId
    ]);
}
