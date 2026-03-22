<?php
/**
 * Stripe Donation Processing Endpoint
 *
 * This file handles donation payments through Stripe.
 * SETUP: Add STRIPE_SECRET_KEY to your .env file
 * Get your key from: https://dashboard.stripe.com/apikeys
 */

// Debug mode - remove after fixing
if (isset($_GET['debug'])) {
    header('Content-Type: text/plain');
    echo "=== PROCESS-DONATION DEBUG ===\n\n";
    echo "Requiring env-loader...\n";
}

require_once __DIR__ . '/../includes/env-loader.php';
Env::load();

if (isset($_GET['debug'])) {
    $key = env('STRIPE_SECRET_KEY', 'NOT_FOUND');
    echo "STRIPE_SECRET_KEY from env(): " . substr($key, 0, 7) . "... (length: " . strlen($key) . ")\n";
    echo "Direct getenv(): " . substr(getenv('STRIPE_SECRET_KEY') ?: 'NOT_SET', 0, 7) . "...\n";
    exit;
}

// Load Stripe key from environment
define('STRIPE_SECRET_KEY', env('STRIPE_SECRET_KEY', 'sk_test_YOUR_SECRET_KEY_HERE'));

// Only process POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

// Check if Stripe key is configured
if (empty(STRIPE_SECRET_KEY) || STRIPE_SECRET_KEY === 'sk_test_YOUR_SECRET_KEY_HERE') {
    http_response_code(500);
    echo json_encode(['error' => 'Stripe is not configured. Please add your secret key to the .env file.']);
    exit;
}

// Validate the key format
if (!str_starts_with(STRIPE_SECRET_KEY, 'sk_live_') && !str_starts_with(STRIPE_SECRET_KEY, 'sk_test_') && !str_starts_with(STRIPE_SECRET_KEY, 'rk_live_') && !str_starts_with(STRIPE_SECRET_KEY, 'rk_test_')) {
    http_response_code(500);
    echo json_encode(['error' => 'Invalid Stripe secret key format. Key should start with sk_live_, sk_test_, rk_live_, or rk_test_.']);
    exit;
}

// Load Stripe PHP library
require_once __DIR__ . '/../vendor/autoload.php';

// Set Stripe API key
\Stripe\Stripe::setApiKey(STRIPE_SECRET_KEY);

// Set CA bundle for SSL verification (fixes certificate errors on some hosts)
$caBundlePath = __DIR__ . '/../includes/cacert.pem';
if (file_exists($caBundlePath)) {
    \Stripe\Stripe::setCABundlePath($caBundlePath);
}

