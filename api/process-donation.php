<?php
/**
 * Stripe Donation Processing Endpoint
 *
 * This file handles donation payments through Stripe.
 * SETUP REQUIRED: Add your Stripe secret key below to enable payment processing.
 * Get your key from: https://dashboard.stripe.com/apikeys
 */

// IMPORTANT: Add your Stripe secret key here
define('STRIPE_SECRET_KEY', 'sk_test_YOUR_SECRET_KEY_HERE'); // REPLACE THIS

// Only process POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

// Check if Stripe key is configured
if (STRIPE_SECRET_KEY === 'sk_test_YOUR_SECRET_KEY_HERE') {
    http_response_code(500);
    echo json_encode(['error' => 'Stripe is not configured. Please add your secret key.']);
    exit;
}

// Load Stripe PHP library (you'll need to install this via Composer)
// Run: composer require stripe/stripe-php
// Uncomment the line below once Stripe is installed:
// require_once __DIR__ . '/../vendor/autoload.php';

// For now, return a setup message
if (!class_exists('\Stripe\Stripe')) {
    http_response_code(500);
    echo json_encode([
        'error' => 'Stripe library not installed. Run: composer require stripe/stripe-php',
        'setup_instructions' => [
            '1. Install Composer if not already installed: https://getcomposer.org',
            '2. Navigate to your project directory',
            '3. Run: composer require stripe/stripe-php',
            '4. Add your Stripe secret key to this file',
            '5. Test with Stripe test mode keys first'
        ]
    ]);
    exit;
}

// Set Stripe API key
\Stripe\Stripe::setApiKey(STRIPE_SECRET_KEY);

try {
    // Get form data
    $amount = filter_input(INPUT_POST, 'amount', FILTER_VALIDATE_FLOAT);
    $frequency = filter_input(INPUT_POST, 'frequency', FILTER_SANITIZE_STRING);
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

        // Return client secret from the subscription's payment intent
        echo json_encode([
            'success' => true,
            'clientSecret' => $subscription->latest_invoice->payment_intent->client_secret,
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

} catch (\Stripe\Exception\InvalidRequestException $e) {
    // Invalid parameters
    http_response_code(400);
    echo json_encode(['error' => $e->getMessage()]);

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
