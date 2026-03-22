<?php
/**
 * Stripe Debug Endpoint - TEMPORARY
 * Delete this file after debugging is complete
 */

// Show errors for debugging
ini_set('display_errors', 1);
error_reporting(E_ALL);

header('Content-Type: text/plain');

echo "=== STRIPE DEBUG ===\n\n";

// Check PHP version
echo "PHP Version: " . PHP_VERSION . "\n\n";

// Check if env file exists
$envPath = __DIR__ . '/../.env';
echo ".env file exists: " . (file_exists($envPath) ? 'YES' : 'NO') . "\n";

// Try to load env manually
if (file_exists($envPath)) {
    $envContent = file_get_contents($envPath);
    $lines = explode("\n", $envContent);

    echo "\n=== ENV FILE KEYS ===\n";
    foreach ($lines as $line) {
        $line = trim($line);
        if (empty($line) || $line[0] === '#') continue;

        if (strpos($line, '=') !== false) {
            list($key, $value) = explode('=', $line, 2);
            $key = trim($key);
            $value = trim($value);

            // Mask sensitive values
            if (stripos($key, 'KEY') !== false || stripos($key, 'SECRET') !== false || stripos($key, 'PASS') !== false) {
                if (strlen($value) > 10) {
                    $value = substr($value, 0, 7) . '...' . substr($value, -4) . ' (length: ' . strlen($value) . ')';
                } else {
                    $value = 'TOO_SHORT or EMPTY';
                }
            }
            echo "$key = $value\n";
        }
    }
}

// Check vendor
echo "\n=== VENDOR ===\n";
echo "vendor/autoload.php exists: " . (file_exists(__DIR__ . '/../vendor/autoload.php') ? 'YES' : 'NO') . "\n";

// Check CA cert
echo "includes/cacert.pem exists: " . (file_exists(__DIR__ . '/../includes/cacert.pem') ? 'YES' : 'NO') . "\n";

// Try Stripe connection
echo "\n=== STRIPE TEST ===\n";

// Get key from env
$secretKey = '';
if (file_exists($envPath)) {
    $envContent = file_get_contents($envPath);
    if (preg_match('/STRIPE_SECRET_KEY\s*=\s*(.+)/', $envContent, $matches)) {
        $secretKey = trim($matches[1]);
    }
}

if (!empty($secretKey)) {
    try {
        require_once __DIR__ . '/../vendor/autoload.php';

        \Stripe\Stripe::setApiKey($secretKey);

        // Set CA bundle
        $caBundlePath = __DIR__ . '/../includes/cacert.pem';
        if (file_exists($caBundlePath)) {
            \Stripe\Stripe::setCABundlePath($caBundlePath);
        }

        // Test 1: Try to create a minimal payment intent
        echo "Testing PaymentIntent creation...\n";
        $paymentIntent = \Stripe\PaymentIntent::create([
            'amount' => 100,
            'currency' => 'gbp',
            'description' => 'Test - will be cancelled',
        ]);

        echo "SUCCESS! PaymentIntent created: " . $paymentIntent->id . "\n";
        echo "Client Secret exists: " . (!empty($paymentIntent->client_secret) ? 'YES' : 'NO') . "\n";

        // Cancel the test payment intent
        $paymentIntent->cancel();
        echo "Test PaymentIntent cancelled.\n";

    } catch (\Stripe\Exception\AuthenticationException $e) {
        echo "AUTH ERROR: " . $e->getMessage() . "\n";
    } catch (\Stripe\Exception\PermissionException $e) {
        echo "PERMISSION ERROR: " . $e->getMessage() . "\n";
    } catch (\Stripe\Exception\InvalidRequestException $e) {
        echo "INVALID REQUEST: " . $e->getMessage() . "\n";
    } catch (\Stripe\Exception\ApiConnectionException $e) {
        echo "CONNECTION ERROR: " . $e->getMessage() . "\n";
    } catch (Exception $e) {
        echo "ERROR: " . get_class($e) . " - " . $e->getMessage() . "\n";
    }
} else {
    echo "No secret key found in env\n";
}

echo "\n=== DONE ===\n";