try {
    // Set JSON content type for all responses
    header('Content-Type: application/json');

    // Get form data
    $amount = filter_input(INPUT_POST, 'amount', FILTER_VALIDATE_FLOAT);
    $frequency = isset($_POST['frequency']) ? trim($_POST['frequency']) : '';
    $email = filter_input(INPUT_POST, 'email', FILTER_VALIDATE_EMAIL);
    $giftAid = isset($_POST['gift_aid']) && $_POST['gift_aid'] === 'yes';

    // Validate required fields
    if (!$amount || $amount < 1) {
        throw new Exception('Invalid donation amount');
    }

    if (!$email) {
        throw new Exception('Valid email address required');
    }

    if (!in_array($frequency, ['one-time', 'weekly', 'monthly'])) {
        throw new Exception('Invalid frequency');
    }

    // Convert amount to pence (Stripe uses smallest currency unit)
    $amountInPence = (int)($amount * 100);

    // Create Stripe customer
    $customer = \Stripe\Customer::create([
        'email' => $email,
        'metadata' => [
            'gift_aid' => $giftAid ? 'yes' : 'no',
            'frequency' => $frequency
        ]
    ]);

    // Handle one-time vs recurring donations
    if ($frequency === 'one-time') {
        // Create a one-time payment intent
        $paymentIntent = \Stripe\PaymentIntent::create([
            'amount' => $amountInPence,
            'currency' => 'gbp',
            'customer' => $customer->id,
            'description' => 'Donation to Alive Church',
            'receipt_email' => $email,
            'metadata' => [
                'gift_aid' => $giftAid ? 'yes' : 'no',
                'type' => 'one-time donation'
            ]
        ]);

        // Verify we got a valid client_secret
        if (empty($paymentIntent->client_secret)) {
            throw new Exception('Payment intent created but no client secret returned. Check Stripe API key permissions.');
        }

        // Return client secret for confirming payment on frontend
        echo json_encode([
            'success' => true,
            'clientSecret' => $paymentIntent->client_secret,
            'type' => 'payment'
        ]);

    } else {
        // Create recurring subscription
        // First, create a price for the recurring donation
        $interval = $frequency === 'weekly' ? 'week' : 'month';

        $price = \Stripe\Price::create([
            'unit_amount' => $amountInPence,
            'currency' => 'gbp',
            'recurring' => ['interval' => $interval],
            'product_data' => [
                'name' => ucfirst($frequency) . ' Donation to Alive Church'
            ]
        ]);

        // Create subscription
        $subscription = \Stripe\Subscription::create([
            'customer' => $customer->id,
            'items' => [['price' => $price->id]],
            'payment_behavior' => 'default_incomplete',
            'expand' => ['latest_invoice.payment_intent'],
            'metadata' => [
                'gift_aid' => $giftAid ? 'yes' : 'no',
                'type' => $frequency . ' donation'
            ]
        ]);

        // Verify we got a valid client_secret from subscription
        $clientSecret = $subscription->latest_invoice->payment_intent->client_secret ?? null;
        if (empty($clientSecret)) {
            throw new Exception('Subscription created but no client secret returned. Check Stripe API key permissions.');
        }

        // Return client secret from the subscription's payment intent
        echo json_encode([
            'success' => true,
            'clientSecret' => $clientSecret,
            'subscriptionId' => $subscription->id,
            'type' => 'subscription'
        ]);
    }

    // Log donation (optional - add your own logging)
    logDonation([
        'amount' => $amount,
        'frequency' => $frequency,
        'email' => $email,
        'gift_aid' => $giftAid,
        'timestamp' => date('Y-m-d H:i:s')
    ]);

} catch (\Stripe\Exception\CardException $e) {
    // Card was declined
    http_response_code(400);
    echo json_encode(['error' => $e->getMessage()]);

} catch (\Stripe\Exception\RateLimitException $e) {
    // Too many requests
    http_response_code(429);
    echo json_encode(['error' => 'Too many requests. Please wait a moment and try again.']);

} catch (\Stripe\Exception\InvalidRequestException $e) {
    // Invalid parameters
    http_response_code(400);
    echo json_encode(['error' => $e->getMessage()]);

} catch (\Stripe\Exception\AuthenticationException $e) {
    // Invalid API key
    http_response_code(500);
    echo json_encode(['error' => 'Payment system authentication error. Please contact the church.']);

} catch (\Stripe\Exception\ApiConnectionException $e) {
    // Network error
    http_response_code(500);
    echo json_encode(['error' => 'Could not connect to payment system. Please try again.']);

} catch (\Stripe\Exception\ApiErrorException $e) {
    // Generic Stripe API error
    http_response_code(500);
    echo json_encode(['error' => 'Payment error: ' . $e->getMessage()]);

} catch (Exception $e) {
    // General error
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}

/**
 * Log donation to JSON file (optional backup logging)
 */
function logDonation($data) {
    $logFile = __DIR__ . '/../data/donations.json';
    $logDir = dirname($logFile);

    // Create data directory if it doesn't exist
    if (!is_dir($logDir)) {
        mkdir($logDir, 0755, true);
    }

    $donations = [];
    if (file_exists($logFile)) {
        $donations = json_decode(file_get_contents($logFile), true) ?? [];
    }

    $donations[] = $data;
    file_put_contents($logFile, json_encode($donations, JSON_PRETTY_PRINT));
}
