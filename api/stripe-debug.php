<?php
/**
 * Stripe Debug Endpoint - TEMPORARY
 * Delete this file after debugging is complete
 */

require_once __DIR__ . '/../includes/env-loader.php';
Env::load();

header('Content-Type: application/json');

$secretKey = env('STRIPE_SECRET_KEY', '');
$publishableKey = env('STRIPE_PUBLISHABLE_KEY', '');

// Mask the keys for display (show first 7 and last 4 chars)
function maskKey($key) {
    if (strlen($key) < 15) return 'TOO_SHORT';
    if (empty($key)) return 'EMPTY';
    return substr($key, 0, 7) . '...' . substr($key, -4);
}

$debug = [
    'env_file_exists' => file_exists(__DIR__ . '/../.env'),
    'secret_key_prefix' => $secretKey ? substr($secretKey, 0, 7) : 'EMPTY',
    'secret_key_masked' => maskKey($secretKey),
    'secret_key_length' => strlen($secretKey),
    'publishable_key_prefix' => $publishableKey ? substr($publishableKey, 0, 7) : 'EMPTY',
    'publishable_key_masked' => maskKey($publishableKey),
    'php_version' => PHP_VERSION,
    'stripe_library_exists' => file_exists(__DIR__ . '/../vendor/autoload.php'),
];

// Test Stripe connection if key looks valid
if (!empty($secretKey) && (str_starts_with($secretKey, 'sk_') || str_starts_with($secretKey, 'rk_'))) {
    try {
        require_once __DIR__ . '/../vendor/autoload.php';
        \Stripe\Stripe::setApiKey($secretKey);

        // Set CA bundle
        $caBundlePath = __DIR__ . '/../includes/cacert.pem';
        if (file_exists($caBundlePath)) {
            \Stripe\Stripe::setCABundlePath($caBundlePath);
        }

        // Try to retrieve account info (simple API test)
        $account = \Stripe\Account::retrieve();
        $debug['stripe_connection'] = 'SUCCESS';
        $debug['stripe_account_id'] = $account->id ?? 'unknown';
        $debug['stripe_account_type'] = $account->type ?? 'unknown';
    } catch (\Stripe\Exception\AuthenticationException $e) {
        $debug['stripe_connection'] = 'AUTH_FAILED';
        $debug['stripe_error'] = $e->getMessage();
    } catch (\Stripe\Exception\PermissionException $e) {
        $debug['stripe_connection'] = 'PERMISSION_DENIED';
        $debug['stripe_error'] = $e->getMessage();
    } catch (\Stripe\Exception\ApiConnectionException $e) {
        $debug['stripe_connection'] = 'CONNECTION_FAILED';
        $debug['stripe_error'] = $e->getMessage();
    } catch (Exception $e) {
        $debug['stripe_connection'] = 'ERROR';
        $debug['stripe_error'] = $e->getMessage();
    }
} else {
    $debug['stripe_connection'] = 'SKIPPED - Invalid key format';
}

echo json_encode($debug, JSON_PRETTY_PRINT);
