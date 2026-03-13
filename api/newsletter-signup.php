<?php
/**
 * Newsletter Signup Handler
 *
 * Handles email newsletter subscriptions from the footer form.
 * Supports both AJAX (JSON) and traditional form submissions.
 * Stores emails to JSON and optionally integrates with email service providers.
 */

session_start();

// Only process POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    header('Location: /');
    exit;
}

// Check if this is an AJAX request
$isAjax = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) &&
          strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';

// Get email from POST data (works for both form and JSON)
$email = filter_input(INPUT_POST, 'email', FILTER_VALIDATE_EMAIL);

if (!$email) {
    if ($isAjax) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Please enter a valid email address.']);
        exit;
    }
    $_SESSION['newsletter_error'] = 'Please enter a valid email address.';
    header('Location: ' . ($_SERVER['HTTP_REFERER'] ?? '/'));
    exit;
}

// Check for duplicate subscriptions
if (isAlreadySubscribed($email)) {
    if ($isAjax) {
        header('Content-Type: application/json');
        echo json_encode(['success' => true, 'message' => 'You\'re already subscribed! Check your inbox for our latest updates.']);
        exit;
    }
    $_SESSION['newsletter_message'] = 'You\'re already subscribed! Check your inbox for our latest updates.';
    header('Location: ' . ($_SERVER['HTTP_REFERER'] ?? '/'));
    exit;
}

try {
    // Save to JSON file
    saveSubscriber($email);

    // Note: Confirmation emails disabled to avoid slow response times
    // Re-enable with proper email queue/service when ready
    // sendConfirmationEmail($email);

    // Optional: Add to email service provider (Mailchimp, ConvertKit, etc.)
    // addToEmailService($email);

    if ($isAjax) {
        header('Content-Type: application/json');
        echo json_encode(['success' => true, 'message' => 'Success! You\'re now subscribed.']);
        exit;
    }

    // Set success message
    $_SESSION['newsletter_message'] = 'Success! You\'re now subscribed.';

} catch (Throwable $e) {
    error_log('Newsletter signup error: ' . $e->getMessage());

    if ($isAjax) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Something went wrong. Please try again later.']);
        exit;
    }

    $_SESSION['newsletter_error'] = 'Something went wrong. Please try again later.';
}

// Redirect back to referring page (for non-AJAX)
header('Location: ' . ($_SERVER['HTTP_REFERER'] ?? '/'));
exit;

/**
 * Save subscriber to JSON file
 */
function saveSubscriber($email) {
    $dataFile = __DIR__ . '/../data/newsletter-subscribers.json';
    $dataDir = dirname($dataFile);

    // Create data directory if it doesn't exist
    if (!is_dir($dataDir)) {
        if (!@mkdir($dataDir, 0755, true)) {
            error_log("Newsletter: Failed to create data directory: $dataDir");
            throw new Exception("Failed to create data directory");
        }
    }

    // Check if directory is writable
    if (!is_writable($dataDir)) {
        error_log("Newsletter: Data directory not writable: $dataDir");
        throw new Exception("Data directory not writable");
    }

    // Load existing subscribers
    $subscribers = [];
    if (file_exists($dataFile)) {
        $content = file_get_contents($dataFile);
        if ($content === false) {
            error_log("Newsletter: Failed to read subscribers file: $dataFile");
            throw new Exception("Failed to read subscribers file");
        }
        $subscribers = json_decode($content, true) ?? [];
    }

    // Add new subscriber
    $subscribers[] = [
        'email' => $email,
        'subscribed_at' => date('Y-m-d H:i:s'),
        'ip_address' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
        'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown',
        'status' => 'confirmed' // confirmed, unsubscribed
    ];

    // Save to file
    $result = file_put_contents($dataFile, json_encode($subscribers, JSON_PRETTY_PRINT));
    if ($result === false) {
        error_log("Newsletter: Failed to write to subscribers file: $dataFile");
        throw new Exception("Failed to write to subscribers file");
    }
}

/**
 * Check if email is already subscribed
 */
