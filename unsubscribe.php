<?php
/**
 * Welcome Journey Unsubscribe Handler
 *
 * Allows visitors to unsubscribe from the welcome email sequence.
 * URL: /unsubscribe?token=XXXXX
 */

require_once __DIR__ . '/includes/db-config.php';
require_once __DIR__ . '/includes/WelcomeJourney.php';
require_once __DIR__ . '/includes/site-config.php';

$site = getSiteConfig();
$pageTitle = "Unsubscribe - " . $site['name'];

// Get the token from query string
$token = $_GET['token'] ?? '';
$message = '';
$success = false;

if (!empty($token)) {
    try {
        $pdo = getDbConnection();
        $welcomeJourney = new WelcomeJourney($pdo);

        if ($welcomeJourney->unsubscribe($token)) {
            $success = true;
            $message = "You have been successfully unsubscribed from our welcome emails.";
        } else {
            $message = "We couldn't find that subscription. You may have already unsubscribed, or the link may have expired.";
        }
    } catch (Exception $e) {
        error_log("Unsubscribe error: " . $e->getMessage());
        $message = "Something went wrong. Please try again or contact us directly.";
    }
} else {
    $message = "Invalid unsubscribe link. Please check the link in your email and try again.";
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($pageTitle); ?></title>
    <meta name="robots" content="noindex, nofollow">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
            background: linear-gradient(135deg, #fdf2f8 0%, #f5f3ff 50%, #ecfdf5 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        .container {
            background: white;
            border-radius: 24px;
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.1);
            max-width: 480px;
            width: 100%;
            padding: 48px;
            text-align: center;
        }
        .icon {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 24px;
            font-size: 36px;
        }
        .icon.success {
            background: linear-gradient(135deg, #ecfdf5 0%, #d1fae5 100%);
        }
        .icon.error {
            background: linear-gradient(135deg, #fef2f2 0%, #fee2e2 100%);
        }
        h1 {
            font-size: 24px;
            font-weight: 700;
            color: #1e1a2b;
            margin-bottom: 16px;
        }
        p {
            font-size: 16px;
            line-height: 1.6;
            color: #71717a;
            margin-bottom: 24px;
        }
        .btn {
            display: inline-block;
            background: linear-gradient(135deg, #eb008b 0%, #d4007d 100%);
            color: white;
            font-weight: 600;
            font-size: 16px;
            padding: 14px 32px;
            border-radius: 50px;
            text-decoration: none;
            transition: transform 0.2s, box-shadow 0.2s;
        }
        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 20px -5px rgba(235, 0, 139, 0.4);
        }
        .note {
            margin-top: 32px;
            padding-top: 24px;
            border-top: 1px solid #e4e4e7;
            font-size: 14px;
            color: #a1a1aa;
        }
        .note a {
            color: #eb008b;
            text-decoration: none;
        }
        .note a:hover {
            text-decoration: underline;
        }
    </style>
</head>
<body>
    <div class="container">
        <?php if ($success): ?>
            <div class="icon success">
                <span>✓</span>
            </div>
            <h1>You're Unsubscribed</h1>
            <p><?= htmlspecialchars($message); ?></p>
            <p style="font-size: 14px;">We're sorry to see you go! You'll no longer receive our welcome emails, but you're always welcome at Alive Church.</p>
        <?php else: ?>
            <div class="icon error">
                <span>!</span>
            </div>
            <h1>Oops!</h1>
            <p><?= htmlspecialchars($message); ?></p>
        <?php endif; ?>

        <a href="/" class="btn">Visit Our Website</a>

        <div class="note">
            <p>Questions? <a href="/contact-us">Contact us</a> or email us at<br>
            <a href="mailto:<?= htmlspecialchars($site['email'] ?? 'hello@alivechurch.co.uk'); ?>"><?= htmlspecialchars($site['email'] ?? 'hello@alivechurch.co.uk'); ?></a></p>
        </div>
    </div>
</body>
</html>
