<?php
/**
 * Enhanced Form Handler with Email Notifications
 *
 * Handles form submissions, stores to JSON, and sends email notifications to church staff.
 */

// Church staff email for notifications (update this with real email)
define('CHURCH_ADMIN_EMAIL', 'admin@alivechurch.co.uk');
define('CHURCH_NAME', 'Alive Church');

/**
 * Sanitize form field input
 */
function sanitize_field(string $value): string
{
    return trim(filter_var($value, FILTER_SANITIZE_FULL_SPECIAL_CHARS));
}

/**
 * Store form submission to JSON file (backup storage)
 */
function store_form_submission(string $type, array $data): bool
{
    $dir = __DIR__ . '/../storage';
    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
    }
    $file = $dir . '/form-submissions.json';
    $existing = [];
    if (file_exists($file)) {
        $json = file_get_contents($file);
        $decoded = json_decode($json, true);
        if (is_array($decoded)) {
            $existing = $decoded;
        }
    }
    $existing[] = [
        'type' => $type,
        'timestamp' => gmdate('c'),
        'data' => $data,
        'ip_address' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
    ];
    return (bool) file_put_contents($file, json_encode($existing, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
}

/**
 * Send email notification to church staff
 */
function send_form_notification(string $type, array $data): bool
{
    $to = CHURCH_ADMIN_EMAIL;
    $siteName = CHURCH_NAME;

    // Build email based on form type
    switch ($type) {
        case 'contact':
            $subject = "New Contact Form Submission - {$siteName}";
            $message = build_contact_email($data);
            $replyTo = $data['email'] ?? null;
            break;

        case 'prayer':
            $subject = "New Prayer Request - {$siteName}";
            $message = build_prayer_email($data);
            $replyTo = $data['email'] ?? null;
            break;

        case 'visit':
            $subject = "New Visit Registration - {$siteName}";
            $message = build_visit_email($data);
            $replyTo = $data['email'] ?? null;
            break;

        default:
            $subject = "New Form Submission - {$siteName}";
            $message = build_generic_email($type, $data);
            $replyTo = $data['email'] ?? null;
            break;
    }

    // Set up email headers
    $headers = "MIME-Version: 1.0\r\n";
    $headers .= "Content-type: text/html; charset=UTF-8\r\n";
    $headers .= "From: {$siteName} Website <noreply@alivechurch.co.uk>\r\n";

    if ($replyTo && filter_var($replyTo, FILTER_VALIDATE_EMAIL)) {
        $headers .= "Reply-To: {$replyTo}\r\n";
    }

    // Send email
    $sent = mail($to, $subject, $message, $headers);

    // Log if email failed
    if (!$sent) {
        error_log("Failed to send form notification email for type: {$type}");
    }

    return $sent;
}

/**
 * Build contact form email template
 */
function build_contact_email(array $data): string
{
    $name = htmlspecialchars($data['name'] ?? 'N/A');
    $email = htmlspecialchars($data['email'] ?? 'N/A');
    $phone = htmlspecialchars($data['phone'] ?? 'Not provided');
    $message = nl2br(htmlspecialchars($data['message'] ?? 'N/A'));
    $timestamp = date('l, F j, Y \a\t g:i A');

    return "
    <html>
    <head>
        <style>
            body { font-family: Arial, sans-serif; line-height: 1.6; color: #2D1B4E; }
            .container { max-width: 600px; margin: 0 auto; padding: 20px; background: #fff; }
            .header { background: linear-gradient(135deg, #FF1493 0%, #4B2679 100%); padding: 20px; color: white; }
            .content { padding: 30px; background: #F9FAFB; border-radius: 8px; margin: 20px 0; }
            .field { margin-bottom: 20px; }
            .field-label { font-weight: 600; color: #4B2679; margin-bottom: 5px; }
            .field-value { color: #2D1B4E; }
            .footer { padding: 20px; text-align: center; font-size: 14px; color: #6B7280; }
        </style>
    </head>
    <body>
        <div class='container'>
            <div class='header'>
                <h2>New Contact Form Submission</h2>
            </div>
            <div class='content'>
                <div class='field'>
                    <div class='field-label'>Name:</div>
                    <div class='field-value'>{$name}</div>
                </div>
                <div class='field'>
                    <div class='field-label'>Email:</div>
                    <div class='field-value'><a href='mailto:{$email}'>{$email}</a></div>
                </div>
                <div class='field'>
                    <div class='field-label'>Phone:</div>
                    <div class='field-value'>{$phone}</div>
                </div>
                <div class='field'>
                    <div class='field-label'>Message:</div>
                    <div class='field-value'>{$message}</div>
                </div>
            </div>
            <div class='footer'>
                <p>Submitted on {$timestamp}</p>
                <p><small>This is an automated notification from your website contact form.</small></p>
            </div>
        </div>
    </body>
    </html>
    ";
}

/**
 * Build prayer request email template
 */
function build_prayer_email(array $data): string
{
    $name = htmlspecialchars($data['name'] ?? 'Anonymous');
    $email = htmlspecialchars($data['email'] ?? 'Not provided');
    $request = nl2br(htmlspecialchars($data['prayer_request'] ?? $data['message'] ?? 'N/A'));
    $timestamp = date('l, F j, Y \a\t g:i A');

    return "
    <html>
    <head>
        <style>
            body { font-family: Arial, sans-serif; line-height: 1.6; color: #2D1B4E; }
            .container { max-width: 600px; margin: 0 auto; padding: 20px; background: #fff; }
            .header { background: linear-gradient(135deg, #4B2679 0%, #2D1B4E 100%); padding: 20px; color: white; }
            .content { padding: 30px; background: #F9FAFB; border-radius: 8px; margin: 20px 0; }
            .field { margin-bottom: 20px; }
            .field-label { font-weight: 600; color: #4B2679; margin-bottom: 5px; }
            .field-value { color: #2D1B4E; }
            .prayer-note { background: #FEF3C7; border-left: 4px solid #F59E0B; padding: 15px; margin: 20px 0; }
            .footer { padding: 20px; text-align: center; font-size: 14px; color: #6B7280; }
        </style>
    </head>
    <body>
        <div class='container'>
            <div class='header'>
                <h2>🙏 New Prayer Request</h2>
            </div>
            <div class='content'>
                <div class='field'>
                    <div class='field-label'>From:</div>
                    <div class='field-value'>{$name}</div>
                </div>
                <div class='field'>
                    <div class='field-label'>Email:</div>
                    <div class='field-value'>{$email}</div>
                </div>
                <div class='prayer-note'>
                    <div class='field-label'>Prayer Request:</div>
                    <div class='field-value'>{$request}</div>
                </div>
            </div>
            <div class='footer'>
                <p>Submitted on {$timestamp}</p>
                <p><small>Remember to follow up and let them know you're praying!</small></p>
            </div>
        </div>
    </body>
    </html>
    ";
}

/**
 * Build visit registration email template
 */
function build_visit_email(array $data): string
{
    $name = htmlspecialchars($data['name'] ?? 'N/A');
    $email = htmlspecialchars($data['email'] ?? 'N/A');
    $phone = htmlspecialchars($data['phone'] ?? 'Not provided');
    $adults = htmlspecialchars($data['adults'] ?? '1');
    $children = htmlspecialchars($data['children'] ?? '0');
    $service = htmlspecialchars($data['service'] ?? 'Not specified');
    $timestamp = date('l, F j, Y \a\t g:i A');

    return "
    <html>
    <head>
        <style>
            body { font-family: Arial, sans-serif; line-height: 1.6; color: #2D1B4E; }
            .container { max-width: 600px; margin: 0 auto; padding: 20px; background: #fff; }
            .header { background: linear-gradient(135deg, #FF1493 0%, #4B2679 100%); padding: 20px; color: white; }
            .content { padding: 30px; background: #F9FAFB; border-radius: 8px; margin: 20px 0; }
            .field { margin-bottom: 15px; }
            .field-label { font-weight: 600; color: #4B2679; margin-bottom: 5px; }
            .field-value { color: #2D1B4E; }
            .highlight { background: #E0F2FE; border-left: 4px solid #0284C7; padding: 15px; margin: 20px 0; }
            .footer { padding: 20px; text-align: center; font-size: 14px; color: #6B7280; }
        </style>
    </head>
    <body>
        <div class='container'>
            <div class='header'>
                <h2>👋 New First-Time Visitor Registration</h2>
            </div>
            <div class='content'>
                <div class='field'>
                    <div class='field-label'>Name:</div>
                    <div class='field-value'>{$name}</div>
                </div>
                <div class='field'>
                    <div class='field-label'>Email:</div>
                    <div class='field-value'><a href='mailto:{$email}'>{$email}</a></div>
                </div>
                <div class='field'>
                    <div class='field-label'>Phone:</div>
                    <div class='field-value'>{$phone}</div>
                </div>
                <div class='highlight'>
                    <div class='field-label'>Service Time:</div>
                    <div class='field-value'>{$service}</div>
                    <div class='field-label' style='margin-top: 10px;'>Party Size:</div>
                    <div class='field-value'>{$adults} adults, {$children} children</div>
                </div>
            </div>
            <div class='footer'>
                <p>Registered on {$timestamp}</p>
                <p><small>Make sure the welcome team is ready to greet them!</small></p>
            </div>
        </div>
    </body>
    </html>
    ";
}

/**
 * Build generic email template for other form types
 */
function build_generic_email(string $type, array $data): string
{
    $timestamp = date('l, F j, Y \a\t g:i A');
    $fields = '';

    foreach ($data as $key => $value) {
        $label = ucfirst(str_replace('_', ' ', $key));
        $val = is_array($value) ? json_encode($value) : htmlspecialchars($value);
        $fields .= "<div class='field'><div class='field-label'>{$label}:</div><div class='field-value'>{$val}</div></div>";
    }

    return "
    <html>
    <head>
        <style>
            body { font-family: Arial, sans-serif; line-height: 1.6; color: #2D1B4E; }
            .container { max-width: 600px; margin: 0 auto; padding: 20px; background: #fff; }
            .header { background: linear-gradient(135deg, #FF1493 0%, #4B2679 100%); padding: 20px; color: white; }
            .content { padding: 30px; background: #F9FAFB; border-radius: 8px; margin: 20px 0; }
            .field { margin-bottom: 15px; }
            .field-label { font-weight: 600; color: #4B2679; margin-bottom: 5px; }
            .field-value { color: #2D1B4E; }
            .footer { padding: 20px; text-align: center; font-size: 14px; color: #6B7280; }
        </style>
    </head>
    <body>
        <div class='container'>
            <div class='header'>
                <h2>New Form Submission: {$type}</h2>
            </div>
            <div class='content'>
                {$fields}
            </div>
            <div class='footer'>
                <p>Submitted on {$timestamp}</p>
            </div>
        </div>
    </body>
    </html>
    ";
}

/**
 * Store form submission to database
 */
function store_form_to_database(string $type, array $data): bool
{
    try {
        require_once __DIR__ . '/db-config.php';
        $pdo = getDbConnection();

        $stmt = $pdo->prepare(
            "INSERT INTO form_submissions (form_type, form_data, ip_address, submitted_at, processed)
             VALUES (?, ?, ?, NOW(), FALSE)"
        );

        $result = $stmt->execute([
            $type,
            json_encode($data, JSON_UNESCAPED_UNICODE),
            $_SERVER['REMOTE_ADDR'] ?? 'unknown'
        ]);

        return $result;
    } catch (Exception $e) {
        error_log("Failed to store form submission to database: " . $e->getMessage());
        return false;
    }
}

/**
 * Process form submission (main handler function)
 */
function process_form_submission(string $type, array $data): bool
{
    // Store to database (primary storage)
    $storedDb = store_form_to_database($type, $data);

    // Store to JSON (backup)
    $storedJson = store_form_submission($type, $data);

    // Send email notification
    $emailSent = send_form_notification($type, $data);

    // Return true if at least one method succeeded
    return $storedDb || $storedJson || $emailSent;
}