function isAlreadySubscribed($email) {
    $dataFile = __DIR__ . '/../data/newsletter-subscribers.json';

    if (!file_exists($dataFile)) {
        return false;
    }

    $subscribers = json_decode(file_get_contents($dataFile), true) ?? [];

    foreach ($subscribers as $subscriber) {
        if (isset($subscriber['email']) &&
            strtolower($subscriber['email']) === strtolower($email) &&
            (!isset($subscriber['status']) || $subscriber['status'] !== 'unsubscribed')) {
            return true;
        }
    }

    return false;
}

/**
 * Send welcome email to new subscriber
 */
function sendConfirmationEmail($email) {
    $siteName = 'Alive Church';
    $subject = 'You\'re Subscribed to Alive Church Updates!';

    $message = "
    <html>
    <head>
        <style>
            body { font-family: Arial, sans-serif; line-height: 1.6; color: #2D1B4E; }
            .container { max-width: 600px; margin: 0 auto; padding: 20px; }
            .header { background: linear-gradient(135deg, #FF1493 0%, #4B2679 100%); padding: 30px; text-align: center; color: white; }
            .content { background: #fff; padding: 30px; }
            .footer { background: #F9FAFB; padding: 20px; text-align: center; font-size: 14px; color: #6B7280; }
            .button { display: inline-block; padding: 12px 24px; background: #FF1493; color: white; text-decoration: none; border-radius: 8px; margin: 20px 0; }
        </style>
    </head>
    <body>
        <div class='container'>
            <div class='header'>
                <h1>Welcome to Alive Church!</h1>
            </div>
            <div class='content'>
                <p>Hi there,</p>
                <p><strong>You're all set!</strong> Thank you for subscribing to Alive Church updates. You'll now receive:</p>
                <ul>
                    <li>Weekly sermon highlights and teaching series</li>
                    <li>Event invites and special gatherings</li>
                    <li>Stories of God at work in our community</li>
                    <li>Prayer updates and ministry opportunities</li>
                </ul>
                <p>We're so glad you're part of the Alive family!</p>
                <p style='text-align: center;'>
                    <a href='https://alivechurch.co.uk' class='button'>Visit Our Website</a>
                </p>
                <p>Blessings,<br><strong>The Alive Church Team</strong></p>
            </div>
            <div class='footer'>
                <p>Alive House, Nelson Street, Norwich NR2 4DR</p>
                <p><a href='mailto:hello@alivechurch.co.uk'>hello@alivechurch.co.uk</a></p>
                <p><small>Don't want these emails? Contact us to unsubscribe.</small></p>
            </div>
        </div>
    </body>
    </html>
    ";

    $headers = "MIME-Version: 1.0\r\n";
    $headers .= "Content-type: text/html; charset=UTF-8\r\n";
    $headers .= "From: Alive Church <hello@alivechurch.co.uk>\r\n";
    $headers .= "Reply-To: hello@alivechurch.co.uk\r\n";

    // Send email
    mail($email, $subject, $message, $headers);
}

/**
 * Optional: Add subscriber to email service provider
 * Uncomment and configure for Mailchimp, ConvertKit, etc.
 */
function addToEmailService($email) {
    // Example: Mailchimp API integration
    // $apiKey = 'YOUR_MAILCHIMP_API_KEY';
    // $listId = 'YOUR_LIST_ID';
    // $dataCenter = 'us1'; // e.g., us1, us2, etc.

    // $url = "https://{$dataCenter}.api.mailchimp.com/3.0/lists/{$listId}/members";

    // $data = [
    //     'email_address' => $email,
    //     'status' => 'subscribed'
    // ];

    // $ch = curl_init($url);
    // curl_setopt($ch, CURLOPT_USERPWD, "user:{$apiKey}");
    // curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    // curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    // curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    // curl_setopt($ch, CURLOPT_POST, 1);
    // curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    // curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    // $result = curl_exec($ch);
    // curl_close($ch);

    // return json_decode($result, true);
}
