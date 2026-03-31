<?php
/**
 * Send Assignment Email Notification
 * Helper script for sending email notifications to team members
 */

require_once __DIR__ . '/../../../includes/Auth.php';
require_once __DIR__ . '/../../../includes/db-config.php';

/**
 * Send assignment confirmation email to a team member
 *
 * @param PDO $pdo Database connection
 * @param int $rotaId Service rota ID
 * @return bool Success status
 */
function sendAssignmentEmail(PDO $pdo, int $rotaId): bool
{
    // Get assignment details
    $stmt = $pdo->prepare("
        SELECT sr.*,
               s.service_date, s.start_time, s.end_time, s.title, s.location,
               st.name as service_type_name, st.color as service_type_color,
               r.name as role_name,
               t.name as team_name,
               CONCAT(m.first_name, ' ', m.last_name) as member_name,
               m.email as member_email,
               m.first_name
        FROM service_rota sr
        JOIN services s ON sr.service_id = s.id
        JOIN service_types st ON s.service_type_id = st.id
        JOIN service_roles r ON sr.role_id = r.id
        JOIN service_teams t ON r.team_id = t.id
        JOIN members m ON sr.member_id = m.id
        WHERE sr.id = ?
    ");
    $stmt->execute([$rotaId]);
    $data = $stmt->fetch();

    if (!$data || !$data['member_email']) {
        return false;
    }

    // Format service date
    $serviceDate = new DateTime($data['service_date']);
    $formattedDate = $serviceDate->format('l, F j, Y');
    $formattedTime = date('g:i A', strtotime($data['start_time']));

    // Generate confirmation URLs
    $baseUrl = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://$_SERVER[HTTP_HOST]";
    $confirmUrl = $baseUrl . "/adminnew/services/confirm?token=" . urlencode($data['confirmation_token']) . "&action=confirm";
    $declineUrl = $baseUrl . "/adminnew/services/confirm?token=" . urlencode($data['confirmation_token']) . "&action=decline";
    $viewUrl = $baseUrl . "/adminnew/services/confirm?token=" . urlencode($data['confirmation_token']);

    // Build email subject
    $subject = "Service Assignment: {$data['role_name']} - {$formattedDate}";

    // Build email body (HTML)
    $htmlBody = buildEmailHtml($data, $formattedDate, $formattedTime, $confirmUrl, $declineUrl, $viewUrl);

    // Build email body (Plain text fallback)
    $textBody = buildEmailText($data, $formattedDate, $formattedTime, $confirmUrl, $declineUrl);

    // Send email
    return sendEmail($data['member_email'], $subject, $htmlBody, $textBody);
}

/**
 * Build HTML email body
 */
function buildEmailHtml(array $data, string $formattedDate, string $formattedTime, string $confirmUrl, string $declineUrl, string $viewUrl): string
{
    $firstName = htmlspecialchars($data['first_name']);
    $serviceName = htmlspecialchars($data['title'] ?: $data['service_type_name']);
    $roleName = htmlspecialchars($data['role_name']);
    $teamName = htmlspecialchars($data['team_name']);
    $location = htmlspecialchars($data['location'] ?: 'Main Auditorium');

    return <<<HTML
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style>
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif; line-height: 1.6; color: #333; margin: 0; padding: 0; background-color: #f5f5f5; }
        .container { max-width: 600px; margin: 20px auto; background: white; border-radius: 8px; overflow: hidden; box-shadow: 0 2px 8px rgba(0,0,0,0.1); }
        .header { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 30px 20px; text-align: center; }
        .header h1 { margin: 0; font-size: 24px; }
        .content { padding: 30px 20px; }
        .service-card { background: #f8f9fa; border-left: 4px solid #667eea; padding: 20px; margin: 20px 0; border-radius: 4px; }
        .service-card h2 { margin: 0 0 15px 0; font-size: 20px; color: #333; }
        .detail-row { display: flex; margin: 10px 0; }
        .detail-label { font-weight: 600; min-width: 100px; color: #666; }
        .detail-value { color: #333; }
        .role-badge { display: inline-block; background: #667eea; color: white; padding: 6px 12px; border-radius: 4px; font-weight: 600; font-size: 14px; }
        .button-container { text-align: center; margin: 30px 0; }
        .button { display: inline-block; padding: 14px 32px; margin: 0 8px; text-decoration: none; border-radius: 6px; font-weight: 600; font-size: 16px; }
        .button-confirm { background: #10b981; color: white; }
        .button-decline { background: #ef4444; color: white; }
        .footer { background: #f8f9fa; padding: 20px; text-align: center; font-size: 14px; color: #666; border-top: 1px solid #e5e7eb; }
        .footer a { color: #667eea; text-decoration: none; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>Service Assignment Confirmation</h1>
        </div>
        <div class="content">
            <p>Hi $firstName,</p>
            <p>You have been scheduled to serve on the <strong>$teamName</strong> team for an upcoming service!</p>

            <div class="service-card">
                <h2>$serviceName</h2>
                <div class="detail-row">
                    <span class="detail-label">Date:</span>
                    <span class="detail-value">$formattedDate</span>
                </div>
                <div class="detail-row">
                    <span class="detail-label">Time:</span>
                    <span class="detail-value">$formattedTime</span>
                </div>
                <div class="detail-row">
                    <span class="detail-label">Location:</span>
                    <span class="detail-value">$location</span>
                </div>
                <div class="detail-row">
                    <span class="detail-label">Your Role:</span>
                    <span class="detail-value"><span class="role-badge">$roleName</span></span>
                </div>
            </div>

            <p><strong>Please confirm your availability:</strong></p>

            <div class="button-container">
                <a href="$confirmUrl" class="button button-confirm">I Can Serve</a>
                <a href="$declineUrl" class="button button-decline">I Can't Make It</a>
            </div>

            <p style="font-size: 14px; color: #666;">
                If the buttons above don't work, you can also <a href="$viewUrl">view this assignment online</a> to respond.
            </p>
        </div>
        <div class="footer">
            <p>Thank you for serving!</p>
            <p style="margin-top: 15px; font-size: 12px;">
                This is an automated message. Please do not reply directly to this email.
            </p>
        </div>
    </div>
</body>
</html>
HTML;
}

/**
 * Build plain text email body
 */
function buildEmailText(array $data, string $formattedDate, string $formattedTime, string $confirmUrl, string $declineUrl): string
{
    $firstName = $data['first_name'];
    $serviceName = $data['title'] ?: $data['service_type_name'];
    $roleName = $data['role_name'];
    $teamName = $data['team_name'];
    $location = $data['location'] ?: 'Main Auditorium';

    return <<<TEXT
Hi $firstName,

You have been scheduled to serve on the $teamName team for an upcoming service!

SERVICE DETAILS:
Service: $serviceName
Date: $formattedDate
Time: $formattedTime
Location: $location
Your Role: $roleName

PLEASE CONFIRM YOUR AVAILABILITY:

To confirm you can serve:
$confirmUrl

If you cannot make it:
$declineUrl

Thank you for serving!

---
This is an automated message. Please do not reply directly to this email.
TEXT;
}

/**
 * Send email using PHP mail() or your preferred email service
 *
 * @param string $to Recipient email address
 * @param string $subject Email subject
 * @param string $htmlBody HTML email body
 * @param string $textBody Plain text email body
 * @return bool Success status
 */
function sendEmail(string $to, string $subject, string $htmlBody, string $textBody): bool
{
    // Email headers
    $boundary = md5(uniqid(time()));

    $headers = [
        "From: Alive Church <noreply@alivechurch.org>",
        "Reply-To: noreply@alivechurch.org",
        "MIME-Version: 1.0",
        "Content-Type: multipart/alternative; boundary=\"$boundary\""
    ];

    // Email body with both HTML and plain text
    $body = <<<BODY
--$boundary
Content-Type: text/plain; charset=UTF-8
Content-Transfer-Encoding: 7bit

$textBody

--$boundary
Content-Type: text/html; charset=UTF-8
Content-Transfer-Encoding: 7bit

$htmlBody

--$boundary--
BODY;

    // Send email
    // In production, you might want to use a service like SendGrid, Mailgun, etc.
    return mail($to, $subject, $body, implode("\r\n", $headers));
}

/**
 * Send reminder emails for all pending assignments in a service
 */
function sendServiceReminders(PDO $pdo, int $serviceId): int
{
    $stmt = $pdo->prepare("
        SELECT id
        FROM service_rota
        WHERE service_id = ?
        AND status = 'pending'
        AND member_id IS NOT NULL
    ");
    $stmt->execute([$serviceId]);
    $rotaIds = $stmt->fetchAll(PDO::FETCH_COLUMN);

    $sent = 0;
    foreach ($rotaIds as $rotaId) {
        if (sendAssignmentEmail($pdo, $rotaId)) {
            $sent++;
        }
    }

    return $sent;
}

// If called directly via API
if (__FILE__ === $_SERVER['SCRIPT_FILENAME']) {
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
    $input = json_decode(file_get_contents('php://input'), true) ?? $_POST;
    $action = $input['action'] ?? '';

    try {
        if ($action === 'send-assignment') {
            $rotaId = (int)($input['rota_id'] ?? 0);
            if (!$rotaId) {
                throw new Exception('Rota ID is required');
            }

            $success = sendAssignmentEmail($pdo, $rotaId);
            echo json_encode([
                'success' => $success,
                'message' => $success ? 'Email sent' : 'Failed to send email'
            ]);

        } elseif ($action === 'send-service-reminders') {
            $serviceId = (int)($input['service_id'] ?? 0);
            if (!$serviceId) {
                throw new Exception('Service ID is required');
            }

            $sent = sendServiceReminders($pdo, $serviceId);
            echo json_encode([
                'success' => true,
                'message' => "$sent email(s) sent",
                'count' => $sent
            ]);

        } else {
            throw new Exception('Invalid action');
        }
    } catch (Exception $e) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
}
